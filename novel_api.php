<?php
// novel.php - 支持多轮对话和任务处理的小说转剧本系统
set_time_limit(600);
ini_set('memory_limit', '2G');

// 启动会话 - 必须在任何输出之前调用
session_start();

// 彻底清理缓冲区
while (ob_get_level()) ob_end_clean();

header('Content-Type: application/json');
header('Connection: close');

require_once 'config.php';
require_once 'Logger.php';
require_once 'DeepSeekClient.php';
require_once 'Auth.php';

// 确保输出目录存在
$outputsDir = __DIR__ . '/results';
if (!is_dir($outputsDir)) {
    if (!mkdir($outputsDir, 0755, true)) {
        echo json_encode(['error' => '无法创建输出目录']);
        exit;
    }
}

// 处理请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查Content-Type是否为application/json
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    if (strpos($contentType, 'application/json') !== false) {
        // 从JSON请求体中获取action
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $action = $data['action'] ?? '';
    } else {
        // 从POST参数中获取action
        $action = $_POST['action'] ?? '';
    }
    
    // 根据action执行不同操作
    switch ($action) {
        case 'start_conversion':
            startConversion();
            break;
            
        case 'check_status':
            checkStatus();
            break;
            
        case 'read_file':
            readGeneratedFile();
            break;
            
        case 'analyze':
            analyzeNovel();
            break;
            
        case 'delete_all_tasks':
            deleteAllTasks();
            break;
        
        case 'delete_task':
            deleteTask();
            break;
            
        default:
            // 默认处理小说转换
            handleNovelConversion();
            break;
    }
} else if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // 处理GET请求（轮询任务状态）
    $taskId = $_GET['task_id'] ?? '';
    
    if (!empty($taskId)) {
        // 安全检查：确保task_id只包含允许的字符
        if (!preg_match('/^script_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
            echo json_encode(['error' => '无效的任务ID']);
            exit;
        }
        
        $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
        
        if (file_exists($taskFile)) {
            $result = file_get_contents($taskFile);
            if ($result === false) {
                echo json_encode(['error' => '无法读取任务结果']);
            } else {
                echo $result;
            }
        } else {
            echo json_encode([
                'task_id' => $taskId,
                'status' => 'processing',
                'progress' => 0,
                'message' => '分析任务正在进行中...'
            ]);
        }
    } else {
        echo json_encode(['error' => '缺少任务ID参数']);
    }
    exit;
} else {
    echo json_encode(['error' => '不支持的请求方法']);
    exit;
}

/**
 * 开始转换任务
 */
function startConversion() {
    try {
        // 获取输入数据
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $text = $data['novel'] ?? getInputText();
        
        if (empty($text)) {
            echo json_encode(['error' => '小说内容不能为空']);
            exit;
        }
        
        // 检查文本长度
        $textLength = mb_strlen($text, 'UTF-8');
        if ($textLength < 100) {
            echo json_encode(['error' => '小说文本过短，请提供至少100字符的内容']);
            exit;
        }
        if ($textLength > 5500000) {
            echo json_encode(['error' => '小说文本过长，请提供小于5500,000字符的内容']);
            exit;
        }
        
        // 提前计算总轮次：将小说内容拆分为多个块
        $textChunks = splitTextIntoChunks($text);
        $maxRounds = count($textChunks);
        
        // 获取当前用户ID
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        // 检查用户积分是否足够
        $requiredPoints = Config::NOVEL_TO_SCRIPT_COST * $maxRounds;
        
        // 检查积分是否足够
        if (!$auth->checkUserPoints($userId, $requiredPoints)) {
            echo json_encode(['error' => "积分不足，无法进行小说转剧本操作。需要 {$requiredPoints} 积分，当前积分不足"]);
            exit;
        }
        
        // 生成唯一任务ID
        $taskId = uniqid('script_analysis_', true);
        $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
        
        // 立即创建任务状态文件
        $initialResult = [
            'task_id' => $taskId,
            'status' => 'processing',
            'start_time' => date('Y-m-d H:i:s'),
            'text_length' => $textLength,
            'message' => '转换任务已开始，请稍后查询结果'
        ];
        
        file_put_contents($taskFile, json_encode($initialResult, JSON_UNESCAPED_UNICODE));
        
        // 扣除积分，并传递taskId
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '小说转剧本', 'novel_to_script', $taskId);
        if (!$deductResult['success']) {
            echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
            exit;
        }
        
        // 立即返回任务ID给前端
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'processing',
            'user_id' => $userId,
            'message' => '转换任务已开始，请稍后查询结果'
        ]);
        
        // 确保输出发送到客户端
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            flush();
        }
        
        // 设置后台执行
        ignore_user_abort(true);
        set_time_limit(0);
        
        // 开始后台处理，传递用户ID、预计算的总轮次和文本块
        processNovelConversion($taskId, $text, $userId, $maxRounds, $textChunks);
        
    } catch (Exception $e) {
        echo json_encode(['error' => '处理出错: ' . $e->getMessage()]);
        exit;
    }
}

