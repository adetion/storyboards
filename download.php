<?php
require_once 'config.php';

// 设置基本的Content-Type
header('Content-Type: text/plain; charset=utf-8');

// 简单的安全检查
function sanitizeFilename($filename) {
    // 只允许字母、数字、下划线、破折号和点号
    $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $filename);
    // 防止路径遍历
    $filename = basename($filename);
    return $filename;
}

try {
    $filename = $_GET['file'] ?? '';
    if (empty($filename)) {
        throw new Exception('文件参数不能为空');
    }
    
    // 安全过滤文件名
    $filename = sanitizeFilename($filename);
    $filepath = Config::OUTPUT_DIR . $filename;
    
    if (!file_exists($filepath)) {
        throw new Exception('文件不存在: ' . $filename);
    }
    
    // 检查文件是否在输出目录内（防止路径遍历）
    $realFilePath = realpath($filepath);
    $realOutputDir = realpath(Config::OUTPUT_DIR);
    
    if (strpos($realFilePath, $realOutputDir) !== 0) {
        throw new Exception('非法文件访问');
    }
    
    // 获取文件信息
    $fileSize = filesize($filepath);
    $fileContent = file_get_contents($filepath);
    
    if ($fileContent === false) {
        throw new Exception('文件读取失败');
    }
    
    // 设置下载头
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $fileSize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // 输出文件内容
    echo $fileContent;
    exit;
    
} catch (Exception $e) {
    // 记录错误日志
    $logger = new Logger();
    $logger->error("下载失败: " . $e->getMessage());
    
    // 返回JSON错误信息
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}
?>