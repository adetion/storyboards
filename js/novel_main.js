// 页面加载完成后自动检测最新任务
document.addEventListener('DOMContentLoaded', function () {
    // 初始化变量 
    const novelText = document.getElementById('novelText');
    const novelFile = document.getElementById('novelFile');
    const textLength = document.getElementById('textLength');
    const convertBtn = document.getElementById('convertBtn');
    const resultDiv = document.getElementById('conversionResult');
    const loadingDiv = document.getElementById('progress');
    const progressContainer = document.getElementById('progressContainer');
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const progressInfo = document.getElementById('progressInfo');
    const errorDiv = document.getElementById('error');
    const successDiv = document.getElementById('success');
    const historyList = document.getElementById('historyList');
    const refreshHistoryBtn = document.getElementById('refreshHistoryBtn');
    const deleteAllBtn = document.getElementById('deleteAllBtn');
    const confirmationDialog = document.getElementById('confirmationDialog');
    const confirmationMessage = document.getElementById('confirmationMessage');
    const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
    const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');

    // 历史任务详情悬浮层相关元素
    const taskDetailDialog = document.getElementById('taskDetailDialog');
    const detailTaskId = document.getElementById('detail-task-id');
    const detailTotalRounds = document.getElementById('detail-total-rounds');
    const detailMessage = document.getElementById('detail-message');
    const detailContent = document.getElementById('detail-content');
    const closeTaskDetail = document.getElementById('closeTaskDetail');
    const closeTaskDetailBottom = document.getElementById('closeTaskDetailBottom');
    const refreshTaskDetail = document.getElementById('refreshTaskDetail');
    const detailProgressBar = document.getElementById('detail-progress-bar');
    const detailProgressText = document.getElementById('detail-progress-text');

    // 自动加载相关元素
    const autoLoadNotice = document.getElementById('autoLoadNotice');
    const autoLoadText = document.getElementById('autoLoadText');

    // 新增：输入方式相关元素
    const textInputMethod = document.getElementById('textInputMethod');
    const fileInputMethod = document.getElementById('fileInputMethod');
    const textInputSection = document.getElementById('textInputSection');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const uploadedFileName = document.getElementById('uploadedFileName');

    // 全局变量
    let currentTaskId = null;
    let pollInterval = null;
    let taskToDelete = null;
    let currentDetailTaskId = null; // 当前详情页显示的任务ID
    let autoLoadInterval = null; // 自动加载轮询间隔
    let uploadedFileContent = ''; // 存储上传的文件内容

    // 检查所有必要的DOM元素是否存在
    if (!novelText || !convertBtn || !resultDiv) {
        // console.error('必要的DOM元素未找到，请检查HTML结构');
        return;
    }

    // 标签页切换
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');

    tabs.forEach(tab => {
        tab.addEventListener('click', function () {
            const tabName = this.getAttribute('data-tab');

            // 更新激活的标签
            tabs.forEach(t => t.classList.remove('active'));
            this.classList.add('active');

            // 显示对应的内容
            tabContents.forEach(content => {
                content.classList.remove('active');
                if (content.id === tabName) {
                    content.classList.add('active');
                }
            });

            // 如果切换到历史任务标签，加载历史任务
            if (tabName === 'history') {
                loadHistoryTasks();
            }
        });
    });

    // 初始化历史任务 
    loadHistoryTasks();

    // 页面加载时自动检查最新任务
    autoLoadLatestTask();

    // 新增：输入方式切换事件
    if (textInputMethod && fileInputMethod) {
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
    }

    // 新增：文件选择事件
    if (novelFile) {
        novelFile.addEventListener('change', function (e) {
            const file = e.target.files[0];
            if (file) {
                // 检查文件类型
                if (!file.name.toLowerCase().endsWith('.txt')) {
                    showError('请选择.txt格式的文本文件');
                    novelFile.value = '';
                    if (uploadedFileName) uploadedFileName.textContent = '';
                    uploadedFileContent = ''; // 重置文件内容
                    return;
                }

                // 显示文件名
                if (uploadedFileName) uploadedFileName.textContent = `已选择文件: ${file.name}`;

                // 读取文件内容
                const reader = new FileReader();
                reader.onload = function (e) {
                    try {
                        const content = e.target.result;

                        // 检查文件大小
                        if (content.length > 5500000) {
                            showError('文件内容超过5500,000字符限制');
                            novelFile.value = '';
                            if (uploadedFileName) uploadedFileName.textContent = '';
                            uploadedFileContent = ''; // 重置文件内容
                            return;
                        }

                        uploadedFileContent = content;

                        // 无论当前选择的是哪种输入方式，都自动填充内容到textarea中
                        if (novelText) {
                            novelText.value = content;
                            novelText.dispatchEvent(new Event('input')); // 触发input事件更新字符计数
                        }
                    } catch (error) {
                        showError('读取文件失败: ' + error.message);
                        uploadedFileContent = ''; // 重置文件内容
                    }
                };
                reader.onerror = function () {
                    showError('读取文件时发生错误');
                    uploadedFileContent = ''; // 重置文件内容
                };
                reader.readAsText(file);
            }
        });
    }

    // 刷新历史按钮 
    if (refreshHistoryBtn) {
        refreshHistoryBtn.addEventListener('click', function () {
            loadHistoryTasks();
            showSuccess('历史任务列表已刷新');
        });
    }

    // 删除全部按钮
    if (deleteAllBtn) {
        deleteAllBtn.addEventListener('click', function () {
            // 使用自定义确认对话框，与删除按钮效果一致
            taskToDelete = 'all'; // 使用'all'标识删除全部任务
            if (confirmationMessage) confirmationMessage.textContent = '您确定要删除所有历史任务吗？此操作不可恢复。';
            if (confirmationDialog) confirmationDialog.style.display = 'flex';
        });
    }

    // 转换为剧本按钮点击事件
    if (convertBtn) {
        convertBtn.addEventListener('click', function () {
            let novel = '';

            // 根据选择的输入方式获取小说内容
            if (textInputMethod && textInputMethod.checked) {
                novel = novelText.value.trim();
            } else if (fileInputMethod && fileInputMethod.checked) {
                novel = uploadedFileContent.trim();
            }

            if (!novel) {
                showError('请输入小说内容或上传小说文件');
                return;
            }

            // 检查字符数
            if (novel.length < 100) {
                showError('小说内容至少需要100个字符');
                return;
            }

            if (novel.length > 5500000) {
                showError('小说内容不能超过5,500,000个字符');
                return;
            }

            // 显示加载状态 
            if (loadingDiv) loadingDiv.style.display = 'flex';
            if (errorDiv) errorDiv.style.display = 'none';
            if (successDiv) successDiv.style.display = 'none';
            if (progressInfo) progressInfo.textContent = '正在启动后台分析任务...';
            updateProgress(0);
            convertBtn.disabled = true;

            // 发送请求到后端 
            fetch('novel_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                credentials: 'same-origin', // 包含cookie，确保服务器能识别当前用户
                body: JSON.stringify({
                    action: 'start_conversion',
                    novel: novel
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
                        convertBtn.disabled = false;
                        if (loadingDiv) loadingDiv.style.display = 'none';
                        return;
                    }

                    currentTaskId = data.task_id;
            
                    // 显示任务ID
                    const processingTaskIdElement = document.getElementById('processingTaskId');
                    if (processingTaskIdElement) {
                        processingTaskIdElement.textContent = data.task_id;
                    }
            
                    if (progressInfo) progressInfo.textContent = '后台任务已启动，正在轮询结果...';
                    updateProgress(10);

                    // 保存任务信息到本地存储 
                    saveTaskToHistory(data.task_id, novel, 'processing');
                    
                    // 实时更新历史任务列表，无论当前处于哪个标签页
                    loadHistoryTasks();
                    
                    // 开始轮询任务状态
                    startPollingTaskStatus(data.task_id);
                })
                .catch(error => {
                    // console.error('Error:', error);
                    showError('提交失败: ' + error.message);
                    convertBtn.disabled = false;
                    if (loadingDiv) loadingDiv.style.display = 'none';
                });
        });
    }

    // 显示文本输入区域
    function showTextInputSection() {
        if (textInputSection) textInputSection.style.display = 'block';
        if (fileUploadSection) fileUploadSection.style.display = 'none';
    }

    // 显示文件上传区域
    function showFileUploadSection() {
        if (textInputSection) textInputSection.style.display = 'none';
        if (fileUploadSection) fileUploadSection.style.display = 'block';
    }

    // 更新进度条
    function updateProgress(percent) {
        if (progressBar) {
            progressBar.style.width = percent + '%';
            progressBar.textContent = percent + '%'; // 显示百分比文字
        }
    }
    
    // 刷新状态
    window.checkStatusAgain = function() {
        if (currentTaskId) {
            startPollingTaskStatus(currentTaskId);
        }
    }
    
    // 复制任务ID
    window.copyProcessingTaskId = function() {
        const taskIdElement = document.getElementById('processingTaskId');
        if (taskIdElement && taskIdElement.textContent) {
            navigator.clipboard.writeText(taskIdElement.textContent).then(function() {
                showSuccess('任务ID已复制到剪贴板');
            }).catch(function(err) {
                showError('复制失败: ' + err.message);
            });
        }
    }

    // 显示错误信息
    function showError(message) {
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        }
    }

    // 显示成功信息
    function showSuccess(message) {
        if (successDiv) {
            successDiv.textContent = message;
            successDiv.style.display = 'block';
            setTimeout(() => {
                successDiv.style.display = 'none';
            }, 3000);
        }
    }

    // 开始轮询任务状态
    function startPollingTaskStatus(taskId) {
        if (pollInterval) {
            clearInterval(pollInterval);
        }
        
        // 添加模拟进度更新，确保用户看到进度条在动
        let mockProgress = 0;
        let mockProgressInterval;
        
        // 启动模拟进度更新，每500ms增加1-3%，直到达到真实进度
        mockProgressInterval = setInterval(function() {
            mockProgress += Math.random() * 2 + 1;
            if (mockProgress > 95) mockProgress = 95; // 模拟进度不超过95%
            updateProgress(mockProgress);
        }, 500);

        pollInterval = setInterval(function () {
            fetch(`novel_api.php?task_id=${taskId}`, {
                method: 'GET',
                credentials: 'same-origin'
            })
                .then(response => response.json())
                .then(data => {
                    // 清除模拟进度更新
                    clearInterval(mockProgressInterval);
                    
                    // 更新当前轮次信息
                    const currentRoundInfo = document.getElementById('currentRoundInfo');
                    if (currentRoundInfo) {
                        if (data.current_round && data.total_rounds) {
                            currentRoundInfo.textContent = `第${data.current_round}/${data.total_rounds}轮`;
                        } else {
                            currentRoundInfo.textContent = '正在处理...';
                        }
                    }
                    
                    // 显示实时转换结果预览
                    const realTimeResult = document.getElementById('realTimeResult');
                    if (realTimeResult && data.content) {
                        realTimeResult.textContent = data.content;
                        // 滚动到底部，显示最新内容
                        realTimeResult.scrollTop = realTimeResult.scrollHeight;
                    }
                    
                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        if (progressInfo) progressInfo.textContent = '任务已完成';
                        updateProgress(100);

                        // 显示结果 - 传递整个data对象，而不是data.result
                        displayResult(data);

                        // 更新任务状态为完成，并保存结果 - 传递整个data对象
                        updateTaskStatus(taskId, 'completed', data);

                        // 重新加载历史任务
                        loadHistoryTasks();

                        if (convertBtn) convertBtn.disabled = false;
                        if (loadingDiv) loadingDiv.style.display = 'none';
                    } else if (data.status === 'failed') {
                        clearInterval(pollInterval);
                        if (progressInfo) progressInfo.textContent = '任务执行失败: ' + data.message;
                        updateProgress(0);
                        showError('任务执行失败: ' + data.message);
                        if (convertBtn) convertBtn.disabled = false;
                        if (loadingDiv) loadingDiv.style.display = 'none';

                        // 更新任务状态为失败
                        updateTaskStatus(taskId, 'failed');

                        // 重新加载历史任务
                        loadHistoryTasks();
                    } else {
                        // 更新进度信息
                        const progress = data.progress || 0;
                        updateProgress(progress);
                        if (progressInfo) {
                            progressInfo.textContent = data.message || '任务进行中...';

                            // 如果有轮次信息，更新任务详情
                            if (data.current_round && data.total_rounds) {
                                progressInfo.textContent = `第${data.current_round}/${data.total_rounds}轮 - ${data.message || '任务进行中...'}`;
                            }
                        }
                        
                        // 更新任务状态并刷新历史任务列表
                        updateTaskStatus(taskId, 'processing', data);
                        loadHistoryTasks();
                        
                        // 如果真实进度还没到95%，重新启动模拟进度
                        if (progress < 95) {
                            mockProgress = progress;
                            mockProgressInterval = setInterval(function() {
                                mockProgress += Math.random() * 2 + 1;
                                if (mockProgress > 95) mockProgress = 95;
                                updateProgress(mockProgress);
                            }, 500);
                        }
                    }
                })
                .catch(error => {
                    // console.error('Polling error:', error);
                    // 继续模拟进度，直到API恢复正常
                });
        }, 2000); // 每2秒轮询一次
    }

    // 显示结果
    function displayResult(result) {
        // 确定剧本内容
        let scriptContent = '';
        if (result) {
            // 支持多种结果格式
            if (result.script) {
                // 旧格式：结果在script字段中
                scriptContent = result.script;
            } else if (result.content) {
                // 新格式：结果在content字段中
                scriptContent = result.content;
            }
        }
        
        if (scriptContent) {
            if (resultDiv) {
                resultDiv.innerHTML = `
                    <div class="result-content">
                        <pre style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(scriptContent)}</pre>
                    </div>
                    <div class="action-buttons" style="margin-top: 15px;">
                        <button class="btn btn-primary" id="copyScriptBtn">
                            <span>复制剧本</span>
                        </button>
                        <button class="btn btn-primary" id="downloadScriptBtn">
                            <span>下载剧本</span>
                        </button>
                    </div>
                `;

                // 添加复制按钮事件
                const copyBtn = document.getElementById('copyScriptBtn');
                if (copyBtn) {
                    copyBtn.addEventListener('click', function () {
                        copyToClipboard(scriptContent);
                    });
                }

                // 添加下载按钮事件
                const downloadBtn = document.getElementById('downloadScriptBtn');
                if (downloadBtn) {
                    downloadBtn.addEventListener('click', function () {
                        downloadScript(scriptContent);
                    });
                }
            }

            showSuccess('小说已成功转换为剧本！');
        } else {
            if (resultDiv) resultDiv.innerHTML = '<p>未能生成有效的剧本结果</p>';
        }
    }

    // 转义HTML特殊字符
    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };

        return text.replace(/[&<>"']/g, function (m) { return map[m]; });
    }

    // 复制到剪贴板
    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function () {
            showSuccess('剧本已复制到剪贴板');
        }, function (err) {
            showError('复制失败: ' + err);
        });
    }

    // 下载剧本
    function downloadScript(script) {
        const blob = new Blob([script], { type: 'text/plain;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = '剧本_' + new Date().toISOString().slice(0, 10) + '.txt';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }

    // 保存任务到历史记录
    function saveTaskToHistory(taskId, content, status, result = null) {
        // 只在taskId有效时保存任务
        if (!taskId) {
            console.error('保存任务失败：taskId无效');
            return;
        }
        
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
        let history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
        
        // 检查是否有旧的本地任务（没有用户ID前缀），如果有则迁移到新键名下
        if (history.length === 0) {
            const oldKey = 'novelTasks';
            const oldHistory = JSON.parse(localStorage.getItem(oldKey) || '[]');
            if (oldHistory.length > 0) {
                // 将旧任务迁移到新键名下
                localStorage.setItem(localStorageKey, JSON.stringify(oldHistory));
                // 对于非demo用户，不删除旧键，避免影响其他用户
                // localStorage.removeItem(oldKey);
                history = oldHistory;
            }
        }

        // 检查任务是否已存在
        const existingIndex = history.findIndex(item => item.taskId === taskId);
        const taskItem = {
            taskId: taskId,
            timestamp: new Date().toISOString(),
            content: content.substring(0, 200) + (content.length > 200 ? '...' : ''), // 只保存前200个字符
            status: status,
            fullContent: content,
            result: result // 保存任务结果
        };

        if (existingIndex >= 0) {
            // 更新现有任务
            // 保留原有result值（如果新result为空）
            if (!result && history[existingIndex].result) {
                taskItem.result = history[existingIndex].result;
            }
            history[existingIndex] = taskItem;
            console.log('更新任务到历史记录:', taskId);
        } else {
            // 添加新任务
            history.unshift(taskItem); // 添加到数组开头
            console.log('添加新任务到历史记录:', taskId);

            // 限制历史记录数量
            if (history.length > 50) {
                history = history.slice(0, 50);
            }
        }

        localStorage.setItem(localStorageKey, JSON.stringify(history));
        console.log('历史任务已保存到localStorage，当前任务数量:', history.length);
    }

    // 更新任务状态
    function updateTaskStatus(taskId, status, result = null) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
        let history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
        const index = history.findIndex(item => item.taskId === taskId);
        if (index >= 0) {
            history[index].status = status;
            // 如果提供了result，保存它
            if (result !== null) {
                history[index].result = result;
            }
            localStorage.setItem(localStorageKey, JSON.stringify(history));
        }
    }

    // 加载历史任务
    async function loadHistoryTasks() {
        try {
            // 使用包含用户ID的键名，确保本地任务与用户关联
            const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
            
            // 从API获取最新任务列表
            const apiUrl = window.location.origin + '/auth_api.php?action=getUserTasks';
            const response = await fetch(apiUrl, {
                method: 'GET',
                credentials: 'same-origin'
            });
            const data = await response.json();
            
            if (data.success && data.data) {
                // 筛选出小说转剧本的任务
                const novelTasks = data.data.filter(task => task.task_type === 'novel_to_script');
                
                // 将任务转换为前端需要的格式
                const formattedTasks = novelTasks.map(task => {
                    // 转换状态码
                    let status = 'processing';
                    if (task.status === 0) {
                        status = 'failed';
                    } else if (task.status === 1) {
                        status = 'processing';
                    } else if (task.status === 2) {
                        status = 'completed';
                    }

                    let contentValue = '';
                    try {
                        const parsedData = JSON.parse(task.input_data || '{}');
                        contentValue = parsedData?.content || '';
                    } catch (error) {
                        contentValue = '';
                    }

                    const parsedData_out = JSON.parse(task.output_data || '{}');
                    const contentValue_out = parsedData_out?.content || '';
                    
                    return {
                        taskId: task.task_id ,
                        content: contentValue || '',
                        status: status,
                        timestamp: new Date(task.created_at).getTime(),
                        fullContent: contentValue_out || '',
                    };
                });
                
                // 保存到localStorage
                localStorage.setItem(localStorageKey, JSON.stringify(formattedTasks));
                
                // 显示或隐藏删除全部按钮
                if (deleteAllBtn) {
                    if (formattedTasks.length === 0) {
                        deleteAllBtn.style.display = 'none';
                    } else {
                        deleteAllBtn.style.display = 'inline-block';
                    }
                }
                
                if (historyList) {
                    if (formattedTasks.length === 0) {
                        historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
                        return;
                    }

                    historyList.innerHTML = '';
                    formattedTasks.forEach(task => {
                        // 跳过没有taskId的任务
                        if (!task.taskId) {
                            return;
                        }
                        
                        const taskElement = document.createElement('div');
                        taskElement.className = 'history-item';
                        taskElement.style.cursor = 'pointer';
                        taskElement.setAttribute('data-task-id', task.taskId);
                        // 截取前100字显示传入的小说内容
                        const novelPreview = task.content.length > 100 
                            ? escapeHtml(task.content.substring(0, 100)) + '...' 
                            : escapeHtml(task.content);
                        
                        taskElement.innerHTML = `
                            <div class="history-item-header">
                                <div class="task-id">任务ID: ${task.taskId.substring(16, 100) }</div>
                                <div class="task-status status-${task.status}">${getStatusText(task.status)}</div>
                            </div>
                            <div class="history-item-content">
                                <div class="task-preview">${novelPreview}</div>
                                <div class="task-time">${formatTime(task.timestamp)}</div>
                            </div>
                            <div class="history-item-actions">
                                <button class="btn-details" data-task-id="${task.taskId}" style="white-space: nowrap;">
                                    <i class="fas fa-info-circle"></i> 详情
                                </button>
                                <button class="btn-delete" data-task-id="${task.taskId}" style="white-space: nowrap;">
                                    <i class="fas fa-trash"></i> 删除
                                </button>
                            </div>
                        `;
                        
                        // 添加点击事件
                        taskElement.addEventListener('click', function(e) {
                            // 如果点击的是按钮，不触发显示结果
                            if (e.target.closest('button')) {
                                return;
                            }
                            
                            const clickedTaskId = this.getAttribute('data-task-id');
                            showHistoryTaskResult(clickedTaskId);
                        });
                        
                        historyList.appendChild(taskElement);
                    });
                }
            }
        } catch (error) {
            console.error('加载历史任务失败:', error);
            // 加载失败时，使用localStorage中的缓存数据
            // 使用包含用户ID的键名，确保本地任务与用户关联
            const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
            const history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
            
            // 显示或隐藏删除全部按钮
            if (deleteAllBtn) {
                if (history.length === 0) {
                    deleteAllBtn.style.display = 'none';
                } else {
                    deleteAllBtn.style.display = 'inline-block';
                }
            }
            
            if (historyList) {
                if (history.length === 0) {
                    historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
                    return;
                }

                historyList.innerHTML = '';
                history.forEach(task => {
                    // 跳过没有taskId的任务
                    if (!task.taskId) {
                        return;
                    }
                    
                    const taskElement = document.createElement('div');
                    taskElement.className = 'history-item';
                    taskElement.style.cursor = 'pointer';
                    taskElement.setAttribute('data-task-id', task.taskId);
                    // 安全获取taskId的前8个字符
                    const shortTaskId = task.taskId.substring(0, 8) + '...';
                    taskElement.innerHTML = `
                        <div class="history-item-header">
                            <div class="task-id">任务ID: ${shortTaskId}</div>
                            <div class="task-status status-${task.status}">${getStatusText(task.status)}</div>
                        </div>
                        <div class="history-item-content">
                            <div class="task-preview">${escapeHtml(task.content)}</div>
                            <div class="task-time">${formatTime(task.timestamp)}</div>
                        </div>
                        <div class="history-item-actions">
                            <button class="btn-details" data-task-id="${task.taskId}">
                                <i class="fas fa-info-circle"></i> 详情
                            </button>
                            <button class="btn-delete" data-task-id="${task.taskId}">
                                <i class="fas fa-trash"></i> 删除
                            </button>
                        </div>
                    `;
                    
                    // 添加点击事件
                    taskElement.addEventListener('click', function(e) {
                        // 如果点击的是按钮，不触发显示结果
                        if (e.target.closest('button')) {
                            return;
                        }
                        
                        const clickedTaskId = this.getAttribute('data-task-id');
                        showHistoryTaskResult(clickedTaskId);
                    });
                    
                    historyList.appendChild(taskElement);
                });
            }
        }

        // 添加详情按钮事件
        document.querySelectorAll('.btn-details').forEach(button => {
            button.addEventListener('click', function () {
                const taskId = this.getAttribute('data-task-id');
                showTaskDetails(taskId);
            });
        });

        // 添加删除按钮事件
        document.querySelectorAll('.btn-delete').forEach(button => {
            button.addEventListener('click', function () {
                const taskId = this.getAttribute('data-task-id');
                showDeleteConfirmation(taskId);
            });
        });
    }

    // 获取状态文本
    function getStatusText(status) {
        switch (status) {
            case 'completed': return '已完成';
            case 'processing': return '处理中';
            case 'failed': return '已失败';
            default: return '未知';
        }
    }

    // 格式化时间
    function formatTime(timestamp) {
        const date = new Date(timestamp);
        return date.toLocaleString('zh-CN');
    }

    // 显示任务详情
    async function showTaskDetails(taskId) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
        const history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
        const task = history.find(item => item.taskId === taskId);

        if (task && taskDetailDialog) {
            // 显示任务编号
            if (detailTaskId) detailTaskId.textContent = taskId;
            
            // 显示总轮次和当前进度
            if (detailTotalRounds) detailTotalRounds.textContent = '1'; // 小说转剧本通常只有1轮
            if (detailMessage) detailMessage.textContent = getStatusText(task.status);

            // 更新进度条
            let progressPercent = 0;
            if (task.status === 'completed') {
                progressPercent = 100;
            } else if (task.status === 'processing') {
                progressPercent = 50; // 处理中显示50%
            }

            if (detailProgressBar) detailProgressBar.style.width = progressPercent + '%';
            if (detailProgressText) detailProgressText.textContent = progressPercent + '%';

            // 获取实时转换后的剧本内容
            try {
                // 从API获取最新任务结果
                const response = await fetch(`novel_api.php?task_id=${taskId}`, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
                
                const data = await response.json();
                
                // 确定要显示的内容
                let scriptContent = '';
                if (data.content) {
                    // 新格式：结果在content字段中
                    scriptContent = data.content;
                } else if (data.script) {
                    // 旧格式：结果在script字段中
                    scriptContent = data.script;
                } else if (task.result && task.result.content) {
                    // 本地存储中有结果
                    scriptContent = task.result.content;
                } else if (task.result && task.result.script) {
                    // 本地存储中有旧格式结果
                    scriptContent = task.result.script;
                } else {
                    // 默认显示小说内容
                    scriptContent = task.fullContent || task.content;
                }
                
                if (detailContent) detailContent.value = scriptContent;
            } catch (error) {
                console.error('获取任务结果失败:', error);
                // 失败时显示小说内容
                if (detailContent) detailContent.value = task.fullContent || task.content;
            }

            currentDetailTaskId = taskId;
            taskDetailDialog.style.display = 'flex';
        }
    }

    // 显示删除确认对话框
    function showDeleteConfirmation(taskId) {
        taskToDelete = taskId;
        if (confirmationMessage) confirmationMessage.textContent = '您确定要删除这个任务吗？此操作不可恢复。';
        if (confirmationDialog) confirmationDialog.style.display = 'flex';
    }
    
    // 显示历史任务结果
    async function showHistoryTaskResult(taskId) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
        const history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
        let task = history.find(item => item.taskId === taskId);
        
        if (task) {
            // 切换到"小说分析"标签页
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabs.forEach(t => t.classList.remove('active'));
            tabContents.forEach(c => c.classList.remove('active'));
            
            const novelAnalysisTab = document.querySelector('[data-tab="new-task"]');
            const novelAnalysisContent = document.getElementById('new-task');
            if (novelAnalysisTab) novelAnalysisTab.classList.add('active');
            if (novelAnalysisContent) novelAnalysisContent.classList.add('active');
            
            // 如果任务已完成但没有result，或者result没有有效内容，从API获取最新结果
            if (task.status === 'completed' && (!task.result || !task.result.script && !task.result.content)) {
                try {
                    // 显示加载状态
                    if (loadingDiv) loadingDiv.style.display = 'flex';
                    if (progressInfo) progressInfo.textContent = '正在从服务器获取最新任务结果...';
                    if (convertBtn) convertBtn.disabled = true;
                    
                    // 显示任务ID
                    const processingTaskIdElement = document.getElementById('processingTaskId');
                    if (processingTaskIdElement) {
                        processingTaskIdElement.textContent = taskId;
                    }
                    
                    // 从API获取最新任务结果
                    const response = await fetch(`novel_api.php?task_id=${taskId}`, {
                        method: 'GET',
                        credentials: 'same-origin'
                    });
                    
                    const data = await response.json();
                    
                    // 更新localStorage中的任务
                    if (data.status === 'completed') {
                        updateTaskStatus(taskId, 'completed', data);
                        
                        // 重新获取任务
                        // 使用包含用户ID的键名，确保本地任务与用户关联
                        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
                        const updatedHistory = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
                        task = updatedHistory.find(item => item.taskId === taskId);
                    }
                } catch (error) {
                    console.error('获取任务结果失败:', error);
                } finally {
                    // 隐藏加载状态
                    if (loadingDiv) loadingDiv.style.display = 'none';
                    if (convertBtn) convertBtn.disabled = false;
                }
            }
            
            if (task.status === 'completed' && task.result) {
                // 显示任务结果
                let displayResultData;
                // 检查任务结果的格式
                if (task.result.script) {
                    // 已有的格式，直接使用
                    displayResultData = task.result;
                } else if (task.result.content) {
                    // API返回的格式，需要转换为displayResult期望的格式
                    displayResultData = {
                        script: task.result.content
                    };
                } else {
                    // 没有可用的结果数据
                    displayResultData = null;
                }
                
                if (displayResultData) {
                    displayResult(displayResultData);
                    showSuccess('已加载历史任务结果');
                } else {
                    // 任务没有结果
                    if (resultDiv) {
                        resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">该任务没有可用的转换结果</p>';
                    }
                    showError('该任务没有可用的转换结果');
                }
            } else if (task.status === 'processing') {
                // 任务正在处理中，开始轮询状态
                if (loadingDiv) loadingDiv.style.display = 'flex';
                if (progressInfo) progressInfo.textContent = '任务正在处理中，正在查询最新状态...';
                if (convertBtn) convertBtn.disabled = true;
                
                // 显示任务ID
                const processingTaskIdElement = document.getElementById('processingTaskId');
                if (processingTaskIdElement) {
                    processingTaskIdElement.textContent = taskId;
                }
                
                startPollingTaskStatus(taskId);
            } else {
                // 任务没有结果
                if (resultDiv) {
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">该任务没有可用的转换结果</p>';
                }
                showError('该任务没有可用的转换结果');
            }
        }
    }

    // 关闭任务详情对话框
    function closeTaskDetailDialog() {
        if (taskDetailDialog) taskDetailDialog.style.display = 'none';
        currentDetailTaskId = null;
    }

    // 自动加载最新任务
    function autoLoadLatestTask() {
        // 显示自动加载提示
        if (autoLoadNotice) autoLoadNotice.style.display = 'flex';

        // 检查是否有正在进行的任务
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
        const history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
        const processingTask = history.find(task => task.status === 'processing');

        if (processingTask && autoLoadText) {
            autoLoadText.textContent = '检测到未完成的任务，正在恢复状态...';

            // 模拟加载过程
            setTimeout(() => {
                if (autoLoadNotice) autoLoadNotice.style.display = 'none';

                // 开始轮询任务状态，实现断点续传
                startPollingTaskStatus(processingTask.taskId);
                
                // 显示加载状态
                if (loadingDiv) loadingDiv.style.display = 'flex';
                if (progressInfo) progressInfo.textContent = '正在恢复任务状态...';
                if (convertBtn) convertBtn.disabled = true;

                // 更新全局currentTaskId
                currentTaskId = processingTask.taskId;

                // 显示任务ID
                const processingTaskIdElement = document.getElementById('processingTaskId');
                if (processingTaskIdElement) {
                    processingTaskIdElement.textContent = processingTask.taskId;
                }

                // 显示任务详情
                showTaskDetails(processingTask.taskId);
            }, 1500);
        } else {
            // 没有正在进行的任务，隐藏提示
            setTimeout(() => {
                if (autoLoadNotice) autoLoadNotice.style.display = 'none';
            }, 1000);
        }
    }

    // 事件监听器
    if (closeTaskDetail) {
        closeTaskDetail.addEventListener('click', closeTaskDetailDialog);
    }

    if (closeTaskDetailBottom) {
        closeTaskDetailBottom.addEventListener('click', closeTaskDetailDialog);
    }

    // 刷新任务详情
    if (refreshTaskDetail) {
        refreshTaskDetail.addEventListener('click', function () {
            if (currentDetailTaskId) {
                showTaskDetails(currentDetailTaskId);
                showSuccess('任务详情已刷新');
            }
        });
    }

    // 确认删除
    if (confirmDeleteBtn) {
        confirmDeleteBtn.addEventListener('click', function () {
            if (taskToDelete) {
                if (taskToDelete === 'all') {
                    // 删除全部任务
                    fetch('novel_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            action: 'delete_all_tasks'
                        })
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.success) {
                                // 清空localStorage中的任务
                                // 使用包含用户ID的键名，确保本地任务与用户关联
                                const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
                                localStorage.removeItem(localStorageKey);
                                // 重新加载历史任务列表
                                loadHistoryTasks();
                                showSuccess('所有历史任务已删除');
                            } else {
                                showError('删除失败: ' + data.error);
                            }
                        })
                        .catch(error => {
                            showError('删除失败: ' + error.message);
                        })
                        .finally(() => {
                            // 隐藏确认对话框
                            if (confirmationDialog) confirmationDialog.style.display = 'none';
                            taskToDelete = null;
                        });
                } else {
                    // 删除单个任务
                            fetch('novel_api.php', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                },
                                credentials: 'same-origin',
                                body: JSON.stringify({
                                    action: 'delete_task',
                                    task_id: taskToDelete
                                })
                            })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.success) {
                                        // 从localStorage中删除任务
                                        // 使用包含用户ID的键名，确保本地任务与用户关联
                                        const localStorageKey = 'user_' + window.currentUserId + '_novelTasks';
                                        let history = JSON.parse(localStorage.getItem(localStorageKey) || '[]');
                                        history = history.filter(item => item.taskId !== taskToDelete);
                                        localStorage.setItem(localStorageKey, JSON.stringify(history));

                                        // 重新加载历史任务列表
                                        loadHistoryTasks();
                                        showSuccess('任务已删除');
                                    } else {
                                        showError('删除失败: ' + data.error);
                                    }
                                })
                                .catch(error => {
                                    showError('删除失败: ' + error.message);
                                })
                                .finally(() => {
                                    // 隐藏确认对话框
                                    if (confirmationDialog) confirmationDialog.style.display = 'none';
                                    taskToDelete = null;
                                });
                }
            }
        });
    }

    // 取消删除
    if (cancelDeleteBtn) {
        cancelDeleteBtn.addEventListener('click', function () {
            if (confirmationDialog) confirmationDialog.style.display = 'none';
            taskToDelete = null;
        });
    }

    // 点击对话框外部关闭
    window.addEventListener('click', function (event) {
        if (taskDetailDialog && event.target === taskDetailDialog) {
            closeTaskDetailDialog();
        }
        if (confirmationDialog && event.target === confirmationDialog) {
            if (confirmationDialog) confirmationDialog.style.display = 'none';
            taskToDelete = null;
        }
    });

    // 文本长度统计
    if (novelText) {
        novelText.addEventListener('input', function () {
            const length = this.value.length;
            if (textLength) textLength.textContent = length.toLocaleString();

            // 更新长度显示颜色
            if (textLength) {
                if (length < 100) {
                    textLength.style.color = 'var(--error-color)';
                } else if (length > 5500000) {
                    textLength.style.color = 'var(--error-color)';
                } else {
                    textLength.style.color = 'var(--success-color)';
                }
            }
        });
    }
});
