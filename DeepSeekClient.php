<?php
class DeepSeekClient {
    private $apiKey;
    private $apiUrl;
    private $logger;
    
    public function __construct($logger = null) {
        $this->apiKey = Config::DEEPSEEK_API_KEY();
        $this->apiUrl = Config::DEEPSEEK_API_URL();
        $this->logger = $logger ?? new Logger();
    }
    
    /**
     * 检查API配置是否完整
     */
    private function checkApiConfig() {
        if (empty($this->apiKey)) {
            throw new Exception("API Key 未配置");
        }
        if (empty($this->apiUrl)) {
            throw new Exception("API URL 未配置");
        }
        if (empty(Config::DEEPSEEK_MODEL())) {
            throw new Exception("API Model 未配置");
        }
    }
    
    /**
     * 优化的API调用 - 支持大内容
     */
    public function callApi($prompt, $systemMessage = null, $temperature = null) {
        $temperature = $temperature ?? Config::TEMPERATURE;
        
        $messages = [];
        
        if ($systemMessage) {
            $messages[] = [
                'role' => 'system',
                'content' => $systemMessage
            ];
        }
        
        $messages[] = [
            'role' => 'user',
            'content' => $prompt
        ];
        
        $data = [
            'model' => Config::DEEPSEEK_MODEL(),
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => 8000, // 合理限制
            'stream' => false
        ];
        
        $this->logger->debug("API请求，提示词长度: " . mb_strlen($prompt));
        
        try {
            // 检查API配置
            $this->checkApiConfig();
            
            $ch = curl_init();
            
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->apiUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data),
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 1200,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'NovelScriptConverter/1.0'
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                throw new Exception("cURL错误: " . $error);
            }
            
            if ($httpCode !== 200) {
                // 记录详细的错误信息
                $this->logger->error("API返回错误码: " . $httpCode . ", 响应: " . $response);
                throw new Exception("HTTP错误: " . $httpCode . "，请检查API配置和网络连接");
            }
            
            $result = json_decode($response, true);
            
            if (!isset($result['choices'][0]['message']['content'])) {
                throw new Exception("API响应格式错误");
            }
            
            $content = $result['choices'][0]['message']['content'];
            $this->logger->info("API调用成功，返回长度: " . mb_strlen($content));
            
            return $content;
            
        } catch (Exception $e) {
            $this->logger->error("API调用失败: " . $e->getMessage());
            throw $e;
        }
    }
}
?>
