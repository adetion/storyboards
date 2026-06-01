<?php 
set_time_limit(600);
ini_set('memory_limit', '1G');
ini_set('max_execution_time', 3000);

// 加载核心依赖，无论在何种环境下
require_once 'config.php';
require_once 'Auth.php';
require_once 'TaskManager.php';

/**
 * 安全地记录日志到文件
 */
function logToFile($logFile, $message) {
    try {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        // 确保日志目录存在
        $logDir = dirname($logFile);
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        // 限制日志文件大小（例如10MB）
        if (file_exists($logFile) && filesize($logFile) > 10 * 1024 * 1024) {
            // 备份旧日志
            $backupFile = $logFile . '.' . date('Ymd');
            rename($logFile, $backupFile);
        }
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    } catch (Exception $e) {
        // 如果日志写入失败，静默处理
        error_log("日志写入失败: " . $e->getMessage());
    }
}

/**
 * 拆分剧本为多个块（优化大文件处理）
 */
function splitScriptIntoChunks($script, $maxChunkSize = 1500) {
    // 如果剧本不长，直接返回
    if (mb_strlen($script, 'UTF-8') <= $maxChunkSize) {
        return [$script];
    }
    
    $chunks = [];
    
    // 按场景拆分（支持中文场景标记）
    $scenePattern = '/(?:^|\n)(?:场景|第[一二三四五六七八九十零百千万]+场|场次|【|SCENE|ACT)/ui';
    $scenes = preg_split($scenePattern, $script);
    
    // 保留分隔符
    preg_match_all($scenePattern, $script, $matches, PREG_OFFSET_CAPTURE);
    
    $currentChunk = '';
    $sceneIndex = 0;
    
    foreach ($scenes as $index => $scene) {
        if ($index === 0 && empty(trim($scene))) {
            continue;
        }
        
        // 添加回分隔符
        if ($index > 0 && isset($matches[0][$index-1])) {
            $scene = $matches[0][$index-1][0] . $scene;
        }
        
        $sceneLength = mb_strlen($scene, 'UTF-8');
        
        // 如果单个场景就很大，需要进一步拆分
        if ($sceneLength > $maxChunkSize) {
            $subChunks = splitLargeScene($scene, $maxChunkSize);
            foreach ($subChunks as $subChunk) {
                if (mb_strlen($currentChunk . $subChunk, 'UTF-8') > $maxChunkSize && !empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = $subChunk;
                } else {
                    $currentChunk .= $subChunk;
                }
            }
        } else {
            if (mb_strlen($currentChunk . $scene, 'UTF-8') > $maxChunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = $scene;
            } else {
                $currentChunk .= $scene;
            }
        }
    }
    
    if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
    }
    
    return $chunks;
}

/**
 * 拆分大场景
 */
function splitLargeScene($scene, $maxChunkSize) {
    $subChunks = [];
    $lines = preg_split('/\r\n|\r|\n/', $scene);
    
    $currentSubChunk = '';
    foreach ($lines as $line) {
        $lineLength = mb_strlen($line, 'UTF-8');
        
        if (mb_strlen($currentSubChunk . $line, 'UTF-8') > $maxChunkSize && !empty($currentSubChunk)) {
            $subChunks[] = $currentSubChunk;
            $currentSubChunk = $line . "\n";
        } else {
            $currentSubChunk .= $line . "\n";
        }
    }
    
    if (!empty($currentSubChunk)) {
        $subChunks[] = $currentSubChunk;
    }
    
    return $subChunks;
}

/**
 * 异步处理分镜数据
 */
function asyncProcessStoryboard($taskId, $resultFile, $scriptPath, $logFile) {
    // 记录开始日志
    logToFile($logFile, "开始异步处理分镜数据，任务ID: {$taskId}");
    
    // 使用register_shutdown_function在脚本结束后执行
    register_shutdown_function(function() use ($taskId, $resultFile, $scriptPath, $logFile) {
        try {
            logToFile($logFile, "后台进程启动，处理任务ID: {$taskId}");
            
            // 验证文件存在
            if (!file_exists($resultFile)) {
                logToFile($logFile, "错误: 结果文件不存在 - {$resultFile}");
                return;
            }
            
            // 包含处理脚本
            if (!file_exists($scriptPath)) {
                logToFile($logFile, "错误: 脚本文件不存在 - {$scriptPath}");
                return;
            }
            
            require_once $scriptPath;
            
            // 读取并解析JSON
            $jsonContent = file_get_contents($resultFile);
            logToFile($logFile, "读取结果文件成功，大小: " . strlen($jsonContent) . " 字节");
            
            $finalResult = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                logToFile($logFile, "JSON解析失败: " . json_last_error_msg());
                return;
            }
            
            if (!isset($finalResult['content'])) {
                logToFile($logFile, "错误: JSON缺少content字段");
                return;
            }
            
            logToFile($logFile, "JSON解析成功，开始保存分镜数据");
            
            // 调用处理函数
            processAndSaveStoryboards($taskId, $finalResult['content']);
            
            logToFile($logFile, "分镜保存成功，任务ID: {$taskId}");
            
        } catch (Exception $e) {
            logToFile($logFile, "分镜保存失败: " . $e->getMessage());
            logToFile($logFile, "错误堆栈: " . $e->getTraceAsString());
        }
    });
    
    // 快速响应客户端，让处理在后台进行
    // 设置一些配置确保后台处理能正确运行
    ignore_user_abort(true);
    session_write_close();
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request(); // 如果使用FastCGI
    }
    
    return true;
}

/**
 * 快速同步处理（对于小数据量）
 */
function quickProcessStoryboard($taskId, $resultFile, $scriptPath, $logFile) {
    // 只处理小文件，避免超时
    if (!file_exists($resultFile) || filesize($resultFile) > 1048576) { // > 1MB
        return false;
    }
    
    logToFile($logFile, "尝试快速同步处理，任务ID: {$taskId}");
    
    require_once $scriptPath;
    
    $jsonContent = file_get_contents($resultFile);
    $finalResult = json_decode($jsonContent, true);
    
    if (!$finalResult || !isset($finalResult['content'])) {
        return false;
    }
    
    // 检查content是否过大
    if (strlen($finalResult['content']) > 100000) { // > 100KB
        return false;
    }
    
    // 快速处理
    processAndSaveStoryboards($taskId, $finalResult['content']);
    logToFile($logFile, "快速同步处理成功，任务ID: {$taskId}");
    
    return true;
}

/**
 * 备选方案：使用popen纯PHP后台进程
 */
