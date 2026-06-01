<?php

/**
 * 修复版图生视频系统（支持图片上传和任务历史记录）
 * 文件名：img2video.php
 */

// 配置


// 启动会话
session_start();

// 引入配置和Auth类
require_once 'config.php';
require_once 'Auth.php';

define('API_BASE_URL', Config::VIDEO_GENERATION_API_URL());
define('AUTH_TOKEN', 'Bearer ' . Config::VIDEO_GENERATION_API_KEY());
const OUTPUT_DIR = Config::OUTPUT_DIR;


// 创建输出目录（如果不存在）
if (!file_exists(OUTPUT_DIR)) {
    mkdir(OUTPUT_DIR, 0777, true);
}

// JSON响应函数
function jsonResponse($code = 0, $data = null, $msg = '')
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'code' => $code,
        'data' => $data,
        'msg' => $msg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// API请求函数
function apiRequest($url, $method = 'POST', $data = null, $isFileUpload = false)
{
    $ch = curl_init();

    $headers = [
        'Content-Type: ' . ($isFileUpload ? 'multipart/form-data' : 'application/json'),
        'Authorization: ' . AUTH_TOKEN,
        'User-Agent: Mozilla/5.0'
    ];

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isFileUpload ? $data : json_encode($data));
        }
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['code' => 1, 'msg' => "请求失败: " . $error];
    }

    if ($httpCode !== 200) {
        return ['code' => 1, 'msg' => "HTTP错误: {$httpCode}"];
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['code' => 1, 'msg' => "响应解析失败: " . json_last_error_msg()];
    }

    return $result;
}

// ===================== 数据库任务记录功能 =====================

// 保存任务记录到数据库
function saveTaskToDb($taskId, $userId, $taskData)
{
    try {
        $db = Database::getInstance();

        // 准备任务数据
        $title = mb_strimwidth($taskData['prompt'], 0, 100, '...');
        $status = 0; // 0=处理中，1=成功，2=失败

        // 转换input_data为JSON
        $inputData = json_encode([
            'prompt' => $taskData['prompt'] ?? '',
            'image_path' => $taskData['image_path'] ?? '',
            'image_url' => $taskData['image_url'] ?? '',
            'required_points' => $taskData['required_points'] ?? Config::VIDEO_GENERATION_COST
        ], JSON_UNESCAPED_UNICODE);

        // 检查任务是否已存在
        $existingTask = $db->queryOne(
            "SELECT id FROM tasks WHERE task_id = ? AND user_id = ?",
            [$taskId, $userId]
        );

        if ($existingTask) {
            // 更新现有任务
            $db->execute(
                "UPDATE tasks SET 
                 title = ?,
                 status = ?,
                 progress = ?,
                 input_data = ?,
                 updated_at = CURRENT_TIMESTAMP
                 WHERE task_id = ? AND user_id = ?",
                [
                    $title,
                    $status,
                    0,
                    $inputData,
                    $taskId,
                    $userId
                ]
            );
            return $existingTask['id'];
        } else {
            // 插入新任务
            $taskDbId = $db->insert(
                "INSERT INTO tasks (user_id, task_type, title, status, progress, input_data, task_id, current_status, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)",
                [
                    $userId,
                    'img2video',
                    $title,
                    $status,
                    0,
                    $inputData,
                    $taskId,
                    0
                ]
            );
            return $taskDbId;
        }
    } catch (Exception $e) {
        error_log("保存任务到数据库失败: " . $e->getMessage());
        return false;
    }
}

// 更新任务状态
function updateTaskStatusInDb($taskId, $userId, $status, $outputData = null, $progress = null)
{
    try {
        $db = Database::getInstance();

        $updates = [];
        $params = [];

        // 状态映射：0=处理中，1=成功，2=失败
        $statusMap = [
            'processing' => 0,
            'success' => 1,
            'error' => 2
        ];

        if (isset($statusMap[$status])) {
            $updates[] = "status = ?";
            $params[] = $statusMap[$status];
        }

        if ($progress !== null) {
            $updates[] = "progress = ?";
            $params[] = $progress;
        }

        if ($outputData !== null) {
            $updates[] = "output_data = ?";
            $params[] = json_encode($outputData, JSON_UNESCAPED_UNICODE);
        }

        if ($status === 'success') {
            $updates[] = "completed_at = CURRENT_TIMESTAMP";
        }

        if (!empty($updates)) {
            $params[] = $taskId;
            $params[] = $userId;

            $sql = "UPDATE tasks SET " . implode(", ", $updates) . ", updated_at = CURRENT_TIMESTAMP WHERE task_id = ? AND user_id = ?";
            $affectedRows = $db->execute($sql, $params);
            return $affectedRows > 0;
        }

        return false;
    } catch (Exception $e) {
        error_log("更新任务状态失败: " . $e->getMessage());
        return false;
    }
}

// 获取用户的任务历史
function getUserTasksFromDb($userId, $limit = 10, $page = 1)
{
    try {
        $db = Database::getInstance();

        $offset = ($page - 1) * $limit;

        // 获取任务列表
        $tasks = $db->query(
            "SELECT id, task_id, task_type, title, status, progress, 
                    input_data, output_data, created_at, completed_at
             FROM tasks 
             WHERE user_id = ? and task_type = ?
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, 'img2video', $limit, $offset]
        );

        // 获取总数量
        $totalResult = $db->queryOne(
            "SELECT COUNT(*) as total FROM tasks WHERE user_id = ? and task_type = ?",
            [$userId, 'img2video']
        );
        $total = $totalResult['total'] ?? 0;

        // 处理任务数据
        $processedTasks = [];
        foreach ($tasks as $task) {
            // 解析输入数据
            $inputData = json_decode($task['input_data'] ?? '{}', true) ?: [];

            // 解析输出数据
            $outputData = json_decode($task['output_data'] ?? '{}', true) ?: [];

            // 状态文本映射
            $statusText = [
                0 => '处理中',
                1 => '成功',
                2 => '失败'
            ];

            $processedTasks[] = [
                'id' => $task['id'],
                'task_id' => $task['task_id'],
                'task_type' => $task['task_type'],
                'title' => $task['title'],
                'status' => $task['status'],
                'status_text' => $statusText[$task['status']] ?? '未知',
                'progress' => $task['progress'],
                'prompt' => $inputData['prompt'] ?? '',
                'image_url' => $inputData['image_url'] ?? '',
                'video_url' => $outputData['video_url'] ?? '',
                'created_at' => $task['created_at'],
                'completed_at' => $task['completed_at']
            ];
        }

        return [
            'tasks' => $processedTasks,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'has_more' => ($offset + count($tasks)) < $total
        ];
    } catch (Exception $e) {
        error_log("获取用户任务失败: " . $e->getMessage());
        return ['tasks' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'has_more' => false];
    }
}

