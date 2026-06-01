// 图片懒加载功能
class ImageLazyLoader {
    constructor() {
        this.observer = null;
        this.initObserver();
    }
    
    initObserver() {
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const img = entry.target;
                        this.loadImage(img);
                        observer.unobserve(img);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.01
            });
        }
    }
    
    loadImage(img) {
        const imageUrl = img.getAttribute('data-src');
        if (imageUrl) {
            img.src = imageUrl;
            img.removeAttribute('data-src');
            img.classList.add('lazy-loaded');
            // 图片加载完成后添加淡入效果
            img.onload = function() {
                img.style.opacity = '1';
            };
        }
    }
    
    observeImages() {
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => {
            // 设置初始样式，准备淡入效果
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease-in-out';
            if (this.observer) {
                this.observer.observe(img);
            } else {
                // 降级方案：直接加载所有图片
                this.loadImage(img);
            }
        });
    }
    
    static createPlaceholder(width, height) {
        return `data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}"%3E%3Crect width="${width}" height="${height}" fill="%23f0f0f0"/%3E%3Ctext x="50%" y="50%" dominant-baseline="middle" text-anchor="middle" fill="%23ccc" font-size="14"%3E加载中...%3C/text%3E%3C/svg%3E`;
    }
}

// 全局懒加载实例
let imageLazyLoader = null;

// 分镜应用主逻辑
document.addEventListener('DOMContentLoaded', function() {
    // 初始化图片懒加载
    imageLazyLoader = new ImageLazyLoader();
    
    // 初始化所有交互功能（只初始化一次，不依赖数据）
    initNavigation();
    initFloatingBar();
    initDropdowns();
    initExpandCollapseAllButtons(); // 初始化展开/收缩所有场次按钮
    initMobileMenu();      // 初始化移动端菜单
    initGenerateButtons(); // 初始化生成参考图按钮（使用事件委托）
    initActionButtons();   // 初始化表格操作按钮（使用事件委托）
    initGenerateModal();   // 初始化生成参考图模态框
    
    // 初始化用户体验区域的tab切换功能
    if (typeof setupExperienceTabSwitching === 'function') {
        setupExperienceTabSwitching();
    }
    
    // 使用requestAnimationFrame延迟加载分镜数据，避免阻塞主线程
    requestAnimationFrame(() => {
        loadStoryboardData();  // 加载分镜数据
        console.log('智影工场界面已加载');
    });
});

// 重新观察图片（用于动态添加的图片）
function observeLazyImages() {
    if (imageLazyLoader) {
        imageLazyLoader.observeImages();
    }
}

// 初始化生成参考图模态框
function initGenerateModal() {
    const modal = document.getElementById('generateModal');
    const closeBtn = document.getElementById('generateModalClose');
    const cancelBtn = document.getElementById('modalCancelBtn');
    const form = document.getElementById('generateImageForm');
    
    // 关闭按钮事件
    if (closeBtn) {
        closeBtn.addEventListener('click', closeGenerateModal);
    }
    
    // 取消按钮事件
    if (cancelBtn) {
        cancelBtn.addEventListener('click', closeGenerateModal);
    }
    
    // 生成图片按钮事件
    const generateBtn = document.getElementById('modalGenerateBtn');
    if (generateBtn) {
        generateBtn.addEventListener('click', function() {
            if (form) {
                form.dispatchEvent(new Event('submit'));
            }
        });
    }
    
    // 点击遮罩层关闭
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                closeGenerateModal();
            }
        });
    }
    
    // ESC键关闭
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal && modal.classList.contains('active')) {
            closeGenerateModal();
        }
    });
    
    // 初始化风格预设
    initModalStylePresets();
    
    // 初始化比例预设
    initModalRatioPresets();
    
    // 表单提交事件
    if (form) {
        form.addEventListener('submit', handleGenerateImageSubmit);
    }
}

// 风格预设数据
const modalStylePresets = [
    { id: '12', name: '线稿2.0', label: '线稿手绘', icon: 'fas fa-pencil-alt' },
    { id: '10', name: '写实2.0', label: '写实', icon: 'fas fa-camera' },
    { id: '5', name: '手绘动画', label: '手绘动画', icon: 'fas fa-paint-brush' },
    { id: '11', name: '动漫2.0', label: '动漫二次元', icon: 'fas fa-gamepad' },
    { id: '18', name: '动漫玄幻', label: '古风玄幻', icon: 'fas fa-hat-wizard' },
    { id: '20', name: '一致性动漫', label: '一致动漫', icon: 'fas fa-sync-alt' },
    { id: '17', name: '吉卜力', label: '宫崎骏风', icon: 'fas fa-film' },
    { id: '7', name: '国风写实', label: '国风写实', icon: 'fas fa-mountain' },
    { id: '16', name: '国风工笔', label: '国风工笔', icon: 'fas fa-brush' },
    { id: '22', name: '一致性通用', label: '一致通用', icon: 'fas fa-globe' },
    { id: '21', name: '通用3.0', label: '通用3.0', icon: 'fas fa-star' },
    { id: '10', name: '通用2.0', label: '通用2.0', icon: 'fas fa-star-half-alt' },
    { id: '19', name: '一致性写实', label: '一致写实', icon: 'fas fa-user-check' },
    { id: '15', name: '王家卫', label: '港风', icon: 'fas fa-theater-masks' },
    { id: '6', name: '3D动画', label: '3D动画', icon: 'fas fa-cube' },
    { id: '4', name: '欧美漫画', label: '欧美漫画', icon: 'fas fa-mask' },
    { id: '13', name: '蒸汽朋克', label: '蒸汽朋克', icon: 'fas fa-city' }
];

// 比例预设数据
const modalRatioPresets = [
    { id: '16:9', name: '横屏 16:9', label: '16:9', icon: 'fas fa-desktop' },
    { id: '9:16', name: '竖屏 9:16', label: '9:16', icon: 'fas fa-mobile-alt' },
    { id: '21:9', name: '超宽屏 21:9', label: '21:9', icon: 'fas fa-film' },
    { id: '3:2', name: '宽屏 3:2', label: '3:2', icon: 'fas fa-desktop-alt' },
    { id: '2:3', name: '高屏 2:3', label: '2:3', icon: 'fas fa-desktop-alt' },
    { id: '4:3', name: '4:3 比例', label: '4:3', icon: 'fas fa-square' },
    { id: '3:4', name: '3:4 比例', label: '3:4', icon: 'fas fa-square' },
    { id: '1:1', name: '正方形比例', label: '正方形', icon: 'fas fa-square' },
];

// 初始化风格预设
function initModalStylePresets() {
    const container = document.getElementById('modalStylePresets');
    if (!container) return;
    
    container.innerHTML = '';
    
    modalStylePresets.forEach(preset => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'preset-btn';
        button.dataset.style = preset.id;
        button.innerHTML = `
            <i class="${preset.icon}"></i>
            <div class="preset-label">${preset.label}</div>
        `;
        
        if (preset.id === '12') {
            button.classList.add('active');
        }
        
        button.addEventListener('click', function() {
            const siblings = container.querySelectorAll('.preset-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            document.getElementById('modalStyle').value = this.dataset.style;
            document.getElementById('modalCurrentStyle').textContent = preset.name;
        });
        
        container.appendChild(button);
    });
}

// 初始化比例预设
function initModalRatioPresets() {
    const container = document.getElementById('modalRatioPresets');
    if (!container) return;
    
    container.innerHTML = '';
    
    modalRatioPresets.forEach(preset => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'preset-btn ratio-preset';
        button.dataset.ratio = preset.id;
        let ratioClass = 'ratio-visual';
        if (preset.id === '16:9') {
            ratioClass = 'ratio-visual_169';
        } else if (preset.id === '9:16') {
            ratioClass = 'ratio-visual_916';
        } else if (preset.id === '21:9') {
            ratioClass = 'ratio-visual_219';
        } else if (preset.id === '3:2') {
            ratioClass = 'ratio-visual_32';
        } else if (preset.id === '2:3') {
            ratioClass = 'ratio-visual_23';
        } else if (preset.id === '4:3') {
            ratioClass = 'ratio-visual_43';
        } else if (preset.id === '3:4') {
            ratioClass = 'ratio-visual_34';
        } else if (preset.id === '1:1') {
            ratioClass = 'ratio-visual_11';
        }
        
        button.innerHTML = `
            <div class="${ratioClass}"></div>
            <div class="preset-label">${preset.label}</div>
        `;
        
        if (preset.id === '16:9') {
            button.classList.add('active');
        }
        
        button.addEventListener('click', function() {
            const siblings = container.querySelectorAll('.preset-btn');
            siblings.forEach(btn => btn.classList.remove('active'));
            this.classList.add('active');
            
            document.getElementById('modalPicSize').value = this.dataset.ratio;
            document.getElementById('modalCurrentRatio').textContent = preset.name;
        });
        
        container.appendChild(button);
    });
}

