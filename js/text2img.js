// 全局函数
function openImageModal(imageUrl) {
    const modalImage = document.getElementById('modalImage');
    const modalDownloadBtn = document.getElementById('modalDownloadBtn');
    const imageModal = document.getElementById('imageModal');

    if (modalImage && imageModal) {
        modalImage.src = imageUrl;
        modalDownloadBtn.href = imageUrl;
        modalDownloadBtn.download = `generated-image-${Date.now()}.jpg`;
        imageModal.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
}

function closeImageModal() {
    const imageModal = document.getElementById('imageModal');
    if (imageModal) {
        imageModal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    // DOM元素
    const form = document.getElementById('generateForm');
    const loadingOverlay = document.getElementById('loadingOverlay');
    const initialState = document.getElementById('initialState');
    const imageContainer = document.getElementById('imageContainer');
    const resultInfo = document.getElementById('resultInfo');
    const processingState = document.getElementById('processingState');
    const errorAlert = document.getElementById('errorAlert');
    const errorMessage = document.getElementById('errorMessage');
    const themeToggle = document.getElementById('themeToggle');
    const generateBtn = document.getElementById('generateBtn');
    const imageModal = document.getElementById('imageModal');
    const modalCloseBtn = document.getElementById('modalCloseBtn');
    const taskIdInput = document.getElementById('taskIdInput');

    // 主题切换
    themeToggle.addEventListener('click', function () {
        document.body.classList.toggle('dark-theme');
        const icon = this.querySelector('i');
        if (document.body.classList.contains('dark-theme')) {
            icon.className = 'fas fa-sun';
        } else {
            icon.className = 'fas fa-moon';
        }
    });

    // 预设数据
    const stylePresets = [{
        id: '12',
        name: '线稿2.0',
        label: '线稿手绘',
        icon: 'fas fa-pencil-alt'
    },
    {
        id: '10',
        name: '写实2.0',
        label: '写实',
        icon: 'fas fa-camera'
    },
    {
        id: '5',
        name: '手绘动画',
        label: '手绘动画',
        icon: 'fas fa-paint-brush'
    },
    {
        id: '11',
        name: '动漫2.0',
        label: '动漫二次元',
        icon: 'fas fa-gamepad'
    },
    {
        id: '18',
        name: '动漫玄幻',
        label: '古风玄幻',
        icon: 'fas fa-hat-wizard'
    },
    {
        id: '20',
        name: '一致性动漫',
        label: '一致动漫',
        icon: 'fas fa-sync-alt'
    },
    {
        id: '17',
        name: '吉卜力',
        label: '宫崎骏风',
        icon: 'fas fa-film'
    },
    {
        id: '7',
        name: '国风写实',
        label: '国风写实',
        icon: 'fas fa-mountain'
    },
    {
        id: '16',
        name: '国风工笔',
        label: '国风工笔',
        icon: 'fas fa-brush'
    },
    {
        id: '22',
        name: '一致性通用',
        label: '一致通用',
        icon: 'fas fa-globe'
    },
    {
        id: '21',
        name: '通用3.0',
        label: '通用3.0',
        icon: 'fas fa-star'
    },
    {
        id: '10',
        name: '通用2.0',
        label: '通用2.0',
        icon: 'fas fa-star-half-alt'
    },
    {
        id: '19',
        name: '一致性写实',
        label: '一致写实',
        icon: 'fas fa-user-check'
    },
    {
        id: '15',
        name: '王家卫',
        label: '港风',
        icon: 'fas fa-theater-masks'
    },
    {
        id: '6',
        name: '3D动画',
        label: '3D动画',
        icon: 'fas fa-cube'
    },
    {
        id: '4',
        name: '欧美漫画',
        label: '欧美漫画',
        icon: 'fas fa-mask'
    },
    {
        id: '13',
        name: '蒸汽朋克',
        label: '蒸汽朋克',
        icon: 'fas fa-city'
    }
    ];

    const ratioPresets = [{
        id: '16:9',
        name: '横屏 16:9',
        label: '16:9',
        icon: 'fas fa-desktop'
    },
    {
        id: '9:16',
        name: '竖屏 9:16',
        label: '9:16',
        icon: 'fas fa-mobile-alt'
    },
    {
        id: '2.35:1',
        name: '超宽屏 2.35:1',
        label: '2.35:1',
        icon: 'fas fa-film'
    },
    {
        id: '1:2.35',
        name: '超高屏 1:2.35',
        label: '1:2.35',
        icon: 'fas fa-film'
    },
    {
        id: '1:1',
        name: '正方形 1:1',
        label: '1:1',
        icon: 'fas fa-square'
    },
    {
        id: '4:3',
        name: '传统 4:3',
        label: '4:3',
        icon: 'fas fa-tv'
    },
    {
        id: '3:4',
        name: '竖屏 3:4',
        label: '3:4',
        icon: 'fas fa-tablet-alt'
    },
    {
        id: '3:2',
        name: '横屏 3:2',
        label: '3:2',
        icon: 'fas fa-camera'
    },
    {
        id: '2:3',
        name: '竖屏 2:3',
        label: '2:3',
        icon: 'fas fa-portrait'
    }
    ];

    const countPresets = [{
        id: '1',
        name: '1张',
        label: '1张',
        icon: '1'
    },
    {
        id: '2',
        name: '2张',
        label: '2张',
        icon: '2'
    },
    {
        id: '3',
        name: '3张',
        label: '3张',
        icon: '3'
    },
    {
        id: '4',
        name: '4张',
        label: '4张',
        icon: '4'
    }
    ];

    // 初始化预设按钮
    function initPresets() {
        // 样式预设
        const styleContainer = document.getElementById('stylePresets');
        stylePresets.forEach(preset => {
            const button = createPresetButton(preset, 'style');
            if (preset.id === '21') {
                button.classList.add('active');
                document.getElementById('currentStyle').textContent = preset.name;
            }
            styleContainer.appendChild(button);
        });

        // 比例预设
        const ratioContainer = document.getElementById('ratioPresets');
        ratioPresets.forEach(preset => {
            const button = createPresetButton(preset, 'ratio');
            if (preset.id === '16:9') {
                button.classList.add('active');
                document.getElementById('currentRatio').textContent = preset.name;
            }
            ratioContainer.appendChild(button);
        });

        // 数量预设
        const countContainer = document.getElementById('countPresets');
        countPresets.forEach(preset => {
            const button = createPresetButton(preset, 'count');
            if (preset.id === '1') {
                button.classList.add('active');
                document.getElementById('currentCount').textContent = preset.name;
            }
            countContainer.appendChild(button);
        });
    }

    function createPresetButton(preset, type) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'preset-btn';
        button.dataset[type] = preset.id;

        if (type === 'ratio') {
            button.classList.add('ratio-preset');
            button.innerHTML = `
                <div class="ratio-visual"></div>
                <div class="preset-label">${preset.label}</div>
            `;
        } else if (type === 'count') {
            button.classList.add('count-preset');
            button.innerHTML = `
                <div class="preset-label">${preset.label}</div>
            `;
        } else {
            button.innerHTML = `
                <i class="${preset.icon || 'fas fa-palette'}"></i>
                <div class="preset-label">${preset.label}</div>
            `;
        }

        button.addEventListener('click', function () {
            const siblings = this.parentElement.querySelectorAll('.preset-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');

            document.getElementById(type).value = this.dataset[type];
            const currentElement = document.getElementById(`current${type.charAt(0).toUpperCase() + type.slice(1)}`);
            if (currentElement) {
                currentElement.textContent = preset.name;
            }
        });

        return button;
    }

    // 示例按钮点击事件
    document.querySelectorAll('.example-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            document.getElementById('prompt').value = this.dataset.prompt;
            document.getElementById('prompt').scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
            document.getElementById('prompt').focus();
        });
    });

    // 表单提交
    form.addEventListener('submit', async function (e) {
        e.preventDefault();

        const formData = {
            action: 'generate',
            prompt: document.getElementById('prompt').value.trim(),
            style: document.getElementById('style').value,
            picSize: document.getElementById('picSize').value,
            count: parseInt(document.getElementById('count').value) || 1
        };

        if (!formData.prompt) {
            showError('请输入图片描述');
            return;
        }

        showLoading();

        try {
            const response = await fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(formData)
            });

            const result = await response.json();
            hideLoading();

            if (result.code === 0 && result.data) {
                const taskData = result.data;

                if (taskData.status === 'success') {
                    displayResult(taskData);
                } else if (taskData.status === 'processing') {
                    showProcessingState(taskData.taskId, taskData.progress || 0, taskData.message);
                    pollTaskStatus(taskData.taskId);
                }
            } else {
                showError(`生成失败: ${result.msg || '未知错误'}`);
            }

        } catch (error) {
            hideLoading();
            showError(`网络错误: ${error.message}`);
        }
    });

    // 轮询任务状态
    function pollTaskStatus(taskId) {
        let pollCount = 0;
        const maxPolls = 18; // 最多轮询18次（约3分钟）

        const pollInterval = setInterval(() => {
            pollCount++;
            if (pollCount > maxPolls) {
                clearInterval(pollInterval);
                showProcessingState(taskId, 100, '图片生成超时，请稍后查询状态');
                return;
            }

            checkTaskStatus(taskId).then(data => {
                if (data.status === 'success') {
                    clearInterval(pollInterval);
                    displayResult(data);
                } else if (data.status === 'error') {
                    clearInterval(pollInterval);
                    showError(`任务失败: ${data.message || '未知错误'}`);
                } else if (data.status === 'processing') {
                    showProcessingState(taskId, data.progress || 0, data.message || '图片正在生成，请耐心等待...');
                }
            }).catch(error => {
                console.error('轮询失败:', error);
                if (pollCount > maxPolls) {
                    clearInterval(pollInterval);
                }
            });
        }, 10000); // 每10秒轮询一次
    }

    // 检查任务状态
    function checkTaskStatus(taskId) {
        return fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'task_status',
                taskId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0 && data.data) {
                    return data.data;
                } else {
                    throw new Error(data.msg || '状态查询失败');
                }
            });
    }

    // 手动检查状态
    function checkStatusAgain() {
        const taskId = document.getElementById('processingTaskId').textContent;
        if (taskId) {
            checkTaskStatus(taskId).then(data => {
                if (data.status === 'success') {
                    displayResult(data);
                } else if (data.status === 'error') {
                    showError(`任务失败: ${data.message || '未知错误'}`);
                } else {
                    showProcessingState(taskId, data.progress || 0, data.message || '图片仍在生成中');
                }
            }).catch(error => {
                showError(`状态查询失败: ${error.message}`);
            });
        }
    }

    // 查询历史任务
    function retrieveTask() {
        const taskId = taskIdInput.value.trim();
        if (!taskId) {
            showError('请输入Task ID');
            taskIdInput.focus();
            return;
        }
        retrieveTaskById(taskId);
    }

    // 通过Task ID查询任务
    function retrieveTaskById(taskId) {
        showProcessingState(taskId, 0, '正在查询任务状态...');

        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'task_status',
                taskId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0 && data.data) {
                    const taskData = data.data;

                    if (taskData.status === 'success') {
                        displayResult(taskData);
                    } else if (taskData.status === 'processing') {
                        showProcessingState(taskData.taskId, taskData.progress || 0, taskData.message || '图片正在生成，请稍候...');
                        pollTaskStatus(taskData.taskId);
                    } else if (taskData.status === 'error') {
                        showError(`任务失败: ${taskData.errorMessage || '未知错误'}`);
                    }
                } else {
                    showError(`查询失败: ${data.msg || '未知错误'}`);
                }
            })
            .catch(error => {
                showError(`查询失败: ${error.message}`);
            });
    }



    // 显示结果
    function displayResult(data) {
        hideError();
        initialState.style.display = 'none';
        processingState.style.display = 'none';
        imageContainer.style.display = 'flex';
        resultInfo.style.display = 'block';

        // 生成图片内容
        generateImageContent(data);
        // 生成信息内容
        generateInfoContent(data);

        imageContainer.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
        });
    }

    // 生成图片内容
    function generateImageContent(data) {
        let html = '';
        const count = data.count || 1;
        const imageUrls = data.imageUrls || (data.imageUrl ? [data.imageUrl] : []);

        if (count === 1 && imageUrls.length > 0) {
            const escapedUrl = imageUrls[0].replace(/'/g, "\\'");
            html = `<img src="${imageUrls[0]}" alt="生成的图片" class="single-image" onclick="openImageModal('${escapedUrl}')">`;
        } else if (imageUrls.length > 1) {
            html = '<div class="multi-image-grid">';
            imageUrls.forEach((url, index) => {
                const escapedUrl = url.replace(/'/g, "\\'");
                html += `
                    <div class="image-item">
                        <img src="${url}" alt="生成的图片 ${index + 1}" onclick="openImageModal('${escapedUrl}')">
                        <div class="image-info">图片 ${index + 1}</div>
                    </div>
                `;
            });
            html += '</div>';
        } else {
            html = `
                <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
                    <h3>未生成图片</h3>
                    <p>没有获取到图片数据</p>
                </div>
            `;
        }

        imageContainer.innerHTML = html;
    }

    // 生成信息内容
    function generateInfoContent(data) {
        const count = data.count || 1;
        let html = `
            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">任务ID：</span>
                    <span class="info-value">${data.taskId || ''}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">提示词：</span>
                    <span class="info-value">${data.prompt || 'N/A'}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">艺术风格：</span>
                    <span class="info-value">${data.style || '21'}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">图片比例：</span>
                    <span class="info-value">${data.picSize || '16:9'}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">生成数量：</span>
                    <span class="info-value">${count} 张</span>
                </div>
                <div class="info-item">
                    <span class="info-label">实际生成：</span>
                    <span class="info-value">${(data.imageUrls || []).length} 张</span>
                </div>
                <div class="info-item">
                    <span class="info-label">生成状态：</span>
                    <span class="info-value">${data.status || 'completed'}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">进度：</span>
                    <span class="info-value">${data.progress || 100}%</span>
                </div>
            </div>
            <div class="result-actions">
                <button type="button" class="btn btn-secondary" onclick="copyTaskId('${data.taskId || ''}')">
                    <i class="fas fa-copy"></i> 复制Task ID
                </button>
            </div>
        `;

        resultInfo.innerHTML = html;
    }

    // 复制Task ID
    function copyTaskId(taskId) {
        if (taskId) {
            navigator.clipboard.writeText(taskId).then(() => {
                alert('Task ID 已复制到剪贴板');
            });
        }
    }

    // 复制处理中的Task ID
    function copyProcessingTaskId() {
        const taskId = document.getElementById('processingTaskId').textContent;
        if (taskId) {
            navigator.clipboard.writeText(taskId).then(() => {
                alert('Task ID 已复制到剪贴板');
            });
        }
    }

    // 显示错误
    function showError(message) {
        errorMessage.textContent = message;
        errorAlert.style.display = 'block';
        initialState.style.display = 'none';
        imageContainer.style.display = 'none';
        resultInfo.style.display = 'none';
        processingState.style.display = 'none';
    }

    // 隐藏错误
    function hideError() {
        errorAlert.style.display = 'none';
    }

    // 显示加载状态
    function showLoading() {
        loadingOverlay.classList.add('active');
        generateBtn.disabled = true;
        generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
    }

    // 隐藏加载状态
    function hideLoading() {
        loadingOverlay.classList.remove('active');
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> 开始生成图片';
    }

    // 加载更多历史
    function loadMoreHistory() {
        alert('加载更多功能待实现');
    }

    // 重试任务
    function retryTask() {
        alert('重试功能待实现');
    }

    // 初始化模态框事件
    function initModalEvents() {
        modalCloseBtn.addEventListener('click', closeImageModal);
        imageModal.addEventListener('click', function (e) {
            if (e.target === imageModal) {
                closeImageModal();
            }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && imageModal.classList.contains('active')) {
                closeImageModal();
            }
        });
    }

    // 初始化
    initPresets();
    initModalEvents();

    // Task ID输入框回车查询
    if (taskIdInput) {
        taskIdInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                retrieveTask();
            }
        });
    }

    // 显示主内容区域
    const pageContent = document.getElementById('pageContent');
    if (pageContent) {
        pageContent.style.display = 'block';
    }
});

