<?php
/**
 * 获取当前登录用户的openid
 */

// 使用绝对路径包含文件
define('ROOT_PATH', dirname(__DIR__));
require_once ROOT_PATH . '/Auth.php';

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

$auth = new Auth();
$currentUser = $auth->getCurrentUser();

if (!$currentUser) {
    echo json_encode([
        'code' => 0,
        'msg' => '用户未登录',
        'openid' => null
    ]);
    exit(0);
}

// 从数据库获取用户的openid
$userId = $currentUser['id'];
require_once ROOT_PATH . '/Database.php';
$db = Database::getInstance();
$sql = "SELECT openid FROM users WHERE id = ?";
$user = $db->queryOne($sql, [$userId]);

// 返回openid，开发环境下如果没有openid则返回默认值
$openid = $user['openid'] ?? 'o2Nt65B1-xxx'; // 开发环境默认值

echo json_encode([
    'code' => 1,
    'msg' => '获取成功',
    'openid' => $openid
]);
