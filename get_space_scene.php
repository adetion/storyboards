<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取场次名称、任务ID和用户ID
$sceneName = isset($_GET['scene_name']) ? trim($_GET['scene_name']) : '';
$taskId = isset($_GET['task_id']) ? trim($_GET['task_id']) : null;
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : ($_SESSION['user_id'] ?? null);

if (empty($sceneName)) {
    echo json_encode(['success' => false, 'message' => '场次名称不能为空']);
    exit;
}

if (!$userId) {
    echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
    exit;
}

// 引入配置文件
require_once __DIR__ . '/config.php';

try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 处理场次名称，提取核心场景名称
    // 移除"场次 X - "前缀
    $coreSceneName = preg_replace('/^场次\s*\d+\s*-\s*/', '', $sceneName);
    $coreSceneName = trim($coreSceneName);
    
    // 调试信息
    $debugInfo = [
        'original_scene_name' => $sceneName,
        'core_scene_name' => $coreSceneName,
        'task_id' => $taskId,
        'user_id' => $userId
    ];
    
    // 测试输出
    // echo json_encode([
    //     'success' => true,
    //     'message' => '测试信息',
    //     'data' => [
    //         'scene_name' => $sceneName,
    //         'core_scene_name' => $coreSceneName,
    //         'task_id' => $taskId
    //     ]
    // ]);
    // exit;

    // 查询相关的时空场景信息
    // 首先尝试使用核心场景名称进行精确匹配
    $sql = "SELECT id, name, imageUrl FROM spaces WHERE name = :core_scene_name AND user_id = :user_id";
    $params = [':core_scene_name' => $coreSceneName, ':user_id' => $userId];
    
    // 添加task_id条件（如果提供）
    if ($taskId !== null) {
        $sql .= " AND task_id = :task_id";
        $params[':task_id'] = $taskId;
    }
    
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $space = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$space && $taskId !== null) {
        // 如果使用task_id条件没有找到，尝试不使用task_id条件
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE name = :core_scene_name AND user_id = :user_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':core_scene_name' => $coreSceneName, ':user_id' => $userId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$space) {
        // 如果没有找到完全匹配的，尝试使用核心场景名称进行模糊匹配
        $likePattern = '%' . $coreSceneName . '%';
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id";
        $params = [':pattern' => $likePattern, ':user_id' => $userId];
        
        // 添加task_id条件（如果提供）
        if ($taskId !== null) {
            $sql .= " AND task_id = :task_id";
            $params[':task_id'] = $taskId;
        }
        
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$space && $taskId !== null) {
        // 如果使用task_id条件进行模糊匹配没有找到，尝试不使用task_id条件
        $likePattern = '%' . $coreSceneName . '%';
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pattern' => $likePattern, ':user_id' => $userId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$space) {
        // 如果仍然没有找到，尝试使用原始场次名称进行模糊匹配
        $likePattern = '%' . $sceneName . '%';
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id";
        $params = [':pattern' => $likePattern, ':user_id' => $userId];
        
        // 添加task_id条件（如果提供）
        if ($taskId !== null) {
            $sql .= " AND task_id = :task_id";
            $params[':task_id'] = $taskId;
        }
        
        $sql .= " LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$space && $taskId !== null) {
        // 如果使用task_id条件进行原始场次名称模糊匹配没有找到，尝试不使用task_id条件
        $likePattern = '%' . $sceneName . '%';
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':pattern' => $likePattern, ':user_id' => $userId]);
        $space = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$space) {
        // 尝试移除核心场景名称中的括号和内容
        $cleanCoreSceneName = preg_replace('/\s*[\(（].*?[\)）]\s*$/', '', $coreSceneName);
        $cleanCoreSceneName = trim($cleanCoreSceneName);
        
        if ($cleanCoreSceneName !== $coreSceneName) {
                // 尝试使用清理后的核心场景名称进行模糊匹配
                $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id";
                $params = [':pattern' => '%' . $cleanCoreSceneName . '%', ':user_id' => $userId];
            
            if ($taskId !== null) {
                $sql .= " AND task_id = :task_id";
                $params[':task_id'] = $taskId;
            }
            
            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $space = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if (!$space) {
        // 尝试使用核心场景名称的关键字进行匹配
        // 例如："直升机坠落现场" 匹配 "直升机坠落现场 (室外)"
        $sql = "SELECT id, name, imageUrl FROM spaces WHERE user_id = :user_id";
        $params = [':user_id' => $userId];
        
        if ($taskId !== null) {
            $sql .= " AND task_id = :task_id";
            $params[':task_id'] = $taskId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $allSpaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 对所有时空场景进行相似度匹配
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($allSpaces as $spaceItem) {
            // 清理空间场景名称
            $cleanSpaceName = preg_replace('/\s*[\(（].*?[\)）]\s*$/', '', $spaceItem['name']);
            $cleanSpaceName = trim($cleanSpaceName);
            
            // 清理核心场景名称
            $cleanCoreSceneName = preg_replace('/\s*[\(（].*?[\)）]\s*$/', '', $coreSceneName);
            $cleanCoreSceneName = trim($cleanCoreSceneName);
            
            // 计算相似度
            $score = 0;
            
            // 检查核心场景名称是否包含在空间场景名称中
            if (strpos($spaceItem['name'], $coreSceneName) !== false) {
                $score += 10;
            }
            // 检查空间场景名称是否包含在核心场景名称中
            if (strpos($coreSceneName, $spaceItem['name']) !== false) {
                $score += 10;
            }
            // 检查清理后的核心场景名称是否包含在清理后的空间场景名称中
            if (strpos($cleanSpaceName, $cleanCoreSceneName) !== false) {
                $score += 8;
            }
            // 检查清理后的空间场景名称是否包含在清理后的核心场景名称中
            if (strpos($cleanCoreSceneName, $cleanSpaceName) !== false) {
                $score += 8;
            }
            // 检查核心场景名称是否与清理后的空间场景名称相同
            if ($coreSceneName === $cleanSpaceName) {
                $score += 12;
            }
            // 检查清理后的核心场景名称是否与空间场景名称相同
            if ($cleanCoreSceneName === $spaceItem['name']) {
                $score += 12;
            }
            
            // 如果分数高于当前最佳分数，更新最佳匹配
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $spaceItem;
            }
        }
        
        // 如果找到相似度高于阈值的匹配
        if ($bestMatch && $bestScore >= 8) {
            $space = $bestMatch;
        }
    }
    
    if (!$space) {
        // 尝试使用核心场景名称的前20个字符进行匹配
        $shortCoreSceneName = mb_substr($coreSceneName, 0, 20, 'UTF-8');
        if (mb_strlen($coreSceneName, 'UTF-8') > 20) {
            $sql = "SELECT id, name, imageUrl FROM spaces WHERE name LIKE :pattern AND user_id = :user_id";
            $params = [':pattern' => '%' . $shortCoreSceneName . '%', ':user_id' => $userId];
            
            if ($taskId !== null) {
                $sql .= " AND task_id = :task_id";
                $params[':task_id'] = $taskId;
            }
            
            $sql .= " LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $space = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }

    if ($space) {
        echo json_encode([
            'success' => true,
            'space' => $space,
            'debug' => $debugInfo
        ]);
    } else {
        // 尝试查询所有用户的时空场景，用于调试
        $sql = "SELECT id, name, task_id FROM spaces WHERE user_id = :user_id LIMIT 10";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':user_id' => $userId]);
        $allSpaces = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $debugInfo['all_spaces'] = $allSpaces;
        
        echo json_encode([
            'success' => false,
            'message' => '未找到相关的时空场景',
            'debug' => $debugInfo
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '数据库错误: ' . $e->getMessage()
    ]);
}
?>
