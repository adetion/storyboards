// 移除API密钥传递，API密钥不再从前端传递
// 保存任务到历史记录

function saveTaskToHistory(taskId, script, status) {
    // 使用包含用户ID的键名，确保本地任务与用户关联
    const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
    let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];

    const taskData = {
        id: taskId,
        script: script,
        status: status,
        created: new Date().toISOString()
    };

    // 检查任务是否已存在
    const existingTaskIndex = tasks.findIndex(task => task.id === taskId);

    if (existingTaskIndex >= 0) {
        tasks[existingTaskIndex] = taskData;
    } else {
        tasks.push(taskData);
    }

    localStorage.setItem(localStorageKey, JSON.stringify(tasks));
}

// 如果从数据库获取到了当前任务号，将其保存到本地历史记录
if (window.dbTaskId) {
    // 使用空字符串作为script参数，因为我们没有实际的脚本内容
    saveTaskToHistory(window.dbTaskId, 'scriptAnalysisTasks', 'completed');
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

    // 点击遮罩层关闭
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                closeGenerateModal();
            }
        });
    }

    // ESC键关闭
    document.addEventListener('keydown', function (e) {
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
const modalStylePresets = [{
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

// 比例预设数据
const modalRatioPresets = [{
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
    id: '21:9',
    name: '超宽屏 21:9',
    label: '21:9',
    icon: 'fas fa-film'
}
];

// 初始化风格预设
function initModalStylePresets() {
    const container = document.getElementById('modalStylePresets');
    if (!container) return;

    container.innerHTML = modalStylePresets.map(preset => `
        <button type="button" class="preset-btn ${preset.id === '12' ? 'active' : ''}" 
                data-style="${preset.id}" data-name="${preset.name}">
            <i class="${preset.icon}"></i>
            <span>${preset.label}</span>
        </button>
    `).join('');

    // 添加点击事件
    container.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const styleId = this.getAttribute('data-style');
            const styleName = this.getAttribute('data-name');

            // 更新选中状态
            container.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // 更新隐藏字段和显示
            document.getElementById('modalStyle').value = styleId;
            document.getElementById('modalCurrentStyle').textContent = styleName;
        });
    });
}

// 初始化比例预设
function initModalRatioPresets() {
    const container = document.getElementById('modalRatioPresets');
    if (!container) return;

    container.innerHTML = modalRatioPresets.map(preset => `
        <button type="button" class="preset-btn ${preset.id === '16:9' ? 'active' : ''}" 
                data-ratio="${preset.id}" data-name="${preset.name}">
            <i class="${preset.icon}"></i>
            <span>${preset.label}</span>
        </button>
    `).join('');

    // 添加点击事件
    container.querySelectorAll('.preset-btn').forEach(btn => {
        btn.addEventListener('click', function () {
            const ratioId = this.getAttribute('data-ratio');
            const ratioName = this.getAttribute('data-name');

            // 更新选中状态
            container.querySelectorAll('.preset-btn').forEach(b => b.classList.remove('active'));
            this.classList.add('active');

            // 更新隐藏字段和显示
            document.getElementById('modalPicSize').value = ratioId;
            document.getElementById('modalCurrentRatio').textContent = ratioName;
        });
    });
}

// 打开生成参考图模态框
function openGenerateModal() {
    const modal = document.getElementById('generateModal');
    if (modal) {
        modal.classList.add('active');
    }
}

// 关闭生成参考图模态框
function closeGenerateModal() {
    const modal = document.getElementById('generateModal');
    if (modal) {
        modal.classList.remove('active');
    }
}

// 处理生成图片提交
async function handleGenerateImageSubmit(e) {
    e.preventDefault();

    const style = document.getElementById('modalStyle').value;
    const picSize = document.getElementById('modalPicSize').value;
    const prompt = document.getElementById('promptInput').value;

    if (!prompt.trim()) {
        alert('请输入提示词');
        return;
    }

    const generateBtn = document.getElementById('modalGenerateBtn');
    generateBtn.disabled = true;
    generateBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 生成中...';

    try {
        const response = await fetch('storyboard_api.php?action=generate_reference_image', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `style=${style}&pic_size=${picSize}&prompt=${encodeURIComponent(prompt)}`,
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            alert('生成成功！');
            closeGenerateModal();
            // 刷新页面或更新显示
            location.reload();
        } else {
            alert(data.message || '生成失败，请重试');
        }
    } catch (error) {
        console.error('生成失败:', error);
        alert('生成失败，请重试');
    } finally {
        generateBtn.disabled = false;
        generateBtn.innerHTML = '<i class="fas fa-magic"></i> 生成图片';
    }
}

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

// 重新观察图片（用于动态添加的图片）
function observeLazyImages() {
    if (imageLazyLoader) {
        imageLazyLoader.observeImages();
    }
}

// 页面加载时初始化
document.addEventListener('DOMContentLoaded', function () {
    // 初始化图片懒加载
    imageLazyLoader = new ImageLazyLoader();
    
    // 初始化生成参考图模态框
    initGenerateModal();

    // 生成参考图按钮
    const generateBtn = document.getElementById('generateReferenceImage');
    if (generateBtn) {
        generateBtn.addEventListener('click', openGenerateModal);
    }

    // 重新生成按钮
    const regenerateBtn = document.getElementById('regenerateReferenceImage');
    if (regenerateBtn) {
        regenerateBtn.addEventListener('click', openGenerateModal);
    }
});
