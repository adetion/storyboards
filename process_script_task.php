<?php

/**
 * 处理剧本转分镜任务的脚本
 * 支持直接调用或命令行执行
 */

// 设置最大执行时间
set_time_limit(0);

// 增加内存限制
ini_set('memory_limit', '512M');

// 引入必要的文件
dirname(__FILE__);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/TaskManager.php';

/**
 * 处理脚本任务的主函数
 * @param string $taskParamsFile 任务参数文件路径
 * @return bool 是否成功
 */
function processScriptTask($taskParamsFile)
{
    // 添加详细日志输出，便于调试
    $logFile = __DIR__ . '/process_script_task.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task started with params file: {$taskParamsFile}\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PHP version: " . phpversion() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Current working directory: " . getcwd() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script path: " . __DIR__ . "/process_script_task.php\n", FILE_APPEND);

    // 检查文件是否存在
    if (!file_exists($taskParamsFile)) {
        $errorMsg = "Task params file not found: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    // 读取任务参数
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Reading task params from file: {$taskParamsFile}\n", FILE_APPEND);
    $taskParamsJson = file_get_contents($taskParamsFile);

    if ($taskParamsJson === false) {
        $errorMsg = "Failed to read task params file: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    // 记录文件内容长度
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task params file size: " . strlen($taskParamsJson) . " bytes\n", FILE_APPEND);

    $taskParams = json_decode($taskParamsJson, true);

    // 检查JSON解析是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Failed to parse task params: " . json_last_error_msg() . "\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        // 记录无法解析的JSON内容的前100个字符，便于调试
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] First 100 chars of unparseable JSON: " . substr($taskParamsJson, 0, 100) . "\n", FILE_APPEND);
        return false;
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task params parsed successfully\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task ID: {$taskParams['task_id']}\n", FILE_APPEND);

    // 任务主逻辑
    $taskId = $taskParams['task_id'];
    $script = $taskParams['script'];
    $prompt = $taskParams['prompt'];
    $scriptChunks = $taskParams['script_chunks'];
    $maxRounds = $taskParams['max_rounds'];
    $userId = $taskParams['user_id'];

    $resultsDir = __DIR__ . '/results';
    $resultFile = $resultsDir . '/' . $taskId . '.json';

    try {
        // 获取API密钥
        $apiKey = Config::DEEPSEEK_API_KEY();

        if (empty($apiKey)) {
            throw new Exception('请配置正确的DeepSeek API密钥');
        }

        // 使用TaskManager管理任务
        $taskManager = TaskManager::getInstance();
        $dbTaskId = null;

        try {
            // 检查任务是否已经存在
            $existingTask = $taskManager->getTaskByExternalId($taskId);

            if ($existingTask) {
                // 任务已经存在，直接使用已有的任务ID
                $dbTaskId = $existingTask['id'];
                error_log("Task already exists, using existing task ID: {$dbTaskId}");
            } else {
                // 创建任务，使用外部taskId作为核心task_id
                $scriptLength = mb_strlen($script, 'UTF-8');
                $inputData = [
                    'script_length' => (int)$scriptLength,
                    'max_rounds' => (int)$maxRounds,
                    'task_type' => 'script_to_storyboard',
                    'created_at' => date('Y-m-d H:i:s')
                ];

                $dbTaskId = $taskManager->createTask(
                    $userId,
                    TaskManager::TYPE_SCRIPT_TO_STORYBOARD,
                    '剧本转分镜',
                    $inputData,
                    [],
                    $taskId
                );
            }

            // 更新任务状态为处理中 - 使用外部任务ID $taskId
            $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_PROCESSING, 0);
        } catch (Exception $e) {
            error_log("TaskManager - 任务管理初始化失败: " . $e->getMessage());
        }

        // ===== 跳过角色分析，直接进入分镜分析 =====
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 跳过角色分析，直接进入分镜分析\n", FILE_APPEND);
        
        // 更新任务进度，直接开始分镜分析
        $taskManager->updateTaskProgress($taskId, 10, "开始分镜分析");
        
        // 写入分镜分析进度文件
        $storyboardProgressResult = [
            'task_id' => $taskId,
            'status' => 'processing',
            'progress' => 10,
            'message' => '开始分镜分析，请稍候...',
            'content' => '',
            'is_storyboard_analysis' => true  // 标记为分镜分析阶段
        ];
        $jsonProgress = json_encode($storyboardProgressResult, JSON_UNESCAPED_UNICODE);
        if ($jsonProgress !== false) {
            file_put_contents($resultFile, $jsonProgress, LOCK_EX);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已写入分镜分析进度文件\n", FILE_APPEND);
        }
        
        // 跳过分镜分析部分的执行，直接进入下一个阶段
        goto storyboard_analysis_start;
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始角色分析\n", FILE_APPEND);
        
        // 更新任务进度为角色分析中
        $taskManager = TaskManager::getInstance();
        $taskManager->updateTaskProgress($taskId, 5, "开始角色分析");
        
        // 写入角色分析进度文件
        $characterProgressResult = [
            'task_id' => $taskId,
            'status' => 'processing',
            'progress' => 5,
            'message' => '正在进行角色分析，请稍候...',
            'content' => '',
            'is_character_analysis' => true  // 标记为角色分析阶段
        ];
        $jsonProgress = json_encode($characterProgressResult, JSON_UNESCAPED_UNICODE);
        if ($jsonProgress !== false) {
            file_put_contents($resultFile, $jsonProgress, LOCK_EX);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已写入角色分析进度文件\n", FILE_APPEND);
        }
        
        try {
            // 获取数据库连接
            $db = Database::getInstance();
            $pdo = $db->getPdo();
            
            // 获取用户的当前剧组ID
            $sql = "SELECT id, current_task_id FROM crew WHERE admin_user_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$userId]);
            $crew = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $crewId = null;
            if ($crew) {
                $crewId = $crew['id'];
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 找到剧组ID: {$crewId}\n", FILE_APPEND);
            } else {
                // 用户没有剧组，创建一个默认剧组
                $sql = "INSERT INTO crew (admin_user_id, name, status, current_task_id, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, NOW(), NOW())";
                $stmt = $pdo->prepare($sql);
                $defaultCrewName = "{$userId}的默认剧组";
                $stmt->execute([$userId, $defaultCrewName, 1, $taskId]);
                $crewId = $pdo->lastInsertId();
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 创建新剧组ID: {$crewId}\n", FILE_APPEND);
            }
            
            // 构建角色分析prompt
            $characterAnalysisPrompt = '【重要：这是一个全新的角色分析任务，与分镜分析完全无关！请只进行角色分析，绝对不要输出任何分镜分析内容！】

请从剧本中提炼出所有角色信息，并严格按照以下Markdown格式输出：

《剧本标题》角色设定及服装妆造分析

【角色分析格式】
请按角色顺序逐个输出，每个角色包含以下完整信息：

1. 马小川
- 角色描述：角色的基本描述
- 服装：服装描述
- 妆造：妆造描述
- 年龄跨度：年龄变化
- 身份变化：身份变化
- 核心特质：核心性格特点
- 关键经历：重要经历
- 起点：初始状态
- 转折：重要转折点
- 低谷：低谷时期
- 觉醒：觉醒时刻
- 成长：成长过程
- 高潮：高潮时刻
- 终点：最终状态
- 关系类型：与某角色的关系类型
- 关系描述：与某角色的关系详细描述

2. 角色名2
- 角色描述：...
（继续列出所有角色，每个角色都要包含以上所有字段）

【严格格式要求】
1. 必须按角色顺序逐个输出，每个角色包含完整的所有字段
2. 角色标题格式：必须使用 `数字. 角色名称` 格式，例如 `1. 马小川`
3. 角色名称必须是纯文本，不能包含 `**` 标记、不能包含描述信息
4. 列表项必须使用 `-` 前缀，格式为 `- 字段名：内容`
5. 只输出Markdown格式，绝对禁止表格格式
6. 只输出角色分析内容，绝对不要输出任何分镜分析内容
7. 每个角色都要包含完整的所有字段信息
8. 服装和妆造要符合剧本世界观及时代背景
9. 未提及的服装妆造也要根据角色设定合理推断提供
10. 不要输出"可能"、"大概"等不确定的字眼
11. 不要输出任何分镜分析相关的字段（如场次号、镜号、景别等）
12. 只输出角色相关的信息（角色名称、服装、妆造、设定、弧光、关系）
13. 确保每个角色都有完整的描述信息
14. 关系信息可以重复出现（如角色A与角色B的关系，角色B与角色A的关系）
15. 如果某个角色没有某个字段的信息，可以省略该字段，但不要输出空值
';
            
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 调用角色分析API\n", FILE_APPEND);
            
            // 调用DeepSeek API进行角色分析
            $characterMessages = [
                [
                    'role' => 'system',
                    'content' => $characterAnalysisPrompt
                ],
                [
                    'role' => 'user',
                    'content' => $script
                ]
            ];
            
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始调用API，剧本长度: " . strlen($script) . " 字符\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API_KEY: " . $apiKey . " \nAPI_URL：". Config::DEEPSEEK_API_URL (), FILE_APPEND);
            
            try {
                $characterResponse = pst_callDeepSeekAPIWithRetry($apiKey, $characterMessages, 0);
                
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析API返回，大小: " . strlen($characterResponse) . " 字节\n", FILE_APPEND);
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API返回内容前500字符: " . substr($characterResponse, 0, 500) . "\n", FILE_APPEND);
                
                // 解析角色分析结果
                $characterData = json_decode($characterResponse, true);
                
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] JSON解析结果: " . (json_last_error() === JSON_ERROR_NONE ? '成功' : json_last_error_msg()) . "\n", FILE_APPEND);
                
                // 检查是否是JSON格式
                if (json_last_error() === JSON_ERROR_NONE && isset($characterData['choices']) && isset($characterData['choices'][0]) && isset($characterData['choices'][0]['message']['content'])) {
                    // JSON格式
                    $characterContent = $characterData['choices'][0]['message']['content'];
                } else {
                    // 直接使用返回的内容（可能是Markdown格式）
                    $characterContent = $characterResponse;
                }
                
                // 检查内容是否完整（如果不完整，发送"继续"获取剩余内容）
                $maxContinueAttempts = 3;
                $continueAttempt = 0;
                
                while ($continueAttempt < $maxContinueAttempts) {
                    $isIncomplete = false;
                    $reason = '';
                    
                    // 1. 检查API返回的finish_reason（如果是length，说明被截断）
                    if (isset($characterData['choices'][0]['finish_reason']) && $characterData['choices'][0]['finish_reason'] === 'length') {
                        $isIncomplete = true;
                        $reason = 'API返回finish_reason为length，内容被截断';
                    }
                    
                    // 2. 检查内容是否在句子中间结束
                    $trimmedContent = trim($characterContent);
                    $lastChar = mb_substr($trimmedContent, -1);
                    if (!in_array($lastChar, ['。', '！', '？', '：', '；'])) {
                        $isIncomplete = true;
                        $reason = '内容在句子中间结束，最后一个字符: ' . $lastChar;
                    }
                    
                    // 3. 检查内容是否在章节中间结束
                    $lines = explode("\n", $characterContent);
                    $lastNonEmptyLine = '';
                    foreach (array_reverse($lines) as $line) {
                        $trimmedLine = trim($line);
                        if (!empty($trimmedLine)) {
                            $lastNonEmptyLine = $trimmedLine;
                            break;
                        }
                    }
                    // 如果最后一行是章节标题（以数字开头），说明不完整
                    if (preg_match('/^\d+[\.\、]/', $lastNonEmptyLine)) {
                        $isIncomplete = true;
                        $reason = '最后一行是章节标题，内容不完整';
                    }
                    
                    // 4. 检查是否有未闭合的括号或引号
                    $openBrackets = substr_count($characterContent, '（') - substr_count($characterContent, '）');
                    $openParens = substr_count($characterContent, '(') - substr_count($characterContent, ')');
                    $openQuotes = substr_count($characterContent, '"') % 2;
                    
                    if ($openBrackets > 0 || $openParens > 0 || $openQuotes > 0) {
                        $isIncomplete = true;
                        $reason = '存在未闭合的括号或引号';
                    }
                    
                    // 5. 检查内容长度是否异常短（可能被截断）
                    if (strlen($characterContent) < 1000) {
                        $isIncomplete = true;
                        $reason = '内容长度过短，可能被截断';
                    }
                    
                    // 6. 检查是否缺少关键章节
                    $hasBasicSection = preg_match('/一、所有角色/', $characterContent);
                    $hasClothingSection = preg_match('/服装|妆造/', $characterContent);
                    $hasArcSection = preg_match('/弧光|人物弧光/', $characterContent);
                    $hasRelationshipSection = preg_match('/关系|人物关系/', $characterContent);
                    
                    if ($hasBasicSection && !$hasClothingSection && !$hasArcSection && !$hasRelationshipSection) {
                        $isIncomplete = true;
                        $reason = '只有基本信息章节，缺少其他章节';
                    }
                    
                    if (!$isIncomplete) {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 内容完整性检查通过，无需继续\n", FILE_APPEND);
                        break;
                    }
                    
                    $continueAttempt++;
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 检测到内容不完整（{$reason}），发送继续获取剩余内容（第{$continueAttempt}次尝试）\n", FILE_APPEND);
                    
                    // 添加"继续"消息
                    $continueMessages = array_merge($characterMessages, [
                        ['role' => 'assistant', 'content' => $characterContent],
                        ['role' => 'user', 'content' => '继续']
                    ]);
                    
                    // 调用API获取剩余内容
                    $continueResponse = pst_callDeepSeekAPIWithRetry($apiKey, $continueMessages, 0);
                    
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 继续API返回，大小: " . strlen($continueResponse) . " 字节\n", FILE_APPEND);
                    
                    // 解析继续返回的内容
                    $continueData = json_decode($continueResponse, true);
                    
                    if (json_last_error() === JSON_ERROR_NONE && isset($continueData['choices']) && isset($continueData['choices'][0]) && isset($continueData['choices'][0]['message']['content'])) {
                        $continueContent = $continueData['choices'][0]['message']['content'];
                        // 更新characterData以便下次检查finish_reason
                        $characterData = $continueData;
                    } else {
                        $continueContent = $continueResponse;
                    }
                    
                    // 检查继续返回的内容是否有效
                    if (empty(trim($continueContent))) {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 继续返回内容为空，停止继续\n", FILE_APPEND);
                        break;
                    }
                    
                    // 合并内容
                    $characterContent .= "\n" . $continueContent;
                    
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 合并后内容大小: " . strlen($characterContent) . " 字节\n", FILE_APPEND);
                }
                
                // 尝试提取角色信息
                $characters = [];
                
                // 预处理：将#和*字符替换为空，避免干扰解析
                // 使用mb_ereg_replace或preg_replace处理多字节字符
                $characterContent = preg_replace('/[#*]/', '', $characterContent);
                
                // 确保内容是UTF-8编码
                if (!mb_check_encoding($characterContent, 'UTF-8')) {
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 检测到非UTF-8编码，尝试转换\n", FILE_APPEND);
                    // 尝试从其他编码转换为UTF-8
                    $characterContent = mb_convert_encoding($characterContent, 'UTF-8', 'UTF-8, GBK, GB2312, BIG5');
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 编码转换完成\n", FILE_APPEND);
                }
                
                // 移除BOM标记（如果有）
                $characterContent = preg_replace('/^\xEF\xBB\xBF/', '', $characterContent);
                
                // 清理可能的无效UTF-8字符
                $characterContent = mb_convert_encoding($characterContent, 'UTF-8', 'UTF-8');
                
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 预处理后内容前500字符: " . substr($characterContent, 0, 500) . "\n", FILE_APPEND);
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 预处理后内容后500字符: " . substr($characterContent, -500) . "\n", FILE_APPEND);
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 预处理完成，已移除所有#和*字符\n", FILE_APPEND);
                
                // 简化解析：按角色顺序直接提取所有信息
                $lines = explode("\n", $characterContent);
                $currentCharacter = null;
                $characters = [];
                $lineNumber = 0;
                
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始简化解析角色内容，总行数: " . count($lines) . "\n", FILE_APPEND);
                
                foreach ($lines as $line) {
                    $lineNumber++;
                    $line = trim($line);
                    
                    // 跳过空行
                    if (empty($line)) {
                        continue;
                    }
                    
                    // 检测角色标题（格式：1. 马小川 或 1、马小川）
                    if (preg_match('/^\s*\d+[\.\、]\s*(.+)$/', $line, $matches)) {
                        $characterName = trim($matches[1]);
                        // 排除关系标题（如：1. 马小川与奶奶的关系）
                        if (strpos($characterName, '与') !== false && strpos($characterName, '的关系') !== false) {
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 第{$lineNumber}行: 跳过关系标题 - " . $characterName . "\n", FILE_APPEND);
                            continue;
                        }
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 第{$lineNumber}行: 检测到角色名称 - " . $characterName . "\n", FILE_APPEND);
                        
                        // 保存上一个角色
                        if ($currentCharacter) {
                            $characters[] = $currentCharacter;
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 保存角色: " . $currentCharacter['name'] . "\n", FILE_APPEND);
                        }
                        
                        // 创建新角色
                        $currentCharacter = [
                            'crew_id' => $crewId,
                            'name' => $characterName,
                            'description' => '',
                            'clothing_description' => '',
                            'character_arc' => '',
                            'relationship_nodes' => '',
                            'relationship_types' => '',
                            'relationship_details' => '',
                            'graph_level' => 0,
                            'centrality_score' => 0.5
                        ];
                        continue;
                    }
                    
                    // 如果没有当前角色，跳过
                    if (!$currentCharacter) {
                        continue;
                    }
                    
                    // 检测角色字段信息（格式：- 字段名：内容）
                    if (preg_match('/^-\s*([^：:]+)[：:](.+)$/', $line, $matches)) {
                        $fieldName = trim($matches[1]);
                        $content = trim($matches[2]);
                        
                        // 根据字段名映射到对应的角色属性
                        switch ($fieldName) {
                            case '角色描述':
                            case '描述':
                                $currentCharacter['description'] = $content;
                                break;
                            case '服装':
                                if (empty($currentCharacter['clothing_description'])) {
                                    $currentCharacter['clothing_description'] = '服装：' . $content;
                                } else {
                                    $currentCharacter['clothing_description'] .= ' | 服装：' . $content;
                                }
                                break;
                            case '妆造':
                                if (empty($currentCharacter['clothing_description'])) {
                                    $currentCharacter['clothing_description'] = '妆造：' . $content;
                                } else {
                                    $currentCharacter['clothing_description'] .= ' | 妆造：' . $content;
                                }
                                break;
                            case '年龄跨度':
                            case '身份变化':
                            case '核心特质':
                            case '关键经历':
                            case '起点':
                            case '转折':
                            case '低谷':
                            case '觉醒':
                            case '成长':
                            case '高潮':
                            case '终点':
                                // 这些字段都归入character_arc
                                if (empty($currentCharacter['character_arc'])) {
                                    $currentCharacter['character_arc'] = $fieldName . '：' . $content;
                                } else {
                                    $currentCharacter['character_arc'] .= ' | ' . $fieldName . '：' . $content;
                                }
                                break;
                            case '关系类型':
                                if (empty($currentCharacter['relationship_types'])) {
                                    $currentCharacter['relationship_types'] = $content;
                                } else {
                                    $currentCharacter['relationship_types'] .= ' | ' . $content;
                                }
                                break;
                            case '关系描述':
                                if (empty($currentCharacter['relationship_details'])) {
                                    $currentCharacter['relationship_details'] = $content;
                                } else {
                                    $currentCharacter['relationship_details'] .= ' | ' . $content;
                                }
                                break;
                            default:
                                // 其他字段归入description
                                if (empty($currentCharacter['description'])) {
                                    $currentCharacter['description'] = $fieldName . '：' . $content;
                                } else {
                                    $currentCharacter['description'] .= ' | ' . $fieldName . '：' . $content;
                                }
                                break;
                        }
                        
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 第{$lineNumber}行: 添加字段 {$fieldName} - " . $content . "\n", FILE_APPEND);
                    }
                }
                
                // 添加最后一个角色
                if ($currentCharacter) {
                    $characters[] = $currentCharacter;
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 保存最后一个角色: " . $currentCharacter['name'] . "\n", FILE_APPEND);
                }
                
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 解析出 " . count($characters) . " 个角色\n", FILE_APPEND);
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色数据: " . json_encode($characters, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);
                    
                // 批量插入角色到数据库
                if (!empty($characters)) {
                    // 检查characters表的字符集是否已经是utf8mb4
                    // 使用information_schema.COLUMNS表查询字符集
                    $charsetCheckSql = "SELECT CHARACTER_SET_NAME, COLLATION_NAME 
                                       FROM information_schema.COLUMNS 
                                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'characters' 
                                       LIMIT 1";
                    $charsetResult = $pdo->query($charsetCheckSql)->fetch(PDO::FETCH_ASSOC);
                    
                    $needCharsetUpdate = false;
                    if ($charsetResult) {
                        $currentCharset = $charsetResult['CHARACTER_SET_NAME'];
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 当前characters表字符集: " . $currentCharset . "\n", FILE_APPEND);
                        if (strtolower($currentCharset) !== 'utf8mb4') {
                            $needCharsetUpdate = true;
                        }
                    } else {
                        // 查询失败，默认需要更新
                        $needCharsetUpdate = true;
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 无法查询characters表字符集，将尝试更新\n", FILE_APPEND);
                    }
                    
                    // 只有在字符集不是utf8mb4时才修改
                    if ($needCharsetUpdate) {
                        try {
                            // 修改整个表的字符集
                            $pdo->exec("ALTER TABLE characters CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 成功修改characters表字符集为utf8mb4\n", FILE_APPEND);
                            
                            // 确保所有文本字段都使用utf8mb4字符集
                            $textFields = ['name', 'description', 'clothing_description', 'character_arc', 'relationship_nodes', 'relationship_types', 'relationship_details'];
                            foreach ($textFields as $field) {
                                try {
                                    $pdo->exec("ALTER TABLE characters MODIFY COLUMN {$field} TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 成功修改字段 {$field} 字符集为utf8mb4\n", FILE_APPEND);
                                } catch (Exception $e) {
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 修改字段 {$field} 字符集失败: " . $e->getMessage() . "\n", FILE_APPEND);
                                }
                            }
                        } catch (Exception $e) {
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 修改characters表字符集失败: " . $e->getMessage() . "\n", FILE_APPEND);
                        }
                    } else {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] characters表字符集已经是utf8mb4，跳过修改\n", FILE_APPEND);
                    }
                    
                    // 确保数据库连接使用utf8mb4字符集
                    $pdo->exec("SET NAMES utf8mb4");
                    $pdo->exec("SET CHARACTER SET utf8mb4");
                    
                    // 确保所有数据都是utf8mb4编码
                    foreach ($characters as &$char) {
                        $char['name'] = $char['name'];
                        $char['description'] = $char['description'];
                        $char['clothing_description'] = $char['clothing_description'];
                        $char['character_arc'] = $char['character_arc'];
                        $char['relationship_nodes'] = $char['relationship_nodes'];
                        $char['relationship_types'] = $char['relationship_types'];
                        $char['relationship_details'] = $char['relationship_details'];
                    }
                    unset($char);
                    
                    // 过滤掉无效的角色数据（没有crew_id或name的角色）
                    $validCharacters = array_filter($characters, function($char) use ($crewId, $logFile) {
                        $isValid = !empty($char['name']) && !empty($char['crew_id']) && $char['crew_id'] == $crewId;
                        if (!$isValid) {
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 过滤无效角色: name=" . ($char['name'] ?? 'null') . ", crew_id=" . ($char['crew_id'] ?? 'null') . "\n", FILE_APPEND);
                        }
                        return $isValid;
                    });
                    
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 过滤后剩余 " . count($validCharacters) . " 个有效角色\n", FILE_APPEND);
                    
                    if (empty($validCharacters)) {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 没有有效的角色数据，跳过插入和JSON文件保存\n", FILE_APPEND);
                    } else {
                        // 保存角色数据到单独的JSON文件，便于调试
                        $charactersJsonFile = __DIR__ . '/characters_' . $taskId . '.json';
                        $charactersJson = json_encode($validCharacters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                        file_put_contents($charactersJsonFile, $charactersJson);
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色数据已保存到: " . $charactersJsonFile . "\n", FILE_APPEND);
                        
                        // 第一步：INSERT所有角色到数据库（只包含基本信息）
                        $insertSql = "INSERT INTO characters (crew_id, name, description, graph_level, centrality_score) VALUES ";
                        $values = [];
                        $params = [];
                        $characterIdMap = []; // 存储角色名称到ID的映射
                            
                        foreach ($validCharacters as $index => $char) {
                            $placeholders = [];
                            foreach (['crew_id', 'name', 'description', 'graph_level', 'centrality_score'] as $field) {
                                $placeholders[] = '?';
                                $params[] = $char[$field] ?? '';
                            }
                            $values[] = '(' . implode(', ', $placeholders) . ')';
                        }
                        
                        $insertSql .= implode(', ', $values);
                        
                        // 在执行INSERT之前再次确保字符集设置
                        $pdo->exec("SET NAMES utf8mb4");
                        $pdo->exec("SET CHARACTER SET utf8mb4");
                        
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 执行角色插入SQL: " . $insertSql . "\n", FILE_APPEND);
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 参数数量: " . count($params) . "\n", FILE_APPEND);
                        
                        // 记录前几个参数的编码信息
                        for ($i = 0; $i < min(3, count($params)); $i++) {
                            $param = $params[$i];
                            $encoding = mb_detect_encoding($param, 'UTF-8, GBK, GB2312, BIG5');
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 参数{$i}编码: " . $encoding . ", 长度: " . strlen($param) . ", 内容: " . substr($param, 0, 50) . "\n", FILE_APPEND);
                        }
                        
                        $stmt = $pdo->prepare($insertSql);
                        $result = $stmt->execute($params);
                        
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 执行结果: " . ($result ? '成功' : '失败') . "\n", FILE_APPEND);
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 成功插入 " . count($validCharacters) . " 个角色到剧组 {$crewId}\n", FILE_APPEND);
                        
                        // 第二步：UPDATE每个角色的服装、弧光、关系字段
                        if ($result) {
                            // 获取刚插入的角色ID
                            $selectSql = "SELECT id, name FROM characters WHERE crew_id = ? ORDER BY id";
                            $stmt = $pdo->prepare($selectSql);
                            $stmt->execute([$crewId]);
                            $dbCharacters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // 建立角色名称到ID的映射
                            foreach ($dbCharacters as $dbChar) {
                                $characterIdMap[$dbChar['name']] = $dbChar['id'];
                            }
                            
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 建立角色名称到ID的映射，共 " . count($characterIdMap) . " 个角色\n", FILE_APPEND);
                            
                            // 逐个UPDATE角色的服装、弧光、关系字段
                            foreach ($validCharacters as $char) {
                                $charName = $char['name'];
                                if (!isset($characterIdMap[$charName])) {
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 警告 - 找不到角色 {$charName} 的ID，跳过更新\n", FILE_APPEND);
                                    continue;
                                }
                                
                                $charId = $characterIdMap[$charName];
                                
                                // 更新服装描述
                                if (!empty($char['clothing_description'])) {
                                    $updateSql = "UPDATE characters SET clothing_description = ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateSql);
                                    $stmt->execute([$char['clothing_description'], $charId]);
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 更新角色 {$charName} (ID: {$charId}) 的服装描述\n", FILE_APPEND);
                                }
                                
                                // 更新人物弧光
                                if (!empty($char['character_arc'])) {
                                    $updateSql = "UPDATE characters SET character_arc = ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateSql);
                                    $stmt->execute([$char['character_arc'], $charId]);
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 更新角色 {$charName} (ID: {$charId}) 的人物弧光\n", FILE_APPEND);
                                }
                                
                                // 更新关系节点
                                if (!empty($char['relationship_nodes'])) {
                                    $updateSql = "UPDATE characters SET relationship_nodes = ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateSql);
                                    $stmt->execute([$char['relationship_nodes'], $charId]);
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 更新角色 {$charName} (ID: {$charId}) 的关系节点\n", FILE_APPEND);
                                }
                                
                                // 更新关系类型
                                if (!empty($char['relationship_types'])) {
                                    $updateSql = "UPDATE characters SET relationship_types = ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateSql);
                                    $stmt->execute([$char['relationship_types'], $charId]);
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 更新角色 {$charName} (ID: {$charId}) 的关系类型\n", FILE_APPEND);
                                }
                                
                                // 更新关系详情
                                if (!empty($char['relationship_details'])) {
                                    $updateSql = "UPDATE characters SET relationship_details = ? WHERE id = ?";
                                    $stmt = $pdo->prepare($updateSql);
                                    $stmt->execute([$char['relationship_details'], $charId]);
                                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 更新角色 {$charName} (ID: {$charId}) 的关系详情\n", FILE_APPEND);
                                }
                            }
                            
                            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 完成所有角色的服装、弧光、关系字段更新\n", FILE_APPEND);
                        }
                    }
                    } else {
                        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 没有解析到角色数据\n", FILE_APPEND);
                    }
            } catch (Exception $e) {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析失败: " . $e->getMessage() . "\n", FILE_APPEND);
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 错误堆栈: " . $e->getTraceAsString() . "\n", FILE_APPEND);
                
                // 更新任务状态为失败
                $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_FAILED, 0, null, "角色分析失败: " . $e->getMessage());
                
                // 写入失败状态到进度文件
                $errorProgressResult = [
                    'task_id' => $taskId,
                    'status' => 'error',
                    'error' => $e->getMessage(),
                    'content' => '',
                    'is_character_analysis' => true
                ];
                $jsonProgress = json_encode($errorProgressResult, JSON_UNESCAPED_UNICODE);
                if ($jsonProgress !== false) {
                    file_put_contents($resultFile, $jsonProgress, LOCK_EX);
                }
                
                // 角色分析失败，直接返回，不继续执行分镜分析
                return false;
            }
        } catch (Exception $e) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析失败: " . $e->getMessage() . "\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 错误堆栈: " . $e->getTraceAsString() . "\n", FILE_APPEND);
            
            // 更新任务状态为失败
            $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_FAILED, 0, null, "角色分析失败: " . $e->getMessage());
            
            // 写入失败状态到进度文件
            $errorProgressResult = [
                'task_id' => $taskId,
                'status' => 'error',
                'error' => $e->getMessage(),
                'content' => '',
                'is_character_analysis' => true
            ];
            $jsonProgress = json_encode($errorProgressResult, JSON_UNESCAPED_UNICODE);
            if ($jsonProgress !== false) {
                file_put_contents($resultFile, $jsonProgress, LOCK_EX);
            }
            
            // 角色分析失败，直接返回，不继续执行分镜分析
            return false;
        }
        
