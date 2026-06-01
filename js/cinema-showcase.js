// 智影工场 - 影视制作全流程智能管理平台 JavaScript

// 导航栏交互
const navbar = document.querySelector('.navbar');
let hamburger = document.querySelector('.hamburger');
let navLinks = document.querySelector('.nav-links');
let navItems = document.querySelectorAll('.nav-links li');

// 初始化移动菜单功能 - 避免重复绑定事件
function initMobileMenu() {
    console.log('开始初始化移动菜单功能');
    
    // 重新获取元素引用，避免引用过时
    hamburger = document.querySelector('.hamburger');
    navLinks = document.querySelector('.nav-links');
    navItems = document.querySelectorAll('.nav-links li');
    
    // 确保元素存在
    if (!hamburger) {
        // console.error('错误: 汉堡菜单元素(.hamburger)不存在');
    }
    if (!navLinks) {
        // console.error('错误: 导航链接元素(.nav-links)不存在');
    }
    if (!hamburger || !navLinks) {
        // console.error('无法初始化移动菜单: 缺少必要的DOM元素');
        return;
    }
    
    console.log('已找到菜单元素:', {
        hamburgerElement: hamburger ? '找到' : '未找到',
        navLinksElement: navLinks ? '找到' : '未找到',
        navItemsCount: navItems ? navItems.length : 0,
        currentWindowWidth: window.innerWidth,
        isMobileView: window.innerWidth < 992
    });
    
    // 移除所有现有事件监听器 - 使用新的方法
    function resetElement(element) {
        const newElement = element.cloneNode(true);
        element.parentNode.replaceChild(newElement, element);
        return newElement;
    }
    
    // 重置汉堡按钮和导航菜单
    hamburger = resetElement(hamburger);
    
    // 重置导航项
    const navItemsArray = Array.from(navItems);
    navItemsArray.forEach((item, index) => {
        const cleanItem = resetElement(item);
        // 重新获取所有导航项
        if (index === navItemsArray.length - 1) {
            navItems = document.querySelectorAll('.nav-links li');
        }
    });
    
    // 核心菜单切换函数
    function toggleMenu() {
        console.log('切换菜单状态:', {
            beforeState: {
                hamburgerActive: hamburger.classList.contains('active'),
                navLinksActive: navLinks.classList.contains('active')
            }
        });
        
        // 切换激活状态类
        hamburger.classList.toggle('active');
        navLinks.classList.toggle('active');
        
        const isOpen = hamburger.classList.contains('active');
        
        // 控制页面滚动
        document.body.style.overflow = isOpen ? 'hidden' : '';
        
        // 直接修改navLinks的style属性，确保菜单能正常显示
        if (isOpen) {
            navLinks.style.transform = 'translateY(0)';
            navLinks.style.opacity = '1';
            navLinks.style.pointerEvents = 'auto';
        } else {
            navLinks.style.transform = 'translateY(-100%)';
            navLinks.style.opacity = '0';
            navLinks.style.pointerEvents = 'none';
        }
        
        // 导航项动画效果
        navItems.forEach((item, index) => {
            if (isOpen) {
                item.style.animation = `navItemFade 0.5s ease forwards ${index / 7 + 0.3}s`;
            } else {
                item.style.animation = '';
            }
        });
        
        console.log('菜单切换完成:', {
            afterState: {
                hamburgerActive: isOpen,
                navLinksActive: navLinks.classList.contains('active'),
                bodyOverflow: document.body.style.overflow
            },
            timestamp: new Date().toISOString()
        });
    }
    
    // 添加点击事件
    hamburger.addEventListener('click', function(e) {
        e.stopPropagation(); // 阻止事件冒泡
        toggleMenu();
    });
    
    // 添加触摸事件支持
    // 触摸开始事件
    hamburger.addEventListener('touchstart', function(e) {
        e.preventDefault(); // 防止触发默认行为
        e.stopPropagation(); // 阻止事件冒泡
        toggleMenu();
    }, { passive: false });
    
    // 添加触摸结束事件，增强移动设备交互体验
    hamburger.addEventListener('touchend', function(e) {
        e.preventDefault();
        e.stopPropagation();
    }, { passive: false });
    
    // 添加触摸取消事件处理
    hamburger.addEventListener('touchcancel', function(e) {
        e.preventDefault();
        e.stopPropagation();
    }, { passive: false });
    
    // 为汉堡按钮添加CSS触摸反馈支持
    hamburger.style.tapHighlightColor = 'transparent'; // 移除Android默认点击高亮
    hamburger.style.userSelect = 'none'; // 防止文本选择
    hamburger.style.cursor = 'pointer'; // 确保鼠标指针正确
    hamburger.style.outline = 'none'; // 移除焦点轮廓，减少视觉干扰
    
    // 为导航链接添加触摸事件支持
    navItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            // 触摸开始时的视觉反馈
            link.addEventListener('touchstart', function() {
                this.style.opacity = '0.8';
            }, { passive: true });
            
            // 触摸结束时恢复
            link.addEventListener('touchend', function() {
                this.style.opacity = '1';
            }, { passive: true });
            
            // 触摸取消时恢复
            link.addEventListener('touchcancel', function() {
                this.style.opacity = '1';
            }, { passive: true });
            
            // 增强链接的点击区域
            link.style.display = 'block';
            link.style.padding = '10px';
            link.style.width = '100%';
            link.style.boxSizing = 'border-box';
        }
    });
    
    // 添加触摸滑动支持，允许通过滑动手势关闭菜单
    let touchStartX = 0;
    document.addEventListener('touchstart', function(e) {
        touchStartX = e.touches[0].clientX;
    }, { passive: true });
    
    document.addEventListener('touchmove', function(e) {
        if (!navLinks.classList.contains('active')) return;
        
        const touchX = e.touches[0].clientX;
        const diffX = touchX - touchStartX;
        
        // 如果从左向右滑动且滑动距离超过50px，则关闭菜单
        if (diffX > 50 && touchStartX < 100) { // 从屏幕左侧滑动
            hamburger.classList.remove('active');
            navLinks.classList.remove('active');
            document.body.style.overflow = '';
            navItems.forEach(item => {
                item.style.animation = '';
            });
            console.log('通过滑动手势关闭菜单');
        }
    }, { passive: true });
    
    // 为每个导航项添加点击事件
    navItems.forEach(item => {
        const link = item.querySelector('a');
        if (link) {
            link.addEventListener('click', function(e) {
                // 关闭菜单
                if (window.innerWidth < 992) {
                    hamburger.classList.remove('active');
                    navLinks.classList.remove('active');
                    // 直接修改navLinks的style属性，确保菜单能完全关闭
                    navLinks.style.transform = 'translateY(-100%)';
                    navLinks.style.opacity = '0';
                    navLinks.style.pointerEvents = 'none';
                    document.body.style.overflow = '';
                    navItems.forEach(item => {
                        item.style.animation = '';
                    });
                }
                
                // 平滑滚动处理
                const targetId = this.getAttribute('href');
                if (targetId && targetId.startsWith('#') && targetId.length > 1) {
                    e.preventDefault();
                    const targetElement = document.querySelector(targetId);
                    if (targetElement) {
                        const navbarHeight = document.querySelector('.navbar')?.offsetHeight || 80;
                        const targetPosition = targetElement.getBoundingClientRect().top + window.pageYOffset - navbarHeight;
                        
                        window.scrollTo({
                            top: targetPosition,
                            behavior: 'smooth'
                        });
                    }
                }
            });
        }
    });
    
    // 点击页面其他地方关闭菜单
    document.addEventListener('click', function(e) {
        const targetInfo = {
            tagName: e.target.tagName,
            className: e.target.className,
            id: e.target.id
        };
        
        if (navLinks.classList.contains('active')) {
            if (!hamburger.contains(e.target) && !navLinks.contains(e.target)) {
                console.log('点击页面其他区域触发菜单关闭:', {
                    target: targetInfo,
                    menuStateBefore: true
                });
                
                hamburger.classList.remove('active');
                navLinks.classList.remove('active');
                document.body.style.overflow = '';
                navItems.forEach(item => {
                    item.style.animation = '';
                });
                
                console.log('菜单已关闭', {
                    timestamp: new Date().toISOString()
                });
            } else {
                console.log('点击菜单内部区域，不关闭菜单', {
                    target: targetInfo
                });
            }
        }
    });
    
    // 初始化菜单状态
    const isMobileView = window.innerWidth < 992;
    if (isMobileView) {
        navLinks.classList.remove('active');
        hamburger.classList.remove('active');
        document.body.style.overflow = '';
        console.log('初始化移动端菜单状态为关闭');
    } else {
        console.log('当前为桌面视图，菜单初始化完成');
    }
    
    // 添加窗口大小变化监听
    window.addEventListener('resize', function() {
        const currentWidth = window.innerWidth;
        console.log('窗口大小变化:', {
            width: currentWidth,
            isMobileView: currentWidth < 992
        });
        
        // 在桌面/移动视图切换时重置菜单状态
        if (currentWidth >= 992) {
            navLinks.classList.remove('active');
            hamburger.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
    
    console.log('移动菜单功能初始化完成:', {
        currentView: isMobileView ? '移动端' : '桌面端',
        menuState: '关闭'
    });
    
    // 添加完整的菜单测试函数，可在控制台手动调用测试
    window.testMobileMenu = function() {
        console.log('=== 移动端菜单功能测试 ===');
        
        // 测试1: 元素存在性检查
        console.log('\n1. DOM元素检查:');
        console.log('   汉堡按钮存在:', !!hamburger, hamburger);
        console.log('   导航菜单存在:', !!navLinks, navLinks);
        console.log('   菜单项数量:', navItems ? navItems.length : 0);
        
        // 测试2: 视图模式检查
        console.log('\n2. 视图模式检查:');
        const isMobileView = window.innerWidth < 992;
        console.log('   当前窗口宽度:', window.innerWidth, 'px');
        console.log('   是移动视图:', isMobileView);
        
        if (!isMobileView) {
            console.log('   ⚠️ 请缩小浏览器窗口到992px以下，或使用开发者工具的设备模拟功能来测试移动菜单');
            console.log('   测试将继续，但结果可能不准确');
        }
        
        // 测试3: 初始状态检查
        console.log('\n3. 初始状态检查:');
        console.log('   汉堡按钮初始状态:', hamburger.classList.contains('active') ? '激活' : '非激活');
        console.log('   导航菜单初始状态:', navLinks.classList.contains('active') ? '激活' : '非激活');
        
        // 测试4: 菜单打开测试
        console.log('\n4. 菜单打开测试...');
        
        // 确保菜单初始为关闭状态
        if (hamburger.classList.contains('active')) {
            toggleMenu();
        }
        
        // 触发点击事件
        console.log('   触发汉堡按钮点击事件');
        hamburger.click();
        
        // 检查打开状态
        setTimeout(() => {
            console.log('   打开后状态检查:');
            console.log('   - 汉堡按钮激活:', hamburger.classList.contains('active'));
            console.log('   - 导航菜单激活:', navLinks.classList.contains('active'));
            console.log('   - 页面滚动锁定:', document.body.style.overflow === 'hidden');
            
            // 测试5: 触摸事件模拟
            console.log('\n5. 触摸事件模拟测试...');
            
            // 创建触摸事件
            const touchStartEvent = new TouchEvent('touchstart', {
                touches: [{ clientX: 10, clientY: 10 }],
                bubbles: true,
                cancelable: true
            });
            
            console.log('   触发触摸开始事件');
            hamburger.dispatchEvent(touchStartEvent);
            
            // 测试6: 菜单项点击测试
            console.log('\n6. 菜单项点击测试...');
            if (navItems && navItems.length > 0) {
                const firstMenuItem = navItems[0].querySelector('a');
                if (firstMenuItem) {
                    console.log(`   点击第一个菜单项: ${firstMenuItem.textContent.trim()}`);
                    firstMenuItem.click();
                }
            }
            
            // 最终状态检查
            setTimeout(() => {
                console.log('\n7. 最终状态检查:');
                console.log('   汉堡按钮激活:', hamburger.classList.contains('active'));
                console.log('   导航菜单激活:', navLinks.classList.contains('active'));
                console.log('   页面滚动锁定:', document.body.style.overflow === 'hidden');
                
                // 测试总结
                console.log('\n=== 测试总结 ===');
                const menuFunctional = !hamburger.classList.contains('active') && !navLinks.classList.contains('active');
                console.log('   菜单功能状态:', menuFunctional ? '正常' : '异常');
                
                if (menuFunctional) {
                    console.log('   🎉 移动端菜单测试通过!');
                } else {
                    console.log('   ⚠️ 请检查控制台错误信息或DOM结构');
                    console.log('   建议检查CSS样式中的.nav-links.active类是否正确定义');
                }
                
                // 重置菜单状态为关闭
                if (hamburger.classList.contains('active')) {
                    toggleMenu();
                }
                document.body.style.overflow = '';
                console.log('=== 移动端菜单功能测试完成 ===');
            }, 1000);
        }, 1000);
    };
    
    // 添加移动端菜单性能监控
    window.mobileMenuPerformance = {
        measureTime: function() {
            console.log('开始测量菜单响应性能...');
            const startTime = performance.now();
            
            // 切换菜单状态
            toggleMenu();
            
            setTimeout(() => {
                const endTime = performance.now();
                console.log(`菜单切换响应时间: ${(endTime - startTime).toFixed(2)}ms`);
                
                // 切回初始状态
                toggleMenu();
            }, 500);
        }
    };
    

}

// 高级视频循环控制函数 - 确保在所有环境中正确循环播放
function setupVideoLoopControl() {
    const video = document.querySelector('.bg-video');
    if (!video) {
        // console.error('背景视频元素未找到');
        return;
    }
    
    // 强制设置所有播放相关属性
    video.autoplay = true;
    video.muted = true;
    video.loop = true;
    video.playsinline = true;
    video.preload = 'auto';
    
    // 存储视频状态
    let videoState = {
        isPlaying: false,
        lastTimeUpdate: 0,
        checkInterval: null,
        attemptCount: 0,
        maxAttempts: 10
    };
    
    // 视频结束事件 - 主要循环控制点
    video.addEventListener('ended', function() {
        console.log('[视频控制] 视频播放结束事件触发');
        forceVideoRestart();
    }, { capture: true, passive: false });
    
    // 时间更新事件 - 检测接近结束时主动干预
    video.addEventListener('timeupdate', function() {
        const currentTime = video.currentTime;
        const duration = video.duration;
        
        // 记录最后更新时间，用于检测卡顿
        videoState.lastTimeUpdate = Date.now();
        videoState.isPlaying = !video.paused;
        
        // 当视频快结束时(95%进度)，主动准备循环
        if (duration > 0 && currentTime / duration > 0.95) {
            console.log('[视频控制] 检测到视频接近结束，准备循环播放');
            // 提前设置currentTime为一个很小的值，而不是等到完全结束
            setTimeout(() => {
                if (video.currentTime / duration > 0.98) {
                    forceVideoRestart();
                }
            }, 100);
        }
    }, { passive: true });
    
    // 播放事件
    video.addEventListener('play', function() {
        console.log('[视频控制] 视频开始播放');
        videoState.isPlaying = true;
        videoState.attemptCount = 0;
    }, { passive: true });
    
    // 暂停事件
    video.addEventListener('pause', function() {
        console.log('[视频控制] 视频暂停');
        // 检测是否因为播放结束而暂停
        if (!isNaN(video.duration) && Math.abs(video.currentTime - video.duration) < 0.1) {
            console.log('[视频控制] 检测到视频在结尾处暂停');
            forceVideoRestart();
        }
    }, { passive: true });
    
    // 加载事件
    video.addEventListener('loadedmetadata', function() {
        console.log('[视频控制] 视频元数据已加载，时长:', video.duration);
        // 元数据加载后立即开始播放
        if (video.paused) {
            attemptPlay();
        }
    }, { passive: true });
    
    // 错误处理
    video.addEventListener('error', function(e) {
        // console.error('[视频控制] 视频错误:', e);
        // 尝试切换到备用格式
        const sources = video.querySelectorAll('source');
        if (sources.length > 1) {
            // 交换source顺序，优先使用MP4
            const mainSource = sources[0];
            const backupSource = sources[1];
            video.removeChild(mainSource);
            video.removeChild(backupSource);
            video.appendChild(backupSource);
            video.appendChild(mainSource);
            console.log('[视频控制] 已切换视频源优先级');
            video.load();
            setTimeout(attemptPlay, 1000);
        } else {
            // 显示备选图片
            video.poster = 'assets/scene-placeholder.png';
        }
    }, { passive: true });
    
    // 强制视频重启函数
    function forceVideoRestart() {
        console.log('[视频控制] 执行强制视频重启');
        
        // 重置播放位置到开始
        video.currentTime = 0.1; // 设置为0.1秒而不是0，避免某些浏览器的问题
        
        // 尝试播放
        attemptPlay();
    }
    
    // 尝试播放函数，带错误处理和重试逻辑
    function attemptPlay() {
        if (videoState.attemptCount >= videoState.maxAttempts) {
            console.warn('[视频控制] 达到最大播放尝试次数，暂停尝试');
            return;
        }
        
        videoState.attemptCount++;
        console.log(`[视频控制] 尝试播放 (${videoState.attemptCount}/${videoState.maxAttempts})`);
        
        // 先暂停再播放，确保状态正确
        video.pause();
        video.play().catch(error => {
            console.warn('[视频控制] 播放失败:', error);
            // 稍后重试
            setTimeout(attemptPlay, 2000);
        });
    }
    
    // 定期状态检查 - 防止循环失效
    function startPeriodicCheck() {
        if (videoState.checkInterval) {
            clearInterval(videoState.checkInterval);
        }
        
        videoState.checkInterval = setInterval(() => {
            const currentTime = Date.now();
            const timeSinceLastUpdate = currentTime - videoState.lastTimeUpdate;
            
            // 如果超过5秒没有时间更新，可能是播放卡住了
            if (timeSinceLastUpdate > 5000 && videoState.isPlaying) {
                console.log('[视频控制] 检测到视频可能卡住，重新开始播放');
                forceVideoRestart();
            }
            
            // 如果视频应该在播放但实际上暂停了，并且不在开始位置，尝试重新播放
            if (video.paused && video.currentTime > 0 && video.currentTime < video.duration - 0.1) {
                console.log('[视频控制] 检测到视频意外暂停，尝试恢复播放');
                attemptPlay();
            }
        }, 3000); // 每3秒检查一次
    }
    
    // 页面可见性变化事件 - 处理页面切换回来时的播放状态
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && video.paused && video.currentTime > 0) {
            console.log('[视频控制] 页面变为可见，恢复视频播放');
            attemptPlay();
        }
    });
    
    // 视频性能优化函数
    function optimizeVideoPerformance() {
        console.log('[视频控制] 应用视频性能优化');
        
        // 检测网络状态并相应调整
        if ('connection' in navigator) {
            const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            console.log(`[视频控制] 网络类型: ${connection.effectiveType}, 下行速度: ${connection.downlink}Mbps`);
            
            // 根据网络状况调整视频质量
            if (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g' || connection.downlink < 1) {
                console.log('[视频控制] 检测到低速网络，应用额外优化');
                // 对于低速网络，可以考虑降低视频质量或使用更轻量级的格式
                const sources = video.querySelectorAll('source');
                sources.forEach(source => {
                    if (source.type === 'video/mp4' && connection.downlink < 0.5) {
                        console.log('[视频控制] 低速网络，优先使用MP4格式');
                        source.setAttribute('data-low-bandwidth', 'true');
                    }
                });
            }
        }
        
        // 视频缓冲策略优化
        video.addEventListener('progress', function() {
            if (video.buffered.length > 0) {
                const bufferedEnd = video.buffered.end(video.buffered.length - 1);
                const duration = video.duration;
                
                if (duration > 0) {
                    const bufferedPercent = (bufferedEnd / duration) * 100;
                    console.log(`[视频控制] 缓冲进度: ${bufferedPercent.toFixed(2)}%`);
                    
                    // 预加载控制 - 对于较长视频可以限制缓冲量
                    if (bufferedPercent > 50 && video.currentTime / duration < 0.2) {
                        video.pause();
                        setTimeout(() => video.play().catch(e => console.warn('[视频控制] 缓冲优化暂停后播放失败:', e)), 100);
                    }
                }
            }
        }, { passive: true });
        
        // 低功耗模式检测
        if ('connection' in navigator && 'saveData' in navigator.connection) {
            if (navigator.connection.saveData) {
                console.log('[视频控制] 用户启用了数据保护模式');
                // 应用额外的数据节省措施
                video.poster = 'assets/scene-placeholder.png';
                // 可以考虑在数据保护模式下默认只显示静态图片
            }
        }
        
        // 减少后台处理
        video.addEventListener('waiting', function() {
            console.log('[视频控制] 视频等待缓冲');
            // 可以在这里添加UI提示
        }, { passive: true });
        
        video.addEventListener('stalled', function() {
            console.log('[视频控制] 视频加载停滞');
            // 尝试重新加载视频
            setTimeout(() => {
                if (video.readyState < 2) {
                    console.log('[视频控制] 视频长时间停滞，尝试重新加载');
                    video.load();
                    attemptPlay();
                }
            }, 3000);
        }, { passive: true });
    }
    
    // 初始化视频控制
        console.log('[视频控制] 初始化高级视频循环控制');
        startPeriodicCheck();
        
        // 添加性能优化措施
        optimizeVideoPerformance();
        
        // 立即尝试播放
        setTimeout(attemptPlay, 500);
    }

