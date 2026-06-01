<?php

/**
 * 仿DeepSeek 聊天API接口 - 支持多轮对话版本
 * 支持两种方式：
 * 1. 免费接口：URL参数方式：https://text.pollinations.ai/{prompt}    
 * 2. DeepSeek API格式：POST JSON数据 
 * 新增功能：全自动多轮对话支持
 */

// 允许跨域访问 
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 设置默认响应类型为JSON 
header('Content-Type: application/json');

// 处理 OPTIONS 请求（预检请求）
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 会话存储配置（生产环境建议使用数据库或Redis）
define('SESSION_DIR', __DIR__ . '/sessions/');
if (!file_exists(SESSION_DIR)) {
    mkdir(SESSION_DIR, 0755, true);
}

// 会话配置 
define('SESSION_TIMEOUT', 3600); // 1小时超时
define('MAX_HISTORY_LENGTH', 20); // 最大历史记录条数
define('MAX_CONTEXT_TOKENS', 4000); // 最大上下文token数

/**
 * 会话管理类 - 处理多轮对话上下文 
 */
class SessionManager
{
    private $sessionId;
    private $sessionFile;

    public function __construct($sessionId = null)
    {
        if ($sessionId) {
            $this->sessionId = $this->sanitizeSessionId($sessionId);
        } else {
            $this->sessionId = $this->generateSessionId();
        }
        $this->sessionFile = SESSION_DIR . $this->sessionId . '.json';
    }

    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * 加载会话数据
     */
    public function loadSession()
    {
        if (!file_exists($this->sessionFile)) {
            return $this->createNewSession();
        }

        $data = json_decode(file_get_contents($this->sessionFile), true);
        if (!$data) {
            return $this->createNewSession();
        }

        // 检查会话是否过期
        if (time() - $data['last_activity'] > SESSION_TIMEOUT) {
            return $this->createNewSession();
        }

        return $data;
    }

    /**
     * 保存会话数据
     */
    public function saveSession($data)
    {
        $data['last_activity'] = time();
        $data['session_id'] = $this->sessionId;

        // 限制历史记录长度 
        if (count($data['history']) > MAX_HISTORY_LENGTH) {
            $data['history'] = array_slice($data['history'], -MAX_HISTORY_LENGTH);
        }

        file_put_contents($this->sessionFile, json_encode($data, JSON_PRETTY_PRINT));
        return $data;
    }

    /**
     * 清理过期会话
     */
    public function cleanupExpiredSessions()
    {
        $files = glob(SESSION_DIR . '*.json');
        $now = time();
        $cleaned = 0;

        foreach ($files as $file) {
            $data = json_decode(file_get_contents($file), true);
            if ($data && ($now - $data['last_activity'] > SESSION_TIMEOUT)) {
                unlink($file);
                $cleaned++;
            }
        }

        return $cleaned;
    }

    private function createNewSession()
    {
        return [
            'session_id' => $this->sessionId,
            'created_at' => time(),
            'last_activity' => time(),
            'history' => [],
            'context_summary' => '',
            'total_tokens' => 0
        ];
    }

    private function generateSessionId()
    {
        return 'sess_' . md5(uniqid() . microtime() . $_SERVER['REMOTE_ADDR']);
    }

    private function sanitizeSessionId($id)
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $id);
    }
}

/**
 * 上下文管理器 - 处理对话历史和上下文 
 */
class ContextManager
{
    private $sessionData;
    private $maxTokens;

    public function __construct($sessionData, $maxTokens = MAX_CONTEXT_TOKENS)
    {
        $this->sessionData = $sessionData;
        $this->maxTokens = $maxTokens;
    }

    /**
     * 添加新的对话轮次
     */
    public function addConversationTurn($userMessage, $assistantResponse)
    {
        $newTurn = [
            'user' => $userMessage,
            'assistant' => $assistantResponse,
            'timestamp' => time(),
            'tokens' => strlen($userMessage) + strlen($assistantResponse)
        ];

        $this->sessionData['history'][] = $newTurn;
        $this->sessionData['total_tokens'] += $newTurn['tokens'];

        // 如果超过token限制，清理最早的历史
        while ($this->sessionData['total_tokens'] > $this->maxTokens && count($this->sessionData['history']) > 1) {
            $removed = array_shift($this->sessionData['history']);
            $this->sessionData['total_tokens'] -= $removed['tokens'];
        }

        // 更新上下文摘要
        $this->updateContextSummary();

        return $this->sessionData;
    }

