<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到首页
    header('Location: index.html');
    exit(0);
}

// 引入配置文件
require_once 'config.php';
require_once 'Auth.php';

/**
 * 文生图API调用实例（带数据库任务记录）
 * 兼容PHP 7.4+
 */


class TextToImageClient
{

    private $API_URL = '';
    private $timeout = 600;

    public function __construct()
    {
        $this->API_URL = Config::TEXT2IMG_API_URL();
    }
    /**
     * 获取API地址
     */
    public function getApiUrl()
    {
        return $this->API_URL;
    }

    /**
     * 设置API地址
     */
    public function setApiUrl($url)
    {
        $this->API_URL = $url;
        return $this;
    }

    /**
     * 设置超时时间（秒）
     */
    public function setTimeout($seconds)
    {
        $this->timeout = $seconds;
        return $this;
    }

    /**
     * 基本调用示例 - 生成单张图片
     */
    public function generateSingleImage($prompt, $options = [])
    {
        // 合并参数
        $params = array_merge([
            'prompt' => $prompt,
            'style' => $options['style'] ?? 21,
            'picSize' => $options['picSize'] ?? '16:9'
        ], $options);

        // 移除不必要的参数
        unset($params['style'], $params['picSize'], $params['count']);

        return $this->callApi($params);
    }

    /**
     * 批量生成图片示例
     */
    public function generateMultipleImages($prompt, $count = 1, $options = [])
    {
        $params = array_merge([
            'prompt' => $prompt,
            'count' => $count,
            'style' => $options['style'] ?? 21,
            'picSize' => $options['picSize'] ?? '16:9'
        ], $options);

        return $this->callApi($params);
    }

    /**
     * 带样式的生成示例
     */
    public function generateWithStyle($prompt, $style = 21, $options = [])
    {
        $params = array_merge([
            'prompt' => $prompt,
            'style' => $style
        ], $options);

        return $this->callApi($params);
    }

    /**
     * 带比例的生成示例
     */
    public function generateWithRatio($prompt, $picSize = '16:9', $options = [])
    {
        $params = array_merge([
            'prompt' => $prompt,
            'picSize' => $picSize
        ], $options);

        return $this->callApi($params);
    }

    /**
     * 自定义生成示例
     */
    public function generateCustom($params)
    {
        return $this->callApi($params);
    }

    /**
     * 调用API核心方法
     */
    private function callApi($params)
    {
        $ch = curl_init();

        // 准备请求数据
        $jsonData = json_encode($params, JSON_UNESCAPED_UNICODE);

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->API_URL,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
                'User-Agent: TextToImageClient/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL Error: ' . $error,
                'http_code' => $httpCode
            ];
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'JSON Parse Error: ' . json_last_error_msg(),
                'raw_response' => $response,
                'http_code' => $httpCode
            ];
        }

        return [
            'success' => isset($result['code']) && $result['code'] === 0,
            'http_code' => $httpCode,
            'response' => $result
        ];
    }

    /**
     * 保存图片到本地
     */
    public function saveImageToFile($imageUrl, $savePath)
    {
        try {
            $imageData = file_get_contents($imageUrl);

            if ($imageData === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to download image'
                ];
            }

            $result = file_put_contents($savePath, $imageData);

            if ($result === false) {
                return [
                    'success' => false,
                    'error' => 'Failed to save image to file'
                ];
            }

            return [
                'success' => true,
                'file_path' => $savePath,
                'file_size' => filesize($savePath)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }

    /**
     * 获取API状态（帮助信息）
     */
    public function getApiInfo()
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->API_URL,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'TextToImageClient/1.0'
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? ['error' => 'Failed to get API info'];
    }
}

// ===================== 数据库任务记录功能 =====================

// 保存任务记录到数据库
function saveTextToImageTaskToDb($taskId, $userId, $taskData)
{
    try {
        $db = Database::getInstance();

        // 准备任务数据
        $title = mb_strimwidth($taskData['prompt'], 0, 100, '...');
        $status = 0; // 0=处理中，1=成功，2=失败
        $taskType = $taskData['count'] > 1 ? 'text2img_batch' : 'text2img';

        // 转换input_data为JSON
        $inputData = json_encode([
            'prompt' => $taskData['prompt'] ?? '',
            'style' => $taskData['style'] ?? 21,
            'picSize' => $taskData['picSize'] ?? '16:9',
            'count' => $taskData['count'] ?? 1,
            'required_points' => Config::IMAGE_GENERATION_COST * ($taskData['count'] ?? 1)
        ], JSON_UNESCAPED_UNICODE);

        // 检查任务是否已存在
        $existingTask = $db->queryOne(
            "SELECT id FROM tasks WHERE task_id = ? AND user_id = ? AND task_type = ?",
            [$taskId, $userId, $taskType]
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
                 WHERE task_id = ? AND user_id = ? AND task_type = ?",
                [
                    $title,
                    $status,
                    0,
                    $inputData,
                    $taskId,
                    $userId,
                    $taskType
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
                    $taskType,
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
        error_log("保存文生图任务到数据库失败: " . $e->getMessage());
        return false;
    }
}

// 更新任务状态
function updateTextToImageTaskStatus($taskId, $userId, $status, $outputData = null, $progress = null)
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
        error_log("更新文生图任务状态失败: " . $e->getMessage());
        return false;
    }
}