// 打开生成参考图模态框
function openGenerateModal(button) {
    const modal = document.getElementById('generateModal');
    const modalHeader = modal.querySelector('.modal-header h2');
    const promptInput = document.getElementById('promptInput');
    const genresDisplay = document.getElementById('modalGenresDisplay');
    const charactersDisplay = document.getElementById('modalCharactersDisplay');
    
    if (!modal || !promptInput) return;
    
    // 获取当前分镜行
    const shotRow = button.closest('.shot-row');
    if (!shotRow) return;
    
    // 获取按钮类型
    const buttonType = button.getAttribute('data-type') || 'reference';
    
    // 根据按钮类型设置模态框标题
    if (modalHeader) {
        if (buttonType === 'cameraMovement') {
            modalHeader.textContent = '生成运镜画面';
        } else {
            modalHeader.textContent = '生成参考图';
        }
    }
    
    // 根据按钮类型控制grid-selector的显示
    const gridSelector = document.querySelector('.grid-selector');
    if (gridSelector) {
        if (buttonType === 'cameraMovement') {
            gridSelector.style.display = 'inline-block';
        } else {
            gridSelector.style.display = 'none';
        }
    }
    
    // 获取镜号和场次ID
    const shotId = shotRow.querySelector('td:nth-child(2)')?.textContent;
    let sceneId = null;
    
    let prevElement = shotRow.previousElementSibling;
    while (prevElement) {
        if (prevElement.classList.contains('scene-header')) {
            sceneId = prevElement.querySelector('.scene-number')?.textContent?.replace('#', '');
            break;
        }
        prevElement = prevElement.previousElementSibling;
    }
    
    // 保存当前分镜信息到全局变量
    window.currentGenerateShot = {
        shotRow: shotRow,
        shotId: shotId,
        sceneId: sceneId,
        button: button,
        type: buttonType // 保存按钮类型
    };
    
    // 从数据库中获取分镜数据，包括imageUrl
    let shotData = null;
    let imageUrl = '';
    
    if (shotId && window.dbTaskId && sceneId) {
        // 从API获取指定场次的指定分镜数据
        fetch(`./storyboard_api.php?task_id=${window.dbTaskId}&scene_id=${sceneId}&shot_id=${shotId}`)
            .then(response => response.json())
            .then(data => {
                if (data && data.scenes && data.scenes.length > 0 && data.scenes[0].shots && data.scenes[0].shots.length > 0) {
                    // 直接使用返回的分镜数据
                    shotData = data.scenes[0].shots[0];
                    imageUrl = shotData.imageUrl || '';
                    
                    // 更新提示词
                    if (shotData) {
                        if (buttonType === 'cameraMovement') {
                            // 运镜画面的提示词
                            const defaultPrompt = `帮我生成图片：基于参考图并以参考图为主体，依据场景剧情[${shotData.script}]，生成一张专业的[3x3]网格分镜图，需要包括不同景别、不同角度的[9]张画幅都与参考图一致的分镜图，注意环境的空间布局、空间中人物与所有内容物品的相对位置，并生成不同角度的符合剧情发展的连贯性分镜图，一定要保持环境与场景一致，确保所有分镜居右一致的色彩分级。`;
                            promptInput.value = defaultPrompt;
                        } else {
                            // 处理角色列表，添加编号
                            let charactersText = shotData.characters;
                            let tu_count = 1; // 初始化角色数量
                            
                            console.log('原始角色数据:', shotData.characters);
                            if (charactersText && charactersText !== '无') {
                                const characters = charactersText.split(/[,，]/).map(char => char.trim().replace(/\s*\(.*?\)\s*$/, '')).filter(char => char);
                                tu_count = characters.length+1; // 设置角色数量
                                console.log('分割后的角色数组:', characters);
                                if (characters.length > 0) {
                                    charactersText = characters.map((char, index) => `${char}（图${index + 1}）`).join('，');
                                    console.log('处理后的角色文本:', charactersText);
                                }
                            }
                            
                            console.log('最终角色文本:', charactersText);
                            // 参考画面的提示词
                            const defaultPrompt = `地点（图${tu_count}）：${shotData.location}。场景（图${tu_count}）：${shotData.sceneExpectation}。时段与天气：${shotData.time}${shotData.weather}；光线与色调：${shotData.lightTone}。运镜手法：${shotData.shotType}${shotData.cameraAngle}视角，${shotData.lensFocalLength}${shotData.cameraMovement}。角色：${charactersText}；角色妆造：${shotData.characterCostumes}；角色动作：${shotData.characterActions}。道具：${shotData.props}。剧本内容：${shotData.script}。构图与焦点：${shotData.compositionFocus}。`;
                            promptInput.value = defaultPrompt;
                        }
                    }
                    
                    // 更新参考图区域
                    const referenceImageContainer = document.getElementById('modalReferenceImageContainer');
                    const referenceImage = document.getElementById('modalReferenceImage');
                    const cancelBtn = document.getElementById('modalCancelBtn');
                    const generateBtn = document.getElementById('modalGenerateBtn');
                    
                    if (referenceImageContainer && referenceImage) {
                        if (buttonType === 'cameraMovement') {
                            // 显示参考图区域
                            if (imageUrl) {
                                // 加载图片
                                referenceImage.src = imageUrl;
                                // 启用按钮
                                if (cancelBtn) cancelBtn.disabled = false;
                                if (generateBtn) generateBtn.disabled = false;
                                // 移除提醒信息
                                const referenceImageParent = referenceImage.parentElement;
                                if (referenceImageParent) {
                                    const warningElement = referenceImageParent.querySelector('.warning-message');
                                    if (warningElement) {
                                        warningElement.remove();
                                    }
                                }
                            } else {
                                // 没有图片时显示占位符
                                referenceImage.src = '';
                                referenceImage.alt = '暂无参考图';
                                // 显示提醒信息
                                const referenceImageParent = referenceImage.parentElement;
                                if (referenceImageParent) {
                                    // 检查是否已经有提醒信息
                                    let warningElement = referenceImageParent.querySelector('.warning-message');
                                    if (!warningElement) {
                                        warningElement = document.createElement('div');
                                        warningElement.className = 'warning-message';
                                        warningElement.style.cssText = `
                                            position: absolute;
                                            top: 50%;
                                            left: 50%;
                                            transform: translate(-50%, -50%);
                                            background: rgba(255, 107, 107, 0.9);
                                            color: white;
                                            padding: 20px;
                                            border-radius: 8px;
                                            font-weight: bold;
                                            text-align: center;
                                            z-index: 10;
                                            max-width: 90%;
                                        `;
                                        warningElement.textContent = '请先在参考画面中生成参考图';
                                        referenceImageParent.style.position = 'relative';
                                        referenceImageParent.appendChild(warningElement);
                                    }
                                }
                                // 禁用按钮
                                if (cancelBtn) cancelBtn.disabled = true;
                                if (generateBtn) generateBtn.disabled = true;
                            }
                            referenceImageContainer.style.display = 'block';
                        } else {
                            // 隐藏参考图区域
                            referenceImageContainer.style.display = 'none';
                            // 启用按钮
                            if (cancelBtn) cancelBtn.disabled = false;
                            if (generateBtn) generateBtn.disabled = false;
                        }
                    }
                } else {
                    // API调用失败或未找到分镜，使用DOM中的数据
                    shotData = getShotDataFromRow(shotRow);
                    if (shotData) {
                        if (buttonType === 'cameraMovement') {
                            // 运镜画面的提示词
                            const defaultPrompt = `帮我生成图片：基于参考图并以参考图为主体，依据场景剧情[${shotData.script}]，生成一张专业的[3x3]网格分镜图，需要包括不同景别、不同角度的[9]张画幅都与参考图一致的分镜图，注意环境的空间布局、空间中人物与所有内容物品的相对位置，并生成不同角度的符合剧情发展的连贯性分镜图，一定要保持环境与场景一致，确保所有分镜居右一致的色彩分级。`;
                            promptInput.value = defaultPrompt;
                        } else {
                            // 处理角色列表，添加编号
                            let charactersText = shotData.characters;
                            let tu_count = 1; // 初始化角色数量
                            console.log('API失败路径 - 原始角色数据:', shotData.characters);
                            if (charactersText && charactersText !== '无') {
                                const characters = charactersText.split(/[,，]/).map(char => char.trim().replace(/\s*\(.*?\)\s*$/, '')).filter(char => char);
                                tu_count = characters.length+1; // 设置角色数量
                                console.log('API失败路径 - 分割后的角色数组:', characters);
                                if (characters.length > 0) {
                                    charactersText = characters.map((char, index) => `${char}（图${index + 1}）`).join('，');
                                    console.log('API失败路径 - 处理后的角色文本:', charactersText);
                                }
                            }
                            console.log('API失败路径 - 最终角色文本:', charactersText);
                            // 参考画面的提示词
                            const defaultPrompt = `地点（图${tu_count}）：${shotData.location}。场景（图${tu_count}）：${shotData.sceneExpectation}。时段与天气：${shotData.time}${shotData.weather}；光线与色调：${shotData.lightTone}。运镜手法：${shotData.shotType}${shotData.cameraAngle}视角，${shotData.lensFocalLength}${shotData.cameraMovement}。角色：${charactersText}；角色妆造：${shotData.characterCostumes}；角色动作：${shotData.characterActions}。道具：${shotData.props}。剧本内容：${shotData.script}。构图与焦点：${shotData.compositionFocus}。`;
                            promptInput.value = defaultPrompt;
                        }
                    }
                    
                    // 更新参考图区域
                    const referenceImageContainer = document.getElementById('modalReferenceImageContainer');
                    const referenceImage = document.getElementById('modalReferenceImage');
                    const cancelBtn = document.getElementById('modalCancelBtn');
                    const generateBtn = document.getElementById('modalGenerateBtn');
                    
                    if (referenceImageContainer && referenceImage) {
                        if (buttonType === 'cameraMovement') {
                            // 显示参考图区域
                            if (shotData && shotData.imageUrl) {
                                // 加载图片
                                referenceImage.src = shotData.imageUrl;
                                // 启用按钮
                                if (cancelBtn) cancelBtn.disabled = false;
                                if (generateBtn) generateBtn.disabled = false;
                                // 移除提醒信息
                                const referenceImageParent = referenceImage.parentElement;
                                if (referenceImageParent) {
                                    const warningElement = referenceImageParent.querySelector('.warning-message');
                                    if (warningElement) {
                                        warningElement.remove();
                                    }
                                }
                            } else {
                                // 没有图片时显示占位符
                                referenceImage.src = '';
                                referenceImage.alt = '暂无参考图';
                                // 显示提醒信息
                                const referenceImageParent = referenceImage.parentElement;
                                if (referenceImageParent) {
                                    // 检查是否已经有提醒信息
                                    let warningElement = referenceImageParent.querySelector('.warning-message');
                                    if (!warningElement) {
                                        warningElement = document.createElement('div');
                                        warningElement.className = 'warning-message';
                                        warningElement.style.cssText = `
                                            position: absolute;
                                            top: 50%;
                                            left: 50%;
                                            transform: translate(-50%, -50%);
                                            background: rgba(255, 107, 107, 0.9);
                                            color: white;
                                            padding: 20px;
                                            border-radius: 8px;
                                            font-weight: bold;
                                            text-align: center;
                                            z-index: 10;
                                            max-width: 90%;
                                        `;
                                        warningElement.textContent = '请先在参考画面中生成参考图';
                                        referenceImageParent.style.position = 'relative';
                                        referenceImageParent.appendChild(warningElement);
                                    }
                                }
                                // 禁用按钮
                                if (cancelBtn) cancelBtn.disabled = true;
                                if (generateBtn) generateBtn.disabled = true;
                            }
                            referenceImageContainer.style.display = 'block';
                        } else {
                            // 隐藏参考图区域
                            referenceImageContainer.style.display = 'none';
                            // 启用按钮
                            if (cancelBtn) cancelBtn.disabled = false;
                            if (generateBtn) generateBtn.disabled = false;
                        }
                    }
                }
            })
            .catch(error => {
                console.error('获取分镜数据失败:', error);
                // API调用失败，使用DOM中的数据
                shotData = getShotDataFromRow(shotRow);
                if (shotData) {
                    if (buttonType === 'cameraMovement') {
                        // 运镜画面的提示词
                        const defaultPrompt = `帮我生成图片：基于参考图并以参考图为主体，依据场景剧情[${shotData.script}]，生成一张专业的[3x3]网格分镜图，需要包括不同景别、不同角度的[9]张画幅都与参考图一致的分镜图，注意环境的空间布局、空间中人物与所有内容物品的相对位置，并生成不同角度的符合剧情发展的连贯性分镜图，一定要保持环境与场景一致，确保所有分镜居右一致的色彩分级。`;
                        promptInput.value = defaultPrompt;
                    } else {
                        // 处理角色列表，添加编号
                            let charactersText = shotData.characters;
                            let tu_count = 1; // 初始化角色数量
                            console.log('API异常路径 - 原始角色数据:', shotData.characters);
                            if (charactersText && charactersText !== '无') {
                                const characters = charactersText.split(/[,，]/).map(char => char.trim().replace(/\s*\(.*?\)\s*$/, '')).filter(char => char);
                                tu_count = characters.length+1; // 设置角色数量
                                console.log('API异常路径 - 分割后的角色数组:', characters);
                                if (characters.length > 0) {
                                    charactersText = characters.map((char, index) => `${char}（图${index + 1}）`).join('，');
                                    console.log('API异常路径 - 处理后的角色文本:', charactersText);
                                }
                            }
                            console.log('API异常路径 - 最终角色文本:', charactersText);
                            // 参考画面的提示词
                        const defaultPrompt = `地点（图${tu_count}）：${shotData.location}。场景（图${tu_count}）：${shotData.sceneExpectation}。时段与天气：${shotData.time}${shotData.weather}；光线与色调：${shotData.lightTone}。运镜手法：${shotData.shotType}${shotData.cameraAngle}视角，${shotData.lensFocalLength}${shotData.cameraMovement}。角色：${charactersText}；角色妆造：${shotData.characterCostumes}；角色动作：${shotData.characterActions}。道具：${shotData.props}。剧本内容：${shotData.script}。构图与焦点：${shotData.compositionFocus}。`;
                        promptInput.value = defaultPrompt;
                    }
                }
                
                // 更新参考图区域
                const referenceImageContainer = document.getElementById('modalReferenceImageContainer');
                const referenceImage = document.getElementById('modalReferenceImage');
                const cancelBtn = document.getElementById('modalCancelBtn');
                const generateBtn = document.getElementById('modalGenerateBtn');
                
                if (referenceImageContainer && referenceImage) {
                    if (buttonType === 'cameraMovement') {
                        // 显示参考图区域
                        if (shotData && shotData.imageUrl) {
                            // 加载图片
                            referenceImage.src = shotData.imageUrl;
                            // 启用按钮
                            if (cancelBtn) cancelBtn.disabled = false;
                            if (generateBtn) generateBtn.disabled = false;
                            // 移除提醒信息
                            const referenceImageParent = referenceImage.parentElement;
                            if (referenceImageParent) {
                                const warningElement = referenceImageParent.querySelector('.warning-message');
                                if (warningElement) {
                                    warningElement.remove();
                                }
                            }
                        } else {
                            // 没有图片时显示占位符
                            referenceImage.src = '';
                            referenceImage.alt = '暂无参考图';
                            // 显示提醒信息
                            const referenceImageParent = referenceImage.parentElement;
                            if (referenceImageParent) {
                                // 检查是否已经有提醒信息
                                let warningElement = referenceImageParent.querySelector('.warning-message');
                                if (!warningElement) {
                                    warningElement = document.createElement('div');
                                    warningElement.className = 'warning-message';
                                    warningElement.style.cssText = `
                                        position: absolute;
                                        top: 50%;
                                        left: 50%;
                                        transform: translate(-50%, -50%);
                                        background: rgba(255, 107, 107, 0.9);
                                        color: white;
                                        padding: 20px;
                                        border-radius: 8px;
                                        font-weight: bold;
                                        text-align: center;
                                        z-index: 10;
                                        max-width: 90%;
                                    `;
                                    warningElement.textContent = '请先在参考画面中生成参考图';
                                    referenceImageParent.style.position = 'relative';
                                    referenceImageParent.appendChild(warningElement);
                                }
                            }
                            // 禁用按钮
                            if (cancelBtn) cancelBtn.disabled = true;
                            if (generateBtn) generateBtn.disabled = true;
                        }
                        referenceImageContainer.style.display = 'block';
                    } else {
                        // 隐藏参考图区域
                        referenceImageContainer.style.display = 'none';
                        // 启用按钮
                        if (cancelBtn) cancelBtn.disabled = false;
                        if (generateBtn) generateBtn.disabled = false;
                    }
                }
            });
    } else {
        // 没有shotId、taskId或sceneId，使用DOM中的数据
        shotData = getShotDataFromRow(shotRow);
        if (shotData) {
            if (buttonType === 'cameraMovement') {
                // 运镜画面的提示词
                const defaultPrompt = `帮我生成图片：基于参考图并以参考图为主体，依据场景剧情[${shotData.script}]，生成一张专业的[3x3]网格分镜图，需要包括不同景别、不同角度的[9]张画幅都与参考图一致的分镜图，注意环境的空间布局、空间中人物与所有内容物品的相对位置，并生成不同角度的符合剧情发展的连贯性分镜图，一定要保持环境与场景一致，确保所有分镜居右一致的色彩分级。`;
                promptInput.value = defaultPrompt;
            } else {
                // 处理角色列表，添加编号
                        let charactersText = shotData.characters;
                        let tu_count = 1; // 初始化角色数量
                        console.log('无shotId路径 - 原始角色数据:', shotData.characters);
                        if (charactersText && charactersText !== '无') {
                            const characters = charactersText.split(/[,，]/).map(char => char.trim().replace(/\s*\(.*?\)\s*$/, '')).filter(char => char);
                            tu_count = characters.length+1; // 设置角色数量
                            console.log('无shotId路径 - 分割后的角色数组:', characters);
                            if (characters.length > 0) {
                                charactersText = characters.map((char, index) => `${char}（图${index + 1}）`).join('，');
                                console.log('无shotId路径 - 处理后的角色文本:', charactersText);
                            }
                        }
                        console.log('无shotId路径 - 最终角色文本:', charactersText);
                        // 参考画面的提示词
                const defaultPrompt = `地点（图${tu_count}）：${shotData.location}。场景（图${tu_count}）：${shotData.sceneExpectation}。时段与天气：${shotData.time}${shotData.weather}；光线与色调：${shotData.lightTone}。运镜手法：${shotData.shotType}${shotData.cameraAngle}视角，${shotData.lensFocalLength}${shotData.cameraMovement}。角色：${charactersText}；角色妆造：${shotData.characterCostumes}；角色动作：${shotData.characterActions}。道具：${shotData.props}。剧本内容：${shotData.script}。构图与焦点：${shotData.compositionFocus}。`;
                promptInput.value = defaultPrompt;
            }
        }
        
        // 更新参考图区域
        const referenceImageContainer = document.getElementById('modalReferenceImageContainer');
        const referenceImage = document.getElementById('modalReferenceImage');
        const cancelBtn = document.getElementById('modalCancelBtn');
        const generateBtn = document.getElementById('modalGenerateBtn');
        
        if (referenceImageContainer && referenceImage) {
            if (buttonType === 'cameraMovement') {
                // 显示参考图区域
                if (shotData && shotData.imageUrl) {
                    // 加载图片
                    referenceImage.src = shotData.imageUrl;
                    // 启用按钮
                    if (cancelBtn) cancelBtn.disabled = false;
                    if (generateBtn) generateBtn.disabled = false;
                    // 移除提醒信息
                    const referenceImageParent = referenceImage.parentElement;
                    if (referenceImageParent) {
                        const warningElement = referenceImageParent.querySelector('.warning-message');
                        if (warningElement) {
                            warningElement.remove();
                        }
                    }
                } else {
                    // 没有图片时显示占位符
                    referenceImage.src = '';
                    referenceImage.alt = '暂无参考图';
                    // 显示提醒信息
                    const referenceImageParent = referenceImage.parentElement;
                    if (referenceImageParent) {
                        // 检查是否已经有提醒信息
                        let warningElement = referenceImageParent.querySelector('.warning-message');
                        if (!warningElement) {
                            warningElement = document.createElement('div');
                            warningElement.className = 'warning-message';
                            warningElement.style.cssText = `
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                background: rgba(255, 107, 107, 0.9);
                                color: white;
                                padding: 20px;
                                border-radius: 8px;
                                font-weight: bold;
                                text-align: center;
                                z-index: 10;
                                max-width: 90%;
                            `;
                            warningElement.textContent = '请先在参考画面中生成参考图';
                            referenceImageParent.style.position = 'relative';
                            referenceImageParent.appendChild(warningElement);
                        }
                    }
                    // 禁用按钮
                    if (cancelBtn) cancelBtn.disabled = true;
                    if (generateBtn) generateBtn.disabled = true;
                }
                referenceImageContainer.style.display = 'block';
            } else {
                // 隐藏参考图区域
                referenceImageContainer.style.display = 'none';
                // 启用按钮
                if (cancelBtn) cancelBtn.disabled = false;
                if (generateBtn) generateBtn.disabled = false;
            }
        }
    }

    
    // 加载题材信息（只调用一次）
    if (genresDisplay && window.dbTaskId) {
        genresDisplay.innerHTML = '<span class="genres-loading">加载中...</span>';
        
        fetch(`./get_genres.php?task_id=${window.dbTaskId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.genres && data.genres.length > 0) {
                    const genresHtml = data.genres.map(g => `<span class="genre-tag">${g}</span>`).join('');
                    genresDisplay.innerHTML = genresHtml;
                } else {
                    genresDisplay.innerHTML = '<span class="no-genres">暂无题材设定</span>';
                }
            })
            .catch(error => {
                console.error('加载题材失败:', error);
                genresDisplay.innerHTML = '<span class="error-text">加载失败</span>';
            });
    } else if (genresDisplay) {
        genresDisplay.innerHTML = '<span class="no-genres">暂无题材设定</span>';
    }
    
    // 加载角色信息
    if (charactersDisplay && shotId) {
        charactersDisplay.innerHTML = '<span class="characters-loading">加载中...</span>';
        
        // 从API获取角色数据
        fetch(`./get_characters.php?shot_id=${shotId}&scene_id=${sceneId}&task_id=${window.dbTaskId || ''}&type=${encodeURIComponent(buttonType)}`)
            .then(response => response.json())
            .then(data => {
                console.log('角色数据:', data); // 调试用
                if (data.success && data.characters && data.characters.length > 0) {
                    // 检查是否有有效的角色（排除名称为"无"的情况）
                    const validCharacters = data.characters.filter(character => 
                        character.name && character.name.trim() !== '' && character.name.trim() !== '无'
                    );
                    
                    if (validCharacters.length > 0) {
                        const charactersHtml = validCharacters.map(character => {
                            // 处理角色名称，移除括号及括号里的内容
                            let characterName = character.name;
                            if (characterName) {
                                characterName = characterName.replace(/\s*\(.*?\)\s*$/, '').trim();
                            }
                            // 使用角色的three_view_image作为头像，如果没有则使用默认生成的头像
                            const avatarUrl = character.three_view_image || `https://trae-api-cn.mchost.guru/api/ide/v1/text_to_image?prompt=${encodeURIComponent(characterName + ' 头像 头部特写 写实风格')}&image_size=square`;
                            return `
                                <div class="character-item">
                                    <div class="character-avatar"><img src="${avatarUrl}" alt="${characterName}"></div>
                                    <div class="character-name">${characterName}</div>
                                </div>
                            `;
                        }).join('');
                        charactersDisplay.innerHTML = charactersHtml;
                    } else {
                        charactersDisplay.innerHTML = '<span class="no-characters">暂无角色设定</span>';
                    }
                } else {
                    charactersDisplay.innerHTML = '<span class="no-characters">暂无角色设定</span>';
                }
            })
            .catch(error => {
                console.error('加载角色失败:', error);
                charactersDisplay.innerHTML = '<span class="error-text">加载失败</span>';
            });
    } else if (charactersDisplay) {
        charactersDisplay.innerHTML = '<span class="no-characters">暂无角色设定</span>';
    }

    // 加载时空场景数据
    if (shotId && sceneId) {
        if (typeof loadSpaceSceneData === 'function') {
            loadSpaceSceneData(sceneId, shotId);
        }
    }

    // 显示模态框
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// 关闭生成参考图模态框
function closeGenerateModal() {
    const modal = document.getElementById('generateModal');
    if (modal) {
        modal.classList.remove('active');
        document.body.style.overflow = '';
    }
}

