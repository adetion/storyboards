<?php
/**
 * 订单状态查询接口
 */

require_once 'WxPay.php';

header('Content-Type: application/json; charset=utf-8');

$wxpay = new WxPay();
$outTradeNo = $_GET['out_trade_no'] ?? '';

if (empty($outTradeNo)) {
    echo json_encode([
        'code' => 400,
        'message' => '订单号不能为空'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 查询订单状态
$result = $wxpay->orderQuery($outTradeNo);

if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
    echo json_encode([
        'out_trade_no' => $result['out_trade_no'],
        'transaction_id' => $result['transaction_id'] ?? '',
        'trade_state' => $result['trade_state'] ?? '',
        'total_fee' => $result['total_fee'] ?? 0,
        'time_end' => $result['time_end'] ?? '',
        'trade_state_desc' => $result['trade_state_desc'] ?? ''
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'code' => 500,
        'message' => $result['return_msg'] ?? '查询失败',
        'err_code' => $result['err_code'] ?? '',
        'err_code_des' => $result['err_code_des'] ?? ''
    ], JSON_UNESCAPED_UNICODE);
}