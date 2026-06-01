// 页面加载完成后初始化
document.addEventListener('DOMContentLoaded', function () {
    // 检查登录状态，保护页面访问
    checkLoginStatus(true);

    loadAnnouncementData();
    setupEventListeners();
});

// 存储当前日期和所有拍摄日期
let currentDateString = '';
let shootingDates = [];

// 设置事件监听器
function setupEventListeners() {
    // 日期导航
    if (document.getElementById('prev-day')) {
        document.getElementById('prev-day').addEventListener('click', function () {
            navigateToPreviousDay();
        });
    }

    if (document.getElementById('next-day')) {
        document.getElementById('next-day').addEventListener('click', function () {
            navigateToNextDay();
        });
    }

    // 新建通告
    if (document.getElementById('new-announcement')) {
        document.getElementById('new-announcement').addEventListener('click', function () {
            showNotification('新建拍摄通告');
        });
    }

    // 打印通告
    if (document.getElementById('print-announcement')) {
        document.getElementById('print-announcement').addEventListener('click', function () {
            window.print();
        });
    }

    // 按钮事件
    if (document.getElementById('edit-announcement')) {
        document.getElementById('edit-announcement').addEventListener('click', function () {
            showNotification('编辑拍摄通告');
        });
    }

    if (document.getElementById('export-pdf')) {
        document.getElementById('export-pdf').addEventListener('click', function () {
            showNotification('导出PDF');
        });
    }

    if (document.getElementById('send-email')) {
        document.getElementById('send-email').addEventListener('click', function () {
            showNotification('邮件发送');
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

// 导航到前一天
function navigateToPreviousDay() {
    const currentIndex = shootingDates.indexOf(currentDateString);
    if (currentIndex > 0) {
        const prevDate = shootingDates[currentIndex - 1];
        loadAnnouncementData(prevDate);
        showNotification(`跳转到 ${formatDateForDisplay(prevDate)} 的拍摄通告`);
    }
}

// 导航到后一天
function navigateToNextDay() {
    const currentIndex = shootingDates.indexOf(currentDateString);
    if (currentIndex < shootingDates.length - 1) {
        const nextDate = shootingDates[currentIndex + 1];
        loadAnnouncementData(nextDate);
        showNotification(`跳转到 ${formatDateForDisplay(nextDate)} 的拍摄通告`);
    }
}

// 格式化日期用于显示
function formatDateForDisplay(dateString) {
    const date = new Date(dateString);
    const year = date.getFullYear();
    const month = date.getMonth() + 1;
    const day = date.getDate();
    const weekdays = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
    const weekday = weekdays[date.getDay()];
    return `${year}年${month}月${day}日 (${weekday})`;
}

// 加载拍摄通告数据
async function loadAnnouncementData(date = null) {
    try {
        // 按照优先级获取task_id：
        // 1. 首先使用从数据库中获取的task_id（window.dbTaskId，优先级最高）
        // 2. 否则使用URL参数中的task_id
        // 3. 如果都没有，使用默认的announcement-data.json文件
        const urlParams = new URLSearchParams(window.location.search);
        const urlTaskId = urlParams.get('task_id');
        const taskId = window.dbTaskId || urlTaskId;

        // 新增：判断taskId的有效性，并根据情况决定是否请求接口
        if (taskId !== null && taskId !== undefined && typeof taskId === 'string' && taskId.trim().length > 0) {
            // 构建results目录下的文件路径（从js目录需要返回上一级）
            const resultsFilePath = `../results/${taskId}_announcement.json`;

            // 尝试检查文件是否存在
            try {
                const checkResponse = await fetch(resultsFilePath, { method: 'HEAD' });
                if (!checkResponse.ok) {
                    // 文件不存在，请求接口（返回根目录）
                    console.log(`文件 ${resultsFilePath} 不存在，开始请求接口...`);
                    const apiResponse = await fetch(`../announcement_api.php?task_id=${encodeURIComponent(taskId)}`);

                    if (apiResponse.ok) {
                        console.log('接口请求成功');
                        // 注意：这里只是发起请求，不处理响应数据
                        // 接口应负责生成文件，然后我们继续加载文件
                    } else {
                        console.warn(`接口请求失败: ${apiResponse.status}`);
                    }
                } else {
                    console.log(`文件 ${resultsFilePath} 已存在`);
                }
            } catch (checkError) {
                console.warn('检查文件存在性时出错:', checkError);
                // 检查失败时也尝试请求接口
                try {
                    await fetch(`../announcement_api.php?task_id=${encodeURIComponent(taskId)}`);
                } catch (apiError) {
                    console.warn('接口请求失败:', apiError);
                }
            }
        }

        // 构建JSON文件路径
        const url = taskId
            ? `../results/${taskId}_announcement.json`
            : '../json/announcement-data.json';

        const response = await fetch(url);
        if (!response.ok) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }
        const data = await response.json();

        // 获取所有拍摄日期
        shootingDates = Object.keys(data.shootingDays).sort();

        // 如果没有指定日期，则使用第一个日期
        if (!date && shootingDates.length > 0) {
            date = shootingDates[0];
        }

        // 检查指定日期是否存在
        if (date && data.shootingDays[date]) {
            currentDateString = date;
            // 更新显示的日期
            document.getElementById('current-date').textContent = formatDateForDisplay(date);
            // 渲染指定日期的数据
            renderAnnouncement(data.shootingDays[date], data.project);
        } else {
            document.getElementById('announcement-content').innerHTML =
                '<div class="error-message">暂无拍摄通告数据</div>';
        }
    } catch (error) {
        // console.error('加载拍摄通告数据失败:', error);
        document.getElementById('announcement-content').innerHTML =
            `<div class="error-message">
                <p>加载数据失败，请稍后重试。</p>
                <p>错误信息: ${error.message}</p>
            </div>`;
    }
}

// 渲染拍摄通告
function renderAnnouncement(dayData, project) {
    const container = document.getElementById('announcement-content');
    const shootDay = dayData.shootDay;
    const scenes = dayData.scenes;
    const crew = dayData.crew;

    if (!scenes || scenes.length === 0) {
        container.innerHTML = '<div class="error-message">暂无拍摄通告数据</div>';
        return;
    }

    // 生成HTML
    let html = `
        <div class="announcement-container">
            <div class="document-header">
                <div class="document-title">拍摄通告</div>
                <div class="document-subtitle">《${project.name}》拍摄通告</div>
            </div>

            <div class="document-meta">
                <div class="meta-item">
                    <div class="meta-label">拍摄日期</div>
                    <div class="meta-value">${formatDateForDisplay(shootDay.date)}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">天气预报</div>
                    <div class="meta-value"><i class="fas fa-sun weather-icon"></i>${shootDay.weather}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">拍摄地点</div>
                    <div class="meta-value">${shootDay.location}</div>
                </div>
            </div>

            <div class="document-meta">
                <div class="meta-item">
                    <div class="meta-label">集合时间</div>
                    <div class="meta-value">${shootDay.callTime}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">开机时间</div>
                    <div class="meta-value">${shootDay.shootTime}</div>
                </div>
                <div class="meta-item">
                    <div class="meta-label">收工时间</div>
                    <div class="meta-value">${shootDay.wrapTime}</div>
                </div>
            </div>

            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-button active" data-tab="scenes">拍摄场次</button>
                    <button class="tab-button" data-tab="cast">演员安排</button>
                    <button class="tab-button" data-tab="costume">服装清单</button>
                    <button class="tab-button" data-tab="props">道具清单</button>
                    <button class="tab-button" data-tab="crew">工作人员</button>
                    <button class="tab-button" data-tab="equipment">设备清单</button>
                    <button class="tab-button" data-tab="notes">注意事项</button>
                </div>

                <div class="tab-content active" id="scenes-tab">
                    <div class="section">
                        <div class="section-title">拍摄场次</div>
                        <ul class="scene-list">`;

    // 添加所有场次信息
    scenes.forEach((scene, sceneIndex) => {
        // 计算场次完成率
        const totalSceneShots = scene.shots ? scene.shots.length : 0;
        const completedSceneShots = scene.shots ? scene.shots.filter(shot => shot.status === 'completed').length : 0;
        const sceneCompletionRate = totalSceneShots > 0 ? Math.round((completedSceneShots / totalSceneShots) * 100) : 0;

        html += `
            <li class="scene-item">
                <div class="scene-header">
                    <div class="scene-number">场次 ${scene.sceneId} - ${scene.sceneName}</div>
                    <div class="scene-location">${scene.setting} | ${scene.location}</div>
                </div>
                <div>时间: ${scene.estimatedTime} ${scene.actualTime ? `| 实际: ${scene.actualTime}` : ''} | 页码（仅供参考）: ${scene.pageNumbers} | 完成率: ${sceneCompletionRate}%</div>
                <div class="shot-list">`;

        // 添加镜头列表
        if (scene.shots) {
            scene.shots.forEach(shot => {
                html += `
                    <div class="shot-item">
                        <span class="shot-id">${shot.shotId}.</span>
                        <span class="shot-type">${shot.originalShotId}</span>
                        <span class="shot-type">${shot.shotType}</span>
                        <span class="shot-duration">${shot.duration || 0}秒</span>
                        <span class="status-indicator ${shot.status === 'completed' ? 'status-completed' : shot.status === 'in-progress' ? 'status-in-progress' : 'status-not-started'}" 
                              style="display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-left: 10px;"></span>
                    </div>`;
            });
        }

        html += `
                </div>
                <div class="scene-details">
                    <div class="detail-item">
                        <span class="detail-label">剧本:</span>
                        <span>${scene.scriptNotes}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">时长:</span>
                        <span>预计 ${scene.shots ? scene.shots.reduce((total, shot) => total + (shot.duration || 0), 0) : 0} 秒</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">导演:</span>
                        <span>${project.director}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">摄影:</span>
                        <span>${scene.shots && scene.shots.length > 0 && scene.shots[0].equipment ? scene.shots[0].equipment.camera : '未指定'}</span>
                    </div>
                </div>
            </li>`;
    });

    html += `
                        </ul>
                    </div>
                </div>

                <div class="tab-content" id="cast-tab">
                    <div class="section">
                        <div class="section-title">演员安排</div>
                        <div class="cast-list">`;

    // 收集所有演员信息
    const castMembers = [];
    scenes.forEach(scene => {
        if (scene.cast) {
            scene.cast.forEach(cast => {
                // 处理可能包含多个角色的字符串
                const characters = cast.character.split(/[,\，]/).map(c => c.trim()).filter(c => c);

                characters.forEach(character => {
                    // 查找是否已经有这个角色的记录
                    const existingCast = castMembers.find(m => m.character === character);

                    // 如果还没有这个角色的记录，则添加
                    if (!existingCast) {
                        // 创建一个新的角色记录，将角色名更新为单个角色
                        const newCast = { ...cast, character: character };
                        castMembers.push(newCast);
                    }
                });
            });
        }
    });

    // 添加演员信息
    castMembers.forEach(cast => {
        html += `
            <div class="cast-item">
                <div class="role">${cast.character}</div>
                <div class="actor">${cast.actor}</div>
                <div>呼叫时间: ${cast.callTime} | 到达时间: ${cast.arrivalTime}</div>
                <div>戏服: ${cast.costume}</div>
                <div>化妆: ${cast.makeup}</div>
                <div>备注: ${cast.notes}</div>
            </div>`;
    });

    html += `
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="costume-tab">
                    <div class="section">
                        <div class="section-title">服装清单</div>
                        <div class="costume-list">`;

    // 收集所有角色的服装信息
    const costumeData = {};

    // 使用已经去重的演员信息来收集服装数据
    castMembers.forEach(cast => {
        if (!costumeData[cast.character]) {
            costumeData[cast.character] = {
                actor: cast.actor,
                costumes: new Set(),
                makeup: new Set()
            };
        }

        if (cast.costume && cast.costume !== "无") {
            // 处理可能包含多个服装的字符串
            const individualCostumes = cast.costume.split(/[,\，]/).map(c => c.trim()).filter(c => c);
            individualCostumes.forEach(c => costumeData[cast.character].costumes.add(c));
        }

        if (cast.makeup && cast.makeup !== "无") {
            // 处理可能包含多个妆造的字符串
            const individualMakeups = cast.makeup.split(/[,\，]/).map(m => m.trim()).filter(m => m);
            individualMakeups.forEach(m => costumeData[cast.character].makeup.add(m));
        }
    });

    // 添加服装信息
    for (const [character, data] of Object.entries(costumeData)) {
        html += `
            <div class="costume-item">
                <div class="character-name">${character} (${data.actor})</div>
                <div class="costume-details">
                    <div class="detail-label">服装要求:</div>
                    <div>${Array.from(data.costumes).join(', ') || '未指定'}</div>
                </div>
                <div class="makeup-details">
                    <div class="detail-label">妆造要求:</div>
                    <div>${Array.from(data.makeup).join(', ') || '未指定'}</div>
                </div>
            </div>`;
    }

    html += `
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="props-tab">
                    <div class="section">
                        <div class="section-title">道具清单</div>
                        <div class="props-list">`;

    // 收集所有道具信息
    const propsSet = new Set();
    scenes.forEach(scene => {
        // 收集场景道具
        if (scene.setDressing) {
            scene.setDressing.forEach(prop => {
                if (prop && prop !== "无") {
                    // 处理可能包含多个道具的字符串
                    const individualProps = prop.split(/[,\，]/).map(p => p.trim()).filter(p => p);
                    individualProps.forEach(p => propsSet.add(p));
                }
            });
        }

        // 收集特殊设备作为道具
        if (scene.specialEquipment) {
            scene.specialEquipment.forEach(prop => {
                if (prop && prop !== "无") {
                    // 处理可能包含多个道具的字符串
                    const individualProps = prop.split(/[,\，]/).map(p => p.trim()).filter(p => p);
                    individualProps.forEach(p => propsSet.add(p));
                }
            });
        }

        // 收集镜头道具
        if (scene.shots) {
            scene.shots.forEach(shot => {
                if (shot.props) {
                    shot.props.forEach(prop => {
                        if (prop && prop !== "无") {
                            // 处理可能包含多个道具的字符串
                            const individualProps = prop.split(/[,\，]/).map(p => p.trim()).filter(p => p);
                            individualProps.forEach(p => propsSet.add(p));
                        }
                    });
                }
            });
        }

        // 收集场景级别的道具
        if (scene.props) {
            scene.props.forEach(prop => {
                if (prop && prop !== "无") {
                    // 处理可能包含多个道具的字符串
                    const individualProps = prop.split(/[,\，]/).map(p => p.trim()).filter(p => p);
                    individualProps.forEach(p => propsSet.add(p));
                }
            });
        }
    });

    // 添加道具信息
    if (propsSet.size > 0) {
        // 对道具进行排序并去重
        const sortedProps = Array.from(propsSet).sort();
        sortedProps.forEach(prop => {
            html += `
            <div class="prop-item">
                <div class="prop-name">${prop}</div>
                <div class="prop-status">
                    <span class="status-indicator status-not-started" style="display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 10px;"></span>
                    待准备
                </div>
            </div>`;
        });
    } else {
        html += `<div class="no-data">暂无道具信息</div>`;
    }

    html += `
                        </div>
                    </div>
                </div>

                <div class="tab-content" id="crew-tab">
                    <div class="section">
                        <div class="section-title">工作人员</div>
                        <div class="crew-list">`;

    // 添加关键工作人员信息
    if (crew && crew.keyCrew) {
        crew.keyCrew.forEach(member => {
            html += `
            <div class="crew-item">
                <div class="role">${member.role}</div>
                <div class="actor">${member.name}</div>
                <div>电话: ${member.phone}</div>
                <div>邮箱: ${member.email}</div>
                <div>呼叫时间: ${member.callTime}</div>
            </div>`;
        });
    }

    html += `
                        </div>
                    </div>`;

    if (crew && crew.departments) {
        html += `
                    <div class="section">
                        <div class="section-title">各部门人员</div>
                        <div class="crew-list">`;

        // 添加各部门人员信息
        crew.departments.forEach(dept => {
            html += `
            <div class="crew-item">
                <div class="role">${dept.name}</div>
                <div class="actor">${dept.members ? dept.members.join(', ') : ''}</div>
            </div>`;
        });

        html += `
                        </div>
                    </div>`;
    }

    if (dayData.transportation || dayData.meals) {
        html += `
                    <div class="section">
                        <div class="section-title">交通与餐饮</div>`;

        if (dayData.transportation) {
            html += `
                        <div class="detail-item">
                            <span class="detail-label">剧组交通:</span>
                            <span>${dayData.transportation.crewTransport} - 出发时间: ${dayData.transportation.departureTime}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">演员交通:</span>
                            <span>${dayData.transportation.castTransport}</span>
                        </div>`;
        }

        if (dayData.meals) {
            html += `
                        <div class="detail-item">
                            <span class="detail-label">早餐:</span>
                            <span>${dayData.meals.breakfast && dayData.meals.breakfast.provided ? `${dayData.meals.breakfast.time}于${dayData.meals.breakfast.location}提供` : '不提供'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">午餐:</span>
                            <span>${dayData.meals.lunch && dayData.meals.lunch.provided ? `${dayData.meals.lunch.time}于${dayData.meals.lunch.location}提供` : '不提供'}</span>
                        </div>`;
        }

        html += `
                    </div>`;
    }

    html += `
                </div>

                <div class="tab-content" id="equipment-tab">
                    <div class="section">
                        <div class="section-title">设备清单</div>`;

    if (dayData.equipment) {
        html += `
                        <div class="equipment-list">`;

        if (dayData.equipment.cameras) {
            html += `
                            <div class="equipment-category">
                                <h4>摄影设备</h4>`;

            // 添加摄影设备
            dayData.equipment.cameras.forEach(cam => {
                html += `
                                <div class="equipment-item">
                                    ${cam.item} (${cam.quantity}台) - 负责人: ${cam.assignedTo}
                                    <span class="status-indicator ${cam.status === 'in-use' ? 'status-in-progress' : cam.status === 'available' ? 'status-not-started' : 'status-completed'}" 
                                          style="float: right; width: 12px; height: 12px; border-radius: 50%;"></span>
                                </div>`;
            });

            html += `
                            </div>`;
        }

        if (dayData.equipment.lenses) {
            html += `
                            <div class="equipment-category">
                                <h4>镜头设备</h4>`;

            // 添加镜头设备
            dayData.equipment.lenses.forEach(lens => {
                html += `
                                <div class="equipment-item">
                                    ${lens.item} (${lens.quantity}个) - 负责人: ${lens.assignedTo}
                                    <span class="status-indicator ${lens.status === 'in-use' ? 'status-in-progress' : lens.status === 'available' ? 'status-not-started' : 'status-completed'}" 
                                          style="float: right; width: 12px; height: 12px; border-radius: 50%;"></span>
                                </div>`;
            });

            html += `
                            </div>`;
        }

        if (dayData.equipment.lighting) {
            html += `
                            <div class="equipment-category">
                                <h4>灯光设备</h4>`;

            // 添加灯光设备
            dayData.equipment.lighting.forEach(light => {
                html += `
                                <div class="equipment-item">
                                    ${light.item} (${light.quantity}个) - 负责人: ${light.assignedTo}
                                    <span class="status-indicator ${light.status === 'in-use' ? 'status-in-progress' : light.status === 'available' ? 'status-not-started' : 'status-completed'}" 
                                          style="float: right; width: 12px; height: 12px; border-radius: 50%;"></span>
                                </div>`;
            });

            html += `
                            </div>`;
        }

        if (dayData.equipment.sound) {
            html += `
                            <div class="equipment-category">
                                <h4>录音设备</h4>`;

            // 添加录音设备
            dayData.equipment.sound.forEach(sound => {
                html += `
                                <div class="equipment-item">
                                    ${sound.item} (${sound.quantity}个) - 负责人: ${sound.assignedTo}
                                    <span class="status-indicator ${sound.status === 'in-use' ? 'status-in-progress' : sound.status === 'available' ? 'status-not-started' : 'status-completed'}" 
                                          style="float: right; width: 12px; height: 12px; border-radius: 50%;"></span>
                                </div>`;
            });

            html += `
                            </div>`;
        }

        if (dayData.equipment.support) {
            html += `
                            <div class="equipment-category">
                                <h4>辅助设备</h4>`;

            // 添加辅助设备
            dayData.equipment.support.forEach(support => {
                html += `
                                <div class="equipment-item">
                                    ${support.item} (${support.quantity}个) - 负责人: ${support.assignedTo}
                                    <span class="status-indicator ${support.status === 'in-use' ? 'status-in-progress' : support.status === 'available' ? 'status-not-started' : 'status-completed'}" 
                                          style="float: right; width: 12px; height: 12px; border-radius: 50%;"></span>
                                </div>`;
            });

            html += `
                            </div>`;
        }

        html += `
                        </div>`;
    }

    html += `
                    </div>
                </div>

                <div class="tab-content" id="notes-tab">
                    <div class="section">
                        <div class="section-title">注意事项</div>`;

    if (dayData.notes) {
        html += `
                        <div class="notes-section">
                            <div class="notes-title">制片备注:</div>`;

        // 添加制片备注
        if (dayData.notes.productionNotes) {
            dayData.notes.productionNotes.forEach(note => {
                html += `
                            <div>${note}</div>`;
            });
        }

        html += `
                        </div>`;

        if (dayData.notes.directorNotes) {
            html += `
                        <div class="notes-section">
                            <div class="notes-title">导演备注:</div>`;

            // 添加导演备注
            dayData.notes.directorNotes.forEach(note => {
                html += `
                            <div>${note}</div>`;
            });

            html += `
                        </div>`;
        }

        if (dayData.notes.cinematographerNotes) {
            html += `
                        <div class="notes-section">
                            <div class="notes-title">摄影备注:</div>`;

            // 添加摄影备注
            dayData.notes.cinematographerNotes.forEach(note => {
                html += `
                            <div>${note}</div>`;
            });

            html += `
                        </div>`;
        }

        if (dayData.notes.castingNotes) {
            html += `
                        <div class="notes-section">
                            <div class="notes-title">演员备注:</div>`;

            // 添加演员备注
            dayData.notes.castingNotes.forEach(note => {
                html += `
                            <div>${note}</div>`;
            });

            html += `
                        </div>`;
        }

        if (dayData.safety) {
            html += `
                        <div class="notes-section">
                            <div class="notes-title">安全须知:</div>
                            <div>安全员: ${dayData.safety.safetyOfficer}</div>
                            <div>紧急联系电话: ${dayData.safety.emergencyContact}</div>
                            <div>急救站: ${dayData.safety.firstAidStation}</div>
                            <div>疏散路线: ${dayData.safety.evacuationPlan}</div>
                        </div>`;
        }
    }

    html += `
                    </div>
                </div>
            </div>`;

    if (dayData.signatureSection !== false) {
        html += `
            <div class="signature-section">
                <div class="signature-block">
                    <div>制片人签字:</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-block">
                    <div>导演签字:</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-block">
                    <div>通告日期:</div>
                    <div class="signature-line"></div>
                </div>
            </div>`;
    }

    html += `
        </div>

        `;

    container.innerHTML = html;

    // 添加标签页切换功能
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', function () {
            // 移除所有活动状态
            document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));

            // 添加当前活动状态
            this.classList.add('active');
            const tabId = this.getAttribute('data-tab');
            const contentElement = document.getElementById(tabId + '-tab');
            if (contentElement) {
                contentElement.classList.add('active');
            }
        });
    });

    // 导出Word功能
    const exportWordButton = document.getElementById('export-word');
    if (exportWordButton) {
        exportWordButton.addEventListener('click', function () {
            // 获取当前URL中的task_id参数
            const urlParams = new URLSearchParams(window.location.search);
            let taskId = urlParams.get('task_id');

            // 获取当前选中的日期
            const currentDate = document.querySelector('.date-navigation .current-date')?.textContent || '';

            // 构建导出URL
            let exportUrl = 'export_announcement.php';
            if (taskId) {
                exportUrl += '?task_id=' + taskId;
                if (currentDate) {
                    exportUrl += '&date=' + encodeURIComponent(currentDate);
                }
            } else if (currentDate) {
                exportUrl += '?date=' + encodeURIComponent(currentDate);
            }

            // 创建临时链接并触发下载
            const link = document.createElement('a');
            link.href = exportUrl;
            link.download = '拍摄通告_' + (currentDate || 'unknown') + '.doc';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        });
    }

    // 编辑通告功能
    document.getElementById('edit-announcement')?.addEventListener('click', function () {
        showNotification('编辑通告功能待开发');
    });
}
