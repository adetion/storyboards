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
            <div class="function-tab active">剧本区</div>
        </div>
        <div class="function-right">
            <div class="btn-group">
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="content">
            <div class="input-section">
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="new-task">剧本分析</div>
                        <div class="tab" data-tab="history">历史任务</div>
                    </div>

                    <div class="tab-content active" id="new-task">
                        <h2>输入区域</h2>
                        <div class="auto-load-notice" id="auto-load-notice" style="display: none;">
                            <div class="loader"></div>
                            <span id="auto-load-text">正在检查最新任务状态...</span>
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
                            <label for="script">剧本内容</label>
                            <textarea id="script" placeholder="请粘贴或输入您的剧本内容..."></textarea>
                            <div class="loading" id="loading">
                                <p>正在分析剧本内容，请稍候...</p>
                                <div class="progress-container" id="progress-container">
                                    <div class="progress-bar" id="progress-bar">
                                        <div class="progress-text" id="progress-text">0%</div>
                                    </div>
                                </div>
                                <div class="progress-info" id="progress-info">准备开始分析...</div>
                            </div>
                        </div>


                        <!-- 文件上传区域（默认隐藏） -->
                        <div class="file-upload-section" id="fileUploadSection" style="display: none;">
                            <div class="form-group">
                                <label for="novelFile">上传剧本文件：</label>
                                <div style="margin-top: 10px;">
                                    <label for="scriptsFile" class="custom-file-upload">
                                        <i class="fas fa-file-upload"></i> 选择文件
                                    </label>
                                    <input type="file" id="scriptsFile" accept=".txt">
                                    <div class="file-info">支持.txt格式文本文件，最大300,000字符</div>
                                    <div id="uploadedFileName" style="margin-top: 10px; font-weight: 600; color: var(--success-color);"></div>
                                </div>
                            </div>

                        </div>
                        <div class="points-info">
                            <i class="fas fa-coins"></i> 剧本转分镜每轮次消耗 <strong>100 积分</strong>
                        </div>
                        <div style="margin-top:50px;"><button id="submit-btn">转换为分镜</button></div>






                        <div class="error" id="error"></div>
                        <div class="success" id="success">分析完成！结果已显示在右侧。</div>
                    </div>

                    <div class="tab-content" id="history">
                        <h2>历史任务记录</h2>
                        <div class="action-buttons">
                            <button id="refresh-history-btn" class="secondary-button">刷新</button>
                            <button id="delete-all-btn" class="danger-button">删除全部</button>
                        </div>
                        <div class="history-list" id="history-list">
                            <div class="empty-state">暂无历史任务</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="result-section">
                <h2>转换结果预览</h2>
                <div class="result-container" id="result">
                    <p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">
                        转换结果预览将在此处显示...
                    </p>
                </div>
            </div>
        </div>

        <!-- 确认对话框 -->
        <div class="confirmation-dialog" id="confirmation-dialog">
            <div class="confirmation-content">
                <h3>确认删除</h3>
                <p id="confirmation-message" style="font-size: 0.9rem;">您确定要删除这个任务吗？此操作不可恢复。</p>
                <div class="confirmation-buttons">
                    <button id="cancel-delete-btn" class="secondary-button">取消</button>
                    <button id="confirm-delete-btn" class="danger-button">确认删除</button>
                </div>
            </div>
        </div>

        <!-- 历史任务详情悬浮层 -->
        <div class="task-detail-dialog" id="task-detail-dialog">
            <div class="task-detail-content">
                <div class="task-detail-header">
                    <h3>任务详情</h3>
                    <button id="close-task-detail" class="close-button">&times;</button>
                </div>
                <div class="task-info">
                    <p><strong>任务编号:</strong> <span id="detail-task-id"></span></p>
                    <p><strong>总轮次:</strong> <span id="detail-total-rounds"></span></p>
                    <p><strong>当前进度:</strong> <span id="detail-message"></span></p>
                    <div class="task-progress-container">
                        <div class="task-progress-bar" id="detail-progress-bar">
                            <div class="task-progress-text" id="detail-progress-text">0%</div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="detail-content"><strong>分析内容:</strong></label>
                    <textarea id="detail-content" readonly></textarea>
                </div>
                <div class="task-detail-actions">
                    <button id="refresh-task-detail" class="secondary-button">刷新</button>
                    <button id="close-task-detail-bottom" class="secondary-button">关闭</button>
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
    <script src="js/scripts_main.js"></script>
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
