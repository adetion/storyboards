// 全局变量定义
let user = {};
let selectedAmount = 0;
let selectedPoints = 0;
let selectedVipOption = {};
let selectedRechargeAmount = 0;
let selectedRechargePoints = 0;
let selectedVipAmount = 0;
let selectedVipLevel = 0;

// 充值和会员购买相关函数定义
function selectRechargeOption(amount, points, element = null) {
    //console.log('selectRechargeOption函数被调用，amount:', amount, 'points:', points);
    selectedAmount = amount;
    selectedPoints = points;
    
    // 移除其他选项的选中状态
    document.querySelectorAll('.recharge-option').forEach(option => {
        option.classList.remove('selected');
    });
    
    // 添加当前选项的选中状态
    if (element) {
        // 使用传递的element参数
        if (element.classList.contains('recharge-option')) {
            element.classList.add('selected');
        } else {
            // 如果传递的不是.recharge-option元素，找到最近的.recharge-option元素
            const rechargeOptionElement = element.closest('.recharge-option');
            if (rechargeOptionElement) {
                rechargeOptionElement.classList.add('selected');
            }
        }
    }

    // 更新全局变量
    selectedRechargeAmount = amount;
    selectedRechargePoints = points;

    // 更新显示
    const selectedAmountEl = document.getElementById('selected-amount');
    const selectedPointsEl = document.getElementById('selected-points');
    
    if (selectedAmountEl && selectedPointsEl) {
        selectedAmountEl.textContent = `¥${amount.toFixed(2)}`;
        selectedPointsEl.textContent = `${points}积分`;
    }
}

async function confirmRecharge() {
    if (selectedAmount === 0) {
        showToast('请选择充值金额', 'error');
        return;
    }

    try {
        // 检查是否在微信环境中
        if (typeof WeixinJSBridge === 'undefined') {
            showToast('请在微信浏览器中完成支付', 'error');
            return;
        }

        // 生成订单号
        const orderNo = 'RECHARGE' + Date.now() + Math.floor(Math.random() * 1000);

        // 获取当前用户的openid（从全局用户对象获取）
        let openid = user.openid;
        
        // 检查openid是否存在
        if (!openid) {
            // 尝试从session获取openid
            openid = sessionStorage.getItem('wx_openid');
        }
        
        if (!openid) {
            // 需要微信授权获取openid
            showToast('正在获取微信授权...', 'info');
            const appid = '<?php echo Config::WX_APPID; ?>';
            // 使用无需登录的中转页面作为回调
            const redirect_uri = encodeURIComponent(window.location.origin + '/wx_auth_callback.php');
            const auth_url = `https://open.weixin.qq.com/connect/oauth2/authorize?appid=${appid}&redirect_uri=${redirect_uri}&response_type=code&scope=snsapi_base&state=recharge_${orderNo}_${selectedAmount}_${selectedPoints}#wechat_redirect`;
            window.location.href = auth_url;
            return;
        }

        // 调用微信支付API
        const response = await fetch('pay.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                openid: openid,
                amount: selectedAmount * 100, // 转换为分
                order_no: orderNo,
                body: `充值 ${selectedPoints} 积分`,
                attach: 'recharge'
            })
        });

        const result = await response.json();

        if (result.code === 1) {
            // 调用微信JSAPI支付
            WeixinJSBridge.invoke(
                'getBrandWCPayRequest',
                result.data.pay_params,
                function(res) {
                    if (res.err_msg === 'get_brand_wcpay_request:ok') {
                        // 支付成功
                        showToast('充值成功！', 'success');
                        // 刷新页面或更新积分显示
                        window.location.reload();
                    } else {
                        // 支付失败
                        showToast('充值失败：' + res.err_msg, 'error');
                    }
                }
            );
        } else if (result.need_auth) {
            // 检查是否需要微信授权
            const appid = '<?php echo Config::WX_APPID; ?>';
            const redirect_uri = encodeURIComponent(window.location.href);
            const auth_url = `https://open.weixin.qq.com/connect/oauth2/authorize?appid=${appid}&redirect_uri=${redirect_uri}&response_type=code&scope=snsapi_base&state=123#wechat_redirect`;
            window.location.href = auth_url;
            return;
        } else {
            showToast('创建支付订单失败：' + result.msg, 'error');
        }
    } catch (error) {
        //console.error('充值失败：', error);
        showToast('充值失败，请稍后重试', 'error');
    }
}

function selectVipOption(amount, level, duration, element = null) {
    //console.log('selectVipOption函数被调用，amount:', amount, 'level:', level, 'duration:', duration);
    selectedVipOption = {
        amount: amount,
        level: level,
        duration: duration
    };

    // 移除其他选项的选中状态
    document.querySelectorAll('.vip-option').forEach(option => {
        option.classList.remove('selected');
    });

    // 添加当前选项的选中状态
    if (element) {
        // 使用传递的element参数
        if (element.classList.contains('vip-option')) {
            element.classList.add('selected');
        } else {
            // 如果传递的不是.vip-option元素，找到最近的.vip-option元素
            const vipOptionElement = element.closest('.vip-option');
            if (vipOptionElement) {
                vipOptionElement.classList.add('selected');
            }
        }
    }

    selectedVipAmount = amount;
    selectedVipLevel = level;

    // 更新显示
    document.getElementById('selected-vip-amount').textContent = `¥${amount.toFixed(2)}`;

    let vipType = '';
    if (level === 1) {
        vipType = '个人会员';
    } else if (level === 2) {
        vipType = '团队会员';
    }
    document.getElementById('selected-vip-type').textContent = vipType;
}

