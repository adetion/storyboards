-- MySQL数据库完整迁移脚本
-- 包含所有表结构定义

-- =============================================================================
-- 用户认证与基础信息相关表
-- =============================================================================

-- 用户表
-- 存储系统用户的基本信息
-- 字段说明:
--   id: 用户唯一标识符
--   username: 用户名，唯一且不能为空
--   password: 加密后的用户密码
--   email: 用户邮箱地址，唯一
--   phone: 用户手机号码，唯一且不能为空
--   created_at: 账户创建时间
--   updated_at: 账户信息最后更新时间
--   status: 用户状态，1表示正常，其他值表示禁用
--   level: 用户等级，0表示免费，1表示个人会员，2表示团队会员
--   openid: 微信用户唯一标识符
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20) UNIQUE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    status TINYINT NOT NULL DEFAULT 1,
    level TINYINT NOT NULL DEFAULT 0,
    openid VARCHAR(100) UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 短信验证码表
-- 存储手机验证码信息，用于用户注册和登录验证
-- 字段说明:
--   id: 记录唯一标识符
--   phone: 手机号码
--   code: 验证码
--   expired_at: 验证码过期时间
--   created_at: 验证码创建时间
--   used: 是否已使用，0表示未使用，1表示已使用
CREATE TABLE IF NOT EXISTS sms_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    phone VARCHAR(20) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expired_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 邮箱验证码表
-- 存储邮箱验证码信息，用于用户注册和找回密码
-- 字段说明:
--   id: 记录唯一标识符
--   email: 邮箱地址
--   code: 验证码
--   expired_at: 验证码过期时间
--   created_at: 验证码创建时间
--   used: 是否已使用，0表示未使用，1表示已使用
CREATE TABLE IF NOT EXISTS email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    code VARCHAR(6) NOT NULL,
    expired_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used TINYINT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 用户中心相关表
-- =============================================================================

-- 用户扩展信息表
-- 存储用户的个人资料信息
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   nickname: 用户昵称
--   avatar: 用户头像URL
--   gender: 用户性别，1表示男性，2表示女性，0表示未知
--   birthday: 用户生日
--   bio: 用户个人简介
--   created_at: 创建时间
--   updated_at: 最后更新时间
CREATE TABLE IF NOT EXISTS user_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    nickname VARCHAR(50),
    avatar VARCHAR(255),
    gender TINYINT,
    birthday DATE,
    bio TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户余额表
-- 存储用户的账户余额信息
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   balance: 用户账户余额
--   updated_at: 最后更新时间
CREATE TABLE IF NOT EXISTS user_balances (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    balance DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户积分表
-- 存储用户的积分信息
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   points: 用户积分总数
--   updated_at: 最后更新时间
CREATE TABLE IF NOT EXISTS user_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points INT NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户充值记录表
-- 存储用户的充值记录
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   amount: 充值金额
--   order_no: 订单号，唯一
--   payment_method: 支付方式
--   status: 订单状态，0表示未支付，1表示已支付
--   created_at: 订单创建时间
--   paid_at: 支付时间
CREATE TABLE IF NOT EXISTS recharge_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    order_no VARCHAR(50) UNIQUE NOT NULL,
    payment_method VARCHAR(20) NOT NULL,
    status TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户消费记录表
-- 存储用户的消费记录
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   amount: 消费金额
--   order_no: 订单号，唯一
--   item_type: 商品类型
--   item_id: 商品ID
--   description: 消费描述
--   created_at: 消费时间
CREATE TABLE IF NOT EXISTS consumption_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    order_no VARCHAR(50) UNIQUE NOT NULL,
    item_type VARCHAR(20) NOT NULL,
    item_id INT,
    description TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 任务管理系统相关表
-- =============================================================================

-- 任务表
-- 存储用户创建的各种任务信息
-- 字段说明:
--   id: 任务唯一标识符
--   user_id: 关联用户ID
--   task_type: 任务类型，如novel_to_script, script_to_storyboard等
--   title: 任务标题
--   status: 任务状态，0表示失败，1表示进行中，2表示已完成
--   progress: 任务进度，取值范围0-100
--   input_data: 输入数据（JSON格式）
--   output_data: 输出数据（JSON格式）
--   created_at: 任务创建时间
--   updated_at: 任务更新时间
--   completed_at: 任务完成时间
--   task_id: 任务标识符
--   current_status: 当前任务状态，0表示非当前任务，1表示当前活跃任务
CREATE TABLE IF NOT EXISTS tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    task_type VARCHAR(50) NOT NULL,
    title VARCHAR(100) NOT NULL,
    status TINYINT NOT NULL DEFAULT 0,
    progress INT NOT NULL DEFAULT 0,
    input_data TEXT,
    output_data TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    task_id VARCHAR(100) DEFAULT NULL,
    current_status INT DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 任务详情表
-- 存储任务的详细信息，用于存储更详细的任务参数
-- 字段说明:
--   id: 记录唯一标识符
--   task_id: 关联任务ID
--   key: 参数键名
--   value: 参数值
CREATE TABLE IF NOT EXISTS task_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    task_id INT NOT NULL,
    `key` VARCHAR(50) NOT NULL,
    value TEXT,
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 用户积分变动记录表
-- 存储用户积分变动历史记录
-- 字段说明:
--   id: 记录唯一标识符
--   user_id: 关联用户ID
--   points_change: 积分变动值，正数表示增加，负数表示减少
--   reason: 积分变动原因
--   source: 积分来源，如system表示系统赠送
--   task_id: 关联任务ID
--   content: 附加内容
--   created_at: 变动时间
CREATE TABLE IF NOT EXISTS points_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    points_change INT NOT NULL,
    reason VARCHAR(100) NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'system',
    task_id VARCHAR(50),
    content TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================================================
-- 数据库索引定义
-- =============================================================================

-- 为常用查询字段创建索引以优化查询性能
CREATE INDEX idx_users_phone ON users(phone);
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);

CREATE INDEX idx_sms_verifications_phone ON sms_verifications(phone);

CREATE INDEX idx_email_verifications_email ON email_verifications(email);

CREATE INDEX idx_user_profiles_user_id ON user_profiles(user_id);

CREATE INDEX idx_user_balances_user_id ON user_balances(user_id);

CREATE INDEX idx_user_points_user_id ON user_points(user_id);

CREATE INDEX idx_points_history_user_id ON points_history(user_id);

CREATE INDEX idx_recharge_records_user_id ON recharge_records(user_id);

CREATE INDEX idx_consumption_records_user_id ON consumption_records(user_id);

CREATE INDEX idx_tasks_user_id ON tasks(user_id);
CREATE INDEX idx_tasks_type_status ON tasks(task_type, status);
CREATE INDEX idx_tasks_current_status ON tasks(current_status);

CREATE INDEX idx_task_details_task_id ON task_details(task_id);