<?php
// wx_pay_demo.php - 微信支付JSAPI示例
header('Content-Type: text/html; charset=utf-8');

// 引入配置文件
require_once 'config.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

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

// 生成随机字符串
function generateNonceStr($length = 32) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

// 生成签名
function makeSign($params, $key) {
    // 过滤参数
    $signParams = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $k === 'sign') {
            continue;
        }
        $signParams[$k] = (string)$v;
    }

    // 排序
    ksort($signParams);

    // 拼接
    $string = '';
    foreach ($signParams as $k => $v) {
        $string .= "{$k}={$v}&";
    }

    // 移除末尾的&，添加key
    $string = rtrim($string, '&') . '&key=' . $key;

    // 生成MD5签名并转为大写
    return strtoupper(md5($string));
}

// 微信授权获取openid
$openid = null;
$error_msg = '';

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
    } else {
        $error_msg = '获取openid失败: ' . json_encode($result, JSON_UNESCAPED_UNICODE);
        wxLog($error_msg, 'ERROR');
    }
} else {
    // 检查是否已获取到openid（通过session）
    session_start();
    if (isset($_SESSION['wx_openid'])) {
        $openid = $_SESSION['wx_openid'];
    }
}

// 保存openid到session
session_start();
if ($openid) {
    $_SESSION['wx_openid'] = $openid;
}

// 处理支付请求
$pay_result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $openid) {
    try {
        // 获取表单数据
        $amount = intval($_POST['amount'] ?? 0);
        $body = trim($_POST['body'] ?? '测试商品');
        
        // 验证金额
        if ($amount < Config::WX_MIN_AMOUNT || $amount > Config::WX_MAX_AMOUNT) {
            throw new Exception("金额需在" . (Config::WX_MIN_AMOUNT / 100) . "元到" . (Config::WX_MAX_AMOUNT / 100) . "元之间");
        }
        
        // 生成订单号
        $order_no = 'TEST' . date('YmdHis') . mt_rand(1000, 9999);
        
        // 构建微信支付参数
        $appid = Config::WX_APPID;
        $mch_id = Config::WX_MCH_ID;
        $nonce_str = generateNonceStr();
        $total_fee = $amount;
        $spbill_create_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $notify_url = Config::WX_NOTIFY_URL;
        $trade_type = 'JSAPI';
        $attach = 'test';
        
        $params = [
            'appid' => $appid,
            'mch_id' => $mch_id,
            'nonce_str' => $nonce_str,
            'body' => $body,
            'out_trade_no' => $order_no,
            'total_fee' => $total_fee,
            'spbill_create_ip' => $spbill_create_ip,
            'notify_url' => $notify_url,
            'trade_type' => $trade_type,
            'openid' => $openid,
            'attach' => $attach
        ];
        
        // 生成签名
        $sign = makeSign($params, Config::WX_KEY);
        $params['sign'] = $sign;
        
        // 构建XML
        $xml = '<xml>';
        foreach ($params as $k => $v) {
            $xml .= "<{$k}><![CDATA[{$v}]]></{$k}>";
        }
        $xml .= '</xml>';
        
        // 调用微信统一下单API
        $url = Config::WX_API_URL . '/pay/unifiedorder';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $responseXml = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("HTTP请求失败: {$error}");
        }
        
        // 解析微信返回
        $result = [];
        preg_match_all('/<(\w+)>(?:<!\[CDATA\[)?([^\]]+)(?:\]\]>)?<\/\1>/', $responseXml, $matches);
        if (isset($matches[1]) && isset($matches[2])) {
            for ($i = 0; $i < count($matches[1]); $i++) {
                $result[$matches[1][$i]] = $matches[2][$i];
            }
        }
        
        wxLog("微信返回: " . json_encode($result, JSON_UNESCAPED_UNICODE));
        
        // 检查响应结果
        if (empty($result) || $result['return_code'] !== 'SUCCESS') {
            throw new Exception($result['return_msg'] ?? '微信接口返回异常');
        }
        
        if ($result['result_code'] !== 'SUCCESS') {
            throw new Exception($result['err_code_des'] ?? $result['err_code'] ?? '支付失败');
        }
        
        // 生成前端支付参数
        $timeStamp = time();
        $nonceStr = generateNonceStr();
        $prepayId = $result['prepay_id'];
        
        $payParams = [
            'appId' => $appid,
            'timeStamp' => (string)$timeStamp,
            'nonceStr' => $nonceStr,
            'package' => "prepay_id={$prepayId}",
            'signType' => 'MD5',
        ];
        
        $payParams['paySign'] = makeSign($payParams, Config::WX_KEY);
        
        $pay_result = [
            'success' => true,
            'pay_params' => $payParams,
            'order_no' => $order_no
        ];
        
        wxLog("支付参数生成成功: " . json_encode($pay_result, JSON_UNESCAPED_UNICODE));
        
    } catch (Exception $e) {
        $pay_result = [
            'success' => false,
            'error' => $e->getMessage()
        ];
        wxLog("支付处理失败: " . $e->getMessage(), 'ERROR');
    }
}

