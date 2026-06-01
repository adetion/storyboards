<?php
// 引入数据库文件
require_once __DIR__ . '/Database.php';

class Config
{
    // 静态属性存储API配置
    private static $apiConfig = null;

    // API参数配置
    const MAX_TOKENS = 8000;
    const TEMPERATURE = 0.7;

    // 从数据库获取API配置
    private static function getApiConfig()
    {
        // 如果已经获取过，直接返回
        if (self::$apiConfig !== null) {
            return self::$apiConfig;
        }

        // 默认配置
        $defaultConfig = [
            'deepseek_api_key' => '',
            'deepseek_api_url' => '',
            'deepseek_model' => '',
            'text2img_api_url' => '',
            'text2img_api_key' => '',
            'text2img_api_model' => '',
            'video_generation_api_url' => '',
            'video_generation_task_api_url' => '',
            'video_generation_api_key' => '',
            'video_generation_api_model' => '',
            'img2text_api_url' => '',
            'img2text_api_key' => '',
            'img2text_api_model' => ''
        ];

        try {
            // 获取当前登录用户ID
            $userId = null;
            // 检查是否是后台任务处理（命令行模式）
            $isCliMode = php_sapi_name() === 'cli';

            // 只有在非命令行模式下才尝试启动会话
            if (!$isCliMode && session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            if (!$isCliMode && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            } elseif ($isCliMode) {
                // 命令行模式下，尝试从全局变量获取用户ID
                global $argv;
                if (isset($argv[2])) {
                    $userId = $argv[2];
                } else {
                    // 默认使用用户ID 1
                    $userId = 1;
                }
            }

            // 获取用户等级（从数据库获取，假设等级存储在users表的level字段）
            $level = 1; // 默认等级

            // 如果有用户ID，从数据库获取用户等级
            if ($userId) {
                $db = Database::getInstance();
                $userSql = "SELECT level FROM users WHERE id = ? LIMIT 1";
                $userResult = $db->queryOne($userSql, [$userId]);
                if ($userResult && isset($userResult['level'])) {
                    $level = $userResult['level'];
                }
            }

            // 当用户等级为2时，使用指定的用户ID获取API配置
            $apiUserId = $userId;
            if ($level === 2) {
                $apiUserId = 665588567;
            }

            // 如果有用户ID，则从数据库获取配置
            if ($apiUserId) {
                $db = Database::getInstance();
                $sql = "SELECT 
                            text2text_api_key as deepseek_api_key, 
                            text2text_api_url as deepseek_api_url,
                            text2text_api_model as deepseek_model,
                            text2img_api_key,
                            text2img_api_url,
                            text2img_api_model,
                            img2video_api_key as video_generation_api_key,
                            img2video_api_url as video_generation_api_url,
                            img2video_task_api_url as video_generation_task_api_url,
                            img2video_api_model as video_generation_api_model,
                            img2text_api_key,
                            img2text_api_url,
                            img2text_api_model
                        FROM api_keys 
                        WHERE user_id = ? LIMIT 1";
                $result = $db->queryOne($sql, [$apiUserId]);

                // 如果获取到配置，则使用数据库中的配置
                if ($result) {
                    // 只覆盖非空值
                    foreach ($result as $key => $value) {
                        if ($value !== null && $value !== '') {
                            $defaultConfig[$key] = $value;
                        }
                    }
                } else {
                    // 如果没有找到用户的API配置，尝试使用默认用户ID 1的配置
                    $defaultUserId = 1;
                    $defaultResult = $db->queryOne($sql, [$defaultUserId]);
                    if ($defaultResult) {
                        // 只覆盖非空值
                        foreach ($defaultResult as $key => $value) {
                            if ($value !== null && $value !== '') {
                                $defaultConfig[$key] = $value;
                            }
                        }
                    }
                }
            } else {
                // 如果没有用户ID，尝试使用默认用户ID 1的配置
                try {
                    $db = Database::getInstance();
                    $defaultUserId = 1;
                    $sql = "SELECT 
                                text2text_api_key as deepseek_api_key, 
                                text2text_api_url as deepseek_api_url,
                                text2text_api_model as deepseek_model,
                                text2img_api_key,
                                text2img_api_url,
                                text2img_api_model,
                                img2video_api_key as video_generation_api_key,
                                img2video_api_url as video_generation_api_url,
                                img2video_task_api_url as video_generation_task_api_url,
                                img2video_api_model as video_generation_api_model,
                                img2text_api_key,
                                img2text_api_url,
                                img2text_api_model
                            FROM api_keys 
                            WHERE user_id = ? LIMIT 1";
                    $defaultResult = $db->queryOne($sql, [$defaultUserId]);
                    if ($defaultResult) {
                        // 只覆盖非空值
                        foreach ($defaultResult as $key => $value) {
                            if ($value !== null && $value !== '') {
                                $defaultConfig[$key] = $value;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // 忽略数据库错误，继续使用默认配置
                }
            }

            // 缓存配置
            self::$apiConfig = $defaultConfig;
        } catch (Exception $e) {
            // 如果发生错误，使用默认配置
            error_log('获取API配置失败: ' . $e->getMessage());
            self::$apiConfig = $defaultConfig;
        }

        return self::$apiConfig;
    }

    // 动态获取API配置的静态方法
    public static function DEEPSEEK_API_KEY()
    {
        $config = self::getApiConfig();
        return $config['deepseek_api_key'];
    }

    public static function DEEPSEEK_API_URL()
    {
        $config = self::getApiConfig();
        return $config['deepseek_api_url'];
    }

    public static function DEEPSEEK_MODEL()
    {
        $config = self::getApiConfig();
        return $config['deepseek_model'];
    }

    public static function TEXT2IMG_API_URL()
    {
        $config = self::getApiConfig();
        return $config['text2img_api_url'];
    }

    public static function TEXT2IMG_API_KEY()
    {
        $config = self::getApiConfig();
        return $config['text2img_api_key'];
    }

    public static function TEXT2IMG_API_MODEL()
    {
        $config = self::getApiConfig();
        return $config['text2img_api_model'];
    }

    public static function VIDEO_GENERATION_API_URL()
    {
        $config = self::getApiConfig();
        return $config['video_generation_api_url'];
    }

    public static function VIDEO_GENERATION_TASK_API_URL()
    {
        $config = self::getApiConfig();
        return $config['video_generation_task_api_url'];
    }

    public static function VIDEO_GENERATION_API_KEY()
    {
        $config = self::getApiConfig();
        return $config['video_generation_api_key'];
    }

    public static function VIDEO_GENERATION_MODEL()
    {
        $config = self::getApiConfig();
        return $config['video_generation_api_model'];
    }

    public static function IMG2TEXT_API_URL()
    {
        $config = self::getApiConfig();
        return $config['img2text_api_url'];
    }

    public static function IMG2TEXT_API_KEY()
    {
        $config = self::getApiConfig();
        return $config['img2text_api_key'];
    }

    public static function IMG2TEXT_API_MODEL()
    {
        $config = self::getApiConfig();
        return $config['img2text_api_model'];
    }



    // 处理限制
    const MAX_CHUNK_LENGTH = 3000;
    const MAX_SCENES_PER_REQUEST = 5;

    // 文件路径配置
    const UPLOAD_DIR = __DIR__ . '/uploads/';
    const OUTPUT_DIR = __DIR__ . '/outputs/';
    const LOG_DIR = __DIR__ . '/logs/';
    const CACHE_DIR = __DIR__ . '/cache/';

    // 分析参数
    const ANALYSIS_SAMPLE_LENGTH = 3000;
    const MIN_TEXT_LENGTH = 100;
    const MAX_TEXT_LENGTH = 100000;




    // 验证码配置
    const SMS_API_KEY = ''; // 短信API密钥
    const EMAIL_SMTP_HOST = 'smtp.example.com'; // 邮箱SMTP主机
    const EMAIL_SMTP_PORT = 465; // 邮箱SMTP端口
    const EMAIL_USERNAME = 'noreply@example.com'; // 邮箱用户名
    const EMAIL_PASSWORD = ''; // 邮箱密码
    const EMAIL_FROM = 'noreply@example.com'; // 发件人邮箱
    const VERIFICATION_CODE_EXPIRE = 180; // 验证码过期时间（秒） - 3分钟
    const VERIFICATION_CODE_LENGTH = 6; // 验证码长度

    // 阿里云短信配置
    const ALIYUN_SMS_ACCESS_KEY_ID = '';
    const ALIYUN_SMS_ACCESS_KEY_SECRET = '';
    const ALIYUN_SMS_SIGN_NAME = '';
    const ALIYUN_SMS_TEMPLATE_CODE = '';
    const ALIYUN_SMS_ENDPOINT = 'dysmsapi.aliyuncs.com';
    const ALIYUN_SMS_REGION_ID = 'cn-hangzhou';

    // 认证配置
    const SESSION_EXPIRE = 3600; // 会话过期时间（秒）

    // 充值规则
    const RECHARGE_RATE = 100; // 1元 = 100积分
    const CURRENCY_UNIT = '元'; // 金额单位
    const POINTS_UNIT = '积分'; // 积分单位
    const DEFAULT_REGISTER_POINTS = 10000; // 新用户注册默认赠送积分

    // 会员价格配置（键格式：type_level，1=月度，2=年度；1=普通，2=高级，3=贵宾/定制）
    const VIP_PRICES = [
        '1_1' => 29,     // 月度-普通会员：29元/月
        '1_2' => 59,     // 月度-高级会员：59元/月
        '1_3' => 299,    // 月度-贵宾：299元/月
        '2_1' => 299,    // 年度-普通会员：299元/年
        '2_2' => 599,    // 年度-高级会员：599元/年
        '2_3' => 2990    // 年度-定制会员：2990元/年
    ];

    // 会员积分配置（键格式：type_level，1=月度，2=年度；1=普通，2=高级，3=贵宾/定制）
    const VIP_POINTS = [
        '1_1' => ['base' => 6000, 'bonus' => 500],      // 月度-普通会员：6000积分/月，赠500积分/月
        '1_2' => ['base' => 20000, 'bonus' => 2000],   // 月度-高级会员：20000积分/月，赠2000积分/月
        '1_3' => ['base' => 150000, 'bonus' => 10000],  // 月度-贵宾：150000积分/月，赠10000积分/月
        '2_1' => ['base' => 6000, 'bonus' => 500],      // 年度-普通会员：6000积分/月，赠500积分/月
        '2_2' => ['base' => 20000, 'bonus' => 2000],   // 年度-高级会员：20000积分/月，赠2000积分/月
        '2_3' => ['base' => 150000, 'bonus' => 10000]   // 年度-定制会员：150000积分/月，赠10000积分/月
    ];

    // 功能积分消耗规则
    const NOVEL_TO_SCRIPT_COST = 100; // 小说转剧本每轮次消耗积分
    const SCRIPT_TO_STORYBOARD_COST = 100; // 剧本转分镜每轮次消耗积分
    const IMAGE_GENERATION_COST = 20; // 文生图每生成一张图消耗积分
    const VIDEO_GENERATION_COST = 300; // 视频生成每轮次消耗积分
    const CHARACTER_CREATION_COST = 100; // 角色创作每轮次消耗积分
    const CHARACTER_MAX_TEXT_LENGTH = 300000; // 角色创作最大文本长度

    // 微信支付配置

    const WX_APPID = '';           // 微信服务号AppID
    const WX_APPSECRET = '';     // 微信服务号AppSecret，需要替换为真实密钥
    const WX_MCH_ID = '';                  // 微信支付商户号
    const WX_KEY = ''; // APIv2/v3密钥  
    const WX_SERIAL_NO = ''; // 证书序列号
    const WX_API_URL = 'https://api.mch.weixin.qq.com';
    const WX_NOTIFY_URL = 'https://yourdomain.com/wxpay/notify.php'; // 支付结果回调地址
    const WX_PRIVATE_KEY_PATH = __DIR__ . '/pay_cert/apiclient_key.pem'; // 私钥文件路径
    const WX_CERT_PATH = __DIR__ . '/pay_cert/apiclient_cert.pem'; // 证书文件路径
    const WX_ROOT_CA_PATH = __DIR__ . '/pay_cert/apiclient_cert.p12'; // 根证书路径
    const WX_CERTIFICATE = <<<CERT
    -----BEGIN CERTIFICATE-----
    xxxxxx==
    -----END CERTIFICATE-----
    CERT;
    const WX_LOG_ENABLED = true;                     // 是否启用日志
    const WX_LOG_PATH = __DIR__ . '/logs/pay.log';   // 日志文件路径
    const WX_DEBUG_MODE = false;                     // 调试模式开关
    const WX_MAX_AMOUNT = 5000000;                   // 单笔最大金额（分）50000元
    const WX_MIN_AMOUNT = 100;                       // 单笔最小金额（分）1元


    // 目录配置
    const ROOT_PATH = __DIR__ . '/';
    const INC_PATH = __DIR__ . '/inc/';
    const TEMPLATE_PATH = __DIR__ . '/';
    const CSS_PATH = __DIR__ . '/css/';
    const JS_PATH = __DIR__ . '/js/';
    const JSON_PATH = __DIR__ . '/json/';
    const RESULTS_PATH = __DIR__ . '/results/';
    const CACHE_PATH = __DIR__ . '/cache/';
    const EDITS_PATH = __DIR__ . '/edits/';
    const EXPORTS_PATH = __DIR__ . '/exports/';
    const SQL_PATH = __DIR__ . '/sql/';
    const ASSETS_PATH = __DIR__ . '/assets/';

    // 缓存配置
    const CACHE_ENABLED = true;
    const CACHE_LIFETIME = 3600; // 缓存有效期（秒）
    const CACHE_PREFIX = 'announcement_';

    // 导出配置
    const EXPORT_ENABLED = true;
    const EXPORT_FORMATS = ['pdf', 'word', 'excel'];
    const EXPORT_CHARSET = 'UTF-8';

    // 编辑配置
    const EDIT_ENABLED = true;
    const MAX_EDIT_HISTORY = 50; // 最大编辑历史记录数

    // 模板配置
    const TEMPLATE_EXTENSION = '.html';
    const TEMPLATE_DELIMITERS = ['left' => '{{', 'right' => '}}'];

    // JSON配置
    const JSON_INDENT = 2;
    const JSON_PRETTY_PRINT = true;

    // 应用配置
    const APP_NAME = '智影工场 - 拍摄通告系统';
    const APP_VERSION = '1.0.0';
    const DEBUG = false;

    public static function init()
    {
        // 创建必要目录
        $dirs = [
            self::UPLOAD_DIR,
            self::OUTPUT_DIR,
            self::LOG_DIR,
            self::CACHE_DIR,
            self::RESULTS_PATH,
            self::EDITS_PATH,
            self::EXPORTS_PATH,
            self::JSON_PATH,
            self::SQL_PATH
        ];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }

        // 错误报告设置 - 生产环境配置
        error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);
        ini_set('display_errors', 0); // 生产环境关闭错误显示
        ini_set('log_errors', 1); // 开启错误日志
        ini_set('error_log', self::LOG_DIR . 'php_errors.log');
        ini_set('log_errors_max_len', 1024000); // 设置错误日志最大长度为1MB
    }
}

// 初始化配置
Config::init();
