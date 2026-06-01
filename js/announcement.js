// 示例JSON数据
const exampleJSON = ``;


// 全局变量
let allData = null; // 存储所有数据
let currentDate = null; // 当前显示的月份
let selectedDay = null; // 选中的日期
let shootingDaysMap = new Map(); // 存储所有拍摄日数据

// 星期几的简写
const weekdays = ['日', '一', '二', '三', '四', '五', '六'];

// 从URL中获取参数
function getUrlParameter(name) {
    name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
    const regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    const results = regex.exec(location.search);
    return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
}

// 从服务器获取JSON数据
async function fetchJsonFromServer(taskId) {
    try {
        const response = await fetch(`results/${taskId}_announcement.json`);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        //console.error('获取JSON数据失败:', error);
        throw error;
    }
}

// 页面加载完成后初始化功能
document.addEventListener('DOMContentLoaded', function() {
    // 初始化编辑功能 - 同步执行，不耗时
    initEditFunctionality();
    
    // 将耗时操作放入队列，异步执行，避免阻塞DOMContentLoaded
    setTimeout(async function() {
        // 自动加载数据（如果URL中有task_id）
        await autoLoadData();
        // 异步生成拍摄通告JSON文件
        asyncGenerateAnnouncement();
    }, 0);
});

// 自动加载数据（如果URL中有task_id）
async function autoLoadData() {
    let taskId = getUrlParameter('task_id');
    if (!taskId) {
        // 从隐藏字段获取taskId
        const taskIdInput = document.getElementById('taskId');
        if (taskIdInput) {
            taskId = JSON.parse(taskIdInput.value);
        }
    }
    if (taskId) {
        //console.log('从URL获取到task_id:', taskId);
        try {
            // 从服务器获取JSON数据
            const jsonData = await fetchJsonFromServer(taskId);

            // 填充到文本框
            const jsonInput = document.getElementById('jsonInput');
            jsonInput.value = JSON.stringify(jsonData, null, 2);

            // 自动调用loadData
            loadData();
        } catch (error) {
            showMessage(`获取JSON数据失败：${error.message}`, 'error');
        }
    }
}

function loadData() {
    const jsonInput = document.getElementById('jsonInput').value;
    const messageArea = document.getElementById('messageArea');
    const loading = document.getElementById('loading');

    if (!jsonInput.trim()) {
        showMessage('请输入JSON数据！', 'error');
        return;
    }

    // 显示加载动画
    loading.style.display = 'block';
    messageArea.innerHTML = '';

    // 使用setTimeout避免UI阻塞
    setTimeout(() => {
        try {
            //console.log('开始解析JSON...');
            // 解析JSON数据
            allData = JSON.parse(jsonInput);

            //console.log('JSON解析成功，数据结构:', Object.keys(allData));
            //console.log('检查拍摄日路径:', allData?.original_data?.shootingDays ? '存在' : '不存在');

            if (allData.original_data) {
                //console.log('original_data 键:', Object.keys(allData.original_data));
            }

            // 提取所有拍摄日
            extractShootingDays();

            // 设置当前日期为最早拍摄日（如果存在）
            if (shootingDaysMap.size > 0) {
                // 获取所有拍摄日期并排序
                const sortedDates = Array.from(shootingDaysMap.keys()).sort();
                const firstShootingDate = sortedDates[0];

                //console.log(`找到 ${shootingDaysMap.size} 个拍摄日`);
                //console.log(`最早拍摄日: ${firstShootingDate}`);
                //console.log('所有拍摄日:', Array.from(shootingDaysMap.keys()));

                // 将 currentDate 设置为最早拍摄日
                currentDate = new Date(firstShootingDate);

                // 设置选中日期为第一天
                selectedDay = firstShootingDate;

                //console.log(`设置当前日期为最早拍摄日: ${firstShootingDate}`);
            } else {
                //console.warn('没有找到拍摄日数据');
                //console.warn('检查 original_data.shootingDays:', allData?.original_data?.shootingDays);
                //console.warn('original_data 内容:', allData?.original_data);

                showMessage('数据中没有找到拍摄日信息！', 'error');
                loading.style.display = 'none';
                return;
            }

            // 显示日历
            showCalendar();

            // 自动生成第一天的拍摄通告
            setTimeout(() => {
                if (selectedDay && shootingDaysMap.has(selectedDay)) {
                    generateShootingNotice();
                } else {
                    //console.error('无法生成通告：选中的日期无效');
                    //console.error('selectedDay:', selectedDay);
                    //console.error('shootingDaysMap 有该日期吗?', shootingDaysMap.has(selectedDay));
                }
            }, 500);

            showMessage('数据加载成功！已自动生成第一天的拍摄通告。', 'success');

        } catch (error) {
            //console.error('JSON解析错误:', error);
            showJSONError(error, jsonInput);
        } finally {
            // 隐藏加载动画
            loading.style.display = 'none';
        }
    }, 100);
}

// 初始化编辑功能
function initEditFunctionality() {
    const toggleEditBtn = document.getElementById('toggle-edit');
    const undoBtn = document.getElementById('undo');
    const redoBtn = document.getElementById('redo');
    const editStatus = document.getElementById('edit-status');
    let isEditMode = false;

    if (toggleEditBtn) {
        toggleEditBtn.addEventListener('click', function() {
            isEditMode = !isEditMode;
            const cells = document.querySelectorAll('#outputContainer td, #outputContainer th');

            cells.forEach(cell => {
                if (isEditMode) {
                    cell.contentEditable = 'true';
                    cell.classList.add('editable');
                    cell.addEventListener('input', handleCellEdit);
                } else {
                    cell.contentEditable = 'false';
                    cell.classList.remove('editable');
                    cell.removeEventListener('input', handleCellEdit);
                }
            });

            toggleEditBtn.innerHTML = isEditMode ? '<i class="fas fa-eye"></i> 切换查看模式' : '<i class="fas fa-edit"></i> 切换编辑模式';
            editStatus.textContent = isEditMode ? '当前处于编辑模式，可直接修改单元格内容' : '当前处于查看模式';
            undoBtn.disabled = !isEditMode;
            redoBtn.disabled = !isEditMode;
        });
    }

    // 单元格编辑处理
    function handleCellEdit(e) {
        const cell = e.target;
        const row = cell.parentNode.rowIndex;
        const col = cell.cellIndex;
        const content = cell.textContent;

        // 保存编辑到本地存储，作为临时解决方案
        saveEditToLocalStorage(row, col, content);
    }

    // 获取当前用户ID
    function getCurrentUserId() {
        const userIdInput = document.getElementById('userId');
        return userIdInput ? userIdInput.value : '';
    }

    // 保存编辑到本地存储
    function saveEditToLocalStorage(row, col, content) {
        // 使用包含用户ID的键名，确保本地任务与用户关联
        const localStorageKey = 'user_' + getCurrentUserId() + '_tableEdits';
        const edits = JSON.parse(localStorage.getItem(localStorageKey)) || [];
        edits.push({
            row: row,
            col: col,
            content: content,
            timestamp: new Date().toISOString(),
            date: selectedDay
        });
        localStorage.setItem(localStorageKey, JSON.stringify(edits));
    }

    // 撤销功能（当前使用简单的页面刷新实现）
    if (undoBtn) {
        undoBtn.addEventListener('click', function() {
            // 简单实现：清除本地存储并刷新页面
            const localStorageKey = 'user_' + getCurrentUserId() + '_tableEdits';
            localStorage.removeItem(localStorageKey);
            if (selectedDay && shootingDaysMap.has(selectedDay)) {
                generateShootingNotice();
            }
        });
    }

    // 重做功能（当前使用简单的页面刷新实现）
    if (redoBtn) {
        redoBtn.addEventListener('click', function() {
            // 简单实现：重新加载当前拍摄通告
            if (selectedDay && shootingDaysMap.has(selectedDay)) {
                generateShootingNotice();
            }
        });
    }
}

// 异步生成拍摄通告JSON文件
function asyncGenerateAnnouncement() {
    // 获取taskId
    let taskId = getUrlParameter('task_id');
    if (!taskId) {
        // 从隐藏字段获取taskId
        const taskIdInput = document.getElementById('taskId');
        if (taskIdInput) {
            taskId = JSON.parse(taskIdInput.value);
        }
    }

    if (!taskId) {
        //console.error('异步生成拍摄通告失败: 缺少有效的task_id');
        // 显示错误信息给用户
        //alert('生成拍摄通告失败：缺少有效的任务ID。请确保您有权限访问该任务或联系剧组管理员。');
        return;
    }

    const loadingIndicator = document.getElementById('loading-indicator');
    if (loadingIndicator) {
        loadingIndicator.style.display = 'block';
    }

    // 异步调用生成拍摄通告的API
    fetch(`announcement.php?action=generate_announcement&task_id=${taskId}`, {
            method: 'GET',
            cache: 'no-cache',
            timeout: 30000, // 30秒超时
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                //console.log('拍摄通告JSON文件生成成功:', data.message);
            } else {
                //console.error('拍摄通告JSON文件生成失败:', data.error || data.message || '未知错误');
                if (data.details) {
                    //console.error('错误详情:', data.details);
                }
            }
        })
        .catch(error => {
            //console.error('生成拍摄通告时发生网络错误:', error.message);
        })
        .finally(() => {
            if (loadingIndicator) {
                // 延迟隐藏加载提示，给用户足够的视觉反馈
                setTimeout(() => {
                    loadingIndicator.style.display = 'none';
                }, 1000);
            }
        });
}

// 添加编辑模式的CSS样式
const style = document.createElement('style');
style.textContent = `
    .editable {
        background-color: #ffffcc !important;
        border: 2px solid #ffcc00 !important;
        cursor: text !important;
    }
    
    .editable:focus {
        outline: none !important;
        background-color: #fff9c4 !important;
        border-color: #ff9800 !important;
    }
`;
document.head.appendChild(style);

function extractShootingDays() {
    shootingDaysMap.clear();

    //console.log('开始提取拍摄日...');
    //console.log('allData:', allData);
    //console.log('original_data:', allData?.original_data);

    if (allData?.original_data?.shootingDays) {
        const shootingDays = allData.original_data.shootingDays;
        //console.log('shootingDays 类型:', typeof shootingDays);
        //console.log('shootingDays 键:', Object.keys(shootingDays));

        Object.entries(shootingDays).forEach(([date, dayData]) => {
            //console.log(`添加拍摄日: ${date}`, dayData);
            shootingDaysMap.set(date, dayData);
        });
    } else {
        //console.log('未找到 shootingDays，检查其他可能路径...');
        // 尝试其他可能的路径
        if (allData.shootingDays) {
            //console.log('在根级别找到 shootingDays');
            Object.entries(allData.shootingDays).forEach(([date, dayData]) => {
                shootingDaysMap.set(date, dayData);
            });
        }
    }

    //console.log(`提取了 ${shootingDaysMap.size} 个拍摄日`);
}

