<?php
/**
 * 文生图API接口 - 单文件版本
 * （备用web-post模式，不通过正规API接口）
 * 兼容PHP 7.4
 */

// 配置
error_reporting(0); // 生产环境关闭错误显示
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class TextToImageAPI
{
    // API配置
    private $config = [
        'api_key' => '',
        'endpoints' => [
            'txt2img' => '',   //文生图
            'txt2role' => '',  //文生角色
            'status' => ''     //状态查询
        ],
        'polling' => [
            'max_attempts' => 30,
            'interval' => 3,
            'timeout' => 90
        ],
        'defaults' => [
            'picSize' => '16:9',
            'style' => 12,
            'count' => 1,
            'imgWidth' => 1344,
            'imgHeight' => 768
        ]
    ];

    // 比例与尺寸映射（与第二个接口一致）
    private $sizeMapping = [
        '1:1' => ['width' => 1024, 'height' => 1024],
        '16:9' => ['width' => 1344, 'height' => 768],
        '4:3' => ['width' => 1152, 'height' => 896],
        '3:2' => ['width' => 1216, 'height' => 832],
        '2:3' => ['width' => 832, 'height' => 1216],
        '3:4' => ['width' => 896, 'height' => 1152],
        '9:16' => ['width' => 768, 'height' => 1344],
        '1:2.35' => ['width' => 672, 'height' => 1600],
        '2.35:1' => ['width' => 1600, 'height' => 672]
    ];

    // 风格映射（与第二个接口一致）
    private $styleMapping = [
        11 => '动漫2.0（高质量二次元）',
        22 => '一致性通用',
        20 => '一致性动漫',
        19 => '一致性写实',
        21 => '通用3.0',
        10 => '写实2.0（或通用2.0）',
        17 => '吉卜力（宫崎骏经典）',
        18 => '古风小说（动漫风玄幻）',
        15 => '王家卫（迷离光影）',
        16 => '国风工笔（古典东方风）',
        12 => '线稿2.0',
        13 => '双城之战（大热剧集）',
        5 => '手绘动画（童书绘本）',
        6 => '3D动画',
        4 => '欧美漫画',
        7 => '国风写实'
    ];

    /**
     * 主处理函数
     */
    public function handle()
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $query = $_GET;
            $input = json_decode(file_get_contents('php://input'), true) ?: [];

            // 合并所有参数
            $params = array_merge($query, $input, $_POST);

            // 路由处理
            if ($method === 'POST') {
                if (isset($params['action'])) {
                    switch ($params['action']) {
                        case 'generate':
                            return $this->generateImage($params);
                        case 'generate-async':
                            return $this->generateImageAsync($params);
                        case 'check-status':
                            return $this->checkStatus($params);
                        case 'batch-generate':
                            return $this->batchGenerate($params);
                        default:
                            return $this->jsonResponse(400, 'Invalid action');
                    }
                } else {
                    // 默认生成（修改为与第二个接口相同的处理方式）
                    return $this->generateImage($params);
                }
            } elseif ($method === 'GET') {
                if (isset($params['taskId'])) {
                    return $this->checkStatus($params);
                } else {
                    return $this->showHelp();
                }
            } else {
                return $this->jsonResponse(405, 'Method Not Allowed');
            }
        } catch (Exception $e) {
            return $this->jsonResponse(500, 'Server Error: ' . $e->getMessage());
        }
    }

/**
 * 同步生成图片（修改为与第二个接口相同的格式）
 */
private function generateImage($params)
{
    // 验证参数（与第二个接口一致）
    $prompt = $this->getParam($params, 'prompt');
    if (empty($prompt)) {
        return $this->jsonResponse(400, 'Parameter "prompt" is required');
    }

    // 获取并验证参数（与第二个接口一致）
    $prompt = trim($prompt);
    $picSize = $this->getValidPicSize($params);
    $style = $this->getValidStyle($params);
    $count = (int)$this->getParam($params, 'count', 1);
    
    // 获取尺寸（使用第二个接口的尺寸映射）
    $sizeInfo = $this->getSizeInfo($picSize);
    $imgWidth = $sizeInfo['width'];
    $imgHeight = $sizeInfo['height'];

    // 如果count大于1，执行批量生成
    if ($count > 1) {
        // 修复：当count>1时，使用同一个prompt生成多张图片，而不是需要prompts数组
        return $this->multiGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight, $count);
    }

    // 单张生成
    return $this->singleGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight);
}

