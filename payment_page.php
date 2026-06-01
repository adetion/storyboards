<?php
/**
 * 集成微信授权的支付页面
 * 自动获取用户openid，无需手动输入
 * 支持多业务配置
 */

// 开启会话
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// 加载配置
function loadPaymentConfig() {
    $configPath = __DIR__ . '/payment_config.json';
    if (file_exists($configPath)) {
        return json_decode(file_get_contents($configPath), true);
    }
    return null;
}

// 获取业务ID
function getBusinessId() {
    if (isset($_REQUEST['business_id'])) {
        return $_REQUEST['business_id'];
    }
    if (isset($_GET['business'])) {
        return $_GET['business'];
    }
    return 'zhiying_gongchang'; // 默认业务
}

// 生成订单号
function generateOrderNo() {
    $timestamp = date('YmdHis');
    $random = mt_rand(100000, 999999);
    return "ORD{$timestamp}{$random}";
}

// 输出JSON响应
function jsonResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE);
    exit();
}

$CONFIG = loadPaymentConfig();
$BUSINESS_ID = getBusinessId();
$BUSINESS_CONFIG = $CONFIG ? ($CONFIG['business'][$BUSINESS_ID] ?? null) : null;

// 检查是否是微信浏览器
function isWeChatBrowser() {
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    return strpos($userAgent, 'MicroMessenger') !== false;
}

// 检查是否是API请求
$isApiRequest = ($_SERVER['REQUEST_METHOD'] === 'POST') && 
    (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false);

if ($isApiRequest) {
    // 处理API请求
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    if ($action === 'create_order') {
        // 创建订单
        if (!$BUSINESS_CONFIG) {
            jsonResponse(false, '未找到业务配置');
        }
        
        $productId = $input['product_id'] ?? '';
        $amount = $input['amount'] ?? 0;
        
        // 验证产品是否存在，并校验金额
        $productExists = false;
        $productPrice = 0;
        foreach ($BUSINESS_CONFIG['products'] as $product) {
            if ($product['id'] === $productId) {
                $productExists = true;
                $productPrice = $product['price'] * 100; // 转换为分
                break;
            }
        }

        if (!$productExists) {
            jsonResponse(false, '产品不存在');
        }

        if ((int)$amount !== (int)$productPrice) {
            jsonResponse(false, '支付金额与产品价格不匹配');
        }
        
        // 获取用户openid
        $openid = $_SESSION['user_openid'] ?? '';
        if (empty($openid)) {
            jsonResponse(false, '用户未授权');
        }
        
        // 调用pay_standalone.php创建真实订单
        $payData = [
            'amount' => $amount,
            'body' => $productId . '_subscription',
            'attach' => 'vip_' . $productId,
            'openid' => $openid
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://' . $_SERVER['HTTP_HOST'] . '/pay_standalone.php?action=create_order');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 || !$response) {
            jsonResponse(false, '支付服务调用失败');
        }
        
        $result = json_decode($response, true);
        
        if ($result['code'] === 1) {
            jsonResponse(true, '订单创建成功', [
                'order_no' => $result['order_no'],
                'amount' => $result['amount'],
                'prepay_id' => $result['prepay_id'],
                'wx_params' => $result['wx_params']
            ]);
        } else {
            jsonResponse(false, $result['msg'] ?? '订单创建失败');
        }
    } else {
        jsonResponse(false, '无效的操作');
    }
}

// 下面是页面渲染逻辑
require_once 'config.php';

// 生成授权链接（使用 payment_config.json 中的配置）
function getOAuthUrl($redirect_uri, $scope = 'snsapi_base', $state = 'STATE') {
    global $CONFIG;
    $appId = $CONFIG['wechat']['appid'] ?? 'wx7a15973fe4c9f064';
    $redirect_uri = urlencode($redirect_uri);
    return "https://open.weixin.qq.com/connect/oauth2/authorize?appid={$appId}&redirect_uri={$redirect_uri}&response_type=code&scope={$scope}&state={$state}#wechat_redirect";
}

// 根据code获取openid（使用 payment_config.json 中的配置）
function getOpenIdByCode($code) {
    global $CONFIG;
    $appId = $CONFIG['wechat']['appid'] ?? 'wx7a15973fe4c9f064';
    $appSecret = $CONFIG['wechat']['appsecret'] ?? '692a720b323f10ec045e42fa66f9ff43';

    $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid={$appId}&secret={$appSecret}&code={$code}&grant_type=authorization_code";
    $result = json_decode(file_get_contents($url), true);

    if (isset($result['openid'])) {
        return $result['openid'];
    }
    return null;
}

