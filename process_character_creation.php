<?php
set_time_limit(0);
ini_set('memory_limit', '1G');
mb_internal_encoding('UTF-8');

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';

$logFile = __DIR__ . '/process_character_creation.log';

function logToFile($message, $customLogFile = null) {
    global $logFile;
    $targetFile = $customLogFile ?? $logFile;
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    file_put_contents($targetFile, $logMessage, FILE_APPEND);
}

function processCharacterCreation($taskParamsFile) {
    global $logFile;
    logToFile("开始处理角色创作任务，参数文件: {$taskParamsFile}");
    
    if (!file_exists($taskParamsFile)) {
        logToFile("错误: 任务参数文件不存在: {$taskParamsFile}");
        return false;
    }
    
    $taskParamsJson = file_get_contents($taskParamsFile);
    if ($taskParamsJson === false) {
        logToFile("错误: 无法读取任务参数文件: {$taskParamsFile}");
        return false;
    }
    
    $taskParams = json_decode($taskParamsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logToFile("错误: JSON解析失败: " . json_last_error_msg());
        return false;
    }
    
    $taskId = $taskParams['task_id'] ?? '';
    $script = $taskParams['script'] ?? '';
    $userId = $taskParams['user_id'] ?? 0;
    
    if (empty($taskId) || empty($script)) {
        logToFile("错误: 任务参数不完整: task_id={$taskId}, script_length=" . strlen($script));
        return false;
    }
    
    logToFile("任务ID: {$taskId}");
    logToFile("剧本长度: " . strlen($script) . " 字符");
    logToFile("用户ID: {$userId}");
    
    $resultFile = __DIR__ . '/results/' . $taskId . '.json';
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        $sql = "SELECT 
                    text2text_api_key as deepseek_api_key, 
                    text2text_api_url as deepseek_api_url,
                    text2text_api_model as deepseek_model,
                    text2img_api_key,
                    text2img_api_url,
                    img2video_api_key as video_generation_api_key,
                    img2video_api_url as video_generation_api_url
                FROM api_keys 
                WHERE user_id = ? LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $apiConfig = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$apiConfig) {
            logToFile("错误: 未找到用户 {$userId} 的API配置");
            throw new Exception('API配置未找到');
        }
        
        logToFile("API配置已加载");
        logToFile("API URL: {$apiConfig['deepseek_api_url']}");
        logToFile("API Model: {$apiConfig['deepseek_model']}");
        logToFile("API Key长度: " . (empty($apiConfig['deepseek_api_key']) ? 0 : strlen($apiConfig['deepseek_api_key'])));
        
        $apiKey = $apiConfig['deepseek_api_key'];
        if (empty($apiKey)) {
            logToFile("错误: DeepSeek API密钥为空");
            throw new Exception('DeepSeek API密钥未配置');
        }
        
        $apiUrl = $apiConfig['deepseek_api_url'];
        if (empty($apiUrl)) {
            logToFile("错误: DeepSeek API URL为空");
            throw new Exception('DeepSeek API URL未配置');
        }
        
        $apiModel = $apiConfig['deepseek_model'];
        if (empty($apiModel)) {
            logToFile("错误: DeepSeek API模型为空");
            throw new Exception('DeepSeek API模型未配置');
        }
        
        // 检查是否是Volcengine ARK API
        if (strpos($apiUrl, 'ark.cn-beijing.volces.com') !== false) {
            logToFile("检测到Volcengine ARK API，将使用特殊处理");
            // Volcengine ARK API可能需要不同的认证方式或参数
        }
        
        logToFile("API URL: {$apiUrl}");
        logToFile("API Model: {$apiModel}");
        
        $sql = "SELECT id, current_characters_task_id FROM crew WHERE admin_user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $crewId = null;
        if ($crew) {
            $crewId = $crew['id'];
            logToFile("找到剧组ID: {$crewId}");
        } else {
            $sql = "INSERT INTO crew (admin_user_id, name, status, current_characters_task_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $defaultCrewName = "{$userId}的默认剧组";
            $stmt->execute([$userId, $defaultCrewName, 1, $taskId]);
            $crewId = $pdo->lastInsertId();
            logToFile("创建新剧组ID: {$crewId}");
        }
        
        updateTaskProgress($taskId, $resultFile, 10, '正在提取角色信息...', 'extracting_characters', []);
        
        $characterExtractionPrompt = '你是一个专业的角色分析专家。请从小说剧本中提炼出所有角色信息，并严格按照以下格式输出：

【输出格式要求】
每个角色必须按以下格式输出：

1. 角色名
- 角色描述：[角色的基本描述，包括外貌、身份等]
- 性别：[男/女/其他]
- 年龄：[具体年龄或年龄范围]
- 服装：[服装的详细描述]
- 妆造：[妆容和造型的详细描述]
- 人设：[角色的性格、背景、身份等设定]

2. 角色名
- 角色描述：...
- 性别：...
- 年龄：...
- 服装：...
- 妆造：...
- 人设：...

