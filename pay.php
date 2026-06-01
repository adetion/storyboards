<?php
// pay.php - 微信支付JSAPI接口
header('Content-Type: application/json; charset=utf-8');

// 启动session
session_start();

// 引入配置和微信授权处理
require_once 'config.php';
require_once 'wx_auth.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 日志函数
function wxLog($message, $level = 'INFO')
{
    if (!Config::WX_LOG_ENABLED) return;

    $logDir = dirname(Config::WX_LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] [{$level}] {$message}\n";
    file_put_contents(Config::WX_LOG_PATH, $log, FILE_APPEND);
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    wxLog('非POST请求', 'ERROR');
    die(json_encode(['code' => 0, 'msg' => '请使用POST请求'], JSON_UNESCAPED_UNICODE));
}

// 获取并验证参数
$amount = intval($_POST['amount'] ?? 0);
$order_no = trim($_POST['order_no'] ?? '');
$body = trim($_POST['body'] ?? '账户充值');
$attach = trim($_POST['attach'] ?? 'recharge');

// 验证会员支付金额
if (strpos($attach, 'membership') !== false) {
    // 解析会员类型和时长
    $vipType = 1; // 默认个人会员
    $duration = 1; // 默认月会员
    $amountYuan = $amount / 100; // 转换为元
    
    // 从attach字段解析会员信息
    if (strpos($attach, '_') !== false) {
        $parts = explode('_', $attach);
        if (count($parts) >= 3) {
            $vipType = intval($parts[1]); // 会员类型：1=个人，2=团队
            $duration = intval($parts[2]); // 时长：1=月，2=年
        }
    }
    
    // 获取对应会员类型和时长的预期价格
    $key = "{$vipType}_{$duration}";
    $expectedPrice = 0;
    if (isset(Config::VIP_PRICES[$key])) {
        $expectedPrice = Config::VIP_PRICES[$key]; // 元
    }
    
    // 验证金额是否匹配
    if ($expectedPrice > 0 && abs($amountYuan - $expectedPrice) > 0.01) { // 允许0.01元的误差
        wxLog("会员支付金额验证失败，type: {$vipType}, duration: {$duration}, expected: {$expectedPrice}, actual: {$amountYuan}", 'ERROR');
        die(json_encode(['code' => 0, 'msg' => '支付金额不正确，请重新选择支付选项'], JSON_UNESCAPED_UNICODE));
    }
}

// 获取openid - 从微信授权中获取真实openid
$openid = null;
$need_auth = false;

// 检查是否有微信授权code
if (isset($_GET['code'])) {
    // 从GET参数获取openid
    $code = $_GET['code'];
    $openid = getOpenIdByCode($code);
    
    // 保存openid到session
    if ($openid) {
        $_SESSION['user_openid'] = $openid;
    }
} elseif (isset($_SESSION['user_openid'])) {
    // 从会话中获取openid
    $openid = $_SESSION['user_openid'];
} else {
    // 检查是否通过POST参数传递了openid（用于AJAX调用）
    $openid = trim($_POST['openid'] ?? '');
    
    // 如果没有获取到openid，需要授权
    if (empty($openid)) {
        $need_auth = true;
    }
}

// 如果需要授权，返回错误信息
if ($need_auth) {
    die(json_encode(['code' => 0, 'msg' => '请先登录微信获取授权', 'need_auth' => true], JSON_UNESCAPED_UNICODE));
}

// 验证openid格式
if (!preg_match('/^o[A-Za-z0-9_-]{10,}$/', $openid)) {
    die(json_encode(['code' => 0, 'msg' => 'openid格式无效，请提供真实有效的微信openid'], JSON_UNESCAPED_UNICODE));
}

wxLog("使用获取到的openid: {$openid}");
if ($amount < Config::WX_MIN_AMOUNT || $amount > Config::WX_MAX_AMOUNT) {
    die(json_encode(['code' => 0, 'msg' => "金额需在" . (Config::WX_MIN_AMOUNT / 100) . "元到" . (Config::WX_MAX_AMOUNT / 100) . "元之间"], JSON_UNESCAPED_UNICODE));
}
if (empty($order_no)) {
    die(json_encode(['code' => 0, 'msg' => '订单号不能为空'], JSON_UNESCAPED_UNICODE));
}

// 生成随机字符串
function generateNonceStr($length = 32)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

// 生成签名（V2接口用）
function makeSign($params, $key)
{
    // 1. 过滤参数：去除空值和sign
    $signParams = [];
    foreach ($params as $k => $v) {
        if ($v === '' || $k === 'sign') {
            continue;
        }
        $signParams[$k] = (string)$v;
    }

    // 2. 参数排序
    ksort($signParams);

    // 3. 拼接签名字符串
    $string = '';
    foreach ($signParams as $k => $v) {
        $string .= "{$k}={$v}&";
    }

    // 4. 移除末尾的&，添加key
    $string = rtrim($string, '&') . '&key=' . $key;

    // 5. 生成MD5签名并转为大写
    return strtoupper(md5($string));
}