function asyncProcessWithPopen($taskId, $resultFile, $scriptPath, $logFile) {
    if (!function_exists('popen')) {
        return false;
    }
    
    // 构建要执行的PHP代码
    $phpCode = sprintf('<?php
        // 设置错误处理
        ini_set("display_errors", 0);
        ini_set("log_errors", 1);
        ini_set("error_log", %s);
        
        try {
            // 包含必要文件
            require_once %s;
            
            $taskId = %s;
            $resultFile = %s;
            $logFile = %s;
            
            // 记录开始
            file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] 后台进程启动，任务ID: $taskId\n", FILE_APPEND);
            
            if (!file_exists($resultFile)) {
                throw new Exception("结果文件不存在: " . $resultFile);
            }
            
            $jsonContent = file_get_contents($resultFile);
            $finalResult = json_decode($jsonContent, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception("JSON解析失败: " . json_last_error_msg());
            }
            
            if (!isset($finalResult["content"])) {
                throw new Exception("JSON缺少content字段");
            }
            
            // 处理分镜数据
            processAndSaveStoryboards($taskId, $finalResult["content"]);
            
            file_put_contents($logFile, "[" . date("Y-m-d H:i:s") . "] 分镜保存成功，任务ID: $taskId\n", FILE_APPEND);
            
        } catch (Exception $e) {
            $errorMsg = "[" . date("Y-m-d H:i:s") . "] 分镜保存失败: " . $e->getMessage() . "\n";
            $errorMsg .= "堆栈: " . $e->getTraceAsString() . "\n";
            file_put_contents($logFile, $errorMsg, FILE_APPEND);
        }
    ?>',
    var_export($logFile, true),
    var_export($scriptPath, true),
    var_export($taskId, true),
    var_export($resultFile, true),
    var_export($logFile, true)
    );
    
    // 创建临时PHP文件
    $tempFile = tempnam(sys_get_temp_dir(), 'storyboard_');
    file_put_contents($tempFile, $phpCode);
    
    // 使用popen在后台执行
    $command = sprintf('php %s > /dev/null 2>&1 &', escapeshellarg($tempFile));
    $handle = popen($command, 'r');
    
    if (is_resource($handle)) {
        pclose($handle);
        
        // 延迟删除临时文件
        register_shutdown_function(function() use ($tempFile) {
            if (file_exists($tempFile)) {
                @unlink($tempFile);
            }
        });
        
        return true;
    }
    
    // 清理临时文件
    if (file_exists($tempFile)) {
        @unlink($tempFile);
    }
    
    return false;
}

