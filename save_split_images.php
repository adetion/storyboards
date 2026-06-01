<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '用户未登录']);
    exit;
}

// 获取用户ID
$userId = $_SESSION['user_id'];

// 引入配置文件
require_once 'config.php';
require_once 'Database.php';

// 获取请求数据
$requestData = json_decode(file_get_contents('php://input'), true);

if (!$requestData || !isset($requestData['images']) || !isset($requestData['shotId']) || !isset($requestData['sceneId']) || !isset($requestData['taskId'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$images = $requestData['images'];
$shotId = $requestData['shotId'];
$sceneId = $requestData['sceneId'];
$taskId = $requestData['taskId'];
$userIdFromRequest = $requestData['userId'] ?? '';

// 验证用户ID
if (!empty($userIdFromRequest) && $userIdFromRequest != $userId) {
    echo json_encode(['success' => false, 'message' => '用户ID验证失败']);
    exit;
}

// 初始化数据库连接
$db = Database::getInstance();
$pdo = $db->getPdo();

// 验证分镜属于当前用户和任务
$sql = "SELECT shots_id FROM shots WHERE shots_id = :shot_id AND user_id = :user_id AND task_id = :task_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
$stmt->execute();

if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
    echo json_encode(['success' => false, 'message' => '分镜验证失败']);
    exit;
}

// 图片保存目录
$saveDir = __DIR__ . '/uploads/split_images/';
if (!is_dir($saveDir)) {
    mkdir($saveDir, 0755, true);
}

// 保存图片到本地服务器
$localImages = [];
try {
    foreach ($images as $index => $image) {
        $imageUrl = $image['url'] ?? '';
        if (empty($imageUrl)) continue;
        
        // 生成唯一文件名
        $filename = uniqid('split_') . '_' . $index . '.jpg';
        $savePath = $saveDir . $filename;
        
        // 下载图片
        $imageData = file_get_contents($imageUrl);
        if ($imageData) {
            // 保存到本地
            file_put_contents($savePath, $imageData);
            
            // 生成本地URL
            $localUrl = 'uploads/split_images/' . $filename;
            $localImages[] = [
                'url' => $localUrl,
                'index' => $index,
                'taskId' => $taskId,
                'userId' => $userId
            ];
        }
    }
    
    // 如果成功保存了图片，更新数据库
    if (!empty($localImages)) {
        // 将本地图片URL转换为JSON格式
        $videoImageUrl = json_encode($localImages);
        
        // 更新shots表中的video_image_Url字段
        $sql = "UPDATE shots SET video_image_Url = :video_image_url WHERE shots_id = :shot_id AND user_id = :user_id AND task_id = :task_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':video_image_url', $videoImageUrl, PDO::PARAM_STR);
        $stmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $stmt->execute();
        
        // 检查更新是否成功
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true,
                'message' => '图片保存成功',
                'localImages' => $localImages
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => '更新数据库失败'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => '没有成功保存的图片'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => '保存图片时发生错误: ' . $e->getMessage()
    ]);
}
?>
