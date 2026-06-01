// 页面加载完成后获取数据并渲染故事板
document.addEventListener('DOMContentLoaded', function () {
    // 初始化浮动提示条
    initFloatingBar();
    loadStoryboardData();
    // 初始化生成参考图按钮
    initGenerateButtons();
    // 初始化生成视频按钮
    initGenerateVideoButton();
    
    // 检查是否有正在执行的视频生成任务
    checkAndRestoreOngoingTasks();
});

// 检查并恢复正在执行的任务
async function checkAndRestoreOngoingTasks() {
    const ongoingTasks = getOngoingTasks();
    
    // 遍历所有正在执行的任务
    for (const taskId in ongoingTasks) {
        const taskInfo = ongoingTasks[taskId];
        const shotId = taskInfo.shotId;
        
        try {
            // 获取任务状态
            const response = await fetch('video_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_task',
                    task_id: taskId
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                
                if (data.code === 0 && data.data) {
                    const taskData = data.data;
                    
                    // 检查任务是否仍在执行中
                    if (taskData.status === 0 || taskData.status === 1) {
                        // 显示分镜卡片上的视频生成中标签
                        showVideoGeneratingTag(shotId);
                        
                        // 可以选择自动打开模态框显示任务状态
                        // 或者在用户点击生成视频按钮时显示
                    } else {
                        // 任务已完成或失败，从本地存储中移除
                        removeOngoingTask(taskId);
                    }
                }
            }
        } catch (error) {
            console.error('检查正在执行的任务失败:', error);
            // 从本地存储中移除失败的任务
            removeOngoingTask(taskId);
        }
    }
}

