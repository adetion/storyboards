<?php
/**
 * notify.php - 微信支付V3回调通知接口
 */

require_once 'config.php';
require_once 'Database.php';

date_default_timezone_set('Asia/Shanghai');

function notifyLog($message) {
    if (!Config::WX_LOG_ENABLED) return;
    $logDir = dirname(Config::WX_LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] [回调V3] {$message}\n";
    file_put_contents(Config::WX_LOG_PATH, $log, FILE_APPEND);
}

function createNonceStr($length = 32) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

notifyLog("=== 收到V3支付回调通知 ===");

$body = file_get_contents('php://input');
notifyLog("原始数据: {$body}");

if (empty($body)) {
    notifyLog('错误: 回调数据为空');
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => '数据为空']);
    exit;
}

// 提取签名相关HTTP头
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

notifyLog("签名头 - Signature: {$signature}, Timestamp: {$timestamp}, Nonce: {$nonce}, Serial: {$serial}");

$data = json_decode($body, true);
if (!$data || !isset($data['resource'])) {
    notifyLog('错误: 回调数据格式错误');
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => '数据格式错误']);
    exit;
}

// 1. 验证平台签名
function downloadPlatformCert() {
    $url = '/v3/certificates';

    $timestamp = (string)time();
    $nonceStr = createNonceStr();
    $message = "GET\n{$url}\n{$timestamp}\n{$nonceStr}\n\n";

    $privateKey = openssl_get_privatekey(file_get_contents(Config::WX_PRIVATE_KEY_PATH));
    if (!$privateKey) {
        notifyLog('错误: 无法加载商户私钥');
        return false;
    }

    $sig = '';
    openssl_sign($message, $sig, $privateKey, 'sha256WithRSAEncryption');
    openssl_free_key($privateKey);
    $sig = base64_encode($sig);

    $auth = 'WECHATPAY2-SHA256-RSA2048 mchid="' . Config::WX_MCH_ID
        . '",nonce_str="' . $nonceStr
        . '",signature="' . $sig
        . '",timestamp="' . $timestamp
        . '",serial_no="' . Config::WX_SERIAL_NO . '"';

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.mch.weixin.qq.com' . $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'User-Agent: Mozilla/5.0',
        'Authorization: ' . $auth
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    notifyLog("下载平台证书 HTTP:{$httpCode}");

    if ($httpCode !== 200) {
        return false;
    }

    $result = json_decode($response, true);
    $certs = $result['data'] ?? [];

    $certPath = Config::WX_ROOT_CA_PATH;
    // Use the cert path directory
    $certSavePath = dirname(Config::WX_PRIVATE_KEY_PATH) . '/platform_cert.pem';

    foreach ($certs as $cert) {
        $ciphertext = $cert['encrypt_certificate']['ciphertext'];
        $associatedData = $cert['encrypt_certificate']['associated_data'] ?? '';
        $nonceStr = $cert['encrypt_certificate']['nonce'];

        $decryptedCert = decryptAes256Gcm($ciphertext, $associatedData, $nonceStr);
        if ($decryptedCert) {
            file_put_contents($certSavePath, $decryptedCert);
            notifyLog("平台证书已保存到: {$certSavePath}");
            return $certSavePath;
        }
    }

    return false;
}

function decryptAes256Gcm($ciphertext, $associatedData, $nonceStr) {
    $key = Config::WX_KEY;
    $ciphertext = base64_decode($ciphertext);

    $decrypted = openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $nonceStr,
        $associatedData
    );

    if ($decrypted === false) {
        notifyLog("AEAD_AES_256_GCM解密失败");
        return false;
    }

    return $decrypted;
}

function verifyPlatformSignature($body, $signature, $timestamp, $nonce) {
    $certPath = dirname(Config::WX_PRIVATE_KEY_PATH) . '/platform_cert.pem';

    if (!file_exists($certPath)) {
        notifyLog("平台证书不存在，尝试下载...");
        $certPath = downloadPlatformCert();
        if (!$certPath) {
            notifyLog('错误: 下载平台证书失败');
            return false;
        }
    }

    $certContent = file_get_contents($certPath);
    $publicKey = openssl_get_publickey($certContent);
    if (!$publicKey) {
        notifyLog('错误: 无法加载平台公钥');
        return false;
    }

    $message = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
    $decodedSignature = base64_decode($signature);

    $result = openssl_verify($message, $decodedSignature, $publicKey, 'sha256WithRSAEncryption');
    openssl_free_key($publicKey);

    return $result === 1;
}

