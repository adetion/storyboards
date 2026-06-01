<?php
/**
 * monthly_points.php
 * 每月自动赠送积分脚本
 * 执行频率：每月1日凌晨2点
 */

// 引入必要的类和配置
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Auth.php';

// 日志函数
function logMessage($message, $level = 'info') {
    $logFile = __DIR__ . '/../logs/monthly_points.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message\n";
    file_put_contents($logFile, $logEntry, FILE_APPEND);
    echo $logEntry;
}

try {
    logMessage('开始执行每月积分赠送任务');
    
    // 初始化Auth类
    $auth = new Auth();
    
    // 查询所有活跃用户
    $sql = "SELECT id, level, membership_expire FROM users WHERE status = 1";
    $users = $auth->db->query($sql);
    
    if (empty($users)) {
        logMessage('没有活跃用户需要赠送积分');
        exit(0);
    }
    
    $totalUsers = count($users);
    $successCount = 0;
    $failedCount = 0;
    
    logMessage("共找到 {$totalUsers} 个活跃用户");
    
    // 遍历用户，赠送积分
    foreach ($users as $user) {
        $userId = $user['id'];
        $level = $user['level'];
        $membershipExpire = $user['membership_expire'];
        $pointsToAdd = 0;
        $reason = '';
        
        try {
            // 检查用户是否为有效会员
            $isValidMember = false;
            $memberType = '';
            
            if ($level > 0 && $membershipExpire) {
                // 检查会员是否过期
                $now = date('Y-m-d H:i:s');
                if ($membershipExpire >= $now) {
                    $isValidMember = true;
                    
                    // 判断会员类型（月度或年度）
                    // 这里假设通过level无法直接判断，需要根据membership_expire计算
                    // 简单处理：如果有效期大于11个月，视为年度会员
                    $expireTime = strtotime($membershipExpire);
                    $nowTime = time();
                    $diffMonths = (($expireTime - $nowTime) / (30 * 24 * 60 * 60));
                    
                    if ($diffMonths > 11) {
                        $memberType = '2'; // 年度会员
                    } else {
                        $memberType = '1'; // 月度会员
                    }
                }
            }
            
            if ($isValidMember) {
                // 会员用户：根据等级和类型获取积分配置
                $configKey = "{$memberType}_{$level}";
                if (isset(Config::VIP_POINTS[$configKey])) {
                    $pointsConfig = Config::VIP_POINTS[$configKey];
                    $pointsToAdd = $pointsConfig['base'] + $pointsConfig['bonus'];
                    $reason = "月度会员积分赠送（等级：{$level}，类型：{$memberType}）";
                } else {
                    logMessage("用户ID {$userId} 会员配置不存在：{$configKey}", 'warning');
                    $failedCount++;
                    continue;
                }
            } else {
                // 普通用户：固定赠送500积分
                $pointsToAdd = 500;
                $reason = "月度普通用户积分赠送";
            }
            
            // 添加积分
            $result = $auth->addUserPoints($userId, $pointsToAdd, $reason, 'system');
            
            if ($result['success']) {
                $successCount++;
                logMessage("用户ID {$userId} 积分赠送成功：+{$pointsToAdd} 积分，原因：{$reason}");
            } else {
                $failedCount++;
                logMessage("用户ID {$userId} 积分赠送失败：{$result['message']}", 'error');
            }
            
        } catch (Exception $e) {
            $failedCount++;
            logMessage("处理用户ID {$userId} 时发生异常：" . $e->getMessage(), 'error');
        }
    }
    
    logMessage("每月积分赠送任务完成：成功 {$successCount} 人，失败 {$failedCount} 人");
    
} catch (Exception $e) {
    logMessage("执行每月积分赠送任务时发生严重错误：" . $e->getMessage(), 'error');
    exit(1);
}

exit(0);
