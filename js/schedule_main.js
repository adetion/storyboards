        // 页面加载完成后初始化
        document.addEventListener('DOMContentLoaded', function() {
            loadScheduleData();
            setupEventListeners();
        });

        // 设置事件监听器
        function setupEventListeners() {
            // 月份导航
            if (document.getElementById('prev-month')) {
                document.getElementById('prev-month').addEventListener('click', function() {
                    changeMonth(-1);
                });
            }
            
            if (document.getElementById('next-month')) {
                document.getElementById('next-month').addEventListener('click', function() {
                    changeMonth(1);
                });
            }
            
            // 新建计划
            if (document.getElementById('new-schedule')) {
                document.getElementById('new-schedule').addEventListener('click', function() {
                    //showNotification('新建拍摄计划');
                });
            }
            
            // 打印计划
            if (document.getElementById('print-schedule')) {
                document.getElementById('print-schedule').addEventListener('click', function() {
                    window.print();
                });
            }
            
            // 导出拍摄计划
            if (document.getElementById('export-schedule')) {
                document.getElementById('export-schedule').addEventListener('click', function() {
                    // 获取当前URL中的task_id参数
                    const urlParams = new URLSearchParams(window.location.search);
                    let taskId = urlParams.get('task_id');
                    
                    // 构建导出URL
                    let exportUrl = 'export_schedule.php';
                    if (taskId) {
                        exportUrl += '?task_id=' + taskId;
                    }
                    
                    // 创建临时链接并触发下载
                    const link = document.createElement('a');
                    link.href = exportUrl;
                    link.download = '拍摄计划.doc';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                });
            }
            
            // 刷新日历
            if (document.getElementById('refresh-calendar')) {
                document.getElementById('refresh-calendar').addEventListener('click', function() {
                    loadScheduleData();
                    //showNotification('刷新日历');
                });
            }
            
            // 日历日期点击事件
            document.addEventListener('click', function(e) {
                if (e.target.classList.contains('calendar-day') && 
                    !e.target.classList.contains('calendar-day-header') &&
                    e.target.hasAttribute('data-date')) {
                    // 移除所有选中状态
                    document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
                    // 添加当前选中状态
                    e.target.classList.add('selected');
                    // 加载选中日期的数据
                    selectedDate = e.target.getAttribute('data-date');
                    loadScheduleData(selectedDate);
                    //showNotification(`选择日期: ${selectedDate}`);
                    
                    // 点击日期后自动隐藏悬浮日历
                    const calendarContainer = document.getElementById('calendarContainer');
                    const floatBtn = document.getElementById('calendarFloatBtn');
                    if (calendarContainer && floatBtn) {
                        calendarContainer.style.display = 'none';
                        floatBtn.style.transform = 'translateY(-50%)';
                    }
                }
            });
            
            // 筛选器事件
            if (document.getElementById('location-filter')) {
                document.getElementById('location-filter').addEventListener('change', function() {
                    //showNotification(`筛选地点: ${this.value}`);
                });
            }
            
            if (document.getElementById('scene-filter')) {
                document.getElementById('scene-filter').addEventListener('change', function() {
                    //showNotification(`筛选场次: ${this.value}`);
                });
            }
            
            if (document.getElementById('status-filter')) {
                document.getElementById('status-filter').addEventListener('change', function() {
                    //showNotification(`筛选状态: ${this.value}`);
                });
            }
            
            if (document.getElementById('date-filter')) {
                document.getElementById('date-filter').addEventListener('change', function() {
                    //showNotification(`筛选日期: ${this.value}`);
                });
            }
        }

        // 显示通知
        function showNotification(message) {
            // 创建通知元素
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: #333;
                color: white;
                padding: 15px 20px;
                border-radius: 4px;
                z-index: 1000;
                box-shadow: 0 2px 10px rgba(0,0,0,0.2);
                transform: translateX(100%);
                transition: transform 0.3s ease;
            `;
            
            document.body.appendChild(notification);
            
            // 显示动画
            setTimeout(() => {
                notification.style.transform = 'translateX(0)';
            }, 10);
            
            // 3秒后自动移除
            setTimeout(() => {
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }

        // 月份切换功能
        let currentMonth = new Date(); // 当前月份
        let selectedDate = new Date(currentMonth.getFullYear(), currentMonth.getMonth(), 1).toISOString().split('T')[0]; // 当月第一天
        let schedule = {}; // 存储当前加载的拍摄计划数据

        function changeMonth(delta) {
            currentMonth.setMonth(currentMonth.getMonth() + delta);
            // 重新加载当前选中日期的数据以更新日历显示
            loadScheduleData(selectedDate);
            //showNotification(`切换到 ${currentMonth.getFullYear()}年${currentMonth.getMonth() + 1}月`);
        }

        function updateCalendarTitle() {
            const monthNames = ['一月', '二月', '三月', '四月', '五月', '六月',
                              '七月', '八月', '九月', '十月', '十一月', '十二月'];
            const titleElement = document.getElementById('calendarTitle');
            if (titleElement) {
                titleElement.textContent = 
                    `${currentMonth.getFullYear()}年${monthNames[currentMonth.getMonth()]}`;
            }
        }

        // 缓存机制
        const scheduleCache = new Map();
        
        // 加载拍摄计划数据
async function loadScheduleData(date = null) {
    try {
        // 获取URL中的task_id参数
        const urlParams = new URLSearchParams(window.location.search);
        let taskId = urlParams.get('task_id'); // 改为let声明，允许重新赋值
        
        // 如果URL中没有task_id，尝试从本地存储获取最后一个已完成的任务ID
        if (!taskId) {
            taskId = getLastCompletedTaskId();
            console.log('从本地存储获取最后一个已完成任务ID:', taskId);
        }

        // 构建缓存键
        const cacheKey = taskId || 'default';
        
        // 检查缓存中是否有数据
        if (scheduleCache.has(cacheKey)) {
            console.log('从缓存加载拍摄计划数据');
            const data = scheduleCache.get(cacheKey);
            
            // 如果没有指定日期，使用第一个日期
            if (!date) {
                date = Object.keys(data.schedule)[0];
            }
            
            // 更新全局选中日期
            selectedDate = date;
            
            renderSchedule(data, date);
            populateFilters(data);
            
            // 更新日历选中状态
            updateCalendarSelection(date);
            
            // 更新月份标题
            updateCalendarTitle();
            return;
        }

        // 构建JSON文件路径
        const jsonPath = taskId 
           ? `./results/${taskId}_schedule.json`
           : './json/schedule-data.json';

        console.log('加载拍摄计划数据文件:', jsonPath);
        
        let response;
        
        // 根据taskId是否为空选择不同的加载方式
        if (!taskId) {
            // 当taskId为空时，直接加载JSON文件
            response = await fetch(jsonPath);
        } else {
            // 当taskId不为空时，调用schedule_api.php
            response = await fetch(`schedule_api.php?task_id=${taskId}`);
        }
        
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();
        
        // 将数据存入缓存
        scheduleCache.set(cacheKey, data);
        
        // 如果没有指定日期，使用第一个日期
        if (!date) {
            date = Object.keys(data.schedule)[0];
        }
        
        // 更新全局选中日期
        selectedDate = date;
        
        renderSchedule(data, date);
        populateFilters(data);
        
        // 更新日历选中状态
        updateCalendarSelection(date);
        
        // 更新月份标题
        updateCalendarTitle();
    } catch (error) {
        // console.error('加载拍摄计划数据失败:', error);
        document.getElementById('schedule-content').innerHTML = 
            `<div class="error-message">
                <p>加载数据失败，请稍后重试。</p>
                <p>错误信息: ${error.message}</p>
            </div>`;
    }
}


/**
 * 获取最后一个已完成的任务ID
 * @returns {string|null} 返回最后一个已完成任务的ID，如果没有则返回null
 */
function getLastCompletedTaskId() {
    try {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + window.currentUserId + '_scriptAnalysisTasks';
        let tasks = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        
        // 检查是否有旧的本地任务（没有用户ID前缀），如果有则迁移到新键名下
        if (tasks.length === 0) {
            const oldKey = 'scriptAnalysisTasks';
            const oldTasks = JSON.parse(localStorage.getItem(oldKey) || '[]');
            if (oldTasks.length > 0) {
                // 将旧任务迁移到新键名下
                localStorage.setItem(localStorageKey, JSON.stringify(oldTasks));
                // 对于非demo用户，不删除旧键，避免影响其他用户
                // localStorage.removeItem(oldKey);
                tasks = oldTasks;
            }
        }
        
        if (tasks.length === 0) {
            return null;
        }

        // 按创建时间倒序排序，获取最新的任务
        const sortedTasks = tasks.sort((a, b) => new Date(b.created) - new Date(a.created));
        
        // 查找最后一个状态为'completed'的任务
        const lastCompletedTask = sortedTasks.find(task => task.status === 'completed');
        
        return lastCompletedTask ? lastCompletedTask.id : null;
    } catch (error) {
        // console.error('获取最后一个已完成任务ID失败:', error);
        return null;
    }
}

        // 更新日历选中状态
        function updateCalendarSelection(selectedDate) {
            // 移除所有选中状态
            document.querySelectorAll('.calendar-day').forEach(d => d.classList.remove('selected'));
            
            // 添加当前选中状态
            const selectedElement = document.querySelector(`.calendar-day[data-date="${selectedDate}"]`);
            if (selectedElement) {
                selectedElement.classList.add('selected');
            }
        }

        // 生成日历HTML
        function generateCalendar(date, scheduleData, selectedDate) {
            const year = date.getFullYear();
            const month = date.getMonth();
            
            // 获取月份的第一天和最后一天
            const firstDay = new Date(year, month, 1);
            const lastDay = new Date(year, month + 1, 0);
            
            // 获取第一天是星期几 (0=周日, 1=周一, ..., 6=周六)
            const firstDayOfWeek = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; // 调整为周一为0
            
            // 获取最后一天的日期
            const daysInMonth = lastDay.getDate();
            
            let calendarHtml = '';
            
            // 添加月初的空白天数
            for (let i = 0; i < firstDayOfWeek; i++) {
                calendarHtml += '<div class="calendar-day"></div>';
            }
            
            // 添加月份中的每一天
            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const hasShoots = scheduleData[dateStr] ? 'has-data' : '';
                const isSelected = selectedDate === dateStr ? 'selected' : '';
                
                calendarHtml += `<div class="calendar-day ${hasShoots} ${isSelected}" data-date="${dateStr}">${day}</div>`;
            }
            
            return calendarHtml;
        }

        // 切换日历显示/隐藏
        function toggleCalendar() {
            const calendarContainer = document.getElementById('calendarContainer');
            const floatBtn = document.getElementById('calendarFloatBtn');
            const calendarGrid = document.getElementById('calendarGrid');

            if (calendarContainer.style.display === 'block') {
                // 隐藏日历
                calendarContainer.style.display = 'none';
                floatBtn.style.transform = 'translateY(-50%)';
            } else {
                // 显示日历
                calendarContainer.style.display = 'block';
                floatBtn.style.transform = 'translateY(-50%) scale(0.9)';
                
                // 更新月份标题
                updateCalendarTitle();
                
                // 重新渲染日历
                const calendarHtml = generateCalendar(currentMonth, window.schedule, selectedDate);
                calendarGrid.innerHTML = `
                    <div class="calendar-day calendar-day-header">周一</div>
                    <div class="calendar-day calendar-day-header">周二</div>
                    <div class="calendar-day calendar-day-header">周三</div>
                    <div class="calendar-day calendar-day-header">周四</div>
                    <div class="calendar-day calendar-day-header">周五</div>
                    <div class="calendar-day calendar-day-header">周六</div>
                    <div class="calendar-day calendar-day-header">周日</div>
                    ${calendarHtml}
                `;
            }
        }

        // 填充筛选器选项 - 优化版
        function populateFilters(scheduleData) {
            const locationFilter = document.getElementById('location-filter');
            const sceneFilter = document.getElementById('scene-filter');
            
            // 如果没有筛选器元素，直接返回
            if (!locationFilter && !sceneFilter) {
                return;
            }
            
            // 清空现有选项
            if (locationFilter) {
                locationFilter.innerHTML = '<option value="">全部地点</option>';
            }
            
            if (sceneFilter) {
                sceneFilter.innerHTML = '<option value="">全部场次</option>';
            }
            
            // 优化：只在有筛选器需要填充时才处理数据
            if (locationFilter || sceneFilter) {
                // 收集所有唯一的地点
                const locations = new Set();
                const scenes = new Set();
                
                // 优化：使用for循环代替forEach，提高性能
                const scheduleValues = Object.values(scheduleData.schedule);
                for (let i = 0; i < scheduleValues.length; i++) {
                    const day = scheduleValues[i];
                    if (locationFilter) {
                        locations.add(day.location);
                    }
                    
                    if (sceneFilter) {
                        const dayScenes = day.scenes;
                        for (let j = 0; j < dayScenes.length; j++) {
                            const scene = dayScenes[j];
                            scenes.add(`${scene.sceneId} - ${scene.sceneName}`);
                        }
                    }
                }
                
                // 填充地点筛选器 - 优化：使用innerHTML一次性插入
                if (locationFilter && locations.size > 0) {
                    const locationOptions = [];
                    locations.forEach(location => {
                        locationOptions.push(`<option value="${location}">${location}</option>`);
                    });
                    locationFilter.innerHTML += locationOptions.join('');
                }
                
                // 填充场次筛选器 - 优化：使用innerHTML一次性插入
                if (sceneFilter && scenes.size > 0) {
                    const sceneOptions = [];
                    scenes.forEach(sceneName => {
                        const sceneId = sceneName.split(' - ')[0];
                        sceneOptions.push(`<option value="${sceneId}">${sceneName}</option>`);
                    });
                    sceneFilter.innerHTML += sceneOptions.join('');
                }
            }
        }

        // 渲染拍摄计划
        function renderSchedule(data, selectedDate) {
            const container = document.getElementById('schedule-content');
            const project = data.project;
            // 将schedule数据存储到全局变量
            window.schedule = data.schedule;
            
            // 获取选定日期的数据
            const currentDay = window.schedule[selectedDate];
            
            if (!currentDay) {
                container.innerHTML = `<div class="error-message">未找到拍摄计划</div>`;
                return;
            }
            
            // 计算统计数据
            const totalScenes = project.totalScenes;
            const totalShots = project.totalShots;
            const estimatedDays = project.estimatedDays;
            const completionRate = data.statistics.scenesCompletionRate/data.statistics.shotsCompletionRate;
            const daysCompleted = project.daysCompleted;
            
            // 生成日历
            const calendarHtml = generateCalendar(currentMonth, window.schedule, selectedDate);
            
            // 优化HTML构建：使用数组和join代替字符串拼接
            const htmlParts = [];
            
            // 添加统计数据部分
            htmlParts.push(`
                <div class="stats-container">
                    <div class="stat-card">
                        <div class="stat-label">总场次</div>
                        <div class="stat-value">${totalScenes}</div>
                        <div class="stat-label">已完成 ${data.statistics.totalScenesCompleted} 场</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">总镜头</div>
                        <div class="stat-value">${totalShots}</div>
                        <div class="stat-label">已完成 ${data.statistics.totalShotsCompleted} 个</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">拍摄天数</div>
                        <div class="stat-value">${estimatedDays}</div>
                        <div class="stat-label">已完成 ${daysCompleted} 天</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">完成率</div>
                        <div class="stat-value">${completionRate}%</div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${completionRate}%"></div>
                        </div>
                    </div>
                </div>

                <div class="schedule-list">
                    <h3>${currentDay.date} ${currentDay.dayOfWeek} 拍摄安排</h3>
                    <div style="margin-bottom: 15px; padding: 10px; background: var(--gray-100); border-radius: var(--border-radius);">
                        <strong>集合时间:</strong> ${currentDay.callTime} | 
                        <strong>开机时间:</strong> ${currentDay.shootTime} | 
                        <strong>收工时间:</strong> ${currentDay.wrapTime} | 
                        <strong>天气:</strong> ${currentDay.weather}
                    </div>`);

            // 显示当天的所有场次
            for (let i = 0; i < currentDay.scenes.length; i++) {
                const scene = currentDay.scenes[i];
                // 计算场次完成率
                const totalSceneShots = scene.shots.length;
                const completedSceneShots = scene.shots.filter(shot => shot.status === 'completed').length;
                const sceneCompletionRate = totalSceneShots > 0 ? Math.round((completedSceneShots / totalSceneShots) * 100) : 0;
                
                htmlParts.push(`
                    <div class="schedule-item">
                        <div class="time-column">${scene.startTime} - ${scene.endTime}</div>
                        <div class="content-column">
                            <div>
                                <span class="scene-tag">场次 ${scene.sceneId}</span>
                                <span class="location-tag">${scene.location} (${scene.type})</span>
                                <!--<span class="location-tag">${scene.priority === '最高' ? '最高优先级' : scene.priority === '次高' ? '次高优先级' : scene.priority === '高' ? '高优先级' : scene.priority === '中' ? '中优先级' : scene.priority === '低' ? '低优先级' : '最低优先级'}</span>-->
                            </div>
                            <div style="margin: 10px 0;">
                                <span class="status-indicator ${scene.status === 'completed' ? 'status-completed' : scene.status === 'in-progress' ? 'status-in-progress' : 'status-not-started'}"></span>
                                ${scene.sceneName}
                                <div style="float: right;">
                                    <span>${sceneCompletionRate}% 完成</span>
                                    <div class="progress-bar" style="width: 100px; display: inline-block; margin-left: 10px;">
                                        <div class="progress-fill" style="width: ${sceneCompletionRate}%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="scene-details">
                                <!---<div class="detail-row">
                                    <div class="detail-label">页码:</div>
                                    <div class="detail-value">${scene.pageNumbers}</div>
                                </div>-->
                                <div class="detail-row">
                                    <div class="detail-label">镜头:</div>
                                    <div class="detail-value">${scene.shots.length} 个 (${completedSceneShots} 已完成)</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">镜头列表:</div>
                                    <div class="detail-value">${scene.shots.map(shot => shot.shotId).join(', ')}</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">时长:</div>
                                    <div class="detail-value">预计：${scene.shots.reduce((acc, shot) => acc + shot.duration, 0)} 秒</div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">演员:</div>
                                    <div class="detail-value" id="yanyuan">
    ${[...new Set(
        scene.actors
            ?.map(actor => actor.character)
            ?.filter(character => typeof character === 'string')
            ?.flatMap(character => character.split('，').map(i => i.trim()))
            ?.filter(item => item) 
        || []
    )].join('，') || ' '}
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label" id="daoju">道具:</div>
                                    <div class="detail-value">
    ${(() => {
        const items = scene.props
            ?.filter(item => typeof item === 'string')
            ?.flatMap(item => item.split('，').map(i => i.trim()))
            ?.filter(item => item) || [];
        
        const uniqueItems = [...new Set(items)];
        return uniqueItems.length > 0 ? uniqueItems.join('，') : ' ';
    })()}
                                    </div>
                                </div>
                                <div class="detail-row">
                                    <div class="detail-label">摄影:</div>
                                    <div class="detail-value">${scene.crew.cinematographer}</div>
                                </div>
                            </div>
                        </div>
                    </div>`);
            }

            // 关闭schedule-list容器
            htmlParts.push(`
                </div>`);

            // 一次性插入所有HTML
            container.innerHTML = htmlParts.join('');
            
            // 在内容插入后执行去重
            // setTimeout(() => {
            //   StringUtils.processElements(['yanyuan', 'daoju']);
            // }, 0);
        }
        
        
        

        // 通用的字符串去重工具
const StringUtils = {
    removeDuplicates: function(str, outputSeparator = '，', inputSeparators = [',', '，', '、']) {
        if (!str || typeof str !== 'string') return str;
        
        let normalizedStr = str;
        
        // 将所有输入分隔符统一替换为输出分隔符
        inputSeparators.forEach(sep => {
            normalizedStr = normalizedStr.replace(new RegExp(sep, 'g'), outputSeparator);
        });
        
        // 分割、清理、去重
        const uniqueItems = [
            ...new Set(
                normalizedStr
                    .split(outputSeparator)
                    .map(item => item.trim())
                    .filter(item => item !== '')
            )
        ];
        
        return uniqueItems.join(outputSeparator);
    },
    
    processElements: function(elementIds, separator = '，') {
        elementIds.forEach(id => {
            const element = document.getElementById(id);
            if (element && element.textContent) {
                const originalText = element.textContent;
                const uniqueText = this.removeDuplicates(originalText, separator);
                element.textContent = uniqueText;
            }
        });
    }
};

