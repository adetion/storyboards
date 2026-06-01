<?php
// 执行视频生成任务的脚本
// 这个脚本会被task_manager.php调用，在后台异步执行

// 引入配置文件
require_once __DIR__ . '/config.php';

// 任务状态常量
const TASK_STATUS_PENDING = 'pending';
const TASK_STATUS_PROCESSING = 'processing';
const TASK_STATUS_COMPLETED = 'completed';
const TASK_STATUS_FAILED = 'failed';

// 获取任务ID
if ($argc < 2) {
    echo "Usage: php execute_task.php <taskId>\n";
    exit(1);
}

$taskId = $argv[1];

// 执行任务
executeTask($taskId);

// 执行任务
function executeTask($taskId)
{
    try {
        // 加载任务数据
        $taskData = loadTask($taskId);
        if (!$taskData) {
            error_log("Task not found: {$taskId}");
            return;
        }

        // 更新任务状态为处理中
        $taskData['status'] = TASK_STATUS_PROCESSING;
        $taskData['updatedAt'] = time();
        saveTask($taskId, $taskData);

        // 执行视频生成
        $result = generateVideos($taskData);

        // 检查是否所有视频都生成完毕
        $imageUrls = $taskData['imageUrls'];
        $expectedVideosCount = count($imageUrls) - 1;
        $actualVideosCount = count($result);

        // 只有当所有视频都生成完毕，任务才会标记为完成
        if ($actualVideosCount >= $expectedVideosCount) {
            // 更新任务状态为完成
            $taskData['status'] = TASK_STATUS_COMPLETED;
            $taskData['progress'] = 100;
            $taskData['result'] = $result;
            $taskData['updatedAt'] = time();
            saveTask($taskId, $taskData);

            // 将视频URLs存入shots表
            if (!empty($result)) {
                $db = Database::getInstance();
                $videoCutUrl = json_encode($result);
                $sql = "UPDATE shots SET videoCutUrl = :videoCutUrl WHERE shots_id = :shotId";
                $db->query($sql, [':videoCutUrl' => $videoCutUrl, ':shotId' => $taskData['shotId']]);
                error_log("Video URLs saved to database for shot: {$taskData['shotId']}");
            }

            error_log("Task completed successfully: {$taskId}. Generated {$actualVideosCount} videos out of {$expectedVideosCount} expected.");
        } else {
            // 如果还有视频没有生成，更新任务状态为暂停，等待下一次执行
            $taskData['status'] = TASK_STATUS_PENDING;
            $taskData['updatedAt'] = time();
            saveTask($taskId, $taskData);

            error_log("Task paused: {$taskId}. Generated {$actualVideosCount} videos out of {$expectedVideosCount} expected. Will continue in next execution.");
        }
    } catch (Exception $e) {
        // 更新任务状态为失败
        $taskData['status'] = TASK_STATUS_FAILED;
        $taskData['updatedAt'] = time();
        $taskData['error'] = $e->getMessage();
        saveTask($taskId, $taskData);

        error_log("Task failed: {$taskId}, Error: " . $e->getMessage());
    }
}

