<?php
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



// 检查用户是否有"分镜管理"的历史任务，并获取当前task_id
$currentTaskId = null;

// 引入配置文件
require_once __DIR__ . '/config.php';

// 获取当前用户ID
$userId = $_SESSION['user_id'];

try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 1. 直接执行storyboards_sort.php的代码重新设定分镜排序
    error_log("直接执行storyboards_sort.php代码");

    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();

        // 获取用户的剧组ID
        $crewSql = "SELECT id FROM crew WHERE admin_user_id = :user_id LIMIT 1";
        $crewStmt = $pdo->prepare($crewSql);
        $crewStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $crewStmt->execute();
        $crewData = $crewStmt->fetch(PDO::FETCH_ASSOC);

        if ($crewData) {
            $crewId = $crewData['id'];

            // 获取当前剧组下的所有任务
            $taskSql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type IN ('storyboard_management', 'script_to_storyboard') AND task_id IS NOT NULL";
            $taskStmt = $pdo->prepare($taskSql);
            $taskStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $taskStmt->execute();
            $tasks = $taskStmt->fetchAll(PDO::FETCH_ASSOC);

            error_log("故事板排序: 找到 " . count($tasks) . " 个任务");

            // 处理每个任务的分镜排序
            foreach ($tasks as $task) {
                $taskId = $task['task_id'];

                error_log("故事板排序: 处理任务 " . $taskId);

                // 检查是否存在重复的sort_order值
                $checkDuplicatesSql = "SELECT sort_order, COUNT(*) as count FROM shots WHERE task_id = :task_id GROUP BY sort_order HAVING COUNT(*) > 1";
                $checkDuplicatesStmt = $pdo->prepare($checkDuplicatesSql);
                $checkDuplicatesStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
                $checkDuplicatesStmt->execute();
                $duplicates = $checkDuplicatesStmt->fetchAll(PDO::FETCH_ASSOC);

                // 只有当存在重复值时才重置排序
                if (count($duplicates) > 0) {
                    error_log("故事板排序: 任务 " . $taskId . " 存在重复的sort_order值，需要重置排序");

                    // 获取当前任务下的所有分镜，按照scenes_id和shots_id排序
                    $shotSql = "SELECT id, scenes_id, shots_id FROM shots WHERE task_id = :task_id ORDER BY scenes_id ASC, shots_id ASC";
                    $shotStmt = $pdo->prepare($shotSql);
                    $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
                    $shotStmt->execute();
                    $shots = $shotStmt->fetchAll(PDO::FETCH_ASSOC);

                    error_log("故事板排序: 任务 " . $taskId . " 找到 " . count($shots) . " 个分镜");

                    // 重新设定sort_order值
                    $sortOrder = 1;
                    foreach ($shots as $shot) {
                        $updateSql = "UPDATE shots SET sort_order = :sort_order WHERE id = :shot_id AND task_id = :task_id";
                        $updateStmt = $pdo->prepare($updateSql);
                        $updateStmt->bindParam(':sort_order', $sortOrder, PDO::PARAM_INT);
                        $updateStmt->bindParam(':shot_id', $shot['id'], PDO::PARAM_INT);
                        $updateStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
                        $result = $updateStmt->execute();
                        error_log("故事板排序: 更新分镜 " . $shot['id'] . " 排序为 " . $sortOrder . " 结果: " . ($result ? '成功' : '失败'));
                        $sortOrder++;
                    }
                } else {
                    error_log("故事板排序: 任务 " . $taskId . " 没有重复的sort_order值，不需要重置排序");
                }
            }

            error_log("分镜排序更新成功");
        } else {
            error_log("分镜排序更新失败: 未找到用户的剧组");
        }
    } catch (Exception $e) {
        error_log("分镜排序更新失败: " . $e->getMessage());
    }

    // 2. 从crew表中获取当前任务号
    $sql = "SELECT current_task_id FROM crew WHERE admin_user_id = :user_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $crewResult = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($crewResult && !empty($crewResult['current_task_id'])) {
        $currentTaskId = $crewResult['current_task_id'];
        error_log("从crew表获取到current_task_id: {$currentTaskId}");
    } else {
        // 3. 如果crew表中没有，则查询用户的"storyboard_management"历史任务
        $sql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type IN ('storyboard_management', 'script_to_storyboard') AND task_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['task_id'])) {
            $currentTaskId = $result['task_id'];
            error_log("从tasks表获取到task_id: {$currentTaskId}");
        }
    }
} catch (Exception $e) {
    // 数据库查询失败时，记录错误但继续执行
    error_log("获取当前task_id失败: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>故事板 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/gushiban_style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="css/menu.css">
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>
    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="tab active">故事板</div>
        </div>
        <div class="function-right">
            <div class="action-buttons">
                <!-- <button class="btn btn-primary" id="generateReferenceImage">
                    <i class="fas fa-image"></i> 生成参考图
                </button>
                <button class="btn btn-primary" id="regenerateReferenceImage">
                    <i class="fas fa-redo"></i> 重新生成
                </button> -->
                <button class="btn btn-primary">
                    <i class="fas fa-download"></i> 导出故事板
                </button>
                <!-- <button class="btn btn-primary" id="generateStoryboardVideo">
                    <i class="fas fa-video"></i> 生成视频
                </button> -->
            </div>
        </div>
    </div>

    <!-- 浮动提示条 -->
    <div class="floating-bar hidden">
        <div class="bar-content">
            <div class="bar-info">
                <i class="fas fa-info-circle"></i>
                <div class="bar-text">
                    <strong>关于故事板</strong>
                    <span>在故事板模式下，你可以用类似于连环画的形式浏览故事的完整性，并做出适当调整。</span>
                </div>
            </div>
            <button class="btn btn-primary">生成视频故事板（即将开放）</button>
            <div class="bar-actions">
                <span>下一步，您可以</span>
                <button class="btn btn-primary">预览拍摄计划</button>
            </div>
            <!-- 积分消耗提示 -->
            <div class="points-notice" style="background-color: #fef3c7; border: 1px solid #fcd34d; border-radius: 8px; padding: 10px 16px; margin: 10px 20px; display: flex; align-items: center; gap: 8px; color: #92400e;">
                <i class="fas fa-coins" style="color: #f59e0b;"></i>
                <span><strong>积分提示：</strong>生成故事板视频每次消耗<?php echo Config::VIDEO_GENERATION_COST; ?>积分，时长5秒；生成参考图每张消耗<?php echo Config::IMAGE_GENERATION_COST; ?>积分</span>
            </div>
            <button class="btn-icon close-btn"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <!-- 主内容区 -->
    <main class="main-content" id="pageContent" style="display: none;">
        <div id="storyboard-container">
            <div class="loading-message">
                <i class="fas fa-spinner fa-spin"></i> 正在加载分镜数据...
            </div>
        </div>
    </main>

    <!-- 生成视频模态框 -->
    <div class="generate-modal-overlay" id="generateModal">
        <div class="generate-modal">
            <div class="modal-header">
                <h2>生成视频</h2>
                <button class="modal-close-btn" id="generateModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="generateImageForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-info-circle"></i>
                            剧本题材
                        </label>
                        <div class="genres-display" id="modalGenresDisplay">
                            <span class="genres-loading">加载中...</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-keyboard"></i>
                            切片提示词
                        </label>
                        <div class="slice-prompts-container" style="margin-top: 10px;">
                            <div class="slice-prompts-scroll" style="display: flex; gap: 15px; overflow-x: auto; padding: 10px 0; white-space: nowrap;">
                                <!-- 切片提示词输入框将在这里动态添加 -->
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-palette"></i>
                            艺术风格
                            <span class="current-selection" id="modalCurrentStyle">线稿手绘</span>
                        </label>
                        <div class="presets-grid" id="modalStylePresets">
                        </div>
                        <input type="hidden" id="modalStyle" value="12">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-crop-alt"></i>
                            图片比例
                            <span class="current-selection" id="modalCurrentRatio">横屏 16:9</span>
                        </label>
                        <div class="presets-grid" id="modalRatioPresets">
                        </div>
                        <input type="hidden" id="modalPicSize" value="16:9">
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="btn btn-secondary" id="modalCancelBtn">
                            <i class="fas fa-times"></i> 取消
                        </button>
                        <button type="submit" class="btn btn-primary" id="modalGenerateBtn">
                            <i class="fas fa-magic"></i> 生成视频
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 从数据库中获取的当前任务号，优先级最高
        window.dbTaskId = <?php echo $currentTaskId ? '"' . $currentTaskId . '"' : 'null'; ?>;
        // 获取当前用户ID
        window.currentUserId = <?php echo $userId; ?>;
    </script>
    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script src="js/gushiban.js?v=<?php echo time(); ?>"></script>
    <script src="js/main_storyboards.js?v=<?php echo time(); ?>"></script>
    <script>
        // 页面加载时检查登录状态，保护页面访问
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>
</body>

</html>
