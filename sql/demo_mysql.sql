-- demo_mysql.sql - 测试用户演示数据初始化 (MySQL兼容版本)

-- 1. 先删除已存在的demo用户（避免冲突）
DELETE FROM users WHERE username = 'demo';

-- 2. 创建测试用户 (账号: demo, 密码: demo, 手机号: 13800138000)
INSERT INTO users (username, password, email, phone, created_at, updated_at, status, level, openid) 
VALUES ('demo', '$2y$10$X28TbsWjyRRaTOGMhiFtwOhrunx7kSPinuwyOKiG4tt9NZzf2JJPe', 'demo@example.com', '13800138000', NOW(), NOW(), 1, 0, 'o2Nt65B1-xxx'); -- 演示用openid

-- 3. 初始化用户扩展信息
INSERT INTO user_profiles (user_id, nickname, avatar, gender, birthday, bio, created_at, updated_at)
SELECT id, '演示用户', 'assets/default-avatar.png', 1, '1990-01-01', '这是一个演示用户，用于展示系统功能', NOW(), NOW()
FROM users WHERE username = 'demo';

-- 4. 初始化用户余额
INSERT INTO user_balances (user_id, balance, updated_at)
SELECT id, 1000.50, NOW()
FROM users WHERE username = 'demo';

-- 5. 初始化用户积分
INSERT INTO user_points (user_id, points, updated_at)
SELECT id, 5000, NOW()
FROM users WHERE username = 'demo';

-- 6. 初始化充值记录
INSERT INTO recharge_records (user_id, amount, order_no, payment_method, status, created_at, paid_at)
SELECT id, 500.00, CONCAT('RECHARGE_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), '支付宝', 1, DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM users WHERE username = 'demo';

INSERT INTO recharge_records (user_id, amount, order_no, payment_method, status, created_at, paid_at)
SELECT id, 800.00, CONCAT('RECHARGE_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), '微信支付', 1, DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)
FROM users WHERE username = 'demo';

INSERT INTO recharge_records (user_id, amount, order_no, payment_method, status, created_at, paid_at)
SELECT id, 300.50, CONCAT('RECHARGE_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), '银行卡', 1, DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY)
FROM users WHERE username = 'demo';

-- 7. 初始化消费记录
INSERT INTO consumption_records (user_id, amount, order_no, item_type, item_id, description, created_at)
SELECT id, 199.00, CONCAT('CONSUME_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), 'storyboard', 1, '生成故事板', DATE_SUB(NOW(), INTERVAL 8 DAY)
FROM users WHERE username = 'demo';

INSERT INTO consumption_records (user_id, amount, order_no, item_type, item_id, description, created_at)
SELECT id, 299.00, CONCAT('CONSUME_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), 'text2img', 2, '文生图生成', DATE_SUB(NOW(), INTERVAL 6 DAY)
FROM users WHERE username = 'demo';

INSERT INTO consumption_records (user_id, amount, order_no, item_type, item_id, description, created_at)
SELECT id, 499.00, CONCAT('CONSUME_', SUBSTRING(MD5(RAND()) FROM 1 FOR 8), '_', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s')), 'script', 3, '剧本生成', DATE_SUB(NOW(), INTERVAL 3 DAY)
FROM users WHERE username = 'demo';

-- 8. 初始化历史任务
-- 小说转剧本任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'novel_to_script', '小说《测试小说》转剧本', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"novel_title":"测试小说","novel_content":"这是一个测试小说内容","genre":"现代都市"}', '{"script_title":"测试剧本","scenes":3}', DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), 0
FROM users WHERE username = 'demo';

-- 剧本转分镜任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'script_to_storyboard', '剧本《测试剧本》转分镜', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"script_id":1,"scene_count":5}', '{"storyboard_id":1,"shots":20}', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), 0
FROM users WHERE username = 'demo';

-- 分镜管理任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'storyboard_management', '分镜管理', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"storyboard_id":1}', '{"updated_shots":15}', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), 0
FROM users WHERE username = 'demo';

-- 故事板任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'storyboard', '创建故事板', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"title":"测试故事板","description":"这是一个测试故事板","shots":10}', '{"storyboard_id":2,"shots":10}', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), 0
FROM users WHERE username = 'demo';

-- 拍摄计划任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'shooting_plan', '生成拍摄计划', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"storyboard_id":2,"shooting_days":7}', '{"plan_id":1,"days":7,"scenes":15}', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), 0
FROM users WHERE username = 'demo';

-- 拍摄通告任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'shooting_notice', '生成拍摄通告', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"plan_id":1,"date":"2025-12-10"}', '{"notice_id":1,"date":"2025-12-10"}', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 1 DAY), 0
FROM users WHERE username = 'demo';

