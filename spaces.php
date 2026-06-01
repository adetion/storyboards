<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
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
    <link rel="stylesheet" href="css/characters_style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
</head>

<body>
    <?php include 'header.html'; ?>

    <div class="function-bar">
        <div class="function-left">
            <div class="function-tab active">时空场景</div>
        </div>
        <div class="function-right">
            <div class="btn-group">
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="content">
            <div class="result-section">
                <h2>时空场景列表</h2>
                <div class="result-container" id="result">
                    <p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">
                        正在加载当前剧本的时空场景列表...
                    </p>
                </div>
            </div>

            <div class="input-section">
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="new-task">时空场景分析</div>
                        <div class="tab" data-tab="history">历史任务</div>
                    </div>

                    <div class="tab-content active" id="new-task">
                        <h2>输入区域</h2>
                        <div class="auto-load-notice" id="auto-load-notice" style="display: none;">
                            <div class="loader"></div>
                            <span id="auto-load-text">正在检查最新任务状态...</span>
                        </div>

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

                        <div class="form-group" id="textInputSection">
                            <label for="script">小说剧本内容</label>
                            <textarea id="script" placeholder="请粘贴或输入您的小说剧本内容..."></textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> / 300,000 字符
                            </div>
                            <div class="loading" id="loading">
                                <p>正在分析时空场景信息，请稍候...</p>
                                <div class="progress-container" id="progress-container">
                                    <div class="progress-bar" id="progress-bar">
                                        <div class="progress-text" id="progress-text">0%</div>
                                    </div>
                                </div>
                                <div class="progress-info" id="progress-info">准备开始分析...</div>
                            </div>
                        </div>

                        <div class="file-upload-section" id="fileUploadSection" style="display: none;">
                            <div class="form-group">
                                <label for="novelFile">上传剧本文件：</label>
                                <div style="margin-top: 10px;">
                                    <label for="spacesFile" class="custom-file-upload">
                                        <i class="fas fa-file-upload"></i> 选择文件
                                    </label>
                                    <input type="file" id="spacesFile" accept=".txt">
                                    <div class="file-info">支持.txt格式文本文件，最大300,000字符</div>
                                    <div id="uploadedFileName" style="margin-top: 10px; font-weight: 600; color: var(--success-color);"></div>
                                </div>
                            </div>
                        </div>

                        <div class="points-info">
                            <i class="fas fa-coins"></i> 时空场景分析每轮次消耗 <strong>100 积分</strong>
                        </div>
                        <div class="points-info">
                            <i class="fas fa-image"></i> 场景图生成每张消耗 <strong>20 积分</strong>
                        </div>
                        <div style="margin-top:50px;"><button id="submit-btn">开始时空场景分析</button></div>

                        <div class="error" id="error"></div>
                        <div class="success" id="success">时空场景分析完成！结果已显示在上方。</div>
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
        </div>

        <?php include 'footer.html'; ?>
    </div>

    <div class="confirmation-dialog" id="confirmation-dialog" style="display: none;">
        <div class="confirmation-content">
            <h3>确认删除</h3>
            <p id="confirmation-message" style="font-size: 0.9rem;">您确定要删除这个任务吗？此操作不可恢复。</p>
            <div class="confirmation-buttons">
                <button id="cancel-delete-btn" class="secondary-button">取消</button>
                <button id="confirm-delete-btn" class="danger-button">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 图片上传模态框 -->
    <div class="modal" id="imageUploadModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>上传场景图片</h2>
                <button class="modal-close" id="imageUploadModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label for="sceneName">场景名称：</label>
                    <input type="text" id="sceneName" readonly>
                </div>
                <div class="form-group">
                    <label>上传图片：</label>
                    <div class="file-upload-section">
                        <label for="imageFile" class="custom-file-upload">
                            <i class="fas fa-file-image"></i> 选择图片
                        </label>
                        <input type="file" id="imageFile" accept=".jpg,.jpeg,.png,.gif">
                        <div class="file-info">支持JPG、JPEG、PNG、GIF格式图片，单张不超过5MB</div>
                        <div id="uploadedImageName" style="margin-top: 10px; font-weight: 600; color: var(--success-color);"></div>
                    </div>
                </div>
                <div class="form-group">
                    <label>图片URL（可选，多个URL请用逗号分隔）：</label>
                    <textarea id="imageUrlInput" placeholder="请输入图片URL，多个URL请用逗号分隔..." rows="3"></textarea>
                </div>
                <div class="preview-section" id="preview-section" style="margin-top: 20px; display: none;">
                    <h4>预览：</h4>
                    <div class="image-preview-container" id="image-preview-container"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="imageUploadCancelBtn" class="btn btn-secondary">取消</button>
                <button id="imageUploadSubmitBtn" class="btn btn-primary">保存</button>
            </div>
        </div>
    </div>

    <!-- 图片查看模态框 -->
    <div class="modal" id="imageViewModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>场景图片</h2>
                <button class="modal-close" id="imageViewModalClose">&times;</button>
            </div>
            <div class="modal-body">
                <div class="image-gallery" id="image-gallery"></div>
            </div>
            <div class="modal-footer">
                <button id="imageViewCloseBtn" class="btn btn-secondary">关闭</button>
            </div>
        </div>
    </div>
    <script src="js/task_ui_components.js"></script>
    <script>
        window.currentUserId = <?php echo $_SESSION['user_id']; ?>;
    </script>
    <script src="js/spaces_main.js"></script>
    <script>
        const taskUI = new TaskUIComponents();

        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>
</body>

</html>