// 切换日历显示/隐藏
function toggleCalendar() {
    const calendarContainer = document.getElementById('calendarContainer');
    const floatBtn = document.getElementById('calendarFloatBtn');

    if (calendarContainer.style.display === 'block') {
        // 隐藏日历
        calendarContainer.style.display = 'none';
        floatBtn.style.transform = 'translateY(-50%)';
    } else {
        // 显示日历
        calendarContainer.style.display = 'block';
        floatBtn.style.transform = 'translateY(-50%) scale(0.9)';
        renderCalendar();
        //console.log('日历已显示，当前月份:', currentDate.getFullYear(), '年', currentDate.getMonth() + 1, '月');
    }
}

// 显示日历
function showCalendar() {
    const calendarContainer = document.getElementById('calendarContainer');

    // 强制显示日历容器
    calendarContainer.style.display = 'block';

    // 渲染日历
    renderCalendar();

    //console.log('日历已显示，当前月份:', currentDate.getFullYear(), '年', currentDate.getMonth() + 1, '月');
}

// 渲染日历
function renderCalendar() {
    //console.log('开始渲染日历，currentDate:', currentDate);
    //console.log('shootingDaysMap 内容:', Array.from(shootingDaysMap.entries()));

    const calendarTitle = document.getElementById('calendarTitle');
    const calendarGrid = document.getElementById('calendarGrid');

    // 设置标题
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth() + 1;
    calendarTitle.textContent = `${year}年${month}月`;

    //console.log('日历标题设置为:', calendarTitle.textContent);
    //console.log('当前月份拍摄日检查:');

    // 检查当月有哪些拍摄日
    const currentMonthShootingDays = Array.from(shootingDaysMap.keys()).filter(date => {
        const dateObj = new Date(date);
        return dateObj.getFullYear() === year && dateObj.getMonth() + 1 === month;
    });
    //console.log(`当前月 ${year}-${month} 的拍摄日:`, currentMonthShootingDays);

    // 清空日历网格
    calendarGrid.innerHTML = '';

    // 添加星期标题
    weekdays.forEach(day => {
        const dayHeader = document.createElement('div');
        dayHeader.className = 'calendar-day-header';
        dayHeader.textContent = day;
        calendarGrid.appendChild(dayHeader);
    });

    // 获取当月第一天和最后一天
    const firstDay = new Date(year, month - 1, 1);
    const lastDay = new Date(year, month, 0);
    const daysInMonth = lastDay.getDate();
    const firstDayOfWeek = firstDay.getDay();

    // 添加空白单元格
    for (let i = 0; i < firstDayOfWeek; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        calendarGrid.appendChild(emptyDay);
    }

    // 添加日期单元格
    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;

        // 检查这一天是否有拍摄数据
        if (shootingDaysMap.has(dateStr)) {
            dayElement.classList.add('has-data');

            // 获取拍摄日信息
            const dayData = shootingDaysMap.get(dateStr);
            const shootDay = dayData.shootDay;

            // 添加提示信息
            const dayInfo = document.createElement('div');
            dayInfo.className = 'day-info';

            if (shootDay?.scenes) {
                const sceneCount = dayData.scenes?.length || 0;
                dayInfo.textContent = `${sceneCount}场`;
            }

            dayElement.appendChild(dayInfo);
        }

        // 如果是选中的日期（这里是第一天）
        if (selectedDay === dateStr) {
            dayElement.classList.add('selected');
            //console.log('选中日期:', dateStr);
        }

        // 点击事件
        dayElement.addEventListener('click', (event) => {
            if (shootingDaysMap.has(dateStr)) {
                selectDay(dateStr, event);
            }
        });

        calendarGrid.appendChild(dayElement);
    }

    //console.log('日历渲染完成');
}


// 选择日期
function selectDay(dateStr, event) {
    selectedDay = dateStr;

    // 更新UI
    document.querySelectorAll('.calendar-day').forEach(day => {
        day.classList.remove('selected');
    });

    // 使用传入的event参数
    event.currentTarget.classList.add('selected');

    // 自动生成拍摄通告
    setTimeout(() => {
        generateShootingNotice();
    }, 100);

    // 选择日期后自动收起日历
    setTimeout(() => {
        const calendarContainer = document.getElementById('calendarContainer');
        const floatBtn = document.getElementById('calendarFloatBtn');
        calendarContainer.style.display = 'none';
        floatBtn.style.transform = 'translateY(-50%)';
    }, 200);
}


// 上一个月
function prevMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar();
}

// 下一个月
function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar();
}

// 生成拍摄通告
function generateShootingNotice() {
    // 先检查基本条件
    if (!allData) {
        showMessage('请先加载数据！', 'error');
        return;
    }

    if (!selectedDay) {
        showMessage('请先从日历中选择一个拍摄日期！', 'error');
        return;
    }

    if (!shootingDaysMap.has(selectedDay)) {
        showMessage('选中的日期没有拍摄数据！', 'error');
        return;
    }

    const outputContainer = document.getElementById('outputContainer');
    const messageArea = document.getElementById('messageArea');
    const loading = document.getElementById('loading');

    messageArea.innerHTML = '';

    // 显示加载动画
    loading.style.display = 'block';

    setTimeout(() => {
        try {
            // 获取选中的拍摄日数据
            const dayData = shootingDaysMap.get(selectedDay);

            // 创建包含选中日数据的完整数据结构
            const shootingData = {
                ...allData,
                shooting_date: selectedDay,
                shooting_day: getShootingDayNumber(selectedDay),
                original_data: {
                    ...allData.original_data,
                    shootingDays: {
                        [selectedDay]: dayData
                    }
                }
            };

            // 清空之前的输出
            outputContainer.innerHTML = '';

            // 生成拍摄通告表格
            createShootingNoticeTable(shootingData, outputContainer);

            // 显示成功消息
            showMessage(`已生成 ${selectedDay} 的拍摄通告`, 'success');

        } catch (error) {
            showMessage('生成拍摄通告时出错：' + error.message, 'error');
        } finally {
            // 隐藏加载动画
            loading.style.display = 'none';
        }
    }, 100);
}

// 获取拍摄天数（第几天）
function getShootingDayNumber(selectedDate) {
    // 按日期排序所有拍摄日
    const sortedDates = Array.from(shootingDaysMap.keys()).sort();
    const dayNumber = sortedDates.indexOf(selectedDate) + 1;
    return dayNumber > 0 ? dayNumber : 1;
}