【重要规则】
1. 角色标题格式：必须使用 "数字. 角色名称" 格式，例如 "1. 张三"
2. 列表项必须使用 "- " 前缀，格式为 "- 字段名：内容"
3. 每个角色必须包含完整的6个字段：角色描述、性别、年龄、服装、妆造、人设
4. 字段名和内容之间必须使用中文冒号"："分隔
5. 每个字段的内容必须具体、详细，不能为空或省略
6. 不要使用表格格式，只使用上述Markdown列表格式
7. 不要输出任何解释性文字，只输出角色信息
8. 确保每个角色的信息都是独立的，不要混淆

现在，请开始分析并输出角色信息！';
        
        $messages = [
            [
                'role' => 'system',
                'content' => $characterExtractionPrompt
            ],
            [
                'role' => 'user',
                'content' => $script
            ]
        ];
        
        logToFile("开始调用角色提取API");
        
        $requestData = [
            'model' => $apiModel,
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 8000,
            'stream' => false
        ];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8',
                'Authorization: Bearer ' . $apiKey,
                'User-Agent: CharacterCreation/1.0'
            ],
            CURLOPT_TIMEOUT => 12000,
            CURLOPT_CONNECTTIMEOUT => 300
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        // 调试信息：记录完整响应
        logToFile("API响应HTTP状态码: {$httpCode}");
        if ($response) {
            logToFile("API响应内容: " . $response);
        }
        if ($error) {
            logToFile("API请求错误: " . $error);
        }
        
        if ($httpCode !== 200 || !$response) {
            throw new Exception("API请求失败: HTTP {$httpCode}" . ($error ? " - {$error}" : '') . ($response ? " - 响应内容: {$response}" : ''));
        }
        
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('API响应JSON解析错误: ' . json_last_error_msg());
        }
        
        if (!isset($result['choices'][0]['message']['content'])) {
            throw new Exception('无法解析API响应内容');
        }
        
        $characterResponse = $result['choices'][0]['message']['content'];
        
        $characterResponse = preg_replace('/[\x{200B}\x{200A}\x{200C}\x{200D}\x{FEFF}\x{00A0}]/u', '', $characterResponse);
        
        $detectedEncoding = mb_detect_encoding($characterResponse, 'UTF-8, GBK, GB2312, BIG5, ISO-8859-1');
        logToFile("检测到的编码: {$detectedEncoding}");
        logToFile("前100字节: " . bin2hex(substr($characterResponse, 0, 100)));
        
        if ($detectedEncoding !== 'UTF-8' && $detectedEncoding !== false) {
            $characterResponse = mb_convert_encoding($characterResponse, 'UTF-8', $detectedEncoding);
            logToFile("编码转换: {$detectedEncoding} -> UTF-8");
        }
        
        $characterResponse = mb_convert_encoding($characterResponse, 'UTF-8', 'UTF-8');
        
        $characterResponse = preg_replace('/[\x{200B}\x{200A}\x{200C}\x{200D}\x{FEFF}\x{00A0}]/u', '', $characterResponse);
        
        logToFile("API返回，大小: " . strlen($characterResponse) . " 字节");
        logToFile("API返回原始内容:\n" . $characterResponse);
        
        // 解析角色信息
        $characters = parseCharacterResponse($characterResponse, $crewId, $taskId, $userId, $logFile);
        
        // 生成三视图提示词
        foreach ($characters as &$char) {
            if (empty($char['gender']) || $char['gender'] === '未知') {
                $char['gender'] = extractGender($char['description'] ?? '', $logFile);
            }
            
            if (empty($char['age'])) {
                $char['age'] = extractAge($char['description'] ?? '', $logFile);
            }
            
            if (empty($char['clothing_description'])) {
                $char['clothing_description'] = extractClothing($char['description'] ?? '', $logFile);
            }
            
            if (empty($char['makeup_description'])) {
                $char['makeup_description'] = extractMakeup($char['description'] ?? '', $logFile);
            }
            
            if (empty($char['character_design'])) {
                $char['character_design'] = extractCharacterDesign($char['description'] ?? '', $logFile);
            }
            
            $char['three_view_prompt'] = generateThreeViewPrompt($char);
        }
        
        // 将解析后的角色列表保存为JSON文件
        $apiResponseFile = __DIR__ . '/results/' . $taskId . '_api_response.json';
        $apiResponseData = [
            'task_id' => $taskId,
            'response_time' => date('Y-m-d H:i:s'),
            'raw_content' => $characterResponse,
            'characters' => $characters
        ];
        $jsonResult = json_encode($apiResponseData, JSON_UNESCAPED_UNICODE);
        $fileWritten = false;
        if ($jsonResult !== false) {
            $writeResult = file_put_contents($apiResponseFile, $jsonResult, LOCK_EX);
            if ($writeResult !== false) {
                logToFile("API返回结果已保存到: {$apiResponseFile}");
                $fileWritten = true;
            } else {
                logToFile("警告: JSON编码失败，无法保存API响应到文件");
            }
        }
        
        if (empty($characters)) {
            logToFile("没有解析到角色数据");
            updateTaskProgress($taskId, $resultFile, 100, '未找到角色信息', 'completed', []);
            return false;
        }
        
        logToFile("解析出 " . count($characters) . " 个角色");
        
        updateTaskProgress($taskId, $resultFile, 30, '正在分析角色属性...', 'analyzing_attributes', $characters);
        
        // 第一步：从JSON文件中读取角色信息，如果文件不存在则直接使用内存中的角色数据
        $charactersFromJson = [];
        if (file_exists($apiResponseFile)) {
            logToFile("从JSON文件中读取角色信息: {$apiResponseFile}");
            $jsonContent = file_get_contents($apiResponseFile);
            if ($jsonContent !== false) {
                $apiResponseData = json_decode($jsonContent, true);
                $charactersFromJson = $apiResponseData['characters'] ?? [];
            }
        }
        
        // 如果从文件中读取不到角色信息，直接使用内存中的角色数据
        if (empty($charactersFromJson)) {
            logToFile("从JSON文件中读取不到角色信息，使用内存中的角色数据");
            $charactersFromJson = $characters;
        }
        
        updateTaskProgress($taskId, $resultFile, 50, '正在将角色信息存入数据库...', 'saving_to_database', $charactersFromJson);
        
        // 第二步：将角色信息存入数据库
        $pdo->exec("SET NAMES utf8mb4");
        $pdo->exec("SET CHARACTER SET utf8mb4");
        $pdo->exec("SET collation_connection = utf8mb4_unicode_ci");
        
        $insertSql = "INSERT INTO characters (user_id, crew_id, task_id, character_number, name, description, gender, age, clothing_description, makeup_description, accessories, character_arc, character_development, relationship_nodes, relationship_types, relationship_details, graph_level, centrality_score, makeup, character_design, three_view_prompt, three_view_image, status) VALUES ";
        $values = [];
        $params = [];
        
        $characterNumber = 1;
        foreach ($charactersFromJson as $char) {
            $char['user_id'] = $userId;
            $char['character_number'] = $characterNumber++;
            $char['three_view_image'] = '';
            $char['status'] = 'active';
            
            // 清理关键字段中的"??"
            $char['description'] = str_replace('??', '', $char['description'] ?? '');
            $char['clothing_description'] = str_replace('??', '', $char['clothing_description'] ?? '');
            $char['makeup_description'] = str_replace('??', '', $char['makeup_description'] ?? '');
            $char['gender'] = str_replace('??', '', $char['gender'] ?? '');
            $char['age'] = str_replace('??', '', $char['age'] ?? '');
            $char['makeup'] = str_replace('??', '', $char['makeup'] ?? '');
            $char['character_design'] = str_replace('??', '', $char['character_design'] ?? '');
            $char['three_view_prompt'] = str_replace('??', '', $char['three_view_prompt'] ?? '');
            
            $placeholders = [];
            foreach (['user_id', 'crew_id', 'task_id', 'character_number', 'name', 'description', 'gender', 'age', 'clothing_description', 'makeup_description', 'accessories', 'character_arc', 'character_development', 'relationship_nodes', 'relationship_types', 'relationship_details', 'graph_level', 'centrality_score', 'makeup', 'character_design', 'three_view_prompt', 'three_view_image', 'status'] as $field) {
                $placeholders[] = '?';
                $value = $char[$field] ?? '';
                if (!is_string($value)) {
                    $value = (string)$value;
                }
                $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
                $params[] = $value;
            }
            $values[] = '(' . implode(', ', $placeholders) . ')';
        }
        
        $insertSql .= implode(', ', $values);
        
        logToFile("执行角色插入SQL");
        logToFile("参数数量: " . count($params));
        
        try {
            $stmt = $pdo->prepare($insertSql);
            $result = $stmt->execute($params);
            
            if ($result) {
                logToFile("成功插入 " . count($charactersFromJson) . " 个角色到剧组 {$crewId}");
            } else {
                logToFile("角色插入失败");
                throw new Exception('角色数据插入失败');
            }
        } catch (Exception $e) {
            logToFile("角色插入异常: " . $e->getMessage());
            logToFile("错误堆栈: " . $e->getTraceAsString());
            throw $e;
        }
        
        updateTaskProgress($taskId, $resultFile, 80, '正在生成三视图图片...', 'generating_images', $charactersFromJson);
        
        // 第三步：从数据库中读取角色，逐个生成三视图并更新数据库
        $auth = new Auth();
        $text2imgApiUrl = $apiConfig['text2img_api_url'];
        $text2imgApiKey = $apiConfig['text2img_api_key'];
        
        // 从数据库中获取所有角色
        $sql = "SELECT id, name, character_number, three_view_prompt FROM characters WHERE task_id = ? AND user_id = ? ORDER BY character_number";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId, $userId]);
        $dbCharacters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        logToFile("从数据库中读取到 " . count($dbCharacters) . " 个角色，开始生成三视图");
        
        // 遍历所有角色，生成三视图
        $generatedCount = 0;
        $totalCount = count($dbCharacters);
        foreach ($dbCharacters as $dbChar) {
            $characterId = $dbChar['id'];
            $characterName = $dbChar['name'];
            $characterNumber = $dbChar['character_number'];
            $threeViewPrompt = $dbChar['three_view_prompt'];
            
            // 检查角色名称是否为空，如果为空则从内存中的角色列表获取
            if (empty($characterName)) {
                // 从内存中的角色列表中查找对应角色
                $matchedChar = null;
                // 使用charactersFromJson，因为它包含了与数据库中实际存储的character_number一致的角色数据
                foreach ($charactersFromJson as $char) {
                    if ($char['character_number'] == $characterNumber) {
                        $matchedChar = $char;
                        break;
                    }
                }
                
                if ($matchedChar) {
                    $characterName = $matchedChar['name'];
                    logToFile("修复角色名称：从空值修复为 {$characterName} (角色编号: {$characterNumber})");
                    // 更新数据库中的角色名称
                    $updateNameSql = "UPDATE characters SET name = ? WHERE id = ?";
                    $updateNameStmt = $pdo->prepare($updateNameSql);
                    $updateNameStmt->execute([$characterName, $characterId]);
                    logToFile("已更新数据库中的角色名称为 {$characterName}");
                } else {
                    $characterName = "角色_{$characterNumber}";
                    logToFile("无法修复角色名称，使用默认名称 {$characterName} (角色编号: {$characterNumber})");
                }
            }
            
            logToFile("处理角色：ID={$characterId}, 编号={$characterNumber}, 名称={$characterName}");
            
            if (empty($threeViewPrompt)) {
                logToFile("角色 {$characterName} 没有三视图提示词，跳过生成");
                continue;
            }
            
            // 检查积分
            $requiredPoints = Config::IMAGE_GENERATION_COST;
            if (!$auth->checkUserPoints($userId, $requiredPoints)) {
                logToFile("用户积分不足，跳过角色 {$characterName} 的三视图生成");
                continue;
            }
            
            // 扣除积分
            $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '角色三视图生成', 'character_three_view', $characterId);
            if (!$deductResult['success']) {
                logToFile("积分扣除失败，跳过角色 {$characterName} 的三视图生成: " . $deductResult['message']);
                continue;
            }
            
            // 生成三视图
            logToFile("开始生成角色 {$characterName} 的三视图");
            $imageUrl = generateThreeViewImage($threeViewPrompt, $logFile, $text2imgApiUrl, $text2imgApiKey);
            
            if ($imageUrl) {
                // 更新数据库
                $updateSql = "UPDATE characters SET three_view_image = ?, updated_at = NOW() WHERE id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$imageUrl, $characterId]);
                
                logToFile("角色 {$characterName} 三视图生成成功: {$imageUrl}");
                $generatedCount++;
            } else {
                logToFile("角色 {$characterName} 三视图生成失败");
            }
        }
        
        // 任务完成后，从数据库中读取完整的角色数据，包括id和three_view_image
        $sql = "SELECT id, character_number, name, gender, age, clothing_description, makeup_description, character_design, three_view_prompt, three_view_image FROM characters WHERE task_id = ? AND user_id = ? ORDER BY character_number";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$taskId, $userId]);
        $completedCharacters = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 检查是否所有环节都已完成：所有有三视图提示词的角色都已生成三视图图片
        $allStepsCompleted = true;
        $pendingCharacters = [];
        
        foreach ($completedCharacters as $char) {
            // 只有有三视图提示词的角色才需要检查是否生成了图片
            if (!empty($char['three_view_prompt'])) {
                // 如果有提示词但没有生成图片，则任务未完成
                if (empty($char['three_view_image'])) {
                    $allStepsCompleted = false;
                    $pendingCharacters[] = $char['name'];
                }
            }
        }
        
        if ($allStepsCompleted) {
            // 所有环节都已完成，更新任务状态为完成
            logToFile("所有角色创作环节已完成，开始终止任务");
            
            // 更新任务进度文件
            updateTaskProgress($taskId, $resultFile, 100, '角色创作完成', 'completed', $completedCharacters);
            
            // 更新数据库中的任务状态
            require_once __DIR__ . '/TaskManager.php';
            $taskManager = new TaskManager();
            $taskManager->updateTaskProgress($taskId, 100, '角色创作完成');
            $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_COMPLETED, 100);
            
            logToFile("角色创作任务已成功终止");
        } else {
            // 仍有环节未完成，任务继续处理中
            $pendingCount = count($pendingCharacters);
            $pendingList = implode(', ', $pendingCharacters);
            logToFile("角色创作任务仍有 {$pendingCount} 个角色的三视图未生成：{$pendingList}");
            
            // 更新任务进度文件
            updateTaskProgress($taskId, $resultFile, 80, '角色创作进行中，部分角色三视图未生成', 'processing', $completedCharacters);
            
            // 更新数据库中的任务状态为处理中
            require_once __DIR__ . '/TaskManager.php';
            $taskManager = new TaskManager();
            $taskManager->updateTaskProgress($taskId, 80, '角色创作进行中，部分角色三视图未生成');
            $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_PROCESSING, 80);
            
            logToFile("角色创作任务部分完成，已生成 {$generatedCount}/{$totalCount} 个角色的三视图");
        }
        
        return true;
        
    } catch (Exception $e) {
        logToFile("角色创作异常: " . $e->getMessage());
        logToFile("错误堆栈: " . $e->getTraceAsString());
        
        updateTaskProgress($taskId, $resultFile ?? '', 100, '角色创作失败', 'error', []);
        
        // 更新数据库中的任务状态
        require_once __DIR__ . '/TaskManager.php';
        $taskManager = new TaskManager();
        $taskManager->updateTaskProgress($taskId, 100, '角色创作失败');
        $taskManager->updateTaskStatus($taskId, TaskManager::STATUS_FAILED, 100);
        
        return false;
    }
}

