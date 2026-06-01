<?php

/**
 * 文生图/图生图代理接口 - 整合版
 * 功能：
 * 1. 代理转发到外部API（原text2img_proxy.php功能）
 * 2. 直接处理文生图/图生图请求（原text2img_no_proxy.php功能）
 * 3. 积分扣除和任务记录
 */

// 启动会话 - 必须在任何输出之前调用
session_start();



header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// 引入配置和认证类
require_once 'config.php';
require_once 'Auth.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * API兼容性适配器类
 */
class APICompatibilityAdapter
{
    // 新接口配置
    private $newApiConfig = [
        'api_url' => "",
        'default_model' => "",
        'default_size' => "2560x1440",
        'timeout' => 360
    ];

    /**
     * 构造函数 - 从配置中初始化
     */
    public function __construct()
    {
        // 从配置类中获取所有API配置，并使用默认值作为fallback
        $this->newApiConfig['api_url'] = method_exists('Config', 'TEXT2IMG_API_URL') ? (Config::TEXT2IMG_API_URL() ?: "https://ark.cn-beijing.volces.com/api/v3/images/generations") : "https://ark.cn-beijing.volces.com/api/v3/images/generations";
        $this->newApiConfig['default_model'] = method_exists('Config', 'TEXT2IMG_API_MODEL') ? (Config::TEXT2IMG_API_MODEL() ?: "doubao-seedream-4-5-251128") : "doubao-seedream-4-5-251128";
        $this->apiKey = method_exists('Config', 'TEXT2IMG_API_KEY') ? (Config::TEXT2IMG_API_KEY() ?: "f3627756-88d1-45a9-ba4e-6025571f48b3") : "f3627756-88d1-45a9-ba4e-6025571f48b3";
    }

    // API密钥
    private $apiKey;

    // 风格映射（与老接口一致）
    private $styleMapping = [
        11 => '动漫（高质量二次元）',
        22 => '一致性通用',
        20 => '一致性动漫',
        19 => '一致性写实',
        21 => '通用',
        10 => '写实（或通用2.0）',
        17 => '吉卜力（宫崎骏经典）',
        18 => '古风小说（动漫风玄幻）',
        15 => '王家卫（迷离光影）',
        16 => '国风工笔（古典东方风）',
        12 => '线稿',
        13 => '蒸汽朋克（大热剧集）',
        5 => '手绘动画（童书绘本）',
        6 => '3D动画',
        4 => '欧美漫画',
        7 => '国风写实'
    ];

    // 风格关键词映射
    private $styleKeywords = [
        11 => ['动漫', '二次元', 'anime', 'manga', '日漫', '动漫风'],
        22 => ['一致性', 'consistent', '通用'],
        20 => ['动漫一致性', 'consistent anime'],
        19 => ['写实一致性', 'consistent realistic'],
        21 => ['通用3.0', 'general 3.0'],
        10 => ['写实', 'realistic', '真实', '写实风', 'photorealistic'],
        17 => ['吉卜力', '宫崎骏', 'ghibli', '吉卜力风格'],
        18 => ['古风', '玄幻', '仙侠', '古风小说'],
        15 => ['王家卫', '迷离', '光影', 'wong kar-wai'],
        16 => ['国风', '工笔', '古典', '东方', 'chinese style'],
        12 => ['线稿', '线描', '线稿风', 'line art'],
        13 => ['蒸汽朋克', 'arcane', 'arcane style'],
        5 => ['手绘', '绘本', '童书', 'hand-drawn', 'children book'],
        6 => ['3D', '三维', 'three-dimensional', '3d animation'],
        4 => ['欧美漫画', '美漫', 'comic', 'western comic'],
        7 => ['国风写实', 'chinese realistic']
    ];

    // 尺寸映射 2K
    // private $sizeToRatioMapping = [
    //     '2048x2048' => '1:1',
    //     '2304x1728' => '4:3',
    //     '1728x2304' => '3:4',
    //     '2560x1440' => '16:9',
    //     '1440x2560' => '9:16',
    //     '2496x1664' => '3:2',
    //     '1664x2496' => '2:3',
    //     '3024x1296' => '21:9'
    // ];

    // 尺寸映射 4K
    private $sizeToRatioMapping = [
        '4096x4096' => '1:1',
        '4704x3520' => '4:3',
        '3520x4704' => '3:4',
        '5504x3040' => '16:9',
        '3040x5504' => '9:16',
        '4992x3328' => '3:2',
        '3328x4992' => '2:3',
        '6240x2656' => '21:9'
    ];

