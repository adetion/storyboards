<?php
// 支付成功页面
$outTradeNo = $_GET['out_trade_no'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付成功</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .success { color: #67C23A; font-size: 48px; }
        .order-info { margin: 30px 0; font-size: 18px; }
        .btn { display: inline-block; padding: 10px 20px; background: #409EFF; color: white; 
               text-decoration: none; border-radius: 4px; margin: 10px; }
    </style>
</head>
<body>
    <div class="success">✓</div>
    <h1>支付成功</h1>
    
    <div class="order-info">
        <p>订单号：<?php echo htmlspecialchars($outTradeNo); ?></p>
        <p>感谢您的购买！</p>
    </div>
    
    <div>
        <a href="/" class="btn">返回首页</a>
        <a href="order_detail.php?no=<?php echo $outTradeNo; ?>" class="btn">查看订单</a>
    </div>
</body>
</html>