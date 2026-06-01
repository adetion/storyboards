<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// 引入配置文件
require_once __DIR__ . '/config.php';

try {
    // 获取视频生成相关的配置参数
    $config = [
        'api_url' => Config::VIDEO_GENERATION_API_URL(),
        'api_key' => Config::VIDEO_GENERATION_API_KEY(),
        'api_mode' => 'ark'
    ];
    
    // 返回配置参数
    echo json_encode([
        'code' => 0,
        'msg' => 'Success',
        'timestamp' => time(),
        'data' => $config
    ]);
} catch (Exception $e) {
    echo json_encode([
        'code' => 500,
        'msg' => '服务器错误: ' . $e->getMessage(),
        'timestamp' => time()
    ]);
}
?>