// 检查是否在CLI环境下运行
if (php_sapi_name() === 'cli') {
    // CLI环境下，不执行HTTP请求处理逻辑
    // 核心函数定义将在后面加载，不需要直接返回
} else {
    // HTTP环境下的处理
    // 启动会话 - 必须在任何输出之前调用
    session_start();

    // 彻底清理缓冲区
    while (ob_get_level()) ob_end_clean();

    header('Content-Type: application/json');
    header('Connection: close');

    // 确保结果目录存在 - 定义在所有请求处理之前
    $resultsDir = __DIR__ . '/results';
    if (!is_dir($resultsDir)) {
        if (!mkdir($resultsDir, 0755, true)) {
            echo json_encode(['error' => '无法创建结果目录']);
            exit;
        }
    }

    // 处理预检请求 
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        exit(0);
    }

    // 注意：scripts_api.php支持两种请求方法：
    // 1. POST请求：用于创建新的剧本分析任务（不带task_id参数）
    // 2. GET请求：用于查询任务状态（必须带task_id参数，格式：scripts_api.php?task_id={task_id}）
    // 重要：查询任务状态的请求必须使用GET方法，不能使用POST方法
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // GET请求用于查询任务状态，POST请求用于创建新任务
        // 因此POST请求不应该包含task_id参数
        if (isset($_GET['task_id'])) {
            echo json_encode(['error' => '创建任务时不应使用task_id参数']);
            exit;
        }
        
        // 获取POST数据 
        $input = file_get_contents('php://input');
        
        // 检查输入大小
        if (strlen($input) > 10 * 1024 * 1024) { // 10MB限制
            echo json_encode(['error' => '剧本文件过大，请压缩至10MB以内']);
            exit;
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }

        $script = $data['script'] ?? '';
        $prompt = $data['prompt'] ?? '分场次（场次不要合并）提炼出各自场次的能分拆出的所有分镜（最少1个，最多9个，且各场次的分镜不允许合并分析及处理），按场次号及分镜号顺序全部列出所有分镜，每个分镜（故事板）必须包含：排序号、场次号、镜号、地点（场景地）、时间（从"日、夜、晨、暮、黄昏、黎明"综合判断）、天气（预估分镜剧情的天气，从"晴、阴、雨、雾、雪、风、沙、霾"综合判断）、参考画面（推荐的文生图提示词，要尽可能详细且符合专业摄影手法，包括运镜策略，必须能让文生图的AI完美理解）、景别（推荐从中选择：大远景、远景、全景、中全景、中景、中近景、特写、大特写、航拍）、时长(预估秒数)、内容（分镜剧情梗概）、剧本（属于该分镜的剧本内容全文）、台词（属于该分镜的所有台词对白）、角色清单（该分镜中）、各角色推荐服装（适合该分镜剧情，要明确到底是什么服装，且要明晰属于哪个角色）、各角色推荐妆造（适合该分镜剧情，要明确到底是何种妆造如如何化妆怎么化妆什么发型发饰什么配饰等等，且要明晰属于哪个角色）、（演员）角色动作（要考虑每个角色在当前分镜中的动作表现，包括行走、对话、表情等，且要明晰属于哪个角色）、道具（属于该分镜的所有道具）、场景预期（推荐选景置景相关）、声音设计（推荐音效等，如环境音、背景音等相关描述）、摄像机角度（推荐拍摄角度）、构图与焦点（三分法、对称构图、景深、焦点转移等）、运镜（推荐专业的运镜手法和方式，运镜包含：正面、侧面、斜侧、背面、平视、仰拍、俯拍、顶拍、荷兰角、推/拉镜、摇镜、移镜、升降镜、主观视角、客观视角、过肩、窥视、反射、微距，或以上组合）、摄像机设备（推荐设备，分别优选给出国产和国外设备，以便让用户可以二选一）、镜头焦段（推荐建议的具体焦段）、光线与色调(光源方向：顺光、侧光、逆光、顶光等；影调：高调、低调、对比度；色调：冷色、暖色、单色调)。';
        
        if (empty($script)) {
            echo json_encode(['error' => '剧本内容不能为空']);
            exit;
        }

        // 检查剧本内容大小
        if (strlen($script) > 5 * 1024 * 1024) { // 5MB剧本内容限制
            echo json_encode(['error' => '剧本内容过大，请压缩至5MB以内']);
            exit;
        }

        // 生成唯一任务ID 
        $taskId = uniqid('script_analysis_', true);
        $resultFile = $resultsDir . '/' . $taskId . '.json';
        
        $scriptChunks = splitScriptIntoChunks($script);
            
        $maxRounds = min(99, count($scriptChunks) + 2);
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        
        // 检查用户积分是否足够
        $requiredPoints = Config::SCRIPT_TO_STORYBOARD_COST * $maxRounds;
        
        // 检查积分是否足够
        if (!$auth->checkUserPoints($userId, $requiredPoints)) {
            echo json_encode(['error' => "积分不足，无法进行剧本转分镜操作。需要 {$requiredPoints} 积分，当前积分不足"]);
            exit;
        }
        
        // 扣除积分，并传递taskId
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '剧本转分镜', 'script_to_storyboard', $taskId);
        if (!$deductResult['success']) {
            echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
            exit;
        }
        
        

        // 安全截取剧本预览（处理中文字符）
        $scriptPreview = mb_strlen($script, 'UTF-8') > 500 
            ? mb_substr($script, 0, 500, 'UTF-8') . '...' 
            : $script;

        // 立即创建任务状态文件（优化大文件处理）
        // 计算初始进度：current_round / total_rounds * 100
        $initialProgress = round((1 / $maxRounds) * 100, 2);
        $initialResult = [
            'task_id' => $taskId,
            'status' => 'processing',
            'current_round' => 1,
            'total_rounds' => $maxRounds,
            'progress' => $initialProgress,
            'start_time' => date('Y-m-d H:i:s'),
            'script_preview' => $scriptPreview,
            'prompt' => $prompt,
            'content' => '',
            'rounds' => $maxRounds,
            'message' => '分析任务已开始，请稍后查询结果'
        ];
        
        // 优化文件写入
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

        // 创建任务参数文件
        $taskParams = [
            'task_id' => $taskId,
            'script' => $script,
            'prompt' => $prompt,
            'script_chunks' => $scriptChunks,
            'max_rounds' => $maxRounds,
            'user_id' => $userId
        ];
        
        $taskParamsFile = $resultsDir . '/' . $taskId . '_params.json';
        
        // 检查文件是否成功写入
        if (file_put_contents($taskParamsFile, json_encode($taskParams)) === false) {
            error_log("无法写入任务参数文件: {$taskParamsFile}");
            echo json_encode(['error' => '无法创建任务参数文件']);
            exit(0);
        }
        

        // 立即创建任务记录到数据库，确保tasks表中有任务记录
        require_once __DIR__ . '/TaskManager.php';
        require_once __DIR__ . '/Database.php';
        
        $taskManager = TaskManager::getInstance();
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 无论用户是否登录，都创建任务，使用外部taskId作为核心task_id
        $scriptLength = mb_strlen($script, 'UTF-8');
        $inputData = [
            'script_length' => (int)$scriptLength,
            'max_rounds' => (int)$maxRounds,
            'task_type' => 'script_to_storyboard',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // 确保userId不为null，使用默认值1
        $safeUserId = $userId !== null ? $userId : 1;
        
        // 创建任务记录
        $dbTaskId = $taskManager->createTask(
            $safeUserId,
            TaskManager::TYPE_SCRIPT_TO_STORYBOARD,
            '剧本转分镜',
            $inputData,
            [],
            $taskId
        );
        
        // 更新任务状态为处理中
        $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, 0);
        
        // 处理crew表的逻辑
        try {
            // 检查用户是否已有剧组
            $sql = "SELECT id, current_task_id FROM crew WHERE admin_user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$safeUserId]);
            $crew = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($crew) {
                // 用户已有剧组，更新current_task_id
                $sql = "UPDATE crew SET current_task_id = ?, updated_at = NOW() WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$dbTaskId, $crew['id']]);
                error_log("已更新用户 {$safeUserId} 的剧组 current_task_id 为 {$dbTaskId}");
            } else {
                // 用户没有剧组，创建一个默认剧组
                $sql = "INSERT INTO crew (admin_user_id, name, status, current_task_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $defaultCrewName = "{$safeUserId}的默认剧组";
                $stmt->execute([$safeUserId, $defaultCrewName, 1, $dbTaskId]);
                error_log("已为用户 {$safeUserId} 创建默认剧组，并设置 current_task_id 为 {$dbTaskId}");
            }
        } catch (Exception $e) {
            error_log("处理剧组记录时出错: " . $e->getMessage());
        }
        
        // 立即返回任务ID给前端 
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'processing',
            'message' => '分析任务已开始，请稍后查询结果'
        ]);
        
        // 确保输出发送到客户端
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // 传统方式刷新输出
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        
        // 直接调用processScriptTask函数处理任务，使用同步执行但优化输出处理
        // 这样可以确保任务正常执行，同时通过优化输出避免前台长时间等待
        
        // 确保输出发送到客户端
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // 传统方式刷新输出
            while (ob_get_level()) {
                ob_end_flush();
            }
            flush();
        }
        
        // 关闭会话，避免阻塞
        if (session_id()) {
            session_write_close();
        }
        
        // 设置后台执行环境
        ignore_user_abort(true);
        set_time_limit(0);
        
        // 直接调用processScriptTask函数处理任务
        require_once __DIR__ . '/process_script_task.php';
        
        // 开始后台处理
        processScriptTask($taskParamsFile);
        
        // 退出当前脚本
        exit(0);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // 查询任务状态必须使用GET方法，格式：scripts_api.php?task_id={task_id}
        // 重要：不支持使用POST方法查询任务状态
        $taskId = $_GET['task_id'] ?? '';
        
        if (empty($taskId)) {
            echo json_encode(['error' => '任务ID不能为空']);
            exit;
        }

        // 安全检查：确保task_id只包含允许的字符
        if (!preg_match('/^script_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
            echo json_encode(['error' => '无效的任务ID']);
            exit;
        }

        // 检查用户权限 - 确保当前用户只能访问自己的任务
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        // 从数据库中检查任务的所有者
        require_once __DIR__ . '/TaskManager.php';
        require_once __DIR__ . '/Database.php';
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 查询任务的所有者
        $sql = "SELECT user_id FROM tasks WHERE task_id = :task_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $stmt->execute();
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            // 任务不存在
            echo json_encode([
                'task_id' => $taskId,
                'status' => 'error',
                'message' => '任务不存在，可能任务已被删除或从未创建',
                'error' => 'Task not found'
            ]);
            exit;
        }
        
        // 检查用户是否有权限访问该任务（只允许任务所有者访问）
        if ($task['user_id'] != $userId) {
            echo json_encode([
                'task_id' => $taskId,
                'status' => 'error',
                'message' => '您没有权限访问该任务',
                'error' => 'Permission denied'
            ]);
            exit;
        }

        // 使用正确的文件名格式（与background process一致）
        $resultFile = $resultsDir . '/' . $taskId . '.json';

        if (file_exists($resultFile)) {
            // 优化大文件读取
            $result = file_get_contents($resultFile);
            if ($result === false) {
                echo json_encode(['error' => '无法读取任务结果']);
            } else {
                echo $result;
            }
        } else {
            // 任务文件不存在，返回error状态
            echo json_encode([
                'task_id' => $taskId,
                'status' => 'error',
                'message' => '任务文件不存在，可能任务已被删除或从未创建',
                'error' => 'Task file not found'
            ]);
        }
    } else {
        echo json_encode(['error' => '不支持的请求方法']);
    }
}

/**
 * 处理剧本分析任务（增强版，解决分镜截断问题）此函数并未使用，整个处理流程已经移至process_script_task.php
 */
