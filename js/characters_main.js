document.addEventListener('DOMContentLoaded', function() {
    const scriptTextarea = document.getElementById('script');
    const charCount = document.getElementById('charCount');
    const textInputMethod = document.getElementById('textInputMethod');
    const fileInputMethod = document.getElementById('fileInputMethod');
    const textInputSection = document.getElementById('textInputSection');
    const fileUploadSection = document.getElementById('fileUploadSection');
    const charactersFile = document.getElementById('charactersFile');
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

    let currentTaskId = null;
    let pollInterval = null;
    let uploadedFileContent = null;

    const MAX_TEXT_LENGTH = 300000;

    function init() {
        setupEventListeners();
        setupTabSwitching();
        loadHistory();
        loadLatestTaskCharacters();
    }

    function setupEventListeners() {
        textInputMethod.addEventListener('change', handleInputMethodChange);
        fileInputMethod.addEventListener('change', handleInputMethodChange);
        scriptTextarea.addEventListener('input', handleTextInput);
        charactersFile.addEventListener('change', handleFileUpload);
        submitBtn.addEventListener('click', handleSubmit);
        refreshHistoryBtn.addEventListener('click', loadHistory);
        deleteAllBtn.addEventListener('click', handleDeleteAll);
        cancelDeleteBtn.addEventListener('click', hideConfirmationDialog);
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
            charactersFile.value = '';
            uploadedFileName.textContent = '';
            uploadedFileContent = null;
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            const content = e.target.result;
            
            if (content.length > MAX_TEXT_LENGTH) {
                showError(`文件内容过长（${content.length.toLocaleString()}字符），请缩短至${MAX_TEXT_LENGTH.toLocaleString()}字符以内`);
                charactersFile.value = '';
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
            showError('请输入或上传小说剧本内容');
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
        resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">正在分析角色信息...</p>';

        fetch('characters_api.php', {
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
            progressInfo.textContent = '任务已创建，正在分析角色信息...';
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

            fetch(`characters_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
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
                        
                        showResult(data.characters);
                        successDiv.style.display = 'block';
                        successDiv.textContent = '角色创作完成！结果已显示在右侧。';
                        
                        setTimeout(() => {
                            successDiv.style.display = 'none';
                        }, 3000);
                        
                        loadHistory();
                    } else if (data.status === 'error') {
                        clearInterval(pollInterval);
                        pollInterval = null;
                        loadingDiv.style.display = 'none';
                        submitBtn.disabled = false;
                        showError('角色创作失败: ' + (data.error || data.message || '未知错误'));
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

        if (data.characters && data.characters.length > 0) {
            showResult(data.characters);
        }
    }

    function showResult(characters) {
        if (!characters || characters.length === 0) {
            resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无角色数据</p>';
            return;
        }

        let html = '<div class="character-table-container">';
        html += '<table class="character-table">';
        
        // 表头
        html += `
            <thead>
                <tr>
                    <th>编号</th>
                    <th>名称</th>
                    <th>性别</th>
                    <th>年龄</th>
                    <th>服装</th>
                    <th>妆造</th>
                    <th>人设</th>
                    <th style="display:none;">三视图提示词</th>
                    <th>三视图</th>
                    <th>操作</th>
                </tr>
            </thead>
        `;
        
        // 表体
        html += '<tbody>';
        
        characters.forEach(char => {
            // 生成三视图按钮文本
            const generateBtnText = char.three_view_image 
                ? '重新生成' 
                : '生成三视图';
            
            html += `
                <tr data-character-id="${char.id}">
                    <td class="character-number">${char.character_number}</td>
                    <td class="character-name">${char.name}</td>
                    <td class="character-gender">${char.gender || '未知'}</td>
                    <td class="character-age">${char.age || '未知'}</td>
                    <td class="character-clothing">${char.clothing_description || '暂无'}</td>
                    <td class="character-makeup">${char.makeup_description || '暂无'}</td>
                    <td class="character-design">${char.character_design || '暂无'}</td>
                    <td class="character-prompt" style="display:none;">
                        <div class="prompt-container">
                            <textarea class="three-view-prompt" rows="4" data-character-id="${char.id}">${char.three_view_prompt || '暂无提示词'}</textarea>
                            <button class="copy-btn" onclick="copyPrompt(${char.id})" title="复制提示词">
                                <i class="fas fa-copy"></i>
                            </button>
                        </div>
                    </td>
                    <td class="character-three-view">
                        <div class="three-view-image" id="three-view-image-${char.id}">
                                ${char.three_view_image 
                                    ? `<img src="${char.three_view_image}" alt="${char.name}三视图" onclick="showThreeViewModal('${char.three_view_image}', '${char.name}')">`
                                    : '<div class="three-view-placeholder"><i class="fas fa-image"></i><br>暂无图片</div>'
                                }
                            </div>
                        <button class="generate-btn" onclick="generateThreeView(${char.id})">
                            <i class="fas fa-magic"></i> ${generateBtnText}
                        </button>
                    </td>
                    <td class="character-actions">
                        <button class="action-btn edit-btn" onclick="editCharacter(${char.id})" title="编辑">
                            <i class="fas fa-edit"></i> 编辑
                        </button>
                        <button class="action-btn delete-btn" onclick="deleteCharacter(${char.id})" title="删除">
                            <i class="fas fa-trash"></i> 删除
                        </button>
                    </td>
                </tr>
            `;
        });
        
        html += '</tbody>';
        html += '</table>';
        html += '</div>';
        
        resultDiv.innerHTML = html;
        
        // 初始化自动调整文本域高度
        initAutoResizeTextareas();
    }
    
    // 自动调整文本域高度函数
    function resizeTextarea(textarea) {
        textarea.style.height = 'auto';
        textarea.style.height = textarea.scrollHeight + 'px';
    }
    
    // 初始化所有文本域的自动调整
    function initAutoResizeTextareas() {
        const textareas = document.querySelectorAll('.three-view-prompt');
        textareas.forEach(textarea => {
            resizeTextarea(textarea);
            textarea.addEventListener('input', () => resizeTextarea(textarea));
        });
    }
    
    // 显示三视图大图模态框
    function showThreeViewModal(imageUrl, characterName) {
        const modal = document.createElement('div');
        modal.className = 'modal three-view-modal';
        modal.innerHTML = `
            <div class="modal-content three-view-modal-content">
                <div class="modal-header">
                    <h3>${characterName} - 三视图</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body three-view-modal-body">
                    <img src="${imageUrl}" alt="${characterName}三视图" class="three-view-large-image">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary" onclick="window.open('${imageUrl}', '_blank')">在新窗口打开</button>
                    <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">关闭</button>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }

    function generateThreeView(characterId) {
        const characterRow = document.querySelector(`[data-character-id="${characterId}"]`);
        if (!characterRow) {
            showError('未找到角色信息');
            return;
        }
        
        const characterName = characterRow.querySelector('.character-name').textContent;
        const promptElement = characterRow.querySelector('.three-view-prompt');
        let currentPrompt = promptElement.value;
        
        // 如果没有提示词，使用默认提示词
        if (!currentPrompt || currentPrompt === '暂无提示词') {
            currentPrompt = `生成一张三视图（正面、侧影、背影）：${characterName}。尺寸：16:9；风格：电影写实。`;
        }
        
        // 创建编辑提示词的模态框，使用唯一ID避免冲突
        const modalId = `generate-modal-${characterId}`;
        const confirmBtnId = `confirm-generate-btn-${characterId}`;
        
        const promptModal = document.createElement('div');
        promptModal.className = 'modal';
        promptModal.id = modalId;
        promptModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>编辑三视图提示词 - ${characterName}</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>三视图提示词</label>
                        <textarea id="three-view-prompt-edit-${characterId}" rows="6" style="width: 100%;">${currentPrompt.replace(/"/g, '&quot;')}</textarea>
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
        const textarea = promptModal.querySelector(`#three-view-prompt-edit-${characterId}`);
        resizeTextarea(textarea);
        textarea.addEventListener('input', () => resizeTextarea(textarea));
        
        // 确定生成按钮事件
        promptModal.querySelector(`#${confirmBtnId}`).onclick = function() {
            const editedPrompt = textarea.value;
            if (!editedPrompt) {
                showError('提示词不能为空');
                return;
            }
            
            // 保存编辑后的提示词到表格
            promptElement.value = editedPrompt;
            resizeTextarea(promptElement);
            
            // 关闭编辑模态框
            promptModal.remove();
            
            // 创建独立的确认对话框，避免事件处理函数冲突
            const confirmationModal = document.createElement('div');
            confirmationModal.className = 'confirmation-dialog';
            confirmationModal.innerHTML = `
                <div class="confirmation-content">
                    <h3>确认生成</h3>
                    <p>生成三视图将消耗 20 积分，确定要为角色 "${characterName}" 生成三视图吗？</p>
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
                
                const generateBtn = characterRow.querySelector('.generate-btn');
                const originalText = generateBtn.innerHTML;
                generateBtn.disabled = true;
                generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
                
                fetch('characters_api.php?action=generate_three_view', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        character_id: characterId,
                        prompt: editedPrompt
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
                        successDiv.textContent = '三视图生成成功！';
                        
                        setTimeout(() => {
                            successDiv.style.display = 'none';
                        }, 3000);
                        
                        // 刷新整个角色列表，确保所有数据都是最新的
                        fetch(`characters_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.characters && data.characters.length > 0) {
                                    showResult(data.characters);
                                }
                            });
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

    function copyPrompt(characterId) {
        const characterRow = document.querySelector(`[data-character-id="${characterId}"]`);
        if (!characterRow) return;
        
        const promptElement = characterRow.querySelector('.three-view-prompt');
        const prompt = promptElement.value;
        
        if (navigator.clipboard) {
            navigator.clipboard.writeText(prompt).then(() => {
                successDiv.style.display = 'block';
                successDiv.textContent = '提示词已复制到剪贴板！';
                setTimeout(() => {
                    successDiv.style.display = 'none';
                }, 2000);
            }).catch(err => {
                showError('复制失败: ' + err.message);
            });
        } else {
            showError('您的浏览器不支持剪贴板操作');
        }
    }

    function editCharacter(characterId) {
        const characterRow = document.querySelector(`[data-character-id="${characterId}"]`);
        if (!characterRow) {
            showError('未找到角色信息');
            return;
        }
        
        // 从表格行中获取角色信息
        const characterName = characterRow.querySelector('.character-name').textContent;
        const gender = characterRow.querySelector('.character-gender').textContent;
        const age = characterRow.querySelector('.character-age').textContent;
        const clothing = characterRow.querySelector('.character-clothing').textContent;
        const makeup = characterRow.querySelector('.character-makeup').textContent;
        const characterDesign = characterRow.querySelector('.character-design').textContent;
        const threeViewPrompt = characterRow.querySelector('.three-view-prompt').value;
        
        // 创建编辑模态框，使用唯一ID避免冲突
        const modalId = `edit-modal-${characterId}`;
        const saveBtnId = `save-edit-btn-${characterId}`;
        
        const editModal = document.createElement('div');
        editModal.className = 'modal';
        editModal.id = modalId;
        editModal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3>编辑角色 - ${characterName}</h3>
                    <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label>名称</label>
                        <input type="text" id="edit-name-${characterId}" value="${characterName.replace(/"/g, '&quot;')}">
                    </div>
                    <div class="form-group">
                        <label>性别</label>
                        <input type="text" id="edit-gender-${characterId}" value="${gender.replace(/"/g, '&quot;')}">
                    </div>
                    <div class="form-group">
                        <label>年龄</label>
                        <input type="text" id="edit-age-${characterId}" value="${age.replace(/"/g, '&quot;')}">
                    </div>
                    <div class="form-group">
                        <label>服装</label>
                        <textarea id="edit-clothing-${characterId}" rows="3">${clothing.replace(/"/g, '&quot;')}</textarea>
                    </div>
                    <div class="form-group">
                        <label>妆造</label>
                        <textarea id="edit-makeup-${characterId}" rows="3">${makeup.replace(/"/g, '&quot;')}</textarea>
                    </div>
                    <div class="form-group">
                        <label>人设</label>
                        <textarea id="edit-character-design-${characterId}" rows="3">${characterDesign.replace(/"/g, '&quot;')}</textarea>
                    </div>
                    <div class="form-group">
                        <label>三视图提示词</label>
                        <textarea id="edit-three-view-prompt-${characterId}" rows="4">${threeViewPrompt.replace(/"/g, '&quot;')}</textarea>
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
                character_id: characterId,
                name: modal.querySelector(`#edit-name-${characterId}`).value,
                gender: modal.querySelector(`#edit-gender-${characterId}`).value,
                age: modal.querySelector(`#edit-age-${characterId}`).value,
                clothing: modal.querySelector(`#edit-clothing-${characterId}`).value,
                makeup: modal.querySelector(`#edit-makeup-${characterId}`).value,
                character_design: modal.querySelector(`#edit-character-design-${characterId}`).value,
                three_view_prompt: modal.querySelector(`#edit-three-view-prompt-${characterId}`).value
            };
            
            fetch('characters_api.php?action=edit_character', {
                method: 'POST',
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
                    successDiv.textContent = '角色信息已更新！';
                    
                    setTimeout(() => {
                        successDiv.style.display = 'none';
                    }, 3000);
                    
                    // 关闭模态框
                    editModal.remove();
                    
                    // 刷新角色列表
                    fetch(`characters_api.php?task_id=${encodeURIComponent(currentTaskId)}`)
                        .then(response => response.json())
                        .then(data => {
                            if (data.characters && data.characters.length > 0) {
                                showResult(data.characters);
                            }
                        });
                }
            })
            .catch(error => {
                showError('保存失败: ' + error.message);
            });
        };
    }

    function exportCharacter(characterId) {
        const characterRow = document.querySelector(`[data-character-id="${characterId}"]`);
        if (!characterRow) {
            showError('未找到角色信息');
            return;
        }
        
        // 从表格行中获取角色信息
        const characterName = characterRow.querySelector('.character-name').textContent;
        const characterNumber = characterRow.querySelector('.character-number').textContent;
        const gender = characterRow.querySelector('.character-gender').textContent;
        const age = characterRow.querySelector('.character-age').textContent;
        const clothing = characterRow.querySelector('.character-clothing').textContent;
        const makeup = characterRow.querySelector('.character-makeup').textContent;
        const characterDesign = characterRow.querySelector('.character-design').textContent;
        const threeViewPrompt = characterRow.querySelector('.three-view-prompt').value;
        
        // 准备导出数据
        const exportData = {
            [characterNumber]: {
                '角色名称': characterName,
                '性别': gender,
                '年龄': age,
                '服装': clothing,
                '妆造': makeup,
                '人设': characterDesign,
                '三视图提示词': threeViewPrompt
            }
        };
        
        // 生成并下载JSON文件
        const jsonStr = JSON.stringify(exportData, null, 2);
        const blob = new Blob([jsonStr], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${characterName}_角色信息.json`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
        
        // 显示成功消息
        successDiv.style.display = 'block';
        successDiv.textContent = '角色信息已导出！';
        setTimeout(() => {
            successDiv.style.display = 'none';
        }, 2000);
    }

    function deleteCharacter(characterId) {
        const characterRow = document.querySelector(`[data-character-id="${characterId}"]`);
        if (!characterRow) {
            showError('未找到角色信息');
            return;
        }
        
        const characterName = characterRow.querySelector('.character-name').textContent;
        
        confirmationMessage.textContent = `您确定要删除角色 "${characterName}" 吗？此操作不可恢复。`;
        confirmationDialog.style.display = 'flex';
        
        confirmDeleteBtn.onclick = function() {
            hideConfirmationDialog();
            
            fetch('characters_api.php?action=delete_character', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    character_id: characterId
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
                    successDiv.textContent = '角色已删除！';
                    
                    setTimeout(() => {
                        successDiv.style.display = 'none';
                    }, 2000);
                    
                    characterRow.remove();
                    
                    if (document.querySelectorAll('.character-table tbody tr').length === 0) {
                        resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无角色数据</p>';
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
        fetch('characters_api.php?action=history')
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
                        <span><i class="fas fa-users"></i> ${task.character_count || 0} 个角色</span>
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
    
    // 加载当前剧组的当前任务角色列表
    function loadLatestTaskCharacters() {
        // 首先获取当前用户的当前剧组和当前任务
        fetch('characters_api.php?action=get_current_task')
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
                    // 从当前任务中获取角色列表
                    fetch(`characters_api.php?task_id=${encodeURIComponent(data.task_id)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络响应不正常');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('加载角色列表失败:', data.error);
                                // 检查是否是 "无效的任务ID" 错误
                                if (data.error === '无效的任务ID') {
                                    // 自动加载历史任务中最后一条任务
                                    loadHistoryTaskAndShowCharacters();
                                } else {
                                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载角色列表失败</p>';
                                }
                                return;
                            }
                            
                            if (data.characters && data.characters.length > 0) {
                                showResult(data.characters);
                            } else {
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无角色数据</p>';
                            }
                        })
                        .catch(error => {
                            console.error('加载角色列表失败:', error);
                            resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载角色列表失败，请刷新页面重试</p>';
                        });
                } else {
                    resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无当前任务，请先创建角色分析任务</p>';
                }
            })
            .catch(error => {
                console.error('加载当前任务失败:', error);
                resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载当前任务失败，请刷新页面重试</p>';
            });
    }
    
    // 加载历史任务并使用最后一条任务的ID获取角色列表
    function loadHistoryTaskAndShowCharacters() {
        fetch('characters_api.php?action=history')
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
                    
                    // 使用最后一条任务的ID重新获取角色列表
                    fetch(`characters_api.php?task_id=${encodeURIComponent(lastTask.task_id)}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('网络响应不正常');
                            }
                            return response.json();
                        })
                        .then(data => {
                            if (data.error) {
                                console.error('加载历史任务角色列表失败:', data.error);
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务角色列表失败</p>';
                                return;
                            }
                            
                            if (data.characters && data.characters.length > 0) {
                                showResult(data.characters);
                            } else {
                                resultDiv.innerHTML = '<p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">暂无角色数据</p>';
                            }
                        })
                        .catch(error => {
                            console.error('加载历史任务角色列表失败:', error);
                            resultDiv.innerHTML = '<p style="color: #e74c3c; text-align: center; margin-top: 50px; font-size: 0.9rem;">加载历史任务角色列表失败，请刷新页面重试</p>';
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
        fetch(`characters_api.php?task_id=${encodeURIComponent(taskId)}`)
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

                if (data.characters && data.characters.length > 0) {
                    showResult(data.characters);
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
            
            fetch(`characters_api.php?task_id=${encodeURIComponent(taskId)}`, {
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
            
            fetch('characters_api.php?action=delete_all', {
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

    window.generateThreeView = generateThreeView;
    window.copyPrompt = copyPrompt;
    window.editCharacter = editCharacter;
    window.exportCharacter = exportCharacter;
    window.deleteCharacter = deleteCharacter;
    window.viewTask = viewTask;
    window.deleteTask = deleteTask;
    window.showThreeViewModal = showThreeViewModal;

    init();
});