function parseCharacterResponseFromFile($filePath, $crewId, $taskId, $userId, $customLogFile = null) {
    global $logFile;
    $targetLogFile = $customLogFile ?? $logFile;
    
    logToFile("从文件读取API响应: {$filePath}", $targetLogFile);
    
    // 检查文件是否存在
    if (!file_exists($filePath)) {
        logToFile("文件不存在: {$filePath}", $targetLogFile);
        return [];
    }
    
    $jsonContent = file_get_contents($filePath);
    if ($jsonContent === false) {
        logToFile("无法读取文件: {$filePath}", $targetLogFile);
        return [];
    }
    
    $apiResponseData = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logToFile("JSON解析失败: " . json_last_error_msg(), $targetLogFile);
        return [];
    }
    
    $rawContent = $apiResponseData['raw_content'] ?? '';
    if (empty($rawContent)) {
        logToFile("文件中没有原始内容", $targetLogFile);
        return [];
    }
    
    // 调用原有的parseCharacterResponse函数解析角色信息
    return parseCharacterResponse($rawContent, $crewId, $taskId, $userId, $customLogFile);
}

/**
 * 清理非法字符，确保字符串是有效的UTF-8编码
 * @param string $string 输入字符串
 * @return string 清理后的字符串
 */
function cleanIllegalCharacters($string) {
    if (empty($string)) {
        return $string;
    }
    
    // 1. 确保字符串是有效的UTF-8编码，移除无效字符
    $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8//IGNORE');
    
    // 2. 移除所有控制字符，除了换行符、回车符和制表符
    $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
    
    // 3. 移除零宽字符和其他不可见字符
    $string = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u', '', $string);
    
    // 4. 移除UTF-8替换字符 "��"（十六进制: efbfbd）
    $string = str_replace("��", '', $string);
    $string = str_replace("??", '', $string);
    
    // 5. 再次确保字符串是有效的UTF-8编码
    $string = iconv('UTF-8', 'UTF-8//IGNORE', $string);
    
    return $string;
}

