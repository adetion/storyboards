<?php
// 引入配置文件
require_once __DIR__ . '/config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到首页
    header('Location: index.html');
    exit(0);
}

$taskId = $_GET['task_id'] ?? null;
$action = $_GET['action'] ?? null;

// 处理生成拍摄通告的API请求
if ($action === 'generate_announcement') {
    // 设置错误报告，捕获所有错误
    // error_reporting(E_ALL);
    // ini_set('display_errors', 0);

    try {
        $result = checkAndGenerateAnnouncementSimple($taskId);
        header('Content-Type: application/json');
        echo json_encode(['success' => $result, 'message' => $result ? '拍摄通告生成成功' : '拍摄通告生成失败']);
    } catch (Exception $e) {
        // error_log("generate_announcement error: " . $e->getMessage() . " Stack trace: " . $e->getTraceAsString());
        header('Content-Type: application/json');
        //echo json_encode(['success' => false, 'error' => '生成拍摄通告时发生异常', 'details' => $e->getMessage()]);
    } catch (Error $err) {
        // error_log("generate_announcement error: " . $err->getMessage() . " Stack trace: " . $err->getTraceAsString());
        header('Content-Type: application/json');
        //echo json_encode(['success' => false, 'error' => '生成拍摄通告时发生错误', 'details' => $err->getMessage()]);
    }
    exit;
}

// 获取当前用户ID
$userId = $_SESSION['user_id'] ?? null;

// 数据库任务ID（如果有）
$dbTaskId = null;

// 仅在有用户ID时执行数据库查询
if ($userId) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 1. 首先检查是否是剧组成员或管理员，获取剧组当前任务号
        require_once __DIR__ . '/Auth.php';
        $auth = new Auth();
        $crewTaskResult = $auth->getCurrentTaskNumberWithFallback($userId);
        if ($crewTaskResult['success'] && !empty($crewTaskResult['data'])) {
            $dbTaskId = $crewTaskResult['data'];
            // error_log("获取剧组当前任务号成功: {$dbTaskId}");
        }

        // 2. 如果没有剧组任务号，查询用户自己的最近script_analysis任务
        if (empty($dbTaskId)) {
            // 查询用户的最近script_analysis任务
            $sql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type = 'script_analysis' AND task_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['task_id'])) {
                $dbTaskId = $result['task_id'];
            } else {
                // 如果没有找到，再查询shooting_notice任务
                $sql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type = 'shooting_notice' AND task_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();

                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($result && !empty($result['task_id'])) {
                    $dbTaskId = $result['task_id'];
                } else {
                    // 3. 如果还是没有，查询用户可访问的共享任务
                    $sql = "SELECT sr.resource_id as task_id FROM shared_resources sr 
                           JOIN crew_organization co ON sr.crew_id = co.crew_id 
                           WHERE co.user_id = :user_id 
                           ORDER BY sr.created_at DESC LIMIT 1";
                    $stmt = $pdo->prepare($sql);
                    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                    $stmt->execute();
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && !empty($result['task_id'])) {
                        $dbTaskId = $result['task_id'];
                    }
                }
            }
        }

        // error_log("最终获取到的task_id: " . ($dbTaskId ?? 'null'));
    } catch (Exception $e) {
        // error_log("Failed to get current task_id: " . $e->getMessage());
    }
}

// 如果从数据库获取到了task_id，但URL中没有提供，就使用数据库中的task_id
if ($dbTaskId && !$taskId) {
    $taskId = $dbTaskId;
}

function checkAndGenerateAnnouncementSimple($task_ids)
{
    $task_ids = trim($task_ids);
    if (empty($task_ids)) {
        // error_log("checkAndGenerateAnnouncementSimple: Invalid task_id provided");
        return false;
    }

    // 使用绝对路径，避免路径问题
    $basePath = __DIR__ . '/results/';
    $scheduleFile = $basePath . "{$task_ids}_schedule.json";
    $announcementFile = $basePath . "{$task_ids}_announcement.json";

    // 如果announcement文件已存在，直接返回true
    if (file_exists($announcementFile)) {
        // error_log("checkAndGenerateAnnouncementSimple: announcement file already exists for task_id {$task_ids}");
        return true;
    }

    // 如果schedule文件不存在，无法生成announcement
    if (!file_exists($scheduleFile)) {
        // error_log("checkAndGenerateAnnouncementSimple: schedule file not found for task_id {$task_ids}");
        return false;
    }

    try {
        // 直接调用PHP脚本，避免HTTP请求的开销
        $apiFile = __DIR__ . '/announcement_api.php';
        if (file_exists($apiFile)) {
            // 保存原始的GET参数
            $originalTaskId = $_GET['task_id'] ?? null;
            $_GET['task_id'] = $task_ids;

            // 捕获输出
            ob_start();
            include $apiFile;
            $output = ob_get_clean();

            // 恢复原始GET参数
            if ($originalTaskId !== null) {
                $_GET['task_id'] = $originalTaskId;
            } else {
                unset($_GET['task_id']);
            }

            // 检查announcement文件是否已生成
            if (file_exists($announcementFile)) {
                // error_log("checkAndGenerateAnnouncementSimple: Successfully generated announcement file for task_id {$task_ids}");
                return true;
            } else {
                // error_log("checkAndGenerateAnnouncementSimple: Failed to generate announcement file for task_id {$task_ids}, API output: {$output}");
                return false;
            }
        } else {
            // error_log("checkAndGenerateAnnouncementSimple: announcement_api.php not found");
            return false;
        }
    } catch (Exception $e) {
        // error_log("checkAndGenerateAnnouncementSimple: Exception occurred for task_id {$task_ids}: " . $e->getMessage());
        return false;
    }
}



