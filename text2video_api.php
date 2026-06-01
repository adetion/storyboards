<?php
// text2video_api.php - 视频生成API
// 实现文本生成剧本和剧本生成分镜功能

// 引入配置文件
require_once __DIR__ . '/config.php';

// 确保错误日志目录存在
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', Config::LOG_DIR . 'php_errors.log');

// 确保缓存目录存在
$cacheDir = Config::CACHE_DIR;
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// 确保上传目录存在   uploadDir
$uploadDir = Config::UPLOAD_DIR;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// 确保输出目录存在
$outputDir = Config::OUTPUT_DIR;
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}

// 确保引用图片目录存在
$referenceDir = $uploadDir . 'references/';
if (!is_dir($referenceDir)) {
    mkdir($referenceDir, 0755, true);
}

// 确保分镜图片目录存在
$storyboardDir = $uploadDir . 'storyboards/';
if (!is_dir($storyboardDir)) {
    mkdir($storyboardDir, 0755, true);
}

/**
 * 记录日志
 * @param string $message 日志消息
 * @param string $level 日志级别
 */
function logMessage($message, $level = 'info') {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] [$level] $message");
}

/**
 * 保存任务状态到缓存文件
 * @param string $taskId 任务ID
 * @param array $statusData 状态数据
 */
function saveTaskStatusToCache($taskId, $statusData) {
    global $cacheDir;
    try {
        $cacheFile = $cacheDir . '/task_' . md5($taskId) . '.json';
        $statusData['last_updated'] = time();
        file_put_contents($cacheFile, json_encode($statusData));
        logMessage('任务状态文件缓存更新成功: ' . $cacheFile);
    } catch (Exception $cacheError) {
        logMessage('任务状态文件缓存更新失败: ' . $cacheError->getMessage(), 'error');
    }
}

/**
 * 从缓存文件获取任务状态
 * @param string $taskId 任务ID
 * @return array|null 任务状态数据
 */
function getTaskStatusFromCache($taskId) {
    global $cacheDir;
    try {
        $cacheFile = $cacheDir . '/task_' . md5($taskId) . '.json';
        if (file_exists($cacheFile)) {
            $content = file_get_contents($cacheFile);
            return json_decode($content, true);
        }
    } catch (Exception $cacheError) {
        logMessage('任务状态文件缓存读取失败: ' . $cacheError->getMessage(), 'error');
    }
    return null;
}

/**
 * 调用文生文API
 * @param string $prompt 提示词
 * @param string $model 模型名称
 * @return string|null 生成的文本
 */