async function confirmVipPurchase() {
    if (!selectedVipOption.amount) {
        showToast('请选择会员套餐', 'error');
        return;
    }

    try {
        // 检查是否在微信环境中
        if (typeof WeixinJSBridge === 'undefined') {
            showToast('请在微信浏览器中完成支付', 'error');
            return;
        }

        // 生成订单号
        const orderNo = 'VIP' + Date.now() + Math.floor(Math.random() * 1000);

        // 获取当前用户的openid（从全局用户对象获取）
        let openid = user.openid;
        
        // 检查openid是否存在
        if (!openid) {
            // 尝试从session获取openid
            openid = sessionStorage.getItem('wx_openid');
        }
        
        if (!openid) {
            // 需要微信授权获取openid
            showToast('正在获取微信授权...', 'info');
            const appid = '<?php echo Config::WX_APPID; ?>';
            // 使用无需登录的中转页面作为回调
            const redirect_uri = encodeURIComponent(window.location.origin + '/wx_auth_callback.php');
            const auth_url = `https://open.weixin.qq.com/connect/oauth2/authorize?appid=${appid}&redirect_uri=${redirect_uri}&response_type=code&scope=snsapi_base&state=vip_${orderNo}_${selectedVipOption.amount}_${selectedVipOption.level}#wechat_redirect`;
            window.location.href = auth_url;
            return;
        }

        // 调用微信支付API
        const response = await fetch('pay.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                openid: openid,
                amount: selectedVipOption.amount * 100, // 转换为分
                order_no: orderNo,
                body: '购买会员服务',
                attach: `membership_${selectedVipOption.level}_${selectedVipOption.duration}`
            })
        });

        const result = await response.json();

        if (result.code === 1) {
            // 调用微信JSAPI支付
            WeixinJSBridge.invoke(
                'getBrandWCPayRequest',
                result.data.pay_params,
                function(res) {
                    if (res.err_msg === 'get_brand_wcpay_request:ok') {
                        // 支付成功
                        showToast('购买成功！', 'success');
                        // 刷新页面或更新会员等级显示
                        window.location.reload();
                    } else {
                        // 支付失败
                        showToast('购买失败：' + res.err_msg, 'error');
                    }
                }
            );
        } else {
            showToast('创建支付订单失败：' + result.msg, 'error');
        }
    } catch (error) {
        //console.error('购买失败：', error);
        showToast('购买失败，请稍后重试', 'error');
    }
}

// 显示和隐藏模态框函数
function showResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function hideResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
}

// 用户中心移动端导航切换
function toggleNavToggle() {
    const userNav = document.querySelector('.user-nav');
    const navToggleBtn = document.querySelector('.nav-toggle-btn');
    
    if (userNav && navToggleBtn) {
        userNav.classList.toggle('expanded');
        navToggleBtn.classList.toggle('expanded');
        
        // 切换按钮图标
        const icon = navToggleBtn.querySelector('i');
        if (icon) {
            if (userNav.classList.contains('expanded')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        }
    }
}

// 支付卡片切换功能
function showPaymentTab(tabName) {
    // 隐藏所有卡片
    const cards = document.querySelectorAll('.payment-card');
    cards.forEach(card => {
        card.style.display = 'none';
        card.classList.remove('active');
    });
    
    // 移除所有标签的active状态
    const tabs = document.querySelectorAll('.payment-tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // 显示选中的卡片
    document.getElementById(tabName + '-card').style.display = 'block';
    document.getElementById(tabName + '-card').classList.add('active');
    
    // 添加选中标签的active状态
    event.target.classList.add('active');
}

// 导航折叠切换（非移动端）
function toggleNavCollapse() {
    const userNav = document.querySelector('.user-nav');
    if (userNav) {
        userNav.classList.toggle('collapsed');
        
        // 切换按钮图标
        const btn = userNav.querySelector('.nav-collapse-btn');
        const icon = btn ? btn.querySelector('i') : null;
        if (icon) {
            if (userNav.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-left';
            } else {
                icon.className = 'fas fa-chevron-right';
            }
        }
    }
}

// 标签页切换功能
function initTabSwitching() {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');

    navItems.forEach(item => {
        item.addEventListener('click', function(e) {
            // 检查是否有data-tab属性，如果没有则是外部链接，允许默认跳转
            if (this.hasAttribute('data-tab')) {
                e.preventDefault();
                e.stopPropagation();

                // 移除所有激活状态
                navItems.forEach(nav => nav.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // 添加当前激活状态
                this.classList.add('active');
                const tabId = this.dataset.tab;
                document.getElementById(tabId).classList.add('active');
                
                // 如果是组织架构标签页，初始化其内部数据
                if (tabId === 'organization') {
                    initOrganizationManagement();
                }
                
                // 移动端导航点击后自动收起
                if (window.innerWidth <= 1024) {
                    toggleNavToggle();
                }
            }
        });
    });
}

// 余额标签页切换功能
function initBalanceTabSwitching() {
    const balanceTabs = document.querySelectorAll('.balance-tab');
    const recordLists = document.querySelectorAll('.record-list');

    balanceTabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // 移除所有激活状态
            balanceTabs.forEach(t => t.classList.remove('active'));
            recordLists.forEach(list => list.classList.remove('active'));

            // 添加当前激活状态
            this.classList.add('active');
            const tabId = this.dataset.balanceTab;
            document.getElementById(tabId + '-records').classList.add('active');
        });
    });
}

