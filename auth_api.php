<?php
// auth.php - 认证API接口

require_once 'config.php';
require_once 'Auth.php';

// 开启会话（如果尚未开启）
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}



// 设置CORS头
// 使用动态Origin，允许包含credentials
$origin = $_SERVER['HTTP_ORIGIN'] ?? '*';
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Credentials: true');
header('Content-Type: application/json');



// 记录请求信息
// error_log("收到请求: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
// error_log("请求时间: " . date('Y-m-d H:i:s'));
// error_log("客户端IP: " . $_SERVER['REMOTE_ADDR']);

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    // error_log("处理OPTIONS预检请求");
    echo json_encode(['success' => true]);
    exit(0);
}

// 获取请求数据
// 如果是POST请求，使用$_POST数组
// 否则，尝试从php://input读取（对于直接访问的情况）
$requestData = $_POST;
if (empty($requestData) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $rawInput = file_get_contents('php://input');
    // error_log("原始POST数据: " . $rawInput);
    $requestData = json_decode($rawInput, true) ?: [];
}
$action = $_GET['action'] ?? '';

// error_log("解析的action: " . $action);
// error_log("请求数据: " . print_r($requestData, true));

// 创建Auth实例
// error_log("创建Auth实例");
$auth = new Auth();
// error_log("Auth实例创建完成");

// 响应函数
function sendResponse($data) {
    echo json_encode($data);
    exit;
}

// 处理注册请求
function handleRegister($auth, $data) {
    // 验证必填字段
    if (empty($data['username']) || empty($data['password']) || empty($data['confirm_password'])) {
        return ['success' => false, 'message' => '用户名、密码和确认密码不能为空'];
    }
    
    if ($data['password'] !== $data['confirm_password']) {
        return ['success' => false, 'message' => '两次输入的密码不一致'];
    }
    
    return $auth->register($data);
}

// 处理登录请求
function handleLogin($auth, $data) {
    if (empty($data['identifier']) || empty($data['password'])) {
        return ['success' => false, 'message' => '账号/手机号/邮箱和密码不能为空'];
    }
    
    $type = $data['type'] ?? 'username';
    return $auth->login($data['identifier'], $data['password'], $type);
}

// 处理验证码登录请求
function handleLoginWithCode($auth, $data) {
    if (empty($data['phone']) || empty($data['code'])) {
        return ['success' => false, 'message' => '手机号和验证码不能为空'];
    }
    
    return $auth->loginWithVerificationCode($data['phone'], $data['code']);
}

// 处理一键登录请求
function handleOneClickLogin($auth, $data) {
    if (empty($data['phone'])) {
        return ['success' => false, 'message' => '手机号不能为空'];
    }
    
    return $auth->loginWithOneClick($data['phone']);
}

// 确保日志目录存在
function ensureLogDirectory() {
    $logDir = __DIR__ . '/logs';
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
}

// 写入日志到文件
function writeSmsLog($message) {
    ensureLogDirectory();
    $logFile = __DIR__ . '/logs/sms_attempts.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    // 同时写入系统错误日志
    error_log($message);
}

// 检查IP请求频率
function checkIpRateLimit($ip) {
    $limitFile = __DIR__ . '/temp/ip_rate_limit.json';
    $limitData = [];
    
    // 确保目录存在
    if (!file_exists(__DIR__ . '/temp')) {
        mkdir(__DIR__ . '/temp', 0755, true);
    }
    
    // 读取现有数据
    if (file_exists($limitFile)) {
        $limitData = json_decode(file_get_contents($limitFile), true) ?? [];
    }
    
    $now = time();
    $window = 60; // 60秒窗口
    $maxRequests = 5; // 最大请求数
    
    // 清理过期数据
    foreach ($limitData as $existingIp => $timestamps) {
        $limitData[$existingIp] = array_filter($timestamps, function($timestamp) use ($now, $window) {
            return $now - $timestamp < $window;
        });
        
        if (empty($limitData[$existingIp])) {
            unset($limitData[$existingIp]);
        }
    }
    
    // 检查当前IP
    if (isset($limitData[$ip])) {
        if (count($limitData[$ip]) >= $maxRequests) {
            return false;
        }
        $limitData[$ip][] = $now;
    } else {
        $limitData[$ip] = [$now];
    }
    
    // 保存数据
    file_put_contents($limitFile, json_encode($limitData));
    return true;
}