/**
 * 检查任务状态
 */
function checkStatus() {
    $taskId = $_POST['task_id'] ?? '';
    
    if (empty($taskId)) {
        echo json_encode(['error' => '任务ID不能为空']);
        exit;
    }
    
    // 安全检查：确保task_id只包含允许的字符
    if (!preg_match('/^script_analysis_[a-zA-Z0-9_.-]+$/', $taskId)) {
        echo json_encode(['error' => '无效的任务ID']);
        exit;
    }
    
    $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
    
    if (file_exists($taskFile)) {
        $result = file_get_contents($taskFile);
        if ($result === false) {
            echo json_encode(['error' => '无法读取任务结果']);
        } else {
            echo $result;
        }
    } else {
        echo json_encode([
            'task_id' => $taskId,
            'status' => 'processing',
            'message' => '分析任务正在进行中...'
        ]);
    }
}

/**
 * 处理小说转换任务（多轮对话）
 */
function processNovelConversion($taskId, $text, $userId, $maxRounds, $textChunks) {
    $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
    $logger = new Logger();
    
    try {
        $fullResponse = '';
        $completedRounds = 0;
        
        // 系统提示词
        $systemPrompt = "你是一个专业的小说转剧本编剧。请将用户提供的小说内容转换为标准的影视剧本格式。要求如下：
        1. 严格按照影视剧本格式编写，每个场景必须包含：场景号、内外景标识（如：内/外）、时间、地点
        2. 每个场景必须包含完整的场景描述、人物对话、动作描述、镜头提示等
        3. 必须完整保留小说中的所有情节、人物、对话、场景，不得遗漏任何内容
        4. 对话要符合人物性格和情节发展，动作描述要具体生动，便于拍摄
        5. 保持故事的连贯性和完整性，严格遵循原作的时间线和逻辑
        6. 保持原作的风格和基调，不得随意修改或添加原创内容
        7. 只返回标准剧本内容，不得包含任何多余的解释、续写、总结、说明或注释
        8. 不得添加任何关于剧本创作的额外说明或建议
        9. 不得包含任何形式的自我介绍或开场白
        10. 必须严格按照格式要求输出，不得有任何偏离";
        
        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ]
        ];

        // 使用TaskManager管理任务
        $taskManager = null;
        $dbTaskId = null;
        try {
            require_once __DIR__ . '/TaskManager.php';
            $taskManager = TaskManager::getInstance();
            
            // 创建任务，允许userId为null，使用外部taskId作为核心task_id
            // 创建简单可靠的input_data，只包含必要的基本数据
            $textLength = mb_strlen($text, 'UTF-8');
            
            // 进一步简化input_data，只包含最基本的、绝对可靠的数字和字符串
            // 避免任何可能导致JSON编码失败的复杂数据
            $inputData = [
                'text_length' => (int)$textLength,  // 确保是整数类型
                'max_rounds' => (int)$maxRounds,    // 确保是整数类型
                'task_type' => 'novel_to_script',   // 简单字符串
                'content' => $text,
                'created_at' => date('Y-m-d H:i:s') // 标准日期格式字符串
            ];
            
            // 移除可能导致问题的text_preview字段，进一步简化数据结构
            // 只保留绝对必要的数据，确保JSON编码一定成功
       
            // 直接在novel_api.php中尝试JSON编码，验证数据可用性
            $testJson = json_encode($inputData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            
            $dbTaskId = $taskManager->createTask(
                $userId,
                TaskManager::TYPE_NOVEL_TO_SCRIPT,
                '小说转剧本',
                $inputData,
                [],
                $taskId // 使用外部taskId作为核心task_id
            );
            
            error_log("novel_api.php - Task created with dbTaskId: $dbTaskId");
            
            // 更新任务状态为处理中
            $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, 0);
        } catch (Exception $e) {
            // 数据库操作失败时，记录错误但继续执行
            $logger->error("Failed to initialize TaskManager: " . $e->getMessage());
        }

        // 先检查API配置
        try {
            $logger->info("检查API配置");
            $client = new DeepSeekClient($logger);
            // 使用反射调用私有方法检查API配置
            $reflection = new ReflectionClass($client);
            $method = $reflection->getMethod('checkApiConfig');
            $method->setAccessible(true);
            $method->invoke($client);
            $logger->info("API配置检查通过");
        } catch (Exception $e) {
            $logger->error("API配置检查失败: " . $e->getMessage());
            saveErrorResult($taskId, "API配置错误: " . $e->getMessage());
            return;
        }
        
        // 处理每个文本块
        for ($round = 1; $round <= $maxRounds; $round++) {
            // 更新进度
            $progress = round(($round / $maxRounds) * 100, 2);
            $progressResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => $round,
                'total_rounds' => $maxRounds,
                'progress' => $progress,
                'message' => "正在进行第{$round}轮转换，总轮次{$maxRounds}，请耐心等待（关闭页面不影响任务运行）...",
                'content' => $fullResponse,
                'rounds' => $round - 1
            ];
            
            file_put_contents($taskFile, json_encode($progressResult, JSON_UNESCAPED_UNICODE));
            
            // 更新进度到数据库
            if ($taskManager && $dbTaskId) {
                try {
                    $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, $progress);
                    $taskManager->updateTaskProgress($dbTaskId, $progress, "正在进行第{$round}/{$maxRounds}轮转换");
                } catch (Exception $e) {
                    $logger->error("Failed to update task progress: " . $e->getMessage());
                }
            }
            
            // 构建当前轮次的消息
            $userMessage = buildUserMessage($round, $textChunks);
            $messages[] = ['role' => 'user', 'content' => $userMessage];
            
            // 清理过长的消息历史以节省token
            $messages = cleanupMessageHistory($messages);
            
            // 调用API
            try {
                $content = callDeepSeekAPI($messages);
                $fullResponse .= $content . "\n\n";
                $completedRounds = $round;
                
                // 添加到对话历史
                $messages[] = ['role' => 'assistant', 'content' => $content];
                
                // 添加延迟避免频繁请求
                sleep(2);
            } catch (Exception $e) {
                $logger->error("API调用失败: " . $e->getMessage());
                saveErrorResult($taskId, "API调用失败: " . $e->getMessage());
                return;
            }
        }
        
        // 保存最终结果到文件
        $filename = 'final_script_' . date('Y-m-d_H-i-s') . '.txt';
        $filepath = Config::OUTPUT_DIR . $filename;
        file_put_contents($filepath, $fullResponse);
        
        // 保存最终结果
        $finalResult = [
            'task_id' => $taskId,
            'status' => 'completed',
            'start_time' => date('Y-m-d H:i:s', filemtime($taskFile)),
            'end_time' => date('Y-m-d H:i:s'),
            'text_length' => mb_strlen($text, 'UTF-8'),
            'content' => mb_strlen($fullResponse, 'UTF-8') > 5000000 
                ? mb_substr($fullResponse, 0, 5000000, 'UTF-8') 
                : $fullResponse,
            'rounds' => $completedRounds,
            'filename' => $filename,
            'download_url' => 'download.php?file=' . $filename,
            'file_size' => filesize($filepath),
            'message' => '小说转剧本任务已完成'
        ];
        
        file_put_contents($taskFile, json_encode($finalResult, JSON_UNESCAPED_UNICODE));
        
        // 更新任务状态为完成到数据库
        if ($taskManager && $dbTaskId) {
            try {
                $outputData = [
                    'script_content' => $finalResult['content'],
                    'rounds' => $completedRounds,
                    'filename' => $filename,
                    'download_url' => $finalResult['download_url'],
                    'file_size' => $finalResult['file_size'],
                    'message' => $finalResult['message']
                ];
                
                // 更新任务状态为已完成
                $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_COMPLETED, 100, $outputData);
                
                // 创建剧本记录
                $taskManager->createScript($dbTaskId, $finalResult['content'], '小说转剧本_' . date('Y-m-d'), '系统自动生成');
            } catch (Exception $e) {
                $logger->error("Failed to update task completion status: " . $e->getMessage());
            }
        }
        
    } catch (Exception $e) {
        saveErrorResult($taskId, $e->getMessage());
    }
}