// 生成微信授权链接
if (!$openid && !$error_msg) {
    $appid = Config::WX_APPID;
    $redirect_uri = urlencode('https://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF']);
    $auth_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appid}&redirect_uri={$redirect_uri}&response_type=code&scope=snsapi_base&state=123#wechat_redirect";
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>微信支付JSAPI示例</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 20px;
        }
        .container {
            max-width: 500px;
            margin: 0 auto;
            background-color: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            text-align: center;
            margin-bottom: 30px;
        }
        .error {
            color: red;
            padding: 10px;
            background-color: #ffebee;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .success {
            color: green;
            padding: 10px;
            background-color: #e8f5e8;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #555;
        }
        input[type="text"], input[type="number"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
            box-sizing: border-box;
        }
        input[type="submit"] {
            width: 100%;
            padding: 15px;
            background-color: #1aad19;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 18px;
            cursor: pointer;
        }
        input[type="submit"]:hover {
            background-color: #189016;
        }
        .auth-btn {
            display: block;
            width: 100%;
            padding: 15px;
            background-color: #1296db;
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 4px;
            font-size: 18px;
            margin-top: 20px;
        }
        .auth-btn:hover {
            background-color: #0e80c1;
        }
        .info {
            background-color: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid #2196f3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>微信支付JSAPI示例</h1>
        
        <?php if ($error_msg): ?>
            <div class="error">
                <strong>错误：</strong><?php echo $error_msg; ?>
            </div>
        <?php elseif (!$openid): ?>
            <!-- 微信授权按钮 -->
            <div class="info">
                <strong>提示：</strong>请先通过微信授权获取您的openid
            </div>
            <a href="<?php echo $auth_url; ?>" class="auth-btn">微信授权登录</a>
        <?php else: ?>
            <!-- 支付表单 -->
            <div class="info">
                <strong>已获取到openid：</strong><?php echo $openid; ?>
            </div>
            
            <form method="post">
                <div class="form-group">
                    <label for="body">商品名称：</label>
                    <input type="text" id="body" name="body" value="测试商品" required>
                </div>
                
                <div class="form-group">
                    <label for="amount">支付金额（分）：</label>
                    <input type="number" id="amount" name="amount" value="100" min="100" max="5000000" required>
                </div>
                
                <input type="submit" value="发起微信支付">
            </form>
            
            <!-- 支付结果和JSAPI调用 -->
            <?php if ($pay_result): ?>
                <?php if ($pay_result['success']): ?>
                    <div class="success">
                        <strong>支付参数生成成功！</strong>点击下方按钮发起支付
                    </div>
                    
                    <!-- 微信支付JSAPI -->
                    <script type="text/javascript">
                        // 微信JSAPI支付
                        function onBridgeReady() {
                            WeixinJSBridge.invoke(
                                'getBrandWCPayRequest',
                                <?php echo json_encode($pay_result['pay_params'], JSON_UNESCAPED_UNICODE); ?>,
                                function(res) {
                                    if (res.err_msg == "get_brand_wcpay_request:ok") {
                                        alert("支付成功！订单号：<?php echo $pay_result['order_no']; ?>");
                                        window.location.href = window.location.href;
                                    } else if (res.err_msg == "get_brand_wcpay_request:cancel") {
                                        alert("支付已取消");
                                    } else {
                                        alert("支付失败：" + res.err_msg);
                                    }
                                }
                            );
                        }
                        
                        // 检查微信环境
                        if (typeof WeixinJSBridge == "undefined") {
                            if (document.addEventListener) {
                                document.addEventListener('WeixinJSBridgeReady', onBridgeReady, false);
                            } else if (document.attachEvent) {
                                document.attachEvent('WeixinJSBridgeReady', onBridgeReady);
                                document.attachEvent('onWeixinJSBridgeReady', onBridgeReady);
                            }
                        } else {
                            onBridgeReady();
                        }
                    </script>
                <?php else: ?>
                    <div class="error">
                        <strong>支付参数生成失败：</strong><?php echo $pay_result['error']; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
