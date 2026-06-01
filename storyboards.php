<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到首页
    header('Location: index.html');
    exit(0);
}


// 检查用户是否有"分镜管理"的历史任务，并获取当前task_id
$currentTaskId = null;

// 引入配置文件
require_once __DIR__ . '/config.php';

// 获取当前用户ID
$userId = $_SESSION['user_id'];

try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 1. 从crew表中获取当前任务号
    $sql = "SELECT current_task_id FROM crew WHERE admin_user_id = :user_id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();

    $crewResult = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($crewResult && !empty($crewResult['current_task_id'])) {
        $currentTaskId = $crewResult['current_task_id'];
        error_log("从crew表获取到current_task_id: {$currentTaskId}");
    } else {
        // 2. 如果crew表中没有，则查询用户的"storyboard_management"历史任务
        $sql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type IN ('storyboard_management', 'script_to_storyboard') AND task_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result && !empty($result['task_id'])) {
            $currentTaskId = $result['task_id'];
            error_log("从tasks表获取到task_id: {$currentTaskId}");
        }
    }
} catch (Exception $e) {
    // 数据库查询失败时，记录错误但继续执行
    error_log("获取当前task_id失败: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">

</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>

    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="tab active">分镜管理</div>
            <button class="btn btn-primary btn-dropdown">
                <i class="fas fa-plus"></i> 新建
                <i class="fas fa-caret-down"></i>
            </button>
            <div class="btn-group">
                <button class="btn btn-secondary" id="expand-all-scenes">
                    <i class="fas fa-expand-alt"></i> 展开所有
                </button>
                <button class="btn btn-secondary" id="collapse-all-scenes">
                    <i class="fas fa-compress-alt"></i> 收缩所有
                </button>
                <button class="btn btn-danger" id="delete-all-scenes">
                    <i class="fas fa-trash"></i> 删除所有
                </button>
            </div>
        </div>
        <div class="function-right">
            <div class="btn-group">
                <button class="btn btn-secondary btn-dropdown">
                    <i class="fas fa-cog"></i> 分镜设置
                    <i class="fas fa-caret-down"></i>
                </button>
                <button class="btn btn-secondary">
                    <i class="fas fa-columns"></i> 列设置
                </button>
            </div>
        </div>
    </div>

    <!-- 浮动提示条 -->
    <div class="floating-bar hidden">
        <div class="bar-content">
            <div class="bar-info">
                <i class="fas fa-info-circle"></i>
                <div class="bar-text">
                    <strong>关于制作分镜</strong>
                    <span>分镜的基础单元是镜头，在这里您可以按照您的想法完善镜头信息</span>
                </div>
            </div>
            <button class="btn btn-primary">生成全部分镜图</button>
            <div class="bar-actions">
                <span>如果已经完成了分镜的制作，您可以</span>
                <button class="btn btn-primary">预览故事板</button>
            </div>
            <!-- 积分消耗提示 -->
            <div class="points-notice">
                <i class="fas fa-coins"></i>
                <span><strong>积分提示：</strong>生成参考图每张消耗<?php echo Config::IMAGE_GENERATION_COST; ?>积分</span>
            </div>
            <button class="btn-icon close-btn"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <!-- 主内容区 -->
    <main class="main-content" id="pageContent" style="display: none;">
        <!-- 分镜表格 -->
        <div class="storyboard-table-container">
            <table class="storyboard-table">
                <thead>
                    <tr>
                        <th class="fixed-col">排序</th>
                        <th class="fixed-col">镜号</th>
                        <th>参考画面</th>
                        <th>运镜画面</th>
                        <th>景别</th>
                        <th>时长(秒)</th>
                        <th>内容</th>
                        <th>备注</th>
                        <th>场景预期</th>
                        <th>声音</th>
                        <th>摄像机角度</th>
                        <th>运镜</th>
                        <th>摄像机装备</th>
                        <th>镜头焦段</th>
                        <th>构图与焦点</th>
                        <th>光线与色调</th>
                        <th>地点</th>
                        <th>时间</th>
                        <th>天气</th>
                        <th>台词</th>
                        <th>剧本</th>
                        <th>角色清单</th>
                        <th>各角色推荐服装</th>
                        <th>各角色推荐妆造</th>
                        <th>角色动作</th>
                        <th>道具</th>
                    </tr>
                </thead>
                <tbody id="storyboard-table-body">
                    <!-- 动态加载数据 -->
                </tbody>
            </table>
        </div>
    </main>

    <!-- 生成参考图模态框 -->
    <div class="generate-modal-overlay" id="generateModal">
        <div class="generate-modal">
            <div class="modal-header">
                <h2>生成参考图</h2>
                <button class="modal-close-btn" id="generateModalClose">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <form id="generateImageForm">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-info-circle"></i>
                            剧本题材
                        </label>
                        <div class="genres-display" id="modalGenresDisplay">
                            <span class="genres-loading">加载中...</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-map-marked-alt"></i>
                            时空场景
                            <div class="scene-tags" id="modalSceneTags">
                                <span class="scene-tag scene-name-tag">加载中...</span>
                                <span class="scene-tag scene-shot-tag">加载中...</span>
                            </div>
                        </label>
                        <div class="space-scene-display" id="modalSpaceSceneDisplay">
                            <span class="space-scene-loading">加载中...</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-users"></i>
                            角色
                        </label>
                        <div class="characters-display" id="modalCharactersDisplay">
                            <span class="characters-loading">加载中...</span>
                        </div>
                    </div>
                    <div class="form-group" id="modalReferenceImageContainer" style="display: none;">
                        <label class="form-label">
                            <i class="fas fa-image"></i>
                            参考图
                        </label>
                        <div class="reference-image-container" style="display: flex; justify-content: center; align-items: center; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                            <img id="modalReferenceImage" src="" alt="参考图" style="max-width: 100%; max-height: 300px; object-fit: contain; border-radius: 4px;">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="promptInput">
                            <i class="fas fa-keyboard"></i>
                            提示词
                            <div class="grid-selector">
                                <button type="button" class="grid-btn" data-grid="2x2" data-count="4">四宫格</button>
                                <button type="button" class="grid-btn active" data-grid="3x3" data-count="9">九宫格</button>
                                <button type="button" class="grid-btn" data-grid="4x4" data-count="16">十六宫格</button>
                                <button type="button" class="grid-btn" data-grid="5x5" data-count="25">二十五宫格</button>
                                <button type="button" class="grid-btn" data-grid="6x6" data-count="36">三十六宫格</button>
                            </div>
                        </label>
                        <textarea
                            id="promptInput"
                            class="form-control"
                            rows="4"
                            placeholder="请输入图片描述..."></textarea>
                    </div>
                    <style>
                        .grid-selector {
                            display: inline-block;
                            margin-left: 20px;
                            vertical-align: middle;
                        }

                        .grid-btn {
                            display: inline-block;
                            padding: 4px 10px;
                            margin-right: 8px;
                            background: #f8f9fa;
                            border: 1px solid #dee2e6;
                            border-radius: 4px;
                            font-size: 12px;
                            cursor: pointer;
                            transition: all 0.2s ease;
                        }

                        .grid-btn:hover {
                            background: #e9ecef;
                            border-color: #adb5bd;
                        }

                        .grid-btn.active {
                            background: #007bff;
                            color: white;
                            border-color: #007bff;
                        }

                        /* 时空场景标签样式 */
                        .scene-tags {
                            display: inline-flex;
                            gap: 8px;
                            margin-left: 15px;
                            vertical-align: middle;
                        }

                        .scene-tag {
                            display: inline-block;
                            padding: 2px 8px;
                            border-radius: 12px;
                            font-size: 12px;
                            font-weight: 600;
                        }

                        .scene-name-tag {
                            background: #e3f2fd;
                            color: #1976d2;
                            border: 1px solid #bbdefb;
                        }

                        .scene-shot-tag {
                            background: #e8f5e8;
                            color: #388e3c;
                            border: 1px solid #c8e6c9;
                        }

                        /* 时空场景显示样式 */
                        .space-scene-display {
                            display: flex;
                            flex-wrap: wrap;
                            gap: 10px;
                            margin-top: 10px;
                            padding: 10px;
                            background: #f8f9fa;
                            border-radius: 8px;
                            border: 1px solid #dee2e6;
                        }

                        .space-scene-item {
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            gap: 5px;
                            padding: 10px;
                            background: white;
                            border-radius: 6px;
                            border: 1px solid #e9ecef;
                            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
                            max-width: 150px;
                        }

                        .space-scene-image {
                            width: 100%;
                            max-width: 130px;
                            max-height: 80px;
                            object-fit: cover;
                            border-radius: 4px;
                            border: 1px solid #dee2e6;
                        }

                        .space-scene-name {
                            font-size: 12px;
                            font-weight: 600;
                            color: #495057;
                            text-align: center;
                            white-space: nowrap;
                            overflow: hidden;
                            text-overflow: ellipsis;
                            max-width: 130px;
                        }
                    </style>
                    <script>
                        document.addEventListener('DOMContentLoaded', function() {
                            const gridButtons = document.querySelectorAll('.grid-btn');
                            const promptInput = document.getElementById('promptInput');

                            gridButtons.forEach(button => {
                                button.addEventListener('click', function() {
                                    // 移除所有按钮的激活状态
                                    gridButtons.forEach(btn => btn.classList.remove('active'));
                                    // 激活当前按钮
                                    this.classList.add('active');

                                    // 获取当前按钮的数据
                                    const grid = this.getAttribute('data-grid');
                                    const count = this.getAttribute('data-count');

                                    // 获取当前提示词内容
                                    let promptText = promptInput.value;

                                    // 替换内容
                                    // 先替换网格大小
                                    promptText = promptText.replace(/\[\d+x\d+\]/g, `[${grid}]`);
                                    // 再替换数量
                                    promptText = promptText.replace(/\[\d+\]/g, `[${count}]`);

                                    // 更新提示词内容
                                    promptInput.value = promptText;
                                });
                            });
                        });

                        // 加载时空场景数据
                        function loadSpaceSceneData(sceneId, shotId) {
                            const sceneTags = document.getElementById('modalSceneTags');
                            const spaceSceneDisplay = document.getElementById('modalSpaceSceneDisplay');
                            const sceneNameTag = sceneTags.querySelector('.scene-name-tag');
                            const sceneShotTag = sceneTags.querySelector('.scene-shot-tag');

                            // 重置显示
                            sceneNameTag.textContent = '加载中...';
                            sceneShotTag.textContent = '加载中...';
                            spaceSceneDisplay.innerHTML = '<span class="space-scene-loading">加载中...</span>';

                            // 获取当前场次信息
                            fetch(`get_scene_info.php?scene_id=${sceneId}&user_id=<?php echo $_SESSION['user_id']; ?>&crew_id=<?php echo $_SESSION['crew_id'] ?? 0; ?>`)
                                .then(response => response.json())
                                .then(sceneData => {
                                    if (sceneData.success) {
                                        // 更新场次名称标签
                                        sceneNameTag.textContent = sceneData.scene_name || '未知场次';

                                        // 更新场次和分镜号标签
                                        const sceneNumber = sceneId.toString().padStart(3, '0');
                                        sceneShotTag.textContent = `镜号：${sceneNumber}-${shotId}`;

                                        // 获取相关的时空场景信息
                                        return fetch(`get_space_scene.php?scene_name=${encodeURIComponent(sceneData.scene_name)}&task_id=${window.dbTaskId || ''}`);
                                    } else {
                                        sceneNameTag.textContent = '未知场次';
                                        sceneShotTag.textContent = `镜号：${sceneId.toString().padStart(3, '0')}-${shotId}`;
                                        spaceSceneDisplay.innerHTML = '<span class="space-scene-error">无法加载时空场景信息</span>';
                                        throw new Error('无法获取场次信息');
                                    }
                                })
                                .then(response => response.json())
                                .then(spaceData => {
                                    if (spaceData.success && spaceData.space) {
                                        // 显示时空场景信息
                                        const space = spaceData.space;
                                        let imageUrl = '';

                                        if (space.imageUrl) {
                                            try {
                                                const imageUrls = JSON.parse(space.imageUrl);
                                                imageUrl = imageUrls[0] || '';
                                            } catch (e) {
                                                imageUrl = space.imageUrl;
                                            }
                                        }

                                        if (imageUrl) {
                                            spaceSceneDisplay.innerHTML = `
                                                <div class="space-scene-item">
                                                    <img src="${imageUrl}" alt="${space.name}" class="space-scene-image">
                                                    <div class="space-scene-name">${space.name}</div>
                                                </div>
                                            `;
                                        } else {
                                            spaceSceneDisplay.innerHTML = '<span class="space-scene-error">该场景暂无图片</span>';
                                        }
                                    } else {
                                        spaceSceneDisplay.innerHTML = '<span class="space-scene-error">未找到相关时空场景</span>';
                                    }
                                })
                                .catch(error => {
                                    console.error('加载时空场景数据失败:', error);
                                    spaceSceneDisplay.innerHTML = '<span class="space-scene-error">加载失败</span>';
                                });
                        }
                    </script>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-palette"></i>
                            艺术风格
                            <span class="current-selection" id="modalCurrentStyle">线稿手绘</span>
                        </label>
                        <div class="presets-grid" id="modalStylePresets">
                        </div>
                        <input type="hidden" id="modalStyle" value="12">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-crop-alt"></i>
                            图片比例
                            <span class="current-selection" id="modalCurrentRatio">横屏 16:9</span>
                        </label>
                        <div class="presets-grid" id="modalRatioPresets">
                        </div>
                        <input type="hidden" id="modalPicSize" value="16:9">
                    </div>
                </form>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" id="modalCancelBtn">
                    <i class="fas fa-times"></i> 取消
                </button>
                <button type="button" class="btn btn-primary" id="modalGenerateBtn">
                    <i class="fas fa-magic"></i> 生成图片
                </button>
            </div>
        </div>
    </div>
    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script>
        // 从数据库中获取的当前任务号，优先级最高
        window.dbTaskId = <?php echo $currentTaskId ? '"' . $currentTaskId . '"' : 'null'; ?>;
        // 获取当前用户ID
        window.currentUserId = <?php echo $userId; ?>;
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
    </script>
    <script src="js/main.js?v=<?php echo time(); ?>"></script>
    <script>
        // 页面加载时检查登录状态，保护页面访问
        document.addEventListener('DOMContentLoaded', function() {
            checkLoginStatus(true);
        });
    </script>
</body>

</html>