// 处理发送短信验证码请求
function handleSendSms($auth, $data) {
    // 记录请求信息
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $phone = $data['phone'] ?? 'empty';
    $referer = $_SERVER['HTTP_REFERER'] ?? 'unknown';
    $requestMethod = $_SERVER['REQUEST_METHOD'] ?? 'unknown';
    $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
    
    $logMessage = "[SMS Request] IP: $ip, UserAgent: $userAgent, Phone: $phone, Referer: $referer, Method: $requestMethod, URI: $requestUri";
    writeSmsLog($logMessage);
    
    // 检查IP请求频率
    if (!checkIpRateLimit($ip)) {
        $errorMessage = "[SMS Error] IP rate limit exceeded: $ip, Phone: $phone";
        writeSmsLog($errorMessage);
        return ['success' => false, 'message' => '请求过于频繁，请稍后重试'];
    }
    
    if (empty($data['phone'])) {
        $errorMessage = "[SMS Error] Empty phone from IP: $ip, UserAgent: $userAgent";
        writeSmsLog($errorMessage);
        return ['success' => false, 'message' => '手机号不能为空'];
    }
    
    try {
        $result = $auth->sendSmsVerification($data['phone']);
        $resultMessage = "[SMS Result] Phone: {$data['phone']}, Success: " . ($result['success'] ? 'Yes' : 'No') . ", Message: " . ($result['message'] ?? 'No message');
        writeSmsLog($resultMessage);
        return $result;
    } catch (Exception $e) {
        $errorMessage = "[SMS Exception] Phone: {$data['phone']}, IP: $ip, Error: " . $e->getMessage();
        writeSmsLog($errorMessage);
        return ['success' => false, 'message' => '发送验证码失败，请稍后重试'];
    }
}

// 处理发送邮件验证码请求
function handleSendEmail($auth, $data) {
    if (empty($data['email'])) {
        return ['success' => false, 'message' => '邮箱不能为空'];
    }
    
    return $auth->sendEmailVerification($data['email']);
}

// 处理获取当前用户请求
function handleGetCurrentUser($auth) {
    $user = $auth->getCurrentUser();
    if ($user) {
        return ['success' => true, 'user' => $user];
    } else {
        return ['success' => false, 'message' => '未登录'];
    }
}

// 处理登出请求
function handleLogout($auth) {
    return $auth->logout();
}

// 处理获取页面内容请求
function handleGetPageContent($auth, $page) {
    $user = $auth->getCurrentUser();
    if ($user) {
        return ['success' => true, 'message' => '已授权访问', 'user' => $user, 'content' => true];
    } else {
        return ['success' => false, 'message' => '未登录，无法访问该页面'];
    }
}