// 组织架构管理初始化函数
function initOrganizationManagement() {
    // 确保剧组列表标签和内容处于激活状态
    const tabs = document.querySelectorAll('#organization .tab');
    tabs.forEach(tab => tab.classList.remove('active'));
    document.querySelector('#organization .tab[onclick="openOrganizationTab(event, \'crew-list\')"]').classList.add('active');
    
    const tabContents = document.querySelectorAll('#organization .tab-content');
    tabContents.forEach(content => content.classList.remove('active'));
    document.getElementById('crew-list').classList.add('active');
    
    // 初始化组织架构管理相关数据
    // 只需要调用一次getCrews()来初始化缓存，其他函数会自动使用缓存
    loadCrews();
    
    // 初始化所有下拉框选项
    loadMemberCrewOptions();
    loadPermissionCrewOptions();
    loadResourceCrewOptions();
}

// 组织架构内部标签页切换
function openOrganizationTab(event, tabName) {
    // 隐藏所有标签页内容
    const tabContents = document.querySelectorAll('#organization .tab-content');
    tabContents.forEach(content => {
        content.classList.remove('active');
    });
    
    // 移除所有标签的active类
    const tabs = document.querySelectorAll('#organization .tab');
    tabs.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // 显示当前标签页内容
    document.getElementById(tabName).classList.add('active');
    event.currentTarget.classList.add('active');
    
    // 加载对应数据
    // 所有函数都使用getCrews()或getCrew()，会自动利用缓存
    if (tabName === 'crew-list') {
        loadCrews();
    } else if (tabName === 'member-management') {
        loadMembers();
    } else if (tabName === 'permission-management') {
        loadPermissions();
    } else if (tabName === 'shared-resources') {
        // 确保资源剧组选项已加载
        loadResourceCrewOptions().then(() => {
            loadResources();
        });
    }
}

// 模态框显示/隐藏函数
function showCreateCrewModal() {
    document.getElementById('create-crew-modal').style.display = 'flex';
}

function showAddMemberModal() {
    document.getElementById('add-member-modal').style.display = 'flex';
    // loadAddMemberCrewOptions会使用getCrews()的缓存，无需担心多次API调用
    // 但可以考虑添加一个标志，只在必要时重新加载
    loadAddMemberCrewOptions();
}

function showSetPermissionModal(memberId) {
    document.getElementById('permission-member-id').value = memberId;
    document.getElementById('set-permission-modal').style.display = 'flex';
}

function showResetPasswordModal(memberId) {
    document.getElementById('reset-member-id').value = memberId;
    document.getElementById('reset-password-modal').style.display = 'flex';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        // 重置表单
        const form = modal.querySelector('form');
        if (form) {
            form.reset();
        }
    }
}

// 设置默认剧组
function setDefaultCrew(crewId) {
    const select = document.getElementById('member-crew-id');
    if (select) {
        select.value = crewId;
    }
}

// 添加表单提交事件监听器
document.addEventListener('DOMContentLoaded', function() {
    // 监听创建剧组表单提交
    const createCrewForm = document.getElementById('create-crew-form');
    if (createCrewForm) {
        createCrewForm.addEventListener('submit', handleCreateCrew);
    }
    
    // 监听编辑剧组表单提交
    const editCrewForm = document.getElementById('edit-crew-form');
    if (editCrewForm) {
        editCrewForm.addEventListener('submit', handleEditCrew);
    }
    
    // 监听添加成员表单提交
    const addMemberForm = document.getElementById('add-member-form');
    if (addMemberForm) {
        addMemberForm.addEventListener('submit', handleAddMember);
    }
    
    // 监听编辑成员表单提交
    const editMemberForm = document.getElementById('edit-member-form');
    if (editMemberForm) {
        editMemberForm.addEventListener('submit', handleEditMember);
    }
    
    // 监听设置权限表单提交
    const setPermissionForm = document.getElementById('set-permission-form');
    if (setPermissionForm) {
        setPermissionForm.addEventListener('submit', handleSetPermission);
    }
    
    // 监听重置密码表单提交
    const resetPasswordForm = document.getElementById('reset-password-form');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', handleResetPassword);
    }
});

