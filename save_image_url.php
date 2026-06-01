<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入必要的文件
require_once __DIR__ . '/config.php';

// Get the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data || !isset($data['shotId']) || !isset($data['taskId']) || !isset($data['sceneId'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters: shotId, taskId or sceneId']);
    exit();
}

$shotId = $data['shotId'];
$taskId = $data['taskId'];
$sceneId = $data['sceneId'];

// 检查是保存参考画面还是运镜画面
$isCameraMovement = isset($data['imageUrls']);
$imageField = $isCameraMovement ? 'imageUrls' : 'imageUrl';
$imageValue = $isCameraMovement ? $data['imageUrls'] : $data['imageUrl'];

// 获取grid_type字段的值（如果存在）
$gridType = isset($data['grid_type']) ? $data['grid_type'] : null;

try {
    // 获取数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 构建SQL语句，根据是否有grid_type值来决定是否更新该字段
    if ($gridType !== null) {
        // 更新图片URL和grid_type字段
        $sql = "UPDATE shots SET $imageField = :imageValue, grid_type = :gridType WHERE task_id = :taskId AND scenes_id = :sceneId AND shots_id = :shotId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':imageValue', $imageValue, PDO::PARAM_STR);
        $stmt->bindParam(':gridType', $gridType, PDO::PARAM_INT);
        $stmt->bindParam(':taskId', $taskId, PDO::PARAM_STR);
        $stmt->bindParam(':sceneId', $sceneId, PDO::PARAM_STR);
        $stmt->bindParam(':shotId', $shotId, PDO::PARAM_INT);
    } else {
        // 只更新图片URL字段
        $sql = "UPDATE shots SET $imageField = :imageValue WHERE task_id = :taskId AND scenes_id = :sceneId AND shots_id = :shotId";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':imageValue', $imageValue, PDO::PARAM_STR);
        $stmt->bindParam(':taskId', $taskId, PDO::PARAM_STR);
        $stmt->bindParam(':sceneId', $sceneId, PDO::PARAM_STR);
        $stmt->bindParam(':shotId', $shotId, PDO::PARAM_INT);
    }
    $stmt->execute();
    
    $affectedRows = $stmt->rowCount();
    
    if ($affectedRows > 0) {
        echo json_encode(['success' => true, 'message' => 'Image URL saved to database successfully', 'affected_rows' => $affectedRows]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Shot not found in database', 'taskId' => $taskId, 'shotId' => $shotId]);
    }
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