/**
 * 多张图片生成（修复：count>1时使用同一个prompt生成多张图片）
 */
private function multiGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight, $count)
{
    try {
        // 准备请求数据（直接使用count参数）
        $requestData = [
            'prompt' => $prompt,
            'picSize' => $picSize,
            'style' => $style,
            'count' => $count,
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight
        ];

        // 步骤1：创建任务
        $taskResult = $this->apiRequest('txt2img', $requestData);
        if ($taskResult['code'] !== 0 || empty($taskResult['data'])) {
            return $this->jsonResponse(500, 'Failed to create task: ' . ($taskResult['msg'] ?? 'Unknown error'));
        }

        $taskId = $taskResult['data'];
        $maxAttempts = $this->config['polling']['max_attempts'];
        $interval = $this->config['polling']['interval'];
        $timeout = $this->config['polling']['timeout'];

        // 步骤2：轮询结果
        $images = [];
        $startTime = time();
        $attempts = 0;

        while ($attempts < $maxAttempts) {
            $attempts++;

            // 检查超时
            if ((time() - $startTime) > $timeout) {
                return $this->jsonResponse(408, 'Request timeout', [
                    'prompt' => $prompt,
                    'picSize' => $picSize,
                    'style' => $style,
                    'styleName' => $this->styleMapping[$style] ?? '未知风格',
                    'imgWidth' => $imgWidth,
                    'imgHeight' => $imgHeight,
                    'count' => $count,
                    'taskId' => $taskId,
                    'status' => 'timeout',
                    'generatedCount' => count($images)
                ]);
            }

            // 查询状态
            $statusResult = $this->apiRequest('status', [
                'taskId' => $taskId,
                'type' => '1'
            ]);

            if ($statusResult['code'] !== 0) {
                return $this->jsonResponse(500, 'Failed to check status: ' . ($statusResult['msg'] ?? 'Unknown error'));
            }

            $tasks = $statusResult['data'] ?? [];
            $allDone = true;
            $currentImages = [];

            foreach ($tasks as $task) {
                $status = $task['status'] ?? 'unknown';
                
                if ($status === 'done' && !empty($task['resultUrl'])) {
                    $currentImages[] = [
                        'url' => $task['resultUrl'],
                        'id' => $task['id'] ?? '',
                        'taskId' => $task['taskId'] ?? $taskId,
                        'ratio' => $task['ratio'] ?? '16:9'
                    ];
                } elseif ($status === 'error' || $status === 'failed') {
                    return $this->jsonResponse(500, 'Task failed: ' . ($task['message'] ?? 'Unknown error'), [
                        'prompt' => $prompt,
                        'picSize' => $picSize,
                        'style' => $style,
                        'styleName' => $this->styleMapping[$style] ?? '未知风格',
                        'imgWidth' => $imgWidth,
                        'imgHeight' => $imgHeight,
                        'count' => $count,
                        'taskId' => $taskId,
                        'status' => 'failed',
                        'generatedCount' => count($images)
                    ]);
                } elseif ($status !== 'done') {
                    $allDone = false;
                }
            }

            // 更新图片列表
            $images = $currentImages;

            // 所有任务完成
            if ($allDone) {
                if (empty($images)) {
                    return $this->jsonResponse(500, 'No images generated', [
                        'prompt' => $prompt,
                        'picSize' => $picSize,
                        'style' => $style,
                        'styleName' => $this->styleMapping[$style] ?? '未知风格',
                        'imgWidth' => $imgWidth,
                        'imgHeight' => $imgHeight,
                        'count' => $count,
                        'taskId' => $taskId,
                        'status' => 'done_no_images',
                        'generatedCount' => 0
                    ]);
                }
                
                //$img=str_replace('static.chuangyi-keji.com', 'static.storyboards.cn', $images[0]['url']);
                $img = $images[0]['url'];
                
                // 返回格式与第二个接口风格一致
                $responseData = [
                    'prompt' => $prompt,
                    'englishPrompt' => $prompt, // 第一个接口不翻译，所以与prompt相同
                    'picSize' => $picSize,
                    'style' => $style,
                    'styleName' => $this->styleMapping[$style] ?? '未知风格',
                    'imgWidth' => $imgWidth,
                    'imgHeight' => $imgHeight,
                    'aspectRatio' => $picSize,
                    'taskId' => $taskId,
                    'generateStatus' => 'completed',
                    'elapsedTime' => time() - $startTime,
                    'attempts' => $attempts,
                    'totalImages' => count($images),
                    'requestedCount' => $count,
                    'images' => $images,
                    // 为了兼容性，也包含imageUrl字段（指向第一张图片）
                    'imageUrl' => !empty($img) ? $img : null,
                    'fullImageUrl' => !empty($img) ? $img : null
                ];

                return $this->jsonResponse(0, 'Success', $responseData);
            }

            // 等待
            sleep($interval);
        }

        return $this->jsonResponse(408, 'Max polling attempts reached', [
            'prompt' => $prompt,
            'picSize' => $picSize,
            'style' => $style,
            'styleName' => $this->styleMapping[$style] ?? '未知风格',
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight,
            'count' => $count,
            'taskId' => $taskId,
            'status' => 'polling_exceeded',
            'generatedCount' => count($images),
            'images' => $images
        ]);

    } catch (Exception $e) {
        return $this->jsonResponse(500, 'Generation failed: ' . $e->getMessage(), [
            'prompt' => $prompt,
            'picSize' => $picSize,
            'style' => $style,
            'styleName' => $this->styleMapping[$style] ?? '未知风格',
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight,
            'count' => $count,
            'status' => 'error'
        ]);
    }
}

    /**
     * 单张图片生成（新方法，返回格式与第二个接口一致）
     */
    private function singleGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight)
    {
        try {
            // 准备请求数据（保持原接口格式，但使用验证过的参数）
            $requestData = [
                'prompt' => $prompt,
                'picSize' => $picSize,
                'style' => $style,
                'count' => 1,
                'imgWidth' => $imgWidth,
                'imgHeight' => $imgHeight
            ];

            // 步骤1：创建任务
            $taskResult = $this->apiRequest('txt2img', $requestData);
            if ($taskResult['code'] !== 0 || empty($taskResult['data'])) {
                return $this->jsonResponse(500, 'Failed to create task: ' . ($taskResult['msg'] ?? 'Unknown error'));
            }

            $taskId = $taskResult['data'];
            $maxAttempts = $this->config['polling']['max_attempts'];
            $interval = $this->config['polling']['interval'];
            $timeout = $this->config['polling']['timeout'];

            // 步骤2：轮询结果
            $images = [];
            $startTime = time();
            $attempts = 0;

            while ($attempts < $maxAttempts) {
                $attempts++;

                // 检查超时
                if ((time() - $startTime) > $timeout) {
                    return $this->jsonResponse(408, 'Request timeout', [
                        'imageUrl' => null,
                        'prompt' => $prompt,
                        'picSize' => $picSize,
                        'style' => $style,
                        'styleName' => $this->styleMapping[$style] ?? '未知风格',
                        'imgWidth' => $imgWidth,
                        'imgHeight' => $imgHeight,
                        'taskId' => $taskId,
                        'status' => 'timeout'
                    ]);
                }

                // 查询状态
                $statusResult = $this->apiRequest('status', [
                    'taskId' => $taskId,
                    'type' => '1'
                ]);

                if ($statusResult['code'] !== 0) {
                    return $this->jsonResponse(500, 'Failed to check status: ' . ($statusResult['msg'] ?? 'Unknown error'));
                }

                $tasks = $statusResult['data'] ?? [];
                $allDone = true;

                foreach ($tasks as $task) {
                    $status = $task['status'] ?? 'unknown';
                    
                    if ($status === 'done' && !empty($task['resultUrl'])) {
                        $images[] = [
                            'url' => $task['resultUrl'],
                            'id' => $task['id'] ?? '',
                            'taskId' => $task['taskId'] ?? $taskId,
                            'ratio' => $task['ratio'] ?? '16:9'
                        ];
                    } elseif ($status === 'error' || $status === 'failed') {
                        return $this->jsonResponse(500, 'Task failed: ' . ($task['message'] ?? 'Unknown error'), [
                            'imageUrl' => null,
                            'prompt' => $prompt,
                            'picSize' => $picSize,
                            'style' => $style,
                            'styleName' => $this->styleMapping[$style] ?? '未知风格',
                            'imgWidth' => $imgWidth,
                            'imgHeight' => $imgHeight,
                            'taskId' => $taskId,
                            'status' => 'failed'
                        ]);
                    } elseif ($status !== 'done') {
                        $allDone = false;
                    }
                }

                // 所有任务完成
                if ($allDone || !empty($images)) {
                    if (empty($images)) {
                        return $this->jsonResponse(500, 'No images generated', [
                            'imageUrl' => null,
                            'prompt' => $prompt,
                            'picSize' => $picSize,
                            'style' => $style,
                            'styleName' => $this->styleMapping[$style] ?? '未知风格',
                            'imgWidth' => $imgWidth,
                            'imgHeight' => $imgHeight,
                            'taskId' => $taskId,
                            'status' => 'done_no_images'
                        ]);
                    }

                    // 返回格式与第二个接口一致
// 在 singleGenerate() 方法的返回数据中，添加以下字段：
$img=str_replace('static.chuangyi-keji.com', 'static.storyboards.cn', $images[0]['url']);
//$img = $images[0]['url'];
$responseData = [
    'imageUrl' => $img,
    'fullImageUrl' => $img, // 第一个接口没有本地存储，所以与imageUrl相同
    'prompt' => $prompt,
    'englishPrompt' => $prompt, // 第一个接口不翻译，所以与prompt相同
    'picSize' => $picSize,
    'style' => $style,
    'styleName' => $this->styleMapping[$style] ?? '未知风格',
    'imgWidth' => $imgWidth,
    'imgHeight' => $imgHeight,
    'aspectRatio' => $picSize,
    'taskId' => $taskId,
    'generateStatus' => 'completed',
    'elapsedTime' => time() - $startTime,
    'attempts' => $attempts,
    'totalImages' => 1,  // 新增：总图片数
    'allImages' => $images  // 新增：所有图片数组
];

                    return $this->jsonResponse(0, 'Success', $responseData);
                }

                // 等待
                sleep($interval);
            }

            return $this->jsonResponse(408, 'Max polling attempts reached', [
                'imageUrl' => null,
                'prompt' => $prompt,
                'picSize' => $picSize,
                'style' => $style,
                'styleName' => $this->styleMapping[$style] ?? '未知风格',
                'imgWidth' => $imgWidth,
                'imgHeight' => $imgHeight,
                'taskId' => $taskId,
                'status' => 'polling_exceeded'
            ]);

        } catch (Exception $e) {
            return $this->jsonResponse(500, 'Generation failed: ' . $e->getMessage(), [
                'imageUrl' => null,
                'prompt' => $prompt,
                'picSize' => $picSize,
                'style' => $style,
                'styleName' => $this->styleMapping[$style] ?? '未知风格',
                'imgWidth' => $imgWidth,
                'imgHeight' => $imgHeight,
                'status' => 'error'
            ]);
        }
    }

    /**
     * 异步生成图片（修改返回格式）
     */
    private function generateImageAsync($params)
    {
        $prompt = $this->getParam($params, 'prompt');
        if (empty($prompt)) {
            return $this->jsonResponse(400, 'Parameter "prompt" is required');
        }

        // 获取并验证参数
        $prompt = trim($prompt);
        $picSize = $this->getValidPicSize($params);
        $style = $this->getValidStyle($params);
        
        // 获取尺寸
        $sizeInfo = $this->getSizeInfo($picSize);
        $imgWidth = $sizeInfo['width'];
        $imgHeight = $sizeInfo['height'];

        // 创建任务
        $requestData = [
            'prompt' => $prompt,
            'picSize' => $picSize,
            'style' => $style,
            'count' => 1,
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight
        ];

        $taskResult = $this->apiRequest('txt2img', $requestData);
        if ($taskResult['code'] !== 0 || empty($taskResult['data'])) {
            return $this->jsonResponse(500, 'Failed to create task: ' . ($taskResult['msg'] ?? 'Unknown error'));
        }

        $taskId = $taskResult['data'];
        
        // 获取当前URL用于状态查询
        $baseUrl = $this->getBaseUrl();
        $checkUrl = $baseUrl . '?taskId=' . urlencode($taskId);

        // 启动后台轮询（如果有回调URL）
        $callbackUrl = $this->getParam($params, 'callbackUrl');
        if ($callbackUrl) {
            $this->startBackgroundPolling($taskId, $callbackUrl);
        }

        // 返回格式与第二个接口风格一致
        return $this->jsonResponse(0, 'Task created', [
            'taskId' => $taskId,
            'prompt' => $prompt,
            'englishPrompt' => $prompt,
            'picSize' => $picSize,
            'style' => $style,
            'styleName' => $this->styleMapping[$style] ?? '未知风格',
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight,
            'status' => 'processing',
            'checkUrl' => $checkUrl,
            'estimatedTime' => '30-90 seconds'
        ]);
    }

    /**
     * 检查任务状态（修改返回格式）
     */
    private function checkStatus($params)
    {
        $taskId = $this->getParam($params, 'taskId');
        if (empty($taskId)) {
            return $this->jsonResponse(400, 'Parameter "taskId" is required');
        }

        $statusResult = $this->apiRequest('status', [
            'taskId' => $taskId,
            'type' => '1'
        ]);

        if ($statusResult['code'] !== 0) {
            return $this->jsonResponse(500, 'Failed to check status: ' . ($statusResult['msg'] ?? 'Unknown error'));
        }

        $tasks = $statusResult['data'] ?? [];
        $images = [];
        $status = 'processing';
        $failedTasks = [];

        foreach ($tasks as $task) {
            $taskStatus = $task['status'] ?? 'unknown';
            
            if ($taskStatus === 'done' && !empty($task['resultUrl'])) {
                $images[] = [
                    'url' => $task['resultUrl'],
                    'id' => $task['id'] ?? '',
                    'taskId' => $task['taskId'] ?? $taskId,
                    'ratio' => $task['ratio'] ?? '16:9'
                ];
            } elseif ($taskStatus === 'error' || $taskStatus === 'failed') {
                $failedTasks[] = [
                    'id' => $task['id'] ?? '',
                    'message' => $task['message'] ?? 'Unknown error'
                ];
            }
        }

        // 确定总体状态
        if (!empty($images)) {
            $status = 'completed';
        } elseif (!empty($failedTasks)) {
            $status = 'failed';
        } elseif (!empty($tasks)) {
            // 检查是否有任务仍在处理中
            $allDone = true;
            foreach ($tasks as $task) {
                if (($task['status'] ?? '') !== 'done') {
                    $allDone = false;
                    break;
                }
            }
            $status = $allDone ? 'completed_no_images' : 'processing';
        }

        // 构建响应数据（与第二个接口风格一致）
        $responseData = [
            'taskId' => $taskId,
            'status' => $status,
            'images' => $images,
            'totalImages' => count($images),
            'failedTasks' => $failedTasks,
            'details' => $tasks
        ];

        // 如果有图片，添加与第二个接口一致的字段
        if (!empty($images)) {
            $responseData['imageUrl'] = $images[0]['url'];
            $responseData['fullImageUrl'] = $images[0]['url'];
        }

        return $this->jsonResponse(0, 'Status checked', $responseData);
    }

    /**
     * 批量生成图片（修改返回格式）
     */