    /**
     * 主处理函数 - 直接处理模式
     */
    public function handleDirect($params)
    {
        try {
            $method = $_SERVER['REQUEST_METHOD'];

            if ($method === 'POST') {
                return $this->processDirectRequest($params);
            } elseif ($method === 'GET') {
                return $this->showHelp();
            } else {
                return $this->jsonResponse(405, 'Method Not Allowed');
            }
        } catch (Exception $e) {
            return $this->jsonResponse(500, 'Server Error: ' . $e->getMessage());
        }
    }

    /**
     * 处理直接请求
     */
    private function processDirectRequest($params)
    {
        // 验证必需参数
        $prompt = trim($params['prompt'] ?? '');

        if (empty($prompt)) {
            return $this->jsonResponse(400, 'Parameter "prompt" is required');
        }

        // 使用构造函数中获取的API密钥
        $apiKey = $this->apiKey;

        // 如果参数中提供了API密钥，则优先使用参数中的
        if (!empty($params['text2img_api_key'])) {
            $apiKey = trim($params['text2img_api_key']);
        }

        if (empty($apiKey)) {
            return $this->jsonResponse(400, 'API key is required');
        }

        // 检查是否有参考图片参数
        $hasReferenceImages = $this->hasReferenceImages($params);

        // 记录开始时间
        $startTime = time();

        // 调用新接口
        $newApiResult = $this->callNewAPI($prompt, $apiKey, $params, $hasReferenceImages);

        if (!$newApiResult['success']) {
            return $this->jsonResponse(500, 'New API Error: ' . $newApiResult['error'], [
                'prompt' => $prompt,
                'apiCalled' => true,
                'hasReferenceImages' => $hasReferenceImages,
                'newApiError' => $newApiResult
            ]);
        }

        // 从prompt中识别风格
        $detectedStyle = $this->detectStyleFromPrompt($prompt);

        // 获取尺寸信息
        $size = $params['size'] ?? $this->newApiConfig['default_size'];
        list($imgWidth, $imgHeight) = explode('x', $size);
        $picSize = $this->sizeToRatioMapping[$size] ?? $this->calculateRatio($imgWidth, $imgHeight);

        // 提取图片URL
        $imageUrl = $this->extractImageUrl($newApiResult['data']);

        if (empty($imageUrl)) {
            return $this->jsonResponse(500, 'No image URL generated by new API', [
                'prompt' => $prompt,
                'hasReferenceImages' => $hasReferenceImages,
                'newApiResponse' => $newApiResult
            ]);
        }

        // 下载图片到本地服务器并更新URL
        $localImageUrl = downloadImageToLocal($imageUrl, []);
        if ($localImageUrl !== $imageUrl) {
            $imageUrl = $localImageUrl;
        }

        // 提取usage信息
        $usage = $newApiResult['usage'] ?? [
            'generated_images' => 1,
            'output_tokens' => 0,
            'total_tokens' => 0
        ];

        // 获取图片生成模式
        $imageGenerationMode = $hasReferenceImages ? 'image_to_image' : 'text_to_image';

        // 构造与老接口兼容的响应格式
        $oldFormatResponse = [
            'imageUrl' => $imageUrl,
            'prompt' => $prompt,
            'picSize' => $picSize,
            'style' => $detectedStyle['id'],
            'styleName' => $detectedStyle['name'],
            'imgWidth' => (int)$imgWidth,
            'imgHeight' => (int)$imgHeight,
            'aspectRatio' => $picSize,
            'taskId' => $this->generateTaskId(),
            'generateStatus' => 'completed',
            'elapsedTime' => time() - $startTime,
            'attempts' => 1,
            'totalImages' => 1,
            'allImages' => [
                [
                    'url' => $imageUrl,
                    'id' => uniqid('img_'),
                    'taskId' => $this->generateTaskId(),
                    'ratio' => $picSize
                ]
            ],
            'styleDetection' => [
                'detected' => $detectedStyle['confidence'] > 0,
                'confidence' => $detectedStyle['confidence'],
                'keywords' => $detectedStyle['keywords']
            ],
            'newApiInfo' => [
                'model' => $params['text2img_api_model'] ?? $this->newApiConfig['default_model'],
                'size' => $size,
                'apiUsed' => 'volcengine',
                'mode' => $imageGenerationMode
            ],
            'usage' => $usage
        ];

        // 处理多图情况
        $images = $this->extractAllImages($newApiResult['data']);
        // 添加调试信息
        error_log('Extracted images count: ' . count($images));
        error_log('New API response data: ' . json_encode($newApiResult['data']));
        
        if (count($images) > 1) {
            $oldFormatResponse['totalImages'] = count($images);
            $oldFormatResponse['allImages'] = $images;
            $oldFormatResponse['requestedCount'] = count($images);
            $oldFormatResponse['images'] = $images;

            // 更新usage中的图片数量
            if (isset($oldFormatResponse['usage'])) {
                $oldFormatResponse['usage']['generated_images'] = count($images);
            }
        } else {
            // 即使只有一张图片，也检查API响应是否可能包含多张图片
            error_log('API response: ' . json_encode($newApiResult));
        }

        // 如果使用了参考图，在响应中添加相关信息
        if ($hasReferenceImages) {
            $referenceImages = $this->parseReferenceImages($params);
            $oldFormatResponse['hasReferenceImages'] = true;
            $oldFormatResponse['referenceImageCount'] = count($referenceImages);
            $oldFormatResponse['newApiInfo']['mode'] = count($referenceImages) > 1 ? 'multi_image_to_image' : 'image_to_image';
        }

        return $this->jsonResponse(0, 'Success', $oldFormatResponse);
    }