function processScriptAnalysis($taskId, $script, $prompt, $scriptChunks, $maxRounds) {
    // Use consistent filename with the initial progress file
    $resultFile = __DIR__ . '/results/' . $taskId . '_storyboard.json';
    $resultFile_storyboards = __DIR__ . '/results/' . $taskId . '_storyboards.json';

    // API密钥
    $apiKey = Config::DEEPSEEK_API_KEY();
    
    if (empty($apiKey)) {
        saveErrorResult($taskId, '请配置正确的DeepSeek API密钥');
        return;
    }
    // 使用TaskManager管理任务
    $taskManager = null;
    $dbTaskId = null;    
    try {
        // 优化：对于大剧本，使用更智能的拆分策略
        // $scriptChunks = splitScriptIntoChunks($script);
        
        // $maxRounds = min(99, count($scriptChunks) + 2);

        $fullResponse = '';
        $completedRounds = 0;
        $lastIncompleteShot = ''; // 存储上一个不完整的分镜
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = TaskManager::getInstance();
        $textLength = mb_strlen($script, 'UTF-8');
        $inputData = [
                'text_length' => (int)$textLength,  // 确保是整数类型
                'max_rounds' => (int)$maxRounds,    // 确保是整数类型
                'task_type' => 'script_to_storyboard',   // 简单字符串
                'content' => mb_substr($script, 0, 500, 'UTF-8'),
                'created_at' => date('Y-m-d H:i:s') // 标准日期格式字符串
            ];
        $testJson = json_encode($inputData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
            
        $dbTaskId = $taskManager->createTask(
                $userId,
                TaskManager::TYPE_NOVEL_TO_SCRIPT,
                '剧本转分镜',
                $inputData,
                [],
                $taskId // 使用外部taskId作为核心task_id
            );
        // 更新任务状态为处理中
        $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, 0);
        
        // 系统提示词优化，特别强调分镜完整性
        $systemPrompt = "你是一个专业的剧本分析师。请详细分析用户提供的剧本内容，根据用户的提示进行深入分析。由于剧本较长，请分部分进行分析。\n\n重要要求：\n1. 每个分镜分析必须完整，包含所有要求的字段\n2. 如果分析被中断，请在下一轮继续完成当前分镜\n3. 严格按照如下格式给出回复：| 排序号 | 场次号 | 镜号 | 地点 | 时间 | 天气 | 参考画面 | 景别 | 时长(秒) | 内容 | 剧本 | 台词 | 角色清单 | 各角色推荐服装 | 各角色推荐妆造 | 角色动作 | 道具 | 场景预期 | 声音设计 | 摄像机角度 | 构图与焦点 | 运镜 | 摄像机设备 | 镜头焦段 | 光线与色调 |\n4. 多余的无关的话不要说。";
        
        $optimizedPrompt = optimizePrompt($prompt);
        
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "分析要求：{$optimizedPrompt}\n\n剧本较长，我将分部分提供。请为每个部分提供详细分析，确保每个分镜分析完整。"
            ]
        ];
        
        
        
        for ($round = 1; $round <= $maxRounds; $round++) {
            // 更新进度
            $progressResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => $round,
                'total_rounds' => $maxRounds,
                'message' => "正在进行第{$round}轮分析...",
                'content' => $fullResponse,
                'rounds' => $round - 1
            ];
            
            $jsonProgress = json_encode($progressResult, JSON_UNESCAPED_UNICODE);
            if ($jsonProgress !== false) {
                file_put_contents($resultFile, $jsonProgress, LOCK_EX);
            }
            
            // 构建当前轮次的消息
            $userMessage = buildUserMessage($round, $scriptChunks, $lastIncompleteShot);
            
            // 如果有未完成的分镜，优先处理
            if (!empty($lastIncompleteShot)) {
                $userMessage = "上一轮最后一个分镜分析不完整，请先完成这个分镜的分析：\n\n{$lastIncompleteShot}\n\n然后继续分析新的剧本内容。";
                $lastIncompleteShot = ''; // 清空，等待新的响应
            }
            
            $messages[] = ['role' => 'user', 'content' => $userMessage];
            
            // 清理过长的消息历史以节省token
            $messages = cleanupMessageHistory($messages);
            
            // 调用API
            $content = callDeepSeekAPIWithRetry($apiKey, $messages, $round);
            
            // 检查响应是否完整（关键改进）
            $isComplete = checkResponseCompleteness($content);
            
            if (!$isComplete && $round < $maxRounds) {
                // 响应不完整，保存未完成的部分用于下一轮
                $lastIncompleteShot = extractLastIncompleteShot($content);
                $content = $content . "[分析被截断，将在下一轮继续]";
            } else {
                // 响应完整，清空未完成标记
                $lastIncompleteShot = '';
            }
            
            $fullResponse .= $content . "\n\n";
            $completedRounds = $round;
            
            // 添加到对话历史
            $messages[] = ['role' => 'assistant', 'content' => $content];
            
            // 检查是否完成所有剧本块且没有未完成的分镜
            if ($round >= count($scriptChunks) && empty($lastIncompleteShot)) {
                // 所有剧本块已处理且没有未完成的分镜，可以结束
                break;
            }
            
            // 如果达到最大轮次但还有未完成的分镜，尝试最后一轮专门处理
            if ($round === $maxRounds - 1 && !empty($lastIncompleteShot)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => "请专门完成这个未完成的分镜分析：\n\n{$lastIncompleteShot}\n\n这是最后一轮，请确保分析完整。不润色、不扩写、不输出任何其他文本、说明或解释。"
                ];
            }
            
            // 添加延迟避免频繁请求
            sleep(3);
        }
        
        
        
        // 保存最终结果
        $finalResult = [
            'task_id' => $taskId,
            'status' => 'completed',
            'start_time' => date('Y-m-d H:i:s', filemtime($resultFile)),
            'end_time' => date('Y-m-d H:i:s'),
            'script_preview' => mb_strlen($script, 'UTF-8') > 500 
                ? mb_substr($script, 0, 500, 'UTF-8') . '...' 
                : $script,
            'prompt' => $prompt,
            'content' => trim($fullResponse),
            'rounds' => $completedRounds,
            'message' => '分析任务已完成'
        ];
        
        $jsonFinal = json_encode($finalResult, JSON_UNESCAPED_UNICODE);
        if ($jsonFinal !== false) {
            file_put_contents($resultFile, $jsonFinal, LOCK_EX);
        }
        
        $filepath = Config::OUTPUT_DIR . $resultFile;
        
        
        // 更新任务状态为完成到数据库
        if ($taskManager && $dbTaskId) {
            try {
                $outputData = [
                    'script_content' => mb_substr($fullResponse, 0, 500, 'UTF-8'),
                    'rounds' => $completedRounds,
                    'filename' => $resultFile,
                    'download_url' => 'download.php?file=' . $resultFile,
                    'file_size' => filesize($filepath),
                    'message' => '分析任务已完成'
                ];
                
                // 更新任务状态为已完成
                $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_COMPLETED, 100, $outputData);
                
                // 创建剧本记录
                $taskManager->createScript($dbTaskId, mb_substr($fullResponse, 0, 500, 'UTF-8'), '剧本转分镜_' . date('Y-m-d'), '系统自动生成');
            } catch (Exception $e) {
                $logger->error("Failed to update task completion status: " . $e->getMessage());
            }
        }
        
        
        //分析完成后进行分镜的正式分拆（$resultFile = __DIR__ . '/results/' . $taskId . '.json';）
        try {
            generateTargetJsonFromJsonFile($resultFile, $resultFile_storyboards);
            echo "\n转换完成！\n";
        } catch (Exception $e) {
            echo "错误: " . $e->getMessage() . "\n";
        }
    } catch (Exception $e) {
        saveErrorResult($taskId, $e->getMessage());
    }
}

