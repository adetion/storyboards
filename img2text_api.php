<?php
require_once __DIR__ . '/config.php';

// 图片理解API接口类
class ImageUnderstandingAPI
{
    private $api_url;
    private $api_key;
    private $api_model;

    public function __construct()
    {
        $this->api_url = Config::IMG2TEXT_API_URL();
        $this->api_key = Config::IMG2TEXT_API_KEY();
        $this->api_model = Config::IMG2TEXT_API_MODEL();
    }

    /**
     * 处理API请求
     * @param array $images 图片URL数组
     * @param string $prompt 总提示词
     * @return array API响应结果
     */
    public function processImages(array $images, string $prompt): array
    {
        // 验证输入
        if (empty($images) || count($images) < 3) {
            return ['error' => '至少需要3张图片'];
        }

        if (empty($prompt)) {
            return ['error' => '提示词不能为空'];
        }

        // 构建API请求内容
        $content = [];

        // 添加图片内容
        foreach ($images as $imageUrl) {
            // 验证URL格式
            if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                return ['error' => '图片URL格式不正确: ' . $imageUrl];
            }

            $content[] = [
                'type' => 'input_image',
                'image_url' => $imageUrl
            ];
        }

        // 添加文本提示词
        $content[] = [
            'type' => 'input_text',
            'text' => $this->buildPrompt($prompt)
        ];

        // 构建完整请求数据
        $requestData = [
            'model' => $this->api_model,
            'input' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ];