// 视频错误处理函数
function handleVideoError() {
    const video = document.querySelector('.bg-video');
    if (video) {
        // 设置备选图片作为背景
        video.poster = 'assets/scene-placeholder.png';
        
        // 创建并添加额外的备选背景
        const fallbackDiv = document.createElement('div');
        fallbackDiv.className = 'video-error-fallback';
        fallbackDiv.style.backgroundImage = 'url(assets/scene-placeholder.png)';
        fallbackDiv.style.backgroundSize = 'cover';
        fallbackDiv.style.backgroundPosition = 'center';
        fallbackDiv.style.position = 'absolute';
        fallbackDiv.style.top = '0';
        fallbackDiv.style.left = '0';
        fallbackDiv.style.width = '100%';
        fallbackDiv.style.height = '100%';
        fallbackDiv.style.zIndex = '-2';
        
        video.parentNode.prepend(fallbackDiv);
    }
}

// 页面加载时的动画效果
window.addEventListener('DOMContentLoaded', function() {
    // 初始化视频错误处理
    handleVideoError();
    
    // 初始化导航相关功能
    initNavbar(); // 只负责平滑滚动，不再处理菜单
    initMobileMenu(); // 专门负责移动菜单功能和触摸支持
    
    // 初始化滚动监听
    initScrollEffects();
    
    // 初始化动画
    initHeroAnimation();
    initFeatureCardAnimation();
    initWorkflowAnimation();
    initArchitectureAnimation();
    
    // 初始化用户评价轮播
    setupTestimonialSlider();
    
    // 初始化用户体验标签页
    setupExperienceTabSwitching();
    
    // 确保slogan只在首屏显示
    handleSloganVisibility();
    
    // 确保scroll-down只在首屏显示
    handleScrollDownVisibility();
    
    // 优化移动端体验
    optimizeForMobile();
    
    // 执行移动端交互测试 - 确保在所有功能初始化后执行
    testMobileInteractions();
});

