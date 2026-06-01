<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'code' => 405,
        'msg' => 'Method Not Allowed',
        'timestamp' => time()
    ]);
    exit();
}

// 获取请求数据
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// 检查必要的参数
if (!isset($data['firstFrame']) || !isset($data['lastFrame']) || !isset($data['prompt'])) {
    echo json_encode([
        'code' => 400,
        'msg' => '缺少必要的参数',
        'timestamp' => time()
    ]);
    exit();
}

// 引入配置文件
require_once __DIR__ . '/config.php';

try {
    // 获取视频生成相关的配置参数
    $apiUrl = Config::VIDEO_GENERATION_API_URL();
    $apiKey = Config::VIDEO_GENERATION_API_KEY();

    // 检查配置参数是否完整
    if (!($apiUrl && $apiKey)) {
        throw new Error('视频生成API配置不完整');
    }

    // 构建请求数据
    $requestData = [
        "model" => "doubao-seedance-1-5-pro-251215",
        "content" => [
            [
                "type" => "text",
                "text" => $data['prompt']
            ],
            [
                "type" => "image_url",
                "image_url" => [
                    "url" => $data['firstFrame']
                ],
                "role" => "first_frame"
            ],
            [
                "type" => "image_url",
                "image_url" => [
                    "url" => $data['lastFrame']
                ],
                "role" => "last_frame"
            ]
        ],
        "generate_audio" => true,
        "ratio" => "adaptive",
        "duration" => isset($data['duration']) ? $data['duration'] : 8,
        "watermark" => false
    ];

    // 调用图生视频API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

    $response = curl_exec($ch);

    if (curl_errno($ch)) {
        throw new Error('API请求失败: ' . curl_error($ch));
    }

    curl_close($ch);

    // 解析API响应
    $apiResponse = json_decode($response, true);

    // 这里需要根据API的实际响应格式来处理
    // 暂时使用一个模拟的视频URL
    $apiVideoUrl = 'https://files.wop.cc/videos/video-' . time() . '.mp4';

    // 确保outputs/videos目录存在
    $videosDir = __DIR__ . '/outputs/videos';
    if (!is_dir($videosDir)) {
        mkdir($videosDir, 0755, true);
    }

    // 生成视频文件名
    $videoFileName = 'video-' . time() . '.mp4';
    $localVideoPath = $videosDir . '/' . $videoFileName;

    // 下载视频到本地服务器
    // 注意：这里只是模拟下载，实际项目中需要根据API的响应获取真实的视频URL并下载
    // 暂时创建一个空文件作为占位符
    file_put_contents($localVideoPath, '');

    // 构建视频URL
    $videoUrl = 'https://files.yourdomain.com/videos/' . $videoFileName;

    // 将视频URL存入shots表的videoCutUrl字段
    if (isset($data['shotId'])) {
        $db = Database::getInstance();
        $sql = "UPDATE shots SET videoCutUrl = :videoUrl WHERE shots_id = :shotId";
        $db->query($sql, [':videoUrl' => $videoUrl, ':shotId' => $data['shotId']]);
    }

    // 返回结果
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => [
            'videoUrl' => $videoUrl
        ]
    ]);
} catch (Error $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '视频生成失败: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '服务器错误: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}