        // 发送API请求
        return $this->callAPI($requestData);
    }

    /**
     * 构建完整的提示词
     * @param string $prompt 原始提示词
     * @return string 完整提示词
     */
    private function buildPrompt(string $prompt): string
    {
        return <<<PROMPT
你是一个专业的视频分镜编剧。请严格遵循以下流程，基于我提供的所有图片和一段"总提示词"[{$prompt}]，直接生成最终可用的"片段提示词"序列。

输入：
图片序列：按顺序上传的N张图（N>2）。
总提示词：一段描述核心情节或对话的文本。

处理流程（你内部执行，不输出）：
1. 单图解析：为每张图生成简短的"分图提示词"，精确描述画面中的核心要素：人物、表情、动作、场景细节和景别。
2. 相邻帧融合：将相邻两张图（如图n与图n+1）的"分图提示词"进行融合，构建出"首尾融合提示词"。融合时必须基于画面内容，设计合理的镜头运动（如推、拉、摇、移、跟）和动作/情绪过渡，确保情节连贯。
3. 整体剧情同步与扩展：将所有"首尾融合提示词"按顺序排列，视为一个连贯的视觉剧本。将"总提示词"的核心内容（如对话、情节）合理地拆分、分配到对应的视觉片段中，并进行必要的扩写，使语言描述与画面动作精确匹配，形成完整的叙事流。

最终输出要求：
仅输出一个名为"最终片段提示词序列"的列表。列表中的项目数量必须等于（图片总数 - 1），即与需要生成的视频数量严格一一对应。

每个项目的格式必须为：
text【片段X】画面与运镜：[此处填写该视频片段的完整提示词，需整合镜头运动、起始与结束画面状态、以及连贯的动作描述]
对话/剧情：[此处填写分配到此片段中的具体对话、旁白或情节描述，须与"总提示词"强相关且符合画面情境]

请确保：
1. 输出直接是"最终片段提示词序列"，不包含任何分析、解释、示例或中间步骤。
2. 每个片段的"画面与运镜"描述能独立用于视频生成。
3. 所有片段在剧情上无缝衔接，构成一个完整的长叙事。
4. 不同的片段中不得出现相同或类似的对话、旁白。

现在，请开始处理我提供的图片和总提示词。最终只输出给我一个纯json格式的文本，形如：
{
    "data": [
        {
            "name": "片段1",
            "content": "画面与运镜：从大殿中景起始，黑甲佩剑武将与绣纹华服女子在台阶下方对峙对视，两侧侍从垂首恭立，镜头缓慢推近人物，随后以淡入淡出转场切换至暖光室内中景画面，黑甲武将手指对面束发华服男子，背景为山水屏风与燃烛，镜头稳定聚焦两人对峙状态。对话/剧情：旁白：「金銮殿上的僵局刚平，镇北将军便即刻入宫拦下了私会外戚的七皇子。」将军厉声：「你私通外臣，罔顾国法，可知这是诛九族的大罪！」"
        },
        {
            "name": "片段2",
            "content": "画面与运镜：从室内中景的两人对峙画面开始，镜头快速推近，完成从中景到面部特写的运镜，光线集中打在黑甲武将的脸上，虚化背景的屏风与烛火，凸显武将怒目圆睁、神色凌厉愤懑的状态。对话/剧情：将军怒视皇子，语气里满是失望与震怒：「陛下待你恩重如山，你竟胆大包天，勾结逆党图谋不轨！」"
        },
        {
            "name": "片段3",
            "content": "画面与运镜：从黑甲武将的面部特写开始，镜头向下摇移同时缓慢拉远，最终定格在华服皇子的腰侧，皇子双手紧握剑柄，指节泛白，华服刺绣与鎏金带扣细节清晰，光线聚焦于剑柄与皇子的手，放大其紧绷的情绪。对话/剧情：七皇子隐忍咬牙，声音带着不甘：「将军何必故作清高，这朝堂早已腐朽不堪，我只是在做该做的事！」说着指尖发力，按紧剑柄，欲拔剑相向"
        }
    ]
}
PROMPT;
    }

    /**
     * 调用第三方API
     * @param array $data 请求数据
     * @return array 响应结果
     */
    private function callAPI(array $data): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->api_key,
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $this->api_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 600,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($error) {
            return ['error' => 'CURL错误: ' . $error];
        }

        if ($httpCode !== 200) {
            return ['error' => "API请求失败，HTTP状态码: {$httpCode}", 'response' => $response];
        }

        // 尝试解析JSON响应
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['error' => 'JSON解析失败: ' . json_last_error_msg(), 'raw_response' => $response];
        }

        // 提取output内的content内的text的值
        if (isset($decodedResponse['output']) && is_array($decodedResponse['output'])) {
            foreach ($decodedResponse['output'] as $outputItem) {
                if (isset($outputItem['type']) && $outputItem['type'] === 'message') {
                    if (isset($outputItem['content']) && is_array($outputItem['content'])) {
                        foreach ($outputItem['content'] as $contentItem) {
                            if (isset($contentItem['type']) && $contentItem['type'] === 'output_text') {
                                if (isset($contentItem['text'])) {
                                    // 解析text值（因为它是一个JSON字符串）
                                    $textContent = json_decode($contentItem['text'], true);
                                    if (json_last_error() === JSON_ERROR_NONE) {
                                        return $textContent;
                                    } else {
                                        return ['error' => '解析output_text失败: ' . json_last_error_msg()];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return ['error' => '无法从API响应中提取有效的output_text'];
    }
}

// API接口处理脚本 (api.php)
header('Content-Type: application/json; charset=utf-8');

// 设置错误处理
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('仅支持POST请求');
    }

    // 获取原始POST数据
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('JSON数据格式错误: ' . json_last_error_msg());
    }

    // 验证必要参数
    if (empty($data['image_urls']) || !is_array($data['image_urls'])) {
        throw new Exception('参数错误: image_urls必须为非空数组');
    }

    if (empty($data['prompt']) || !is_string($data['prompt'])) {
        throw new Exception('参数错误: prompt必须为非空字符串');
    }

    // 创建API处理器
    $api = new ImageUnderstandingAPI();

    // 处理请求
    $result = $api->processImages($data['image_urls'], $data['prompt']);

    // 输出结果
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    // 错误处理
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}

// 使用示例脚本 (example_usage.php)
class ExampleUsage
{
    public static function demo()
    {
        $images = [
            'https://example.com/image1.jpg',
            'https://example.com/image2.jpg',
            'https://example.com/image3.jpg',
            'https://example.com/image4.jpg'
        ];

        $prompt = '在一个科幻实验室中，科学家们发现了一种新的能源晶体，但晶体突然失控引发危机。主角必须做出艰难选择来拯救实验室。';

        $api = new ImageUnderstandingAPI();
        $result = $api->processImages($images, $prompt);

        // 处理结果
        if (isset($result['error'])) {
            echo "错误: " . $result['error'] . PHP_EOL;
        } else {
            // 输出API返回的JSON
            echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        }
    }
}

// 如果直接访问此文件，显示使用说明
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__) && !isset($_SERVER['REQUEST_METHOD'])) {
    echo "图片理解API接口\n";
    echo "使用方法：通过POST请求调用，传递JSON格式参数\n";
    echo "参数格式：\n";
    echo "{\n";
    echo "  \"images\": [\"图片URL1\", \"图片URL2\", \"图片URL3\"],\n";
    echo "  \"prompt\": \"总提示词内容\"\n";
    echo "}\n";
    echo "\n最少需要3张图片。\n";
}
