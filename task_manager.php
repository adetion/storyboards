<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入配置文件
require_once __DIR__ . '/config.php';

// 任务状态常量
const TASK_STATUS_PENDING = 'pending';
const TASK_STATUS_PROCESSING = 'processing';
const TASK_STATUS_COMPLETED = 'completed';
const TASK_STATUS_FAILED = 'failed';

// 获取请求方法
$method = $_SERVER['REQUEST_METHOD'];

try {
    // 根据请求方法处理不同的操作
    if ($method === 'POST') {
        // 处理创建任务的请求
        handleCreateTask();
    } elseif ($method === 'GET') {
        // 检查是否有shotId参数
        if (isset($_GET['shotId'])) {
            // 处理获取指定分镜的进行中任务的请求
            handleGetOngoingTasks();
        } else {
            // 处理获取任务状态的请求
            handleGetTaskStatus();
        }
    } else {
        echo json_encode([
            'code' => 405,
            'msg' => 'Method Not Allowed',
            'timestamp' => time()
        ]);
        exit();
    }
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '服务器错误: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}

// 处理创建任务的请求
function handleCreateTask() {
    // 获取请求数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // 检查必要的参数
    if (!isset($data['shotId']) || !isset($data['imageUrls'])) {
        echo json_encode([
            'code' => 400,
            'msg' => '缺少必要的参数',
            'timestamp' => time()
        ]);
        exit();
    }
    
    // 创建任务ID
    $taskId = 'task_' . uniqid();
    
    // 获取当前登录用户ID
    $userId = getCurrentUserId();
    
    // 构建任务数据
    $taskData = [
        'taskId' => $taskId,
        'userId' => $userId,
        'shotId' => $data['shotId'],
        'imageUrls' => $data['imageUrls'],
        'prompt' => isset($data['prompt']) ? $data['prompt'] : '',
        'duration' => isset($data['duration']) ? $data['duration'] : 8,
        'status' => TASK_STATUS_PENDING,
        'progress' => 0,
        'currentIndex' => 0, // 当前处理到的图片索引，用于断点续传
        'videoUrls' => [], // 已生成的视频URLs，用于断点续传
        'createdAt' => time(),
        'updatedAt' => time(),
        'result' => null
    ];
    
    // 保存任务数据到文件
    saveTask($taskId, $taskData);
    
    // 异步执行任务
    executeTaskAsync($taskId);
    
    // 返回任务ID
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => [
            'taskId' => $taskId
        ]
    ]);
}

// 处理获取任务状态的请求
function handleGetTaskStatus() {
    // 获取任务ID
    $taskId = isset($_GET['taskId']) ? $_GET['taskId'] : null;
    if (!$taskId) {
        echo json_encode([
            'code' => 400,
            'msg' => '缺少taskId参数',
            'timestamp' => time()
        ]);
        exit();
    }
    
    // 读取任务数据
    $taskData = loadTask($taskId);
    if (!$taskData) {
        echo json_encode([
            'code' => 404,
            'msg' => '任务不存在',
            'timestamp' => time()
        ]);
        exit();
    }
    
    // 返回任务状态
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => $taskData
    ]);
}

// 获取当前登录用户ID
function getCurrentUserId() {
    // 检查是否是后台任务处理（命令行模式）
    $isCliMode = php_sapi_name() === 'cli';
    
    // 只有在非命令行模式下才尝试启动会话
    if (!$isCliMode && session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!$isCliMode && isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }
    
    return null;
}

// 获取用户的任务目录
function getUserTasksDir($userId = null) {
    // 如果没有提供用户ID，获取当前登录用户ID
    if ($userId === null) {
        $userId = getCurrentUserId();
    }
    
    // 确保outputs/tasks目录存在
    $baseTasksDir = __DIR__ . '/outputs/tasks';
    if (!is_dir($baseTasksDir)) {
        mkdir($baseTasksDir, 0755, true);
    }
    
    // 为每个用户创建单独的任务目录
    $userTasksDir = $baseTasksDir . '/' . ($userId ? $userId : 'anonymous');
    if (!is_dir($userTasksDir)) {
        mkdir($userTasksDir, 0755, true);
    }
    
    return $userTasksDir;
}

// 保存任务数据到文件
function saveTask($taskId, $taskData) {
    // 获取用户的任务目录
    $tasksDir = getUserTasksDir();
    
    // 保存任务数据
    $taskFile = $tasksDir . '/' . $taskId . '.json';
    file_put_contents($taskFile, json_encode($taskData, JSON_PRETTY_PRINT));
}

// 从文件加载任务数据
function loadTask($taskId) {
    // 只从当前用户的任务目录加载，确保用户只能访问自己的任务
    $tasksDir = getUserTasksDir();
    $taskFile = $tasksDir . '/' . $taskId . '.json';
    
    if (file_exists($taskFile)) {
        $taskData = json_decode(file_get_contents($taskFile), true);
        return $taskData;
    }
    
    return null;
}

// 异步执行任务
function executeTaskAsync($taskId) {
    // 获取当前用户ID
    $userId = getCurrentUserId();
    
    // 这里使用exec函数在后台执行任务
    // 注意：在实际生产环境中，可能需要使用更可靠的任务队列系统
    $phpPath = PHP_BINARY;
    $scriptPath = __DIR__ . '/execute_task.php';
    $command = "{$phpPath} {$scriptPath} {$taskId} {$userId} > /dev/null 2>&1 &";
    exec($command);
}

// 处理获取指定分镜的进行中任务的请求
function handleGetOngoingTasks() {
    // 获取shotId参数
    $shotId = isset($_GET['shotId']) ? $_GET['shotId'] : null;
    if (!$shotId) {
        echo json_encode([
            'code' => 400,
            'msg' => '缺少shotId参数',
            'timestamp' => time()
        ]);
        exit();
    }
    
    // 获取用户的任务目录
    $tasksDir = getUserTasksDir();
    if (!is_dir($tasksDir)) {
        echo json_encode([
            'code' => 0,
            'msg' => 'Success',
            'timestamp' => time(),
            'data' => null
        ]);
        exit();
    }
    
    $taskFiles = glob($tasksDir . '/*.json');
    
    // 查找指定分镜的进行中任务
    foreach ($taskFiles as $taskFile) {
        $taskData = json_decode(file_get_contents($taskFile), true);
        
        // 检查任务是否属于指定的分镜，并且状态为pending或processing
        if ($taskData && strval($taskData['shotId']) === strval($shotId) && 
            ($taskData['status'] == TASK_STATUS_PENDING || $taskData['status'] == TASK_STATUS_PROCESSING)) {
            
            // 返回找到的任务
            echo json_encode([
                'code' => 0,
                'msg' => 'Success',
                'timestamp' => time(),
                'data' => $taskData
            ]);
            exit();
        }
    }
    
    // 没有找到进行中的任务
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => null
    ]);
}
?>
