<?php
/**
 * 统一任务API接口
 * 提供RESTful API，支持任务的创建、查询、更新等操作
 */

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 允许跨域请求
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入任务管理器
require_once __DIR__ . '/TaskManager.php';

// 引入认证类
require_once __DIR__ . '/Auth.php';

// 初始化认证
$auth = new Auth();

// 获取请求方法和路径
$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

// 解析路径参数
$pathParts = explode('/', trim($path, '/'));
$resource = $pathParts[0] ?? '';
$id = $pathParts[1] ?? null;

// 获取请求数据
$input = json_decode(file_get_contents('php://input'), true) ?? [];

// 初始化任务管理器
$taskManager = TaskManager::getInstance();

// 响应结果
$response = [
    'success' => false,
    'message' => '',
    'data' => null
];

// 检查用户是否登录
$user = $auth->checkLogin();
if (!$user['success']) {
    $response['message'] = '用户未登录';
    sendResponse($response, 401);
    exit;
}

$userId = $user['data']['id'];

// 路由处理
try {
    switch ($method) {
        case 'GET':
            handleGetRequest($resource, $id, $userId);
            break;
        case 'POST':
            handlePostRequest($resource, $userId, $input);
            break;
        case 'PUT':
            handlePutRequest($resource, $id, $userId, $input);
            break;
        case 'DELETE':
            handleDeleteRequest($resource, $id, $userId);
            break;
        default:
            $response['message'] = '不支持的请求方法';
            sendResponse($response, 405);
    }
} catch (Exception $e) {
    $response['message'] = '服务器错误: ' . $e->getMessage();
    sendResponse($response, 500);
}

/**
 * 处理GET请求
 */
function handleGetRequest($resource, $id, $userId) {
    global $taskManager, $response;
    
    switch ($resource) {
        case 'tasks':
            if ($id) {
                // 获取单个任务
                $task = $taskManager->getTask($id);
                if ($task && $task['user_id'] == $userId) {
                    $response['success'] = true;
                    $response['data'] = $task;
                    $response['message'] = '获取任务成功';
                } else {
                    $response['message'] = '任务不存在或无权限访问';
                    sendResponse($response, 404);
                }
            } else {
                // 获取任务列表
                $taskType = $_GET['type'] ?? null;
                $status = $_GET['status'] ?? null;
                $limit = (int) ($_GET['limit'] ?? 20);
                $offset = (int) ($_GET['offset'] ?? 0);
                
                $tasks = $taskManager->getUserTasks($userId, $taskType, $status, $limit, $offset);
                $response['success'] = true;
                $response['data'] = $tasks;
                $response['message'] = '获取任务列表成功';
            }
            break;
        
        case 'task-by-external-id':
            // 根据外部任务ID获取任务
            $externalId = $_GET['external_id'] ?? null;
            if ($externalId) {
                $task = $taskManager->getTaskByExternalId($externalId);
                if ($task && $task['user_id'] == $userId) {
                    $response['success'] = true;
                    $response['data'] = $task;
                    $response['message'] = '获取任务成功';
                } else {
                    $response['message'] = '任务不存在或无权限访问';
                    sendResponse($response, 404);
                }
            } else {
                $response['message'] = '缺少外部任务ID';
                sendResponse($response, 400);
            }
            break;
        
        case 'task-types':
            // 获取任务类型列表
            $taskTypes = [
                ['value' => TaskManager::TYPE_NOVEL_TO_SCRIPT, 'label' => '小说转剧本'],
                ['value' => TaskManager::TYPE_SCRIPT_TO_STORYBOARD, 'label' => '剧本转分镜'],
                ['value' => TaskManager::TYPE_STORYBOARD_MANAGEMENT, 'label' => '分镜管理'],
                ['value' => TaskManager::TYPE_GUSHIBAN, 'label' => '故事板'],
                ['value' => TaskManager::TYPE_SCHEDULE, 'label' => '拍摄计划'],
                ['value' => TaskManager::TYPE_ANNOUNCEMENT, 'label' => '拍摄通告']
            ];
            $response['success'] = true;
            $response['data'] = $taskTypes;
            $response['message'] = '获取任务类型列表成功';
            break;
        
        case 'task-statuses':
            // 获取任务状态列表
            $taskStatuses = [
                ['value' => TaskManager::STATUS_PENDING, 'label' => '待处理'],
                ['value' => TaskManager::STATUS_PROCESSING, 'label' => '处理中'],
                ['value' => TaskManager::STATUS_COMPLETED, 'label' => '已完成'],
                ['value' => TaskManager::STATUS_FAILED, 'label' => '失败'],
                ['value' => TaskManager::STATUS_CANCELLED, 'label' => '已取消']
            ];
            $response['success'] = true;
            $response['data'] = $taskStatuses;
            $response['message'] = '获取任务状态列表成功';
            break;
            
        case 'tasks/stats':
            // 获取任务统计数据
            $stats = getTaskStats($userId);
            $response['success'] = true;
            $response['data'] = $stats;
            $response['message'] = '获取任务统计数据成功';
            break;
            
        case 'tasks/analysis':
            // 获取任务分析数据
            $timeRange = $_GET['time_range'] ?? '7d'; // 7d, 30d, 90d, all
            $analysis = getTaskAnalysis($userId, $timeRange);
            $response['success'] = true;
            $response['data'] = $analysis;
            $response['message'] = '获取任务分析数据成功';
            break;
        
        default:
            $response['message'] = '无效的资源路径';
            sendResponse($response, 404);
    }
    
    sendResponse($response);
}