// 获取单个任务详情
function getTaskFromDb($taskId, $userId = null)
{
    try {
        $db = Database::getInstance();

        $sql = "SELECT id, user_id, task_id, task_type, title, status, progress, 
                       input_data, output_data, created_at, updated_at, completed_at,
                       task_id as api_task_id, current_status
                FROM tasks 
                WHERE task_id = ?";
        $params = [$taskId];

        if ($userId !== null) {
            $sql .= " AND user_id = ?";
            $params[] = $userId;
        }

        $task = $db->queryOne($sql, $params);

        if (!$task) {
            return null;
        }

        // 解析数据
        $inputData = json_decode($task['input_data'] ?? '{}', true) ?: [];
        $outputData = json_decode($task['output_data'] ?? '{}', true) ?: [];

        // 状态映射
        $statusMap = [
            0 => 'processing',
            1 => 'success',
            2 => 'error'
        ];

        return [
            'id' => $task['id'],
            'user_id' => $task['user_id'],
            'task_id' => $task['task_id'],
            'api_task_id' => $task['api_task_id'],
            'task_type' => $task['task_type'],
            'title' => $task['title'],
            'status' => $statusMap[$task['status']] ?? 'unknown',
            'status_code' => $task['status'],
            'progress' => $task['progress'],
            'current_status' => $task['current_status'],
            'prompt' => $inputData['prompt'] ?? '',
            'image_path' => $inputData['image_path'] ?? '',
            'image_url' => $inputData['image_url'] ?? '',
            'video_url' => $outputData['video_url'] ?? '',
            'video_id' => $outputData['video_id'] ?? '',
            'created_at' => $task['created_at'],
            'updated_at' => $task['updated_at'],
            'completed_at' => $task['completed_at'],
            'input_data' => $inputData,
            'output_data' => $outputData
        ];
    } catch (Exception $e) {
        error_log("获取任务详情失败: " . $e->getMessage());
        return null;
    }
}

// ===================== 原有功能 =====================

// 上传图片到本地服务器
function uploadImageToLocal()
{
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['code' => 1, 'msg' => '没有上传文件或上传失败'];
    }

    // 检查文件类型
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = mime_content_type($_FILES['image']['tmp_name']);

    if (!in_array($fileType, $allowedTypes)) {
        return ['code' => 1, 'msg' => '只允许上传JPEG、PNG、GIF、WEBP格式的图片'];
    }

    // 检查文件大小（最大5MB）
    $maxSize = 5 * 1024 * 1024; // 5MB
    if ($_FILES['image']['size'] > $maxSize) {
        return ['code' => 1, 'msg' => '图片大小不能超过5MB'];
    }

    // 生成唯一文件名
    $extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
    $filename = 'upload_' . date('YmdHis') . '_' . uniqid() . '.' . $extension;
    $uploadPath = OUTPUT_DIR . $filename;

    // 移动上传的文件
    if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadPath)) {
        // 保存到会话中
        $_SESSION['uploaded_image'] = $uploadPath;
        $_SESSION['uploaded_image_info'] = [
            'path' => $uploadPath,
            'filename' => $filename,
            'time' => time()
        ];

        return [
            'code' => 0,
            'data' => [
                'imagePath' => $uploadPath,
                'filename' => $filename,
                'imageUrl' => getImageUrl($uploadPath)
            ],
            'msg' => '图片上传成功'
        ];
    } else {
        return ['code' => 1, 'msg' => '文件保存失败'];
    }
}

// 获取图片的URL（根据实际环境调整）
function getImageUrl($imagePath)
{
    // 如果是本地文件系统路径，直接返回路径用于API上传
    // API需要的是可访问的URL，这里我们返回本地路径，API应该能处理文件上传
    return $imagePath;
}

// 上传图片到API
function uploadImageToAPI($imagePath)
{
    if (!$imagePath || !file_exists($imagePath)) {
        return ['code' => 1, 'msg' => '图片文件不存在，请先上传图片'];
    }

    $url = API_BASE_URL . '/img/upload';
    $postData = [
        'file' => new CURLFile($imagePath, mime_content_type($imagePath), basename($imagePath))
    ];

    return apiRequest($url, 'POST', $postData, true);
}

// 生成视频
function generateVideo($imageUrl, $prompt)
{
    $url = API_BASE_URL . '/img2Video';
    $data = [
        'type' => 'I2V',
        'modelName' => 'seedance',
        'version' => 'lite',
        'image' => $imageUrl,
        'prompt' => $prompt,
        'duration' => '5',
        'resolution' => '480p'
    ];

    return apiRequest($url, 'POST', $data);
}

// 检查状态
function checkStatus($taskId)
{
    $url = API_BASE_URL . '/status';
    $data = [
        'taskId' => $taskId,
        'type' => '3'
    ];

    return apiRequest($url, 'POST', $data);
}

// 获取历史记录
function getHistory()
{
    $url = API_BASE_URL . '/ti2video/history';
    $data = [
        'pageNum' => 1,
        'pageSize' => 10
    ];

    return apiRequest($url, 'POST', $data);
}

// 查找视频URL
function findVideoUrl($videoId)
{
    $history = getHistory();

    if ($history['code'] === 0 && !empty($history['data']['records'])) {
        foreach ($history['data']['records'] as $record) {
            if (!empty($record['data'][0]) && $record['data'][0]['id'] == $videoId) {
                return $record['data'][0]['resultUrl'];
            }
        }
    }

    return null;
}

