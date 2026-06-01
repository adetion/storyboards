<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入必要的文件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';

// 初始化认证
$auth = new Auth();

// 检查用户是否登录
$user = $auth->checkLogin();
if (!$user['success']) {
    echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

$userId = $user['data']['id'];

// 获取task_id参数
$taskId = $_GET['task_id'] ?? '';

if (empty($taskId)) {
    echo json_encode(['error' => '缺少task_id参数'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

try {
    // 获取数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 从crew表获取genres字段
    $sql = "SELECT genres FROM crew WHERE admin_user_id = :user_id AND current_task_id = :task_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $genres = $result['genres'] ?? '';
        
        // 解析genres（可能是JSON格式或逗号分隔）
        if (!empty($genres)) {
            // 尝试解析为JSON
            $decoded = json_decode($genres, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $genresArray = $decoded;
            } else {
                // 如果不是JSON，按逗号分隔
                $genresArray = array_map('trim', explode(',', $genres));
            }
        } else {
            $genresArray = [];
        }
        
        echo json_encode([
            'success' => true,
            'genres' => $genresArray,
            'genres_text' => implode(', ', $genresArray)
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode([
            'success' => true,
            'genres' => [],
            'genres_text' => ''
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    // 记录错误
    error_log("Get Genres API Error: " . $e->getMessage());
    // 返回错误响应
    echo json_encode(['error' => '获取题材失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(0);
}
?>