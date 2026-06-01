<?php
/**
 * 微信授权回调中转页面
 * 无需登录即可访问，用于处理微信授权获取openid
 */
header('Content-Type: text/html; charset=utf-8');

// 引入配置文件
require_once 'config.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 日志函数
function wxLog($message, $level = 'INFO') {
    if (!Config::WX_LOG_ENABLED) return;

    $logDir = dirname(Config::WX_LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] [{$level}] {$message}\n";
    file_put_contents(Config::WX_LOG_PATH, $log, FILE_APPEND);
}

// 初始化变量
$openid = null;
$error_msg = '';
$redirect_url = 'usercenter.php';
$state_data = [];

// 检查是否有code参数
if (isset($_GET['code'])) {
    // 从微信授权回调获取openid
    $code = $_GET['code'];
    $appid = Config::WX_APPID;
    $secret = Config::WX_APPSECRET;
    
    // 获取openid
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appid}&secret={$secret}&code={$code}&grant_type=authorization_code";
    $result = json_decode(file_get_contents($url), true);
    
    if (isset($result['openid'])) {
        $openid = $result['openid'];
        wxLog("成功获取openid: {$openid}");
        
        // 保存openid到session
        $_SESSION['wx_openid'] = $openid;
        
        // 如果用户已登录，更新数据库中的openid
        if (isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true) {
            try {
                require_once 'Auth.php';
                $auth = new Auth();
                $auth->updateUserOpenid($_SESSION['user_id'], $openid);
                wxLog("成功更新用户openid到数据库，用户ID: {$_SESSION['user_id']}");
            } catch (Exception $e) {
                wxLog('更新用户openid失败: ' . $e->getMessage(), 'ERROR');
            }
        }
        
        // 解析state参数，获取原始请求信息
        if (isset($_GET['state'])) {
            $state = $_GET['state'];
            $state_parts = explode('_', $state);
            
            if (count($state_parts) > 0) {
                $state_data['type'] = $state_parts[0];
                if (count($state_parts) > 1) {
                    $state_data['order_no'] = $state_parts[1];
                }
                if (count($state_parts) > 2) {
                    $state_data['amount'] = $state_parts[2];
                }
                if (count($state_parts) > 3) {
                    $state_data['extra'] = $state_parts[3];
                }
            }
        }
        
        // 根据授权类型设置跳转URL
        if (isset($state_data['type'])) {
            switch ($state_data['type']) {
                case 'recharge':
                    $redirect_url = "usercenter.php?auth_type=recharge&order_no={$state_data['order_no']}&amount={$state_data['amount']}&extra={$state_data['extra']}";
                    break;
                case 'vip':
                    $redirect_url = "usercenter.php?auth_type=vip&order_no={$state_data['order_no']}&amount={$state_data['amount']}&extra={$state_data['extra']}";
                    break;
                default:
                    $redirect_url = "usercenter.php?auth_type={$state_data['type']}";
                    break;
            }
        }
        
    } else {
        $error_msg = '获取openid失败: ' . json_encode($result, JSON_UNESCAPED_UNICODE);
        wxLog($error_msg, 'ERROR');
    }
} else {
    $error_msg = '缺少code参数，未获取到微信授权';
    wxLog($error_msg, 'ERROR');
}

// 跳转回原页面
if (empty($error_msg)) {
    wxLog("微信授权成功，跳转回: {$redirect_url}");
    header("Location: {$redirect_url}");
    exit(0);
} else {
    // 显示错误信息
    echo '<html>
<head>
    <meta charset="UTF-8">
    <title>微信授权失败</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            padding: 50px;
            background-color: #f5f7fa;
        }
        .error-container {
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            max-width: 500px;
            margin: 0 auto;
        }
        .error-title {
            color: #ef4444;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .error-message {
            color: #64748b;
            margin-bottom: 30px;
            line-height: 1.5;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background-color: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background-color: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <h1 class="error-title">微信授权失败</h1>
        <p class="error-message">' . $error_msg . '</p>
        <a href="usercenter.php" class="btn">返回用户中心</a>
    </div>
</body>
</html>';
    exit(0);
}