    /**
     * 检查是否有参考图片
     */
    private function hasReferenceImages($params)
    {
        // 检查image参数
        if (isset($params['image']) && !empty($params['image'])) {
            return true;
        }

        // 检查其他可能的参数名
        $imageParamNames = ['reference_image', 'input_image', 'base_image', 'source_image'];
        foreach ($imageParamNames as $paramName) {
            if (isset($params[$paramName]) && !empty($params[$paramName])) {
                return true;
            }
        }

        return false;
    }

    /**
     * 解析参考图片参数
     */
    private function parseReferenceImages($params)
    {
        $referenceImages = [];

        // 首先检查image参数
        if (isset($params['image'])) {
            $imageParam = $params['image'];

            // 如果是数组，直接使用
            if (is_array($imageParam)) {
                foreach ($imageParam as $image) {
                    if (!empty($image)) {
                        $referenceImages[] = trim($image);
                    }
                }
            }
            // 如果是字符串，可能是用逗号分隔的多个URL
            elseif (is_string($imageParam) && !empty($imageParam)) {
                $images = explode(',', $imageParam);
                foreach ($images as $image) {
                    $trimmedImage = trim($image);
                    if (!empty($trimmedImage)) {
                        $referenceImages[] = $trimmedImage;
                    }
                }
            }
        }

        // 如果image参数没有找到，检查其他可能的参数名
        if (empty($referenceImages)) {
            $imageParamNames = ['reference_image', 'input_image', 'base_image', 'source_image'];
            foreach ($imageParamNames as $paramName) {
                if (isset($params[$paramName]) && !empty($params[$paramName])) {
                    $imageParam = $params[$paramName];

                    // 如果是数组
                    if (is_array($imageParam)) {
                        foreach ($imageParam as $image) {
                            if (!empty($image)) {
                                $referenceImages[] = trim($image);
                            }
                        }
                    }
                    // 如果是字符串
                    elseif (is_string($imageParam)) {
                        $images = explode(',', $imageParam);
                        foreach ($images as $image) {
                            $trimmedImage = trim($image);
                            if (!empty($trimmedImage)) {
                                $referenceImages[] = $trimmedImage;
                            }
                        }
                    }

                    // 找到一个参数就退出
                    if (!empty($referenceImages)) {
                        break;
                    }
                }
            }
        }

        return $referenceImages;
    }

