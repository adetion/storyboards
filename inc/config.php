<?php

// 加载主配置文件，统一配置管理
require_once __DIR__ . '/../config.php';

// 为保持向后兼容性，将Config类的配置转换为$GLOBALS['config']格式
$GLOBALS['config'] = [
    // 目录配置
    'root_path' => Config::ROOT_PATH,
    'inc_path' => Config::INC_PATH,
    'template_path' => Config::TEMPLATE_PATH,
    'css_path' => Config::CSS_PATH,
    'js_path' => Config::JS_PATH,
    'json_path' => Config::JSON_PATH,
    'results_path' => Config::RESULTS_PATH,
    'cache_path' => Config::CACHE_PATH,
    'edits_path' => Config::EDITS_PATH,
    'exports_path' => Config::EXPORTS_PATH,
    'sql_path' => Config::SQL_PATH,
    'assets_path' => Config::ASSETS_PATH,
    
    // 缓存配置
    'cache_enabled' => Config::CACHE_ENABLED,
    'cache_lifetime' => Config::CACHE_LIFETIME,
    'cache_prefix' => Config::CACHE_PREFIX,
    
    // 导出配置
    'export_enabled' => Config::EXPORT_ENABLED,
    'export_formats' => Config::EXPORT_FORMATS,
    'export_charset' => Config::EXPORT_CHARSET,
    
    // 编辑配置
    'edit_enabled' => Config::EDIT_ENABLED,
    'max_edit_history' => Config::MAX_EDIT_HISTORY,
    
    // 数据库配置
    'db' => [
        'driver' => Config::DB_TYPE,
        'host' => Config::DB_HOST,
        'port' => Config::DB_PORT,
        'database' => Config::DB_NAME,
        'username' => Config::DB_USER,
        'password' => Config::DB_PASS,
        'charset' => 'utf8mb4',
        'collation' => 'utf8mb4_unicode_ci',
        'prefix' => '',
    ],
    
    // 模板配置
    'template_extension' => Config::TEMPLATE_EXTENSION,
    'template_delimiters' => Config::TEMPLATE_DELIMITERS,
    
    // JSON配置
    'json_indent' => Config::JSON_INDENT,
    'json_pretty_print' => Config::JSON_PRETTY_PRINT,
    
    // 应用配置
    'app_name' => Config::APP_NAME,
    'app_version' => Config::APP_VERSION,
    'debug' => Config::DEBUG,
];

// 目录创建逻辑已移至Config::init()方法中，此处不再重复处理