/**
 * 构建用户消息
 */
function buildUserMessage($round, $textChunks) {
    if ($round === 1) {
        $currentChunk = $textChunks[0] ?? '';
        return "小说第一部分：
{$currentChunk}

请严格按照系统提示词要求，开始转换为标准影视剧本。必须完整保留所有情节，不得遗漏任何内容。只返回剧本内容，不得有任何多余解释或说明。";
    } elseif ($round < count($textChunks)) {
        $currentChunk = $textChunks[$round - 1];
        return "小说下一部分：
{$currentChunk}

请继续严格按照系统提示词要求转换为标准影视剧本。必须完整保留所有情节，保持与前面内容的连贯性。只返回剧本内容，不得有任何多余解释或说明。";
    } else {
        $currentChunk = $textChunks[$round - 1];
        return "小说最后一部分：
{$currentChunk}

请严格按照系统提示词要求完成转换，生成完整的标准影视剧本。必须完整保留所有情节，确保故事连贯性和完整性。只返回剧本内容，不得有任何多余解释、总结或说明。";
    }
}

/**
 * 清理消息历史
 */
function cleanupMessageHistory($messages) {
    // 保留系统消息和最近几轮对话
    if (count($messages) > 10) {
        $cleanedMessages = [
            $messages[0], // 系统消息
        ];
        
        // 添加最近的几轮对话
        $recentMessages = array_slice($messages, -8);
        foreach ($recentMessages as $message) {
            $cleanedMessages[] = $message;
        }
        
        return $cleanedMessages;
    }
    return $messages;
}

