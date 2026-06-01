<?php 
set_time_limit(600);
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 3000);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config.php';
require_once 'Auth.php';
require_once 'TaskManager.php';
require_once 'process_character_creation.php';

set_time_limit(0);
ini_set('memory_limit', '1G');

$resultsDir = __DIR__ . '/results';
if (!is_dir($resultsDir)) {
    if (!mkdir($resultsDir, 0755, true)) {
        echo json_encode(['error' => '无法创建结果目录']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'generate_three_view') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $characterId = $data['character_id'] ?? 0;
        $prompt = $data['prompt'] ?? null;
        
        if (empty($characterId)) {
            echo json_encode(['error' => '角色ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT id, user_id, name, three_view_prompt FROM characters WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            echo json_encode(['error' => '角色不存在']);
            exit;
        }
        
        if ($character['user_id'] != $userId) {
            echo json_encode(['error' => '您没有权限操作该角色']);
            exit;
        }
        
        if (empty($character['three_view_prompt']) && empty($prompt)) {
            echo json_encode(['error' => '该角色没有三视图提示词']);
            exit;
        }
        
        $finalPrompt = $prompt ?? $character['three_view_prompt'];
        
        if (!empty($prompt)) {
            $updateSql = "UPDATE characters SET three_view_prompt = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute([$prompt, $characterId]);
        }
        
        $requiredPoints = Config::IMAGE_GENERATION_COST;
        
        if (!$auth->checkUserPoints($userId, $requiredPoints)) {
            echo json_encode(['error' => "积分不足，生成三视图需要 {$requiredPoints} 积分"]);
            exit;
        }
        
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '角色三视图生成', 'character_three_view', $characterId);
        if (!$deductResult['success']) {
            echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
            exit;
        }
        
        // 从数据库获取用户等级
        $sql = "SELECT level FROM users WHERE id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $apiUserId = $userId;
        // 如果用户等级为2，使用特殊用户ID的API配置
        if ($userInfo && $userInfo['level'] == 2) {
            $apiUserId = 665588567;
        }
        
        $sql = "SELECT 
                    text2img_api_key,
                    text2img_api_url
                FROM api_keys 
                WHERE user_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$apiUserId]);
        $apiConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $text2imgApiUrl = $apiConfig['text2img_api_url'] ?? null;
        $text2imgApiKey = $apiConfig['text2img_api_key'] ?? null;
        
        $logFile = __DIR__ . '/process_character_creation.log';
        $imageUrl = generateThreeViewImage($finalPrompt, $logFile, $text2imgApiUrl, $text2imgApiKey);
        
        if ($imageUrl) {
            $sql = "UPDATE characters SET three_view_image = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$imageUrl, $characterId]);
            
            // 检查是否所有角色的三视图都已生成完成
            $sql = "SELECT task_id FROM characters WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$characterId]);
            $characterInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            $taskId = $characterInfo['task_id'];
            
            $sql = "SELECT COUNT(*) as total, SUM(CASE WHEN three_view_image IS NOT NULL AND three_view_image != '' THEN 1 ELSE 0 END) as completed
                   FROM characters WHERE task_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$taskId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 如果所有角色的三视图都已生成完成，更新任务状态为完成
            if ($result['total'] > 0 && $result['completed'] == $result['total']) {
                require_once __DIR__ . '/TaskManager.php';
                $taskManager = TaskManager::getInstance();
                $taskManager->updateTaskProgress($taskId, 100, '角色创作完成');
                $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_COMPLETED, 100);
            }
            
            echo json_encode(['success' => true, 'image_url' => $imageUrl]);
        } else {
            echo json_encode(['error' => '三视图生成失败']);
        }
        exit;
    }
    
    if ($action === 'edit_character') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $characterId = $data['character_id'] ?? 0;
        
        if (empty($characterId)) {
            echo json_encode(['error' => '角色ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT id, user_id FROM characters WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            echo json_encode(['error' => '角色不存在']);
            exit;
        }
        
        if ($character['user_id'] != $userId) {
            echo json_encode(['error' => '您没有权限操作该角色']);
            exit;
        }
        
        $sql = "UPDATE characters SET name = ?, gender = ?, age = ?, clothing_description = ?, makeup = ?, character_design = ?, three_view_prompt = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'] ?? '',
            $data['gender'] ?? '',
            $data['age'] ?? '',
            $data['clothing'] ?? '',
            $data['makeup'] ?? '',
            $data['character_design'] ?? '',
            $data['three_view_prompt'] ?? '',
            $characterId
        ]);
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    if ($action === 'delete_character') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $characterId = $data['character_id'] ?? 0;
        
        if (empty($characterId)) {
            echo json_encode(['error' => '角色ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT id, user_id FROM characters WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$characterId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            echo json_encode(['error' => '角色不存在']);
            exit;
        }
        
        if ($character['user_id'] != $userId) {
            echo json_encode(['error' => '您没有权限操作该角色']);
            exit;
        }
        
        $sql = "DELETE FROM characters WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$characterId]);
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    if (isset($_GET['task_id'])) {
        echo json_encode(['error' => '创建任务时不应使用task_id参数']);
        exit;
    }
    
    $input = file_get_contents('php://input');
    
    if (strlen($input) > 10 * 1024 * 1024) {
        echo json_encode(['error' => '剧本文件过大，请压缩至10MB以内']);
        exit;
    }
    
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
        exit;
    }

    $script = $data['script'] ?? '';
    
    if (empty($script)) {
        echo json_encode(['error' => '剧本内容不能为空']);
        exit;
    }
    
    if (strlen($script) > 5 * 1024 * 1024) {
        echo json_encode(['error' => '剧本内容过大，请压缩至5MB以内']);
        exit;
    }

    $taskId = uniqid('character_analysis_', true);
    $resultFile = $resultsDir . '/' . $taskId . '.json';
    
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    $requiredPoints = Config::CHARACTER_CREATION_COST;
    
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        echo json_encode(['error' => "积分不足，无法进行角色创作操作。需要 {$requiredPoints} 积分，当前积分不足"]);
        exit;
    }
    
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '角色创作', 'character_creation', $taskId);
    if (!$deductResult['success']) {
        echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
        exit;
    }
    
    $scriptPreview = mb_strlen($script, 'UTF-8') > 500 
        ? mb_substr($script, 0, 500, 'UTF-8') . '...' 
        : $script;

    $initialResult = [
        'task_id' => $taskId,
        'status' => 'processing',
        'current_stage' => 'extracting_characters',
        'progress' => 5,
        'start_time' => date('Y-m-d H:i:s'),
        'script_preview' => $scriptPreview,
        'characters' => [],
        'logs' => [],
        'message' => '开始提取角色信息...'
    ];
    
    $jsonResult = json_encode($initialResult, JSON_UNESCAPED_UNICODE);
    if ($jsonResult === false) {
        echo json_encode(['error' => 'JSON编码错误: ' . json_last_error_msg()]);
        exit;
    }
    
    $writeResult = file_put_contents($resultFile, $jsonResult, LOCK_EX);
    if ($writeResult === false) {
        error_log("无法写入任务文件: {$resultFile}");
        echo json_encode(['error' => '无法创建任务文件，请检查服务器权限']);
        exit;
    }
    
    $taskParams = [
        'task_id' => $taskId,
        'script' => $script,
        'user_id' => $userId
    ];
    
    $taskParamsFile = $resultsDir . '/' . $taskId . '_params.json';
    
    if (file_put_contents($taskParamsFile, json_encode($taskParams)) === false) {
        error_log("无法写入任务参数文件: {$taskParamsFile}");
    }
    
    require_once __DIR__ . '/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $taskManager = TaskManager::getInstance();
    
    $inputData = [
        'script_length' => mb_strlen($script, 'UTF-8'),
        'task_type' => 'character_creation',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $safeUserId = $userId !== null ? $userId : 1;
    
    $dbTaskId = $taskManager->createTask(
        $safeUserId,
        'character_creation',
        '角色创作',
        $inputData,
        [],
        $taskId
    );
    
    $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, 0);
    
    try {
        $sql = "SELECT id, current_task_id FROM crew WHERE admin_user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$safeUserId]);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($crew) {
            $sql = "UPDATE crew SET current_task_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$dbTaskId, $crew['id']]);
            $crewId = $crew['id'];
        } else {
            $sql = "INSERT INTO crew (admin_user_id, name, status, current_task_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $defaultCrewName = "{$safeUserId}的默认剧组";
            $stmt->execute([$safeUserId, $defaultCrewName, 1, $dbTaskId]);
            $crewId = $pdo->lastInsertId();
        }
    } catch (Exception $e) {
        error_log("处理剧组记录时出错: " . $e->getMessage());
    }
    
    echo json_encode([
        'task_id' => $taskId,
        'status' => 'processing',
        'message' => '角色创作任务已开始，请稍后查询结果'
    ], JSON_UNESCAPED_UNICODE);
    
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
    
    if (session_id()) {
        session_write_close();
    }
    
    ignore_user_abort(true);
    set_time_limit(0);
    
    $command = sprintf('cd %s && php %s %s', 
        escapeshellarg(__DIR__),
        escapeshellarg('process_character_creation.php'),
        escapeshellarg($taskParamsFile)
    );
    
    $processStarted = false;
    if (function_exists('shell_exec')) {
        shell_exec($command . ' > /dev/null 2>&1 &');
        error_log("后台进程已启动 (shell_exec)");
        $processStarted = true;
    } elseif (function_exists('proc_open')) {
        $descriptorspec = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];
        
        $process = proc_open($command . ' > /dev/null 2>&1 &', $descriptorspec, $pipes);
        
        if (is_resource($process)) {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
            error_log("后台进程已启动 (proc_open)");
            $processStarted = true;
        } else {
            error_log("后台进程启动失败: proc_open failed");
        }
    }
    
    if (!$processStarted) {
        error_log("后台进程启动失败: exec/proc_open/shell_exec都被禁用，尝试直接执行");
        $oldDir = getcwd();
        chdir(__DIR__);
        $_SERVER['argv'] = ['process_character_creation.php', $taskParamsFile];
        $_SERVER['argc'] = 2;
        $logFile = __DIR__ . '/process_character_creation.log';
        processCharacterCreation($taskParamsFile);
        chdir($oldDir);
        exit(0);
    }
    
    exit(0);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'history') {
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        // 检查用户是否已登录
        if (!$userId) {
            echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT t.task_id, t.status, t.created_at, 
                (SELECT COUNT(*) FROM characters c WHERE c.task_id = t.task_id) as character_count
                FROM tasks t
                WHERE t.user_id = ? AND t.task_type = 'character_creation'
                ORDER BY t.created_at DESC
                LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $formattedTasks = [];
        foreach ($tasks as $task) {
            $statusMap = [
                TaskManager::STATUS_PENDING => 'processing',
                TaskManager::STATUS_PROCESSING => 'processing',
                TaskManager::STATUS_COMPLETED => 'completed',
                TaskManager::STATUS_FAILED => 'error',
                TaskManager::STATUS_CANCELLED => 'error'
            ];
            
            $formattedTasks[] = [
                'task_id' => $task['task_id'],
                'status' => $statusMap[$task['status']] ?? 'processing',
                'created_at' => $task['created_at'],
                'character_count' => (int)$task['character_count']
            ];
        }
        
        echo json_encode(['tasks' => $formattedTasks], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($action === 'get_character') {
        // 支持id和character_id两种参数名
        $characterId = $_GET['id'] ?? $_GET['character_id'] ?? 0;
        
        if (empty($characterId)) {
            echo json_encode(['error' => '角色ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 选择所有字段，确保包含three_view_image
        $sql = "SELECT * FROM characters WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$characterId, $userId]);
        $character = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$character) {
            echo json_encode(['error' => '未找到角色信息']);
            exit;
        }
        
        echo json_encode(['character' => $character], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($action === 'get_current_task') {
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        if (!$userId) {
            echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 查询当前用户的剧组
        $sql = "SELECT id, current_task_id FROM crew WHERE admin_user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$crew) {
            echo json_encode(['error' => '未找到剧组信息']);
            exit;
        }
        
        if (empty($crew['current_task_id'])) {
            echo json_encode(['error' => '暂无当前角色任务，请先创建角色分析任务']);
            exit;
        }
        
        echo json_encode(['task_id' => $crew['current_task_id']], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $taskId = $_GET['task_id'] ?? '';
    
    if (empty($taskId)) {
        echo json_encode(['error' => '任务ID不能为空']);
        exit;
    }

    if (!preg_match('/^character_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
        echo json_encode(['error' => '无效的任务ID']);
        exit;
    }

    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    // 检查用户是否已登录
    if (!$userId) {
        echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $sql = "SELECT user_id FROM tasks WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'error',
            'message' => '任务不存在，可能任务已被删除或从未创建',
            'error' => 'Task not found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($task['user_id'] != $userId) {
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'error',
            'message' => '您没有权限访问该任务',
            'error' => 'Permission denied'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 从数据库中查询最新的角色数据，包括three_view_image，添加用户ID过滤
    $sql = "SELECT id, character_number, name, gender, age, clothing_description, makeup_description, character_design, three_view_prompt, three_view_image FROM characters WHERE task_id = ? AND user_id = ? ORDER BY character_number";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId, $userId]);
    $characters = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 检查所有角色的三视图是否都已经生成完成
    $allThreeViewsGenerated = true;
    foreach ($characters as $char) {
        // 只有有三视图提示词的角色才需要检查是否生成了图片
        if (!empty($char['three_view_prompt']) && empty($char['three_view_image'])) {
            $allThreeViewsGenerated = false;
            break;
        }
    }
    
    // 检查任务状态
    $sql = "SELECT status, progress FROM tasks WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    $taskStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 将status转换为整数，确保比较正确
    $taskStatus['status'] = (int)$taskStatus['status'];
    
    // 如果所有角色的三视图都已经生成完成，强制将任务状态更新为完成
    if ($allThreeViewsGenerated && $taskStatus['status'] != TaskManager::STATUS_COMPLETED) {
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = TaskManager::getInstance();
        $taskManager->updateTaskProgress($taskId, 100, '角色创作完成');
        $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_COMPLETED, 100);
        $taskStatus['status'] = TaskManager::STATUS_COMPLETED;
    }
    
    $status = $taskStatus['status'] == TaskManager::STATUS_COMPLETED ? 'completed' : 
              ($taskStatus['status'] == TaskManager::STATUS_FAILED ? 'error' : 'processing');
    $progress = $taskStatus['progress'] ?? 100;
    $message = $status == 'completed' ? '角色创作完成' : 
              ($status == 'error' ? '角色创作失败' : '任务正在处理中');
    
    // 构建响应数据
    $response = [
        'task_id' => $taskId,
        'status' => $status,
        'progress' => $progress,
        'message' => $message,
        'current_stage' => $status,
        'characters' => $characters,
        'logs' => []
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $taskId = $_GET['task_id'] ?? '';
    $deleteAll = isset($_GET['delete_all']) && $_GET['delete_all'] === 'true';
    
    if ($deleteAll) {
        $sql = "SELECT task_id FROM tasks WHERE user_id = ? AND task_type = 'character_creation'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedCount = 0;
        foreach ($tasks as $task) {
            $taskTaskId = $task['task_id'];
            
            $deleteStmt = $pdo->prepare("DELETE FROM characters WHERE task_id = ?");
            $deleteStmt->execute([$taskTaskId]);
            
            $deleteStmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ? AND user_id = ?");
            $deleteStmt->execute([$taskTaskId, $userId]);
            
            $resultFile = $resultsDir . '/' . $taskTaskId . '.json';
            if (file_exists($resultFile)) {
                unlink($resultFile);
            }
            
            $taskParamsFile = $resultsDir . '/' . $taskTaskId . '_params.json';
            if (file_exists($taskParamsFile)) {
                unlink($taskParamsFile);
            }
            
            $deletedCount++;
        }
        
        echo json_encode(['success' => true, 'deleted_count' => $deletedCount], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if (empty($taskId)) {
        echo json_encode(['error' => '任务ID不能为空']);
        exit;
    }
    
    if (!preg_match('/^character_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
        echo json_encode(['error' => '无效的任务ID']);
        exit;
    }
    
    $sql = "SELECT user_id FROM tasks WHERE task_id = ? AND task_type = 'character_creation'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    $task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$task) {
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'error',
            'message' => '任务不存在',
            'error' => 'Task not found'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    if ($task['user_id'] != $userId) {
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'error',
            'message' => '您没有权限删除该任务',
            'error' => 'Permission denied'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    $sql = "DELETE FROM characters WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    
    $sql = "DELETE FROM tasks WHERE task_id = ? AND user_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId, $userId]);
    
    $resultFile = $resultsDir . '/' . $taskId . '.json';
    if (file_exists($resultFile)) {
        unlink($resultFile);
    }
    
    $taskParamsFile = $resultsDir . '/' . $taskId . '_params.json';
    if (file_exists($taskParamsFile)) {
        unlink($taskParamsFile);
    }
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['error' => '不支持的请求方法']);
}
?>