storyboard_analysis_start:        // ===== 角色分析结束 =====
        
        // 更新任务进度，直接开始分镜分析
        $taskManager->updateTaskProgress($taskId, 10, "开始分镜分析");
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始分镜分析，更新进度为10%\n", FILE_APPEND);

        $fullResponse = '';
        $completedRounds = 0;
        $lastIncompleteShot = ''; // 存储上一个不完整的分镜
        $interactionHistory = []; // 存储与DeepSeek的交互历史

        // 系统提示词
        $systemPrompt = "你是一个专业的剧本分析师。请详细分析用户提供的剧本内容，根据用户的提示进行深入分析。由于剧本较长，请分部分进行分析。\n\n重要要求：\n1. 每个分镜分析必须完整，包含所有要求的字段\n2. 如果分析被中断，请在下一轮继续完成当前分镜\n3. 严格按照如下格式给出回复：| 排序号 | 场次号 | 镜号 | 地点 | 时间 | 天气 | 参考画面 | 景别 | 时长(秒) | 内容 | 剧本 | 台词 | 角色清单 | 各角色推荐服装 | 各角色推荐妆造 | 角色动作 | 道具 | 场景预期 | 声音设计 | 摄像机角度 | 构图与焦点 | 运镜 | 摄像机设备 | 镜头焦段 | 光线与色调 |\n4. 多余的无关的话不要说。";

        $optimizedPrompt = pst_optimizePrompt($prompt);

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

        // 记录循环开始
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始分析循环，总轮次: {$maxRounds}\n", FILE_APPEND);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 剧本块数量: " . count($scriptChunks) . "，maxRounds: {$maxRounds}\n", FILE_APPEND);

        for ($round = 1; $round <= $maxRounds; $round++) {
            // 记录当前轮次开始
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始第 {$round} 轮分析，总轮次: {$maxRounds}\n", FILE_APPEND);

            // 记录交互开始
            $interactionStartTime = date('Y-m-d H:i:s');
            $interactionStartTimestamp = microtime(true);

            // 构建当前轮次的消息
            $userMessage = pst_buildUserMessage($round, $scriptChunks, $lastIncompleteShot);

            // 如果有未完成的分镜，优先处理
            if (!empty($lastIncompleteShot)) {
                $userMessage = "上一轮最后一个分镜分析不完整，请先完成这个分镜的分析：\n\n{$lastIncompleteShot}\n\n然后继续分析新的剧本内容。";
                $lastIncompleteShot = ''; // 清空，等待新的响应
            }

            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // 清理过长的消息历史以节省token
            $messages = pst_cleanupMessageHistory($messages);



            // 更新进度
            $progress = round(($round / $maxRounds) * 100, 2);

            // 记录交互开始消息
            $interactionMessage = "正在进行第{$round}轮分析，每轮次约需50~300秒，本页面可以关闭，支持断点续传……";

            // 添加当前交互到历史记录（处理中状态）
            $currentInteraction = [
                'round' => $round,
                'start_time' => $interactionStartTime,
                'status' => 'processing',
                'message' => $interactionMessage
            ];
            $interactionHistory[] = $currentInteraction;

            $progressResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => $round,
                'total_rounds' => $maxRounds,
                'progress' => $progress,
                'message' => $interactionMessage,
                'content' => $fullResponse,
                'rounds' => $round - 1,
                'interaction_history' => $interactionHistory
            ];

            // 写入进度文件
            $jsonProgress = json_encode($progressResult, JSON_UNESCAPED_UNICODE);
            if ($jsonProgress !== false) {
                $writeResult = file_put_contents($resultFile, $jsonProgress, LOCK_EX);
                if ($writeResult === false) {
                    error_log("无法写入任务进度文件: {$resultFile}");
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 无法写入任务进度文件: {$resultFile}\n", FILE_APPEND);
                } else {
                    error_log("已成功写入任务进度文件: {$resultFile}, 大小: {$writeResult} 字节");
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已成功写入任务进度文件: {$resultFile}, 大小: {$writeResult} 字节\n", FILE_APPEND);
                }
            } else {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] JSON编码失败\n", FILE_APPEND);
            }

            // 更新进度到数据库
            if ($taskManager) {
                try {
                    $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_PROCESSING, $progress);
                    $taskManager->updateTaskProgress($taskId, $progress, $interactionMessage);
                } catch (Exception $e) {
                    error_log("TaskManager - 更新任务进度失败: " . $e->getMessage());
                }
            }

            // 调用API前记录日志
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始调用Agentic API，轮次: {$round}\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 消息数量: " . count($messages) . ", 消息历史大小: " . strlen(json_encode($messages)) . " 字节\n", FILE_APPEND);

            // 调用API
            $content = pst_callDeepSeekAPIWithRetry($apiKey, $messages, $round);

            // API调用后记录日志
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API调用返回，轮次: {$round}, 返回内容大小: " . strlen($content) . " 字节\n", FILE_APPEND);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API调用返回前100字符: " . substr($content, 0, 100) . "...\n", FILE_APPEND);

            // 记录交互结束
            $interactionEndTime = date('Y-m-d H:i:s');
            $interactionEndTimestamp = microtime(true);
            $interactionDuration = round($interactionEndTimestamp - $interactionStartTimestamp, 2);

            // 检查响应是否完整
            $isComplete = pst_checkResponseCompleteness($content);
            $interactionStatus = $isComplete ? 'completed' : 'incomplete';

            if (!$isComplete && $round < $maxRounds) {
                // 响应不完整，保存未完成的部分用于下一轮
                $lastIncompleteShot = pst_extractLastIncompleteShot($content);
                $content = $content . "[分析被截断，将在下一轮继续]";
            } else {
                // 响应完整，清空未完成标记
                $lastIncompleteShot = '';
            }

            // 收集所有轮次的结果，直接添加到fullResponse中，不做截断处理
            $fullResponse .= $content . "\n\n";
            $completedRounds = $round;

            // 添加到对话历史
            $messages[] = ['role' => 'assistant', 'content' => $content];

            // 更新交互历史记录（完成状态）
            $interactionHistory[$round - 1] = [
                'round' => $round,
                'start_time' => $interactionStartTime,
                'end_time' => $interactionEndTime,
                'duration' => $interactionDuration,
                'status' => $interactionStatus,
                'message' => $interactionMessage
            ];

            // 更新最终结果文件，包含完整的交互历史
            $progressResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => $round,
                'total_rounds' => $maxRounds,
                'progress' => $progress,
                'message' => "第{$round}轮分析完成",
                'content' => $fullResponse,
                'rounds' => $round,
                'interaction_history' => $interactionHistory
            ];

            $jsonProgress = json_encode($progressResult, JSON_UNESCAPED_UNICODE);
            if ($jsonProgress !== false) {
                $writeResult = file_put_contents($resultFile, $jsonProgress, LOCK_EX);
                if ($writeResult === false) {
                    error_log("无法写入任务进度文件: {$resultFile}");
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 无法写入任务进度文件: {$resultFile}\n", FILE_APPEND);
                } else {
                    error_log("已成功写入任务进度文件: {$resultFile}, 大小: {$writeResult} 字节");
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已成功写入任务进度文件: {$resultFile}, 大小: {$writeResult} 字节\n", FILE_APPEND);
                    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 第{$round}轮分析完成，进度: {$progress}%\n", FILE_APPEND);
                }
            } else {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] JSON编码失败\n", FILE_APPEND);
            }

            // 检查是否完成所有剧本块且没有未完成的分镜
            // 但即使完成了所有剧本块，也需要继续执行完所有轮次，因为可能还需要进行后续的完善和润色
            // if ($round >= count($scriptChunks) && empty($lastIncompleteShot)) {
            //     // 所有剧本块已处理且没有未完成的分镜，可以结束
            //     break;
            // }

            // 如果达到最大轮次但还有未完成的分镜，尝试最后一轮专门处理
            if ($round === $maxRounds - 1 && !empty($lastIncompleteShot)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => "请专门完成这个未完成的分镜分析：\n\n{$lastIncompleteShot}\n\n这是最后一轮，请确保分析完整。不润色、不扩写、不输出任何其他文本、说明或解释。"
                ];
            }

            // 添加延迟避免频繁请求
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 第 {$round} 轮分析完成，休息3秒后继续下一轮\n", FILE_APPEND);
            sleep(3);
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 休息结束，准备进行下一轮分析\n", FILE_APPEND);
        }

        // 记录循环结束
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 分析循环结束，完成轮次: {$completedRounds}\n", FILE_APPEND);

        // 保存最终结果
        $finalResult = [
            'task_id' => $taskId,
            'status' => 'completed',
            'current_round' => $completedRounds,
            'total_rounds' => $maxRounds,
            'progress' => 100,
            'start_time' => file_exists($resultFile) ? date('Y-m-d H:i:s', filemtime($resultFile)) : date('Y-m-d H:i:s'),
            'end_time' => date('Y-m-d H:i:s'),
            'content' => trim($fullResponse),
            'rounds' => $completedRounds,
            'message' => '分析任务已完成',
            'interaction_history' => $interactionHistory
        ];
        // 写入进度文件
        $jsonFinal = json_encode($finalResult, JSON_UNESCAPED_UNICODE);
        if ($jsonFinal !== false) {
            $writeResult = file_put_contents($resultFile, $jsonFinal, LOCK_EX);
            if ($writeResult === false) {
                error_log("无法写入最终结果文件: {$resultFile}");
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 无法写入最终结果文件: {$resultFile}\n", FILE_APPEND);
            } else {
                error_log("已成功写入最终结果文件: {$resultFile}");
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已成功写入最终结果文件: {$resultFile}, 大小: {$writeResult} 字节\n", FILE_APPEND);
            }
        } else {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 最终结果JSON编码失败\n", FILE_APPEND);
        }

        // 更新数据库中任务状态为已完成
        if ($taskManager) {
            $outputData = [
                'storyboard_content' => $finalResult['content'],
                'rounds' => $completedRounds,
                'message' => $finalResult['message']
            ];

            // 更新任务状态为已完成 - 使用外部任务ID $taskId
            $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_COMPLETED, 100, $outputData);

            // 创建剧本记录 - 添加错误处理，避免数据库错误导致整个任务失败
            try {
                $taskManager->createScript($taskId, $finalResult['content'], '剧本转分镜_' . date('Y-m-d'), '系统自动生成');
            } catch (Exception $e) {
                error_log("创建剧本记录失败: " . $e->getMessage());
                // 继续执行，不中断任务
            }
        }

        // 同步处理分镜正式分拆，确保数据保存到数据库中
        $logMessage = "开始同步处理分镜数据，任务ID: {$taskId}";
        error_log($logMessage);
        file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

        try {
            $logMessage = "准备包含process_and_save_storyboards.php文件";
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

            // 直接调用processAndSaveStoryboards函数
            require_once __DIR__ . '/process_and_save_storyboards.php';

            $logMessage = "成功包含process_and_save_storyboards.php文件";
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

            // 检查processAndSaveStoryboards函数是否存在
            if (!function_exists('processAndSaveStoryboards')) {
                $logMessage = "ERROR: processAndSaveStoryboards函数不存在";
                error_log($logMessage);
                file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
            } else {
                $logMessage = "processAndSaveStoryboards函数存在";
                error_log($logMessage);
                file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
            }

            // 读取结果文件
            if (!file_exists($resultFile)) {
                $logMessage = "ERROR: 结果文件不存在: {$resultFile}";
                error_log($logMessage);
                file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
            } else {
                $resultFileContent = file_get_contents($resultFile);
                $logMessage = "成功读取结果文件，大小: " . strlen($resultFileContent) . " 字节";
                error_log($logMessage);
                file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

                $logMessage = "结果文件内容: " . substr($resultFileContent, 0, 500) . "...";
                error_log($logMessage);
                file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

                $finalResult = json_decode($resultFileContent, true);

                if ($finalResult === null) {
                    $logMessage = 'JSON解析失败: ' . json_last_error_msg();
                    error_log($logMessage);
                    file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
                } else {
                    $logMessage = 'JSON解析成功，content字段长度: ' . strlen($finalResult['content']);
                    error_log($logMessage);
                    file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

                    // 调用processAndSaveStoryboards函数保存分镜数据
                    $logMessage = "准备调用processAndSaveStoryboards函数";
                    error_log($logMessage);
                    file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

                    $result = processAndSaveStoryboards($taskId, $finalResult['content']);

                    $logMessage = '分镜保存成功，场景数: ' . $result['scenes_count'] . ', 分镜数: ' . $result['shots_count'];
                    error_log($logMessage);
                    file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
                }
            }
        } catch (Exception $e) {
            $logMessage = '分镜保存失败: ' . $e->getMessage();
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

            $logMessage = '错误堆栈: ' . $e->getTraceAsString();
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
        } catch (Error $e) {
            $logMessage = '致命错误: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine();
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);

            $logMessage = '错误堆栈: ' . $e->getTraceAsString();
            error_log($logMessage);
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] " . $logMessage . "\n", FILE_APPEND);
        }
    } catch (Exception $e) {
        pst_saveErrorResult($taskId, $e->getMessage(), $resultFile, $taskManager, $interactionHistory);
    }

    return true;
}

