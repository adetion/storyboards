<?php
/**
 * 处理和保存分镜数据的独立函数
 */

// 确保在CLI环境下不执行HTTP相关代码
// 移除CLI检查，允许在任何环境下被包含
// if (php_sapi_name() !== 'cli') {
//     header('Content-Type: application/json');
//     echo json_encode(['error' => '该脚本仅支持CLI环境']);
//     exit(1);
// }

// 加载核心依赖 - 只加载必要的文件
require_once __DIR__ . '/config.php';

/**
 * 解析表格行数据
 */
function pss_parseTableRow($row) {
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
 * 生成场景标签
 */
function pss_generateSceneTags($shotData) {
    $tags = [];
    
    // 时间标签
    $time = $shotData['时间'] ?? '';
    if (in_array($time, ['日', '晨', '中午', '上午', '下午'])) {
        $tags[] = '日戏';
    } elseif (in_array($time, ['夜', '黄昏', '暮'])) {
        $tags[] = '夜戏';
    }
    
    // 内外景标签
    $location = $shotData['地点'] ?? '';
    if (strpos($location, '外') !== false) {
        $tags[] = '外景';
    } else {
        $tags[] = '内景';
    }
    
    // 地点标签
    if (!empty($location)) {
        $tags[] = $location;
    }
    
    return array_unique($tags);
}

/**
 * 处理并保存分镜数据到数据库
 * @param string $taskId 任务ID
 * @param string $content AI分析的文本内容
 * @return array 操作结果
 */
function processAndSaveStoryboards($taskId, $content) {
    // 构建JSON输入格式
    $jsonInput = json_encode([
        'content' => $content
    ]);
    
    // 获取数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 直接使用传入的content，不再进行JSON封装和解封
    // 这避免了JSON转义导致的内容丢失问题
    $content = $content;
    
    // 按行分割文本
    $lines = explode("\n", trim($content));
    
    // 移除空行和处理重复数据
    $dataLines = [];
    $processedLines = [];
    $isFirstHeader = true;
    $maxSceneNumber = 0;
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // 跳过空行
        if (empty($trimmed)) {
            continue;
        }
        
        // 跳过表头分隔线
        if (preg_match('/^\|\s*-+\s*(\|\s*-+\s*)+$/', $trimmed)) {
            continue;
        }
        
        // 检测表头行
        $potentialHeaders = pss_parseTableRow($trimmed);
        $headerKeywords = ['排序号', '场次号', '镜号', '地点', '时间', '天气'];
        $headerMatchCount = 0;
        
        foreach ($headerKeywords as $keyword) {
            if (in_array($keyword, $potentialHeaders)) {
                $headerMatchCount++;
            }
        }
        
        // 处理表头行
        if ($headerMatchCount >= 3) {
            if ($isFirstHeader) {
                // 第一次出现的表头，保留
                $dataLines[] = $trimmed;
                $isFirstHeader = false;
            }
            // 跳过重复表头
            continue;
        }
        
        // 检查是否为有效的数据行（以|开头，包含排序号、场次号、镜号）
        if (preg_match('/^\|\s*\d+\s*\|\s*(\d+)\s*\|\s*\d+\s*\|/', $trimmed, $matches)) {
            $sceneNumber = intval($matches[1]);
            
            // 检查是否已经处理过该行，避免重复
            if (!in_array($trimmed, $processedLines)) {
                $dataLines[] = $trimmed;
                $processedLines[] = $trimmed;
                
                // 更新最大场次号
                if ($sceneNumber > $maxSceneNumber) {
                    $maxSceneNumber = $sceneNumber;
                }
            }
        } else {
            // 非数据行，直接添加
            $dataLines[] = $trimmed;
        }
    }
    
    if (count($dataLines) < 2) {
        throw new InvalidArgumentException('表格数据不足，至少需要表头和数据行');
    }
    
    // 提取表头 - 找到第一个有效的表头行
    $headers = [];
    $dataStartIndex = 0;
    
    for ($i = 0; $i < count($dataLines); $i++) {
        $potentialHeaders = pss_parseTableRow($dataLines[$i]);
        
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
        throw new InvalidArgumentException('未找到有效的表头行');
    }
    
    // 按场次分组数据
        $scenesData = [];
        
        // 处理数据行（从数据开始索引处开始）
        for ($i = $dataStartIndex; $i < count($dataLines); $i++) {
            $rowData = pss_parseTableRow($dataLines[$i]);
            
            // 跳过表头重复行 - 只有当行数据与表头完全匹配时才认为是表头行
            $isHeaderRow = false;
            // 检查前3个单元格是否与表头前3个字段完全匹配
            $headerMatchCount = 0;
            $minCheckFields = min(3, count($headers), count($rowData));
            for ($j = 0; $j < $minCheckFields; $j++) {
                if (trim($rowData[$j]) === trim($headers[$j])) {
                    $headerMatchCount++;
                }
            }
            // 如果前3个字段都匹配，认为是表头行
            if ($headerMatchCount >= 3) {
                $isHeaderRow = true;
            }
            
            if ($isHeaderRow) {
                continue; // 跳过表头重复行
            }
            
            // 如果行数据数量与表头不匹配，尝试处理
            if (count($rowData) < count($headers)) {
                // 行数据不完整，补充缺失的字段
                $missingFields = count($headers) - count($rowData);
                for ($j = 0; $j < $missingFields; $j++) {
                    $rowData[] = '';
                }
            }
            
            // 检查是否为空行或无效数据 - 只有当场次号和镜号都为空时才跳过
            $isEmptyRow = true;
            // 检查前3个单元格（排序号、场次号、镜号）是否都为空
            for ($j = 0; $j < min(3, count($rowData)); $j++) {
                if (!empty(trim($rowData[$j])) && !in_array(trim($rowData[$j]), $headers)) {
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
            
            // 验证必要字段 - 只检查场次号和镜号是否为非空
            if (empty($shotData['场次号']) || empty($shotData['镜号'])) {
                continue;
            }
            
            // 确保场次号是数字
            $sceneNumber = is_numeric($shotData['场次号']) ? intval($shotData['场次号']) : 0;
            if ($sceneNumber <= 0) {
                continue;
            }
            
            $sceneId = "SC" . str_pad($sceneNumber, 3, '0', STR_PAD_LEFT);
            
            if (!isset($scenesData[$sceneId])) {
            // 生成场景标签 - 直接基于时间和地点信息
            $tags = [];
            
            // 时间标签
            $time = $shotData['时间'] ?? '';
            if (in_array($time, ['日', '晨', '中午', '上午', '下午'])) {
                $tags[] = '日戏';
            } elseif (in_array($time, ['夜', '黄昏', '暮'])) {
                $tags[] = '夜戏';
            }
            
            // 内外景标签
            $location = $shotData['地点'] ?? '';
            if (strpos($location, '外') !== false) {
                $tags[] = '外景';
            } else {
                $tags[] = '内景';
            }
            
            // 地点标签
            if (!empty($location)) {
                $tags[] = $location;
            }
            
            $scenesData[$sceneId] = [
                'id' => $sceneId,
                'name' => "场次 {$sceneNumber} - " . ($shotData['地点'] ?? '未知地点'),
                'tags' => array_unique($tags),
                'shots' => []
            ];
        }
            
            // 构建shot对象 - 严格按照源数据字段对应
            $shot = [
                'id' => $shotData['镜号'] ?? '',
                'shotType' => $shotData['景别'] ?? '',
                'duration' => intval($shotData['时长(秒)'] ?? 5),
                'content' => $shotData['内容'] ?? '',
                'remark' => $shotData['内容'] ?? '',
                'sceneExpectation' => $shotData['场景预期'] ?? '',
                'sound' => $shotData['声音设计'] ?? '',
                'cameraAngle' => $shotData['摄像机角度'] ?? '',
                'cameraMovement' => $shotData['运镜'] ?? '',
                'cameraEquipment' => $shotData['摄像机设备'] ?? '',
                'lensFocalLength' => $shotData['镜头焦段'] ?? '',
                'compositionFocus' => $shotData['构图与焦点'] ?? '',
                'lightTone' => $shotData['光线与色调'] ?? '',
                'location' => $shotData['地点'] ?? '',
                'time' => $shotData['时间'] ?? '',
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
            
            // 检查分镜是否已经存在，避免重复数据
            $shotExists = false;
            foreach ($scenesData[$sceneId]['shots'] as $existingShot) {
                if ($existingShot['id'] === $shot['id']) {
                    $shotExists = true;
                    break;
                }
            }
            
            if (!$shotExists) {
                $scenesData[$sceneId]['shots'][] = $shot;
            }
    }
    
    // 按场次号排序
    uasort($scenesData, function($a, $b) {
        return strcmp($a['id'], $b['id']);
    });
    
    // 构建与json/storyboard-data.json格式一致的数据结构
    $finalData = [
        'scenes' => array_values($scenesData)
    ];
    
    // 确保results目录存在
    $resultsDir = __DIR__ . '/results';
    if (!is_dir($resultsDir)) {
        if (mkdir($resultsDir, 0755, true)) {
            error_log("成功创建results目录: {$resultsDir}");
        } else {
            error_log("创建results目录失败: {$resultsDir}");
            throw new RuntimeException('无法创建results目录: ' . $resultsDir);
        }
    }
    
    // 保存到results/{task_id}_storyboards.json文件
    $jsonFilePath = $resultsDir . '/' . $taskId . '_storyboards.json';
    $jsonData = json_encode($finalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    error_log("准备写入JSON文件: {$jsonFilePath}");
    error_log("JSON数据大小: " . strlen($jsonData) . " 字节");
    error_log("JSON数据前100字符: " . substr($jsonData, 0, 100) . "...");
    
    $writeResult = file_put_contents($jsonFilePath, $jsonData);
    if ($writeResult === false) {
        error_log("写入JSON文件失败: {$jsonFilePath}");
        throw new RuntimeException('无法写入JSON文件: ' . $jsonFilePath);
    } else {
        error_log("写入JSON文件成功: {$jsonFilePath}, 写入了 {$writeResult} 字节");
    }
    
    // 从JSON文件读取数据
    $jsonContent = file_get_contents($jsonFilePath);
    if ($jsonContent === false) {
        throw new RuntimeException('无法读取JSON文件: ' . $jsonFilePath);
    }
    
    // 解析JSON数据
    $storyboardData = json_decode($jsonContent, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new InvalidArgumentException('JSON文件解析错误: ' . json_last_error_msg());
    }
    
    // 检查是否包含scenes字段
    if (!isset($storyboardData['scenes']) || !is_array($storyboardData['scenes'])) {
        throw new InvalidArgumentException('JSON文件缺少scenes字段或格式不正确');
    }
    
    // 使用从JSON文件读取的数据
    $scenesData = [];
    foreach ($storyboardData['scenes'] as $scene) {
        $scenesData[$scene['id']] = $scene;
    }
    
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
        foreach ($scenesData as $scene) {
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
        throw new RuntimeException("数据库操作失败: " . $e->getMessage());
    }
}