// 检查所有分镜的视频生成任务状态
async function checkAllShotsTasks() {
    const shotCards = document.querySelectorAll('.shot-card');
    const shotInfo = [];
    
    // 收集所有分镜ID和场次ID
    for (const card of shotCards) {
        const shotId = card.getAttribute('data-shot-id');
        const sceneId = card.getAttribute('data-scene-id');
        if (shotId && sceneId) {
            shotInfo.push({ shotId, sceneId });
        }
    }
    
    if (shotInfo.length === 0) {
        return;
    }
    
    // 提取所有分镜ID
    const shotIds = shotInfo.map(info => info.shotId);
    
    try {
        // 批量检查所有分镜的视频生成任务状态
        const response = await fetch('video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_user_tasks',
                shot_ids: shotIds
            })
        });
        
        if (!response.ok) {
            throw new Error(`获取任务状态失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code === 0 && data.data) {
            const tasks = data.data;
            
            // 为每个有进行中任务的分镜显示生成中标签
            for (const task of tasks) {
                if (task.shot_id && task.scenes_id && (task.status === 0 || task.status === 1)) {
                    // 找到对应的分镜卡片
                    const card = shotCards.forEach(card => {
                        const cardShotId = card.getAttribute('data-shot-id');
                        const cardSceneId = card.getAttribute('data-scene-id');
                        if (cardShotId === task.shot_id && cardSceneId === task.scenes_id) {
                            showVideoGeneratingTag(cardShotId);
                        }
                    });
                }
            }
        }
    } catch (error) {
        console.error('批量检查进行中任务失败:', error);
        
        // 如果批量检查失败，回退到单个检查（可选）
        // for (const info of shotInfo) {
        //     const ongoingTask = await checkOngoingTasks(info.shotId);
        //     if (ongoingTask) {
        //         showVideoGeneratingTag(info.shotId);
        //     }
        // }
    }
}

// 初始化生成视频按钮
function initGenerateVideoButton() {
    const generateVideoBtn = document.getElementById('generateStoryboardVideo');
    if (generateVideoBtn) {
        generateVideoBtn.addEventListener('click', generateStoryboardVideo);
    }
    
    // 检查所有分镜的视频生成任务状态
    checkAllShotsTasks();
}

// 加载分镜数据
async function loadStoryboardData(sceneId = null, shotId = null) {
    try {
        // 获取URL中的参数
        const urlParams = new URLSearchParams(window.location.search);
        let taskId = urlParams.get('task_id');
        let urlSceneId = urlParams.get('scene_id');
        let urlShotId = urlParams.get('shot_id');
        
        // 使用传入的参数或URL参数
        const finalSceneId = sceneId || urlSceneId;
        const finalShotId = shotId || urlShotId;
        
        // 如果URL中没有任务号，优先使用从数据库获取的当前任务号
        if (!taskId && window.dbTaskId) {
            taskId = window.dbTaskId;
        }
        
        // 如果都没有，尝试获取最新已完成任务的ID
        if (!taskId) {
            taskId = getLatestCompletedTaskId();
        }
        
        // 构建API或JSON文件路径，传递必要的参数
        let dataUrl;
        if (taskId) {
            let apiUrl = `./storyboard_api.php?task_id=${taskId}&sort_by=sort_order`;
            if (finalSceneId) {
                apiUrl += `&scene_id=${finalSceneId}`;
                if (finalShotId) {
                    apiUrl += `&shot_id=${finalShotId}`;
                }
            }
            dataUrl = apiUrl;
        } else {
            dataUrl = './json/storyboard-data.json';
        }
        
        // 保存taskId到全局变量，以便在其他地方使用
        window.currentTaskId = taskId;

        const response = await fetch(dataUrl);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // 添加调试信息，检查shot数据结构
        console.log('Storyboard data:', data);
        if (data.scenes && data.scenes.length > 0) {
            data.scenes.forEach((scene, sceneIndex) => {
                if (scene.shots && scene.shots.length > 0) {
                    scene.shots.forEach((shot, shotIndex) => {
                        console.log(`Scene ${sceneIndex + 1}, Shot ${shotIndex + 1} (ID: ${shot.id}):`, {
                            videoCutUrl: shot.videoCutUrl,
                            hasVideoCutUrl: !!shot.videoCutUrl,
                            videoCutUrlType: typeof shot.videoCutUrl,
                            videoCutUrlLength: shot.videoCutUrl ? shot.videoCutUrl.length : 0
                        });
                    });
                }
            });
        }
        
        renderStoryboard(data.scenes);
    } catch (error) {
        // console.error('加载分镜数据失败:', error);
        document.getElementById('storyboard-container').innerHTML =
            `<div class="error-message">
                <p>加载数据失败，请稍后重试。</p>
                <p>错误信息: ${error.message}</p>
            </div>`;
    }
}

/**
 * 获取本地存储中最新且已完成的最后一个任务的ID
 * @returns {string|null} 返回任务ID，如果没有符合条件的任务则返回null
 */
function getLatestCompletedTaskId() {
    // 确保window.currentUserId已设置
    if (!window.currentUserId) {
        console.warn('当前用户ID未设置，无法获取用户关联的任务');
        return null;
    }
    
    // 从本地存储中获取所有任务，使用包含用户ID的键名
    const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
    let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
    
    // 不再自动迁移旧数据，避免跨用户数据泄露
    if (tasks.length === 0) {
        console.warn('当前用户没有本地任务数据');
        return null;
    }
    
    // 过滤出已完成的任务
    const completedTasks = tasks.filter(task => task.status === 'completed');
    
    if (completedTasks.length === 0) {
        return null;
    }
    
    // 按创建时间降序排序，获取最新的已完成任务
    completedTasks.sort((a, b) => new Date(b.created) - new Date(a.created));
    
    // 返回最新已完成任务的ID
    return completedTasks[0].id;
}

// 初始化生成参考图按钮 - 优化版本，使用事件委托减少事件监听器
function initGenerateButtons() {
    // 使用事件委托，将事件监听器绑定到容器上
    const container = document.getElementById('storyboard-container');
    if (container) {
        // 移除并重新添加事件监听器，避免重复绑定
        container.removeEventListener('click', handleGenerateButtonClick);
        container.addEventListener('click', handleGenerateButtonClick);
        
        // 为生成按钮添加悬停效果的事件委托
        container.removeEventListener('mouseenter', handleButtonHover);
        container.addEventListener('mouseenter', handleButtonHover);
        container.removeEventListener('mouseleave', handleButtonHover);
        container.addEventListener('mouseleave', handleButtonHover);
    }
}

// 统一处理按钮悬停效果的事件委托函数
function handleButtonHover(e) {
    const button = e.target.closest('.generate-btn');
    if (button) {
        if (e.type === 'mouseenter') {
            button.style.opacity = '1';
        } else if (e.type === 'mouseleave') {
            button.style.opacity = '0.7';
        }
    }
}

// 统一的按钮点击处理函数
function handleGenerateButtonClick(e) {
    // 检查点击的元素是否是"生成参考图"按钮
    if (e.target.classList.contains('generate-btn')) {
        e.stopPropagation();
        // 调用async函数
        (async () => {
            await generateReferenceImage(e.target);
        })();
    }
    
    // 检查点击的元素是否是"生成视频"按钮
    if (e.target.classList.contains('generate-video-btn') || e.target.closest('.generate-video-btn')) {
        e.stopPropagation();
        const button = e.target.closest('.generate-video-btn');
        // 调用async函数
        (async () => {
            await openGenerateVideoModal(button);
        })();
    }
}

// 统一的按钮鼠标进入处理函数
function handleButtonMouseEnter() {
    this.style.opacity = '1';
}

// 统一的按钮鼠标离开处理函数
function handleButtonMouseLeave() {
    this.style.opacity = '0.7';
}

// 生成参考图
async function generateReferenceImage(button) {
    // 获取当前分镜卡片
    const shotCard = button.closest('.shot-card');
    if (!shotCard) return;
    
    // 获取分镜ID和场次ID
    const shotId = button.getAttribute('data-shot-id');
    const sceneId = shotCard.getAttribute('data-scene-id');
    if (!shotId || !sceneId) {
        alert('无法获取分镜ID或场次ID');
        return;
    }
    
    // 获取分镜数据
    let shotData = getShotDataFromCard(shotCard);
    
    // 使用get_shot_data.php获取完整的分镜数据，包括video_image_Url
    try {
        const response = await fetch('get_shot_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ shotId: shotId })
        });
        if (response.ok) {
            const data = await response.json();
            if (data.code === 0 && data.data) {
                shotData = { ...shotData, ...data.data };
            }
        }
    } catch (error) {
        console.error('获取分镜数据失败:', error);
    }
    
    if (!shotData) return;
    
    // 移除Config对象初始化，API密钥不再从前端传递
    
    // 构造prompt
    const prompt = `${shotData.location}，${shotData.sceneExpectation}，${shotData.time}${shotData.weather}，${shotData.lightTone}。${shotData.shotType}${shotData.cameraAngle}视角，${shotData.lensFocalLength}${shotData.cameraMovement}。${shotData.characters}；${shotData.characterCostumes}；${shotData.characterActions}；${shotData.script}。${shotData.compositionFocus}。`;
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    const originalText = button.textContent;
    button.textContent = '生成中...';
    
    // 调用本地代理接口（解决跨域问题）
    fetch('./text2img_no_proxy.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin', // 包含cookie，确保服务器能识别当前用户
        body: JSON.stringify({
            "prompt": prompt,
            "aspectRatio": "landscape",
            "imgCount": 1,
            "steps": 30
        })
    })
    .then(response => {
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
    })
    .then(data => {
        console.log('API响应数据:', data); // 调试用
        
        // 根据实际API响应结构调整判断逻辑
        if (data.code === 0 && data.msg === "Success") {
            // 获取图片URL，可能有多个位置
            const imageUrl = data.data?.imageUrl || 
                           data.data?.fullImageUrl || 
                           (data.data?.allImages && data.data.allImages[0]?.url);
            
            if (imageUrl) {
                // 显示生成的图片
                displayReferenceImage(shotCard, imageUrl);
                // 保存图片URL到分镜数据中
                saveImageUrlToShot(shotId, sceneId, imageUrl);
            } else {
                alert('生成成功但未获取到图片URL');
                // 恢复按钮状态
                button.disabled = false;
                button.textContent = originalText;
            }
        } else {
            alert('生成参考图失败: ' + (data.msg || data.error || '未知错误'));
            // 恢复按钮状态
            button.disabled = false;
            button.textContent = originalText;
        }
    })
    .catch(error => {
        // console.error('生成参考图出错:', error);
        alert('生成参考图时发生错误: ' + error.message);
        // 恢复按钮状态
        button.disabled = false;
        button.textContent = originalText;
    });
}

// 从卡片中提取分镜数据
function getShotDataFromCard(shotCard) {
    try {
        // 从卡片属性中获取数据
        const shotData = {
            location: shotCard.getAttribute('data-location') || '',
            sceneExpectation: shotCard.getAttribute('data-scene-expectation') || '',
            time: shotCard.getAttribute('data-time') || '',
            weather: shotCard.getAttribute('data-weather') || '',
            lightTone: shotCard.getAttribute('data-light-tone') || '',
            shotType: shotCard.getAttribute('data-shot-type') || '',
            cameraAngle: shotCard.getAttribute('data-camera-angle') || '',
            lensFocalLength: shotCard.getAttribute('data-lens-focal-length') || '',
            cameraMovement: shotCard.getAttribute('data-camera-movement') || '',
            characters: shotCard.getAttribute('data-characters') || '',
            characterCostumes: shotCard.getAttribute('data-character-costumes') || '',
            characterActions: shotCard.getAttribute('data-character-actions') || '',
            script: shotCard.getAttribute('data-script') || '',
            compositionFocus: shotCard.getAttribute('data-composition-focus') || ''
        };
        
        return shotData;
    } catch (error) {
        // console.error('提取分镜数据出错:', error);
        return null;
    }
}

// 显示参考图片
function displayReferenceImage(shotCard, imageUrl) {
    // 找到图片容器
    const imageContainer = shotCard.querySelector('.shot-image');
    if (!imageContainer) return;
    
    // 创建容器元素
    const container = document.createElement('div');
    container.className = 'reference-container';
    container.style.cssText = `
        width: 100%;
        height: 100%;
        position: relative;
    `;
    
    // 创建图片元素
    const img = document.createElement('img');
    img.src = imageUrl;
    img.alt = '参考图';
    img.style.cssText = `
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 4px;
    `;
    
    // 组装元素
    container.appendChild(img);
    
    // 替换原有内容
    imageContainer.innerHTML = '';
    imageContainer.appendChild(container);
    
    // 恢复按钮状态（这里不需要，因为在上面的then/catch中已经处理了）
}

// 保存图片URL到分镜数据中
function saveImageUrlToShot(shotId, sceneId, imageUrl) {
    // 调用后端PHP脚本保存图片URL到JSON文件
    fetch('./save_image_url.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            "shotId": parseInt(shotId),
            "sceneId": parseInt(sceneId),
            "imageUrl": imageUrl,
            "taskId": window.currentTaskId || null
        })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // console.error('保存图片URL失败:', data.error);
            alert('保存图片URL失败: ' + data.error);
        } else {
            console.log('图片URL保存成功');
        }
    })
    .catch(error => {
        // console.error('保存图片URL时发生错误:', error);
        alert('保存图片URL时发生错误: ' + error.message);
    });
}

// 渲染故事板 - 优化版本，减少DOM操作和事件绑定
function renderStoryboard(scenes) {
    const container = document.getElementById('storyboard-container');
    if (!container) {
        console.error('渲染故事板失败: 找不到storyboard-container元素');
        return;
    }
    
    if (!scenes || scenes.length === 0) {
        container.innerHTML = '<div class="no-data-message">暂无分镜数据</div>';
        return;
    }

    // 收集所有分镜，不分场次
    let allShots = [];
    scenes.forEach(scene => {
        if (scene.shots && scene.shots.length > 0) {
            scene.shots.forEach(shot => {
                allShots.push({
                    ...shot,
                    sceneName: scene.name
                });
            });
        }
    });

    // 只用sort_order字段排序
    allShots.sort((a, b) => {
        // 比较sort_order字段
        const sortOrderA = parseInt(a.sortOrder || '0');
        const sortOrderB = parseInt(b.sortOrder || '0');
        return sortOrderA - sortOrderB;
    });

    // 检查是否有分镜数据
    if (allShots.length === 0) {
        container.innerHTML = '<div class="no-data-message">暂无分镜数据</div>';
        return;
    }

    // 获取URL中的task_id参数
    const urlParams = new URLSearchParams(window.location.search);
    let taskId = urlParams.get('task_id') || getLatestCompletedTaskId();
    window.currentTaskId = taskId; // 保存到全局变量，避免重复计算

    // 生成HTML
    let html = `<div class="storyboard-grid">
                  <div class="info-message" style="grid-column: 1 / -1; padding: 5px; text-align: center; background: var(--gray-100); border-radius: var(--border-radius); font-size: 14px;">
                    共找到 ${allShots.length} 个分镜
                  </div>`;

    allShots.forEach((shot, index) => {
        html += createShotCard(shot, index);
    });
    html += '</div>';

    // 清空容器并添加新内容
    container.innerHTML = '';
    container.innerHTML = html;

    // 初始化生成参考图按钮
    initGenerateButtons();
    
    // 初始化拖拽功能
    initDragAndDrop(allShots);
    
    // 使用事件委托为所有编辑按钮添加点击事件，减少事件监听器数量
    const grid = container.querySelector('.storyboard-grid');
    if (grid) {
        grid.addEventListener('click', function (e) {
            const button = e.target.closest('.edit-btn');
            if (button) {
                e.stopPropagation(); // 防止触发拖拽
                const shotId = button.getAttribute('data-shot-id');
                const sceneId = button.getAttribute('data-scene-id');
                window.location.href = `storyboard-detail.php?task_id=${taskId}&scene_id=${sceneId}&id=${shotId}`;
            }
        });
    }
    
    // 检查所有分镜的视频生成任务状态
    checkAllShotsTasks();
}

// 创建分镜卡片
function createShotCard(shot, index) {
    // 检查是否已有参考图
    const hasReferenceImage = shot.imageUrl && shot.imageUrl.trim() !== '';
    // 检查是否已有运镜画面
    const hasCameraMovementImage = shot.imageUrls && shot.imageUrls.trim() !== '';
    
    // 无论是否有参考图，都显示完整信息
    return `
        <div class="shot-card" data-index="${index}" data-shot-id="${shot.id}" data-scene-id="${shot.sceneId || ''}"
             data-location="${shot.location || ''}" 
             data-scene-expectation="${shot.sceneExpectation || ''}" 
             data-time="${shot.time || ''}" 
             data-weather="${shot.weather || ''}" 
             data-light-tone="${shot.lightTone || ''}" 
             data-shot-type="${shot.shotType || ''}" 
             data-camera-angle="${shot.cameraAngle || ''}" 
             data-lens-focal-length="${shot.lensFocalLength || ''}" 
             data-camera-movement="${shot.cameraMovement || ''}" 
             data-characters="${shot.characters || ''}" 
             data-character-costumes="${shot.characterCostumes || ''}" 
             data-character-actions="${shot.characterActions || ''}" 
             data-script="${shot.script || ''}" 
             data-composition-focus="${shot.compositionFocus || ''}" 
             draggable="true">
            <!-- 视频生成中标签 -->
            <div class="video-generating-tag" style="display: none; position: absolute; top: 10px; right: 10px; padding: 6px 12px; background: linear-gradient(90deg, #ff6b6b 0%, #ee5a52 100%); color: white; border-radius: 16px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1); z-index: 10;">
                <i class="fas fa-spinner fa-spin"></i> 视频生成中...
            </div>
            <div class="shot-images">
                <!-- 运镜画面 -->
                <div class="shot-image reference-image">
                    <div class="image-label">运镜画面</div>
                    ${hasCameraMovementImage ? 
                        `<div class="reference-container" style="width: 100%; height: 100%; position: relative;">
                            <img src="${shot.imageUrls}" alt="运镜画面" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px;">
                        </div>` :
                        `<div class="image-placeholder">
                            <i class="fas fa-image"></i>
                            <span>无运镜画面</span>
                        </div>`
                    }
                </div>
                <!-- 成片预览 -->
                <div class="shot-image camera-movement-image">
                    <div class="image-label">成片预览</div>
                    ${shot.videoCutUrl && shot.videoCutUrl.trim() !== '' ? 
                        (() => {
                            try {
                                const videoUrls = JSON.parse(shot.videoCutUrl);
                                if (videoUrls && videoUrls.length > 0) {
                                    // 多个视频，显示第一个作为预览
                                    const firstVideoUrl = videoUrls[0];
                                    return `<div class="reference-container" style="width: 100%; height: 100%; position: relative; cursor: pointer;" onclick="openVideoPreviewModal('${firstVideoUrl}')">
                                        <video src="${firstVideoUrl}" alt="成片预览" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px;">
                                            <source src="${firstVideoUrl}" type="video/mp4">
                                            您的浏览器不支持视频播放
                                        </video>
                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.6); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-play"></i>
                                        </div>
                                    </div>`;
                                } else {
                                    // 空数组，显示占位符
                                    return `<div class="image-placeholder">
                                        <i class="fas fa-video"></i>
                                        <span>无成片预览</span>
                                    </div>`;
                                }
                            } catch (e) {
                                // 解析失败，作为单个URL处理
                                const videoUrl = shot.videoCutUrl.trim();
                                if (videoUrl !== '') {
                                    return `<div class="reference-container" style="width: 100%; height: 100%; position: relative; cursor: pointer;" onclick="openVideoPreviewModal('${videoUrl}')">
                                        <video src="${videoUrl}" alt="成片预览" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px;">
                                            <source src="${videoUrl}" type="video/mp4">
                                            您的浏览器不支持视频播放
                                        </video>
                                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0, 0, 0, 0.6); color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                            <i class="fas fa-play"></i>
                                        </div>
                                    </div>`;
                                } else {
                                    return `<div class="image-placeholder">
                                        <i class="fas fa-video"></i>
                                        <span>无成片预览</span>
                                    </div>`;
                                }
                            }
                        })() :
                        `<div class="image-placeholder">
                            <i class="fas fa-video"></i>
                            <span>无成片预览</span>
                        </div>`
                    }
                </div>
            </div>
            <div class="shot-info">
                <div class="shot-header">
                    <div class="shot-number">镜头 ${shot.id}</div>
                    <div class="shot-edit" style="margin-top: 10px; text-align: right;">
                    <button class="btn btn-secondary edit-btn" data-shot-id="${shot.id}" data-scene-id="${shot.sceneId || ''}">
                        <i class="fas fa-edit"></i> 编辑
                    </button>
                </div>
                </div>
                <div class="shot-details">
                    <p><strong>场次:</strong> <span class="tagS" style="background-color: #4ecdc4; color: #FFFFFF; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${shot.sceneName || '未指定'}</span></p>
                    <p><strong>景别:</strong> <span class="tagS" style="background-color: #4ecdc4; color: #FFFFFF; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${shot.shotType || '未指定'}</span></p>
                    <p><strong>时长:</strong> ${shot.duration || '未指定'}秒</p>
                    <p><strong>内容:</strong> ${shot.content || '无内容描述'}</p>
                    <p><strong>剧本:</strong> ${shot.script || '无剧本'}</p>
                </div>
                ${shot.tags && shot.tags.length > 0 ? `
                    <div class="shot-tags">
                        ${shot.tags.map(tag => `<span class="tag" style="background-color: #4ecdc4; color: #FFFFFF; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${tag}</span>`).join('')}
                    </div>
                ` : ''}
                <div class="shot-actions" style="margin-top: 10px; text-align: right;">
                    ${shot.video_image_Url && shot.video_image_Url.trim() !== '' ? 
                        `<button class="btn btn-primary generate-video-btn" data-shot-id="${shot.id}" data-scene-id="${shot.sceneId || ''}">
                            <i class="fas fa-video"></i> 生成视频
                        </button>` : 
                        `<span class="btn btn-secondary" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: not-allowed; font-size: 14px;">
                            暂无切片
                        </span>`
                    }
                </div>
                
            </div>
        </div>
    `;
}


// 初始化浮动提示条
function initFloatingBar() {
    // 默认显示浮动提示条
    const floatingBar = document.querySelector('.floating-bar');
    const mainContent = document.querySelector('.main-content');
    const floatingBarHeight = 80; // 浮动提示条的高度
    
    // 动态调整main-content的padding-bottom
    function adjustMainContentPadding() {
        if (floatingBar && mainContent) {
            if (floatingBar.classList.contains('hidden')) {
                mainContent.style.paddingBottom = '0';
            } else {
                mainContent.style.paddingBottom = floatingBarHeight + 'px';
            }
        }
    }
    
    // 初始化时调整一次
    adjustMainContentPadding();

    // 点击"分镜区"标签时显示浮动提示条
    const storyboardTab = document.querySelector('.function-left .tab');
    if (storyboardTab) {
        storyboardTab.addEventListener('click', function () {
            floatingBar.classList.remove('hidden');
            adjustMainContentPadding();
        });
    }

    // 关闭按钮
    const closeBtn = document.querySelector('.floating-bar .close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function () {
            floatingBar.classList.add('hidden');
            adjustMainContentPadding();
        });
    }
}