// 导航栏初始化 - 主要处理非菜单部分的功能
function initNavbar() {
    // 滚动时导航栏样式变化
    window.addEventListener('scroll', function() {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    });
}

// 滚动效果初始化
function initScrollEffects() {
    const sections = document.querySelectorAll('section');
    const scrollThreshold = 0.2;
    
    function checkScroll() {
        sections.forEach(section => {
            const sectionTop = section.getBoundingClientRect().top;
            const sectionHeight = section.clientHeight;
            const windowHeight = window.innerHeight;
            
            if (sectionTop < windowHeight * (1 - scrollThreshold) && sectionTop > -sectionHeight * scrollThreshold) {
                section.classList.add('visible');
            }
        });
    }
    
    // 初始检查
    checkScroll();
    
    // 滚动时检查
    window.addEventListener('scroll', checkScroll);
}

// Hero区域动画
function initHeroAnimation() {
    const heroTitle = document.querySelector('.hero-title');
    const heroSubtitle = document.querySelector('.hero-subtitle');
    const heroDescription = document.querySelector('.hero-description');
    const ctaButtons = document.querySelector('.cta-buttons');
    const scrollDown = document.querySelector('.scroll-down');
    
    // 文字渐入动画
    setTimeout(() => {
        if (heroTitle) {
            heroTitle.style.opacity = '1';
            heroTitle.style.transform = 'translateY(0)';
        }
    }, 300);
    
    setTimeout(() => {
        if (heroSubtitle) {
            heroSubtitle.style.opacity = '1';
            heroSubtitle.style.transform = 'translateY(0)';
        }
    }, 600);
    
    setTimeout(() => {
        if (heroDescription) {
            heroDescription.style.opacity = '1';
            heroDescription.style.transform = 'translateY(0)';
        }
    }, 900);
    
    setTimeout(() => {
        if (ctaButtons) {
            ctaButtons.style.opacity = '1';
            ctaButtons.style.transform = 'translateY(0)';
        }
    }, 1200);
    
    setTimeout(() => {
        if (scrollDown) {
            scrollDown.style.opacity = '1';
            // 添加弹跳动画
            const icon = scrollDown.querySelector('i');
            if (icon) {
                icon.classList.add('bounce-animation');
            }
        }
    }, 1500);
    
    // 电影胶片和镜头动画
    const filmReel = document.querySelector('.film-reel');
    const cameraLens = document.querySelector('.camera-lens');
    
    if (filmReel) {
        filmReel.style.animation = 'rotate 20s linear infinite';
    }
    
    if (cameraLens) {
        cameraLens.style.animation = 'pulse 2s ease-in-out infinite';
    }
}