// 处理微信授权
function handleWechatAuth() {
    if (isset($_SESSION['user_openid'])) {
        return $_SESSION['user_openid'];
    }
    
    if (isset($_GET['code'])) {
        $code = $_GET['code'];
        $openid = getOpenIdByCode($code);
        
        if ($openid) {
            $_SESSION['user_openid'] = $openid;
            return $openid;
        }
    }
    
    return null;
}

// 获取当前页面URL
function getCurrentUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
}

// 检查授权，如果在微信浏览器中则尝试获取openid
$openid = handleWechatAuth();
if (!$openid && isWeChatBrowser()) {
    $currentUrl = getCurrentUrl();
    $authUrl = getOAuthUrl($currentUrl, 'snsapi_base');
    header('Location: ' . $authUrl);
    exit();
}

// 渲染页面
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $BUSINESS_CONFIG ? htmlspecialchars($BUSINESS_CONFIG['name']) : '支付页面'; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'PingFang SC', 'Microsoft YaHei', sans-serif;
            min-height: 100vh;
            color: #fff;
            background: <?php echo $BUSINESS_CONFIG ? htmlspecialchars($BUSINESS_CONFIG['theme']['background']) : 'linear-gradient(135deg, #0f0f23 0%, #1a1a3e 50%, #0f0f23 100%)'; ?>;
        }
        
        .particles {
            position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
            pointer-events: none; overflow: hidden; z-index: 0;
        }
        
        .particle {
            position: absolute; width: 4px; height: 4px; 
            border-radius: 50%;
            animation: float 15s infinite ease-in-out;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0) translateX(0); opacity: 0.6; }
            50% { transform: translateY(-100px) translateX(50px); opacity: 1; }
        }
        
        nav {
            position: fixed; top: 0; left: 0; right: 0; z-index: 100;
            background: rgba(15, 15, 35, 0.9); backdrop-filter: blur(20px);
            padding: 15px 30px; display: flex; justify-content: space-between; align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .logo {
            font-size: 24px; font-weight: bold;
        }
        
        .hero {
            padding: 100px 30px 40px; text-align: center; position: relative; z-index: 1;
        }
        
        .hero h1 {
            font-size: 36px; font-weight: bold; margin-bottom: 15px;
        }
        
        .hero p { font-size: 16px; color: rgba(255,255,255,0.7); margin-bottom: 30px; }
        
        .stats {
            display: flex; justify-content: center; gap: 40px; margin-bottom: 40px;
        }
        
        .stat-item { text-align: center; }
        .stat-number {
            font-size: 28px; font-weight: bold;
        }
        .stat-label { font-size: 12px; color: rgba(255,255,255,0.6); margin-top: 5px; }
        
        .countdown {
            background: rgba(255, 107, 157, 0.1); border-radius: 10px; padding: 15px;
            text-align: center; margin-bottom: 40px; border: 1px solid rgba(255, 107, 157, 0.3);
        }
        
        .countdown-title { font-size: 14px; margin-bottom: 10px; }
        
        .countdown-timer { display: flex; justify-content: center; gap: 10px; }
        
        .countdown-item {
            background: rgba(255,255,255,0.1); padding: 8px 15px; border-radius: 8px; min-width: 50px;
        }
        
        .countdown-number { font-size: 20px; font-weight: bold; }
        .countdown-label { font-size: 9px; color: rgba(255,255,255,0.6); }
        
        .pricing-section { padding: 40px 30px; position: relative; z-index: 1; }
        
        .section-title { text-align: center; font-size: 26px; margin-bottom: 8px; }
        .section-subtitle { text-align: center; color: rgba(255,255,255,0.6); margin-bottom: 40px; }
        
        .pricing-cards {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px; max-width: 1000px; margin: 0 auto;
        }
        
        .pricing-card {
            background: rgba(255,255,255,0.05); border-radius: 15px; padding: 30px 25px;
            position: relative; transition: all 0.3s; border: 2px solid transparent;
        }
        
        .pricing-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.2);
        }
        
        .badge {
            position: absolute; top: -8px; right: 25px;
            padding: 4px 12px; border-radius: 15px; font-size: 11px; font-weight: bold;
        }
        
        .card-title { font-size: 20px; font-weight: bold; margin-bottom: 8px; }
        .card-desc { color: rgba(255,255,255,0.6); font-size: 12px; margin-bottom: 25px; }
        
        .price { margin-bottom: 25px; }
        
        .price-value {
            font-size: 40px; font-weight: bold;
        }
        
        .price-unit { font-size: 14px; color: rgba(255,255,255,0.6); margin-left: 3px; }
        .original-price {
            text-decoration: line-through; color: rgba(255,255,255,0.4); font-size: 12px; margin-left: 8px;
        }
        
        .features { list-style: none; margin-bottom: 25px; }
        
        .feature-item {
            padding: 8px 0; display: flex; align-items: center; gap: 8px;
            color: rgba(255,255,255,0.8); font-size: 12px;
        }
        
        .feature-item::before { content: '✓'; font-weight: bold; }
        
        .buy-btn {
            width: 100%; padding: 12px; border: none; border-radius: 25px;
            font-size: 14px; font-weight: bold; cursor: pointer; transition: all 0.3s;
            color: #fff;
        }
        
        .buy-btn:hover { transform: scale(1.03); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
        
        .testimonials { padding: 40px 30px; position: relative; z-index: 1; }
        
        .testimonials-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px; max-width: 1000px; margin: 0 auto;
        }
        
        .testimonial-card {
            background: rgba(255,255,255,0.05); border-radius: 12px; padding: 20px;
            transition: all 0.3s;
        }
        
        .testimonial-card:hover { transform: translateY(-5px); }
        
        .testimonial-content {
            font-size: 12px; color: rgba(255,255,255,0.8); margin-bottom: 15px; line-height: 1.6;
        }
        
        .testimonial-author { display: flex; align-items: center; gap: 12px; }
        
        .author-avatar {
            width: 35px; height: 35px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 14px;
        }
        
        .author-info { font-size: 12px; }
        .author-name { font-weight: bold; }
        .author-title { color: rgba(255,255,255,0.5); font-size: 10px; }
        
        .faq { padding: 40px 30px; position: relative; z-index: 1; }
        
        .faq-container { max-width: 600px; margin: 0 auto; }
        
        .faq-item {
            background: rgba(255,255,255,0.05); border-radius: 8px; margin-bottom: 10px; overflow: hidden;
        }
        
        .faq-question {
            padding: 15px; display: flex; justify-content: space-between; align-items: center;
            cursor: pointer; font-weight: bold; font-size: 13px; transition: background 0.3s;
        }
        
        .faq-question:hover { background: rgba(255,255,255,0.1); }
        
        .faq-question::after { content: '+'; font-size: 16px; }
        .faq-question.active::after { content: '-'; }
        
        .faq-answer {
            padding: 0 15px; color: rgba(255,255,255,0.6); font-size: 12px; line-height: 1.5;
            max-height: 0; overflow: hidden; transition: all 0.3s;
        }
        
        .faq-answer.active { padding-bottom: 15px; max-height: 150px; }
        
        footer {
            padding: 30px; text-align: center; border-top: 1px solid rgba(255,255,255,0.1);
            position: relative; z-index: 1;
        }
        
        .footer-text { color: rgba(255,255,255,0.5); font-size: 12px; }
        
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.8); backdrop-filter: blur(10px); z-index: 1000;
            justify-content: center; align-items: center;
        }
        
        .modal-overlay.show { display: flex; }
        
        .payment-modal {
            background: linear-gradient(135deg, #1a1a3e, #0f0f23); border-radius: 15px;
            padding: 30px; max-width: 400px; width: 90%;
            border: 1px solid rgba(100,200,255,0.3);
            box-shadow: 0 15px 40px rgba(100,200,255,0.2);
        }
        
        .modal-title { font-size: 20px; font-weight: bold; text-align: center; margin-bottom: 8px; }
        .modal-subtitle { text-align: center; color: rgba(255,255,255,0.6); margin-bottom: 25px; font-size: 13px; }
        
        .order-summary {
            background: rgba(255,255,255,0.05); border-radius: 12px; padding: 15px; margin-bottom: 25px;
        }
        
        .order-item {
            display: flex; justify-content: space-between; padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.1); font-size: 13px;
        }
        
        .order-item:last-child { border-bottom: none; font-weight: bold; }
        
        .order-label { color: rgba(255,255,255,0.6); }
        
        .payment-btn {
            width: 100%; padding: 15px; border: none; border-radius: 25px;
            font-size: 16px; font-weight: bold; cursor: pointer; transition: all 0.3s;
            color: #fff;
            margin-bottom: 12px;
        }
        
        .payment-btn:hover { transform: scale(1.02); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }
        
        .payment-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }
        
        .cancel-btn {
            width: 100%; padding: 12px; border: 1px solid rgba(255,255,255,0.3);
            border-radius: 25px; font-size: 14px; cursor: pointer;
            background: transparent; color: rgba(255,255,255,0.8);
            transition: all 0.3s;
        }
        
        .cancel-btn:hover { background: rgba(255,255,255,0.1); }
        
        .success-modal { text-align: center; }
        
        .success-icon {
            width: 70px; height: 70px; border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 15px; font-size: 35px;
        }
        
        .order-no {
            margin-top: 15px; padding: 15px; background: rgba(100,200,255,0.1);
            border-radius: 8px; font-size: 12px;
        }
        
        @media (max-width: 600px) {
            .hero h1 { font-size: 28px; }
            .stats { gap: 25px; }
            .stat-number { font-size: 22px; }
            .pricing-card.featured { transform: none; }
        }
    </style>