/**
 * 调用DeepSeek API
 */
function callDeepSeekAPI($messages) {
    $logger = new Logger();
    $client = new DeepSeekClient($logger);
    
    // 构建对话上下文
    $prompt = '';
    foreach ($messages as $message) {
        $prompt .= $message['role'] . ': ' . $message['content'] . "\n\n";
    }
    
    // 调用API
    $response = $client->callApi($prompt, null, 0.7);
    return $response;
}

/**
 * 拆分文本为多个块
 */
function splitTextIntoChunks($text, $maxChunkSize = 3000) {
    // 如果文本不长，直接返回
    if (mb_strlen($text, 'UTF-8') <= $maxChunkSize) {
        return [$text];
    }
    
    $chunks = [];
    
    // 按章节拆分（支持中文章节标记）
    $chapterPattern = '/(?:^|\n)(第[一二三四五六七八九十百千万]+[章节卷集部篇回]|Chapter|CHAPTER)/u';
    $chapters = preg_split($chapterPattern, $text);
    
    // 保留分隔符
    preg_match_all($chapterPattern, $text, $matches, PREG_OFFSET_CAPTURE);
    
    $currentChunk = '';
    $chapterIndex = 0;
    
    foreach ($chapters as $index => $chapter) {
        if ($index === 0 && empty(trim($chapter))) {
            continue;
        }
        
        // 添加回分隔符
        if ($index > 0 && isset($matches[0][$index-1])) {
            $chapter = $matches[0][$index-1][0] . $chapter;
        }
        
        $chapterLength = mb_strlen($chapter, 'UTF-8');
        
        // 如果单个章节就很大，需要进一步拆分
        if ($chapterLength > $maxChunkSize) {
            $subChunks = splitLargeChapter($chapter, $maxChunkSize);
            foreach ($subChunks as $subChunk) {
                if (mb_strlen($currentChunk . $subChunk, 'UTF-8') > $maxChunkSize && !empty($currentChunk)) {
                    $chunks[] = $currentChunk;
                    $currentChunk = $subChunk;
                } else {
                    $currentChunk .= $subChunk;
                }
            }
        } else {
            if (mb_strlen($currentChunk . $chapter, 'UTF-8') > $maxChunkSize && !empty($currentChunk)) {
                $chunks[] = $currentChunk;
                $currentChunk = $chapter;
            } else {
                $currentChunk .= $chapter;
            }
        }
    }
    
    if (!empty($currentChunk)) {
        $chunks[] = $currentChunk;
    }
    
    return $chunks;
}

/**
 * 拆分大章节
 */