/**
 * 构建用户消息
 */
function pst_buildUserMessage($round, $scriptChunks, $lastIncompleteShot)
{
    $chunkCount = count($scriptChunks);

    if ($chunkCount === 0) {
        return "请分析剧本内容，确保每个分镜完整。";
    }

    // 确保索引不越界
    $chunkIndex = min($round - 1, $chunkCount - 1);
    $currentChunk = $scriptChunks[$chunkIndex] ?? '';

    if ($round === 1) {
        return "剧本第一部分：\n{$currentChunk}\n\n请开始分析，确保每个分镜完整。";
    } elseif ($round < $chunkCount) {
        return "剧本下一部分：\n{$currentChunk}\n\n请继续分析，保持与前面内容的连贯性。";
    } else {
        if ($round === $chunkCount) {
            return "剧本最后一部分：\n{$currentChunk}\n\n请完成分析，不润色、不扩写、不输出任何其他文本、说明或解释。";
        } else {
            // 超出剧本块数量的轮次，用于完善和润色
            //return "请基于前面的分析结果，对整个剧本进行完善和润色，确保所有分镜完整且连贯。";
            return "请基于前面的分析结果，确保所有分镜都已分析完整并完成。不润色、不扩写、不输出任何无关的文本、说明或解释。";
        }
    }
}

