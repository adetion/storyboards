// menu.js - 简化版本
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 移动端菜单初始化');
    
    const menuToggle = document.getElementById('menuToggle');
    const mainNav = document.getElementById('mainNav');
    const navOverlay = document.getElementById('navOverlay');
    
    if (!menuToggle || !mainNav) {
        // console.error('❌ 未找到菜单元素');
        return;
    }
    
    // 当前菜单状态
    let isOpen = false;
    
    // 打开菜单
    function openMenu() {
        console.log('📱 打开菜单');
        
        mainNav.classList.add('show');
        if (navOverlay) navOverlay.classList.add('show');
        
        // 更新按钮图标
        menuToggle.innerHTML = '<i class="fas fa-times"></i>';
        menuToggle.setAttribute('aria-expanded', 'true');
        
        // 阻止背景滚动
        document.body.classList.add('menu-open');
        
        isOpen = true;
    }
    
    // 关闭菜单
    function closeMenu() {
        console.log('📱 关闭菜单');
        
        mainNav.classList.remove('show');
        if (navOverlay) navOverlay.classList.remove('show');
        
        // 恢复按钮图标
        menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        menuToggle.setAttribute('aria-expanded', 'false');
        
        // 恢复背景滚动
        document.body.classList.remove('menu-open');
        
        isOpen = false;
    }
    
    // 切换菜单
    function toggleMenu(e) {
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }
        
        if (window.innerWidth > 768) return;
        
        if (isOpen) {
            closeMenu();
        } else {
            openMenu();
        }
    }
    
    // 绑定事件
    menuToggle.addEventListener('click', toggleMenu);
    
    // 遮罩层点击关闭
    if (navOverlay) {
        navOverlay.addEventListener('click', closeMenu);
    }
    
    // 菜单链接点击关闭
    const navLinks = mainNav.querySelectorAll('a');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                setTimeout(closeMenu, 100);
            }
        });
    });
    
    // ESC键关闭
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            closeMenu();
        }
    });
    
    // 窗口大小变化
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768 && isOpen) {
            closeMenu();
        }
    });
    
    // 初始状态
    if (window.innerWidth <= 768) {
        mainNav.style.display = 'none';
        menuToggle.style.display = 'flex';
    } else {
        mainNav.style.display = 'flex';
        menuToggle.style.display = 'none';
    }
    
    console.log('✅ 菜单初始化完成');
    
    // 调试：强制检查点击事件
    menuToggle.addEventListener('touchstart', function() {
        console.log('👆 触摸菜单按钮');
    }, { passive: true });
    
    // 添加一个测试按钮（开发时使用）
    // if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
    //     const testBtn = document.createElement('button');
    //     testBtn.textContent = '测试菜单';
    //     testBtn.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;padding:10px;background:#f00;color:#fff;';
    //     testBtn.onclick = function() {
    //         console.log('=== 菜单状态测试 ===');
    //         console.log('isOpen:', isOpen);
    //         console.log('mainNav class:', mainNav.className);
    //         console.log('mainNav style:', mainNav.style.cssText);
    //         console.log('menuToggle style:', menuToggle.style.cssText);
    //         alert('菜单状态：' + (isOpen ? '打开' : '关闭'));
    //     };
    //     document.body.appendChild(testBtn);
    // }
});