function splitLargeChapter($chapter, $maxChunkSize) {
    $subChunks = [];
    $paragraphs = preg_split('/\n\s*\n/', $chapter);
    
    $currentSubChunk = '';
    foreach ($paragraphs as $paragraph) {
        $paragraphLength = mb_strlen($paragraph, 'UTF-8');
        
        if (mb_strlen($currentSubChunk . $paragraph, 'UTF-8') > $maxChunkSize && !empty($currentSubChunk)) {
            $subChunks[] = $currentSubChunk;
            $currentSubChunk = $paragraph . "\n\n";
        } else {
            $currentSubChunk .= $paragraph . "\n\n";
        }
    }
    
    if (!empty($currentSubChunk)) {
        $subChunks[] = $currentSubChunk;
    }
    
    return $subChunks;
}

/**
 * 保存错误结果
 */
function saveErrorResult($taskId, $errorMessage) {
    $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
    
    // 检查错误信息是否包含 API 配置问题
    $userFriendlyError = $errorMessage;
    if (strpos($errorMessage, 'API Key 未配置') !== false || 
        strpos($errorMessage, 'API URL 未配置') !== false || 
        strpos($errorMessage, 'API Model 未配置') !== false) {
        $userFriendlyError = "API 配置不完整，请联系管理员配置 API 密钥和 URL";
    } elseif (strpos($errorMessage, 'HTTP错误: 400') !== false) {
        $userFriendlyError = "API 请求失败，请检查 API 配置是否正确";
    }
    
    $errorResult = [
        'task_id' => $taskId,
        'status' => 'error',
        'start_time' => file_exists($taskFile) ? date('Y-m-d H:i:s', filemtime($taskFile)) : date('Y-m-d H:i:s'),
        'end_time' => date('Y-m-d H:i:s'),
        'content' => '',
        'error' => $userFriendlyError
    ];
    
    file_put_contents($taskFile, json_encode($errorResult, JSON_UNESCAPED_UNICODE));
    
    // 更新数据库中任务状态为失败
    try {
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = TaskManager::getInstance();
        
        // 根据外部task_id获取任务
        $task = $taskManager->getTaskByExternalId($taskId);
        if ($task) {
            $taskManager->updateTaskStatus($task['task_id'], TaskManager::STATUS_FAILED, 0, null, $userFriendlyError);
        }
    } catch (Exception $e) {
        // 忽略错误，继续执行
    }
}

/**
 * 获取输入文本
 */
function getInputText() {
    if (isset($_POST['novel_text'])) {
        return $_POST['novel_text'];
    }
    
    if (isset($_FILES['novel_file']) && $_FILES['novel_file']['error'] === UPLOAD_ERR_OK) {
        return file_get_contents($_FILES['novel_file']['tmp_name']);
    }
    
    return '';
}

/**
 * 处理默认小说转换请求
 */
function handleNovelConversion() {
    // 获取输入数据
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    $text = $data['novel'] ?? getInputText();
    
    // 检查文本长度
    $textLength = mb_strlen($text, 'UTF-8');
    if ($textLength < 100) {
        echo json_encode(['error' => '小说文本过短，请提供至少100字符的内容']);
        exit;
    }
    if ($textLength > 5500000) {
        echo json_encode(['error' => '小说文本过长，请提供小于5500,000字符的内容']);
        exit;
    }
    
    // 提前计算总轮次：将小说内容拆分为多个块
    $textChunks = splitTextIntoChunks($text);
    $maxRounds = count($textChunks);
    
    // 获取当前用户ID
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    
    // userId为null时不执行任务
    if (!$userId) {
        echo json_encode(['error' => '用户未登录，无法执行任务']);
        exit;
    }
    
    // 生成唯一任务ID
    $taskId = uniqid('script_analysis_', true);
    
    // 检查用户积分是否足够
    $requiredPoints = Config::NOVEL_TO_SCRIPT_COST * $maxRounds;
    
    // 检查积分是否足够
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        echo json_encode(['error' => "积分不足，无法进行小说转剧本操作。需要 {$requiredPoints} 积分，当前积分不足"]);
        exit;
    }
    
    // 扣除积分，并传递taskId
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '小说转剧本', 'novel_to_script', $taskId);
    if (!$deductResult['success']) {
        echo json_encode(['error' => '积分扣除失败：' . $deductResult['message']]);
        exit;
    }
    
    // 立即创建任务状态文件
    $taskFile = Config::OUTPUT_DIR . $taskId . '.json';
    $initialResult = [
        'task_id' => $taskId,
        'status' => 'processing',
        'start_time' => date('Y-m-d H:i:s'),
        'text_length' => $textLength,
        'message' => '转换任务已开始，请稍后查询结果'
    ];
    
    file_put_contents($taskFile, json_encode($initialResult, JSON_UNESCAPED_UNICODE));
    
    // 立即返回任务ID给前端
    echo json_encode([
        'task_id' => $taskId,
        'status' => 'processing',
        'user_id' => $userId,
        'message' => '转换任务已开始，请稍后查询结果'
    ]);
    
    // 确保输出发送到客户端
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        if (ob_get_level() > 0) {
            ob_end_flush();
        }
        flush();
    }
    
    // 设置后台执行
    ignore_user_abort(true);
    set_time_limit(0);
    
    // 开始后台处理，传递用户ID、预计算的总轮次和文本块
    processNovelConversion($taskId, $text, $userId, $maxRounds, $textChunks);
}

