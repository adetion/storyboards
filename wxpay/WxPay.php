<?php

/**
 * 修复签名问题的WxPay类
 */

class WxPayFixed
{
    private $config;

    public function __construct()
    {
        $this->config = require __DIR__ . '/config.php';
        $this->validateConfig();
    }

    /**
     * 验证配置
     */
    private function validateConfig()
    {
        $required = ['app_id', 'mch_id', 'api_key'];
        foreach ($required as $key) {
            if (empty($this->config[$key])) {
                throw new Exception("配置项 {$key} 不能为空");
            }
        }

        // 验证API密钥长度
        if (strlen($this->config['api_key']) != 32) {
            throw new Exception("API密钥必须是32位，当前长度: " . strlen($this->config['api_key']));
        }
    }

    /**
     * 生成扫码支付订单
     */
    public function createNativeOrder($outTradeNo, $body, $totalFee)
    {
        try {
            // 构建参数
            $params = [
                'appid'            => $this->config['app_id'],
                'mch_id'           => $this->config['mch_id'],
                'nonce_str'        => $this->createNonceStr(),
                'body'             => $this->filterBody($body),
                'out_trade_no'     => $outTradeNo,
                'total_fee'        => intval($totalFee),
                'spbill_create_ip' => $this->getClientIp(),
                'notify_url'       => $this->config['notify_url'],
                'trade_type'       => 'NATIVE',
                'product_id'       => $outTradeNo,
            ];

            // 生成签名
            $params['sign'] = $this->makeSign($params);

            // 调试：输出签名信息
            $this->debugSign($params);

            // 转换为XML
            $xml = $this->arrayToXml($params);

            // 发送请求
            $response = $this->curlPost($xml, 'https://api.mch.weixin.qq.com/pay/unifiedorder');

            // 解析响应
            $result = $this->xmlToArray($response);

            if ($result['return_code'] == 'SUCCESS') {
                if ($result['result_code'] == 'SUCCESS') {
                    return [
                        'success'     => true,
                        'code_url'    => $result['code_url'] ?? '',
                        'prepay_id'   => $result['prepay_id'] ?? '',
                        'out_trade_no' => $outTradeNo,
                    ];
                } else {
                    return [
                        'success' => false,
                        'err_code' => $result['err_code'] ?? '',
                        'err_code_des' => $result['err_code_des'] ?? '业务失败',
                    ];
                }
            } else {
                return [
                    'success' => false,
                    'err_code_des' => $result['return_msg'] ?? '请求失败',
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'err_code_des' => $e->getMessage(),
            ];
        }
    }

    /**
     * 生成签名（修复版）
     */
    private function makeSign($params)
    {
        // 1. 过滤空值（空字符串和null）
        $params = array_filter($params, function ($value) {
            return $value !== '' && $value !== null;
        });

        // 2. 按键名ASCII码从小到大排序
        ksort($params);

        // 3. 拼接成URL键值对格式
        $string = '';
        foreach ($params as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }

        // 4. 拼接API密钥
        $string .= 'key=' . $this->config['api_key'];

        // 5. MD5加密并转为大写
        $sign = strtoupper(md5($string));

        return $sign;
    }

    /**
     * 调试签名信息
     */
    private function debugSign($params)
    {
        $debugFile = __DIR__ . '/sign_debug.log';

        // 复制参数用于调试
        $debugParams = $params;
        unset($debugParams['sign']); // 移除签名

        // 重新生成签名以便调试
        $debugParams = array_filter($debugParams);
        ksort($debugParams);

        $string = '';
        foreach ($debugParams as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string .= 'key=' . $this->config['api_key'];

        $log = "【签名调试】\n";
        $log .= "时间: " . date('Y-m-d H:i:s') . "\n";
        $log .= "API密钥: " . substr($this->config['api_key'], 0, 8) . "..." . "\n";
        $log .= "参数字符串: " . $string . "\n";
        $log .= "生成签名: " . $params['sign'] . "\n";
        $log .= "参数列表:\n";

        foreach ($debugParams as $key => $value) {
            $log .= sprintf("  %-15s = %s\n", $key, $value);
        }

        $log .= "\n";

        file_put_contents($debugFile, $log, FILE_APPEND);
    }

    /**
     * 过滤商品描述
     */
    private function filterBody($body)
    {
        // 移除特殊字符
        $body = preg_replace('/[^\x{4e00}-\x{9fa5}a-zA-Z0-9]/u', '', $body);

        // 限制长度
        $body = mb_substr($body, 0, 127, 'UTF-8');

        return $body;
    }

    /**
     * CURL请求
     */
    private function curlPost($xml, $url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        // 禁用SSL验证（本地测试）
        if ($this->isLocalEnv()) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('CURL错误: ' . curl_error($ch));
        }

        curl_close($ch);

        return $response;
    }

    /**
     * 判断是否为本地环境
     */
    private function isLocalEnv()
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        return $ip === '::1' ||
            $ip === '127.0.0.1' ||
            strpos($host, 'localhost') !== false ||
            strpos($host, '.test') !== false ||
            strpos($host, '.local') !== false;
    }

    /**
     * 获取客户端IP
     */
    private function getClientIp()
    {
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    /**
     * 创建随机字符串
     */
    private function createNonceStr($length = 32)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    /**
     * 数组转XML
     */
    private function arrayToXml($arr)
    {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= '<' . $key . '>' . $val . '</' . $key . '>';
            } else {
                $xml .= '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }

    /**
     * XML转数组
     */
    private function xmlToArray($xml)
    {
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data ?: [];
    }
}