/**
 * 构建用户消息
 */
function buildUserMessage($round, $scriptChunks, $lastIncompleteShot) {
    if ($round === 1) {
        $currentChunk = $scriptChunks[0] ?? '';
        $chunkPreview = mb_strlen($currentChunk, 'UTF-8') > 1000 
            ? mb_substr($currentChunk, 0, 1000, 'UTF-8') . '...' 
            : $currentChunk;
            
        return "剧本第一部分：\n{$chunkPreview}\n\n请开始分析，确保每个分镜分析完整。";
    } elseif ($round <= count($scriptChunks)) {
        $currentChunk = $scriptChunks[$round - 1];
        $chunkPreview = mb_strlen($currentChunk, 'UTF-8') > 1000 
            ? mb_substr($currentChunk, 0, 1000, 'UTF-8') . '...' 
            : $currentChunk;
            
        return "剧本下一部分：\n{$chunkPreview}\n\n请继续分析，确保每个分镜分析完整。";
    } else {
        return "请基于前面的分析，提供一个完整的总结。";
    }
}

/**
 * 清理消息历史
 */
function cleanupMessageHistory($messages) {
    if (count($messages) > 6) {
        // 保留系统消息、初始消息和最近2轮对话
        $cleanedMessages = [
            $messages[0], // 系统消息
            $messages[1], // 初始用户消息
        ];
        
        // 添加最近的两轮对话（如果存在）
        $recentMessages = array_slice($messages, -2);
        foreach ($recentMessages as $message) {
            $cleanedMessages[] = $message;
        }
        
        return $cleanedMessages;
    }
    return $messages;
}

/**
 * 检查响应完整性
 */
function checkResponseCompleteness($content) {
    // 检查是否包含所有必需字段的结尾标记
    $requiredEndFields = [
        '光线与色调',
        '镜头焦段', 
    ];
    
    // 如果内容以完整的表格行结束，认为是完整的
    if (preg_match('/\|\s*[^|]*\s*\|\s*$/', trim($content))) {
        return true;
    }
    
    // 检查是否包含关键结束字段
    foreach ($requiredEndFields as $field) {
        if (strpos($content, $field) !== false) {
            // 如果找到了关键字段，检查其后的内容是否完整
            $lastFieldPos = strrpos($content, $field);
            $contentAfterLastField = substr($content, $lastFieldPos);
            
            // 如果关键字段后面有合理的内容长度，认为是完整的
            if (strlen($contentAfterLastField) > 30) {
                return true;
            }
        }
    }
    
    // 检查是否有明显的截断迹象
    $truncationIndicators = [
        '...',
        '[分析被截断',
        '（未完）',
        '（继续）'
    ];
    
    foreach ($truncationIndicators as $indicator) {
        if (strpos($content, $indicator) !== false) {
            return false;
        }
    }
    
    // 默认认为是完整的
    return true;
}

/**
 * 提取最后一个不完整的分镜
 */
function extractLastIncompleteShot($content) {
    // 按行分割内容
    $lines = explode("\n", $content);
    $incompleteShot = '';
    
    // 从后往前查找可能的不完整分镜
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        
        if (empty($line)) {
            continue;
        }
        
        // 如果遇到表格行开始，开始收集
        if (preg_match('/^\|\s*\d+\s*\|/', $line)) {
            $incompleteShot = $line . "\n" . $incompleteShot;
            
            // 检查这一行是否完整（包含足够多的字段）
            $fieldCount = substr_count($line, '|');
            if ($fieldCount >= 15) { // 至少有15个字段，可能是不完整的行
                break;
            }
        } elseif (!empty($incompleteShot)) {
            // 继续收集相关行
            $incompleteShot = $line . "\n" . $incompleteShot;
        }
    }
    
    return trim($incompleteShot);
}

/**
 * 优化提示词，减少token使用
 */
function optimizePrompt($prompt) {
    // 使用优化后的提示词，确保AI输出严格按照表格格式
    $optimizedPrompt = "【必须严格遵守的输出格式要求：只输出表格，不输出任何其他文本！！！】

请严格按照以下步骤执行：
1. 分场次分析，场次之间不要合并
2. 提炼出每个场次能拆分出的所有分镜（最少1个，最多9个，各场次分镜不允许合并分析及处理）
3. 按场次号及分镜号顺序，使用严格的表格格式列出所有分镜
4. 每个分镜必须包含以下字段，字段顺序严格固定，不得增减：
   | 排序号 | 场次号 | 镜号 | 地点 | 时间 | 天气 | 参考画面 | 景别 | 时长(秒) | 内容 | 剧本 | 台词 | 角色清单 | 各角色推荐服装 | 各角色推荐妆造 | 角色动作 | 道具 | 场景预期 | 声音设计 | 摄像机角度 | 构图与焦点 | 运镜 | 摄像机设备 | 镜头焦段 | 光线与色调 |

【字段说明及要求：】
- 排序号：按分镜出现顺序的数字编号
- 场次号：场次的数字编号
- 镜号：分镜的数字编号
- 地点：场景名称
- 时间：从\"日、夜、晨、暮、黄昏、黎明\"中选择
- 天气：从\"晴、阴、雨、雾、雪、风、沙、霾\"中选择
- 参考画面：详细的文字生成图提示词，符合专业摄影手法，包含运镜策略
- 景别：从\"大远景、远景、全景、中全景、中景、中近景、特写、大特写、航拍\"中选择
- 时长(秒)：预估的分镜时长
- 内容：分镜剧情概要
- 剧本：该分镜的完整剧本内容
- 台词：该分镜的所有台词对白
- 角色清单：该分镜中出现的所有角色
- 各角色推荐服装：每个角色的详细服装描述
- 各角色推荐妆造：每个角色的详细妆造描述
- 角色动作：每个角色的详细动作描述
- 道具：该分镜中使用的所有道具
- 场景预期：选景建议
- 声音设计：音效、环境音、背景音乐等描述
- 摄像机角度：专业拍摄角度描述
- 构图与焦点：三分法、对称构图、景深等描述
- 运镜：专业运镜手法描述，可从正面、侧面、斜面、背面、平视角、仰拍、俯拍、顶拍、鸟瞰角、推/拉镜、摇镜、移镜、升降镜、主观视角、客观视角、过肩、跟摄、反光、微距中选择或组合
- 摄像机设备：分别推荐国产和国外设备
- 镜头焦段：具体的镜头焦段推荐
- 光线与色调：光源方向、影调、色调描述

【输出示例：】
| 排序号 | 场次号 | 镜号 | 地点 | 时间 | 天气 | 参考画面 | 景别 | 时长(秒) | 内容 | 剧本 | 台词 | 角色清单 | 各角色推荐服装 | 各角色推荐妆造 | 角色动作 | 道具 | 场景预期 | 声音设计 | 摄像机角度 | 构图与焦点 | 运镜 | 摄像机设备 | 镜头焦段 | 光线与色调 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| 1 | 1 | 1 | 仙人山顶 | 傍晚 | 晴 | 残阳如血，染红层峦，悬崖边，穆子峰踉跄奔至，身后是万丈深渊，退无可退 | 大远景 | 10 | 穆子峰被追杀至悬崖边，退无可退 | 残阳如血，染红层峦。穆子峰踉跄奔至悬崖之巅，粗布衣衫多处撕裂，身后是万丈深渊，退无可退。 | 无 | 穆子峰 | 粗布衣衫，多处撕裂，沾有血迹 | 头发凌乱，面色苍白，额头有汗水 | 踉跄奔跑，回望追兵，眼神绝望 | 无 | 险峻的悬崖之巅，夕阳西下 | 山风呼啸，远处传来追兵的呼喊声 | 俯拍 | 三分法构图，穆子峰位于画面右侧，深渊占据左侧大部分 | 缓慢推镜，聚焦穆子峰绝望的表情 | 国产：大疆如影4；国外：RED KOMODO | 24mm | 侧光，暖色调，高对比度 |

【注意事项：】
1. 只输出表格，不润色、不扩写、不输出任何其他文本、说明或解释！
2. 严格按照字段顺序和格式输出，不得遗漏任何字段！
3. 每个分镜必须占一行，不得跨行！
4. 表格必须包含表头和分隔线！
5. 所有字段必须填写，不能为空！
6. 输出必须为纯文本，不包含任何Markdown标题或其他格式！
7. 严格按照场次号和镜号顺序排列！

现在，请开始分析并输出表格！";
    return $optimizedPrompt;
}