/**
 * 删除所有任务
 */
function deleteAllTasks() {
    try {
        // 获取当前用户ID
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => '用户未登录']);
            exit;
        }
        
        // 使用TaskManager删除所有任务
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = TaskManager::getInstance();
        
        // 直接使用TaskManager的方法或重新初始化Database获取PDO连接
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 删除用户的所有小说转剧本任务
        $sql = "DELETE FROM tasks WHERE user_id = ? AND task_type = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, TaskManager::TYPE_NOVEL_TO_SCRIPT]);
        
        // 返回成功响应
        echo json_encode(['success' => true, 'message' => '所有任务已删除']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => '删除失败：' . $e->getMessage()]);
    }
}

/**
 * 删除单个任务
 */
function deleteTask() {
    try {
        // 获取请求数据
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);
        $taskId = $data['task_id'] ?? '';
        
        if (empty($taskId)) {
            echo json_encode(['success' => false, 'error' => '缺少任务ID']);
            exit;
        }
        
        // 获取当前用户ID
        $auth = new Auth();
        $userId = $auth->getCurrentUserId();
        
        if (!$userId) {
            echo json_encode(['success' => false, 'error' => '用户未登录']);
            exit;
        }
        
        // 使用TaskManager删除单个任务
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = TaskManager::getInstance();
        
        // 直接使用Database获取PDO连接
        require_once __DIR__ . '/Database.php';
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 验证任务是否属于当前用户
        $sql = "SELECT id FROM tasks WHERE task_id = ? AND user_id = ? AND task_type = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId, $userId, TaskManager::TYPE_NOVEL_TO_SCRIPT]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$task) {
            echo json_encode(['success' => false, 'error' => '任务不存在或无权删除']);
            exit;
        }
        
        // 删除任务相关数据
        $pdo->beginTransaction();
        
        // 删除剧本记录
        $pdo->exec("DELETE FROM scripts WHERE task_id = '$taskId'");
        
        // 删除任务日志
        $pdo->exec("DELETE FROM task_logs WHERE task_id = '$taskId'");
        
        // 删除任务详情
        $pdo->exec("DELETE FROM task_details WHERE task_id = '$taskId'");
        
        // 删除任务主记录
        $sql = "DELETE FROM tasks WHERE task_id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId, $userId]);
        
        $pdo->commit();
        
        // 返回成功响应
        echo json_encode(['success' => true, 'message' => '任务已删除']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => '删除失败：' . $e->getMessage()]);
    }
}

/**
 * 原始的直接文件转换函数（保持向后兼容）
 */
