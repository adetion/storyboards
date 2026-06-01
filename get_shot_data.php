<?php

/**
 * 获取分镜数据接口
 * 功能：根据shotId获取分镜的video_image_Url字段值
 */

// 启动会话 - 必须在任何输出之前调用
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 引入配置和数据库类
require_once 'config.php';

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode([
        'code' => 401,
        'msg' => '用户未登录',
        'timestamp' => time()
    ]);
    exit();
}

// 获取当前登录用户ID
$currentUserId = $_SESSION['user_id'];

// 获取请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 首先尝试从POST请求体中获取shotId
$shotId = isset($data['shotId']) ? $data['shotId'] : null;

// 如果POST请求体中没有shotId，尝试从GET参数中获取
if ($shotId === null) {
    $shotId = isset($_GET['shotId']) ? $_GET['shotId'] : null;
    // 保持向后兼容性，也支持id参数
    if ($shotId === null) {
        $shotId = isset($_GET['id']) ? $_GET['id'] : null;
    }
}

// 检查是否有shotId参数
if (empty($shotId)) {
    echo json_encode([
        'code' => 400,
        'msg' => '缺少shotId参数',
        'timestamp' => time()
    ]);
    exit();
}

$shotId = trim($shotId);

// 获取其他参数，优先从POST请求体中获取，其次从GET参数中获取
// 支持驼峰命名和下划线命名
$taskId = isset($data['taskId']) ? $data['taskId'] : (isset($data['task_id']) ? $data['task_id'] : (isset($_GET['taskId']) ? $_GET['taskId'] : (isset($_GET['task_id']) ? $_GET['task_id'] : null)));
$sceneId = isset($data['sceneId']) ? $data['sceneId'] : (isset($data['scenes_id']) ? $data['scenes_id'] : (isset($_GET['sceneId']) ? $_GET['sceneId'] : (isset($_GET['scenes_id']) ? $_GET['scenes_id'] : null)));

try {
    // 创建数据库实例
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 构建查询语句，强制添加用户ID条件
    $sql = "SELECT imageUrls, video_image_Url, videoCutUrl, grid_type, script, CutPrompt FROM shots WHERE shots_id = ? AND user_id = ?";
    $params = [$shotId, $currentUserId];
    $paramTypes = [PDO::PARAM_STR, PDO::PARAM_INT];
    
    // 添加额外的条件参数
    if ($taskId !== null) {
        $sql .= " AND task_id = ?";
        $params[] = $taskId;
        $paramTypes[] = PDO::PARAM_STR;
    }
    
    if ($sceneId !== null) {
        $sql .= " AND scenes_id = ?";
        $params[] = $sceneId;
        $paramTypes[] = PDO::PARAM_INT;
    }
    
    $sql .= " LIMIT 1";
    
    // 执行查询
    $stmt = $pdo->prepare($sql);
    
    // 绑定参数
    for ($i = 0; $i < count($params); $i++) {
        $stmt->bindParam($i + 1, $params[$i], $paramTypes[$i]);
    }
    
    $stmt->execute();
    
    $shotData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$shotData) {
        echo json_encode([
            'code' => 404,
            'msg' => '分镜不存在',
            'timestamp' => time()
        ]);
        exit();
    }
    
    // 返回分镜数据
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => $shotData
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '服务器错误: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
    exit();
}
?>
