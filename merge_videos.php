<?php

/**
 * 视频合并API接口
 * 用于合并多个视频片段为一个完整视频
 */

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入必要的文件
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';

// 初始化认证
$auth = new Auth();

// 开发模式：允许跳过登录验证（仅用于测试）
$devMode = false; // 设置为false禁用开发模式

if ($devMode) {
    // 开发模式下使用默认用户ID
    $userId = 1;
} else {
    // 检查用户是否登录
    $user = $auth->checkLogin();
    if (!$user['success']) {
        echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
        exit(0);
    }

    $userId = $user['data']['id'];
}

// 处理JSON请求数据
$postData = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
    $rawData = file_get_contents('php://input');
    $postData = json_decode($rawData, true) ?: [];
}

// 验证参数
$shotId = $postData['shot_id'] ?? '';
$videoUrls = $postData['video_urls'] ?? [];

if (empty($shotId)) {
    echo json_encode([
        'code' => 1,
        'msg' => '缺少分镜ID'
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

if (empty($videoUrls) || !is_array($videoUrls) || count($videoUrls) < 2) {
    echo json_encode([
        'code' => 1,
        'msg' => '至少需要2个视频片段才能合并'
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 初始化数据库连接
$pdo = null;
try {
    // 引入Database类
    require_once __DIR__ . '/Database.php';

    // 使用Database类获取PDO实例
    $db = Database::getInstance();
    $pdo = $db->getPdo();
} catch (Exception $e) {
    echo json_encode([
        'code' => 1,
        'msg' => '数据库连接失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 定义ffmpeg路径和输出目录
$ffmpegPath = '/www/server/ffmpeg/ffmpeg-6.1/ffmpeg';
$outputDir = __DIR__ . '/outputs/videos';
$tempDir = __DIR__ . '/outputs/temp';

// 确保目录存在
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0755, true);
}
if (!is_dir($tempDir)) {
    mkdir($tempDir, 0755, true);
}

// 生成唯一的文件名
$timestamp = time();
$randomStr = substr(md5(uniqid()), 0, 8);
$outputFilename = "merged_{$shotId}_{$timestamp}_{$randomStr}.mp4";
$outputPath = $outputDir . '/' . $outputFilename;

// 下载视频文件到临时目录
$tempFiles = [];
try {
    foreach ($videoUrls as $index => $videoUrl) {
        $tempFilename = "temp_{$index}_{$timestamp}.mp4";
        $tempFilePath = $tempDir . '/' . $tempFilename;

        // 下载视频文件
        $videoContent = file_get_contents($videoUrl);
        if ($videoContent === false) {
            throw new Exception("无法下载视频文件: $videoUrl");
        }

        // 保存到临时文件
        if (file_put_contents($tempFilePath, $videoContent) === false) {
            throw new Exception("无法保存临时文件: $tempFilePath");
        }

        $tempFiles[] = $tempFilePath;
    }
} catch (Exception $e) {
    // 清理临时文件
    foreach ($tempFiles as $tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }

    echo json_encode([
        'code' => 1,
        'msg' => '下载视频文件失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 创建ffmpeg命令
$concatListFile = $tempDir . "/concat_list_{$timestamp}.txt";
$concatListContent = '';
foreach ($tempFiles as $tempFile) {
    $concatListContent .= "file '$tempFile'\n";
}
file_put_contents($concatListFile, $concatListContent);

// 构建ffmpeg命令
$ffmpegCommand = escapeshellcmd($ffmpegPath) . " -f concat -safe 0 -i " . escapeshellarg($concatListFile) . " -c copy " . escapeshellarg($outputPath);

// 执行ffmpeg命令
try {
    $output = shell_exec($ffmpegCommand . ' 2>&1');

    // 检查命令执行结果
    if (!file_exists($outputPath)) {
        throw new Exception("ffmpeg执行失败: $output");
    }
} catch (Exception $e) {
    // 清理临时文件
    foreach ($tempFiles as $tempFile) {
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
    }
    if (file_exists($concatListFile)) {
        unlink($concatListFile);
    }

    echo json_encode([
        'code' => 1,
        'msg' => '合并视频失败: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 清理临时文件
foreach ($tempFiles as $tempFile) {
    if (file_exists($tempFile)) {
        unlink($tempFile);
    }
}
if (file_exists($concatListFile)) {
    unlink($concatListFile);
}

// 生成视频URL
$videoUrl = "https://files.yourdomain.com/videos/{$outputFilename}";

// 更新shots表的videoCutUrl字段
try {
    $sql = "UPDATE shots SET videoCutUrl = ? WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$videoUrl, $shotId]);
} catch (Exception $e) {
    // 即使数据库更新失败，视频文件已经生成，仍然返回成功
    error_log("更新数据库失败: " . $e->getMessage());
}

// 返回成功响应
echo json_encode([
    'code' => 0,
    'msg' => 'Success',
    'data' => [
        'video_url' => $videoUrl,
        'filename' => $outputFilename
    ]
], JSON_UNESCAPED_UNICODE);