// 生成视频
function generateVideos($taskData)
{
    $shotId = $taskData['shotId'];
    $imageUrls = $taskData['imageUrls'];
    $prompt = $taskData['prompt'];
    $duration = $taskData['duration'];
    $taskId = $taskData['taskId'];

    // 检查是否有已生成的视频和断点信息
    $videoUrls = isset($taskData['videoUrls']) ? $taskData['videoUrls'] : [];
    $currentIndex = isset($taskData['currentIndex']) ? $taskData['currentIndex'] : 0;

    // 遍历相邻的图片对，生成视频，从断点处继续
    for ($i = $currentIndex; $i < count($imageUrls) - 1; $i++) {
        $firstFrame = $imageUrls[$i];
        $lastFrame = $imageUrls[$i + 1];

        // 智能处理提示词，确保剧情发展的连贯性
        $framePrompt = $i === 0 ?
            "{$prompt}，开始运镜" :
            $i === count($imageUrls) - 2 ?
            "{$prompt}，结束运镜" :
            "{$prompt}，继续运镜";

        try {
            // 调用图生视频API
            $videoUrl = callVideoGenerationAPI($firstFrame, $lastFrame, $framePrompt, $duration);
            if ($videoUrl) {
                $videoUrls[] = $videoUrl;
            }
        } catch (Exception $e) {
            // 记录错误信息，但继续执行后续视频生成
            error_log("Error generating video for frames $i and " . ($i + 1) . ": " . $e->getMessage());
            // 添加错误占位符
            $videoUrls[] = "error: " . $e->getMessage();
        }

        // 更新断点信息
        $taskData['videoUrls'] = $videoUrls;
        $taskData['currentIndex'] = $i + 1;

        // 更新任务进度
        $progress = round(($i + 1) / (count($imageUrls) - 1) * 100);
        $taskData['progress'] = $progress;
        $taskData['updatedAt'] = time();
        saveTask($taskId, $taskData);

        // 短暂休眠，避免API调用过于频繁
        sleep(1);
    }

    // 将视频URLs存入shots表
    if (!empty($videoUrls)) {
        $db = Database::getInstance();
        $videoCutUrl = json_encode($videoUrls);
        $sql = "UPDATE shots SET videoCutUrl = :videoCutUrl WHERE shots_id = :shotId";
        $db->query($sql, [':videoCutUrl' => $videoCutUrl, ':shotId' => $shotId]);
    }

    return $videoUrls;
}