// 获取文生图任务历史
function getUserTextToImageTasks($userId, $limit = 10, $page = 1)
{
    try {
        $db = Database::getInstance();

        $offset = ($page - 1) * $limit;

        // 获取任务列表（只获取文生图相关的任务）
        $tasks = $db->query(
            "SELECT id, task_id, task_type, title, status, progress, 
                    input_data, output_data, created_at, completed_at
             FROM tasks 
             WHERE user_id = ? AND task_type IN ('text2img', 'text2img_batch')
             ORDER BY created_at DESC 
             LIMIT ? OFFSET ?",
            [$userId, $limit, $offset]
        );

        // 获取总数量
        $totalResult = $db->queryOne(
            "SELECT COUNT(*) as total FROM tasks WHERE user_id = ? AND task_type IN ('text2img', 'text2img_batch')",
            [$userId]
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

            // 获取图片URL
            $imageUrl = '';
            if ($task['task_type'] === 'text2img_batch' && isset($outputData['images'][0]['url'])) {
                $imageUrl = $outputData['images'][0]['url'];
            } elseif (isset($outputData['imageUrl'])) {
                $imageUrl = $outputData['imageUrl'];
            }

            $processedTasks[] = [
                'id' => $task['id'],
                'task_id' => $task['task_id'],
                'task_type' => $task['task_type'],
                'title' => $task['title'],
                'status' => $task['status'],
                'status_text' => $statusText[$task['status']] ?? '未知',
                'progress' => $task['progress'],
                'prompt' => $inputData['prompt'] ?? '',
                'style' => $inputData['style'] ?? 21,
                'picSize' => $inputData['picSize'] ?? '16:9',
                'count' => $inputData['count'] ?? 1,
                'image_url' => $imageUrl,
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
        error_log("获取用户文生图任务失败: " . $e->getMessage());
        return ['tasks' => [], 'total' => 0, 'page' => $page, 'limit' => $limit, 'has_more' => false];
    }
}

// 获取单个文生图任务详情
function getTextToImageTask($taskId, $userId = null)
{
    try {
        $db = Database::getInstance();

        $sql = "SELECT id, user_id, task_id, task_type, title, status, progress, 
                       input_data, output_data, created_at, updated_at, completed_at,
                       task_id as api_task_id, current_status
                FROM tasks 
                WHERE task_id = ? AND task_type IN ('text2img', 'text2img_batch')";
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

        // 获取图片URLs
        $imageUrls = [];
        if ($task['task_type'] === 'text2img_batch' && isset($outputData['images'])) {
            foreach ($outputData['images'] as $image) {
                if (isset($image['url'])) {
                    $imageUrls[] = $image['url'];
                }
            }
        } elseif (isset($outputData['imageUrl'])) {
            $imageUrls[] = $outputData['imageUrl'];
        }

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
            'style' => $inputData['style'] ?? 21,
            'picSize' => $inputData['picSize'] ?? '16:9',
            'count' => $inputData['count'] ?? 1,
            'image_urls' => $imageUrls,
            'image_url' => !empty($imageUrls) ? $imageUrls[0] : '',
            'created_at' => $task['created_at'],
            'updated_at' => $task['updated_at'],
            'completed_at' => $task['completed_at'],
            'input_data' => $inputData,
            'output_data' => $outputData
        ];
    } catch (Exception $e) {
        error_log("获取文生图任务详情失败: " . $e->getMessage());
        return null;
    }
}

// 处理文生图请求
function processTextToImageRequest($params)
{
    // 检查用户积分
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();

    if (!$userId) {
        return ['code' => 1, 'msg' => '用户未登录'];
    }

    $prompt = $params['prompt'] ?? '';
    $style = $params['style'] ?? 21;
    $picSize = $params['picSize'] ?? '16:9';
    $count = intval($params['count'] ?? 1);

    if (empty($prompt)) {
        return ['code' => 1, 'msg' => '请输入图片描述'];
    }

    if ($count < 1 || $count > 4) {
        $count = 1;
    }

    // 计算所需积分
    $requiredPoints = Config::IMAGE_GENERATION_COST * $count;

    // 检查积分是否足够
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        return ['code' => 1, 'msg' => '积分不足，生成图片需要' . $requiredPoints . '积分'];
    }

    // 创建文生图客户端
    $client = new TextToImageClient();

    // 生成任务ID
    $taskId = 'txt2img_' . date('YmdHis') . '_' . uniqid();

    // 保存任务记录到数据库
    $taskData = [
        'prompt' => $prompt,
        'style' => $style,
        'picSize' => $picSize,
        'count' => $count,
        'required_points' => $requiredPoints
    ];

    $taskDbId = saveTextToImageTaskToDb($taskId, $userId, $taskData);

    try {
        // 调用API生成图片
        if ($count === 1) {
            $result = $client->generateSingleImage($prompt, [
                'style' => $style,
                'picSize' => $picSize
            ]);
        } else {
            $result = $client->generateMultipleImages($prompt, $count, [
                'style' => $style,
                'picSize' => $picSize
            ]);
        }

        if (!$result['success']) {
            updateTextToImageTaskStatus($taskId, $userId, 'error', [
                'error' => 'API调用失败: ' . ($result['error'] ?? '未知错误')
            ]);
            return ['code' => 1, 'msg' => '图片生成失败: ' . ($result['error'] ?? '未知错误')];
        }

        $response = $result['response'];

        if ($response['code'] !== 0) {
            updateTextToImageTaskStatus($taskId, $userId, 'error', [
                'error' => 'API返回错误: ' . ($response['msg'] ?? '未知错误')
            ]);
            return ['code' => 1, 'msg' => $response['msg'] ?? '图片生成失败'];
        }

        $data = $response['data'] ?? [];

        // 扣除用户积分
        $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '文生图', 'text2img', $taskId);
        if (!$deductResult['success']) {
            updateTextToImageTaskStatus($taskId, $userId, 'error', [
                'error' => '积分扣除失败: ' . $deductResult['message']
            ]);
            return ['code' => 1, 'msg' => '积分扣除失败: ' . $deductResult['message']];
        }

        // 准备输出数据
        $outputData = [
            'task_id' => $taskId,
            'prompt' => $prompt,
            'style' => $style,
            'picSize' => $picSize,
            'count' => $count,
            'points_deducted' => true,
            'points_used' => $requiredPoints,
            'completed_at' => date('Y-m-d H:i:s')
        ];

        // 处理图片数据
        if ($count === 1 && isset($data['imageUrl'])) {
            $outputData['imageUrl'] = $data['imageUrl'];
        } elseif (isset($data['results']) && is_array($data['results'])) {
            $outputData['images'] = [];
            foreach ($data['results'] as $index => $item) {
                if (isset($item['imageUrl'])) {
                    $outputData['images'][] = [
                        'url' => $item['imageUrl'],
                        'index' => $index + 1
                    ];
                }
            }
        }

        // 更新任务记录为成功
        updateTextToImageTaskStatus($taskId, $userId, 'success', $outputData, 100);

        // 返回结果
        $returnData = array_merge($data, [
            'taskId' => $taskId,
            'progress' => 100,
            'status' => 'success',
            'canRetrieveLater' => true,
            'db_task_id' => $taskDbId
        ]);

        return [
            'code' => 0,
            'data' => $returnData,
            'msg' => '生成成功，已扣除' . $requiredPoints . '积分'
        ];
    } catch (Exception $e) {
        updateTextToImageTaskStatus($taskId, $userId, 'error', [
            'error' => '系统异常: ' . $e->getMessage()
        ]);
        return ['code' => 1, 'msg' => '系统异常: ' . $e->getMessage()];
    }
}