// 显示图片大图模态框
function showImageModal(imageUrl, title, event) {
    const modal = document.createElement('div');
    modal.className = 'modal image-modal';
    
    // 尝试从点击事件中获取分镜信息
    let shotId = null;
    let sceneId = null;
    let shotRow = null;
    
    if (event && event.target) {
        // 从点击的图片元素向上查找分镜行
        shotRow = event.target.closest('.shot-row');
        if (shotRow) {
            // 获取镜号
            shotId = shotRow.querySelector('td:nth-child(2)')?.textContent;
            
            // 获取场次ID
            let prevElement = shotRow.previousElementSibling;
            while (prevElement) {
                if (prevElement.classList.contains('scene-header')) {
                    sceneId = prevElement.querySelector('.scene-number')?.textContent?.replace('#', '');
                    break;
                }
                prevElement = prevElement.previousElementSibling;
            }
            
            // 保存当前分镜信息到全局变量
            if (shotId && sceneId) {
                window.currentGenerateShot = {
                    shotRow: shotRow,
                    shotId: shotId,
                    sceneId: sceneId,
                    type: 'cameraMovement' // 默认类型为运镜画面
                };
            }
        }
    }
    
    // 初始化分割图片按钮显示状态
    let showSplitButton = title === '运镜画面';
    
    // 构建模态框HTML
    let modalHtml = `
        <div class="modal-content image-modal-content">
            <div class="modal-header">
                <h3>${title}</h3>
                <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
            </div>
            <div class="modal-body image-modal-body">
                <img src="${imageUrl}" alt="${title}" class="large-image">
                <!-- 缩略图显示区域 -->
                <div class="thumbnails-container" style="margin-top: 20px; overflow-x: auto; white-space: nowrap; padding: 10px 0;">
                <h4 style="margin-bottom: 10px;">切片图<span style="font-size:9px;color:#c6c6c6;">（备注:切片图数量=宫格数-1）</span></h4>    
                <div class="thumbnails-scroll" style="display: inline-block;">
                        <!-- 缩略图将在这里动态添加 -->
                    </div>
                </div>
                <!-- 切片提示词区域 -->
                <div class="slice-prompts-container" style="margin-top: 20px; width: 100%; overflow: hidden;">
                    <h4 style="margin-bottom: 10px;">切片提示词<span style="font-size:9px;color:#c6c6c6;">（备注:切片提示词数量=宫格数-1，与切片图数量对应）</span></h4>
                    <div class="slice-prompts-scroll" style="width: 100%; overflow: hidden;">
                        <!-- 切片提示词输入框将在这里动态添加 -->
                    </div>
                </div>
            </div>
            <div class="modal-footer">
    `;
    
    // 只在运镜画面模态框中添加按钮
    if (showSplitButton) {
        modalHtml += `<button id="generateSlicePromptsBtn" class="btn btn-primary" style="display: none;">生成切片提示词</button>`;
        modalHtml += `<button id="splitImageBtn" class="btn btn-primary">分割图片</button>`;
    }
    
    // 添加其他按钮
    modalHtml += `
                <button class="btn btn-primary" onclick="window.open('${imageUrl}', '_blank')">在新窗口打开</button>
                <button class="btn btn-secondary" onclick="this.closest('.modal').remove()">关闭</button>
            </div>
        </div>
    `;
    
    modal.innerHTML = modalHtml;
    document.body.appendChild(modal);
    
    // 为分隔图片按钮添加点击事件（如果存在）
    const splitImageBtn = document.getElementById('splitImageBtn');
    if (splitImageBtn) {
        splitImageBtn.addEventListener('click', function() {
            splitImage(imageUrl, modal);
        });
    }
    
    // 如果是运镜画面模态框，检查video_image_Url字段和CutPrompt字段
    if (title === '运镜画面' && shotId) {
        // 从数据库中获取该分镜的video_image_Url字段值和CutPrompt字段值
        fetch('https://wop.cc/get_shot_data.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ shotId: shotId })
        })
        .then(response => response.json())
        .then(data => {
            // 如果video_image_Url字段非空非NULL，添加转成视频按钮并隐藏分割图片按钮
            if (data.code === 0 && data.data) {
                // 检查video_image_Url字段
                if (data.data.video_image_Url) {
                    // 添加转成视频按钮
                    const modalFooter = modal.querySelector('.modal-footer');
                    if (modalFooter) {
                        const convertToVideoBtn = document.createElement('button');
                        convertToVideoBtn.className = 'btn btn-primary';
                        convertToVideoBtn.textContent = '转成视频';
                        convertToVideoBtn.addEventListener('click', function() {
                            // 创建提示模态框
                            const modal = document.createElement('div');
                            modal.className = 'modal';
                            
                            let modalHtml = `
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h3>提示</h3>
                                        <button class="modal-close" onclick="this.closest('.modal').remove()">&times;</button>
                                    </div>
                                    <div class="modal-body">
                                        <p style="text-align: center; margin: 20px 0;">"转成视频"功能请移步【故事板】进行操作...</p>
                                    </div>
                                    <div class="modal-footer">
                                        <button class="btn btn-primary" onclick="window.location.href='gushiban.php';">前往【故事板】</button>
                                    </div>
                                </div>
                            `;
                            
                            modal.innerHTML = modalHtml;
                            document.body.appendChild(modal);
                        });
                        
                        // 将转成视频按钮插入到第一个位置
                        modalFooter.insertBefore(convertToVideoBtn, modalFooter.firstChild);
                    }
                    
                    // 隐藏分割图片按钮
                    // const splitBtn = document.getElementById('splitImageBtn');
                    // if (splitBtn) {
                    //     splitBtn.style.display = 'none';
                    // }
                    
                    // 尝试加载已保存的图片
                    try {
                        const videoImageUrl = data.data.video_image_Url;
                        const splitImages = JSON.parse(videoImageUrl);
                        
                        if (Array.isArray(splitImages) && splitImages.length > 0) {
                            // 为每个图片URL添加"https://wop.cc/"前缀
                            const formattedImages = splitImages.map(img => {
                                return {
                                    url: 'https://wop.cc/' + img.url,
                                    id: img.id || 'img_' + Math.random().toString(36).substr(2, 9),
                                    taskId: img.taskId || '',
                                    ratio: img.ratio || '16:9'
                                };
                            });
                            
                            // 显示分割图片
                            displayThumbnails(formattedImages, modal);
                            
                            // 检查CutPrompt字段是否有值，如果有则回填到输入框中
                            if (data.data.CutPrompt) {
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
                        }
                    } catch (error) {
                        console.error('解析video_image_Url字段失败:', error);
                    }
                }
            }
        })
        .catch(error => {
            console.error('获取分镜数据失败:', error);
        });
    }
}