$verified = verifyPlatformSignature($body, $signature, $timestamp, $nonce);
if (!$verified) {
    notifyLog('错误: 平台签名验证失败');
    http_response_code(401);
    echo json_encode(['code' => 'FAIL', 'message' => '签名验证失败']);
    exit;
}

notifyLog("平台签名验证通过");

// 2. 解密回调数据
$resource = $data['resource'];
$ciphertext = $resource['ciphertext'];
$associatedData = $resource['associated_data'] ?? '';
$nonceStr = $resource['nonce'];

$decrypted = decryptAes256Gcm($ciphertext, $associatedData, $nonceStr);
if (!$decrypted) {
    notifyLog('错误: 回调数据解密失败');
    http_response_code(400);
    echo json_encode(['code' => 'FAIL', 'message' => '数据解密失败']);
    exit;
}

$orderData = json_decode($decrypted, true);
notifyLog("解密数据: " . json_encode($orderData, JSON_UNESCAPED_UNICODE));

// 3. 提取订单信息
$outTradeNo = $orderData['out_trade_no'];
$transactionId = $orderData['transaction_id'];
$tradeState = $orderData['trade_state'] ?? 'UNKNOWN';
$totalFee = $orderData['amount']['total'] ?? 0;
$openid = $orderData['payer']['openid'] ?? '';
$attach = $orderData['attach'] ?? 'membership';

notifyLog("订单信息 - 订单号:{$outTradeNo}, 微信订单:{$transactionId}, 状态:{$tradeState}, 金额:{$totalFee}分, openid:{$openid}");

if ($tradeState !== 'SUCCESS') {
    notifyLog("交易状态非成功: {$tradeState}，终止处理");
    http_response_code(200);
    echo json_encode(['code' => 'SUCCESS', 'message' => 'OK']);
    exit;
}

// 4. 业务处理
try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 查找订单对应的用户
    $sql = "SELECT user_id FROM consumption_records WHERE order_no = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outTradeNo]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    $userId = null;
    if ($record && $record['user_id']) {
        $userId = $record['user_id'];
    }

    // 如果consumption_records中没有，通过openid查找用户
    if (!$userId && !empty($openid)) {
        $sql = "SELECT id FROM users WHERE openid = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$openid]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            $userId = $user['id'];
        }
    }

    if (!$userId) {
        notifyLog("错误: 无法确定用户ID - 订单:{$outTradeNo}, openid:{$openid}");
        http_response_code(200);
        echo json_encode(['code' => 'SUCCESS', 'message' => 'OK']);
        exit;
    }

    $yuanAmount = $totalFee / 100;
    $level = 0;
    $points = 0;

    // 根据金额匹配会员等级
    if ($yuanAmount == 9.9) {
        $level = 1; $points = 500;
    } elseif ($yuanAmount == 99) {
        $level = 1; $points = 500;
    } elseif ($yuanAmount == 299) {
        $level = 3; $points = 5000;
    } elseif ($yuanAmount == 2999 || $yuanAmount == 2990) {
        $level = 3; $points = 5000;
    } elseif ($yuanAmount == 29) {
        $level = 1; $points = 1000;
    } elseif ($yuanAmount == 59) {
        $level = 2; $points = 2000;
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

    // 更新消费记录状态
    $sql = "UPDATE consumption_records SET status = 1 WHERE order_no = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outTradeNo]);

    $pdo->commit();

    notifyLog("业务处理成功 - userId:{$userId}, orderNo:{$outTradeNo}, amount:{$yuanAmount}元, level:{$level}, points:{$points}");

} catch (Exception $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    notifyLog("业务处理异常: {$e->getMessage()}");
}

// 5. 返回成功响应
http_response_code(200);
echo json_encode(['code' => 'SUCCESS', 'message' => '成功']);
notifyLog("返回成功响应");