/**
 * 清理消息历史
 */
function pst_cleanupMessageHistory($messages)
{
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
 * 优化提示词
 */
function pst_optimizePrompt($prompt)
{
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
1. 只输出表格，不输出任何其他文本、说明或解释！
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
function pst_callDeepSeekAPIWithRetry($apiKey, $messages, $round)
{
    // 不允许使用测试模式，必须调用真实API
    if (empty($apiKey)) {
        throw new Exception('DeepSeek API密钥未配置');
    }

    $maxRetries = 3;
    $retryDelay = 5; // 重试延迟（秒）
    
    for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
        try {
            // 根据轮次调整超时时间
            // 角色分析使用更长的超时时间（300秒）
            $timeout = $round === 0 ? 300000 : ($round === 1 ? 12000 : 9000);
            
            // 构建请求数据 - 根据轮次调整token数量
            $maxTokens = 8000;

            $requestData = [
                'model' => Config::DEEPSEEK_MODEL(),
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => $maxTokens,
                'stream' => false
            ];

            // 调用DeepSeek API 
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => Config::DEEPSEEK_API_URL(),
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
                // 如果是403错误，可能是API密钥问题或频率限制
                if ($httpCode === 403) {
                    $errorMsg = "API请求被拒绝（HTTP 403）";
                    if ($attempt < $maxRetries) {
                        $errorMsg .= "，将在{$retryDelay}秒后重试（第{$attempt}次尝试）";
                    } else {
                        $errorMsg .= "，已达到最大重试次数。请检查API密钥是否有效或联系管理员。";
                    }
                    throw new Exception($errorMsg);
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
            // 记录API调用失败的错误信息
            file_put_contents(__DIR__ . '/process_script_task.log', "[" . date('Y-m-d H:i:s') . "] API调用失败（第{$attempt}次尝试）: " . $e->getMessage() . "\n", FILE_APPEND);
            
            // 如果不是最后一次尝试，等待后重试
            if ($attempt < $maxRetries) {
                sleep($retryDelay);
                continue;
            }
            
            // 最后一次尝试仍然失败，抛出异常
            throw $e;
        }
    }
}

/**
 * 检查响应完整性
 */
function pst_checkResponseCompleteness($content)
{
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
function pst_extractLastIncompleteShot($content)
{
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
 * 保存错误结果
 */
function pst_saveErrorResult($taskId, $errorMessage, $resultFile, $taskManager, $interactionHistory = [])
{
    // 读取已有的进度数据，保留已有的交互历史
    $existingData = [];
    if (file_exists($resultFile)) {
        $existingContent = file_get_contents($resultFile);
        if ($existingContent) {
            $existingData = json_decode($existingContent, true);
        }
    }

    // 使用已有的交互历史或传入的交互历史
    $finalInteractionHistory = !empty($interactionHistory) ? $interactionHistory : ($existingData['interaction_history'] ?? []);

    $errorResult = [
        'task_id' => $taskId,
        'status' => 'error',
        'start_time' => file_exists($resultFile) ? date('Y-m-d H:i:s', filemtime($resultFile)) : date('Y-m-d H:i:s'),
        'end_time' => date('Y-m-d H:i:s'),
        'content' => $existingData['content'] ?? '',
        'error' => $errorMessage,
        'interaction_history' => $finalInteractionHistory
    ];

    file_put_contents($resultFile, json_encode($errorResult, JSON_UNESCAPED_UNICODE));

    // 更新数据库中任务状态为失败 - 只检查$taskManager，不再依赖$dbTaskId
    if ($taskManager) {
        $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_FAILED, 0, null, $errorMessage);
    }
}

// 检查是否有命令行参数
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    // 命令行模式，直接执行任务
    processScriptTask($argv[1]);
    exit(0);
}

// 如果是直接包含调用，不执行主逻辑，由调用者处理
if (php_sapi_name() !== 'cli') {
    // 定义一个常量，标记脚本已被包含
    define('PROCESS_SCRIPT_TASK_INCLUDED', true);
    // 不执行主逻辑，由调用者决定何时调用processScriptTask函数
}