    /**
     * 构建当前对话的完整上下文 
     */
    public function buildFullContext($currentPrompt)
    {
        if (empty($this->sessionData['history'])) {
            return $currentPrompt;
        }

        $context = "当前对话上下文：\n";

        // 添加上下文摘要（如果有）
        if (!empty($this->sessionData['context_summary'])) {
            $context .= "对话摘要：" . $this->sessionData['context_summary'] . "\n\n";
        }

        // 添加最近的历史记录 
        $context .= "最近对话历史：\n";
        foreach ($this->sessionData['history'] as $index => $turn) {
            $context .= sprintf(
                "%d. 用户: %s\n   助手: %s\n\n",
                $index + 1,
                substr($turn['user'], 0, 200), // 限制长度 
                substr($turn['assistant'], 0, 300)
            );
        }

        $context .= "当前问题：{$currentPrompt}";

        return $context;
    }

    /**
     * 更新上下文摘要（简化版，实际可集成AI摘要功能）
     */
    private function updateContextSummary()
    {
        if (count($this->sessionData['history']) <= 3) {
            $this->sessionData['context_summary'] = '';
            return;
        }

        // 简单的关键词提取和摘要生成 
        $recentTurns = array_slice($this->sessionData['history'], -3);
        $topics = [];

        foreach ($recentTurns as $turn) {
            // 简单的关键词提取（实际应用中可使用更复杂的NLP处理）
            $words = preg_split('/\s+/', $turn['user'] . ' ' . $turn['assistant']);
            $keywords = array_filter($words, function ($word) {
                return strlen($word) > 3 && !in_array(strtolower($word), ['这个', '那个', '是的', '不是', '可以', '不能']);
            });

            $topics = array_merge($topics, array_slice($keywords, 0, 3));
        }

        $topics = array_unique($topics);
        $this->sessionData['context_summary'] = '讨论主题：' . implode('、', array_slice($topics, 0, 5));
    }

    /**
     * 清除对话历史
     */
    public function clearHistory()
    {
        $this->sessionData['history'] = [];
        $this->sessionData['context_summary'] = '';
        $this->sessionData['total_tokens'] = 0;
        return $this->sessionData;
    }
}

/**
 * 从请求中提取 prompt 内容（增强版，支持会话ID）
 */
function extractPromptFromRequest()
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '';
    $rawInput = file_get_contents('php://input');

    // 初始化结果 
    $result = [
        'prompt' => '',
        'type' => 'unknown',
        'session_id' => null,
        'reset_conversation' => false,
        'original_data' => []
    ];

    // 从Header或参数获取会话ID 
    $result['session_id'] = $_GET['session_id'] ??
        $_POST['session_id'] ??
        ($_SERVER['HTTP_X_SESSION_ID'] ?? null);

    // 检查是否要重置对话 
    $result['reset_conversation'] = isset($_GET['reset']) ||
        isset($_POST['reset']) ||
        (isset($_SERVER['HTTP_X_RESET_CONVERSATION']) &&
            $_SERVER['HTTP_X_RESET_CONVERSATION'] === 'true');

    // DeepSeek API 标准格式处理 
    if (strpos($contentType, 'application/json') !== false || json_decode($rawInput) !== null) {
        $jsonData = json_decode($rawInput, true);

        if ($jsonData && is_array($jsonData)) {
            $result['original_data'] = $jsonData;

            // 从JSON中获取会话ID和重置标志 
            if (isset($jsonData['session_id'])) {
                $result['session_id'] = $jsonData['session_id'];
            }
            if (isset($jsonData['reset_conversation'])) {
                $result['reset_conversation'] = (bool)$jsonData['reset_conversation'];
            }

            // DeepSeek 聊天格式 
            if (isset($jsonData['messages']) && is_array($jsonData['messages'])) {
                $messages = $jsonData['messages'];
                $lastUserMessage = '';

                foreach (array_reverse($messages) as $message) {
                    if (
                        isset($message['role']) && $message['role'] === 'user' &&
                        isset($message['content']) && is_string($message['content'])
                    ) {
                        $lastUserMessage = trim($message['content']);
                        break;
                    }
                }

                if (!empty($lastUserMessage)) {
                    $result['prompt'] = $lastUserMessage;
                    $result['type'] = 'deepseek_api';
                }
            }

            // 其他字段（如果上面没有找到）
            elseif (isset($jsonData['prompt'])) {
                $result['prompt'] = trim($jsonData['prompt']);
                $result['type'] = 'direct_prompt';
            } elseif (isset($jsonData['content'])) {
                $result['prompt'] = trim($jsonData['content']);
                $result['type'] = 'direct_content';
            } elseif (isset($jsonData['input'])) {
                $result['prompt'] = trim($jsonData['input']);
                $result['type'] = 'direct_input';
            }
        }
    }

    // 其他请求方式的处理（保持原有逻辑）
    if (empty($result['prompt'])) {
        // GET请求
        if ($method === 'GET') {
            $requestUri = $_SERVER['REQUEST_URI'] ?? '';
            $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
            $pathInfo = str_replace($scriptName, '', $requestUri);
            $pathInfo = trim($pathInfo, '/');

            if (!empty($pathInfo)) {
                $pathPrompt = urldecode($pathInfo);
                if (!empty($pathPrompt)) {
                    $result['prompt'] = $pathPrompt;
                    $result['type'] = 'url_path';
                }
            }

            if (empty($result['prompt'])) {
                foreach (['prompt', 'content', 'q'] as $key) {
                    if (isset($_GET[$key]) && is_string($_GET[$key])) {
                        $result['prompt'] = trim($_GET[$key]);
                        $result['type'] = 'get_' . $key;
                        break;
                    }
                }
            }
        }

        // POST请求
        if ($method === 'POST' && empty($result['prompt'])) {
            foreach (['prompt', 'content'] as $key) {
                if (isset($_POST[$key]) && is_string($_POST[$key])) {
                    $result['prompt'] = trim($_POST[$key]);
                    $result['type'] = 'post_' . $key;
                    break;
                }
            }
        }

        // 原始文本
        if (empty($result['prompt']) && !empty($rawInput) && json_decode($rawInput) === null) {
            $result['prompt'] = trim($rawInput);
            $result['type'] = 'raw_text';
        }
    }

    return $result;
}

