<?php
require_once 'config.php';
require_once 'Logger.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

class StatusResponse {
    public static function send($data) {
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    $status = [
        'service' => 'Novel to Script Converter',
        'version' => '1.0.0',
        'status' => 'running',
        'timestamp' => time(),
        'server_time' => date('Y-m-d H:i:s'),
        'directories' => [],
        'system' => [
            'php_version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time')
        ]
    ];
    
    // 检查目录状态
    $directories = [
        'uploads' => Config::UPLOAD_DIR,
        'outputs' => Config::OUTPUT_DIR,
        'logs' => Config::LOG_DIR,
        'cache' => Config::CACHE_DIR
    ];
    
    foreach ($directories as $name => $path) {
        $status['directories'][$name] = [
            'path' => $path,
            'exists' => is_dir($path),
            'writable' => is_writable($path),
            'file_count' => count(glob($path . '/*'))
        ];
    }
    
    // 检查API密钥
    $apiKeyStatus = !empty(Config::DEEPSEEK_API_KEY()) && Config::DEEPSEEK_API_KEY() !== 'your_api_key_here';
    $status['api'] = [
        'configured' => $apiKeyStatus,
        'url' => Config::DEEPSEEK_API_URL()
    ];
    
    // 检查最近日志
    $logFile = Config::LOG_DIR . 'script_converter_' . date('Y-m-d') . '.log';
    if (file_exists($logFile)) {
        $logs = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $status['recent_logs'] = array_slice($logs, -5); // 最后5条日志
    }
    
    StatusResponse::send([
        'success' => true,
        'data' => $status
    ]);
    
} catch (Exception $e) {
    StatusResponse::send([
        'success' => false,
        'message' => '状态检查失败: ' . $e->getMessage()
    ]);
}
?>