/**
 * 调用DeepSeek API（带重试机制）
 */
function callDeepSeekAPIWithRetry($apiKey, $messages, $round, $maxRetries = 3) {
    for ($retry = 0; $retry < $maxRetries; $retry++) {
        try {
            // 根据轮次调整超时时间
            $timeout = $round === 1 ? 12000 : 9000;
            
            // 构建请求数据 - 根据轮次调整token数量
            $maxTokens = 8000;
            
            $requestData = [
                'model' => 'deepseek-chat',
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => $maxTokens,
                'stream' => false 
            ];
            
            // 调用DeepSeek API 
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://api.deepseek.com/v1/chat/completions', 
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($requestData),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                    'User-Agent: ScriptAnalyzer/1.0'
                ],
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_CONNECTTIMEOUT => 300 
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                if ($retry < $maxRetries - 1) {
                    sleep(5);
                    continue;
                }
                throw new Exception("API请求失败: HTTP {$httpCode}" . ($error ? " - {$error}" : ''));
            }
            
            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('API响应JSON解析错误: ' . json_last_error_msg());
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception('无法解析API响应内容');
            }
            
            return $result['choices'][0]['message']['content'];
            
        } catch (Exception $e) {
            if ($retry < $maxRetries - 1) {
                sleep(5);
                continue;
            }
            throw $e;
        }
    }
}

/**
 * 保存错误结果 
 */
function saveErrorResult($taskId, $errorMessage) {
    $resultFile = __DIR__ . '/results/' . $taskId . '.json';
    
    $errorResult = [
        'task_id' => $taskId,
        'status' => 'error',
        'start_time' => file_exists($resultFile) ? date('Y-m-d H:i:s', filemtime($resultFile)) : date('Y-m-d H:i:s'),
        'end_time' => date('Y-m-d H:i:s'),
        'content' => '',
        'error' => $errorMessage 
    ];
    
    $jsonError = json_encode($errorResult, JSON_UNESCAPED_UNICODE);
    if ($jsonError !== false) {
        file_put_contents($resultFile, $jsonError, LOCK_EX);
    }
}


//分镜正式分拆部分

/**
 * 从JSON输入中解析分镜数据并生成目标格式的JSON文件
 * @param string $jsonInput JSON格式的输入字符串
 * @param string $outputFile 输出文件路径
 * @return array 解析结果信息
 */
function parseShootingScriptToTargetJson($jsonInput, $outputFile = './scenes_output/final_scenes.json') {
    
    // 创建输出目录
    $outputDir = dirname($outputFile);
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir, 0755, true) && !is_dir($outputDir)) {
            throw new \RuntimeException('无法创建目录: ' . $outputDir);
        }
    }
    
    // 解析JSON输入
    $data = json_decode($jsonInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException('JSON解析错误: ' . json_last_error_msg());
    }
    
    // 检查content字段是否存在
    if (!isset($data['content'])) {
        throw new \InvalidArgumentException('JSON数据中缺少content字段');
    }
    
    $content = $data['content'];
    
    // 按行分割文本
    $lines = explode("\n", trim($content));
    
    // 移除表头分隔线和其他空行
    $dataLines = array_filter($lines, function($line) {
        $trimmed = trim($line);
        return !empty($trimmed) && !preg_match('/^[-| ]+$/', $trimmed);
    });
    
    // 重新索引数组
    $dataLines = array_values($dataLines);
    
    if (count($dataLines) < 2) {
        throw new \InvalidArgumentException('表格数据不足，至少需要表头和数据行');
    }
    
    // 提取表头 - 找到第一个有效的表头行
    $headers = [];
    $dataStartIndex = 0;
    
    for ($i = 0; $i < count($dataLines); $i++) {
        $potentialHeaders = parseTableRow($dataLines[$i]);
        
        // 检查是否包含关键字段来判断是否为表头
        $headerKeywords = ['排序号', '场次号', '镜号', '地点', '时间', '天气'];
        $isHeader = false;
        
        foreach ($headerKeywords as $keyword) {
            if (in_array($keyword, $potentialHeaders)) {
                $isHeader = true;
                break;
            }
        }
        
        if ($isHeader) {
            $headers = $potentialHeaders;
            $dataStartIndex = $i + 1;
            break;
        }
    }
    
    if (empty($headers)) {
        throw new \InvalidArgumentException('未找到有效的表头行');
    }
    
    // 按场次分组数据
    $scenesData = [];
    
    // 处理数据行（从数据开始索引处开始）
    for ($i = $dataStartIndex; $i < count($dataLines); $i++) {
        $rowData = parseTableRow($dataLines[$i]);
        
        // 跳过表头重复行 - 检查是否包含表头关键词
        $isHeaderRow = false;
        foreach ($headers as $header) {
            if (in_array($header, $rowData)) {
                $isHeaderRow = true;
                break;
            }
        }
        
        if ($isHeaderRow) {
            continue; // 跳过表头重复行
        }
        
        // 如果行数据数量与表头不匹配，跳过
        if (count($rowData) !== count($headers)) {
            continue;
        }
        
        // 检查是否为空行或无效数据
        $isEmptyRow = true;
        foreach ($rowData as $cell) {
            if (!empty(trim($cell)) && !in_array(trim($cell), $headers)) {
                $isEmptyRow = false;
                break;
            }
        }
        
        if ($isEmptyRow) {
            continue;
        }
        
        // 构建分镜数据
        $shotData = [];
        foreach ($headers as $index => $header) {
            $shotData[trim($header)] = trim($rowData[$index]);
        }
        
        // 验证必要字段
        if (empty($shotData['场次号']) || empty($shotData['镜号']) || !is_numeric($shotData['场次号'])) {
            continue;
        }
        
        $sceneNumber = intval($shotData['场次号']);
        $sceneId = "SC" . str_pad($sceneNumber, 3, '0', STR_PAD_LEFT);
        
        if (!isset($scenesData[$sceneId])) {
            $scenesData[$sceneId] = [
                'id' => $sceneId,
                'name' => "场次 {$sceneNumber} - " . ($shotData['地点'] ?? '未知地点'),
                'tags' => generateSceneTags($shotData),
                'shots' => []
            ];
        }
        
        // 构建shot对象 - 严格按照源数据字段对应
        $shot = [
          
            'shot_id' => $shotData['镜号'] ?? '',
            'shotType' => $shotData['景别'] ?? '', // 直接使用源数据
            'duration' => intval($shotData['时长(秒)'] ?? 5),
            'content' => $shotData['内容'] ?? '',
            'remark' => $shotData['内容'] ?? '', // 使用内容作为备注
            'sceneExpectation' => $shotData['场景预期'] ?? '',
            'sound' => $shotData['声音设计'] ?? '',
            'cameraAngle' => $shotData['摄像机角度'] ?? '', // 直接使用源数据
            'cameraMovement' => $shotData['运镜'] ?? '', // 直接使用源数据
            'cameraEquipment' => $shotData['摄像机设备'] ?? '', // 直接使用源数据
            'lensFocalLength' => $shotData['镜头焦段'] ?? '', // 直接使用源数据
            'compositionFocus' => $shotData['构图与焦点'] ?? '',
            'lightTone' => $shotData['光线与色调'] ?? '', // 直接使用源数据
            'location' => $shotData['地点'] ?? '',
            'time' => $shotData['时间'] ?? '', // 直接使用源数据
            'weather' => $shotData['天气'] ?? '',
            'dialogue' => $shotData['台词'] ?? '',
            'script' => $shotData['剧本'] ?? '',
            'characters' => $shotData['角色清单'] ?? '',
            'characterCostumes' => $shotData['各角色推荐服装'] ?? '',
            'characterMakeup' => $shotData['各角色推荐妆造'] ?? '',
            'characterActions' => $shotData['角色动作'] ?? '',
            'props' => $shotData['道具'] ?? '',
            'customContent' => '自定义内容'
        ];
        
        $scenesData[$sceneId]['shots'][] = $shot;
    }
    
    // 按场次号排序
    uasort($scenesData, function($a, $b) {
        return strcmp($a['id'], $b['id']);
    });
    
    // 构建最终JSON结构
    $finalData = [
        'scenes' => array_values($scenesData)
    ];
    
    // 转换为JSON并保存
    $jsonData = json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if (file_put_contents($outputFile, $jsonData)) {
        return [
            'filename' => basename($outputFile),
            'filepath' => $outputFile,
            'scenes_count' => count($scenesData),
            'shots_count' => array_sum(array_map(function($scene) {
                return count($scene['shots']);
            }, $scenesData)),
            'status' => 'success'
        ];
    } else {
        throw new \RuntimeException('无法写入文件: ' . $outputFile);
    }
}