// 其他辅助函数（保持原有实现）
function sanitizePrompt($prompt)
{
    if (empty($prompt)) return '';
    $prompt = trim($prompt);
    if (strlen($prompt) > 4000) {
        $prompt = substr($prompt, 0, 4000);
    }
    return $prompt;
}

function generateDeepSeekResponse($success, $data = [], $error = null)
{
    $response = [
        'id' => 'chatcmpl_' . uniqid(),
        'object' => 'chat.completion',
        'created' => time(),
        'model' => 'deepseek-chat',
        'choices' => [],
        'usage' => [
            'prompt_tokens' => $data['prompt_tokens'] ?? 0,
            'completion_tokens' => $data['completion_tokens'] ?? 0,
            'total_tokens' => ($data['prompt_tokens'] ?? 0) + ($data['completion_tokens'] ?? 0)
        ]
    ];

    if ($success) {
        $response['choices'][] = [
            'index' => 0,
            'message' => [
                'role' => 'assistant',
                'content' => $data['content'] ?? '',
                'image_url' => $data['image_url'] ?? null
            ],
            'finish_reason' => 'stop'
        ];
    } else {
        $response['error'] = [
            'message' => $error['message'] ?? 'Unknown error',
            'type' => $error['type'] ?? 'api_error',
            'code' => $error['code'] ?? 500
        ];
    }

    // 添加多轮对话相关信息 
    $response['conversation'] = [
        'session_id' => $data['session_id'] ?? null,
        'history_count' => $data['history_count'] ?? 0,
        'total_tokens' => $data['total_tokens'] ?? 0,
        'context_summary' => $data['context_summary'] ?? '',
        'reset_available' => true
    ];

    $response['pollinations'] = [
        'prompt' => $data['prompt'] ?? '',
        'request_type' => $data['request_type'] ?? 'unknown',
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'content_type' => $data['content_type'] ?? 'text/plain'
    ];

    return $response;
}

function callPollinationsAPI($prompt)
{
    $encodedPrompt = urlencode($prompt);
    $apiUrl = "https://text.pollinations.ai/"  . $encodedPrompt;

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $apiUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'DeepSeek-API-Client/3.2',
    ]);

    $responseData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlError = curl_error($ch);

    curl_close($ch);

    return [
        'data' => $responseData,
        'http_code' => $httpCode,
        'content_type' => $contentType,
        'error' => $curlError
    ];
}

/**
 * 主处理函数 - 增强版支持多轮对话
 */
