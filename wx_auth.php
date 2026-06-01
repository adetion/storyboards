<?php
// wx_auth.php - 微信网页授权处理

// 开启会话
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once 'config.php';

// 生成授权链接的函数
function getOAuthUrl($redirect_uri, $scope = 'snsapi_base', $state = 'STATE') {
    $appId = Config::WX_APPID;
    // $redirect_uri 必须是网页授权域名下的URL，且需要URL编码
    $redirect_uri = urlencode($redirect_uri);
    
    // snsapi_base: 静默授权，不弹出授权页面，仅获取openid
    // snsapi_userinfo: 弹出授权页面，可获取用户头像、昵称等信息
    $url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appId}&redirect_uri={$redirect_uri}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
    
    return $url;
}

// 根据code获取openid的函数
function getOpenIdByCode($code) {
    $appId = Config::WX_APPID;
    $appSecret = Config::WX_APPSECRET;
    
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret={$appSecret}&code={$code}&grant_type=authorization_code";
    
    $result = json_decode(file_get_contents($url), true);
    
    if (isset($result['openid'])) {
        return $result['openid']; // 成功获取到openid
    } else {
        // 错误处理，记录日志
        // error_log('获取openid失败: ' . json_encode($result));
        return null;
    }
}

// 微信授权处理主函数
function handleWechatAuth() {
    // 如果会话中已有openid，直接返回
    if (isset($_SESSION['user_openid'])) {
        return $_SESSION['user_openid'];
    }
    
    // 如果有code，直接获取openid
    if (isset($_GET['code'])) {
        $code = $_GET['code'];
        $openid = getOpenIdByCode($code);
        
        if ($openid) {
            // 将openid存入session，避免同一页面重复获取
            $_SESSION['user_openid'] = $openid;
            return $openid;
        } else {
            die('获取用户信息失败，请稍后重试。');
        }
    }
    
    return null;
}

// 微信授权检查函数
function checkWechatAuth() {
    $openid = handleWechatAuth();
    
    // 如果没有openid，跳转到微信授权页面
    if (!$openid) {
        $currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        $authUrl = getOAuthUrl($currentUrl, 'snsapi_base');
        header('Location: ' . $authUrl);
        exit();
    }
    
    return $openid;
}

// 微信授权跳转函数
function redirectToWechatAuth() {
    $currentUrl = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $authUrl = getOAuthUrl($currentUrl, 'snsapi_base');
    header('Location: ' . $authUrl);
    exit();
}