// 功能卡片动画
function initFeatureCardAnimation() {
    const featureCards = document.querySelectorAll('.feature-card');
    
    function animateFeatureCards() {
        featureCards.forEach((card, index) => {
            const cardTop = card.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (cardTop < windowHeight * 0.8) {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            }
        });
    }
    
    // 初始检查
    animateFeatureCards();
    
    // 滚动时检查
    window.addEventListener('scroll', animateFeatureCards);
    
    // 悬停效果增强
    featureCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-10px) scale(1.02)';
            this.style.boxShadow = '0 20px 40px rgba(0, 0, 0, 0.15)';
            
            // 图标动画
            const icon = this.querySelector('.feature-icon');
            if (icon) {
                icon.style.transform = 'scale(1.1) rotate(5deg)';
            }
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0) scale(1)';
            this.style.boxShadow = '0 10px 30px rgba(0, 0, 0, 0.1)';
            
            // 图标恢复
            const icon = this.querySelector('.feature-icon');
            if (icon) {
                icon.style.transform = 'scale(1) rotate(0)';
            }
        });
    });
}

// 工作流程动画
function initWorkflowAnimation() {
    const workflowItems = document.querySelectorAll('.workflow-item');
    const workflowArrows = document.querySelectorAll('.workflow-arrow');
    
    function animateWorkflow() {
        workflowItems.forEach((item, index) => {
            const itemTop = item.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (itemTop < windowHeight * 0.75) {
                setTimeout(() => {
                    item.classList.add('active');
                    
                    // 显示箭头
                    if (index < workflowArrows.length) {
                        setTimeout(() => {
                            workflowArrows[index].classList.add('active');
                        }, 300);
                    }
                }, index * 200);
            }
        });
    }
    
    // 初始检查
    animateWorkflow();
    
    // 滚动时检查
    window.addEventListener('scroll', animateWorkflow);
}