    /**
     * 调用新文生图/图生图API
     */
    private function callNewAPI($prompt, $apiKey, $params, $hasReferenceImages = false)
    {
        // 构建新接口请求数据
        $requestData = [
            'model' => $params['text2img_api_model'] ?? $this->newApiConfig['default_model'],
            'prompt' => $prompt,
            'size' => $params['size'] ?? $this->newApiConfig['default_size'],
            'watermark' => $this->normalizeBoolean($params['watermark'] ?? false),
            'stream' => $this->normalizeBoolean($params['stream'] ?? false)
        ];
        
        // 添加调试信息
        error_log('Request data: ' . json_encode($requestData));

        // 如果存在参考图片，添加到请求数据中
        if ($hasReferenceImages) {
            $referenceImages = $this->parseReferenceImages($params);
            if (!empty($referenceImages)) {
                $requestData['image'] = $referenceImages;

                // 添加sequential_image_generation参数（如果提供）
                if (isset($params['sequential_image_generation'])) {
                    $requestData['sequential_image_generation'] = $params['sequential_image_generation'];
                }
                
                // 添加sequential_image_generation_options参数（如果提供）
                if (isset($params['sequential_image_generation_options'])) {
                    $requestData['sequential_image_generation_options'] = $params['sequential_image_generation_options'];
                }

                // 添加图生图特有的参数
                $img2imgParams = ['strength', 'guidance_scale', 'image_num', 'seed', 'filter_param'];
                foreach ($img2imgParams as $paramName) {
                    if (isset($params[$paramName])) {
                        $requestData[$paramName] = $params[$paramName];
                    }
                }
            }
        }

        // 添加通用可选参数
        if (isset($params['negative_prompt'])) {
            $requestData['negative_prompt'] = trim($params['negative_prompt']);
        }

        if (isset($params['response_format'])) {
            $requestData['response_format'] = trim($params['response_format']);
        }

        if (isset($params['n'])) {
            $requestData['n'] = (int)$params['n'];
        }

        // 设置请求头
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];

        // 调用API
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->newApiConfig['api_url'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($requestData, JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => $this->newApiConfig['timeout'],
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 300,
            CURLOPT_HEADER => false
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errorNo = curl_errno($ch);
        curl_close($ch);

        // 处理错误
        if ($errorNo !== 0) {
            return [
                'success' => false,
                'error' => 'cURL Error (' . $errorNo . '): ' . $error,
                'httpCode' => $httpCode,
                'requestData' => $hasReferenceImages ? ['has_images' => true, 'image_count' => count($referenceImages ?? [])] : []
            ];
        }

        if ($httpCode !== 200) {
            $errorInfo = json_decode($response, true);
            $errorMessage = $errorInfo['error']['message'] ?? $response ?? "HTTP Error: $httpCode";
            return [
                'success' => false,
                'error' => $errorMessage,
                'httpCode' => $httpCode,
                'response' => $response,
                'requestData' => $hasReferenceImages ? ['has_images' => true] : []
            ];
        }

        // 解析响应
        $result = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'error' => 'Invalid JSON response: ' . json_last_error_msg(),
                'response' => $response
            ];
        }

        // 提取usage信息
        $usage = $this->extractUsageInfo($result);