// 通过Task ID查询文生图任务
function retrieveTaskByTextId(taskId) {
    // 直接显示处理状态，避免函数依赖
    const errorAlert = document.getElementById('errorAlert');
    const initialState = document.getElementById('initialState');
    const imageContainer = document.getElementById('imageContainer');
    const resultInfo = document.getElementById('resultInfo');
    const processingState = document.getElementById('processingState');

    // 隐藏错误
    if (errorAlert) errorAlert.style.display = 'none';

    // 显示处理中状态
    if (initialState) initialState.style.display = 'none';
    if (imageContainer) imageContainer.style.display = 'none';
    if (resultInfo) resultInfo.style.display = 'none';
    if (processingState) processingState.style.display = 'block';

    // 设置任务ID
    const processingTaskId = document.getElementById('processingTaskId');
    if (processingTaskId && taskId) {
        processingTaskId.textContent = taskId;
    }

    // 设置消息
    const processingMessage = document.getElementById('processingMessage');
    if (processingMessage) {
        processingMessage.textContent = '正在查询任务状态...';
    }

    // 重置进度条
    const progressFill = document.getElementById('progressFill');
    if (progressFill) {
        progressFill.style.width = '0%';
    }

    // 发送请求查询任务状态
    fetch('', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            action: 'task_status',
            taskId
        })
    })
        .then(response => response.json())
        .then(data => {
            if (data.code === 0 && data.data) {
                const taskData = data.data;

                if (taskData.status === 'success') {
                    // 直接调用displayResult显示结果
                    displayResult(taskData);
                } else if (taskData.status === 'processing') {
                    // 更新处理状态
                    if (processingMessage) {
                        processingMessage.textContent = taskData.message || '图片正在生成，请稍候...';
                    }
                    if (progressFill && taskData.progress) {
                        progressFill.style.width = taskData.progress + '%';
                    }

                    // 开始轮询
                    if (typeof pollTaskStatus === 'function') {
                        pollTaskStatus(taskData.taskId);
                    } else {
                        // 如果pollTaskStatus未定义，提供一个简单的轮询
                        setTimeout(() => {
                            retrieveTaskByTextId(taskId);
                        }, 5000);
                    }
                } else if (taskData.status === 'error') {
                    // 显示错误
                    const errorMessage = document.getElementById('errorMessage');
                    const errorAlert = document.getElementById('errorAlert');
                    if (errorMessage && errorAlert) {
                        errorMessage.textContent = `任务失败: ${taskData.errorMessage || '未知错误'}`;
                        errorAlert.style.display = 'block';
                    }
                }
            } else {
                // 显示错误
                const errorMessage = document.getElementById('errorMessage');
                const errorAlert = document.getElementById('errorAlert');
                if (errorMessage && errorAlert) {
                    errorMessage.textContent = `查询失败: ${data.msg || '未知错误'}`;
                    errorAlert.style.display = 'block';
                }
            }
        })
        .catch(error => {
            // 显示错误
            const errorMessage = document.getElementById('errorMessage');
            const errorAlert = document.getElementById('errorAlert');
            if (errorMessage && errorAlert) {
                errorMessage.textContent = `查询失败: ${error.message}`;
                errorAlert.style.display = 'block';
            }
        });
}

