<?php
// 启动会话 - 必须在任何输出之前调用
session_start();

// 添加会话调试
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 初始会话: " . json_encode($_SESSION) . PHP_EOL, FILE_APPEND);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 引入配置和认证类
require_once 'config.php';
require_once 'Auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Get the JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate input
if (!$data) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit();
}

// 记录请求信息
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 请求数据: " . json_encode($data) . PHP_EOL, FILE_APPEND);
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 当前会话: " . json_encode($_SESSION) . PHP_EOL, FILE_APPEND);

// Forward request to the actual API
$apiUrl = Config::TEXT2IMG_API_URL();
$apiKey = Config::TEXT2IMG_API_KEY();

// 添加调试日志
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - API URL: {$apiUrl}" . PHP_EOL, FILE_APPEND);
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - API Key: {$apiKey}" . PHP_EOL, FILE_APPEND);

// 检查API URL和API密钥是否为空
if (empty($apiUrl)) {
    echo json_encode(['success' => false, 'error' => 'API URL 未配置']);
    exit();
}

if (empty($apiKey)) {
    echo json_encode(['success' => false, 'error' => 'API Key 未配置']);
    exit();
}

// 使用Config类获取的API密钥替换前端传递的密钥
$data['text2img_api_key'] = $apiKey;
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 最终请求数据: " . json_encode($data) . PHP_EOL, FILE_APPEND);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// 记录完整的 API 响应和 HTTP 状态码
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - HTTP 状态码: {$httpCode}" . PHP_EOL, FILE_APPEND);
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - API 响应: {$response}" . PHP_EOL, FILE_APPEND);
file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - Curl 错误: {$error}" . PHP_EOL, FILE_APPEND);

if ($error) {
    echo json_encode(['success' => false, 'error' => 'Curl error: ' . $error]);
    exit();
}

if ($httpCode >= 400) {
    // 返回完整的 API 响应，帮助调试
    $apiResponse = json_decode($response, true);
    if ($apiResponse) {
        echo json_encode(['success' => false, 'error' => 'API error with HTTP code: ' . $httpCode, 'api_response' => $apiResponse]);
    } else {
        echo json_encode(['success' => false, 'error' => 'API error with HTTP code: ' . $httpCode, 'raw_response' => $response]);
    }
    exit();
}

// 解析API响应，获取task_id
$auth = new Auth();
$userId = $auth->getCurrentUserId();
// error_log("text2img_proxy.php - 用户ID: {$userId}, taskId: {$taskId}");
if ($userId) {
    // 解析API响应
    $apiResponse = json_decode($response, true);
    $taskId = $apiResponse['data']['taskId'] ?? $apiResponse['data']['task_id'] ?? null;
    $prompt = $apiResponse['data']['prompt'] ?? $apiResponse['data']['prompt'] ?? null;
    $imageUrl = $apiResponse['data']['imageUrl'] ?? $apiResponse['data']['image_url'] ?? null;
    $progress = $apiResponse['data']['generateStatus'] ?? $apiResponse['data']['generateStatus'] ?? 0;

    // 计算所需积分
    $requiredPoints = Config::IMAGE_GENERATION_COST;

    // 检查积分是否足够
    if (!$auth->checkUserPoints($userId, $requiredPoints)) {
        echo json_encode([
            'success' => false,
            'error' => '积分不足，文生图每张需要消耗' . Config::IMAGE_GENERATION_COST . '积分'
        ]);
        exit;
    }

    try {
        // 创建数据库实例
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 下载图片到本地并生成新的URL
        $localImageUrl = $imageUrl;
        if ($imageUrl) {
            // 确保输出目录存在
            $outputDir = __DIR__ . '/outputs/images';
            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0755, true);
            }

            // 生成唯一的文件名
            $filename = 'img_' . uniqid() . '_' . time() . '.png';
            $localPath = $outputDir . '/' . $filename;

            // 下载图片
            $imgCh = curl_init($imageUrl);
            $fp = fopen($localPath, 'wb');
            curl_setopt($imgCh, CURLOPT_FILE, $fp);
            curl_setopt($imgCh, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($imgCh, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($imgCh, CURLOPT_TIMEOUT, 30);
            $imgResult = curl_exec($imgCh);
            curl_close($imgCh);
            fclose($fp);

            if ($imgResult) {
                // 生成新的URL
                $localImageUrl = 'https://files.yourdomain.com/images/' . $filename;
                file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 图片已下载到: {$localPath}" . PHP_EOL, FILE_APPEND);
                file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 新的图片URL: {$localImageUrl}" . PHP_EOL, FILE_APPEND);

                // 更新API响应中的图片URL
                if (isset($apiResponse['data']['imageUrl'])) {
                    $apiResponse['data']['imageUrl'] = $localImageUrl;
                }
                if (isset($apiResponse['data']['image_url'])) {
                    $apiResponse['data']['image_url'] = $localImageUrl;
                }
                if (isset($apiResponse['data']['allImages']) && is_array($apiResponse['data']['allImages']) && count($apiResponse['data']['allImages']) > 0) {
                    $apiResponse['data']['allImages'][0]['url'] = $localImageUrl;
                }

                // 更新响应字符串
                $response = json_encode($apiResponse);
            } else {
                file_put_contents(__DIR__ . '/logs/text2img_proxy.log', "[" . date('Y-m-d H:i:s') . "] text2img_proxy.php - 图片下载失败，使用原始URL" . PHP_EOL, FILE_APPEND);
            }
        }

        // 插入任务到数据库
        $sql = "INSERT INTO tasks (user_id, task_type, title, status, progress, input_data, output_data, created_at, updated_at, completed_at, task_id) 
               VALUES (:user_id, :task_type, :title, :status, :progress, :input_data, :output_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :task_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':task_type', 'storyboards_images', PDO::PARAM_STR);
        $stmt->bindValue(':title', '分镜图', PDO::PARAM_STR);
        $stmt->bindValue(':status', 2, PDO::PARAM_INT);
        $stmt->bindValue(':progress', 100, PDO::PARAM_INT);
        $stmt->bindValue(':input_data', $prompt, PDO::PARAM_STR);
        $stmt->bindValue(':output_data', $localImageUrl, PDO::PARAM_STR);
        $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        // error_log("插入任务到数据库失败: " . $e->getMessage());
    }

    // 扣除积分，传递task_id和完整的API响应内容
    $deductResult = $auth->deductUserPoints($userId, $requiredPoints, '文生图', 'text2img', $taskId, json_encode($apiResponse));
    if (!$deductResult['success']) {
        echo json_encode([
            'success' => false,
            'error' => $deductResult['message']
        ]);
        exit;
    }
}

// Return the API response
echo $response;
