<?php
// auth_guard.php - 页面访问验证脚本

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到登录页面
    header('Location: index.html');
    exit(0);
}
?>
