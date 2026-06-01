<?php

/**
 * 独立PHP支付系统 - 微信支付V3 API
 * 使用 Database.php 连接数据库
 */

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');
header('Access-Control-Max-Age: 3600');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => 1, 'msg' => 'OK']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/Database.php';

class PaymentConfig
{
    const WX_APPID = '';
    const WX_APPSECRET = '';
    const WX_MCH_ID = '';
    const WX_SERIAL_NO = '';
    const WX_PRIVATE_KEY_PATH = __DIR__ . '/pay_cert/apiclient_key.pem';
    const WX_PLATFORM_CERT_PATH = __DIR__ . '/pay_cert/platform_cert.pem';
    const WX_NOTIFY_URL = 'https://yourdomain.com/notify.php';

    const WX_AUTH_SCOPE = 'snsapi_base';

    const LOG_ENABLED = true;
    const LOG_PATH = __DIR__ . '/logs/pay_standalone.log';

    const DEBUG_MODE = false;

    const MIN_AMOUNT = 100;
    const MAX_AMOUNT = 5000000;

    const PRICES = [
        'vip_monthly_basic' => 9.9,
        'vip_yearly_basic' => 99,
        'vip_monthly_premium' => 299,
        'vip_yearly_premium' => 2999
    ];
}

function logMessage($message, $level = 'INFO')
{
    if (!PaymentConfig::LOG_ENABLED) return;

    $logDir = dirname(PaymentConfig::LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] [{$level}] {$message}\n";
    file_put_contents(PaymentConfig::LOG_PATH, $log, FILE_APPEND);
}

function isWeChatBrowser()
{
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return strpos($userAgent, 'MicroMessenger') !== false;
}

function getWxAuthUrl($redirectUri = '')
{
    if (empty($redirectUri)) {
        $redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    }
    $redirectUri = urlencode($redirectUri);
    $scope = PaymentConfig::WX_AUTH_SCOPE;

    return "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . PaymentConfig::WX_APPID .
        "&redirect_uri={$redirectUri}&response_type=code&scope={$scope}&state=STATE#wechat_redirect";
}

function getWxAuthUrlWithParams($requestId)
{
    $redirectUri = 'https://' . $_SERVER['HTTP_HOST'] . '/pay_standalone.php?action=wx_auth_callback&request_id=' . $requestId;
    $redirectUri = urlencode($redirectUri);
    $scope = PaymentConfig::WX_AUTH_SCOPE;

    return "https://open.weixin.qq.com/connect/oauth2/authorize?appid=" . PaymentConfig::WX_APPID .
        "&redirect_uri={$redirectUri}&response_type=code&scope={$scope}&state=STATE#wechat_redirect";
}

function getOpenIdByCode($code)
{
    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=" . PaymentConfig::WX_APPID .
        "&secret=" . PaymentConfig::WX_APPSECRET .
        "&code={$code}&grant_type=authorization_code";

    $response = file_get_contents($url);
    $data = json_decode($response, true);

    if (isset($data['openid'])) {
        return $data['openid'];
    }

    logMessage("获取OpenID失败: " . $response, 'ERROR');
    return false;
}

function createNonceStr($length = 32)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

function createOrderNo()
{
    return date('YmdHis') . mt_rand(100000, 999999);
}

/**
 * V3 API 签名 - 生成 Authorization 头
 * 签名串: method\nurl\ntimestamp\nnonce_str\nbody\n
 */