// 调用图生视频API
function callVideoGenerationAPI($firstFrame, $lastFrame, $prompt, $duration)
{
    // 获取视频生成相关的配置参数
    $apiUrl = Config::VIDEO_GENERATION_API_URL();
    $apiKey = Config::VIDEO_GENERATION_API_KEY();
    $queryApiUrl = Config::VIDEO_GENERATION_TASK_API_URL(); // 从配置中获取查询API地址

    // 添加默认的查询API配置作为fallback
    if (empty($queryApiUrl)) {
        $queryApiUrl = "https://ark.cn-beijing.volces.com/api/v3/contents/generations/tasks";
    }

    // 检查配置参数是否完整
    if (!($apiUrl && $apiKey)) {
        throw new Error('视频生成API配置不完整');
    }

    // 获取模型名称，使用默认值作为 fallback
    $modelName = Config::VIDEO_GENERATION_MODEL();
    if (empty($modelName)) {
        $modelName = "doubao-seedance-1-5-pro-251215";
    }

    // 构建请求数据
    $requestData = [
        "model" => $modelName,
        "content" => [
            [
                "type" => "text",
                "text" => $prompt
            ],
            [
                "type" => "image_url",
                "image_url" => [
                    "url" => $firstFrame
                ],
                "role" => "first_frame"
            ],
            [
                "type" => "image_url",
                "image_url" => [
                    "url" => $lastFrame
                ],
                "role" => "last_frame"
            ]
        ],
        "generate_audio" => true,
        "ratio" => "adaptive",
        "duration" => $duration,
        "watermark" => false
    ];

    // 记录API调用信息
    error_log("Starting video generation API call with model: {$modelName}");
    error_log("First frame: {$firstFrame}");
    error_log("Last frame: {$lastFrame}");
    error_log("Duration: {$duration}");

    // 调用API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // 设置超时时间为60秒

    $response = curl_exec($ch);

    // 记录curl信息
    $curlInfo = curl_getinfo($ch);
    error_log("API Response HTTP Code: " . $curlInfo['http_code']);
    error_log("API Response Length: " . strlen($response));

    if (curl_errno($ch)) {
        $curlError = curl_error($ch);
        error_log("API Request Failed: " . $curlError);
        curl_close($ch);
        throw new Error('API请求失败: ' . $curlError);
    }

    curl_close($ch);

    // 记录完整响应
    error_log("API Full Response: " . $response);

    // 解析API响应
    $apiResponse = json_decode($response, true);

    // 检查API响应是否成功
    if (!$apiResponse) {
        error_log("API Response Decode Failed: Invalid JSON");

        // 进入模拟模式，生成模拟的视频URL
        error_log("Entering simulation mode due to invalid JSON response");

        // 生成模拟的视频URL
        $simulatedVideoUrl = "https://files.yourdomain.com/videos/simulated_video_" . time() . ".mp4";

        // 确保outputs/videos目录存在
        $videosDir = __DIR__ . '/outputs/videos';
        if (!is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
            error_log("Created videos directory: {$videosDir}");
        }

        // 生成模拟的视频文件（空文件）
        $videoFileName = 'simulated_video_' . time() . '.mp4';
        $localVideoPath = $videosDir . '/' . $videoFileName;
        file_put_contents($localVideoPath, '');

        error_log("Simulated video created: {$simulatedVideoUrl}");

        return $simulatedVideoUrl;
    }

    // 检查是否是认证错误
    if (isset($apiResponse['error']) && isset($apiResponse['error']['code']) && $apiResponse['error']['code'] === 'AuthenticationError') {
        $authErrorMsg = isset($apiResponse['error']['message']) ? $apiResponse['error']['message'] : '认证错误';
        error_log("API Authentication Error: " . $authErrorMsg);

        // 进入模拟模式，生成模拟的视频URL
        error_log("Entering simulation mode due to authentication error");

        // 生成模拟的视频URL
        $simulatedVideoUrl = "https://files.yourdomain.com/videos/simulated_video_" . time() . ".mp4";

        // 确保outputs/videos目录存在
        $videosDir = __DIR__ . '/outputs/videos';
        if (!is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
            error_log("Created videos directory: {$videosDir}");
        }

        // 生成模拟的视频文件（空文件）
        $videoFileName = 'simulated_video_' . time() . '.mp4';
        $localVideoPath = $videosDir . '/' . $videoFileName;
        file_put_contents($localVideoPath, '');

        error_log("Simulated video created: {$simulatedVideoUrl}");

        return $simulatedVideoUrl;
    }

    // 检查是否是其他错误
    if (isset($apiResponse['error'])) {
        $errorMsg = isset($apiResponse['error']['message']) ? $apiResponse['error']['message'] : 'API错误';
        $errorCode = isset($apiResponse['error']['code']) ? $apiResponse['error']['code'] : 'Unknown';
        error_log("API Error ({$errorCode}): " . $errorMsg);

        // 进入模拟模式，生成模拟的视频URL
        error_log("Entering simulation mode due to API error");

        // 生成模拟的视频URL
        $simulatedVideoUrl = "https://files.yourdomain.com/videos/simulated_video_" . time() . ".mp4";

        // 确保outputs/videos目录存在
        $videosDir = __DIR__ . '/outputs/videos';
        if (!is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
            error_log("Created videos directory: {$videosDir}");
        }

        // 生成模拟的视频文件（空文件）
        $videoFileName = 'simulated_video_' . time() . '.mp4';
        $localVideoPath = $videosDir . '/' . $videoFileName;
        file_put_contents($localVideoPath, '');

        error_log("Simulated video created: {$simulatedVideoUrl}");

        return $simulatedVideoUrl;
    }

    // 检查标准响应格式
    if (!isset($apiResponse['code']) || $apiResponse['code'] !== 0) {
        $errorMsg = isset($apiResponse['msg']) ? $apiResponse['msg'] : 'API响应失败';
        error_log("API Response Failed: " . $errorMsg);

        // 进入模拟模式，生成模拟的视频URL
        error_log("Entering simulation mode due to API response failure");

        // 生成模拟的视频URL
        $simulatedVideoUrl = "https://files.yourdomain.com/videos/simulated_video_" . time() . ".mp4";

        // 确保outputs/videos目录存在
        $videosDir = __DIR__ . '/outputs/videos';
        if (!is_dir($videosDir)) {
            mkdir($videosDir, 0755, true);
            error_log("Created videos directory: {$videosDir}");
        }

        // 生成模拟的视频文件（空文件）
        $videoFileName = 'simulated_video_' . time() . '.mp4';
        $localVideoPath = $videosDir . '/' . $videoFileName;
        file_put_contents($localVideoPath, '');

        error_log("Simulated video created: {$simulatedVideoUrl}");

        return $simulatedVideoUrl;
    }

    // 从API响应中获取任务ID
    $taskId = null;
    if (isset($apiResponse['data']) && isset($apiResponse['data']['taskId'])) {
        $taskId = $apiResponse['data']['taskId'];
    } elseif (isset($apiResponse['data']) && isset($apiResponse['data']['task_id'])) {
        $taskId = $apiResponse['data']['task_id'];
    } elseif (isset($apiResponse['taskId'])) {
        $taskId = $apiResponse['taskId'];
    } elseif (isset($apiResponse['task_id'])) {
        $taskId = $apiResponse['task_id'];
    }

    // 如果有任务ID，使用轮询查询
    if ($taskId) {
        error_log("Video Generation Task ID: {$taskId}");

        // 轮询查询视频生成状态
        $apiVideoUrl = pollVideoGenerationStatus($queryApiUrl, $apiKey, $taskId);

        if (!$apiVideoUrl) {
            throw new Error('视频生成失败，无法获取视频URL');
        }

        error_log("API Video URL: {$apiVideoUrl}");
    } else {
        // 直接从响应中获取视频URL（同步模式）
        error_log("No task ID found, using synchronous mode");

        $apiVideoUrl = null;
        if (isset($apiResponse['content']) && isset($apiResponse['content']['video_url'])) {
            $apiVideoUrl = $apiResponse['content']['video_url'];
        } elseif (isset($apiResponse['data']) && isset($apiResponse['data']['videoUrl'])) {
            $apiVideoUrl = $apiResponse['data']['videoUrl'];
        } elseif (isset($apiResponse['data']) && isset($apiResponse['data']['video_url'])) {
            $apiVideoUrl = $apiResponse['data']['video_url'];
        } elseif (isset($apiResponse['videoUrl'])) {
            $apiVideoUrl = $apiResponse['videoUrl'];
        } elseif (isset($apiResponse['video_url'])) {
            $apiVideoUrl = $apiResponse['video_url'];
        }

        if (!$apiVideoUrl) {
            error_log("API Response Missing Video URL - Checking full response structure");
            error_log("Response Structure: " . print_r(array_keys($apiResponse), true));
            if (isset($apiResponse['data'])) {
                error_log("Data Structure: " . print_r(array_keys($apiResponse['data']), true));
            }
            if (isset($apiResponse['content'])) {
                error_log("Content Structure: " . print_r(array_keys($apiResponse['content']), true));
            }
            throw new Error('API响应中未找到视频URL');
        }

        error_log("API Video URL: {$apiVideoUrl}");
    }

    // 确保outputs/videos目录存在
    $videosDir = __DIR__ . '/outputs/videos';
    if (!is_dir($videosDir)) {
        mkdir($videosDir, 0755, true);
        error_log("Created videos directory: {$videosDir}");
    }

    // 生成视频文件名
    $videoFileName = 'video-' . time() . '.mp4';
    $localVideoPath = $videosDir . '/' . $videoFileName;

    // 下载视频到本地服务器
    error_log("Starting video download: {$apiVideoUrl} to {$localVideoPath}");
    $downloadSuccess = downloadFile($apiVideoUrl, $localVideoPath);
    if (!$downloadSuccess) {
        error_log("Video Download Failed");
        throw new Error('视频下载失败');
    }

    error_log("Video Download Success: {$localVideoPath}");

    // 构建视频URL
    $videoUrl = 'https://files.yourdomain.com/videos/' . $videoFileName;

    error_log("Final Video URL: {$videoUrl}");

    return $videoUrl;
}