// 完整流程
function fullProcess($prompt)
{
    // 检查是否有上传的图片
    $imagePath = $_SESSION['uploaded_image'] ?? '';

    if (!$imagePath || !file_exists($imagePath)) {
        return ['code' => 1, 'msg' => '请先上传图片'];
    }

    // 检查并扣除用户积分
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();

    if (!$userId) {
        return ['code' => 1, 'msg' => '用户未登录'];
    }

    // 计算所需积分
    $requiredPoints = Config::VIDEO_GENERATION_COST;

    // 检查积分是否足够
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        return ['code' => 1, 'msg' => '积分不足，生成视频需要' . $requiredPoints . '积分'];
    }

    // 1. 上传图片到API
    $uploadResult = uploadImageToAPI($imagePath);
    if ($uploadResult['code'] !== 0) {
        return $uploadResult;
    }
    $imageUrl = $uploadResult['data'];

    // 2. 生成视频
    $videoResult = generateVideo($imageUrl, $prompt);
    if ($videoResult['code'] !== 0) {
        return $videoResult;
    }
    $taskId = $videoResult['data'];

    // 3. 保存任务记录到数据库
    $taskData = [
        'prompt' => $prompt,
        'image_path' => $imagePath,
        'image_url' => $imageUrl,
        'required_points' => $requiredPoints
    ];

    $taskDbId = saveTaskToDb($taskId, $userId, $taskData);
    if (!$taskDbId) {
        // 即使保存失败也继续流程，但记录日志
        error_log("保存任务记录失败，但继续视频生成流程。Task ID: $taskId, User ID: $userId");
    }

    // 4. 轮询状态
    $maxRetries = 24;
    $statusData = null;

    for ($i = 0; $i < $maxRetries; $i++) {
        $statusResult = checkStatus($taskId);

        if ($statusResult['code'] === 0 && !empty($statusResult['data'][0])) {
            $statusData = $statusResult['data'][0];

            if ($statusData['status'] === 'done') {
                break;
            } elseif ($statusData['status'] === 'error') {
                updateTaskStatusInDb($taskId, $userId, 'error', ['error' => '视频生成失败']);
                return ['code' => 1, 'msg' => '视频生成失败'];
            }
        }

        if ($i < $maxRetries - 1) {
            sleep(5);
        }
    }

    if (!$statusData || $statusData['status'] !== 'done') {
        // 返回任务ID，让用户稍后查询
        updateTaskStatusInDb($taskId, $userId, 'processing', null, 50); // 设置进度为50%
        return [
            'code' => 0,
            'data' => [
                'taskId' => $taskId,
                'status' => 'processing',
                'message' => '视频生成中，请稍后查询状态',
                'canRetrieveLater' => true,
                'progress' => 50
            ],
            'msg' => '视频生成中'
        ];
    }

    // 5. 获取视频URL
    $videoId = $statusData['id'];
    $videoUrl = findVideoUrl($videoId);

    if (!$videoUrl) {
        updateTaskStatusInDb($taskId, $userId, 'error', ['error' => '无法获取视频URL']);
        return ['code' => 1, 'msg' => '无法获取视频URL'];
    }

    // 6. 扣除用户积分
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '图生视频', 'img2video', $taskId);
    if (!$deductResult['success']) {
        updateTaskStatusInDb($taskId, $userId, 'error', ['error' => '积分扣除失败：' . $deductResult['message']]);
        return ['code' => 1, 'msg' => '积分扣除失败：' . $deductResult['message']];
    }

    // 7. 更新任务记录为成功
    $outputData = [
        'video_id' => $videoId,
        'video_url' => $videoUrl,
        'completed_at' => date('Y-m-d H:i:s'),
        'points_deducted' => true,
        'points_used' => $requiredPoints
    ];
    updateTaskStatusInDb($taskId, $userId, 'success', $outputData, 100);

    // 8. 返回结果
    return [
        'code' => 0,
        'data' => [
            'imageUrl' => $imageUrl,
            'videoUrl' => $videoUrl,
            'taskId' => $taskId,
            'videoId' => $videoId,
            'localImagePath' => $imagePath,
            'status' => 'success',
            'canRetrieveLater' => true,
            'progress' => 100,
            'db_task_id' => $taskDbId
        ],
        'msg' => '生成成功，已扣除' . $requiredPoints . '积分'
    ];
}

// 获取任务状态（检查数据库和API）
function getTaskStatus($taskId)
{
    // 从数据库获取任务信息
    $task = getTaskFromDb($taskId);

    if (!$task) {
        return ['code' => 1, 'msg' => '任务不存在'];
    }

    // 如果任务已完成，直接返回
    if ($task['status'] === 'success') {
        return [
            'code' => 0,
            'data' => [
                'taskId' => $task['task_id'],
                'status' => 'success',
                'videoUrl' => $task['video_url'],
                'videoId' => $task['video_id'],
                'prompt' => $task['prompt'],
                'createdAt' => $task['created_at'],
                'completedAt' => $task['completed_at'],
                'progress' => $task['progress']
            ],
            'msg' => '任务已完成'
        ];
    }

    // 如果任务失败，返回错误信息
    if ($task['status'] === 'error') {
        $outputData = $task['output_data'] ?? [];
        $errorMessage = $outputData['error'] ?? '未知错误';
        return [
            'code' => 1,
            'data' => [
                'taskId' => $task['task_id'],
                'status' => 'error',
                'errorMessage' => $errorMessage,
                'createdAt' => $task['created_at']
            ],
            'msg' => $errorMessage
        ];
    }

    // 任务还在处理中，查询API状态
    $statusResult = checkStatus($taskId);

    if ($statusResult['code'] === 0 && !empty($statusResult['data'][0])) {
        $statusData = $statusResult['data'][0];

        if ($statusData['status'] === 'done') {
            // 获取视频URL
            $videoId = $statusData['id'];
            $videoUrl = findVideoUrl($videoId);

            if ($videoUrl) {
                // 更新任务记录为成功
                $outputData = [
                    'video_id' => $videoId,
                    'video_url' => $videoUrl,
                    'completed_at' => date('Y-m-d H:i:s')
                ];
                updateTaskStatusInDb($taskId, $task['user_id'], 'success', $outputData, 100);

                return [
                    'code' => 0,
                    'data' => [
                        'taskId' => $task['task_id'],
                        'status' => 'success',
                        'videoUrl' => $videoUrl,
                        'videoId' => $videoId,
                        'prompt' => $task['prompt'],
                        'createdAt' => $task['created_at'],
                        'completedAt' => date('Y-m-d H:i:s'),
                        'progress' => 100
                    ],
                    'msg' => '视频生成成功'
                ];
            }
        } elseif ($statusData['status'] === 'error') {
            // 更新任务记录为失败
            updateTaskStatusInDb($taskId, $task['user_id'], 'error', [
                'error' => 'API返回生成失败: ' . ($statusData['message'] ?? '未知错误')
            ]);

            return [
                'code' => 1,
                'data' => [
                    'taskId' => $task['task_id'],
                    'status' => 'error',
                    'errorMessage' => 'API返回生成失败: ' . ($statusData['message'] ?? '未知错误'),
                    'createdAt' => $task['created_at']
                ],
                'msg' => '视频生成失败'
            ];
        }
    }

    // 任务仍在处理中，更新进度
    $progress = $task['progress'] < 90 ? $task['progress'] + 10 : $task['progress'];
    updateTaskStatusInDb($taskId, $task['user_id'], 'processing', null, $progress);

    // 返回处理中状态
    return [
        'code' => 0,
        'data' => [
            'taskId' => $task['task_id'],
            'status' => 'processing',
            'message' => '视频生成中，请稍后再试',
            'prompt' => $task['prompt'],
            'createdAt' => $task['created_at'],
            'progress' => $progress
        ],
        'msg' => '视频生成中'
    ];
}

