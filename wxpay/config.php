<?php

/**
 * 微信支付配置
 * 请在生产环境中妥善保管这些信息
 */

return [

    // 商户配置
    'mch_id'        => '你的商户号',      // 商户号
    'app_id'        => '你的应用APPID',      // 应用APPID（公众号或小程序）
    // API密钥 - 在微信商户平台设置
    'api_key'       => '你的API密钥',     // API密钥，32位
    // 证书路径（退款时需要）
    'ssl_cert_path' => 'cert/apiclient_cert.pem',
    'ssl_key_path'  => 'cert/apiclient_key.pem',

    // 支付配置
    'notify_url'    => 'https://yourdomain.com/wxpay/notify.php',  // 支付结果回调地址
    'time_expire'   => '30m',  // 订单过期时间，如30分钟

    // API地址
    'api_url'       => [
        'unifiedorder' => 'https://api.mch.weixin.qq.com/pay/unifiedorder',  // 统一下单
        'orderquery'   => 'https://api.mch.weixin.qq.com/pay/orderquery',    // 查询订单
        'refund'       => 'https://api.mch.weixin.qq.com/secapi/pay/refund', // 申请退款
    ],

    // 日志配置
    'log_path'      => __DIR__ . '/logs/',
    'log_level'     => 'INFO',  // DEBUG, INFO, ERROR
];