// 加载更多历史
function loadMoreTextHistory() {
    alert('加载更多功能待实现');
}
// 显示处理中状态
function showProcessingState(taskId, progress = 0, message = '图片正在生成，请耐心等待...') {
    hideError();
    initialState.style.display = 'none';
    imageContainer.style.display = 'none';
    resultInfo.style.display = 'none';
    processingState.style.display = 'block';

    if (taskId) {
        document.getElementById('processingTaskId').textContent = taskId;
    }

    if (message) {
        document.getElementById('processingMessage').textContent = message;
    }

    if (progress >= 0) {
        const progressFill = document.getElementById('progressFill');
        if (progressFill) {
            progressFill.style.width = progress + '%';
        }
    }
}

// 手动检查状态
function checkStatusAgain() {
    const taskId = document.getElementById('processingTaskId')?.textContent;
    if (taskId) {
        fetch('', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'task_status',
                taskId
            })
        })
            .then(response => response.json())
            .then(data => {
                if (data.code === 0 && data.data) {
                    const taskData = data.data;

                    if (taskData.status === 'success') {
                        // 调用displayResult显示结果
                        if (typeof displayResult === 'function') {
                            displayResult(taskData);
                        }
                    } else if (taskData.status === 'error') {
                        // 显示错误
                        const errorMessage = document.getElementById('errorMessage');
                        const errorAlert = document.getElementById('errorAlert');
                        if (errorMessage && errorAlert) {
                            errorMessage.textContent = `任务失败: ${taskData.message || '未知错误'}`;
                            errorAlert.style.display = 'block';
                        }
                    } else {
                        // 仍在处理中
                        const processingMessage = document.getElementById('processingMessage');
                        const progressFill = document.getElementById('progressFill');
                        if (processingMessage) {
                            processingMessage.textContent = taskData.message || '图片仍在生成中，请稍后再试';
                        }
                        if (progressFill && taskData.progress) {
                            progressFill.style.width = taskData.progress + '%';
                        }
                        alert('图片仍在生成中，请稍后再试');
                    }
                } else {
                    alert(`状态查询失败: ${data.msg || '未知错误'}`);
                }
            })
            .catch(error => {
                alert(`状态查询失败: ${error.message}`);
            });
    }
}

