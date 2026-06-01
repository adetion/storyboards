<?php
/**
 * 微信支付回调通知处理
 * 注意：此文件需要能被微信服务器访问（公网可访问）
 */

require_once 'WxPay.php';

$wxpay = new WxPay();

// 获取回调数据
$xmlData = file_get_contents('php://input');
$data = $wxpay->xmlToArray($xmlData);

// 记录回调数据（用于调试）
file_put_contents('notify_log.txt', date('Y-m-d H:i:s') . "\n" . $xmlData . "\n\n", FILE_APPEND);

// 验证签名
if (!$wxpay->verifyNotify($data)) {
    $response = [
        'return_code' => 'FAIL',
        'return_msg'  => '签名验证失败'
    ];
    echo $wxpay->arrayToXml($response);
    exit;
}

// 验证业务结果
if ($data['return_code'] == 'SUCCESS' && $data['result_code'] == 'SUCCESS') {
    // 支付成功，处理业务逻辑
    
    $outTradeNo = $data['out_trade_no'];
    $transactionId = $data['transaction_id'];
    $totalFee = $data['total_fee'];
    $timeEnd = $data['time_end'];
    
    // TODO: 这里处理您的业务逻辑，例如：
    // 1. 更新订单状态为已支付
    // 2. 记录支付成功时间
    // 3. 发放商品或服务
    // 4. 发送通知等
    
    // 示例：记录到数据库
    // updateOrderStatus($outTradeNo, 'paid', $transactionId);
    
    $response = [
        'return_code' => 'SUCCESS',
        'return_msg'  => 'OK'
    ];
} else {
    // 支付失败
    $response = [
        'return_code' => 'FAIL',
        'return_msg'  => $data['return_msg'] ?? '支付失败'
    ];
}

// 返回响应给微信服务器
echo $wxpay->arrayToXml($response);