// 获取文生图任务状态
function getTextToImageTaskStatus($taskId)
{
    // 从数据库获取任务信息
    $task = getTextToImageTask($taskId);

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
                'prompt' => $task['prompt'],
                'style' => $task['style'],
                'picSize' => $task['picSize'],
                'count' => $task['count'],
                'imageUrls' => $task['image_urls'],
                'imageUrl' => $task['image_url'],
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

    // 任务还在处理中
    $progress = $task['progress'] < 90 ? $task['progress'] + 10 : $task['progress'];
    updateTextToImageTaskStatus($taskId, $task['user_id'], 'processing', null, $progress);

    // 返回处理中状态
    return [
        'code' => 0,
        'data' => [
            'taskId' => $task['task_id'],
            'status' => 'processing',
            'message' => '图片生成中，请稍后再试',
            'prompt' => $task['prompt'],
            'createdAt' => $task['created_at'],
            'progress' => $progress
        ],
        'msg' => '图片生成中'
    ];
}

// 处理AJAX请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $postData = json_decode(file_get_contents('php://input'), true);
    $action = $postData['action'] ?? '';

    switch ($action) {
        case 'generate':
            $params = [
                'prompt' => $postData['prompt'] ?? '',
                'style' => $postData['style'] ?? 21,
                'picSize' => $postData['picSize'] ?? '16:9',
                'count' => intval($postData['count'] ?? 1)
            ];
            $result = processTextToImageRequest($params);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'task_status':
            $taskId = $postData['taskId'] ?? '';
            if (empty($taskId)) {
                jsonResponse(1, null, '请提供Task ID');
            }
            $result = getTextToImageTaskStatus($taskId);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            exit;

        case 'task_history':
            $auth = new Auth();
            $userId = $auth->getCurrentUserId();
            if (!$userId) {
                jsonResponse(1, null, '用户未登录');
            }
            $page = max(1, intval($postData['page'] ?? 1));
            $limit = min(20, intval($postData['limit'] ?? 10));
            $result = getUserTextToImageTasks($userId, $limit, $page);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['code' => 0, 'data' => $result, 'msg' => '获取成功'], JSON_UNESCAPED_UNICODE);
            exit;
    }
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
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>智影工场 - 文生图</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
</head>