/**
 * 解析表格行数据
 */
function parseTableRow($row) {
    $row = trim($row);
    
    // 移除行首尾的管道符
    if (strpos($row, '|') === 0) {
        $row = substr($row, 1);
    }
    if (substr($row, -1) === '|') {
        $row = substr($row, 0, -1);
    }
    
    // 分割单元格
    $cells = explode('|', $row);
    
    // 清理每个单元格的空白
    $cleanedCells = array_map(function($cell) {
        return trim($cell);
    }, $cells);
    
    return $cleanedCells;
}

/**
 * 生成场景标签 - 基于源数据中的地点和时间信息
 */
function generateSceneTags($shotData) {
    $tags = [];
    
    // 时间标签 - 直接从源数据的时间字段提取
    $time = $shotData['时间'] ?? '';
    if (in_array($time, ['日', '晨', '中午', '上午', '下午'])) {
        $tags[] = '日戏';
    } elseif (in_array($time, ['夜', '黄昏', '暮'])) {
        $tags[] = '夜戏';
    }
    
    // 内外景标签 - 基于源数据的地点字段
    $location = $shotData['地点'] ?? '';
    if (strpos($location, '外') !== false) {
        $tags[] = '外景';
    } else {
        $tags[] = '内景';
    }
    
    // 地点标签 - 直接使用源数据中的地点
    if (!empty($location)) {
        $tags[] = $location;
    }
    
    return array_unique($tags);
}

/**
 * 批量生成分镜JSON文件（从JSON输入）
 */
function generateTargetJsonFromJson($jsonInput, $outputFile = './scenes_output/final_scenes.json') {
    $result = parseShootingScriptToTargetJson($jsonInput, $outputFile);
    
    if ($result['status'] === 'success') {
        echo "✓ 成功生成目标JSON文件: {$result['filename']}\n";
        echo "  - 包含 {$result['scenes_count']} 个场次\n";
        echo "  - 包含 {$result['shots_count']} 个分镜\n";
        echo "  - 文件路径: {$result['filepath']}\n";
    } else {
        echo "✗ 生成失败: {$result['filename']}\n";
    }
    
    return $result;
}

/**
 * 从JSON文件读取并生成目标JSON文件
 */
function generateTargetJsonFromJsonFile($jsonFilePath, $outputFile = './scenes_output/final_scenes.json') {
    if (!file_exists($jsonFilePath)) {
        throw new \InvalidArgumentException("JSON文件不存在: {$jsonFilePath}");
    }
    
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        throw new \RuntimeException("无法读取JSON文件: {$jsonFilePath}");
    }
    
    return generateTargetJsonFromJson($jsonContent, $outputFile);
}

/**
 * 将生成的storyboards JSON文件内容存入数据库
 * @param string $storyboardsJsonPath 生成的storyboards JSON文件路径
 * @return array 操作结果
 */