// 处理获取用户所属剧组信息请求
function handleGetUserCrewInfo($auth) {
    require_once 'Database.php';
    
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    try {
        $db = Database::getInstance();
        
        // 1. 查询用户的crew_group字段
        $userInfo = $db->queryOne("SELECT id, crew_group FROM users WHERE id = ?", [$user['id']]);
        
        if (!$userInfo) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        $crewGroup = $userInfo['crew_group'] ?? '';
        $crews = [];
        $crewIdMap = []; // 用于去重
        
        // 2. 如果crew_group不为空，解析剧组ID列表并查询剧组信息
        if (!empty($crewGroup)) {
            $crewIds = array_filter(array_map('trim', explode(',', $crewGroup)));
            
            if (!empty($crewIds)) {
                // 第一个剧组ID为当前剧组
                $currentCrewId = $crewIds[0];
                
                // 构建IN查询
                $placeholders = str_repeat('?,', count($crewIds) - 1) . '?';
                $crewsData = $db->query("SELECT id, name as crew_name, admin_user_id FROM crew WHERE id IN ($placeholders)", $crewIds);
                
                if (is_array($crewsData)) {
                    foreach ($crewsData as $crew) {
                        $crewIdMap[$crew['id']] = [
                            'crew_id' => $crew['id'],
                            'crew_name' => $crew['crew_name'],
                            'is_admin' => $crew['admin_user_id'] === $user['id'] ? 1 : 0,
                            'is_creator' => $crew['admin_user_id'] === $user['id'],
                            'is_current' => $crew['id'] == $currentCrewId // 标记当前剧组
                        ];
                    }
                }
            }
        }
        
        // 3. 查询用户自己创建的剧组
        $createdCrews = $db->query("SELECT id, name as crew_name, admin_user_id FROM crew WHERE admin_user_id = ?", [$user['id']]);
        
        if (is_array($createdCrews)) {
            foreach ($createdCrews as $crew) {
                // 如果该剧组还没有在crewIdMap中（即用户没有通过crew_group加入自己创建的剧组），则添加到crewIdMap中
                if (!isset($crewIdMap[$crew['id']])) {
                    $crewIdMap[$crew['id']] = [
                        'crew_id' => $crew['id'],
                        'crew_name' => $crew['crew_name'],
                        'is_admin' => 1,
                        'is_creator' => true,
                        'is_current' => false // 自己创建的剧组默认为非当前剧组
                    ];
                }
            }
        }
        
        // 将crewIdMap转换为数组
        $crews = array_values($crewIdMap);
        
        // 总是返回数组格式，即使只有一个或没有剧组
        return ['success' => true, 'data' => $crews];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '获取剧组信息失败: ' . $e->getMessage()];
    }
}

// 处理设置当前剧组请求
function handleSetCurrentCrew($auth, $requestData) {
    require_once 'Database.php';
    
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $crewId = isset($requestData['crew_id']) ? intval($requestData['crew_id']) : 0;
    
    if (!$crewId) {
        return ['success' => false, 'message' => '剧组ID不能为空'];
    }
    
    try {
        $db = Database::getInstance();
        
        // 查询用户的crew_group字段
        $userInfo = $db->queryOne("SELECT id, crew_group FROM users WHERE id = ?", [$user['id']]);
        
        if (!$userInfo) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        $crewGroup = $userInfo['crew_group'] ?? '';
        
        if (empty($crewGroup)) {
            return ['success' => false, 'message' => '用户不属于任何剧组'];
        }
        
        // 解析剧组ID列表
        $crewIds = array_filter(array_map('trim', explode(',', $crewGroup)));
        
        // 检查用户是否属于该剧组
        if (!in_array($crewId, $crewIds)) {
            return ['success' => false, 'message' => '您不属于该剧组'];
        }
        
        // 将指定的crew_id移到数组的第一位
        $crewIds = array_diff($crewIds, [$crewId]);
        array_unshift($crewIds, $crewId);
        
        // 重新组合成字符串
        $newCrewGroup = implode(',', $crewIds);
        
        // 更新users表的crew_group字段
        $db->execute("UPDATE users SET crew_group = ? WHERE id = ?", [$newCrewGroup, $user['id']]);
        
        return ['success' => true, 'message' => '当前剧组设置成功'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => '设置当前剧组失败: ' . $e->getMessage()];
    }
}