function makeV3Authorization($method, $url, $body)
{
    $timestamp = (string)time();
    $nonceStr = createNonceStr();

    $message = $method . "\n" . $url . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $body . "\n";

    logMessage("V3签名串: " . json_encode($message));

    $privateKey = openssl_get_privatekey(file_get_contents(PaymentConfig::WX_PRIVATE_KEY_PATH));
    if (!$privateKey) {
        logMessage("无法加载商户私钥: " . PaymentConfig::WX_PRIVATE_KEY_PATH, 'ERROR');
        return false;
    }

    $signature = '';
    $result = openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption');
    openssl_free_key($privateKey);

    if (!$result) {
        logMessage("签名失败", 'ERROR');
        return false;
    }

    $signature = base64_encode($signature);

    $authorization = 'WECHATPAY2-SHA256-RSA2048 mchid="' . PaymentConfig::WX_MCH_ID
        . '",nonce_str="' . $nonceStr
        . '",signature="' . $signature
        . '",timestamp="' . $timestamp
        . '",serial_no="' . PaymentConfig::WX_SERIAL_NO . '"';

    logMessage("V3 Authorization: {$authorization}");

    return [
        'Authorization' => $authorization,
        'timestamp' => $timestamp,
        'nonce_str' => $nonceStr
    ];
}

/**
 * V3 JSAPI 调起支付签名
 * 签名串: appId\ntimeStamp\nnonceStr\nprepay_id=xxx\n
 */
function makeV3JsapiSign($appId, $timestamp, $nonceStr, $prepayId)
{
    $message = $appId . "\n" . $timestamp . "\n" . $nonceStr . "\n" . "prepay_id=" . $prepayId . "\n";

    logMessage("JSAPI签名串: " . json_encode($message));

    $privateKey = openssl_get_privatekey(file_get_contents(PaymentConfig::WX_PRIVATE_KEY_PATH));
    if (!$privateKey) {
        logMessage("无法加载商户私钥用于JSAPI签名", 'ERROR');
        return false;
    }

    $signature = '';
    openssl_sign($message, $signature, $privateKey, 'sha256WithRSAEncryption');
    openssl_free_key($privateKey);

    $signature = base64_encode($signature);
    logMessage("JSAPI签名: {$signature}");

    return $signature;
}

function saveConsumptionRecord($userId, $amount, $orderNo, $itemType, $description)
{
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $sql = "INSERT INTO consumption_records (user_id, amount, order_no, item_type, description, created_at)
                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $amount, $orderNo, $itemType, $description]);

        return true;
    } catch (Exception $e) {
        logMessage("保存消费记录失败: {$e->getMessage()}", 'ERROR');
        return false;
    }
}