// 显示结果
function displayResult(data) {
    const errorAlert = document.getElementById('errorAlert');
    const initialState = document.getElementById('initialState');
    const imageContainer = document.getElementById('imageContainer');
    const resultInfo = document.getElementById('resultInfo');
    const processingState = document.getElementById('processingState');

    // 隐藏其他状态
    if (errorAlert) errorAlert.style.display = 'none';
    if (initialState) initialState.style.display = 'none';
    if (processingState) processingState.style.display = 'none';

    // 显示结果区域
    if (imageContainer) {
        imageContainer.style.display = 'flex';
        imageContainer.style.opacity = '1';

        // 生成图片内容
        const count = data.count || 1;
        const imageUrls = data.imageUrls || (data.imageUrl ? [data.imageUrl] : []);

        let html = '';
        if (count === 1 && imageUrls.length > 0) {
            const escapedUrl = imageUrls[0].replace(/'/g, "\\'");
            html = `<img src="${imageUrls[0]}" alt="生成的图片" class="single-image" onclick="openImageModal('${escapedUrl}')">`;
        } else if (imageUrls.length > 1) {
            html = '<div class="multi-image-grid">';
            imageUrls.forEach((url, index) => {
                const escapedUrl = url.replace(/'/g, "\\'");
                html += `
            <div class="image-item">
                <img src="${url}" alt="生成的图片 ${index + 1}" onclick="openImageModal('${escapedUrl}')">
                <div class="image-info">图片 ${index + 1}</div>
            </div>
        `;
            });
            html += '</div>';
        } else {
            html = `
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3>未生成图片</h3>
            <p>没有获取到图片数据</p>
        </div>
    `;
        }

        imageContainer.innerHTML = html;
    }

    // 显示结果信息
    if (resultInfo) {
        resultInfo.style.display = 'block';

        const count = data.count || 1;
        let html = `
    <div class="info-grid">
        <div class="info-item">
            <span class="info-label">任务ID：</span>
            <span class="info-value">${data.taskId || ''}</span>
        </div>
        <div class="info-item">
            <span class="info-label">提示词：</span>
            <span class="info-value">${data.prompt || 'N/A'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">艺术风格：</span>
            <span class="info-value">${data.style || '21'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">图片比例：</span>
            <span class="info-value">${data.picSize || '16:9'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">生成数量：</span>
            <span class="info-value">${count} 张</span>
        </div>
        <div class="info-item">
            <span class="info-label">实际生成：</span>
            <span class="info-value">${(data.imageUrls || []).length} 张</span>
        </div>
        <div class="info-item">
            <span class="info-label">生成状态：</span>
            <span class="info-value">${data.status || 'completed'}</span>
        </div>
        <div class="info-item">
            <span class="info-label">进度：</span>
            <span class="info-value">${data.progress || 100}%</span>
        </div>
    </div>
    <div class="result-actions">
        <button type="button" class="btn btn-secondary" onclick="copyTaskId('${data.taskId || ''}')">
            <i class="fas fa-copy"></i> 复制Task ID
        </button>
    </div>
`;

        resultInfo.innerHTML = html;
    }
}