<body>
    <?php include 'header.html'; ?>
    <div class="function-bar">
        <div class="function-left">
            <div class="tab active">文生图</div>
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
                                文生图设置
                            </h2>
                        </div>
                        <div class="card-body">
                            <form id="generateForm">
                                <!-- 提示词输入 -->
                                <div class="form-group">
                                    <label class="form-label" for="prompt">
                                        <i class="fas fa-keyboard"></i>
                                        提示词描述
                                    </label>
                                    <textarea
                                        id="prompt"
                                        class="form-control"
                                        placeholder="请输入中文描述您想要的图片内容，例如：一只可爱的小猫在花园里玩耍，阳光明媚，背景有鲜花..."
                                        rows="4"></textarea>
                                </div>

                                <!-- 样式选择 -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-palette"></i>
                                        艺术风格
                                        <span class="current-selection" id="currentStyle">通用3.0</span>
                                    </label>
                                    <div class="presets-grid" id="stylePresets">
                                        <!-- 通过JS动态生成 -->
                                    </div>
                                    <input type="hidden" id="style" value="21">
                                </div>

                                <!-- 比例选择 -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-crop-alt"></i>
                                        图片比例
                                        <span class="current-selection" id="currentRatio">横屏 16:9</span>
                                    </label>
                                    <div class="presets-grid" id="ratioPresets">
                                        <!-- 通过JS动态生成 -->
                                    </div>
                                    <input type="hidden" id="picSize" value="16:9">
                                </div>

                                <!-- 数量选择 -->
                                <div class="form-group">
                                    <label class="form-label">
                                        <i class="fas fa-layer-group"></i>
                                        生成数量
                                        <span class="current-selection" id="currentCount">1张</span>
                                    </label>
                                    <div class="presets-grid" id="countPresets">
                                        <!-- 通过JS动态生成 -->
                                    </div>
                                    <input type="hidden" id="count" value="1">
                                </div>

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

                                <!-- 示例提示词 -->
                                <div class="form-group">
                                    <div class="examples-title">
                                        <i class="fas fa-lightbulb"></i>
                                        <h3>灵感示例</h3>
                                    </div>
                                    <div class="examples-grid">
                                        <button type="button" class="example-btn" data-prompt="一只可爱的熊猫在竹林中吃竹子，阳光透过竹林洒下斑驳光影">
                                            🐼 竹林熊猫
                                        </button>
                                        <button type="button" class="example-btn" data-prompt="未来赛博朋克城市夜景，霓虹灯闪烁，空中飞行器穿梭，雨夜街道反射灯光">
                                            🌃 未来城市
                                        </button>
                                        <button type="button" class="example-btn" data-prompt="古风美女在樱花树下抚琴，粉色花瓣飘落，远处有亭台楼阁">
                                            🌸 古风美女
                                        </button>
                                        <button type="button" class="example-btn" data-prompt="水彩风格的夏日海滩风景，椰子树，蓝色海浪，金色沙滩，远处帆船">
                                            🏖️ 夏日海滩
                                        </button>
                                    </div>
                                </div>

                                <!-- 积分消耗提示 -->
                                <div class="points-info">
                                    <i class="fas fa-coins"></i> 文生图每张消耗 <strong><?php echo Config::IMAGE_GENERATION_COST; ?> 积分</strong>
                                </div>

                                <!-- 生成按钮 -->
                                <button type="submit" class="btn btn-primary" id="generateBtn">
                                    <i class="fas fa-magic"></i>
                                    开始生成图片
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- 历史任务列表 -->
                    <?php
                    $auth = new Auth();
                    $userId = $auth->getCurrentUserId();
                    $userTasks = [];

                    if ($userId) {
                        $result = getUserTextToImageTasks($userId, 5, 1);
                        $userTasks = $result['tasks'] ?? [];
                    }
                    ?>

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
                                        <div class="history-item" onclick="retrieveTaskByTextId('<?php echo htmlspecialchars($task['task_id']); ?>')">
                                            <div class="history-item-header">
                                                <span class="history-task-id">任务: <?php echo htmlspecialchars(substr($task['task_id'], 0, 8)); ?>...</span>
                                                <span class="history-status status-<?php echo $task['status']; ?>">
                                                    <?php echo htmlspecialchars($task['status_text']); ?>
                                                </span>
                                            </div>
                                            <div class="history-prompt"><?php echo htmlspecialchars(mb_strimwidth($task['prompt'], 0, 50, '...')); ?></div>
                                            <div class="history-details">
                                                <span class="history-count"><?php echo $task['count']; ?>张</span>
                                                <span class="history-time"><?php echo htmlspecialchars($task['created_at']); ?></span>
                                            </div>
                                            <?php if ($task['progress'] > 0 && $task['progress'] < 100): ?>
                                                <div class="progress-bar">
                                                    <div class="progress-fill" style="width: <?php echo $task['progress']; ?>%"></div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="btn btn-secondary" onclick="loadMoreTextHistory()">
                                    <i class="fas fa-sync-alt"></i> 加载更多
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 右侧：结果展示 -->
                <div class="right-column">
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">
                                <i class="fas fa-image card-icon"></i>
                                生成结果
                            </h2>
                        </div>
                        <!-- 图片展示区域 -->
                        <div class="image-container" id="imageContainer" style="display: none;">
                            <!-- 通过JS动态生成图片内容 -->
                        </div>
                        <div class="card-body result-content">
                            <!-- 初始空状态 -->
                            <div class="image-container" id="initialState">
                                <div class="empty-state">
                                    <div class="empty-icon">
                                        <i class="fas fa-image"></i>
                                    </div>
                                    <h3>等待生成图片</h3>
                                    <p>设置好参数后，点击"开始生成图片"按钮</p>
                                    <p>生成的图片将在这里显示</p>
                                    <p class="small-text">您可以查询历史任务来获取之前生成的图片</p>
                                </div>
                            </div>



                            <!-- 结果信息 -->
                            <div class="result-info" id="resultInfo" style="display: none;">
                                <!-- 通过JS动态生成信息 -->
                            </div>

                            <!-- 处理中状态 -->
                            <div class="processing-state" id="processingState" style="display: none;">
                                <div class="processing-content">
                                    <div class="spinner"></div>
                                    <h3>图片生成中</h3>
                                    <p id="processingMessage">图片正在生成，请耐心等待...</p>
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
                <h3>正在生成图片中...</h3>
                <p>智影工场正在根据您的描述创作图片，这可能需要30-90秒</p>
                <p>请耐心等待，精彩即将呈现！</p>
            </div>
        </div>

        <!-- 底部 -->
        <footer class="app-footer">
            <div class="footer-content">
                <p>© 2025 智影工场 - AI文生图生成平台 | 让创意触手可及</p>
                <div class="footer-links">
                    <a href="#" class="footer-link">使用教程</a>
                    <a href="#" class="footer-link">风格说明</a>
                    <a href="#" class="footer-link">API文档</a>
                    <a href="#" class="footer-link">关于我们</a>
                    <a href="#" class="footer-link">隐私政策</a>
                </div>
            </div>
        </footer>
    </div>

    <!-- 图片模态框 -->
    <div class="image-modal-overlay" id="imageModal">
        <div class="image-modal">
            <button class="modal-close-btn" id="modalCloseBtn">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" class="modal-image" alt="放大查看">
            <a id="modalDownloadBtn" class="modal-download-btn" download>
                <i class="fas fa-download"></i>
                下载图片
            </a>
            <div class="modal-info">
                点击图片外部区域或关闭按钮可关闭
            </div>
        </div>
    </div>


    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script type="text/javascript" src="js/text2img.js"></script>
</body>

</html>