function createOrder($data)
{
    logMessage("===== createOrder (V3) 请求开始 =====");
    logMessage("接收到的数据: " . json_encode($data));

    if (!isset($data['amount'])) {
        return ['code' => 0, 'msg' => '金额参数不存在'];
    }

    $amount = $data['amount'];

    if (!is_numeric($amount)) {
        return ['code' => 0, 'msg' => '金额参数无效，必须是数字'];
    }

    $amount = (int)$amount;

    if ($amount < PaymentConfig::MIN_AMOUNT || $amount > PaymentConfig::MAX_AMOUNT) {
        return ['code' => 0, 'msg' => '金额超出限制'];
    }

    $body = isset($data['body']) ? $data['body'] : '智影工场会员服务';
    $attach = isset($data['attach']) ? $data['attach'] : 'membership';
    $openid = isset($data['openid']) ? $data['openid'] : '';

    if (!isWeChatBrowser() && empty($openid)) {
        return ['code' => 0, 'msg' => '微信支付需要提供openid'];
    }

    if (isWeChatBrowser() && empty($openid)) {
        $requestId = createNonceStr(16);
        $_SESSION['payment_params_' . $requestId] = [
            'amount' => $amount,
            'body' => $body,
            'attach' => $attach
        ];
        $authUrl = getWxAuthUrlWithParams($requestId);
        logMessage("微信环境缺少openid，需要授权: {$authUrl}");
        return ['code' => 0, 'msg' => '需要微信授权', 'need_auth' => true, 'auth_url' => $authUrl];
    }

    $orderNo = createOrderNo();
    $totalFee = $amount;
    $yuanAmount = $amount / 100;

    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $sql = "SELECT id FROM users WHERE openid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$openid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            logMessage("用户不存在，openid: {$openid}", 'ERROR');
            return ['code' => 0, 'msg' => '用户不存在'];
        }

        $userId = $user['id'];
        $description = "购买会员服务 {$yuanAmount} 元";

        saveConsumptionRecord($userId, $yuanAmount, $orderNo, 'membership', $description);

        // V3 JSAPI 下单请求体
        $params = [
            'appid' => PaymentConfig::WX_APPID,
            'mchid' => PaymentConfig::WX_MCH_ID,
            'description' => $body,
            'out_trade_no' => $orderNo,
            'notify_url' => PaymentConfig::WX_NOTIFY_URL,
            'amount' => [
                'total' => $totalFee,
                'currency' => 'CNY'
            ],
            'payer' => [
                'openid' => $openid
            ],
            'attach' => $attach
        ];

        $jsonBody = json_encode($params, JSON_UNESCAPED_UNICODE);
        logMessage("V3请求JSON: {$jsonBody}");

        // 生成 V3 Authorization 签名
        $authHeaders = makeV3Authorization('POST', '/v3/pay/transactions/jsapi', $jsonBody);
        if (!$authHeaders) {
            return ['code' => 0, 'msg' => '签名生成失败'];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com/v3/pay/transactions/jsapi');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: Mozilla/5.0',
            'Authorization: ' . $authHeaders['Authorization']
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        logMessage("V3 HTTP状态码: {$httpCode}");
        logMessage("V3 CURL错误: {$curlError}");
        logMessage("V3 返回数据: {$response}");

        if ($curlError) {
            return ['code' => 0, 'msg' => '网络请求失败: ' . $curlError];
        }

        $result = json_decode($response, true);

        if ($httpCode !== 200) {
            $errorMsg = $result['message'] ?? '未知错误';
            $errorCode = $result['code'] ?? 'UNKNOWN';
            logMessage("V3下单失败 [{$errorCode}]: {$errorMsg}", 'ERROR');
            return ['code' => 0, 'msg' => "下单失败: {$errorMsg}"];
        }

        $prepayId = $result['prepay_id'];

        // 生成 JSAPI 调起支付参数
        $timeStamp = (string)time();
        $nonceStr = createNonceStr();
        $package = 'prepay_id=' . $prepayId;

        $paySign = makeV3JsapiSign(PaymentConfig::WX_APPID, $timeStamp, $nonceStr, $prepayId);
        if (!$paySign) {
            return ['code' => 0, 'msg' => 'JSAPI签名生成失败'];
        }

        logMessage("V3订单创建成功: {$orderNo}, 金额: {$yuanAmount}元, prepay_id: {$prepayId}");

        return [
            'code' => 1,
            'msg' => '订单创建成功',
            'order_no' => $orderNo,
            'amount' => $yuanAmount,
            'prepay_id' => $prepayId,
            'wx_params' => [
                'appId' => PaymentConfig::WX_APPID,
                'timeStamp' => $timeStamp,
                'nonceStr' => $nonceStr,
                'package' => $package,
                'signType' => 'RSA',
                'paySign' => $paySign
            ]
        ];
    } catch (Exception $e) {
        logMessage("创建订单异常: {$e->getMessage()}", 'ERROR');
        return ['code' => 0, 'msg' => '创建订单失败: ' . $e->getMessage()];
    }
}