// 处理编辑成员表单提交
async function handleEditMember(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    try {
        const response = await fetch(`api/crew_api.php?action=update_member&${params}`);
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('edit-member-modal');
            loadMembers(); // 重新加载成员列表
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        //console.error('编辑成员失败:', error);
        showToast('编辑成员失败，请重试', 'error');
    }
}

// 处理创建剧组
async function handleCreateCrew(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    try {
        const response = await fetch(`api/crew_api.php?action=create_crew&${params}`);
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('create-crew-modal');
            // 清除缓存
            clearCrewsCache();
            loadCrews();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        //console.error('创建剧组失败:', error);
        showToast('创建剧组失败，请重试', 'error');
    }
}

// 处理编辑剧组
async function handleEditCrew(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    try {
        const response = await fetch(`api/crew_api.php?action=update_crew&${params}`);
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('edit-crew-modal');
            // 清除缓存
            clearCrewsCache();
            loadCrews();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        //console.error('编辑剧组失败:', error);
        showToast('编辑剧组失败，请重试', 'error');
    }
}

// 显示编辑剧组模态框
async function showEditCrew(crewId) {
    try {
        // 获取剧组信息
        const data = await getCrew(crewId);
        if (data.success) {
            const crew = data.data;
            
            // 填充表单数据
            document.getElementById('edit-crew-id').value = crew.id;
            document.getElementById('edit-crew-name').value = crew.name;
            document.getElementById('edit-film-name').value = crew.film_name || '';
            document.getElementById('edit-start-date').value = crew.startDate || '';
            document.getElementById('edit-end-date').value = crew.endDate || '';
            document.getElementById('edit-estimated-days').value = crew.estimatedDays || '';
            document.getElementById('edit-total-scenes').value = crew.totalScenes || '';
            document.getElementById('edit-total-shots').value = crew.totalShots || '';
            document.getElementById('edit-actual-days').value = crew.actualDays || '';
            document.getElementById('edit-days-completed').value = crew.daysCompleted || '';
            document.getElementById('edit-completion-rate').value = crew.completionRate || '';
            document.getElementById('edit-crew-description').value = crew.description || '';
            
            // 加载当前用户的任务列表到下拉框
            await loadTasksForCrewEdit(crew.current_task_id);
            
            // 处理剧本题材复选框选中状态
            // 先将所有复选框设置为未选中
            const genreCheckboxes = document.querySelectorAll('#edit-crew-modal input[name="genres[]"]');
            genreCheckboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            // 获取剧组的剧本题材（假设为数组，如 ['当代', '都市']）
            const crewGenres = crew.genres ? (Array.isArray(crew.genres) ? crew.genres : [crew.genres]) : [];
            
            // 将匹配的复选框设置为选中状态
            crewGenres.forEach(genre => {
                const checkbox = document.querySelector(`#edit-crew-modal input[name="genres[]"][value="${genre}"]`);
                if (checkbox) {
                    checkbox.checked = true;
                }
            });
            
            // 显示模态框
            document.getElementById('edit-crew-modal').style.display = 'flex';
        } else {
            showToast('获取剧组信息失败', 'error');
        }
    } catch (error) {
        //console.error('显示编辑剧组模态框失败:', error);
        showToast('显示编辑剧组模态框失败', 'error');
    }
}

// 加载当前用户的任务列表到剧组编辑下拉框
async function loadTasksForCrewEdit(currentTaskId) {
    try {
        const response = await fetch('auth_api.php?action=getUserTasks', {
            method: 'GET',
            credentials: 'same-origin'
        });
        const data = await response.json();
        
        if (data.success && data.data) {
            const taskSelect = document.getElementById('edit-current-task');
            if (!taskSelect) return;
            
            // 清空现有选项
            taskSelect.innerHTML = '<option value="">-- 请选择 --</option>';
            
            // 添加任务选项
            data.data.forEach(task => {
                // 从脚本中提取任务名称
                let taskName = task.task_id;
                let taskDate = task.created_at;
                if (task.script) {
                    try {
                        const scriptData = JSON.parse(task.script);
                        if (scriptData.name) {
                            taskName = scriptData.name;
                        }
                    } catch (e) {
                        console.error('解析脚本失败:', e);
                    }
                }
                
                const option = document.createElement('option');
                option.value = task.task_id;
                option.textContent = taskName+' - '+taskDate;
                taskSelect.appendChild(option);
            });
            
            // 设置当前选中的任务
            if (currentTaskId) {
                taskSelect.value = currentTaskId;
            }
        }
    } catch (error) {
        console.error('加载任务列表失败:', error);
    }
}

// 处理添加成员
async function handleAddMember(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const params = new URLSearchParams(formData).toString();
    
    try {
        const response = await fetch(`api/crew_api.php?action=add_member&${params}`);
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('add-member-modal');
            loadMembers();
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        //console.error('添加成员失败:', error);
        showToast('添加成员失败，请重试', 'error');
    }
}