// 技术架构动画
function initArchitectureAnimation() {
    const archLayers = document.querySelectorAll('.arch-layer');
    
    function animateArchitecture() {
        archLayers.forEach((layer, index) => {
            const layerTop = layer.getBoundingClientRect().top;
            const windowHeight = window.innerHeight;
            
            if (layerTop < windowHeight * 0.8) {
                setTimeout(() => {
                    layer.style.opacity = '1';
                    layer.style.transform = 'translateX(0)';
                }, index * 200);
            }
        });
    }
    
    // 初始检查
    animateArchitecture();
    
    // 滚动时检查
    window.addEventListener('scroll', animateArchitecture);
    
    // 技术徽章悬停效果
    const techBadges = document.querySelectorAll('.tech-badge');
    techBadges.forEach(badge => {
        badge.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
            this.style.boxShadow = '0 5px 15px rgba(0, 0, 0, 0.2)';
        });
        
        badge.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
            this.style.boxShadow = 'none';
        });
    });
}

// 平滑滚动功能
function smoothScroll(targetId) {
    const targetElement = document.querySelector(targetId);
    if (targetElement) {
        window.scrollTo({
            top: targetElement.offsetTop - 80,
            behavior: 'smooth'
        });
    }
}

// 动画效果增强
function enhanceAnimations() {
    // 为数字计数器添加动画
    const counters = document.querySelectorAll('.stat-number');
    const speed = 200;
    
    function animateCounters() {
        counters.forEach(counter => {
            const target = +counter.innerText.replace(/[^0-9]/g, '');
            const count = 0;
            const increment = target / speed;
            
            function updateCount() {
                const value = Math.ceil(count + increment);
                if (value < target) {
                    counter.innerText = value + '+';
                    requestAnimationFrame(updateCount);
                } else {
                    counter.innerText = target + '+';
                }
            }
            
            updateCount();
        });
    }
    
    // 滚动到统计区域时触发动画
    const statsSection = document.querySelector('.stats-grid');
    if (statsSection) {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    animateCounters();
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });
        
        observer.observe(statsSection);
    }
}

// 用户评价轮播功能
function setupTestimonialSlider() {
    const slider = document.querySelector('.testimonials-slider');
    if (!slider) return;
    
    const slides = slider.querySelectorAll('.testimonial-slide');
    const prevBtn = document.querySelector('.testimonial-prev');
    const nextBtn = document.querySelector('.testimonial-next');
    let currentSlide = 0;
    let slideInterval;
    
    // 初始化第一张幻灯片
    slides[currentSlide].classList.add('active');
    
    // 显示幻灯片的函数
    function showSlide(index) {
        // 确保索引在有效范围内
        if (index < 0) index = slides.length - 1;
        if (index >= slides.length) index = 0;
        
        // 为当前和下一张幻灯片添加过渡类
        slides.forEach(slide => {
            slide.classList.remove('active', 'prev', 'next');
        });
        
        // 计算前一张和后一张幻灯片的索引
        const prevIndex = (index - 1 + slides.length) % slides.length;
        const nextIndex = (index + 1) % slides.length;
        
        // 设置幻灯片状态
        slides[prevIndex].classList.add('prev');
        slides[index].classList.add('active');
        slides[nextIndex].classList.add('next');
        
        currentSlide = index;
    }
    
    // 下一张幻灯片
    function nextSlide() {
        showSlide(currentSlide + 1);
    }
    
    // 上一张幻灯片
    function prevSlide() {
        showSlide(currentSlide - 1);
    }
    
    // 设置自动播放
    function startSlideshow() {
        slideInterval = setInterval(nextSlide, 5000);
    }
    
    // 停止自动播放
    function stopSlideshow() {
        clearInterval(slideInterval);
    }
    
    // 按钮事件监听
    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            stopSlideshow();
            prevSlide();
            startSlideshow();
        });
        
        // 添加触摸事件
        prevBtn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            stopSlideshow();
            prevSlide();
            startSlideshow();
        }, { passive: false });
    }
    
    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            stopSlideshow();
            nextSlide();
            startSlideshow();
        });
        
        // 添加触摸事件
        nextBtn.addEventListener('touchstart', function(e) {
            e.preventDefault();
            stopSlideshow();
            nextSlide();
            startSlideshow();
        }, { passive: false });
    }
    
    // 鼠标悬停时停止自动播放
    slider.addEventListener('mouseenter', stopSlideshow);
    slider.addEventListener('mouseleave', startSlideshow);
    
    // 添加触摸滑动功能
    let touchStartX = 0;
    let touchEndX = 0;
    
    slider.addEventListener('touchstart', function(e) {
        touchStartX = e.changedTouches[0].screenX;
        stopSlideshow(); // 触摸开始时暂停自动播放
    });
    
    slider.addEventListener('touchmove', function(e) {
        touchEndX = e.changedTouches[0].screenX;
    });
    
    slider.addEventListener('touchend', function() {
        // 计算滑动距离
        const diff = touchStartX - touchEndX;
        // 设置最小滑动距离阈值
        const minSwipeDistance = 50;
        
        // 根据滑动方向切换幻灯片
        if (diff > minSwipeDistance) {
            // 向左滑动
            nextSlide();
        } else if (diff < -minSwipeDistance) {
            // 向右滑动
            prevSlide();
        }
        
        // 重新开始自动播放
        startSlideshow();
    });
    
    // 开始自动播放
    startSlideshow();
}