function queryOrder($data)
{
    if (!isset($data['order_no'])) {
        return ['code' => 0, 'msg' => '缺少订单号参数'];
    }

    $orderNo = $data['order_no'];
    $url = '/v3/pay/transactions/out-trade-no/' . $orderNo . '?mchid=' . PaymentConfig::WX_MCH_ID;

    $authHeaders = makeV3Authorization('GET', $url, '');
    if (!$authHeaders) {
        return ['code' => 0, 'msg' => '签名生成失败'];
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0',
        'Authorization: ' . $authHeaders['Authorization']
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("V3查询订单 HTTP:{$httpCode} 返回:{$response}");

    $result = json_decode($response, true);

    if ($httpCode !== 200) {
        return ['code' => 0, 'msg' => '查询失败: ' . ($result['message'] ?? '未知错误')];
    }

    // 映射 V3 trade_state 到前端期望的状态
    $tradeState = $result['trade_state'] ?? 'UNKNOWN';
    $statusMap = [
        'SUCCESS'   => 'paid',
        'NOTPAY'    => 'pending',
        'USERPAYING' => 'pending',
        'CLOSED'    => 'cancelled',
        'REVOKED'   => 'cancelled',
        'REFUND'    => 'paid',
        'PAYERROR'  => 'failed'
    ];
    $status = $statusMap[$tradeState] ?? 'pending';

    return [
        'code' => 1,
        'success' => ($tradeState === 'SUCCESS'),
        'msg' => '查询成功',
        'data' => [
            'status' => $status,
            'trade_state' => $tradeState,
            'trade_state_desc' => $result['trade_state_desc'] ?? '',
            'total_fee' => $result['amount']['total'] ?? 0,
            'transaction_id' => $result['transaction_id'] ?? ''
        ],
        'trade_state' => $tradeState,
        'trade_state_desc' => $result['trade_state_desc'] ?? '',
        'total_fee' => $result['amount']['total'] ?? 0,
        'transaction_id' => $result['transaction_id'] ?? ''
    ];
}

/**
 * V3 回调通知处理
 * V3 使用 JSON 格式, 签名在 HTTP 头中
 */
function handleNotify()
{
    $body = file_get_contents('php://input');
    logMessage("收到V3微信回调 - Body: {$body}");

    $headers = [];
    foreach ($_SERVER as $k => $v) {
        if (strpos($k, 'HTTP_') === 0) {
            $key = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($k, 5)))));
            $headers[$key] = $v;
        }
    }

    $signature = $headers['Wechatpay-Signature'] ?? '';
    $timestamp = $headers['Wechatpay-Timestamp'] ?? '';
    $nonce = $headers['Wechatpay-Nonce'] ?? '';
    $serial = $headers['Wechatpay-Serial'] ?? '';

    logMessage("V3回调头 - Signature: {$signature}, Timestamp: {$timestamp}, Nonce: {$nonce}, Serial: {$serial}");

    $data = json_decode($body, true);
    if (!$data || !isset($data['resource'])) {
        logMessage("V3回调数据为空或格式错误", 'ERROR');
        http_response_code(400);
        echo json_encode(['code' => 'FAIL', 'message' => '数据为空']);
        exit;
    }

    // 验证签名
    $signedMessage = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
    $verified = verifyPlatformSignature($signedMessage, $signature, $serial);

    if (!$verified) {
        logMessage("V3回调签名验证失败", 'ERROR');
        http_response_code(401);
        echo json_encode(['code' => 'FAIL', 'message' => '签名验证失败']);
        exit;
    }

    $resource = $data['resource'];
    $ciphertext = $resource['ciphertext'];
    $associatedData = $resource['associated_data'] ?? '';
    $nonceStr = $resource['nonce'];

    // 解密回调数据
    $decrypted = decryptNotifyData($ciphertext, $associatedData, $nonceStr);
    if (!$decrypted) {
        logMessage("V3回调数据解密失败", 'ERROR');
        http_response_code(400);
        echo json_encode(['code' => 'FAIL', 'message' => '数据解密失败']);
        exit;
    }

    $orderData = json_decode($decrypted, true);
    logMessage("V3回调解密数据: " . json_encode($orderData));

    $orderNo = $orderData['out_trade_no'];
    $amount = $orderData['amount']['total'];
    $openid = $orderData['payer']['openid'];
    $attach = $orderData['attach'] ?? 'membership';

    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        $sql = "SELECT user_id FROM consumption_records WHERE order_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$result || !$result['user_id']) {
            logMessage("未找到用户ID: 订单号={$orderNo}", 'ERROR');
            http_response_code(500);
            echo json_encode(['code' => 'FAIL', 'message' => '未找到订单']);
            exit;
        }

        $userId = $result['user_id'];
        $yuanAmount = $amount / 100;
        $level = 0;
        $points = 0;

        if ($yuanAmount == 9.9) {
            $level = 1;
            $points = 500;
        } elseif ($yuanAmount == 99) {
            $level = 1;
            $points = 500;
        } elseif ($yuanAmount == 299) {
            $level = 3;
            $points = 5000;
        } elseif ($yuanAmount == 2999 || $yuanAmount == 2990) {
            $level = 3;
            $points = 5000;
        }

        $pdo->beginTransaction();

        if ($level > 0) {
            $sql = "UPDATE users SET level = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$level, $userId]);
        }

        if ($points > 0) {
            $sql = "UPDATE users SET points = points + ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$points, $userId]);
        }

        $sql = "UPDATE consumption_records SET status = 1 WHERE order_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNo]);

        $pdo->commit();

        logMessage("V3支付成功处理完成 - userId:{$userId}, orderNo:{$orderNo}, amount:{$yuanAmount}元");

        http_response_code(200);
        echo json_encode(['code' => 'SUCCESS', 'message' => '成功']);
    } catch (Exception $e) {
        logMessage("V3回调处理异常: {$e->getMessage()}", 'ERROR');
        http_response_code(500);
        echo json_encode(['code' => 'FAIL', 'message' => '处理异常']);
    }

    exit;
}

