<?php
/**
 * TaskManager - 统一任务管理类
 * 用于管理所有类型的任务，包括创建、查询、更新任务状态等
 */

require_once __DIR__ . '/Database.php';

class TaskManager {
    // 任务状态枚举
    const STATUS_PENDING = 0;     // 待处理
    const STATUS_PROCESSING = 1;  // 处理中
    const STATUS_COMPLETED = 2;   // 已完成
    const STATUS_FAILED = 3;      // 失败
    const STATUS_CANCELLED = 4;   // 已取消
    
    // 任务类型枚举
    const TYPE_NOVEL_TO_SCRIPT = 'novel_to_script';         // 小说转剧本
    const TYPE_SCRIPT_TO_STORYBOARD = 'script_to_storyboard'; // 剧本转分镜
    const TYPE_STORYBOARD_MANAGEMENT = 'storyboard_management'; // 分镜管理
    const TYPE_GUSHIBAN = 'gushiban';                       // 故事板
    const TYPE_SCHEDULE = 'schedule';                       // 拍摄计划
    const TYPE_ANNOUNCEMENT = 'announcement';               // 拍摄通告
    
    // 单例实例
    private static $instance = null;
    private $db;
    private $pdo;
    
    // 私有构造方法
    private function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getPdo();
        
    }
    
    // 获取单例实例
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    
    
    /**
     * 创建新任务
     * @param int|null $userId 用户ID
     * @param string $taskType 任务类型
     * @param string $title 任务标题
     * @param array $inputData 输入数据
     * @param array $taskDetails 任务详情
     * @param string|null $externalTaskId 外部任务ID，如果未提供则自动生成
     * @return string 核心任务号task_id
     */
    public function createTask($userId, $taskType, $title, $inputData = [], $taskDetails = [], $externalTaskId = null) {
        try {
            $this->pdo->beginTransaction();
            
            // 生成真正的核心任务号task_id
            $coreTaskId = $externalTaskId ?: $taskType . '_' . uniqid('', true);
            
            // 确保user_id可以为NULL
            $sql = "INSERT INTO tasks (user_id, task_type, title, status, progress, input_data, task_id) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            // 详细记录input_data信息，帮助调试
            error_log("TaskManager - Attempting to encode input_data: " . print_r($inputData, true));
            error_log("TaskManager - input_data keys: " . implode(', ', array_keys($inputData)));
            
            // 移除JSON_PARTIAL_OUTPUT_ON_ERROR选项，确保json_encode在失败时返回false
            $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE;
            
            // 先处理输入数据，确保所有字符串都是有效的UTF-8
            $processedInputData = $this->processInputData($inputData);
            
            $jsonInputData = json_encode($processedInputData, $jsonOptions);
            
            error_log("TaskManager - JSON encode result: " . ($jsonInputData === false ? "FALSE" : "SUCCESS: " . $jsonInputData));
            
            if ($jsonInputData === false) {
                // 记录json_encode失败的原因
                $jsonError = json_last_error_msg();
                error_log("TaskManager - JSON encode failed: $jsonError for input data type: " . gettype($inputData));
                
                // 创建一个绝对可靠的简化input_data，只包含最基本的数据
                $simpleInputData = [
                    'text_length' => isset($inputData['text_length']) ? (int)$inputData['text_length'] : 0,
                    'max_rounds' => isset($inputData['max_rounds']) ? (int)$inputData['max_rounds'] : 0,
                    'task_type' => 'novel_to_script',
                    'created_at' => date('Y-m-d H:i:s'),
                    'status' => 'processed'
                ];
                
                // 再次尝试JSON编码，使用更严格的错误处理
                $jsonInputData = json_encode($simpleInputData, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
                error_log("TaskManager - Simplified JSON encode result: " . ($jsonInputData === false ? "FALSE" : "SUCCESS: " . $jsonInputData));
                
                // 作为最后的手段，直接使用字符串JSON，确保有数据存入
                if ($jsonInputData === false) {
                    $jsonInputData = '{"text_length":' . $simpleInputData['text_length'] . ',"max_rounds":' . $simpleInputData['max_rounds'] . ',"task_type":"novel_to_script"}';
                    error_log("TaskManager - Using fallback JSON string: $jsonInputData");
                }
            }
            
            $stmt->execute([
                $userId,
                $taskType,
                $title,
                self::STATUS_PENDING,
                0,
                $jsonInputData,
                $coreTaskId
            ]);
            
            $autoIncrementId = $this->pdo->lastInsertId();
            
            // 插入任务详情，使用核心任务号task_id作为外键
            if (!empty($taskDetails)) {
                $sql = "INSERT INTO task_details (task_id, `key`, value) VALUES (?, ?, ?)";
                $stmt = $this->pdo->prepare($sql);
                
                foreach ($taskDetails as $key => $value) {
                    $stmt->execute([$coreTaskId, $key, json_encode($value)]);
                }
            }
            
            // 插入初始日志，使用核心任务号task_id作为外键
            $this->addTaskLog($coreTaskId, self::STATUS_PENDING, "任务已创建");
            
            $this->pdo->commit();
            return $coreTaskId;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("TaskManager - 创建任务失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 更新任务状态
     * @param string $taskId 核心任务号task_id
     * @param int $status 任务状态
     * @param int $progress 任务进度(0-100)
     * @param array $outputData 输出数据
     * @param string $errorMessage 错误信息
     * @return bool 是否成功
     */
    public function updateTaskStatus($taskId, $status, $progress = null, $outputData = null, $errorMessage = null) {
        try {
            // 使用纯索引数组构建参数，确保与SQL占位符顺序一致
            $updateData = [];
            $sql = "UPDATE tasks SET status = ?";
            // 先添加状态参数
            $updateData[] = $status;
            
            if ($progress !== null) {
                $sql .= ", progress = ?";
                $updateData[] = $progress;
            }
            
            // 不更新output_data字段，避免数据过大导致更新失败
            // if ($outputData !== null) {
            //     $sql .= ", output_data = ?";
            //     $updateData[] = json_encode($outputData);
            // }
            
            // 如果任务完成或失败，更新完成时间
            if ($status === self::STATUS_COMPLETED || $status === self::STATUS_FAILED) {
                $sql .= ", completed_at = CURRENT_TIMESTAMP";
            }
            
            // 使用task_id（外部任务ID）作为WHERE条件，而不是自增ID
            $sql .= " WHERE task_id = ?";
            $updateData[] = $taskId;
            
            error_log("TaskManager - updateTaskStatus SQL: $sql");
            error_log("TaskManager - updateTaskStatus params: " . print_r($updateData, true));
            error_log("TaskManager - updateTaskStatus taskId: $taskId");
            
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute($updateData);
            
            error_log("TaskManager - updateTaskStatus result: $result, rows affected: " . $stmt->rowCount());
            
            // 插入状态变更日志 - 添加错误处理
            $statusText = $this->getStatusText($status);
            try {
                $this->addTaskLog($taskId, $status, "任务状态变更为: $statusText" . ($errorMessage ? ", 错误信息: $errorMessage" : ""));
            } catch (Exception $e) {
                error_log("TaskManager - 添加任务日志失败: " . $e->getMessage());
                // 继续执行，不中断主流程
            }
            
            return $result && $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新任务状态失败: " . $e->getMessage());
            // 记录详细错误信息
            $errorInfo = $this->pdo->errorInfo();
            error_log("TaskManager - SQL错误信息: " . print_r($errorInfo, true));
            error_log("TaskManager - SQL语句: $sql");
            error_log("TaskManager - 参数: " . print_r($updateData, true));
            return false;
        }
    }
    
    /**
     * 获取任务信息
     * @param int $taskId 任务ID
     * @return array|null 任务信息
     */
    public function getTask($taskId) {
        try {
            $sql = "SELECT * FROM tasks WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                $task['input_data'] = json_decode($task['input_data'], true) ?: [];
                $task['output_data'] = json_decode($task['output_data'], true) ?: [];
                $task['details'] = $this->getTaskDetails($taskId);
                $task['logs'] = $this->getTaskLogs($taskId);
            }
            
            return $task;
        } catch (Exception $e) {
            error_log("TaskManager - 获取任务信息失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 根据外部任务ID获取任务信息
     * @param string $externalTaskId 外部任务ID
     * @return array|null 任务信息
     */
    public function getTaskByExternalId($externalTaskId) {
        try {
            $sql = "SELECT * FROM tasks WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$externalTaskId]);
            $task = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($task) {
                $task['input_data'] = json_decode($task['input_data'], true) ?: [];
                $task['output_data'] = json_decode($task['output_data'], true) ?: [];
                $task['details'] = $this->getTaskDetails($externalTaskId);
                $task['logs'] = $this->getTaskLogs($externalTaskId);
            }
            
            return $task;
        } catch (Exception $e) {
            error_log("TaskManager - 根据外部ID获取任务失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 获取用户的任务列表
     * @param int $userId 用户ID
     * @param string $taskType 任务类型（可选）
     * @param int $status 任务状态（可选）
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @return array 任务列表
     */
    public function getUserTasks($userId, $taskType = null, $status = null, $limit = 20, $offset = 0) {
        try {
            $sql = "SELECT * FROM tasks WHERE user_id = ?";
            $params = [$userId];
            
            if ($taskType !== null) {
                $sql .= " AND task_type = ?";
                $params[] = $taskType;
            }
            
            if ($status !== null) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 处理JSON数据
            foreach ($tasks as &$task) {
                $task['input_data'] = json_decode($task['input_data'], true) ?: [];
                $task['output_data'] = json_decode($task['output_data'], true) ?: [];
            }
            
            return $tasks;
        } catch (Exception $e) {
            error_log("TaskManager - 获取用户任务列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取任务详情
     * @param int $taskId 任务ID
     * @return array 任务详情
     */
    public function getTaskDetails($taskId) {
        try {
            $sql = "SELECT `key`, value FROM task_details WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($details as $detail) {
                $result[$detail['key']] = json_decode($detail['value'], true) ?: $detail['value'];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("TaskManager - 获取任务详情失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 获取任务日志
     * @param int $taskId 任务ID
     * @param int $limit 限制数量
     * @return array 任务日志
     */
    public function getTaskLogs($taskId, $limit = 50) {
        try {
            $sql = "SELECT * FROM task_logs WHERE task_id = ? ORDER BY created_at DESC LIMIT ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId, $limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取任务日志失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 添加任务日志
     * @param string $taskId 任务ID (字符串类型，支持外部task_id)
     * @param int $status 状态
     * @param string $message 日志消息
     * @return bool 是否成功
     */
    public function addTaskLog($taskId, $status, $message) {
        try {
            $sql = "INSERT INTO task_logs (task_id, status, message) VALUES (?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId, $status, $message]);
            return true;
        } catch (Exception $e) {
            $errorMessage = "TaskManager - 添加任务日志失败: " . $e->getMessage();
            error_log($errorMessage);
            // 检查是否是数据类型错误
            if (strpos($e->getMessage(), "Incorrect integer value") !== false) {
                // 如果是数据类型错误，尝试调整表结构
                try {
                    // 尝试修改task_id字段类型为VARCHAR(100)
                    $this->pdo->exec("ALTER TABLE task_logs CHANGE task_id task_id VARCHAR(100) NOT NULL");
                    error_log("TaskManager - 已自动修改task_logs表的task_id字段类型为VARCHAR(100)");
                    // 再次尝试插入日志
                    $stmt = $this->pdo->prepare($sql);
                    $stmt->execute([$taskId, $status, $message]);
                    return true;
                } catch (Exception $e2) {
                    error_log("TaskManager - 自动修复task_logs表失败: " . $e2->getMessage());
                }
            }
            return false;
        }
    }
    
    /**
     * 更新任务进度
     * @param string $taskId 任务ID (字符串类型，支持外部task_id)
     * @param int $progress 进度(0-100)
     * @param string $message 进度消息
     * @return bool 是否成功
     */
    public function updateTaskProgress($taskId, $progress, $message = null) {
        try {
            $progress = max(0, min(100, $progress));
            
            $sql = "UPDATE tasks SET progress = ? WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$progress, $taskId]);
            
            // 添加进度日志
            if ($message) {
                $this->addTaskLog($taskId, self::STATUS_PROCESSING, $message);
            } else {
                $this->addTaskLog($taskId, self::STATUS_PROCESSING, "进度更新到 $progress%");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("TaskManager - 更新任务进度失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 根据外部任务ID更新任务
     * @param string $externalTaskId 外部任务ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateTaskByExternalId($externalTaskId, $updates) {
        try {
            $sql = "UPDATE tasks SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                // 跳过output_data字段更新，避免数据过大导致更新失败
                if ($key === 'output_data') {
                    continue;
                }
                
                $sql .= "$key = ?, ";
                if (in_array($key, ['input_data']) && is_array($value)) {
                    $params[] = json_encode($value);
                } else {
                    $params[] = $value;
                }
            }
            
            // 如果没有要更新的字段，直接返回成功
            if (count($params) === 1) { // 只包含外部ID参数
                return true;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE task_id = ?";
            $params[] = $externalTaskId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 根据外部ID更新任务失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除任务
     * @param string $taskId 外部任务ID
     * @return bool 是否成功
     */
    public function deleteTask($taskId) {
        try {
            $this->pdo->beginTransaction();
            
            // 删除任务日志
            $sql = "DELETE FROM task_logs WHERE task_id = ?";
            $this->pdo->prepare($sql)->execute([$taskId]);
            
            // 删除任务详情
            $sql = "DELETE FROM task_details WHERE task_id = ?";
            $this->pdo->prepare($sql)->execute([$taskId]);
            
            // 删除任务主表 - 使用task_id（外部任务ID）作为条件，与其他方法保持一致
            $sql = "DELETE FROM tasks WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            
            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("TaskManager - 删除任务失败: " . $e->getMessage());
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
     * 获取任务类型文本
     * @param string $taskType 任务类型
     * @return string 任务类型文本
     */
    public function getTaskTypeText($taskType) {
        $typeMap = [
            self::TYPE_NOVEL_TO_SCRIPT => '小说转剧本',
            self::TYPE_SCRIPT_TO_STORYBOARD => '剧本转分镜',
            self::TYPE_STORYBOARD_MANAGEMENT => '分镜管理',
            self::TYPE_GUSHIBAN => '故事板',
            self::TYPE_SCHEDULE => '拍摄计划',
            self::TYPE_ANNOUNCEMENT => '拍摄通告'
        ];
        
        return $typeMap[$taskType] ?? '未知类型';
    }
    
    /**
     * 处理输入数据，确保所有字符串都是有效的UTF-8
     * @param mixed $data 输入数据
     * @return mixed 处理后的数据
     */
    private function processInputData($data) {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = $this->processInputData($value);
            }
        } elseif (is_string($data)) {
            // 确保字符串是有效的UTF-8
            $data = mb_convert_encoding($data, 'UTF-8', 'UTF-8');
            // 移除无效的UTF-8字符
            $data = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $data);
        }
        return $data;
    }
    
    // -----------------------
    // 剧本相关方法
    // -----------------------
    
    /**
     * 创建剧本
     * @param int $taskId 任务ID
     * @param string $content 剧本内容
     * @param string $title 剧本标题
     * @param string $author 作者
     * @return int 剧本ID
     */
    public function createScript($taskId, $content, $title = '', $author = '') {
        try {
            $sql = "INSERT INTO scripts (task_id, content, title, author) VALUES (?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId, $content, $title, $author]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("TaskManager - 创建剧本失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据任务ID获取剧本
     * @param int $taskId 任务ID
     * @return array|null 剧本信息
     */
    public function getScriptByTaskId($taskId) {
        try {
            $sql = "SELECT * FROM scripts WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取剧本失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新剧本
     * @param int $scriptId 剧本ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateScript($scriptId, $updates) {
        try {
            $sql = "UPDATE scripts SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id = ?";
            $params[] = $scriptId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新剧本失败: " . $e->getMessage());
            return false;
        }
    }
    
    // -----------------------
    // 场次相关方法
    // -----------------------
    
    /**
     * 创建场次
     * @param int $taskId 任务ID
     * @param string $sceneNo 场次编号
     * @param string $sceneName 场次名称
     * @param string $location 地点
     * @param string $dayNight 昼夜
     * @param int $sortOrder 排序索引
     * @return int 场次ID
     */
    public function createScene($taskId, $sceneNo, $sceneName = '', $location = '', $dayNight = '', $sortOrder = 0) {
        try {
            $sql = "INSERT INTO scenes (task_id, scene_id, scene_name, location, day_night, sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId, $sceneNo, $sceneName, $location, $dayNight, $sortOrder]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("TaskManager - 创建场次失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据任务ID获取场次列表
     * @param int $taskId 任务ID
     * @return array 场次列表
     */
    public function getScenesByTaskId($taskId) {
        try {
            $sql = "SELECT * FROM scenes WHERE task_id = ? ORDER BY sort_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取场次列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据场次ID获取场次
     * @param string $sceneId 场次ID
     * @return array|null 场次信息
     */
    public function getSceneById($sceneId) {
        try {
            $sql = "SELECT * FROM scenes WHERE scene_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sceneId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取场次失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新场次
     * @param string $sceneId 场次ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateScene($sceneId, $updates) {
        try {
            $sql = "UPDATE scenes SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE scene_id = ?";
            $params[] = $sceneId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新场次失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除场次
     * @param string $sceneId 场次ID
     * @return bool 是否成功
     */
    public function deleteScene($sceneId) {
        try {
            $this->pdo->beginTransaction();
            
            // 删除场次下的所有分镜
            $sql = "DELETE FROM shots WHERE scenes_id = ?";
            $this->pdo->prepare($sql)->execute([$sceneId]);
            
            // 删除场次
            $sql = "DELETE FROM scenes WHERE scene_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sceneId]);
            
            $this->pdo->commit();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("TaskManager - 删除场次失败: " . $e->getMessage());
            return false;
        }
    }
    
    // -----------------------
    // 分镜相关方法
    // -----------------------
    
    /**
     * 创建分镜
     * @param int $taskId 任务ID
     * @param string $sceneId 场次ID
     * @param string $shotNo 镜号
     * @param array $shotData 分镜数据
     * @param int $sortOrder 排序索引
     * @return int 分镜ID
     */
    public function createStoryboard($taskId, $sceneId, $shotNo, $shotData = [], $sortOrder = 0) {
        try {
            // shots表的字段名与storyboards表不同，需要适配
            $sql = "INSERT INTO shots (task_id, scenes_id, shots_id, shotType, duration, content, remark, 
                    sceneExpectation, sound, cameraAngle, cameraMovement, cameraEquipment, lensFocalLength, 
                    compositionFocus, lightTone, location, time, weather, dialogue, script, characters, 
                    characterCostumes, characterMakeup, characterActions, props, customContent, imageUrl, 
                    sort_order) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $taskId,
                $sceneId,
                $shotNo,
                $shotData['shotType'] ?? '',
                $shotData['duration'] ?? 0,
                $shotData['content'] ?? '',
                $shotData['remark'] ?? '',
                $shotData['sceneExpectation'] ?? '',
                $shotData['sound'] ?? '',
                $shotData['cameraAngle'] ?? '',
                $shotData['cameraMovement'] ?? '',
                $shotData['cameraEquipment'] ?? '',
                $shotData['lensFocalLength'] ?? '',
                $shotData['compositionFocus'] ?? '',
                $shotData['lightTone'] ?? '',
                $shotData['location'] ?? '',
                $shotData['time'] ?? '',
                $shotData['weather'] ?? '',
                $shotData['dialogue'] ?? '',
                $shotData['script'] ?? '',
                $shotData['characters'] ?? '',
                $shotData['characterCostumes'] ?? '',
                $shotData['characterMakeup'] ?? '',
                $shotData['characterActions'] ?? '',
                $shotData['props'] ?? '',
                $shotData['customContent'] ?? '',
                $shotData['imageUrl'] ?? '',
                $sortOrder
            ]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("TaskManager - 创建分镜失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据场次ID获取分镜列表
     * @param string $sceneId 场次ID
     * @return array 分镜列表
     */
    public function getStoryboardsBySceneId($sceneId) {
        try {
            $sql = "SELECT * FROM shots WHERE scenes_id = ? ORDER BY sort_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$sceneId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取分镜列表失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据任务ID获取所有分镜
     * @param int $taskId 任务ID
     * @return array 分镜列表
     */
    public function getAllStoryboardsByTaskId($taskId) {
        try {
            $sql = "SELECT s.*, sc.scene_id, sc.scene_name 
                    FROM shots s 
                    LEFT JOIN scenes sc ON s.scenes_id = sc.scene_id 
                    WHERE s.task_id = ? 
                    ORDER BY sc.sort_order ASC, s.sort_order ASC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            error_log("TaskManager - 获取所有分镜失败: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * 根据ID获取分镜
     * @param int $storyboardId 分镜ID
     * @return array|null 分镜信息
     */
    public function getStoryboardById($storyboardId) {
        try {
            $sql = "SELECT * FROM shots WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$storyboardId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            // 如果没有找到记录，返回null
            return $result !== false ? $result : null;
        } catch (Exception $e) {
            error_log("TaskManager - 获取分镜失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新分镜
     * @param int $storyboardId 分镜ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateStoryboard($storyboardId, $updates) {
        try {
            $sql = "UPDATE shots SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id = ?";
            $params[] = $storyboardId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新分镜失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 删除分镜
     * @param int $storyboardId 分镜ID
     * @return bool 是否成功
     */
    public function deleteStoryboard($storyboardId) {
        try {
            $sql = "DELETE FROM shots WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$storyboardId]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 删除分镜失败: " . $e->getMessage());
            return false;
        }
    }
    
    // -----------------------
    // 拍摄计划相关方法
    // -----------------------
    
    /**
     * 创建拍摄计划
     * @param int $taskId 任务ID
     * @param array $scheduleData 拍摄计划数据
     * @return int 拍摄计划ID
     */
    public function createSchedule($taskId, $scheduleData = []) {
        try {
            $sql = "INSERT INTO schedules (task_id, name, shooting_date, shooting_location, scene_ids, 
                    crew_info, equipment_info, notes) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $taskId,
                $scheduleData['name'] ?? '',
                $scheduleData['shooting_date'] ?? date('Y-m-d'),
                $scheduleData['shooting_location'] ?? '',
                json_encode($scheduleData['scene_ids'] ?? []),
                json_encode($scheduleData['crew_info'] ?? []),
                json_encode($scheduleData['equipment_info'] ?? []),
                $scheduleData['notes'] ?? ''
            ]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("TaskManager - 创建拍摄计划失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据任务ID获取拍摄计划
     * @param int $taskId 任务ID
     * @return array|null 拍摄计划信息
     */
    public function getScheduleByTaskId($taskId) {
        try {
            $sql = "SELECT * FROM schedules WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($schedule) {
                $schedule['scene_ids'] = json_decode($schedule['scene_ids'], true) ?: [];
                $schedule['crew_info'] = json_decode($schedule['crew_info'], true) ?: [];
                $schedule['equipment_info'] = json_decode($schedule['equipment_info'], true) ?: [];
            }
            
            return $schedule;
        } catch (Exception $e) {
            error_log("TaskManager - 获取拍摄计划失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新拍摄计划
     * @param int $scheduleId 拍摄计划ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateSchedule($scheduleId, $updates) {
        try {
            $sql = "UPDATE schedules SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                if (in_array($key, ['scene_ids', 'crew_info', 'equipment_info'])) {
                    $value = json_encode($value);
                }
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id = ?";
            $params[] = $scheduleId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新拍摄计划失败: " . $e->getMessage());
            return false;
        }
    }
    
    // -----------------------
    // 拍摄通告相关方法
    // -----------------------
    
    /**
     * 创建拍摄通告
     * @param int $taskId 任务ID
     * @param array $announcementData 拍摄通告数据
     * @return int 拍摄通告ID
     */
    public function createAnnouncement($taskId, $announcementData = []) {
        try {
            $sql = "INSERT INTO announcements (task_id, schedule_id, title, content, recipients, 
                    issue_date, effective_date) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                $taskId,
                $announcementData['schedule_id'] ?? null,
                $announcementData['title'] ?? '',
                json_encode($announcementData['content'] ?? []),
                json_encode($announcementData['recipients'] ?? []),
                $announcementData['issue_date'] ?? date('Y-m-d H:i:s'),
                $announcementData['effective_date'] ?? date('Y-m-d H:i:s')
            ]);
            return $this->pdo->lastInsertId();
        } catch (Exception $e) {
            error_log("TaskManager - 创建拍摄通告失败: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * 根据任务ID获取拍摄通告
     * @param int $taskId 任务ID
     * @return array|null 拍摄通告信息
     */
    public function getAnnouncementByTaskId($taskId) {
        try {
            $sql = "SELECT * FROM announcements WHERE task_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$taskId]);
            $announcement = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($announcement) {
                $announcement['content'] = json_decode($announcement['content'], true) ?: [];
                $announcement['recipients'] = json_decode($announcement['recipients'], true) ?: [];
            }
            
            return $announcement;
        } catch (Exception $e) {
            error_log("TaskManager - 获取拍摄通告失败: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 更新拍摄通告
     * @param int $announcementId 拍摄通告ID
     * @param array $updates 更新内容
     * @return bool 是否成功
     */
    public function updateAnnouncement($announcementId, $updates) {
        try {
            $sql = "UPDATE announcements SET ";
            $params = [];
            
            foreach ($updates as $key => $value) {
                if (in_array($key, ['content', 'recipients'])) {
                    $value = json_encode($value);
                }
                $sql .= "$key = ?, ";
                $params[] = $value;
            }
            
            $sql = rtrim($sql, ", ") . " WHERE id = ?";
            $params[] = $announcementId;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新拍摄通告失败: " . $e->getMessage());
            return false;
        }
    }
    
    // -----------------------
    // 排序相关方法
    // -----------------------
    
    /**
     * 更新排序索引
     * @param string $table 表名
     * @param mixed $id 记录ID
     * @param int $orderIndex 排序索引
     * @return bool 是否成功
     */
    public function updateOrderIndex($table, $id, $orderIndex) {
        try {
            $validTables = ['scenes', 'shots'];
            if (!in_array($table, $validTables)) {
                throw new Exception("Invalid table name");
            }
            
            // 根据不同表使用不同的ID字段和排序字段
            if ($table === 'scenes') {
                // scenes表使用scene_id作为场景标识，sort_order作为排序字段
                $sql = "UPDATE $table SET sort_order = ? WHERE scene_id = ?";
            } else if ($table === 'shots') {
                // shots表使用shots_id作为分镜标识，sort_order作为排序字段
                $sql = "UPDATE $table SET sort_order = ? WHERE shots_id = ?";
            } else {
                throw new Exception("Invalid table name");
            }
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$orderIndex, $id]);
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            error_log("TaskManager - 更新排序索引失败: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 批量更新排序索引
     * @param string $table 表名
     * @param array $updates 批量更新数据，格式: [id => order_index, ...]
     * @return bool 是否成功
     */
    public function batchUpdateOrderIndex($table, $updates) {
        try {
            $validTables = ['scenes', 'shots'];
            if (!in_array($table, $validTables)) {
                throw new Exception("Invalid table name");
            }
            
            $this->pdo->beginTransaction();
            
            // 根据不同表使用不同的ID字段和排序字段
            if ($table === 'scenes') {
                // scenes表使用scene_id作为场景标识，sort_order作为排序字段
                $sql = "UPDATE $table SET sort_order = ? WHERE scene_id = ?";
            } else if ($table === 'shots') {
                // shots表使用shots_id作为分镜标识，sort_order作为排序字段
                $sql = "UPDATE $table SET sort_order = ? WHERE shots_id = ?";
            } else {
                throw new Exception("Invalid table name");
            }
            
            $stmt = $this->pdo->prepare($sql);
            
            foreach ($updates as $id => $orderIndex) {
                $stmt->execute([$orderIndex, $id]);
            }
            
            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("TaskManager - 批量更新排序索引失败: " . $e->getMessage());
            return false;
        }
    }
}
?>