// 创建拍摄通告表格
function createShootingNoticeTable(data, container) {
    // 提取基本信息
    const basicInfo = extractBasicInfo(data);

    // 更新页面标题，添加影片片名和当前拍摄日期
    const shootingDate = basicInfo.date;
    const filmTitle = basicInfo.title;
    document.title = `《${filmTitle}》拍摄通告 - 智影工场 - ${shootingDate}`;

    // 创建主表格
    const tableHTML = `
        <div class="section">
            <div class="table-container">
                <table class="shooting-table" border="0" cellpadding="0" cellspacing="0">
                <col width="3%"/>
                <col width="4%"/>
                <col width="11%"/>
                <col width="7%"/>
                <col width="4%"/>
                <col width="3.5%"/>
                <col width="3%"/>
                <col width="9%"/>
                <col width="6.5%"/>
                <col width="2%"/>
                <col width="2%"/>
                <col width="2%" span="7"/>
                <col width="7%" span="3"/>
                <col width="7%"/>
                
                <!-- 标题行 -->
                <tr height="39.75">
                    <td height="39.75" colspan="22" style='border-top:none;border-left:none;border-right:none;border-bottom:none;text-align:center;font-size:18pt;font-weight:bold;font-family:微软雅黑;'>
                        《${basicInfo.title}》拍摄通告
                    </td>
                </tr>
                
                <!-- 基本信息行 -->
                <tr height="55">
                    <td height="55" colspan="3" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:16pt;font-weight:bold;background:#F2F2F2;'>
                        ${(() => {
            const date = new Date(basicInfo.date);
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}年${month}月${day}日`;
        })()}
                    </td>
                    <td colspan="6" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-weight:bold;background:#F2F2F2;'>
                        天气：<span style='font-size:16pt;font-weight:bold;'>${basicInfo.weather}</span>
                    </td>
                    <td rowspan="3" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;line-height:1.2;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;'>
                        页数
                    </td>
                    <td style='max-width:21.5px;line-height:1.2;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;'>
                        角色
                    </td>
                    ${basicInfo.roles.slice(0, 7).map((role, index) => `
                        <td style='line-height:1.2;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;'>
                            ${role ? role : ''}
                        </td>
                    `).join('')}
                    ${Array(7 - Math.min(basicInfo.roles.length, 7)).fill().map(() => `
                        <td style='line-height:1.2;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;'>
                            &nbsp;
                        </td>
                    `).join('')}
                    <td colspan="4" style='border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:16pt;font-weight:bold;background:#F2F2F2;'>
                        拍摄 第${basicInfo.day}天<br/>导演：${basicInfo.staff.director}
                    </td>
                </tr>
                
                <!-- 拍摄地点和化妆时间行 -->
                <tr height="50">
                    <td colspan="2" style='max-height:32px;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:10pt;font-weight:bold;'>
                        拍摄地点
                    </td>
                    <td height="32" colspan="7" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:10pt;font-weight:bold;'>
                        ${basicInfo.shootingLocation}
                    </td>
                    <td style='line-height:1.2;max-width:21.5px;max-height:32px;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;font-size:8pt;font-weight:bold;'>
                        交妆时间
                    </td>
                    ${basicInfo.makeupTimes.slice(0, 7).map(time => `
                        <td height="32" class="vertical-text" style='font-size:9pt;'>
                            ${time || '&nbsp;'}
                        </td>
                    `).join('')}
                    <td colspan="2" height="32" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:10.5pt;'>
                        梳化服道：${basicInfo.makeupDeparture || '待定'}<br/>大队出发：${basicInfo.departureTime}
                    </td>
                    <td colspan="2" height="32" style='border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:10.5pt;'>
                        导演出发：${basicInfo.directorDeparture || '待定'}<br/>群演出发：${basicInfo.extrasDeparture || '待定'}
                    </td>
                </tr>
                
                <!-- 表头行 -->
                <tr height="auto"> 
                    <td style='max-height:32px;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;line-height:1.2;letter-spacing:0.5em;text-align:center;font-size:10pt;font-weight:bold;'>
                        顺序
                    </td>
                    <td style='max-height:32px;border:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:10pt;font-weight:bold;'>
                        场次
                    </td>
                    <td style='max-height:32px;border:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        主场景
                    </td>
                    <td style='max-height:32px;border:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        次场景
                    </td>
                    <td style='max-height:32px;max-width:21.5px;width:21.5px;border:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        D/N
                    </td>
                    <td style='max-height:32px;max-width:21.5px;width:21.5px;border:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        I/E
                    </td>
                    <td style='max-height:32px;max-width:21.5px;width:21.5px;border:.5pt solid windowtext;line-height:1.2;letter-spacing:0.5em;text-align:center;font-size:10pt;font-weight:bold;'>
                        镜头数
                    </td>
                    <td colspan="2" style='max-height:32px;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        内容
                    </td>
                    <td style='line-height:1.2;max-width:21.5px;max-height:32px;letter-spacing:0.5em;text-align:center;writing-mode:vertical-rl;font-size:8pt;font-weight:bold;'>
                        出发时间
                    </td>
                    ${basicInfo.departureTimes.slice(0, 7).map(time => `
                        <td max-height="32" class="vertical-text" style='font-size:9pt;'>
                            ${time || '&nbsp;'}
                        </td>
                    `).join('')}
                    <td colspan="2" style='max-height:32px;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:10.5pt;font-weight:bold;'>
                        服化道提示
                    </td>
                    <td colspan="2" style='max-height:32px;border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:10pt;font-weight:bold;'>
                        备注
                    </td>
                </tr>
                
                <!-- 分镜内容（按拍摄位置分组） -->
                ${generateShotsByLocation(data, basicInfo.roles)}
                
                <!-- 工作人员表格 -->
                <tr height="16.50">
                    <td height="16.50" colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        职务
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        导演
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        副导
                    </td>
                    <td colspan="3" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        制片主任
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        摄影师
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        录音师
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        照明师
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        服装师
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        化妆师
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        道具师
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        美术师
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        现场制片
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        外联制片
                    </td>
                    <td style='border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;font-size:9pt;font-weight:bold;background:#F2F2F2;'>
                        生活制片
                    </td>
                </tr>
                
                <!-- 工作人员签名行 -->
                <tr height="21.45">
                    <td height="21.45" colspan="2" style='border-right:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                        签字
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.director || '待定'}
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.assistant_director || '待定'}
                    </td>
                    <td colspan="3" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.producer || '待定'}
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.cinematographer || '待定'}
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.sound_recorder || '待定'}
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.lighting || '待定'}
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.costume || '待定'}
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.makeup || '待定'}
                    </td>
                    <td colspan="2" style='border-right:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.props || '待定'}
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.art || '待定'}
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.on_site_producer || '待定'}
                    </td>
                    <td style='border:.5pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.external_producer || '待定'}
                    </td>
                    <td style='border-right:1.0pt solid windowtext;text-align:center;font-size:9pt;'>
                        ${basicInfo.staff.life_producer || '待定'}
                    </td>
                </tr>
                
                <!-- 制片提示和部门提示 -->
                <tr height="21.45">
                    <td height="21.45" colspan="7" style='border-right:.5pt solid windowtext;border-bottom:none;font-size:9pt;font-weight:bold;'>
                        制片提示：${basicInfo.producerNotes ? `（${basicInfo.producerNotes}）` : '（无特殊情况）'}
                    </td>
                    <td colspan="15" style='border-right:1.0pt solid windowtext;border-bottom:none;font-size:9pt;font-weight:bold;'>
                        各部门提示：${basicInfo.departmentNotes ? `（${basicInfo.departmentNotes}）` : '（请各部门做好准备）'}
                    </td>
                </tr>
                
                <!-- 特殊情况处理 -->
                <tr height="58.85">
                    <td height="58.85" colspan="7" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;font-size:9pt;font-weight:bold;'>
                        特殊情况处理：<br/>${basicInfo.specialNotes || '无'}
                    </td>
                    <td colspan="15" rowspan="2" style='border-right:1.0pt solid windowtext;border-bottom:1.0pt solid windowtext;font-size:9pt;font-weight:bold;'>
                        ${basicInfo.departmentDetails || '请各部门按照拍摄计划做好准备，确保设备正常，人员到位。'}
                    </td>
                </tr>
                
                <!-- 预拍通告 -->
                <tr height="56.65">
                    <td height="56.65" colspan="7" style='border-right:.5pt solid windowtext;border-bottom:1.0pt solid windowtext;font-size:9pt;font-weight:bold;'>
                        预拍通告（次日安排）拍摄日期：${basicInfo.nextDate || '待定'}<br/>
                        拍摄地点：${basicInfo.nextLocation || '待定'}<br/>
                        拍摄场次：${basicInfo.nextScenes || '待定'}<br/>
                        准备要求：${basicInfo.preparation || '请提前做好准备'}
                    </td>
                </tr>
                
                <!-- 联系方式 -->
                <tr height="16.50">
                    <td height="16.50" colspan="22" style='border-right:none;border-bottom:none;font-size:9pt;'>
                        ${basicInfo.contactInfo || '联系方式：请相关工作人员保持通讯畅通'}
                    </td>
                </tr>
            </table>
            </div>
        </div>
    `;

    container.innerHTML = tableHTML;
}

function generateShotsByLocation(data, allRoles) {
    let shootingDay = null;
    if (data.original_data?.shootingDays) {
        const days = Object.values(data.original_data.shootingDays);
        shootingDay = days.length > 0 ? days[0] : null;
    }

    if (!shootingDay || !shootingDay.scenes) {
        return '<tr><td colspan="22" style="text-align:center;padding:20px;">没有分镜数据</td></tr>';
    }

    // 按拍摄位置分组场景
    const groupedByLocation = groupScenesByLocation(shootingDay.scenes);

    let html = '';
    let shotIndex = 1;

    // 处理每个拍摄位置组
    Object.entries(groupedByLocation).forEach(([location, scenes]) => {
        // 添加拍摄位置分组标题行
        html += `
    <tr height="16.80">
        <td height="16.80" colspan="2" style='border-right:none;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;background:#F2F2F2;font-size:10pt;font-weight:bold;'>
            拍摄位置
        </td>
        <td colspan="2" style='border-right:none;border-bottom:.5pt solid windowtext;vertical-align:middle;background:#F2F2F2;font-size:10pt;font-weight:bold;'>
            ${location}
        </td>
        <td style='border-top:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:left;background:#F2F2F2;font-size:10pt;'>
            &nbsp;&nbsp;车程
        </td>
        <td colspan="4" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;vertical-align:middle;background:#F2F2F2;font-size:10pt;'>
            约10分钟
        </td>
        <td style='border:.5pt solid windowtext;text-align:center;'></td>
        <td style='border:.5pt solid windowtext;text-align:center;'></td>
        <td colspan="7" style='border:.5pt solid windowtext;text-align:center;'></td>
        <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;'></td>
        <td colspan="2" style='border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;'></td>
    </tr>