// 处理Web请求
function handleWebRequest()
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // 检查请求类型
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        if (strpos($contentType, 'application/json') !== false) {
            // JSON请求
            $postData = json_decode(file_get_contents('php://input'), true);
            $action = $postData['action'] ?? '';

            switch ($action) {
                case 'generate_storyboard_video':
                    $shotImages = $postData['shotImages'] ?? [];
                    $taskId = $postData['taskId'] ?? '';

                    if (empty($shotImages)) {
                        jsonResponse(1, null, '请提供分镜图片');
                    }

                    $result = generateStoryboardVideo($shotImages, $taskId);
                    jsonResponse($result['code'], $result['data'], $result['msg']);
                    break;

                case 'get_video_url':
                    $videoId = $postData['videoId'] ?? '';

                    if (empty($videoId)) {
                        jsonResponse(1, null, '请提供视频ID');
                    }

                    $videoUrl = findVideoUrl($videoId);

                    if (!$videoUrl) {
                        jsonResponse(1, null, '无法获取视频URL');
                    }

                    jsonResponse(0, [
                        'videoUrl' => $videoUrl,
                        'videoId' => $videoId,
                        'status' => 'success'
                    ], '视频URL获取成功');
                    break;

                case 'status':
                    $taskId = $postData['taskId'] ?? '';

                    if (empty($taskId)) {
                        jsonResponse(1, null, '请提供Task ID');
                    }

                    $result = getTaskStatus($taskId);
                    jsonResponse($result['code'], $result['data'], $result['msg']);
                    break;

                case 'task_history':
                    // 获取用户的任务历史
                    $auth = new Auth();
                    $userId = $auth->getCurrentUserId();

                    if (!$userId) {
                        jsonResponse(1, null, '用户未登录');
                    }

                    $page = max(1, intval($postData['page'] ?? 1));
                    $limit = min(20, intval($postData['limit'] ?? 10));

                    $result = getUserTasksFromDb($userId, $limit, $page);
                    jsonResponse(0, $result, '获取成功');
                    break;

                case 'retrieve_task':
                    $taskId = $postData['taskId'] ?? '';

                    if (empty($taskId)) {
                        jsonResponse(1, null, '请提供Task ID');
                    }

                    $result = getTaskStatus($taskId);
                    jsonResponse($result['code'], $result['data'], $result['msg']);
                    break;
            }
        }

        // 传统表单请求
        $action = $_POST['action'] ?? '';

        switch ($action) {
            case 'full':
                $prompt = $_POST['prompt'] ?? '';
                if (empty($prompt)) {
                    jsonResponse(1, null, '请填写提示词');
                }

                $result = fullProcess($prompt);
                jsonResponse($result['code'], $result['data'], $result['msg']);
                break;

            case 'upload':
                $result = uploadImageToLocal();
                jsonResponse($result['code'], $result['data'], $result['msg']);
                break;

            case 'status':
                $taskId = $_POST['taskId'] ?? '';
                if (empty($taskId)) {
                    jsonResponse(1, null, '请填写Task ID');
                }
                $result = getTaskStatus($taskId);
                jsonResponse($result['code'], $result['data'], $result['msg']);
                break;

            case 'history':
                // 获取用户的任务历史
                $auth = new Auth();
                $userId = $auth->getCurrentUserId();

                if (!$userId) {
                    jsonResponse(1, null, '用户未登录');
                }

                $limit = intval($_POST['limit'] ?? 10);
                $page = intval($_POST['page'] ?? 1);

                $result = getUserTasksFromDb($userId, $limit, $page);
                jsonResponse(0, $result, '获取成功');
                break;

            default:
                jsonResponse(1, null, '未知操作');
        }
    } else {
        // 显示Web界面
        showWebInterface();
    }
}

// 生成故事板视频
function generateStoryboardVideo($shotImages, $taskId)
{
    // 检查并扣除用户积分
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();

    if (!$userId) {
        return ['code' => 1, 'msg' => '用户未登录'];
    }

    // 计算所需积分
    $requiredPoints = Config::VIDEO_GENERATION_COST * count($shotImages);

    // 检查积分是否足够
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        return ['code' => 1, 'msg' => '积分不足，生成视频需要' . $requiredPoints . '积分'];
    }

    // 验证图片URL格式
    $validShotImages = [];
    foreach ($shotImages as $shot) {
        if (isset($shot['imageUrl']) && filter_var($shot['imageUrl'], FILTER_VALIDATE_URL)) {
            $validShotImages[] = $shot;
        }
    }
    if (empty($validShotImages)) {
        return ['code' => 1, 'msg' => '图片URL格式错误'];
    }
    if (empty($validShotImages)) {
        return ['code' => 1, 'msg' => '没有有效的图片URL，无法生成视频'];
    }


    // 目前使用API只支持单张图片生成视频，我们使用第一张有效图片
    $selectedShot = $validShotImages[0];
    $imageUrl = $selectedShot['imageUrl'];
    $shotId = $selectedShot['id'];

    // 生成更有针对性的prompt
    $prompt = '故事板视频，将分镜图片转换为流畅的动画，保持画面的故事性和连贯性';

    // 调用API生成视频
    $videoResult = generateVideo($imageUrl, $prompt);

    if ($videoResult['code'] !== 0) {
        return [
            'code' => 1,
            'msg' => '视频生成请求失败: ' . ($videoResult['msg'] ?? '未知错误')
        ];
    }

    $taskId = $videoResult['data'];

    // 保存任务记录到数据库
    $taskData = [
        'prompt' => $prompt,
        'image_url' => $imageUrl,
        'required_points' => $requiredPoints,
        'shot_id' => $shotId,
        'total_shots' => count($validShotImages),
        'type' => 'storyboard'
    ];

    $taskDbId = saveTaskToDb($taskId, $userId, $taskData);

    // 轮询状态，检查视频生成进度
    $maxRetries = 24;
    $statusData = null;
    $retryCount = 0;

    while ($retryCount < $maxRetries) {
        $statusResult = checkStatus($taskId);

        // 检查API响应格式
        if (!isset($statusResult['code']) || $statusResult['code'] !== 0) {
            $retryCount++;
            if ($retryCount < $maxRetries) {
                sleep(5);
            }
            continue;
        }

        if (isset($statusResult['data'][0])) {
            $statusData = $statusResult['data'][0];

            // 检查任务状态
            if ($statusData['status'] === 'done') {
                break; // 生成成功
            } elseif ($statusData['status'] === 'error') {
                updateTaskStatusInDb($taskId, $userId, 'error', [
                    'error' => '视频生成失败: ' . ($statusData['message'] ?? '未知错误')
                ]);
                return [
                    'code' => 1,
                    'msg' => '视频生成失败: ' . ($statusData['message'] ?? '未知错误')
                ];
            }
        }

        $retryCount++;
        if ($retryCount < $maxRetries) {
            sleep(5);
        }
    }

    if (!$statusData || $statusData['status'] !== 'done') {
        // 视频仍在生成中，返回任务ID让前端轮询
        updateTaskStatusInDb($taskId, $userId, 'processing', null, 50);
        return [
            'code' => 0,
            'data' => [
                'taskId' => $taskId,
                'status' => 'processing',
                'message' => '视频生成中，请稍后查询状态',
                'totalShots' => count($validShotImages),
                'processedShots' => 1,
                'canRetrieveLater' => true,
                'progress' => 50
            ],
            'msg' => '视频生成中'
        ];
    }

    // 获取视频URL
    $videoId = $statusData['id'];
    $videoUrl = findVideoUrl($videoId);

    if (!$videoUrl) {
        updateTaskStatusInDb($taskId, $userId, 'error', ['error' => '无法获取视频URL']);
        return ['code' => 1, 'msg' => '无法获取视频URL，请稍后重试'];
    }

    // 扣除用户积分
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '故事板视频生成', 'storyboard_video', $taskId);
    if (!$deductResult['success']) {
        updateTaskStatusInDb($taskId, $userId, 'error', ['error' => '积分扣除失败：' . $deductResult['message']]);
        return ['code' => 1, 'msg' => '积分扣除失败：' . $deductResult['message']];
    }

    // 更新任务记录为成功
    $outputData = [
        'video_id' => $videoId,
        'video_url' => $videoUrl,
        'completed_at' => date('Y-m-d H:i:s'),
        'points_deducted' => true,
        'points_used' => $requiredPoints,
        'shot_id' => $shotId,
        'total_shots' => count($validShotImages)
    ];
    updateTaskStatusInDb($taskId, $userId, 'success', $outputData, 100);

    // 返回结果
    return [
        'code' => 0,
        'data' => [
            'imageUrl' => $imageUrl,
            'videoUrl' => $videoUrl,
            'taskId' => $taskId,
            'videoId' => $videoId,
            'status' => 'success',
            'totalShots' => count($validShotImages),
            'shotId' => $shotId,
            'canRetrieveLater' => true,
            'progress' => 100,
            'db_task_id' => $taskDbId
        ],
        'msg' => '生成成功，已扣除' . $requiredPoints . '积分'
    ];
}