-- 文生图任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'text_to_image', '文生图生成', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"prompt":"一个美丽的风景","style":"油画","resolution":"1024x768"}', '{"image_id":1,"url":"assets/demo-image.jpg"}', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), 0
FROM users WHERE username = 'demo';

-- 其他任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'other', '其他测试任务', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"task_name":"测试任务","params":{}}', '{"result":"测试结果"}', DATE_SUB(NOW(), INTERVAL 1 DAY), NOW(), NOW(), 0
FROM users WHERE username = 'demo';

-- 剧本分析任务（使用指定任务号）
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'script_analysis', '剧本分析任务', 1, 100, 'script_analysis_692c3a82f35e05.03251698', '{"task_id":"script_analysis_692c3a82f35e05.03251698","script_title":"测试剧本"}', '{"result_file":"script_analysis_692c3a82f35e05.03251698_announcement.json"}', DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), 0
FROM users WHERE username = 'demo';

-- 9. 初始化额外的历史任务（使用不同的任务号）
-- 剧本转分镜任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'script_to_storyboard', '剧本转分镜任务', 1, 100, 'script_analysis_6922c98d8e3442.86162742', '{"script_id":2,"scene_count":8}', '{"storyboard_id":2,"shots":30}', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 0
FROM users WHERE username = 'demo';

-- 分镜管理任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'storyboard_management', '分镜管理任务', 1, 100, 'script_analysis_6922c98d8e3442.86162742', '{"storyboard_id":2}', '{"updated_shots":25}', DATE_SUB(NOW(), INTERVAL 13 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), DATE_SUB(NOW(), INTERVAL 12 DAY), 0
FROM users WHERE username = 'demo';

-- 故事板管理任务
INSERT INTO tasks (user_id, task_type, title, status, progress, task_id, input_data, output_data, created_at, updated_at, completed_at, current_status)
SELECT id, 'storyboard', '故事板管理任务', 1, 100, 'script_analysis_6922c98d8e3442.86162742', '{"title":"测试故事板2","description":"这是第二个测试故事板","shots":15}', '{"storyboard_id":3,"shots":15}', DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), 0
FROM users WHERE username = 'demo';

-- 10. 初始化任务详情
-- 获取任务ID并插入详情
INSERT INTO task_details (task_id, `key`, value)
SELECT id, 'novel_chapters', '10'
FROM tasks 
WHERE user_id = (SELECT id FROM users WHERE username = 'demo') 
ORDER BY created_at ASC 
LIMIT 1;

INSERT INTO task_details (task_id, `key`, value)
SELECT id, 'script_pages', '50'
FROM tasks 
WHERE user_id = (SELECT id FROM users WHERE username = 'demo') 
ORDER BY created_at ASC 
LIMIT 1 OFFSET 1;

INSERT INTO task_details (task_id, `key`, value)
SELECT id, 'storyboard_shots', '20'
FROM tasks 
WHERE user_id = (SELECT id FROM users WHERE username = 'demo') 
ORDER BY created_at ASC 
LIMIT 1 OFFSET 2;

-- 11. 初始化短信验证码记录（演示用）
INSERT INTO sms_verifications (phone, code, expired_at, created_at, used)
VALUES ('13800138000', '123456', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), 0);

-- 12. 初始化邮箱验证码记录（演示用）
INSERT INTO email_verifications (email, code, expired_at, created_at, used)
VALUES ('demo@example.com', '654321', DATE_ADD(NOW(), INTERVAL 5 MINUTE), NOW(), 0);

-- 13. 显示创建的用户信息
SELECT '创建的演示用户信息' AS info;
SELECT id, username, email, phone, created_at FROM users WHERE username = 'demo';

SELECT '初始化完成' AS status;