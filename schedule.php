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


?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>拍摄计划 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/schedule_style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="css/menu.css">
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>
    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="tab active">拍摄计划</div>

            <div class="date-navigation">
                <button class="btn btn-secondary" id="prev-month">
                    <i class="fas fa-chevron-left"></i>上个月
                </button>
                <!--<div class="date-picker">-->
                <!--   <span id="current-month"><?php echo date('Y年n月'); ?></span>-->
                <!--</div>-->
                <button class="btn btn-secondary" id="next-month">
                    下个月<i class="fas fa-chevron-right"></i>
                </button>
            </div>

        </div>
        <div class="function-right">
            <div class="btn-group">
                <button class="btn btn-secondary" id="print-schedule">
                    <i class="fas fa-print"></i> 打印预览
                </button>
                <button class="btn btn-success" id="export-schedule">
                    <i class="fas fa-file-word"></i> 导出计划
                </button>
            </div>
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

    <!-- 主内容区 -->
    <main class="main-content" id="pageContent" style="display: none;">
        <div id="schedule-content">
            <div class="loading-message">
                <i class="fas fa-spinner fa-spin"></i> 正在加载拍摄计划数据...
            </div>
        </div>
    </main>
    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script>
        // 获取当前用户ID
        window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="js/schedule_main.js"></script>
    <script>
        // 页面加载时检查登录状态，保护页面访问
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>
</body>

</html>