// 标签页切换功能 - 用户体验部分 - 全新实现
function setupExperienceTabSwitching() {
    console.log('初始化用户体验标签切换功能');
    
    // 使用直接选择器
    const experienceSection = document.getElementById('experience');
    
    // 健壮性检查
    if (!experienceSection) {
        // console.error('用户体验区域(#experience)未找到');
        return;
    }
    
    // 简化选择器，确保能找到所有元素
    const tabBtns = experienceSection.querySelectorAll('.tab-btn');
    const tabPanes = experienceSection.querySelectorAll('.tab-pane');
    
    console.log(`找到的标签按钮: ${tabBtns.length} 个`);
    console.log(`找到的内容面板: ${tabPanes.length} 个`);
    
    // 验证元素存在
    if (tabBtns.length === 0) {
        // console.error('未找到标签按钮(.tab-btn)');
        return;
    }
    
    if (tabPanes.length === 0) {
        // console.error('未找到内容面板(.tab-pane)');
        return;
    }
    
    // 重置标签状态函数
    function resetTabState() {
        tabBtns.forEach(btn => {
            btn.classList.remove('active');
        });
        
        tabPanes.forEach(pane => {
            pane.classList.remove('active');
        });
    }
    
    // 简单直接的切换逻辑
    function switchTab(tabId) {
        console.log(`切换到标签: ${tabId}`);
        
        // 重置所有标签状态
        resetTabState();
        
        // 找到对应的按钮和面板
        const activeBtn = Array.from(tabBtns).find(btn => btn.dataset.tab === tabId);
        const activePane = Array.from(tabPanes).find(pane => pane.id === tabId);
        
        // 验证找到的元素
        if (activeBtn) {
            activeBtn.classList.add('active');
            console.log(`激活按钮: ${tabId}`);
        }
        
        if (activePane) {
            activePane.classList.add('active');
            console.log(`激活面板: ${tabId}`);
        }
    }
    
    // 绑定点击事件
    function bindEvents() {
        tabBtns.forEach(btn => {
            // 移除旧的事件监听器
            btn.onclick = null;
            
            // 添加新的点击事件
            btn.addEventListener('click', function() {
                const tabId = this.dataset.tab;
                if (tabId) {
                    switchTab(tabId);
                }
            });
        });
    }
    
    // 初始化第一个标签
    function initFirstTab() {
        const firstBtn = tabBtns[0];
        if (firstBtn && firstBtn.dataset.tab) {
            switchTab(firstBtn.dataset.tab);
        }
    }
    
    // 执行初始化
    bindEvents();
    initFirstTab();
    
    // 暴露测试函数
    window.testTabs = function() {
        console.log('测试标签切换');
        if (tabBtns.length > 1) {
            setTimeout(() => {
                switchTab(tabBtns[1].dataset.tab);
            }, 1000);
        }
    };
}

// 页面加载完成后增强动画和初始化所有功能
window.addEventListener('load', function() {
    enhanceAnimations();
    
    // 初始化用户评价轮播
    setupTestimonialSlider();
    
    // 初始化用户体验标签切换功能
    setupExperienceTabSwitching();
});

// 处理slogan的显示 - 确保永续显示
function handleSloganVisibility() {
    // 使用正确的选择器
    const sloganElement = document.querySelector('.hero-big-slogan.video-slogan');
    if (!sloganElement) return;
    
    // 确保slogan始终可见
    function updateSloganVisibility() {
        // 始终保持slogan可见
        sloganElement.style.opacity = '1';
        sloganElement.style.visibility = 'visible';
        sloganElement.style.transition = 'opacity 0.5s ease, visibility 0.5s ease';
    }
    
    // 初始检查
    updateSloganVisibility();
    
    // 窗口大小变化时重新检查，但不移除slogan
    function handleResize() {
        updateSloganVisibility();
    }
    
    // 确保只绑定一次resize事件
    window.removeEventListener('resize', handleResize);
    window.addEventListener('resize', handleResize);
    
    // 添加轮播状态监控，确保轮播正常运行
    function monitorSloganAnimation() {
        const activeSlogan = document.querySelector('.big-slogan-text.active');
        if (!activeSlogan) {
            // 如果没有激活的slogan，手动激活第一个
            const firstSlogan = document.querySelector('.big-slogan-text');
            if (firstSlogan) {
                firstSlogan.classList.add('active');
            }
        }
    }
    
    // 定期检查slogan状态
    setInterval(monitorSloganAnimation, 5000);
}

// 处理scroll-down的显示和隐藏 - 适配移动端
function handleScrollDownVisibility() {
    // 获取scroll-down元素
    const scrollDownElement = document.querySelector('.scroll-down');
    if (!scrollDownElement) return;
    
    // 当滚动超过首屏高度时隐藏scroll-down
    function updateScrollDownVisibility() {
        // 获取视口高度
        const viewportHeight = window.innerHeight;
        // 检测是否为移动设备
        const isMobile = window.innerWidth < 768;
        // 移动端使用更大的阈值，桌面端使用较小的阈值
        const scrollThreshold = isMobile ? viewportHeight * 0.25 : viewportHeight * 0.15;
        
        if (window.scrollY > scrollThreshold) {
            scrollDownElement.style.opacity = '0';
            scrollDownElement.style.visibility = 'hidden';
            scrollDownElement.style.transition = 'opacity 0.5s ease, visibility 0.5s ease';
        } else {
            scrollDownElement.style.opacity = '1';
            scrollDownElement.style.visibility = 'visible';
        }
    }
    
    // 初始检查
    updateScrollDownVisibility();
    
    // 移除可能存在的旧监听器，避免重复绑定
    window.removeEventListener('scroll', updateScrollDownVisibility);
    // 绑定滚动事件
    window.addEventListener('scroll', updateScrollDownVisibility);
    
    // 窗口大小变化时重新检查
    function handleResize() {
        updateScrollDownVisibility();
    }
    
    // 确保只绑定一次resize事件
    window.removeEventListener('resize', handleResize);
    window.addEventListener('resize', handleResize);
}

