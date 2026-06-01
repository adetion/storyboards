-- membership_migration.sql - 会员功能数据库迁移脚本
-- 适用于MySQL和SQLite数据库

-- 1. 为users表添加level和membership_expire字段
-- MySQL语法
ALTER TABLE users ADD COLUMN level INT DEFAULT 0 NOT NULL;
ALTER TABLE users ADD COLUMN membership_expire DATETIME DEFAULT NULL;

-- 2. 用户等级说明：
-- level: 用户等级，0=普通用户，1=个人会员，2=团队会员
-- membership_expire: 会员有效期，NULL=非会员

-- 3. 积分计算规则：
-- 1元 = 100积分（在代码中通过Config::RECHARGE_RATE配置）

-- 4. 会员有效期：
-- 会员升级后有效期为1年，到期自动降级

-- 5. 索引优化（可选）
-- 为membership_expire字段添加索引，提高查询效率
-- CREATE INDEX idx_users_membership_expire ON users(membership_expire);