        return [
            'success' => true,
            'data' => $result,
            'usage' => $usage,
            'httpCode' => $httpCode,
            'hasReferenceImages' => $hasReferenceImages
        ];
    }

    /**
     * 从API响应中提取usage信息
     */
    private function extractUsageInfo($apiResponse)
    {
        // 尝试从不同路径提取usage信息
        $usage = null;

        // 路径1: 直接包含usage字段
        if (isset($apiResponse['usage']) && is_array($apiResponse['usage'])) {
            $usage = $apiResponse['usage'];
        }
        // 路径2: 在data数组中的第一个元素包含usage
        elseif (isset($apiResponse['data'][0]['usage']) && is_array($apiResponse['data'][0]['usage'])) {
            $usage = $apiResponse['data'][0]['usage'];
        }
        // 路径3: 在根级别但有不同格式
        elseif (isset($apiResponse['generated_images'])) {
            $usage = [
                'generated_images' => $apiResponse['generated_images'],
                'output_tokens' => $apiResponse['output_tokens'] ?? 0,
                'total_tokens' => $apiResponse['total_tokens'] ?? 0
            ];
        }

        // 如果没有找到usage信息，创建默认值
        if ($usage === null) {
            // 计算生成的图片数量
            $generatedImages = 0;

            // 尝试从不同路径统计图片数量
            if (isset($apiResponse['data']) && is_array($apiResponse['data'])) {
                $generatedImages = count($apiResponse['data']);
            } elseif (isset($apiResponse['images']) && is_array($apiResponse['images'])) {
                $generatedImages = count($apiResponse['images']);
            } elseif (isset($apiResponse['generations']) && is_array($apiResponse['generations'])) {
                $generatedImages = count($apiResponse['generations']);
            } else {
                $generatedImages = 1; // 默认1张
            }

            $usage = [
                'generated_images' => $generatedImages,
                'output_tokens' => 0,
                'total_tokens' => 0
            ];
        }

        return $usage;
    }

    /**
     * 从prompt中识别风格
     */
    private function detectStyleFromPrompt($prompt)
    {
        if (empty($prompt)) {
            return [
                'id' => 12,
                'name' => '线稿',
                'confidence' => 0,
                'keywords' => []
            ];
        }

        $promptLower = mb_strtolower($prompt, 'UTF-8');
        $bestMatch = [
            'id' => 12,
            'name' => '线稿',
            'confidence' => 0,
            'keywords' => []
        ];

        foreach ($this->styleKeywords as $styleId => $keywords) {
            $matchedKeywords = [];
            $matchScore = 0;

            foreach ($keywords as $keyword) {
                $keywordLower = mb_strtolower($keyword, 'UTF-8');
                if (strpos($promptLower, $keywordLower) !== false) {
                    $matchedKeywords[] = $keyword;
                    $matchScore += 1;

                    // 关键词在开头加分
                    if (strpos($promptLower, $keywordLower) === 0) {
                        $matchScore += 0.5;
                    }
                }
            }

            if ($matchScore > $bestMatch['confidence']) {
                $bestMatch = [
                    'id' => $styleId,
                    'name' => $this->styleMapping[$styleId] ?? '未知风格',
                    'confidence' => $matchScore,
                    'keywords' => $matchedKeywords
                ];
            }
        }

        return $bestMatch;
    }

    /**
     * 从新接口数据中提取图片URL
     */
    private function extractImageUrl($newData)
    {
        // 尝试多种可能的URL路径
        $possiblePaths = [
            ['data', 0, 'url'],
            ['data', 'url'],
            ['url'],
            ['image_url'],
            ['result', 'url'],
            ['images', 0, 'url'],
            ['output', 0, 'url'],
            ['data', 0, 'image_url'],
            ['data', 'image_url'],
            ['generations', 0, 'url'],
        ];

        foreach ($possiblePaths as $path) {
            $value = $this->getArrayValueByPath($newData, $path);
            if (!empty($value) && (filter_var($value, FILTER_VALIDATE_URL) || strpos($value, 'http') === 0)) {
                return $value;
            }
        }

        // 检查base64数据
        if (isset($newData['data'][0]['b64_json'])) {
            return 'data:image/png;base64,' . $newData['data'][0]['b64_json'];
        }

        return '';
    }

    /**
     * 提取所有图片
     */
    private function extractAllImages($newData)
    {
        $images = [];

        $possibleArrays = [
            'data' => $newData['data'] ?? [],
            'images' => $newData['images'] ?? [],
            'output' => $newData['output'] ?? [],
            'results' => $newData['results'] ?? [],
            'generations' => $newData['generations'] ?? []
        ];

        foreach ($possibleArrays as $arrayName => $array) {
            if (is_array($array) && !empty($array)) {
                foreach ($array as $index => $item) {
                    $url = '';

                    if (is_array($item)) {
                        $url = $item['url'] ?? $item['image_url'] ?? $item['image'] ?? '';
                    } else if (is_string($item) && (filter_var($item, FILTER_VALIDATE_URL) || strpos($item, 'http') === 0)) {
                        $url = $item;
                    }

                    if (!empty($url)) {
                        // 下载图片到本地服务器并更新URL
                        $localImageUrl = downloadImageToLocal($url, []);
                        if ($localImageUrl !== $url) {
                            $url = $localImageUrl;
                        }

                        $images[] = [
                            'url' => $url,
                            'id' => uniqid('img_'),
                            'taskId' => $this->generateTaskId(),
                            'ratio' => '16:9'
                        ];
                    }
                }
            }
        }
        
        // 添加调试信息
        error_log('Extracted images from all paths: ' . count($images));

        return $images;
    }

    /**
     * 标准化布尔值
     */
    private function normalizeBoolean($value)
    {
        if ($value === 'true' || $value === true || $value === '1') {
            return true;
        } elseif ($value === 'false' || $value === false || $value === '0') {
            return false;
        }
        return (bool)$value;
    }

    /**
     * 根据宽高计算比例
     */
    private function calculateRatio($width, $height)
    {
        if ($height == 0) return '16:9';

        $ratio = $width / $height;

        if (abs($ratio - 1) < 0.05) return '1:1';
        if (abs($ratio - 16 / 9) < 0.05) return '16:9';
        if (abs($ratio - 4 / 3) < 0.05) return '4:3';
        if (abs($ratio - 3 / 2) < 0.05) return '3:2';
        if (abs($ratio - 2 / 3) < 0.05) return '2:3';
        if (abs($ratio - 3 / 4) < 0.05) return '3:4';
        if (abs($ratio - 9 / 16) < 0.05) return '9:16';

        return '16:9';
    }

    /**
     * 生成任务ID
     */
    private function generateTaskId()
    {
        return time() . mt_rand(100000, 999999);
    }

    /**
     * 通过路径获取数组值
     */
    private function getArrayValueByPath($array, $path)
    {
        $current = $array;
        foreach ($path as $key) {
            if (is_array($current) && array_key_exists($key, $current)) {
                $current = $current[$key];
            } else {
                return null;
            }
        }
        return $current;
    }

    /**
     * 显示帮助信息
     */
    private function showHelp()
    {
        $baseUrl = $this->getBaseUrl();

        return $this->jsonResponse(200, '文生图/图生图API', [
            'description' => '接收参数，调用接口，返回全兼容格式',
            '使用方法' => 'POST ' . $baseUrl . ' - 发送接口格式的参数',
            '必需参数' => [
                'prompt' => '提示词',
                'text2img_api_key' => '接口API密钥'
            ],
            '可选参数' => [
                'size' => '图片尺寸，默认：16:9（2560x1440）；宽高比[宽高像素值]1:1[2048x2048],4:3[2304x1728],3:4[1728x2304],16:9[2560x1440],9:16[1440x2560],3:2[2496x1664],2:3[1664x2496],21:9[3024x1296]',
                'text2img_api_model' => '模型名称',
                'watermark' => '水印，默认：false',
                'stream' => '流式输出，默认：false',
                'negative_prompt' => '负面提示词',
                'response_format' => '返回格式：url 或 b64_json',
                'n' => '生成图片数量',
                'image' => '参考图片（支持1张或多张），格式：1) URL字符串或数组；2) 逗号分隔的URL列表；3) base64编码的图片数据，支持base64：data:image/<图片格式>;base64,<Base64编码>。注意 <图片格式> 需小写，如 data:image/png;base64,<base64_image>',
                'sequential_image_generation' => '多图融合模式：enabled（顺序生成）或 disabled（同步生成），默认：disabled',
                'strength' => '控制参考图的影响程度（0-1），默认：0.8',
                'guidance_scale' => '引导尺度，控制生成图片与提示词的匹配程度，默认：7.5',
                'image_num' => '生成的图片数量，默认：1',
                'seed' => '随机种子，用于生成可重复的结果',
                'filter_param' => '过滤参数，控制内容的敏感性'
            ],
            '图生图示例' => [
                '单图参考' => '{
    "prompt": "将图片中的人物服装换成红色",
    "image": "https://example.com/reference.jpg",
    "text2img_api_key": "your-api-key-here",
    "size": "2560x1440"
}',
                '多图融合' => '{
    "prompt": "将图1的服装换为图2的服装",
    "image": ["https://example.com/image1.jpg", "https://example.com/image2.jpg"],
    "sequential_image_generation": "disabled",
    "text2img_api_key": "your-api-key-here",
    "size": "2560x1440"
}'
            ],
            '风格说明' => [
                '注意' => '新接口不支持风格参数，请在prompt中描述风格',
                '示例' => '在prompt中添加："线稿风格"、"吉卜力风格"、"写实风格"等',
                '支持风格' => $this->styleMapping
            ],
            '测试请求' => [
                '文生图' => 'curl -X POST "' . $baseUrl . '" -H "Content-Type: application/json" -d \'{
    "prompt": "线稿，穆子峰被秦朗等人追至仙人山悬崖边，无路可退，最终被扔下悬崖",
    "text2img_api_key": "your-api-key-here",
    "size": "2560x1440"
}\'',
                '图生图' => 'curl -X POST "' . $baseUrl . '" -H "Content-Type: application/json" -d \'{
    "prompt": "将图片中的人物服装换成红色",
    "image": "https://example.com/reference.jpg",
    "text2img_api_key": "your-api-key-here",
    "size": "2560x1440"
}\''
            ],
            '返回格式' => 'JSON格式'
        ]);
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
     * 返回JSON响应
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

        return $response;
    }
}