function parseCharacterResponse($content, $crewId, $taskId, $userId, $customLogFile = null) {
    global $logFile;
    $targetLogFile = $customLogFile ?? $logFile;
    
    // 1. 统一换行符
    $content = preg_replace('/\r\n/', "\n", $content);
    $content = preg_replace('/\r/', "\n", $content);
    
    // 2. 分割成行
    $lines = explode("\n", $content);
    $currentCharacter = null;
    $characters = [];
    $lineNumber = 0;
    
    logToFile("开始解析角色内容，总行数: " . count($lines), $targetLogFile);
    
    $characterNumber = 0;
    
    foreach ($lines as $line) {
        $lineNumber++;
        // 先记录原始行，用于调试
        $rawLine = $line;
        
        // 只做基本清理，避免过度清理导致数据丢失
        $line = trim($line);
        
        // 跳过空行
        if (empty($line)) {
            continue;
        }
        
        // 只清理一次，避免重复清理导致数据丢失
        $line = cleanIllegalCharacters($line);
        
        // 匹配角色名称行
        if (preg_match('/^\s*\d+[.、]\s*(.+)$/u', $line, $matches)) {
            $rawCharacterName = trim($matches[1]);
            
            // 记录原始角色名称，用于调试
            logToFile("第{$lineNumber}行: 原始角色名称 - " . $rawCharacterName, $targetLogFile);
            logToFile("第{$lineNumber}行: 原始角色名称十六进制 - " . bin2hex($rawCharacterName), $targetLogFile);
            
            // 移除可能的冒号和后续内容，只保留角色名称
            $colonPos = strpos($rawCharacterName, ':');
            if ($colonPos === false) {
                $colonPos = strpos($rawCharacterName, '：');
            }
            if ($colonPos !== false) {
                $rawCharacterName = trim(substr($rawCharacterName, 0, $colonPos));
                logToFile("第{$lineNumber}行: 移除冒号后角色名称 - " . $rawCharacterName, $targetLogFile);
            }
            
            // 清理角色名称中的非法字符
            $characterName = cleanIllegalCharacters($rawCharacterName);
            
            logToFile("第{$lineNumber}行: 清理后角色名称 - " . $characterName, $targetLogFile);
            
            // 检查是否为关系标题，跳过
            if (strpos($characterName, '与') !== false && strpos($characterName, '的关系') !== false) {
                logToFile("第{$lineNumber}行: 跳过关系标题 - " . $characterName, $targetLogFile);
                continue;
            }
            
            // 如果清理后角色名称为空，尝试使用原始名称的一部分
            if (empty($characterName)) {
                // 尝试从原始行中直接提取角色名称，绕过cleanIllegalCharacters
                if (preg_match('/^\s*\d+[.、]\s*([\x{4e00}-\x{9fa5}a-zA-Z0-9]+)/u', $line, $nameMatch)) {
                    $characterName = trim($nameMatch[1]);
                    logToFile("第{$lineNumber}行: 从原始行中提取角色名称 - " . $characterName, $targetLogFile);
                } else {
                    // 如果仍然无法提取，生成一个默认名称
                    $characterName = "角色{$lineNumber}";
                    logToFile("第{$lineNumber}行: 生成默认角色名称 - " . $characterName, $targetLogFile);
                }
            }
            
            logToFile("第{$lineNumber}行: 最终角色名称 - " . $characterName, $targetLogFile);
            logToFile("第{$lineNumber}行: 最终角色名称十六进制 - " . bin2hex($characterName), $targetLogFile);
            
            // 保存当前角色（只有当角色名称不为空时才保存）
            if ($currentCharacter && !empty($currentCharacter['name'])) {
                $characters[] = $currentCharacter;
                logToFile("保存角色: " . $currentCharacter['name'], $targetLogFile);
            }
            
            $characterNumber++;
            
            // 创建新角色，无论角色名称是否为空（至少有一个默认名称）
            $currentCharacter = [
                'user_id' => $userId,
                'crew_id' => $crewId,
                'task_id' => $taskId,
                'character_number' => $characterNumber,
                'name' => $characterName,
                'description' => '',
                'gender' => '未知',
                'age' => '',
                'clothing_description' => '',
                'makeup_description' => '',
                'accessories' => '',
                'character_arc' => '',
                'character_development' => '',
                'relationship_nodes' => '',
                'relationship_types' => '',
                'relationship_details' => '',
                'graph_level' => 0,
                'centrality_score' => 0,
                'makeup' => '',
                'character_design' => '',
                'three_view_prompt' => '',
                'three_view_image' => '',
                'status' => 'active'
            ];
            continue;
        }
        
        if (!$currentCharacter) {
            continue;
        }
        
        // 调试信息：记录处理前的行内容
        logToFile("第{$lineNumber}行: 处理前的行内容 - " . $line, $targetLogFile);
        
        // 先处理列表符号的字段行
        $isListLine = false;
        $originalLine = $line;
        if (substr($line, 0, 2) === '- ') {
            $isListLine = true;
            $line = substr($line, 2); // 移除列表符号
            logToFile("第{$lineNumber}行: 移除列表符号 '- ' 后的内容 - " . $line, $targetLogFile);
        } elseif (substr($line, 0, 2) === '* ') {
            $isListLine = true;
            $line = substr($line, 2); // 移除列表符号
            logToFile("第{$lineNumber}行: 移除列表符号 '* ' 后的内容 - " . $line, $targetLogFile);
        }
        
        // 匹配字段行：字段名：字段值
        $colonPos1 = strpos($line, '：'); // 中文冒号
        $colonPos2 = strpos($line, ':');  // 英文冒号
        $colonPos = $colonPos1 !== false ? $colonPos1 : $colonPos2;
        
        logToFile("第{$lineNumber}行: 中文冒号位置 - " . ($colonPos1 !== false ? $colonPos1 : '未找到'), $targetLogFile);
        logToFile("第{$lineNumber}行: 英文冒号位置 - " . ($colonPos2 !== false ? $colonPos2 : '未找到'), $targetLogFile);
        logToFile("第{$lineNumber}行: 最终冒号位置 - " . ($colonPos !== false ? $colonPos : '未找到'), $targetLogFile);
        
        if ($colonPos !== false) {
            $fieldName = trim(substr($line, 0, $colonPos));
            $fieldContent = trim(substr($line, $colonPos + 1));
            
            // 调试信息：记录提取的字段名和字段值
            logToFile("第{$lineNumber}行: 提取的字段名 - " . $fieldName, $targetLogFile);
            logToFile("第{$lineNumber}行: 提取的字段值 - " . $fieldContent, $targetLogFile);
            
            // 清理字段名和字段值
            $fieldName = cleanIllegalCharacters($fieldName);
            $fieldContent = cleanIllegalCharacters($fieldContent);
            
            // 调试信息：记录清理后的字段名和字段值
            logToFile("第{$lineNumber}行: 清理后的字段名 - " . $fieldName, $targetLogFile);
            logToFile("第{$lineNumber}行: 清理后的字段值 - " . $fieldContent, $targetLogFile);
            
            // 根据字段名更新角色信息
            updateCharacterField($currentCharacter, $fieldName, $fieldContent);
            logToFile("第{$lineNumber}行: 添加字段 {$fieldName} - " . $fieldContent, $targetLogFile);
        } else {
            logToFile("第{$lineNumber}行: 未找到冒号，跳过该行", $targetLogFile);
        }
    }
    
    // 保存最后一个角色（确保即使只有一个角色也能被保存）
    if ($currentCharacter && !empty($currentCharacter['name'])) {
        $characters[] = $currentCharacter;
        logToFile("保存最后一个角色: " . $currentCharacter['name'], $targetLogFile);
    }
    
    // 确保角色数组按character_number排序
    usort($characters, function($a, $b) {
        return $a['character_number'] - $b['character_number'];
    });
    
    logToFile("解析完成，共 " . count($characters) . " 个角色", $targetLogFile);
    
    return $characters;
}