// 初始化拖拽功能 - 优化版本，增强动画效果和用户体验
function initDragAndDrop(shots) {
    const grid = document.querySelector('.storyboard-grid');
    let draggedCard = null;
    let lastDragOverCard = null;
    let lastDragPosition = { x: 0, y: 0 };
    let isDragging = false;

    // 保存事件监听器函数，以便后续移除
    const eventHandlers = {
        dragstart: function (e) {
            const card = e.target.closest('.shot-card');
            if (card) {
                draggedCard = card;
                isDragging = true;
                
                // 记录初始拖拽位置
                lastDragPosition = { x: e.clientX, y: e.clientY };
                
                // 使用requestAnimationFrame优化重绘
                requestAnimationFrame(() => {
                    card.classList.add('dragging');
                });
                
                // 显示拖拽提示
                showDragHint('拖拽分镜到目标位置');
            }
        },
        dragend: function (e) {
            if (draggedCard) {
                // 移除拖拽类
                draggedCard.classList.remove('dragging');
                
                // 清除所有卡片上的拖拽指示类
                const allCards = grid.querySelectorAll('.shot-card');
                allCards.forEach(c => {
                    c.classList.remove('drag-over-before', 'drag-over-after', 'insert-before', 'insert-after');
                });
                
                draggedCard = null;
                lastDragOverCard = null;
                isDragging = false;
                
                // 隐藏拖拽提示
                hideDragHint();
            }
        },
        dragenter: function (e) {
            e.preventDefault();
        },
        dragleave: function (e) {
            // 只有当鼠标真正离开当前元素时才移除类
            const card = e.target.closest('.shot-card');
            if (card && (e.relatedTarget !== card && !card.contains(e.relatedTarget))) {
                card.classList.remove('drag-over-before', 'drag-over-after');
            }
        },
        drop: function (e) {
            e.preventDefault();
            const card = e.target.closest('.shot-card');

            if (draggedCard && card && draggedCard !== card) {
                // 清除当前卡片的拖拽指示类
                card.classList.remove('drag-over-before', 'drag-over-after');
                
                if (lastDragOverCard && lastDragOverCard !== card) {
                    lastDragOverCard.classList.remove('drag-over-before', 'drag-over-after');
                }
                lastDragOverCard = null;

                // 计算放置位置
                const rect = card.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;

                // 插入到目标位置
                if (e.clientY <= midpoint) {
                    // 插入到当前元素之前
                    card.parentNode.insertBefore(draggedCard, card);
                } else {
                    // 插入到当前元素之后
                    card.parentNode.insertBefore(draggedCard, card.nextSibling);
                }

                // 隐藏拖拽提示
                hideDragHint();
                
                // 显示保存中提示
                showDragHint('正在保存排序...');

                // 移除拖拽类并添加放置完成动画
                draggedCard.classList.remove('dragging');
                draggedCard.classList.add('placement-complete');
                
                // 布局重组动画
                const allCards = document.querySelectorAll('.shot-card');
                allCards.forEach((c, index) => {
                    if (c !== draggedCard) {
                        setTimeout(() => {
                            c.classList.add('reorganizing');
                            setTimeout(() => {
                                c.classList.remove('reorganizing');
                            }, 300);
                        }, index * 50);
                    }
                });

                // 移除放置完成动画类
                setTimeout(() => {
                    draggedCard.classList.remove('placement-complete');
                }, 300);

                // 使用requestAnimationFrame优化索引更新，减少重绘
                requestAnimationFrame(() => {
                    updateCardIndices();
                    // 更新排序顺序到数据库
                    updateSortOrder().then(success => {
                        if (success) {
                            // 显示保存成功提示
                            showDragHint('排序保存成功！', 'success');
                            // 2秒后隐藏提示
                            setTimeout(hideDragHint, 2000);
                        } else {
                            // 显示保存失败提示
                            showDragHint('排序保存失败，请重试', 'error');
                            // 3秒后隐藏提示
                            setTimeout(hideDragHint, 3000);
                        }
                    });
                });
            }
        }
    };

    // 节流函数，减少频繁执行
    function throttle(func, delay) {
        let lastCall = 0;
        return function(...args) {
            const now = Date.now();
            if (now - lastCall >= delay) {
                lastCall = now;
                func.apply(this, args);
            }
        };
    }

    // 节流处理dragover事件，减少计算和DOM操作
    const throttledDragOver = throttle(function (e) {
        e.preventDefault();
        const card = e.target.closest('.shot-card');
        if (card && card !== draggedCard) {
            // 只在卡片变化时更新
            if (card !== lastDragOverCard) {
                // 清除之前卡片的拖拽指示类
                if (lastDragOverCard) {
                    lastDragOverCard.classList.remove('drag-over-before', 'drag-over-after');
                }
                lastDragOverCard = card;
                
                // 计算鼠标位置相对于当前卡片的位置
                const rect = card.getBoundingClientRect();
                const midpoint = rect.top + rect.height / 2;
                
                // 移除当前卡片的所有拖拽指示类
                card.classList.remove('drag-over-before', 'drag-over-after');
                
                // 根据鼠标位置添加相应的拖拽指示类
                if (e.clientY <= midpoint) {
                    card.classList.add('drag-over-before');
                } else {
                    card.classList.add('drag-over-after');
                }
            }
        }
    }, 20); // 进一步减少节流时间，提高响应速度

    // 添加事件监听器
    grid.addEventListener('dragstart', eventHandlers.dragstart);
    grid.addEventListener('dragend', eventHandlers.dragend);
    grid.addEventListener('dragenter', eventHandlers.dragenter);
    grid.addEventListener('dragover', throttledDragOver);
    grid.addEventListener('dragleave', eventHandlers.dragleave);
    grid.addEventListener('drop', eventHandlers.drop);
    
    // 更新卡片索引 - 优化版本，减少DOM操作
    function updateCardIndices() {
        const currentGrid = document.querySelector('.storyboard-grid');
        if (!currentGrid) {
            console.error('更新卡片索引失败: 找不到storyboard-grid元素');
            return;
        }
        const updatedCards = currentGrid.querySelectorAll('.shot-card');
        updatedCards.forEach((card, index) => {
            // 只更新必要的属性
            card.setAttribute('data-index', index);
            
            // 只在有参考图时更新data-image-url属性
            const referenceContainer = card.querySelector('.reference-container');
            if (referenceContainer) {
                const img = referenceContainer.querySelector('img');
                if (img) {
                    card.setAttribute('data-image-url', img.src);
                }
            }
        });
    }
    
    // 更新排序顺序到数据库
    function updateSortOrder() {
        return new Promise((resolve, reject) => {
            // 每次都重新获取当前的grid元素，避免使用过时的引用
            const currentGrid = document.querySelector('.storyboard-grid');
            if (!currentGrid) {
                console.error('更新排序顺序失败: 找不到storyboard-grid元素');
                resolve(false);
                return;
            }
            
            const updatedCards = currentGrid.querySelectorAll('.shot-card');
            const sortOrderData = [];
            const shotIds = new Set();
            
            // 收集所有分镜的ID和新的排序顺序，确保没有重复
            updatedCards.forEach((card, index) => {
                const shotId = card.getAttribute('data-shot-id');
                if (shotId && !shotIds.has(shotId)) {
                    shotIds.add(shotId);
                    sortOrderData.push({
                        shotId: shotId,
                        sortOrder: index + 1 // 从1开始
                    });
                }
            });
            
            // 确保任务ID存在
            const taskId = window.currentTaskId || window.dbTaskId;
            
            if (!taskId) {
                console.error('更新排序顺序失败: 缺少任务ID');
                resolve(false);
                return;
            }
            
            console.log('更新排序顺序，任务ID:', taskId);
            console.log('排序数据:', sortOrderData);
            
            // 调用后端API更新排序顺序
            fetch('./update_sort_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    "sortOrderData": sortOrderData,
                    "taskId": taskId
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('API响应:', data);
                if (!data.success) {
                    console.error('更新排序顺序失败:', data.error);
                    resolve(false);
                } else {
                    console.log('排序顺序更新成功');
                    resolve(true);
                }
            })
            .catch(error => {
                console.error('更新排序顺序时发生错误:', error);
                resolve(false);
            });
        });
    }
    
    // 显示拖拽提示
    function showDragHint(message, type = 'info') {
        // 检查是否已存在提示元素
        let hintElement = document.getElementById('drag-hint');
        
        if (!hintElement) {
            // 创建提示元素
            hintElement = document.createElement('div');
            hintElement.id = 'drag-hint';
            hintElement.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 20px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 500;
                z-index: 9999;
                transition: all 0.3s ease;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
                display: flex;
                align-items: center;
                gap: 8px;
            `;
            document.body.appendChild(hintElement);
        }
        
        // 设置提示内容和样式
        hintElement.textContent = message;
        
        // 根据类型设置不同的样式
        switch (type) {
            case 'success':
                hintElement.style.backgroundColor = '#d4edda';
                hintElement.style.color = '#155724';
                hintElement.style.border = '1px solid #c3e6cb';
                break;
            case 'error':
                hintElement.style.backgroundColor = '#f8d7da';
                hintElement.style.color = '#721c24';
                hintElement.style.border = '1px solid #f5c6cb';
                break;
            default:
                hintElement.style.backgroundColor = '#d1ecf1';
                hintElement.style.color = '#0c5460';
                hintElement.style.border = '1px solid #bee5eb';
        }
        
        // 显示提示
        hintElement.style.opacity = '0';
        hintElement.style.transform = 'translateX(100%)';
        
        setTimeout(() => {
            hintElement.style.opacity = '1';
            hintElement.style.transform = 'translateX(0)';
        }, 10);
    }
    
    // 隐藏拖拽提示
    function hideDragHint() {
        const hintElement = document.getElementById('drag-hint');
        if (hintElement) {
            hintElement.style.opacity = '0';
            hintElement.style.transform = 'translateX(100%)';
            
            setTimeout(() => {
                if (hintElement.parentNode) {
                    hintElement.parentNode.removeChild(hintElement);
                }
            }, 300);
        }
    }
    
    // 清理函数，用于移除事件监听器，防止内存泄漏
    function cleanupDragAndDrop() {
        grid.removeEventListener('dragstart', eventHandlers.dragstart);
        grid.removeEventListener('dragend', eventHandlers.dragend);
        grid.removeEventListener('dragenter', eventHandlers.dragenter);
        grid.removeEventListener('dragover', throttledDragOver);
        grid.removeEventListener('dragleave', eventHandlers.dragleave);
        grid.removeEventListener('drop', eventHandlers.drop);
    }
    
    // 保存清理函数到grid对象，以便在需要时调用
    grid._cleanupDragAndDrop = cleanupDragAndDrop;
}

// 生成故事板视频
function generateStoryboardVideo() {
    // 确保所有卡片的图片URL都已更新
    updateCardIndices();
    
    // 收集所有分镜图片
    const shotCards = document.querySelectorAll('.shot-card');
    const shotImages = [];
    
    shotCards.forEach((card, index) => {
        // 直接从图片元素获取URL，确保获取到最新的图片地址
        const referenceContainer = card.querySelector('.reference-container');
        let imageUrl = '';
        
        if (referenceContainer) {
            const img = referenceContainer.querySelector('img');
            if (img) {
                imageUrl = img.src;
            }
        }
        
        if (imageUrl) {
            shotImages.push({
                id: card.getAttribute('data-shot-id'),
                imageUrl: imageUrl,
                index: index
            });
        }
    });
    
    // 检查是否有足够的图片
    if (shotImages.length === 0) {
        alert('请先为分镜生成参考图');
        return;
    }
    
    // 计算所需积分和预期时长
    const requiredPoints = Config.VIDEO_GENERATION_COST * shotImages.length;
    const estimatedTime = shotImages.length * 30; // 每个分镜估计30秒生成时间
    
    // 显示更详细的确认对话框
    if (!confirm(`确定要生成包含 ${shotImages.length} 个分镜的视频吗？\n\n积分消耗：${requiredPoints} 积分\n预期时长：约 ${Math.floor(estimatedTime/60)} 分钟 ${estimatedTime%60} 秒\n\n注意：视频生成过程中请勿关闭浏览器窗口，生成完成后将自动显示结果。`)) {
        return;
    }
    
    // 显示加载状态
    const loadingOverlay = document.createElement('div');
    loadingOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.8);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        color: white;
    `;
    
    const spinner = document.createElement('div');
    spinner.style.cssText = `
        border: 4px solid rgba(255, 255, 255, 0.3);
        border-top: 4px solid white;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        animation: spin 1s linear infinite;
        margin-bottom: 20px;
    `;
    
    const text = document.createElement('div');
    text.textContent = '正在生成视频，请耐心等待...';
    text.style.cssText = `
        font-size: 18px;
        margin-bottom: 10px;
    `;
    
    const progress = document.createElement('div');
    progress.textContent = `已处理 ${0}/${shotImages.length} 个分镜`;
    progress.style.cssText = `
        font-size: 16px;
        margin-bottom: 20px;
    `;
    
    const cancelBtn = document.createElement('button');
    cancelBtn.textContent = '取消生成';
    cancelBtn.style.cssText = `
        padding: 10px 20px;
        background: #dc3545;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    `;
    
    loadingOverlay.appendChild(spinner);
    loadingOverlay.appendChild(text);
    loadingOverlay.appendChild(progress);
    loadingOverlay.appendChild(cancelBtn);
    document.body.appendChild(loadingOverlay);
    
    // 取消生成
    let isCancelled = false;
    cancelBtn.addEventListener('click', () => {
        isCancelled = true;
        loadingOverlay.remove();
    });
    
    // 添加动画样式
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    `;
    document.head.appendChild(style);
    
    // 调用API生成视频
    const data = {
        action: 'create_task',
        shot_id: null, // 故事板视频生成暂时使用null
        image_urls: shotImages.map(shot => shot.imageUrl),
        prompt: '故事板视频，将分镜图片转换为流畅的动画，保持画面的故事性和连贯性',
        duration: 8
    };
    
    fetch('./video_api.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(result => {
        if (isCancelled) return;
        
        loadingOverlay.remove();
        
        if (result.code === 0 && result.data.task_id) {
            // 视频生成任务创建成功，开始轮询状态
            pollVideoStatus(result.data.task_id, shotImages.length);
        } else {
            alert('视频生成失败: ' + (result.msg || '未知错误'));
        }
    })
    .catch(error => {
        if (isCancelled) return;
        
        loadingOverlay.remove();
        alert('视频生成失败: ' + error.message);
    });
}

// 轮询视频生成状态
function pollVideoStatus(taskId, totalShots) {
    let retries = 0;
    const maxRetries = 60; // 最多轮询60次，每次间隔5秒，总共5分钟
    let loadingOverlay = null;
    
    // 创建加载覆盖层
    function createLoadingOverlay() {
        loadingOverlay = document.createElement('div');
        loadingOverlay.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            color: white;
        `;
        
        const spinner = document.createElement('div');
        spinner.style.cssText = `
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin-bottom: 20px;
        `;
        
        const text = document.createElement('div');
        text.textContent = '正在生成视频，请耐心等待...';
        text.style.cssText = `
            font-size: 18px;
            margin-bottom: 10px;
        `;
        
        const progress = document.createElement('div');
        progress.innerHTML = `
            <div style="margin-bottom: 5px;">进度：${0}/${totalShots} 个分镜</div>
            <div style="width: 300px; height: 8px; background: rgba(255, 255, 255, 0.2); border-radius: 4px; overflow: hidden;">
                <div id="progressBar" style="width: ${(1/totalShots)*100}%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease;"></div>
            </div>
        `;
        
        const timeInfo = document.createElement('div');
        timeInfo.textContent = `已等待 ${Math.floor(retries*5/60)} 分钟 ${(retries*5)%60} 秒`;
        timeInfo.style.cssText = `
            font-size: 14px;
            margin-top: 10px;
            color: rgba(255, 255, 255, 0.8);
        `;
        
        // 添加动画样式
        const style = document.createElement('style');
        style.textContent = `
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
        
        loadingOverlay.appendChild(spinner);
        loadingOverlay.appendChild(text);
        loadingOverlay.appendChild(progress);
        loadingOverlay.appendChild(timeInfo);
        document.body.appendChild(loadingOverlay);
    }
    
    // 更新加载覆盖层信息
    function updateLoadingOverlay() {
        if (loadingOverlay) {
            const timeInfo = loadingOverlay.querySelector('div:nth-child(4)');
            if (timeInfo) {
                timeInfo.textContent = `已等待 ${Math.floor(retries*5/60)} 分钟 ${(retries*5)%60} 秒`;
            }
        }
    }
    
    // 移除加载覆盖层
    function removeLoadingOverlay() {
        if (loadingOverlay && loadingOverlay.parentNode) {
            loadingOverlay.parentNode.removeChild(loadingOverlay);
            loadingOverlay = null;
        }
    }
    
    createLoadingOverlay();
    
    const checkStatus = () => {
        if (retries >= maxRetries) {
            removeLoadingOverlay();
            alert('视频生成超时，请稍后在历史记录中查询');
            return;
        }
        
        retries++;
        
        fetch('./video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_task',
                task_id: taskId
            })
        })
        .then(response => response.json())
        .then(result => {
            if (result.code === 0 && result.data) {
                const taskData = result.data;
                const status = taskData.status;
                
                if (status === 'completed') {
                    // 视频生成成功
                    removeLoadingOverlay();
                    if (taskData.video_urls && taskData.video_urls.length > 0) {
                        showVideoResult({
                            videoUrl: taskData.video_urls[0],
                            status: 'success'
                        });
                    } else {
                        alert('视频生成成功，但未找到视频URL');
                    }
                } else if (status === 'failed') {
                    removeLoadingOverlay();
                    alert('视频生成失败: ' + (taskData.error_message || '未知错误'));
                } else {
                    // 继续轮询，更新时间信息
                    updateLoadingOverlay();
                    setTimeout(checkStatus, 5000);
                }
            } else {
                // 继续轮询，更新时间信息
                updateLoadingOverlay();
                setTimeout(checkStatus, 5000);
            }
        })
        .catch(error => {
            // 继续轮询，更新时间信息
            updateLoadingOverlay();
            setTimeout(checkStatus, 5000);
        });
    };
    
    checkStatus();
}

// 打开生成视频模态框
async function openGenerateVideoModal(button) {
    // 获取当前分镜卡片
    const shotCard = button.closest('.shot-card');
    if (!shotCard) return;
    
    // 获取分镜ID和场次ID
    const shotId = button.getAttribute('data-shot-id');
    const sceneId = button.getAttribute('data-scene-id');
    if (!shotId || !sceneId) {
        alert('无法获取分镜ID或场次ID');
        return;
    }
    
    // 检查是否有进行中的视频生成任务
    const ongoingTask = await checkOngoingTasks(shotId);
    
    // 获取分镜数据
    let shotData = getShotDataFromCard(shotCard);
    
    // 使用get_shot_data.php获取完整的分镜数据，包括imageUrls和video_image_Url
    try {
        const response = await fetch(`get_shot_data.php?shotId=${shotId}&scenes_id=${sceneId}`);
        if (response.ok) {
            const data = await response.json();
            if (data.code === 0 && data.data) {
                shotData = { ...shotData, ...data.data };
            }
        }
    } catch (error) {
        console.error('获取分镜数据失败:', error);
    }
    
    if (!shotData) return;
    
    // 创建模态框
    const modal = document.createElement('div');
    modal.className = 'modal image-modal';
    if (ongoingTask) {
        modal.dataset.taskId = ongoingTask.task_id;
    }
    
    // 优先使用video_image_Url中的运镜画面数据
    const cameraMovementImages = shotData.video_image_Url && shotData.video_image_Url.trim() !== '' ? shotData.video_image_Url : shotData.video_image_Url;
    
    // 检查该分镜是否已经有生成完毕的视频
    let hasCompletedVideo = false;
    let completedVideoUrls = [];
    
    // 直接检查是否有已完成的视频任务，不依赖videoCutUrl
    if (!ongoingTask) {
        try {
            // 获取该分镜的已完成视频任务
            const response = await fetch('video_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_user_tasks',
                    shot_id: shotId,
                    status: 2 // 已完成状态
                })
            });
            
            if (response.ok) {
                const data = await response.json();
                if (data.code === 0 && data.data && data.data.length > 0) {
                    // 找到对应场次的已完成视频任务
                    const completedTask = data.data.find(task => task.scenes_id === sceneId);
                    if (completedTask) {
                        // 单独调用get_task API获取任务详情，包括子任务
                        const taskDetailResponse = await fetch('video_api.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                action: 'get_task',
                                task_id: completedTask.task_id
                            })
                        });
                        
                        if (taskDetailResponse.ok) {
                            const taskDetailData = await taskDetailResponse.json();
                            if (taskDetailData.code === 0 && taskDetailData.data && taskDetailData.data.sub_tasks) {
                                const completedSubTasks = taskDetailData.data.sub_tasks.filter(subTask => subTask.status === 2 && subTask.video_url);
                                if (completedSubTasks.length > 0) {
                                    hasCompletedVideo = true;
                                    completedVideoUrls = completedSubTasks.map(subTask => subTask.video_url);
                                }
                            }
                        }
                    }
                }
            }
        } catch (error) {
            console.error('检查已完成视频任务失败:', error);
        }
    }
    
    // 兼容处理：如果没有找到已完成的视频任务，但shotData中有videoCutUrl，尝试使用它
    if (!hasCompletedVideo && shotData.videoCutUrl) {
        try {
            const videoUrls = JSON.parse(shotData.videoCutUrl);
            if (videoUrls && videoUrls.length > 0) {
                hasCompletedVideo = true;
                completedVideoUrls = videoUrls;
            }
        } catch (e) {
            // 解析失败，尝试作为单个URL处理
            if (shotData.videoCutUrl.trim() !== '') {
                hasCompletedVideo = true;
                completedVideoUrls = [shotData.videoCutUrl];
            }
        }
    }
    
    // 生成视频预览HTML
    let videoPreviewHtml = '';
    if (hasCompletedVideo && completedVideoUrls.length > 0) {
        videoPreviewHtml = completedVideoUrls.map((videoUrl, index) => `
            <div class="video-preview-item" style="flex-shrink: 0; width: 300px; height: 320px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #ffffff; display: flex; flex-direction: column;">
                <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px;">
                    <video controls style="width: 100%; height: 100%; object-fit: contain;">
                        <source src="${videoUrl}" type="video/mp4">
                        您的浏览器不支持视频播放
                    </video>
                </div>
                <div style="padding: 10px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
                    <p>片段 ${index + 1}</p>
                </div>
            </div>
        `).join('');
    } else if (shotData.videoCutUrl) {
        // 使用shotData中的视频数据
        try {
            const videoUrls = JSON.parse(shotData.videoCutUrl);
            if (videoUrls && videoUrls.length > 0) {
                videoPreviewHtml = videoUrls.map((videoUrl, index) => `
                    <div class="video-preview-item" style="flex-shrink: 0; width: 300px; height: 320px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #ffffff; display: flex; flex-direction: column;">
                        <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px;">
                            <video controls style="width: 100%; height: 100%; object-fit: contain;">
                                <source src="${videoUrl}" type="video/mp4">
                                您的浏览器不支持视频播放
                            </video>
                        </div>
                        <div style="padding: 10px; border-top: 1px solid #dee2e6; font-size: 12px; color: #6c757d;">
                            <p>片段 ${index + 1}</p>
                        </div>
                    </div>
                `).join('');
            } else {
                videoPreviewHtml = `
                    <div class="video-preview-placeholder" style="flex-shrink: 0; width: 300px; height: 320px; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #ffffff;">
                        <div style="color: #6c757d; text-align: center;">
                            <i class="fas fa-video" style="font-size: 32px; margin-bottom: 15px; color: #adb5bd;"></i>
                            <div>生成后将显示视频预览</div>
                        </div>
                    </div>
                `;
            }
        } catch (e) {
            videoPreviewHtml = `
                <div class="video-preview-placeholder" style="flex-shrink: 0; width: 300px; height: 320px; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #ffffff;">
                    <div style="color: #6c757d; text-align: center;">
                        <i class="fas fa-video" style="font-size: 32px; margin-bottom: 15px; color: #adb5bd;"></i>
                        <div>生成后将显示视频预览</div>
                    </div>
                </div>
            `;
        }
    } else {
        videoPreviewHtml = `
            <div class="video-preview-placeholder" style="flex-shrink: 0; width: 300px; height: 320px; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #ffffff;">
                <div style="color: #6c757d; text-align: center;">
                    <i class="fas fa-video" style="font-size: 32px; margin-bottom: 15px; color: #adb5bd;"></i>
                    <div>生成后将显示视频预览</div>
                </div>
            </div>
        `;
    }
    
    let modalHtml = `
        <div class="modal-content image-modal-content" style="width: 90%; max-width: 900px; height: 90%; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="modal-header">
                <h3 style="margin: 0; color: #FFFFFF; font-size: 18px;">生成视频</h3>
                <button class="modal-close" onclick="this.closest('.modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d;">&times;</button>
            </div>
            <div class="modal-body image-modal-body" style="flex: 1; overflow-y: auto; padding: 20px;">
                <!-- 镜号区域 -->
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-info-circle" style="margin-right: 8px; color: #4ecdc4;"></i>
                        镜号信息
                    </label>
                    <div style="display: flex; gap: 10px; flex-wrap: wrap; padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px;">
                        <span style="padding: 6px 12px; background: #e3f2fd; color: #4ecdc4; border-radius: 16px; font-size: 14px; font-weight: 500;">场次: ${shotData.sceneId || sceneId || '未指定'}</span>
                        <span style="padding: 6px 12px; background: #e3f2fd; color: #4ecdc4; border-radius: 16px; font-size: 14px; font-weight: 500;">镜号: ${shotId}</span>
                    </div>
                </div>
                
                <!-- 任务状态区域 -->
                ${ongoingTask ? `
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-tasks" style="margin-right: 8px; color: #4ecdc4;"></i>
                        进行中任务
                    </label>
                    <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                            <span style="font-weight: 500;">任务ID: ${ongoingTask.task_id}</span>
                            <span class="task-status" style="padding: 2px 8px; background: #fdcb6e; color: #000; border-radius: 12px; font-size: 12px;">${getStatusText(ongoingTask.status)}</span>
                        </div>
                        <div style="margin-bottom: 15px;">
                            <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                <span>进度</span>
                                <span class="task-progress">${ongoingTask.progress}%</span>
                            </div>
                            <div style="width: 100%; height: 8px; background: #dee2e6; border-radius: 4px; overflow: hidden;">
                                <div class="task-progress-bar" style="width: ${ongoingTask.progress}%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease;"></div>
                            </div>
                        </div>
                        
                        <!-- 子任务状态 -->
                        <div style="margin-bottom: 15px;">
                            <div style="margin-bottom: 10px; font-weight: 500;">视频片段生成状态</div>
                            <div class="sub-tasks-container" style="max-height: 200px; overflow-y: auto;">
                                ${ongoingTask.sub_tasks && ongoingTask.sub_tasks.length > 0 ? `
                                    ${ongoingTask.sub_tasks.map((subTask, index) => `
                                        <div style="margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;">
                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                                <span style="font-weight: 500;">片段 ${index + 1}</span>
                                                <span style="padding: 2px 8px; background: ${subTask.status === 0 ? '#fdcb6e' : subTask.status === 1 ? '#74b9ff' : subTask.status === 2 ? '#55efc4' : '#ff7675'}; color: ${subTask.status === 0 ? '#000' : '#fff'}; border-radius: 12px; font-size: 12px;">
                                                    ${getStatusText(subTask.status)}
                                                </span>
                                            </div>
                                            ${subTask.error_message ? `<div style="font-size: 12px; color: #dc3545; margin-top: 5px;">错误: ${subTask.error_message}</div>` : ''}
                                        </div>
                                    `).join('')}
                                ` : `
                                    <div style="text-align: center; color: #6c757d;">暂无子任务信息</div>
                                `}
                            </div>
                        </div>
                        
                        <div style="font-size: 14px; color: #6c757d;">
                                <p>创建时间: ${ongoingTask.created_at}</p>
                                ${ongoingTask.estimated_completion_time ? `<p>预计完成时间: ${ongoingTask.estimated_completion_time}</p>` : ''}
                                <p style="margin-top: 10px; font-style: italic;">提示: 任务在后台执行，您可以关闭此页面，稍后返回查看结果</p>
                            </div>
                    </div>
                </div>
                ` : ''}
                
                <!-- 运镜画面区域 -->
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-video" style="margin-right: 8px; color: #4ecdc4;"></i>
                        运镜画面<span style="font-size:9px;color:#c6c6c6;">（备注:每相邻的两张图作为生成视频的首尾帧，以确保运镜画面的连续）</span>
                    </label>
                    <div style="padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px;">
                        <div class="video-image-thumbnails" id="videoImageThumbnails" style="display: flex; gap: 10px; overflow-x: auto; padding: 10px 0; -ms-overflow-style: none; scrollbar-width: none;">
                            ${cameraMovementImages ? `
                                <!-- 解析运镜画面中的所有图片 -->
                                ${(() => {
                                    let thumbnails = '';
                                    try {
                                        // 尝试解析imageUrls
                                        if (cameraMovementImages.startsWith('[')) {
                                            // 如果是JSON数组
                                            const images = JSON.parse(cameraMovementImages);
                                            images.forEach((img, index) => {
                                                // 处理不同格式的图片数据
                                                const imgUrl = typeof img === 'string' ? img : (img.url || img);
                                                thumbnails += `
                                                    <div class="thumbnail" style="flex-shrink: 0; width: 120px; height: 120px; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                                        <img src="${imgUrl}" alt="运镜画面 ${index + 1}" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                    </div>
                                                `;
                                            });
                                        } else {
                                            // 如果是单个URL
                                            thumbnails = `
                                                <div class="thumbnail" style="flex-shrink: 0; width: 120px; height: 120px; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                                    <img src="${cameraMovementImages}" alt="运镜画面" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                                </div>
                                            `;
                                        }
                                    } catch (e) {
                                        // 如果解析失败，尝试作为单个URL处理
                                        thumbnails = `
                                            <div class="thumbnail" style="flex-shrink: 0; width: 120px; height: 120px; border: 1px solid #dee2e6; border-radius: 4px; overflow: hidden; transition: all 0.2s ease; display: flex; align-items: center; justify-content: center; background: #f8f9fa;">
                                                <img src="${cameraMovementImages}" alt="运镜画面" style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                            </div>
                                        `;
                                    }
                                    return thumbnails;
                                })()}
                            ` : `
                                <div style="color: #6c757d; padding: 30px; border: 2px dashed #dee2e6; border-radius: 8px; flex-shrink: 0; min-width: 200px; text-align: center;">
                                    <i class="fas fa-video" style="font-size: 24px; margin-bottom: 10px; color: #adb5bd;"></i>
                                    <div>暂无运镜画面</div>
                                </div>
                            `}
                        </div>
                        <div style="margin-top: 10px; font-size: 12px; color: #6c757d;">
                            ${cameraMovementImages ? `
                                ${(() => {
                                    try {
                                        if (cameraMovementImages.startsWith('[')) {
                                            const images = JSON.parse(cameraMovementImages);
                                            return `<p>共 ${images.length} 张图片，预计生成 ${images.length - 1} 个视频片段</p>`;
                                        } else {
                                            return `<p>共 1 张图片，需要至少 2 张图片才能生成视频</p>`;
                                        }
                                    } catch (e) {
                                        return `<p>共 1 张图片，需要至少 2 张图片才能生成视频</p>`;
                                    }
                                })()}
                            ` : `<p>需要至少 2 张图片才能生成视频</p>`}
                        </div>
                    </div>
                </div>
                
                <!-- 切片提示词区域 -->
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-keyboard" style="margin-right: 8px; color: #4ecdc4;"></i>
                        切片提示词<span style="font-size:9px;color:#c6c6c6;">（备注:切片提示词与切片图是一一对应的关系，提示词直接绝对首尾帧生成视频的最终效果）</span>
                    </label>
                    <div style="padding: 15px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px;">
                    <div class="slice-prompts-container" style="margin-top: 10px;">
                        <div class="slice-prompts-scroll" id="slicePromptsScroll" style="display: flex; gap: 5px; overflow-x: auto; padding: 10px 0; white-space: nowrap;">
                            <!-- 切片提示词输入框将在这里动态添加 -->
                        </div>
                    </div>
                    </div>
                </div>
                
                <!-- 设定区域 -->
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-cog" style="margin-right: 8px; color: #4ecdc4;"></i>
                        视频设定
                    </label>
                    <div style="padding: 20px; background: #ffffff; border: 1px solid #dee2e6; border-radius: 8px;">
                        <!-- 比例选项卡 -->
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; margin-bottom: 10px; color: #6c757d; font-size: 14px;">视频比例</label>
                            <div style="display: flex; gap: 10px;">
                                <button type="button" class="quality-btn" data-quality="480p" style="padding: 8px 16px; border: 1px solid #dee2e6; border-radius: 4px; background: #ffffff; color: #495057; cursor: pointer; transition: all 0.2s ease;">480p</button>
                                <button type="button" class="quality-btn active" data-quality="720p" style="padding: 8px 16px; border: 1px solid #4ecdc4; border-radius: 4px; background: #4ecdc4; color: #ffffff; cursor: pointer; transition: all 0.2s ease;">720p</button>
                                <button type="button" class="quality-btn" data-quality="1080p" style="padding: 8px 16px; border: 1px solid #dee2e6; border-radius: 4px; background: #ffffff; color: #495057; cursor: pointer; transition: all 0.2s ease;">1080p</button>
                            </div>
                        </div>
                        
                        <!-- 时长滑块 -->
                        <div>
                            <label style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; color: #6c757d; font-size: 14px;">
                                <span>单个视频时长</span>
                                <span id="durationValue">8秒</span>
                            </label>
                            <input type="range" id="durationSlider" min="4" max="12" value="8" step="1" style="width: 100%; height: 6px; border-radius: 3px; background: #dee2e6; outline: none;">
                        </div>
                    </div>
                </div>
                
                <!-- 视频预览区域 -->
                <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                    <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                        <i class="fas fa-film" style="margin-right: 8px; color: #4ecdc4;"></i>
                        视频预览
                    </label>
                    <div id="videoPreview" style="width: 100%; min-height: 350px; border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background: #f8f9fa;">
                        <div class="video-preview-list" id="videoPreviewList" style="display: flex; gap: 15px; overflow-x: auto; padding: 10px 0; -ms-overflow-style: none; scrollbar-width: none;">
                            ${videoPreviewHtml}
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #e9ecef; background: #f8f9fa;">
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()" style="padding: 10px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">取消</button>
                ${hasCompletedVideo && completedVideoUrls.length > 1 ? `
                <button class="btn btn-success" onclick="mergeVideoClips(this)" data-shot-id="${shotId}" data-video-urls='${JSON.stringify(completedVideoUrls)}' style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;"><i class="fas fa-film"></i> 合并成片</button>
                ` : ''}
                ${!ongoingTask ? `
                <button class="btn btn-primary" onclick="generateVideoFromModal(this)" data-shot-id="${shotId}" data-scene-id="${sceneId}" style="padding: 10px 20px; background: #4ecdc4; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">${hasCompletedVideo ? '重新生成' : '生成视频'}</button>
                ` : `
                <button class="btn btn-primary" onclick="refreshTaskStatus(this)" data-task-id="${ongoingTask.task_id}" data-shot-id="${shotId}" style="padding: 10px 20px; background: #4ecdc4; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">刷新状态</button>
                `}
            </div>
        </div>
    `;
    
    modal.innerHTML = modalHtml;
    document.body.appendChild(modal);
    
    // 添加比例选项卡交互
    const qualityBtns = modal.querySelectorAll('.quality-btn');
    qualityBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            // 移除所有按钮的active状态
            qualityBtns.forEach(b => {
                b.classList.remove('active');
                b.style.backgroundColor = '#ffffff';
                b.style.color = '#495057';
                b.style.borderColor = '#dee2e6';
            });
            // 添加当前按钮的active状态
            this.classList.add('active');
            this.style.backgroundColor = '#4ecdc4';
            this.style.color = '#ffffff';
            this.style.borderColor = '#4ecdc4';
        });
    });
    
    // 添加时长滑块交互
    const durationSlider = modal.querySelector('#durationSlider');
    const durationValue = modal.querySelector('#durationValue');
    if (durationSlider && durationValue) {
        durationSlider.addEventListener('input', function() {
            durationValue.textContent = this.value + '秒';
        });
    }
    
    // 生成切片提示词输入框
    generateSlicePromptsInputsForVideoModal(cameraMovementImages, modal, shotId);
}

// 为视频模态框生成切片提示词输入框
function generateSlicePromptsInputsForVideoModal(cameraMovementImages, modal, shotId) {
    const promptsContainer = modal.querySelector('.slice-prompts-scroll');
    if (!promptsContainer) return;
    
    // 清空容器
    promptsContainer.innerHTML = '';
    
    // 计算图片数量
    let imageCount = 0;
    try {
        if (cameraMovementImages) {
            if (cameraMovementImages.startsWith('[')) {
                const images = JSON.parse(cameraMovementImages);
                imageCount = images.length;
            } else {
                imageCount = 1;
            }
        }
    } catch (e) {
        imageCount = 1;
    }
    
    // 生成输入框，数量比图片少1
    const inputCount = Math.max(0, imageCount - 1);
    for (let i = 0; i < inputCount; i++) {
        const inputGroup = document.createElement('div');
        inputGroup.style.cssText = `
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            width: 350px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        `;
        
        inputGroup.innerHTML = `
            <div style="margin-bottom: 10px; font-size: 14px; font-weight: 600; color: #495057; text-align: center;">切片${i + 1}</div>
            <textarea class="slice-prompt-input" data-index="${i}" style="
                width: 100%;
                padding: 12px 14px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                font-size: 13px;
                resize: vertical;
                min-height: 120px;
                box-sizing: border-box;
                font-family: inherit;
            "></textarea>
        `;
        
        promptsContainer.appendChild(inputGroup);
    }
    
    // 检查CutPrompt字段是否有值，如果有则回填到输入框中
    if (shotId) {
        fetch('https://wop.cc/get_shot_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ shotId: shotId })
        })
        .then(response => response.json())
        .then(data => {
            if (data.code === 0 && data.data && data.data.CutPrompt) {
                try {
                    const cutPromptData = JSON.parse(data.data.CutPrompt);
                    if (cutPromptData && cutPromptData.data && Array.isArray(cutPromptData.data)) {
                        const inputs = modal.querySelectorAll('.slice-prompt-input');
                        cutPromptData.data.forEach((item, index) => {
                            if (index < inputs.length) {
                                inputs[index].value = item.content || '';
                            }
                        });
                    }
                } catch (error) {
                    console.error('解析CutPrompt字段失败:', error);
                }
            }
        })
        .catch(error => {
            console.error('获取CutPrompt字段失败:', error);
        });
    }
}

// 刷新任务状态
async function refreshTaskStatus(button) {
    const taskId = button.getAttribute('data-task-id');
    const shotId = button.getAttribute('data-shot-id');
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    const originalText = button.textContent;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 刷新中...';
    
    try {
        // 调用新的视频生成API获取任务状态
        const response = await fetch('video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_task',
                task_id: taskId
            })
        });
        
        if (!response.ok) {
            throw new Error(`获取任务状态失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0 || !data.data) {
            throw new Error(`获取任务状态失败: ${data.msg || '未知错误'}`);
        }
        
        const taskData = data.data;
        
        // 检查是否有未完成的子任务
        const hasPendingSubTasks = taskData.sub_tasks && taskData.sub_tasks.some(subTask => 
            subTask.status === 0 || subTask.status === 1
        );
        
        // 检查任务是否仍在执行中，或者有未完成的子任务
        if (taskData.status === 0 || taskData.status === 1 || hasPendingSubTasks) {
            // 保存任务到本地存储
            saveOngoingTask(taskId, shotId);
            
            // 如果任务状态是待处理，或者有未完成的子任务，自动启动任务
            if (taskData.status === 0 || hasPendingSubTasks) {
                try {
                    // 启动视频生成任务（使用超时处理）
                    const startResponse = await fetch('video_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            action: 'start_task',
                            task_id: taskId
                        }),
                        timeout: 30000 // 30秒超时
                    });
                    
                    if (!startResponse.ok) {
                        console.warn(`启动任务响应状态: ${startResponse.status}，但继续轮询任务状态`);
                        // 即使响应状态不是200，也继续轮询任务状态
                    } else {
                        const startData = await startResponse.json();
                        
                        if (startData.code !== 0) {
                            console.warn(`启动任务返回错误: ${startData.msg || '未知错误'}，但继续轮询任务状态`);
                            // 即使返回错误，也继续轮询任务状态
                        }
                    }
                } catch (startError) {
                    console.warn(`启动任务时发生错误: ${startError.message}，但继续轮询任务状态`);
                    // 即使发生错误，也继续轮询任务状态
                }
            }
        } else {
            // 任务已完成或失败，从本地存储中移除
            removeOngoingTask(taskId);
        }
        
        // 更新模态框中的任务状态和进度
        updateTaskStatusInModal(taskId, taskData);
        
        // 恢复按钮状态
        button.disabled = false;
        button.innerHTML = originalText;
        
        // 从子任务中提取已生成的视频URL
        const generatedVideoUrls = [];
        if (taskData.sub_tasks && taskData.sub_tasks.length > 0) {
            taskData.sub_tasks.forEach(subTask => {
                if (subTask.status === 2 && subTask.video_url) {
                    generatedVideoUrls.push(subTask.video_url);
                }
            });
        }
        
        // 如果有已生成的视频，更新视频预览区域
        if (generatedVideoUrls.length > 0) {
            updateVideoPreviewArea(generatedVideoUrls);
        }
        
        // 如果任务仍在执行中，启动轮询
        if (taskData.status === 0 || taskData.status === 1) {
            // 启动轮询任务状态
            startPollingTaskStatus(taskId, button, originalText);
        }
    } catch (error) {
        console.error('刷新任务状态失败:', error);
        alert(`刷新任务状态失败: ${error.message}`);
        
        // 恢复按钮状态
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// 生成视频
async function generateVideoFromModal(button) {
    const shotId = button.getAttribute('data-shot-id');
    const sceneId = button.getAttribute('data-scene-id');
    // 从切片提示词输入框中获取提示词
    const inputs = button.closest('.modal').querySelectorAll('.slice-prompt-input');
    const prompts = Array.from(inputs).map(input => input.value);
    
    // 显示分镜卡片上的视频生成中标签
    showVideoGeneratingTag(shotId);
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    const originalText = button.textContent;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';
    
    try {
        // 获取运镜画面中的图片
        const videoImageUrl = document.getElementById('videoImageThumbnails');
        if (!videoImageUrl) {
            throw new Error('无法获取运镜画面');
        }
        
        // 提取所有图片URL
        const thumbnails = videoImageUrl.querySelectorAll('.thumbnail img');
        const imageUrls = Array.from(thumbnails).map(img => img.src);
        
        if (imageUrls.length < 2) {
            throw new Error('运镜画面至少需要2张图片才能生成视频');
        }
        
        // 检查提示词数量是否与图片数量匹配
        if (prompts.length !== imageUrls.length - 1) {
            throw new Error(`提示词数量（${prompts.length}）与预期数量（${imageUrls.length - 1}）不匹配`);
        }
        
        // 生成视频的配置参数
        const durationSlider = document.getElementById('durationSlider');
        const duration = durationSlider ? parseInt(durationSlider.value) : 8;
        
        // 创建视频生成任务
        const taskData = {
            action: 'create_task',
            shot_id: shotId,
            scene_id: sceneId,
            image_urls: imageUrls,
            prompts: prompts, // 传递所有切片提示词
            duration: duration
        };
        
        // 调用新的视频生成API创建任务
        const response = await fetch('video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(taskData)
        });
        
        if (!response.ok) {
            throw new Error(`创建任务失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0 || !data.data || !data.data.task_id) {
            throw new Error(`创建任务失败: ${data.msg || '未知错误'}`);
        }
        
        const taskId = data.data.task_id;
        
        // 启动视频生成任务（使用超时处理）
        try {
            const startResponse = await fetch('video_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'start_task',
                    task_id: taskId
                }),
                timeout: 30000 // 30秒超时
            });
            
            if (!startResponse.ok) {
                console.warn(`启动任务响应状态: ${startResponse.status}，但继续轮询任务状态`);
                // 即使响应状态不是200，也继续轮询任务状态
            } else {
                const startData = await startResponse.json();
                
                if (startData.code !== 0) {
                    console.warn(`启动任务返回错误: ${startData.msg || '未知错误'}，但继续轮询任务状态`);
                    // 即使返回错误，也继续轮询任务状态
                }
            }
        } catch (startError) {
            console.warn(`启动任务时发生错误: ${startError.message}，但继续轮询任务状态`);
            // 即使发生错误，也继续轮询任务状态
        }
        
        // 无论启动任务是否成功，都开始轮询任务状态
        
        // 更新模态框，显示任务开始处理的状态
        const modal = button.closest('.modal');
        if (modal) {
            modal.dataset.taskId = taskId;
            
            // 更新模态框内容，显示任务详情
            const modalBody = modal.querySelector('.modal-body');
            if (modalBody) {
                // 在模态框中添加任务状态区域
                const taskStatusHTML = `
                    <div class="form-group" style="margin-bottom: 25px; width: 100%;">
                        <label class="form-label" style="display: block; margin-bottom: 10px; color: #495057; font-weight: 500; text-align: left;">
                            <i class="fas fa-tasks" style="margin-right: 8px; color: #4ecdc4;"></i>
                            视频生成任务
                        </label>
                        <div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span style="font-weight: 500;">任务ID: ${taskId}</span>
                                <span class="task-status" style="padding: 2px 8px; background: #fdcb6e; color: #000; border-radius: 12px; font-size: 12px;">处理中</span>
                            </div>
                            <div style="margin-bottom: 15px;">
                                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                                    <span>进度</span>
                                    <span class="task-progress">0%</span>
                                </div>
                                <div style="width: 100%; height: 8px; background: #dee2e6; border-radius: 4px; overflow: hidden;">
                                    <div class="task-progress-bar" style="width: 0%; height: 100%; background: linear-gradient(90deg, #667eea 0%, #764ba2 100%); transition: width 0.3s ease;"></div>
                                </div>
                            </div>
                            
                            <!-- 子任务状态 -->
                            <div style="margin-bottom: 15px;">
                                <div style="margin-bottom: 10px; font-weight: 500;">视频片段生成状态</div>
                                <div class="sub-tasks-container" style="max-height: 300px; overflow-y: auto;">
                                    <div style="text-align: center; color: #6c757d;">任务正在启动，请稍候...</div>
                                </div>
                            </div>
                            
                            <!-- 任务操作按钮 -->
                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button class="btn btn-secondary" onclick="refreshTaskStatus(this)" data-task-id="${taskId}" data-shot-id="${shotId}" style="padding: 8px 16px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                                    <i class="fas fa-sync"></i> 刷新状态
                                </button>
                                <button class="btn btn-danger" onclick="cancelVideoTask(this)" data-task-id="${taskId}" data-shot-id="${shotId}" style="padding: 8px 16px; background: #dc3545; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                                    <i class="fas fa-trash"></i> 取消任务
                                </button>
                            </div>
                            
                            <div style="font-size: 14px; color: #6c757d; margin-top: 15px;">
                                <p>创建时间: ${new Date().toLocaleString()}</p>
                                <p style="margin-top: 10px; font-style: italic;">提示: 任务在后台执行，您可以关闭此页面，稍后返回查看结果</p>
                            </div>
                        </div>
                    </div>
                `;
                
                // 在模态框顶部添加任务状态区域
                const firstFormGroup = modalBody.querySelector('.form-group');
                if (firstFormGroup) {
                    firstFormGroup.insertAdjacentHTML('beforebegin', taskStatusHTML);
                } else {
                    modalBody.insertAdjacentHTML('afterbegin', taskStatusHTML);
                }
            }
        }
        
        // 开始轮询任务状态
        startPollingTaskStatus(taskId, button, originalText);
        
    } catch (error) {
        console.error('视频生成失败:', error);
        alert(`视频生成失败: ${error.message}`);
        
        // 恢复按钮状态
        button.disabled = false;
        button.innerHTML = originalText;
        
        // 隐藏分镜卡片上的视频生成中标签
        hideVideoGeneratingTag(shotId);
    }
}

// 显示分镜卡片上的视频生成中标签
function showVideoGeneratingTag(shotId) {
    const shotCard = document.querySelector(`.shot-card[data-shot-id="${shotId}"]`);
    if (shotCard) {
        const tag = shotCard.querySelector('.video-generating-tag');
        if (tag) {
            tag.style.display = 'block';
        }
    }
}

// 隐藏分镜卡片上的视频生成中标签
function hideVideoGeneratingTag(shotId) {
    const shotCard = document.querySelector(`.shot-card[data-shot-id="${shotId}"]`);
    if (shotCard) {
        const tag = shotCard.querySelector('.video-generating-tag');
        if (tag) {
            tag.style.display = 'none';
        }
    }
}

// 开始轮询任务状态
function startPollingTaskStatus(taskId, button, originalText) {
    let pollingInterval;
    let pollingCount = 0;
    const maxPollingCount = 60; // 最多轮询60次，每次5秒，共5分钟
    
    // 获取shotId
    const shotId = button.getAttribute('data-shot-id');
    
    // 保存任务ID到本地存储，以便页面跳转后恢复
    saveOngoingTask(taskId, shotId);
    
    // 轮询任务状态
    async function pollTaskStatus() {
        try {
            // 调用新的视频生成API获取任务状态
            const response = await fetch('video_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'get_task',
                    task_id: taskId
                })
            });
            
            if (!response.ok) {
                throw new Error(`获取任务状态失败: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.code !== 0 || !data.data) {
                throw new Error(`获取任务状态失败: ${data.msg || '未知错误'}`);
            }
            
            const taskData = data.data;
            
            // 更新按钮文本，显示任务进度
            button.innerHTML = `<i class="fas fa-spinner fa-spin"></i> 生成中... ${taskData.progress}%`;
            
            // 更新模态框中的任务状态和进度
            updateTaskStatusInModal(taskId, taskData);
            
            // 从子任务中提取已生成的视频URL
            const generatedVideoUrls = [];
            if (taskData.sub_tasks && taskData.sub_tasks.length > 0) {
                taskData.sub_tasks.forEach(subTask => {
                    if (subTask.status === 2 && subTask.video_url) {
                        generatedVideoUrls.push(subTask.video_url);
                    }
                });
            }
            
            // 如果有已生成的视频，更新视频预览区域
            if (generatedVideoUrls.length > 0) {
                updateVideoPreviewArea(generatedVideoUrls);
            }
            
            // 检查任务是否完成
            if (taskData.status === 2) {
                // 清除轮询
                clearInterval(pollingInterval);
                
                // 恢复按钮状态
                button.disabled = false;
                button.innerHTML = originalText;
                
                // 隐藏分镜卡片上的视频生成中标签
                if (shotId) {
                    hideVideoGeneratingTag(shotId);
                }
                
                // 从本地存储中移除已完成的任务
                removeOngoingTask(taskId);
                
                // 显示完成信息
                if (taskData.video_urls && taskData.video_urls.length > 0) {
                    alert(`视频生成成功！共生成 ${taskData.video_urls.length} 个视频`);
                } else {
                    alert('视频生成成功，但没有生成视频文件');
                }
            } else if (taskData.status === 3) {
                // 清除轮询
                clearInterval(pollingInterval);
                
                // 恢复按钮状态
                button.disabled = false;
                button.innerHTML = originalText;
                
                // 隐藏分镜卡片上的视频生成中标签
                if (shotId) {
                    hideVideoGeneratingTag(shotId);
                }
                
                // 从本地存储中移除失败的任务
                removeOngoingTask(taskId);
                
                // 显示错误信息
                alert(`视频生成失败: ${taskData.error_message || '未知错误'}`);
            } else if (pollingCount >= maxPollingCount) {
                // 清除轮询
                clearInterval(pollingInterval);
                
                // 恢复按钮状态
                button.disabled = false;
                button.innerHTML = originalText;
                
                // 隐藏分镜卡片上的视频生成中标签
                if (shotId) {
                    hideVideoGeneratingTag(shotId);
                }
                
                // 从本地存储中移除超时的任务
                removeOngoingTask(taskId);
                
                // 显示超时信息
                alert('视频生成任务超时，请稍后手动查看任务状态');
            }
            
            // 增加轮询计数
            pollingCount++;
            
        } catch (error) {
            console.error('获取任务状态失败:', error);
            
            // 清除轮询
            clearInterval(pollingInterval);
            
            // 恢复按钮状态
            button.disabled = false;
            button.innerHTML = originalText;
            
            // 隐藏分镜卡片上的视频生成中标签
            if (shotId) {
                hideVideoGeneratingTag(shotId);
            }
            
            // 从本地存储中移除失败的任务
            removeOngoingTask(taskId);
            
            // 显示错误信息
            alert(`获取任务状态失败: ${error.message}`);
        }
    }
    
    // 立即执行一次轮询
    pollTaskStatus();
    
    // 设置轮询间隔（5秒）
    pollingInterval = setInterval(pollTaskStatus, 5000);
}