// 更新用户的crew_group字段
function updateUserCrewGroup($userId) {
    require_once 'Database.php';
    $db = Database::getInstance();
    
    // 查询用户所属的所有剧组ID
    $crewIds = [];
    
    // 1. 查询作为剧组成员的记录
    $memberCrews = $db->query("SELECT DISTINCT c.id as crew_id FROM crew_organization co JOIN crew c ON co.crew_id = c.id WHERE co.admin_user_id = ? AND co.enabled = 1", [$userId]);
    
    if (is_array($memberCrews)) {
        foreach ($memberCrews as $crew) {
            $crewIds[] = $crew['crew_id'];
        }
    }
    
    // 2. 查询作为剧组创建者的记录（如果不在成员表中）
    $creatorCrews = $db->query("SELECT id as crew_id FROM crew WHERE admin_user_id = ?", [$userId]);
    
    if (is_array($creatorCrews)) {
        foreach ($creatorCrews as $crew) {
            if (!in_array($crew['crew_id'], $crewIds)) {
                $crewIds[] = $crew['crew_id'];
            }
        }
    }
    
    // 3. 更新users表的crew_group字段
    if (!empty($crewIds)) {
        $crewGroupStr = implode(',', array_unique($crewIds));
        $db->execute("UPDATE users SET crew_group = ? WHERE id = ?", [$crewGroupStr, $userId]);
    } else {
        $db->execute("UPDATE users SET crew_group = '' WHERE id = ?", [$userId]);
    }
}

// 处理用户自主脱离剧组请求
function handleLeaveCrew($auth) {
    require_once 'Database.php';
    
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    try {
        $db = Database::getInstance();
        // 获取剧组ID（如果提供）
        $crewId = isset($_GET['crewId']) ? intval($_GET['crewId']) : null;
        
        // 开始事务
        $db->beginTransaction();
        
        // 查询用户在剧组中的记录
        $query = "SELECT * FROM crew_organization WHERE user_id = ? AND enabled = 1";
        $params = [$user['id']];
        
        if ($crewId) {
            $query .= " AND crew_id = ?";
            $params[] = $crewId;
        }
        
        $crewMember = $db->queryOne($query, $params);
        
        if (!$crewMember) {
            throw new Exception('您不属于任何剧组或该剧组不存在');
        }
        
        // 查询该剧组的创建者信息
        $crew = $db->queryOne("SELECT admin_user_id FROM crew WHERE id = ?", [$crewMember['crew_id']]);
        
        // 检查是否是剧组创建者
        if ($crew && $crew['admin_user_id'] === $user['id']) {
            throw new Exception('剧组创建者无法脱离自己创建的剧组');
        }
        
        // 检查是否是剧组管理员
        if ($crewMember['is_admin'] == 1) {
            throw new Exception('剧组管理员无法脱离剧组');
        }
        
        // 禁用crew_organization表中的记录
        $db->execute("UPDATE crew_organization SET enabled = 0 WHERE user_id = ? AND crew_id = ?", [$user['id'], $crewMember['crew_id']]);
        
        // 更新用户的crew_group字段
        updateUserCrewGroup($user['id']);
        
        $db->commit();
        return ['success' => true, 'message' => '成功脱离剧组'];
    } catch (Exception $e) {
        $db->rollBack();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 处理获取用户完整资料请求
function handleGetUserProfile($auth) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $profile = $auth->getUserProfile($user['id']);
    if ($profile) {
        return ['success' => true, 'data' => $profile];
    } else {
        return ['success' => false, 'message' => '获取用户资料失败'];
    }
}

// 处理更新用户昵称请求
function handleUpdateNickname($auth, $data) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $nickname = $data['nickname'] ?? '';
    if (empty($nickname)) {
        return ['success' => false, 'message' => '昵称不能为空'];
    }
    
    return $auth->updateNickname($user['id'], $nickname);
}

// 处理重置密码请求
function handleResetPassword($auth, $data) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $newPassword = $data['new_password'] ?? '';
    if (empty($newPassword)) {
        return ['success' => false, 'message' => '新密码不能为空'];
    }
    
    return $auth->resetPassword($user['id'], $newPassword);
}