function callTextToTextAPI($prompt, $model = null) {
    try {
        $apiKey = Config::DEEPSEEK_API_KEY();
        $apiUrl = Config::DEEPSEEK_API_URL();
        $apiModel = $model ?: Config::DEEPSEEK_MODEL();
        
        if (empty($apiKey) || empty($apiUrl) || empty($apiModel)) {
            logMessage('文生文API配置不完整', 'error');
            return null;
        }
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $data = [
            'model' => $apiModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'max_tokens' => Config::MAX_TOKENS,
            'temperature' => Config::TEMPERATURE
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            logMessage('文生文API请求失败: ' . curl_error($ch), 'error');
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['choices'][0]['message']['content'])) {
            return $responseData['choices'][0]['message']['content'];
        } else {
            logMessage('文生文API响应格式错误: ' . json_encode($responseData), 'error');
            return null;
        }
        
    } catch (Exception $e) {
        logMessage('文生文API调用错误: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * 调用图生文API（图片识别）
 * @param string $imagePath 图片路径
 * @return string|null 识别结果
 */
function callImageToTextAPI($imagePath) {
    try {
        $apiKey = Config::IMG2TEXT_API_KEY();
        $apiUrl = Config::IMG2TEXT_API_URL();
        $apiModel = Config::IMG2TEXT_API_MODEL();
        
        if (empty($apiKey) || empty($apiUrl) || empty($apiModel)) {
            logMessage('图生文API配置不完整', 'error');
            return null;
        }
        
        // 检查图片是否存在
        if (!file_exists($imagePath)) {
            logMessage('图片文件不存在: ' . $imagePath, 'error');
            return null;
        }
        
        // 将图片转换为base64
        $imageData = file_get_contents($imagePath);
        $base64Image = base64_encode($imageData);
        $imageType = pathinfo($imagePath, PATHINFO_EXTENSION);
        $imageDataUrl = 'data:image/' . $imageType . ';base64,' . $base64Image;
        
        $headers = [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ];
        
        $data = [
            'model' => $apiModel,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => '请详细描述图片中的内容，包括场景、人物、动作、表情等，形成一个连贯的故事描述。'
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => $imageDataUrl
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => Config::MAX_TOKENS,
            'temperature' => Config::TEMPERATURE
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            logMessage('图生文API请求失败: ' . curl_error($ch), 'error');
            curl_close($ch);
            return null;
        }
        
        curl_close($ch);
        
        $responseData = json_decode($response, true);
        
        if (isset($responseData['choices'][0]['message']['content'])) {
            return $responseData['choices'][0]['message']['content'];
        } else {
            logMessage('图生文API响应格式错误: ' . json_encode($responseData), 'error');
            return null;
        }
        
    } catch (Exception $e) {
        logMessage('图生文API调用错误: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * 生成剧本从提示词
 * @param string $prompt 提示词
 * @param string $inputType 输入类型 (text 或 image)
 * @param string $imagePath 图片路径（如果输入类型是image）
 * @return string|null 生成的剧本
 */
function generateScriptFromPrompt($prompt, $inputType = 'text', $imagePath = null) {
    try {
        logMessage('开始生成剧本，输入类型: ' . $inputType);
        
        // 如果输入是图片，先进行图片识别
        if ($inputType === 'image' && $imagePath) {
            logMessage('输入是图片，开始进行图片识别');
            $imageDescription = callImageToTextAPI($imagePath);
            
            if (!$imageDescription) {
                logMessage('图片识别失败，使用默认提示词', 'error');
                // 使用默认提示词，确保不会为空
                $imageDescription = !empty($prompt) ? $prompt : '根据图片内容生成一个详细的剧本';
            }
            
            logMessage('图片识别结果: ' . substr($imageDescription, 0, 100) . '...');
            $prompt = $imageDescription;
        }
        
        // 确保提示词不为空
        if (empty($prompt)) {
            logMessage('提示词为空，使用默认提示词', 'warning');
            $prompt = '生成一个详细的剧本，包括场景描述、角色对话、动作说明等';
        }
        
        // 构建剧本生成提示词
        $scriptPrompt = "请根据以下内容生成一个详细的剧本，包括场景描述、角色对话、动作说明等。剧本应该结构完整，有明确的开头、发展和结尾。\n\n内容: $prompt";
        
        logMessage('调用文生文API生成剧本');
        $script = callTextToTextAPI($scriptPrompt);
        
        if (!$script) {
            logMessage('剧本生成失败', 'error');
            return null;
        }
        
        logMessage('剧本生成成功，长度: ' . strlen($script));
        return $script;
        
    } catch (Exception $e) {
        logMessage('生成剧本错误: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * 生成故事板从剧本
 * @param string $script 剧本内容
 * @return string|null 生成的分镜
 */
function generateStoryboardFromScript($script) {
    try {
        logMessage('开始生成分镜');
        
        // 构建分镜生成提示词
        $storyboardPrompt = "请根据以下剧本生成分镜脚本，每个分镜包括镜号、场景描述、角色动作、镜头类型等信息。分镜应该清晰地展示故事的发展过程。\n\n剧本: $script";
        
        logMessage('调用文生文API生成分镜');
        $storyboard = callTextToTextAPI($storyboardPrompt);
        
        if (!$storyboard) {
            logMessage('分镜生成失败', 'error');
            return null;
        }
        
        logMessage('分镜生成成功，长度: ' . strlen($storyboard));
        return $storyboard;
        
    } catch (Exception $e) {
        logMessage('生成分镜错误: ' . $e->getMessage(), 'error');
        return null;
    }
}

/**
 * 处理视频生成请求
 */
function handleVideoGenerationRequest() {
    try {
        // 获取请求参数
        $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : 'text';
        $prompt = isset($_POST['prompt']) ? $_POST['prompt'] : '';
        $userId = isset($_POST['user_id']) ? $_POST['user_id'] : 1;
        
        // 生成任务ID
        $taskId = 'text2video_task_' . uniqid();
        
        // 初始化任务状态
        $taskStatus = [
            'task_id' => $taskId,
            'status' => 'processing',
            'progress' => 0,
            'current_step' => 1,
            'step_info' => '正在生成剧本...',
            'error' => '',
            'data' => []
        ];
        
        // 保存任务状态到缓存
        saveTaskStatusToCache($taskId, $taskStatus);
        
        // 处理文件上传或图片路径（如果输入是图片）
        $imagePath = null;
        if ($inputType === 'image') {
            // 先检查是否通过POST参数传递了图片路径
            if (isset($_POST['image_path']) && !empty($_POST['image_path'])) {
                $imagePath = $_POST['image_path'];
                // 对于图片路径，我们不检查文件是否存在，因为它可能是一个URL
                // 而是在后续的图片识别步骤中处理
                logMessage('使用传递的图片路径: ' . $imagePath);
            } 
            // 再检查是否有文件上传
            else if (isset($_FILES['image'])) {
                $imageFile = $_FILES['image'];
                if ($imageFile['error'] === UPLOAD_ERR_OK) {
                    $imageName = 'upload_' . uniqid() . '.' . pathinfo($imageFile['name'], PATHINFO_EXTENSION);
                    $imagePath = Config::UPLOAD_DIR . $imageName;
                    if (move_uploaded_file($imageFile['tmp_name'], $imagePath)) {
                        logMessage('图片上传成功: ' . $imagePath);
                    } else {
                        logMessage('图片上传失败', 'error');
                        // 即使图片上传失败，也保持inputType为image，因为用户选择了图片上传
                        // 后续的图片识别步骤会处理这种情况
                    }
                } else {
                    logMessage('图片上传错误: ' . $imageFile['error'], 'error');
                    // 即使图片上传错误，也保持inputType为image
                }
            } else {
                logMessage('输入类型为image但未提供图片', 'error');
                // 即使没有提供图片，也保持inputType为image
            }
        }
        
        // 检查输入参数
        if (empty($prompt)) {
            if ($inputType === 'text') {
                // 对于文本输入，提示词为空时使用默认提示词
                logMessage('文本输入，提示词为空，将使用默认提示词', 'warning');
                // 不直接失败，而是让generateScriptFromPrompt函数处理
            } else if ($inputType === 'image') {
                // 对于图片输入，即使没有prompt，也应该继续执行
                logMessage('图片输入，提示词为空，将使用图片内容生成剧本', 'warning');
            }
        }
        
        // 异步处理工作流
        if (function_exists('pcntl_fork')) {
            // 使用pcntl_fork进行异步处理
            $pid = pcntl_fork();
            if ($pid === 0) {
                // 子进程
                processCompleteWorkflow($taskId, $userId, $inputType, $prompt, $imagePath);
                exit(0);
            } else if ($pid > 0) {
                // 父进程
                logMessage('创建子进程处理任务: ' . $pid);
            } else {
                // fork失败，使用curl异步
                logMessage('pcntl_fork失败，使用curl异步处理', 'warning');
                startAsyncProcessing($taskId, $userId, $inputType, $prompt, $imagePath);
            }
        } else {
            // 使用curl异步处理
            logMessage('pcntl_fork不可用，使用curl异步处理');
            startAsyncProcessing($taskId, $userId, $inputType, $prompt, $imagePath);
        }
        
        // 返回任务ID和初始状态
        echo json_encode([
            'success' => true,
            'task_id' => $taskId,
            'status' => 'processing',
            'progress' => 0,
            'current_step' => 1,
            'step_info' => '正在生成剧本...'
        ]);
        
    } catch (Exception $e) {
        logMessage('处理视频生成请求错误: ' . $e->getMessage(), 'error');
        echo json_encode([
            'success' => false,
            'error' => '处理请求失败: ' . $e->getMessage()
        ]);
    }
}

/**
 * 使用curl启动异步处理
 */
function startAsyncProcessing($taskId, $userId, $inputType, $prompt, $imagePath = null) {
    try {
        $asyncUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?action=process_workflow';
        
        $postData = [
            'task_id' => $taskId,
            'user_id' => $userId,
            'input_type' => $inputType,
            'prompt' => $prompt
        ];
        
        // 如果有图片路径，添加到POST数据
        if ($imagePath) {
            $postData['image_path'] = $imagePath;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $asyncUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        
        $response = curl_exec($ch);
        
        if (curl_errno($ch)) {
            logMessage('启动异步处理失败: ' . curl_error($ch), 'error');
        } else {
            logMessage('异步处理已启动');
        }
        
        curl_close($ch);
        
    } catch (Exception $e) {
        logMessage('启动异步处理错误: ' . $e->getMessage(), 'error');
    }
}

/**
 * 处理完整工作流
 */
function processCompleteWorkflow($taskId, $userId, $inputType, $prompt, $imagePath = null) {
    try {
        logMessage('===== 开始执行工作流任务: ' . $taskId . ' =====');
        logMessage('任务参数: inputType=' . $inputType . ', prompt=' . substr($prompt, 0, 100) . '...');
        
        // 更新任务状态 - 生成剧本
        $taskStatus = getTaskStatusFromCache($taskId);
        if (!$taskStatus) {
            $taskStatus = [
                'task_id' => $taskId,
                'status' => 'processing',
                'progress' => 0,
                'current_step' => 1,
                'step_info' => '正在生成剧本...',
                'error' => '',
                'data' => []
            ];
        }
        
        saveTaskStatusToCache($taskId, $taskStatus);
        
        // 对于文本输入，确保提示词不为空
        // 对于图片输入，即使没有prompt，也会使用图片内容生成剧本
        if ($inputType === 'text' && empty($prompt)) {
            logMessage('文本输入，提示词为空，将使用默认提示词', 'warning');
            // 不直接失败，而是让generateScriptFromPrompt函数处理
        } else if ($inputType === 'image' && empty($prompt)) {
            logMessage('图片输入，提示词为空，将使用图片内容生成剧本', 'warning');
        }
        
        // 第一步：生成剧本
        logMessage('===== 第一步：生成剧本 =====');
        $script = generateScriptFromPrompt($prompt, $inputType, $imagePath);
        
        if (!$script) {
            $errorMessage = '剧本生成失败';
            logMessage($errorMessage, 'error');
            $taskStatus['status'] = 'failed';
            $taskStatus['error'] = $errorMessage;
            saveTaskStatusToCache($taskId, $taskStatus);
            return;
        }
        
        $taskStatus['data']['script'] = $script;
        $taskStatus['progress'] = 30;
        saveTaskStatusToCache($taskId, $taskStatus);
        
        // 第二步：生成分镜
        logMessage('===== 第二步：生成分镜 =====');
        $taskStatus['current_step'] = 2;
        $taskStatus['step_info'] = '正在生成分镜...';
        saveTaskStatusToCache($taskId, $taskStatus);
        
        $storyboard = generateStoryboardFromScript($script);
        
        if (!$storyboard) {
            $errorMessage = '分镜生成失败';
            logMessage($errorMessage, 'error');
            $taskStatus['status'] = 'failed';
            $taskStatus['error'] = $errorMessage;
            saveTaskStatusToCache($taskId, $taskStatus);
            return;
        }
        
        $taskStatus['data']['storyboard'] = $storyboard;
        $taskStatus['progress'] = 60;
        saveTaskStatusToCache($taskId, $taskStatus);
        
        // 完成工作流
        logMessage('===== 工作流执行完成 =====');
        $taskStatus['status'] = 'completed';
        $taskStatus['progress'] = 100;
        $taskStatus['step_info'] = '任务完成';
        saveTaskStatusToCache($taskId, $taskStatus);
        
    } catch (Exception $e) {
        logMessage('处理工作流错误: ' . $e->getMessage(), 'error');
        $taskStatus = getTaskStatusFromCache($taskId);
        if ($taskStatus) {
            $taskStatus['status'] = 'failed';
            $taskStatus['error'] = '处理失败: ' . $e->getMessage();
            saveTaskStatusToCache($taskId, $taskStatus);
        }
    }
}

/**
 * 获取任务状态
 */
function getTaskStatus() {
    try {
        $taskId = isset($_GET['task_id']) ? $_GET['task_id'] : '';
        
        if (empty($taskId)) {
            echo json_encode([
                'success' => false,
                'error' => '任务ID不能为空'
            ]);
            return;
        }
        
        // 从缓存获取任务状态
        $taskStatus = getTaskStatusFromCache($taskId);
        
        if (!$taskStatus) {
            echo json_encode([
                'success' => false,
                'error' => '任务不存在'
            ]);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'task_id' => $taskStatus['task_id'],
            'status' => $taskStatus['status'],
            'progress' => $taskStatus['progress'],
            'current_step' => $taskStatus['current_step'],
            'step_info' => $taskStatus['step_info'],
            'error' => $taskStatus['error'],
            'data' => isset($taskStatus['data']) ? $taskStatus['data'] : []
        ]);
        
    } catch (Exception $e) {
        logMessage('获取任务状态错误: ' . $e->getMessage(), 'error');
        echo json_encode([
            'success' => false,
            'error' => '获取任务状态失败: ' . $e->getMessage()
        ]);
    }
}

/**
 * 处理API请求
 */
function handleApiRequest() {
    // 设置响应头
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // 处理OPTIONS请求
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        echo json_encode(['success' => true]);
        return;
    }
    
    // 获取动作参数
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    switch ($action) {
        case 'upload_image':
            // 处理图片上传
            handleImageUpload();
            break;
            
        case 'process_workflow':
            // 处理工作流
            $taskId = isset($_POST['task_id']) ? $_POST['task_id'] : '';
            $userId = isset($_POST['user_id']) ? $_POST['user_id'] : 1;
            $inputType = isset($_POST['input_type']) ? $_POST['input_type'] : 'text';
            $prompt = isset($_POST['prompt']) ? $_POST['prompt'] : '';
            $imagePath = isset($_POST['image_path']) ? $_POST['image_path'] : null;
            
            if (!empty($taskId)) {
                // 直接调用processCompleteWorkflow函数，让它处理提示词为空的情况
                processCompleteWorkflow($taskId, $userId, $inputType, $prompt, $imagePath);
            }
            break;
        
        case 'status':
            // 获取任务状态
            getTaskStatus();
            break;
        
        default:
            // 处理视频生成请求
            handleVideoGenerationRequest();
            break;
    }
}

// 处理图片上传
function handleImageUpload() {
    try {
        if (!isset($_FILES['image'])) {
            echo json_encode([
                'success' => false,
                'error' => '没有上传图片文件'
            ]);
            return;
        }
        
        $imageFile = $_FILES['image'];
        
        if ($imageFile['error'] !== UPLOAD_ERR_OK) {
            echo json_encode([
                'success' => false,
                'error' => '图片上传失败，错误码: ' . $imageFile['error']
            ]);
            return;
        }
        
        // 验证图片大小
        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($imageFile['size'] > $maxSize) {
            echo json_encode([
                'success' => false,
                'error' => '图片大小不能超过5MB'
            ]);
            return;
        }
        
        // 验证图片格式
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!in_array($imageFile['type'], $allowedTypes)) {
            echo json_encode([
                'success' => false,
                'error' => '只支持JPG、PNG、GIF格式的图片'
            ]);
            return;
        }
        
        // 生成唯一文件名
        $imageName = 'upload_' . uniqid() . '.' . pathinfo($imageFile['name'], PATHINFO_EXTENSION);
        $uploadPath = Config::UPLOAD_DIR . $imageName;
        
        // 移动上传文件
        if (move_uploaded_file($imageFile['tmp_name'], $uploadPath)) {
            // 生成图片URL
            $imageUrl = '/uploads/' . $imageName;
            
            echo json_encode([
                'success' => true,
                'image_url' => $imageUrl
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => '图片保存失败'
            ]);
        }
        
    } catch (Exception $e) {
        logMessage('处理图片上传错误: ' . $e->getMessage(), 'error');
        echo json_encode([
            'success' => false,
            'error' => '处理图片上传失败: ' . $e->getMessage()
        ]);
    }
}

// 启动API处理
handleApiRequest();
?>