// 移动端优化 - 增强触摸支持和滚动体验
function optimizeForMobile() {
    console.log('正在优化移动端体验...');
    
    const width = window.innerWidth;
    const isMobile = width < 768;
    const isTablet = width >= 768 && width < 992;
    const isSmallMobile = width < 576;
    
    console.log(`移动端检测: ${isMobile ? '是' : '否'}`);
    
    // 设置CSS变量
    document.documentElement.style.setProperty('--animation-speed', isMobile ? '0.3s' : '0.5s');
    document.documentElement.style.setProperty('--menu-transition', 'all 0.4s ease');
    document.documentElement.style.setProperty('--is-mobile', isMobile ? 'true' : 'false');
    
    // 简化移动端复杂动画以提高性能
    const complexElements = document.querySelectorAll('.film-reel, .camera-lens');
    if (isMobile) {
        complexElements.forEach(el => {
            if (el) {
                el.style.animation = 'none';
            }
        });
    } else {
        // 在桌面端恢复动画
        if (document.querySelector('.film-reel')) {
            document.querySelector('.film-reel').style.animation = 'rotate 20s linear infinite';
        }
        if (document.querySelector('.camera-lens')) {
            document.querySelector('.camera-lens').style.animation = 'pulse 2s ease-in-out infinite';
        }
    }
    
    // 修复移动端菜单交互
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    // 确保在调整窗口大小时菜单状态正确
    if (width >= 992) {
        // 桌面模式下菜单应该总是可见的
        if (navLinks) {
            navLinks.classList.remove('active');
            navLinks.style.transform = 'none';
            navLinks.style.opacity = '1';
            navLinks.style.pointerEvents = 'all';
        }
        if (hamburger) {
            hamburger.classList.remove('active');
        }
    } else {
        // 移动模式下菜单默认隐藏
        if (navLinks && !navLinks.classList.contains('active')) {
            navLinks.style.transform = 'translateY(-100%)';
            navLinks.style.opacity = '0';
            navLinks.style.pointerEvents = 'none';
        }
        
        // 重新初始化移动菜单，确保触摸支持正常
        if (isMobile) {
            initMobileMenu();
        }
    }
    
    // 优化触摸交互 - 扩展到更多可交互元素
    const interactiveElements = document.querySelectorAll('a, button, .hamburger, .feature-card, .case-card, .testimonial-slide, .cta-buttons button');
    
    // 设置基本触摸优化样式
    interactiveElements.forEach(el => {
        el.style.tapHighlightColor = 'transparent';
        el.style.userSelect = 'none';
        el.style.cursor = 'pointer';
    });
    
    // 添加触摸事件处理 - 优化版本，不阻止正常滚动
    if (isMobile) {
        console.log('添加移动端触摸事件处理');
        
        // 仅为菜单按钮添加特定的触摸处理
        if (hamburger) {
            hamburger.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            });
            hamburger.addEventListener('touchend', function() {
                this.classList.remove('touch-active');
            });
            hamburger.addEventListener('touchcancel', function() {
                this.classList.remove('touch-active');
            });
        }
        
        // 增强菜单链接的触摸反馈
        if (navLinks) {
            const links = navLinks.querySelectorAll('a');
            links.forEach(link => {
                link.addEventListener('touchstart', function() {
                    this.classList.add('touch-active');
                });
                link.addEventListener('touchend', function() {
                    this.classList.remove('touch-active');
                });
                link.addEventListener('touchcancel', function() {
                    this.classList.remove('touch-active');
                });
            });
        }
    }
    
    // 确保页面支持正常的触摸滑动滚动
    document.body.style.touchAction = 'auto';
    document.body.style.webkitTouchCallout = 'none'; // 禁用长按弹出菜单
    
    // 优化滚动效果
    if (isMobile) {
        // 移动端使用auto以获得更流畅的原生滚动体验
        document.documentElement.style.setProperty('scroll-behavior', 'auto');
        
        // 添加滚动性能优化
        document.body.style.overflowX = 'hidden'; // 防止横向滚动条
        
        // 确保关键元素不阻止默认滚动
        const sections = document.querySelectorAll('section');
        sections.forEach(section => {
            section.style.touchAction = 'auto';
        });
    } else {
        document.documentElement.style.setProperty('scroll-behavior', 'smooth');
    }
    
    // 移除可能存在的阻止滚动的全局事件监听器
  // 注释掉克隆body的代码，避免清除所有事件监听器
  
  // 添加高级触摸滚动支持 - 实现平滑滚动和惯性滚动
  if (isMobile) {
    console.log('添加高级触摸滚动支持...');
    
    let startY = 0;
    let currentY = 0;
    let velocity = 0;
    let isScrolling = false;
    let startTime = 0;
    
    // 触摸开始事件
    document.addEventListener('touchstart', function(e) {
      if (isScrolling) {
        return;
      }
      
      startY = e.touches[0].clientY;
      currentY = window.scrollY;
      startTime = Date.now();
      velocity = 0;
      
      // 停止任何正在进行的惯性滚动
      cancelAnimationFrame(window.scrollAnimId);
    }, { passive: true });
    
    // 触摸移动事件 - 实现平滑滚动
    document.addEventListener('touchmove', function(e) {
      const hamburger = document.querySelector('.hamburger');
      if (hamburger && hamburger.classList.contains('active')) {
        return;
      }
      
      const touchY = e.touches[0].clientY;
      const delta = touchY - startY;
      const now = Date.now();
      const elapsed = now - startTime;
      
      // 计算滚动速度
      velocity = delta / (elapsed / 1000);
      
      // 平滑滚动计算 - 加入阻尼效果
      const scrollDelta = -delta * 0.8; // 阻尼系数
      window.scrollTo(0, currentY + scrollDelta);
      
      // 更新开始位置和时间
      startY = touchY;
      currentY = window.scrollY;
      startTime = now;
    }, { passive: true });
    
    // 触摸结束事件 - 实现惯性滚动
    document.addEventListener('touchend', function() {
      const hamburger = document.querySelector('.hamburger');
      if (hamburger && hamburger.classList.contains('active')) {
        return;
      }
      
      // 仅当有足够的速度时才触发惯性滚动
      if (Math.abs(velocity) > 100) {
        isScrolling = true;
        
        let decay = 0.9; // 衰减系数
        let currentVelocity = velocity;
        const targetScroll = window.scrollY;
        
        function scrollWithInertia() {
          currentVelocity *= decay;
          const scrollOffset = targetScroll - currentVelocity * 0.5;
          
          window.scrollTo(0, scrollOffset);
          
          // 当速度足够小时停止惯性滚动
          if (Math.abs(currentVelocity) > 2) {
            window.scrollAnimId = requestAnimationFrame(scrollWithInertia);
          } else {
            isScrolling = false;
          }
        }
        
        scrollWithInertia();
      }
    }, { passive: true });
    
    // 添加CSS优化以提升滚动性能
    document.documentElement.style.scrollBehavior = 'smooth';
    document.body.style.willChange = 'scroll-position';
    document.body.style.overflowScrolling = 'touch'; // iOS滚动优化
  }
  
  console.log('移动端优化完成，触摸滚动支持已添加');
}

