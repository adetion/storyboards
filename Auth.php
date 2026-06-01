<?php
// Auth.php - 用户认证类

require_once 'config.php';
require_once 'Logger.php';

class Auth {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->logger = new Logger();
        
        // 启动会话
        $this->startSession();
    }
    
    // 启动会话
    private function startSession() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // 设置会话过期时间
        $expireTime = Config::SESSION_EXPIRE;
        if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $expireTime)) {
            $this->logout();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    
    // 生成随机验证码
    private function generateVerificationCode($length = 6) {
        return rand(pow(10, $length - 1), pow(10, $length) - 1);
    }
    
    // 生成阿里云短信API签名
    private function generateAliyunSmsSignature($params, $accessKeySecret) {
        ksort($params);
        $stringToSign = "POST&%2F&";
        $canonicalizedQueryString = '';
        foreach ($params as $key => $value) {
            $canonicalizedQueryString .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $canonicalizedQueryString = substr($canonicalizedQueryString, 1);
        $stringToSign .= $this->percentEncode($canonicalizedQueryString);
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $accessKeySecret . '&', true));
        return $signature;
    }
    
    // 检查用户是否登录，返回标准格式的结果
    public function checkLogin() {
        if ($this->isLoggedIn()) {
            $user = $this->getCurrentUser();
            return [
                'success' => true,
                'data' => $user
            ];
        } else {
            return [
                'success' => false,
                'message' => '用户未登录'
            ];
        }
    }
    
    // URL编码
    private function percentEncode($string) {
        $string = urlencode($string);
        $string = preg_replace('/\+/', '%20', $string);
        $string = preg_replace('/%7E/', '~', $string);
        return $string;
    }
    
    // 发送短信验证码（阿里云实现）
    public function sendSmsVerification($phone) {
        // 检查手机号是否为空
        if (empty($phone)) {
            $this->logger->error("手机号为空");
            return ['success' => false, 'message' => '手机号不能为空'];
        }
        
        // 检查手机号格式
        if (!preg_match('/^1[3-9]\d{9}$/', $phone)) {
            $this->logger->error("无效的手机号格式: $phone");
            return ['success' => false, 'message' => '无效的手机号格式'];
        }
        
        // 检查阿里云短信服务状态
        if (empty(Config::ALIYUN_SMS_ACCESS_KEY_ID) || empty(Config::ALIYUN_SMS_ACCESS_KEY_SECRET)) {
            $this->logger->error("阿里云短信配置不完整");
            return ['success' => false, 'message' => '短信服务未配置，请联系管理员'];
        }
        
        // 检查是否在冷却期
        $sql = "SELECT created_at FROM sms_verifications WHERE phone = ? AND used = 0 ORDER BY created_at DESC LIMIT 1";
        $lastVerification = $this->db->queryOne($sql, [$phone]);
        
        if ($lastVerification) {
            $lastTime = strtotime($lastVerification['created_at']);
            if (time() - $lastTime < 60) {
                $this->logger->info("短信发送过于频繁，手机号: $phone，上次发送时间: " . $lastVerification['created_at']);
                return ['success' => false, 'message' => '验证码发送过于频繁，请稍后再试'];
            }
        }
        
        // 生成验证码
        $code = $this->generateVerificationCode(Config::VERIFICATION_CODE_LENGTH);
        $expiredAt = date('Y-m-d H:i:s', time() + Config::VERIFICATION_CODE_EXPIRE);
        
        // 保存验证码
        $sql = "INSERT INTO sms_verifications (phone, code, expired_at) VALUES (?, ?, ?)";
        $this->db->execute($sql, [$phone, $code, $expiredAt]);
        
        // 调用阿里云短信API发送验证码
        $accessKeyId = Config::ALIYUN_SMS_ACCESS_KEY_ID;
        $accessKeySecret = Config::ALIYUN_SMS_ACCESS_KEY_SECRET;
        $signName = Config::ALIYUN_SMS_SIGN_NAME;
        $templateCode = Config::ALIYUN_SMS_TEMPLATE_CODE;
        $endpoint = Config::ALIYUN_SMS_ENDPOINT;
        
        // 构建请求参数
        $params = array(
            'AccessKeyId' => $accessKeyId,
            'Action' => 'SendSms',
            'Format' => 'JSON',
            'PhoneNumbers' => $phone,
            'RegionId' => Config::ALIYUN_SMS_REGION_ID,
            'SignName' => $signName,
            'SignatureMethod' => 'HMAC-SHA1',
            'SignatureNonce' => uniqid(),
            'SignatureVersion' => '1.0',
            'TemplateCode' => $templateCode,
            'TemplateParam' => json_encode(array('code' => $code)),
            'Timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
            'Version' => '2017-05-25'
        );
        
        // 生成签名
        $params['Signature'] = $this->generateAliyunSmsSignature($params, $accessKeySecret);
        
        // 构建请求URL
        $url = "https://{$endpoint}/?";
        $queryString = http_build_query($params);
        $url .= $queryString;
        
        // 发送请求
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            $this->logger->log("发送短信失败: $error");
            return ['success' => false, 'message' => '短信发送失败，请稍后重试'];
        }
        
        // 处理响应
        $result = json_decode($response, true);
        if (isset($result['Code']) && $result['Code'] == 'OK') {
            $this->logger->log("发送短信验证码到 $phone: $code");
            return ['success' => true, 'message' => '验证码发送成功'];
        } else {
            $this->logger->log("发送短信失败: " . json_encode($result));
            $errorMessage = isset($result['Message']) ? $result['Message'] : '短信发送失败，请稍后重试';
            return ['success' => false, 'message' => $errorMessage];
        }
    }
    
    // 发送邮件验证码（模拟实现）
    public function sendEmailVerification($email) {
        // 检查邮箱格式
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '无效的邮箱格式'];
        }
        
        // 检查是否在冷却期
        $sql = "SELECT created_at FROM email_verifications WHERE email = ? AND used = 0 ORDER BY created_at DESC LIMIT 1";
        $lastVerification = $this->db->queryOne($sql, [$email]);
        
        if ($lastVerification) {
            $lastTime = strtotime($lastVerification['created_at']);
            if (time() - $lastTime < 60) {
                return ['success' => false, 'message' => '验证码发送过于频繁，请稍后再试'];
            }
        }
        
        // 生成验证码
        $code = $this->generateVerificationCode(Config::VERIFICATION_CODE_LENGTH);
        $expiredAt = date('Y-m-d H:i:s', time() + Config::VERIFICATION_CODE_EXPIRE);
        
        // 保存验证码
        $sql = "INSERT INTO email_verifications (email, code, expired_at) VALUES (?, ?, ?)";
        $this->db->execute($sql, [$email, $code, $expiredAt]);
        
        // 这里应该调用真实的邮件API发送验证码
        // 模拟发送
        $this->logger->log("发送邮件验证码到 $email: $code");
        
        return ['success' => true, 'message' => '验证码发送成功'];
    }
    
    // 验证短信验证码
    public function verifySmsCode($phone, $code) {
        // 检查验证码
        $sql = "SELECT * FROM sms_verifications WHERE phone = ? AND code = ? AND used = 0 AND expired_at > NOW()";
        $verification = $this->db->queryOne($sql, [$phone, $code]);
        
        if (!$verification) {
            return ['success' => false, 'message' => '无效的验证码'];
        }
        
        // 标记验证码为已使用
        $sql = "UPDATE sms_verifications SET used = 1 WHERE id = ?";
        $affectedRows = $this->db->execute($sql, [$verification['id']]);
        
        if ($affectedRows === 0) {
            return ['success' => false, 'message' => '验证码更新失败，请重试'];
        }
        
        return ['success' => true, 'message' => '验证码验证成功'];
    }
    
    // 验证邮件验证码
    public function verifyEmailCode($email, $code) {
        // 检查验证码
        $sql = "SELECT * FROM email_verifications WHERE email = ? AND code = ? AND used = 0 AND expired_at > NOW()";
        $verification = $this->db->queryOne($sql, [$email, $code]);
        
        if (!$verification) {
            return ['success' => false, 'message' => '无效的验证码'];
        }
        
        // 标记验证码为已使用
        $sql = "UPDATE email_verifications SET used = 1 WHERE id = ?";
        $this->db->execute($sql, [$verification['id']]);
        
        return ['success' => true, 'message' => '验证码验证成功'];
    }
    
    // 用户注册
    public function register($data) {
        // 验证必填字段
        if (empty($data['phone']) && empty($data['email'])) {
            return ['success' => false, 'message' => '手机号或邮箱不能为空'];
        }
        
        // 检查用户名是否已存在
        if (!empty($data['username'])) {
            $sql = "SELECT id FROM users WHERE username = ?";
            $user = $this->db->queryOne($sql, [$data['username']]);
            if ($user) {
                return ['success' => false, 'message' => '用户名已存在'];
            }
        }
        
        // 检查手机号是否已存在
        if (!empty($data['phone'])) {
            $sql = "SELECT id FROM users WHERE phone = ?";
            $user = $this->db->queryOne($sql, [$data['phone']]);
            if ($user) {
                return ['success' => false, 'message' => '手机号已注册'];
            }
            
            // 验证短信验证码
            if (isset($data['phone_code'])) {
                $codeResult = $this->verifySmsCode($data['phone'], $data['phone_code']);
                if (!$codeResult['success']) {
                    return $codeResult;
                }
            }
        }
        
        // 检查邮箱是否已存在
        if (!empty($data['email'])) {
            $sql = "SELECT id FROM users WHERE email = ?";
            $user = $this->db->queryOne($sql, [$data['email']]);
            if ($user) {
                return ['success' => false, 'message' => '邮箱已注册'];
            }
            
            // 验证邮件验证码
            if (isset($data['email_code'])) {
                $codeResult = $this->verifyEmailCode($data['email'], $data['email_code']);
                if (!$codeResult['success']) {
                    return $codeResult;
                }
            }
        }
        
        // 密码加密
        $password = password_hash($data['password'], PASSWORD_BCRYPT);
        
        // 处理email字段，确保唯一性
        $email = $data['email'] ?? null;
        if (empty($email)) {
            // 生成唯一的email值
            $email = $data['username'] . '_' . uniqid() . '@example.com';
        }
        
        // 插入用户数据
        $sql = "INSERT INTO users (username, password, email, phone) VALUES (?, ?, ?, ?)";
        $params = [$data['username'], $password, $email, $data['phone'] ?? null];
        
        try {
            $userId = $this->db->insert($sql, $params);
            
            // 初始化用户中心相关表
            $this->initUserCenterTables($userId);
            
            // 登录用户
            $this->loginUser($userId);
            
            return ['success' => true, 'message' => '注册成功', 'user_id' => $userId];
        } catch (Exception $e) {
            $this->logger->log("注册失败: " . $e->getMessage());
            return ['success' => false, 'message' => '注册失败，请稍后重试'];
        }
    }
    
    // 用户登录（密码登录）
    public function login($identifier, $password, $type = 'username') {
        // 根据类型构建查询条件
        $field = 'username';
        if ($type === 'phone') {
            $field = 'phone';
        } elseif ($type === 'email') {
            $field = 'email';
        }
        
        // 查询用户
        $sql = "SELECT * FROM users WHERE $field = ? AND status = 1";
        $user = $this->db->queryOne($sql, [$identifier]);
        
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 验证密码
        // 特殊处理demo用户，允许使用密码demo登录
        if ($user['username'] === 'demo' && $password === 'demo') {
            // demo用户使用demo密码登录，直接通过验证
        } elseif (!password_verify($password, $user['password'])) {
            return ['success' => false, 'message' => '密码错误'];
        }
        
        // 登录用户
        $this->loginUser($user['id']);
        
        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }
    
    // 用户登录（验证码登录）
    public function loginWithVerificationCode($phone, $code) {
        // 验证验证码
        $verifyResult = $this->verifySmsCode($phone, $code);
        if (!$verifyResult['success']) {
            return $verifyResult;
        }
        
        // 查询用户
        $sql = "SELECT * FROM users WHERE phone = ? AND status = 1";
        $user = $this->db->queryOne($sql, [$phone]);
        
        if (!$user) {
            return ['success' => false, 'message' => '用户不存在'];
        }
        
        // 登录用户
        $this->loginUser($user['id']);
        
        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }
    
    // 手机号一键登录
    public function loginWithOneClick($phone) {
        // 查询用户
        $sql = "SELECT * FROM users WHERE phone = ? AND status = 1";
        $user = $this->db->queryOne($sql, [$phone]);
        
        if (!$user) {
            // 用户不存在，自动注册
            $username = 'user_' . substr($phone, -6);
            $password = $this->generateVerificationCode(8);
            
            $registerResult = $this->register([
                'username' => $username,
                'password' => $password,
                'phone' => $phone
            ]);
            
            if (!$registerResult['success']) {
                return $registerResult;
            }
            
            // 查询新注册的用户
            $sql = "SELECT * FROM users WHERE id = ?";
            $user = $this->db->queryOne($sql, [$registerResult['user_id']]);
        }
        
        // 登录用户
        $this->loginUser($user['id']);
        
        return ['success' => true, 'message' => '登录成功', 'user' => $user];
    }
    
    // 登录用户（设置会话）
    private function loginUser($userId) {
        $_SESSION['user_id'] = $userId;
        $_SESSION['logged_in'] = true;
        $_SESSION['LAST_ACTIVITY'] = time();
        
        // 检查用户是否是剧组管理员或成员
        $this->checkUserCrewStatus($userId);
    }
    
    // 检查用户的剧组状态并设置当前任务号
    private function checkUserCrewStatus($userId) {
        // 检查是否是剧组管理员（admin_user_id）
        $crewAdmin = $this->db->queryOne("SELECT * FROM crew WHERE admin_user_id = ?", [$userId]);
        
        // 检查是否是剧组成员（使用admin_user_id）
        $crewMember = $this->db->queryOne("SELECT * FROM crew_organization WHERE admin_user_id = ?", [$userId]);
        
        // 初始化当前任务号
        $currentTaskId = '';
        
        // 设置初始状态
        if ($crewAdmin) {
            // 剧组管理员，同时处于两种状态，默认使用剧组成员状态
            $_SESSION['is_crew_admin'] = true;
            $_SESSION['is_crew_member'] = true;
            $_SESSION['current_status'] = 'crew'; // crew: 剧组成员, independent: 独立会员
            $_SESSION['crew_id'] = $crewAdmin['id'];
            // 获取管理员自己创建的剧组的当前任务号
            $currentTaskId = $crewAdmin['current_task_id'] ?? '';
        } elseif ($crewMember) {
            // 普通剧组成员，默认使用剧组成员状态
            $_SESSION['is_crew_admin'] = false;
            $_SESSION['is_crew_member'] = true;
            $_SESSION['current_status'] = 'crew';
            $_SESSION['crew_id'] = $crewMember['crew_id'];
            // 获取所属剧组的当前任务号
            $crewInfo = $this->db->queryOne("SELECT current_task_id FROM crew WHERE id = ?", [$crewMember['crew_id']]);
            $currentTaskId = $crewInfo['current_task_id'] ?? '';
        } else {
            // 独立会员
            $_SESSION['is_crew_admin'] = false;
            $_SESSION['is_crew_member'] = false;
            $_SESSION['current_status'] = 'independent';
            $_SESSION['crew_id'] = null;
            // 独立会员没有剧组，使用空任务号
            $currentTaskId = '';
        }
        
        // 保存当前任务号到会话
        $_SESSION['current_task_id'] = $currentTaskId;
        // error_log("检查用户剧组状态 - 用户ID: {$userId}, 当前任务号: {$currentTaskId}");
    }
    
    // 获取当前任务号的完整逻辑（优先级：剧组 > 本地存储 > 无）
    public function getCurrentTaskNumberWithFallback($userId) {
        try {
            // 首先尝试从数据库获取当前任务号（按照优先级）
            $currentTaskId = '';
            
            // 1. 检查是否是剧组成员 - 获取所属剧组的当前任务号（使用admin_user_id）
            $crewMember = $this->db->queryOne("SELECT crew_id FROM crew_organization WHERE admin_user_id = ?", [$userId]);
            if ($crewMember) {
                $crewInfo = $this->db->queryOne("SELECT current_task_id FROM crew WHERE id = ?", [$crewMember['crew_id']]);
                $currentTaskId = $crewInfo['current_task_id'] ?? '';
                error_log("获取当前任务号 - 优先级1：剧组成员，当前任务号: {$currentTaskId}");
            } 
            
            // 2. 如果不是剧组成员或没有当前任务号，检查是否是剧组管理员
            if (empty($currentTaskId)) {
                $crewAdmin = $this->db->queryOne("SELECT id, current_task_id FROM crew WHERE admin_user_id = ?", [$userId]);
                if ($crewAdmin) {
                    $currentTaskId = $crewAdmin['current_task_id'] ?? '';
                    error_log("获取当前任务号 - 优先级2：剧组管理员，当前任务号: {$currentTaskId}");
                }
            }
            
            // 3. 如果还是没有当前任务号，返回空字符串
            error_log("获取当前任务号 - 最终结果: {$currentTaskId}");
            return ['success' => true, 'data' => $currentTaskId];
        } catch (Exception $e) {
            error_log("获取当前任务号失败: " . $e->getMessage());
            return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
        }
    }
    
    // 用户退出
    public function logout() {
        // 清除会话变量
        $_SESSION = [];
        
        // 销毁会话
        session_destroy();
        
        return ['success' => true, 'message' => '退出成功'];
    }
    
    // 初始化用户中心相关表
    private function initUserCenterTables($userId) {
        // 初始化用户扩展信息
        $sql = "INSERT INTO user_profiles (user_id, nickname) VALUES (?, ?)";
        $this->db->insert($sql, [$userId, '用户' . substr($userId, -4)]);
        
        // 初始化用户余额
        $sql = "INSERT INTO user_balances (user_id, balance) VALUES (?, ?)";
        $this->db->insert($sql, [$userId, 0.00]);
        
        // 初始化用户积分
        $sql = "INSERT INTO user_points (user_id, points) VALUES (?, ?)";
        $this->db->insert($sql, [$userId, Config::DEFAULT_REGISTER_POINTS]);
        
        // 记录积分历史
        $sql = "INSERT INTO points_history (user_id, points_change, reason) VALUES (?, ?, ?)";
        $this->db->insert($sql, [$userId, Config::DEFAULT_REGISTER_POINTS, '注册赠送']);
    }
    
    // 获取当前登录用户
    public function getCurrentUser() {
        error_log("Session data: " . print_r($_SESSION, true));
        
        // 检查用户是否已登录
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
            error_log("用户未登录 - SESSION数据: " . print_r($_SESSION, true));
            return null;
        }
        
        // 检查会员有效期
        $this->checkMembershipExpiry($_SESSION['user_id']);
        
        // 从数据库获取用户信息
        $sql = "SELECT id, username, email, phone, created_at, level, membership_expire FROM users WHERE id = ? AND status = 1";
        $user = $this->db->queryOne($sql, [$_SESSION['user_id']]);
        
        if (!$user) {
            error_log("未找到用户ID: " . $_SESSION['user_id']);
            return null;
        }
        
        error_log("成功获取用户信息: " . print_r($user, true));
        return $user;
    }
    
    // 获取用户完整资料
    public function getUserProfile($userId) {
        // 检查会员有效期
        $this->checkMembershipExpiry($userId);
        
        $sql = "SELECT u.*, up.nickname, up.avatar, up.gender, up.birthday, up.bio, ub.balance, upo.points 
               FROM users u 
               LEFT JOIN user_profiles up ON u.id = up.user_id 
               LEFT JOIN user_balances ub ON u.id = ub.user_id 
               LEFT JOIN user_points upo ON u.id = upo.user_id 
               WHERE u.id = ? AND u.status = 1";
        
        return $this->db->queryOne($sql, [$userId]);
    }
    
    // 修改用户昵称
    public function updateNickname($userId, $nickname) {
        $sql = "UPDATE user_profiles SET nickname = ?, updated_at = CURRENT_TIMESTAMP WHERE user_id = ?";
        $this->db->execute($sql, [$nickname, $userId]);
        return ['success' => true, 'message' => '昵称修改成功'];
    }
    
    // 重置用户密码
    public function resetPassword($userId, $newPassword) {
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);
        $sql = "UPDATE users SET password = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $this->db->execute($sql, [$hashedPassword, $userId]);
        return ['success' => true, 'message' => '密码重置成功'];
    }
    
    // 获取用户充值记录
    public function getRechargeRecords($userId, $limit = 10) {
        $sql = "SELECT * FROM recharge_records WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->db->query($sql, [$userId, $limit]);
    }
    
    // 获取用户消费记录
    public function getConsumptionRecords($userId, $limit = 10) {
        $sql = "SELECT * FROM consumption_records WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
        return $this->db->query($sql, [$userId, $limit]);
    }
    
    // 获取用户积分
    public function getUserPoints($userId) {
        $sql = "SELECT points FROM user_points WHERE user_id = ?";
        $result = $this->db->queryOne($sql, [$userId]);
        return $result['points'] ?? 0;
    }
    
    // 增加用户积分（充值或兑换）
    public function addUserPoints($userId, $points, $reason, $source = 'system', $taskId = null, $content = null) {
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 增加积分
            $sql = "UPDATE user_points SET points = points + ? WHERE user_id = ?";
            $this->db->execute($sql, [$points, $userId]);
            
            // 记录积分变动
            $sql = "INSERT INTO points_history (user_id, points_change, reason, source, task_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $points, $reason, $source, $taskId, $content]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 积分增加成功，增加: {$points} 积分，原因: {$reason}，来源: {$source}");
            
            return ['success' => true, 'message' => '积分增加成功'];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 积分增加失败: " . $e->getMessage() . "，增加: {$points} 积分，原因: {$reason}，来源: {$source}");
            return ['success' => false, 'message' => '积分增加失败，请稍后重试'];
        }
    }
    
    // 余额兑换积分
    public function exchangeBalanceToPoints($userId, $amount) {
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 检查余额是否足够
            $balance = $this->getUserBalance($userId);
            if ($balance < $amount) {
                $this->db->rollback();
                return ['success' => false, 'message' => '余额不足'];
            }
            
            // 计算兑换的积分数量
            $points = $amount * Config::RECHARGE_RATE;
            
            // 扣除余额
            $sql = "UPDATE user_balances SET balance = balance - ? WHERE user_id = ?";
            $this->db->execute($sql, [$amount, $userId]);
            
            // 增加积分
            $sql = "UPDATE user_points SET points = points + ? WHERE user_id = ?";
            $this->db->execute($sql, [$points, $userId]);
            
            // 记录积分变动
            $sql = "INSERT INTO points_history (user_id, points_change, reason, source, task_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $points, "余额兑换积分", "balance_exchange", null, null]);
            
            // 记录消费记录
            $orderNo = 'EXCHANGE' . date('YmdHis') . rand(1000, 9999);
            $sql = "INSERT INTO consumption_records (user_id, amount, order_no, item_type, description) VALUES (?, ?, ?, ?, ?)";
            $this->db->execute($sql, [$userId, $amount, $orderNo, 'points_exchange', "余额兑换积分 {$points} 积分"]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 余额兑换积分成功，消耗余额: {$amount} 元，兑换积分: {$points} 积分，来源: balance_exchange");
            
            return ['success' => true, 'message' => '积分兑换成功'];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 余额兑换积分失败: " . $e->getMessage() . "，消耗余额: {$amount} 元");
            return ['success' => false, 'message' => '积分兑换失败，请稍后重试'];
        }
    }
    
    // 检查用户积分是否足够
    public function checkUserPoints($userId, $requiredPoints) {
        $userPoints = $this->getUserPoints($userId);
        return $userPoints >= $requiredPoints;
    }
    
    // 扣除用户积分
    public function deductUserPoints($userId, $points, $reason, $source = 'system', $taskId = null, $content = null) {
        // 检查用户是否为3级且有自己的API_KEY
        $sql = "SELECT u.level, ak.text2text_api_key FROM users u LEFT JOIN api_keys ak ON u.id = ak.user_id WHERE u.id = ?";
        $userInfo = $this->db->queryOne($sql, [$userId]);
        
        if ($userInfo && $userInfo['level'] == 3 && !empty($userInfo['text2text_api_key'])) {
            // 用户为3级且有自己的API_KEY，不扣除积分
            $this->logger->info("用户ID: {$userId} 为3级且使用自己的API_KEY，不扣除积分，原因: {$reason}，来源: {$source}，任务ID: {$taskId}");
            return ['success' => true, 'message' => '积分扣除成功'];
        }
        
        if (!$this->checkUserPoints($userId, $points)) {
            return ['success' => false, 'message' => '积分不足'];
        }
        
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 扣除积分
            $sql = "UPDATE user_points SET points = points - ? WHERE user_id = ?";
            $this->db->execute($sql, [$points, $userId]);
            
            // 记录积分变动
            $sql = "INSERT INTO points_history (user_id, points_change, reason, source, task_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, -$points, $reason, $source, $taskId, $content]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 积分消耗成功，扣除: {$points} 积分，原因: {$reason}，来源: {$source}，任务ID: {$taskId}");
            
            return ['success' => true, 'message' => '积分扣除成功'];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 积分消耗失败: " . $e->getMessage() . "，扣除: {$points} 积分，原因: {$reason}，来源: {$source}，任务ID: {$taskId}");
            return ['success' => false, 'message' => '积分扣除失败，请稍后重试'];
        }
    }
    
    // 获取用户余额
    public function getUserBalance($userId) {
        $sql = "SELECT balance FROM user_balances WHERE user_id = ?";
        $result = $this->db->queryOne($sql, [$userId]);
        return $result['balance'] ?? 0.00;
    }
    
    // 会员升级
    public function upgradeMembership($userId, $level, $amount, $orderNo) {
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 查询用户当前的会员信息
            $sql = "SELECT level as current_level, membership_expire as current_expire FROM users WHERE id = ?";
            $currentUser = $this->db->queryOne($sql, [$userId]);
            
            // 计算新的有效期
            $newExpireDate = '';
            
            if ($currentUser && $currentUser['current_expire']) {
                // 用户已有会员记录
                $currentExpire = strtotime($currentUser['current_expire']);
                $now = time();
                
                if ($currentExpire > $now) {
                    // 会员未过期，在原有有效期基础上延长
                    // 计算延长的时间（根据会员类型）
                    $extendTime = $level == 1 ? '+1 month' : '+1 year';
                    $newExpireDate = date('Y-m-d H:i:s', strtotime($extendTime, $currentExpire));
                } else {
                    // 会员已过期，从当前时间重新计算
                    $newExpireDate = date('Y-m-d H:i:s', strtotime($level == 1 ? '+1 month' : '+1 year'));
                }
            } else {
                // 用户没有会员记录，从当前时间开始计算
                $newExpireDate = date('Y-m-d H:i:s', strtotime($level == 1 ? '+1 month' : '+1 year'));
            }
            
            // 更新会员等级和有效期
            $sql = "UPDATE users SET level = ?, membership_expire = ? WHERE id = ?";
            $this->db->execute($sql, [$level, $newExpireDate, $userId]);
            
            // 创建会员购买记录
            $description = $level == 1 ? '个人会员' : '团队会员';
            $sql = "INSERT INTO consumption_records (user_id, amount, order_no, item_type, description, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $amount, $orderNo, 'membership', $description]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 会员升级成功，等级: {$level}，有效期至: {$newExpireDate}");
            
            return ['success' => true, 'message' => '会员升级成功', 'level' => $level, 'expire_date' => $newExpireDate];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 会员升级失败: " . $e->getMessage() . "，等级: {$level}");
            return ['success' => false, 'message' => '会员升级失败，请稍后重试'];
        }
    }
    
    // 检查会员是否过期并自动降级
    public function checkMembershipExpiry($userId) {
        try {
            // 获取用户会员信息
            $sql = "SELECT level, membership_expire FROM users WHERE id = ?";
            $user = $this->db->queryOne($sql, [$userId]);
            
            if (!$user) {
                return false;
            }
            
            // 检查会员是否过期
            if ($user['level'] > 0 && $user['membership_expire'] < date('Y-m-d H:i:s')) {
                // 会员已过期，自动降级
                $sql = "UPDATE users SET level = 0, membership_expire = NULL WHERE id = ?";
                $this->db->execute($sql, [$userId]);
                
                // 记录日志
                $this->logger->info("用户ID: {$userId} 会员已过期，自动降级为普通用户");
                
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            $this->logger->error("检查会员有效期失败: " . $e->getMessage() . "，用户ID: {$userId}");
            return false;
        }
    }
    
    // 获取用户历史任务
    public function getUserTasks($userId, $taskType = null, $status = null, $page = 1, $pageSize = 10) {
        try {
            $offset = ($page - 1) * $pageSize;
            // 直接执行SQL查询，不使用分页，获取所有任务
            $sql = "SELECT * FROM tasks WHERE task_type='script_to_storyboard' and user_id = ?";
            $params = [$userId];
            
            // // 当 taskType 不为 null 时才添加筛选条件
            // if ($taskType !== null) {
            //     $sql .= " AND task_type = ?";
            //     $params[] = $taskType;
            // }
            
            if ($status !== null) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC";
            
            // 获取所有任务，不分页
            $result = $this->db->query($sql, $params);
            
            // 处理每个任务，确保所有字符串字段都是有效的UTF-8
            $safeTasks = [];
            foreach ($result as $task) {
                $safeTask = [];
                foreach ($task as $key => $value) {
                    if (is_string($value)) {
                        // 确保字符串是有效的UTF-8
                        if (!mb_check_encoding($value, 'UTF-8')) {
                            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
                        }
                        // 清理可能导致JSON编码问题的控制字符
                        $value = preg_replace('/[\x00-\x1F\x7F]/', '', $value);
                    }
                    $safeTask[$key] = $value;
                }
                $safeTasks[] = $safeTask;
            }
            
            return $safeTasks;
        } catch (Exception $e) {
            error_log("获取用户任务失败: " . $e->getMessage());
            return []; // 出错时返回空数组而不是false
        }
    }
    
    // 获取用户任务总数
    public function getUserTasksCount($userId, $taskType = null, $status = null) {
        try {
            $sql = "SELECT COUNT(*) as total FROM tasks WHERE user_id = ?";
            $params = [$userId];
            
            error_log("查询任务总数参数 - userId: $userId, taskType: " . ($taskType ?? 'null'));
            
            // 当 taskType 不为 null 时才添加筛选条件
            if ($taskType !== null) {
                $sql .= " AND task_type = ?";
                $params[] = $taskType;
            }
            
            if ($status !== null) {
                $sql .= " AND status = ?";
                $params[] = $status;
            }
            
            error_log("查询任务总数SQL: $sql");
            error_log("查询任务总数参数: " . json_encode($params));
            
            $result = $this->db->query($sql, $params);
            error_log("查询任务总数结果: " . json_encode($result));
            
            return isset($result[0]['total']) ? (int)$result[0]['total'] : 0;
        } catch (Exception $e) {
            error_log("获取用户任务总数失败: " . $e->getMessage());
            return 0; // 出错时返回0
        }
    }
    
    // 设置当前任务
    public function setCurrentTask($userId, $taskNumber) {
        try {
            // 只有剧组创建者（管理员）才能设置当前任务
            $crew = $this->db->queryOne("SELECT id, admin_user_id FROM crew WHERE admin_user_id = ?", [$userId]);
            
            if (!$crew) {
                return ['success' => false, 'message' => '您不是剧组管理员，无法设置当前任务'];
            }
            
            // 确保是剧组创建者
            if ($crew['admin_user_id'] !== $userId) {
                return ['success' => false, 'message' => '只有剧组创建者才能设置当前任务'];
            }
            
            // 更新crew表中的current_task_id字段
            $sql = "UPDATE crew SET current_task_id = ? WHERE id = ? AND admin_user_id = ?";
            $affectedRows = $this->db->execute($sql, [$taskNumber, $crew['id'], $userId]);
            
            if ($affectedRows === 0) {
                return ['success' => false, 'message' => '更新当前任务失败，未找到对应的剧组或您不是剧组管理员'];
            }
            
            return ['success' => true, 'message' => '设置当前任务成功'];
        } catch (Exception $e) {
            error_log("设置当前任务失败: " . $e->getMessage());
            return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
        }
    }
    
    // 获取当前任务号
    public function getCurrentTaskNumber($userId) {
        try {
            // 检查用户是否是剧组管理员或成员
            $currentTaskId = '';
            
            // 首先检查是否是剧组管理员
            $crewAdmin = $this->db->queryOne("SELECT id, current_task_id FROM crew WHERE admin_user_id = ?", [$userId]);
            if ($crewAdmin) {
                // 管理员直接获取自己创建的剧组的current_task_id
                $currentTaskId = $crewAdmin['current_task_id'] ?? '';
                error_log("获取当前任务号 - 用户是剧组管理员，当前任务号: {$currentTaskId}");
                return ['success' => true, 'data' => $currentTaskId];
            }
            
            // 检查是否是剧组成员（使用admin_user_id）
            $crewMember = $this->db->queryOne("SELECT crew_id FROM crew_organization WHERE admin_user_id = ?", [$userId]);
            if ($crewMember) {
                // 成员获取所属剧组的current_task_id
                $crewId = $crewMember['crew_id'];
                $crewInfo = $this->db->queryOne("SELECT current_task_id FROM crew WHERE id = ?", [$crewId]);
                $currentTaskId = $crewInfo['current_task_id'] ?? '';
                error_log("获取当前任务号 - 用户是剧组成员，所属剧组ID: {$crewId}，当前任务号: {$currentTaskId}");
                return ['success' => true, 'data' => $currentTaskId];
            }
            
            // 不是剧组成员，返回空字符串（允许独立用户访问，不报错）
            error_log("获取当前任务号 - 用户不是剧组成员，返回空字符串");
            return ['success' => true, 'data' => $currentTaskId];
        } catch (Exception $e) {
            error_log("获取当前任务号失败: " . $e->getMessage());
            return ['success' => false, 'message' => '服务器内部错误: ' . $e->getMessage()];
        }
    }
    
    // 获取用户积分历史记录
    public function getPointsHistory($userId, $limit = 10, $page = 1) {
        $offset = ($page - 1) * $limit;
        $sql = "SELECT * FROM points_history WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params = [$userId, $limit, $offset];
        return $this->db->query($sql, $params);
    }
    
    // 获取积分历史记录总数
    public function getPointsHistoryCount($userId) {
        $sql = "SELECT COUNT(*) as count FROM points_history WHERE user_id = ?";
        $result = $this->db->queryOne($sql, [$userId]);
        return $result['count'] ?? 0;
    }
    
    // 检查用户是否已登录
    public function isLoggedIn() {
        return isset($_SESSION['user_id']) && $_SESSION['logged_in'] === true;
    }
    
    // 获取当前登录用户ID
    public function getCurrentUserId() {
        return $this->isLoggedIn() ? $_SESSION['user_id'] : null;
    }
    
    // 获取数据库实例
    public function getDb() {
        return $this->db;
    }
    
    // 更新用户openid
    public function updateUserOpenid($userId, $openid) {
        try {
            // 更新用户表中的openid字段
            $sql = "UPDATE users SET openid = ? WHERE id = ?";
            $this->db->execute($sql, [$openid, $userId]);
            return ['success' => true, 'message' => 'openid更新成功'];
        } catch (Exception $e) {
            $this->logger->error("更新用户openid失败: " . $e->getMessage() . "，用户ID: {$userId}");
            return ['success' => false, 'message' => 'openid更新失败'];
        }
    }
    
    // 创建充值记录
    public function createRechargeRecord($userId, $orderNo, $amount, $paymentMethod = 'wechat') {
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 创建充值记录
            $sql = "INSERT INTO recharge_records (user_id, amount, order_no, payment_method, status, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $amount, $orderNo, $paymentMethod, 0]);
            
            // 计算积分（1元 = 100积分）
            $points = $amount * Config::RECHARGE_RATE;
            
            // 更新充值记录状态为成功
            $sql = "UPDATE recharge_records SET status = 1 WHERE order_no = ?";
            $this->db->execute($sql, [$orderNo]);
            
            // 增加用户余额
            $sql = "UPDATE user_balances SET balance = balance + ? WHERE user_id = ?";
            $this->db->execute($sql, [$amount, $userId]);
            
            // 增加用户积分
            $sql = "UPDATE user_points SET points = points + ? WHERE user_id = ?";
            $this->db->execute($sql, [$points, $userId]);
            
            // 记录积分变动
            $sql = "INSERT INTO points_history (user_id, points_change, reason, source, task_id, content, created_at) VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $points, "充值获得积分", "recharge", null, null]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 充值成功，订单号: {$orderNo}，金额: {$amount} 元，支付方式: {$paymentMethod}，获得积分: {$points}");
            
            return ['success' => true, 'message' => '充值成功', 'points' => $points];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 充值失败: " . $e->getMessage() . "，订单号: {$orderNo}，金额: {$amount} 元");
            return ['success' => false, 'message' => '充值失败，请稍后重试'];
        }
    }
    
    // 创建会员购买记录
    public function createMembershipRecord($userId, $orderNo, $amount, $itemType = 'membership', $description = '') {
        try {
            // 开始事务
            $this->db->beginTransaction();
            
            // 创建消费记录
            $sql = "INSERT INTO consumption_records (user_id, amount, order_no, item_type, description, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
            $this->db->execute($sql, [$userId, $amount, $orderNo, $itemType, $description]);
            
            // 提交事务
            $this->db->commit();
            
            // 记录日志
            $this->logger->info("用户ID: {$userId} 会员购买记录创建成功，订单号: {$orderNo}，金额: {$amount} 元，类型: {$itemType}");
            
            return ['success' => true, 'message' => '会员购买记录创建成功'];
        } catch (Exception $e) {
            // 回滚事务
            $this->db->rollback();
            $this->logger->error("用户ID: {$userId} 会员购买记录创建失败: " . $e->getMessage() . "，订单号: {$orderNo}，金额: {$amount} 元");
            return ['success' => false, 'message' => '会员购买记录创建失败，请稍后重试'];
        }
    }
}
?>
