-- demo_reset_mysql.sql - 重置演示用户数据，保留基本登录信息 (MySQL兼容版本)

-- 获取演示用户ID的子查询

-- 1. 清除用户扩展信息
DELETE FROM user_profiles WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 2. 清除用户余额记录
DELETE FROM user_balances WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 3. 清除用户积分记录
DELETE FROM user_points WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 4. 清除充值记录
DELETE FROM recharge_records WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 5. 清除消费记录
DELETE FROM consumption_records WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 6. 清除任务详情记录（先清除子表）
DELETE FROM task_details WHERE task_id IN (SELECT id FROM tasks WHERE user_id = (SELECT id FROM users WHERE username = 'demo'));

-- 7. 清除任务记录
DELETE FROM tasks WHERE user_id = (SELECT id FROM users WHERE username = 'demo');

-- 8. 清除短信验证码记录
DELETE FROM sms_verifications WHERE phone = (SELECT phone FROM users WHERE username = 'demo');

-- 9. 清除邮箱验证码记录
DELETE FROM email_verifications WHERE email = (SELECT email FROM users WHERE username = 'demo');

-- 10. 重新初始化基本用户中心数据
-- 重新初始化用户扩展信息
INSERT INTO user_profiles (user_id, nickname, avatar, gender, birthday, bio, created_at, updated_at)
SELECT id, '演示用户', 'assets/default-avatar.png', 1, '1990-01-01', '这是一个演示用户，用于展示系统功能', NOW(), NOW()
FROM users WHERE username = 'demo';

-- 重新初始化用户余额
INSERT INTO user_balances (user_id, balance, updated_at)
SELECT id, 0.00, NOW()
FROM users WHERE username = 'demo';

-- 重新初始化用户积分
INSERT INTO user_points (user_id, points, updated_at)
SELECT id, 0, NOW()
FROM users WHERE username = 'demo';

-- 显示重置结果
SELECT '演示用户数据已重置，保留基本登录信息' AS status;
SELECT id, username, email, phone, status FROM users WHERE username = 'demo';