<?php
/**
 * 视频生成API接口
 * 用于处理前端的视频生成请求，包括创建任务、查询状态等
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入必要的文件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/VideoGenerator.php';

// 初始化认证
$auth = new Auth();

// 开发模式：允许跳过登录验证（仅用于测试）
$devMode = false; // 设置为false禁用开发模式

if ($devMode) {
    // 开发模式下使用默认用户ID
    $userId = 1;
    // error_log("Video API - 开发模式启用，使用默认用户ID: $userId");
} else {
    // 检查用户是否登录
    $user = $auth->checkLogin();
    if (!$user['success']) {
        echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    $userId = $user['data']['id'];
}

$videoGenerator = VideoGenerator::getInstance();

// 处理JSON请求数据
$postData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawData = file_get_contents('php://input');
    $postData = json_decode($rawData, true) ?: [];
}

// 处理不同的API请求
$action = $_GET['action'] ?? $_POST['action'] ?? $postData['action'] ?? '';

try {
    switch ($action) {
        case 'create_task':
            // 创建视频生成任务
            $shotId = $_POST['shot_id'] ?? $postData['shot_id'] ?? '';
            $sceneId = $_POST['scene_id'] ?? $postData['scene_id'] ?? '';
            $imageUrls = $_POST['image_urls'] ?? $postData['image_urls'] ?? [];
            $prompts = $_POST['prompts'] ?? $postData['prompts'] ?? [];
            $prompt = $_POST['prompt'] ?? $postData['prompt'] ?? '';
            $duration = (int)($_POST['duration'] ?? $postData['duration'] ?? 5);
            
            if (empty($shotId)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少分镜ID'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            if (empty($sceneId)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少场次ID'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            if (empty($imageUrls) || !is_array($imageUrls)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少图片URL数组'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            if (count($imageUrls) < 2) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '至少需要两张图片来生成视频'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 验证提示词
            if (empty($prompt) && empty($prompts)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少提示词'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 检查提示词数量是否与图片数量匹配
            if (!empty($prompts) && count($prompts) !== count($imageUrls) - 1) {
                echo json_encode([
                    'code' => 1,
                    'msg' => "提示词数量（" . count($prompts) . "）与预期数量（" . (count($imageUrls) - 1) . "）不匹配"
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 创建视频任务
            $videoTaskId = $videoGenerator->createVideoTask($userId, $shotId, $sceneId, $imageUrls, $prompt, $duration, $prompts);
            
            echo json_encode([
                'code' => 0,
                'msg' => 'Success',
                'data' => [
                    'task_id' => $videoTaskId
                ]
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'start_task':
            // 开始处理视频生成任务
            $videoTaskId = $_POST['task_id'] ?? $postData['task_id'] ?? '';
            
            if (empty($videoTaskId)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少视频任务ID'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 立即返回成功响应
            echo json_encode([
                'code' => 0,
                'msg' => 'Success',
                'data' => [
                    'message' => '视频生成任务已开始处理'
                ]
            ], JSON_UNESCAPED_UNICODE);
            
            // 刷新输出缓冲区，确保响应立即发送
            ob_flush();
            flush();
            
            // 后台异步处理任务
            ignore_user_abort(true); // 忽略用户中止
            set_time_limit(0); // 设置无限执行时间
            
            // 开始处理任务
            $result = $videoGenerator->startVideoTask($videoTaskId);
            
            // 任务处理完成后记录日志
            if ($result) {
                error_log("Video API - 视频生成任务处理完成: " . $videoTaskId);
            } else {
                error_log("Video API - 视频生成任务处理失败: " . $videoTaskId);
            }
            break;
            
        case 'get_task':
            // 获取视频任务信息
            $videoTaskId = $_GET['task_id'] ?? $_POST['task_id'] ?? $postData['task_id'] ?? '';
            
            if (empty($videoTaskId)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少视频任务ID'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 获取任务信息
            $task = $videoGenerator->getVideoTask($videoTaskId);
            
            if ($task) {
                // 处理状态文本
                $task['status_text'] = $videoGenerator->getStatusText($task['status']);
                // 处理子任务状态文本
                if (isset($task['sub_tasks'])) {
                    foreach ($task['sub_tasks'] as &$subTask) {
                        $subTask['status_text'] = $videoGenerator->getStatusText($subTask['status']);
                    }
                }
                
                echo json_encode([
                    'code' => 0,
                    'msg' => 'Success',
                    'data' => $task
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'code' => 1,
                    'msg' => '视频任务不存在'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        case 'get_user_tasks':
            // 获取用户的视频任务列表
            $status = $_GET['status'] ?? $_POST['status'] ?? $postData['status'] ?? null;
            $limit = (int)($_GET['limit'] ?? $_POST['limit'] ?? $postData['limit'] ?? 20);
            $offset = (int)($_GET['offset'] ?? $_POST['offset'] ?? $postData['offset'] ?? 0);
            $shotId = $_GET['shot_id'] ?? $_POST['shot_id'] ?? $postData['shot_id'] ?? null;
            $shotIds = $_GET['shot_ids'] ?? $_POST['shot_ids'] ?? $postData['shot_ids'] ?? null;
            
            // 获取任务列表
            $tasks = $videoGenerator->getUserVideoTasks($userId, $status, $limit, $offset, $shotId, $shotIds);
            
            // 处理状态文本
            foreach ($tasks as &$task) {
                $task['status_text'] = $videoGenerator->getStatusText($task['status']);
            }
            
            echo json_encode([
                'code' => 0,
                'msg' => 'Success',
                'data' => $tasks
            ], JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cancel_task':
            // 取消视频任务
            $videoTaskId = $_POST['task_id'] ?? $postData['task_id'] ?? '';
            
            if (empty($videoTaskId)) {
                echo json_encode([
                    'code' => 1,
                    'msg' => '缺少视频任务ID'
                ], JSON_UNESCAPED_UNICODE);
                exit(0);
            }
            
            // 取消任务
            $result = $videoGenerator->cancelVideoTask($videoTaskId);
            
            if ($result) {
                echo json_encode([
                    'code' => 0,
                    'msg' => 'Success',
                    'data' => [
                        'message' => '视频任务已取消'
                    ]
                ], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode([
                    'code' => 1,
                    'msg' => '取消视频任务失败'
                ], JSON_UNESCAPED_UNICODE);
            }
            break;
            
        default:
            echo json_encode([
                'code' => 1,
                'msg' => '未知的API操作'
            ], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Exception $e) {
    error_log("Video API Error: " . $e->getMessage());
    echo json_encode([
        'code' => 1,
        'msg' => 'API调用失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