function processRequest()
{
    // 提取请求信息
    $requestInfo = extractPromptFromRequest();
    $prompt = sanitizePrompt($requestInfo['prompt'] ?? '');
    $sessionId = $requestInfo['session_id'];
    $resetConversation = $requestInfo['reset_conversation'];

    // 初始化会话管理器
    $sessionManager = new SessionManager($sessionId);
    $sessionData = $sessionManager->loadSession();

    // 如果需要重置对话
    if ($resetConversation) {
        $contextManager = new ContextManager($sessionData);
        $sessionData = $contextManager->clearHistory();
        $sessionManager->saveSession($sessionData);

        return generateDeepSeekResponse(true, [
            'prompt' => '对话已重置',
            'request_type' => $requestInfo['type'],
            'content' => '对话历史已清除，开始新的对话。',
            'session_id' => $sessionManager->getSessionId(),
            'history_count' => 0,
            'total_tokens' => 0,
            'context_summary' => '',
            'prompt_tokens' => 0,
            'completion_tokens' => 0
        ]);
    }

    if (empty($prompt)) {
        if ($requestInfo['type'] === 'unknown') {
            http_response_code(400);
            return generateDeepSeekResponse(false, [], [
                'message' => "无有效输入。使用方式：\n" .
                    "1. DeepSeek API格式：POST JSON with {\"messages\": [{\"role\": \"user\", \"content\": \"你的问题\"}]}\n" .
                    "2. 直接提问：GET /api.php/ 你的问题 或 POST with prompt=你的问题\n" .
                    "3. 多轮对话：包含 session_id 参数保持对话上下文\n" .
                    "4. 重置对话：添加 reset=true 参数或 reset_conversation=true",
                'type' => 'invalid_request_error',
                'code' => 400
            ]);
        }

        // 返回当前会话状态 
        return generateDeepSeekResponse(true, [
            'prompt' => '会话状态查询',
            'request_type' => $requestInfo['type'],
            'content' => sprintf(
                "当前会话状态：\n- 会话ID：%s\n- 历史记录：%d条\n- 总token数：%d\n- 上下文摘要：%s",
                $sessionManager->getSessionId(),
                count($sessionData['history']),
                $sessionData['total_tokens'],
                $sessionData['context_summary'] ?: '无'
            ),
            'session_id' => $sessionManager->getSessionId(),
            'history_count' => count($sessionData['history']),
            'total_tokens' => $sessionData['total_tokens'],
            'context_summary' => $sessionData['context_summary'],
            'prompt_tokens' => 0,
            'completion_tokens' => 0
        ]);
    }

    // 构建包含上下文的完整提示 
    $contextManager = new ContextManager($sessionData);
    $fullPrompt = $contextManager->buildFullContext($prompt);

    // 调用 Pollinations.ai  API
    $apiResult = callPollinationsAPI($fullPrompt);

    if ($apiResult['error']) {
        http_response_code(502);
        return generateDeepSeekResponse(false, [
            'prompt' => $prompt,
            'request_type' => $requestInfo['type'],
            'session_id' => $sessionManager->getSessionId()
        ], [
            'message' => '上游API错误: ' . $apiResult['error'],
            'type' => 'api_error',
            'code' => 502
        ]);
    }

    if ($apiResult['http_code'] !== 200) {
        http_response_code($apiResult['http_code']);
        return generateDeepSeekResponse(false, [
            'prompt' => $prompt,
            'request_type' => $requestInfo['type'],
            'session_id' => $sessionManager->getSessionId()
        ], [
            'message' => "上游API返回 HTTP {$apiResult['http_code']}",
            'type' => 'api_error',
            'code' => $apiResult['http_code']
        ]);
    }

    if (empty($apiResult['data'])) {
        http_response_code(502);
        return generateDeepSeekResponse(false, [
            'prompt' => $prompt,
            'request_type' => $requestInfo['type'],
            'session_id' => $sessionManager->getSessionId()
        ], [
            'message' => '未从上游API接收到数据',
            'type' => 'api_error',
            'code' => 502
        ]);
    }

    // 处理API响应
    $assistantResponse = $apiResult['data'];
    if (strpos($apiResult['content_type'], 'image/') !== false) {
        $base64Image = base64_encode($apiResult['data']);
        $imageUrl = "data:{$apiResult['content_type']};base64,{$base64Image}";
        $assistantResponse = "图像生成成功: " . $prompt;
    } else {
        $assistantResponse = $apiResult['data'];
    }

    // 更新对话历史
    $updatedSessionData = $contextManager->addConversationTurn($prompt, $assistantResponse);
    $sessionManager->saveSession($updatedSessionData);

    // 返回响应
    $responseData = [
        'prompt' => $prompt,
        'request_type' => $requestInfo['type'],
        'content' => $assistantResponse,
        'session_id' => $sessionManager->getSessionId(),
        'history_count' => count($updatedSessionData['history']),
        'total_tokens' => $updatedSessionData['total_tokens'],
        'context_summary' => $updatedSessionData['context_summary'],
        'prompt_tokens' => strlen($fullPrompt),
        'completion_tokens' => strlen($assistantResponse),
        'content_type' => $apiResult['content_type']
    ];

    if (isset($imageUrl)) {
        $responseData['image_url'] = $imageUrl;
    }

    return generateDeepSeekResponse(true, $responseData);
}

// 主程序执行 
try {
    // 定期清理过期会话（每100次请求执行一次）
    if (rand(1, 100) === 1) {
        $sessionManager = new SessionManager();
        $cleaned = $sessionManager->cleanupExpiredSessions();
        // error_log("清理了 {$cleaned} 个过期会话");
    }

    $response = processRequest();
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(generateDeepSeekResponse(false, [], [
        'message' => '内部服务器错误: ' . $e->getMessage(),
        'type' => 'internal_error',
        'code' => 500
    ]), JSON_UNESCAPED_UNICODE);
}

exit;
