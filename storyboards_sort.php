<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => '未登录']);
    exit;
}

// 引入配置文件
require_once __DIR__ . '/config.php';

// 获取当前用户ID
$userId = $_SESSION['user_id'];

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 获取用户的剧组ID
    $crewSql = "SELECT id FROM crew WHERE admin_user_id = :user_id LIMIT 1";
    $crewStmt = $pdo->prepare($crewSql);
    $crewStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $crewStmt->execute();
    $crewData = $crewStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$crewData) {
        echo json_encode(['success' => false, 'error' => '未找到用户的剧组']);
        exit;
    }
    
    $crewId = $crewData['id'];
    
    // 获取当前剧组下的所有任务
    $taskSql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type IN ('storyboard_management', 'script_to_storyboard') AND task_id IS NOT NULL";
    $taskStmt = $pdo->prepare($taskSql);
    $taskStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $taskStmt->execute();
    $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("故事板排序: 找到 " . count($tasks) . " 个任务");
    
    // 处理每个任务的分镜排序
    foreach ($tasks as $task) {
        $taskId = $task['task_id'];
        
        error_log("故事板排序: 处理任务 " . $taskId);
        
        // 获取当前任务下的所有分镜，按照scenes_id和shots_id排序
        $shotSql = "SELECT id, scenes_id, shots_id FROM shots WHERE task_id = :task_id ORDER BY scenes_id ASC, shots_id ASC";
        $shotStmt = $pdo->prepare($shotSql);
        $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $shotStmt->execute();
        $shots = $shotStmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("故事板排序: 任务 " . $taskId . " 找到 " . count($shots) . " 个分镜");
        
        // 重新设定sort_order值
        $sortOrder = 1;
        foreach ($shots as $shot) {
            $updateSql = "UPDATE shots SET sort_order = :sort_order WHERE id = :shot_id AND task_id = :task_id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->bindParam(':sort_order', $sortOrder, PDO::PARAM_INT);
            $updateStmt->bindParam(':shot_id', $shot['id'], PDO::PARAM_INT);
            $updateStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
            $result = $updateStmt->execute();
            error_log("故事板排序: 更新分镜 " . $shot['id'] . " 排序为 " . $sortOrder . " 结果: " . ($result ? '成功' : '失败'));
            $sortOrder++;
        }
    }
    
    echo json_encode(['success' => true, 'message' => '分镜排序已更新']);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