// 处理获取用户余额请求
function handleGetUserBalance($auth) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $balance = $auth->getUserBalance($user['id']);
    return ['success' => true, 'data' => ['balance' => $balance]];
}

// 处理获取用户积分请求
function handleGetUserPoints($auth) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $points = $auth->getUserPoints($user['id']);
    return ['success' => true, 'data' => ['points' => $points]];
}

// 处理获取充值记录请求
function handleGetRechargeRecords($auth) {
    try {
        $user = $auth->getCurrentUser();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        // 添加调试信息
        // error_log('handleGetRechargeRecords called for user: ' . $user['id']);
        
        $records = $auth->getRechargeRecords($user['id']);
        
        // 添加调试信息
        // error_log('Recharge records: ' . print_r($records, true));
        
        // 确保返回数据可以被JSON编码
        $safeRecords = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $safeRecords[] = array_map(function($value) {
                    // 将非UTF-8编码的字符串转换为UTF-8
                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    return $value;
                }, $record);
            }
        }
        
        return ['success' => true, 'data' => $safeRecords];
    } catch (Exception $e) {
        // error_log('handleGetRechargeRecords Error: ' . $e->getMessage());
        // error_log('Stack trace: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => '获取充值记录失败: ' . $e->getMessage()];
    }
}

// 处理获取消费记录请求
function handleGetConsumptionRecords($auth) {
    try {
        $user = $auth->getCurrentUser();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        // 添加调试信息
        // error_log('handleGetConsumptionRecords called for user: ' . $user['id']);
        
        $records = $auth->getConsumptionRecords($user['id']);
        
        // 添加调试信息
        // error_log('Consumption records: ' . print_r($records, true));
        
        // 确保返回数据可以被JSON编码
        $safeRecords = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $safeRecords[] = array_map(function($value) {
                    // 将非UTF-8编码的字符串转换为UTF-8
                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    return $value;
                }, $record);
            }
        }
        
        return ['success' => true, 'data' => $safeRecords];
    } catch (Exception $e) {
        // error_log('handleGetConsumptionRecords Error: ' . $e->getMessage());
        // error_log('Stack trace: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => '获取消费记录失败: ' . $e->getMessage()];
    }
}

// 处理获取积分记录请求
function handleGetPointsRecords($auth) {
    try {
        $user = $auth->getCurrentUser();
        if (!$user) {
            return ['success' => false, 'message' => '未登录'];
        }
        
        // 获取分页参数
        $page = $_GET['page'] ?? 1;
        $limit = $_GET['limit'] ?? 10;
        
        // 获取积分记录
        $records = $auth->getPointsHistory($user['id'], $limit, $page);
        $total = $auth->getPointsHistoryCount($user['id']);
        
        // 确保返回数据可以被JSON编码
        $safeRecords = [];
        if (is_array($records)) {
            foreach ($records as $record) {
                $safeRecords[] = array_map(function($value) {
                    // 将非UTF-8编码的字符串转换为UTF-8
                    if (is_string($value) && !mb_check_encoding($value, 'UTF-8')) {
                        $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                    }
                    return $value;
                }, $record);
            }
        }
        
        return ['success' => true, 'data' => $safeRecords, 'total' => $total];
    } catch (Exception $e) {
        // error_log('handleGetPointsRecords Error: ' . $e->getMessage());
        // error_log('Stack trace: ' . $e->getTraceAsString());
        return ['success' => false, 'message' => '获取积分记录失败: ' . $e->getMessage()];
    }
}

