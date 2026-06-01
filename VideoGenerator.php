<?php
/**
 * VideoGenerator - 视频生成队列管理类
 * 用于管理视频生成任务队列，支持多任务处理和连续图片对的视频生成
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/TaskManager.php';

class VideoGenerator {
    // 视频生成任务状态
    const STATUS_PENDING = 0;     // 待处理
    const STATUS_PROCESSING = 1;  // 处理中
    const STATUS_COMPLETED = 2;   // 已完成
    const STATUS_FAILED = 3;      // 失败
    const STATUS_CANCELLED = 4;   // 已取消
    
    // 单例实例
    private static $instance = null;
    private $db;
    private $pdo;
    private $taskManager;
    
    // 私有构造方法
    private function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPdo();
        $this->taskManager = TaskManager::getInstance();
    }
    
    // 获取单例实例
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 创建视频生成任务
     * @param int $userId 用户ID
     * @param string $shotId 分镜ID
     * @param array $imageUrls 图片URL数组
     * @param string $prompt 提示词
     * @param int $duration 视频时长（秒）
     * @param array $prompts 切片提示词数组
     * @return string 视频任务ID
     */
    public function createVideoTask($userId, $shotId, $sceneId, $imageUrls, $prompt, $duration = 5, $prompts = []) {
        try {
            // 生成视频任务ID
            $videoTaskId = 'video_task_' . uniqid('', true);
            
            // 计算需要生成的视频数量（连续图片对的数量）
            $videoCount = max(0, count($imageUrls) - 1);
            
            // 从shots表中获取关联字段的值
            $crewId = null;
            $scenesId = $sceneId;
            $taskId = null;
            
            // 处理sceneId，提取数字部分作为scenesId
            if (is_string($sceneId)) {
                // 提取数字部分
                $numericSceneId = preg_replace('/[^0-9]/', '', $sceneId);
                if (!empty($numericSceneId)) {
                    $scenesId = intval($numericSceneId);
                }
            }
            
            try {
                // 直接使用原始sceneId查询（不转换类型）
                $sql = "SELECT crew_id, scenes_id, task_id FROM shots WHERE shots_id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$shotId]);
                $shotData = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($shotData) {
                    $crewId = $shotData['crew_id'];
                    $scenesId = $shotData['scenes_id'];
                    $taskId = $shotData['task_id'];
                }
            } catch (Exception $e) {
                error_log("VideoGenerator - 获取分镜关联数据失败: " . $e->getMessage());
                // 即使获取失败，也继续创建任务
            }
            
            // 创建视频任务记录
            $sql = "INSERT INTO video_tasks (task_id, user_id, shot_id, crew_id, scenes_id, shot_task_id, image_urls, prompt, prompts, duration, status, progress, total_videos) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $videoTaskId,
                $userId,
                $shotId,
                $crewId,
                $scenesId,
                $taskId,
                json_encode($imageUrls),
                $prompt,
                json_encode($prompts),
                $duration,
                self::STATUS_PENDING,
                0,
                $videoCount
            ]);
            
            // 创建子任务记录（每个连续图片对一个子任务）
            for ($i = 0; $i < $videoCount; $i++) {
                $firstFrameUrl = $imageUrls[$i];
                $lastFrameUrl = $imageUrls[$i + 1];
                
                // 使用对应的切片提示词，如果没有则使用通用提示词
                $subTaskPrompt = $prompt;
                if (!empty($prompts) && isset($prompts[$i])) {
                    $subTaskPrompt = $prompts[$i];
                }
                
                $subTaskId = 'video_subtask_' . uniqid('', true);
                $sql = "INSERT INTO video_subtasks (task_id, sub_task_id, first_frame_url, last_frame_url, prompt, duration, status, video_index) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $subStmt = $this->pdo->prepare($sql);
                $subStmt->execute([
                    $videoTaskId,
                    $subTaskId,
                    $firstFrameUrl,
                    $lastFrameUrl,
                    $subTaskPrompt,
                    $duration,
                    self::STATUS_PENDING,
                    $i
                ]);
            }
            
            return $videoTaskId;
        } catch (Exception $e) {
            error_log("VideoGenerator - 创建视频任务失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 开始处理视频生成任务
     * @param string $videoTaskId 视频任务ID
     * @return bool 是否成功
     */
    public function startVideoTask($videoTaskId) {
        try {
            error_log("VideoGenerator - 开始处理视频任务: " . $videoTaskId);
            
            // 更新任务状态为处理中
            $sql = "UPDATE video_tasks SET status = ? WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([self::STATUS_PROCESSING, $videoTaskId]);
            error_log("VideoGenerator - 更新任务状态为处理中: " . ($result ? '成功' : '失败'));
            
            // 获取所有未完成的子任务（待处理或处理中）
            $sql = "SELECT * FROM video_subtasks WHERE task_id = ? AND status IN (?, ?) ORDER BY video_index ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$videoTaskId, self::STATUS_PENDING, self::STATUS_PROCESSING]);
            $subTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("VideoGenerator - 获取到 " . count($subTasks) . " 个未完成的子任务");
            
            // 逐个处理子任务
            $totalSubTasks = count($subTasks);
            $completedSubTasks = 0;
            
            foreach ($subTasks as $subTask) {
                try {
                    error_log("VideoGenerator - 开始处理子任务: " . $subTask['sub_task_id']);
                    
                    // 更新子任务状态为处理中
                    $sql = "UPDATE video_subtasks SET status = ? WHERE sub_task_id = ?";
                    $subStmt = $this->pdo->prepare($sql);
                    $subResult = $subStmt->execute([self::STATUS_PROCESSING, $subTask['sub_task_id']]);
                    error_log("VideoGenerator - 更新子任务状态为处理中: " . ($subResult ? '成功' : '失败'));
                    
                    // 调用视频生成API
                    $apiResult = $this->callVideoGenerationAPI(
                        $subTask['first_frame_url'],
                        $subTask['last_frame_url'],
                        $subTask['prompt'],
                        $subTask['duration']
                    );
                    
                    error_log("VideoGenerator - API调用结果: " . json_encode($apiResult));
                    
                    if ($apiResult['success']) {
                        // 视频生成任务创建成功，开始轮询状态
                        $taskId = $apiResult['task_id'];
                        $videoUrl = $this->pollVideoGenerationStatus($taskId);
                        
                        error_log("VideoGenerator - 轮询视频生成状态结果: " . ($videoUrl ? $videoUrl : '失败'));
                        
                        if ($videoUrl) {
                            // 视频生成成功，更新子任务状态
                            $sql = "UPDATE video_subtasks SET status = ?, api_task_id = ?, video_url = ? WHERE sub_task_id = ?";
                            $subStmt = $this->pdo->prepare($sql);
                            $subStmt->execute([self::STATUS_COMPLETED, $taskId, $videoUrl, $subTask['sub_task_id']]);
                            $completedSubTasks++;
                            error_log("VideoGenerator - 子任务处理成功: " . $subTask['sub_task_id']);
                        } else {
                            // 视频生成失败
                            $sql = "UPDATE video_subtasks SET status = ?, error_message = ? WHERE sub_task_id = ?";
                            $subStmt = $this->pdo->prepare($sql);
                            $subStmt->execute([self::STATUS_FAILED, '视频生成超时或失败', $subTask['sub_task_id']]);
                            error_log("VideoGenerator - 子任务处理失败: 视频生成超时或失败");
                        }
                    } else {
                        // API调用失败
                        $sql = "UPDATE video_subtasks SET status = ?, error_message = ? WHERE sub_task_id = ?";
                        $subStmt = $this->pdo->prepare($sql);
                        $subStmt->execute([self::STATUS_FAILED, $apiResult['error'], $subTask['sub_task_id']]);
                        error_log("VideoGenerator - 子任务处理失败: " . $apiResult['error']);
                    }
                } catch (Exception $e) {
                    // 子任务处理失败
                    error_log("VideoGenerator - 处理子任务失败: " . $e->getMessage());
                    $sql = "UPDATE video_subtasks SET status = ?, error_message = ? WHERE sub_task_id = ?";
                    $subStmt = $this->pdo->prepare($sql);
                    $subStmt->execute([self::STATUS_FAILED, $e->getMessage(), $subTask['sub_task_id']]);
                }
                
                // 更新任务进度
                $progress = $totalSubTasks > 0 ? round(($completedSubTasks / $totalSubTasks) * 100) : 0;
                $sql = "UPDATE video_tasks SET progress = ? WHERE task_id = ?";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([$progress, $videoTaskId]);
                error_log("VideoGenerator - 更新任务进度: " . $progress . "%");
            }
            
            // 更新任务最终状态
            if ($completedSubTasks === $totalSubTasks) {
                $finalStatus = self::STATUS_COMPLETED;
            } else if ($completedSubTasks === 0) {
                $finalStatus = self::STATUS_FAILED;
            } else {
                $finalStatus = self::STATUS_COMPLETED; // 部分成功也视为完成
            }
            
            error_log("VideoGenerator - 完成子任务数: " . $completedSubTasks . "/" . $totalSubTasks . ", 最终状态: " . $finalStatus);
            
            $sql = "UPDATE video_tasks SET status = ?, completed_at = CURRENT_TIMESTAMP WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $finalResult = $stmt->execute([$finalStatus, $videoTaskId]);
            error_log("VideoGenerator - 更新任务最终状态: " . ($finalResult ? '成功' : '失败'));
            
            return true;
        } catch (Exception $e) {
            error_log("VideoGenerator - 开始处理视频任务失败: " . $e->getMessage());
            // 更新任务状态为失败
            $sql = "UPDATE video_tasks SET status = ?, error_message = ? WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([self::STATUS_FAILED, $e->getMessage(), $videoTaskId]);
            return false;
        }
    }
    
    /**
     * 调用视频生成API
     * @param string $firstFrameUrl 第一帧图片URL
     * @param string $lastFrameUrl 最后一帧图片URL
     * @param string $prompt 提示词
     * @param int $duration 视频时长
     * @return array API调用结果
     */
    private function callVideoGenerationAPI($firstFrameUrl, $lastFrameUrl, $prompt, $duration) {
        try {
            $apiKey = Config::VIDEO_GENERATION_API_KEY();
            $apiUrl = Config::VIDEO_GENERATION_API_URL();
            $model = Config::VIDEO_GENERATION_MODEL();
            
            // 构建请求数据
            $requestData = [
                'model' => $model,
                'content' => [
                    [
                        'type' => 'text',
                        'text' => $prompt
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $firstFrameUrl
                        ],
                        'role' => 'first_frame'
                    ],
                    [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => $lastFrameUrl
                        ],
                        'role' => 'last_frame'
                    ]
                ],
                'generate_audio' => true,
                'ratio' => 'adaptive',
                'duration' => $duration,
                'watermark' => false
            ];
            
            // 构建请求头
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ];
            
            // 初始化curl
            $ch = curl_init();
            
            // 设置curl选项
            curl_setopt($ch, CURLOPT_URL, $apiUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            
            // 执行请求
            $response = curl_exec($ch);
            
            // 检查错误
            if (curl_errno($ch)) {
                $error = curl_error($ch);
                curl_close($ch);
                return [
                    'success' => false,
                    'error' => 'Curl error: ' . $error
                ];
            }
            
            // 获取HTTP状态码
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            // 解析响应
            $responseData = json_decode($response, true);
            
            // 检查响应状态
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'API error: ' . ($responseData['error']['message'] ?? 'Unknown error'),
                    'status_code' => $httpCode
                ];
            }
            
            // 检查是否返回了任务ID
            if (!isset($responseData['id'])) {
                return [
                    'success' => false,
                    'error' => 'Invalid response: No task ID returned'
                ];
            }
            
            return [
                'success' => true,
                'task_id' => $responseData['id']
            ];
        } catch (Exception $e) {
            error_log("VideoGenerator - 调用视频生成API失败: " . $e->getMessage());
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * 轮询视频生成状态
     * @param string $taskId 任务ID
     * @param int $maxRetries 最大重试次数
     * @param int $interval 轮询间隔（秒）
     * @return string|null 生成的视频URL
     */
    private function pollVideoGenerationStatus($taskId, $maxRetries = 60, $interval = 5) {
        try {
            $apiKey = Config::VIDEO_GENERATION_API_KEY();
            $apiUrl = Config::VIDEO_GENERATION_API_URL().'/'. $taskId;
            
            // 构建请求头
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ];
            
            $retryCount = 0;
            while ($retryCount < $maxRetries) {
                // 初始化curl
                $ch = curl_init();
                
                // 设置curl选项
                curl_setopt($ch, CURLOPT_URL, $apiUrl);
                curl_setopt($ch, CURLOPT_HTTPGET, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                
                // 执行请求
                $response = curl_exec($ch);
                
                // 检查错误
                if (curl_errno($ch)) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    error_log("VideoGenerator - 轮询视频状态失败: " . $error);
                    $retryCount++;
                    sleep($interval);
                    continue;
                }
                
                // 获取HTTP状态码
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                // 解析响应
                $responseData = json_decode($response, true);
                
                // 检查响应状态
                if ($httpCode !== 200) {
                    error_log("VideoGenerator - 轮询视频状态API错误: " . ($responseData['error']['message'] ?? 'Unknown error'));
                    $retryCount++;
                    sleep($interval);
                    continue;
                }
                
                // 检查任务状态
                $status = $responseData['status'];
                
                switch ($status) {
                    case 'succeeded':
                        // 任务成功，返回视频URL
                        if (isset($responseData['content']['video_url'])) {
                            return $responseData['content']['video_url'];
                        }
                        return null;
                        
                    case 'failed':
                    case 'expired':
                    case 'cancelled':
                        // 任务失败
                        error_log("VideoGenerator - 视频生成任务失败，状态: " . $status);
                        return null;
                        
                    default:
                        // 任务仍在处理中，继续轮询
                        $retryCount++;
                        sleep($interval);
                        continue 2;
                }
            }
            
            // 超过最大重试次数
            error_log("VideoGenerator - 视频生成任务超时");
            return null;
        } catch (Exception $e) {
            error_log("VideoGenerator - 轮询视频状态失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取视频任务信息
     * @param string $videoTaskId 视频任务ID
     * @return array|null 视频任务信息
     */
    public function getVideoTask($videoTaskId) {
        try {
            $sql = "SELECT * FROM video_tasks WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$videoTaskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                $task['image_urls'] = json_decode($task['image_urls'], true) ?: [];
                $task['sub_tasks'] = $this->getVideoSubTasks($videoTaskId);
            }
            
            return $task;
        } catch (Exception $e) {
            error_log("VideoGenerator - 获取视频任务信息失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取视频子任务列表
     * @param string $videoTaskId 视频任务ID
     * @return array 子任务列表
     */
    public function getVideoSubTasks($videoTaskId) {
        try {
            $sql = "SELECT * FROM video_subtasks WHERE task_id = ? ORDER BY video_index ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$videoTaskId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("VideoGenerator - 获取视频子任务列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取用户的视频任务列表
     * @param int $userId 用户ID
     * @param int $status 状态（可选）
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @param string $shotId 单个分镜ID（可选）
     * @param array $shotIds 多个分镜ID（可选）
     * @return array 视频任务列表
     */
    public function getUserVideoTasks($userId, $status = null, $limit = 20, $offset = 0, $shotId = null, $shotIds = null) {
        try {
            $sql = "SELECT * FROM video_tasks WHERE user_id = ?";
            $params = [$userId];
            
            if ($status !== null) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            // 单个分镜ID查询
            if ($shotId !== null) {
                $sql .= " AND shot_id = ?";
                $params[] = $shotId;
            }
            // 多个分镜ID查询
            else if ($shotIds !== null && is_array($shotIds) && count($shotIds) > 0) {
                $placeholders = str_repeat('?,', count($shotIds) - 1) . '?';
                $sql .= " AND shot_id IN ($placeholders)";
                $params = array_merge($params, $shotIds);
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 处理JSON数据
            foreach ($tasks as &$task) {
                $task['image_urls'] = json_decode($task['image_urls'], true) ?: [];
                // 确保scenes_id字段存在
                if (!isset($task['scenes_id'])) {
                    $task['scenes_id'] = null;
                }
            }
            
            return $tasks;
        } catch (Exception $e) {
            error_log("VideoGenerator - 获取用户视频任务列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 取消视频任务
     * @param string $videoTaskId 视频任务ID
     * @return bool 是否成功
     */
    public function cancelVideoTask($videoTaskId) {
        try {
            $this->pdo->beginTransaction();
            
            // 更新主任务状态
            $sql = "UPDATE video_tasks SET status = ? WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([self::STATUS_CANCELLED, $videoTaskId]);
            
            // 更新所有子任务状态
            $sql = "UPDATE video_subtasks SET status = ? WHERE task_id = ? AND status IN (?, ?)";
            $subStmt = $this->pdo->prepare($sql);
            $subStmt->execute([self::STATUS_CANCELLED, $videoTaskId, self::STATUS_PENDING, self::STATUS_PROCESSING]);
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("VideoGenerator - 取消视频任务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取状态文本
     * @param int $status 状态码
     * @return string 状态文本
     */
    public function getStatusText($status) {
        $statusMap = [
            self::STATUS_PENDING => '待处理',
            self::STATUS_PROCESSING => '处理中',
            self::STATUS_COMPLETED => '已完成',
            self::STATUS_FAILED => '失败',
            self::STATUS_CANCELLED => '已取消'
        ];
        
        return $statusMap[$status] ?? '未知状态';
    }
    
    /**
     * 初始化视频生成相关的数据库表
     * @return bool 是否成功
     */
    public function initDatabase() {
        try {
            // 创建视频任务表
            $sql = "CREATE TABLE IF NOT EXISTS video_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id VARCHAR(100) NOT NULL UNIQUE,
                user_id INT NOT NULL,
                shot_id VARCHAR(50) NOT NULL,
                image_urls TEXT NOT NULL,
                prompt TEXT NOT NULL,
                duration INT NOT NULL DEFAULT 5,
                status INT NOT NULL DEFAULT 0,
                progress INT NOT NULL DEFAULT 0,
                total_videos INT NOT NULL DEFAULT 0,
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->pdo->exec($sql);
            
            // 创建视频子任务表
            $sql = "CREATE TABLE IF NOT EXISTS video_subtasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                task_id VARCHAR(100) NOT NULL,
                sub_task_id VARCHAR(100) NOT NULL UNIQUE,
                first_frame_url TEXT NOT NULL,
                last_frame_url TEXT NOT NULL,
                prompt TEXT NOT NULL,
                duration INT NOT NULL DEFAULT 5,
                status INT NOT NULL DEFAULT 0,
                video_index INT NOT NULL,
                api_task_id VARCHAR(100),
                video_url TEXT,
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                completed_at TIMESTAMP NULL,
                FOREIGN KEY (task_id) REFERENCES video_tasks(task_id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            $this->pdo->exec($sql);
            
            // 创建索引
            try {
                $this->pdo->exec("CREATE INDEX idx_video_tasks_user_id ON video_tasks(user_id)");
            } catch (Exception $e) {
                // 索引已存在，忽略错误
            }
            try {
                $this->pdo->exec("CREATE INDEX idx_video_tasks_shot_id ON video_tasks(shot_id)");
            } catch (Exception $e) {
                // 索引已存在，忽略错误
            }
            try {
                $this->pdo->exec("CREATE INDEX idx_video_subtasks_task_id ON video_subtasks(task_id)");
            } catch (Exception $e) {
                // 索引已存在，忽略错误
            }
            
            return true;
        } catch (Exception $e) {
            error_log("VideoGenerator - 初始化数据库失败: " . $e->getMessage());
            return false;
        }
    }
}

// 初始化数据库表
$videoGenerator = VideoGenerator::getInstance();
$videoGenerator->initDatabase();
?>