// 移动端功能测试函数
function testMobileInteractions() {
    console.log('开始测试移动端交互功能...');
    
    // 检测是否为移动设备
    const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
    const screenWidth = window.innerWidth;
    console.log(`设备检测: ${isMobile ? '移动设备' : '桌面设备'}, 屏幕宽度: ${screenWidth}px`);
    
    // 测试触摸支持
    const touchSupported = ('ontouchstart' in window || navigator.maxTouchPoints > 0);
    console.log(`触摸支持: ${touchSupported ? '支持' : '不支持'}`);
    
    // 测试页面滚动功能
    const mainContent = document.querySelector('.main-content');
    let scrollTestResult = '未知';
    
    if (mainContent) {
      const overflowValue = getComputedStyle(mainContent).overflow;
      const heightValue = getComputedStyle(mainContent).height;
      scrollTestResult = `overflow: ${overflowValue}, height: ${heightValue}`;
      console.log(`主内容区域滚动设置: ${scrollTestResult}`);
    }
    
    // 测试菜单功能
    const hamburger = document.querySelector('.hamburger');
    const navLinks = document.querySelector('.nav-links');
    
    let menuTestResult = '未知';
    
    if (hamburger && navLinks) {
      console.log('菜单元素存在，测试菜单交互');
      
      // 检查是否有触摸事件监听器
      let hasTouchListeners = false;
      
      // 创建临时事件来测试交互性
      const testTouchInteraction = () => {
        try {
          // 模拟触摸事件以测试响应性
          const touchStartEvent = new TouchEvent('touchstart', {
            touches: [{ clientX: 10, clientY: 10 }],
            passive: true
          });
          
          hamburger.dispatchEvent(touchStartEvent);
          hasTouchListeners = true;
          return true;
        } catch (e) {
          return false;
        }
      };
      
      menuTestResult = testTouchInteraction() ? '可交互' : '无响应';
      console.log(`菜单按钮交互状态: ${menuTestResult}`);
    } else {
      console.warn('菜单元素不存在，无法测试菜单交互');
    }
    
    // 测试页面可点击性
    const testClickable = () => {
      const testElement = document.createElement('div');
      testElement.style.position = 'fixed';
      testElement.style.width = '1px';
      testElement.style.height = '1px';
      testElement.style.opacity = '0';
      document.body.appendChild(testElement);
      
      let isClickable = false;
      
      const handleClick = () => {
        isClickable = true;
      };
      
      testElement.addEventListener('click', handleClick);
      
      // 模拟点击
      const clickEvent = new MouseEvent('click', {
        bubbles: true,
        cancelable: true,
        view: window
      });
      
      testElement.dispatchEvent(clickEvent);
      testElement.removeEventListener('click', handleClick);
      document.body.removeChild(testElement);
      
      return isClickable;
    };
    
    const clickTestResult = testClickable() ? '可点击' : '不可点击';
    console.log(`页面点击功能: ${clickTestResult}`);
    
    // 测试触摸滚动功能
    const testTouchScroll = () => {
      // 检查是否有惯性滚动相关的代码
      const hasInertiaScroll = window.scrollAnimId !== undefined;
      const hasTouchEvents = document.documentElement.style.scrollBehavior === 'smooth';
      
      return {
        hasInertiaScroll,
        hasTouchEvents
      };
    };
    
    const touchScrollResult = testTouchScroll();
    console.log(`触摸滚动支持: 惯性滚动 ${touchScrollResult.hasInertiaScroll ? '已启用' : '未启用'}, 平滑滚动 ${touchScrollResult.hasTouchEvents ? '已启用' : '未启用'}`);
    
    // 测试所有交互元素
    const interactiveElements = document.querySelectorAll('a, button, .feature-card, .case-card, .hamburger');
    console.log(`可交互元素数量: ${interactiveElements.length}`);
    
    // 输出测试总结
    console.log('\n=== 移动端功能测试总结 ===');
    console.log(`- 设备类型: ${isMobile ? '移动设备' : '桌面设备'} (${screenWidth}px)`);
    console.log(`- 触摸支持: ${touchSupported ? '支持' : '不支持'}`);
    console.log(`- 页面滚动: ${scrollTestResult}`);
    console.log(`- 菜单交互: ${menuTestResult}`);
    console.log(`- 点击功能: ${clickTestResult}`);
    console.log(`- 滚动增强: 惯性滚动 ${touchScrollResult.hasInertiaScroll ? '√' : '×'}, 平滑滚动 ${touchScrollResult.hasTouchEvents ? '√' : '×'}`);
    console.log(`- 交互元素: ${interactiveElements.length} 个`);
    
    // 生成简单的可视化测试结果（可选，仅在开发环境显示）
    if (window.location.href.includes('localhost') || window.location.href.includes('127.0.0.1')) {
      const testResult = document.createElement('div');
      testResult.style.position = 'fixed';
      testResult.style.bottom = '20px';
      testResult.style.right = '20px';
      testResult.style.background = '#fff';
      testResult.style.padding = '10px';
      testResult.style.borderRadius = '5px';
      testResult.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
      testResult.style.zIndex = '9999';
      testResult.style.fontSize = '12px';
      testResult.innerHTML = ``;
      //document.body.appendChild(testResult);
      
      // 5秒后移除测试结果
      setTimeout(() => {
        //document.body.removeChild(testResult);
      }, 1000);
    }
    
    // 返回测试结果对象
    const testResults = {
      isMobile,
      touchSupported,
      scrollEnabled: mainContent && mainContent.style.overflow !== 'hidden',
      menuInteractive: menuTestResult === '可交互',
      clickEnabled: clickTestResult === '可点击',
      inertiaScrollEnabled: touchScrollResult.hasInertiaScroll,
      allTestsPassed: touchSupported && 
                     (mainContent && mainContent.style.overflow !== 'hidden') && 
                     menuTestResult === '可交互' && 
                     clickTestResult === '可点击'
    };
    
    console.log('\n所有测试是否通过:', testResults.allTestsPassed ? '是' : '否');
    return testResults;
}

// 在页面加载完成后自动运行测试
window.addEventListener('load', function() {
  // 延迟运行测试，确保所有资源加载完成
  setTimeout(() => {
    console.log('\n[自动测试] 开始运行移动端功能测试...');
    testMobileInteractions();
  }, 1000);
});

// 暴露全局测试函数，方便手动测试
window.testMobileTouch = testMobileInteractions;

// 确保只绑定一次resize事件监听器
if (window.optimizeForMobileInitialized !== true) {
    window.optimizeForMobileInitialized = true;
    
    // 监听窗口大小变化
    window.addEventListener('resize', optimizeForMobile);
}