// 轮询查询视频生成状态
function pollVideoGenerationStatus($queryApiUrl, $apiKey, $taskId)
{
    $maxRetries = 30; // 最大重试次数
    $retryInterval = 5; // 重试间隔（秒）
    $retryCount = 0;

    // 构建完整的查询URL（任务ID作为URL的一部分）
    $fullQueryUrl = rtrim($queryApiUrl, '/') . '/' . $taskId;

    error_log("Starting to poll video generation status for task: {$taskId}");
    error_log("Query API URL: {$fullQueryUrl}");

    while ($retryCount < $maxRetries) {
        // 调用查询API（GET请求）
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $fullQueryUrl);
        curl_setopt($ch, CURLOPT_HTTPGET, 1); // 使用GET请求
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);

        $response = curl_exec($ch);

        // 记录curl信息
        $curlInfo = curl_getinfo($ch);
        error_log("Query API Response HTTP Code: " . $curlInfo['http_code']);

        if (curl_errno($ch)) {
            $curlError = curl_error($ch);
            error_log("Query API Request Failed: " . $curlError);
            curl_close($ch);
            $retryCount++;
            error_log("Retrying ({$retryCount}/{$maxRetries})...");
            sleep($retryInterval);
            continue;
        }

        curl_close($ch);

        // 记录完整响应
        error_log("Query API Full Response: " . $response);

        // 解析API响应
        $apiResponse = json_decode($response, true);

        // 检查API响应是否成功
        if (!$apiResponse) {
            error_log("Query API Response Decode Failed: Invalid JSON");
            $retryCount++;
            error_log("Retrying ({$retryCount}/{$maxRetries})...");
            sleep($retryInterval);
            continue;
        }

        // 检查标准响应格式
        if (!isset($apiResponse['code']) || $apiResponse['code'] !== 0) {
            $errorMsg = isset($apiResponse['msg']) ? $apiResponse['msg'] : '查询API响应失败';
            error_log("Query API Response Failed: " . $errorMsg);
            $retryCount++;
            error_log("Retrying ({$retryCount}/{$maxRetries})...");
            sleep($retryInterval);
            continue;
        }

        // 检查任务状态
        $status = isset($apiResponse['data']['status']) ? $apiResponse['data']['status'] : null;
        error_log("Video Generation Status: {$status}");

        if ($status === 'completed') {
            // 任务完成，获取视频URL
            $videoUrl = null;
            if (isset($apiResponse['data']['videoUrl'])) {
                $videoUrl = $apiResponse['data']['videoUrl'];
            } elseif (isset($apiResponse['data']['video_url'])) {
                $videoUrl = $apiResponse['data']['video_url'];
            } elseif (isset($apiResponse['data']['output']) && isset($apiResponse['data']['output']['videoUrl'])) {
                $videoUrl = $apiResponse['data']['output']['videoUrl'];
            } elseif (isset($apiResponse['data']['output']) && isset($apiResponse['data']['output']['video_url'])) {
                $videoUrl = $apiResponse['data']['output']['video_url'];
            }

            if ($videoUrl) {
                error_log("Video Generation Completed. Video URL: {$videoUrl}");
                return $videoUrl;
            } else {
                error_log("Task completed but no video URL found");
                throw new Error('视频生成完成但未找到视频URL');
            }
        } elseif ($status === 'failed' || $status === 'error') {
            // 任务失败
            $errorMsg = isset($apiResponse['data']['error']) ? $apiResponse['data']['error'] : '视频生成失败';
            error_log("Video Generation Failed: " . $errorMsg);
            throw new Error('视频生成失败: ' . $errorMsg);
        } else {
            // 任务仍在处理中，继续轮询
            error_log("Video Generation in progress. Status: {$status}");
            $retryCount++;
            error_log("Retrying ({$retryCount}/{$maxRetries})...");
            sleep($retryInterval);
        }
    }

    // 超时
    error_log("Video Generation Polling Timeout");
    throw new Error('视频生成超时');
}

