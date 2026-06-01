# 智影工场 (Zhiying Workshop)

**AI 驱动的影视创作平台** — 从文字到成片，一站式 AI 影视制作流水线。

【慎入】本系统是【古法：面向业务，非面向对象】PHP编程，有兴趣的可以改得更新潮些。

作者：[adetion] email # <i126@126.com>

声明：非企业个人用户随意使用。非本人授权，不得用于商业用途。

---
<img width="1213" height="914" alt="截屏2026-06-01 22 42 09" src="https://github.com/user-attachments/assets/76358ae5-76e4-4390-be05-e2db459fd13b" />



## 目录

- [概述](#概述)
- [功能特性](#功能特性)
- [技术栈](#技术栈)
- [系统架构](#系统架构)
- [目录结构](#目录结构)
- [环境要求](#环境要求)
- [安装部署](#安装部署)
- [配置参考](#配置参考)
- [数据库设计](#数据库设计)
- [API 参考](#api-参考)
- [AI 制作流水线](#ai-制作流水线)
- [支付系统](#支付系统)
- [定时任务](#定时任务)
- [后台任务处理](#后台任务处理)
- [安全说明](#安全说明)
- [开发说明](#开发说明)

---

## 概述

智影工场是一个面向个人创作者和影视团队的一站式 AI 影视制作平台。用户从小说/剧本文字出发，通过 AI 自动完成剧本改编、分镜设计、角色生成、场景管理、图片生成、视频生成，最终输出拍摄计划和拍摄通告 — 覆盖从创意到成片的全部环节。

**核心价值：** 将传统影视制作中需要数周的前期工作压缩至分钟级，让每个人都能成为导演。

---

## 功能特性

### AI 制作流水线

- **小说转剧本** — 上传小说文本，DeepSeek AI 自动分析并转化为标准剧本格式
- **剧本转分镜** — 自动拆解场次（场景）、生成分镜表（镜头描述、机位、灯光、对白等）
- **AI 角色创作** — AI 生成角色设定及三视图（正面/侧面/背面）
- **场景空间管理** — 拍摄场景/时空的创建、编辑与管理
- **文生图** — 文本描述直接生成分镜画面
- **图生视频** — 基于首尾帧图片生成动态视频片段
- **文生视频** — 文本直接驱动视频生成
- **图生文** — 图片内容识别与描述

### 协作与管理

- **剧组管理** — 创建剧组、添加成员、设置组织架构与权限
- **拍摄计划** — 基于分镜自动生成拍摄日程安排
- **拍摄通告** — 一键生成并导出专业拍摄通告单

### 用户系统

- **多方式注册/登录** — 手机号注册（短信验证码）、邮箱注册、微信 OAuth 一键登录
- **三级会员体系** — 免费/入门版(29元/月)/专业版(59元/月)/尊享版(299元/月)
- **积分系统** — 1元=100积分，新用户赠10000积分，各功能消耗积分

### 支付系统

- **微信支付 APIv3** — JSAPI 支付、Native 支付、支付回调
- **充值/消费记录** — 完整的流水记录与订单管理

---

## 技术栈

| 层级 | 技术 | 说明 |
|------|------|------|
| **后端语言** | PHP 7.3+ | 无框架，原生面向对象 PHP |
| **数据库** | MySQL 5.7+ / SQLite 3 | PDO 抽象层，支持双数据库 |
| **前端** | Vanilla HTML/CSS/JS | 无前端框架，纯原生实现 |
| **AI 文本** | DeepSeek API | 兼容 OpenAI Chat Completions 格式 |
| **AI 图片** | 外部 Text-to-Image API | 配置化接入 |
| **AI 视频** | Doubao Seedance 1.5 Pro | 图生视频 |
| **支付** | 微信支付 APIv3 | RSA 签名 + AES-256-GCM 解密 |
| **短信** | 阿里云短信服务 | HMAC-SHA1 签名 |
| **HTTP 客户端** | PHP cURL | 所有外部 API 调用 |
| **认证** | PHP Session + bcrypt | password_hash / password_verify |
| **日志** | 文件日志 | Logger.php，分级记录 |

---

## 系统架构

```
┌─────────────────────────────────────────────────────────────────┐
│                         前端 (SPA)                               │
│   index.html  login.html  register.html                        │
│   js/*.js  (18 files, ~930KB)  css/*.css  (37 files, ~1MB)     │
└──────────────────────────┬──────────────────────────────────────┘
                           │  AJAX / Fetch
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PHP 路由层 (router.php)                      │
│   敏感文件保护 (.db/.log/.json)  URL 分发                        │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│                     PHP API 层 (action 分发)                     │
│                                                                  │
│  auth_api.php    novel_api.php    scripts_api.php                │
│  characters_api  spaces_api.php   storyboard_api.php             │
│  chat_api.php    video_api.php    text2video_api.php             │
│  img2text_api    schedule_api.php announcement_api.php           │
│  task_api.php    api/crew_api.php                                │
└──────────────────────────┬──────────────────────────────────────┘
                           │
                           ▼
┌───────────────────────────────────────────────────────────────┐
│                     核心业务类 (单例模式)                        │
│                                                               │
│  Config.php        — 配置中心 (API key, 定价, 路径)              │
│  Database.php      — PDO 数据库连接                             │
│         （定义：文本分析、文生图、图生视频、文生视频、图生视频接口）    │
│  Auth.php          — 用户认证/积分/会员                          │
│  TaskManager.php   — 任务/场次/分镜/计划 CRUD                    │
│  DeepSeekClient    — LLM API 调用（文本分析）                    │
│  VideoGenerator    — 任务队列管理                               │
│  Logger.php        — 文件日志                                   │
└──────────┬───────────────────────────┬─────────────────────────┘
           │                           │
           ▼                           ▼
┌───────────────────┐    ┌──────────────────────────┐
│   MySQL 数据库     │    │     外部 AI API 服务       │
│                   │    │                            │
│  users            │    │  DeepSeek (text-to-text)    │
│  tasks / scripts  │    │  Text-to-Image API         │
│  scenes / shots   │    │  Doubao Seedance (video)    │
│  schedules        │    │  Image-to-Text API         │
│  crew / crew_org  │    │  Alibaba Cloud SMS         │
│  points / balance │    │  WeChat Pay APIv3          │
└───────────────────┘    └──────────────────────────┘
```

**架构特点：**

- **单文件入口** — 每个功能页面直接请求对应的 `*_api.php` 文件
- **Action 分发** — API 通过 `$_GET['action']` 或 `$_POST['action']` 进行路由
- **单例模式** — `Database`、`TaskManager`、`VideoGenerator` 采用单例
- **无框架** — 纯 PHP，所有依赖通过 `require_once` 手动引入
- **JSON 通信** — 前后端完全通过 JSON 格式交互

---

## 目录结构

```
wop/
├── api/                             # 子 API 端点
│   ├── crew_api.php                 #   剧组管理完整 API (CRUD, 38KB)
│   ├── get_openid.php               #   微信 OpenID 获取
│   └── osimg.php                    #   图片处理 API (35KB)
│
├── assets/                          # 静态资源
│   ├── cool.webm                    #   首页背景视频 (8MB)
│   ├── logo.png / line.png          #   Logo 与装饰图
│   ├── katong.webp                  #   卡通素材
│   ├── scene-placeholder.png        #   场景占位图
│   ├── default-avatar.png           #   默认头像
│   └── fontawesome/                 #   Font Awesome 图标字体
│
├── css/                             # 样式表 (37 文件, ~1MB)
│   ├── style.css                    #   全局主样式
│   ├── novel_style.css              #   小说页样式
│   ├── cinema-showcase.css          #   影视展示页样式
│   ├── usercenter.css               #   用户中心样式
│   └── ...                          #   其他页面/组件专用样式
│
├── js/                              # JavaScript (18 文件, ~930KB)
│   ├── main.js                      #   主页脚本
│   ├── main_storyboards.js          #   故事板脚本
│   ├── auth.js                      #   认证相关脚本
│   ├── task_ui_components.js        #   任务 UI 组件
│   ├── text2img.js                  #   文生图前端
│   └── ...                          #   其他页面专用脚本
│
├── inc/                             # 内部库
│   ├── config.php                   #   桥接 Config 类到全局变量
│   ├── cache.php                    #   缓存工具
│   ├── edit.php                     #   编辑历史跟踪
│   ├── export.php                   #   PDF/Word/Excel 导出
│   ├── json_loader.php              #   JSON 数据加载器 (JsonLoader 类)
│   └── template_engine.php          #   模板引擎 (TemplateEngine 类, {{}} 占位符)
│
├── json/                            # JSON 数据文件
│   ├── announcement-data.json       #   拍摄通告演示数据
│   ├── casting-data.json            #   演员配置演示数据
│   ├── schedule-data.json           #   拍摄计划演示数据
│   ├── storyboard-data.json         #   故事板演示数据
│   └── tmp.json                     #   剧组相关演示数据
│
├── cron/                            # 定时任务
│   └── monthly_points.php           #   每月积分发放脚本
│
├── pay/                             # 支付相关
│   └── notify.php                   #   支付回调入口
│
├── wxpay/                           # 微信支付集成
│   ├── WxPay.php                    #   微信支付 SDK 封装
│   ├── config.php                   #   微信支付配置
│   ├── check.php                    #   支付状态查询
│   ├── notify.php                   #   支付回调处理
│   ├── qrcode.php                   #   扫码支付
│   └── success.php                  #   支付成功页
│
├── sql/                             # 数据库迁移
│   ├── full_migration_mysql.sql     #   完整 MySQL 建表脚本 (核心表)
│   ├── create_crew_tables.sql       #   剧组建表脚本
│   ├── membership_migration.sql     #   会员字段迁移
│   ├── demo_mysql.sql               #   演示数据
│   └── demo_reset_mysql.sql         #   演示数据重置
│
├── Core Classes (根目录)            # 核心业务类
│   ├── Config.php                   #   配置中心 (420 行)
│   ├── Database.php                 #   数据库连接单例 (支持 MySQL/SQLite)
│   ├── Auth.php                     #   用户认证与权限
│   ├── TaskManager.php              #   任务/场次/分镜统一管理
│   ├── DeepSeekClient.php           #   DeepSeek LLM HTTP 客户端
│   ├── VideoGenerator.php           #   视频生成任务队列
│   └── Logger.php                   #   文件日志工具
│
├── Frontend Pages                   # 前端页面
│   ├── index.html                   #   首页/落地页 (77KB, 1379 行)
│   ├── login.html                   #   登录页
│   ├── register.html                #   注册页
│   ├── header.html / footer.html    #   共享头部/尾部
│   └── f.html                       #   通告展示页
│
├── Domain Pages & APIs (根目录)     # 功能页面 + 对应 API
│   ├── novel.php / novel_api.php              # 小说转剧本
│   ├── scripts.php / scripts_api.php          # 剧本转分镜
│   ├── characters.php / characters_api.php    # AI 角色创作
│   ├── spaces.php / spaces_api.php            # 场景空间管理
│   ├── storyboards.php / storyboard_api.php   # 分镜管理
│   ├── storyboard-detail.php                  # 分镜详情
│   ├── gushiban.php                           # 故事板画廊
│   ├── schedule.php / schedule_api.php        # 拍摄计划
│   ├── announcement.php / announcement_api.php # 拍摄通告
│   ├── chat.php / chat_api.php                # AI 对话
│   ├── crew_management.php                    # 剧组管理
│   ├── usercenter.php                         # 用户中心
│   ├── text2img.php                           # 文生图
│   ├── img2video.php                          # 图生视频
│   ├── text2video.php / text2video_api.php    # 文生视频
│   └── img2text_api.php                       # 图生文
│
├── Payment Files                    # 支付相关
│   ├── pay.php / pay_standalone.php / payment_page.php
│   └── payment_config.json          #   业务配置 (产品/定价/主题/评价)
│
├── Background Processing            # 后台任务处理
│   ├── process_script_task.php / process_script_task_fixed.php
│   ├── process_characters.php / process_character_creation.php
│   ├── process_and_save_storyboards.php
│   └── execute_task.php             #   视频任务命令行执行器
│
├── WeChat OAuth                     # 微信登录
│   ├── wx_auth.php / wx_auth_callback.php
│   └── wx_pay_demo.php
│
├── Utilities                        # 工具文件
│   ├── router.php                   #   开发服务器路由/安全过滤
│   ├── auth_guard.php               #   认证守卫
│   ├── security_check.php           #   安全检查
│   ├── download.php                  #   文件下载
│   ├── export_announcement.php      #   通告导出
│   ├── export_schedule.php          #   计划导出
│   ├── get_api_key.php / save_api_key.php  # API Key 管理
│   ├── get_characters.php / get_genres.php / get_scene_info.php
│   ├── get_shot_data.php / get_space_scene.php / get_video_config.php
│   ├── generate_crew_json.php       #   剧组 JSON 生成
│   ├── save_image_url.php / save_split_images.php
│   ├── merge_videos.php             #   视频合并
│   ├── optimize_prompt.php          #   Prompt 优化
│   ├── fenge.php                    #   分割工具
│   ├── status.php                   #   状态查询
│   ├── migrate_membership_field.php #   会员迁移
│   ├── task_manager.php / schedule_class.php
│   └── text2img_no_proxy.php / text2img_proxy.php / text2img_no_proxy copy.php
│
├── outputs/                         # 输出目录
├── uploads/                         # 上传目录
├── logs/                            # 日志目录
├── cache/                           # 缓存目录
├── results/                         # 结果目录
├── edits/                           # 编辑历史目录
└── exports/                         # 导出目录
```

---

## 环境要求

| 依赖 | 版本 | 说明 |
|------|------|------|
| PHP | >= 7.3 | 支持 7.3+，推荐 8.0+ |
| MySQL | >= 5.7 | 生产环境数据库（支持 utf8mb4） |
| SQLite | 3.x | 开发环境可选替代 |
| Web Server | Nginx / Apache / PHP Built-in | 推荐 Nginx |

**PHP 扩展：**

| 扩展 | 用途 |
|------|------|
| `pdo_mysql` | MySQL 数据库连接 |
| `pdo_sqlite` | SQLite 数据库连接（开发环境） |
| `curl` | 所有外部 API 调用 |
| `mbstring` | 中文字符处理 |
| `openssl` | 微信支付加解密 / 密码哈希 |
| `gd` | 图片处理 |
| `json` | JSON 编解码 |

---
## 实际运行截图
<img width="1213" height="914" alt="截屏2026-06-01 22 42 49" src="https://github.com/user-attachments/assets/a86e19a3-e800-48e5-bfeb-010e85b5d063" />
<img width="1213" height="982" alt="截屏2026-06-01 22 43 53" src="https://github.com/user-attachments/assets/8e3626a9-bc66-466b-947b-132972d915e7" />
<img width="1213" height="836" alt="截屏2026-06-01 22 44 24" src="https://github.com/user-attachments/assets/9bd01548-f909-4892-a843-212e030713ce" />
<img width="1213" height="753" alt="截屏2026-06-01 22 45 32" src="https://github.com/user-attachments/assets/e719c6b7-0348-4cdd-a7fa-80974d338160" />
<img width="1213" height="982" alt="截屏2026-06-01 22 49 19" src="https://github.com/user-attachments/assets/805a99cf-ded5-4302-afef-de043a798e9f" />
<img width="1213" height="982" alt="截屏2026-06-01 22 50 17" src="https://github.com/user-attachments/assets/ce088852-21ef-4e9d-ac48-5248e39388f7" />
<img width="1213" height="982" alt="截屏2026-06-01 22 50 27" src="https://github.com/user-attachments/assets/8cdede15-92b6-4a6c-9cac-6d237e6561bb" />
## 安装部署

### 1. 部署文件

将项目文件复制到 Web 服务器根目录：

```bash
cp -r /path/to/wop /var/www/html/
```

### 2. 配置数据库

编辑 [Database.php](Database.php)，修改数据库连接常量：

```php
const DB_TYPE = 'mysql';        // 或 'sqlite'
const DB_HOST = '127.0.0.1';   // MySQL 主机
const DB_PORT = 3306;           // MySQL 端口
const DB_NAME = 'your_db_name'; // 数据库名
const DB_USER = 'your_db_user'; // 数据库用户
const DB_PASS = 'your_password'; // 数据库密码
```

### 3. 初始化数据库

按顺序执行 SQL 迁移文件：

```bash
mysql -u root -p your_db_name < sql/full_migration_mysql.sql
mysql -u root -p your_db_name < sql/create_crew_tables.sql
mysql -u root -p your_db_name < sql/membership_migration.sql
```

### 4. 配置 API 密钥

编辑 [config.php](config.php) 中的常量（详见[配置参考](#配置参考)），或通过应用界面在数据库 `api_keys` 表中配置。

### 5. 配置微信支付

编辑 [config.php](config.php) 中的微信支付常量，确保证书文件放置在正确的路径。参考 [wxpay/config.php](wxpay/config.php)。

### 6. 目录权限

```bash
chmod -R 755 uploads/ outputs/ logs/ cache/ results/ edits/ exports/
chmod 755 config.php
```

### 7. 配置 Web 服务器

**Nginx 配置示例：**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/wop;

    index index.html index.php;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # 保护敏感文件
    location ~ \.(db|log|json)$ {
        deny all;
    }
}
```

**开发环境快速启动：**

```bash
php -S 0.0.0.0:8000 router.php
```

> `router.php` 会阻止对 `.db`、`.log`、`.json` 文件的直接访问。

### 8. 访问

打开浏览器访问 `http://localhost:8000`，注册账号即可使用。

---

## 配置参考

配置文件：[config.php](config.php)（420 行，`Config` 类）。所有配置为类常量。

### AI API 配置

> API 密钥支持数据库动态配置（存储在 `api_keys` 表），每个用户可使用独立密钥。`Config` 类会按 `当前登录用户 → 用户等级2共享账号(ID 665588567) → 默认用户(ID 1)` 的优先级查找密钥。

| 常量/配置项 | 说明 | 默认值 |
|-------------|------|--------|
| `DEEPSEEK_API_KEY` | 文本生成 API 密钥 | 空（从数据库获取） |
| `DEEPSEEK_API_URL` | 文本生成 API 端点 | 空 |
| `DEEPSEEK_MODEL` | 文本生成模型 | 空 |
| `TEXT2IMG_API_KEY/URL/MODEL` | 文生图 API | 空 |
| `VIDEO_GENERATION_API_KEY/URL/MODEL` | 图生视频 API (Doubao Seedance) | 空 |
| `IMG2TEXT_API_KEY/URL/MODEL` | 图生文 API | 空 |
| `MAX_TOKENS` | 最大 Token 数 | 8000 |
| `TEMPERATURE` | LLM 温度 | 0.7 |

### 积分与定价

| 常量 | 说明 | 值 |
|------|------|-----|
| `RECHARGE_RATE` | 充值比例 | 100 (1元=100积分) |
| `DEFAULT_REGISTER_POINTS` | 新用户赠送积分 | 10000 |
| `NOVEL_TO_SCRIPT_COST` | 小说转剧本消耗 | 100 积分/轮次 |
| `SCRIPT_TO_STORYBOARD_COST` | 剧本转分镜消耗 | 100 积分/轮次 |
| `IMAGE_GENERATION_COST` | 图片生成消耗 | 20 积分/张 |
| `VIDEO_GENERATION_COST` | 视频生成消耗 | 300 积分/轮次 |
| `CHARACTER_CREATION_COST` | 角色创作消耗 | 100 积分/轮次 |

**会员定价 (`VIP_PRICES`)：**

| 类型 | 月度 | 年度 |
|------|------|------|
| 普通会员 | 29 元/月 | 299 元/年 |
| 高级会员 | 59 元/月 | 599 元/年 |
| 贵宾 | 299 元/月 | 2990 元/年 |

### 微信支付

| 常量 | 说明 |
|------|------|
| `WX_APPID` | 微信服务号 AppID |
| `WX_APPSECRET` | 微信服务号 AppSecret |
| `WX_MCH_ID` | 微信支付商户号 |
| `WX_KEY` | APIv3 密钥 |
| `WX_SERIAL_NO` | 证书序列号 |
| `WX_NOTIFY_URL` | 支付回调地址 |
| `WX_PRIVATE_KEY_PATH` | 商户私钥路径 (`pay_cert/apiclient_key.pem`) |
| `WX_CERT_PATH` | 商户证书路径 (`pay_cert/apiclient_cert.pem`) |

### 短信与邮件

| 常量 | 说明 |
|------|------|
| `ALIYUN_SMS_ACCESS_KEY_ID/SECRET` | 阿里云短信 AccessKey |
| `ALIYUN_SMS_SIGN_NAME` | 短信签名 |
| `ALIYUN_SMS_TEMPLATE_CODE` | 短信模板代码 |
| `EMAIL_SMTP_HOST/PORT` | SMTP 服务器 |
| `EMAIL_USERNAME/PASSWORD` | 邮箱账号 |
| `VERIFICATION_CODE_EXPIRE` | 验证码过期秒数 (180s) |

### 目录与缓存

| 常量 | 路径 |
|------|------|
| `UPLOAD_DIR` | `uploads/` |
| `OUTPUT_DIR` | `outputs/` |
| `LOG_DIR` | `logs/` |
| `CACHE_DIR` / `CACHE_PATH` | `cache/` |
| `RESULTS_PATH` | `results/` |
| `EDITS_PATH` | `edits/` |
| `EXPORTS_PATH` | `exports/` |
| `CACHE_ENABLED` | `true` |
| `CACHE_LIFETIME` | 3600 秒 |

---

## 数据库设计

数据库引擎：MySQL InnoDB, 字符集：utf8mb4

### 用户与认证

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `users` | 用户账户 | id, username, password(bcrypt), email, phone, status, level(0-3), membership_expire, openid |
| `sms_verifications` | 短信验证码 | phone, code, expired_at, used |
| `email_verifications` | 邮箱验证码 | email, code, expired_at, used |

### 用户中心

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `user_profiles` | 用户资料 | nickname, avatar, gender, birthday, bio |
| `user_balances` | 账户余额 | balance (DECIMAL) |
| `user_points` | 积分余额 | points (INT) |
| `recharge_records` | 充值记录 | amount, order_no, payment_method, status, paid_at |
| `consumption_records` | 消费记录 | amount, order_no, item_type, item_id, description |
| `points_history` | 积分流水 | points_change, reason, source, task_id |

### API 密钥

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `api_keys` | 用户 API 密钥 | user_id, text2text_api_key/url/model, text2img_api_key/url/model, img2video_api_key/url/model, img2text_api_key/url/model |

### 任务系统

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `tasks` | 任务主表 | user_id, task_type, title, status(0-4), progress(0-100), input_data(JSON), output_data(JSON), task_id(unique), current_status |
| `task_details` | 任务扩展字段 | task_id, key, value |
| `task_logs` | 任务日志 | task_id, status, message |

### 影视制作核心表

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `scripts` | 剧本 | task_id, content, title, author |
| `scenes` | 场次 | task_id, scene_id, scene_name, location, day_night, sort_order |
| `shots` | 分镜 | 32 个字段: task_id, crew_id, scenes_id, shotType, duration, cameraAngle, cameraMovement, cameraEquipment, lensFocalLength, compositionFocus, lightTone, location, time, weather, dialogue, characters, characterCostumes, characterMakeup, characterActions, props, imageUrl, videoCutUrl 等 |
| `schedules` | 拍摄计划 | task_id, name, shooting_date, shooting_location, scene_ids(JSON), crew_info(JSON) |
| `announcements` | 拍摄通告 | task_id, schedule_id, title, content(JSON), recipients(JSON) |

### 剧组协作

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `crew` | 剧组 | admin_user_id, name, description, current_task_id, film_name, estimated_days, completion_rate |
| `crew_organization` | 剧组人员 | crew_id, name, position, group, responsibilities, is_admin, can_modify_password, can_view/edit_resources |
| `crew_permissions` | 权限控制 | crew_id, member_id, resource_type, can_edit |
| `shared_resources` | 共享资源 | crew_id, resource_type, resource_id, title |

### 视频任务

| 表名 | 说明 | 关键字段 |
|------|------|---------|
| `video_tasks` | 视频生成任务 | task_id, user_id, shot_id, image_urls(JSON), prompt, prompts(JSON), duration, status, progress |

---

## API 参考

所有 API 通过独立的 PHP 文件暴露，路由方式为 `$_GET['action']` 或 `$_POST['action']` 分发。请求和响应均为 JSON 格式。

### 认证模块 — [auth_api.php](auth_api.php)

```
支持 25+ actions，包括：
  action=register           注册（用户名+手机+密码+验证码）
  action=login              密码登录
  action=loginWithSms       短信验证码登录
  action=loginWithOneClick  一键注册/登录
  action=sendSms            发送短信验证码
  action=sendEmailCode      发送邮件验证码
  action=checkLogin         检查登录状态
  action=getUserInfo        获取当前用户信息
  action=getUserCrewInfo    获取用户剧组信息
  action=setCurrentCrew     设置当前工作剧组
  action=leaveCrew          退出剧组
  action=getUserTasks       获取用户任务列表
  action=switchUserStatus   切换用户状态
  action=getPointsRecords   获取积分记录
  action=upgradeMembership  升级会员
  action=createRecharge     创建充值订单
  action=exchangeBalance    余额兑换积分
  ...
```

### 小说转剧本 — [novel_api.php](novel_api.php)

```
POST action=start_conversion    开始小说→剧本转换
POST action=analyze             分析文本内容
GET  action=check_status        查询转换状态
GET  action=read_file           读取转换结果
POST action=delete_task         删除任务
POST action=delete_all_tasks    删除全部任务
```

### 剧本转分镜 — [scripts_api.php](scripts_api.php)

```
POST action=start_conversion    开始剧本→分镜转换
GET  action=check_status        查询状态
GET  action=get_scenes          获取场次列表
GET  action=get_storyboards     获取分镜数据
```

### 角色创作 — [characters_api.php](characters_api.php)

```
POST action=generate_three_view  生成角色三视图
POST action=create_character     创建角色
POST action=edit_character       编辑角色
POST action=delete_character     删除角色
GET  action=get_characters       获取角色列表
```

### 场景空间 — [spaces_api.php](spaces_api.php)

```
POST action=edit_scene           编辑场景
POST action=delete_scene         删除场景
GET  action=get_scenes           获取场景列表
```

### 分镜管理 — [storyboard_api.php](storyboard_api.php)

```
GET ?task_id=xxx                 获取任务下所有分镜数据
GET ?task_id=xxx&scene_id=xxx    获取指定场次分镜
GET ?task_id=xxx&shot_id=xxx     获取单条分镜详情
```

### AI 对话 — [chat_api.php](chat_api.php)

```
POST { message, session_id }     发送消息，获取 AI 回复
```

### 视频生成

| 文件 | 说明 |
|------|------|
| [generate_video.php](generate_video.php) | 单视频生成：`POST { firstFrame, lastFrame, prompt, duration }` |
| [text2video_api.php](text2video_api.php) | 文生视频 API |
| [video_api.php](video_api.php) | 视频查询 API |
| [merge_videos.php](merge_videos.php) | 视频合并 |

### 图片生成

| 文件 | 说明 |
|------|------|
| [text2img.php](text2img.php) | 文生图页面 |
| [text2img_proxy.php](text2img_proxy.php) | 文生图代理方式 |
| [text2img_no_proxy.php](text2img_no_proxy.php) | 文生图直连方式 |

### 拍摄计划与通告

| 文件 | 说明 |
|------|------|
| [schedule_api.php](schedule_api.php) | 拍摄计划 CRUD |
| [announcement_api.php](announcement_api.php) | 拍摄通告生成与管理 |
| [export_schedule.php](export_schedule.php) | 拍摄计划导出 |
| [export_announcement.php](export_announcement.php) | 拍摄通告导出 |

### 剧组管理 — [api/crew_api.php](api/crew_api.php)

```
完整的剧组 CRUD API：
  action=create_crew           创建剧组
  action=get_crew              获取剧组信息
  action=update_crew           更新剧组
  action=delete_crew           删除剧组
  action=get_crew_members      获取成员列表
  action=add_crew_member       添加成员
  action=update_crew_member    更新成员
  action=delete_crew_member    删除成员
  action=get_shared_resources  获取共享资源
  ...
```

### 支付

| 文件 | 说明 |
|------|------|
| [pay.php](pay.php) | 支付逻辑 |
| [pay_standalone.php](pay_standalone.php) | 独立支付页 |
| [payment_page.php](payment_page.php) | 支付页面 |
| [notify.php](notify.php) | 微信支付回调处理 (APIv3 签名验证 + AES-256-GCM 解密) |
| [wxpay/check.php](wxpay/check.php) | 支付状态查询 |
| [wxpay/qrcode.php](wxpay/qrcode.php) | Native 扫码支付 |

---

## AI 制作流水线

完整的影视制作流程：

```
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  小说文本  │ →  │  剧本改编  │ →  │  场次拆解  │ →  │  分镜设计  │
│ (Novel)  │    │ (Script)  │    │ (Scenes)  │    │  (Shots)  │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
     │               │               │               │
     │ DeepSeek API  │ DeepSeek API  │ DeepSeek API  │ 32 字段/镜头
     │ 100 积分       │               │               │ 镜头描述、机位、
     │               │               │               │ 灯光、对白等
     │               │               │               │
     ▼               ▼               ▼               ▼
┌──────────┐    ┌──────────┐    ┌──────────┐    ┌──────────┐
│  角色创作  │    │  文生图    │    │  图生视频   │    │  视频合并  │
│(Character)│    │ (T2I)    │    │ (I2V)    │    │ (Merge)  │
└──────────┘    └──────────┘    └──────────┘    └──────────┘
     │               │               │               │
     │ 三视图生成     │ 生成分镜画面    │ Seedance 1.5  │ 合成完整片段
     │ 100 积分/角色  │ 20 积分/张     │ 300 积分/轮次   │
     │               │               │               │
     ▼               ▼               ▼               ▼
               ┌──────────────────────────┐
               │  剧组管理 → 拍摄计划 → 拍摄通告  │
               │  (Crew) → (Schedule) → (Notice) │
               └──────────────────────────┘
```

### 流程说明

1. **小说转剧本** — 用户上传小说文本 → DeepSeek API 分析内容 → 输出标准剧本格式。支持长文本分段处理 (`MAX_CHUNK_LENGTH=3000`, `MAX_TEXT_LENGTH=100000`)
2. **剧本转分镜** — 将剧本按场次拆分 → 每场生成多个分镜 → 每个分镜包含 32 个专业字段（镜头类型、机位、运镜、灯光、对白、服装等）
3. **角色创作** — AI 生成角色设定 + 三视图（正面/侧面/背面）
4. **文生图** — 分镜描述 → 图片生成。三种调用方式（直连/代理）
5. **图生视频** — 首帧 + 尾帧图片 → Doubao Seedance 1.5 Pro → 视频片段
6. **剧组管理** — 创建剧组 → 添加成员 → 设置权限 → 资源共享
7. **拍摄计划** — 基于分镜数据自动生成拍摄日程
8. **拍摄通告** — 一键生成专业通告单，支持导出

### 后台任务机制

AI 操作耗时较长，系统通过以下方式实现异步处理：

1. 前端发起请求 → API 创建任务（status=pending）
2. PHP 通过 `exec()` 在后台启动处理脚本（如 `process_script_task.php`）
3. 处理脚本调用 AI API → 结果写入数据库
4. 前端轮询 `check_status` → 任务完成 → 展示结果

CLI 模式示例：

```bash
php execute_task.php <taskId> [userId]
```

---

## 支付系统

### 微信支付 APIv3

系统集成微信支付 APIv3，支持：

- **JSAPI 支付** — 微信内网页支付
- **Native 支付** — PC 端扫码支付
- **支付回调** — 异步通知处理（签名验证 + AES-256-GCM 解密）

核心文件：

- [wxpay/WxPay.php](wxpay/WxPay.php) — 支付 SDK 封装
- [notify.php](notify.php) — 回调处理入口
- [payment_config.json](payment_config.json) — 业务配置

### 积分体系

```
1 元 = 100 积分

消费规则：
  小说转剧本    100 积分/轮次
  剧本转分镜    100 积分/轮次
  图片生成      20 积分/张
  视频生成      300 积分/轮次
  角色创作      100 积分/轮次

会员月积分发放：
  普通会员     6000 积分/月 + 500 赠送
  高级会员     20000 积分/月 + 2000 赠送
  贵宾        150000 积分/月 + 10000 赠送
```

### 会员等级

| 等级 | 名称 | 月度价格 | 年度价格 |
|------|------|---------|---------|
| 0 | 免费用户 | — | — |
| 1 | 入门版 | 29 元 | 299 元 |
| 2 | 专业版 | 59 元 | 599 元 |
| 3 | 尊享版 | 299 元 | 2990 元 |

等级 2 用户共享管理员 API 密钥池 (user_id=665588567)。

---

## 定时任务

### 每月积分发放 — [cron/monthly_points.php](cron/monthly_points.php)

```bash
# 建议在 crontab 中设置每月 1 日执行
0 0 1 * * php /path/to/wop/cron/monthly_points.php
```

行为：

- 遍历所有有效会员用户
- 根据会员等级和计划类型发放对应积分
- 免费用户赠送 500 基础积分
- 写入 `points_history` 表记录

---

## 后台任务处理

系统使用 PHP `exec()` 实现简单的异步任务处理：

```
用户请求 → API 创建任务(pending) → exec() 启动后台脚本 → 轮询状态 → 展示结果
```

**后台处理脚本：**

| 脚本 | 处理内容 |
|------|---------|
| [process_script_task.php](process_script_task.php) | 剧本转场次/分镜 |
| [process_script_task_fixed.php](process_script_task_fixed.php) | 剧本处理（修复版） |
| [process_characters.php](process_characters.php) | 角色生成 |
| [process_character_creation.php](process_character_creation.php) | 角色创作 |
| [process_and_save_storyboards.php](process_and_save_storyboards.php) | 分镜保存 |
| [execute_task.php](execute_task.php) | 视频生成任务执行器 |

`execute_task.php` 为 CLI 模式运行，支持传入 `$argv` 参数指定 task_id 和 user_id。它调用视频生成 API 创建任务、轮询完成状态、下载视频结果。

---

## 安全说明

### 已实现的安全措施

- **密码存储** — 使用 PHP `password_hash()` (bcrypt) 加密
- **SQL 注入防护** — 全部使用 PDO 预处理语句 (`prepare/execute`)
- **敏感文件保护** — `router.php` 阻止 `.db`、`.log`、`.json` 文件直接访问
- **会话管理** — 基于 PHP Session，支持过期时间控制 (`SESSION_EXPIRE=3600`)
- **支付安全** — 微信支付 APIv3 使用 RSA 签名验证和 AES-256-GCM 解密
- **生产环境配置** — `display_errors=0`，错误写入日志文件

### 已知安全待完善项

- **CSRF 保护** — 当前 API 端点无 CSRF Token 机制
- **速率限制** — 登录/注册/短信发送无频率限制
- **输入验证** — 缺少统一的输入校验框架，各端点自行处理
- **XSS 防护** — JSON API 响应天然免疫，但 HTML 页面输出需注意
- **文件上传** — 上传目录无严格的类型/大小限制
- **HTTPS** — 微信支付回调强制 HTTPS，但建议全局启用

---

## 开发说明

### 本地开发

```bash
# 启动开发服务器
php -S 0.0.0.0:8000 router.php

# 使用 SQLite 进行本地开发
# 编辑 Database.php，设置 DB_TYPE = 'sqlite'
```

### 调试

```bash
# 查看 PHP 错误日志
tail -f logs/php_errors.log

# 查看支付日志
tail -f logs/pay.log

# 开启调试模式
# 编辑 config.php，设置 DEBUG = true
```

### 代码组织约定

- **页面文件** (`xxx.php`) — 包含 HTML 页面代码，浏览器直接访问
- **API 文件** (`xxx_api.php`) — JSON 端点，返回 JSON 数据
- **处理脚本** (`process_xxx.php`) — 后台任务，`exec()` 调用或 CLI 运行
- **核心类** — 根目录首字母大写 `.php` 文件，如 `Auth.php`、`Config.php`
- **子 API** — 放在 `api/` 目录，补充大型功能模块

### 外部依赖

本项目无 Composer/Package 依赖。所有第三方集成（微信支付 SDK、短信 SDK、AI API 客户端）均为手写 PHP 实现。

### 业务配置

产品定价、主题色、评价、FAQ 等业务数据集中在 [payment_config.json](payment_config.json) 中管理。

---

## License

MIT License

---

**智影工场** — 让每个人都能成为导演。
