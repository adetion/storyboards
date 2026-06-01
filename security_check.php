<?php
/**
 * 安全检查脚本，防止直接访问敏感文件
 * 该脚本会在所有PHP脚本执行前自动运行
 */

// 获取当前请求的文件路径
$request_uri = $_SERVER['REQUEST_URI'];
$request_file = basename($request_uri);

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

// 如果是敏感文件，返回403 Forbidden
if ($is_sensitive) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit(0);
}

// 检查是否直接访问了logs目录
if (substr($request_uri, 0, 6) === '/logs/') {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit(0);
}

// 检查是否直接访问了outputs目录下的JSON文件
if (substr($request_uri, 0, 9) === '/outputs/' && substr($request_file, -5) === '.json') {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit(0);
}

// 检查是否直接访问了results目录下的JSON文件
if (substr($request_uri, 0, 9) === '/results/' && substr($request_file, -5) === '.json') {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>403 Forbidden</h1>';
    exit(0);
}

// 继续执行原始脚本
return true;
?>
