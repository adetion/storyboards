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

$userId = $_SESSION['user_id'];

try {
    // 连接数据库
    $db = Database::getInstance();
    
    // 查询用户的API设置
    $sql = "SELECT * FROM api_keys WHERE user_id = ? LIMIT 1";
    $result = $db->query($sql, [$userId]);
    
    $response = [
        'success' => true,
        'text_analysis_api_url' => '',
        'text_analysis_api_key' => '',
        'text_analysis_api_model' => '',
        'text_to_image_api_url' => '',
        'text_to_image_api_key' => '',
        'text_to_image_api_model' => '',
        'image_to_video_api_url' => '',
        'image_to_video_api_key' => '',
        'image_to_video_api_model' => '',
        'img2text_api_url' => '',
        'img2text_api_key' => '',
        'img2text_api_model' => ''
    ];
    
    if (!empty($result)) {
        $apiData = $result[0];
        $response['text_analysis_api_url'] = $apiData['text2text_api_url'];
        $response['text_analysis_api_key'] = $apiData['text2text_api_key'];
        $response['text_analysis_api_model'] = $apiData['text2text_api_model'];
        $response['text_to_image_api_url'] = $apiData['text2img_api_url'];
        $response['text_to_image_api_key'] = $apiData['text2img_api_key'];
        $response['text_to_image_api_model'] = $apiData['text2img_api_model'];
        $response['image_to_video_api_url'] = $apiData['img2video_api_url'];
        $response['image_to_video_api_key'] = $apiData['img2video_api_key'];
        $response['image_to_video_api_model'] = $apiData['img2video_api_model'];
        $response['img2text_api_url'] = isset($apiData['img2text_api_url']) && $apiData['img2text_api_url'] !== null ? $apiData['img2text_api_url'] : '';
        $response['img2text_api_key'] = isset($apiData['img2text_api_key']) && $apiData['img2text_api_key'] !== null ? $apiData['img2text_api_key'] : '';
        $response['img2text_api_model'] = isset($apiData['img2text_api_model']) && $apiData['img2text_api_model'] !== null ? $apiData['img2text_api_model'] : '';
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => '获取API失败: ' . $e->getMessage()
    ]);
    exit;
}
?>