/**
 * 主处理逻辑 - 决定使用代理模式还是直接处理模式
 */
function main()
{
    // 获取请求数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    // 如果没有JSON输入，尝试从其他方式获取
    if (!$data) {
        $data = array_merge($_GET, $_POST);
    }



    // 检查是否应该使用直接处理模式
    $useDirectMode = shouldUseDirectMode();

    if ($useDirectMode) {
        // 直接处理模式
        $adapter = new APICompatibilityAdapter();
        $result = $adapter->handleDirect($data);

        // 处理积分扣除和任务记录（如果用户已登录）
        handlePointsAndTask($result, $data);

        // 输出结果
        echo json_encode($result);
    } else {
        // 代理模式（转发到外部API）
        handleProxyMode($data);
    }
}

/**
 * 判断是否使用直接处理模式
 */
function shouldUseDirectMode()
{
    // 检查是否有特定的参数指示使用代理模式
    if (isset($_GET['proxy']) && $_GET['proxy'] == 'true') {
        return false;
    }

    // 默认使用直接处理模式
    return true;
}

/**
 * 处理代理模式
 */
function handleProxyMode($data)
{
    // 验证输入
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        exit();
    }

    // 获取配置的API URL和Key，使用method_exists检查确保兼容性
    $apiUrl = method_exists('Config', 'TEXT2IMG_API_URL') ? Config::TEXT2IMG_API_URL() : '';
    $apiKey = method_exists('Config', 'TEXT2IMG_API_KEY') ? Config::TEXT2IMG_API_KEY() : '';

    // 检查API URL和API密钥是否为空
    if (empty($apiUrl)) {
        echo json_encode(['success' => false, 'error' => 'API URL 未配置']);
        exit();
    }

    if (empty($apiKey)) {
        echo json_encode(['success' => false, 'error' => 'API Key 未配置']);
        exit();
    }

    // 使用Config类获取的API密钥替换前端传递的密钥
    $data['text2img_api_key'] = $apiKey;

    // 转发请求到外部API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);



    if ($error) {
        echo json_encode(['success' => false, 'error' => 'Curl error: ' . $error]);
        exit();
    }

    if ($httpCode >= 400) {
        // 返回完整的 API 响应，帮助调试
        $apiResponse = json_decode($response, true);
        if ($apiResponse) {
            echo json_encode(['success' => false, 'error' => 'API error with HTTP code: ' . $httpCode, 'api_response' => $apiResponse]);
        } else {
            echo json_encode(['success' => false, 'error' => 'API error with HTTP code: ' . $httpCode, 'raw_response' => $response]);
        }
        exit();
    }

    // 处理积分扣除和任务记录（如果用户已登录）
    $apiResponse = json_decode($response, true);

    // 提取图片URL并下载到本地服务器
    if (isset($apiResponse['data'])) {
        $responseData = $apiResponse['data'];
        $imageUrl = $responseData['imageUrl'] ?? $responseData['image_url'] ?? null;

        if ($imageUrl) {
            $localImageUrl = downloadImageToLocal($imageUrl, $responseData);
            if ($localImageUrl !== $imageUrl) {
                if (isset($responseData['imageUrl'])) {
                    $apiResponse['data']['imageUrl'] = $localImageUrl;
                }
                if (isset($responseData['image_url'])) {
                    $apiResponse['data']['image_url'] = $localImageUrl;
                }
                if (isset($responseData['allImages']) && is_array($responseData['allImages'])) {
                    foreach ($responseData['allImages'] as &$img) {
                        if (isset($img['url'])) {
                            $localImgUrl = downloadImageToLocal($img['url'], $responseData);
                            if ($localImgUrl !== $img['url']) {
                                $img['url'] = $localImgUrl;
                            }
                        }
                    }
                }
                // 更新响应字符串
                $response = json_encode($apiResponse);
            }
        }
    }

    handlePointsAndTask($apiResponse, $data);

    // 返回API响应
    echo $response;
}

