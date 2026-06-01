document.addEventListener('DOMContentLoaded', function () {
    // 初始化变量 
    const scriptTextarea = document.getElementById('script');
    const submitBtn = document.getElementById('submit-btn');
    const resultDiv = document.getElementById('result');
    const loadingDiv = document.getElementById('loading');
    const progressContainer = document.getElementById('progress-container');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const progressInfo = document.getElementById('progress-info');
    const errorDiv = document.getElementById('error');
    const successDiv = document.getElementById('success');
    const historyList = document.getElementById('history-list');
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    const refreshHistoryBtn = document.getElementById('refresh-history-btn');
    const deleteAllBtn = document.getElementById('delete-all-btn');
    const confirmationDialog = document.getElementById('confirmation-dialog');
    const confirmationMessage = document.getElementById('confirmation-message');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');

    // 历史任务详情悬浮层相关元素
    const taskDetailDialog = document.getElementById('task-detail-dialog');
    const detailTaskId = document.getElementById('detail-task-id');
    const detailTotalRounds = document.getElementById('detail-total-rounds');
    const detailMessage = document.getElementById('detail-message');
    const detailContent = document.getElementById('detail-content');
    const closeTaskDetail = document.getElementById('close-task-detail');
    const closeTaskDetailBottom = document.getElementById('close-task-detail-bottom');
    const refreshTaskDetail = document.getElementById('refresh-task-detail');
    const detailProgressBar = document.getElementById('detail-progress-bar');
    const detailProgressText = document.getElementById('detail-progress-text');

    // 自动加载相关元素
    const autoLoadNotice = document.getElementById('auto-load-notice');
    const autoLoadText = document.getElementById('auto-load-text');

    // 新增：输入方式相关元素
    const textInputMethod = document.getElementById('textInputMethod');
    const fileInputMethod = document.getElementById('fileInputMethod');
    const textInputSection = document.getElementById('textInputSection');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const scriptsFileInput = document.getElementById('scriptsFile');
    const uploadedFileName = document.getElementById('uploadedFileName');

    let currentTaskId = null;
    let pollInterval = null;
    let taskToDelete = null;
    let currentDetailTaskId = null; // 当前详情页显示的任务ID
    let autoLoadInterval = null; // 自动加载轮询间隔
    let uploadedFileContent = ''; // 存储上传的文件内容

    // 初始化示例数据 
    scriptTextarea.value = ``;

    // 初始化历史任务 
    loadHistoryTasks();

    // 页面加载时自动检查最新任务并恢复正在进行的任务
    function checkAndResumeOngoingTask() {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        
        // 只允许demo用户（user_id=1）迁移旧数据，避免跨用户数据泄露
        if (tasks.length === 0 && window.currentUserId === 1) {
            const oldKey = 'scriptAnalysisTasks';
            const oldTasks = JSON.parse(localStorage.getItem(oldKey) || '[]');
            if (oldTasks.length > 0) {
                // 为旧任务添加user_id属性，标记为demo用户的任务
                const migratedTasks = oldTasks.map(task => ({
                    ...task,
                    user_id: 1 // 旧任务默认属于demo用户
                }));
                // 将旧任务迁移到新键名下
                localStorage.setItem(localStorageKey, JSON.stringify(migratedTasks));
                tasks = migratedTasks;
            }
        }
        
        // 过滤出当前用户有权访问的任务
        const filteredTasks = tasks.filter(task => {
            // 如果任务没有user_id属性，默认属于demo用户（user_id=1）
            const taskUserId = task.user_id || 1;
            return taskUserId === window.currentUserId;
        });
        
        if (filteredTasks.length === 0) {
            // 没有任务时启用提交按钮
            submitBtn.disabled = false;
            return;
        }
        
        // 按创建时间排序，获取最新的任务
        filteredTasks.sort((a, b) => new Date(b.created) - new Date(a.created));
        const latestTask = filteredTasks[0];
        
        // 只处理仍在进行中的任务
        if (latestTask.status === 'processing') {
            console.log('恢复正在进行的任务:', latestTask.id);
            currentTaskId = latestTask.id;
            
            // 显示加载状态
            loadingDiv.style.display = 'block';
            progressContainer.style.display = 'block';
            autoLoadNotice.style.display = 'block';
            progressInfo.textContent = '正在恢复之前的任务...';
            autoLoadText.textContent = `正在检查任务 ${latestTask.id.substring(0, 8)}... 的状态`;
            updateProgress(0);
            submitBtn.disabled = true;
            
            // 开始轮询任务状态，只使用一个轮询机制
            startPolling();
        } else {
            // 所有任务都已完成时启用提交按钮
            submitBtn.disabled = false;
        }
    }
    
    // 执行任务检查和恢复
    checkAndResumeOngoingTask();

    // 新增：输入方式切换事件
    textInputMethod.addEventListener('change', function () {
        if (this.checked) {
            showTextInputSection();
        }
    });

    fileInputMethod.addEventListener('change', function () {
        if (this.checked) {
            showFileUploadSection();
        }
    });

    // 新增：文件选择事件
    scriptsFileInput.addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (file) {
            // 检查文件类型
            if (!file.name.toLowerCase().endsWith('.txt')) {
                showError('请选择.txt格式的文本文件');
                scriptsFileInput.value = '';
                uploadedFileName.textContent = '';
                return;
            }

            // 显示文件名
            uploadedFileName.textContent = `已选择文件: ${file.name}`;

            // 读取文件内容
            const reader = new FileReader();
            reader.onload = function (e) {
                try {
                    const content = e.target.result;

                    // 检查文件大小
                    if (content.length > 300000) {
                        showError('文件内容超过300,000字符限制');
                        scriptsFileInput.value = '';
                        uploadedFileName.textContent = '';
                        return;
                    }

                    uploadedFileContent = content;

                    // 如果切换到文本输入模式，自动填充内容
                    if (textInputMethod.checked) {
                        scriptTextarea.value = content;
                    }
                } catch (error) {
                    showError('读取文件失败: ' + error.message);
                }
            };
            reader.onerror = function () {
                showError('读取文件时发生错误');
            };
            reader.readAsText(file);
        }
    });

    // 标签切换逻辑 
    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const tabId = this.getAttribute('data-tab');

            // 更新标签状态 
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // 显示对应内容 
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabId) {
                    content.classList.add('active');
                }
            });
        });
    });

    // 刷新历史按钮 
    refreshHistoryBtn.addEventListener('click', function () {
        loadHistoryTasks();
        showSuccess('历史任务列表已刷新');
    });

    // 转换为分镜按钮点击事件
    submitBtn.addEventListener('click', function () {
        let script = '';

        // 根据选择的输入方式获取剧本内容
        if (textInputMethod.checked) {
            script = scriptTextarea.value.trim();
        } else if (fileInputMethod.checked) {
            script = uploadedFileContent.trim();
        }

        if (!script) {
            showError('请输入剧本内容或上传剧本文件');
            return;
        }

        // 显示加载状态 
        loadingDiv.style.display = 'block';
        progressContainer.style.display = 'block';
        errorDiv.style.display = 'none';
        successDiv.style.display = 'none';
        progressInfo.textContent = '正在启动后台分析任务...';
        updateProgress(0);
        submitBtn.disabled = true;

        // 发送请求到后端 
        fetch('scripts_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                script: script
            })
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('提交失败: ' + data.error);
                    submitBtn.disabled = false;
                    return;
                }

                currentTaskId = data.task_id;
                progressInfo.textContent = '后台任务已启动，正在轮询结果...';
                updateProgress(10);

                // 保存任务信息到本地存储 
                saveTaskToHistory(data.task_id, script, 'processing');

                // 开始轮询任务状态 
                startPolling();
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                submitBtn.disabled = false;
                showError('请求失败: ' + error.message);
            });
    });

    // 全部删除按钮 
    deleteAllBtn.addEventListener('click', function () {
        showConfirmationDialog('all', null, '您确定要删除所有历史任务吗？此操作不可恢复。');
    });

    // 确认删除按钮 
    confirmDeleteBtn.addEventListener('click', function () {
        if (taskToDelete === 'all') {
            // 删除所有任务 
            // 使用包含用户ID的键名，确保本地任务与用户关联
            const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
            localStorage.removeItem(localStorageKey);
            loadHistoryTasks();
            hideConfirmationDialog();
            showSuccess('所有历史任务已删除');
            // 删除所有任务后，启用提交按钮
            submitBtn.disabled = false;
        } else {
            // 删除单个任务 
            deleteTask(taskToDelete);
            hideConfirmationDialog();
        }
    });

    // 取消删除按钮 
    cancelDeleteBtn.addEventListener('click', function () {
        hideConfirmationDialog();
    });

    // 关闭任务详情悬浮层
    closeTaskDetail.addEventListener('click', function () {
        taskDetailDialog.style.display = 'none';
    });

    // 关闭任务详情悬浮层（底部按钮）
    closeTaskDetailBottom.addEventListener('click', function () {
        taskDetailDialog.style.display = 'none';
    });

    // 刷新任务详情
    refreshTaskDetail.addEventListener('click', function () {
        if (currentDetailTaskId) {
            loadTaskDetail(currentDetailTaskId);
        }
    });

    // 新增：显示文本输入区域
    function showTextInputSection() {
        textInputSection.style.display = 'block';
        fileUploadSection.style.display = 'none';

        // 如果之前上传了文件，将文件内容填充到文本区域
        if (uploadedFileContent) {
            scriptTextarea.value = uploadedFileContent;
        }
    }

    // 新增：显示文件上传区域
    function showFileUploadSection() {
        textInputSection.style.display = 'none';
        fileUploadSection.style.display = 'block';
    }



    // 开始轮询任务状态 
    function startPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        pollInterval = setInterval(() => {
            checkTaskStatus(currentTaskId);
        }, 3000);
    }

    // 检查任务状态 
    function checkTaskStatus(taskId) {
        fetch(`scripts_api.php?task_id=${taskId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'completed' || (data.progress === 100 && data.current_round === data.total_rounds)) {
                    // 任务完成
                    clearInterval(pollInterval);
                    pollInterval = null;
                    loadingDiv.style.display = 'none';
                    autoLoadNotice.style.display = 'none';
                    submitBtn.disabled = false;
                    
                    // 更新任务状态 
                    updateTaskStatus(taskId, 'completed', data.content);
                    
                    showResult(data.content);
                    successDiv.style.display = 'block';
                    successDiv.textContent = '分析完成！结果已显示在右侧。';
                    
                    // 3秒后自动隐藏成功信息
                    setTimeout(() => {
                        successDiv.style.display = 'none';
                    }, 3000);
                } else if (data.status === 'error') {
                clearInterval(pollInterval);
                loadingDiv.style.display = 'none';
                autoLoadNotice.style.display = 'none';
                submitBtn.disabled = false;
                
                // 根据错误信息显示不同的错误提示
                if (data.error === 'Task file not found') {
                    showError('任务不存在或已被删除: ' + (data.message || '未知错误'));
                    
                    // 更新本地存储中的任务状态为error
                    updateTaskStatus(taskId, 'error', '');
                } else {
                    showError('分析失败: ' + (data.error || '未知错误'));
                    
                    // 更新本地存储中的任务状态为error
                    updateTaskStatus(taskId, 'error', '');
                }
            } else {
                    // 仍在处理中，更新进度信息 
                    progressInfo.textContent = data.message || '正在分析中...';
                    autoLoadText.textContent = `正在检查任务 ${taskId.substring(0, 8)}... ${data.message || '正在分析中...'}`;

                    // 保持提交按钮禁用状态
                    submitBtn.disabled = true;

                    // 使用服务器返回的准确progress字段
        if (data.progress !== undefined) {
            updateProgress(data.progress);
        } else if (data.total_rounds && data.current_round) {
            // 后备方案：根据总轮次和当前轮次计算进度百分比
            const progress = (data.current_round / data.total_rounds) * 100;
            updateProgress(progress);
        } else {
            // 如果没有轮次信息，使用原来的估算方法
            let progress = 10;
            if (data.message) {
                if (data.message.includes('第1轮')) progress = 20;
                else if (data.message.includes('第2轮')) progress = 40;
                else if (data.message.includes('第3轮')) progress = 60;
                else if (data.message.includes('第4轮')) progress = 80;
                else if (data.message.includes('完成') || data.message.includes('结束')) progress = 90;
            }
            updateProgress(progress);
        }

                    // 更新结果区域显示当前进度
                    showResult(data.content || '', data);
                }
            })
            .catch(error => {
                console.error('轮询失败:', error);
                // 网络错误时保持按钮禁用状态
                submitBtn.disabled = true;
            });
    }

    // 显示错误信息 
    function showError(message) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        successDiv.style.display = 'none';
        loadingDiv.style.display = 'none';
    }

    // 显示成功信息 
    function showSuccess(message) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
        errorDiv.style.display = 'none';
        loadingDiv.style.display = 'none';

        // 3秒后自动隐藏成功信息 
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 3000);
    }

    // 更新进度条
    function updateProgress(percent) {
        progressBar.style.width = percent + '%';
        progressText.textContent = Math.round(percent) + '%';
    }

    // 显示结果 
    function showResult(content, progressData) {
        if (progressData && progressData.status === 'processing') {
            // 如果有进度数据且任务正在进行中，显示详细进度信息
            const progress = Math.round(progressData.progress || 0);
            const message = progressData.message || '正在分析中...';
            
            // 获取交互历史
            const interactionHistory = progressData.interaction_history || [];
            
            // 分镜分析阶段，显示轮次信息
            const currentRound = progressData.current_round || 0;
            const totalRounds = progressData.total_rounds || '未知';
            const progressInfoHTML = `
                <div class="progress-info-row">
                    <span class="progress-label">当前进度:</span>
                    <span class="progress-value">${message}</span>
                </div>
                <div class="progress-info-row">
                    <span class="progress-label">已完成轮次:</span>
                    <span class="progress-value">${currentRound}/${totalRounds}</span>
                </div>
                <div class="progress-info-row">
                    <span class="progress-label">总进度:</span>
                    <span class="progress-value">${progress}%</span>
                </div>
            `;
            
            resultDiv.innerHTML = `
                <div class="progress-container-detailed">
                    <h3>分析任务正在进行中</h3>
                    ${progressInfoHTML}
                    <div class="progress-bar-container">
                        <div class="progress-bar-inner" style="width: ${progress}%;"></div>
                    </div>
                    
                    <!-- 详细交互历史 -->
                    <div class="interaction-history-section">
                        <h4>与分镜AI引擎AgenticEngine的交互历史</h4>
                        <div class="interaction-history">
                            ${interactionHistory.map((interaction, index) => `
                                <div class="interaction-item">
                                    <div class="interaction-header">
                                        <span class="interaction-round">第${index + 1}轮交互</span>
                                        <span class="interaction-status ${interaction.status === 'completed' ? 'completed' : interaction.status === 'error' ? 'error' : 'processing'}">
                                            ${interaction.status === 'completed' ? '✓ 完成' : interaction.status === 'error' ? '✗ 错误' : '⟳ 处理中'}
                                        </span>
                                    </div>
                                    <div class="interaction-details">
                                        <div class="interaction-timing">
                                            <span>开始时间: ${interaction.start_time || '未知'}</span>
                                            ${interaction.end_time ? `<span>结束时间: ${interaction.end_time}</span>` : ''}
                                            ${interaction.duration ? `<span>耗时: ${interaction.duration}秒</span>` : ''}
                                        </div>
                                        ${interaction.message ? `<div class="interaction-message">${interaction.message}</div>` : ''}
                                    </div>
                                </div>
                            `).join('')}
                            ${interactionHistory.length === 0 ? '<div class="empty-interaction">暂无交互记录</div>' : ''}
                        </div>
                    </div>
                    
                    ${content ? `<div class="content-preview">
                        <h4>当前分析内容:</h4>
                        <pre style="white-space: pre-wrap; font-family: inherit;">${content}</pre>
                    </div>` : ''}
                </div>
            `;
        } else {
            // 任务已完成或没有进度数据，显示完整结果
            resultDiv.innerHTML = `<pre style="white-space: pre-wrap; font-family: inherit;">${content}</pre>`;
        }
    }

    // 保存任务到历史记录 
    function saveTaskToHistory(taskId, script, status) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];

        const taskData = {
            id: taskId,
            script: script,
            status: status,
            created: new Date().toISOString(),
            user_id: window.currentUserId // 保存任务所属用户ID
        };

        // 检查任务是否已存在 
        const existingTaskIndex = tasks.findIndex(task => task.id === taskId);

        if (existingTaskIndex >= 0) {
            tasks[existingTaskIndex] = taskData;
        } else {
            tasks.push(taskData);
        }

        localStorage.setItem(localStorageKey, JSON.stringify(tasks));

        // 更新历史列表 
        loadHistoryTasks();
    }

    // 更新任务状态 
    function updateTaskStatus(taskId, status, content) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];

        const taskIndex = tasks.findIndex(task => task.id === taskId);

        if (taskIndex >= 0) {
            tasks[taskIndex].status = status;
            if (content) {
                tasks[taskIndex].content = content;
            }

            localStorage.setItem(localStorageKey, JSON.stringify(tasks));

            // 如果任务完成或失败，检查是否还有其他进行中的任务
            if (status === 'completed' || status === 'error') {
                checkIfAnyTaskProcessing();
            }
        }
    }

    // 检查是否有任何任务仍在处理中
    function checkIfAnyTaskProcessing() {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        
        // 只允许demo用户（user_id=1）迁移旧数据，避免跨用户数据泄露
        if (tasks.length === 0 && window.currentUserId === 1) {
            const oldKey = 'scriptAnalysisTasks';
            const oldTasks = JSON.parse(localStorage.getItem(oldKey) || '[]');
            if (oldTasks.length > 0) {
                // 将旧任务迁移到新键名下
                localStorage.setItem(localStorageKey, JSON.stringify(oldTasks));
                tasks = oldTasks;
            }
        }
        const hasProcessingTask = tasks.some(task => task.status === 'processing');

        // 如果没有进行中的任务，启用提交按钮
        if (!hasProcessingTask) {
            submitBtn.disabled = false;
        }
    }

    // 加载历史任务 
    function loadHistoryTasks() {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        
        // 只允许demo用户（user_id=1）迁移旧数据，避免跨用户数据泄露
        if (tasks.length === 0 && window.currentUserId === 1) {
            const oldKey = 'scriptAnalysisTasks';
            const oldTasks = JSON.parse(localStorage.getItem(oldKey) || '[]');
            if (oldTasks.length > 0) {
                // 为旧任务添加user_id属性，标记为demo用户的任务
                const migratedTasks = oldTasks.map(task => ({
                    ...task,
                    user_id: 1 // 旧任务默认属于demo用户
                }));
                // 将旧任务迁移到新键名下
                localStorage.setItem(localStorageKey, JSON.stringify(migratedTasks));
                tasks = migratedTasks;
            }
        }

        // 过滤出当前用户有权访问的任务
        const filteredTasks = tasks.filter(task => {
            // 如果任务没有user_id属性，默认属于demo用户（user_id=1）
            const taskUserId = task.user_id || 1;
            return taskUserId === window.currentUserId;
        });

        if (filteredTasks.length === 0) {
            historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
        } else {
            let html = '';
            filteredTasks.forEach(task => {
                const statusClass = getStatusClass(task.status);
                const statusText = getStatusText(task.status);
                // 确保task.script和task.id是字符串，防止substring调用失败
                const safeScript = task.script || '';
                const safeId = task.id || '';
                const taskPreview = safeScript.substring(0, 80) + (safeScript.length > 80 ? ' ' : '');
                const createdDate = new Date(task.created).toLocaleString();
                html += `
                <div class="history-item"> 
                    <div class="task-info">
                        <span class="task-id">任务编号：${safeId.substring(0, 80)} &nbsp;&nbsp;</span>
                        <span class="task-status ${statusClass}">
                            ${statusText}
                        </span>
                    </div>
                    
                    <!-- 移除了显示prompt的行 -->
                    <div class="task-actions">
                        <div class="task-actions-left">
                            ${createdDate}
                        </div>
                        <div class="task-actions-right">
                            <button class="task-delete-btn danger-button" data-task-id="${task.id}">删除</button>
                        </div>
                    </div>
                </div>`;
            });

            historyList.innerHTML = html;

            // 为历史任务项添加点击事件 
            document.querySelectorAll('.history-item').forEach(item => {
                item.addEventListener('click', function (e) {
                    if (!e.target.classList.contains('task-delete-btn')) {
                        const taskId = this.querySelector('.task-delete-btn').getAttribute('data-task-id');
                        const statusElement = this.querySelector('.task-status');
                        const status = statusElement.classList.contains('status-completed') ? 'completed' :
                            statusElement.classList.contains('status-error') ? 'error' : 'processing';

                        // 二次判断任务状态，确保显示最新信息
                        checkTaskStatusBeforeDisplay(taskId);
                    }
                });
            });

            // 为删除按钮添加事件 
            document.querySelectorAll('.task-delete-btn').forEach(btn => {
                btn.addEventListener('click', function (e) {
                    e.stopPropagation();
                    const taskId = this.getAttribute('data-task-id');
                    showConfirmationDialog('single', taskId, '您确定要删除这个任务吗？此操作不可恢复。');
                });
            });
        }

        // 加载历史任务后检查是否有进行中的任务
        checkIfAnyTaskProcessing();
    }

    // 获取状态类名 
    function getStatusClass(status) {
        switch (status) {
            case 'completed': return 'status-completed';
            case 'error': return 'status-error';
            default: return 'status-processing';
        }
    }

    // 获取状态文本 
    function getStatusText(status) {
        switch (status) {
            case 'completed': return '已完成';
            case 'error': return '失败';
            default: return '处理中';
        }
    }

    // 加载任务详情
    function loadTaskDetail(taskId) {
        currentDetailTaskId = taskId;

        fetch(`scripts_api.php?task_id=${taskId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                // 显示任务详情悬浮层
                detailTaskId.textContent = data.task_id;
                detailTotalRounds.textContent = data.total_rounds || '未知';
                detailMessage.textContent = data.message || '无进度信息';
                detailContent.value = data.content || '暂无内容';

                // 根据总轮次和当前轮次计算准确的进度百分比
                if (data.total_rounds && data.current_round) {
                    const progress = (data.current_round / data.total_rounds) * 100;
                    detailProgressBar.style.width = progress + '%';
                    detailProgressText.textContent = Math.round(progress) + '%';
                } else {
                    // 如果没有轮次信息，显示0%
                    detailProgressBar.style.width = '0%';
                    detailProgressText.textContent = '0%';
                }

                taskDetailDialog.style.display = 'flex';
            })
            .catch(error => {
                console.error('加载任务详情失败:', error);
                showError('加载任务详情失败: ' + error.message);
            });
    }

    // 检查任务状态并在适当时机显示结果
    function checkTaskStatusBeforeDisplay(taskId) {
        fetch(`scripts_api.php?task_id=${taskId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'completed') {
                    // 任务已完成，显示结果
                    showResult(data.content);
                    successDiv.style.display = 'block';
                    successDiv.textContent = '已加载历史任务结果';

                    // 更新本地存储中的任务状态和内容
                    updateTaskStatus(taskId, 'completed', data.content);
                } else if (data.status === 'error') {
                    showError('任务执行失败: ' + (data.error || '未知错误'));
                } else {
                    // 任务仍在处理中，显示详情悬浮层
                    loadTaskDetail(taskId);
                }
            })
            .catch(error => {
                console.error('检查任务状态失败:', error);
                showError('检查任务状态失败: ' + error.message);
            });
    }

    // 显示确认对话框 
    function showConfirmationDialog(type, taskId, message) {
        taskToDelete = type === 'all' ? 'all' : taskId;
        confirmationMessage.textContent = message;
        confirmationDialog.style.display = 'flex';
    }

    // 隐藏确认对话框 
    function hideConfirmationDialog() {
        confirmationDialog.style.display = 'none';
    }

    // 删除单个任务 
    function deleteTask(taskId) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        const filteredTasks = tasks.filter(task => task.id !== taskId);
        localStorage.setItem(localStorageKey, JSON.stringify(filteredTasks));
        loadHistoryTasks();
        showSuccess('任务已删除');

        // 如果删除的是当前正在轮询的任务，停止轮询
        if (currentTaskId === taskId) {
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            if (autoLoadInterval) {
                clearInterval(autoLoadInterval);
                autoLoadInterval = null;
            }
            autoLoadNotice.style.display = 'none';
        }

        // 删除任务后检查是否还有进行中的任务
        checkIfAnyTaskProcessing();
    }
});