// 处理设置权限
async function handleSetPermission(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    const memberId = document.getElementById('permission-member-id').value;
    const crewId = document.getElementById('permission-crew').value;
    
    // 获取选中的资源类型
    const resourceTypes = formData.getAll('resource_types[]');
    
    if (resourceTypes.length === 0) {
        showToast('请至少选择一个资源类型', 'warning');
        return;
    }
    
    try {
        // 保存每个资源类型的权限
        for (const resourceType of resourceTypes) {
            const response = await fetch(`api/crew_api.php?action=save_permission&member_id=${memberId}&crew_id=${crewId}&resource_type=${resourceType}&can_edit=1`);
            const data = await response.json();
            if (!data.success) {
                throw new Error(data.message);
            }
        }
        showToast('权限保存成功', 'success');
        closeModal('set-permission-modal');
        loadPermissions();
    } catch (error) {
        //console.error('设置权限失败:', error);
        showToast('设置权限失败，请重试', 'error');
    }
}

// 处理重置密码
async function handleResetPassword(event) {
    event.preventDefault();
    const memberId = document.getElementById('reset-member-id').value;
    const newPassword = document.getElementById('new-password').value;
    const confirmPassword = document.getElementById('confirm-password').value;
    
    if (newPassword !== confirmPassword) {
        showToast('两次输入的密码不一致', 'warning');
        return;
    }
    
    try {
        const response = await fetch(`api/crew_api.php?action=reset_password&id=${memberId}&password=${encodeURIComponent(newPassword)}`);
        const data = await response.json();
        if (data.success) {
            showToast(data.message, 'success');
            closeModal('reset-password-modal');
        } else {
            showToast(data.message, 'error');
        }
    } catch (error) {
        //console.error('重置密码失败:', error);
        showToast('重置密码失败，请重试', 'error');
    }
}

// 全局缓存变量
let crewsCache = null;
let crewCache = new Map(); // 按crew_id缓存单个剧组数据

// 统一获取剧组数据的函数，带缓存机制
async function getCrews() {
    // 如果缓存存在，直接返回缓存数据
    if (crewsCache) {
        return crewsCache;
    }
    
    // 缓存不存在，请求API
    try {
        const response = await fetch('api/crew_api.php?action=get_crews');
        const data = await response.json();
        if (data.success) {
            // 缓存数据
            crewsCache = data;
            // 同时缓存单个剧组数据
            data.data.forEach(crew => {
                crewCache.set(crew.id, { success: true, data: crew });
            });
        }
        return data;
    } catch (error) {
        //console.error('获取剧组数据失败:', error);
        return { success: false, data: [] };
    }
}

// 统一获取单个剧组数据的函数，带缓存机制
async function getCrew(crewId) {
    // 如果缓存存在，直接返回缓存数据
    if (crewCache.has(crewId)) {
        return crewCache.get(crewId);
    }
    
    // 缓存不存在，请求API
    try {
        const response = await fetch(`api/crew_api.php?action=get_crew&id=${crewId}`);
        const data = await response.json();
        if (data.success) {
            // 缓存数据
            crewCache.set(crewId, data);
        }
        return data;
    } catch (error) {
        //console.error('获取单个剧组数据失败:', error);
        return { success: false, data: null };
    }
}

// 清除剧组缓存的函数（在数据更新时调用）
function clearCrewsCache() {
    crewsCache = null;
    crewCache.clear();
}

