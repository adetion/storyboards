document.addEventListener('DOMContentLoaded', function() {
    const scriptTextarea = document.getElementById('script');
    const charCount = document.getElementById('charCount');
    const textInputMethod = document.getElementById('textInputMethod');
    const fileInputMethod = document.getElementById('fileInputMethod');
    const textInputSection = document.getElementById('textInputSection');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const spacesFile = document.getElementById('spacesFile');
    const uploadedFileName = document.getElementById('uploadedFileName');
    const submitBtn = document.getElementById('submit-btn');
    const loadingDiv = document.getElementById('loading');
    const progressBar = document.getElementById('progress-bar');
    const progressText = document.getElementById('progress-text');
    const progressInfo = document.getElementById('progress-info');
    const resultDiv = document.getElementById('result');
    const errorDiv = document.getElementById('error');
    const successDiv = document.getElementById('success');
    const autoLoadNotice = document.getElementById('auto-load-notice');
    const autoLoadText = document.getElementById('auto-load-text');
    const historyList = document.getElementById('history-list');
    const refreshHistoryBtn = document.getElementById('refresh-history-btn');
    const deleteAllBtn = document.getElementById('delete-all-btn');
    const confirmationDialog = document.getElementById('confirmation-dialog');
    const confirmationMessage = document.getElementById('confirmation-message');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    const tabs = document.querySelectorAll('.tab');
    const tabContents = document.querySelectorAll('.tab-content');
    
    // 图片上传模态框元素
    const imageUploadModal = document.getElementById('imageUploadModal');
    const imageUploadModalClose = document.getElementById('imageUploadModalClose');
    const imageFile = document.getElementById('imageFile');
    const uploadedImageName = document.getElementById('uploadedImageName');
    const imageUrlInput = document.getElementById('imageUrlInput');
    const imageUploadCancelBtn = document.getElementById('imageUploadCancelBtn');
    const imageUploadSubmitBtn = document.getElementById('imageUploadSubmitBtn');
    const sceneNameInput = document.getElementById('sceneName');
    const previewSection = document.getElementById('preview-section');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    
    // 图片查看模态框元素
    const imageViewModal = document.getElementById('imageViewModal');
    const imageViewModalClose = document.getElementById('imageViewModalClose');
    const imageGallery = document.getElementById('image-gallery');
    const imageViewCloseBtn = document.getElementById('imageViewCloseBtn');

    let currentTaskId = null;
    let pollInterval = null;
    let uploadedFileContent = null;
    let currentSceneId = null;
    let uploadedImageFile = null;

    const MAX_TEXT_LENGTH = 300000;

    function init() {
        setupEventListeners();
        setupTabSwitching();
        loadHistory();
        loadLatestTaskSpaces();
    }

    function setupEventListeners() {
        textInputMethod.addEventListener('change', handleInputMethodChange);
        fileInputMethod.addEventListener('change', handleInputMethodChange);
        scriptTextarea.addEventListener('input', handleTextInput);
        spacesFile.addEventListener('change', handleFileUpload);
        submitBtn.addEventListener('click', handleSubmit);
        refreshHistoryBtn.addEventListener('click', loadHistory);
        deleteAllBtn.addEventListener('click', handleDeleteAll);
        cancelDeleteBtn.addEventListener('click', hideConfirmationDialog);
        
        // 图片上传模态框事件
        if (imageUploadModalClose) {
            imageUploadModalClose.addEventListener('click', hideImageUploadModal);
        }
        if (imageFile) {
            imageFile.addEventListener('change', handleImageFileUpload);
        }
        if (imageUploadCancelBtn) {
            imageUploadCancelBtn.addEventListener('click', hideImageUploadModal);
        }
        if (imageUploadSubmitBtn) {
            imageUploadSubmitBtn.addEventListener('click', handleImageUploadSubmit);
        }
        if (imageUrlInput) {
            imageUrlInput.addEventListener('input', handleImageUrlInput);
        }
        
        // 图片查看模态框事件
        if (imageViewModalClose) {
            imageViewModalClose.addEventListener('click', hideImageViewModal);
        }
        if (imageViewCloseBtn) {
            imageViewCloseBtn.addEventListener('click', hideImageViewModal);
        }
    }

    function setupTabSwitching() {
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                tabContents.forEach(content => {
                    content.classList.remove('active');
                    if (content.id === tabId) {
                        content.classList.add('active');
                    }
                });
            });
        });
    }

    function handleInputMethodChange(e) {
        if (textInputMethod.checked) {
            textInputSection.style.display = 'block';
            fileUploadSection.style.display = 'none';
        } else {
            textInputSection.style.display = 'none';
            fileUploadSection.style.display = 'block';
        }
    }

    function handleTextInput(e) {
        const text = e.target.value;
        const length = text.length;
        charCount.textContent = length.toLocaleString();
        
        if (length > MAX_TEXT_LENGTH) {
            charCount.style.color = '#e74c3c';
            submitBtn.disabled = true;
            showError(`文本长度超过限制（${MAX_TEXT_LENGTH.toLocaleString()}字符），请缩短文本。`);
        } else {
            charCount.style.color = '#2ecc71';
            submitBtn.disabled = false;
            hideError();
        }
    }

    function handleFileUpload(e) {
        const file = e.target.files[0];
        
        if (!file) {
            uploadedFileName.textContent = '';
            uploadedFileContent = null;
            return;
        }

        if (file.type !== 'text/plain') {
            showError('请上传.txt格式的文本文件');
            spacesFile.value = '';
            uploadedFileName.textContent = '';
            uploadedFileContent = null;
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            
            if (content.length > MAX_TEXT_LENGTH) {
                showError(`文件内容过长（${content.length.toLocaleString()}字符），请缩短至${MAX_TEXT_LENGTH.toLocaleString()}字符以内`);
                spacesFile.value = '';
                uploadedFileName.textContent = '';
                uploadedFileContent = null;
                return;
            }

            uploadedFileName.textContent = `已选择: ${file.name} (${content.length.toLocaleString()}字符)`;
            uploadedFileContent = content;
            hideError();
        };

        reader.readAsText(file);
    }

    function handleSubmit(e) {
        e.preventDefault();
        
        let script = '';
        
        if (textInputMethod.checked) {
            script = scriptTextarea.value.trim();
        } else {
            script = uploadedFileContent;
        }

        if (!script) {
            showError('请输入或上传剧本内容');
            return;
        }

        if (script.length > MAX_TEXT_LENGTH) {
            showError(`文本长度超过限制（${MAX_TEXT_LENGTH.toLocaleString()}字符），请缩短文本。`);
            return;
        }

        submitBtn.disabled = true;
        hideError();
        hideSuccess();
        loadingDiv.style.display = 'block';
        progressBar.style.width = '0%';
        progressText.textContent = '0%';
        progressInfo.textContent = '正在提交任务...';
        resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">正在分析时空场景信息...</p>';

        fetch('spaces_api.php', {
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
                showError(data.error);
                submitBtn.disabled = false;
                loadingDiv.style.display = 'none';
                return;
            }

            currentTaskId = data.task_id;
            progressInfo.textContent = '任务已创建，正在分析时空场景信息...';
            startPolling();
        })
        .catch(error => {
            loadingDiv.style.display = 'none';
            submitBtn.disabled = false;
            showError('请求失败: ' + error.message);
        });
    }

    function startPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
        }

        pollInterval = setInterval(() => {
            if (!currentTaskId) {
                return;
            }

            fetch(`spaces_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('网络响应不正常');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.status === 'completed') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        loadingDiv.style.display = 'none';
                        submitBtn.disabled = false;
                        
                        showResult(data.scenes);
                        successDiv.style.display = 'block';
                        successDiv.textContent = '时空场景分析完成！结果已显示在上方。';
                        
                        setTimeout(() => {
                            successDiv.style.display = 'none';
                        }, 3000);
                        
                        loadHistory();
                    } else if (data.status === 'error') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        loadingDiv.style.display = 'none';
                        submitBtn.disabled = false;
                        showError('时空场景分析失败: ' + (data.error || data.message || '未知错误'));
                    } else if (data.status === 'processing') {
                        updateProgress(data);
                    }
                })
                .catch(error => {
                    console.error('轮询失败:', error);
                });
        }, 3000);
    }

    function updateProgress(data) {
        const progress = Math.round(data.progress || 0);
        const message = data.message || '正在分析中...';
        
        progressBar.style.width = `${progress}%`;
        progressText.textContent = `${progress}%`;
        progressInfo.textContent = message;

        if (data.scenes && data.scenes.length > 0) {
            showResult(data.scenes);
        }
    }

    function showResult(scenes) {
        if (!scenes || scenes.length === 0) {
            resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无时空场景数据</p>';
            return;
        }

        let html = '<div class="character-table-container">';
        html += '<table class="character-table">';
        
        // 表头
        html += `
            <thead>
                <tr>
                    <th style="width: 5%;">编号</th>
                    <th style="width: 30%;">时空场景名称</th>
                    <th style="width: 30%;">描述</th>
                    <th style="width: 25%;">场景图</th>
                    <th style="display: none;">图片管理</th>
                    <th style="width: 10%;">操作</th>
                </tr>
            </thead>
        `;
        
        // 表体
        html += '<tbody>';
        
        scenes.forEach(scene => {
            // 检查是否有图片
            const hasImages = scene.imageUrl && scene.imageUrl !== '';
            const imageCount = hasImages ? JSON.parse(scene.imageUrl).length : 0;
            
            // 生成场景图按钮文本
            const generateBtnText = hasImages 
                ? '重新生成' 
                : '生成场景图';
            
            html += `
                <tr data-scene-id="${scene.scene_id}">
                    <td class="character-number">${scene.scene_id}</td>
                    <td class="character-name">${scene.name}</td>
                    <td class="character-design">${scene.description}</td>
                    <td class="character-three-view">
                        <div class="scene-image-container" id="scene-image-${scene.scene_id}">
                            ${hasImages ? 
                                `<img src="${JSON.parse(scene.imageUrl)[0]}" alt="${scene.name}场景图" onclick="showSceneImageModal('${JSON.parse(scene.imageUrl)[0]}', '${scene.name}')" style="max-width: 100px; max-height: 100px;">` : 
                                '<div class="scene-image-placeholder"><i class="fas fa-image"></i><br>暂无图片</div>'
                            }
                        </div>
                        <button class="generate-btn" onclick="generateSceneImage(${scene.scene_id})"><i class="fas fa-magic"></i> ${generateBtnText}</button>
                    </td>
                    <td class="character-gender" style="display: none;">
                        ${hasImages ? 
                            `<button class="action-btn image-btn" onclick="viewSceneImages(${scene.scene_id})" title="查看图片"><i class="fas fa-images"></i> ${imageCount} 张</button>` : 
                            '<span class="no-images">无</span>'
                        }
                    </td>
                    <td class="character-actions">
                        <button class="action-btn edit-btn" onclick="editScene(${scene.scene_id})" title="编辑"><i class="fas fa-edit"></i> 编辑</button>
                        <button class="action-btn image-btn" onclick="manageSceneImages(${scene.scene_id})" title="管理图片" style="display: none;"><i class="fas fa-camera"></i> 管理</button>
                        <button class="action-btn delete-btn" onclick="deleteScene(${scene.scene_id})" title="删除"><i class="fas fa-trash"></i> 删除</button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        
        resultDiv.innerHTML = html;
    }

    function editScene(sceneId) {
        const sceneRow = document.querySelector(`[data-scene-id="${sceneId}"]`);
        if (!sceneRow) {
            showError('未找到时空场景信息');
            return;
        }
        
        // 从表格行中获取时空场景信息
        const sceneName = sceneRow.querySelector('.character-name').textContent;
        const sceneDescription = sceneRow.querySelector('.character-design').textContent;
        
        // 创建编辑模态框，使用唯一ID避免冲突
        const modalId = `edit-modal-${sceneId}`;
        const saveBtnId = `save-edit-btn-${sceneId}`;
        
        const editModal = document.createElement('div');
        editModal.className = 'modal';
        editModal.id = modalId;
        editModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>编辑时空场景 - ${sceneName}</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>时空场景名称</label>
                        <input type="text" id="edit-name-${sceneId}" value="${sceneName.replace(/"/g, '&quot;')}" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label>描述</label>
                        <textarea id="edit-description-${sceneId}" rows="3">${sceneDescription.replace(/"/g, '&quot;')}</textarea>
                    </div>
                    <div class="form-group" style="display: none;">
                        <label>图片管理</label>
                        <button class="btn btn-secondary" onclick="manageSceneImages(${sceneId})">管理图片</button>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">取消</button>
                    <button class="btn btn-primary" id="${saveBtnId}">保存</button>
                </div>
            </div>
        `;
        
        document.body.appendChild(editModal);
        
        // 保存编辑按钮事件
        const saveBtn = document.getElementById(saveBtnId);
        saveBtn.onclick = function() {
            // 获取当前模态框，限制搜索范围
            const modal = this.closest('.modal');
            
            const editedData = {
                scene_id: sceneId,
                name: modal.querySelector(`#edit-name-${sceneId}`).value,
                description: modal.querySelector(`#edit-description-${sceneId}`).value
            };
            
            fetch('spaces_api.php?action=edit_scene', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(editedData)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('保存失败: ' + data.error);
                    return;
                }
                
                if (data.success) {
                    successDiv.style.display = 'block';
                    successDiv.textContent = '时空场景信息已更新！';
                    
                    setTimeout(() => {
                        successDiv.style.display = 'none';
                    }, 3000);
                    
                    // 关闭模态框
                    editModal.remove();
                    
                    // 刷新时空场景列表
                    fetch(`spaces_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.scenes && data.scenes.length > 0) {
                                showResult(data.scenes);
                            }
                        });
                }
            })
            .catch(error => {
                showError('保存失败: ' + error.message);
            });
        };
    }

    function deleteScene(sceneId) {
        const sceneRow = document.querySelector(`[data-scene-id="${sceneId}"]`);
        if (!sceneRow) {
            showError('未找到时空场景信息');
            return;
        }
        
        const sceneName = sceneRow.querySelector('.character-name').textContent;
        
        confirmationMessage.textContent = `您确定要删除时空场景 "${sceneName}" 吗？此操作不可恢复。`;
        confirmationDialog.style.display = 'flex';
        
        confirmDeleteBtn.onclick = function() {
            hideConfirmationDialog();
            
            fetch('spaces_api.php?action=delete_scene', {
                method: 'POST',
                credentials: 'include',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    scene_id: sceneId
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
                    showError('删除失败: ' + data.error);
                    return;
                }
                
                if (data.success) {
                    successDiv.style.display = 'block';
                    successDiv.textContent = '时空场景已删除！';
                    
                    setTimeout(() => {
                        successDiv.style.display = 'none';
                    }, 2000);
                    
                    sceneRow.remove();
                    
                    if (document.querySelectorAll('.space-table tbody tr').length === 0) {
                        resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无时空场景数据</p>';
                    }
                }
            })
            .catch(error => {
                showError('删除失败: ' + error.message);
            });
        };
        
        cancelDeleteBtn.onclick = function() {
            hideConfirmationDialog();
        };
    }

    function loadHistory() {
        fetch('spaces_api.php?action=history')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('加载历史任务失败:', data.error);
                    return;
                }

                if (data.tasks && data.tasks.length > 0) {
                    renderHistory(data.tasks);
                } else {
                    historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
                }
            })
            .catch(error => {
                console.error('加载历史任务失败:', error);
                historyList.innerHTML = '<div class="empty-state">加载失败，请重试</div>';
            });
    }

    function renderHistory(tasks) {
        let html = '';
        
        tasks.forEach(task => {
            const statusClass = task.status === 'completed' ? 'success' : task.status === 'error' ? 'error' : 'processing';
            const statusText = task.status === 'completed' ? '已完成' : task.status === 'error' ? '失败' : '处理中';
            
            html += `
                <div class="history-item" data-task-id="${task.task_id}">
                    <div class="history-item-header">
                        <span class="history-item-title">${task.task_id}</span>
                        <span class="history-item-status ${statusClass}">${statusText}</span>
                    </div>
                    <div class="history-item-info">
                        <span><i class="fas fa-calendar"></i> ${task.created_at}</span>
                        <span><i class="fas fa-map-marker-alt"></i> ${task.scene_count || 0} 个时空场景</span>
                    </div>
                    <div class="history-item-actions">
                        <button class="history-action-btn" onclick="viewTask('${task.task_id}')">查看详情</button>
                        <button class="history-action-btn danger" onclick="deleteTask('${task.task_id}')">删除</button>
                    </div>
                </div>
            `;
        });
        
        historyList.innerHTML = html;
    }
    
    // 加载当前剧组的当前任务时空场景列表
    function loadLatestTaskSpaces() {
        // 首先获取当前用户的当前任务
        fetch('spaces_api.php?action=get_current_task')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('加载当前任务失败:', data.error);
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">' + data.error + '</p>';
                    return;
                }
                
                if (data.task_id) {
                    currentTaskId = data.task_id;
                    // 从当前任务中获取时空场景列表
                    fetch(`spaces_api.php?task_id=${encodeURIComponent(data.task_id)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络响应不正常');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('加载时空场景列表失败:', data.error);
                                // 检查是否是 "无效的任务ID" 错误
                                if (data.error === '无效的任务ID') {
                                    // 自动加载历史任务中最后一条任务
                                    loadHistoryTaskAndShowSpaces();
                                } else {
                                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载时空场景列表失败</p>';
                                }
                                return;
                            }
                            
                            if (data.scenes && data.scenes.length > 0) {
                                showResult(data.scenes);
                            } else {
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无时空场景数据</p>';
                            }
                        })
                        .catch(error => {
                            console.error('加载时空场景列表失败:', error);
                            resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载时空场景列表失败，请刷新页面重试</p>';
                        });
                } else {
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无当前任务，请先创建时空场景分析任务</p>';
                }
            })
            .catch(error => {
                console.error('加载当前任务失败:', error);
                resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载当前任务失败，请刷新页面重试</p>';
            });
    }
    
    // 加载历史任务并使用最后一条任务的ID获取时空场景列表
    function loadHistoryTaskAndShowSpaces() {
        fetch('spaces_api.php?action=history')
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    console.error('加载历史任务失败:', data.error);
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务失败</p>';
                    return;
                }

                if (data.tasks && data.tasks.length > 0) {
                    // 获取最后一条任务（按创建时间排序，最新的在前面）
                    const lastTask = data.tasks[0];
                    currentTaskId = lastTask.task_id;
                    
                    // 显示加载中提示
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">当前任务无效，正在加载最新的历史任务...</p>';
                    
                    // 使用最后一条任务的ID重新获取时空场景列表
                    fetch(`spaces_api.php?task_id=${encodeURIComponent(lastTask.task_id)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络响应不正常');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('加载历史任务时空场景列表失败:', data.error);
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务时空场景列表失败</p>';
                                return;
                            }
                            
                            if (data.scenes && data.scenes.length > 0) {
                                showResult(data.scenes);
                            } else {
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无时空场景数据</p>';
                            }
                        })
                        .catch(error => {
                            console.error('加载历史任务时空场景列表失败:', error);
                            resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务时空场景列表失败，请刷新页面重试</p>';
                        });
                } else {
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无历史任务</p>';
                }
            })
            .catch(error => {
                console.error('加载历史任务失败:', error);
                resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务失败，请刷新页面重试</p>';
            });
    }

    function viewTask(taskId) {
        fetch(`spaces_api.php?task_id=${encodeURIComponent(taskId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('加载任务详情失败: ' + data.error);
                    return;
                }

                if (data.scenes && data.scenes.length > 0) {
                    showResult(data.scenes);
                }
            })
            .catch(error => {
                showError('加载任务详情失败: ' + error.message);
            });
    }

    function deleteTask(taskId) {
        confirmationMessage.textContent = `您确定要删除任务 ${taskId} 吗？此操作不可恢复。`;
        confirmationDialog.style.display = 'flex';
        
        confirmDeleteBtn.onclick = function() {
            hideConfirmationDialog();
            
            fetch(`spaces_api.php?task_id=${encodeURIComponent(taskId)}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                }
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('删除任务失败: ' + data.error);
                    return;
                }

                successDiv.style.display = 'block';
                successDiv.textContent = '任务已删除！';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 2000);
                
                loadHistory();
            })
            .catch(error => {
                showError('删除任务失败: ' + error.message);
            });
        };
        
        cancelDeleteBtn.onclick = function() {
            hideConfirmationDialog();
        };
    }

    function handleDeleteAll() {
        confirmationMessage.textContent = '您确定要删除所有历史任务吗？此操作不可恢复。';
        confirmationDialog.style.display = 'flex';
        confirmDeleteBtn.onclick = function() {
            hideConfirmationDialog();
            
            fetch('spaces_api.php?action=delete_all', {
                method: 'DELETE'
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('删除全部任务失败: ' + data.error);
                    return;
                }

                successDiv.style.display = 'block';
                successDiv.textContent = '所有任务已删除！';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 2000);
                
                loadHistory();
            })
            .catch(error => {
                showError('删除全部任务失败: ' + error.message);
            });
        };
    }

    function hideConfirmationDialog() {
        confirmationDialog.style.display = 'none';
    }

    function showError(message) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }

    function hideError() {
        errorDiv.style.display = 'none';
    }

    function showSuccess(message) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
    }

    function hideSuccess() {
        successDiv.style.display = 'none';
    }

    // 图片上传和处理相关函数
    function manageSceneImages(sceneId) {
        const sceneRow = document.querySelector(`[data-scene-id="${sceneId}"]`);
        if (!sceneRow) {
            showError('未找到时空场景信息');
            return;
        }
        
        currentSceneId = sceneId;
        const sceneName = sceneRow.querySelector('.character-name').textContent;
        
        // 重置表单
        if (sceneNameInput) {
            sceneNameInput.value = sceneName;
        }
        if (imageFile) {
            imageFile.value = '';
        }
        if (uploadedImageName) {
            uploadedImageName.textContent = '';
        }
        if (imageUrlInput) {
            imageUrlInput.value = '';
        }
        if (previewSection) {
            previewSection.style.display = 'none';
        }
        if (imagePreviewContainer) {
            imagePreviewContainer.innerHTML = '';
        }
        
        uploadedImageFile = null;
        
        // 显示图片上传模态框
        if (imageUploadModal) {
            imageUploadModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
    }

    function viewSceneImages(sceneId) {
        const sceneRow = document.querySelector(`[data-scene-id="${sceneId}"]`);
        if (!sceneRow) {
            showError('未找到时空场景信息');
            return;
        }
        
        // 从数据库获取场景信息
        fetch(`spaces_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error('网络响应不正常');
                }
                return response.json();
            })
            .then(data => {
                if (data.error) {
                    showError('加载场景信息失败: ' + data.error);
                    return;
                }
                
                const scene = data.scenes.find(s => s.scene_id == sceneId);
                if (!scene || !scene.imageUrl || scene.imageUrl === '') {
                    showError('该场景没有图片');
                    return;
                }
                
                try {
                    const imageUrls = JSON.parse(scene.imageUrl);
                    
                    // 显示图片查看模态框
                    if (imageViewModal) {
                        // 清空图片画廊
                        if (imageGallery) {
                            imageGallery.innerHTML = '';
                        }
                        
                        // 添加图片到画廊
                        imageUrls.forEach((url, index) => {
                            const imageItem = document.createElement('div');
                            imageItem.className = 'gallery-item';
                            imageItem.innerHTML = `
                                <img src="${url}" alt="场景图片 ${index + 1}">
                                <div class="gallery-caption">图片 ${index + 1}</div>
                            `;
                            if (imageGallery) {
                                imageGallery.appendChild(imageItem);
                            }
                        });
                        
                        imageViewModal.style.display = 'block';
                        document.body.style.overflow = 'hidden';
                    }
                } catch (error) {
                    showError('解析图片URL失败: ' + error.message);
                }
            })
            .catch(error => {
                showError('加载场景信息失败: ' + error.message);
            });
    }

    function handleImageFileUpload(e) {
        const file = e.target.files[0];
        
        if (!file) {
            if (uploadedImageName) {
                uploadedImageName.textContent = '';
            }
            uploadedImageFile = null;
            return;
        }

        const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        
        if (!ALLOWED_TYPES.includes(file.type)) {
            showError('请上传JPG、JPEG、PNG或GIF格式的图片');
            if (imageFile) {
                imageFile.value = '';
            }
            if (uploadedImageName) {
                uploadedImageName.textContent = '';
            }
            uploadedImageFile = null;
            return;
        }

        if (file.size > MAX_FILE_SIZE) {
            showError('图片大小不能超过5MB');
            if (imageFile) {
                imageFile.value = '';
            }
            if (uploadedImageName) {
                uploadedImageName.textContent = '';
            }
            uploadedImageFile = null;
            return;
        }

        uploadedImageFile = file;
        if (uploadedImageName) {
            uploadedImageName.textContent = `已选择: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)}MB)`;
        }
        
        // 预览图片
        const reader = new FileReader();
        reader.onload = function(e) {
            if (previewSection) {
                previewSection.style.display = 'block';
            }
            if (imagePreviewContainer) {
                imagePreviewContainer.innerHTML = `
                    <img src="${e.target.result}" alt="预览图片" style="max-width: 100%; max-height: 300px;">
                `;
            }
        };
        reader.readAsDataURL(file);
    }

    function handleImageUrlInput(e) {
        const imageUrls = e.target.value.trim();
        
        if (imageUrls) {
            const urlArray = imageUrls.split(',').map(url => url.trim()).filter(url => url);
            
            // 预览图片
            if (previewSection) {
                previewSection.style.display = 'block';
            }
            if (imagePreviewContainer) {
                let previewHtml = '';
                urlArray.forEach((url, index) => {
                    previewHtml += `
                        <div class="preview-item">
                            <img src="${url}" alt="预览图片 ${index + 1}" style="max-width: 150px; max-height: 150px; margin: 5px;">
                        </div>
                    `;
                });
                imagePreviewContainer.innerHTML = previewHtml;
            }
        } else {
            if (previewSection) {
                previewSection.style.display = 'none';
            }
            if (imagePreviewContainer) {
                imagePreviewContainer.innerHTML = '';
            }
        }
    }

    function handleImageUploadSubmit() {
        if (!currentSceneId) {
            showError('未选择时空场景');
            return;
        }
        
        let imageUrls = [];
        
        // 处理图片URL输入
        if (imageUrlInput && imageUrlInput.value.trim()) {
            imageUrls = imageUrlInput.value.trim().split(',').map(url => url.trim()).filter(url => url);
        }
        
        // 处理上传的图片文件
        if (uploadedImageFile) {
            // 这里可以添加图片上传到服务器的逻辑
            // 为了演示，我们假设图片已经上传成功，并返回一个URL
            // 实际项目中，应该使用FormData上传文件到服务器
            const mockImageUrl = `https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=${encodeURIComponent(sceneNameInput.value + ' 场景图片')}&image_size=square`;
            imageUrls.push(mockImageUrl);
        }
        
        if (imageUrls.length === 0) {
            showError('请上传图片或输入图片URL');
            return;
        }
        
        // 保存图片URL到数据库
        const imageUrlJson = JSON.stringify(imageUrls);
        
        fetch('spaces_api.php?action=edit_scene', {
            method: 'POST',
            credentials: 'include',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                scene_id: currentSceneId,
                imageUrl: imageUrlJson
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
                showError('保存图片失败: ' + data.error);
                return;
            }
            
            if (data.success) {
                successDiv.style.display = 'block';
                successDiv.textContent = '图片已保存！';
                
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 3000);
                
                // 关闭模态框
                hideImageUploadModal();
                
                // 刷新时空场景列表
                fetch(`spaces_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.scenes && data.scenes.length > 0) {
                            showResult(data.scenes);
                        }
                    });
            }
        })
        .catch(error => {
            showError('保存图片失败: ' + error.message);
        });
    }

    function hideImageUploadModal() {
        if (imageUploadModal) {
            imageUploadModal.style.display = 'none';
            document.body.style.overflow = '';
        }
        currentSceneId = null;
        uploadedImageFile = null;
    }

    function hideImageViewModal() {
        if (imageViewModal) {
            imageViewModal.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // 显示场景图大图模态框
    function showSceneImageModal(imageUrl, sceneName) {
        const modal = document.createElement('div');
        modal.className = 'modal scene-image-modal';
        modal.innerHTML = `
            <div class="modal-content scene-image-modal-content">
                <div class="modal-header">
                    <h3>${sceneName} - 场景图</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body scene-image-modal-body">
                    <img src="${imageUrl}" alt="${sceneName}场景图" class="scene-image-large-image">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="window.open('${imageUrl}', '_blank')">在新窗口打开</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">关闭</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    // 自动调整文本域高度函数
    function resizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }

    function generateSceneImage(sceneId) {
        const sceneRow = document.querySelector(`[data-scene-id="${sceneId}"]`);
        if (!sceneRow) {
            showError('未找到时空场景信息');
            return;
        }
        
        const sceneName = sceneRow.querySelector('.character-name').textContent;
        const sceneDescription = sceneRow.querySelector('.character-design').textContent;
        
        // 生成默认提示词
        let defaultPrompt = `生成一张广角全景图：${sceneName}。${sceneDescription} 尺寸：16:9；风格：电影写实；视角：广角全景。`;
        
        // 创建编辑提示词的模态框，使用唯一ID避免冲突
        const modalId = `generate-modal-${sceneId}`;
        const confirmBtnId = `confirm-generate-btn-${sceneId}`;
        
        const promptModal = document.createElement('div');
        promptModal.className = 'modal';
        promptModal.id = modalId;
        promptModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>编辑场景图提示词 - ${sceneName}</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>场景图提示词</label>
                        <textarea id="scene-image-prompt-edit-${sceneId}" rows="6" style="width: 100%;">${defaultPrompt.replace(/"/g, '&quot;')}</textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">取消</button>
                    <button class="btn btn-primary" id="${confirmBtnId}">确定生成</button>
                </div>
            </div>
        `;
        document.body.appendChild(promptModal);
        
        // 自动调整模态框中的文本域高度
        const textarea = promptModal.querySelector(`#scene-image-prompt-edit-${sceneId}`);
        resizeTextarea(textarea);
        textarea.addEventListener('input', () => resizeTextarea(textarea));
        
        // 确定生成按钮事件
        promptModal.querySelector(`#${confirmBtnId}`).onclick = function() {
            const editedPrompt = textarea.value;
            if (!editedPrompt) {
                showError('提示词不能为空');
                return;
            }
            
            // 关闭编辑模态框
            promptModal.remove();
            
            // 创建独立的确认对话框，避免事件处理函数冲突
            const confirmationModal = document.createElement('div');
            confirmationModal.className = 'confirmation-dialog';
            confirmationModal.innerHTML = `
                <div class="confirmation-content">
                    <h3>确认生成</h3>
                    <p>生成场景图将消耗 20 积分，确定要为时空场景 "${sceneName}" 生成场景图吗？</p>
                    <div class="confirmation-buttons">
                        <button id="confirm-generate-btn" class="btn btn-primary">确定</button>
                        <button id="cancel-generate-btn" class="btn btn-secondary">取消</button>
                    </div>
                </div>
            `;
            document.body.appendChild(confirmationModal);
            
            // 确定按钮事件处理
            confirmationModal.querySelector('#confirm-generate-btn').onclick = function() {
                confirmationModal.remove();
                
                const generateBtn = sceneRow.querySelector('.generate-btn');
                const originalText = generateBtn.innerHTML;
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
                
                fetch('spaces_api.php?action=generate_scene_image', {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        scene_id: sceneId,
                        prompt: editedPrompt,
                        task_id: currentTaskId
                    })
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('网络响应不正常');
                    }
                    return response.json();
                })
                .then(data => {
                    generateBtn.disabled = false;
                    generateBtn.innerHTML = '<i class="fas fa-magic"></i> 重新生成';
                    
                    if (data.error) {
                        showError('生成失败: ' + data.error);
                        return;
                    }
                    
                    if (data.success) {
                        successDiv.style.display = 'block';
                        successDiv.textContent = '场景图生成成功！';
                        
                        setTimeout(() => {
                            successDiv.style.display = 'none';
                        }, 3000);
                        
                        // 刷新时空场景列表
                        if (currentTaskId) {
                            fetch(`spaces_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                                .then(response => response.json())
                                .then(data => {
                                    if (data.scenes && data.scenes.length > 0) {
                                        showResult(data.scenes);
                                    }
                                });
                        } else {
                            // 如果currentTaskId为null，重新加载最新任务
                            loadLatestTaskSpaces();
                        }
                    }
                })
                .catch(error => {
                    generateBtn.disabled = false;
                    generateBtn.innerHTML = originalText;
                    showError('生成失败: ' + error.message);
                });
            };
            
            // 取消按钮事件处理
            confirmationModal.querySelector('#cancel-generate-btn').onclick = function() {
                confirmationModal.remove();
            };
        };
    }

    window.editScene = editScene;
    window.deleteScene = deleteScene;
    window.viewTask = viewTask;
    window.deleteTask = deleteTask;
    window.manageSceneImages = manageSceneImages;
    window.viewSceneImages = viewSceneImages;
    window.generateSceneImage = generateSceneImage;
    window.showSceneImageModal = showSceneImageModal;

    init();
});
