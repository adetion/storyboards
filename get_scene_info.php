<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 获取用户ID（优先使用GET参数，其次使用会话）
$userId = isset($_GET['user_id']) ? intval($_GET['user_id']) : ($_SESSION['user_id'] ?? null);

// 获取剧组ID（优先使用GET参数，其次使用会话）
$crewId = isset($_GET['crew_id']) ? intval($_GET['crew_id']) : ($_SESSION['crew_id'] ?? null);

// 检查用户ID是否有效
if (!$userId) {
    echo json_encode(['success' => false, 'message' => '用户ID不能为空']);
    exit;
}

// 获取场次ID
$sceneId = isset($_GET['scene_id']) ? $_GET['scene_id'] : '';

// 处理场次ID
// 检查是否是数字格式的场次ID
if (is_numeric($sceneId)) {
    // 如果是数字，转换为字符串并格式化为SC001格式
    $numericSceneId = intval($sceneId);
    if ($numericSceneId <= 0) {
        echo json_encode(['success' => false, 'message' => '无效的场次ID']);
        exit;
    }
    // 格式化为SC001格式
    $sceneId = 'SC' . str_pad($numericSceneId, 3, '0', STR_PAD_LEFT);
} elseif (empty($sceneId)) {
    echo json_encode(['success' => false, 'message' => '无效的场次ID']);
    exit;
}

// 引入配置文件
require_once __DIR__ . '/config.php';

try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 查询场次信息
    $sql = "SELECT scene_name FROM scenes WHERE scene_id = :scene_id AND user_id = :user_id";
    $params = [':scene_id' => $sceneId, ':user_id' => $userId];
    
    // 添加crew_id条件（如果提供）
    if ($crewId) {
        $sql .= " AND crew_id = :crew_id";
        $params[':crew_id'] = $crewId;
    }
    
    $sql .= " LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $scene = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($scene) {
        echo json_encode([
            'success' => true,
            'scene_name' => $scene['scene_name']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => '场次不存在'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '数据库错误: ' . $e->getMessage()
    ]);
}
?>