// 保存正在执行的任务到本地存储
function saveOngoingTask(taskId, shotId) {
    try {
        const ongoingTasks = JSON.parse(localStorage.getItem('ongoingVideoTasks') || '{}');
        ongoingTasks[taskId] = {
            shotId: shotId,
            timestamp: Date.now()
        };
        localStorage.setItem('ongoingVideoTasks', JSON.stringify(ongoingTasks));
    } catch (error) {
        console.error('保存正在执行的任务失败:', error);
    }
}

// 从本地存储中移除任务
function removeOngoingTask(taskId) {
    try {
        const ongoingTasks = JSON.parse(localStorage.getItem('ongoingVideoTasks') || '{}');
        delete ongoingTasks[taskId];
        localStorage.setItem('ongoingVideoTasks', JSON.stringify(ongoingTasks));
    } catch (error) {
        console.error('移除任务失败:', error);
    }
}

// 获取所有正在执行的任务
function getOngoingTasks() {
    try {
        return JSON.parse(localStorage.getItem('ongoingVideoTasks') || '{}');
    } catch (error) {
        console.error('获取正在执行的任务失败:', error);
        return {};
    }
}

// 更新模态框中的任务状态和进度
function updateTaskStatusInModal(taskId, taskData) {
    // 查找包含该任务ID的模态框
    const modal = document.querySelector(`.modal[data-task-id="${taskId}"]`);
    if (!modal) return;
    
    // 更新任务状态和进度
    const statusElement = modal.querySelector('.task-status');
    const progressElement = modal.querySelector('.task-progress');
    const progressBar = modal.querySelector('.task-progress-bar');
    
    if (statusElement) {
        statusElement.textContent = getStatusText(taskData.status);
    }
    
    if (progressElement) {
        progressElement.textContent = `${taskData.progress}%`;
    }
    
    if (progressBar) {
        progressBar.style.width = `${taskData.progress}%`;
    }
    
    // 更新子任务状态
    updateSubTaskStatusInModal(modal, taskData.sub_tasks);
    
    // 从子任务中提取已生成的视频URL
    const generatedVideoUrls = [];
    if (taskData.sub_tasks && taskData.sub_tasks.length > 0) {
        taskData.sub_tasks.forEach(subTask => {
            if (subTask.status === 2 && subTask.video_url) {
                generatedVideoUrls.push(subTask.video_url);
            }
        });
    }
    
    // 如果有已生成的视频，更新视频预览区域
    if (generatedVideoUrls.length > 0) {
        updateVideoPreviewArea(generatedVideoUrls);
    }
}