/**
 * 验证微信平台签名
 */
function verifyPlatformSignature($message, $signature, $serial)
{
    $certPath = PaymentConfig::WX_PLATFORM_CERT_PATH;

    // 如果本地没有平台证书，则在首次验证时下载
    if (!file_exists($certPath)) {
        logMessage("平台证书不存在，尝试下载...");
        if (!downloadPlatformCertificates()) {
            logMessage("下载平台证书失败", 'ERROR');
            return false;
        }
    }

    $certContent = file_get_contents($certPath);
    $publicKey = openssl_get_publickey($certContent);
    if (!$publicKey) {
        logMessage("无法加载平台公钥", 'ERROR');
        return false;
    }

    $decodedSignature = base64_decode($signature);
    $result = openssl_verify($message, $decodedSignature, $publicKey, 'sha256WithRSAEncryption');
    openssl_free_key($publicKey);

    return $result === 1;
}

/**
 * 下载微信平台证书
 */
function downloadPlatformCertificates()
{
    $url = '/v3/certificates';
    $authHeaders = makeV3Authorization('GET', $url, '');
    if (!$authHeaders) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0',
        'Authorization: ' . $authHeaders['Authorization']
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    logMessage("下载证书 HTTP:{$httpCode} 返回:{$response}");

    if ($httpCode !== 200) {
        return false;
    }

    $result = json_decode($response, true);
    $certs = $result['data'] ?? [];

    // 解密每个证书并保存
    foreach ($certs as $cert) {
        $ciphertext = $cert['encrypt_certificate']['ciphertext'];
        $associatedData = $cert['encrypt_certificate']['associated_data'] ?? '';
        $nonceStr = $cert['encrypt_certificate']['nonce'];

        $decryptedCert = decryptNotifyData($ciphertext, $associatedData, $nonceStr);
        if ($decryptedCert) {
            file_put_contents(PaymentConfig::WX_PLATFORM_CERT_PATH, $decryptedCert);
            logMessage("平台证书已保存到: " . PaymentConfig::WX_PLATFORM_CERT_PATH);
            return true;
        }
    }

    return false;
}

/**
 * 使用 AEAD_AES_256_GCM 解密 V3 回调数据
 */
function decryptNotifyData($ciphertext, $associatedData, $nonceStr)
{
    $key = PaymentConfig::WX_KEY;

    // V3 APIv3 key 是32字节的十六进制字符串，但这里 WX_KEY 是 ASCII 字符串
    // 需要转为字节后再用于解密
    // 实际 V3 key 应该是在商户平台设置的 APIv3 密钥（32位）
    // 这里 WX_KEY 是 V2 Key，V3 需要单独的 APIv3 密钥

    $ciphertext = base64_decode($ciphertext);

    // APIv3 key 直接作为字节使用
    $decrypted = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonceStr,
        $associatedData
    );

    if ($decrypted === false) {
        logMessage("AEAD_AES_256_GCM解密失败", 'ERROR');
        return false;
    }

    return $decrypted;
}