// 分割图片函数
function splitImage(imageUrl, modal) {
    const splitButton = document.getElementById('splitImageBtn');
    if (splitButton) {
        splitButton.disabled = true;
        splitButton.textContent = '分割中...';
    }
    
    // 获取当前分镜信息
    const currentShot = window.currentGenerateShot;
    if (!currentShot) {
        alert('无法获取当前分镜信息');
        if (splitButton) {
            splitButton.disabled = false;
            splitButton.textContent = '分割图片';
        }
        return;
    }
    
    const { shotId, sceneId } = currentShot;
    if (!shotId || !sceneId) {
        alert('无法获取场次ID或镜号');
        if (splitButton) {
            splitButton.disabled = false;
            splitButton.textContent = '分割图片';
        }
        return;
    }
    
    // 从数据库获取当前分镜的imageUrls和grid_type
    fetch('https://wop.cc/get_shot_data.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            shotId: shotId,
            userId: window.currentUserId,
            taskId: window.dbTaskId,
            sceneId: sceneId
        })
    })
    .then(response => response.json())
    .then(shotData => {
        if (shotData.code === 0 && shotData.data) {
            const { imageUrls, grid_type } = shotData.data;
            
            // 构建请求数据
            const requestData = {
                "image_url": imageUrls || imageUrl,
                "grid_type": grid_type || 9, // 默认九宫格
                "userId": window.currentUserId,
                "taskId": window.dbTaskId,
                "shotId": shotId,
                "sceneId": sceneId
            };
            
            console.log('分割图片请求数据:', requestData);
            
            // 配置请求选项
            const requestOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(requestData)
            };
            
            // 调用分割图片接口
            return fetch('https://wop.cc/fenge.php', requestOptions)
                .then(response => {
                    console.log('分割图片API响应状态:', response.status);
                    if (!response.ok) {
                        return response.text().then(text => {
                            throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                        });
                    }
                    return response.json();
                });
        } else {
            throw new Error('获取分镜数据失败: ' + (shotData.msg || '未知错误'));
        }
    })
    .then(data => {
        console.log('分割图片API响应:', data);
        
        if (data.success) {
            // 处理返回的图片数据
            const tiles = data.data?.tiles || [];
            if (tiles.length > 0) {
                // 转换tiles数据为displayThumbnails函数需要的格式
                const generatedImages = tiles.map(tile => {
                    return {
                        url: tile.url,
                        id: `tile_${tile.index}`,
                        taskId: '',
                        ratio: `${tile.width}:${tile.height}`
                    };
                });
                
                // 在模态框中显示生成的图片
                displayThumbnails(generatedImages, modal);
                
                // 检查CutPrompt字段是否有值，如果有则回填到输入框中
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
                
                // 保存图片到本地服务器并更新数据库
                saveSplitImages(generatedImages, shotId, sceneId, modal)
                    .then(() => {
                        alert('图片分割成功并已保存');
                    })
                    .catch(error => {
                        console.error('保存分割图片失败:', error);
                        alert('保存分割图片失败: ' + error.message);
                    });
            } else {
                alert('分割成功但未获取到图片');
            }
        } else {
            alert('分割图片失败: ' + (data.message || '未知错误'));
        }
    })
    .catch(error => {
        console.error('分割图片时发生错误:', error);
        alert('分割图片时发生错误: ' + error.message);
    })
    .finally(() => {
        if (splitButton) {
            splitButton.disabled = false;
            splitButton.textContent = '分割图片';
        }
    });
}

// 保存分割图片到本地服务器并更新数据库
function saveSplitImages(images, shotId, sceneId, modal) {
    return new Promise((resolve, reject) => {
        // 构建保存请求数据
    const saveData = {
        images: images,
        shotId: shotId,
        sceneId: sceneId,
        taskId: window.dbTaskId || '',
        userId: window.currentUserId
    };
        
        // 配置请求选项
        const requestOptions = {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            credentials: 'same-origin',
            body: JSON.stringify(saveData)
        };
        
        // 调用保存图片接口
        fetch('./save_split_images.php', requestOptions)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // 更新数据库成功
                    console.log('保存分割图片成功:', data);
                    
                    // 显示缩略图
                    displayThumbnails(data.localImages, modal);
                    
                    resolve();
                } else {
                    reject(new Error(data.message || '保存图片失败'));
                }
            })
            .catch(error => {
                reject(error);
            });
    });
}

// 显示分割后的图片缩略图
function displayThumbnails(images, modal) {
    const thumbnailsContainer = modal.querySelector('.thumbnails-scroll');
    if (!thumbnailsContainer) return;
    
    // 清空容器
    thumbnailsContainer.innerHTML = '';
    
    // 添加缩略图
    images.forEach((image, index) => {
        const thumbnail = document.createElement('div');
        thumbnail.style.cssText = `
            display: inline-block;
            margin-right: 10px;
            text-align: center;
        `;
        
        thumbnail.innerHTML = `
            <div style="width: 100px; height: 100px; display: flex; align-items: center; justify-content: center; border: 1px solid #ddd; border-radius: 4px; overflow: hidden;">
                <img src="${image.url}" alt="分割图片 ${index + 1}" style="
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: contain;
                    cursor: pointer;
                " onclick="window.open('${image.url}', '_blank')">
            </div>
            <div style="margin-top: 5px; font-size: 12px; color: #666;">图片 ${index + 1}</div>
        `;
        
        thumbnailsContainer.appendChild(thumbnail);
    });
    
    // 生成切片提示词输入框
    generateSlicePromptsInputs(images, modal);
    
    // 显示生成切片提示词按钮
    const generateBtn = modal.querySelector('#generateSlicePromptsBtn');
    if (generateBtn && images.length > 0) {
        generateBtn.style.display = 'inline-block';
        // 添加点击事件
        generateBtn.onclick = function() {
            generateSlicePrompts(images, modal);
        };
    }
}

// 生成切片提示词输入框
function generateSlicePromptsInputs(images, modal) {
    const promptsContainer = modal.querySelector('.slice-prompts-scroll');
    if (!promptsContainer) return;
    
    // 清空容器
    promptsContainer.innerHTML = '';
    
    // 设置容器基本样式
    promptsContainer.style.cssText = `
        width: 100%;
        margin: 0;
        padding: 0;
        overflow: hidden;
    `;
    
    // 创建滚动容器
    const scrollWrapper = document.createElement('div');
    scrollWrapper.style.cssText = `
        display: flex;
        gap: 15px;
        overflow-x: auto;
        overflow-y: hidden;
        padding: 10px 0;
        white-space: nowrap;
        scrollbar-width: thin;
        scrollbar-color: #888 #f1f1f1;
        cursor: grab;
        -webkit-overflow-scrolling: touch;
        scroll-behavior: smooth;
    `;
    
    // 为滚动容器添加滚动条样式
    const style = document.createElement('style');
    style.textContent = `
        .scroll-wrapper::-webkit-scrollbar {
            height: 8px;
        }
        .scroll-wrapper::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }
        .scroll-wrapper::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 4px;
        }
        .scroll-wrapper::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .scroll-wrapper:active {
            cursor: grabbing;
        }
    `;
    document.head.appendChild(style);
    
    // 添加类名以便应用样式
    scrollWrapper.classList.add('scroll-wrapper');
    
    // 生成输入框，数量比图片少1
    const inputCount = Math.max(0, images.length - 1);
    for (let i = 0; i < inputCount; i++) {
        const inputGroup = document.createElement('div');
        inputGroup.style.cssText = `
            display: flex;
            flex-direction: column;
            flex-shrink: 0;
            width: 200px;
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 6px;
            padding: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        `;
        
        inputGroup.innerHTML = `
            <div style="margin-bottom: 8px; font-size: 12px; font-weight: 600; color: #495057; text-align: center;">切片${i + 1}</div>
            <textarea class="slice-prompt-input" data-index="${i}" style="
                width: 100%;
                padding: 8px 10px;
                border: 1px solid #dee2e6;
                border-radius: 4px;
                font-size: 12px;
                resize: vertical;
                min-height: 80px;
                box-sizing: border-box;
                font-family: inherit;
            "></textarea>
        `;
        
        scrollWrapper.appendChild(inputGroup);
    }
    
    // 将滚动容器添加到切片提示词容器中
    promptsContainer.appendChild(scrollWrapper);
    
    // 确保模态框内容区不会因为切片提示词区域的滚动而滚动
    const modalBody = modal.querySelector('.modal-body');
    if (modalBody) {
        modalBody.style.overflowX = 'hidden';
    }
    
    // 确保滚动容器的父元素也有合适的样式
    const scrollParent = scrollWrapper.parentElement;
    if (scrollParent) {
        scrollParent.style.overflow = 'hidden';
    }
}