function saveStoryboardsToDatabase($storyboardsJsonPath) {
    if (!file_exists($storyboardsJsonPath)) {
        throw new \InvalidArgumentException("Storyboards JSON文件不存在: {$storyboardsJsonPath}");
    }
    
    // 解析JSON文件
    $jsonContent = file_get_contents($storyboardsJsonPath);
    if ($jsonContent === false) {
        throw new \RuntimeException("无法读取Storyboards JSON文件: {$storyboardsJsonPath}");
    }
    
    $data = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new \InvalidArgumentException("JSON解析错误: " . json_last_error_msg());
    }
    
    if (!isset($data['scenes']) || !is_array($data['scenes'])) {
        throw new \InvalidArgumentException("JSON数据中缺少scenes字段或格式不正确");
    }
    
    // 从文件名中提取task_id
    $fileName = basename($storyboardsJsonPath);
    $taskId = preg_replace('/^script_analysis_([0-9a-f.]+)_storyboards\.json$/', '$1', $fileName);
    if (empty($taskId)) {
        throw new \InvalidArgumentException("无法从文件名中提取task_id");
    }
    
    // 获取数据库连接
    require_once __DIR__ . '/Database.php';
    require_once __DIR__ . '/TaskManager.php';
    
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    $taskManager = TaskManager::getInstance();
    
    // 确保userId不为null，使用默认值1
    $safeUserId = 1;
    
    // 尝试从tasks表中获取user_id
    try {
        $sql = "SELECT user_id FROM tasks WHERE task_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($task && !empty($task['user_id'])) {
            $safeUserId = $task['user_id'];
        }
    } catch (Exception $e) {
        error_log("获取任务 {$taskId} 的user_id失败: " . $e->getMessage());
    }
    
    // 获取crew_id
    $crewId = 1;
    try {
        // 检查用户是否已有剧组
        $sql = "SELECT id, current_task_id FROM crew WHERE admin_user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$safeUserId]);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($crew) {
            // 用户已有剧组，更新current_task_id
            $sql = "UPDATE crew SET current_task_id = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$taskId, $crew['id']]);
            $crewId = $crew['id'];
            error_log("已更新用户 {$safeUserId} 的剧组 current_task_id 为 {$taskId}");
        } else {
            // 用户没有剧组，创建一个默认剧组
            $sql = "INSERT INTO crew (admin_user_id, name, status, current_task_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $defaultCrewName = "{$safeUserId}的默认剧组";
            $stmt->execute([$safeUserId, $defaultCrewName, 1, $taskId]);
            $crewId = $pdo->lastInsertId();
            error_log("已为用户 {$safeUserId} 创建默认剧组，并设置 current_task_id 为 {$taskId}");
        }
    } catch (Exception $e) {
        error_log("获取或创建剧组失败: " . $e->getMessage());
    }
    
    try {
        $pdo->beginTransaction();
        
        $scenesCount = 0;
        $shotsCount = 0;
        
        // 遍历scenes，插入数据库
        foreach ($data['scenes'] as $scene) {
            // 插入scenes表
            $sql = "INSERT INTO scenes (user_id, crew_id, task_id, scene_id, scene_name, scenes_tags, created_at, updated_at, status) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)";
            $stmt = $pdo->prepare($sql);
            $tags = json_encode($scene['tags'] ?? []);
            $stmt->execute([$safeUserId, $crewId, $taskId, $scene['id'], $scene['name'], $tags]);
            $scenesCount++;
            
            // 遍历shots，插入数据库
            if (isset($scene['shots']) && is_array($scene['shots'])) {
                foreach ($scene['shots'] as $shot) {
                    $sql = "INSERT INTO shots (user_id, crew_id, task_id, scenes_id, shots_id, shotType, duration, content, remark, sceneExpectation, sound, cameraAngle, cameraMovement, cameraEquipment, lensFocalLength, compositionFocus, lightTone, location, time, weather, dialogue, script, characters, characterCostumes, characterMakeup, characterActions, props, customContent, created_at, updated_at, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), 1)";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        $safeUserId, $crewId, $taskId, $scene['id'], $shot['id'],
                        $shot['shotType'] ?? '', $shot['duration'] ?? 0, $shot['content'] ?? '',
                        $shot['remark'] ?? '', $shot['sceneExpectation'] ?? '', $shot['sound'] ?? '',
                        $shot['cameraAngle'] ?? '', $shot['cameraMovement'] ?? '',
                        $shot['cameraEquipment'] ?? '', $shot['lensFocalLength'] ?? '',
                        $shot['compositionFocus'] ?? '', $shot['lightTone'] ?? '',
                        $shot['location'] ?? '', $shot['time'] ?? '', $shot['weather'] ?? '',
                        $shot['dialogue'] ?? '', $shot['script'] ?? '',
                        $shot['characters'] ?? '', $shot['characterCostumes'] ?? '',
                        $shot['characterMakeup'] ?? '', $shot['characterActions'] ?? '',
                        $shot['props'] ?? '', $shot['customContent'] ?? ''
                    ]);
                    $shotsCount++;
                }
            }
        }
        
        // （可选）更新crew表的current_task_id
        try {
            $sql = "UPDATE crew SET current_task_id = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$taskId, $crewId]);
        } catch (Exception $e) {
            error_log("更新crew表失败: " . $e->getMessage());
        }
        
        $pdo->commit();
        
        return [
            'status' => 'success',
            'message' => 'Storyboards数据成功存入数据库',
            'scenes_count' => $scenesCount,
            'shots_count' => $shotsCount,
            'task_id' => $taskId,
            'user_id' => $safeUserId,
            'crew_id' => $crewId
        ];
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("将Storyboards数据存入数据库失败: " . $e->getMessage());
        throw new \RuntimeException("数据库操作失败: " . $e->getMessage());
    }
}

/**
 * 从数据库中还原storyboards JSON文件
 * @param string $taskId 任务ID
 * @return string 还原的JSON字符串
 */
function restoreStoryboardsJson($taskId) {
    // 获取数据库连接
    require_once __DIR__ . '/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 查询场景数据
    $sceneSql = "SELECT * FROM scenes WHERE task_id = ? ORDER BY scene_id ASC";
    $sceneStmt = $pdo->prepare($sceneSql);
    $sceneStmt->execute([$taskId]);
    $scenes = $sceneStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $finalResult = [
        'task_id' => $taskId,
        'status' => 'completed',
        'scenes' => []
    ];
    
    foreach ($scenes as $scene) {
        // 查询该场景下的分镜数据
        $shotSql = "SELECT * FROM shots WHERE task_id = ? AND scenes_id = ? ORDER BY id ASC";
        $shotStmt = $pdo->prepare($shotSql);
        $shotStmt->execute([$taskId, $scene['scene_id']]);
        $shots = $shotStmt->fetchAll(PDO::FETCH_ASSOC);
        
        $sceneData = [
            'id' => $scene['scene_id'],
            'name' => $scene['scene_name'],
            'tags' => json_decode($scene['tags'] ?? '[]', true),
            'shots' => []
        ];
        
        foreach ($shots as $shot) {
            $sceneData['shots'][] = [
                'id' => $shot['shots_id'],
                'shotType' => $shot['shotType'] ?? '',
                'duration' => $shot['duration'] ?? 0,
                'content' => $shot['content'] ?? '',
                'remark' => $shot['remark'] ?? '',
                'sceneExpectation' => $shot['sceneExpectation'] ?? '',
                'sound' => $shot['sound'] ?? '',
                'cameraAngle' => $shot['cameraAngle'] ?? '',
                'cameraMovement' => $shot['cameraMovement'] ?? '',
                'cameraEquipment' => $shot['cameraEquipment'] ?? '',
                'lensFocalLength' => $shot['lensFocalLength'] ?? '',
                'compositionFocus' => $shot['compositionFocus'] ?? '',
                'lightTone' => $shot['lightTone'] ?? '',
                'location' => $shot['location'] ?? '',
                'time' => $shot['time'] ?? '',
                'weather' => $shot['weather'] ?? '',
                'dialogue' => $shot['dialogue'] ?? '',
                'script' => $shot['script'] ?? '',
                'characters' => $shot['characters'] ?? '',
                'characterCostumes' => $shot['characterCostumes'] ?? '',
                'characterMakeup' => $shot['characterMakeup'] ?? '',
                'characterActions' => $shot['characterActions'] ?? '',
                'props' => $shot['props'] ?? '',
                'customContent' => $shot['customContent'] ?? ''
            ];
        }
        
        $finalResult['scenes'][] = $sceneData;
    }
    
    return json_encode($finalResult, JSON_UNESCAPED_UNICODE);
}