// 数据加载函数
async function loadCrews() {
    try {
        // 获取用户所属的剧组信息（包含当前剧组标记）
        const response = await fetch('auth_api.php?action=getUserCrewInfo', {
            method: 'GET',
            credentials: 'same-origin'
        });
        const userCrewData = await response.json();
        
        if (userCrewData.success && userCrewData.data) {
            const crews = Array.isArray(userCrewData.data) ? userCrewData.data : [userCrewData.data];
            const tbody = document.getElementById('crew-list-body');
            
            if (!tbody) {
                console.error('剧组列表元素不存在');
                return;
            }
            
            if (crews.length === 0) {
                tbody.innerHTML = '<tr><td colspan="15" class="no-data">暂无剧组</td></tr>';
                return;
            }
            
            // 获取所有剧组的详细信息
            const crewIds = crews.map(c => c.crew_id);
            const crewsData = await Promise.all(crewIds.map(async (crewId) => {
                try {
                    const res = await fetch(`api/crew_api.php?action=get_crew&id=${crewId}`, {
                        method: 'GET',
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    return data.success ? data.data : null;
                } catch (error) {
                    console.error('获取剧组详情失败:', error);
                    return null;
                }
            }));
            
            tbody.innerHTML = crews.map((crew, index) => {
                const crewDetail = crewsData[index];
                // 使用返回的is_creator字段判断是否是创建者
                const isCreator = crew.is_creator;
                
                return `
                    <tr>
                        <td>${crew.crew_id}</td>
                        <td>${crew.crew_name}</td>
                        <td>${crewDetail ? (crewDetail.film_name || '') : ''}</td>
                        <td>${crewDetail ? (crewDetail.current_task_name || '-') : '-'}</td>
                        <td>${crewDetail ? (crewDetail.description || '') : ''}</td>
                        <td>${crewDetail ? (crewDetail.startDate || '') : ''}</td>
                        <td>${crewDetail ? (crewDetail.endDate || '') : ''}</td>
                        <td>${crewDetail ? (crewDetail.estimatedDays || 0) : 0}</td>
                        <td>${crewDetail ? (crewDetail.totalScenes || 0) : 0}</td>
                        <td>${crewDetail ? (crewDetail.totalShots || 0) : 0}</td>
                        <td>${crewDetail ? (crewDetail.actualDays || 0) : 0}</td>
                        <td>${crewDetail ? (crewDetail.daysCompleted || 0) : 0}</td>
                        <td>${crewDetail ? (crewDetail.completionRate || 0) : 0}%</td>
                        <td>${crewDetail ? (crewDetail.created_at || '') : ''}</td>
                        <td>
                            <div style="display: flex; gap: 5px; flex-wrap: wrap; align-items: center;">
                                ${crew.is_current ? '<span class="current-crew-badge">当前剧组</span>' : ''}
                                <button class="btn btn-sm" onclick="setCurrentCrew(${crew.crew_id})" ${crew.is_current ? 'disabled' : ''}>
                                    ${crew.is_current ? '当前' : '设为当前'}
                                </button>
                                ${isCreator ? `
                                    <button class="btn btn-sm btn-secondary" onclick="showEditCrew(${crew.crew_id})"><i class="fas fa-edit"></i> 编辑</button>
                                    <button class="btn btn-sm btn-danger" onclick="deleteCrew(${crew.crew_id})"><i class="fas fa-trash"></i> 删除</button>
                                ` : ''}
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
    } catch (error) {
        console.error('加载剧组列表失败:', error);
    }
}

// 设置当前剧组
async function setCurrentCrew(crewId) {
    try {
        const response = await fetch('auth_api.php?action=setCurrentCrew', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `crew_id=${crewId}`,
            credentials: 'same-origin'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showToast('当前剧组设置成功', 'success');
            // 重新加载剧组列表
            loadCrews();
        } else {
            showToast(data.message || '设置当前剧组失败', 'error');
        }
    } catch (error) {
        console.error('设置当前剧组失败:', error);
        showToast('设置当前剧组失败', 'error');
    }
}

async function loadMembers() {
    const crewId = document.getElementById('crew-select').value;
    const search = document.getElementById('member-search').value;
    try {
        // 获取成员列表
        const response = await fetch(`api/crew_api.php?action=get_members&crew_id=${crewId}&search=${encodeURIComponent(search)}`);
        const data = await response.json();
        
        // 获取当前剧组信息，检查创建者（使用缓存机制）
        const crewData = await getCrew(crewId);
        
        if (data.success && crewData.success) {
            const tbody = document.getElementById('member-list-body');
            const isCrewCreator = crewData.data.admin_user_id === window.user_id;
            
            tbody.innerHTML = data.data.map(member => `
                <tr>
                    <td>${member.id}</td>
                    <td>${member.name}</td>
                    <td>${member.gender}</td>
                    <td>${member.position}</td>
                    <td><span class="group-badge">${member.group}</span></td>
                    <td>${member.account}</td>
                    <td class="${member.enabled ? 'status-active' : 'status-inactive'}">
                        ${member.enabled ? '<i class="fas fa-check-circle"></i> 启用' : '<i class="fas fa-times-circle"></i> 禁用'}
                    </td>
                    <td>
                        ${isCrewCreator ? `
                            <button class="btn" onclick="showEditMember(${member.id})"><i class="fas fa-edit"></i> 编辑</button>
                            <button class="btn btn-secondary" onclick="resetMemberPassword(${member.id}, ${member.can_modify_password})"><i class="fas fa-key"></i> 重置密码</button>
                            <button class="btn btn-danger" onclick="deleteMember(${member.id})"><i class="fas fa-trash"></i> 删除</button>
                        ` : '<span class="view-only-text">仅查看</span>'}
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        //console.error('加载成员列表失败:', error);
    }
}

async function loadPermissions() {
    const crewId = document.getElementById('permission-crew-select').value;
    try {
        // 获取权限列表
        const response = await fetch(`api/crew_api.php?action=get_permissions&crew_id=${crewId}`);
        const data = await response.json();
        
        // 获取当前剧组信息，检查创建者（使用缓存机制）
        const crewData = await getCrew(crewId);
        
        if (data.success && crewData.success) {
            const tbody = document.getElementById('permission-list-body');
            const isCrewCreator = crewData.data.admin_user_id === window.user_id;
            
            tbody.innerHTML = data.data.map(permission => `
                <tr>
                    <td>${permission.id}</td>
                    <td>${permission.member_name || permission.member_id}</td>
                    <td>${permission.resource_type}</td>
                    <td>${permission.can_edit ? '允许' : '禁止'}</td>
                    <td>
                        ${isCrewCreator ? `
                            <button class="btn" onclick="showSetPermission(${permission.member_id})"><i class="fas fa-edit"></i> 编辑</button>
                        ` : '<span class="view-only-text">仅查看</span>'}
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        //console.error('加载权限列表失败:', error);
    }
}

async function loadResources() {
    const crewId = document.getElementById('resource-crew-select').value;
    const resourceType = document.getElementById('resource-type-select').value;
    try {
        // 获取资源列表
        const response = await fetch(`api/crew_api.php?action=get_shared_resources&crew_id=${crewId}&resource_type=${resourceType}`);
        const data = await response.json();
        
        // 获取当前剧组信息，检查创建者（使用缓存机制）
        const crewData = await getCrew(crewId);
        
        if (data.success && crewData.success) {
            const tbody = document.getElementById('resource-list-body');
            const isCrewCreator = crewData.data.admin_user_id === window.user_id;
            
            tbody.innerHTML = data.data.map(resource => `
                <tr>
                    <td>${resource.id}</td>
                    <td>${resource.resource_id}</td>
                    <td>${resource.resource_type}</td>
                    <td>${resource.shared_by}</td>
                    <td>${resource.shared_at}</td>
                    <td>
                        <button class="btn btn-primary" onclick="viewResource(${resource.id}, '${resource.resource_type}')"><i class="fas fa-eye"></i> 查看</button>
                        ${isCrewCreator ? `
                            <button class="btn btn-danger" onclick="unshareResource(${resource.id})"><i class="fas fa-times"></i> 取消共享</button>
                        ` : ''}
                    </td>
                </tr>
            `).join('');
        }
    } catch (error) {
        //console.error('加载资源列表失败:', error);
    }
}

// 加载剧组选项到下拉框
async function loadMemberCrewOptions() {
    try {
        const data = await getCrews();
        if (data.success) {
            const select = document.getElementById('crew-select');
            if (select) {
                // 只显示用户自己创建的剧组
                const userCrews = data.data.filter(crew => crew.is_creator || crew.admin_user_id === window.user_id);
                select.innerHTML = '<option value="">选择剧组</option>' + userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
            }
        }
    } catch (error) {
        //console.error('加载剧组选项失败:', error);
    }
}

// 加载权限剧组选项
async function loadPermissionCrewOptions() {
    try {
        const data = await getCrews();
        if (data.success) {
            const select = document.getElementById('permission-crew-select');
            if (select) {
                select.innerHTML = '<option value="">选择剧组</option>' + data.data.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
            }
        }
    } catch (error) {
        //console.error('加载权限剧组选项失败:', error);
    }
}

// 加载资源剧组选项
async function loadResourceCrewOptions() {
    try {
        const data = await getCrews();
        if (data.success) {
            const select = document.getElementById('resource-crew-select');
            if (select) {
                select.innerHTML = '<option value="">选择剧组</option>' + data.data.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
            }
        }
    } catch (error) {
        //console.error('加载资源剧组选项失败:', error);
    }
}

// 加载添加成员模态框中的剧组选项
async function loadAddMemberCrewOptions() {
    try {
        const data = await getCrews();
        if (data.success) {
            const select = document.getElementById('member-crew-id');
            if (select) {
                // 过滤只显示自己创建的剧组（新增成员时只能选择自己创建的剧组）
                const userCrews = data.data.filter(crew => crew.is_creator);
                select.innerHTML = '<option value="">请选择剧组</option>' + userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
            }
        }
    } catch (error) {
        //console.error('加载添加成员剧组选项失败:', error);
    }
}

// 编辑和删除函数
async function deleteCrew(crewId) {
    if (window.confirm('确定要删除该剧组吗？删除后将无法恢复。')) {
        try {
            const response = await fetch(`api/crew_api.php?action=delete_crew&id=${crewId}`);
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                // 清除缓存
                clearCrewsCache();
                loadCrews();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            //console.error('删除剧组失败:', error);
            showToast('删除剧组失败，请重试', 'error');
        }
    }
}

// 显示编辑成员模态框并加载成员信息
async function showEditMember(memberId) {
    try {
        // 获取成员信息
        const response = await fetch(`api/crew_api.php?action=get_member&id=${memberId}`);
        const data = await response.json();
        if (data.success) {
            const member = data.data;
            
            // 填充表单数据
            document.getElementById('edit-member-id').value = member.id;
            document.getElementById('edit-member-name').value = member.name;
            document.getElementById('edit-member-gender').value = member.gender;
            document.getElementById('edit-member-responsibilities').value = member.responsibilities || '';
            document.getElementById('edit-member-phone').value = member.phone || '';
            document.getElementById('edit-member-email').value = member.email || '';
            document.getElementById('edit-member-wechat').value = member.wechat || '';
            document.getElementById('edit-member-account').value = member.account || '';
            document.getElementById('edit-member-is-admin').value = member.is_admin || 0;
            document.getElementById('edit-member-can-modify-password').value = member.can_modify_password || 1;
            document.getElementById('edit-member-is-authorized').value = member.is_authorized || 0;
            
            // 加载剧组选项
            await loadEditMemberCrewOptions(member.crew_id);
            
            // 加载职务选项
            loadEditMemberPositionOptions(member.position);
            
            // 加载分组选项
            loadEditMemberGroupOptions(member.group);
            
            // 显示模态框
            document.getElementById('edit-member-modal').style.display = 'flex';
        } else {
            showToast('获取成员信息失败', 'error');
        }
    } catch (error) {
        //console.error('显示编辑成员模态框失败:', error);
        showToast('显示编辑成员模态框失败', 'error');
    }
}

// 加载编辑成员模态框中的剧组选项
async function loadEditMemberCrewOptions(selectedCrewId = '') {
    try {
        const data = await getCrews();
        if (data.success) {
            const select = document.getElementById('edit-member-crew-id');
            if (select) {
                // 过滤只显示自己创建的剧组
                const userCrews = data.data.filter(crew => crew.admin_user_id === window.user_id);
                select.innerHTML = userCrews.map(crew => `<option value="${crew.id}" ${crew.id == selectedCrewId ? 'selected' : ''}>${crew.name}</option>`).join('');
            }
        }
    } catch (error) {
        //console.error('加载编辑成员剧组选项失败:', error);
    }
}

// 加载编辑成员模态框中的职务选项
function loadEditMemberPositionOptions(selectedPosition = '') {
    const select = document.getElementById('edit-member-position');
    if (select) {
        // 复制添加成员模态框中的职务选项
        const addPositionSelect = document.getElementById('member-position');
        select.innerHTML = addPositionSelect.innerHTML;
        select.value = selectedPosition;
    }
}

// 加载编辑成员模态框中的分组选项
function loadEditMemberGroupOptions(selectedGroup = '') {
    const select = document.getElementById('edit-member-group');
    if (select) {
        // 复制添加成员模态框中的分组选项
        const addGroupSelect = document.getElementById('member-group');
        select.innerHTML = addGroupSelect.innerHTML;
        select.value = selectedGroup;
    }
}

async function deleteMember(memberId) {
    if (window.confirm('确定要删除该成员吗？删除后将无法恢复。')) {
        try {
            const response = await fetch(`api/crew_api.php?action=delete_member&id=${memberId}`);
            const data = await response.json();
            if (data.success) {
                showToast(data.message, 'success');
                loadMembers();
            } else {
                showToast(data.message, 'error');
            }
        } catch (error) {
            //console.error('删除成员失败:', error);
            showToast('删除成员失败，请重试', 'error');
        }
    }
}

function resetMemberPassword(memberId, canModifyPassword) {
    if (!canModifyPassword) {
        showToast('该成员禁止管理员修改密码', 'warning');
        return;
    }
    showResetPasswordModal(memberId);
}

function showSetPermission(memberId) {
    showSetPermissionModal(memberId);
}

function viewResource(resourceId, resourceType) {
    alert(`查看资源：${resourceType} - ${resourceId}`);
}

async function unshareResource(resourceId) {
    if (confirm('确定要取消共享该资源吗？')) {
        try {
            const response = await fetch(`api/crew_api.php?action=unshare_resource&id=${resourceId}`);
            const data = await response.json();
            if (data.success) {
                alert(data.message);
                loadResources();
            } else {
                alert(data.message);
            }
        } catch (error) {
            //console.error('取消共享资源失败:', error);
            alert('取消共享资源失败，请重试');
        }
    }
}

// 初始化函数
function init() {
    // 初始化标签页切换
    initTabSwitching();
    
    // 初始化余额标签页切换
    initBalanceTabSwitching();
    
    // 初始化组织架构管理
    initOrganizationManagement();
    // initOrganizationManagement已经调用了loadCrews()，这里不再重复调用
    // 其他加载函数会在需要时自动调用，利用缓存机制
}

// 平滑滚动到会员权益营销区域
function scrollToMembershipSection() {
    const membershipSection = document.querySelector('.membership-benefits-section');
    if (membershipSection) {
        // 使用setTimeout确保标签切换完成后再滚动
        setTimeout(function() {
            membershipSection.scrollIntoView({
                behavior: 'smooth',
                block: 'start'
            });
        }, 100);
    }
}

// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function() {
    init();
    
    // 默认选中99元/年的会员选项
    const vipOption = document.querySelector('.vip-tier:first-child .vip-option:nth-child(2)');
    if (vipOption) {
        selectVipOption(99, 1, 2, vipOption);
    }
    
    // 默认选中99元的充值选项
    const rechargeOption = document.querySelectorAll('.recharge-option')[1];
    if (rechargeOption) {
        selectRechargeOption(99, 12000, rechargeOption);
    }
    
    // 修复提示信息的颜色配置
    const selectedVip = document.querySelector('.selected-vip');
    if (selectedVip) {
        selectedVip.style.color = 'var(--text-primary)';
        selectedVip.style.background = 'var(--background-hover)';
    }
    
    const selectedRecharge = document.querySelector('.selected-recharge');
    if (selectedRecharge) {
        selectedRecharge.style.color = 'var(--text-primary)';
        selectedRecharge.style.background = 'var(--background-hover)';
    }
    
    // 修复showPaymentTab函数，确保切换到充值卡片时默认选中99元选项
    const originalShowPaymentTab = window.showPaymentTab;
    window.showPaymentTab = function(tabName) {
        originalShowPaymentTab(tabName);
        
        if (tabName === 'recharge') {
            // 切换到充值卡片时，默认选中99元选项
            const rechargeOption = document.querySelectorAll('.recharge-option')[1];
            if (rechargeOption) {
                selectRechargeOption(99, 12000, rechargeOption);
            }
        }
    };
});