// 生成切片提示词
function generateSlicePrompts(images, modal) {
    const generateBtn = modal.querySelector('#generateSlicePromptsBtn');
    if (generateBtn) {
        generateBtn.disabled = true;
        generateBtn.textContent = '生成中...';
    }
    
    // 获取当前分镜信息
    const currentShot = window.currentGenerateShot;
    if (!currentShot) {
        alert('无法获取当前分镜信息');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = '生成切片提示词';
        }
        return;
    }
    
    const { shotId, sceneId } = currentShot;
    if (!shotId || !sceneId) {
        alert('无法获取场次ID或镜号');
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = '生成切片提示词';
        }
        return;
    }
    
    // 获取分镜的script字段内容
    fetch('https://wop.cc/get_shot_data.php', {
        method: 'POST',
        credentials: 'include',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ 
            shotId: shotId,
            userId: window.currentUserId,
            taskId: window.dbTaskId,
            sceneId: sceneId
        })
    })
    .then(response => response.json())
    .then(shotData => {
        if (shotData.code === 0 && shotData.data) {
            // 获取script字段内容，如果为空则使用默认提示词
            const script = shotData.data.script || '请根据图片内容生成切片提示词';
            
            // 构建请求数据
            const imageUrls = images.map(img => img.url);
            const requestData = {
                image_urls: imageUrls,
                prompt: script,
                userId: window.currentUserId,
                taskId: window.dbTaskId,
                shotId: shotId,
                sceneId: sceneId
            };
            
            console.log('生成切片提示词请求数据:', requestData);
            
            // 调用img2text_api.php接口
            return fetch('./img2text_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin',
                body: JSON.stringify(requestData)
            })
            .then(response => response.json());
        } else {
            throw new Error('获取分镜数据失败: ' + (shotData.msg || '未知错误'));
        }
    })
    .then(data => {
        console.log('生成切片提示词API响应:', data);
        
        if (data && data.error) {
            // 处理后端返回的错误信息
            alert('生成切片提示词失败: ' + data.error);
        } else if (data && data.data && Array.isArray(data.data)) {
            // 填充提示词到输入框
            const inputs = modal.querySelectorAll('.slice-prompt-input');
            data.data.forEach((item, index) => {
                if (index < inputs.length) {
                    inputs[index].value = item.content || '';
                }
            });
            
            // 保存返回的JSON到数据库的CutPrompt字段
            const { shotId } = window.currentGenerateShot;
            if (shotId) {
                fetch('./update_cut_prompt.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        shotId: shotId,
                        cutPrompt: JSON.stringify(data),
                        userId: window.currentUserId,
                        taskId: window.dbTaskId
                    })
                })
                .then(response => response.json())
                .then(saveData => {
                    console.log('保存CutPrompt到数据库:', saveData);
                    if (saveData && saveData.code === 0) {
                        alert('切片提示词生成成功并已保存');
                    } else {
                        alert('切片提示词生成成功，但保存到数据库失败');
                    }
                })
                .catch(error => {
                    console.error('保存CutPrompt到数据库失败:', error);
                    alert('切片提示词生成成功，但保存到数据库失败: ' + error.message);
                });
            } else {
                alert('切片提示词生成成功');
            }
        } else {
            alert('生成切片提示词失败: 无效的响应数据');
        }
    })
    .catch(error => {
        console.error('生成切片提示词时发生错误:', error);
        alert('生成切片提示词时发生错误: ' + error.message);
    })
    .finally(() => {
        if (generateBtn) {
            generateBtn.disabled = false;
            generateBtn.textContent = '生成切片提示词';
        }
    });
}

// 处理生成图片表单提交
function handleGenerateImageSubmit(e) {
    e.preventDefault();
    
    const prompt = document.getElementById('promptInput').value.trim();
    const style = document.getElementById('modalStyle').value;
    const picSize = document.getElementById('modalPicSize').value;
    const currentStyleName = document.getElementById('modalCurrentStyle').textContent;
    
    if (!prompt) {
        alert('请输入提示词');
        return;
    }
    
    if (!window.currentGenerateShot) {
        alert('无法获取分镜信息');
        return;
    }
    
    const { shotRow, shotId, sceneId, button, type } = window.currentGenerateShot;
    
    if (!sceneId || !shotId) {
        alert('无法获取场次ID或镜号');
        closeGenerateModal();
        return;
    }
    
    // 获取用户选择的宫格值（仅在生成运镜画面时）
    let grid_type = 9; // 默认九宫格
    if (type === 'cameraMovement') {
        const activeGridBtn = document.querySelector('.grid-btn.active');
        if (activeGridBtn) {
            const gridCount = activeGridBtn.getAttribute('data-count');
            grid_type = parseInt(gridCount) || 9;
        }
    }
    
    const genresDisplay = document.getElementById('modalGenresDisplay');
    let currentGenresText = '';
    if (genresDisplay) {
        const genreTags = genresDisplay.querySelectorAll('.genre-tag');
        currentGenresText = Array.from(genreTags).map(tag => tag.textContent).join('、');
    }
    
    if (!currentGenresText) {
        currentGenresText = '无';
    }
    
    // 在原有prompt后追加艺术风格和题材设定
    let finalPrompt = prompt;
    if (currentStyleName && currentStyleName !== '无') {
        finalPrompt += `，${currentStyleName}风格`;
    }
    if (currentGenresText && currentGenresText !== '无' && currentGenresText !== '加载失败') {
        finalPrompt += `，${currentGenresText}题材`;
    }
    
    // 关闭模态框
    closeGenerateModal();
    
    // 禁用按钮并显示加载状态
    button.disabled = true;
    button.textContent = '生成中...';
    
    // 获取角色三视图图片URL
    const charactersDisplay = document.getElementById('modalCharactersDisplay');
    const characterImages = [];
    
    if (charactersDisplay) {
        const characterItems = charactersDisplay.querySelectorAll('.character-item');
        characterItems.forEach(item => {
            const img = item.querySelector('.character-avatar img');
            if (img && img.src) {
                characterImages.push(img.src);
            }
        });
    }
    
    // 构建请求数据
    const requestData = {
        "prompt": finalPrompt,
        "style": style,
        "picSize": picSize,
        "aspectRatio": picSize === '16:9' ? 'landscape' : (picSize === '9:16' ? 'portrait' : 'landscape'),
        "imgCount": 1,
        "steps": 30,
        "grid_type": grid_type
    };
    
    // 如果有角色图片，添加image参数和sequential_image_generation参数
    if (characterImages.length > 0) {
        requestData.image = characterImages;
        requestData.sequential_image_generation = "disabled";
    }
    
    // 如果有时空场景图片，添加到image参数中
    const spaceSceneDisplay = document.getElementById('modalSpaceSceneDisplay');
    if (spaceSceneDisplay) {
        const spaceSceneImages = [];
        const spaceSceneItems = spaceSceneDisplay.querySelectorAll('.space-scene-item');
        spaceSceneItems.forEach(item => {
            const img = item.querySelector('.space-scene-image');
            if (img && img.src) {
                spaceSceneImages.push(img.src);
            }
        });
        
        if (spaceSceneImages.length > 0) {
            if (requestData.image) {
                // 如果已经有角色图片，合并时空场景图片
                requestData.image = requestData.image.concat(spaceSceneImages);
            } else {
                // 如果没有角色图片，直接使用时空场景图片
                requestData.image = spaceSceneImages;
                requestData.sequential_image_generation = "disabled";
            }
        }
    }
    
    // 调用生成API（带重试机制）
    function fetchWithRetry(url, options, retries = 3, delay = 2000) {
        return new Promise((resolve, reject) => {
            function attempt(remainingRetries) {
                fetch(url, options)
                    .then(response => {
                        if (!response.ok) {
                            // 只对502错误进行重试
                            if (response.status === 502 && remainingRetries > 0) {
                                console.log(`请求失败，状态码: ${response.status}，剩余重试次数: ${remainingRetries - 1}`);
                                // 延迟后重试
                                setTimeout(() => {
                                    attempt(remainingRetries - 1);
                                }, delay);
                            } else {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                        } else {
                            resolve(response);
                        }
                    })
                    .catch(error => {
                        // 网络错误也进行重试
                        if (remainingRetries > 0) {
                            console.log(`请求出错: ${error.message}，剩余重试次数: ${remainingRetries - 1}`);
                            setTimeout(() => {
                                attempt(remainingRetries - 1);
                            }, delay);
                        } else {
                            reject(error);
                        }
                    });
            }
            attempt(retries);
        });
    }
    
    // 配置请求选项，延长超时时间
    const requestOptions = {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        credentials: 'same-origin',
        body: JSON.stringify(requestData),
        timeout: 60000 // 延长超时时间到60秒
    };
    
    // 调用API并处理响应
    fetchWithRetry('https://wop.cc/text2img_no_proxy.php', requestOptions, 3, 3000)
    .then(response => response.json())
    .then(data => {
        console.log('API响应数据:', data);
        
        if (data.code === 0 && data.msg === "Success") {
            const imageUrl = data.data?.imageUrl || 
                           data.data?.fullImageUrl || 
                           (data.data?.allImages && data.data.allImages[0]?.url);
            
            if (imageUrl) {
                if (type === 'cameraMovement') {
                    displayCameraMovementImage(shotRow, imageUrl);
                    saveImageUrlToShotForCameraMovement(shotRow, sceneId, shotId, imageUrl, grid_type);
                } else {
                    displayReferenceImage(shotRow, imageUrl);
                    saveImageUrlToShot(shotRow, sceneId, shotId, imageUrl, grid_type);
                }
            } else {
                alert('生成成功但未获取到图片URL');
            }
        } else {
            alert('生成参考图失败: ' + (data.msg || data.error || '未知错误'));
        }
    })
    .catch(error => {
        alert('生成参考图时发生错误: ' + error.message);
    })
    .finally(() => {
        button.disabled = false;
        button.textContent = '生成参考图';
    });
}

// 初始化移动端菜单
function initMobileMenu() {
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    if (hamburger && navLinks) {
        hamburger.addEventListener('click', function() {
            hamburger.classList.toggle('active');
            navLinks.classList.toggle('active');
            document.body.style.overflow = hamburger.classList.contains('active') ? 'hidden' : '';
        });
    }
}

// 初始化导航功能
function initNavigation() {
    // 导航菜单交互 - 只在没有header.html初始化的情况下执行
    if (typeof window.menuInitialized === 'undefined') {
        const navLinks = document.querySelectorAll('.main-nav a');
        navLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                // 移除所有链接的激活状态
                navLinks.forEach(nav => nav.parentElement.classList.remove('active'));
                // 激活当前链接
                this.parentElement.classList.add('active');
            });
        });
        window.menuInitialized = true;
    }
}

// 初始化浮动提示条
function initFloatingBar() {
    // 默认隐藏浮动提示条
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
        storyboardTab.addEventListener('click', function() {
            floatingBar.classList.remove('hidden');
            adjustMainContentPadding();
        });
    }
    
    // 关闭按钮
    const closeBtn = document.querySelector('.floating-bar .close-btn');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            floatingBar.classList.add('hidden');
            adjustMainContentPadding();
        });
    }
}

// 初始化下拉菜单
function initDropdowns() {
    // 新建下拉按钮
    const newBtn = document.querySelector('.function-left .btn-dropdown');
    if (newBtn) {
        newBtn.addEventListener('click', function() {
            alert('在实际应用中，这里会显示下拉菜单：创建分镜、创建场次');
        });
    }
    
    // 分镜设置下拉按钮
    const settingsBtn = document.querySelector('.function-right .btn-dropdown');
    if (settingsBtn) {
        settingsBtn.addEventListener('click', function() {
            alert('在实际应用中，这里会显示分镜设置菜单');
        });
    }
    
    // 列设置按钮
    const columnBtn = document.querySelector('.function-right .btn:not(.btn-dropdown)');
    if (columnBtn) {
        columnBtn.addEventListener('click', function() {
            alert('在实际应用中，这里会打开列设置面板');
        });
    }
}

