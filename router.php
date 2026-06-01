<?php

/**
 * 路由脚本
 * 用于保护敏感文件，防止直接访问
 */

// 获取当前请求的URI
$uri = $_SERVER['REQUEST_URI'];
$request_file = basename($uri);

// 定义不允许直接访问的敏感文件扩展名
$sensitive_extensions = ['.db', '.log', '.json'];

// 定义不允许直接访问的敏感文件
$sensitive_files = ['data.db', 'data.bak.db'];

// 检查请求是否指向敏感文件
$is_sensitive = false;

// 检查文件扩展名
foreach ($sensitive_extensions as $ext) {
    if (substr($request_file, -strlen($ext)) === $ext) {
        $is_sensitive = true;
        break;
    }
}

// 检查文件名
if (in_array($request_file, $sensitive_files)) {
    $is_sensitive = true;
}

// 检查目录访问
if (
    substr($uri, 0, 6) === '/logs/' ||
    (substr($uri, 0, 9) === '/outputs/' && substr($request_file, -5) === '.json') ||
    (substr($uri, 0, 9) === '/results/' && substr($request_file, -5) === '.json')
) {
    $is_sensitive = true;
}

// 如果是敏感文件，返回403 Forbidden
if ($is_sensitive) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit(0);
}

// 检查是否是实际存在的PHP文件
// 从URI中提取文件路径，去掉查询参数部分
$file_path = __DIR__ . (strpos($uri, '?') !== false ? substr($uri, 0, strpos($uri, '?')) : $uri);
// 使用substr()替代str_ends_with()以兼容PHP 7.3
if (is_file($file_path) && substr($file_path, -4) === '.php') {
    // 执行PHP文件
    include $file_path;
    exit(0);
}

// 检查是否是实际存在的静态文件（排除敏感文件）
if (is_file($file_path) && !$is_sensitive) {
    // 让内置服务器处理静态文件
    return false;
}

// 检查是否是目录请求，尝试加载index.php或index.html
if (is_dir($file_path)) {
    if (is_file($file_path . '/index.php')) {
        include $file_path . '/index.php';
        exit(0);
    } elseif (is_file($file_path . '/index.html')) {
        include $file_path . '/index.html';
        exit(0);
    }
}

// 404 Not Found
header('HTTP/1.1 404 Not Found');
include __DIR__ . '/index.html';
exit(0);