?>


<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拍摄通告 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="./css/announcement.css">
</head>

<body>

    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>

    <!-- 功能区 -->
    <div class="function-bar no-print">
        <div class="function-left" id="function-left">
            <div class="tab active">拍摄通告</div>
            <div class="date-navigation">
                <button class="btn btn-secondary btn-sm" id="prev-month" onclick="prevMonth()">
                    <i class="fas fa-chevron-left"></i> 上一月
                </button>
                <button class="btn btn-secondary btn-sm" id="next-month" onclick="nextMonth()">
                    下一月 <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
        <div class="function-right no-print" id="function-right">
            <div class="btn-group.bak">
                <button id="toggle-edit" class="btn btn-primary btn-sm">
                    <i class="fas fa-edit"></i> 切换编辑模式
                </button>
                <button id="undo" class="btn btn-secondary btn-sm">
                    <i class="fas fa-undo"></i> 撤销
                </button>
                <button id="redo" class="btn btn-secondary btn-sm">
                    <i class="fas fa-redo"></i> 重做
                </button>
                <button id="print-preview" class="btn btn-secondary btn-sm" onclick="window.print()">
                    <i class="fas fa-print"></i> 打印预览
                </button>
                <span id="edit-status" class="status-text">当前处于查看模式</span>
            </div>
        </div>
    </div>
    <!-- 加载提示 -->
    <div id="loading-indicator" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.8); color: white; padding: 20px; border-radius: 10px; z-index: 9999;">
        <div style="text-align: center;">
            <div style="font-size: 18px; margin-bottom: 10px;">正在生成拍摄通告...</div>
            <div style="width: 50px; height: 50px; border: 5px solid #f3f3f3; border-top: 5px solid #3498db; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto;"></div>
            <div style="font-size: 14px; margin-top: 10px;">这可能需要一点时间，请稍候...</div>
        </div>
    </div>



    <!-- 左侧悬浮日历按钮 -->
    <div class="calendar-float-btn no-print" id="calendarFloatBtn" onclick="toggleCalendar()">
        <i class="fas fa-calendar-alt"></i>
    </div>

    <!-- 悬浮日历容器 -->
    <div id="calendarContainer" class="no-print calendar-float-container" style="display: none;">
        <div class="calendar-section">
            <div class="calendar-header no-print">
                <div class="calendar-title" id="calendarTitle"></div>
            </div>

            <div class="calendar-grid" id="calendarGrid">
                <!-- 日历将通过JavaScript动态生成 -->
            </div>
        </div>
    </div>

    <!-- 隐藏字段，用于传递PHP变量给JavaScript -->
    <input type="hidden" id="taskId" value="<?php echo htmlspecialchars(json_encode($taskId), ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" id="userId" value="<?php echo htmlspecialchars($_SESSION['user_id'], ENT_QUOTES, 'UTF-8'); ?>">

    <div class="container">
        <div class="json-input-section no-print" style="display: none;">
            <h2>1. 输入拍摄数据JSON</h2>
            <p>请将完整的拍摄数据JSON粘贴到下方文本框中：</p>
            <textarea id="jsonInput" placeholder="请在此处粘贴JSON数据..."></textarea>

            <div class="btn-group">
                <button class="btn" onclick="loadData()">加载数据</button>
            </div>

            <div id="messageArea"></div>
            <div id="loading" class="loading">正在生成拍摄通告，请稍候...</div>
        </div>

        <div id="outputContainer">
            <!-- 拍摄通告将在这里显示 -->
        </div>

        <!-- <div class="print-btn no-print">
            <button class="btn" onclick="window.print()">🖨️ 打印拍摄通告</button>
        </div> -->
    </div>
    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script src="js/announcement.js"></script>
    <script>
        checkLoginStatus(true);
        // 页面加载时检查登录状态，保护页面访问
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>
</body>

</html>