// 处理获取用户任务请求
function handleGetUserTasks($auth) {
    // error_log("进入handleGetUserTasks函数");
    try {
        // 临时调试模式：允许直接通过URL参数指定user_id
        $user_id = null;
        if (isset($_GET['debug_user_id'])) {
            $user_id = $_GET['debug_user_id'];
            // error_log("使用调试用户ID: " . $user_id);
        } else {
            $user = $auth->getCurrentUser();
            if (!$user) {
                // error_log("获取用户任务失败: 用户未登录");
                return ['success' => false, 'message' => '未登录'];
            }
            $user_id = $user['id'];
            // error_log("当前用户ID: " . $user_id);
        }
        
        // 获取所有用户任务，不分页，使用一个大的合理数值
        $tasks = $auth->getUserTasks($user_id, null, null, 1, 1000);
        // 获取任务总数
        $total = count($tasks);
        
        // error_log("获取到的任务数量: " . count($tasks) . ", 总数: $total");
        
        // 确保返回的数据格式正确
        if (!is_array($tasks)) {
            $tasks = [];
        }
        
        return ['success' => true, 'data' => $tasks, 'total' => $total];
    } catch (Exception $e) {
        // error_log("获取用户任务时发生错误: " . $e->getMessage());
        return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
    }
}

// 处理创建充值记录请求
function handleCreateRecharge($auth, $data) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $orderNo = $data['order_no'] ?? '';
    $amount = $data['amount'] ?? 0;
    $paymentMethod = $data['payment_method'] ?? 'wechat';
    
    if (empty($orderNo) || $amount <= 0) {
        return ['success' => false, 'message' => '无效的订单参数'];
    }
    
    return $auth->createRechargeRecord($user['id'], $orderNo, $amount, $paymentMethod);
}

// 处理设置当前任务请求
function handleSetCurrentTask($auth) {
    try {
        // error_log("开始处理设置当前任务请求");
        
        // 获取当前用户
        $user = $auth->getCurrentUser();
        if (!$user) {
            // error_log("设置当前任务失败: 用户未登录");
            return ['success' => false, 'message' => '未登录'];
        }
        
        $user_id = $user['id'];
        // error_log("当前用户ID: " . $user_id);
        
        // 获取任务号
        $taskNumber = $_GET['taskNumber'] ?? '';
        if (empty($taskNumber)) {
            // error_log("设置当前任务失败: 任务号不能为空");
            return ['success' => false, 'message' => '任务号不能为空'];
        }
        // error_log("要设置的当前任务号: " . $taskNumber);
        
        // 调用Auth类的方法更新当前任务
        $result = $auth->setCurrentTask($user_id, $taskNumber);
        
        if (!$result['success']) {
            // error_log("设置当前任务失败: " . $result['message']);
            return $result;
        }
        
        // error_log("设置当前任务成功");
        return ['success' => true, 'message' => '设置当前任务成功'];
    } catch (Exception $e) {
        // error_log("设置当前任务时发生错误: " . $e->getMessage());
        return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
    }
}

// 处理获取当前任务号请求
function handleGetCurrentTaskNumber($auth) {
    try {
        // error_log("开始处理获取当前任务号请求");
        
        // 获取当前用户
        $user = $auth->getCurrentUser();
        if (!$user) {
            // error_log("获取当前任务号失败: 用户未登录");
            return ['success' => false, 'message' => '未登录'];
        }
        
        $user_id = $user['id'];
        // error_log("当前用户ID: " . $user_id);
        
        // 调用Auth类的完整逻辑方法获取当前任务号
        $result = $auth->getCurrentTaskNumberWithFallback($user_id);
        
        if (!$result['success']) {
            // error_log("获取当前任务号失败: " . $result['message']);
            return $result;
        }
        
        // error_log("获取当前任务号成功，当前任务号: " . $result['data']);
        return ['success' => true, 'data' => $result['data']];
    } catch (Exception $e) {
        // error_log("获取当前任务号时发生错误: " . $e->getMessage());
        return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
    }
}

// 处理创建会员购买记录请求
function handleCreateMembership($auth, $data) {
    $user = $auth->getCurrentUser();
    if (!$user) {
        return ['success' => false, 'message' => '未登录'];
    }
    
    $orderNo = $data['order_no'] ?? '';
    $amount = $data['amount'] ?? 0;
    $itemType = $data['item_type'] ?? 'membership';
    $description = $data['description'] ?? '';
    
    if (empty($orderNo) || $amount <= 0) {
        return ['success' => false, 'message' => '无效的订单参数'];
    }
    
    return $auth->createMembershipRecord($user['id'], $orderNo, $amount, $itemType, $description);
}