/**
 * 处理积分扣除和任务记录（通用函数，两种模式都使用）
 */
function handlePointsAndTask($apiResponse, $originalData)
{
    $auth = new Auth();
    $userId = $auth->getCurrentUserId();

    if ($userId && isset($apiResponse['data'])) {
        $responseData = $apiResponse['data'];

        // 提取必要信息
        $taskId = $responseData['taskId'] ?? $responseData['task_id'] ?? null;
        $prompt = $responseData['prompt'] ?? $originalData['prompt'] ?? null;
        $imageUrl = $responseData['imageUrl'] ?? $responseData['image_url'] ?? null;

        // 检查是否有参考图，如果有多消耗积分
        $hasReferenceImages = false;
        if (isset($originalData['image']) && !empty($originalData['image'])) {
            $hasReferenceImages = true;
        }

        // 计算所需积分，使用默认值确保兼容性
        $basePoints = defined('Config::IMAGE_GENERATION_COST') ? Config::IMAGE_GENERATION_COST : 20;

        // 如果有参考图，增加积分消耗
        $requiredPoints = $hasReferenceImages ? $basePoints * 1.5 : $basePoints;

        // 检查积分是否足够
        if (!$auth->checkUserPoints($userId, $requiredPoints)) {
            $errorMsg = $hasReferenceImages ?
                '积分不足，图生图每张需要消耗' . $requiredPoints . '积分' :
                '积分不足，文生图每张需要消耗' . $requiredPoints . '积分';
            echo json_encode([
                'success' => false,
                'error' => $errorMsg
            ]);
            exit;
        }

        try {
            // 下载图片到本地并生成新的URL（如果需要）
            $localImageUrl = downloadImageToLocal($imageUrl, $responseData);

            // 更新响应中的图片URL
            if ($localImageUrl !== $imageUrl) {
                if (isset($responseData['imageUrl'])) {
                    $apiResponse['data']['imageUrl'] = $localImageUrl;
                }
                if (isset($responseData['image_url'])) {
                    $apiResponse['data']['image_url'] = $localImageUrl;
                }
                if (isset($responseData['allImages']) && is_array($responseData['allImages']) && count($responseData['allImages']) > 0) {
                    $apiResponse['data']['allImages'][0]['url'] = $localImageUrl;
                }
            }

            // 插入任务到数据库
            insertTaskToDatabase($userId, $taskId, $prompt, $localImageUrl, $hasReferenceImages);

            // 扣除积分
            $operationType = $hasReferenceImages ? '图生图' : '文生图';
            $deductResult = $auth->deductUserPoints($userId, $requiredPoints, $operationType, 'text2img', $taskId, json_encode($apiResponse));
            if (!$deductResult['success']) {
                echo json_encode([
                    'success' => false,
                    'error' => $deductResult['message']
                ]);
                exit;
            }
        } catch (Exception $e) {
            // 静默处理异常
        }
    }
}