// 初始化场次展开/收起功能
function initSceneExpansion() {
    const expandButtons = document.querySelectorAll('.expand-btn');
    expandButtons.forEach(button => {
        button.addEventListener('click', function() {
            const icon = this.querySelector('i');
            const isExpanded = icon.classList.contains('fa-caret-down');
            
            // 找到包含分镜的容器
            const sceneHeader = this.closest('.scene-header');
            // 获取同一场次下的所有分镜行（在当前场次条之后，下一个场次条之前的所有行）
            let nextElement = sceneHeader.nextElementSibling;
            while (nextElement && !nextElement.classList.contains('scene-header')) {
                if (nextElement.classList.contains('shot-row')) {
                    if (isExpanded) {
                        // 收起场次
                        nextElement.style.display = 'none';
                    } else {
                        // 展开场次
                        nextElement.style.display = '';
                    }
                }
                nextElement = nextElement.nextElementSibling;
            }
            
            if (isExpanded) {
                // 收起场次
                icon.classList.remove('fa-caret-down');
                icon.classList.add('fa-caret-right');
            } else {
                // 展开场次
                icon.classList.remove('fa-caret-right');
                icon.classList.add('fa-caret-down');
            }
        });
    });
}

// 初始化展开/收缩所有场次按钮
function initExpandCollapseAllButtons() {
    // 展开所有场次按钮
    const expandAllBtn = document.getElementById('expand-all-scenes');
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function() {
            expandAllScenes();
        });
    }
    
    // 收缩所有场次按钮
    const collapseAllBtn = document.getElementById('collapse-all-scenes');
    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function() {
            collapseAllScenes();
        });
    }
}

// 展开所有场次
function expandAllScenes() {
    const sceneHeaders = document.querySelectorAll('.scene-header');
    sceneHeaders.forEach(header => {
        const icon = header.querySelector('.expand-btn i');
        // 获取同一场次下的所有分镜行
        let nextElement = header.nextElementSibling;
        while (nextElement && !nextElement.classList.contains('scene-header')) {
            if (nextElement.classList.contains('shot-row')) {
                // 展开场次
                nextElement.style.display = '';
            }
            nextElement = nextElement.nextElementSibling;
        }
        
        // 更新图标
        if (icon) {
            icon.classList.remove('fa-caret-right');
            icon.classList.add('fa-caret-down');
        }
    });
}

// 收缩所有场次
function collapseAllScenes() {
    const sceneHeaders = document.querySelectorAll('.scene-header');
    sceneHeaders.forEach(header => {
        const icon = header.querySelector('.expand-btn i');
        // 获取同一场次下的所有分镜行
        let nextElement = header.nextElementSibling;
        while (nextElement && !nextElement.classList.contains('scene-header')) {
            if (nextElement.classList.contains('shot-row')) {
                // 收起场次
                nextElement.style.display = 'none';
            }
            nextElement = nextElement.nextElementSibling;
        }
        
        // 更新图标
        if (icon) {
            icon.classList.remove('fa-caret-down');
            icon.classList.add('fa-caret-right');
        }
    });
}

// 初始化拖拽功能
function initDragAndDrop() {
    // 获取表格容器，使用事件委托处理所有拖拽事件
    const tableBody = document.getElementById('storyboard-table-body');
    if (!tableBody) return;
    
    // 简化的拖拽状态管理
    let draggingElement = null;
    let placeholder = null;
    let isDragging = false;
    let lastInsertedBefore = null; // 记录上一次插入位置，减少DOM操作
    let throttled = false; // 用于节流控制
    let draggingShotId = null; // 保存拖拽的分镜ID
    
    // 1. 为所有拖拽手柄添加draggable属性
    const dragHandles = tableBody.querySelectorAll('.drag-handle');
    // 使用requestAnimationFrame延迟添加draggable属性，减少初始化时间
    requestAnimationFrame(() => {
        dragHandles.forEach(handle => {
            handle.setAttribute('draggable', true);
        });
    });
    
    // 2. 处理dragstart事件
    tableBody.addEventListener('dragstart', function(e) {
        if (e.target.closest('.drag-handle')) {
            const dragHandle = e.target.closest('.drag-handle');
            draggingElement = dragHandle.closest('.scene-header, .shot-row');
            
            if (draggingElement) {
                isDragging = true;
                // 标记为拖拽中
                draggingElement.classList.add('dragging');
                
                // 设置拖拽数据
                e.dataTransfer.effectAllowed = 'move';
                const type = draggingElement.classList.contains('scene-header') ? 'scene' : 'shot';
                e.dataTransfer.setData('text/plain', type);
                
                // 保存拖拽的分镜ID（如果是分镜拖拽）
                if (type === 'shot') {
                    const shotCell = draggingElement.querySelector('td:nth-child(2)');
                    if (shotCell) {
                        draggingShotId = parseInt(shotCell.textContent);
                    }
                } else {
                    draggingShotId = null;
                }
            }
        }
    });
    
    // 3. 处理dragend事件
    tableBody.addEventListener('dragend', function() {
        if (draggingElement) {
            draggingElement.classList.remove('dragging');
            draggingElement = null;
        }
        if (placeholder && placeholder.parentNode) {
            placeholder.parentNode.removeChild(placeholder);
            placeholder = null;
        }
        isDragging = false;
        lastInsertedBefore = null;
        throttled = false;
    });
    
    // 4. 处理dragover事件 - 添加节流优化
    tableBody.addEventListener('dragover', function(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        
        if (!isDragging || !draggingElement) return;
        
        // 添加节流控制，减少处理频率
        if (throttled) return;
        throttled = true;
        setTimeout(() => {
            throttled = false;
        }, 50); // 50ms节流间隔
        
        // 创建占位符
        if (!placeholder) {
            placeholder = document.createElement('tr');
            placeholder.className = 'drag-placeholder';
            placeholder.innerHTML = '<td colspan="28"></td>';
        }
        
        // 计算放置位置 - 优化版
        const y = e.clientY;
        let closestElement = null;
        let insertBefore = null;
        
        // 获取所有子元素（排除占位符和拖拽元素）
        const children = Array.from(this.children).filter(child => 
            !child.classList.contains('drag-placeholder') && child !== draggingElement
        );
        
        // 简化位置计算：找到第一个底部超过鼠标位置的元素
        for (const child of children) {
            const rect = child.getBoundingClientRect();
            if (rect.bottom > y) {
                insertBefore = child;
                break;
            }
        }
        
        // 如果没有找到，说明应该放在最后
        if (!insertBefore) {
            insertBefore = null;
        }
        
        // 只在位置改变时才更新占位符位置，减少DOM操作
        if (insertBefore !== lastInsertedBefore) {
            if (insertBefore) {
                this.insertBefore(placeholder, insertBefore);
            } else {
                this.appendChild(placeholder);
            }
            lastInsertedBefore = insertBefore;
        }
    });
    
    // 5. 处理drop事件
    tableBody.addEventListener('drop', function(e) {
        e.preventDefault();
        
        if (!isDragging || !draggingElement) return;
        
        const type = e.dataTransfer.getData('text/plain');
        let isCrossSceneDrag = false;
        let sourceSceneId = null;
        let targetSceneId = null;
        
        // 获取拖拽分镜所属的源场次ID
        if (type === 'shot' && draggingElement.classList.contains('shot-row')) {
            let prevElement = draggingElement.previousElementSibling;
            while (prevElement) {
                if (prevElement.classList.contains('scene-header')) {
                    sourceSceneId = prevElement.querySelector('.scene-number')?.textContent?.replace('#', '');
                    break;
                }
                prevElement = prevElement.previousElementSibling;
            }
        }
        
        // 检查是否拖到场次头部
        const targetHeader = e.target.closest('.scene-header');
        if (type === 'shot' && draggingElement.classList.contains('shot-row') && targetHeader) {
            // 处理分镜拖到场次头部的情况
            targetSceneId = targetHeader.querySelector('.scene-number')?.textContent?.replace('#', '');
            
            // 判断是否为跨场次拖拽
            isCrossSceneDrag = sourceSceneId !== targetSceneId;
            
            let nextElement = targetHeader.nextElementSibling;
            // 找到下一个场次头部
            while (nextElement && !nextElement.classList.contains('scene-header')) {
                nextElement = nextElement.nextElementSibling;
            }
            
            // 将分镜插入到目标场次中
            const element = draggingElement;
            if (nextElement) {
                tableBody.insertBefore(element, nextElement);
            } else {
                tableBody.appendChild(element);
            }
            
            // 添加插入动画效果
            element.classList.add('drag-insert-animation');
            setTimeout(() => {
                element.classList.remove('drag-insert-animation');
            }, 300);
        } else if (placeholder) {
            // 普通drop事件处理
            if (type === 'scene' && draggingElement.classList.contains('scene-header')) {
                // 处理场次拖拽，包括其所有分镜
                const allElementsToMove = [draggingElement];
                let nextElement = draggingElement.nextElementSibling;
                
                // 收集当前场次的所有分镜
                while (nextElement && !nextElement.classList.contains('scene-header')) {
                    if (nextElement.classList.contains('shot-row')) {
                        allElementsToMove.push(nextElement);
                    }
                    nextElement = nextElement.nextElementSibling;
                }
                
                // 插入所有元素到新位置
                for (let i = allElementsToMove.length - 1; i >= 0; i--) {
                    const element = allElementsToMove[i];
                    tableBody.insertBefore(element, placeholder);
                    
                    // 添加插入动画效果
                    element.classList.add('drag-insert-animation');
                    setTimeout(() => {
                        element.classList.remove('drag-insert-animation');
                    }, 300);
                }
            } else if (type === 'shot' && draggingElement.classList.contains('shot-row')) {
                // 处理分镜拖拽
                const element = draggingElement;
                tableBody.insertBefore(element, placeholder);
                
                // 添加插入动画效果
                element.classList.add('drag-insert-animation');
                setTimeout(() => {
                    element.classList.remove('drag-insert-animation');
                }, 300);
                
                // 获取目标场次ID
                let targetElement = placeholder;
                if (!targetElement.classList.contains('scene-header')) {
                    targetElement = targetElement.previousElementSibling;
                    while (targetElement && !targetElement.classList.contains('scene-header')) {
                        targetElement = targetElement.previousElementSibling;
                    }
                }
                if (targetElement && targetElement.classList.contains('scene-header')) {
                    targetSceneId = targetElement.querySelector('.scene-number')?.textContent?.replace('#', '');
                }
                
                // 判断是否为跨场次拖拽
                isCrossSceneDrag = sourceSceneId !== targetSceneId;
            }
            
            // 移除占位符
            if (placeholder.parentNode) {
                placeholder.parentNode.removeChild(placeholder);
            }
        }
        
        // 拖拽结束后，更新数据库中的排序
        if (type === 'shot') {
            if (isCrossSceneDrag && sourceSceneId && targetSceneId) {
                // 跨场次拖拽，调用跨场次拖拽API
                updateCrossSceneOrder(sourceSceneId, targetSceneId);
            } else {
                // 同场次拖拽，调用原有API
                updateShotOrder();
            }
        } else if (type === 'scene') {
            // 场次拖拽，需要更新场次排序
            updateSceneOrder();
        }
        
        // 重置状态
        draggingElement.classList.remove('dragging');
        draggingElement = null;
        placeholder = null;
        isDragging = false;
        draggingShotId = null;
    });
    
    // 跨场次拖拽更新函数
    function updateCrossSceneOrder(sourceSceneId, targetSceneId) {
        const taskId = window.currentTaskId || window.dbTaskId;
        if (!taskId) return;
        
        // 使用dragstart事件中保存的拖拽分镜ID
        if (!draggingShotId) {
            console.error('拖拽分镜ID未找到');
            return;
        }
        
        // 获取目标场次下的所有分镜行，按照拖拽后的顺序
        let targetSceneHeader = null;
        const sceneHeaders = document.querySelectorAll('.scene-header');
        for (const header of sceneHeaders) {
            const sceneNumber = header.querySelector('.scene-number');
            if (sceneNumber && sceneNumber.textContent.includes(`#${targetSceneId}`)) {
                targetSceneHeader = header;
                break;
            }
        }
        if (!targetSceneHeader) return;
        
        const targetShotIds = [];
        let nextElement = targetSceneHeader.nextElementSibling;
        while (nextElement && !nextElement.classList.contains('scene-header')) {
            if (nextElement.classList.contains('shot-row')) {
                const shotId = parseInt(nextElement.querySelector('td:nth-child(2)').textContent);
                if (!isNaN(shotId)) {
                    targetShotIds.push(shotId);
                }
            }
            nextElement = nextElement.nextElementSibling;
        }
        
        // 调用跨场次拖拽API，发送拖拽的分镜ID和目标场次的分镜顺序
        fetch('./update_cross_scene_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                "task_id": taskId,
                "source_scene_id": sourceSceneId,
                "target_scene_id": targetSceneId,
                "shot_ids": [draggingShotId],
                "target_shot_order": targetShotIds
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log(`跨场次分镜拖拽更新成功`);
                
                // 重新加载所有场次的分镜数据，确保显示正确
                loadStoryboardData();
            } else {
                console.error('跨场次分镜拖拽更新失败:', data.error);
            }
        })
        .catch(error => {
            console.error('更新跨场次分镜排序时发生错误:', error);
        });
    }
    
    // 更新分镜排序
    function updateShotOrder() {
        const taskId = window.currentTaskId || window.dbTaskId;
        if (!taskId) return;
        
        // 获取所有场次
        const sceneHeaders = document.querySelectorAll('.scene-header');
        
        // 遍历每个场次，更新其下的分镜排序
        sceneHeaders.forEach(sceneHeader => {
            const sceneId = sceneHeader.querySelector('.scene-number')?.textContent?.replace('#', '');
            if (!sceneId) return;
            
            // 收集该场次下的所有分镜ID
            const shotOrder = collectShotOrder(sceneHeader);
            if (shotOrder.length === 0) return;
            
            // 调用API更新分镜排序
            fetch('./update_storyboard_order.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    "task_id": taskId,
                    "type": "shot",
                    "scene_id": sceneId,
                    "new_order": shotOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log(`场次${sceneId}的分镜排序更新成功`);
                    
                    // 实时更新所有场次的分镜号显示，处理跨场次拖拽情况
                    const sceneHeaders = document.querySelectorAll('.scene-header');
                    
                    // 遍历每个场次，更新其下的分镜号
                    sceneHeaders.forEach(header => {
                        let shotIndex = 1;
                        let nextElement = header.nextElementSibling;
                        
                        // 获取当前场次ID
                        const currentSceneId = header.querySelector('.scene-number')?.textContent?.replace('#', '');
                        if (!currentSceneId) return;
                        
                        // 遍历当前场次下的所有分镜行，更新镜号
                        while (nextElement && !nextElement.classList.contains('scene-header')) {
                            if (nextElement.classList.contains('shot-row')) {
                                // 更新镜号单元格内容
                                const shotCell = nextElement.querySelector('td:nth-child(2)');
                                if (shotCell) {
                                    shotCell.textContent = shotIndex;
                                    // 更新拖拽时使用的data属性（如果有的话）
                                    const generateBtn = nextElement.querySelector('.generate-btn, .regenerate-btn');
                                    if (generateBtn) {
                                        generateBtn.setAttribute('data-shot-id', shotIndex);
                                    }
                                }
                                shotIndex++;
                            }
                            nextElement = nextElement.nextElementSibling;
                        }
                    });
                } else {
                    console.error(`场次${sceneId}的分镜排序更新失败:`, data.error);
                }
            })
            .catch(error => {
                console.error('更新分镜排序时发生错误:', error);
            });
        });
    }
    
    // 更新场次排序
    function updateSceneOrder() {
        const taskId = window.currentTaskId || window.dbTaskId;
        if (!taskId) return;
        
        // 收集所有场次的排序
        const sceneOrder = collectSceneOrder();
        if (sceneOrder.length === 0) return;
        
        // 调用API更新场次排序
        fetch('./update_storyboard_order.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                "task_id": taskId,
                "type": "scene",
                "new_order": sceneOrder
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('场次排序更新成功');
                // 重新加载分镜数据，更新显示的场次号
                loadStoryboardData();
            } else {
                console.error('场次排序更新失败:', data.error);
            }
        })
        .catch(error => {
            console.error('更新场次排序时发生错误:', error);
        });
    }
    
    // 收集某场次下所有分镜的ID和当前顺序
    function collectShotOrder(sceneHeader) {
        const shotIds = [];
        
        // 获取该场次下的所有分镜行
        let nextElement = sceneHeader.nextElementSibling;
        while (nextElement && !nextElement.classList.contains('scene-header')) {
            if (nextElement.classList.contains('shot-row')) {
                // 获取镜号（去除可能的#符号）
                const shotCell = nextElement.querySelector('td:nth-child(2)');
                if (shotCell) {
                    const shotId = shotCell.textContent.replace(/[^0-9]/g, '');
                    if (shotId) {
                        shotIds.push(shotId);
                    }
                }
            }
            nextElement = nextElement.nextElementSibling;
        }
        
        return shotIds;
    }
    
    // 收集所有场次的ID和当前顺序
    function collectSceneOrder() {
        const sceneIds = [];
        const sceneHeaders = document.querySelectorAll('.scene-header');
        
        sceneHeaders.forEach(header => {
            const sceneId = header.querySelector('.scene-number')?.textContent?.replace('#', '');
            if (sceneId) {
                sceneIds.push(sceneId);
            }
        });
        
        return sceneIds;
    }
}