function directFileConvert($text) {
    // 添加积分检查逻辑
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    $requiredPoints = Config::NOVEL_TO_SCRIPT_COST;
    
    // 生成taskId
    $taskId = uniqid('script_analysis_', true);
    
    if ($userId && !$auth->checkUserPoints($userId, $requiredPoints)) {
        return [
            'success' => false,
            'error' => "积分不足，无法进行小说转剧本操作。需要 {$requiredPoints} 积分，当前积分不足"
        ];
    }
    
    // 扣除积分，并传递taskId
    if ($userId) {
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '小说转剧本', 'novel_to_script_direct', $taskId);
        if (!$deductResult['success']) {
            return [
                'success' => false,
                'error' => '积分扣除失败：' . $deductResult['message']
            ];
        }
    }
    
    // 原有转换逻辑
    $logger = new Logger();
    $prompt = "你是一个专业的小说转剧本编剧。请将以下完整小说转换为标准的影视剧本格式，要求如下：\n1. 严格按照影视剧本格式编写，每个场景必须包含：场景号、内外景标识（如：内/外）、时间、地点\n2. 必须完整保留小说中的所有情节、人物、对话、场景，不得遗漏任何内容\n3. 对话要符合人物性格和情节发展，动作描述要具体生动，便于拍摄\n4. 保持故事的连贯性和完整性，严格遵循原作的时间线和逻辑\n5. 保持原作的风格和基调，不得随意修改或添加原创内容\n6. 只返回标准剧本内容，不得包含任何多余的解释、续写、总结、说明或注释\n7. 不得添加任何关于剧本创作的额外说明或建议\n8. 不得包含任何形式的自我介绍或开场白\n\n小说内容：\n{$text}";
    
    $client = new DeepSeekClient($logger);
    $script = $client->callApi($prompt, "专业编剧", 0.3);
    
    // 直接保存文件，返回文件信息
    $filename = 'script_analysis_' . date('Y-m-d_H-i-s') . '.txt';
    $filepath = Config::OUTPUT_DIR . $filename;
    file_put_contents($filepath, $script);
    
    return [
        'success' => true,
        'data' => [
            'final_script' => $script, // 添加剧本内容直接返回
            'download_url' => 'download.php?file=' . $filename,
            'file_size' => filesize($filepath),
            'filename' => $filename,
            'message' => '剧本已生成并保存'
        ]
    ];
}

// 添加文件读取函数
function readGeneratedFile() {
    $filename = $_POST['filename'] ?? '';
    $filepath = Config::OUTPUT_DIR . $filename;
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => '文件不存在']);
        exit;
    }
    
    $content = file_get_contents($filepath);
    echo json_encode([
        'success' => true,
        'data' => [
            'content' => $content,
            'filename' => $filename,
            'file_size' => filesize($filepath)
        ]
    ]);
    exit;
}

/**
 * 小说分析功能
 */
function analyzeNovel() {
    $text = getInputText();
    $analysisType = $_POST['analysis_type'] ?? 'quick';
    $result = analyzeNovelFunction($text, $analysisType);
    echo json_encode($result);
    exit;
}

function analyzeNovelFunction($text, $analysisType = 'quick') {
    $logger = new Logger();
    
    if ($analysisType === 'detailed') {
        $prompt = "请对以下小说进行详细的分拆潜力分析，包括：
        1. 整体结构评估
        2. 情节线分析
        3. 角色体系分析
        4. 分拆可行性评分
        5. 详细的分拆方案
        6. 潜在风险和挑战
        
        小说内容：\n\n{$text}";
    } else {
        $prompt = "请对以下小说进行快速分拆潜力分析，提供：
        1. 分拆可行性评分（1-10分）
        2. 简要评估
        3. 推荐方案
        
        小说内容：\n\n{$text}";
    }
    
    $client = new DeepSeekClient($logger);
    $analysisResult = $client->callApi($prompt, "剧本分析专家", 0.3);
    
    // 解析分析结果（这里需要根据实际的API返回格式调整）
    $analysisData = [
        'final_assessment' => [
            'overall_score' => 8.5,
            'splitting_feasibility' => '高',
            'recommended_approach' => '按情节弧分拆',
            'estimated_parts' => '3-4部',
            'reasons' => ['情节结构清晰', '角色发展完整', '有自然的分段点'],
            'risks' => ['部分过渡需要调整', '需要补充连接剧情'],
            'splitting_strategy' => ['第一部：建立世界观', '第二部：冲突发展', '第三部：高潮结局']
        ],
        'analysis' => json_decode($analysisResult, true) ?: $analysisResult
    ];
    
    // 保存分析结果到文件
    $filename = 'script_analysis_' . $analysisType . '_' . date('Y-m-d_H-i-s') . '.json';
    $filepath = Config::OUTPUT_DIR . $filename;
    file_put_contents($filepath, json_encode($analysisData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    return [
        'success' => true,
        'data' => [
            'final_assessment' => $analysisData['final_assessment'],
            'analysis' => $analysisData['analysis'],
            'download_url' => 'download.php?file=' . $filename,
            'file_size' => filesize($filepath),
            'filename' => $filename,
            'message' => $analysisType === 'detailed' ? '详细分析完成' : '快速分析完成'
        ]
    ];
}
?>