/**
 * 更新角色字段信息
 * @param array $character 角色数组
 * @param string $fieldName 字段名
 * @param string $fieldContent 字段内容
 */
function updateCharacterField(&$character, $fieldName, $fieldContent) {
    // 字段内容已经在调用前清理过，无需重复清理
    
    switch ($fieldName) {
        case '角色描述':
        case '描述':
            $character['description'] = $fieldContent;
            break;
        case '性别':
            $character['gender'] = $fieldContent;
            break;
        case '年龄':
            $character['age'] = $fieldContent;
            break;
        case '服装':
            $character['clothing_description'] = $fieldContent;
            break;
        case '妆造':
            $character['makeup_description'] = $fieldContent;
            $character['makeup'] = $fieldContent;
            break;
        case '人设':
        case '性格':
        case '背景':
        case '身份':
            if (empty($character['character_design'])) {
                $character['character_design'] = $fieldContent;
            } else {
                $character['character_design'] .= ' | ' . $fieldContent;
            }
            break;
        default:
            if (empty($character['description'])) {
                $character['description'] = $fieldName . '：' . $fieldContent;
            } else {
                $character['description'] .= ' | ' . $fieldName . '：' . $fieldContent;
            }
            break;
    }
}

function extractGender($description, $logFile) {
    global $logFile;
    // 清理描述中的非法字符
    $description = cleanIllegalCharacters($description);
    
    $genderKeywords = [
        '男' => '男', 
        '男性' => '男', 
        '先生' => '男', 
        '男孩' => '男',
        '女' => '女', 
        '女性' => '女', 
        '女士' => '女', 
        '女孩' => '女',
        '他' => '男',
        '她' => '女'
    ];
    
    foreach ($genderKeywords as $keyword => $value) {
        if (strpos($description, $keyword) !== false) {
            logToFile("检测到性别: {$value}", $logFile);
            return $value;
        }
    }
    
    return '未知';
}

