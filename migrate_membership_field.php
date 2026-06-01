<?php
// migrate_membership_field.php - 添加会员有效期字段并实现自动降级功能

require_once 'config.php';

// 创建数据库连接
$db = Database::getInstance();

// 检查users表是否有level和membership_expire字段
try {
    // 检查并添加level字段
    $result = $db->query("SHOW COLUMNS FROM users LIKE 'level'");
    if (empty($result)) {
        echo "添加level字段到users表...\n";
        $db->execute("ALTER TABLE users ADD COLUMN level INT DEFAULT 0 NOT NULL");
        echo "✓ level字段添加成功\n";
    } else {
        echo "✓ level字段已存在\n";
    }

    // 检查并添加membership_expire字段
    $result = $db->query("SHOW COLUMNS FROM users LIKE 'membership_expire'");
    if (empty($result)) {
        echo "添加membership_expire字段到users表...\n";
        $db->execute("ALTER TABLE users ADD COLUMN membership_expire DATETIME DEFAULT NULL");
        echo "✓ membership_expire字段添加成功\n";
    } else {
        echo "✓ membership_expire字段已存在\n";
    }

    echo "\n数据库迁移完成！\n";
    echo "字段说明：\n";
    echo "- level: 用户等级，0=普通用户，1=个人会员，2=团队会员\n";
    echo "- membership_expire: 会员有效期，NULL=非会员\n";
} catch (Exception $e) {
    echo "数据库迁移失败：" . $e->getMessage() . "\n";
    exit(1);
}

// 测试数据更新
echo "\n更新测试用户数据...\n";
try {
    // 更新demo用户为普通用户
    $db->execute("UPDATE users SET level = 0, membership_expire = NULL WHERE username = 'demo'");
    echo "✓ 测试用户数据更新成功\n";

    // 显示用户表结构
    echo "\nusers表结构：\n";
    $columns = $db->query("SHOW COLUMNS FROM users");
    foreach ($columns as $column) {
        echo "- {$column['Field']}: {$column['Type']} ({$column['Null']})\n";
    }
} catch (Exception $e) {
    echo "测试数据更新失败：" . $e->getMessage() . "\n";
}

// 测试自动降级函数
echo "\n测试自动降级函数...\n";
function checkMembershipExpiry($userId)
{
    global $db;

    try {
        // 获取用户会员信息
        $user = $db->queryOne("SELECT level, membership_expire FROM users WHERE id = ?", [$userId]);

        if (!$user) {
            return false;
        }

        // 检查会员是否过期
        if ($user['level'] > 0 && $user['membership_expire'] < date('Y-m-d H:i:s')) {
            // 会员已过期，自动降级
            $db->execute("UPDATE users SET level = 0, membership_expire = NULL WHERE id = ?", [$userId]);
            echo "✓ 用户ID {$userId} 会员已过期，自动降级为普通用户\n";
            return true;
        }

        return false;
    } catch (Exception $e) {
        echo "自动降级失败：" . $e->getMessage() . "\n";
        return false;
    }
}

// 测试自动降级函数
// checkMembershipExpiry(1); // 取消注释以测试

echo "\n迁移脚本执行完成！\n";