`;

        // 处理该拍摄位置下的所有分镜
        scenes.forEach(scene => {
            if (!scene.shots) return;

            // 场景名称解析
            const sceneNameParts = scene.sceneName ? scene.sceneName.split(' - ') : ['', ''];

            // 处理每个分镜
            scene.shots.forEach(shot => {
                // 1. 从分镜中提取角色信息
                const shotCharacters = extractCharactersFromShot(shot);
                const shotRoles = new Set(shotCharacters);

                // 2. 确保有7个角色检查结果
                const roleChecks = [];
                for (let i = 0; i < 7; i++) {
                    if (i < allRoles.length && allRoles[i] && shotRoles.has(allRoles[i])) {
                        roleChecks.push(allRoles[i].charAt(0));
                    } else {
                        roleChecks.push('');
                    }
                }

                // 3. 收集该分镜的服化道信息（直接从shot对象获取）
                const roleCostumeInfo = new Map();
                const propSet = new Set();

                // 处理该分镜中的角色服化道信息
                if (shotCharacters.length > 0) {
                    shotCharacters.forEach(role => {
                        if (!roleCostumeInfo.has(role)) {
                            roleCostumeInfo.set(role, {
                                costume: new Set(),
                                makeup: new Set()
                            });
                        }
                    });

                    // 从shot.characterCostumes中提取服装信息
                    if (shot.characterCostumes && shot.characterCostumes.trim()) {
                        const costumeInfo = parseCostumeMakeupInfo(shot.characterCostumes, shotCharacters, 'costume');
                        costumeInfo.forEach((info, role) => {
                            const roleData = roleCostumeInfo.get(role);
                            if (roleData) {
                                info.costumes.forEach(item => roleData.costume.add(item));
                            }
                        });
                    }

                    // 从shot.characterMakeup中提取化妆信息
                    if (shot.characterMakeup && shot.characterMakeup.trim()) {
                        const makeupInfo = parseCostumeMakeupInfo(shot.characterMakeup, shotCharacters, 'makeup');
                        makeupInfo.forEach((info, role) => {
                            const roleData = roleCostumeInfo.get(role);
                            if (roleData) {
                                info.makeups.forEach(item => roleData.makeup.add(item));
                            }
                        });
                    }
                }

                // 4. 收集道具信息
                if (shot.props && shot.props.trim() && shot.props !== "无") {
                    const shotProps = parseProps(shot.props);
                    shotProps.forEach(prop => {
                        if (prop && prop.trim()) {
                            propSet.add(prop.trim());
                        }
                    });
                }

                // 5. 也可以添加场景级别的道具（如果需要）
                if (scene.props && Array.isArray(scene.props)) {
                    scene.props.forEach(prop => {
                        if (prop && prop.trim() && prop !== "无") {
                            // 检查是否与分镜道具重复
                            const baseProp = normalizePropItem(prop.trim());
                            let isDuplicate = false;
                            propSet.forEach(existingProp => {
                                if (normalizePropItem(existingProp) === baseProp) {
                                    isDuplicate = true;
                                }
                            });
                            if (!isDuplicate) {
                                propSet.add(prop.trim());
                            }
                        }
                    });
                }

                // 6. 生成最终的服化道提示文本
                const costumePropsText = generateCostumePropsText(roleCostumeInfo, propSet);

                // 7. 生成表格行
                html += `
            <tr height="32">
                <td height="42" style='border-left:1.0pt solid windowtext;border-top:.5pt solid windowtext;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${shotIndex++}
                </td>
                <td style='border:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${shot.originalShotId || ''}
                </td>
                <td style='border-left:.5pt solid windowtext;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;vertical-align:middle;font-size:9pt;'>
                    ${shot.location || ''}
                </td>
                <td style='border-left:.5pt solid windowtext;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${shot.sceneExpectation || ''}
                </td>
                <td style='border-left:.5pt solid windowtext;border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${scene.dayNight || ''}
                </td>
                <td style='border:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${scene.intExt || ''}
                </td>
                <td style='border:.5pt solid windowtext;text-align:center;vertical-align:middle;font-size:9pt;'>
                    1
                </td>
                <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:left;vertical-align:middle;font-size:9pt;'>
                    ${shot.script || ''}
                </td>
                <td style='border:.5pt solid windowtext;max-width:21.45;text-align:center;vertical-align:middle;font-size:9pt;'>
                    ${scene.pageNumbers || ''}
                </td>
                <td style='border:.5pt solid windowtext;max-width:21.45;text-align:center;vertical-align:middle;font-size:9pt;'>
                    &nbsp;
                </td>
                <!-- 角色列（固定7列） -->
                ${roleChecks.map((check, index) => `
                    <td style='border:.5pt solid windowtext;max-width:21.45;text-align:center;vertical-align:middle;font-size:9pt;font-weight:bold;${check ? 'background-color:#d4edda;color:#155724;' : ''}'>
                        ${check}
                    </td>
                `).join('')}
                <!-- 服化道提示列 -->
                <td colspan="2" style='border-right:.5pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:left;vertical-align:middle;font-size:9pt;'>
                    ${shot.characterCostumes || ''} ${shot.characterMakeup || ''} ${shot.props || ''}
                </td>
                <!-- 备注列 -->
                <td colspan="2" style='border-right:1.0pt solid windowtext;border-bottom:.5pt solid windowtext;text-align:left;vertical-align:middle;font-size:9pt;'>
                    ${shot.description || ''}； ${shot.characterActions || ''}； ${shot.focus || ''}
                </td>
            </tr>
        `;
            });
        });
    });

    return html;
}

// 新增辅助函数：从分镜中提取角色
function extractCharactersFromShot(shot) {
    if (!shot.characters || !shot.characters.trim()) {
        return [];
    }

    // 分割角色字符串，去除"若干"等词
    const characters = shot.characters.split(/[，,、]/);
    return characters
        .map(char => char.trim().replace("若干", ""))
        .filter(char => char && char !== "待定" && char !== "无");
}

// 修正后的辅助函数：解析服装/化妆信息
function parseCostumeMakeupInfo(infoText, characters, infoType) {
    const result = new Map();

    // 如果信息是"无"，直接返回空结果
    if (infoText && infoText.trim() === "无") {
        return result;
    }

    // 初始化每个角色的数据
    characters.forEach(role => {
        result.set(role, {
            costumes: new Set(),
            makeups: new Set()
        });
    });

    if (!infoText) return result;

    // 按分号分割
    const parts = infoText.split(/[；;]/);

    parts.forEach(part => {
        const trimmedPart = part.trim();
        if (!trimmedPart || trimmedPart === "无") return; // 跳过"无"

        // 检查是否有角色前缀（如"林晓：考古工作服（卡其色夹克，深色工装裤）"）
        const colonIndex = Math.max(
            trimmedPart.indexOf('：'),
            trimmedPart.indexOf(':')
        );

        if (colonIndex > 0) {
            const rolePart = trimmedPart.substring(0, colonIndex).trim();
            const contentPart = trimmedPart.substring(colonIndex + 1).trim();

            // 如果内容是"无"，跳过
            if (!contentPart || contentPart === "无") return;

            // 在角色列表中查找匹配的角色
            const matchedRoles = characters.filter(role => rolePart.includes(role) || role.includes(rolePart));

            matchedRoles.forEach(role => {
                const roleData = result.get(role);
                if (roleData) {
                    // 根据传入的infoType参数判断是服装还是化妆信息
                    if (infoType === 'costume') {
                        // 进一步分割详细服装信息
                        const items = splitDetailedCostumeItems(contentPart);
                        items.forEach(item => roleData.costumes.add(item));
                    } else if (infoType === 'makeup') {
                        // 进一步分割详细化妆信息
                        const items = splitDetailedMakeupItems(contentPart);
                        items.forEach(item => roleData.makeups.add(item));
                    }
                }
            });
        } else {
            // 如果没有角色前缀，假设适用于所有角色
            // 但如果内容是"无"，跳过
            if (trimmedPart === "无") return;

            characters.forEach(role => {
                const roleData = result.get(role);
                if (roleData) {
                    if (infoType === 'costume') {
                        roleData.costumes.add(trimmedPart);
                    } else if (infoType === 'makeup') {
                        roleData.makeups.add(trimmedPart);
                    }
                }
            });
        }
    });

    return result;
}

// 新增辅助函数：分割详细服装项
function splitDetailedCostumeItems(costumeText) {
    if (!costumeText || costumeText.trim() === "无") return [];

    // 先分割括号内容
    const items = [];

    // 处理括号内的详细描述
    const bracketRegex = /（([^）]+)）|\(([^)]+)\)/g;
    let match;
    while ((match = bracketRegex.exec(costumeText)) !== null) {
        const bracketContent = match[1] || match[2];
        if (bracketContent && bracketContent.trim() !== "无") {
            const subItems = bracketContent.split(/[，,]/);
            subItems.forEach(item => {
                const trimmedItem = item.trim();
                if (trimmedItem && trimmedItem !== "无") {
                    items.push(trimmedItem);
                }
            });
        }
    }

    // 如果没有括号内容，直接返回整个文本
    if (items.length === 0) {
        const cleanedText = costumeText.replace(/[（）()]/g, '').trim();
        if (cleanedText && cleanedText !== "无") {
            items.push(cleanedText);
        }
    }

    return items;
}

// 新增辅助函数：分割详细化妆项
function splitDetailedMakeupItems(makeupText) {
    if (!makeupText || makeupText.trim() === "无") return [];

    const items = [];

    // 处理括号内的详细描述
    const bracketRegex = /（([^）]+)）|\(([^)]+)\)/g;
    let match;
    while ((match = bracketRegex.exec(makeupText)) !== null) {
        const bracketContent = match[1] || match[2];
        if (bracketContent && bracketContent.trim() !== "无") {
            const subItems = bracketContent.split(/[，,]/);
            subItems.forEach(item => {
                const trimmedItem = item.trim();
                if (trimmedItem && trimmedItem !== "无") {
                    items.push(trimmedItem);
                }
            });
        }
    }

    // 如果没有括号内容，直接返回整个文本
    if (items.length === 0) {
        const cleanedText = makeupText.replace(/[（）()]/g, '').trim();
        if (cleanedText && cleanedText !== "无") {
            items.push(cleanedText);
        }
    }

    return items;
}

// 新增辅助函数：解析道具信息
function parseProps(propsText) {
    if (!propsText || propsText === "无") return [];

    // 按逗号分割道具
    const props = propsText.split(/[，,]/);
    return props
        .map(prop => prop.trim())
        .filter(prop => prop && prop !== "无" && prop !== "");
}

// 更新 normalizePropItem 函数
function normalizePropItem(item) {
    if (!item) return '';

    let normalized = item
        .replace(/（[^）]*）|\([^)]*\)/g, '') // 移除括号内容
        .replace(/×\d+/g, '') // 移除数量标记
        .replace(/[。，、]/g, '') // 移除标点
        .trim()
        .toLowerCase(); // 统一小写

    // 移除常见的重复标记
    normalized = normalized.replace(/(道具|工具|器材|设备|机|铲|箱|盒|简)$/, '');

    return normalized;
}



// 辅助函数：分割服装信息（使用智能版本）
function splitCostumeItems(costumeText) {
    return splitCostumeItemsAdvanced(costumeText);
}

// 辅助函数：分割化妆信息（使用智能版本）
function splitMakeupItems(makeupText) {
    return splitMakeupItemsAdvanced(makeupText);
}

// 辅助函数：生成服化道提示文本（修正版）
function generateCostumePropsText(roleCostumeInfo, propSet) {
    const parts = [];

    // 用于全局去重
    const globalCostumes = new Set();
    const globalMakeups = new Set();
    const globalProps = new Set();

    // 按角色收集服装和化妆信息
    roleCostumeInfo.forEach((info, role) => {
        // 收集该角色的服装（去重）
        const costumes = Array.from(info.costume);
        const roleCostumes = [];

        costumes.forEach(item => {
            if (item && item.trim()) {
                // 标准化服装项
                const normalized = normalizeCostumeItem(item);
                if (!globalCostumes.has(normalized)) {
                    globalCostumes.add(normalized);
                    roleCostumes.push(item.trim());
                }
            }
        });

        // 收集该角色的化妆（去重）
        const makeups = Array.from(info.makeup);
        const roleMakeups = [];

        makeups.forEach(item => {
            if (item && item.trim()) {
                // 标准化化妆项
                const normalized = normalizeMakeupItem(item);
                if (!globalMakeups.has(normalized)) {
                    globalMakeups.add(normalized);
                    roleMakeups.push(item.trim());
                }
            }
        });

        // 如果这个角色有服化道信息，添加到总列表
        const roleItems = [];
        if (roleCostumes.length > 0) {
            roleItems.push(`${role}服装：${roleCostumes.join('、')}`);
        }
        if (roleMakeups.length > 0) {
            roleItems.push(`${role}妆造：${roleMakeups.join('、')}`);
        }

        if (roleItems.length > 0) {
            parts.push(roleItems.join('；'));
        }
    });

    // 添加道具信息（全局去重）
    const props = Array.from(propSet);
    if (props.length > 0) {
        const uniqueProps = [];

        props.forEach(prop => {
            if (prop && prop.trim()) {
                // 标准化道具项
                const normalized = normalizePropItem(prop.trim());
                if (!globalProps.has(normalized)) {
                    globalProps.add(normalized);
                    uniqueProps.push(prop.trim());
                }
            }
        });

        if (uniqueProps.length > 0) {
            parts.push(`道具：${uniqueProps.join('、')}`);
        }
    }

    // 返回合并后的文本
    return parts.join('；');
}

// 辅助函数：标准化服装项
function normalizeCostumeItem(item) {
    if (!item) return '';

    // 移除括号内容、数量标记等
    let normalized = item
        .replace(/（[^）]*）|\([^)]*\)/g, '') // 移除括号内容
        .replace(/×\d+/g, '') // 移除数量标记如"×3"
        .replace(/[。，、]/g, '') // 移除标点
        .trim()
        .toLowerCase(); // 统一小写

    // 移除常见的重复标记
    normalized = normalized.replace(/(服装|装|衣|服|衫|裤|裙)$/, '');

    return normalized;
}

// 辅助函数：标准化化妆项
function normalizeMakeupItem(item) {
    if (!item) return '';

    // 移除括号内容、数量标记等
    let normalized = item
        .replace(/（[^）]*）|\([^)]*\)/g, '') // 移除括号内容
        .replace(/×\d+/g, '') // 移除数量标记
        .replace(/[。，、]/g, '') // 移除标点
        .trim()
        .toLowerCase(); // 统一小写

    // 移除常见的重复标记
    normalized = normalized.replace(/(妆|化妆|妆造|造型|发型)$/, '');

    return normalized;
}

// 辅助函数：标准化道具项
function normalizePropItem(item) {
    if (!item) return '';

    // 移除括号内容、数量标记等
    let normalized = item
        .replace(/（[^）]*）|\([^)]*\)/g, '') // 移除括号内容
        .replace(/×\d+/g, '') // 移除数量标记
        .replace(/[。，、]/g, '') // 移除标点
        .trim()
        .toLowerCase(); // 统一小写

    // 移除常见的重复标记
    normalized = normalized.replace(/(道具|工具|器材|设备|机)$/, '');

    return normalized;
}

// 辅助函数：智能分割服化道信息（新增）
function splitCostumeItemsAdvanced(costumeText) {
    if (!costumeText) return [];

    const result = new Set();

    // 按分号分割
    const parts = costumeText.split(/[；;]/);

    parts.forEach(part => {
        const trimmed = part.trim();
        if (!trimmed) return;

        // 检查是否有角色前缀（如"林晓："）
        const colonIndex = Math.max(
            trimmed.indexOf('：'),
            trimmed.indexOf(':')
        );

        if (colonIndex > 0) {
            // 提取冒号后的内容
            const content = trimmed.substring(colonIndex + 1).trim();
            if (content) {
                // 进一步分割逗号分隔的项
                const subItems = content.split(/[，,]/);
                subItems.forEach(subItem => {
                    const trimmedSubItem = subItem.trim();
                    if (trimmedSubItem) {
                        result.add(trimmedSubItem);
                    }
                });
            }
        } else {
            // 直接添加
            result.add(trimmed);
        }
    });

    return Array.from(result);
}

// 辅助函数：智能分割化妆信息（新增）
function splitMakeupItemsAdvanced(makeupText) {
    if (!makeupText) return [];

    const result = new Set();

    // 按分号分割
    const parts = makeupText.split(/[；;]/);

    parts.forEach(part => {
        const trimmed = part.trim();
        if (!trimmed) return;

        // 检查是否有角色前缀（如"林晓："）
        const colonIndex = Math.max(
            trimmed.indexOf('：'),
            trimmed.indexOf(':')
        );

        if (colonIndex > 0) {
            // 提取冒号后的内容
            const content = trimmed.substring(colonIndex + 1).trim();
            if (content) {
                // 进一步分割逗号分隔的项
                const subItems = content.split(/[，,]/);
                subItems.forEach(subItem => {
                    const trimmedSubItem = subItem.trim();
                    if (trimmedSubItem) {
                        result.add(trimmedSubItem);
                    }
                });
            }
        } else {
            // 直接添加
            result.add(trimmed);
        }
    });

    return Array.from(result);
}




// 按拍摄位置分组场景
function groupScenesByLocation(scenes) {
    const groups = {};

    scenes.forEach(scene => {
        // 提取拍摄位置
        let location = '';
        if (scene.sceneName && scene.sceneName.includes("-")) {
            location = scene.sceneName.split("-")[1].trim();
        } else if (scene.location) {
            location = scene.location;
        } else {
            location = "未指定位置";
        }

        // 简化位置名称
        const simplifiedLocation = simplifyLocation(location);

        if (!groups[simplifiedLocation]) {
            groups[simplifiedLocation] = [];
        }
        groups[simplifiedLocation].push(scene);
    });

    return groups;
}

// 简化位置名称
function simplifyLocation(location) {
    if (location.includes("考古现场") || location.includes("发掘现场")) {
        return "考古现场";
    }
    if (location.includes("博物馆") || location.includes("保管室") || location.includes("展厅")) {
        return "博物馆";
    }
    if (location.includes("室内") || location.includes("内景") || location.includes("棚内")) {
        return "室内场景";
    }
    if (location.includes("室外") || location.includes("外景") || location.includes("户外")) {
        return "室外场景";
    }
    return location;
}

function extractBasicInfo(data) {
    let shootingDay = null;

    // 1. 尝试根据当前日期从 data.shootingDays 获取对应的 shootingDay（根据真实JSON数据结构）
    if (data.shootingDays && typeof data.shootingDays === 'object') {
        // 获取当前拍摄日期
        const currentDate = data.shooting_date || (shootingDay?.shootDay?.date) || "";

        if (currentDate && data.shootingDays[currentDate]) {
            // 如果有对应日期的拍摄数据，使用该数据
            shootingDay = data.shootingDays[currentDate];
            //console.log('✅ 从 data.shootingDays[' + currentDate + '] 获取 shootingDay:', shootingDay);
        } else {
            // 否则使用第一个值（兼容现有逻辑）
            const days = Object.values(data.shootingDays);
            shootingDay = days.length > 0 ? days[0] : null;
            //console.log('✅ 从 data.shootingDays 获取第一个值作为 shootingDay:', shootingDay);
        }
    }
    // 2. 尝试从 data.original_data.shootingDays 获取 shootingDay（兼容原有数据结构）
    else if (data.original_data?.shootingDays) {
        // 获取当前拍摄日期
        const currentDate = data.shooting_date || (shootingDay?.shootDay?.date) || "";

        if (currentDate && data.original_data.shootingDays[currentDate]) {
            // 如果有对应日期的拍摄数据，使用该数据
            shootingDay = data.original_data.shootingDays[currentDate];
            //console.log('✅ 从 data.original_data.shootingDays[' + currentDate + '] 获取 shootingDay:', shootingDay);
        } else {
            // 否则使用第一个值（兼容现有逻辑）
            const days = Object.values(data.original_data.shootingDays);
            shootingDay = days.length > 0 ? days[0] : null;
            //console.log('✅ 从 data.original_data.shootingDays 获取第一个值作为 shootingDay:', shootingDay);
        }
    }
    // 3. 尝试直接从 data 中获取 scenes（如果 data 本身就是一个拍摄日数据）
    else if (data.scenes && Array.isArray(data.scenes)) {
        shootingDay = {
            scenes: data.scenes
        };
        //console.log('✅ 直接从 data.scenes 获取 shootingDay:', shootingDay);
    }

    //console.log('🔍 调试：shootingDay:', shootingDay);

    // 添加详细调试信息 - 跟踪整个数据流转
    //console.log('🔍 调试：extractBasicInfo 开始');
    //console.log('1. 传入的 data 对象:', data);
    //console.log('2. data 对象的所有键:', Object.keys(data));
    //console.log('3. allData 对象:', allData);
    //console.log('4. allData 对象的所有键:', Object.keys(allData));

    // 提取电影标题 - 增强逻辑，确保能正确获取到值
    let title = "未命名电影";

    // 添加详细调试，检查title相关数据
    //console.log('🔍 调试：标题获取开始');
    //console.log('📋 data 对象类型:', typeof data);
    //console.log('📋 allData 对象类型:', typeof allData);
    //console.log('📋 data 是否为空:', data === null || data === undefined);
    //console.log('📋 allData 是否为空:', allData === null || allData === undefined);

    // 打印 data 对象的完整键值对
    if (data && typeof data === 'object') {
        //console.log('🔑 data 所有键:', Object.keys(data));
        // 检查 data 中的所有可能的标题字段
        for (let key in data) {
            if (data.hasOwnProperty(key)) {
                const value = data[key];
                // 如果值是字符串且长度大于5，可能是标题
                if (typeof value === 'string' && value.length > 5) {
                    //console.log(`🔍 发现 data.${key}: "${value}"`);
                }
            }
        }
    }

    // 打印 allData 对象的完整键值对
    if (allData && typeof allData === 'object') {
        //console.log('🔑 allData 所有键:', Object.keys(allData));
        // 检查 allData 中的所有可能的标题字段
        for (let key in allData) {
            if (allData.hasOwnProperty(key)) {
                const value = allData[key];
                // 如果值是字符串且长度大于5，可能是标题
                if (typeof value === 'string' && value.length > 5) {
                    //console.log(`🔍 发现 allData.${key}: "${value}"`);
                }
            }
        }
    }

    // 打印原始data和allData的完整结构
    //console.log('📊 data 完整结构:', JSON.stringify(data, null, 2));
    //console.log('📊 allData 完整结构:', JSON.stringify(allData, null, 2));

    // 简化获取逻辑，确保优先从 project.name 获取标题
    let foundTitle = false;

    // 1. 最优先：从 data.project.name 获取标题（根据用户提供的JSON结构）
    if (data?.project?.name && typeof data.project.name === 'string' && data.project.name.trim() !== '') {
        title = data.project.name.trim();
        foundTitle = true;
        //console.log('✅ 从 data.project.name 获取标题 (正确位置):', title);
    }
    // 2. 其次：从 allData.project.name 获取标题（根据用户提供的JSON结构）
    else if (allData?.project?.name && typeof allData.project.name === 'string' && allData.project.name.trim() !== '') {
        title = allData.project.name.trim();
        foundTitle = true;
        //console.log('✅ 从 allData.project.name 获取标题 (正确位置):', title);
    }
    // 3. 第三：从 data.title 获取标题
    else if (data?.title && typeof data.title === 'string' && data.title.trim() !== '') {
        title = data.title.trim();
        foundTitle = true;
        //console.log('✅ 从 data.title 获取标题:', title);
    }
    // 4. 第四：从 allData.title 获取标题
    else if (allData?.title && typeof allData.title === 'string' && allData.title.trim() !== '') {
        title = allData.title.trim();
        foundTitle = true;
        //console.log('✅ 从 allData.title 获取标题:', title);
    }
    // 5. 第五：从 data 对象中寻找明确的标题字段
    else if (data && typeof data === 'object') {
        // 优先检查明确的标题字段
        const titleFields = ['name', 'shooting_title', 'film_title', 'movie_title', 'drama_title', 'production_title'];
        for (let field of titleFields) {
            if (data[field] && typeof data[field] === 'string' && data[field].trim() !== '') {
                title = data[field].trim();
                foundTitle = true;
                //console.log('✅ 从 data.' + field + ' 获取标题:', title);
                break;
            }
        }
    }
    // 6. 第六：从 allData 对象中寻找明确的标题字段
    else if (allData && typeof allData === 'object') {
        const titleFields = ['name', 'shooting_title', 'film_title', 'movie_title', 'drama_title', 'production_title'];
        for (let field of titleFields) {
            if (allData[field] && typeof allData[field] === 'string' && allData[field].trim() !== '') {
                title = allData[field].trim();
                foundTitle = true;
                //console.log('✅ 从 allData.' + field + ' 获取标题:', title);
                break;
            }
        }
    }
    // 7. 第七：避免获取到日期 - 跳过日期格式的字符串
    // 这里添加日期检测，避免将日期误判为标题

    // 8. 最后的保障：只在万不得已的情况下使用
    if (!foundTitle) {
        //console.error('❌ 无法从指定位置获取标题，尝试其他位置');
        // 可以添加更多的容错逻辑，但避免获取到日期
        // 例如，检查字符串是否包含特定关键词
        if (data && typeof data === 'object') {
            for (let key in data) {
                if (data.hasOwnProperty(key) && typeof data[key] === 'string') {
                    const value = data[key].trim();
                    // 跳过日期格式的字符串 (YYYY-MM-DD)
                    if (value.match(/^\d{4}-\d{2}-\d{2}$/)) {
                        //console.log('⏭️  跳过日期格式字符串:', value);
                        continue;
                    }
                    // 寻找可能的标题，跳过明显不是标题的字段
                    if (value && value.length > 5 &&
                        !value.includes('http') && // 跳过URL
                        !value.includes('@') && // 跳过邮箱
                        !value.match(/^\d+$/)) { // 跳过纯数字
                        title = value;
                        foundTitle = true;
                        //console.log('🔧 从 data.' + key + ' 获取标题 (备用):', title);
                        break;
                    }
                }
            }
        }
    }

    // 确保 title 不是空值
    if (!title || title === "") {
        title = "未命名电影";
        //console.log('🔧 标题为空，使用默认值:', title);
    }

    //console.log('🔍 调试：标题获取结束，最终标题:', title);
    //console.log('🔍 调试：标题是否找到:', foundTitle);

    // 提取角色名称 - 只提取当天拍摄场景中的角色，过滤掉"无"的角色
    const roles = new Set();
    if (shootingDay?.scenes) {
        // 对场景进行排序：日戏排在前，夜戏排在后
        const sortedScenes = [...shootingDay.scenes].sort((a, b) => {
            // 获取场景的dayNight字段，默认为"日"
            const dayNightA = a.dayNight || a.setting || "日";
            const dayNightB = b.dayNight || b.setting || "日";

            // 日戏排在前，夜戏排在后
            if (dayNightA === dayNightB) {
                return 0;
            }
            return dayNightA === "日" ? -1 : 1;
        });

        // console.log('🔍 调试：场景排序前:', shootingDay.scenes.map(scene => ({
        //     sceneId: scene.sceneId,
        //     dayNight: scene.dayNight || scene.setting
        // })));
        // console.log('🔍 调试：场景排序后:', sortedScenes.map(scene => ({
        //     sceneId: scene.sceneId,
        //     dayNight: scene.dayNight || scene.setting
        // })));

        // 更新shootingDay.scenes为排序后的场景
        shootingDay.scenes = sortedScenes;

        shootingDay.scenes.forEach(scene => {
            // 从场景的cast中提取角色
            if (scene.cast) {
                scene.cast.forEach(castMember => {
                    if (castMember.character) {
                        const characters = castMember.character.split(/[，,、]/);
                        characters.forEach(char => {
                            const trimmedChar = char.trim().replace("若干", "");
                            // 过滤掉"无"、"待定"等无效角色
                            if (trimmedChar && trimmedChar !== "待定" && trimmedChar !== "无") {
                                roles.add(trimmedChar);
                            }
                        });
                    }
                });
            }

            // 从场景的shots中提取角色（作为补充）
            if (scene.shots) {
                scene.shots.forEach(shot => {
                    if (shot.characters) {
                        const characters = shot.characters.split(/[，,、]/);
                        characters.forEach(char => {
                            const trimmedChar = char.trim().replace("若干", "");
                            // 过滤掉"无"、"待定"等无效角色
                            if (trimmedChar && trimmedChar !== "待定" && trimmedChar !== "无") {
                                roles.add(trimmedChar);
                            }
                        });
                    }
                });
            }
        });
    }

    const roleArray = Array.from(roles);
    //console.log('🔍 调试：当天拍摄角色:', roleArray);

    // 提取每个角色的拍摄时间，并预估交妆时间和出发时间
    // 1. 提取每个角色的最早拍摄时间
    const roleShootingTimes = new Map();

    // 收集所有场景的startTime，用于计算默认时间
    const allSceneStartTimes = [];

    if (shootingDay?.scenes && Array.isArray(shootingDay.scenes)) {
        //console.log('🔍 调试：开始提取角色拍摄时间');

        shootingDay.scenes.forEach(scene => {
            // 收集场景的startTime
            if (scene.startTime && typeof scene.startTime === 'string') {
                allSceneStartTimes.push(scene.startTime);
            }

            // 遍历该场景的所有拍摄时间
            if (scene.shots && Array.isArray(scene.shots)) {
                scene.shots.forEach(shot => {
                    // 获取该拍摄的时间信息 - 尝试从多个字段获取
                    let shotStartTime = "";

                    // 优先从startTime字段获取
                    if (shot.startTime && typeof shot.startTime === 'string') {
                        shotStartTime = shot.startTime;
                    }
                    // 其次从estimatedTime字段获取（假设格式为 "HH:MM-HH:MM"）
                    else if (shot.estimatedTime && typeof shot.estimatedTime === 'string') {
                        // 提取开始时间
                        const timeMatch = shot.estimatedTime.match(/^(\d{2}:\d{2})/);
                        if (timeMatch) {
                            shotStartTime = timeMatch[1];
                        }
                    }
                    // 再次从scene的startTime字段获取
                    else if (scene.startTime && typeof scene.startTime === 'string') {
                        shotStartTime = scene.startTime;
                    }
                    // 最后从shootDay的callTime字段获取（作为默认时间）
                    else if (shootingDay?.shootDay?.callTime && typeof shootingDay.shootDay.callTime === 'string') {
                        shotStartTime = shootingDay.shootDay.callTime;
                    }

                    //console.log('🔍 调试：shot:', shot.originalShotId, 'startTime:', shotStartTime);

                    // 如果有角色信息，记录每个角色的拍摄时间
                    if (shot.characters && typeof shot.characters === 'string') {
                        const characters = shot.characters.split(/[,，、]/).map(char => char.trim());

                        characters.forEach(character => {
                            if (character && character !== "待定" && character !== "若干") {
                                // 如果是新角色，初始化
                                if (!roleShootingTimes.has(character)) {
                                    roleShootingTimes.set(character, []);
                                }

                                // 添加拍摄时间
                                if (shotStartTime) {
                                    roleShootingTimes.get(character).push(shotStartTime);
                                }
                            }
                        });
                    }
                });
            }
        });

        //console.log('🔍 调试：角色拍摄时间:', Object.fromEntries(roleShootingTimes));
    }

    // 2. 计算每个角色的交妆时间和出发时间，基于shootDay和cast的时间信息
    const roleSchedule = new Map();
    const defaultMakeupTime = 90; // 默认化妆时间（分钟）
    const defaultTravelTime = 60; // 默认路途时间（分钟）

    // 获取shootDay的时间信息
    const shootDayCallTime = shootingDay?.shootDay?.callTime || "06:00"; // 集合时间
    const shootDayShootTime = shootingDay?.shootDay?.shootTime || "08:00"; // 拍摄开始时间
    const shootDayWrapTime = shootingDay?.shootDay?.wrapTime || "02:00"; // 收工时间

    //console.log('🔍 调试：shootDay时间信息:', {
    //    callTime: shootDayCallTime,
    //    shootTime: shootDayShootTime,
    //    wrapTime: shootDayWrapTime
    //});

    // 解析shootDay的callTime和shootTime为分钟数
    const [callTimeHours, callTimeMinutes] = shootDayCallTime.split(':').map(Number);
    const callTimeTotalMinutes = callTimeHours * 60 + callTimeMinutes;

    const [shootTimeHours, shootTimeMinutes] = shootDayShootTime.split(':').map(Number);
    const shootTimeTotalMinutes = shootTimeHours * 60 + shootTimeMinutes;

    // 从所有场景startTime中找出最早的时间
    let earliestSceneStartTime = shootDayShootTime;
    if (allSceneStartTimes.length > 0) {
        // 排序时间字符串，找出最早的时间
        const sortedStartTimes = [...allSceneStartTimes].sort((a, b) => {
            const [aH, aM] = a.split(':').map(Number);
            const [bH, bM] = b.split(':').map(Number);
            return (aH * 60 + aM) - (bH * 60 + bM);
        });
        earliestSceneStartTime = sortedStartTimes[0];
    }

    const [earliestHours, earliestMinutes] = earliestSceneStartTime.split(':').map(Number);
    const earliestSceneTotalMinutes = earliestHours * 60 + earliestMinutes;

    //console.log('🔍 调试：最早场景开始时间:', earliestSceneStartTime);

    // 检查当天场景主要是日戏还是夜戏
    let isNightShoot = false;
    if (shootingDay?.scenes && Array.isArray(shootingDay.scenes)) {
        // 统计夜戏数量
        const nightSceneCount = shootingDay.scenes.filter(scene => {
            const dayNight = scene.dayNight || scene.setting || "日";
            return dayNight === "夜" || dayNight === "傍晚" || dayNight === "黄昏" || dayNight === "日落" || dayNight === "黎明";
        }).length;

        // 如果夜戏数量超过总场景数的一半，或者所有场景都是夜戏，则认为是夜戏拍摄
        isNightShoot = nightSceneCount > shootingDay.scenes.length / 2 || nightSceneCount === shootingDay.scenes.length;
    }

    //console.log('🔍 调试：当天是否为夜戏拍摄:', isNightShoot);

    // 获取scenes中的第一个startTime作为主要基准时间
    let primaryBaseTime = null;
    if (shootingDay?.scenes && Array.isArray(shootingDay.scenes) && shootingDay.scenes.length > 0) {
        // 遍历场景，找到第一个有效的startTime
        for (let scene of shootingDay.scenes) {
            if (scene.startTime && typeof scene.startTime === 'string' && scene.startTime.trim() !== '') {
                primaryBaseTime = scene.startTime;
                break; // 只使用第一个有效的startTime
            }
        }
    }

    //console.log('🔍 调试：从scenes获取的第一个startTime:', primaryBaseTime);

    // 计算默认的交妆时间和出发时间
    let defaultBaseTime = "08:00"; // 默认基准时间

    // 优先级：scenes中的第一个startTime > shootDay.shootTime > shootDay.callTime
    if (primaryBaseTime) {
        defaultBaseTime = primaryBaseTime;
    } else if (shootingDay?.shootDay?.shootTime && typeof shootingDay.shootDay.shootTime === 'string') {
        defaultBaseTime = shootingDay.shootDay.shootTime;
    } else if (shootingDay?.shootDay?.callTime && typeof shootingDay.shootDay.callTime === 'string') {
        defaultBaseTime = shootingDay.shootDay.callTime;
    }

    //console.log('🔍 调试：最终使用的基准时间:', defaultBaseTime);

    // 解析基准时间
    const [baseH, baseM] = defaultBaseTime.split(':').map(Number);
    const baseTotalMinutes = baseH * 60 + baseM;

    // 直接使用scenes中的startTime作为基准，不进行日夜属性调整
    // 优先使用从scenes获取的第一个startTime，否则使用shootDay的时间
    let finalBaseTime = defaultBaseTime;
    let finalBaseTotalMinutes = baseTotalMinutes;

    // 如果有从scenes获取的startTime，直接使用它作为最终基准
    if (primaryBaseTime) {
        finalBaseTime = primaryBaseTime;
        const [pbH, pbM] = primaryBaseTime.split(':').map(Number);
        finalBaseTotalMinutes = pbH * 60 + pbM;
    }

    //console.log('🔍 调试：最终基准时间（直接使用scenes startTime）:', finalBaseTime);

    // 计算默认交妆时间和出发时间
    // 交妆时间 = 最终基准时间 - 化妆时间
    defaultMakeupTotalMinutes = finalBaseTotalMinutes - defaultMakeupTime;
    // 出发时间 = 最终基准时间 - 路途时间
    defaultDepartureTotalMinutes = finalBaseTotalMinutes - defaultTravelTime;

    // 处理时间为负数的情况（即前一天的时间）
    if (defaultMakeupTotalMinutes < 0) {
        defaultMakeupTotalMinutes += 24 * 60; // 加上24小时
    }
    if (defaultDepartureTotalMinutes < 0) {
        defaultDepartureTotalMinutes += 24 * 60; // 加上24小时
    }

    // 转换为HH:MM格式
    const defaultMakeupHours = Math.floor(defaultMakeupTotalMinutes / 60);
    const defaultMakeupMins = defaultMakeupTotalMinutes % 60;
    const defaultMakeupTimeFormatted = `${String(defaultMakeupHours).padStart(2, '0')}:${String(defaultMakeupMins).padStart(2, '0')}`;

    const defaultDepartureHours = Math.floor(defaultDepartureTotalMinutes / 60);
    const defaultDepartureMins = defaultDepartureTotalMinutes % 60;
    const defaultDepartureTimeFormatted = `${String(defaultDepartureHours).padStart(2, '0')}:${String(defaultDepartureMins).padStart(2, '0')}`;

    //console.log('🔍 调试：日/夜戏判断结果 - 日戏/夜戏:', isNightShoot ? '夜戏' : '日戏');
    //console.log('🔍 调试：默认交妆时间:', defaultMakeupTimeFormatted, '默认出发时间:', defaultDepartureTimeFormatted);

    //console.log('🔍 调试：默认交妆时间:', defaultMakeupTimeFormatted, '默认出发时间:', defaultDepartureTimeFormatted);

    // 从cast中提取每个角色的callTime和arrivalTime
    const roleCastInfo = new Map();
    if (shootingDay?.scenes) {
        shootingDay.scenes.forEach(scene => {
            if (scene.cast) {
                scene.cast.forEach(castMember => {
                    if (castMember.character) {
                        const characters = castMember.character.split(/[，,、]/).map(char => char.trim().replace("若干", ""));
                        characters.forEach(character => {
                            if (character && character !== "待定" && character !== "无") {
                                // 提取callTime和arrivalTime
                                const callTime = castMember.callTime || "";
                                const arrivalTime = castMember.arrivalTime || "";

                                // 如果该角色已经有信息，合并
                                if (roleCastInfo.has(character)) {
                                    const existingInfo = roleCastInfo.get(character);
                                    roleCastInfo.set(character, {
                                        callTime: existingInfo.callTime || callTime,
                                        arrivalTime: existingInfo.arrivalTime || arrivalTime
                                    });
                                } else {
                                    roleCastInfo.set(character, {
                                        callTime: callTime,
                                        arrivalTime: arrivalTime
                                    });
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    //console.log('🔍 调试：角色cast信息:', Object.fromEntries(roleCastInfo));

    // 为每个角色设置交妆时间和出发时间
    roleArray.forEach(character => {
        let characterMakeupTime = defaultMakeupTimeFormatted;
        let characterDepartureTime = defaultDepartureTimeFormatted;
        let characterShootingTime = finalBaseTime; // 使用最终基准时间作为拍摄时间

        // 检查该角色是否有cast信息
        if (roleCastInfo.has(character)) {
            const castInfo = roleCastInfo.get(character);

            // 解析castInfo中的时间
            let castArrivalTimeTotalMinutes = null;
            let castCallTimeTotalMinutes = null;
            let hasValidCastTime = false;

            // 解析arrivalTime（优先级最高）
            if (castInfo.arrivalTime && typeof castInfo.arrivalTime === 'string') {
                const [arrivalHours, arrivalMinutes] = castInfo.arrivalTime.split(':').map(Number);
                castArrivalTimeTotalMinutes = arrivalHours * 60 + arrivalMinutes;
                hasValidCastTime = true;
            }

            // 解析callTime
            if (castInfo.callTime && typeof castInfo.callTime === 'string') {
                const [callHours, callMinutes] = castInfo.callTime.split(':').map(Number);
                castCallTimeTotalMinutes = callHours * 60 + callMinutes;
                hasValidCastTime = true;
            }

            // 时间优先级：arrivalTime > callTime > scenes第一个startTime > 默认时间
            if (castArrivalTimeTotalMinutes !== null) {
                // 交妆时间 = arrivalTime（演员到达化妆场地的时间）
                characterMakeupTime = castInfo.arrivalTime;

                // 出发时间 = arrivalTime - 路途时间（演员需要在arrivalTime到达化妆场地）
                let departureTotalMinutes = castArrivalTimeTotalMinutes - defaultTravelTime;
                if (departureTotalMinutes < 0) {
                    departureTotalMinutes += 24 * 60; // 加上24小时
                }
                const departureHours = Math.floor(departureTotalMinutes / 60);
                const departureMinutes = departureTotalMinutes % 60;
                characterDepartureTime = `${String(departureHours).padStart(2, '0')}:${String(departureMinutes).padStart(2, '0')}`;
            }
            // 如果没有arrivalTime，但有callTime，使用callTime计算
            else if (castCallTimeTotalMinutes !== null) {
                // 交妆时间 = callTime - 化妆时间（化妆需要在callTime前完成）
                let makeupTotalMinutes = castCallTimeTotalMinutes - defaultMakeupTime;
                if (makeupTotalMinutes < 0) {
                    makeupTotalMinutes += 24 * 60; // 加上24小时
                }
                const makeupHours = Math.floor(makeupTotalMinutes / 60);
                const makeupMinutes = makeupTotalMinutes % 60;
                characterMakeupTime = `${String(makeupHours).padStart(2, '0')}:${String(makeupMinutes).padStart(2, '0')}`;

                // 出发时间 = callTime - 路途时间（演员需要在callTime到达片场）
                let departureTotalMinutes = castCallTimeTotalMinutes - defaultTravelTime;
                if (departureTotalMinutes < 0) {
                    departureTotalMinutes += 24 * 60; // 加上24小时
                }
                const departureHours = Math.floor(departureTotalMinutes / 60);
                const departureMinutes = departureTotalMinutes % 60;
                characterDepartureTime = `${String(departureHours).padStart(2, '0')}:${String(departureMinutes).padStart(2, '0')}`;
            }
            // 如果没有有效的cast时间，使用默认计算的时间
            else {
                // 直接使用基于scenes第一个startTime计算的默认时间
                characterMakeupTime = defaultMakeupTimeFormatted;
                characterDepartureTime = defaultDepartureTimeFormatted;
            }
        } else {
            // 如果没有cast信息，使用默认计算的时间
            // 直接使用基于scenes第一个startTime计算的默认时间
            characterMakeupTime = defaultMakeupTimeFormatted;
            characterDepartureTime = defaultDepartureTimeFormatted;
        }

        // 确定拍摄时间：优先使用primaryBaseTime，否则使用finalBaseTime
        let shootingTime = primaryBaseTime || finalBaseTime;

        const schedule = {
            earliestShootingTime: shootingTime, // 使用优先的拍摄时间
            makeupTime: characterMakeupTime, // 使用基于优先级计算的交妆时间
            departureTime: characterDepartureTime // 使用基于优先级计算的出发时间
        };

        roleSchedule.set(character, schedule);

        //console.log('🔍 调试：角色', character, '日程:', schedule);
    });

    //console.log('🔍 调试：角色日程:', Object.fromEntries(roleSchedule));

    // 3. 生成makeupTimes和departureTimes数组
    let makeupTimes = [];
    let departureTimes = [];

    // 遍历角色数组，为每个角色获取预估的交妆时间和出发时间
    roleArray.forEach(character => {
        if (roleSchedule.has(character)) {
            const schedule = roleSchedule.get(character);
            makeupTimes.push(schedule.makeupTime);
            departureTimes.push(schedule.departureTime);
        } else {
            makeupTimes.push("");
            departureTimes.push("");
        }
    });

    //console.log('🔍 调试：makeupTimes:', makeupTimes);
    //console.log('🔍 调试：departureTimes:', departureTimes);

    // 确保数组长度至少为7（兼容现有代码）
    while (makeupTimes.length < 7) {
        makeupTimes.push("");
        departureTimes.push("");
    }

    // 提取拍摄地点：从scenes中的sceneName提取，过滤掉"场次"、"-"及空格，去重后join在一起
    let shootingLocation = "未指定";

    if (shootingDay?.scenes && Array.isArray(shootingDay.scenes) && shootingDay.scenes.length > 0) {
        //console.log('🔍 调试：开始提取拍摄地点');

        // 从scenes中提取所有sceneName，确保是字符串类型
        const sceneNames = shootingDay.scenes.map(scene => String(scene.sceneName || ""))
            .filter(name => name.trim() !== "");

        //console.log('🔍 调试：原始sceneNames:', sceneNames);

        // 过滤掉"场次"、"-"及空格，去重
        const filteredLocations = new Set();
        sceneNames.forEach(name => {
            // 过滤逻辑：去掉"场次 X - "格式的前缀，保留地址中的数字
            const filtered = name
                .replace(/场次\s+\d+\s+-\s+/g, "") // 去掉"场次 X - "格式的前缀，保留地址中的数字
                .trim(); // 去掉首尾空格

            if (filtered !== "") {
                filteredLocations.add(filtered);
                //console.log('🔍 调试：过滤后location:', filtered);
            }
        });

        //console.log('🔍 调试：去重后locations:', Array.from(filteredLocations));

        // 将过滤后的结果join在一起
        if (filteredLocations.size > 0) {
            shootingLocation = Array.from(filteredLocations).join("、");
            //console.log('✅ 拍摄地点提取成功:', shootingLocation);
        }
    }

    //console.log('🔍 调试：最终shootingLocation:', shootingLocation);

    // 从最后一天的crew.keyCrew中提取剧组人员信息
    let staffFromCrew = {
        director: "未指定",
        assistant_director: "未指定",
        producer: "未指定",
        cinematographer: "未指定",
        sound_recorder: "未指定",
        lighting: "未指定",
        costume: "未指定",
        makeup: "未指定",
        props: "未指定",
        art: "未指定",
        on_site_producer: "未指定",
        external_producer: "未指定",
        life_producer: "未指定"
    };

    // 保存完整的keyCrew数据用于联系方式
    let fullKeyCrew = [];

    // 获取最后一天的数据
    let lastDayData = null;
    if (data.shootingDays && typeof data.shootingDays === 'object') {
        // 获取所有拍摄日期并排序，找到最后一天
        const shootingDates = Object.keys(data.shootingDays).sort();
        if (shootingDates.length > 0) {
            lastDayData = data.shootingDays[shootingDates[shootingDates.length - 1]];
            //console.log('✅ 获取最后一天数据:', lastDayData);

            // 从最后一天数据中提取keyCrew
            if (lastDayData.crew && Array.isArray(lastDayData.crew.keyCrew)) {
                const keyCrew = lastDayData.crew.keyCrew;
                fullKeyCrew = keyCrew;
                //console.log('🔍 调试：最后一天keyCrew数据:', keyCrew);

                // 根据keyCrew数据更新staffFromCrew
                keyCrew.forEach(member => {
                    const role = member.role || "";
                    const name = member.name || "未指定";

                    // 根据角色映射到对应的字段
                    switch (role) {
                        case "导演":
                            staffFromCrew.director = name;
                            break;
                        case "副导演":
                            staffFromCrew.assistant_director = name;
                            break;
                        case "制片主任":
                        case "制片人":
                            staffFromCrew.producer = name;
                            break;
                        case "摄影师":
                            staffFromCrew.cinematographer = name;
                            break;
                        case "录音师":
                        case "录音":
                            staffFromCrew.sound_recorder = name;
                            break;
                        case "照明师":
                        case "灯光":
                            staffFromCrew.lighting = name;
                            break;
                        case "服装师":
                        case "服装":
                            staffFromCrew.costume = name;
                            break;
                        case "化妆师":
                        case "化妆":
                            staffFromCrew.makeup = name;
                            break;
                        case "道具师":
                        case "道具":
                            staffFromCrew.props = name;
                            break;
                        case "美术师":
                        case "美术":
                            staffFromCrew.art = name;
                            break;
                        case "现场制片":
                            staffFromCrew.on_site_producer = name;
                            break;
                        case "外联制片":
                            staffFromCrew.external_producer = name;
                            break;
                        case "生活制片":
                            staffFromCrew.life_producer = name;
                            break;
                            // 其他角色根据需要添加映射
                    }
                });
            }
        }
    }

    //console.log('🔍 调试：从最后一天crew.keyCrew提取的剧组人员:', staffFromCrew);
    //console.log('🔍 调试：完整的keyCrew数据:', fullKeyCrew);

    // 计算下一天的日期
    const currentDate = new Date(data.shooting_date || (shootingDay?.shootDay?.date) || "");
    const nextDateObj = new Date(currentDate);
    nextDateObj.setDate(nextDateObj.getDate() + 1);
    const nextDateStr = nextDateObj.toISOString().split('T')[0];

    // 获取下一天的拍摄数据
    let nextDayData = null;
    if (shootingDaysMap && shootingDaysMap.has(nextDateStr)) {
        nextDayData = shootingDaysMap.get(nextDateStr);
    } else if (allData?.original_data?.shootingDays && allData.original_data.shootingDays[nextDateStr]) {
        nextDayData = allData.original_data.shootingDays[nextDateStr];
    } else if (allData?.shootingDays && allData.shootingDays[nextDateStr]) {
        nextDayData = allData.shootingDays[nextDateStr];
    }

    // 提取下一天的拍摄地点
    let nextLocation = "待定";
    if (nextDayData?.scenes && Array.isArray(nextDayData.scenes)) {
        const nextSceneNames = nextDayData.scenes.map(scene => {
                // 确保sceneName是字符串类型
                return String(scene.sceneName || "");
            })
            .filter(name => name.trim() !== "");

        const nextFilteredLocations = new Set();
        nextSceneNames.forEach(name => {
            const filtered = name
                .replace(/场次\s+\d+\s+-\s+/g, "")
                .trim();

            if (filtered !== "") {
                nextFilteredLocations.add(filtered);
            }
        });

        if (nextFilteredLocations.size > 0) {
            nextLocation = Array.from(nextFilteredLocations).join("、");
        }
    }

    // 提取下一天的拍摄场次
    let nextScenes = "待定";
    if (nextDayData?.scenes && Array.isArray(nextDayData.scenes)) {
        const nextSceneNumbers = nextDayData.scenes.map(scene => {
                // 确保sceneNumber是字符串类型
                return String(scene.sceneNumber || "");
            })
            .filter(number => number.trim() !== "");

        if (nextSceneNumbers.length > 0) {
            nextScenes = nextSceneNumbers.join("、");
        }
    }

    // 提取当前天的productionNotes用于当前天的部门提示
    let productionNotes = String(data.notes?.productionNotes || "");
    if (!productionNotes && shootingDay?.notes?.productionNotes) {
        productionNotes = String(shootingDay.notes.productionNotes || "");
    }

    // 严格按照用户要求：预拍通告中的准备要求使用下一天的productionNotes
    // 提取下一天的productionNotes
    let nextDayProductionNotes = "";
    if (nextDayData?.notes?.productionNotes) {
        nextDayProductionNotes = String(nextDayData.notes.productionNotes || "");
    }

    // 准备要求：将下一天productionNotes中的“今日拍摄场次”改为“拍摄场次”
    let preparation = "请提前做好准备";
    if (nextDayProductionNotes) {
        preparation = nextDayProductionNotes.replace(/今日拍摄场次/g, "拍摄场次");
        //console.log('🔍 调试：使用下一天的productionNotes生成准备要求:', preparation);
    } else {
        //console.log('🔍 调试：下一天没有productionNotes，使用默认准备要求');
    }

    // 计算梳化服道、大队出发、导演出发、群演出发时间
    // 优先使用scenes中的第一个startTime作为基准
    let baseTime = "08:00"; // 默认基准时间

    // 1. 优先使用从scenes获取的第一个startTime
    if (primaryBaseTime) {
        baseTime = primaryBaseTime;
    }
    // 2. 其次使用shootDay的shootTime
    else if (shootingDay?.shootDay?.shootTime && typeof shootingDay.shootDay.shootTime === 'string') {
        baseTime = shootingDay.shootDay.shootTime;
    }
    // 3. 最后使用shootDay的callTime
    else if (shootingDay?.shootDay?.callTime && typeof shootingDay.shootDay.callTime === 'string') {
        baseTime = shootingDay.shootDay.callTime;
    }

    //console.log('🔍 调试：时间计算基准时间:', baseTime);

    // 解析部门时间计算的基准时间（使用不同的变量名避免重复声明）
    const [deptBaseH, deptBaseM] = baseTime.split(':').map(Number);
    const deptBaseTotalMinutes = deptBaseH * 60 + deptBaseM;

    // 默认时间设置
    let makeupDepartureTime = "06:00"; // 梳化服道时间
    let teamDepartureTime = "07:00"; // 大队出发时间
    let directorDepartureTime = "07:30"; // 导演出发时间
    let extrasDepartureTime = "08:00"; // 群演出发时间

    // 基于基准时间计算各时间，确保结果合理

    // 梳化服道时间：基准时间前2小时
    let makeupDepartureTotal = deptBaseTotalMinutes - 120;
    if (makeupDepartureTotal < 0) makeupDepartureTotal += 1440; // 处理跨天情况
    const mdH = Math.floor(makeupDepartureTotal / 60) % 24;
    const mdM = makeupDepartureTotal % 60;
    makeupDepartureTime = `${String(mdH).padStart(2, '0')}:${String(mdM).padStart(2, '0')}`;

    // 大队出发时间：基准时间前1小时
    let teamDepartureTotal = deptBaseTotalMinutes - 60;
    if (teamDepartureTotal < 0) teamDepartureTotal += 1440;
    const tdH = Math.floor(teamDepartureTotal / 60) % 24;
    const tdM = teamDepartureTotal % 60;
    teamDepartureTime = `${String(tdH).padStart(2, '0')}:${String(tdM).padStart(2, '0')}`;

    // 导演出发时间：基准时间前45分钟
    let directorDepartureTotal = deptBaseTotalMinutes - 45;
    if (directorDepartureTotal < 0) directorDepartureTotal += 1440;
    const ddH = Math.floor(directorDepartureTotal / 60) % 24;
    const ddM = directorDepartureTotal % 60;
    directorDepartureTime = `${String(ddH).padStart(2, '0')}:${String(ddM).padStart(2, '0')}`;

    // 群演出发时间：基准时间前30分钟
    let extrasDepartureTotal = deptBaseTotalMinutes - 30;
    if (extrasDepartureTotal < 0) extrasDepartureTotal += 1440;
    const edH = Math.floor(extrasDepartureTotal / 60) % 24;
    const edM = extrasDepartureTotal % 60;
    extrasDepartureTime = `${String(edH).padStart(2, '0')}:${String(edM).padStart(2, '0')}`;

    // 如果有具体数据，优先使用
    if (data.departure_time) {
        teamDepartureTime = data.departure_time;
    }
    if (data.makeup_departure) {
        makeupDepartureTime = data.makeup_departure;
    }
    if (data.director_departure) {
        directorDepartureTime = data.director_departure;
    }
    if (data.extras_departure) {
        extrasDepartureTime = data.extras_departure;
    }

    // console.log('🔍 调试：计算出的时间:', {
    //     makeupDepartureTime,
    //     teamDepartureTime,
    //     directorDepartureTime,
    //     extrasDepartureTime
    // });

    // 返回基本信息对象
    const basicInfo = {
        title: title,
        date: data.shooting_date || (shootingDay?.shootDay?.date) || "未指定日期",
        weather: data.weather || (shootingDay?.shootDay?.weather) || "未指定",
        day: data.shooting_day || 1,
        shootingLocation: shootingLocation,
        director: data.project?.director || "未指定",
        makeupTimes: makeupTimes,
        departureTimes: departureTimes,
        departureTime: teamDepartureTime, // 大队出发时间
        wakeUpTime: data.wake_up_time || "06:00",
        breakfastTime: data.breakfast_time || "07:30",
        roles: roleArray,
        staff: staffFromCrew, // 使用从最后一天crew.keyCrew提取的剧组人员
        producerNotes: data.special_notes || "无特殊情况",
        departmentNotes: "请各部门做好准备",
        specialNotes: data.special_notes || "无",
        departmentDetails: productionNotes || "请各部门按照拍摄计划做好准备，确保设备正常，人员到位。",
        nextDate: nextDateStr,
        nextLocation: nextLocation,
        nextScenes: nextScenes,
        preparation: preparation,
        // 生成联系方式信息 - 排除指定角色
        contactInfo: fullKeyCrew.length > 0 ? 
            `联系方式：${fullKeyCrew.filter(member => {
                // 排除这些角色
                const excludedRoles = ['副导演', '制片主任', '录音师', '照明师', '服装师', '化妆师', '道具师', '美术师', '外联制片', '生活制片'];
                return !excludedRoles.includes(member.role);
            }).map(member => `${member.role}：${member.name} ${member.phone || ''}`).join('；')}` : 
            "联系方式：请相关工作人员保持通讯畅通",
        makeupDeparture: makeupDepartureTime, // 梳化服道时间
        directorDeparture: directorDepartureTime, // 导演出发时间
        extrasDeparture: extrasDepartureTime // 群演出发时间
    };

    // console.log('🔍 调试：最终提取的基本信息');
    // console.log('basicInfo.title:', basicInfo.title);
    // console.log('完整的 basicInfo:', basicInfo);

    return basicInfo;
}


// 显示JSON错误详情
function showJSONError(error, jsonInput) {
    const messageArea = document.getElementById('messageArea');

    let errorMessage = `JSON解析错误: ${error.message}\n\n`;

    // 尝试提取错误位置
    const match = error.message.match(/position (\d+)/);
    if (match) {
        const errorPosition = parseInt(match[1]);

        // 计算错误行和列
        const textBeforeError = jsonInput.substring(0, errorPosition);
        const lineNumber = (textBeforeError.match(/\n/g) || []).length + 1;
        const columnNumber = errorPosition - textBeforeError.lastIndexOf('\n');

        errorMessage += `错误位置: 第${lineNumber}行，第${columnNumber}列\n\n`;

        // 显示错误周围的上下文
        const lines = jsonInput.split('\n');
        const startLine = Math.max(0, lineNumber - 3);
        const endLine = Math.min(lines.length, lineNumber + 2);

        errorMessage += "错误上下文:\n";
        for (let i = startLine; i < endLine; i++) {
            const line = lines[i];
            const lineNum = i + 1;

            if (lineNum === lineNumber) {
                errorMessage += `→ ${lineNum}: ${line}\n`;

                let pointer = '  ';
                for (let j = 0; j < columnNumber + lineNum.toString().length + 2; j++) {
                    pointer += ' ';
                }
                pointer += '^\n';
                errorMessage += pointer;
            } else {
                errorMessage += `  ${lineNum}: ${line}\n`;
            }
        }
    }

    messageArea.innerHTML = `<div class="error-message">${errorMessage}</div>`;
}

// 显示消息
function showMessage(message, type = 'info') {
    const messageArea = document.getElementById('messageArea');
    const className = type === 'error' ? 'error-message' : 'success-message';
    messageArea.innerHTML = `<div class="${className}">${message}</div>`;
}


// 页面加载时初始化
window.onload = function() {
    const textarea = document.getElementById('jsonInput');
    textarea.placeholder = "请粘贴完整的拍摄数据JSON...\n\n提示：系统支持多日拍摄数据，加载后可选择不同日期查看。";

    // 添加键盘快捷键
    textarea.addEventListener('keydown', function(e) {
        if (e.ctrlKey && e.key === 'Enter') {
            generateShootingNotice();
        }
    });

    // 初始化当前日期为今天
    //currentDate = new Date();

    // 自动加载数据（如果URL中有task_id）
    autoLoadData();
};