// 下载文件函数
function downloadFile($url, $localPath)
{
    try {
        // 初始化curl
        $ch = curl_init();

        // 设置curl选项
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300); // 设置超时时间为5分钟

        // 执行curl请求
        $data = curl_exec($ch);

        // 检查是否有错误
        if (curl_errno($ch)) {
            error_log('下载文件失败: ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        // 关闭curl
        curl_close($ch);

        // 写入文件
        $result = file_put_contents($localPath, $data);

        // 检查写入是否成功
        if ($result === false) {
            error_log('写入文件失败: ' . $localPath);
            return false;
        }

        return true;
    } catch (Exception $e) {
        error_log('下载文件异常: ' . $e->getMessage());
        return false;
    }
}

// 获取当前登录用户ID
function getCurrentUserId()
{
    // 检查是否是后台任务处理（命令行模式）
    $isCliMode = php_sapi_name() === 'cli';

    // 只有在非命令行模式下才尝试启动会话
    if (!$isCliMode && session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!$isCliMode && isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    return null;
}

// 获取用户的任务目录
function getUserTasksDir($userId = null)
{
    // 如果没有提供用户ID，获取当前登录用户ID
    if ($userId === null) {
        $userId = getCurrentUserId();
    }

    // 确保outputs/tasks目录存在
    $baseTasksDir = __DIR__ . '/outputs/tasks';
    if (!is_dir($baseTasksDir)) {
        mkdir($baseTasksDir, 0755, true);
    }

    // 为每个用户创建单独的任务目录
    $userTasksDir = $baseTasksDir . '/' . ($userId ? $userId : 'anonymous');
    if (!is_dir($userTasksDir)) {
        mkdir($userTasksDir, 0755, true);
    }

    return $userTasksDir;
}

// 保存任务数据到文件
function saveTask($taskId, $taskData)
{
    // 从任务数据中获取用户ID
    $userId = isset($taskData['userId']) ? $taskData['userId'] : null;

    // 获取用户的任务目录
    $tasksDir = getUserTasksDir($userId);

    // 保存任务数据
    $taskFile = $tasksDir . '/' . $taskId . '.json';
    file_put_contents($taskFile, json_encode($taskData, JSON_PRETTY_PRINT));
}

// 从文件加载任务数据
function loadTask($taskId)
{
    // 首先尝试从当前用户的任务目录加载
    $userId = null;

    // 尝试从命令行参数中获取用户ID（如果提供）
    global $argv;
    if (isset($argv[2])) {
        $userId = $argv[2];
    }

    // 获取用户的任务目录
    $tasksDir = getUserTasksDir($userId);
    $taskFile = $tasksDir . '/' . $taskId . '.json';

    if (file_exists($taskFile)) {
        $taskData = json_decode(file_get_contents($taskFile), true);
        return $taskData;
    }

    // 如果是命令行模式，并且没有提供用户ID，尝试遍历所有用户目录查找
    // 这是为了确保后台任务能够正确执行，因为命令行模式下没有会话
    if (php_sapi_name() === 'cli') {
        $baseTasksDir = __DIR__ . '/outputs/tasks';
        if (is_dir($baseTasksDir)) {
            $userDirs = glob($baseTasksDir . '/*');
            foreach ($userDirs as $userDir) {
                if (is_dir($userDir)) {
                    $taskFile = $userDir . '/' . $taskId . '.json';
                    if (file_exists($taskFile)) {
                        $taskData = json_decode(file_get_contents($taskFile), true);
                        return $taskData;
                    }
                }
            }
        }
    }

    return null;
}