function extractAge($description, $logFile) {
    global $logFile;
    // 清理描述中的非法字符
    $description = cleanIllegalCharacters($description);
    
    if (preg_match('/(\d+)岁/', $description, $matches)) {
        $age = $matches[1];
        logToFile("检测到年龄: {$age}岁", $logFile);
        return $age;
    }
    
    if (preg_match('/(\d+)岁左右/', $description, $matches)) {
        $age = $matches[1];
        logToFile("检测到年龄范围: {$age}岁左右", $logFile);
        return $age;
    }
    
    if (preg_match('/(\d+)多岁/', $description, $matches)) {
        $age = $matches[1];
        logToFile("检测到年龄范围: {$age}多岁", $logFile);
        return $age;
    }
    
    if (preg_match('/(\d+)多岁左右/', $description, $matches)) {
        $age = $matches[1];
        logToFile("检测到年龄范围: {$age}多岁左右", $logFile);
        return $age;
    }
    
    if (preg_match('/(\d+)多岁上下/', $description, $matches)) {
        $age = $matches[1];
        logToFile("检测到年龄范围: {$age}多岁上下", $logFile);
        return $age;
    }
    
    return '';
}

function extractClothing($description, $logFile) {
    global $logFile;
    // 清理描述中的非法字符
    $description = cleanIllegalCharacters($description);
    
    $clothingKeywords = ['服装' => '服装', '衣服' => '服装', '穿着' => '服装', '身着' => '服装'];
    
    foreach ($clothingKeywords as $keyword) {
        if (strpos($description, $keyword) !== false) {
            $clothingStart = strpos($description, $keyword);
            $clothingEnd = strpos($description, '。', $clothingStart);
            
            if ($clothingEnd !== false) {
                $clothing = substr($description, $clothingStart, $clothingEnd - $clothingStart + 1);
                // 清理提取的服装信息
                $clothing = cleanIllegalCharacters($clothing);
                logToFile("检测到服装: {$clothing}");
                return $clothing;
            }
        }
    }
    
    return '';
}