// 路由处理
$result = ['success' => false, 'message' => '无效的请求操作'];
// error_log("开始路由处理，action: " . $action);

switch ($action) {
    case 'register':
        // error_log("开始处理注册请求");
        $result = handleRegister($auth, $requestData);
        // error_log("注册请求处理完成，结果: " . print_r($result, true));
        break;
    case 'login':
        // error_log("开始处理登录请求");
        $result = handleLogin($auth, $requestData);
        // error_log("登录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'loginWithCode':
        // error_log("开始处理验证码登录请求");
        $result = handleLoginWithCode($auth, $requestData);
        // error_log("验证码登录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'oneClickLogin':
        // error_log("开始处理一键登录请求");
        $result = handleOneClickLogin($auth, $requestData);
        // error_log("一键登录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'sendSms':
        // error_log("开始处理发送短信验证码请求");
        $result = handleSendSms($auth, $requestData);
        // error_log("发送短信验证码请求处理完成，结果: " . print_r($result, true));
        break;
    case 'sendEmail':
        // error_log("开始处理发送邮件验证码请求");
        $result = handleSendEmail($auth, $requestData);
        // error_log("发送邮件验证码请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getCurrentUser':
        // error_log("开始处理获取当前用户请求");
        $result = handleGetCurrentUser($auth);
        // error_log("获取当前用户请求处理完成，结果: " . print_r($result, true));
        break;
    case 'logout':
        // error_log("开始处理登出请求");
        $result = handleLogout($auth);
        // error_log("登出请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserCrewInfo':
        // error_log("开始处理获取用户所属剧组信息请求");
        $result = handleGetUserCrewInfo($auth);
        // error_log("获取用户所属剧组信息请求处理完成，结果: " . print_r($result, true));
        break;
    case 'setCurrentCrew':
        // error_log("开始处理设置当前剧组请求");
        $result = handleSetCurrentCrew($auth, $requestData);
        // error_log("设置当前剧组请求处理完成，结果: " . print_r($result, true));
        break;
    case 'leaveCrew':
        // error_log("开始处理用户自主脱离剧组请求");
        $result = handleLeaveCrew($auth);
        // error_log("用户自主脱离剧组请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getPageContent':
        $page = $requestData['page'] ?? '';
        // error_log("开始处理获取页面内容请求，页面: " . $page);
        $result = handleGetPageContent($auth, $page);
        // error_log("获取页面内容请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserProfile':
        // error_log("开始处理获取用户资料请求");
        $result = handleGetUserProfile($auth);
        // error_log("获取用户资料请求处理完成，结果: " . print_r($result, true));
        break;
    case 'updateNickname':
        // error_log("开始处理更新昵称请求");
        $result = handleUpdateNickname($auth, $requestData);
        // error_log("更新昵称请求处理完成，结果: " . print_r($result, true));
        break;
    case 'resetPassword':
        // error_log("开始处理重置密码请求");
        $result = handleResetPassword($auth, $requestData);
        // error_log("重置密码请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserBalance':
        // error_log("开始处理获取用户余额请求");
        $result = handleGetUserBalance($auth);
        // error_log("获取用户余额请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserPoints':
        // error_log("开始处理获取用户积分请求");
        $result = handleGetUserPoints($auth);
        // error_log("获取用户积分请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getRechargeRecords':
        // error_log("开始处理获取充值记录请求");
        $result = handleGetRechargeRecords($auth);
        // error_log("获取充值记录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getConsumptionRecords':
        // error_log("开始处理获取消费记录请求");
        $result = handleGetConsumptionRecords($auth);
        // error_log("获取消费记录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getPointsRecords':
        // error_log("开始处理获取积分记录请求");
        $result = handleGetPointsRecords($auth);
        // error_log("获取积分记录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserTasks':
        // error_log("开始处理获取用户任务请求");
        $result = handleGetUserTasks($auth);
        // error_log("获取用户任务请求处理完成，结果: " . print_r($result, true));
        break;
    case 'setCurrentTask':
        // error_log("开始处理设置当前任务请求");
        $result = handleSetCurrentTask($auth);
        // error_log("设置当前任务请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getCurrentTaskNumber':
        // error_log("开始处理获取当前任务号请求");
        $result = handleGetCurrentTaskNumber($auth);
        // error_log("获取当前任务号请求处理完成，结果: " . print_r($result, true));
        break;
    case 'createRecharge':
        // error_log("开始处理创建充值记录请求");
        $result = handleCreateRecharge($auth, $requestData);
        // error_log("创建充值记录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'createMembership':
        // error_log("开始处理创建会员购买记录请求");
        $result = handleCreateMembership($auth, $requestData);
        // error_log("创建会员购买记录请求处理完成，结果: " . print_r($result, true));
        break;
    case 'switchUserStatus':
        // error_log("开始处理切换用户状态请求");
        // 检查用户是否登录
        $user = $auth->getCurrentUser();
        if (!$user) {
            $result = ['success' => false, 'message' => '未登录'];
        } else {
            $newStatus = $_GET['status'] ?? 'independent';
            
            // 验证状态值
            if (!in_array($newStatus, ['crew', 'independent'])) {
                $result = ['success' => false, 'message' => '无效的状态值'];
            } else {
                // 检查用户是否有切换到该状态的权限
                if ($newStatus === 'crew') {
                    // 检查是否是剧组成员或管理员
                    $isCrewAdmin = $_SESSION['is_crew_admin'] ?? false;
                    $isCrewMember = $_SESSION['is_crew_member'] ?? false;
                    if (!$isCrewAdmin && !$isCrewMember) {
                        $result = ['success' => false, 'message' => '您不是剧组成员，无法切换到该状态'];
                    } else {
                        // 执行状态切换
                        $_SESSION['current_status'] = $newStatus;
                        $result = ['success' => true, 'message' => '状态切换成功', 'current_status' => $newStatus];
                    }
                } else {
                    // 切换到独立会员状态，所有登录用户都可以
                    $_SESSION['current_status'] = $newStatus;
                    $result = ['success' => true, 'message' => '状态切换成功', 'current_status' => $newStatus];
                }
            }
        }
        // error_log("切换用户状态请求处理完成，结果: " . print_r($result, true));
        break;
    case 'getUserStatus':
        // error_log("开始处理获取用户状态请求");
        // 检查用户是否登录
        $user = $auth->getCurrentUser();
        if (!$user) {
            $result = ['success' => false, 'message' => '未登录'];
        } else {
            $result = [
                'success' => true,
                'current_status' => $_SESSION['current_status'] ?? 'independent',
                'is_crew_admin' => $_SESSION['is_crew_admin'] ?? false,
                'is_crew_member' => $_SESSION['is_crew_member'] ?? false,
                'crew_id' => $_SESSION['crew_id'] ?? null
            ];
        }
        // error_log("获取用户状态请求处理完成，结果: " . print_r($result, true));
        break;
    default:
        // error_log("收到无效的请求操作: " . $action);
        $result = ['success' => false, 'message' => '无效的请求操作'];
}

// 发送响应
// error_log("准备发送响应，结果类型: " . gettype($result));
// error_log("准备发送响应，结果: " . print_r($result, true));
$jsonResult = json_encode($result, JSON_UNESCAPED_UNICODE);
// error_log("JSON编码结果: " . $jsonResult);
// error_log("JSON编码错误: " . json_last_error_msg());
echo $jsonResult;
// error_log("响应已发送");
exit;
?>
