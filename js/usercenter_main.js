// 获取API_KEY
async function getApiKey() {
    try {
        const response = await fetch('get_api_key.php', {
            method: 'GET',
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error('获取API失败');
        }

        const data = await response.json();
        
        // 设置文本分析API
        if (data.text_analysis_api_url) {
            document.getElementById('text_analysis_api_url').value = data.text_analysis_api_url;
        }
        if (data.text_analysis_api_key) {
            document.getElementById('text_analysis_api_key').value = data.text_analysis_api_key;
        }
        if (data.text_analysis_api_model) {
            document.getElementById('text_analysis_api_model').value = data.text_analysis_api_model;
        }
        
        // 设置文(图)生图API
        if (data.text_to_image_api_url) {
            document.getElementById('text_to_image_api_url').value = data.text_to_image_api_url;
        }
        if (data.text_to_image_api_key) {
            document.getElementById('text_to_image_api_key').value = data.text_to_image_api_key;
        }
        if (data.text_to_image_api_model) {
            document.getElementById('text_to_image_api_model').value = data.text_to_image_api_model;
        }
        
        // 设置图生视频API
        if (data.image_to_video_api_url) {
            document.getElementById('image_to_video_api_url').value = data.image_to_video_api_url;
        }
        if (data.image_to_video_api_key) {
            document.getElementById('image_to_video_api_key').value = data.image_to_video_api_key;
        }
        if (data.image_to_video_api_model) {
            document.getElementById('image_to_video_api_model').value = data.image_to_video_api_model;
        }
        
        // 设置图片理解API
        if (data.img2text_api_url) {
            document.getElementById('img2text_api_url').value = data.img2text_api_url;
        }
        if (data.img2text_api_key) {
            document.getElementById('img2text_api_key').value = data.img2text_api_key;
        }
        if (data.img2text_api_model) {
            document.getElementById('img2text_api_model').value = data.img2text_api_model;
        }
    } catch (error) {
        //console.error('获取API时出错:', error);
    }
}

// 保存API_KEY
async function saveApiKey() {
    // 获取API设置项
    const textAnalysisApiUrl = document.getElementById('text_analysis_api_url').value.trim();
    const textAnalysisApiKey = document.getElementById('text_analysis_api_key').value.trim();
    const textAnalysisApiModel = document.getElementById('text_analysis_api_model').value.trim();
    
    const textToImageApiUrl = document.getElementById('text_to_image_api_url').value.trim();
    const textToImageApiKey = document.getElementById('text_to_image_api_key').value.trim();
    const textToImageApiModel = document.getElementById('text_to_image_api_model').value.trim();
    
    const imageToVideoApiUrl = document.getElementById('image_to_video_api_url').value.trim();
    const imageToVideoApiKey = document.getElementById('image_to_video_api_key').value.trim();
    const imageToVideoApiModel = document.getElementById('image_to_video_api_model').value.trim();
    
    const img2textApiUrl = document.getElementById('img2text_api_url').value.trim();
    const img2textApiKey = document.getElementById('img2text_api_key').value.trim();
    const img2textApiModel = document.getElementById('img2text_api_model').value.trim();

    try {
        // 构建表单数据
        const formData = new URLSearchParams();
        formData.append('text_analysis_api_url', textAnalysisApiUrl);
        formData.append('text_analysis_api_key', textAnalysisApiKey);
        formData.append('text_analysis_api_model', textAnalysisApiModel);
        formData.append('text_to_image_api_url', textToImageApiUrl);
        formData.append('text_to_image_api_key', textToImageApiKey);
        formData.append('text_to_image_api_model', textToImageApiModel);
        formData.append('image_to_video_api_url', imageToVideoApiUrl);
        formData.append('image_to_video_api_key', imageToVideoApiKey);
        formData.append('image_to_video_api_model', imageToVideoApiModel);
        formData.append('img2text_api_url', img2textApiUrl);
        formData.append('img2text_api_key', img2textApiKey);
        formData.append('img2text_api_model', img2textApiModel);
        
        const response = await fetch('save_api_key.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: formData.toString(),
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.success) {
            alert('API保存成功');
        } else {
            alert('API保存失败: ' + (data.message || '未知错误'));
        }
    } catch (error) {
        //console.error('保存API时出错:', error);
        alert('保存API时发生网络错误');
    }

    return false;
}

// 切换API_KEY可见性
function toggleApiKeyVisibility(type) {
    // 特定类型的API_KEY
    const apiKeyInput = document.getElementById(`${type}_api_key`);
    // 获取当前点击的元素
    const toggleBtn = event.currentTarget;
    
    if (apiKeyInput) {
        const icon = toggleBtn.querySelector('i');
        
        if (apiKeyInput.type === 'password') {
            // 切换为可见
            apiKeyInput.type = 'text';
            icon.classList.remove('fa-eye-slash');
            icon.classList.add('fa-eye');
        } else {
            // 切换为隐藏
            apiKeyInput.type = 'password';
            icon.classList.remove('fa-eye');
            icon.classList.add('fa-eye-slash');
        }
    }
}

// API调用函数
async function apiCall(endpoint, method = 'GET', data = null) {
    try {
        const headers = {
            // 设置默认的Accept头
            'Accept': 'application/json'
        };

        // 只在POST请求且有数据时设置Content-Type头
        if (method === 'POST' && data) {
            headers['Content-Type'] = 'application/x-www-form-urlencoded';
        }

        const options = {
            method,
            headers,
            credentials: 'same-origin' // 包含同域cookie
        };

        if (data && method === 'POST') {
            // 将数据转换为URLSearchParams格式，适合$_POST获取
            const formData = new URLSearchParams();
            for (const key in data) {
                formData.append(key, data[key]);
            }
            options.body = formData;
        }

        //console.log('API调用:', endpoint, options);

        const response = await fetch(endpoint, options);

        // 检查响应状态
        if (!response.ok) {
            // 获取错误响应文本
            let errorText;
            try {
                errorText = await response.text();
            } catch (e) {
                errorText = `HTTP错误! 状态: ${response.status}`;
            }
            throw new Error(errorText || `HTTP错误! 状态: ${response.status}`);
        }

        // 尝试获取响应文本
        const text = await response.text();
        //console.log('响应文本内容:', text.substring(0, 100) + (text.length > 100 ? '...' : ''));

        // 处理空响应
        if (!text || text.trim() === '') {
            //console.warn('响应内容为空');
            return {
                success: true,
                data: []
            };
        }

        // 检查是否是有效的JSON
        let result;
        try {
            result = JSON.parse(text);
        } catch (parseError) {
            //console.warn('响应不是有效的JSON格式，内容为:', text.substring(0, 100));
            //console.warn('JSON解析错误:', parseError);
            // 对于非JSON响应，返回包含原始文本的对象
            return {
                success: true,
                data: text
            };
        }

        return result;
    } catch (error) {
        //console.error('API调用错误:', error);
        // 重新抛出错误，让调用者处理
        throw error;
    }
}



// 创建toast提示函数
function showToast(message, type = 'info') {
    // 创建toast元素
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 12px 24px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#3b82f6'};
        color: white;
        border-radius: 8px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        z-index: 10000;
        transition: all 0.3s ease;
        transform: translateX(100%);
        font-size: 14px;
        font-weight: 500;
    `;
    toast.textContent = message;

    // 添加到body
    document.body.appendChild(toast);

    // 显示toast
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 10);

    // 3秒后自动隐藏
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            document.body.removeChild(toast);
        }, 300);
    }, 3000);
}



// 生成订单号
function generateOrderNo() {
    const timestamp = Date.now();
    const random = Math.floor(Math.random() * 10000).toString().padStart(4, '0');
    return `ORD${timestamp}${random}`;
}




// 初始化页面
async function initPage() {
    try {
        //console.log('开始初始化页面...');

        // 先显示页面内容，避免因API调用问题导致页面不显示
        document.getElementById('pageContent').style.display = 'block';

        // 加载用户资料
        try {
            await loadUserProfile();
            //console.log('用户资料加载完成');
        } catch (error) {
            //console.error('加载用户资料失败:', error);
            // 如果加载用户资料失败，尝试直接获取当前用户信息
            try {
                const currentUserResponse = await apiCall('auth_api.php?action=getCurrentUser');
                if (currentUserResponse.success && currentUserResponse.user) {
                    user = currentUserResponse.user;
                    //console.log('通过getCurrentUser获取到用户信息:', user);
                }
            } catch (currentUserError) {
                //console.error('获取当前用户信息失败:', currentUserError);
            }
        }

        // 即使用户信息加载失败，也继续尝试加载其他数据

        // 加载历史任务
        try {
            await loadTasks();
            //console.log('历史任务加载完成');
        } catch (error) {
            //console.error('加载历史任务失败:', error);
        }

        // 加载充值记录
        try {
            await loadRechargeRecords();
            //console.log('充值记录加载完成');
        } catch (error) {
            //console.error('加载充值记录失败:', error);
        }

        // 加载消费记录
        try {
            await loadConsumptionRecords();
            //console.log('消费记录加载完成');
        } catch (error) {
            //console.error('加载消费记录失败:', error);
        }

        // 加载积分记录
        try {
            await loadPointsRecords();
            //console.log('积分记录加载完成');
        } catch (error) {
            //console.error('加载积分记录失败:', error);
        }

        // 加载API设置
        try {
            await getApiKey();
            //console.log('API设置加载完成');
        } catch (error) {
            //console.error('加载API设置失败:', error);
        }

        //console.log('页面初始化完成');
    } catch (error) {
        //console.error('初始化页面失败:', error);
        // 即使初始化失败，也要确保页面显示
        document.getElementById('pageContent').style.display = 'block';
        alert('页面初始化失败，请刷新页面重试');
    }
}

// 脱离剧组函数
async function leaveCrew(crewId = null) {
    const confirmMessage = crewId ? '确定要脱离该剧组吗？脱离后将无法查看该剧组资源。' : '确定要脱离当前剧组吗？脱离后将无法查看该剧组资源。';

    if (confirm(confirmMessage)) {
        try {
            //console.log('开始脱离剧组...', crewId);
            let apiUrl = 'auth_api.php?action=leaveCrew';
            if (crewId) {
                apiUrl += `&crewId=${crewId}`;
            }
            const response = await apiCall(apiUrl);
            //console.log('脱离剧组响应:', response);

            if (response.success) {
                alert(response.message);
                // 重新加载用户资料
                await loadUserProfile();
            } else {
                alert(response.message || '脱离剧组失败');
            }
        } catch (error) {
            //console.error('脱离剧组失败:', error);
            alert('脱离剧组失败，请重试');
        }
    }
}

// 加载充值记录
async function loadRechargeRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.recharge;
        const response = await apiCall(`auth_api.php?action=getRechargeRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.recharge.totalItems = totalItems;
        paginationState.recharge.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('recharge-records-body');
        const paginationContainer = document.getElementById('recharge-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无充值记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保amount是数字类型
            const amount = parseFloat(record.amount) || 0.00;
            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>¥${amount.toFixed(2)}</td>
                    <td>${record.payment_method || '未知'}</td>
                    <td>${record.status === 'success' ? '成功' : '失败'}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'recharge',
            currentPage,
            paginationState.recharge.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载充值记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('recharge-records-body');
        const paginationContainer = document.getElementById('recharge-pagination');
        tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无充值记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}

// 加载消费记录
async function loadConsumptionRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.consumption;
        const response = await apiCall(`auth_api.php?action=getConsumptionRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.consumption.totalItems = totalItems;
        paginationState.consumption.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('consumption-records-body');
        const paginationContainer = document.getElementById('consumption-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无消费记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保amount是数字类型
            const amount = parseFloat(record.amount) || 0.00;
            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>¥${amount.toFixed(2)}</td>
                    <td>${record.consumption_type || '未知'}</td>
                    <td>${record.description || ''}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'consumption',
            currentPage,
            paginationState.consumption.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载消费记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('consumption-records-body');
        const paginationContainer = document.getElementById('consumption-pagination');
        tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无消费记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}

// 加载积分记录
async function loadPointsRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.points;
        const response = await apiCall(`auth_api.php?action=getPointsRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.points.totalItems = totalItems;
        paginationState.points.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('points-records-body');
        const paginationContainer = document.getElementById('points-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="no-data">暂无积分记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保points_change是数字类型
            const pointsChange = parseInt(record.points_change) || 0;
            const change = pointsChange > 0 ? `+${pointsChange}` : pointsChange;

            // 检查是否有content字段且包含图片信息
            const hasImageContent = record.content && (record.source === 'text2img' || record.source === 'image_generation');
            const operationBtn = hasImageContent ? `
                <td>
                    <button class="btn-primary btn-sm" onclick="viewGeneratedImage('${encodeURIComponent(record.content)}')">
                        <i class="fas fa-image"></i> 查看图片
                    </button>
                </td>
            ` : '<td>-</td>';

            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>${change}</td>
                    <td>${record.source || '未知'}</td>
                    <td>${record.task_id || '-'}</td>
                    ${operationBtn}
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'points',
            currentPage,
            paginationState.points.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载积分记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('points-records-body');
        const paginationContainer = document.getElementById('points-pagination');
        tbody.innerHTML = '<tr><td colspan="5" class="no-data">暂无积分记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}




// 加载用户资料
async function loadUserProfile() {
    try {
        //console.log('开始加载用户资料...');
        const response = await apiCall('auth_api.php?action=getUserProfile');
        //console.log('用户资料响应:', response);

        if (!response.success) {
            throw new Error(response.message || '获取用户资料失败');
        }

        const userData = response.data;

        // 存储用户信息到全局变量
        user = userData;

        document.getElementById('username-display').textContent = userData.username;
        document.getElementById('nickname-display').textContent = userData.nickname;
        document.getElementById('phone-display').textContent = userData.phone || '未绑定';
        document.getElementById('email-display').textContent = userData.email || '未绑定';
        document.getElementById('created-at-display').textContent = userData.created_at;
        document.getElementById('nickname-input').value = userData.nickname;

        // 显示会员等级信息
        const level = userData.level || 0;
        const expireDate = userData.membership_expire;
        const levelDisplay = document.getElementById('membership-level-display');
        const expireDisplay = document.getElementById('membership-expire-display');

        // 根据等级设置不同图标和名称
        let levelName = '普通用户';
        let levelIcon = '';
        let levelColor = '#666';

        switch (level) {
            case 1:
                levelName = '个人会员';
                levelIcon = '<i class="fas fa-crown" style="color: #FFD700; margin-right: 5px;"></i>';
                levelColor = '#FFD700';
                break;
            case 2:
                levelName = '团队会员';
                levelIcon = '<i class="fas fa-crown" style="color: #C0C0C0; margin-right: 5px;"></i>';
                levelColor = '#C0C0C0';
                break;
            default:
                levelName = '普通用户';
                levelIcon = '';
                levelColor = '#666';
        }

        // 设置会员等级显示
        levelDisplay.innerHTML = levelIcon + levelName;
        levelDisplay.style.color = levelColor;
        levelDisplay.style.fontWeight = 'bold';

        // 设置会员有效期显示
        if (expireDate) {
            expireDisplay.textContent = `有效期至: ${expireDate}`;
        } else {
            expireDisplay.textContent = '';
        }

        // 保存用户ID到全局变量，用于权限检查
        window.user_id = userData.id;

        // 设置所属剧组信息
        const crewResponse = await apiCall('auth_api.php?action=getUserCrewInfo');
        //console.log('所属剧组响应:', crewResponse);
        if (crewResponse.success && crewResponse.data) {
            const crewInfo = crewResponse.data;

            // 检查是否是数组（多个剧组）
            if (Array.isArray(crewInfo)) {
                // 多个剧组情况
                let crewHtml = '';
                crewInfo.forEach((crew) => {
                    crewHtml += `
                        <div class="crew-item" style="margin-bottom: 15px; padding: 10px; background-color: #f9f9f9; border-radius: 6px;">
                            <div style="display: flex; align-items: center;">
                                ${crew.is_current ? '<span style="margin-right: 5px; padding: 2px 6px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 3px; font-size: 12px; font-weight: 600;">当前</span>' : ''}
                                <span class="crew-name" style="font-weight: 600;">${crew.crew_name}</span>
                                ${crew.is_creator ? '<span style="margin-left: 5px; padding: 2px 6px; background-color: #4CAF50; color: white; border-radius: 3px; font-size: 12px;">创建者</span>' : ''}
                                ${crew.is_admin && !crew.is_creator ? '<span style="margin-left: 5px; padding: 2px 6px; background-color: #2196F3; color: white; border-radius: 3px; font-size: 12px;">管理员</span>' : ''}
                            </div>
                            ${!crew.is_creator ? `
                                <div class="crew-actions" style="margin-top: 5px;">
                                    <button class="btn btn-sm btn-secondary" onclick="leaveCrew(${crew.crew_id || ''})">
                                        <i class="fas fa-sign-out-alt"></i> 脱离剧组
                                    </button>
                                    <small style="color: #666; margin-left: 5px;">脱离后将无法查看该剧组资源</small>
                                </div>
                            ` : ''}
                        </div>
                    `;
                });
                document.getElementById('crew-name-display').innerHTML = crewHtml;
                document.getElementById('leave-crew-section').style.display = 'none';
            } else {
                // 单个剧组情况
                const crewNameHtml = crewInfo.is_current
                    ? `<span style="margin-right: 5px; padding: 2px 6px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; border-radius: 3px; font-size: 12px; font-weight: 600;">当前</span>${crewInfo.crew_name}`
                    : crewInfo.crew_name;
                document.getElementById('crew-name-display').innerHTML = crewNameHtml;
                // 剧组创建者和管理员无法脱离剧组
                if (crewInfo.is_creator || crewInfo.is_admin === 1) {
                    document.getElementById('leave-crew-section').style.display = 'none';
                } else {
                    document.getElementById('leave-crew-section').style.display = 'block';
                }
            }
        } else {
            document.getElementById('crew-name-display').textContent = '无';
            document.getElementById('leave-crew-section').style.display = 'none';
        }

        // 更新头像
        const avatarUrl = userData.avatar || 'assets/default-avatar.png';
        document.getElementById('avatar-img').src = avatarUrl;

        //console.log('用户资料加载完成:', userData);
    } catch (error) {
        //console.error('加载用户资料失败:', error);
        throw error;
    }
}


// 加载充值记录
async function loadRechargeRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.recharge;
        const response = await apiCall(`auth_api.php?action=getRechargeRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.recharge.totalItems = totalItems;
        paginationState.recharge.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('recharge-records-body');
        const paginationContainer = document.getElementById('recharge-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无充值记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保amount是数字类型
            const amount = parseFloat(record.amount) || 0.00;
            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>¥${amount.toFixed(2)}</td>
                    <td>${record.payment_method || '未知'}</td>
                    <td>${record.status === 'success' ? '成功' : '失败'}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'recharge',
            currentPage,
            paginationState.recharge.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载充值记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('recharge-records-body');
        const paginationContainer = document.getElementById('recharge-pagination');
        tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无充值记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}

// 加载消费记录
async function loadConsumptionRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.consumption;
        const response = await apiCall(`auth_api.php?action=getConsumptionRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.consumption.totalItems = totalItems;
        paginationState.consumption.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('consumption-records-body');
        const paginationContainer = document.getElementById('consumption-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无消费记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保amount是数字类型
            const amount = parseFloat(record.amount) || 0.00;
            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>¥${amount.toFixed(2)}</td>
                    <td>${record.consumption_type || '未知'}</td>
                    <td>${record.description || ''}</td>
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'consumption',
            currentPage,
            paginationState.consumption.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载消费记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('consumption-records-body');
        const paginationContainer = document.getElementById('consumption-pagination');
        tbody.innerHTML = '<tr><td colspan="4" class="no-data">暂无消费记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}

// 加载积分记录
async function loadPointsRecords() {
    try {
        const {
            currentPage,
            pageSize
        } = paginationState.points;
        const response = await apiCall(`auth_api.php?action=getPointsRecords&page=${currentPage}&pageSize=${pageSize}`);
        // 确保records是数组类型
        const records = Array.isArray(response.data) ? response.data : [];
        const totalItems = response.total || records.length;

        // 更新分页状态
        paginationState.points.totalItems = totalItems;
        paginationState.points.totalPages = Math.ceil(totalItems / pageSize);

        const tbody = document.getElementById('points-records-body');
        const paginationContainer = document.getElementById('points-pagination');

        if (records.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="no-data">暂无积分记录</td></tr>';
            paginationContainer.innerHTML = '';
            return;
        }

        let html = '';
        records.forEach(record => {
            // 确保points_change是数字类型
            const pointsChange = parseInt(record.points_change) || 0;
            const change = pointsChange > 0 ? `+${pointsChange}` : pointsChange;

            // 检查是否有content字段且包含图片信息
            const hasImageContent = record.content && (record.source === 'text2img' || record.source === 'image_generation');
            const operationBtn = hasImageContent ? `
                <td>
                    <button class="btn-primary btn-sm" onclick="viewGeneratedImage('${encodeURIComponent(record.content)}')">
                        <i class="fas fa-image"></i> 查看图片
                    </button>
                </td>
            ` : '<td>-</td>';

            html += `
                <tr>
                    <td>${record.created_at}</td>
                    <td>${change}</td>
                    <td>${record.source || '未知'}</td>
                    <td>${record.task_id || '-'}</td>
                    ${operationBtn}
                </tr>
            `;
        });

        tbody.innerHTML = html;

        // 渲染分页
        const paginationHTML = generatePaginationHTML(
            'points',
            currentPage,
            paginationState.points.totalPages
        );
        paginationContainer.innerHTML = paginationHTML;
    } catch (error) {
        //console.error('加载积分记录失败:', error);
        // 设置默认状态
        const tbody = document.getElementById('points-records-body');
        const paginationContainer = document.getElementById('points-pagination');
        tbody.innerHTML = '<tr><td colspan="5" class="no-data">暂无积分记录</td></tr>';
        paginationContainer.innerHTML = '';
    }
}

// 查看生成的图片
function viewGeneratedImage(contentJson) {
    try {
        const content = JSON.parse(decodeURIComponent(contentJson));
        const data = content.data;

        // 创建模态框
        const modal = document.createElement('div');
        modal.className = 'modal-overlay';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.8);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        `;

        // 创建模态框内容
        const modalContent = document.createElement('div');
        modalContent.style.cssText = `
            background: white;
            border-radius: 12px;
            padding: 20px;
            max-width: 90%;
            max-height: 90%;
            overflow: auto;
            position: relative;
        `;

        // 创建关闭按钮
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        `;
        closeBtn.onclick = () => {
            document.body.removeChild(modal);
        };

        // 创建图片容器
        const imageContainer = document.createElement('div');
        imageContainer.style.cssText = `
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 20px;
        `;

        // 处理图片显示
        if (data.imageUrl) {
            // 单张图片
            const img = document.createElement('img');
            img.src = data.imageUrl;
            img.alt = '生成的图片';
            img.style.cssText = `
                max-width: 100%;
                max-height: 60vh;
                border-radius: 8px;
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            `;
            imageContainer.appendChild(img);
        } else if (data.images && data.images.length > 0) {
            // 多张图片
            const grid = document.createElement('div');
            grid.style.cssText = `
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                width: 100%;
            `;

            data.images.forEach((image, index) => {
                const img = document.createElement('img');
                img.src = image.url;
                img.alt = `生成的图片 ${index + 1}`;
                img.style.cssText = `
                    width: 100%;
                    height: 200px;
                    object-fit: cover;
                    border-radius: 8px;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
                    cursor: pointer;
                    transition: transform 0.3s ease;
                `;
                img.onclick = () => {
                    // 点击放大查看
                    const fullSizeImg = document.createElement('img');
                    fullSizeImg.src = image.url;
                    fullSizeImg.alt = `生成的图片 ${index + 1}`;
                    fullSizeImg.style.cssText = `
                        max-width: 100%;
                        max-height: 80vh;
                        border-radius: 8px;
                        box-shadow: 0 8px 30px rgba(0, 0, 0, 0.4);
                    `;

                    // 创建放大查看模态框
                    const fullModal = document.createElement('div');
                    fullModal.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: rgba(0, 0, 0, 0.9);
                        z-index: 11000;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        padding: 20px;
                    `;
                    fullModal.onclick = () => {
                        document.body.removeChild(fullModal);
                    };

                    const fullContent = document.createElement('div');
                    fullContent.style.cssText = `
                        position: relative;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    `;

                    fullContent.appendChild(fullSizeImg);
                    fullModal.appendChild(fullContent);
                    document.body.appendChild(fullModal);
                };
                grid.appendChild(img);
            });
            imageContainer.appendChild(grid);
        } else {
            const noImageMsg = document.createElement('p');
            noImageMsg.textContent = '没有找到图片信息';
            noImageMsg.style.cssText = `
                color: var(--gray-color);
                font-size: 16px;
                text-align: center;
                padding: 40px;
            `;
            imageContainer.appendChild(noImageMsg);
        }

        // 创建关闭按钮
        const closeBtn2 = document.createElement('button');
        closeBtn2.textContent = '关闭';
        closeBtn2.style.cssText = `
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-top: 20px;
        `;
        closeBtn2.onclick = () => {
            document.body.removeChild(modal);
        };

        // 组装模态框
        modalContent.appendChild(closeBtn);
        modalContent.appendChild(imageContainer);
        modalContent.appendChild(closeBtn2);
        modal.appendChild(modalContent);

        // 添加到页面
        document.body.appendChild(modal);

        // 点击模态框背景关闭
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                document.body.removeChild(modal);
            }
        });

        // ESC键关闭
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.body.removeChild(modal);
            }
        });
    } catch (error) {
        //console.error('查看图片失败:', error);
        alert('查看图片失败，请稍后重试');
    }
}

// 全局变量存储任务数据
let allTasks = [];
let currentTaskNumber = '';
let currentTask = {};

// 加载历史任务
async function loadTasks() {
    try {
        //console.log('开始加载历史任务...');

        // 检查用户是否已登录
        if (!user || Object.keys(user).length === 0) {
            //console.warn('用户未登录，无法加载历史任务');
            const tasksTableBody = document.getElementById('tasks-records-body');
            const taskNumbersList = document.getElementById('task-numbers-list');
            if (tasksTableBody) {
                tasksTableBody.innerHTML = '<tr><td colspan="10" class="no-data">请先登录以查看历史任务</td></tr>';
            }
            if (taskNumbersList) {
                taskNumbersList.innerHTML = '<div class="no-data">请先登录以查看任务列表</div>';
            }
            return;
        }

        // 构建API请求URL，获取所有任务
        const apiUrl = `auth_api.php?action=getUserTasks`;
        //console.log('正在加载历史任务，请求URL:', apiUrl);

        // 调用API获取所有任务
        const response = await apiCall(apiUrl);
        //console.log('API响应:', response);

        // 检查响应是否有效
        if (!response || typeof response !== 'object') {
            throw new Error('无效的响应数据');
        }

        // 检查API调用是否成功
        if (!response.success) {
            //console.warn('API调用失败:', response.message);
            // 显示错误信息
            const tasksTableBody = document.getElementById('tasks-records-body');
            const taskNumbersList = document.getElementById('task-numbers-list');
            if (tasksTableBody) {
                tasksTableBody.innerHTML = `<tr><td colspan="9" class="no-data">加载失败: ${response.message || '未知错误'}</td></tr>`;
            }
            if (taskNumbersList) {
                taskNumbersList.innerHTML = `<div class="no-data">加载失败: ${response.message || '未知错误'}</div>`;
            }
            return;
        }

        // 确保响应数据是数组
        allTasks = Array.isArray(response.data) ? response.data : [];

        //console.log('获取到的任务数据:', allTasks);
        //console.log('总任务数:', allTasks.length);

        // 从服务器获取当前任务号
        await fetchCurrentTaskNumber();

        // 生成任务号列表
        generateTaskNumbersList();

        // 加载初始任务列表
        loadTasksByTaskNumber(currentTaskNumber);

        //console.log('历史任务加载完成');
    } catch (error) {
        //console.error('加载历史任务失败:', error);
    }
}

// 从服务器获取当前任务号
async function fetchCurrentTaskNumber() {
    try {
        //console.log('开始获取当前任务号...');

        // 构建API请求URL
        const apiUrl = `auth_api.php?action=getCurrentTaskNumber`;
        //console.log('正在获取当前任务号，请求URL:', apiUrl);

        // 调用API获取当前任务号
        const response = await apiCall(apiUrl);
        //console.log('获取当前任务号API响应:', response);

        // 检查响应是否有效
        if (response && response.success && response.data) {
            const serverCurrentTask = response.data;
            //console.log('从服务器获取的当前任务号:', serverCurrentTask);

            // 更新currentTask对象
            currentTask = {
                taskNumber: serverCurrentTask
            };

            // 保存到localStorage，用于优化用户体验
            const localStorageKey = 'user_' + window.user_id + '_currentTask';
            localStorage.setItem(localStorageKey, JSON.stringify(currentTask));

            // 检查是否有当前任务号
            if (empty(currentTask.taskNumber)) {
                // 检查用户是否有剧组（通过会话状态判断）
                const hasCrew = (window.user && (window.user.is_crew_admin || window.user.is_crew_member)) ||
                    (window.sessionStorage && window.sessionStorage.getItem('is_crew_admin') === 'true') ||
                    (window.sessionStorage && window.sessionStorage.getItem('is_crew_member') === 'true');
                if (!hasCrew) {
                    // 显示提示信息：用户需要有剧组才能激活当前任务
                    showNotification('你首先需要有个剧组，才能激活当前任务，您所创建的剧组，您就是大总管了！', 'info');
                }
            }
        } else {
            //console.warn('获取当前任务号失败，使用localStorage中的值');
            // 如果从服务器获取失败，使用localStorage中的值
            try {
                const localStorageKey = 'user_' + window.user_id + '_currentTask';
                currentTask = JSON.parse(localStorage.getItem(localStorageKey) || '{}');
            } catch (parseError) {
                //console.warn('解析当前任务信息失败:', parseError);
                currentTask = {};
            }

            // 检查localStorage中是否有任务号
            if (empty(currentTask.taskNumber)) {
                // 显示提示信息：用户需要有剧组才能激活当前任务
                showNotification('你首先需要有个剧组，才能激活当前任务，您所创建的剧组，您就是大总管了！', 'info');
            }
        }
    } catch (error) {
        //console.error('获取当前任务号失败:', error);
        // 如果获取失败，使用localStorage中的值
        try {
            const localStorageKey = 'user_' + window.user_id + '_currentTask';
            currentTask = JSON.parse(localStorage.getItem(localStorageKey) || '{}');
        } catch (parseError) {
            //console.warn('解析当前任务信息失败:', parseError);
            currentTask = {};
        }

        // 检查是否有当前任务号
        if (empty(currentTask.taskNumber)) {
            // 显示提示信息：用户需要有剧组才能激活当前任务
            showNotification('你首先需要有个剧组，才能激活当前任务，您所创建的剧组，您就是大总管了！', 'info');
        }
    }
}

// 检查字符串是否为空（包括空格）
function empty(value) {
    return value === undefined || value === null || value === '' || value.trim() === '';
}


// 生成任务号列表
function generateTaskNumbersList() {
    const taskNumbersList = document.getElementById('task-numbers-list');
    if (!taskNumbersList) {
        //console.error('任务号列表容器不存在');
        return;
    }

    if (allTasks.length === 0) {
        taskNumbersList.innerHTML = '<div class="no-data">暂无任务号</div>';
        return;
    }

    // 提取唯一任务号
    const taskNumbers = [...new Set(allTasks.map(task => task.task_id).filter(Boolean))];

    // 获取当前任务号
    const isDemoUser = user && user.username === 'demo';
    let currentTaskNumberValue = currentTask.taskNumber || '';

    if (isDemoUser && taskNumbers.length > 0 && !currentTaskNumberValue) {
        currentTaskNumberValue = taskNumbers[0];
        // 保存到localStorage
        const localStorageKey = 'user_' + window.user_id + '_currentTask';
        localStorage.setItem(localStorageKey, JSON.stringify({
            taskNumber: taskNumbers[0]
        }));
        currentTask = {
            taskNumber: taskNumbers[0]
        };
    }

    let html = '';

    // 添加"所有任务"选项
    html += `
        <div class="task-number-item ${currentTaskNumber === '' ? 'active' : ''}" data-task-number="">
            <span>所有任务</span>
            ${currentTaskNumberValue === '' ? '<span class="current-task-badge">当前</span>' : ''}
        </div>
    `;

    // 添加每个任务号
    taskNumbers.forEach(taskNumber => {
        const isCurrentTask = currentTaskNumberValue === taskNumber;
        const isActive = currentTaskNumber === taskNumber;

        // 只显示script_analysis_后面的部分
        let displayTaskNumber = taskNumber;
        if (displayTaskNumber.startsWith('script_analysis_')) {
            displayTaskNumber = displayTaskNumber.substring(16); // 16是'script_analysis_'的长度
        }

        html += `
            <div class="task-number-item ${isActive ? 'active' : ''} ${isCurrentTask ? 'current' : ''}" data-task-number="${taskNumber}">
                <span>${displayTaskNumber}</span>
                ${isCurrentTask ? '<span class="current-task-badge">当前</span>' : ''}
            </div>
        `;
    });

    taskNumbersList.innerHTML = html;

    // 添加任务号点击事件监听器
    const taskNumberItems = document.querySelectorAll('.task-number-item');
    taskNumberItems.forEach(item => {
        item.addEventListener('click', handleTaskNumberClick);
    });
}

// 处理任务号点击事件
function handleTaskNumberClick(event) {
    const taskNumber = event.currentTarget.dataset.taskNumber;
    currentTaskNumber = taskNumber;

    // 更新标题
    const selectedTaskNumberTitle = document.getElementById('selected-task-number-title');
    if (selectedTaskNumberTitle) {
        selectedTaskNumberTitle.textContent = taskNumber ? `任务: ${taskNumber}` : '所有任务';
    }

    // 更新激活状态
    const taskNumberItems = document.querySelectorAll('.task-number-item');
    taskNumberItems.forEach(item => {
        item.classList.remove('active');
    });
    event.currentTarget.classList.add('active');

    // 加载对应任务号的任务
    loadTasksByTaskNumber(taskNumber);
}

// 按任务号加载任务
function loadTasksByTaskNumber(taskNumber) {
    const tasksTableBody = document.getElementById('tasks-records-body');
    const tasksTableHeader = document.querySelector('.record-table thead tr');
    if (!tasksTableBody || !tasksTableHeader) {
        //console.error('任务表格元素不存在');
        return;
    }

    // 更新当前任务号变量
    currentTaskNumber = taskNumber;

    // 更新标题
    const selectedTaskNumberTitle = document.getElementById('selected-task-number-title');
    if (selectedTaskNumberTitle) {
        selectedTaskNumberTitle.textContent = taskNumber ? `任务: ${taskNumber}` : '所有任务';
    }

    // 动态更新表格表头
    if (taskNumber === '') {
        // 显示所有任务时，隐藏"当前任务"和"操作"列
        tasksTableHeader.innerHTML = `
            <th>序号</th>
            <th>类型</th>
            <th>标题</th>
            <th>结果</th>
            <th>状态</th>
            <th>进度</th>
            <th>创建时间</th>
        `;
    } else {
        // 显示特定任务时，显示所有列
        tasksTableHeader.innerHTML = `
            <th>序号</th>
            <th>类型</th>
            <th>标题</th>
            <th>结果</th>
            <th>状态</th>
            <th>进度</th>
            <th>创建时间</th>
            <th>当前任务</th>
            <th>操作</th>
        `;
    }

    // 任务类型映射，对应六类任务
    const taskTypeMap = {
        // 小说类任务
        'novel': '小说',
        'novel_to_script': '小说',
        // 剧本类任务
        'script': '剧本',
        'script_to_storyboard': '剧本',
        'script_analysis': '剧本',
        // 分镜图类任务
        'storyboard': '分镜图',
        'storyboard_management': '分镜图',
        'shooting_plan': '分镜图',
        'shooting_notice': '分镜图',
        // 图片类任务
        'text_to_image': '图片',
        // 分镜视频类任务
        'storyboard_video': '分镜视频',
        // 视频类任务
        'text2img_batch': '文生图',
        'text2img': '文生图',
        'img2video': '图生视频',
        'video': '视频',
        // 其他任务
        'other': '其他'
    };

    // 过滤任务
    let filteredTasks = allTasks;
    if (taskNumber) {
        filteredTasks = allTasks.filter(task => task.task_id === taskNumber);
    }

    if (filteredTasks.length === 0) {
        // 如果没有任务，显示提示信息，根据是否显示所有任务调整colspan
        const colspan = taskNumber === '' ? 6 : 9;
        tasksTableBody.innerHTML = `<tr><td colspan="${colspan}" class="no-data">暂无任务</td></tr>`;
        return;
    }

    let html = '';
    const isDemoUser = user && user.username === 'demo';

    // 获取当前任务号（从localStorage中获取，最终以crew表中的为准）
    let currentTaskNumberValue = currentTask.taskNumber || '';

    // 对于demo用户，我们将第一个任务的任务号设为当前任务号
    if (isDemoUser && filteredTasks.length > 0 && filteredTasks[0].task_id && !currentTaskNumberValue) {
        currentTaskNumberValue = filteredTasks[0].task_id;
        // 保存到localStorage
        const localStorageKey = 'user_' + window.user_id + '_currentTask';
        localStorage.setItem(localStorageKey, JSON.stringify({
            taskNumber: filteredTasks[0].task_id
        }));
        currentTask = {
            taskNumber: filteredTasks[0].task_id
        };
    }

    // 基于所有任务确定是否有当前任务
    let hasCurrentTask = false;
    let selectedTaskNumber = '';

    if (filteredTasks.length > 0) {
        selectedTaskNumber = filteredTasks[0].task_id || '';

        // 检查是否有当前任务
        hasCurrentTask = filteredTasks.some(task => task.task_id === currentTaskNumberValue);
    }

    // 生成表格行
    if (filteredTasks.length > 0) {
        // 添加表格行，最后一列合并
        filteredTasks.forEach((task, index) => {
            // 确保任务对象存在
            if (!task || typeof task !== 'object') {
                //console.warn('无效的任务数据:', task);
                return;
            }

            // 基于任务号判断是否为当前任务
            let isCurrentTask = task.task_id === currentTaskNumberValue;

            // 获取任务状态文本和样式
            let statusText = '未知';
            let statusClass = '';
            if (task.status === 2) {
                statusText = '已完成';
                statusClass = 'success';
            } else if (task.status === 1) {
                statusText = '进行中';
                statusClass = 'pending';
            } else if (task.status === 0) {
                statusText = '失败';
                statusClass = 'failed';
            }
            if (task.output_data == null) {
                statusText = '失败';
                statusClass = 'failed';
            }

            // 任务类型中文名称，映射到六类任务
            const taskTypeName = taskTypeMap[task.task_type] || '其他';
            let datas = {};
            try {
                // 直接检查是否为URL，如果是则跳过JSON解析
                let outputDataStr = String(task.output_data);
                let cleanUrl = outputDataStr.trim();

                // 去除所有可能的包装字符：反引号、单引号、双引号
                cleanUrl = cleanUrl.replace(/^[`'"\s]+|[`'"\s]+$/g, '');

                if (cleanUrl.startsWith('http')) {
                    // 如果是URL，直接处理为媒体预览，跳过JSON解析
                    const isVideo = cleanUrl.match(/\.(mp4|avi|mov|wmv|flv|webm)$/i);
                    if (isVideo) {
                        datas = {
                            video_url: cleanUrl
                        };
                    } else {
                        datas = {
                            imageUrl: cleanUrl
                        };
                    }
                } else {
                    // 不是URL，尝试JSON解析
                    let cleanOutputData = outputDataStr.trim();
                    // 去除所有可能的包装字符
                    cleanOutputData = cleanOutputData.replace(/^[`'"\s]+|[`'"\s]+$/g, '');

                    const parsedData = JSON.parse(cleanOutputData);
                    // 确保parsedData不是null或undefined，否则使用空对象
                    datas = parsedData !== null && parsedData !== undefined ? parsedData : {};
                }
            } catch (parseError) {
                //console.warn('无效的output_data JSON格式:', task.output_data, parseError);
                // 处理非JSON格式的数据，例如直接作为URL
                try {
                    let mediaUrl = String(task.output_data);
                    // 去除所有可能的包装字符
                    mediaUrl = mediaUrl.replace(/^[`'"\s]+|[`'"\s]+$/g, '');

                    if (mediaUrl.startsWith('http')) {
                        // 判断是图片还是视频，优先根据文件扩展名
                        const isVideo = mediaUrl.match(/\.(mp4|avi|mov|wmv|flv|webm)$/i);
                        if (isVideo) {
                            datas = {
                                video_url: mediaUrl
                            };
                        } else {
                            datas = {
                                imageUrl: mediaUrl
                            };
                        }
                    }
                } catch (e) {
                    //console.warn('处理URL失败:', e);
                    datas = {};
                }
            }


            // 生成媒体预览HTML
            function generateMediaPreview(imageUrl, videoUrl) {
                let mediaUrl = imageUrl || videoUrl;
                if (!mediaUrl) {
                    return '';
                }

                // 判断是图片还是视频
                const isVideo = videoUrl || mediaUrl.match(/\.(mp4|avi|mov|wmv|flv|webm)$/i);

                if (isVideo) {
                    // 视频预览
                    return `
                        <div class="media-preview" style="width: 120px; height: 80px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <video width="100%" height="100%" controls style="object-fit: cover;">
                                <source src="${videoUrl || mediaUrl}" type="video/mp4">
                                您的浏览器不支持视频播放
                            </video>
                        </div>
                    `;
                } else {
                    // 图片预览
                    return `
                        <div class="media-preview" style="width: 120px; height: 80px; overflow: hidden; display: flex; align-items: center; justify-content: center;">
                            <img src="${imageUrl || mediaUrl}" width="100%" height="100%" style="object-fit: cover;" alt="预览图">
                        </div>
                    `;
                }
            }

            // 生成结果HTML
            const mediaPreview = generateMediaPreview(datas.imageUrl, datas.video_url);
            const resultContent = mediaPreview || (datas.imageUrl || datas.video_url || '');

            // 第一行
            if (index === 0) {
                // 显示所有任务时不显示操作按钮
                if (currentTaskNumber === '') {
                    html += `
                        <tr id="task-${task.id || index}">
                            <td>${task.id || index + 1}</td>
                            <td>${taskTypeName}</td>
                            <td>${task.title || '未命名任务'}</td>
                            <td>${resultContent}</td>
                            <td><span class="task-status ${statusClass}">${statusText}</span></td>
                            <td>${task.progress || 0}%</td>
                            <td>${task.created_at || '未知时间'}</td>
                        </tr>
                    `;
                } else {
                    html += `
                        <tr id="task-${task.id || index}">
                            <td>${task.id || index + 1}</td>
                            <td>${taskTypeName}</td>
                            <td>${task.title || '未命名任务'}</td>
                            <td>${resultContent}</td>
                            <td><span class="task-status ${statusClass}">${statusText}</span></td>
                            <td>${task.progress || 0}%</td>
                            <td>${task.created_at || '未知时间'}</td>
                            <td>${isCurrentTask ? '<span class="current-task-badge">是</span>' : '否'}</td>
                            <td rowspan="${filteredTasks.length}" class="merged-action-cell">
                                <button class="btn-set-current ${hasCurrentTask ? 'current' : ''}" data-task-number="${selectedTaskNumber}">
                                    ${hasCurrentTask ? '当前任务' : '设为当前任务'}
                                </button>
                            </td>
                        </tr>
                    `;
                }
            } else {
                // 其他行
                if (currentTaskNumber === '') {
                    html += `
                        <tr id="task-${task.id || index}">
                            <td>${task.id || index + 1}</td>
                            <td>${taskTypeName}</td>
                            <td>${task.title || '未命名任务'}</td>
                            <td>${resultContent}</td>
                            <td><span class="task-status ${statusClass}">${statusText}</span></td>
                            <td>${task.progress || 0}%</td>
                            <td>${task.created_at || '未知时间'}</td>
                        </tr>
                    `;
                } else {
                    html += `
                        <tr id="task-${task.id || index}">
                            <td>${task.id || index + 1}</td>
                            <td>${taskTypeName}</td>
                            <td>${task.title || '未命名任务'}</td>
                            <td>${resultContent}</td>
                            <td><span class="task-status ${statusClass}">${statusText}</span></td>
                            <td>${task.progress || 0}%</td>
                            <td>${task.created_at || '未知时间'}</td>
                            <td>${isCurrentTask ? '<span class="current-task-badge">是</span>' : '否'}</td>
                        </tr>
                    `;
                }
            }
        });
    }

    // 更新任务表格
    tasksTableBody.innerHTML = html;

    // 添加"设为当前任务"按钮的点击事件监听器
    const setCurrentButtons = document.querySelectorAll('.btn-set-current');
    setCurrentButtons.forEach(button => {
        button.addEventListener('click', handleSetCurrentTask);
    });
}

// 处理设为当前任务的函数
async function handleSetCurrentTask(event) {
    const taskNumber = event.target.dataset.taskNumber;

    try {
        // 调用API更新crew表中的current_task_id
        const apiUrl = `auth_api.php?action=setCurrentTask&taskNumber=${encodeURIComponent(taskNumber)}`;
        const response = await apiCall(apiUrl);

        if (response && response.success) {
            //console.log('API更新当前任务成功:', response);
        } else {
            //console.warn('API更新当前任务失败:', response);
        }
    } catch (error) {
        //console.error('更新当前任务失败:', error);
    }

    // 保存当前任务号到localStorage，不需要保存具体的任务ID和类型
    const newCurrentTask = {
        taskNumber: taskNumber
    };
    const localStorageKey = 'user_' + window.user_id + '_currentTask';
    localStorage.setItem(localStorageKey, JSON.stringify(newCurrentTask));
    currentTask = newCurrentTask;

    // 重新加载任务列表以显示当前任务高亮
    loadTasks();

    // 显示成功提示
    showNotification('已成功设为当前任务', 'success');
}

// 显示通知
function showNotification(message, type = 'info') {
    // 创建通知元素
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;

    // 添加样式
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        border-radius: 8px;
        color: white;
        font-weight: 600;
        z-index: 10000;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        opacity: 0;
        transform: translateY(-20px);
        transition: all 0.3s ease;
    `;

    // 根据类型设置背景色
    if (type === 'success') {
        notification.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
    } else if (type === 'error') {
        notification.style.background = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
    } else {
        notification.style.background = 'linear-gradient(135deg, #667eea 0%, #5a67d8 100%)';
    }

    // 添加到页面
    document.body.appendChild(notification);

    // 显示动画
    setTimeout(() => {
        notification.style.opacity = '1';
        notification.style.transform = 'translateY(0)';
    }, 100);

    // 自动隐藏
    setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateY(-20px)';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 3000);
}


// 导航折叠/展开功能

// 移动端导航切换（悬浮按钮）
function toggleNavToggle() {
    const nav = document.querySelector('.user-nav');
    const toggleBtn = document.querySelector('.nav-toggle-btn');

    if (nav && toggleBtn) {
        nav.classList.toggle('expanded');
        toggleBtn.classList.toggle('expanded');

        // 切换按钮图标
        const icon = toggleBtn.querySelector('i');
        if (icon) {
            if (nav.classList.contains('expanded')) {
                icon.className = 'fas fa-times';
            } else {
                icon.className = 'fas fa-bars';
            }
        }
    }
}

// 非移动端导航折叠（侧边按钮）
function toggleNavCollapse() {
    const nav = document.querySelector('.user-nav');
    if (nav) {
        nav.classList.toggle('collapsed');

        // 切换按钮图标
        const btn = nav.querySelector('.nav-collapse-btn');
        const icon = btn ? btn.querySelector('i') : null;
        if (icon) {
            if (nav.classList.contains('collapsed')) {
                icon.className = 'fas fa-chevron-left';
            } else {
                icon.className = 'fas fa-chevron-right';
            }
        }
    }
}

// 切换到组织架构标签页
function switchToOrganizationTab() {
    // 移除所有激活状态
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');

    navItems.forEach(nav => nav.classList.remove('active'));
    tabContents.forEach(content => content.classList.remove('active'));

    // 激活组织架构标签
    const organizationNav = document.querySelector('.nav-item[data-tab="organization"]');
    const organizationContent = document.getElementById('organization');

    if (organizationNav) {
        organizationNav.classList.add('active');
    }

    if (organizationContent) {
        organizationContent.classList.add('active');
        // 初始化组织管理数据
        initOrganizationManagement();
    }

    // 移动端导航自动收起
    if (window.innerWidth <= 1024) {
        toggleNavToggle();
    }
}

// 标签页切换功能
function initTabSwitching() {
    const navItems = document.querySelectorAll('.nav-item');
    const tabContents = document.querySelectorAll('.tab-content');

    navItems.forEach(item => {
        item.addEventListener('click', function (e) {
            // 检查是否有data-tab属性，如果没有则是外部链接，允许默认跳转
            if (this.hasAttribute('data-tab')) {
                e.preventDefault();

                // 移除所有激活状态
                navItems.forEach(nav => nav.classList.remove('active'));
                tabContents.forEach(content => content.classList.remove('active'));

                // 添加当前激活状态
                this.classList.add('active');
                const tabId = this.dataset.tab;
                const tabElement = document.getElementById(tabId);
                if (tabElement) {
                    tabElement.classList.add('active');
                }

                // 如果是组织架构标签页，初始化其内部数据
                if (tabId === 'organization') {
                    initOrganizationManagement();
                }

                // 移动端导航点击后自动收起
                if (window.innerWidth <= 1024) {
                    toggleNavToggle();
                }
            }
            // 否则，不阻止默认行为，允许链接正常跳转
        });
    });
}

// 余额标签页切换功能
function initBalanceTabSwitching() {
    const balanceTabs = document.querySelectorAll('.balance-tab');
    const recordLists = document.querySelectorAll('.record-list');

    balanceTabs.forEach(tab => {
        tab.addEventListener('click', function () {
            // 移除所有激活状态
            balanceTabs.forEach(t => t.classList.remove('active'));
            recordLists.forEach(list => list.classList.remove('active'));

            // 添加当前激活状态
            this.classList.add('active');
            const tabId = this.dataset.balanceTab;
            const recordElement = document.getElementById(tabId + '-records');
            if (recordElement) {
                recordElement.classList.add('active');
            }
        });
    });
}

// 分页状态管理
const paginationState = {
    // 余额记录（充值记录）
    recharge: {
        currentPage: 1,
        pageSize: 10,
        totalItems: 0,
        totalPages: 0
    },
    // 余额记录（消费记录）
    consumption: {
        currentPage: 1,
        pageSize: 10,
        totalItems: 0,
        totalPages: 0
    },
    // 积分记录
    points: {
        currentPage: 1,
        pageSize: 10,
        totalItems: 0,
        totalPages: 0
    },
    // 历史任务
    tasks: {
        currentPage: 1,
        pageSize: 10,
        totalItems: 0,
        totalPages: 0
    }
};

// 生成分页HTML
function generatePaginationHTML(type, currentPage, totalPages) {
    if (totalPages <= 1) {
        return '<div class="pagination"><div class="pagination-info"><span>共 1 页</span></div></div>';
    }

    let html = `
        <div class="pagination">
            <div class="pagination-info">
                <div class="page-size-selector">
                    <span>每页显示：</span>
                    <select onchange="changePageSize('${type}', this.value)">
                        <option value="10" ${paginationState[type].pageSize === 10 ? 'selected' : ''}>10条</option>
                        <option value="20" ${paginationState[type].pageSize === 20 ? 'selected' : ''}>20条</option>
                        <option value="50" ${paginationState[type].pageSize === 50 ? 'selected' : ''}>50条</option>
                    </select>
                </div>
                <span>共 ${totalPages} 页</span>
            </div>
            <div class="pagination-controls">
                <button class="page-btn" onclick="goToPage('${type}', ${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>
                    <i class="fas fa-chevron-left"></i>
                </button>
    `;

    // 生成页码按钮
    const startPage = Math.max(1, currentPage - 2);
    const endPage = Math.min(totalPages, startPage + 4);

    if (startPage > 1) {
        html += '<button class="page-btn" onclick="goToPage(\'' + type + '\', 1)">1</button>';
        if (startPage > 2) {
            html += '<span class="page-btn ellipsis">...</span>';
        }
    }

    for (let i = startPage; i <= endPage; i++) {
        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" onclick="goToPage('${type}', ${i})"></button>`;
    }

    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            html += '<span class="page-btn ellipsis">...</span>';
        }
        html += '<button class="page-btn" onclick="goToPage(\'' + type + '\', ' + totalPages + ')">' + totalPages + '</button>';
    }

    html += `
                <button class="page-btn" onclick="goToPage('${type}', ${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
    `;

    return html;
}

// 跳转到指定页码
function goToPage(type, page) {
    if (page < 1 || page > paginationState[type].totalPages) {
        return;
    }

    paginationState[type].currentPage = page;

    // 根据类型重新加载数据
    switch (type) {
        case 'recharge':
            loadRechargeRecords();
            break;
        case 'consumption':
            loadConsumptionRecords();
            break;
        case 'points':
            loadPointsRecords();
            break;
        case 'tasks':
            loadTasks();
            break;
    }
}

// 改变每页显示条数
function changePageSize(type, pageSize) {
    paginationState[type].pageSize = parseInt(pageSize);
    paginationState[type].currentPage = 1;

    // 根据类型重新加载数据
    switch (type) {
        case 'recharge':
            loadRechargeRecords();
            break;
        case 'consumption':
            loadConsumptionRecords();
            break;
        case 'points':
            loadPointsRecords();
            break;
        case 'tasks':
            loadTasks();
            break;
    }
}

// 任务筛选功能
function initTaskFiltering() {
    const filterBtns = document.querySelectorAll('.filter-btn');

    filterBtns.forEach(btn => {
        btn.addEventListener('click', function () {
            // 移除所有激活状态
            filterBtns.forEach(b => b.classList.remove('active'));

            // 添加当前激活状态
            this.classList.add('active');

            // 筛选逻辑（模拟）
            const filter = this.dataset.filter;
            //console.log('筛选任务:', filter);
            // 实际项目中这里会调用API获取筛选后的任务

            // 重置当前页码
            paginationState.tasks.currentPage = 1;
            // 更新任务列表显示
            loadTasks();
        });
    });
}

// 昵称编辑功能
function toggleNicknameEdit() {
    const editForm = document.getElementById('nickname-edit-form');
    const nicknameDisplay = document.getElementById('nickname-display');
    const editBtn = document.querySelector('.btn-edit');

    if (editForm.style.display === 'flex') {
        editForm.style.display = 'none';
        nicknameDisplay.style.display = 'inline';
        editBtn.style.display = 'inline';
    } else {
        editForm.style.display = 'flex';
        nicknameDisplay.style.display = 'none';
        editBtn.style.display = 'none';
        document.getElementById('nickname-input').focus();
    }
}

// 保存昵称
async function saveNickname() {
    const input = document.getElementById('nickname-input');
    const newNickname = input.value.trim();

    if (!newNickname) {
        alert('昵称不能为空');
        return;
    }

    try {
        // 调用API保存昵称
        await apiCall('auth_api.php?action=updateNickname', 'POST', {
            nickname: newNickname
        });

        // 更新页面显示
        document.getElementById('nickname-display').textContent = newNickname;
        toggleNicknameEdit();

        alert('昵称修改成功');
    } catch (error) {
        //console.error('保存昵称失败:', error);
    }
}

function cancelNicknameEdit() {
    toggleNicknameEdit();
}

// 密码重置功能
function showResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'flex';
}

function hideResetPasswordModal() {
    document.getElementById('resetPasswordModal').style.display = 'none';
    document.getElementById('resetPasswordForm').reset();
}

// 重置密码表单提交
document.addEventListener('DOMContentLoaded', function () {
    //checkLoginStatusWhenReady();

    // 处理从wx_auth_callback.php重定向回来的授权参数，自动继续支付流程
    async function handleAuthCallback() {
        const urlParams = new URLSearchParams(window.location.search);
        const authType = urlParams.get('auth_type');
        const orderNo = urlParams.get('order_no');
        const amount = urlParams.get('amount');
        const extra = urlParams.get('extra');

        if (authType) {
            //console.log('处理微信授权回调，authType:', authType, 'orderNo:', orderNo, 'amount:', amount, 'extra:', extra);

            // 保存授权信息到sessionStorage，供后续支付使用
            sessionStorage.setItem('wx_auth_type', authType);
            if (orderNo) sessionStorage.setItem('wx_order_no', orderNo);
            if (amount) sessionStorage.setItem('wx_amount', amount);
            if (extra) sessionStorage.setItem('wx_extra', extra);

            // 获取当前用户的openid（从全局用户对象获取）
            let openid = user.openid;

            // 尝试从session获取openid
            if (!openid) {
                const localStorageKey = 'user_' + window.user_id + '_wx_openid';
                openid = sessionStorage.getItem('wx_openid') || localStorage.getItem(localStorageKey);
            }

            // 如果有openid，根据auth_type自动继续支付流程
            if (openid) {
                if (authType === 'recharge') {
                    //console.log('充值授权完成，准备自动进行支付');

                    // 设置选中的充值选项
                    selectedAmount = parseFloat(amount);
                    selectedPoints = parseInt(extra);

                    // 直接调用微信支付API
                    try {
                        await proceedWithRechargePayment(openid, orderNo, selectedAmount, selectedPoints);
                    } catch (error) {
                        //console.error('自动充值支付失败:', error);
                        showToast('支付失败，请重试', 'error');
                    }
                } else if (authType === 'vip') {
                    //console.log('会员购买授权完成，准备自动进行支付');

                    // 设置选中的会员选项
                    selectedVipOption = {
                        amount: parseFloat(amount),
                        level: parseInt(extra)
                    };
                    selectedVipAmount = parseFloat(amount);
                    selectedVipLevel = parseInt(extra);

                    // 直接调用微信支付API
                    try {
                        await proceedWithVipPayment(openid, orderNo, selectedVipOption);
                    } catch (error) {
                        //console.error('自动会员支付失败:', error);
                        showToast('支付失败，请重试', 'error');
                    }
                }
            } else {
                //console.error('没有获取到openid，无法继续支付流程');
                showToast('获取用户信息失败，请重试', 'error');
            }

            // 清理URL参数，避免刷新页面时重复处理
            const cleanUrl = window.location.origin + window.location.pathname;
            window.history.replaceState({}, document.title, cleanUrl);
        }
    }

    // 充值支付流程
    async function proceedWithRechargePayment(openid, orderNo, amount, points) {
        //console.log('开始充值支付流程，openid:', openid, 'orderNo:', orderNo, 'amount:', amount, 'points:', points);

        try {
            // 调用微信支付API
            const response = await fetch('pay.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    openid: openid,
                    amount: amount * 100, // 转换为分
                    order_no: orderNo,
                    body: `充值 ${points} 积分`,
                    attach: 'recharge'
                })
            });

            const result = await response.json();

            if (result.code === 1) {
                // 调用微信JSAPI支付
                WeixinJSBridge.invoke(
                    'getBrandWCPayRequest',
                    result.data.pay_params,
                    function (res) {
                        if (res.err_msg === 'get_brand_wcpay_request:ok') {
                            // 支付成功
                            showToast('充值成功！', 'success');
                            hideRechargeModal();
                            // 延迟刷新页面，让用户有足够时间看到提示
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
                        } else {
                            // 支付失败
                            showToast('充值失败：' + res.err_msg, 'error');
                        }
                    }
                );
            } else {
                showToast('创建支付订单失败：' + result.msg, 'error');
            }
        } catch (error) {
            //console.error('充值支付失败:', error);
            throw error;
        }
    }

    // 会员支付流程
    async function proceedWithVipPayment(openid, orderNo, vipOption) {
        //console.log('开始会员支付流程，openid:', openid, 'orderNo:', orderNo, 'vipOption:', vipOption);

        try {
            // 调用微信支付API
            const response = await fetch('pay.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    openid: openid,
                    amount: vipOption.amount * 100, // 转换为分
                    order_no: orderNo,
                    body: '购买会员服务',
                    attach: `membership_${vipOption.level}_${vipOption.duration}`
                })
            });

            const result = await response.json();

            if (result.code === 1) {
                // 调用微信JSAPI支付
                WeixinJSBridge.invoke(
                    'getBrandWCPayRequest',
                    result.data.pay_params,
                    function (res) {
                        if (res.err_msg === 'get_brand_wcpay_request:ok') {
                            // 支付成功
                            showToast('购买成功！', 'success');
                            hideVipModal();
                            // 延迟刷新页面，让用户有足够时间看到提示
                            setTimeout(() => {
                                window.location.reload();
                            }, 2000);
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
            //console.error('会员支付失败:', error);
            throw error;
        }
    }

    // 调用授权回调处理函数
    handleAuthCallback();

    // 初始化功能
    initTabSwitching();
    initBalanceTabSwitching();
    initTaskFiltering();

    // 初始化组织架构管理，确保剧组列表默认加载数据
    initOrganizationManagement();

    // 初始化页面数据
    initPage();

    // 密码重置表单提交处理
    const resetPasswordForm = document.getElementById('resetPasswordForm');
    if (resetPasswordForm) {
        resetPasswordForm.addEventListener('submit', async function (e) {
            e.preventDefault();

            const newPassword = document.getElementById('user-new-password').value;
            const confirmPassword = document.getElementById('user-confirm-password').value;

            if (!newPassword) {
                alert('新密码不能为空');
                return;
            }

            if (newPassword !== confirmPassword) {
                alert('两次输入的密码不一致');
                return;
            }

            try {
                // 调用API重置密码
                await apiCall('auth_api.php?action=resetPassword', 'POST', {
                    new_password: newPassword
                });

                hideResetPasswordModal();
                alert('密码重置成功');
            } catch (error) {
                //console.error('重置密码失败:', error);
            }
        });
    }

    // 点击模态框外部关闭
    const modal = document.getElementById('resetPasswordModal');
    if (modal) {
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                hideResetPasswordModal();
            }
        });
    }
});