function extractMakeup($description, $logFile) {
    global $logFile;
    // 清理描述中的非法字符
    $description = cleanIllegalCharacters($description);
    
    $makeupKeywords = ['妆造' => '妆造', '妆容' => '妆造', '化妆' => '化妆'];
    
    foreach ($makeupKeywords as $keyword) {
        if (strpos($description, $keyword) !== false) {
            $makeupStart = strpos($description, $keyword);
            $makeupEnd = strpos($description, '。', $makeupStart);
            
            if ($makeupEnd !== false) {
                $makeup = substr($description, $makeupStart, $makeupEnd - $makeupStart + 1);
                // 清理提取的妆造信息
                $makeup = cleanIllegalCharacters($makeup);
                logToFile("检测到妆造: {$makeup}");
                return $makeup;
            }
        }
    }
    
    return '';
}

function extractCharacterDesign($description, $logFile) {
    global $logFile;
    // 清理描述中的非法字符
    $description = cleanIllegalCharacters($description);
    
    $designKeywords = ['人设' => '人设', '性格' => '性格', '背景' => '背景', '身份' => '身份'];
    
    foreach ($designKeywords as $keyword => $value) {
        if (strpos($description, $keyword) !== false) {
            $designStart = strpos($description, $keyword);
            $designEnd = strpos($description, '。', $designStart);
            
            if ($designEnd !== false) {
                $design = substr($description, $designStart, $designEnd - $designStart + 1);
                // 清理提取的人设信息
                $design = cleanIllegalCharacters($design);
                logToFile("检测到人设: {$design}");
                return $design;
            }
        }
    }
    
    return '';
}

