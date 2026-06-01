// 分镜详情页面逻辑
document.addEventListener('DOMContentLoaded', function() {
    // 标签页切换功能
    const tabButtons = document.querySelectorAll('.tab-btn');
    const tabPanes = document.querySelectorAll('.tab-pane');
    
    tabButtons.forEach(button => {
        button.addEventListener('click', function() {
            const tabId = this.getAttribute('data-tab');
            
            // 移除所有按钮的激活状态
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // 激活当前按钮
            this.classList.add('active');
            
            // 隐藏所有标签内容
            tabPanes.forEach(pane => pane.classList.remove('active'));
            // 显示当前标签内容
            document.getElementById(tabId).classList.add('active');
        });
    });
    
    // 获取URL参数
    function getUrlParams() {
        const params = {};
        const search = window.location.search.substring(1);
        const pairs = search.split('&');
        for (let pair of pairs) {
            const [key, value] = pair.split('=');
            params[decodeURIComponent(key)] = decodeURIComponent(value);
        }
        return params;
    }
    
    // 从URL参数中获取task_id、scene_id和shot_id
    const params = getUrlParams();
    const taskId = params.task_id || '';
    const sceneId = params.scene_id || params.sceneId || '';
    const shotId = params.id || params.shot_id || '';
    
    console.log('URL参数:', { taskId, sceneId, shotId });
    
    // 如果缺少必要参数，显示错误信息
    if (!taskId || !sceneId || !shotId) {
        alert('缺少必要的参数');
        window.location.href = 'gushiban.php';
        return;
    }
    
    // 获取分镜数据
    async function fetchShotData() {
        try {
            const response = await fetch(`storyboard_api.php?task_id=${taskId}&scene_id=${sceneId}&shot_id=${shotId}`);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const data = await response.json();
            console.log('获取到的分镜数据:', data);
            return data;
        } catch (error) {
            console.error('获取分镜数据失败:', error);
            alert('获取分镜数据失败: ' + error.message);
            return null;
        }
    }
    
    // 填充分镜数据到页面
    function populateShotData(data) {
        if (!data || !data.scenes || data.scenes.length === 0) {
            alert('未找到分镜数据');
            return;
        }
        
        const scene = data.scenes[0];
        const shot = scene.shots[0];
        
        // 更新页面标题
        document.getElementById('shotNumber').textContent = shot.id;
        document.getElementById('sceneNumber').textContent = scene.id;
        
        // 更新基本信息
        document.getElementById('sortOrder').value = shot.sort_order || '';
        document.getElementById('sceneId').value = shot.sceneId || '';
        document.getElementById('shotId').value = shot.id || '';
        document.getElementById('location').value = shot.location || '';
        document.getElementById('time').value = shot.time || '';
        document.getElementById('weather').value = shot.weather || '';
        document.getElementById('shotTypeInput').value = shot.shotType || '';
        document.getElementById('durationInput').value = shot.duration || '';
        
        // 更新内容描述
        document.getElementById('content').value = shot.content || '';
        document.getElementById('remark').value = shot.remark || '';
        document.getElementById('sceneExpectation').value = shot.sceneExpectation || '';
        document.getElementById('sound').value = shot.sound || '';
        
        // 更新角色信息
        document.getElementById('characters').value = shot.characters || '';
        document.getElementById('characterCostumes').value = shot.characterCostumes || '';
        document.getElementById('characterMakeup').value = shot.characterMakeup || '';
        document.getElementById('characterActions').value = shot.characterActions || '';
        
        // 更新技术参数
        document.getElementById('cameraAngle').value = shot.cameraAngle || '';
        document.getElementById('compositionFocus').value = shot.compositionFocus || '';
        document.getElementById('cameraMovementInput').value = shot.cameraMovement || '';
        document.getElementById('cameraEquipment').value = shot.cameraEquipment || '';
        document.getElementById('lensFocalLength').value = shot.lensFocalLength || '';
        document.getElementById('lightTone').value = shot.lightTone || '';
        
        // 更新左侧信息
        document.getElementById('shotType').textContent = shot.shotType || '-';
        document.getElementById('duration').textContent = shot.duration ? shot.duration + '秒' : '-';
        document.getElementById('cameraMovement').textContent = shot.cameraMovement || '-';
        
        // 更新参考画面 放imageUrl字段中图片
        if (shot.imageUrl && shot.imageUrl.trim() !== '') {
            document.getElementById('referenceImagePlaceholder').style.display = 'none';
            document.getElementById('referenceImage').style.display = 'block';
            document.getElementById('referenceImageSrc').src = shot.imageUrl;
        } else {
            document.getElementById('referenceImagePlaceholder').style.display = 'block';
            document.getElementById('referenceImage').style.display = 'none';
        }
        
        // 更新分镜画面 放imageUrls字段中图片，宫格单图
        if (shot.imageUrls && shot.imageUrls.trim() !== '') {
            try {
                let images = [];
                if (shot.imageUrls.startsWith('[')) {
                    // JSON数组格式
                    images = JSON.parse(shot.imageUrls);
                } else {
                    // 单个URL
                    images = [shot.imageUrls];
                }
                
                if (images.length > 0) {
                    // 处理第一个图片URL可能是对象的情况
                    let firstImageUrl = images[0];
                    if (typeof firstImageUrl === 'object' && firstImageUrl !== null) {
                        firstImageUrl = firstImageUrl.url || firstImageUrl.src || firstImageUrl.imageUrl || JSON.stringify(firstImageUrl);
                    }
                    firstImageUrl = String(firstImageUrl);
                    
                    document.getElementById('canvasPlaceholder').style.display = 'none';
                    document.getElementById('canvasImage').style.display = 'flex';
                    document.getElementById('shotImage').src = firstImageUrl;
                } else {
                    document.getElementById('canvasPlaceholder').style.display = 'flex';
                    document.getElementById('canvasImage').style.display = 'none';
                }
            } catch (error) {
                console.error('解析分镜画面失败:', error);
                document.getElementById('canvasPlaceholder').style.display = 'flex';
                document.getElementById('canvasImage').style.display = 'none';
            }
        } else {
            document.getElementById('canvasPlaceholder').style.display = 'flex';
            document.getElementById('canvasImage').style.display = 'none';
        }

        // 更新运镜画面 放video_image_Url字段中多图（横向放缩略图）
        let cameraMovementImages = [];
        
        // 只使用 video_image_Url 字段
        if (shot.video_image_Url && shot.video_image_Url.trim() !== '') {
            try {
                if (shot.video_image_Url.startsWith('[')) {
                    // JSON数组格式
                    cameraMovementImages = JSON.parse(shot.video_image_Url);
                } else {
                    // 单个URL
                    cameraMovementImages = [shot.video_image_Url];
                }
            } catch (error) {
                console.error('解析 video_image_Url 失败:', error);
                cameraMovementImages = [];
            }
        }
        
        if (cameraMovementImages.length > 0) {
            let thumbnailsHtml = '';
            cameraMovementImages.forEach((imgItem, index) => {
                // 处理imgItem可能是对象的情况
                let imgUrl = imgItem;
                if (typeof imgItem === 'object' && imgItem !== null) {
                    // 尝试获取对象中的URL属性
                    imgUrl = imgItem.url || imgItem.src || imgItem.imageUrl || JSON.stringify(imgItem);
                }
                // 确保imgUrl是字符串
                imgUrl = String(imgUrl);
                
                thumbnailsHtml += `
                    <div class="thumbnail">
                        <img src="${imgUrl}" alt="运镜画面 ${index + 1}">
                    </div>
                `;
            });
            document.getElementById('videoImageThumbnails').innerHTML = thumbnailsHtml;
        } else {
            document.getElementById('videoImageThumbnails').innerHTML = `
                <div class="video-placeholder">
                    <i class="fas fa-images"></i>
                    <p>暂无运镜画面</p>
                </div>
            `;
        }

        // 更新提示词
        document.getElementById('videoPrompt').value = shot.script || shot.content || '';

        // 更新成片预览 放videoCutUrl字段中视频
        let finalVideoUrls = [];
        
        // 只使用 videoCutUrl 字段
        if (shot.videoCutUrl && shot.videoCutUrl.trim() !== '') {
            try {
                if (shot.videoCutUrl.startsWith('[')) {
                    // JSON数组格式
                    finalVideoUrls = JSON.parse(shot.videoCutUrl);
                } else {
                    // 单个URL
                    finalVideoUrls = [shot.videoCutUrl];
                }
            } catch (error) {
                console.error('解析 videoCutUrl 失败:', error);
                finalVideoUrls = [];
            }
        }
        
        if (finalVideoUrls.length > 0) {
            // 处理第一个视频URL可能是对象的情况
            let firstVideoUrl = finalVideoUrls[0];
            if (typeof firstVideoUrl === 'object' && firstVideoUrl !== null) {
                firstVideoUrl = firstVideoUrl.url || firstVideoUrl.src || firstVideoUrl.videoUrl || JSON.stringify(firstVideoUrl);
            }
            firstVideoUrl = String(firstVideoUrl);
            
            document.getElementById('videoPreviewPlaceholder').style.display = 'none';
            document.getElementById('videoPreview').style.display = 'block';
            document.getElementById('previewVideoSource').src = firstVideoUrl;
            document.getElementById('previewVideo').load();
        } else {
            document.getElementById('videoPreviewPlaceholder').style.display = 'block';
            document.getElementById('videoPreview').style.display = 'none';
        }
    }
    
    // 编辑按钮功能
    const editBtn = document.getElementById('editBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function() {
            // 启用所有表单控件
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.removeAttribute('readonly');
            });
            
            // 显示保存按钮
            document.getElementById('saveBtn').style.display = 'inline-block';
            // 隐藏编辑按钮
            editBtn.style.display = 'none';
        });
    }
    
    // 保存按钮功能
    const saveBtn = document.getElementById('saveBtn');
    if (saveBtn) {
        saveBtn.addEventListener('click', async function() {
            // 收集表单数据
            const formData = {
                task_id: taskId,
                scene_id: sceneId,
                shot_id: shotId,
                sort_order: document.getElementById('sortOrder').value,
                location: document.getElementById('location').value,
                time: document.getElementById('time').value,
                weather: document.getElementById('weather').value,
                shotType: document.getElementById('shotTypeInput').value,
                duration: document.getElementById('durationInput').value,
                content: document.getElementById('content').value,
                remark: document.getElementById('remark').value,
                sceneExpectation: document.getElementById('sceneExpectation').value,
                sound: document.getElementById('sound').value,
                characters: document.getElementById('characters').value,
                characterCostumes: document.getElementById('characterCostumes').value,
                characterMakeup: document.getElementById('characterMakeup').value,
                characterActions: document.getElementById('characterActions').value,
                cameraAngle: document.getElementById('cameraAngle').value,
                compositionFocus: document.getElementById('compositionFocus').value,
                cameraMovement: document.getElementById('cameraMovementInput').value,
                cameraEquipment: document.getElementById('cameraEquipment').value,
                lensFocalLength: document.getElementById('lensFocalLength').value,
                lightTone: document.getElementById('lightTone').value
            };
            
            console.log('保存的分镜数据:', formData);
            
            // 这里应该发送POST请求到服务器保存数据
            // 暂时使用alert模拟
            alert('保存成功！');
            
            // 禁用所有表单控件
            const formControls = document.querySelectorAll('.form-control');
            formControls.forEach(control => {
                control.setAttribute('readonly', 'readonly');
            });
            
            // 隐藏保存按钮
            saveBtn.style.display = 'none';
            // 显示编辑按钮
            document.getElementById('editBtn').style.display = 'inline-block';
        });
    }
    
    // 关闭按钮功能
    const closeBtn = document.getElementById('closeBtn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            window.location.href = `gushiban.php?task_id=${taskId}`;
        });
    }
    
    // 初始化页面
    async function initPage() {
        const data = await fetchShotData();
        if (data) {
            populateShotData(data);
        }
    }
    
    // 初始化页面
    initPage();
    
    console.log('分镜详情页面已加载');
});