// 更新模态框中的子任务状态
function updateSubTaskStatusInModal(modal, subTasks) {
    const subTasksContainer = modal.querySelector('.sub-tasks-container');
    if (!subTasksContainer) return;
    
    // 清空子任务容器
    subTasksContainer.innerHTML = '';
    
    // 添加子任务状态
    if (subTasks && subTasks.length > 0) {
        subTasks.forEach((subTask, index) => {
            const subTaskElement = document.createElement('div');
            subTaskElement.className = 'sub-task-item';
            subTaskElement.style.cssText = 'margin-bottom: 10px; padding: 10px; background: #f8f9fa; border-radius: 6px;';
            
            subTaskElement.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                    <span style="font-weight: 500;">片段 ${index + 1}</span>
                    <span style="padding: 2px 8px; background: ${subTask.status === 0 ? '#fdcb6e' : subTask.status === 1 ? '#74b9ff' : subTask.status === 2 ? '#55efc4' : '#ff7675'}; color: ${subTask.status === 0 ? '#000' : '#fff'}; border-radius: 12px; font-size: 12px;">
                        ${getStatusText(subTask.status)}
                    </span>
                </div>
                ${subTask.error_message ? `<div style="font-size: 12px; color: #dc3545; margin-top: 5px;">错误: ${subTask.error_message}</div>` : ''}
            `;
            
            subTasksContainer.appendChild(subTaskElement);
        });
    } else {
        subTasksContainer.innerHTML = '<div style="text-align: center; color: #6c757d;">暂无子任务信息</div>';
    }
}

// 检查是否有进行中的视频生成任务
async function checkOngoingTasks(shotId) {
    try {
        // 调用新的视频生成API获取指定分镜的进行中任务
        const response = await fetch('video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'get_user_tasks',
                shot_id: shotId
            })
        });
        
        if (!response.ok) {
            throw new Error(`获取任务状态失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0 || !data.data) {
            throw new Error(`获取任务状态失败: ${data.msg || '未知错误'}`);
        }
        
        // 查找进行中的任务
        const tasks = data.data;
        const ongoingTask = tasks.find(task => 
            task.status === 0 || task.status === 1 // 使用数字状态码
        );
        
        // 返回找到的任务（如果有）
        return ongoingTask || null;
    } catch (error) {
        console.error('检查进行中任务失败:', error);
        return null;
    }
}

// 获取任务状态的文本描述
function getStatusText(status) {
    const statusMap = {
        0: '待处理',
        1: '处理中',
        2: '已完成',
        3: '失败',
        'pending': '待处理',
        'processing': '处理中',
        'completed': '已完成',
        'failed': '失败'
    };
    return statusMap[status] || status;
}

// 调用图生视频API
async function generateVideo(firstFrame, lastFrame, prompt, quality, duration, shotId) {
    try {
        // 调用后端的generate_video.php文件生成视频
        const response = await fetch('generate_video.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                firstFrame: firstFrame,
                lastFrame: lastFrame,
                prompt: prompt,
                duration: duration,
                shotId: shotId
            })
        });
        
        if (!response.ok) {
            throw new Error(`API请求失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0 || !data.data || !data.data.videoUrl) {
            throw new Error(`视频生成失败: ${data.msg || '未知错误'}`);
        }
        
        // 返回生成的视频URL
        return data.data.videoUrl;
    } catch (error) {
        console.error('调用图生视频API失败:', error);
        throw error;
    }
}

// 更新视频预览区域
function updateVideoPreviewArea(videoUrls) {
    const videoPreviewList = document.getElementById('videoPreviewList');
    if (!videoPreviewList) return;
    
    // 清空预览区域
    videoPreviewList.innerHTML = '';
    
    // 检查是否有视频URL
    if (videoUrls && videoUrls.length > 0) {
        // 添加每个视频预览
        videoUrls.forEach((videoUrl, index) => {
            const videoItem = document.createElement('div');
            videoItem.className = 'video-preview-item';
            videoItem.style.cssText = 'flex-shrink: 0; width: 300px; height: 320px; border: 1px solid #dee2e6; border-radius: 8px; overflow: hidden; background: #ffffff; display: flex; flex-direction: column;';
            
            videoItem.innerHTML = `
                <div style="flex: 1; display: flex; align-items: center; justify-content: center; padding: 10px;">
                    <video controls style="width: 100%; height: 100%; object-fit: contain;">
                        <source src="${videoUrl}" type="video/mp4">
                        您的浏览器不支持视频播放
                    </video>
                </div>
                <div style="padding: 10px; border-top: 1px solid #e9ecef; background: #f8f9fa; display: flex; gap: 5px;">
                    <a href="${videoUrl}" download="video-${index + 1}.mp4" class="btn btn-sm btn-primary" style="flex: 1; padding: 6px 10px; font-size: 12px;">
                        <i class="fas fa-download"></i> 下载
                    </a>
                    <button class="btn btn-sm btn-secondary" onclick="openVideoPreviewModal('${videoUrl}')" style="flex: 1; padding: 6px 10px; font-size: 12px;">
                        <i class="fas fa-expand"></i> 全屏
                    </button>
                </div>
            `;
            
            videoPreviewList.appendChild(videoItem);
        });
    } else {
        // 显示占位符
        const placeholderItem = document.createElement('div');
        placeholderItem.className = 'video-preview-placeholder';
        placeholderItem.style.cssText = 'flex-shrink: 0; width: 300px; height: 320px; border: 2px dashed #dee2e6; border-radius: 8px; display: flex; align-items: center; justify-content: center; background: #ffffff;';
        
        placeholderItem.innerHTML = `
            <div style="color: #6c757d; text-align: center;">
                <i class="fas fa-video" style="font-size: 32px; margin-bottom: 15px; color: #adb5bd;"></i>
                <div>生成后将显示视频预览</div>
            </div>
        `;
        
        videoPreviewList.appendChild(placeholderItem);
    }
}

// 打开视频预览模态框
function openVideoPreviewModal(videoUrl) {
    // 创建模态框
    const modal = document.createElement('div');
    modal.className = 'modal image-modal';
    
    let modalHtml = `
        <div class="modal-content image-modal-content">
            <div class="modal-header">
                <h3>视频预览</h3>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body image-modal-body">
                <video controls autoplay style="width: 100%; height: 100%; object-fit: contain;">
                    <source src="${videoUrl}" type="video/mp4">
                    您的浏览器不支持视频播放
                </video>
            </div>
        </div>
    `;
    
    modal.innerHTML = modalHtml;
    document.body.appendChild(modal);
}

// 显示视频生成结果
function showVideoResult(data) {
    // 创建视频结果弹窗
    const modal = document.createElement('div');
    modal.className = 'modal image-modal';
    
    let modalHtml = `
        <div class="modal-content image-modal-content">
            <div class="modal-header">
                <h3>视频生成成功</h3>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body image-modal-body">
                <video controls style="width: 100%; height: 100%; object-fit: contain;">
                    <source src="${data.videoUrl}" type="video/mp4">
                    您的浏览器不支持视频播放
                </video>
                <div class="modal-actions" style="margin-top: 20px; display: flex; gap: 10px;">
                    <a href="${data.videoUrl}" download="storyboard-video-${Date.now()}.mp4" class="btn btn-primary">
                        <i class="fas fa-download"></i> 下载视频
                    </a>
                    <button class="btn btn-secondary" onclick="copyVideoLink('${data.videoUrl}')">
                        <i class="fas fa-share"></i> 分享视频
                    </button>
                </div>
            </div>
        </div>
    `;
    
    modal.innerHTML = modalHtml;
    document.body.appendChild(modal);
}

// 复制视频链接
function copyVideoLink(videoUrl) {
    navigator.clipboard.writeText(videoUrl)
    .then(() => {
        alert('视频链接已复制到剪贴板');
    })
    .catch(err => {
        alert('复制失败，请手动复制链接');
    });
}

// 合并视频片段
async function mergeVideoClips(button) {
    const shotId = button.getAttribute('data-shot-id');
    const videoUrls = JSON.parse(button.getAttribute('data-video-urls'));
    
    if (!videoUrls || videoUrls.length < 2) {
        alert('至少需要2个视频片段才能合并');
        return;
    }
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    const originalText = button.innerHTML;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 合并中...';
    
    try {
        // 调用后端API合并视频
        const response = await fetch('merge_videos.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                shot_id: shotId,
                video_urls: videoUrls
            })
        });
        
        if (!response.ok) {
            throw new Error(`合并视频失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0) {
            throw new Error(`合并视频失败: ${data.msg || '未知错误'}`);
        }
        
        // 显示成功信息
        alert('视频合并成功！');
        
        // 关闭模态框
        button.closest('.modal').remove();
        
    } catch (error) {
        console.error('合并视频失败:', error);
        alert(`合并视频失败: ${error.message}`);
        
        // 恢复按钮状态
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// 取消视频任务
async function cancelVideoTask(button) {
    const taskId = button.getAttribute('data-task-id');
    const shotId = button.getAttribute('data-shot-id');
    
    if (!confirm('确定要取消这个视频生成任务吗？取消后将无法恢复。')) {
        return;
    }
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    const originalText = button.textContent;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 取消中...';
    
    try {
        // 调用API取消任务
        const response = await fetch('video_api.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                action: 'cancel_task',
                task_id: taskId
            })
        });
        
        if (!response.ok) {
            throw new Error(`取消任务失败: ${response.status}`);
        }
        
        const data = await response.json();
        
        if (data.code !== 0) {
            throw new Error(`取消任务失败: ${data.msg || '未知错误'}`);
        }
        
        // 从本地存储中移除任务
        removeOngoingTask(taskId);
        
        // 隐藏分镜卡片上的视频生成中标签
        if (shotId) {
            hideVideoGeneratingTag(shotId);
        }
        
        // 更新模态框中的任务状态
        const modal = button.closest('.modal');
        if (modal) {
            const statusElement = modal.querySelector('.task-status');
            if (statusElement) {
                statusElement.textContent = '已取消';
                statusElement.style.backgroundColor = '#ff7675';
                statusElement.style.color = '#fff';
            }
            
            // 禁用操作按钮
            const actionButtons = modal.querySelectorAll('.btn-secondary, .btn-danger');
            actionButtons.forEach(btn => {
                btn.disabled = true;
                btn.style.opacity = '0.6';
            });
        }
        
        // 显示取消成功的提示
        alert('视频生成任务已成功取消');
        
    } catch (error) {
        console.error('取消任务失败:', error);
        alert(`取消任务失败: ${error.message}`);
    } finally {
        // 恢复按钮状态
        button.disabled = false;
        button.innerHTML = originalText;
    }
}