function generateThreeViewPrompt($character) {
    $prompt = "生成一张完整全尺寸全身（从头到脚部）三视图（依次为：正面、侧影、背影）：{$character['name']}，";
    
    if (!empty($character['gender']) && $character['gender'] !== '未知') {
        $prompt .= "{$character['gender']}，";
    }
    
    if (!empty($character['age'])) {
        $prompt .= "{$character['age']}，";
    }
    
    if (!empty($character['clothing_description'])) {
        $prompt .= "{$character['clothing_description']}，";
    }
    
    if (!empty($character['makeup_description'])) {
        $prompt .= "{$character['makeup_description']}，";
    }
    
    if (!empty($character['character_design'])) {
        $prompt .= "{$character['character_design']}。";
    }
    
    $prompt .= "尺寸：16:9；风格：电影写实。";
    
    // 清理生成的提示词，确保没有非法字符
    return cleanIllegalCharacters($prompt);
}

function generateThreeViewImage($prompt, $logFile, $apiUrl = null, $apiKey = null) {
    logToFile("开始生成三视图，提示词: " . mb_substr($prompt, 0, 100, 'UTF-8') . "...", $logFile);
    
    if ($apiUrl === null) {
        $apiUrl = Config::TEXT2IMG_API_URL();
    }
    
    if ($apiKey === null) {
        $apiKey = Config::TEXT2IMG_API_KEY();
    }
    
    if (empty($apiUrl) || empty($apiKey)) {
        logToFile("文生图API未配置", $logFile);
        return '';
    }
    
    logToFile("使用API URL: {$apiUrl}", $logFile);
    logToFile("使用API Key: {$apiKey}", $logFile);
    
    // 从Config类获取文生图API模型
    $text2imgApiModel = Config::TEXT2IMG_API_MODEL();
    if (empty($text2imgApiModel)) {
        $text2imgApiModel = 'doubao-seedream-4-5-251128'; // 默认模型
    }
    
    logToFile("使用API Model: {$text2imgApiModel}", $logFile);
    
    // 使用官方文生图API格式
    $requestData = [
        'model' => $text2imgApiModel,
        'prompt' => $prompt,
        'sequential_image_generation' => 'disabled',
        'response_format' => 'url',
        'size' => '2K',
        'stream' => false,
        'watermark' => false
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
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
        logToFile("文生图请求失败: {$error}", $logFile);
        return '';
    }
    
    if ($httpCode !== 200) {
        logToFile("文生图API返回错误: HTTP {$httpCode}", $logFile);
        return '';
    }
    
    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logToFile("文生图响应JSON解析错误: " . json_last_error_msg(), $logFile);
        return '';
    }
    
    logToFile("API响应: {$response}", $logFile);
    
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
        logToFile("文生图响应中没有图片URL", $logFile);
        return '';
    }
    
    logToFile("文生图成功，原始URL: {$imageUrl}", $logFile);
    
    $outputDir = __DIR__ . '/outputs/images';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filename = 'three_view_' . uniqid() . '_' . time() . '.png';
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
        logToFile("图片已下载到本地: {$localImageUrl}", $logFile);
        return $localImageUrl;
    } else {
        logToFile("图片下载失败，使用原始URL", $logFile);
        return $imageUrl;
    }
}

function updateTaskProgress($taskId, $resultFile, $progress, $message, $status, $characters = []) {
    $result = [
        'task_id' => $taskId,
        'status' => $status,
        'progress' => $progress,
        'message' => $message,
        'current_stage' => $status === 'completed' ? 'completed' : 'processing',
        'characters' => $characters,
        'logs' => ["[" . date('Y-m-d H:i:s') . "] {$message}"]
    ];
    
    $jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE);
    if ($jsonResult === false) {
        return;
    }
    
    $writeResult = file_put_contents($resultFile, $jsonResult, LOCK_EX);
    if ($writeResult === false) {
        error_log("无法更新任务文件: {$resultFile}");
    }
}

if (php_sapi_name() === 'cli' && isset($argv[1]) && realpath($argv[0]) === __FILE__) {
    processCharacterCreation($argv[1]);
    exit(0);
}
?>
