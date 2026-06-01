<?php
require_once 'WxPay.php';

// 示例：生成支付二维码
$wxpay = new WxPayFixed();

// 获取订单参数（实际应从数据库或SESSION中获取）
$outTradeNo = $_GET['out_trade_no'] ?? date('YmdHis') . mt_rand(1000, 9999);
$body = $_GET['body'] ?? '测试商品';
$totalFee = $_GET['total_fee'] ?? 1; // 单位：分，1分钱用于测试

// 创建订单
$result = $wxpay->createNativeOrder($outTradeNo, $body, $totalFee);

if ($result['success']) {
    // 保存订单信息到数据库（这里需要您自己实现）
    // saveOrder($outTradeNo, $totalFee, $result['prepay_id']);
    
    $codeUrl = $result['code_url'];
    $orderNo = $result['out_trade_no'];
} else {
    $error = $result['err_code_des'];
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>微信扫码支付</title>
    <script src="https://cdn.bootcdn.net/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.bootcdn.net/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .qrcode-container { text-align: center; margin: 30px 0; }
        #qrcode { display: inline-block; }
        .order-info { background: #f5f5f5; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .status { padding: 10px; text-align: center; }
        .success { color: #67C23A; }
        .error { color: #F56C6C; }
    </style>
</head>
<body>
    <h2>微信扫码支付</h2>
    
    <div class="order-info">
        <p><strong>订单号：</strong><?php echo $orderNo ?? ''; ?></p>
        <p><strong>商品描述：</strong><?php echo htmlspecialchars($body); ?></p>
        <p><strong>支付金额：</strong><?php echo ($totalFee / 100); ?>元</p>
    </div>
    
    <?php if (isset($error)): ?>
        <div class="status error">
            <p>支付订单创建失败：<?php echo $error; ?></p>
        </div>
    <?php elseif (isset($codeUrl)): ?>
        <div class="qrcode-container">
            <p>请使用微信扫码支付</p>
            <div id="qrcode"></div>
            <p style="color: #999; font-size: 14px; margin-top: 15px;">
                二维码有效期30分钟，请尽快支付
            </p>
        </div>
        
        <div class="status">
            <p id="payment-status">等待扫码支付...</p>
            <p><a href="check.php?out_trade_no=<?php echo $orderNo; ?>">手动查询支付状态</a></p>
        </div>
        
        <script>
            // 生成二维码
            new QRCode(document.getElementById("qrcode"), {
                text: "<?php echo $codeUrl; ?>",
                width: 200,
                height: 200,
                colorDark : "#000000",
                colorLight : "#ffffff",
                correctLevel : QRCode.CorrectLevel.H
            });
            
            // 轮询查询支付状态
            let checkCount = 0;
            const maxChecks = 300; // 30分钟（每6秒一次）
            
            function checkPayment() {
                if (checkCount >= maxChecks) {
                    $('#payment-status').html('二维码已过期，请刷新页面重新获取');
                    return;
                }
                
                $.ajax({
                    url: 'check.php',
                    type: 'GET',
                    data: {
                        out_trade_no: '<?php echo $orderNo; ?>'
                    },
                    success: function(response) {
                        const data = JSON.parse(response);
                        
                        if (data.trade_state === 'SUCCESS') {
                            $('#payment-status').html('<span class="success">支付成功！正在跳转...</span>');
                            // 支付成功，跳转到成功页面
                            setTimeout(function() {
                                window.location.href = 'success.php?out_trade_no=' + data.out_trade_no;
                            }, 1500);
                        } else if (data.trade_state === 'USERPAYING') {
                            // 用户支付中，继续轮询
                            setTimeout(checkPayment, 6000); // 6秒后再次查询
                            checkCount++;
                        } else if (data.trade_state === 'CLOSED' || data.trade_state === 'REVOKED') {
                            $('#payment-status').html('<span class="error">订单已关闭</span>');
                        } else if (data.trade_state === 'PAYERROR') {
                            $('#payment-status').html('<span class="error">支付失败</span>');
                        } else {
                            // 其他状态继续轮询
                            setTimeout(checkPayment, 6000);
                            checkCount++;
                        }
                    },
                    error: function() {
                        setTimeout(checkPayment, 6000);
                        checkCount++;
                    }
                });
            }
            
            // 开始轮询
            setTimeout(checkPayment, 3000); // 3秒后开始第一次查询
        </script>
    <?php endif; ?>
</body>
</html>