// 初始化生成参考图按钮
function initGenerateButtons() {
    // 使用事件委托，将事件监听器绑定到表格容器上
    const tableBody = document.getElementById('storyboard-table-body');
    if (tableBody) {
        tableBody.addEventListener('click', function(e) {
            // 找到真正的按钮元素（可能点击的是按钮内的文本或图标）
            let button = e.target;
            while (button && !button.classList.contains('btn-secondary') && !button.classList.contains('regenerate-btn')) {
                button = button.parentElement;
                if (!button) break;
            }
            
            // 检查是否找到按钮元素，并且是"生成参考图"或"重新生成"按钮
            if (button && (button.classList.contains('btn-secondary') || button.classList.contains('regenerate-btn')) && 
                (button.textContent.includes('生成参考图') || button.textContent.includes('重新生成'))) {
                e.preventDefault();
                e.stopPropagation();
                openGenerateModal(button);
            }
        });
    }
}

// 从表格行中提取分镜数据
function getShotDataFromRow(shotRow) {
    try {
        // 获取表格中各列的数据
        const cells = shotRow.querySelectorAll('td');
        
        // 获取参考画面列中的图片URL
        let imageUrl = '';
        const referenceCell = shotRow.querySelector('td:nth-child(4)');
        if (referenceCell) {
            const imgElement = referenceCell.querySelector('img');
            if (imgElement) {
                imageUrl = imgElement.src;
            }
        }
        
        // 根据表格结构提取数据 (注意索引是从0开始的)
        const shotData = {
            location: cells[16]?.textContent || '',           // 地点
            sceneExpectation: cells[8]?.textContent || '',    // 场景预期
            time: cells[17]?.textContent || '',               // 时间
            weather: cells[18]?.textContent || '',            // 天气
            lightTone: cells[15]?.textContent || '',          // 光线与色调
            shotType: cells[4]?.textContent || '',            // 景别
            cameraAngle: cells[10]?.textContent || '',        // 摄像机角度
            lensFocalLength: cells[13]?.textContent || '',    // 镜头焦段
            cameraMovement: cells[11]?.textContent || '',     // 运镜
            characters: cells[21]?.textContent || '',         // 角色清单
            characterCostumes: cells[23]?.textContent || '',  // 各角色推荐服装
            characterActions: cells[24]?.textContent || '',   // 角色动作
            script: cells[20]?.textContent || '', 
            props: cells[25]?.textContent || '',
            compositionFocus: cells[14]?.textContent || '',   // 构图与焦点
            imageUrl: imageUrl                               // 参考画面图片URL
        };
        
        return shotData;
    } catch (error) {
        // console.error('提取分镜数据出错:', error);
        return null;
    }
}

// 显示参考图片
function displayReferenceImage(shotRow, imageUrl) {
    // 找到参考画面单元格 (第3列)
    const referenceCell = shotRow.querySelector('td:nth-child(3)');
    if (!referenceCell) return;
    
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
        cursor: pointer;
    `;
    img.onclick = function(event) {
        showImageModal(imageUrl, '参考图', event);
    };
    
    // 创建悬浮按钮
    const button = document.createElement('button');
    button.className = 'btn btn-sm btn-secondary regenerate-btn';
    button.textContent = '重新生成';
    button.setAttribute('data-type', 'reference');
    button.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        opacity: 0.7;
        transition: opacity 0.3s;
    `;
    
    // 添加按钮悬停效果
    button.addEventListener('mouseenter', function() {
        this.style.opacity = '1';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.opacity = '0.7';
    });
    
    // 为按钮添加点击事件
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        openGenerateModal(this);
    });
    
    // 组装元素
    container.appendChild(img);
    container.appendChild(button);
    
    // 替换原有内容
    referenceCell.innerHTML = '';
    referenceCell.appendChild(container);
}

// 显示运镜画面
function displayCameraMovementImage(shotRow, imageUrl) {
    // 找到运镜画面单元格 (第4列)
    const cameraMovementCell = shotRow.querySelector('td:nth-child(4)');
    if (!cameraMovementCell) return;
    
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
    img.alt = '运镜画面';
    img.style.cssText = `
        width: 100%;
        height: 100%;
        object-fit: contain;
        border-radius: 4px;
        cursor: pointer;
    `;
    img.onclick = function(event) {
        showImageModal(imageUrl, '运镜画面', event);
    };
    
    // 创建悬浮按钮
    const button = document.createElement('button');
    button.className = 'btn btn-sm btn-secondary regenerate-btn';
    button.textContent = '重新生成';
    button.setAttribute('data-type', 'cameraMovement');
    button.style.cssText = `
        position: absolute;
        top: 10px;
        right: 10px;
        z-index: 10;
        opacity: 0.7;
        transition: opacity 0.3s;
    `;
    
    // 添加按钮悬停效果
    button.addEventListener('mouseenter', function() {
        this.style.opacity = '1';
    });
    
    button.addEventListener('mouseleave', function() {
        this.style.opacity = '0.7';
    });
    
    // 为按钮添加点击事件
    button.addEventListener('click', function(e) {
        e.stopPropagation();
        openGenerateModal(this);
    });
    
    // 组装元素
    container.appendChild(img);
    container.appendChild(button);
    
    // 替换原有内容
    cameraMovementCell.innerHTML = '';
    cameraMovementCell.appendChild(container);
}

// 保存图片URL到分镜数据中
function saveImageUrlToShot(shotRow, sceneId, shotId, imageUrl, grid_type = null) {
    // 调用后端PHP脚本保存图片URL到JSON文件
    const requestData = {
        "sceneId": sceneId,
        "shotId": parseInt(shotId),
        "imageUrl": imageUrl,
        "taskId": window.currentTaskId || window.dbTaskId || null  // 传递taskId以确定正确的JSON文件
    };
    
    // 如果有grid_type值，添加到请求数据中
    if (grid_type !== null) {
        requestData.grid_type = grid_type;
    }
    
    fetch('./save_image_url.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // console.error('保存图片URL失败:', data.error);
        } else {
            console.log('图片URL保存成功');
        }
    })
    .catch(error => {
        // console.error('保存图片URL时发生错误:', error);
    });
}

// 保存运镜画面图片URL到分镜数据中
function saveImageUrlToShotForCameraMovement(shotRow, sceneId, shotId, imageUrl, grid_type = null) {
    // 调用后端PHP脚本保存图片URL到JSON文件
    const requestData = {
        "sceneId": sceneId,
        "shotId": parseInt(shotId),
        "imageUrls": imageUrl,
        "taskId": window.currentTaskId || window.dbTaskId || null  // 传递taskId以确定正确的JSON文件
    };
    
    // 如果有grid_type值，添加到请求数据中
    if (grid_type !== null) {
        requestData.grid_type = grid_type;
    }
    
    fetch('./save_image_url.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify(requestData)
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) {
            // console.error('保存运镜画面图片URL失败:', data.error);
        } else {
            console.log('运镜画面图片URL保存成功');
        }
    })
    .catch(error => {
        // console.error('保存运镜画面图片URL时发生错误:', error);
    });
}

// 初始化表格操作按钮
function initActionButtons() {
    const actionButtons = document.querySelectorAll('.action-col .btn-icon');
    actionButtons.forEach(button => {
        button.addEventListener('click', function() {
            alert('在实际应用中，这里会显示分镜操作菜单');
        });
    });
}


