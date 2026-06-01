<?php

/**
 * 处理角色分析的脚本
 * 专门负责从剧本中提取角色信息并存入数据库
 */

// 设置最大执行时间
set_time_limit(0);

// 增加内存限制
ini_set('memory_limit', '512M');

// 引入必要的文件
dirname(__FILE__);
require_once __DIR__ . '/config.php';

/**
 * 处理角色分析的主函数
 * @param string $taskParamsFile 任务参数文件路径
 * @return bool 是否成功
 */
function processCharacters($taskParamsFile)
{
    // 添加详细日志输出，便于调试
    $logFile = __DIR__ . '/process_characters.log';
    $resultFile = __DIR__ . '/results/' . basename($taskParamsFile, '.json');
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析任务开始\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 任务参数文件: {$taskParamsFile}\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PHP version: " . phpversion() . "\n", FILE_APPEND);

    // 检查文件是否存在
    if (!file_exists($taskParamsFile)) {
        $errorMsg = "任务参数文件不存在: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    // 读取任务参数
    $taskParamsJson = file_get_contents($taskParamsFile);
    if ($taskParamsJson === false) {
        $errorMsg = "无法读取任务参数文件: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    $taskParams = json_decode($taskParamsJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "JSON解析失败: " . json_last_error_msg() . "\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 任务参数解析成功\n", FILE_APPEND);

    // 提取任务参数
    $taskId = $taskParams['task_id'] ?? '';
    $script = $taskParams['script'] ?? '';
    $userId = $taskParams['user_id'] ?? 0;

    if (empty($taskId) || empty($script)) {
        $errorMsg = "任务参数不完整: task_id={$taskId}, script_length=" . strlen($script) . "\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 任务ID: {$taskId}\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 剧本长度: " . strlen($script) . " 字符\n", FILE_APPEND);

    try {
        // 获取API密钥
        $apiKey = Config::DEEPSEEK_API_KEY();
        if (empty($apiKey)) {
            throw new Exception('DeepSeek API密钥未配置');
        }

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始调用角色分析API\n", FILE_APPEND);

        // 获取数据库连接
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 获取用户的当前剧组ID
        $sql = "SELECT id, current_characters_task_id FROM crew WHERE admin_user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);
        $crew = $stmt->fetch(PDO::FETCH_ASSOC);

        $crewId = null;
        if ($crew) {
            $crewId = $crew['id'];
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 找到剧组ID: {$crewId}\n", FILE_APPEND);
        } else {
            // 用户没有剧组，创建一个默认剧组
            $sql = "INSERT INTO crew (admin_user_id, name, status, current_characters_task_id, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, NOW(), NOW())";
            $stmt = $pdo->prepare($sql);
            $defaultCrewName = "{$userId}的默认剧组";
            $stmt->execute([$userId, $defaultCrewName, 1, $taskId]);
            $crewId = $pdo->lastInsertId();
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 创建新剧组ID: {$crewId}\n", FILE_APPEND);
        }

        // 构建角色分析提示词
        $characterAnalysisPrompt = '请从剧本中提炼出所有角色信息，并严格按照以下Markdown格式输出：

《剧本标题》角色设定及服装妆造分析

【角色分析格式】
请按角色顺序逐个输出，每个角色包含以下完整信息：

1. 角色名1
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
15. 如果某个角色没有某个字段的信息，可以省略该字段，但不要输出空值';

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

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始调用API\n", FILE_APPEND);

        $characterResponse = callDeepSeekAPI($apiKey, $characterMessages);

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API返回，大小: " . strlen($characterResponse) . " 字节\n", FILE_APPEND);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API返回内容前500字符: " . substr($characterResponse, 0, 500) . "\n", FILE_APPEND);

        // 保存API返回内容到文件
        $apiResponseFile = __DIR__ . '/character_api_response_' . $taskId . '.txt';
        file_put_contents($apiResponseFile, $characterResponse);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] API返回内容已保存到: " . $apiResponseFile . "\n", FILE_APPEND);

        // 解析角色信息
        $characters = parseCharacters($characterResponse, $crewId, $logFile);

        if (empty($characters)) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 没有解析到角色数据\n", FILE_APPEND);
            return false;
        }

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 解析出 " . count($characters) . " 个角色\n", FILE_APPEND);

        // 保存角色数据到数据库
        $result = saveCharactersToDatabase($characters, $crewId, $pdo, $logFile, $taskId);

        if ($result) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析完成\n", FILE_APPEND);

            // 更新resultFile，标记角色分析完成，准备进行分镜分析
            $updateResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => 1,
                'total_rounds' => 2,
                'progress' => 50,
                'start_time' => date('Y-m-d H:i:s'),
                'script_preview' => mb_strlen($script, 'UTF-8') > 500
                    ? mb_substr($script, 0, 500, 'UTF-8') . '...'
                    : $script,
                'prompt' => '',
                'content' => '',
                'rounds' => 2,
                'message' => '角色分析完成，正在启动分镜分析...',
                'is_character_analysis' => true,
                'character_analysis_completed' => true // 标记角色分析已完成
            ];

            $updateJsonResult = json_encode($updateResult, JSON_UNESCAPED_UNICODE);
            $writeResult = file_put_contents($resultFile, $updateJsonResult, LOCK_EX);
            if ($writeResult === false) {
                error_log("无法更新resultFile: {$resultFile}");
            } else {
                file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 已更新resultFile，标记角色分析完成\n", FILE_APPEND);
            }

            return true;
        } else {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色分析失败\n", FILE_APPEND);
            return false;
        }
    } catch (Exception $e) {
        $errorMsg = "角色分析异常: " . $e->getMessage() . "\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 错误堆栈: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        return false;
    }
}