function handleWxAuth()
{
    if (isset($_GET['code'])) {
        $code = $_GET['code'];
        $openid = getOpenIdByCode($code);

        if ($openid) {
            $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/demo.html?openid=' . $openid;
            header("Location: {$redirectUrl}");
            exit;
        } else {
            echo json_encode(['code' => 0, 'msg' => '获取openid失败']);
            exit;
        }
    } else {
        $authUrl = getWxAuthUrl();
        header("Location: {$authUrl}");
        exit;
    }
}

function handleWxAuthCallback()
{
    $code = $_GET['code'] ?? '';
    $requestId = $_GET['request_id'] ?? '';

    logMessage("wx_auth_callback - code: {$code}, request_id: {$requestId}");

    if (empty($code) || empty($requestId)) {
        logMessage("wx_auth_callback - 参数缺失", 'ERROR');
        echo json_encode(['code' => 0, 'msg' => '参数缺失']);
        exit;
    }

    $openid = getOpenIdByCode($code);

    if (!$openid) {
        logMessage("wx_auth_callback - 获取openid失败", 'ERROR');
        echo json_encode(['code' => 0, 'msg' => '获取openid失败']);
        exit;
    }

    $paymentParams = $_SESSION['payment_params_' . $requestId] ?? null;

    if (!$paymentParams) {
        logMessage("wx_auth_callback - 未找到保存的订单参数");
        $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/demo.html?openid=' . $openid;
        header("Location: {$redirectUrl}");
        exit;
    }

    unset($_SESSION['payment_params_' . $requestId]);

    logMessage("wx_auth_callback - 恢复订单参数: " . json_encode($paymentParams));

    $redirectUrl = 'https://' . $_SERVER['HTTP_HOST'] . '/demo.html?openid=' . $openid .
        '&amount=' . $paymentParams['amount'] .
        '&body=' . urlencode($paymentParams['body']) .
        '&attach=' . urlencode($paymentParams['attach']);

    logMessage("wx_auth_callback - 重定向到: {$redirectUrl}");
    header("Location: {$redirectUrl}");
    exit;
}

$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';

switch ($action) {
    case 'create_order':
        logMessage("===== create_order (V3) =====");
        logMessage("请求时间: " . date('Y-m-d H:i:s'));
        logMessage("请求方法: " . $_SERVER['REQUEST_METHOD']);
        logMessage("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? '未设置'));

        $rawInput = file_get_contents('php://input');
        logMessage("原始输入: " . ($rawInput ?: '空'));

        $data = json_decode($rawInput, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            logMessage("JSON解析失败: " . json_last_error_msg(), 'ERROR');
            $data = null;
        }

        if (empty($data)) {
            $data = $_POST;
        }

        if (empty($data) && isset($_GET['amount'])) {
            $data['amount'] = $_GET['amount'];
        }

        if (isset($_GET['code']) && empty($data['openid'])) {
            $openid = getOpenIdByCode($_GET['code']);
            if ($openid) {
                $data['openid'] = $openid;
            }
        }

        $result = createOrder($data);
        echo json_encode($result);
        break;

    case 'query_order':
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        if (empty($data)) {
            $data = $_POST;
        }
        $result = queryOrder($data);
        echo json_encode($result);
        break;

    case 'notify':
        handleNotify();
        break;

    case 'wx_auth':
        handleWxAuth();
        break;

    case 'wx_auth_callback':
        handleWxAuthCallback();
        break;

    case 'test_amount':
        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);
        logMessage("测试接口 - 输入: " . $rawInput);
        if (isset($data['amount'])) {
            echo json_encode([
                'code' => 1,
                'msg' => '金额参数接收成功',
                'received_amount' => $data['amount'],
                'is_numeric' => is_numeric($data['amount']),
                'as_integer' => (int)$data['amount']
            ]);
        } else {
            echo json_encode([
                'code' => 0,
                'msg' => '金额参数不存在',
                'received_data' => $data,
                'post_data' => $_POST,
                'get_data' => $_GET
            ]);
        }
        break;

    default:
        echo json_encode(['code' => 0, 'msg' => '无效的操作']);
        break;
}