// 主入口
handleWebRequest();

// 显示Web界面
function showWebInterface()
{
    // 检查已上传的图片
    $uploadedImage = $_SESSION['uploaded_image'] ?? '';
    $imageExists = $uploadedImage && file_exists($uploadedImage);
    $imageInfo = $_SESSION['uploaded_image_info'] ?? null;

    // 获取用户的任务历史
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();
    $userTasks = [];

    if ($userId) {
        $result = getUserTasksFromDb($userId, 5, 1);
        $userTasks = $result['tasks'] ?? [];
    }
?>
    <!DOCTYPE html>
    <html>

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
        <title>智影工场 图生视频</title>
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/menu.css">
        <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    </head>

    <body>
        <?php include 'header.html'; ?>
        <div class="function-bar">
            <div class="function-left">
                <div class="tab active">图生视频</div>
            </div>
            <div class="function-right">
                <div class="header-actions">
                    <button class="theme-toggle" id="themeToggle">
                        <i class="fas fa-moon"></i>
                    </button>
                </div>
            </div>
        </div>
        <div class="app-container">
            <main id="pageContent" style="display: none;">
                <div class="two-column-layout">
                    <!-- 左侧：参数设置和生成 -->
                    <div class="left-column">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-cogs card-icon"></i>
                                    图生视频设置
                                </h2>
                            </div>
                            <div class="card-body">
                                <form id="videoForm">
                                    <!-- 上传与预览双列布局 -->
                                    <div class="form-group">
                                        <label class="form-label" for="imageInput">
                                            <i class="fas fa-cloud-upload-alt"></i>
                                            上传图片
                                        </label>
                                        <div class="upload-preview-row">
                                            <!-- 左侧：上传图片区 -->
                                            <div class="upload-section">
                                                <div class="file-input-container">
                                                    <div class="file-input" id="uploadArea">
                                                        <i class="fas fa-cloud-upload-alt"></i>
                                                        <p>点击或拖拽图片到此处上传</p>
                                                        <p>支持JPEG, PNG, GIF, WEBP格式，最大5MB</p>
                                                        <input type="file" name="image" id="imageInput" accept="image/*">
                                                    </div>
                                                </div>
                                                <!-- 图片状态信息 -->
                                                <div id="uploadStatus" class="status-info" style="display: none;"></div>
                                            </div>

                                            <!-- 右侧：图片预览区 -->
                                            <div class="preview-section">
                                                <div class="preview-container" id="previewContainer">
                                                    <?php if ($imageExists && $imageInfo): ?>
                                                        <img id="previewImage" class="preview-image" src="<?php echo OUTPUT_DIR . htmlspecialchars($imageInfo['filename']); ?>" alt="预览">
                                                    <?php else: ?>
                                                        <div class="empty-preview">
                                                            <i class="fas fa-image"></i>
                                                            <p>上传图片后将在此处显示预览</p>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 提示词输入 -->
                                    <div class="form-group">
                                        <label class="form-label" for="promptInput">
                                            <i class="fas fa-keyboard"></i>
                                            提示词
                                        </label>
                                        <textarea
                                            id="promptInput"
                                            class="form-control"
                                            placeholder="描述你想生成的画面和动作，例如：美丽的油画少女，柔光落在她的脸上"
                                            rows="2" required></textarea>
                                    </div>

                                    <!-- 积分消耗提示 -->
                                    <div class="points-info">
                                        <i class="fas fa-coins"></i> 图生视频每次消耗 <strong><?php echo Config::VIDEO_GENERATION_COST; ?> 积分</strong>
                                    </div>

                                    <!-- 生成按钮 -->
                                    <button type="button" class="btn btn-primary" id="generateBtn" onclick="generateVideo()" <?php echo !$imageExists ? 'disabled' : ''; ?>>
                                        <i class="fas fa-magic"></i>
                                        生成视频
                                    </button>

                                    <!-- 任务查询区域 -->
                                    <div class="task-query-section">
                                        <div class="form-group">
                                            <label class="form-label">
                                                <i class="fas fa-search"></i>
                                                查询历史任务
                                            </label>
                                            <div class="input-group">
                                                <input type="text" id="taskIdInput" class="form-control" placeholder="输入Task ID查询历史任务">
                                                <button type="button" class="btn btn-secondary" onclick="retrieveTask()">
                                                    <i class="fas fa-history"></i> 查询
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <!-- 历史任务列表 -->
                        <?php if (!empty($userTasks)): ?>
                            <div class="card">
                                <div class="card-header">
                                    <h2 class="card-title">
                                        <i class="fas fa-history card-icon"></i>
                                        最近任务
                                    </h2>
                                </div>
                                <div class="card-body">
                                    <div class="history-list">
                                        <?php foreach ($userTasks as $task): ?>
                                            <div class="history-item" onclick="retrieveTaskById('<?php echo htmlspecialchars($task['task_id']); ?>')">
                                                <div class="history-item-header">
                                                    <span class="history-task-id">任务: <?php echo htmlspecialchars(substr($task['task_id'], 0, 8)); ?>...</span>
                                                    <span class="history-status status-<?php echo $task['status']; ?>">
                                                        <?php echo htmlspecialchars($task['status_text']); ?>
                                                    </span>
                                                </div>
                                                <div class="history-prompt"><?php echo htmlspecialchars(mb_strimwidth($task['prompt'], 0, 50, '...')); ?></div>
                                                <div class="history-time"><?php echo htmlspecialchars($task['created_at']); ?></div>
                                                <?php if ($task['progress'] > 0 && $task['progress'] < 100): ?>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%"></div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 右侧：结果展示 -->
                    <div class="right-column">
                        <div class="card">
                            <div class="card-header">
                                <h2 class="card-title">
                                    <i class="fas fa-video card-icon"></i>
                                    生成结果
                                </h2>
                            </div>
                            <!-- 视频展示区域 -->
                            <div class="image-container" id="resultContainer" style="display: none;">
                                <div class="video-container">
                                    <video id="resultVideo" class="single-image" controls>
                                        <source id="videoSource" src="" type="video/mp4">
                                        您的浏览器不支持视频播放
                                    </video>
                                </div>
                            </div>
                            <div class="card-body result-content">
                                <!-- 初始空状态 -->
                                <div class="image-container" id="initialState">
                                    <div class="empty-state">
                                        <div class="empty-icon">
                                            <i class="fas fa-video"></i>
                                        </div>
                                        <h3>等待生成视频</h3>
                                        <p>上传图片并设置提示词后，点击"生成视频"按钮</p>
                                        <p>生成的视频将在这里显示</p>
                                        <p class="small-text">您可以查询历史任务来获取之前生成的视频</p>
                                    </div>
                                </div>



                                <!-- 结果信息 -->
                                <div class="result-info" id="videoInfo" style="display: none;">
                                    <div class="info-grid">
                                        <div class="info-item">
                                            <span class="info-label">任务ID：</span>
                                            <span id="videoTaskId" class="info-value"></span>
                                            <button class="copy-btn" onclick="copyTaskId()" title="复制Task ID">
                                                <i class="fas fa-copy"></i>
                                            </button>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">生成时间：</span>
                                            <span id="videoTime" class="info-value"></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">状态：</span>
                                            <span id="videoStatus" class="info-value"></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">提示词：</span>
                                            <span id="videoPrompt" class="info-value"></span>
                                        </div>
                                        <div class="info-item">
                                            <span class="info-label">进度：</span>
                                            <span id="videoProgress" class="info-value"></span>
                                        </div>
                                    </div>
                                    <div class="result-actions">
                                        <a id="videoDownloadBtn" class="btn-primary" download>
                                            <i class="fas fa-download"></i>
                                            下载视频
                                        </a>
                                        <button type="button" class="btn btn-secondary" onclick="saveTaskForLater()">
                                            <i class="fas fa-save"></i>
                                            保存记录
                                        </button>
                                    </div>
                                </div>

                                <!-- 处理中状态 -->
                                <div class="processing-state" id="processingState" style="display: none;">
                                    <div class="processing-content">
                                        <div class="spinner"></div>
                                        <h3>视频生成中</h3>
                                        <p id="processingMessage">视频正在生成，请耐心等待...</p>
                                        <div class="progress-bar">
                                            <div class="progress-fill" id="progressFill" style="width: 0%"></div>
                                        </div>
                                        <p class="small-text">任务ID: <span id="processingTaskId"></span></p>
                                        <p class="small-text">您可以保存此Task ID，稍后回来查询结果</p>
                                        <div class="processing-actions">
                                            <button type="button" class="btn btn-secondary" onclick="checkStatusAgain()">
                                                <i class="fas fa-sync-alt"></i> 刷新状态
                                            </button>
                                            <button type="button" class="btn btn-secondary" onclick="copyProcessingTaskId()">
                                                <i class="fas fa-copy"></i> 复制Task ID
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- 错误提示 -->
                                <div class="error-alert" id="errorAlert" style="display: none;">
                                    <div class="error-header">
                                        <i class="fas fa-exclamation-circle"></i>
                                        <h3>生成失败</h3>
                                    </div>
                                    <p id="errorMessage"></p>
                                    <div class="error-actions">
                                        <button type="button" class="btn btn-secondary" onclick="retryTask()">
                                            <i class="fas fa-redo"></i> 重试
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

            <!-- 加载遮罩层 -->
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <div class="loading-text">
                    <h3>正在生成视频中...</h3>
                    <p>智影工场正在根据您的图片和描述创作视频，这可能需要几分钟时间</p>
                    <p>请耐心等待，精彩即将呈现！</p>
                </div>
            </div>
        </div>

        <!-- 底部版权声明栏 -->
        <?php include 'footer.html'; ?>

        <script>
            // 主题切换功能
            function initThemeToggle() {
                const themeToggle = document.getElementById('themeToggle');
                if (themeToggle) {
                    themeToggle.addEventListener('click', function() {
                        document.body.classList.toggle('dark-theme');
                        const icon = this.querySelector('i');
                        if (document.body.classList.contains('dark-theme')) {
                            icon.className = 'fas fa-sun';
                        } else {
                            icon.className = 'fas fa-moon';
                        }
                    });
                }
            }

            // 预览图片
            function previewUploadedImage(file, previewImage) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImage.src = e.target.result;
                }
                reader.readAsDataURL(file);
            }

            // 显示状态信息
            function showStatusMessage(elementId, message, isError = false) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.style.display = 'block';
                    element.className = `status-info ${isError ? 'error' : ''}`;
                    element.innerHTML = message;
                }
            }

            // 隐藏状态信息
            function hideStatusMessage(elementId) {
                const element = document.getElementById(elementId);
                if (element) {
                    element.style.display = 'none';
                }
            }

            // 自动上传图片
            function uploadImageAuto(file) {
                const uploadStatus = document.getElementById('uploadStatus');
                const previewContainer = document.getElementById('previewContainer');
                const previewImage = document.getElementById('previewImage');
                const generateBtn = document.getElementById('generateBtn');
                const promptInput = document.getElementById('promptInput');

                const formData = new FormData();
                formData.append('action', 'upload');
                formData.append('image', file);

                // 显示上传状态
                showStatusMessage('uploadStatus', '<div class="spinner" style="width:20px;height:20px;border-width:2px;margin:0 auto;display:inline-block;vertical-align:middle;margin-right:10px;"></div> 正在上传图片...');

                // 禁用生成按钮
                generateBtn.disabled = true;

                fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 0) {
                            showStatusMessage('uploadStatus', `✓ ${data.msg}`);

                            // 显示预览
                            previewContainer.style.display = 'flex';

                            // 创建或更新预览图片
                            let imgElement = previewImage;
                            if (!imgElement) {
                                // 如果预览图片元素不存在，创建一个新的
                                imgElement = document.createElement('img');
                                imgElement.id = 'previewImage';
                                imgElement.className = 'preview-image';
                                imgElement.alt = '预览';

                                // 移除空预览提示
                                const emptyPreview = previewContainer.querySelector('.empty-preview');
                                if (emptyPreview) {
                                    emptyPreview.remove();
                                }

                                previewContainer.appendChild(imgElement);
                            }

                            // 更新预览图片
                            previewUploadedImage(file, imgElement);

                            // 启用生成按钮
                            generateBtn.disabled = false;

                            // 自动聚焦到提示词输入框
                            if (promptInput) {
                                promptInput.focus();
                            }

                            // 3秒后隐藏上传状态提示
                            setTimeout(() => {
                                hideStatusMessage('uploadStatus');
                            }, 3000);
                        } else {
                            showStatusMessage('uploadStatus', `✗ ${data.msg}`, true);
                        }
                    })
                    .catch(error => {
                        showStatusMessage('uploadStatus', `✗ 上传失败: ${error}`, true);
                    });
            }

            // 预览并自动上传图片
            function previewAndUploadImage() {
                const input = document.getElementById('imageInput');
                const previewContainer = document.getElementById('previewContainer');
                const previewImage = document.getElementById('previewImage');

                if (input.files && input.files[0]) {
                    const file = input.files[0];

                    // 检查文件类型
                    if (!file.type.match('image.*')) {
                        showStatusMessage('uploadStatus', '请选择图片文件', true);
                        return;
                    }

                    // 自动上传图片
                    uploadImageAuto(file);
                }
            }

            // 显示错误信息
            function showError(message) {
                const errorAlert = document.getElementById('errorAlert');
                const errorMessage = document.getElementById('errorMessage');
                const initialState = document.getElementById('initialState');
                const resultContainer = document.getElementById('resultContainer');
                const videoInfo = document.getElementById('videoInfo');
                const processingState = document.getElementById('processingState');

                if (errorAlert && errorMessage) {
                    errorMessage.textContent = message;
                    errorAlert.style.display = 'block';
                    initialState.style.display = 'none';
                    resultContainer.style.display = 'none';
                    videoInfo.style.display = 'none';
                    processingState.style.display = 'none';
                }
            }

            // 隐藏错误信息
            function hideError() {
                const errorAlert = document.getElementById('errorAlert');
                if (errorAlert) {
                    errorAlert.style.display = 'none';
                }
            }

            // 显示处理中状态
            function showProcessingState(taskId, progress = 0, message = '视频正在生成，请耐心等待...') {
                const initialState = document.getElementById('initialState');
                const resultContainer = document.getElementById('resultContainer');
                const videoInfo = document.getElementById('videoInfo');
                const processingState = document.getElementById('processingState');
                const errorAlert = document.getElementById('errorAlert');

                hideError();
                initialState.style.display = 'none';
                resultContainer.style.display = 'none';
                videoInfo.style.display = 'none';
                processingState.style.display = 'block';

                if (taskId) {
                    document.getElementById('processingTaskId').textContent = taskId;
                }

                if (message) {
                    document.getElementById('processingMessage').textContent = message;
                }

                if (progress >= 0) {
                    const progressFill = document.getElementById('progressFill');
                    if (progressFill) {
                        progressFill.style.width = progress + '%';
                    }
                }
            }

            // 生成视频
            function generateVideo() {
                const promptInput = document.getElementById('promptInput');
                const prompt = promptInput.value.trim();

                if (!prompt) {
                    showError('请输入提示词');
                    promptInput.focus();
                    return;
                }

                // 检查是否有上传的图片
                const previewContainer = document.getElementById('previewContainer');
                const previewImage = document.getElementById('previewImage');
                if (!previewImage) {
                    showError('请先上传图片');
                    document.getElementById('imageInput').click();
                    return;
                }

                // 显示加载遮罩
                const loadingOverlay = document.getElementById('loadingOverlay');
                loadingOverlay.classList.add('active');

                // 禁用按钮防止重复提交
                const generateBtn = document.getElementById('generateBtn');
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';

                // 准备表单数据
                const formData = new FormData();
                formData.append('action', 'full');
                formData.append('prompt', prompt);

                // 发送请求
                fetch('', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        // 隐藏加载遮罩
                        loadingOverlay.classList.remove('active');

                        // 恢复按钮状态
                        generateBtn.disabled = false;
                        generateBtn.innerHTML = '<i class="fas fa-magic"></i> 生成视频';

                        // 处理结果
                        if (data.code === 0 && data.data) {
                            const taskData = data.data;

                            if (taskData.status === 'success') {
                                // 显示成功结果
                                displayResult(taskData);
                            } else if (taskData.status === 'processing') {
                                // 显示处理中状态
                                showProcessingState(taskData.taskId, taskData.progress || 0, taskData.message);
                                // 开始轮询状态
                                pollTaskStatus(taskData.taskId);
                            }
                        } else {
                            // 显示错误信息
                            showError(`生成失败: ${data.msg || '未知错误'}`);
                        }
                    })
                    .catch(error => {
                        // 隐藏加载遮罩
                        loadingOverlay.classList.remove('active');

                        // 恢复按钮状态
                        generateBtn.disabled = false;
                        generateBtn.innerHTML = '<i class="fas fa-magic"></i> 生成视频';

                        // 显示错误信息
                        showError(`网络错误: ${error.message}`);
                    });
            }

            // 轮询任务状态
            function pollTaskStatus(taskId) {
                let pollCount = 0;
                const maxPolls = 30; // 最多轮询30次（约5分钟）

                const pollInterval = setInterval(() => {
                    pollCount++;
                    if (pollCount > maxPolls) {
                        clearInterval(pollInterval);
                        showProcessingState(taskId, 100, '视频生成超时，请稍后查询状态');
                        return;
                    }

                    checkTaskStatus(taskId).then(data => {
                        if (data.status === 'success') {
                            clearInterval(pollInterval);
                            displayResult(data);
                        } else if (data.status === 'error') {
                            clearInterval(pollInterval);
                            showError(`任务失败: ${data.message || '未知错误'}`);
                        } else if (data.status === 'processing') {
                            // 更新进度
                            showProcessingState(taskId, data.progress || 0, data.message || '视频正在生成，请耐心等待...');
                        }
                    }).catch(error => {
                        console.error('轮询失败:', error);
                        if (pollCount > maxPolls) {
                            clearInterval(pollInterval);
                        }
                    });
                }, 10000); // 每10秒轮询一次
            }

            // 检查任务状态
            function checkTaskStatus(taskId) {
                return fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=status&taskId=${encodeURIComponent(taskId)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 0 && data.data) {
                            return data.data;
                        } else {
                            throw new Error(data.msg || '状态查询失败');
                        }
                    });
            }

            // 手动检查状态
            function checkStatusAgain() {
                const taskId = document.getElementById('processingTaskId').textContent;
                if (taskId) {
                    checkTaskStatus(taskId).then(data => {
                        if (data.status === 'success') {
                            displayResult(data);
                        } else if (data.status === 'error') {
                            showError(`任务失败: ${data.message || '未知错误'}`);
                        } else {
                            showProcessingState(taskId, data.progress || 0, data.message || '视频仍在生成中');
                        }
                    }).catch(error => {
                        showError(`状态查询失败: ${error.message}`);
                    });
                }
            }

            // 显示结果
            function displayResult(data) {
                // 隐藏初始状态和处理中状态
                const initialState = document.getElementById('initialState');
                const processingState = document.getElementById('processingState');
                const resultContainer = document.getElementById('resultContainer');
                const videoInfo = document.getElementById('videoInfo');
                const errorAlert = document.getElementById('errorAlert');

                hideError();
                initialState.style.display = 'none';
                processingState.style.display = 'none';
                resultContainer.style.display = 'flex';
                videoInfo.style.display = 'block';

                // 更新视频信息
                document.getElementById('videoTaskId').textContent = data.taskId || '';
                document.getElementById('videoTime').textContent = data.completedAt ? new Date(data.completedAt).toLocaleString() : new Date().toLocaleString();
                document.getElementById('videoStatus').textContent = data.status === 'success' ? '生成成功' : '处理中';
                document.getElementById('videoPrompt').textContent = data.prompt || '';
                document.getElementById('videoProgress').textContent = (data.progress || 0) + '%';

                // 如果有视频URL，显示视频
                if (data.videoUrl) {
                    const videoSource = document.getElementById('videoSource');
                    const resultVideo = document.getElementById('resultVideo');
                    const videoDownloadBtn = document.getElementById('videoDownloadBtn');

                    videoSource.src = data.videoUrl;
                    resultVideo.load();

                    // 设置下载链接
                    videoDownloadBtn.href = data.videoUrl;
                    videoDownloadBtn.download = `generated-video-${Date.now()}.mp4`;
                }

                // 滚动到结果区域
                resultContainer.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }

            // 查询历史任务
            function retrieveTask() {
                const taskIdInput = document.getElementById('taskIdInput');
                const taskId = taskIdInput.value.trim();

                if (!taskId) {
                    showError('请输入Task ID');
                    taskIdInput.focus();
                    return;
                }

                retrieveTaskById(taskId);
            }

            // 通过Task ID查询任务
            function retrieveTaskById(taskId) {
                // 显示处理中状态
                showProcessingState(taskId, 0, '正在查询任务状态...');

                fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=status&taskId=${encodeURIComponent(taskId)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.code === 0 && data.data) {
                            const taskData = data.data;

                            if (taskData.status === 'success') {
                                // 显示成功结果
                                displayResult(taskData);
                            } else if (taskData.status === 'processing') {
                                // 显示处理中状态
                                showProcessingState(taskData.taskId, taskData.progress || 0, taskData.message || '视频正在生成，请稍候...');
                                // 开始轮询状态
                                pollTaskStatus(taskData.taskId);
                            } else if (taskData.status === 'error') {
                                showError(`任务失败: ${taskData.errorMessage || '未知错误'}`);
                            }
                        } else {
                            showError(`查询失败: ${data.msg || '未知错误'}`);
                        }
                    })
                    .catch(error => {
                        showError(`查询失败: ${error.message}`);
                    });
            }

            // 复制Task ID
            function copyTaskId() {
                const taskId = document.getElementById('videoTaskId').textContent;
                if (taskId) {
                    navigator.clipboard.writeText(taskId).then(() => {
                        alert('Task ID 已复制到剪贴板');
                    });
                }
            }

            // 复制处理中的Task ID
            function copyProcessingTaskId() {
                const taskId = document.getElementById('processingTaskId').textContent;
                if (taskId) {
                    navigator.clipboard.writeText(taskId).then(() => {
                        alert('Task ID 已复制到剪贴板');
                    });
                }
            }

            // 保存任务记录
            function saveTaskForLater() {
                const taskId = document.getElementById('videoTaskId').textContent;
                if (taskId) {
                    // 这里可以将任务ID保存到用户的本地存储中
                    // 使用包含用户ID的键名，确保本地任务与用户关联
                    const localStorageKey = 'user_' + <?php echo $_SESSION['user_id']; ?> + '_savedVideoTasks';
                    let savedTasks = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
                    if (!savedTasks.includes(taskId)) {
                        savedTasks.push(taskId);
                        localStorage.setItem(localStorageKey, JSON.stringify(savedTasks));
                        alert('任务已保存到本地记录');
                    } else {
                        alert('任务已在记录中');
                    }
                }
            }

            // 重试任务
            function retryTask() {
                // 这里可以实现重试失败的任务
                alert('重试功能待实现');
            }

            // 初始化事件监听器
            document.addEventListener('DOMContentLoaded', function() {
                // 显示主内容区域
                const pageContent = document.getElementById('pageContent');
                if (pageContent) {
                    pageContent.style.display = 'block';
                }

                // 初始化主题切换
                initThemeToggle();

                // 文件选择事件 - 选择后自动上传
                const imageInput = document.getElementById('imageInput');
                if (imageInput) {
                    imageInput.addEventListener('change', previewAndUploadImage);
                }

                // 拖拽上传支持
                const uploadArea = document.getElementById('uploadArea');
                if (uploadArea && imageInput) {
                    uploadArea.addEventListener('dragover', function(e) {
                        e.preventDefault();
                        this.style.borderColor = 'var(--primary-color)';
                        this.style.background = 'linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%)';
                    });

                    uploadArea.addEventListener('dragleave', function(e) {
                        e.preventDefault();
                        this.style.borderColor = '#e2e8f0';
                        this.style.background = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';
                    });

                    uploadArea.addEventListener('drop', function(e) {
                        e.preventDefault();
                        this.style.borderColor = '#e2e8f0';
                        this.style.background = 'linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%)';

                        if (e.dataTransfer.files.length) {
                            const file = e.dataTransfer.files[0];

                            // 检查文件类型
                            if (!file.type.match('image.*')) {
                                showStatusMessage('uploadStatus', '请选择图片文件', true);
                                return;
                            }

                            // 创建FileList对象并设置文件
                            const dataTransfer = new DataTransfer();
                            dataTransfer.items.add(file);
                            imageInput.files = dataTransfer.files;

                            // 触发上传
                            previewAndUploadImage();
                        }
                    });
                }

                // 表单提交事件
                const videoForm = document.getElementById('videoForm');
                if (videoForm) {
                    videoForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        generateVideo();
                    });
                }

                // 输入框回车提交
                const promptInput = document.getElementById('promptInput');
                if (promptInput) {
                    promptInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter' && e.ctrlKey) {
                            generateVideo();
                        }
                    });

                    // 页面加载时如果有已上传图片，自动聚焦到提示词
                    const previewContainer = document.getElementById('previewContainer');
                    if (previewContainer.style.display === 'flex') {
                        promptInput.focus();
                    }
                }

                // Task ID输入框回车查询
                const taskIdInput = document.getElementById('taskIdInput');
                if (taskIdInput) {
                    taskIdInput.addEventListener('keydown', function(e) {
                        if (e.key === 'Enter') {
                            retrieveTask();
                        }
                    });
                }
            });
        </script>
    </body>

    </html>
<?php
}
