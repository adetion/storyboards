-- 创建剧组表
CREATE TABLE IF NOT EXISTS `crew` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `admin_user_id` INT NOT NULL COMMENT '所属管理员ID，关联user表',
  `name` VARCHAR(100) NOT NULL COMMENT '剧组名称',
  `description` TEXT COMMENT '剧组描述',
  `current_task_id` VARCHAR(256) DEFAULT '' COMMENT '当前任务号',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建剧组组织架构表
CREATE TABLE IF NOT EXISTS `crew_organization` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `crew_id` INT NOT NULL COMMENT '所属剧组ID，关联crew表',
  `admin_user_id` INT NOT NULL COMMENT '所属管理员ID，关联user表',
  `user_id` INT COMMENT '关联users表ID，已授权用户',
  `name` VARCHAR(100) NOT NULL COMMENT '姓名',
  `gender` VARCHAR(10) DEFAULT '男' COMMENT '性别',
  `position` VARCHAR(100) NOT NULL COMMENT '职务',
  `group` VARCHAR(50) NOT NULL COMMENT '所属分组',
  `responsibilities` TEXT COMMENT '职责',
  `phone` VARCHAR(20) COMMENT '联系电话',
  `email` VARCHAR(100) COMMENT '联系邮件',
  `wechat` VARCHAR(50) COMMENT '微信号',
  `account` VARCHAR(50) UNIQUE COMMENT '登录账号',
  `password` VARCHAR(255) COMMENT '登录密码',
  `is_admin` TINYINT DEFAULT 0 COMMENT '是否管理员，1为管理员',
  `can_modify_password` TINYINT DEFAULT 1 COMMENT '是否允许管理员修改密码，1为允许',
  `can_view_resources` TINYINT DEFAULT 1 COMMENT '是否允许查看资源，1为允许',
  `can_edit_resources` TINYINT DEFAULT 0 COMMENT '是否允许编辑资源，1为允许',
  `enabled` TINYINT DEFAULT 1 COMMENT '是否启用，1为启用',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建剧组权限表
CREATE TABLE IF NOT EXISTS `crew_permissions` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `crew_id` INT NOT NULL COMMENT '所属剧组ID，关联crew表',
  `member_id` INT NOT NULL COMMENT '成员ID，关联crew_organization表',
  `resource_type` VARCHAR(50) NOT NULL COMMENT '资源类型',
  `can_edit` TINYINT DEFAULT 0 COMMENT '是否允许编辑，1为允许',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建共享资源表
CREATE TABLE IF NOT EXISTS `shared_resources` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `crew_id` INT NOT NULL COMMENT '所属剧组ID，关联crew表',
  `admin_user_id` INT NOT NULL COMMENT '所属管理员ID，关联user表',
  `resource_type` VARCHAR(50) NOT NULL COMMENT '资源类型',
  `resource_id` INT NOT NULL COMMENT '资源ID',
  `title` VARCHAR(200) NOT NULL COMMENT '资源标题',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 添加外键约束
ALTER TABLE `crew_organization` ADD CONSTRAINT `fk_crew_organization_crew` FOREIGN KEY (`crew_id`) REFERENCES `crew`(`id`) ON DELETE CASCADE;
ALTER TABLE `crew_permissions` ADD CONSTRAINT `fk_crew_permissions_crew` FOREIGN KEY (`crew_id`) REFERENCES `crew`(`id`) ON DELETE CASCADE;
ALTER TABLE `crew_permissions` ADD CONSTRAINT `fk_crew_permissions_member` FOREIGN KEY (`member_id`) REFERENCES `crew_organization`(`id`) ON DELETE CASCADE;
ALTER TABLE `shared_resources` ADD CONSTRAINT `fk_shared_resources_crew` FOREIGN KEY (`crew_id`) REFERENCES `crew`(`id`) ON DELETE CASCADE;