/**
 * 批量生成图片（修改返回格式）- 这个需要prompts数组
 */
private function batchGenerate($params)
{
    $prompts = $this->getParam($params, 'prompts');
    if (empty($prompts) || !is_array($prompts)) {
        return $this->jsonResponse(400, 'Parameter "prompts" (array) is required');
    }

    $results = [];
    $errors = [];
    
    // 获取公共参数
    $picSize = $this->getValidPicSize($params);
    $style = $this->getValidStyle($params);
    $sizeInfo = $this->getSizeInfo($picSize);
    $imgWidth = $sizeInfo['width'];
    $imgHeight = $sizeInfo['height'];
    $count = (int)$this->getParam($params, 'count', 1);  // 新增：获取count参数

    foreach ($prompts as $index => $prompt) {
        if (!empty($prompt)) {
            try {
                if ($count > 1) {
                    // 每个prompt生成多张图片
                    $result = $this->multiGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight, $count);
                } else {
                    // 每个prompt生成单张图片
                    $result = $this->singleGenerate($prompt, $picSize, $style, $imgWidth, $imgHeight);
                }
                $resultData = json_decode(json_encode($result), true);
                
                $results[] = [
                    'index' => $index,
                    'prompt' => $prompt,
                    'success' => $resultData['code'] === 0,
                    'data' => $resultData['data'] ?? null,
                    'message' => $resultData['msg'] ?? ''
                ];
            } catch (Exception $e) {
                $errors[] = [
                    'index' => $index,
                    'prompt' => $prompt,
                    'error' => $e->getMessage()
                ];
            }
        }
    }

    // 返回格式与第二个接口风格一致
    return $this->jsonResponse(0, 'Batch generation completed', [
        'total' => count($prompts),
        'successful' => count($results),
        'failed' => count($errors),
        'results' => $results,
        'errors' => $errors,
        'config' => [
            'picSize' => $picSize,
            'style' => $style,
            'styleName' => $this->styleMapping[$style] ?? '未知风格',
            'imgWidth' => $imgWidth,
            'imgHeight' => $imgHeight,
            'count' => $count  // 新增：包含count参数
        ]
    ]);
}

    /**
     * 获取有效的图片比例（与第二个接口一致）
     */
    private function getValidPicSize($params)
    {
        $picSize = $this->getParam($params, 'picSize', '16:9');
        $picSize = str_replace('：', ':', $picSize);
        $picSize = str_replace(' ', '', $picSize);
        
        return isset($this->sizeMapping[$picSize]) ? $picSize : '16:9';
    }

    /**
     * 获取有效的风格（与第二个接口一致）
     */
    private function getValidStyle($params)
    {
        $style = (int)$this->getParam($params, 'style', 12);
        return isset($this->styleMapping[$style]) ? $style : 12;
    }

    /**
     * 获取尺寸信息（与第二个接口一致）
     */
    private function getSizeInfo($picSize)
    {
        return $this->sizeMapping[$picSize] ?? ['width' => 1344, 'height' => 768];
    }

    /**
     * API请求封装
     */
    private function apiRequest($endpoint, $data)
    {
        if (!isset($this->config['endpoints'][$endpoint])) {
            throw new Exception('Invalid endpoint: ' . $endpoint);
        }

        $url = $this->config['endpoints'][$endpoint];
        $headers = [
            'Authorization: ' . $this->config['api_key'],
            'Content-Type: application/json',
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept: application/json'
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }

        if ($httpCode !== 200) {
            throw new Exception('HTTP Error: ' . $httpCode);
        }

        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $result;
    }

    /**
     * 启动后台轮询
     */
    private function startBackgroundPolling($taskId, $callbackUrl)
    {
        // 创建临时脚本
        $scriptContent = '<?php
$taskId = \'' . addslashes($taskId) . '\';
$callbackUrl = \'' . addslashes($callbackUrl) . '\';
$apiKey = \'' . addslashes($this->config['api_key']) . '\';

for ($i = 0; $i < 30; $i++) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => \'https://yourdomai/status\',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode([\'taskId\' => $taskId, \'type\' => \'1\']),
        CURLOPT_HTTPHEADER => [\'Authorization: \' . $apiKey, \'Content-Type: application/json\'],
        CURLOPT_TIMEOUT => 10
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $result = json_decode($response, true);
    $tasks = $result[\'data\'] ?? [];
    $allDone = true;
    $images = [];
    
    foreach ($tasks as $task) {
        if (($task[\'status\'] ?? \'\') === \'done\' && !empty($task[\'resultUrl\'])) {
            $images[] = $task[\'resultUrl\'];
        }
        if (($task[\'status\'] ?? \'\') !== \'done\') {
            $allDone = false;
        }
    }
    
    if ($allDone || !empty($images)) {
        $ch = curl_init($callbackUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                \'taskId\' => $taskId,
                \'status\' => $allDone ? \'completed\' : \'partial\',
                \'images\' => $images,
                \'timestamp\' => time()
            ]),
            CURLOPT_HTTPHEADER => [\'Content-Type: application/json\'],
            CURLOPT_TIMEOUT => 5
        ]);
        curl_exec($ch);
        curl_close($ch);
        break;
    }
    
    sleep(3);
}

// 最终检查（超时后）
$ch = curl_init($callbackUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode([
        \'taskId\' => $taskId,
        \'status\' => \'timeout\',
        \'timestamp\' => time()
    ]),
    CURLOPT_HTTPHEADER => [\'Content-Type: application/json\'],
    CURLOPT_TIMEOUT => 5
]);
curl_exec($ch);
curl_close($ch);
?>';

        $tempFile = tempnam(sys_get_temp_dir(), 'poll_') . '.php';
        file_put_contents($tempFile, $scriptContent);
        
        // 在后台执行
        $command = 'php ' . escapeshellarg($tempFile) . ' > /dev/null 2>&1 &';
        exec($command);
        
        // 清理临时文件（异步）
        register_shutdown_function(function() use ($tempFile) {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        });
    }

    /**
     * 获取参数值
     */
    private function getParam($params, $key, $default = '')
    {
        return $params[$key] ?? $default;
    }

    /**
     * 获取基础URL
     */
    private function getBaseUrl()
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        return $protocol . '://' . $host . $script;
    }

    /**
     * 显示帮助信息
     */
    private function showHelp()
    {
        $baseUrl = $this->getBaseUrl();
        
        return $this->jsonResponse(200, 'Text-to-Image API', [
            'description' => '文生图API接口 - 兼容第二个接口格式',
            'endpoints' => [
                'POST ' . $baseUrl => [
                    'description' => '同步生成图片（等待结果）',
                    'parameters' => [
                        'prompt' => '提示词（必需）',
                        'picSize' => '图片比例，可选值：1:1, 16:9, 4:3, 3:2, 2:3, 3:4, 9:16, 2.35:1，1:2.35，默认：16:9',
                        'style' => '风格ID，可选值：11,22,20,19,21,10,17,18,15,16,12,13,5,6,4,7，默认：12',
                        'count' => '生成数量，默认：1',
                        'imgWidth' => '图片宽度，默认根据比例自动计算',
                        'imgHeight' => '图片高度，默认根据比例自动计算'
                    ]
                ],
                'POST ' . $baseUrl . '?action=generate-async' => [
                    'description' => '异步生成图片（立即返回任务ID）',
                    'parameters' => [
                        'prompt' => '提示词（必需）',
                        'callbackUrl' => '回调URL（可选）'
                    ]
                ],
                'GET ' . $baseUrl . '?taskId=TASK_ID' => [
                    'description' => '查询任务状态',
                    'parameters' => [
                        'taskId' => '任务ID（必需）'
                    ]
                ],
                'POST ' . $baseUrl . '?action=batch-generate' => [
                    'description' => '批量生成图片',
                    'parameters' => [
                        'prompts' => '提示词数组（必需）',
                        'count' => '每个提示词生成数量，默认：1'
                    ]
                ]
            ],
            'examples' => [
    '同步生成单张' => 'curl -X POST "' . $baseUrl . '" -H "Content-Type: application/json" -d \'{"prompt":"一只可爱的小猫"}\'',
    '同步生成多张' => 'curl -X POST "' . $baseUrl . '" -H "Content-Type: application/json" -d \'{"prompt":"一只可爱的小猫", "count": 4}\'',  // 新增示例
    '异步生成' => 'curl -X POST "' . $baseUrl . '?action=generate-async" -H "Content-Type: application/json" -d \'{"prompt":"美丽的日落"}\'',
    '查询状态' => 'curl "' . $baseUrl . '?taskId=1995456862640238592"'
],
            'style_mapping' => $this->styleMapping,
            'size_mapping' => $this->sizeMapping
        ]);
    }

    /**
     * 返回JSON响应（保持原有格式，这是两个接口的共同格式）
     */
    private function jsonResponse($code, $message, $data = null)
    {
        $response = [
            'code' => $code,
            'msg' => $message,
            'timestamp' => time()
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        return $response;
    }
}

// 执行API
$api = new TextToImageAPI();
$api->handle();
