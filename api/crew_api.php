<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 引入配置文件
require_once '../config.php';

// 更新用户的crew_group字段
function updateUserCrewGroup($userId) {
    global $db;
    
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

// 获取数据库实例
$db = Database::getInstance();
$pdo = $db->getPdo();

// 检查用户是否登录
function checkLogin() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
        echo json_encode(['success' => false, 'message' => '用户未登录']);
        exit();
    }
    return $_SESSION['user_id'];
}

// 获取请求参数
$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

// 处理API请求
switch ($action) {
    // 创建新剧组
    case 'create_crew':
        checkLogin();
        $name = $_GET['name'] ?? '';
        $description = $_GET['description'] ?? '';
        $film_name = $_GET['film_name'] ?? '';
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        
        // 处理整数字段，将空字符串转换为0
        $estimatedDays = empty($_GET['estimatedDays']) ? 0 : (int)$_GET['estimatedDays'];
        $totalScenes = empty($_GET['totalScenes']) ? 0 : (int)$_GET['totalScenes'];
        $totalShots = empty($_GET['totalShots']) ? 0 : (int)$_GET['totalShots'];
        $actualDays = empty($_GET['actualDays']) ? 0 : (int)$_GET['actualDays'];
        $daysCompleted = empty($_GET['daysCompleted']) ? 0 : (int)$_GET['daysCompleted'];
        $completionRate = empty($_GET['completionRate']) ? 0 : (int)$_GET['completionRate'];
        
        // 获取genres参数并处理为JSON格式
        $genres = $_GET['genres'] ?? [];
        if (!is_array($genres)) {
            $genres = $_GET['genres'] ? explode(',', $_GET['genres']) : [];
        }
        $genresJson = json_encode($genres);
        
        $user_id = $_SESSION['user_id'];
        
        // 检查是否已创建过剧组
        $existing = $db->queryOne("SELECT * FROM crew WHERE admin_user_id = ?", [$user_id]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => '已创建过剧组，无法重复创建']);
            exit();
        }
        
        // 插入新剧组
        $db->execute("INSERT INTO crew (admin_user_id, name, description, film_name, startDate, endDate, estimatedDays, totalScenes, totalShots, actualDays, daysCompleted, completionRate, genres) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [$user_id, $name, $description, $film_name, $startDate, $endDate, $estimatedDays, $totalScenes, $totalShots, $actualDays, $daysCompleted, $completionRate, $genresJson]);
        echo json_encode(['success' => true, 'message' => '剧组创建成功']);
        break;
    
    // 获取单个剧组信息
    case 'get_crew':
        try {
            $user_id = checkLogin();
            $id = $_GET['id'] ?? '';
            
            error_log("get_crew: user_id=$user_id, crew_id=$id");
            
            // 检查是否是剧组管理员或成员
            $crew = $db->queryOne("SELECT * FROM crew WHERE id = ?", [$id]);
            if ($crew) {
                error_log("get_crew: crew found, id=" . $crew['id']);
                
                // 将genres从JSON字符串转换为数组
                if (!empty($crew['genres'])) {
                    $crew['genres'] = json_decode($crew['genres'], true);
                } else {
                    $crew['genres'] = [];
                }
                
                // 获取当前任务ID和名称（使用crew表的current_task_id字段）
                if (!empty($crew['current_task_id'])) {
                    error_log("get_crew: current_task_id=" . $crew['current_task_id']);
                    $task = $db->queryOne("SELECT task_id FROM tasks WHERE task_id = ? AND user_id = ?", [$crew['current_task_id'], $user_id]);
                    if ($task) {
                        error_log("get_crew: task found, task_id=" . $task['task_id']);
                        $crew['current_task_id'] = $task['task_id'];
                        $crew['current_task_name'] = $task['task_id'];
                    } else {
                        error_log("get_crew: task not found for task_id=" . $crew['current_task_id']);
                        $crew['current_task_id'] = $crew['current_task_id'];
                        $crew['current_task_name'] = $crew['current_task_id'];
                    }
                } else {
                    error_log("get_crew: no current_task_id");
                    $crew['current_task_id'] = '';
                    $crew['current_task_name'] = '';
                }
                
                // 检查是否是管理员或成员
                $isAdmin = $crew['admin_user_id'] == $user_id;
                $isMember = $db->queryOne("SELECT * FROM crew_organization WHERE crew_id = ? AND admin_user_id = ?", [$id, $user_id]) !== null;
                
                if ($isAdmin || $isMember) {
                    echo json_encode(['success' => true, 'data' => $crew]);
                } else {
                    echo json_encode(['success' => false, 'message' => '无权限查看该剧组']);
                }
            } else {
                error_log("get_crew: crew not found");
                echo json_encode(['success' => false, 'message' => '剧组不存在']);
            }
        } catch (Exception $e) {
            error_log("get_crew error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '服务器错误: ' . $e->getMessage()]);
        }
        break;
    
    // 更新剧组信息
    case 'update_crew':
        checkLogin();
        $id = $_GET['id'] ?? '';
        $name = $_GET['name'] ?? '';
        $description = $_GET['description'] ?? '';
        $film_name = $_GET['film_name'] ?? null;
        $startDate = $_GET['startDate'] ?? null;
        $endDate = $_GET['endDate'] ?? null;
        
        // 处理整数字段，将空字符串转换为null
        $estimatedDays = empty($_GET['estimatedDays']) ? null : (int)$_GET['estimatedDays'];
        $totalScenes = empty($_GET['totalScenes']) ? null : (int)$_GET['totalScenes'];
        $totalShots = empty($_GET['totalShots']) ? null : (int)$_GET['totalShots'];
        $actualDays = empty($_GET['actualDays']) ? null : (int)$_GET['actualDays'];
        $daysCompleted = empty($_GET['daysCompleted']) ? null : (int)$_GET['daysCompleted'];
        $completionRate = empty($_GET['completionRate']) ? null : (int)$_GET['completionRate'];
        
        // 处理genres字段
        $genres = isset($_GET['genres']) ? $_GET['genres'] : null;
        
        // 处理current_task_id字段
        $currentTaskId = isset($_GET['current_task_id']) ? $_GET['current_task_id'] : null;
        
        $user_id = $_SESSION['user_id'];
        
        // 检查剧组是否存在
        $existing = $db->queryOne("SELECT * FROM crew WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
        if (!$existing) {
            echo json_encode(['success' => false, 'message' => '剧组不存在']);
            exit();
        }
        
        // 构建更新语句和参数
        $updateFields = [];
        $params = [];
        
        if ($name !== '') {
            $updateFields[] = "name = ?";
            $params[] = $name;
        }
        
        if ($description !== '') {
            $updateFields[] = "description = ?";
            $params[] = $description;
        }
        
        if ($film_name !== null) {
            $updateFields[] = "film_name = ?";
            $params[] = $film_name;
        }
        
        if ($startDate !== null) {
            $updateFields[] = "startDate = ?";
            $params[] = $startDate;
        }
        
        if ($endDate !== null) {
            $updateFields[] = "endDate = ?";
            $params[] = $endDate;
        }
        
        if ($estimatedDays !== null) {
            $updateFields[] = "estimatedDays = ?";
            $params[] = $estimatedDays;
        }
        
        if ($totalScenes !== null) {
            $updateFields[] = "totalScenes = ?";
            $params[] = $totalScenes;
        }
        
        if ($totalShots !== null) {
            $updateFields[] = "totalShots = ?";
            $params[] = $totalShots;
        }
        
        if ($actualDays !== null) {
            $updateFields[] = "actualDays = ?";
            $params[] = $actualDays;
        }
        
        if ($daysCompleted !== null) {
            $updateFields[] = "daysCompleted = ?";
            $params[] = $daysCompleted;
        }
        
        if ($completionRate !== null) {
            $updateFields[] = "completionRate = ?";
            $params[] = $completionRate;
        }
        
        // 处理genres字段更新
        if ($genres !== null) {
            if (!is_array($genres)) {
                $genres = $genres ? explode(',', $genres) : [];
            }
            
            $genresJson = json_encode($genres);
            $updateFields[] = "genres = ?";
            $params[] = $genresJson;
        }
        
        // 处理current_task_id字段更新
        if ($currentTaskId !== null) {
            $updateFields[] = "current_task_id = ?";
            $params[] = $currentTaskId;
        }
        
        // 如果没有要更新的字段，直接返回成功
        if (empty($updateFields)) {
            echo json_encode(['success' => true, 'message' => '剧组信息无变更']);
            exit();
        }
        
        // 构建完整的SQL语句
        $sql = "UPDATE crew SET " . implode(', ', $updateFields) . " WHERE id = ? AND admin_user_id = ?";
        $params[] = $id;
        $params[] = $user_id;
        
        // 更新剧组信息
        $db->execute($sql, $params);
        echo json_encode(['success' => true, 'message' => '剧组更新成功']);
        break;
    
    // 删除剧组
    case 'delete_crew':
        checkLogin();
        $id = $_GET['id'] ?? '';
        $user_id = $_SESSION['user_id'];
        
        // 事务处理，先删除关联数据
        $db->beginTransaction();
        try {
            // 获取该剧组的所有成员
            $members = $db->query("SELECT DISTINCT user_id FROM crew_organization WHERE crew_id = ? AND user_id IS NOT NULL", [$id]);
            
            // 删除成员
            $db->execute("DELETE FROM crew_organization WHERE crew_id = ? AND admin_user_id = ?", [$id, $user_id]);
            // 删除权限
            $db->execute("DELETE FROM crew_permissions WHERE crew_id = ?", [$id]);
            // 删除共享资源
            $db->execute("DELETE FROM shared_resources WHERE crew_id = ? AND admin_user_id = ?", [$id, $user_id]);
            // 删除剧组
            $db->execute("DELETE FROM crew WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
            
            // 更新所有相关成员的crew_group字段
            if (is_array($members)) {
                foreach ($members as $member) {
                    updateUserCrewGroup($member['user_id']);
                }
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => '剧组删除成功']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => '删除失败：' . $e->getMessage()]);
        }
        break;
    
    // 获取剧组列表
    case 'get_crews':
        $user_id = checkLogin();
        
        // 查询所有剧组
        $allCrews = $db->query("SELECT * FROM crew ORDER BY created_at DESC");
        
        // 查询当前用户加入的剧组ID
        $memberCrewIds = $db->query("SELECT crew_id FROM crew_organization WHERE admin_user_id = ?", [$user_id]);
        $memberCrewIdArray = array_column($memberCrewIds, 'crew_id');
        
        // 为每个剧组添加is_member和is_creator字段，并处理genres字段
        foreach ($allCrews as &$crew) {
            $crew['is_creator'] = $crew['admin_user_id'] == $user_id;
            $crew['is_member'] = in_array($crew['id'], $memberCrewIdArray) || $crew['is_creator'];
            
            // 将genres从JSON字符串转换为数组
            if (!empty($crew['genres'])) {
                $crew['genres'] = json_decode($crew['genres'], true);
            } else {
                $crew['genres'] = [];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $allCrews]);
        break;
    
    // 添加成员
    case 'add_member':
        $user_id = checkLogin();
        $crew_id = $_GET['crew_id'] ?? '';
        $name = $_GET['name'] ?? '';
        $gender = $_GET['gender'] ?? '男';
        $position = $_GET['position'] ?? '';
        $group = $_GET['group'] ?? '';
        $responsibilities = $_GET['responsibilities'] ?? '';
        $phone = $_GET['phone'] ?? '';
        $email = $_GET['email'] ?? '';
        $wechat = $_GET['wechat'] ?? '';
        $account = $_GET['account'] ?? '';
        $password = $_GET['password'] ?? '';
        $is_admin = $_GET['is_admin'] ?? 0;
        $can_modify_password = $_GET['can_modify_password'] ?? 1;
        $is_authorized = $_GET['is_authorized'] ?? 0;
        
        // 检查是否是第一个成员
        $memberCount = $db->queryOne("SELECT COUNT(*) as count FROM crew_organization WHERE crew_id = ?", [$crew_id])['count'];
        $is_admin = $memberCount == 0 ? 1 : $is_admin;
        
        // 生成默认账号和密码
        if (empty($account)) {
            $account = strtolower(str_replace(' ', '', $name)) . date('ymd');
        }
        if (empty($password)) {
            $password = '123456'; // 默认密码
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 插入新成员
            $db->execute("INSERT INTO crew_organization (crew_id, admin_user_id, name, gender, position, `group`, responsibilities, phone, email, wechat, account, password, is_admin, can_modify_password, is_authorized) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                $crew_id, $user_id, $name, $gender, $position, $group, $responsibilities, $phone, $email, $wechat, $account, password_hash($password, PASSWORD_DEFAULT), $is_admin, $can_modify_password, $is_authorized
            ]);
            $memberId = $db->lastInsertId();
            
            // 如果选择了授权登录
            if ($is_authorized == 1) {
                // 检查手机号是否已注册
                $existingUser = $db->queryOne("SELECT id FROM users WHERE phone = ?", [$phone]);
                if (!$existingUser) {
                    // 生成密码（使用成员手机号后6位或默认密码）
                    $authPassword = substr($phone, -6) ?: '123456';
                    $hashedPassword = password_hash($authPassword, PASSWORD_DEFAULT);
                    
                    // 生成用户名（使用成员姓名+手机号后4位）
                    $username = str_replace(' ', '', $name) . substr($phone, -4);
                    
                    // 创建用户记录
                    $db->execute(
                        "INSERT INTO users (username, password, email, phone, status) VALUES (?, ?, ?, ?, ?)",
                        [$username, $hashedPassword, $email ?: null, $phone, 1]
                    );
                    $newUserId = $db->lastInsertId();
                    
                    // 更新成员的user_id
                    $db->execute(
                        "UPDATE crew_organization SET user_id = ? WHERE id = ?",
                        [$newUserId, $memberId]
                    );
                }
                
                // 更新新成员的crew_group字段
                if (isset($newUserId)) {
                    updateUserCrewGroup($newUserId);
                }
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => '成员添加成功']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => '添加成员失败：' . $e->getMessage()]);
        }
        break;
    
    // 获取成员列表
    case 'get_members':
        $user_id = checkLogin();
        $crew_id = $_GET['crew_id'] ?? '';
        $search = $_GET['search'] ?? '';
        
        // 检查用户是否是该剧组的创建者
        $crew = $db->queryOne("SELECT * FROM crew WHERE id = ?", [$crew_id]);
        if (!$crew) {
            echo json_encode(['success' => false, 'message' => '剧组不存在']);
            break;
        }
        
        // 只有剧组创建者才能查看成员列表
        if ($crew['admin_user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => '无权限查看该剧组成员']);
            break;
        }
        
        if ($search) {
            // 带搜索功能
            $sql = "SELECT * FROM crew_organization WHERE crew_id = ? AND (name LIKE ? OR position LIKE ? OR account LIKE ?) ORDER BY `group`, created_at DESC";
            $params = [$crew_id, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%'];
            $members = $db->query($sql, $params);
        } else {
            // 不带搜索功能
            $members = $db->query("SELECT * FROM crew_organization WHERE crew_id = ? ORDER BY `group`, created_at DESC", [$crew_id]);
        }
        
        echo json_encode(['success' => true, 'data' => $members]);
        break;
    
    // 获取单个成员信息
    case 'get_member':
        $user_id = checkLogin();
        $id = $_GET['id'] ?? '';
        
        // 先获取成员信息
        $member = $db->queryOne("SELECT * FROM crew_organization WHERE id = ?", [$id]);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => '成员不存在']);
            exit;
        }
        
        // 获取剧组信息
        $crew = $db->queryOne("SELECT * FROM crew WHERE id = ?", [$member['crew_id']]);
        if (!$crew) {
            echo json_encode(['success' => false, 'message' => '剧组不存在']);
            exit;
        }
        
        // 检查是否是管理员或剧组成员
        $isAdmin = $crew['admin_user_id'] == $user_id;
        $isMember = $db->queryOne("SELECT * FROM crew_organization WHERE crew_id = ? AND admin_user_id = ?", [$member['crew_id'], $user_id]) !== null;
        
        if ($isAdmin || $isMember) {
            echo json_encode(['success' => true, 'data' => $member]);
        } else {
            echo json_encode(['success' => false, 'message' => '无权限查看该成员']);
        }
        break;
    
    // 更新成员信息
    case 'update_member':
        $user_id = checkLogin();
        $id = $_GET['id'] ?? '';
        $name = $_GET['name'] ?? '';
        $gender = $_GET['gender'] ?? '男';
        $position = $_GET['position'] ?? '';
        $group = $_GET['group'] ?? '';
        $responsibilities = $_GET['responsibilities'] ?? '';
        $phone = $_GET['phone'] ?? '';
        $email = $_GET['email'] ?? '';
        $wechat = $_GET['wechat'] ?? '';
        $account = $_GET['account'] ?? '';
        $is_admin = $_GET['is_admin'] ?? 0;
        $can_modify_password = $_GET['can_modify_password'] ?? 1;
        $enabled = $_GET['enabled'] ?? 1;
        $is_authorized = $_GET['is_authorized'] ?? null;
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 获取原成员信息
            $originalMember = $db->queryOne("SELECT * FROM crew_organization WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
            if (!$originalMember) {
                throw new Exception('成员不存在');
            }
            
            // 更新成员基本信息
            $sql = "UPDATE crew_organization SET name = ?, gender = ?, position = ?, `group` = ?, responsibilities = ?, phone = ?, email = ?, wechat = ?, account = ?, is_admin = ?, can_modify_password = ?, enabled = ?";
            $params = [$name, $gender, $position, $group, $responsibilities, $phone, $email, $wechat, $account, $is_admin, $can_modify_password, $enabled];
            
            // 如果提供了密码且密码不为空，则更新密码
            $password = $_GET['password'] ?? '';
            $hashedPassword = null;
            if (!empty($password)) {
                // 只生成一次密码哈希值
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ", password = ?";
                $params[] = $hashedPassword;
            }
            
            // 如果提供了is_authorized参数
            if ($is_authorized !== null) {
                $sql .= ", is_authorized = ?";
                $params[] = $is_authorized;
                
                // 如果是从未授权变为授权
                if ($originalMember['is_authorized'] == 0 && $is_authorized == 1) {
                    // 检查手机号是否已注册
                    $existingUser = $db->queryOne("SELECT id FROM users WHERE phone = ?", [$phone]);
                    if (!$existingUser) {
                        // 生成密码（使用成员手机号后6位或默认密码）
                        $authPassword = $password ?: substr($phone, -6) ?: '123456';
                        $hashedPassword = password_hash($authPassword, PASSWORD_DEFAULT);
                        
                        // 生成用户名（使用成员姓名）
                        $username = str_replace(' ', '', $name);
                        
                        // 创建用户记录
                        $db->execute(
                            "INSERT INTO users (username, password, email, phone, status) VALUES (?, ?, ?, ?, ?)",
                            [$username, $hashedPassword, $email ?: null, $phone, 1]
                        );
                        $newUserId = $db->lastInsertId();
                        
                        // 更新成员的user_id
                        $sql .= ", user_id = ?";
                        $params[] = $newUserId;
                    }
                }
            }
            
            $sql .= " WHERE id = ? AND admin_user_id = ?";
            $params[] = $id;
            $params[] = $user_id;
            
            $db->execute($sql, $params);
            
            // 如果成员已授权且有对应的user_id，同时更新users表
            if ($originalMember['is_authorized'] == 1 && !empty($originalMember['user_id'])) {
                // 准备更新users表的字段
                $userUpdates = [];
                $userParams = [];
                
                // 更新姓名（username）
                $userUpdates[] = "username = ?";
                $userParams[] = str_replace(' ', '', $name);
                
                // 更新邮箱
                $userUpdates[] = "email = ?";
                $userParams[] = $email ?: null;
                
                // 更新手机号
                $userUpdates[] = "phone = ?";
                $userParams[] = $phone;
                
                // 如果提供了密码且密码不为空，则更新密码
                if (!empty($hashedPassword)) {
                    $userUpdates[] = "password = ?";
                    $userParams[] = $hashedPassword;
                }
                
                // 执行更新
                if (!empty($userUpdates)) {
                    $userSql = "UPDATE users SET " . implode(', ', $userUpdates) . " WHERE id = ?";
                    $userParams[] = $originalMember['user_id'];
                    $db->execute($userSql, $userParams);
                }
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => '成员信息更新成功']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => '更新成员失败：' . $e->getMessage()]);
        }
        break;
    
    // 授权成员登录网站
    case 'authorize_member':
        $user_id = checkLogin();
        $id = $_GET['id'] ?? '';
        
        // 获取成员信息
        $member = $db->queryOne("SELECT * FROM crew_organization WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => '成员不存在']);
            exit();
        }
        
        // 检查是否已经授权
        if ($member['is_authorized'] == 1) {
            echo json_encode(['success' => false, 'message' => '该成员已经授权，无法重复授权']);
            exit();
        }
        
        // 检查手机号是否已注册
        $existingUser = $db->queryOne("SELECT id FROM users WHERE phone = ?", [$member['phone']]);
        if ($existingUser) {
            echo json_encode(['success' => false, 'message' => '该手机号已注册，无法授权']);
            exit();
        }
        
        // 检查手机号是否存在
        if (empty($member['phone'])) {
            echo json_encode(['success' => false, 'message' => '该成员未设置手机号，无法授权']);
            exit();
        }
        
        // 生成密码（使用成员手机号后6位或默认密码）
        $password = substr($member['phone'], -6) ?: '123456';
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // 生成用户名（使用成员姓名+手机号后4位）
        $username = str_replace(' ', '', $member['name']) . substr($member['phone'], -4);
        
        // 事务处理
        $db->beginTransaction();
        try {
            // 创建用户记录
            $db->execute(
                "INSERT INTO users (username, password, email, phone, status) VALUES (?, ?, ?, ?, ?)",
                [$username, $hashedPassword, $member['email'] ?: null, $member['phone'], 1]
            );
            $newUserId = $db->lastInsertId();
            
            // 初始化用户积分
            $db->execute(
                "INSERT INTO user_points (user_id, points) VALUES (?, ?)",
                [$newUserId, 200]
            );
            
            // 记录积分历史
            $db->execute(
                "INSERT INTO points_history (user_id, points_change, reason) VALUES (?, ?, ?)",
                [$newUserId, 200, '剧组成员授权赠送']
            );
            
            // 初始化用户余额
            $db->execute(
                "INSERT INTO user_balances (user_id, balance) VALUES (?, ?)",
                [$newUserId, 0.00]
            );
            
            // 初始化用户资料
            $db->execute(
                "INSERT INTO user_profiles (user_id, nickname) VALUES (?, ?)",
                [$newUserId, $member['name']]
            );
            
            // 更新成员授权状态
            $db->execute(
                "UPDATE crew_organization SET is_authorized = 1, user_id = ? WHERE id = ? AND admin_user_id = ?",
                [$newUserId, $id, $user_id]
            );
            
            // 更新新授权成员的crew_group字段
            updateUserCrewGroup($newUserId);
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => '成员授权成功，用户名：' . $username . '，初始密码：' . $password]);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => '授权失败：' . $e->getMessage()]);
        }
        break;

    // 删除成员
    case 'delete_member':
        $user_id = checkLogin();
        $id = $_GET['id'] ?? '';
        
        // 获取要删除的成员信息
        $member = $db->queryOne("SELECT user_id FROM crew_organization WHERE id = ?", [$id]);
        
        $db->execute("DELETE FROM crew_organization WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
        $db->execute("DELETE FROM crew_permissions WHERE member_id = ?", [$id]);
        
        // 更新被删除成员的crew_group字段
        if ($member && $member['user_id']) {
            updateUserCrewGroup($member['user_id']);
        }
        
        echo json_encode(['success' => true, 'message' => '成员删除成功']);
        break;
    
    // 重置密码
    case 'reset_password':
        $user_id = checkLogin();
        $id = $_GET['id'] ?? '';
        $password = $_GET['password'] ?? '123456';
        
        // 检查是否允许修改密码
        $member = $db->queryOne("SELECT * FROM crew_organization WHERE id = ? AND admin_user_id = ?", [$id, $user_id]);
        if (!$member) {
            echo json_encode(['success' => false, 'message' => '成员不存在']);
            exit();
        }
        
        if ($member['can_modify_password'] == 0) {
            echo json_encode(['success' => false, 'message' => '该成员禁止管理员修改密码']);
            exit();
        }
        
        // 开始事务
        $db->beginTransaction();
        
        try {
            // 生成一次哈希值，确保两个表使用相同的哈希值
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // 更新crew_organization表中的密码
            $db->execute("UPDATE crew_organization SET password = ? WHERE id = ? AND admin_user_id = ?", [
                $hashedPassword, $id, $user_id
            ]);
            
            // 如果成员已授权，同时更新users表中的密码
            if ($member['is_authorized'] == 1 && !empty($member['user_id'])) {
                $db->execute("UPDATE users SET password = ? WHERE id = ?", [
                    $hashedPassword, $member['user_id']
                ]);
            }
            
            $db->commit();
            echo json_encode(['success' => true, 'message' => '密码重置成功']);
        } catch (Exception $e) {
            $db->rollBack();
            echo json_encode(['success' => false, 'message' => '重置密码失败：' . $e->getMessage()]);
        }
        break;
    
    // 获取权限列表
    case 'get_permissions':
        $user_id = checkLogin();
        $crew_id = $_GET['crew_id'] ?? '';
        
        // 获取所有成员
        $members = $db->query("SELECT id, name FROM crew_organization WHERE crew_id = ?", [$crew_id]);
        $memberMap = [];
        foreach ($members as $member) {
            $memberMap[$member['id']] = $member['name'];
        }
        
        // 获取所有资源类型
        $resourceTypes = [
            'novel_to_script' => '小说转剧本',
            'script_to_storyboard' => '剧本转分镜',
            'storyboard' => '分镜管理',
            'shooting_plan' => '拍摄计划',
            'shooting_notice' => '拍摄通告',
            'text_to_image' => '文生图',
            'image_to_video' => '图生视频'
        ];
        
        // 获取已设置的权限
        $permissions = $db->query("SELECT * FROM crew_permissions WHERE crew_id = ?", [$crew_id]);
        $permissionMap = [];
        foreach ($permissions as $perm) {
            $key = $perm['member_id'] . '_' . $perm['resource_type'];
            $permissionMap[$key] = $perm['can_edit'];
        }
        
        // 构建完整的权限列表
        $result = [];
        foreach ($members as $member) {
            foreach ($resourceTypes as $typeKey => $typeName) {
                $key = $member['id'] . '_' . $typeKey;
                $canEdit = $permissionMap[$key] ?? 0;
                $result[] = [
                    'id' => $member['id'] . '_' . $typeKey,
                    'member_id' => $member['id'],
                    'member_name' => $member['name'],
                    'resource_type' => $typeName,
                    'resource_type_key' => $typeKey,
                    'can_edit' => $canEdit
                ];
            }
        }
        
        echo json_encode(['success' => true, 'data' => $result]);
        break;
    
    // 保存权限
    case 'save_permission':
        $user_id = checkLogin();
        $crew_id = $_GET['crew_id'] ?? '';
        $member_id = $_GET['member_id'] ?? '';
        $resource_type = $_GET['resource_type'] ?? '';
        $can_edit = $_GET['can_edit'] ?? 0;
        
        // 检查权限是否已存在
        $existing = $db->queryOne("SELECT * FROM crew_permissions WHERE crew_id = ? AND member_id = ? AND resource_type = ?", [$crew_id, $member_id, $resource_type]);
        if ($existing) {
            $db->execute("UPDATE crew_permissions SET can_edit = ? WHERE id = ?", [$can_edit, $existing['id']]);
        } else {
            $db->execute("INSERT INTO crew_permissions (crew_id, member_id, resource_type, can_edit) VALUES (?, ?, ?, ?)", [$crew_id, $member_id, $resource_type, $can_edit]);
        }
        echo json_encode(['success' => true, 'message' => '权限保存成功']);
        break;
    
    // 获取共享资源
    case 'get_resources':
        $user_id = checkLogin();
        $resources = $db->query("SELECT * FROM shared_resources WHERE admin_user_id = ? ORDER BY created_at DESC", [$user_id]);
        echo json_encode(['success' => true, 'data' => $resources]);
        break;
    
    // 共享资源
    case 'share_resource':
        $user_id = checkLogin();
        $crew_id = $_GET['crew_id'] ?? '';
        $resource_type = $_GET['resource_type'] ?? '';
        $resource_id = $_GET['resource_id'] ?? '';
        $title = $_GET['title'] ?? '';
        
        // 检查资源是否已共享
        $existing = $db->queryOne("SELECT * FROM shared_resources WHERE crew_id = ? AND resource_type = ? AND resource_id = ?", [$crew_id, $resource_type, $resource_id]);
        if ($existing) {
            echo json_encode(['success' => false, 'message' => '资源已共享']);
            exit();
        }
        
        // 插入共享资源
        $db->execute("INSERT INTO shared_resources (crew_id, admin_user_id, resource_type, resource_id, title) VALUES (?, ?, ?, ?, ?)", [$crew_id, $user_id, $resource_type, $resource_id, $title]);
        echo json_encode(['success' => true, 'message' => '资源共享成功']);
        break;
    
    // 检查是否有权限访问资源
    case 'can_access':
        $user_id = checkLogin();
        $resource_id = $_GET['resource_id'] ?? '';
        $resource_type = $_GET['resource_type'] ?? '';
        
        // 检查是否是资源所属管理员
        $resource = $db->queryOne("SELECT * FROM shared_resources WHERE resource_id = ? AND resource_type = ?", [$resource_id, $resource_type]);
        if (!$resource) {
            echo json_encode(['success' => false, 'message' => '资源不存在']);
            exit();
        }
        
        // 资源所属管理员可以直接访问
        if ($resource['admin_user_id'] == $user_id) {
            echo json_encode(['success' => true, 'can_access' => true]);
            exit();
        }
        
        // 检查是否是该剧组的成员
        $member = $db->queryOne("SELECT * FROM crew_organization WHERE crew_id = ? AND user_id = ?", [$resource['crew_id'], $user_id]);
        echo json_encode(['success' => true, 'can_access' => $member !== null]);
        break;
    
    // 检查是否有权限编辑资源
    case 'can_edit':
        $user_id = checkLogin();
        $resource_id = $_GET['resource_id'] ?? '';
        $resource_type = $_GET['resource_type'] ?? '';
        
        // 检查是否是资源所属管理员
        $resource = $db->queryOne("SELECT * FROM shared_resources WHERE resource_id = ? AND resource_type = ?", [$resource_id, $resource_type]);
        if (!$resource) {
            echo json_encode(['success' => false, 'message' => '资源不存在']);
            exit();
        }
        
        // 资源所属管理员可以直接编辑
        if ($resource['admin_user_id'] == $user_id) {
            echo json_encode(['success' => true, 'can_edit' => true]);
            exit();
        }
        
        // 检查成员是否有编辑权限
        $permission = $db->queryOne("SELECT * FROM crew_permissions WHERE crew_id = ? AND member_id = ? AND resource_type = ? AND can_edit = 1", [$resource['crew_id'], $user_id, $resource_type]);
        echo json_encode(['success' => true, 'can_edit' => $permission !== null]);
        break;
    
    default:
        echo json_encode(['success' => false, 'message' => '无效的请求']);
        break;
}