/**
 * 调用DeepSeek API
 */
function callDeepSeekAPI($apiKey, $messages)
{
    $logFile = __DIR__ . '/process_characters.log';

    $requestData = [
        'model' => Config::DEEPSEEK_MODEL(),
        'messages' => $messages,
        'temperature' => 0.7,
        'max_tokens' => 8000,
        'stream' => false
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => Config::DEEPSEEK_API_URL(),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($requestData),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'User-Agent: CharacterAnalyzer/1.0'
        ],
        CURLOPT_TIMEOUT => 12000,
        CURLOPT_CONNECTTIMEOUT => 300
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($httpCode !== 200 || !$response) {
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
}

/**
 * 解析角色信息
 */
function parseCharacters($content, $crewId, $logFile)
{
    $lines = explode("\n", $content);
    $currentCharacter = null;
    $characters = [];
    $lineNumber = 0;

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 开始解析角色内容，总行数: " . count($lines) . "\n", FILE_APPEND);

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

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 解析完成，共 " . count($characters) . " 个角色\n", FILE_APPEND);

    return $characters;
}

/**
 * 保存角色数据到数据库
 */
function saveCharactersToDatabase($characters, $crewId, $pdo, $logFile, $taskId)
{
    if (empty($characters)) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 没有角色数据需要保存\n", FILE_APPEND);
        return false;
    }

    // 确保数据库连接使用utf8mb4字符集
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");

    // 过滤掉无效的角色数据
    $validCharacters = array_filter($characters, function ($char) use ($crewId, $logFile) {
        $isValid = !empty($char['name']) && !empty($char['crew_id']) && $char['crew_id'] == $crewId;
        if (!$isValid) {
            file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 过滤无效角色: name=" . ($char['name'] ?? 'null') . ", crew_id=" . ($char['crew_id'] ?? 'null') . "\n", FILE_APPEND);
        }
        return $isValid;
    });

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 过滤后剩余 " . count($validCharacters) . " 个有效角色\n", FILE_APPEND);

    // 保存角色数据到单独的JSON文件，便于调试
    $charactersJsonFile = __DIR__ . '/characters_' . $taskId . '.json';
    $charactersJson = json_encode($validCharacters, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    file_put_contents($charactersJsonFile, $charactersJson);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 角色数据已保存到: " . $charactersJsonFile . "\n", FILE_APPEND);

    // 第一步：INSERT所有角色到数据库（只包含基本信息）
    $insertSql = "INSERT INTO characters (crew_id, name, description, graph_level, centrality_score) VALUES ";
    $values = [];
    $params = [];
    $characterIdMap = [];

    foreach ($validCharacters as $index => $char) {
        $placeholders = [];
        foreach (['crew_id', 'name', 'description', 'graph_level', 'centrality_score'] as $field) {
            $placeholders[] = '?';
            $params[] = $char[$field] ?? '';
        }
        $values[] = '(' . implode(', ', $placeholders) . ')';
    }

    $insertSql .= implode(', ', $values);

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 执行角色插入SQL\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 参数数量: " . count($params) . "\n", FILE_APPEND);

    try {
        $stmt = $pdo->prepare($insertSql);
        $result = $stmt->execute($params);

        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 执行结果: " . ($result ? '成功' : '失败') . "\n", FILE_APPEND);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 成功插入 " . count($validCharacters) . " 个角色到剧组 {$crewId}\n", FILE_APPEND);

        if (!$result) {
            return false;
        }

        // 第二步：UPDATE每个角色的服装、弧光、关系字段
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

        return true;
    } catch (Exception $e) {
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 保存角色数据失败: " . $e->getMessage() . "\n", FILE_APPEND);
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] 错误堆栈: " . $e->getTraceAsString() . "\n", FILE_APPEND);
        return false;
    }
}

// 检查是否有命令行参数
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    // 命令行模式，直接执行任务
    processCharacters($argv[1]);
    exit(0);
}
