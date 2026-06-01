<?php
// 确保所有输出都是JSON格式
header('Content-Type: application/json; charset=utf-8');

// 检查是否在命令行模式下运行
$isCliMode = php_sapi_name() === 'cli';

// 启动会话（仅在非命令行模式下）
if (!$isCliMode && session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!$isCliMode && (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true)) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

// 引入配置文件
try {
    require_once __DIR__ . '/config.php';
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => '配置文件加载失败: ' . $e->getMessage()]);
    exit;
}

// 获取参数
$shotId = $_GET['shot_id'] ?? null;
$taskId = $_GET['task_id'] ?? null;
$sceneId = $_GET['scene_id'] ?? null; // 获取场次编号
$type = $_GET['type'] ?? 'reference'; // 获取类型参数，默认为reference

if (!$shotId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => '缺少必要参数']);
    exit;
}

// 如果在命令行模式下，使用默认用户ID
if ($isCliMode) {
    $userId = 1;
} else {
    $userId = $_SESSION['user_id'];
}

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 获取分镜数据
    if ($taskId) {
        // 如果有 task_id，使用 task_id 查询分镜数据
        if ($sceneId) {
            // 如果有 scenes_id，添加场次条件
            $shotSql = "SELECT characters FROM shots WHERE shots_id = :shot_id AND scenes_id = :scenes_id AND task_id = :task_id AND user_id = :user_id";
            $shotStmt = $pdo->prepare($shotSql);
            $shotStmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
            $shotStmt->bindParam(':scenes_id', $sceneId, PDO::PARAM_STR);
            $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
            $shotStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        } else {
            // 如果没有 scenes_id，只使用 shot_id 和 task_id
            $shotSql = "SELECT characters FROM shots WHERE shots_id = :shot_id AND task_id = :task_id AND user_id = :user_id";
            $shotStmt = $pdo->prepare($shotSql);
            $shotStmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
            $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
            $shotStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        }
    } else {
        // 如果没有 task_id，只使用 shot_id 查询分镜数据
        if ($sceneId) {
            // 如果有 scene_id，添加场次条件
            $shotSql = "SELECT characters FROM shots WHERE shots_id = :shot_id AND scenes_id = :scenes_id AND user_id = :user_id";
            $shotStmt = $pdo->prepare($shotSql);
            $shotStmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
            $shotStmt->bindParam(':scenes_id', $sceneId, PDO::PARAM_STR);
            $shotStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        } else {
            // 如果没有 scene_id，只使用 shot_id
            $shotSql = "SELECT characters FROM shots WHERE shots_id = :shot_id AND user_id = :user_id";
            $shotStmt = $pdo->prepare($shotSql);
            $shotStmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
            $shotStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        }
    }
    $shotStmt->execute();
    $shotData = $shotStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shotData || !$shotData['characters']) {
        echo json_encode(['success' => true, 'total_characters' => 0, 'characters' => []]);
        exit;
    }
    
    // 分割角色，使用最可靠的方法
    $charactersStr = trim($shotData['characters']);
    
    // 初始化角色数组
    $characters = [];
    
    // 方法1：使用多种分隔符分割
    $delimiters = [',', '，', '、', ' '];
    $currentChar = '';
    
    for ($i = 0; $i < mb_strlen($charactersStr); $i++) {
        $char = mb_substr($charactersStr, $i, 1);
        
        if (in_array($char, $delimiters)) {
            // 遇到分隔符，添加当前角色
            $trimmedChar = trim($currentChar);
            if (!empty($trimmedChar)) {
                $characters[] = $trimmedChar;
                $currentChar = '';
            }
        } else {
            // 不是分隔符，继续构建角色名称
            $currentChar .= $char;
        }
    }
    
    // 添加最后一个角色
    $trimmedChar = trim($currentChar);
    if (!empty($trimmedChar)) {
        $characters[] = $trimmedChar;
    }
    
    // 如果没有分割出角色，直接使用原始字符串
    if (empty($characters)) {
        $characters[] = $charactersStr;
    }
    
    // 去重，避免重复角色
    $characters = array_unique($characters);
    $characters = array_values($characters);
    
    // 获取用户的剧组ID
    $crewId = null;
    try {
        $crewSql = "SELECT id FROM crew WHERE admin_user_id = :user_id LIMIT 1";
        $crewStmt = $pdo->prepare($crewSql);
        $crewStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $crewStmt->execute();
        $crewData = $crewStmt->fetch(PDO::FETCH_ASSOC);
        $crewId = $crewData['id'] ?? null;
    } catch (Exception $e) {
        // 剧组查询失败，继续执行，只返回角色名称
    }
    
    // 获取每个角色的三视图
    $characterDetails = [];
    foreach ($characters as $character) {
        // 移除角色名称中的括号和内容（同时支持中英文括号）
        $cleanCharacter = preg_replace('/\s*[\(（].*?[\)）]\s*$/', '', $character);
        $cleanCharacter = trim($cleanCharacter);
        
        try {
            $threeViewImage = '';
            if ($crewId) {
                // 尝试精确匹配角色名称
                $charSql = "SELECT three_view_image FROM characters WHERE name = :name AND crew_id = :crew_id LIMIT 1";
                $charStmt = $pdo->prepare($charSql);
                $charStmt->bindParam(':name', $cleanCharacter, PDO::PARAM_STR);
                $charStmt->bindParam(':crew_id', $crewId, PDO::PARAM_INT);
                $charStmt->execute();
                $charData = $charStmt->fetch(PDO::FETCH_ASSOC);
                
                // 如果精确匹配失败，尝试模糊匹配
                if (!$charData) {
                    $charSql = "SELECT three_view_image FROM characters WHERE name LIKE CONCAT('%', :name, '%') AND crew_id = :crew_id LIMIT 1";
                    $charStmt = $pdo->prepare($charSql);
                    $charStmt->bindParam(':name', $cleanCharacter, PDO::PARAM_STR);
                    $charStmt->bindParam(':crew_id', $crewId, PDO::PARAM_INT);
                    $charStmt->execute();
                    $charData = $charStmt->fetch(PDO::FETCH_ASSOC);
                }
                
                $threeViewImage = $charData['three_view_image'] ?? '';
            }
            
            $characterDetails[] = [
                'name' => $cleanCharacter,
                'three_view_image' => $threeViewImage
            ];
        } catch (Exception $e) {
            // 如果查询失败，只返回角色名称
            $characterDetails[] = [
                'name' => $cleanCharacter,
                'three_view_image' => ''
            ];
        }
    }
    
    // 构建响应
    $response = [
        'success' => true,
        'total_characters' => count($characterDetails),
        'characters' => $characterDetails
    ];
    
    // 输出JSON响应
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '服务器错误: ' . $e->getMessage()]);
}

// 确保脚本结束
exit;
?>
