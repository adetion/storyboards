<?php
// Database.php - 数据库连接类，支持SQLite和MySQL

// 数据库配置，使用远程mysql服务器
const DB_TYPE = 'mysql'; // 数据库类型：sqlite或mysql

const DB_HOST = '127.0.0.1'; // 数据库主机
const DB_PORT = 3306;
const DB_NAME = 'yourdbname'; // 数据库名称
const DB_USER = 'yourdbuser'; // 数据库用户名
const DB_PASS = 'dbpwd'; // 数据库密码
const DB_FILE = __DIR__ . '/data.db'; // SQLite数据库文件路径 - 放在storyboarding-app目录下

class Database
{
    // 单例实例
    private static $instance = null;
    // PDO连接实例
    private $pdo = null;

    // 私有构造方法，防止外部实例化
    private function __construct()
    {
        $this->connect();
    }

    // 获取单例实例
    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // 连接数据库
    private function connect()
    {
        try {
            // 从Config类获取数据库配置（生产环境使用MySQL）
            $dbType = DB_TYPE;
            // error_log("Database.php - 数据库类型: $dbType");

            if ($dbType === 'mysql') {
                // MySQL连接（生产环境）
                $dbHost = DB_HOST;
                $dbPort = DB_PORT;
                $dbName = DB_NAME;
                $dbUser = DB_USER;
                $dbPass = DB_PASS;

                // error_log("Database.php - 连接到MySQL数据库: $dbHost:$dbPort/$dbName");
                $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
                $this->pdo = new PDO($dsn, $dbUser, $dbPass);
                // error_log("Database.php - MySQL连接成功");
            } else {
                // SQLite连接（开发环境备用）
                $dbFile = DB_FILE;
                // error_log("Database.php - 连接到SQLite数据库文件: $dbFile");
                // 确保目录存在
                $dbDir = dirname($dbFile);
                if (!is_dir($dbDir)) {
                    // error_log("Database.php - 创建数据库目录: $dbDir");
                    mkdir($dbDir, 0755, true);
                }
                $dsn = "sqlite:$dbFile";
                $this->pdo = new PDO($dsn);
                // error_log("Database.php - SQLite连接成功");
                // $this->pdo->exec("PRAGMA foreign_keys = ON;"); // 启用外键约束
            }

            // 设置PDO属性
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // 禁用预处理语句模拟
            $this->pdo->exec("SET NAMES utf8mb4"); // 设置字符集
            $this->pdo->exec("SET CHARACTER SET utf8mb4"); // 设置字符集
            $this->pdo->exec("SET collation_connection = utf8mb4_unicode_ci"); // 设置排序规则

            // error_log("Database.php - PDO属性设置完成");

        } catch (PDOException $e) {
            // error_log("Database.php - 数据库连接失败: " . $e->getMessage());
            throw new Exception("数据库连接失败: " . $e->getMessage());
        }
    }


    // 获取PDO实例
    public function getPdo()
    {
        return $this->pdo;
    }

    // 查询（返回所有结果）
    public function query($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);

        // 直接执行，不转换参数类型
        // 只有在特定情况下（如LIMIT和OFFSET）才需要转换为int类型
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    // 查询（返回单行结果）
    public function queryOne($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);

        // 直接执行，不转换参数类型
        // 只有在特定情况下（如LIMIT和OFFSET）才需要转换为int类型
        $stmt->execute($params);
        return $stmt->fetch();
    }

    // 执行（INSERT、UPDATE、DELETE等）
    public function execute($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    // 插入数据并返回自增ID
    public function insert($sql, $params = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->pdo->lastInsertId();
    }

    // 开启事务
    public function beginTransaction()
    {
        return $this->pdo->beginTransaction();
    }

    // 提交事务
    public function commit()
    {
        return $this->pdo->commit();
    }

    // 回滚事务
    public function rollBack()
    {
        return $this->pdo->rollBack();
    }

    // 获取错误信息
    public function errorInfo()
    {
        return $this->pdo->errorInfo();
    }

    // 获取最后插入的ID
    public function lastInsertId()
    {
        return $this->pdo->lastInsertId();
    }
}
