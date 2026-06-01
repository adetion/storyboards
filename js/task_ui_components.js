/**
 * 统一任务UI组件库
 * 提供任务相关的UI组件，包括任务输入表单、进度条、历史任务列表、任务统计等
 */

// 任务UI组件类
class TaskUIComponents {
    constructor(options = {}) {
        this.apiBaseUrl = options.apiBaseUrl || '/task_api.php';
        this.taskTypes = [];
        this.taskStatuses = [];
        this.taskStats = {};
        this.loadingStates = {};
        this.subscribers = [];
        this.refreshInterval = null;
        
        // 初始化WebSocket配置
        this.wsConfig = null;
        this.ws = null;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.isPaused = false;
        
        // 初始化组件
        this.loadTaskTypes();
        this.loadTaskStatuses();
        this.initEventListeners();
        
        // 如果提供了WebSocket配置，初始化WebSocket
        if (options.wsUrl) {
            this.initWebSocket({
                url: options.wsUrl,
                userId: options.userId
            });
        }
    }
    
    /**
     * 初始化事件监听器
     */
    initEventListeners() {
        // 监听任务状态变化事件
        document.addEventListener('task-status-changed', (e) => {
            this.notifySubscribers(e.detail);
        });
        
        // 监听页面可见性变化，优化WebSocket连接
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.pauseWebSocket();
            } else {
                this.resumeWebSocket();
            }
        });
    }
    
    /**
     * 初始化WebSocket连接
     * @param {Object} options 配置选项
     */
    initWebSocket(options = {}) {
        if (!window.WebSocket) {
            console.warn('浏览器不支持WebSocket，无法使用实时任务状态更新');
            return false;
        }
        
        this.wsConfig = {
            url: options.url || 'ws://' + window.location.hostname + ':9502',
            reconnectInterval: options.reconnectInterval || 3000,
            maxReconnectAttempts: options.maxReconnectAttempts || 5,
            userId: options.userId || null
        };
        
        this.ws = null;
        this.reconnectAttempts = 0;
        this.reconnectTimer = null;
        this.isPaused = false;
        
        this.connectWebSocket();
        return true;
    }
    
    /**
     * 连接WebSocket服务器
     */
    connectWebSocket() {
        try {
            // 检查wsConfig是否已初始化
            if (!this.wsConfig) {
                console.warn('WebSocket配置未初始化，跳过连接');
                return;
            }
            
            this.ws = new WebSocket(this.wsConfig.url);
            
            this.ws.onopen = () => {
                console.log('WebSocket连接已建立');
                this.reconnectAttempts = 0;
                
                // 注册用户ID
                if (this.wsConfig.userId) {
                    this.sendWebSocketMessage({
                        action: 'register',
                        user_id: this.wsConfig.userId
                    });
                    
                    // 订阅任务状态更新
                    this.sendWebSocketMessage({ action: 'subscribe' });
                }
            };
            
            this.ws.onmessage = (event) => {
                this.handleWebSocketMessage(event);
            };
            
            this.ws.onerror = (error) => {
                console.error('WebSocket错误:', error);
            };
            
            this.ws.onclose = () => {
                console.log('WebSocket连接已关闭');
                this.handleWebSocketClose();
            };
        } catch (error) {
            console.error('连接WebSocket失败:', error);
            this.handleWebSocketClose();
        }
    }
    
    /**
     * 处理WebSocket消息
     * @param {MessageEvent} event 消息事件
     */
    handleWebSocketMessage(event) {
        try {
            const data = JSON.parse(event.data);
            
            if (data.type === 'task_update') {
                // 处理任务状态更新
                this.processTaskUpdates(data.tasks);
            } else if (data.success !== undefined) {
                // 处理API响应
                console.log('WebSocket API响应:', data);
            } else {
                console.log('收到WebSocket消息:', data);
            }
        } catch (error) {
            console.error('解析WebSocket消息失败:', error);
        }
    }
    
    /**
     * 处理任务更新数据
     * @param {Array} tasks 任务列表
     */
    processTaskUpdates(tasks) {
        if (!Array.isArray(tasks)) return;
        
        tasks.forEach(task => {
            // 更新本地任务状态
            this.triggerTaskStatusChange(task);
        });
    }
    
    /**
     * 处理WebSocket连接关闭
     */
    handleWebSocketClose() {
        if (this.isPaused) return;
        
        // 检查wsConfig是否已初始化
        if (!this.wsConfig) {
            console.warn('WebSocket配置未初始化，跳过重连');
            return;
        }
        
        // 尝试重连
        if (this.reconnectAttempts < this.wsConfig.maxReconnectAttempts) {
            this.reconnectAttempts++;
            console.log(`尝试重新连接WebSocket... (${this.reconnectAttempts}/${this.wsConfig.maxReconnectAttempts})`);
            
            this.reconnectTimer = setTimeout(() => {
                this.connectWebSocket();
            }, this.wsConfig.reconnectInterval);
        } else {
            console.error('WebSocket重连失败，已达到最大尝试次数');
        }
    }
    
    /**
     * 发送WebSocket消息
     * @param {Object} message 消息内容
     */
    sendWebSocketMessage(message) {
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify(message));
            return true;
        }
        return false;
    }
    
    /**
     * 暂停WebSocket连接
     */
    pauseWebSocket() {
        this.isPaused = true;
        if (this.ws) {
            this.ws.close();
        }
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
    }
    
    /**
     * 恢复WebSocket连接
     */
    resumeWebSocket() {
        this.isPaused = false;
        // 检查wsConfig是否已初始化
        if (this.wsConfig) {
            this.connectWebSocket();
        } else {
            console.warn('WebSocket配置未初始化，跳过连接恢复');
        }
    }
    
    /**
     * 关闭WebSocket连接
     */
    closeWebSocket() {
        this.isPaused = true;
        if (this.reconnectTimer) {
            clearTimeout(this.reconnectTimer);
        }
        if (this.ws) {
            this.ws.close();
        }
    }
    
    /**
     * 加载任务类型列表
     */
    async loadTaskTypes() {
        try {
            // 直接使用默认任务类型，不依赖API调用
            this.taskTypes = [
                { value: 'novel_to_script', label: '小说转剧本' },
                { value: 'script_to_storyboard', label: '剧本转分镜' },
                { value: 'storyboard_management', label: '分镜管理' },
                { value: 'gushiban', label: '故事板' },
                { value: 'schedule', label: '拍摄计划' },
                { value: 'announcement', label: '拍摄通告' }
            ];
        } catch (error) {
            console.error('加载任务类型失败:', error);
            // 确保始终有默认值
            this.taskTypes = [
                { value: 'novel_to_script', label: '小说转剧本' },
                { value: 'script_to_storyboard', label: '剧本转分镜' },
                { value: 'storyboard_management', label: '分镜管理' },
                { value: 'gushiban', label: '故事板' },
                { value: 'schedule', label: '拍摄计划' },
                { value: 'announcement', label: '拍摄通告' }
            ];
        }
    }
    
    /**
     * 加载任务状态列表
     */
    async loadTaskStatuses() {
        try {
            // 直接使用默认任务状态，不依赖API调用
            this.taskStatuses = [
                { value: 0, label: '待处理' },
                { value: 1, label: '处理中' },
                { value: 2, label: '已完成' },
                { value: 3, label: '失败' },
                { value: 4, label: '已取消' }
            ];
        } catch (error) {
            console.error('加载任务状态失败:', error);
            // 确保始终有默认值
            this.taskStatuses = [
                { value: 0, label: '待处理' },
                { value: 1, label: '处理中' },
                { value: 2, label: '已完成' },
                { value: 3, label: '失败' },
                { value: 4, label: '已取消' }
            ];
        }
    }
    
    /**
     * 加载任务统计数据
     */
    async loadTaskStats(userId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/tasks/stats?user_id=${userId}`);
            const data = await response.json();
            if (data.success) {
                this.taskStats = data.data;
                return data.data;
            }
        } catch (error) {
            console.error('加载任务统计失败:', error);
        }
        return null;
    }
    
    /**
     * 订阅任务状态变化
     */
    subscribe(callback) {
        this.subscribers.push(callback);
    }
    
    /**
     * 取消订阅
     */
    unsubscribe(callback) {
        this.subscribers = this.subscribers.filter(subscriber => subscriber !== callback);
    }
    
    /**
     * 通知所有订阅者
     */
    notifySubscribers(data) {
        this.subscribers.forEach(subscriber => {
            try {
                subscriber(data);
            } catch (error) {
                console.error('通知订阅者失败:', error);
            }
        });
    }
    
    /**
     * 开始自动刷新任务状态
     */
    startAutoRefresh(interval = 5000) {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        this.refreshInterval = setInterval(() => {
            this.refreshTasks();
        }, interval);
    }
    
    /**
     * 停止自动刷新任务状态
     */
    stopAutoRefresh() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
            this.refreshInterval = null;
        }
    }
    
    /**
     * 刷新任务列表
     */
    async refreshTasks() {
        // 触发任务刷新事件
        const event = new CustomEvent('tasks-refreshed', { detail: {} });
        document.dispatchEvent(event);
        this.notifySubscribers({ action: 'tasks-refreshed' });
    }
    
    /**
     * 创建统一的任务输入表单
     * @param {Object} options 配置选项
     * @param {Function} onSubmit 提交回调函数
     * @returns {HTMLElement} 表单元素
     */
    createTaskForm(options = {}, onSubmit) {
        const form = document.createElement('div');
        form.className = 'task-form';
        
        // 表单HTML结构
        form.innerHTML = `
            <div class="form-section">
                <h3>${options.title || '任务输入'}</h3>
                
                <!-- 任务类型选择 -->
                ${options.showTaskType ? `
                <div class="form-group">
                    <label for="taskType">任务类型</label>
                    <select id="taskType" class="form-control">
                        ${this.taskTypes.map(type => `<option value="${type.value}">${type.label}</option>`).join('')}
                    </select>
                </div>
                ` : ''}
                
                <!-- 任务标题 -->
                ${options.showTitle ? `
                <div class="form-group">
                    <label for="taskTitle">任务标题</label>
                    <input type="text" id="taskTitle" class="form-control" placeholder="请输入任务标题">
                </div>
                ` : ''}
                
                <!-- 文本输入区域 -->
                ${options.showTextArea ? `
                <div class="form-group">
                    <label for="taskInput">${options.textAreaLabel || '输入内容'}</label>
                    <textarea id="taskInput" class="form-control" rows="10" placeholder="${options.textAreaPlaceholder || '请输入内容'}"></textarea>
                </div>
                ` : ''}
                
                <!-- 文件上传 -->
                ${options.showFileUpload ? `
                <div class="form-group">
                    <label for="taskFile">${options.fileUploadLabel || '上传文件'}</label>
                    <input type="file" id="taskFile" class="form-control" accept="${options.fileAccept || '.txt'}">
                    <div id="uploadedFileName" class="file-info"></div>
                </div>
                ` : ''}
                
                <!-- 积分提示 -->
                ${options.pointsInfo ? `
                <div class="points-info">
                    <i class="fas fa-coins"></i> ${options.pointsInfo}
                </div>
                ` : ''}
                
                <!-- 操作按钮 -->
                <div class="btn-group">
                    <button type="button" id="submitBtn" class="btn btn-primary">${options.submitText || '提交'}</button>
                    ${options.showCancel ? `<button type="button" id="cancelBtn" class="btn btn-secondary">取消</button>` : ''}
                </div>
                
                <!-- 加载状态 -->
                <div class="loading" id="taskLoading" style="display: none;">
                    <p>正在处理，请稍候...</p>
                    <div class="progress-container">
                        <div class="progress-bar">
                            <div class="progress-text">0%</div>
                        </div>
                    </div>
                    <div class="progress-info">准备开始...</div>
                </div>
                
                <!-- 消息显示 -->
                <div class="error" id="taskError" style="display: none;"></div>
                <div class="success" id="taskSuccess" style="display: none;"></div>
            </div>
        `;
        
        // 获取元素
        const submitBtn = form.querySelector('#submitBtn');
        const cancelBtn = form.querySelector('#cancelBtn');
        const taskLoading = form.querySelector('#taskLoading');
        const taskError = form.querySelector('#taskError');
        const taskSuccess = form.querySelector('#taskSuccess');
        const taskFile = form.querySelector('#taskFile');
        const uploadedFileName = form.querySelector('#uploadedFileName');
        
        // 文件上传处理
        if (taskFile && uploadedFileName) {
            taskFile.addEventListener('change', (e) => {
                const file = e.target.files[0];
                if (file) {
                    uploadedFileName.textContent = `已选择文件: ${file.name}`;
                } else {
                    uploadedFileName.textContent = '';
                }
            });
        }
        
        // 提交按钮事件
        submitBtn.addEventListener('click', async () => {
            // 隐藏消息
            taskError.style.display = 'none';
            taskSuccess.style.display = 'none';
            
            // 显示加载状态
            taskLoading.style.display = 'block';
            submitBtn.disabled = true;
            
            try {
                // 收集表单数据
                const taskTypeElement = form.querySelector('#taskType');
                const taskTitleElement = form.querySelector('#taskTitle');
                const taskInputElement = form.querySelector('#taskInput');
                
                const formData = {
                    task_type: options.defaultTaskType || (taskTypeElement ? taskTypeElement.value : '') || '',
                    title: (taskTitleElement ? taskTitleElement.value : '') || (options.defaultTitle || '新任务'),
                    input_data: {
                        text: (taskInputElement ? taskInputElement.value : '') || '',
                        file: null // 文件上传需要特殊处理
                    }
                };
                
                // 调用提交回调
                const result = await onSubmit(formData);
                
                if (result.success) {
                    taskSuccess.textContent = result.message || '任务提交成功';
                    taskSuccess.style.display = 'block';
                    
                    // 重置表单
                    if (options.resetOnSuccess) {
                        const taskInput = form.querySelector('#taskInput');
                        if (taskInput) taskInput.value = '';
                        const taskFile = form.querySelector('#taskFile');
                        if (taskFile) taskFile.value = '';
                        const uploadedFileName = form.querySelector('#uploadedFileName');
                        if (uploadedFileName) uploadedFileName.textContent = '';
                    }
                } else {
                    taskError.textContent = result.message || '任务提交失败';
                    taskError.style.display = 'block';
                }
            } catch (error) {
                taskError.textContent = `提交失败: ${error.message}`;
                taskError.style.display = 'block';
            } finally {
                // 隐藏加载状态
                taskLoading.style.display = 'none';
                submitBtn.disabled = false;
            }
        });
        
        // 取消按钮事件
        if (cancelBtn) {
            cancelBtn.addEventListener('click', () => {
                if (options.onCancel) {
                    options.onCancel();
                }
            });
        }
        
        return form;
    }
    
    /**
     * 创建统一的进度条组件
     * @param {Object} options 配置选项
     * @returns {HTMLElement} 进度条元素
     */
    createProgressBar(options = {}) {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'progress-container';
        progressContainer.style.display = options.visible ? 'block' : 'none';
        
        progressContainer.innerHTML = `
            <div class="progress-info">${options.info || '准备开始...'}</div>
            <div class="progress-bar-container">
                <div class="progress-bar" style="width: ${options.initialProgress || 0}%;">
                    <div class="progress-text">${options.initialProgress || 0}%</div>
                </div>
            </div>
        `;
        
        // 保存引用
        this.progressBar = progressContainer.querySelector('.progress-bar');
        this.progressText = progressContainer.querySelector('.progress-text');
        this.progressInfo = progressContainer.querySelector('.progress-info');
        
        return progressContainer;
    }
    
    /**
     * 更新进度条
     * @param {number} progress 进度值(0-100)
     * @param {string} info 进度信息
     */
    updateProgress(progress, info = null) {
        if (this.progressBar && this.progressText) {
            progress = Math.max(0, Math.min(100, progress));
            this.progressBar.style.width = `${progress}%`;
            this.progressText.textContent = `${progress}%`;
        }
        
        if (this.progressInfo && info) {
            this.progressInfo.textContent = info;
        }
    }
    
    /**
     * 创建统一的历史任务列表组件
     * @param {Object} options 配置选项
     * @param {Function} onTaskClick 任务点击回调
     * @returns {HTMLElement} 历史任务列表元素
     */
    async createHistoryTaskList(options = {}, onTaskClick) {
        const historyContainer = document.createElement('div');
        historyContainer.className = 'history-task-list';
        
        historyContainer.innerHTML = `
            <div class="history-header">
                <h3>${options.title || '历史任务'}</h3>
                <div class="action-buttons">
                    <button id="refreshHistoryBtn" class="btn btn-secondary">刷新</button>
                    <button id="deleteAllBtn" class="btn btn-danger">删除全部</button>
                </div>
            </div>
            <div id="historyList" class="history-list">
                <div class="loading">正在加载历史任务...</div>
            </div>
        `;
        
        // 获取元素
        const refreshBtn = historyContainer.querySelector('#refreshHistoryBtn');
        const deleteAllBtn = historyContainer.querySelector('#deleteAllBtn');
        const historyList = historyContainer.querySelector('#historyList');
        
        // 加载历史任务
        const loadHistory = async () => {
            historyList.innerHTML = '<div class="loading">正在加载历史任务...</div>';
            
            try {
                const response = await fetch(`${this.apiBaseUrl}/tasks?type=${options.taskType || ''}&limit=${options.limit || 20}&offset=0`);
                const data = await response.json();
                
                if (data.success && data.data.length > 0) {
                    historyList.innerHTML = '';
                    
                    data.data.forEach(task => {
                        const taskItem = this.createHistoryTaskItem(task, onTaskClick);
                        historyList.appendChild(taskItem);
                    });
                } else {
                    historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
                }
            } catch (error) {
                historyList.innerHTML = `<div class="error">加载失败: ${error.message}</div>`;
            }
        };
        
        // 刷新按钮事件
        refreshBtn.addEventListener('click', loadHistory);
        
        // 删除全部按钮事件
        deleteAllBtn.addEventListener('click', () => {
            if (confirm('确定要删除全部历史任务吗？此操作不可恢复。')) {
                // 这里需要调用API删除全部任务
                alert('删除全部功能待实现');
            }
        });
        
        // 初始加载
        loadHistory();
        
        return historyContainer;
    }
    
    /**
     * 创建历史任务项
     * @param {Object} task 任务数据
     * @param {Function} onTaskClick 任务点击回调
     * @returns {HTMLElement} 任务项元素
     */
    createHistoryTaskItem(task, onTaskClick) {
        const taskItem = document.createElement('div');
        taskItem.className = `history-task-item status-${task.status}`;
        
        // 获取任务状态文本
        const getStatusText = (status) => {
            const statusMap = {
                0: '待处理',
                1: '处理中',
                2: '已完成',
                3: '失败',
                4: '已取消'
            };
            return statusMap[status] || '未知状态';
        };
        
        // 获取任务类型文本
        const getTaskTypeText = (type) => {
            const typeMap = {
                'novel_to_script': '小说转剧本',
                'script_to_storyboard': '剧本转分镜',
                'storyboard_management': '分镜管理',
                'gushiban': '故事板',
                'schedule': '拍摄计划',
                'announcement': '拍摄通告'
            };
            return typeMap[type] || '未知类型';
        };
        
        taskItem.innerHTML = `
            <div class="task-item-header">
                <div class="task-title">${task.title}</div>
                <div class="task-meta">
                    <span class="task-type">${getTaskTypeText(task.task_type)}</span>
                    <span class="task-status">${getStatusText(task.status)}</span>
                    <span class="task-time">${new Date(task.created_at).toLocaleString()}</span>
                </div>
            </div>
            <div class="task-item-body">
                <div class="task-progress">
                    <div class="progress-bar-container small">
                        <div class="progress-bar" style="width: ${task.progress}%;"></div>
                    </div>
                    <span class="progress-text">${task.progress}%</span>
                </div>
                <div class="task-actions">
                    <button class="btn btn-small btn-primary view-task">查看</button>
                    <button class="btn btn-small btn-secondary edit-task">编辑</button>
                    <button class="btn btn-small btn-danger delete-task">删除</button>
                </div>
            </div>
        `;
        
        // 绑定事件
        taskItem.querySelector('.view-task').addEventListener('click', () => {
            onTaskClick(task, 'view');
        });
        
        taskItem.querySelector('.edit-task').addEventListener('click', () => {
            onTaskClick(task, 'edit');
        });
        
        taskItem.querySelector('.delete-task').addEventListener('click', () => {
            if (confirm('确定要删除这个任务吗？此操作不可恢复。')) {
                this.deleteTask(task.id);
                taskItem.remove();
            }
        });
        
        return taskItem;
    }
    
    /**
     * 删除任务
     * @param {number} taskId 任务ID
     */
    async deleteTask(taskId) {
        try {
            const response = await fetch(`${this.apiBaseUrl}/tasks/${taskId}`, {
                method: 'DELETE'
            });
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.message || '删除失败');
            }
        } catch (error) {
            console.error('删除任务失败:', error);
            alert(`删除任务失败: ${error.message}`);
        }
    }
    
    /**
     * 创建统一的任务详情对话框
     * @param {Object} options 配置选项
     * @returns {HTMLElement} 任务详情对话框元素
     */
    createTaskDetailDialog(options = {}) {
        const dialog = document.createElement('div');
        dialog.className = 'task-detail-dialog';
        dialog.style.display = 'none';
        
        dialog.innerHTML = `
            <div class="task-detail-content">
                <div class="task-detail-header">
                    <h3>${options.title || '任务详情'}</h3>
                    <button class="close-button">&times;</button>
                </div>
                <div class="task-info">
                    <p><strong>任务编号:</strong> <span id="detail-task-id"></span></p>
                    <p><strong>任务类型:</strong> <span id="detail-task-type"></span></p>
                    <p><strong>任务状态:</strong> <span id="detail-status"></span></p>
                    <p><strong>当前进度:</strong> <span id="detail-message"></span></p>
                    <div class="task-progress-container">
                        <div class="task-progress-bar" id="detail-progress-bar">
                            <div class="task-progress-text" id="detail-progress-text">0%</div>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label for="detail-content"><strong>任务内容:</strong></label>
                    <textarea id="detail-content" readonly></textarea>
                </div>
                <div class="task-detail-actions">
                    <button class="btn btn-secondary" id="refresh-task-detail">刷新</button>
                    <button class="btn btn-secondary" id="close-task-detail">关闭</button>
                </div>
            </div>
        `;
        
        // 绑定事件
        dialog.querySelector('.close-button').addEventListener('click', () => {
            dialog.style.display = 'none';
        });
        
        dialog.querySelector('#close-task-detail').addEventListener('click', () => {
            dialog.style.display = 'none';
        });
        
        // 刷新按钮事件
        const refreshBtn = dialog.querySelector('#refresh-task-detail');
        if (refreshBtn && options.onRefresh) {
            refreshBtn.addEventListener('click', options.onRefresh);
        }
        
        // 添加到页面
        document.body.appendChild(dialog);
        
        return dialog;
    }
    
    /**
     * 显示任务详情对话框
     * @param {HTMLElement} dialog 对话框元素
     * @param {Object} task 任务数据
     */
    showTaskDetail(dialog, task) {
        // 填充数据
        dialog.querySelector('#detail-task-id').textContent = task.id;
        dialog.querySelector('#detail-task-type').textContent = this.getTaskTypeText(task.task_type);
        dialog.querySelector('#detail-status').textContent = this.getStatusText(task.status);
        dialog.querySelector('#detail-message').textContent = task.status === 2 ? '任务已完成' : '任务处理中';
        
        // 更新进度条
        const progressBar = dialog.querySelector('#detail-progress-bar');
        const progressText = dialog.querySelector('#detail-progress-text');
        if (progressBar && progressText) {
            progressBar.style.width = `${task.progress}%`;
            progressText.textContent = `${task.progress}%`;
        }
        
        // 填充内容
        const contentTextarea = dialog.querySelector('#detail-content');
        if (contentTextarea) {
            contentTextarea.value = JSON.stringify(task.input_data, null, 2);
        }
        
        // 显示对话框
        dialog.style.display = 'flex';
    }
    
    /**
     * 获取任务类型文本
     * @param {string} taskType 任务类型
     * @returns {string} 任务类型文本
     */
    getTaskTypeText(taskType) {
        const typeMap = {
            'novel_to_script': '小说转剧本',
            'script_to_storyboard': '剧本转分镜',
            'storyboard_management': '分镜管理',
            'gushiban': '故事板',
            'schedule': '拍摄计划',
            'announcement': '拍摄通告'
        };
        return typeMap[taskType] || '未知类型';
    }
    
    /**
     * 获取状态文本
     * @param {number} status 状态码
     * @returns {string} 状态文本
     */
    getStatusText(status) {
        const statusMap = {
            0: '待处理',
            1: '处理中',
            2: '已完成',
            3: '失败',
            4: '已取消'
        };
        return statusMap[status] || '未知状态';
    }
}

// 初始化任务UI组件
if (typeof window !== 'undefined') {
    window.TaskUIComponents = TaskUIComponents;
}
