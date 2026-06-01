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

$resultsDir = __DIR__ . '/results';
if (!is_dir($resultsDir)) {
    if (!mkdir($resultsDir, 0755, true)) {
        echo json_encode(['error' => '无法创建结果目录']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_GET['action'] ?? '';
    
    if ($action === 'edit_scene') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $sceneId = $data['scene_id'] ?? 0;
        
        if (empty($sceneId)) {
            echo json_encode(['error' => '时空场景ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 先尝试通过scene_id查找（前端传递的是AI生成的场景编号），只查找当前用户的场景
        $sql = "SELECT id, user_id FROM spaces WHERE scene_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sceneId, $userId]);
        $scene = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果没找到，再尝试通过id查找（数据库主键）
        if (!$scene) {
            $sql = "SELECT id, user_id FROM spaces WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sceneId, $userId]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        if (!$scene) {
            echo json_encode(['error' => '时空场景不存在']);
            exit;
        }
        
        if ($scene['user_id'] != $userId) {
            echo json_encode(['error' => '您没有权限操作该时空场景']);
            exit;
        }
        
        $sql = "UPDATE spaces SET name = ?, description = ?, updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['name'] ?? '',
            $data['description'] ?? '',
            $scene['id']
        ]);
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    if ($action === 'delete_scene') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $sceneId = $data['scene_id'] ?? 0;
        
        if (empty($sceneId)) {
            echo json_encode(['error' => '时空场景ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT id, user_id FROM spaces WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sceneId]);
        $scene = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$scene) {
            echo json_encode(['error' => '时空场景不存在']);
            exit;
        }
        
        if ($scene['user_id'] != $userId) {
            echo json_encode(['error' => '您没有权限操作该时空场景']);
            exit;
        }
        
        $sql = "DELETE FROM spaces WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sceneId]);
        
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
            exit;
    }
    
    if ($action === 'generate_scene_image') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => 'JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        $sceneId = $data['scene_id'] ?? 0;
        $prompt = $data['prompt'] ?? '';
        $taskId = $data['task_id'] ?? '';
        
        if (empty($sceneId)) {
            echo json_encode(['error' => '时空场景ID不能为空']);
            exit;
        }
        
        if (empty($prompt)) {
            echo json_encode(['error' => '提示词不能为空']);
            exit;
        }
        
        if (empty($taskId)) {
            echo json_encode(['error' => '任务ID不能为空']);
            exit;
        }
        
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        if (!$userId) {
            echo json_encode(['error' => '用户未登录']);
            exit;
        }
        
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 先尝试通过scene_id查找（前端传递的是AI生成的场景编号），只查找当前用户和特定任务的场景
        $sql = "SELECT id, user_id, name, description, scene_id, task_id FROM spaces WHERE scene_id = ? AND user_id = ? AND task_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$sceneId, $userId, $taskId]);
        $scene = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // 如果没找到，再尝试通过id查找（数据库主键），只查找当前用户和特定任务的场景
        if (!$scene) {
            $sql = "SELECT id, user_id, name, description, scene_id, task_id FROM spaces WHERE id = ? AND user_id = ? AND task_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$sceneId, $userId, $taskId]);
            $scene = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        // 添加调试信息
        error_log("Debug: taskId = {$taskId}");
        error_log("Debug: Scene found: " . ($scene ? 'yes' : 'no'));
        
        if (!$scene) {
            echo json_encode(['error' => '时空场景不存在']);
            exit;
        }
        
        // 使用数据库中的真实id
        $realSceneId = $scene['id'];
        
        // 添加调试信息
        error_log("Debug: scene_id = {$sceneId}, realSceneId = {$realSceneId}");
        error_log("Debug: scene['user_id'] = {$scene['user_id']}, type = " . gettype($scene['user_id']));
        error_log("Debug: userId = {$userId}, type = " . gettype($userId));
        error_log("Debug: (int)scene['user_id'] = " . (int)$scene['user_id'] . ", (int)userId = " . (int)$userId);
        error_log("Debug: Comparison result: " . ((int)$scene['user_id'] != (int)$userId));
        
        // 重新启用权限验证，但添加更详细的错误信息
        if ((int)$scene['user_id'] != (int)$userId) {
            error_log("Permission denied: scene user_id {$scene['user_id']} != current user_id {$userId}");
            echo json_encode(['error' => '您没有权限操作该时空场景', 'debug' => ['scene_user_id' => $scene['user_id'], 'current_user_id' => $userId]]);
            exit;
        }
        
        // 检查积分
        $requiredPoints = 20; // 场景图生成消耗20积分
        
        if (!$auth->checkUserPoints($userId, $requiredPoints)) {
            echo json_encode(['error' => "积分不足，无法生成场景图。需要 {$requiredPoints} 积分，当前积分不足"]);
            exit;
        }
        
        // 扣除积分
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '生成场景图', 'generate_scene_image', uniqid());
        if (!$deductResult['success']) {
            echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
            exit;
        }
        
        // 从Config类获取文生图API配置
        $text2imgApiUrl = Config::TEXT2IMG_API_URL();
        $text2imgApiKey = Config::TEXT2IMG_API_KEY();
        
        if (empty($text2imgApiUrl) || empty($text2imgApiKey)) {
            echo json_encode(['error' => '文生图API未配置']);
            exit;
        }
        
        // 调试信息
        error_log('Using API URL: ' . $text2imgApiUrl);
        error_log('Using API Key: ' . $text2imgApiKey);
        
        // 从Config类获取文生图API模型
        $text2imgApiModel = Config::TEXT2IMG_API_MODEL();
        if (empty($text2imgApiModel)) {
            $text2imgApiModel = 'doubao-seedream-4-5-251128'; // 默认模型
        }
        
        // 生成场景图 - 使用官方API格式
        $apiData = [
            'model' => $text2imgApiModel,
            'prompt' => $prompt,
            'sequential_image_generation' => 'disabled',
            'response_format' => 'url',
            'size' => '2K',
            'stream' => false,
            'watermark' => false
        ];
        
        // 调试信息
        error_log('Using API Model: ' . $text2imgApiModel);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $text2imgApiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($apiData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $text2imgApiKey
            ],
            CURLOPT_TIMEOUT => 300,
            CURLOPT_CONNECTTIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            echo json_encode(['error' => '文生图请求失败: ' . $error]);
            exit;
        }
        
        if ($httpCode !== 200) {
            echo json_encode(['error' => '文生图API返回错误: HTTP ' . $httpCode . '，响应内容: ' . $response]);
            exit;
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo json_encode(['error' => '文生图响应JSON解析错误: ' . json_last_error_msg()]);
            exit;
        }
        
        // 调试信息
        error_log('API Response: ' . $response);
        
        // 解析官方API响应格式
        $imageUrl = '';
        if (isset($result['data']) && is_array($result['data'])) {
            foreach ($result['data'] as $item) {
                if (isset($item['url'])) {
                    $imageUrl = $item['url'];
                    break;
                }
            }
        }
        
        if (empty($imageUrl)) {
            echo json_encode(['error' => '文生图响应中没有图片URL']);
            exit;
        }
        
        // 下载图片到本地
        $outputDir = __DIR__ . '/outputs/images';
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        $filename = 'scene_' . uniqid() . '_' . time() . '.png';
        $localPath = $outputDir . '/' . $filename;
        
        $imgCh = curl_init($imageUrl);
        $fp = fopen($localPath, 'wb');
        curl_setopt($imgCh, CURLOPT_FILE, $fp);
        curl_setopt($imgCh, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($imgCh, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($imgCh, CURLOPT_TIMEOUT, 60);
        $imgResult = curl_exec($imgCh);
        curl_close($imgCh);
        fclose($fp);

        if ($imgResult) {
            $localImageUrl = 'https://files.wop.cc/images/' . $filename;
        } else {
            $localImageUrl = $imageUrl;
        }
        
        // 保存场景图URL到数据库
        $sql = "UPDATE spaces SET imageUrl = ? WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            json_encode([$localImageUrl]), // 存储为JSON数组
            $realSceneId
        ]);
        
        echo json_encode(['success' => true, 'image_url' => $localImageUrl], JSON_UNESCAPED_UNICODE);
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
    
    // 过滤掉指定的表格字符串
    $tablePattern1 = '/\| 排序号 \| 场次号 \| 镜号 \| 地点 \| 时间 \| 天气 \| 参考画面 \| 景别 \| 时长\(秒\) \| 内容 \| 剧本 \| 台词 \| 角色清单 \| 各角色推荐服装 \| 各角色推荐妆造 \| 角色动作 \| 道具 \| 场景预期 \| 声音设计 \| 摄像机角度 \| 构图与焦点 \| 运镜 \| 摄像机设备 \| 镜头焦段 \| 光线与色调 \|\s*\| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \| --- \|/s';
    $tablePattern2 = '/\| 排序号 \| 场次号 \| 镜号 \| 地点 \| 时间 \| 天气 \| 参考画面 \| 景别 \| 时长\(秒\) \| 内容 \| 剧本 \| 台词 \| 角色清单 \| 各角色推荐服装 \| 各角色推荐妆造 \| 角色动作 \| 道具 \| 场景预期 \| 声音设计 \| 摄像机角度 \| 构图与焦点 \| 运镜 \| 摄像机设备 \| 镜头焦段 \| 光线与色调 \|\s*\| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \| :--- \|/s';
    
    $script = preg_replace($tablePattern1, '', $script);
    $script = preg_replace($tablePattern2, '', $script);
   // 验证脚本内容
    if (empty($script)) {
        echo json_encode(['error' => '剧本内容不能为空']);
        exit;
    }
    
    // 检查脚本长度
    $scriptLength = mb_strlen($script, 'UTF-8');
    $maxScriptLength = 80000; // 单次API请求的最大脚本长度
    $isLongScript = $scriptLength > $maxScriptLength;
    
    if (strlen($script) > 5 * 1024 * 1024) {
        echo json_encode(['error' => '剧本内容过大，请压缩至5MB以内']);
        exit;
    }

    $taskId = uniqid('space_analysis_', true);
    $resultFile = $resultsDir . '/' . $taskId . '.json';
    
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    $requiredPoints = 100; // 时空场景分析消耗100积分
    
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        echo json_encode(['error' => "积分不足，无法进行时空场景分析操作。需要 {$requiredPoints} 积分，当前积分不足"]);
        exit;
    }
    
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '时空场景分析', 'space_analysis', $taskId);
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
        'current_stage' => 'extracting_spaces',
        'progress' => 5,
        'start_time' => date('Y-m-d H:i:s'),
        'script_preview' => $scriptPreview,
        'scenes' => [],
        'logs' => [],
        'message' => '开始提取时空场景信息...'
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
    
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $taskManager = TaskManager::getInstance();
    
    $inputData = [
        'script_length' => mb_strlen($script, 'UTF-8'),
        'task_type' => 'space_analysis',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $safeUserId = $userId !== null ? $userId : 1;
    
    $dbTaskId = $taskManager->createTask(
        $safeUserId,
        'space_analysis',
        '时空场景分析',
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
        'message' => '时空场景分析任务已开始，请稍后查询结果'
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
    
    // 直接处理时空场景分析
    try {
        // 更新任务进度
        $taskManager->updateTaskProgress($dbTaskId, 20, '正在分析时空场景...');
        
        // 从Config类获取API配置
        $apiKey = Config::DEEPSEEK_API_KEY();
        $apiUrl = Config::DEEPSEEK_API_URL();
        $apiModel = Config::DEEPSEEK_MODEL();
        
        if (empty($apiKey)) {
            throw new Exception('DeepSeek API密钥未配置');
        }
        
        if (empty($apiUrl)) {
            throw new Exception('DeepSeek API URL未配置');
        }
        
        if (empty($apiModel)) {
            throw new Exception('DeepSeek API模型未配置');
        }
        
        // 构建基础提示词
        $basePrompt = "对当前剧本分镜，在转成视频前，请提炼出必须保持一致性的的核心的时空场景（每个不超过20个字概括），回复的格式严格要求形如（必须是json格式。禁止回复任何无关的解释性、示例、概括、总结的文字）：
{
  \"success\": true,
  \"message\": \"时空场景数据获取成功\",
  \"scenes\": [
    {
      \"scene_id\": 1,
      \"name\": \"场景名称 (场景类型)\",
      \"description\": \"场景描述\"
    }
  ]
}

剧本内容：";
        
        // 调用API分析函数
        function callApi($apiUrl, $apiKey, $apiModel, $prompt) {
            $messages = [
                [
                    'role' => 'system',
                    'content' => '你是一个专业的剧本分析助手，负责提炼剧本中的时空场景。'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ];
            
            $apiData = [
                'model' => $apiModel,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'stream' => false
            ];
            
            $ch = curl_init($apiUrl);
            curl_setopt_array($ch, [
                CURLOPT_URL => $apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($apiData, JSON_UNESCAPED_UNICODE),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json; charset=utf-8',
                    'Authorization: Bearer ' . $apiKey,
                    'User-Agent: SpaceAnalysis/1.0'
                ],
                CURLOPT_TIMEOUT => 12000,
                CURLOPT_CONNECTTIMEOUT => 300
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            
            if ($httpCode !== 200 || !$response) {
                throw new Exception("API请求失败: HTTP {$httpCode}" . ($curlError ? " - {$curlError}" : '') . ($response ? " - 响应内容: {$response}" : ''));
            }
            
            if ($curlError) {
                throw new Exception('API调用失败: ' . $curlError);
            }
            
            $result = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('API响应JSON解析错误: ' . json_last_error_msg());
            }
            
            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception('无法解析API响应内容');
            }
            
            $aiResponseContent = $result['choices'][0]['message']['content'];
            $aiResult = json_decode($aiResponseContent, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('AI返回的JSON解析错误: ' . json_last_error_msg());
            }
            
            return $aiResult;
        }
        
        // 处理脚本分析
        $allScenes = [];
        
        if ($isLongScript) {
            // 长脚本：分割成两部分处理
            $taskManager->updateTaskProgress($dbTaskId, 30, '正在分割长脚本...');
            
            // 分割脚本
            $part1 = mb_substr($script, 0, $maxScriptLength, 'UTF-8');
            $part2 = mb_substr($script, $maxScriptLength, null, 'UTF-8');
            
            // 分析第一部分
            $taskManager->updateTaskProgress($dbTaskId, 40, '正在分析脚本第一部分...');
            $prompt1 = $basePrompt . $part1;
            $result1 = callApi($apiUrl, $apiKey, $apiModel, $prompt1);
            
            // 分析第二部分
            $taskManager->updateTaskProgress($dbTaskId, 60, '正在分析脚本第二部分...');
            $prompt2 = $basePrompt . $part2;
            $result2 = callApi($apiUrl, $apiKey, $apiModel, $prompt2);
            
            // 合并结果
            $taskManager->updateTaskProgress($dbTaskId, 80, '正在合并分析结果...');
            if (isset($result1['scenes'])) {
                $allScenes = array_merge($allScenes, $result1['scenes']);
            }
            if (isset($result2['scenes'])) {
                $allScenes = array_merge($allScenes, $result2['scenes']);
            }
            
            // 去重并重新编号
            $uniqueScenes = [];
            $sceneNames = [];
            $sceneId = 1;
            
            foreach ($allScenes as $scene) {
                if (isset($scene['name']) && !in_array($scene['name'], $sceneNames)) {
                    $scene['scene_id'] = $sceneId++;
                    $uniqueScenes[] = $scene;
                    $sceneNames[] = $scene['name'];
                }
            }
            
            $allScenes = $uniqueScenes;
        } else {
            // 短脚本：直接处理
            $taskManager->updateTaskProgress($dbTaskId, 40, '正在分析脚本...');
            $prompt = $basePrompt . $script;
            $result = callApi($apiUrl, $apiKey, $apiModel, $prompt);
            
            if (isset($result['scenes'])) {
                $allScenes = $result['scenes'];
            }
        }
        
        // 验证场景数据
        if (empty($allScenes)) {
            throw new Exception('AI分析失败: 未提取到时空场景');
        }
        
        $scenes = $allScenes;
        
        // 更新任务进度
        $taskManager->updateTaskProgress($dbTaskId, 80, '正在保存时空场景...');
        
        // 保存时空场景到数据库
        foreach ($scenes as $scene) {
            $sql = "INSERT INTO spaces (task_id, user_id, name, description, imageUrl, scene_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $taskId,
                $userId,
                $scene['name'],
                $scene['description'],
                $scene['imageUrl'] ?? '',
                $scene['scene_id']
            ]);
        }
        
        // 更新任务状态
        $taskManager->updateTaskProgress($dbTaskId, 100, '时空场景分析完成');
        $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_COMPLETED, 100);
        
        // 更新结果文件
        $finalResult = [
            'task_id' => $taskId,
            'status' => 'completed',
            'current_stage' => 'completed',
            'progress' => 100,
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => date('Y-m-d H:i:s'),
            'script_preview' => $scriptPreview,
            'scenes' => $scenes,
            'logs' => [],
            'message' => '时空场景分析完成'
        ];
        
        file_put_contents($resultFile, json_encode($finalResult, JSON_UNESCAPED_UNICODE), LOCK_EX);
        
    } catch (Exception $e) {
        error_log("时空场景分析失败: " . $e->getMessage());
        $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_FAILED, 0);
        
        $errorResult = [
            'task_id' => $taskId,
            'status' => 'error',
            'current_stage' => 'error',
            'progress' => 0,
            'start_time' => date('Y-m-d H:i:s'),
            'end_time' => date('Y-m-d H:i:s'),
            'script_preview' => $scriptPreview,
            'scenes' => [],
            'logs' => [],
            'message' => '时空场景分析失败: ' . $e->getMessage(),
            'error' => $e->getMessage()
        ];
        
        file_put_contents($resultFile, json_encode($errorResult, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
    
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
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
                (SELECT COUNT(*) FROM spaces s WHERE s.task_id = t.task_id) as scene_count
                FROM tasks t
                WHERE t.user_id = ? AND t.task_type = 'space_analysis'
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
                'scene_count' => (int)$task['scene_count']
            ];
        }
        
        echo json_encode(['tasks' => $formattedTasks], JSON_UNESCAPED_UNICODE);
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
            echo json_encode(['error' => '暂无当前时空场景任务，请先创建时空场景分析任务']);
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

    if (!preg_match('/^space_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
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

    // 从数据库中查询最新的时空场景数据
    $sql = "SELECT id, scene_id, name, description, imageUrl FROM spaces WHERE task_id = ? AND user_id = ? ORDER BY scene_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId, $userId]);
    $scenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 检查任务状态
    $sql = "SELECT status, progress FROM tasks WHERE task_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$taskId]);
    $taskStatus = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 将status转换为整数，确保比较正确
    $taskStatus['status'] = (int)$taskStatus['status'];
    
    $status = $taskStatus['status'] == TaskManager::STATUS_COMPLETED ? 'completed' : 
              ($taskStatus['status'] == TaskManager::STATUS_FAILED ? 'error' : 'processing');
    $progress = $taskStatus['progress'] ?? 100;
    $message = $status == 'completed' ? '时空场景分析完成' : 
              ($status == 'error' ? '时空场景分析失败' : '任务正在处理中');
    
    // 构建响应数据
    $response = [
        'task_id' => $taskId,
        'status' => $status,
        'progress' => $progress,
        'message' => $message,
        'current_stage' => $status,
        'scenes' => $scenes,
        'logs' => []
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $taskId = $_GET['task_id'] ?? '';
    $action = $_GET['action'] ?? '';
    
    if ($action === 'delete_all') {
        $sql = "SELECT task_id FROM tasks WHERE user_id = ? AND task_type = 'space_analysis'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $deletedCount = 0;
        foreach ($tasks as $task) {
            $taskTaskId = $task['task_id'];
            
            $deleteStmt = $pdo->prepare("DELETE FROM spaces WHERE task_id = ?");
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
    
    if (!preg_match('/^space_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
        echo json_encode(['error' => '无效的任务ID']);
        exit;
    }
    
    $sql = "SELECT user_id FROM tasks WHERE task_id = ? AND task_type = 'space_analysis'";
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
    
    $sql = "DELETE FROM spaces WHERE task_id = ?";
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