function getLatestCompletedTaskId() {
    // 确保window.currentUserId已设置
    if (!window.currentUserId) {
        console.warn('当前用户ID未设置，无法获取用户关联的任务');
        return null;
    }
    
    // 使用包含用户ID的键名，确保本地任务与用户关联
    const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
    let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
    
    // 不再自动迁移旧数据，避免跨用户数据泄露
    // 只返回当前用户的任务
    if (tasks.length === 0) {
        console.warn('当前用户没有本地任务数据');
        return null;
    }
    
    // 找到最后一个状态为 completed 的任务的 id
    for (let i = tasks.length - 1; i >= 0; i--) {
        if (tasks[i].status === 'completed') {
            return tasks[i].id;
        }
    }

    return null;
}

// 加载分镜数据
function loadStoryboardData() {
    // 确保window.currentUserId已设置
    if (!window.currentUserId) {
        console.error('当前用户ID未设置，无法加载分镜数据');
        const tableBody = document.getElementById('storyboard-table-body');
        if (tableBody) {
            tableBody.innerHTML = '<tr><td colspan="27" style="text-align: center; color: #ff6b6b; padding: 20px;">用户未登录，无法访问分镜数据</td></tr>';
        }
        return;
    }
    
    // 按照优先级获取task_id：
    // 1. 首先使用从数据库中获取的task_id（window.dbTaskId，优先级最高）
    // 2. 然后使用URL参数中的task_id
    // 3. 最后使用本地存储的当前用户的task_id
    const urlParams = new URLSearchParams(window.location.search);
    const urlTaskId = urlParams.get('task_id');
    let taskId = window.dbTaskId || urlTaskId;
    
    // 只在没有其他来源时使用本地存储的任务ID，并且确保是当前用户的
    if (!taskId || taskId === null || taskId === undefined) {
        taskId = getLatestCompletedTaskId();
    }
    
    // 构建API请求URL，不传递sort_by参数，默认按shots_id排序
    const apiUrl = taskId 
        ? `./storyboard_api.php?task_id=${taskId}`
        : './json/storyboard-data.json';
    
    // 保存taskId到全局变量，以便在其他地方使用
    window.currentTaskId = taskId;
    
    console.log('加载分镜数据API:', apiUrl);
    console.log('使用的task_id:', taskId, '（来源：', window.dbTaskId ? '数据库' : urlTaskId ? 'URL参数' : '本地存储', '）');
    
    // 添加加载指示器
    const loadingIndicator = document.createElement('div');
    loadingIndicator.className = 'loading-container';
    loadingIndicator.innerHTML = '<div class="loading"></div><span>加载分镜数据中...</span>';
    const tableContainer = document.querySelector('.storyboard-table-container');
    if (tableContainer) {
        tableContainer.appendChild(loadingIndicator);
    }
    
    // 使用fetch请求数据，设置cache: 'no-cache'以确保获取最新数据
    fetch(apiUrl, {
        cache: 'no-cache',
        headers: {
            'Content-Type': 'application/json'
        }
    })
        .then(response => {
            // 移除加载指示器
            if (loadingIndicator.parentNode) {
                loadingIndicator.parentNode.removeChild(loadingIndicator);
            }
            // 检查响应状态
            if (!response.ok) {
                throw new Error(`HTTP错误!状态: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            try {
                // 尝试解析JSON
                const data = JSON.parse(text);
                // 使用requestAnimationFrame延迟渲染，减少主线程阻塞
                requestAnimationFrame(() => {
                    renderStoryboardData(data);
                });
                
                // 使用requestIdleCallback在浏览器空闲时初始化非关键功能
                if ('requestIdleCallback' in window) {
                    requestIdleCallback(() => {
                        // 延迟初始化场景展开功能
                        initSceneExpansion();
                        // 延迟初始化拖拽功能，只在数据加载完成后执行一次
                        initDragAndDrop();
                        // 延迟检查文本高度
                        requestIdleCallback(checkAndApplyTextClamping, { timeout: 2000 });
                    }, { timeout: 1500 });
                } else {
                    // 降级处理，使用setTimeout延迟执行
                    setTimeout(() => {
                        initSceneExpansion();
                        initDragAndDrop();
                        setTimeout(checkAndApplyTextClamping, 500);
                    }, 800);
                }
            } catch (jsonError) {
                // 处理JSON解析错误
                console.error('解析分镜数据失败:', jsonError);
                const tableBody = document.getElementById('storyboard-table-body');
                if (tableBody) {
                    tableBody.innerHTML = '<tr><td colspan="27" style="text-align: center; color: #ff6b6b; padding: 20px;">分镜数据解析失败，请检查网络连接或联系管理员</td></tr>';
                }
            }
        })
        .catch(error => {
            // 移除加载指示器
            if (loadingIndicator.parentNode) {
                loadingIndicator.parentNode.removeChild(loadingIndicator);
            }
            console.error('加载分镜数据失败:', error);
            // 显示友好的错误提示
            const tableBody = document.getElementById('storyboard-table-body');
            if (tableBody) {
                tableBody.innerHTML = '<tr><td colspan="27" style="text-align: center; color: #ff6b6b; padding: 20px;">加载分镜数据失败，请确保您已登录且有权限访问此任务</td></tr>';
            }
        });
}



// 渲染分镜数据
function renderStoryboardData(data) {
    const tableBody = document.getElementById('storyboard-table-body');
    if (!tableBody) return;
    
    // 清空现有内容
    tableBody.innerHTML = '';
    
    // 使用字符串构建器而不是直接字符串连接，提高性能
    const htmlBuilder = [];
    
    // 预编译模板字符串，减少重复字符串连接
    const sceneHeaderTemplate = scene => {
        // 处理标签数组
        let tagsHtml = '';
        for (let i = 0; i < scene.tags.length; i++) {
            tagsHtml += `<span class="tag">${scene.tags[i]}</span>`;
        }
        
        return `
        <tr class="scene-header">
            <td colspan="27">
                <div class="scene-header-content">
                    <button class="btn-icon expand-btn"><i class="fas fa-caret-down"></i></button>
                    <div class="drag-handle"><i class="fas fa-grip-lines"></i></div>
                    <div class="scene-info">
                        <span class="scene-name">${scene.name}</span>
                        <span class="scene-number">#${scene.id}</span>
                    </div>
                    <div class="scene-tags">
                        ${tagsHtml}
                    </div>
                    <div class="scene-actions">
                        <button class="btn btn-sm">演员</button>
                        <button class="btn btn-sm">服装</button>
                        <button class="btn btn-sm">化妆</button>
                        <button class="btn btn-sm">道具</button>
                    </div>
                </div>
            </td>
        </tr>`;
    };
    
    const shotRowTemplate = (shot) => {
        // 检查是否已有参考图
        const hasReferenceImage = shot.imageUrl && shot.imageUrl.trim() !== '';
        
        // 生成参考图HTML
        let referenceHtml = '';
        if (hasReferenceImage) {
            referenceHtml = `<div class="reference-container" style="width: 100%; height: 100%; position: relative;">
                <img src="${shot.imageUrl}" alt="参考图" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px; cursor: pointer;" onclick="showImageModal('${shot.imageUrl}', '参考图', event);">
                <button class="btn btn-sm btn-secondary regenerate-btn" style="position: absolute; top: 10px; right: 10px; z-index: 10; opacity: 0.7;" data-type="reference">重新生成</button>
            </div>`;
        } else {
            referenceHtml = `<div class="reference-placeholder">
                <div class="reference-text">${shot.content}</div>
                <button class="btn btn-sm btn-secondary" data-type="reference">生成参考图</button>
            </div>`;
        }
        
        // 检查是否已有运镜画面
        const hasCameraMovementImage = shot.imageUrls && shot.imageUrls.trim() !== '';
        
        // 生成运镜画面HTML
        let cameraMovementHtml = '';
        if (hasCameraMovementImage) {
            cameraMovementHtml = `<div class="reference-container" style="width: 100%; height: 100%; position: relative;">
                <img src="${shot.imageUrls}" alt="运镜画面" style="width: 100%; height: 100%; object-fit: contain; border-radius: 4px; cursor: pointer;" onclick="showImageModal('${shot.imageUrls}', '运镜画面', event);">
                <button class="btn btn-sm btn-secondary regenerate-btn" style="position: absolute; top: 10px; right: 10px; z-index: 10; opacity: 0.7;" data-type="cameraMovement">重新生成</button>
            </div>`;
        } else {
            cameraMovementHtml = `<div class="reference-placeholder">
                <div class="reference-text">${shot.content}</div>
                <button class="btn btn-sm btn-secondary" data-type="cameraMovement">生成参考图</button>
            </div>`;
        }
        
        return `
            <tr class="shot-row">
                <td class="fixed-col"><i class="fas fa-grip-lines drag-handle"></i></td>
                <td class="fixed-col">${shot.id}</td>
                <td class="image-cell">
                    ${referenceHtml}
                </td>
                <td class="image-cell">
                    ${cameraMovementHtml}
                </td>
                <td>${shot.shotType}</td>
                <td>${shot.duration}</td>
                <td>${shot.content}</td>
                <td>${shot.remark}</td>
                <td>${shot.sceneExpectation}</td>
                <td>${shot.sound}</td>
                <td>${shot.cameraAngle}</td>
                <td>${shot.cameraMovement}</td>
                <td>${shot.cameraEquipment}</td>
                <td>${shot.lensFocalLength}</td>
                <td>${shot.compositionFocus}</td>
                <td>${shot.lightTone}</td>
                <td>${shot.location}</td>
                <td>${shot.time}</td>
                <td>${shot.weather}</td>
                <td class="auto-wrap">${shot.dialogue}</td>
                <td class="auto-wrap">${shot.script}</td>
                <td>${shot.characters}</td>
                <td class="auto-wrap">${shot.characterCostumes}</td>
                <td class="auto-wrap">${shot.characterMakeup}</td>
                <td class="auto-wrap">${shot.characterActions}</td>
                <td>${shot.props}</td>
            </tr>`;
    };
    
    // 遍历所有场次，使用模板生成HTML并添加到构建器中
    if (data && Array.isArray(data.scenes)) {
        for (let i = 0; i < data.scenes.length; i++) {
            const scene = data.scenes[i];
            // 添加场次头部
            htmlBuilder.push(sceneHeaderTemplate(scene));
            
            // 添加场次下的所有分镜
            if (Array.isArray(scene.shots)) {
                for (let j = 0; j < scene.shots.length; j++) {
                    const shot = scene.shots[j];
                    htmlBuilder.push(shotRowTemplate(shot));
                }
            }
        }
    } else {
        // 显示友好的错误提示
        tableBody.innerHTML = '<tr><td colspan="27" style="text-align: center; color: #ff6b6b; padding: 20px;">分镜数据格式错误或无数据，请检查任务ID或联系管理员</td></tr>';
        return;
    }
    
    // 使用innerHTML一次性插入所有内容，减少DOM操作次数
    tableBody.innerHTML = htmlBuilder.join('');
    
    // 注意：initGenerateButtons()已在页面初始化时调用，这里不需要重复调用
}

// 检查文本高度并应用省略效果
function checkAndApplyTextClamping() {
    // 使用requestIdleCallback在浏览器空闲时执行，减少主线程阻塞
    if ('requestIdleCallback' in window) {
        requestIdleCallback(() => {
            // 除排序、镜号、画面、参考画面、景别、时长（秒）之外的所有字段
            const textCells = document.querySelectorAll('.storyboard-table tbody td:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)):not(:nth-child(6))');
            
            // 使用requestAnimationFrame批量处理，减少回流重绘
            requestAnimationFrame(() => {
                textCells.forEach((cell, index) => {
                    // 分批次处理，每批次100个元素
                    if (index % 100 === 0) {
                        requestAnimationFrame(() => {
                            // 检查单元格内容是否超出高度
                            if (cell.scrollHeight > 151) {
                                cell.classList.add('text-clamp');
                            }
                        });
                    } else {
                        // 直接处理，不使用requestAnimationFrame
                        if (cell.scrollHeight > 151) {
                            cell.classList.add('text-clamp');
                        }
                    }
                });
            });
        }, { timeout: 2000 });
    } else {
        // 降级处理，使用setTimeout延迟执行
        setTimeout(() => {
            const textCells = document.querySelectorAll('.storyboard-table tbody td:not(:nth-child(1)):not(:nth-child(2)):not(:nth-child(3)):not(:nth-child(4)):not(:nth-child(5)):not(:nth-child(6))');
            
            textCells.forEach(cell => {
                if (cell.scrollHeight > 151) {
                    cell.classList.add('text-clamp');
                }
            });
        }, 500);
    }
}