/**
 * 获取任务统计数据
 * @param int $userId 用户ID
 * @return array 统计数据
 */
function getTaskStats($userId) {
    global $taskManager;
    
    try {
        $pdo = $taskManager->getPdo();
        
        // 统计总任务数
        $totalSql = "SELECT COUNT(*) as total FROM tasks WHERE user_id = ?";
        $stmt = $pdo->prepare($totalSql);
        $stmt->execute([$userId]);
        $total = $stmt->fetchColumn();
        
        // 按状态统计任务数
        $statusSql = "SELECT status, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY status";
        $stmt = $pdo->prepare($statusSql);
        $stmt->execute([$userId]);
        $statusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 按类型统计任务数
        $typeSql = "SELECT task_type, COUNT(*) as count FROM tasks WHERE user_id = ? GROUP BY task_type";
        $stmt = $pdo->prepare($typeSql);
        $stmt->execute([$userId]);
        $typeStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // 统计最近7天的任务数
        $recentSql = "SELECT DATE(created_at) as date, COUNT(*) as count FROM tasks 
                     WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
                     GROUP BY DATE(created_at) ORDER BY date";
        $stmt = $pdo->prepare($recentSql);
        $stmt->execute([$userId]);
        $recentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'total' => $total,
            'pending' => $statusStats[TaskManager::STATUS_PENDING] ?? 0,
            'processing' => $statusStats[TaskManager::STATUS_PROCESSING] ?? 0,
            'completed' => $statusStats[TaskManager::STATUS_COMPLETED] ?? 0,
            'failed' => $statusStats[TaskManager::STATUS_FAILED] ?? 0,
            'cancelled' => $statusStats[TaskManager::STATUS_CANCELLED] ?? 0,
            'by_type' => $typeStats,
            'recent_7days' => $recentStats
        ];
    } catch (Exception $e) {
        error_log("获取任务统计数据失败: " . $e->getMessage());
        return [
            'total' => 0,
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
            'by_type' => [],
            'recent_7days' => []
        ];
    }
}

/**
 * 获取任务分析数据
 * @param int $userId 用户ID
 * @param string $timeRange 时间范围
 * @return array 分析数据
 */
function getTaskAnalysis($userId, $timeRange) {
    global $taskManager;
    
    try {
        $pdo = $taskManager->getPdo();
        
        // 根据时间范围确定WHERE条件
        $timeWhere = "";
        switch ($timeRange) {
            case '7d':
                $timeWhere = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case '30d':
                $timeWhere = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
            case '90d':
                $timeWhere = "AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)";
                break;
            case 'all':
                $timeWhere = "";
                break;
            default:
                $timeWhere = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        }
        
        // 平均完成时间
        $avgTimeSql = "SELECT AVG(TIMESTAMPDIFF(SECOND, created_at, completed_at)) as avg_seconds 
                     FROM tasks WHERE user_id = ? AND status = ? $timeWhere";
        $stmt = $pdo->prepare($avgTimeSql);
        $stmt->execute([$userId, TaskManager::STATUS_COMPLETED]);
        $avgSeconds = $stmt->fetchColumn();
        
        // 成功率
        $successRateSql = "SELECT COUNT(*) as total, 
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed 
                     FROM tasks WHERE user_id = ? $timeWhere";
        $stmt = $pdo->prepare($successRateSql);
        $stmt->execute([TaskManager::STATUS_COMPLETED, $userId]);
        $successData = $stmt->fetch(PDO::FETCH_ASSOC);
        $successRate = $successData['total'] > 0 ? ($successData['completed'] / $successData['total']) * 100 : 0;
        
        // 任务类型分布
        $typeDistributionSql = "SELECT task_type, COUNT(*) as count, 
                     SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as completed 
                     FROM tasks WHERE user_id = ? $timeWhere GROUP BY task_type";
        $stmt = $pdo->prepare($typeDistributionSql);
        $stmt->execute([TaskManager::STATUS_COMPLETED, $userId]);
        $typeDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'avg_completion_time' => $avgSeconds ? round($avgSeconds / 60, 2) . '分钟' : 'N/A',
            'success_rate' => round($successRate, 2) . '%',
            'by_type' => $typeDistribution
        ];
    } catch (Exception $e) {
        error_log("获取任务分析数据失败: " . $e->getMessage());
        return [
            'avg_completion_time' => 'N/A',
            'success_rate' => 'N/A',
            'by_type' => []
        ];
    }
}