// 开始实时轮询任务状态
function startTaskStatusPolling(taskId, modal) {
    let pollingInterval;
    let pollingCount = 0;
    const maxPollingCount = 120; // 最多轮询120次，每次5秒，共10分钟
    
    // 轮询任务状态
    async function pollTaskStatus() {
        try {
            const response = await fetch(`task_manager.php?taskId=${taskId}`);
            if (!response.ok) {
                throw new Error(`获取任务状态失败: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.code !== 0 || !data.data) {
                throw new Error(`获取任务状态失败: ${data.msg || '未知错误'}`);
            }
            
            const taskData = data.data;
            
            // 更新模态框中的任务状态
            updateSpecificModalTaskStatus(modal, taskData);
            
            // 检查任务是否完成或失败
            if (taskData.status === 'completed' || taskData.status === 'failed') {
                // 清除轮询
                clearInterval(pollingInterval);
                
                // 如果是完成状态，更新视频预览区域
                if (taskData.status === 'completed' && taskData.videoUrls && taskData.videoUrls.length > 0) {
                    updateVideoPreviewArea(taskData.videoUrls);
                }
            } else if (pollingCount >= maxPollingCount) {
                // 清除轮询
                clearInterval(pollingInterval);
            }
            
            // 增加轮询计数
            pollingCount++;
            
        } catch (error) {
            console.error('获取任务状态失败:', error);
        }
    }
    
    // 立即执行一次轮询
    pollTaskStatus();
    
    // 设置轮询间隔（5秒）
    pollingInterval = setInterval(pollTaskStatus, 5000);
    
    // 监听模态框关闭事件，清除轮询
    const closeBtn = modal.querySelector('.modal-close');
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            clearInterval(pollingInterval);
        });
    }
    
    // 监听确定按钮点击事件，清除轮询
    const confirmBtn = modal.querySelector('.btn-primary');
    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            clearInterval(pollingInterval);
        });
    }
}

// 更新特定模态框中的任务状态
function updateSpecificModalTaskStatus(modal, taskData) {
    // 更新状态
    const statusElement = modal.querySelector('.task-status');
    if (statusElement) {
        statusElement.textContent = getStatusText(taskData.status);
    }
    
    // 更新进度
    const progressElement = modal.querySelector('.task-progress');
    if (progressElement) {
        progressElement.textContent = taskData.progress + '%';
    }
    
    // 更新进度条
    const progressBar = modal.querySelector('.progress-bar');
    if (progressBar) {
        progressBar.style.width = taskData.progress + '%';
    }
    
    // 更新生成视频模态框中的任务标签
    const generateModal = document.querySelector('.modal[data-task-id="' + taskData.taskId + '"]');
    if (generateModal) {
        const taskTag = generateModal.querySelector('.task-status-tag');
        if (taskTag) {
            taskTag.innerHTML = `<i class="fas fa-spinner fa-spin"></i> 生成中 ${taskData.progress}%`;
        }
    }
}

// 显示进行中任务的模态框
function showOngoingTaskModal(taskData) {
    // 创建模态框
    const modal = document.createElement('div');
    modal.className = 'modal image-modal';
    modal.dataset.taskId = taskData.taskId;
    
    let modalHtml = `
        <div class="modal-content image-modal-content" style="width: 90%; max-width: 500px;">
            <div class="modal-header">
                <h3 style="margin: 0; color: #FFFFFF; font-size: 18px;">进行中的任务</h3>
                <button class="modal-close" onclick="this.closest('.modal').remove()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: #6c757d;">&times;</button>
            </div>
            <div class="modal-body image-modal-body" style="padding: 20px;">
                <div style="padding: 20px; background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
                    <div style="margin-bottom: 15px;">
                        <strong>任务ID:</strong> <span style="color: #4ecdc4;">${taskData.taskId}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>状态:</strong> <span class="task-status" style="color: #4ecdc4;">${getStatusText(taskData.status)}</span>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <strong>进度:</strong> <span class="task-progress" style="color: #4ecdc4;">${taskData.progress}%</span>
                    </div>
                    <div style="margin-bottom: 20px;">
                        <div style="width: 100%; height: 8px; background: #dee2e6; border-radius: 4px; overflow: hidden;">
                            <div class="progress-bar" style="width: ${taskData.progress}%; height: 100%; background: linear-gradient(90deg, #4ecdc4 0%, #45b7aa 100%); transition: width 0.3s ease;"></div>
                        </div>
                    </div>
                    <div style="color: #6c757d; font-size: 14px;">
                        任务将在后台继续执行，您可以稍后返回查看结果。
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; padding: 20px; border-top: 1px solid #e9ecef; background: #f8f9fa;">
                <button class="btn btn-primary" onclick="this.closest('.modal').remove()" style="padding: 10px 20px; background: #4ecdc4; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px; font-weight: 500;">确定</button>
            </div>
        </div>
    `;
    
    modal.innerHTML = modalHtml;
    document.body.appendChild(modal);
    
    // 开始实时轮询任务状态
    startTaskStatusPolling(taskData.taskId, modal);
}


