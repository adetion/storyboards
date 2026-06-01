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

// 引入统一任务管理器
require_once __DIR__ . '/TaskManager.php';

// 初始化任务管理器
$taskManager = TaskManager::getInstance();

?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/scripts_style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>

    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="function-tab active">小说转剧本</div>
        </div>
        <div class="function-right">
            <div class="btn-group">
            </div>
        </div>
    </div>

    <div class="main-content" id="pageContent" style="display: none;">
        <div class="content">
            <div class="input-section">
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="new-task">小说分析</div>
                        <div class="tab" data-tab="history">历史任务</div>
                    </div>

                    <div class="tab-content active" id="new-task">
                        <h2>输入区域</h2>
                        <div class="auto-load-notice" id="autoLoadNotice" style="display: none;">
                            <div class="loader"></div>
                            <span id="autoLoadText">正在检查最新任务状态...</span>
                        </div>

                        <!-- 输入方式选择 -->
                        <div class="input-method-group">
                            <label>输入方式：</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="inputMethod" value="text" id="textInputMethod" checked>
                                    <span class="radio-custom"></span>
                                    直接输入文本
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="inputMethod" value="file" id="fileInputMethod">
                                    <span class="radio-custom"></span>
                                    上传文件
                                </label>
                            </div>
                        </div>

                        <!-- 直接输入文本区域（默认显示） -->
                        <div class="form-group" id="textInputSection">
                            <label for="novelText">小说文本内容：</label>
                            <textarea id="novelText" placeholder="请粘贴或输入您的小说内容..."></textarea>
                            <div class="file-info">
                                <span id="textLength">0</span> 字符
                            </div>
                        </div>

                        <!-- 文件上传区域（默认隐藏） -->
                        <div class="file-upload-section" id="fileUploadSection" style="display: none;">
                            <div class="form-group">
                                <label for="novelFile">上传小说文件：</label>
                                <div style="margin-top: 10px;">
                                    <label for="novelFile" class="custom-file-upload">
                                        <i class="fas fa-file-upload"></i> 选择文件
                                    </label>
                                    <input type="file" id="novelFile" accept=".txt">
                                    <div class="file-info">支持.txt格式文本文件，最大300,000字符</div>
                                    <div id="uploadedFileName" style="margin-top: 10px; font-weight: 600; color: var(--success-color);"></div>
                                </div>
                            </div>
                        </div>

                        <div class="points-info">
                            <i class="fas fa-coins"></i> 小说转剧本每轮次消耗 <strong>100 积分</strong>
                        </div>
                        <button id="convertBtn" class="btn btn-primary">转换为剧本</button>

                        <div class="processing-state" id="progress" style="display: none;">
                            <div class="processing-content">
                                <div class="spinner"></div>
                                <h3>小说转剧本中</h3>
                                <p id="progressInfo">正在启动后台分析任务...</p>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressBar" style="width: 0%"></div>
                                </div>
                                <p class="small-text">任务ID: <span id="processingTaskId"></span></p>
                                <p class="small-text">当前进度: <span id="currentRoundInfo"></span></p>
                                <p class="small-text">您可以保存此Task ID，稍后回来查询结果</p>
                                <div class="real-time-result">
                                    <h4>实时转换结果预览</h4>
                                    <div id="realTimeResult" class="real-time-result-content"></div>
                                </div>
                                <div class="processing-actions">
                                    <button type="button" class="btn btn-secondary" onclick="checkStatusAgain()">
                                        <i class="fas fa-sync-alt"></i> 刷新状态
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="copyProcessingTaskId()">
                                        <i class="fas fa-copy"></i> 复制Task ID
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="error" id="error"></div>
                        <div class="success" id="success">分析完成！结果已显示在右侧。</div>
                    </div>

                    <div class="tab-content" id="history">
                        <h2>历史任务记录</h2>
                        <div class="action-buttons">
                            <button id="refreshHistoryBtn" class="secondary-button">刷新</button>
                            <button id="deleteAllBtn" class="danger-button">删除全部</button>
                        </div>
                        <div class="history-list" id="historyList">
                            <div class="empty-state">暂无历史任务</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="result-section">
                <h2>转换结果预览 <label style="font-size: 0.8rem; color: #7f8c8d;">（仅供参考，您可以将其再次发送到剧本转分镜）</label></h2>
                <div class="result-container" id="conversionResult">
                    <p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">
                        转换结果预览将在此处显示...
                    </p>
                </div>
            </div>
        </div>

        <!-- 确认对话框 -->
        <div class="confirmation-dialog" id="confirmationDialog">
            <div class="confirmation-content">
                <h3>确认删除</h3>
                <p id="confirmationMessage" style="font-size: 0.9rem;">您确定要删除这个任务吗？此操作不可恢复。</p>
                <div class="confirmation-buttons">
                    <button id="cancelDeleteBtn" class="secondary-button">取消</button>
                    <button id="confirmDeleteBtn" class="danger-button">确认删除</button>
                </div>
            </div>
        </div>

        <!-- 历史任务详情悬浮层 -->
        <div class="task-detail-dialog" id="taskDetailDialog">
            <div class="task-detail-content">
                <div class="task-detail-header">
                    <h3>任务详情</h3>
                    <button id="closeTaskDetail" class="close-button">&times;</button>
                </div>
                <div class="task-info">
                    <p class="task-id-row"><strong>任务编号:</strong></p>
                    <p class="task-id-value"><span id="detail-task-id"></span></p>
                    <div class="task-stats-row">
                        <span class="task-stat-item"><strong>总轮次:</strong> <span id="detail-total-rounds"></span></span>
                        <span class="task-stat-item"><strong>当前进度:</strong> <span id="detail-message"></span></span>
                        <span class="task-stat-item"><strong>进度百分比:</strong> <span id="detail-progress-text">0%</span></span>
                    </div>
                    <div class="task-progress-container">
                        <div class="task-progress-bar" id="detail-progress-bar">
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="detail-content"><strong>实时转换后的剧本内容:</strong></label>
                    <textarea id="detail-content" readonly></textarea>
                </div>
                <div class="task-detail-actions">
                    <button id="refreshTaskDetail" class="secondary-button">刷新</button>
                    <button id="closeTaskDetailBottom" class="secondary-button">关闭</button>
                </div>
            </div>
        </div>

        <!-- 底部版权声明栏 -->
        <?php include 'footer.html'; ?>
    </div>

    <script src="js/task_ui_components.js"></script>
    <script>
        // 获取当前用户ID
        window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="js/novel_main.js"></script>
    <script>
        // 初始化任务UI组件
        const taskUI = new TaskUIComponents();

        // 页面加载时检查登录状态，保护页面访问
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>

</body>

</html>