/**
 * 处理POST请求
 */
function handlePostRequest($resource, $userId, $input) {
    global $taskManager, $response;
    
    switch ($resource) {
        case 'tasks':
            // 创建新任务
            $taskType = $input['task_type'] ?? null;
            $title = $input['title'] ?? null;
            $inputData = $input['input_data'] ?? [];
            $taskDetails = $input['task_details'] ?? [];
            
            if (!$taskType || !$title) {
                $response['message'] = '缺少必要参数';
                sendResponse($response, 400);
                return;
            }
            
            $taskId = $taskManager->createTask($userId, $taskType, $title, $inputData, $taskDetails);
            $response['success'] = true;
            $response['data'] = ['task_id' => $taskId];
            $response['message'] = '任务创建成功';
            break;
        
        case 'tasks':
            if (isset($input['action'])) {
                // 处理任务操作
                $action = $input['action'];
                $taskId = $input['task_id'] ?? null;
                
                if (!$taskId) {
                    $response['message'] = '缺少任务ID';
                    sendResponse($response, 400);
                    return;
                }
                
                switch ($action) {
                    case 'update-progress':
                        // 更新任务进度
                        $progress = $input['progress'] ?? null;
                        $message = $input['message'] ?? null;
                        if ($progress === null) {
                            $response['message'] = '缺少进度参数';
                            sendResponse($response, 400);
                            return;
                        }
                        
                        $result = $taskManager->updateTaskProgress($taskId, $progress, $message);
                        if ($result) {
                            $response['success'] = true;
                            $response['message'] = '任务进度更新成功';
                        } else {
                            $response['message'] = '任务进度更新失败';
                            sendResponse($response, 500);
                        }
                        break;
                    
                    case 'add-log':
                        // 添加任务日志
                        $status = $input['status'] ?? null;
                        $message = $input['message'] ?? null;
                        if ($status === null || !$message) {
                            $response['message'] = '缺少必要参数';
                            sendResponse($response, 400);
                            return;
                        }
                        
                        $result = $taskManager->addTaskLog($taskId, $status, $message);
                        if ($result) {
                            $response['success'] = true;
                            $response['message'] = '任务日志添加成功';
                        } else {
                            $response['message'] = '任务日志添加失败';
                            sendResponse($response, 500);
                        }
                        break;
                    
                    default:
                        $response['message'] = '无效的操作类型';
                        sendResponse($response, 400);
                }
            }
            break;
        
        default:
            $response['message'] = '无效的资源路径';
            sendResponse($response, 404);
    }
    
    sendResponse($response);
}

/**
 * 处理PUT请求
 */
function handlePutRequest($resource, $id, $userId, $input) {
    global $taskManager, $response;
    
    switch ($resource) {
        case 'tasks':
            if ($id) {
                // 更新任务状态
                $status = $input['status'] ?? null;
                $progress = $input['progress'] ?? null;
                $outputData = $input['output_data'] ?? null;
                $errorMessage = $input['error_message'] ?? null;
                
                if ($status === null) {
                    $response['message'] = '缺少状态参数';
                    sendResponse($response, 400);
                    return;
                }
                
                // 检查任务是否存在且属于当前用户
                $task = $taskManager->getTask($id);
                if (!$task || $task['user_id'] != $userId) {
                    $response['message'] = '任务不存在或无权限访问';
                    sendResponse($response, 404);
                    return;
                }
                
                $result = $taskManager->updateTaskStatus($id, $status, $progress, $outputData, $errorMessage);
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = '任务状态更新成功';
                } else {
                    $response['message'] = '任务状态更新失败';
                    sendResponse($response, 500);
                }
            } else {
                $response['message'] = '缺少任务ID';
                sendResponse($response, 400);
            }
            break;
        
        default:
            $response['message'] = '无效的资源路径';
            sendResponse($response, 404);
    }
    
    sendResponse($response);
}

/**
 * 处理DELETE请求
 */
function handleDeleteRequest($resource, $id, $userId) {
    global $taskManager, $response;
    
    switch ($resource) {
        case 'tasks':
            if ($id) {
                // 检查任务是否存在且属于当前用户
                $task = $taskManager->getTask($id);
                if (!$task || $task['user_id'] != $userId) {
                    $response['message'] = '任务不存在或无权限访问';
                    sendResponse($response, 404);
                    return;
                }
                
                $result = $taskManager->deleteTask($id);
                if ($result) {
                    $response['success'] = true;
                    $response['message'] = '任务删除成功';
                } else {
                    $response['message'] = '任务删除失败';
                    sendResponse($response, 500);
                }
            } else {
                $response['message'] = '缺少任务ID';
                sendResponse($response, 400);
            }
            break;
        
        default:
            $response['message'] = '无效的资源路径';
            sendResponse($response, 404);
    }
    
    sendResponse($response);
}

/**
 * 发送响应
 * @param array $response 响应数据
 * @param int $statusCode HTTP状态码
 */
function sendResponse($response, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

// 发送响应
sendResponse($response);
?>