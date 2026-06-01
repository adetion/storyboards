<?php
// 引入配置文件
require_once 'config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否已登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '用户未登录'
    ]);
    exit;
}

// 检查是否为POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '仅支持POST请求'
    ]);
    exit;
}

// 获取API参数
$userId = $_SESSION['user_id'];

// 获取API设置项
$text2textApiUrl = isset($_POST['text_analysis_api_url']) ? trim($_POST['text_analysis_api_url']) : '';
$text2textApiKey = isset($_POST['text_analysis_api_key']) ? trim($_POST['text_analysis_api_key']) : '';
$text2textApiModel = isset($_POST['text_analysis_api_model']) ? trim($_POST['text_analysis_api_model']) : '';

$text2imgApiUrl = isset($_POST['text_to_image_api_url']) ? trim($_POST['text_to_image_api_url']) : '';
$text2imgApiKey = isset($_POST['text_to_image_api_key']) ? trim($_POST['text_to_image_api_key']) : '';
$text2imgApiModel = isset($_POST['text_to_image_api_model']) ? trim($_POST['text_to_image_api_model']) : '';

$img2videoApiUrl = isset($_POST['image_to_video_api_url']) ? trim($_POST['image_to_video_api_url']) : '';
$img2videoApiKey = isset($_POST['image_to_video_api_key']) ? trim($_POST['image_to_video_api_key']) : '';
$img2videoApiModel = isset($_POST['image_to_video_api_model']) ? trim($_POST['image_to_video_api_model']) : '';

$img2textApiUrl = isset($_POST['img2text_api_url']) ? trim($_POST['img2text_api_url']) : '';
$img2textApiKey = isset($_POST['img2text_api_key']) ? trim($_POST['img2text_api_key']) : '';
$img2textApiModel = isset($_POST['img2text_api_model']) ? trim($_POST['img2text_api_model']) : '';

try {
    // 连接数据库
    $db = Database::getInstance();
    
    // 检查api_keys表中是否已存在该用户的记录
    $checkSql = "SELECT id FROM api_keys WHERE user_id = ? LIMIT 1";
    $checkResult = $db->query($checkSql, [$userId]);
    
    if (!empty($checkResult)) {
        // 已存在记录，更新所有API字段
        $updateSql = "UPDATE api_keys SET 
                      text2text_api_url = ?, 
                      text2text_api_key = ?, 
                      text2text_api_model = ?, 
                      text2img_api_url = ?, 
                      text2img_api_key = ?, 
                      text2img_api_model = ?, 
                      img2video_api_url = ?, 
                      img2video_api_key = ?, 
                      img2video_api_model = ?, 
                      img2text_api_url = ?, 
                      img2text_api_key = ?, 
                      img2text_api_model = ?, 
                      updated_at = NOW() 
                      WHERE user_id = ?";
        $db->execute($updateSql, [
            $text2textApiUrl,
            $text2textApiKey,
            $text2textApiModel,
            $text2imgApiUrl,
            $text2imgApiKey,
            $text2imgApiModel,
            $img2videoApiUrl,
            $img2videoApiKey,
            $img2videoApiModel,
            $img2textApiUrl,
            $img2textApiKey,
            $img2textApiModel,
            $userId
        ]);
    } else {
        // 不存在记录，创建新记录
        $insertSql = "INSERT INTO api_keys (user_id, 
                      text2text_api_url, text2text_api_key, text2text_api_model, 
                      text2img_api_url, text2img_api_key, text2img_api_model, 
                      img2video_api_url, img2video_api_key, img2video_api_model, 
                      img2text_api_url, img2text_api_key, img2text_api_model, 
                      created_at, updated_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $db->execute($insertSql, [
            $userId,
            $text2textApiUrl,
            $text2textApiKey,
            $text2textApiModel,
            $text2imgApiUrl,
            $text2imgApiKey,
            $text2imgApiModel,
            $img2videoApiUrl,
            $img2videoApiKey,
            $img2videoApiModel,
            $img2textApiUrl,
            $img2textApiKey,
            $img2textApiModel
        ]);
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'API保存成功'
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '保存API失败: ' . $e->getMessage()
    ]);
    exit;
}
?>