try {
    // 仅在明确的开发环境下才使用模拟支付
    // 生产环境必须调用真实微信支付API
    $http_host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
    $is_local = strpos($http_host, 'localhost') !== false || strpos($http_host, '127.0.0.1') !== false || $http_host === '';

    // 开发环境模拟支付：仅在本地环境且调试模式开启时才生效
    if ($is_local && Config::WX_DEBUG_MODE) {
        $timeStamp = time();
        $nonceStr = generateNonceStr();
        $prepayId = 'wx20241205123456789012345678901234';

        $payParams = [
            'appId'     => Config::WX_APPID,
            'timeStamp' => (string)$timeStamp,
            'nonceStr'  => $nonceStr,
            'package'   => "prepay_id={$prepayId}",
            'signType'  => 'MD5',
        ];

        $payParams['paySign'] = makeSign($payParams, Config::WX_KEY);

        wxLog("开发环境模拟支付订单创建成功: {$order_no}, 金额: {$amount}分, openid: {$openid}");

        echo json_encode([
            'code' => 1,
            'msg'  => '成功',
            'data' => [
                'prepay_id'  => $prepayId,
                'pay_params' => $payParams,
                'order_no'   => $order_no,
                'amount'     => $amount,
                'amount_yuan' => $amount / 100,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    } else {
        // 生产环境：构建微信支付参数
        $appid = Config::WX_APPID;
        $mch_id = Config::WX_MCH_ID;
        $nonce_str = generateNonceStr();
        $total_fee = $amount;
        $spbill_create_ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        // 从配置中获取notify_url，并进行完整的清理
        $notify_url = Config::WX_NOTIFY_URL;
        // 1. 清除可能存在的反引号和其他特殊字符
        $notify_url = preg_replace('/[^a-zA-Z0-9:\.\-_]/', '', $notify_url);
        // 2. 清除空格
        $notify_url = trim($notify_url);
        $trade_type = 'JSAPI';

        // 清理notify_url，确保没有反引号和空格
        $clean_notify_url = preg_replace('/[^a-zA-Z0-9:\.\-_]/', '', Config::WX_NOTIFY_URL);
        $clean_notify_url = trim($clean_notify_url);
        
        // 再次确保没有反引号
        $clean_notify_url = str_replace('`', '', $clean_notify_url);
        
        // 构建参数数组 - 使用干净的notify_url
        $params = [
            'appid'            => $appid,
            'mch_id'           => $mch_id,
            'nonce_str'        => $nonce_str,
            'body'             => $body,
            'out_trade_no'     => $order_no,
            'total_fee'        => $total_fee,
            'spbill_create_ip' => $spbill_create_ip,
            'notify_url'       => $clean_notify_url,
            'trade_type'       => $trade_type,
            'openid'           => $openid,
            'attach'           => $attach
        ];

        // 生成签名 - 使用干净的参数
        $sign = makeSign($params, Config::WX_KEY);
        $params['sign'] = $sign;

        // 手动构建XML，不使用CDATA包装，确保参数值正确
        $xml = "<xml>";
        $xml .= "<appid>{$appid}</appid>";
        $xml .= "<mch_id>{$mch_id}</mch_id>";
        $xml .= "<nonce_str>{$nonce_str}</nonce_str>";
        $xml .= "<body>{$body}</body>";
        $xml .= "<out_trade_no>{$order_no}</out_trade_no>";
        $xml .= "<total_fee>{$total_fee}</total_fee>";
        $xml .= "<spbill_create_ip>{$spbill_create_ip}</spbill_create_ip>";
        $xml .= "<notify_url>{$clean_notify_url}</notify_url>";
        $xml .= "<trade_type>{$trade_type}</trade_type>";
        $xml .= "<openid>{$openid}</openid>";
        $xml .= "<attach>{$attach}</attach>";
        $xml .= "<sign>{$sign}</sign>";
        $xml .= "</xml>";

        // 确保XML完全没有反引号和其他特殊字符
        $xml = str_replace('`', '', $xml);
        // 确保XML中notify_url参数值纯净
        $xml = preg_replace('/<notify_url>[^<]+<\/notify_url>/', '<notify_url>' . $clean_notify_url . '</notify_url>', $xml);

        // 详细日志记录 - 确保不包含反引号
        wxLog("请求参数: " . str_replace('`', '', json_encode($params, JSON_UNESCAPED_UNICODE)));
        wxLog("请求XML: " . str_replace('`', '', $xml));

        // 记录签名计算细节 - 确保不包含反引号
        $clean_key = str_replace('`', '', Config::WX_KEY);
        // 直接构建干净的签名计算字符串，使用与参数数组相同的clean_notify_url
        $signStr = "appid={$appid}&attach={$attach}&body={$body}&mch_id={$mch_id}&nonce_str={$nonce_str}&notify_url={$clean_notify_url}&openid={$openid}&out_trade_no={$order_no}&spbill_create_ip={$spbill_create_ip}&total_fee={$total_fee}&trade_type={$trade_type}&key={$clean_key}";
        // 再次清理，确保万无一失
        $signStr = str_replace('`', '', $signStr);
        $signStr = str_replace('  ', ' ', $signStr); // 去除多余空格
        // 确保签名计算字符串中没有任何特殊字符
        $signStr = preg_replace('/[^a-zA-Z0-9:\.\-_=&]/', '', $signStr);
        wxLog("签名计算字符串: " . $signStr);
        wxLog("计算出的签名: " . $sign);
        wxLog("请求IP: " . $spbill_create_ip);
        wxLog("请求URL: " . str_replace('`', '', Config::WX_API_URL) . '/pay/unifiedorder');

        // 调用微信统一下单接口
        $url = Config::WX_API_URL . '/pay/unifiedorder';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: text/xml',
            'Content-Length: ' . strlen($xml)
        ));

        $responseXml = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception("HTTP请求失败: {$error}");
        }

        wxLog("微信返回XML: {$responseXml}");

        // 使用正则表达式解析微信响应XML
        $result = [];
        
        // 提取return_code
        if (preg_match('/<return_code><!\[CDATA\[([^\]]+)\]\]><\/return_code>/', $responseXml, $matches)) {
            $result['return_code'] = $matches[1];
        }
        
        // 提取return_msg
        if (preg_match('/<return_msg><!\[CDATA\[([^\]]+)\]\]><\/return_msg>/', $responseXml, $matches)) {
            $result['return_msg'] = $matches[1];
        }
        
        // 提取result_code
        if (preg_match('/<result_code><!\[CDATA\[([^\]]+)\]\]><\/result_code>/', $responseXml, $matches)) {
            $result['result_code'] = $matches[1];
        }
        
        // 提取prepay_id
        if (preg_match('/<prepay_id><!\[CDATA\[([^\]]+)\]\]><\/prepay_id>/', $responseXml, $matches)) {
            $result['prepay_id'] = $matches[1];
        }
        
        // 提取err_code和err_code_des（如果有）
        if (preg_match('/<err_code><!\[CDATA\[([^\]]+)\]\]><\/err_code>/', $responseXml, $matches)) {
            $result['err_code'] = $matches[1];
        }
        if (preg_match('/<err_code_des><!\[CDATA\[([^\]]+)\]\]><\/err_code_des>/', $responseXml, $matches)) {
            $result['err_code_des'] = $matches[1];
        }
        
        wxLog("响应结果: " . json_encode($result, JSON_UNESCAPED_UNICODE));

        // 检查响应结果
        if (empty($result) || !isset($result['return_code'])) {
            throw new Exception('微信接口返回数据异常');
        }

        // 检查响应结果 - 增强类型安全
        if (isset($result['return_code']) && $result['return_code'] !== 'SUCCESS') {
            $errorMsg = '请求失败';
            if (isset($result['return_msg'])) {
                $errorMsg = is_string($result['return_msg']) ? $result['return_msg'] : '请求失败';
            }
            throw new Exception($errorMsg);
        }
        
        if (isset($result['result_code']) && $result['result_code'] !== 'SUCCESS') {
            $errorMsg = '支付失败';
            if (isset($result['err_code_des']) && is_string($result['err_code_des'])) {
                $errorMsg = $result['err_code_des'];
            } elseif (isset($result['err_code']) && is_string($result['err_code'])) {
                $errorMsg = $result['err_code'];
            }
            throw new Exception($errorMsg);
        }
        
        // 生成前端支付参数
        $timeStamp = time();
        $nonceStr = generateNonceStr();
        $prepayId = $result['prepay_id'];

        $payParams = [
            'appid'     => $appid,
            'timeStamp' => (string)$timeStamp,
            'nonceStr'  => $nonceStr,
            'package'   => "prepay_id={$prepayId}",
            'signType'  => 'MD5',
        ];

        $payParams['paySign'] = makeSign($payParams, Config::WX_KEY);

        wxLog("支付订单创建成功: {$order_no}, 金额: {$amount}分, prepay_id: {$prepayId}");

        // 返回成功响应
        echo json_encode([
            'code' => 1,
            'msg'  => '成功',
            'data' => [
                'prepay_id'  => $prepayId,
                'pay_params' => $payParams,
                'order_no'   => $order_no,
                'amount'     => $amount,
                'amount_yuan' => $amount / 100,
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
} catch (Exception $e) {
    wxLog("支付订单创建失败: {$order_no}, 错误: " . $e->getMessage(), 'ERROR');
    echo json_encode([
        'code' => 0,
        'msg'  => '支付订单创建失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