</head>
<body>
    <div id="app">
        <div class="loading" style="text-align: center; padding: 50px;">
            <div class="spinner" style="width: 40px; height: 40px; border: 3px solid rgba(255,255,255,0.3); border-top-color: #64c8ff; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p>加载中...</p>
        </div>
    </div>

    <script>
        // 配置数据
        const BUSINESS_ID = '<?php echo $BUSINESS_ID; ?>';
        const CONFIG = <?php echo json_encode($CONFIG, JSON_UNESCAPED_UNICODE); ?>;
        const BUSINESS_CONFIG = <?php echo json_encode($BUSINESS_CONFIG, JSON_UNESCAPED_UNICODE); ?>;
        
        // 用户openid
        const OPENID = '<?php echo $openid; ?>';
        
        // 加载页面
        function loadPage() {
            if (!BUSINESS_CONFIG) {
                alert('未找到业务配置');
                return;
            }
            
            renderPage(BUSINESS_CONFIG);
        }
        
        // HEX转RGB
        function hexToRgb(hex) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            if (result) {
                return `${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}`;
            }
            return '255, 255, 255';
        }
        
        // 渲染页面
        function renderPage(business) {
            const app = document.getElementById('app');
            const theme = business.theme;
            
            // 设置CSS变量
            document.documentElement.style.setProperty('--primary-color', theme.primary);
            document.documentElement.style.setProperty('--secondary-color', theme.secondary);
            document.documentElement.style.setProperty('--gradient-color', `linear-gradient(90deg, ${theme.primary}, ${theme.secondary})`);
            
            // 渲染HTML
            app.innerHTML = `
                <div class="particles" id="particles"></div>
                
                <nav>
                    <div class="logo" style="background: var(--gradient-color); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">${business.logo}</div>
                    <div style="color: rgba(255,255,255,0.5); font-size: 12px;">已登录</div>
                </nav>

                <section class="hero">
                    <h1 style="background: var(--gradient-color); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-size: 200% auto; animation: shimmer 3s linear infinite;">${business.title}</h1>
                    <p>${business.subtitle}</p>
                    
                    <div class="stats">
                        ${business.stats.map(stat => `
                            <div class="stat-item">
                                <div class="stat-number" style="background: var(--gradient-color); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">
                                    <span class="animate-number" data-target="${stat.number}">0</span>
                                </div>
                                <div class="stat-label">${stat.label}</div>
                            </div>
                        `).join('')}
                    </div>
                </section>

                <section class="countdown">
                    <div class="countdown-title" style="color: var(--secondary-color);">限时特惠 · 最后 <span id="countdown-text"></span></div>
                    <div class="countdown-timer">
                        <div class="countdown-item">
                            <div class="countdown-number" id="cd-hours">23</div>
                            <div class="countdown-label">时</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="cd-minutes">59</div>
                            <div class="countdown-label">分</div>
                        </div>
                        <div class="countdown-item">
                            <div class="countdown-number" id="cd-seconds">59</div>
                            <div class="countdown-label">秒</div>
                        </div>
                    </div>
                </section>

                <section class="pricing-section">
                    <h2 class="section-title">选择您的套餐</h2>
                    <p class="section-subtitle">解锁全部功能</p>
                    
                    <div class="pricing-cards">
                        ${business.products.map(product => `
                            <div class="pricing-card ${product.featured ? 'featured' : ''}" style="${product.featured ? 'border-color: ' + theme.primary + '; background: linear-gradient(135deg, rgba(' + hexToRgb(theme.primary) + ',0.15), rgba(' + hexToRgb(theme.secondary) + ',0.15))' : ''}">
                                ${product.badge ? `<div class="badge" style="background: var(--gradient-color);">${product.badge}</div>` : ''}
                                <div class="card-title">${product.name}</div>
                                <div class="card-desc">${product.description}</div>
                                <div class="price">
                                    <span class="price-value" style="background: var(--gradient-color); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">${product.price}</span>
                                    <span class="price-unit">元/${product.period}</span>
                                    ${product.original_price ? `<span class="original-price">原价${product.original_price}元</span>` : ''}
                                </div>
                                <ul class="features">
                                    ${product.features.map(feature => `
                                        <li class="feature-item" style="--primary-color: ${theme.primary}">${feature}</li>
                                    `).join('')}
                                </ul>
                                <button class="buy-btn" style="background: var(--gradient-color);" onclick="openPaymentModal('${product.id}', ${product.price})">立即开通</button>
                            </div>
                        `).join('')}
                    </div>
                </section>

                ${business.testimonials && business.testimonials.length > 0 ? `
                <section class="testimonials">
                    <h2 class="section-title">用户好评</h2>
                    <p class="section-subtitle">听听他们怎么说</p>
                    
                    <div class="testimonials-grid">
                        ${business.testimonials.map(testimonial => `
                            <div class="testimonial-card">
                                <div class="testimonial-content">"${testimonial.content}"</div>
                                <div class="testimonial-author">
                                    <div class="author-avatar" style="background: var(--gradient-color);">${testimonial.author.charAt(0)}</div>
                                    <div class="author-info">
                                        <div class="author-name">${testimonial.author}</div>
                                        <div class="author-title">${testimonial.title}</div>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </section>
                ` : ''}

                ${business.faqs && business.faqs.length > 0 ? `
                <section class="faq">
                    <h2 class="section-title">常见问题</h2>
                    <div class="faq-container">
                        ${business.faqs.map((faq, index) => `
                            <div class="faq-item">
                                <div class="faq-question" onclick="toggleFaq(this)" style="--primary-color: ${theme.primary}">${faq.question}</div>
                                <div class="faq-answer">${faq.answer}</div>
                            </div>
                        `).join('')}
                    </div>
                </section>
                ` : ''}

                <footer>
                    <div class="footer-text">2024 ${business.name} - 专业服务平台</div>
                </footer>

                <div class="modal-overlay" id="payment-modal">
                    <div class="payment-modal">
                        <h3 class="modal-title">确认付款</h3>
                        <p class="modal-subtitle">请确认您的订单信息</p>
                        
                        <div class="order-summary">
                            <div class="order-item">
                                <span class="order-label">商品名称</span>
                                <span class="order-value" id="order-product" style="color: var(--primary-color);">套餐</span>
                            </div>
                            <div class="order-item">
                                <span class="order-label">有效期</span>
                                <span class="order-value" id="order-period" style="color: var(--primary-color);">1个月</span>
                            </div>
                            <div class="order-item">
                                <span class="order-label">应付金额</span>
                                <span class="order-value" id="order-amount" style="color: var(--primary-color);">¥0.00</span>
                            </div>
                        </div>

                        <button class="payment-btn" id="pay-btn" onclick="submitPayment()" style="background: var(--gradient-color);">确认支付</button>
                        <button class="cancel-btn" onclick="closePaymentModal()">取消</button>
                    </div>
                </div>

                <div class="modal-overlay" id="success-modal">
                    <div class="payment-modal success-modal">
                        <div class="success-icon" style="background: var(--gradient-color);">✓</div>
                        <h3 class="modal-title">支付成功！</h3>
                        <p class="modal-subtitle">恭喜您成功开通</p>
                        <div class="order-no" style="color: var(--primary-color);">订单号: <span id="success-order-no"></span></div>
                        <button class="payment-btn" onclick="closeSuccessModal()" style="background: var(--gradient-color);">开始使用</button>
                    </div>
                </div>
            `;
            
            // 初始化功能
            createParticles(business);
            startCountdown();
            if (CONFIG.ui.animate_numbers) {
                animateNumbers();
            }
        }
        
        // 生成粒子背景
        function createParticles(business) {
            const container = document.getElementById('particles');
            const count = CONFIG.ui.particle_count || 40;
            
            for (let i = 0; i < count; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                particle.style.animationDelay = Math.random() * 10 + 's';
                particle.style.animationDuration = (10 + Math.random() * 10) + 's';
                particle.style.background = `rgba(${hexToRgb(business.theme.primary)}, 0.6)`;
                container.appendChild(particle);
            }
        }
        
        // 倒计时
        function startCountdown() {
            const hours = CONFIG.ui.countdown_hours || 24;
            const targetTime = new Date().getTime() + hours * 60 * 60 * 1000;
            
            function update() {
                const now = new Date().getTime();
                const distance = targetTime - now;
                const hoursLeft = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutesLeft = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const secondsLeft = Math.floor((distance % (1000 * 60)) / 1000);
                
                const hoursEl = document.getElementById('cd-hours');
                const minutesEl = document.getElementById('cd-minutes');
                const secondsEl = document.getElementById('cd-seconds');
                
                if (hoursEl) hoursEl.textContent = String(hoursLeft).padStart(2, '0');
                if (minutesEl) minutesEl.textContent = String(minutesLeft).padStart(2, '0');
                if (secondsEl) secondsEl.textContent = String(secondsLeft).padStart(2, '0');
                
                if (distance > 0) requestAnimationFrame(update);
            }
            update();
        }
        
        // 数字动画
        function animateNumbers() {
            const elements = document.querySelectorAll('.animate-number');
            
            elements.forEach(element => {
                const target = parseFloat(element.dataset.target);
                animate(element, target, 2000);
            });
            
            function animate(element, target, duration) {
                const start = 0;
                const startTime = performance.now();
                
                function update(currentTime) {
                    const elapsed = currentTime - startTime;
                    const progress = Math.min(elapsed / duration, 1);
                    const current = start + (target - start) * progress;
                    
                    if (target >= 10000) {
                        element.textContent = Math.floor(current / 10000) + '万+';
                    } else if (target >= 1000) {
                        element.textContent = Math.floor(current).toLocaleString() + '+';
                    } else if (Number.isInteger(target)) {
                        element.textContent = Math.floor(current);
                    } else {
                        element.textContent = current.toFixed(1);
                    }
                    
                    if (progress < 1) requestAnimationFrame(update);
                }
                requestAnimationFrame(update);
            }
        }
        
        // FAQ切换
        function toggleFaq(element) {
            element.classList.toggle('active');
            element.nextElementSibling.classList.toggle('active');
        }
        
        // 支付相关
        let currentProduct = '';
        let currentAmount = 0;

        function openPaymentModal(productId, amount) {
            currentProduct = productId;
            currentAmount = amount;
            
            const product = BUSINESS_CONFIG.products.find(p => p.id === productId);
            
            if (product) {
                document.getElementById('order-product').textContent = product.name;
                document.getElementById('order-period').textContent = '1' + product.period;
                document.getElementById('order-amount').textContent = CONFIG.payment.currency_symbol + amount.toFixed(2);
            }
            
            document.getElementById('payment-modal').classList.add('show');
        }

        function closePaymentModal() {
            document.getElementById('payment-modal').classList.remove('show');
        }

        function closeSuccessModal() {
            document.getElementById('success-modal').classList.remove('show');
        }

        async function submitPayment() {
            const payBtn = document.getElementById('pay-btn');
            payBtn.disabled = true;
            payBtn.textContent = '处理中...';
            
            try {
                console.log('当前产品:', currentProduct);
                console.log('当前金额(元):', currentAmount);
                
                const amountInCents = Math.round(currentAmount * 100);
                console.log('转换为分:', amountInCents);
                
                const response = await fetch('payment_page.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'create_order',
                        business_id: BUSINESS_ID,
                        product_id: currentProduct,
                        amount: amountInCents
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('success-order-no').textContent = result.data.order_no;
                    document.getElementById('payment-modal').classList.remove('show');
                    document.getElementById('success-modal').classList.add('show');
                    
                    // 模拟支付完成
                    setTimeout(async () => {
                        await fetch('pay_standalone.php?action=mock_payment', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ order_no: result.data.order_no })
                        });
                    }, 1000);
                } else {
                    alert(result.message || '支付请求失败');
                }
            } catch (error) {
                console.error('支付请求失败:', error);
                alert('支付请求失败，请重试');
            } finally {
                payBtn.disabled = false;
                payBtn.textContent = '确认支付';
            }
        }
        
        // 页面加载
        loadPage();
    </script>
</body>
</html>