/**
 * 下载图片到本地
 */
function downloadImageToLocal($imageUrl, $responseData)
{
    if (!$imageUrl) {
        return $imageUrl;
    }

    // 确保输出目录存在
    $outputDir = __DIR__ . '/outputs/images';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    // 生成唯一的文件名
    $filename = 'img_' . uniqid() . '_' . time() . '.png';
    $localPath = $outputDir . '/' . $filename;

    // 如果是base64数据，直接保存
    if (strpos($imageUrl, 'data:image/') === 0) {
        $base64Data = substr($imageUrl, strpos($imageUrl, ',') + 1);
        $imageData = base64_decode($base64Data);
        if ($imageData !== false) {
            file_put_contents($localPath, $imageData);
            return 'https://files.wop.cc/images/' . $filename;
        }
        return $imageUrl;
    }

    // 下载图片
    $imgCh = curl_init($imageUrl);
    $fp = fopen($localPath, 'wb');
    curl_setopt($imgCh, CURLOPT_FILE, $fp);
    curl_setopt($imgCh, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($imgCh, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($imgCh, CURLOPT_TIMEOUT, 300);
    $imgResult = curl_exec($imgCh);
    curl_close($imgCh);
    fclose($fp);

    if ($imgResult) {
        // 生成新的URL
        $localImageUrl = 'https://files.wop.cc/images/' . $filename;
        return $localImageUrl;
    } else {
        return $imageUrl;
    }
}

/**
 * 插入任务到数据库
 */
function insertTaskToDatabase($userId, $taskId, $prompt, $imageUrl, $hasReferenceImages = false)
{
    try {
        // 创建数据库实例
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 根据是否有参考图设置不同的标题
        $title = $hasReferenceImages ? '图生图-分镜图' : '文生图-分镜图';

        // 插入任务到数据库
        $sql = "INSERT INTO tasks (user_id, task_type, title, status, progress, input_data, output_data, created_at, updated_at, completed_at, task_id) 
               VALUES (:user_id, :task_type, :title, :status, :progress, :input_data, :output_data, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP, :task_id)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':task_type', 'storyboards_images', PDO::PARAM_STR);
        $stmt->bindValue(':title', $title, PDO::PARAM_STR);
        $stmt->bindValue(':status', 2, PDO::PARAM_INT);
        $stmt->bindValue(':progress', 100, PDO::PARAM_INT);
        $stmt->bindValue(':input_data', $prompt, PDO::PARAM_STR);
        $stmt->bindValue(':output_data', $imageUrl, PDO::PARAM_STR);
        $stmt->bindValue(':task_id', $taskId, PDO::PARAM_STR);
        $stmt->execute();
    } catch (Exception $e) {
        // 静默处理异常
    }
}

// 执行主函数
main();
