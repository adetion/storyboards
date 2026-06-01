<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>分镜详情 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/storyboard-detail.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>

    <!-- 主要内容区 -->
    <main class="main-content" style="margin-top:120px;">
        <div class="function-bar">
            <div class="function-left">
                <button class="btn btn-primary" id="addShotBtn"><i class="fas fa-plus"></i> 分镜详情</button>
                <button class="btn btn-danger" id="deleteBtn"><i class="fas fa-trash"></i> <span style="font-size: 16px; font-weight: normal; margin-left: 20px;">镜头 <span id="shotNumber">0</span> | 场次 <span id="sceneNumber">0</span></span></button>
            </div>
            <div class="function-right">
                <button class="btn btn-secondary" id="editBtn"><i class="fas fa-edit"></i> 编辑</button>
                <button class="btn btn-primary" id="saveBtn" style="display: none;"><i class="fas fa-save"></i> 保存</button>
                <button class="btn btn-danger" id="closeBtn"><i class="fas fa-times"></i> 关闭</button>
            </div>
        </div>


        <!-- 左区：分镜画面和基本信息 -->
        <div class="storyboard-panel">
            <!-- 顶部三列区域：参考画面、分镜画面、成片预览 -->
            <div class="top-three-columns">
                <!-- 参考画面 放imageUrl字段中图片-->
                <div class="section-card column-card">
                    <h4><i class="fas fa-image"></i> 参考画面</h4>
                    <div class="drawing-canvas small-canvas">
                        <div class="reference-image-placeholder" id="referenceImagePlaceholder" style="text-align: center; padding: 20px;">
                            <i class="fas fa-image" style="font-size: 48px; color: var(--primary-color); opacity: 0.5;"></i>
                            <p style="margin-top: 10px; color: var(--text-secondary);">暂无参考画面</p>
                        </div>
                        <div class="reference-image" id="referenceImage" style="display: none; text-align: center;">
                            <img id="referenceImageSrc" src="" alt="参考画面" style="max-width: 100%; max-height: 160px; border-radius: 8px; box-shadow: var(--box-shadow);">
                        </div>
                    </div>
                </div>

                <!-- 分镜画面 放imageUrls字段中图片，宫格单图-->
                <div class="section-card column-card">
                    <h4><i class="fas fa-image"></i> 分镜画面</h4>
                    <div class="drawing-canvas small-canvas">
                        <!-- 分镜画面区域 -->
                        <div class="canvas-placeholder" id="canvasPlaceholder">
                            <i class="fas fa-image"></i>
                            <p>分镜画面</p>
                        </div>
                        <div class="canvas-image" id="canvasImage" style="display: none;">
                            <img id="shotImage" src="" alt="分镜画面">
                        </div>
                    </div>
                </div>

                <!-- 成片预览 放videoCutUrl字段中视频 -->
                <div class="section-card column-card">
                    <h4><i class="fas fa-film"></i> 成片预览</h4>
                    <div class="video-preview-container small-preview">
                        <div class="video-placeholder" id="videoPreviewPlaceholder">
                            <i class="fas fa-video"></i>
                            <p>暂无成片预览</p>
                        </div>
                        <div class="video-preview" id="videoPreview" style="display: none;">
                            <video id="previewVideo" controls>
                                <source id="previewVideoSource" src="" type="video/mp4">
                                您的浏览器不支持视频播放
                            </video>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 运镜画面 放video_image_Url字段中多图（横向放缩略图）-->
            <div class="section-card camera-movement-section">
                <h4><i class="fas fa-video"></i> 运镜画面</h4>
                <div class="video-image-thumbnails drawing-canvas small-canvas" id="videoImageThumbnails">
                    <div class="video-placeholder">
                        <i class="fas fa-images"></i>
                        <p>暂无运镜画面</p>
                    </div>
                </div>
            </div>

            <!-- 提示词 -->
            <div class="section-card prompt-section">
                <h4><i class="fas fa-comment-dots"></i> 提示词</h4>
                <div class="prompt-content">
                    <textarea class="form-control" id="videoPrompt" rows="4" readonly></textarea>
                </div>
            </div>

            <!-- 底部三列区域：景别、时长、运镜 -->
            <div class="bottom-three-columns">
                <div class="info-card">
                    <h4><i class="fas fa-camera"></i> 景别</h4>
                    <div class="info-value" id="shotType">-</div>
                </div>
                <div class="info-card">
                    <h4><i class="fas fa-clock"></i> 时长</h4>
                    <div class="info-value" id="duration">-</div>
                </div>
                <div class="info-card">
                    <h4><i class="fas fa-video"></i> 运镜</h4>
                    <div class="info-value" id="cameraMovement">-</div>
                </div>
            </div>
        </div>



        <!-- 右区：详细参数 -->
        <div class="properties-panel">
            <div class="tabs">
                <button class="tab-btn active" data-tab="basic">基本信息</button>
                <button class="tab-btn" data-tab="content">内容描述</button>
                <button class="tab-btn" data-tab="characters">角色信息</button>
                <button class="tab-btn" data-tab="technical">技术参数</button>
            </div>

            <div class="tab-content">
                <!-- 基本信息 -->
                <div class="tab-pane active" id="basic">
                    <div class="form-group">
                        <label>排序号</label>
                        <input type="text" class="form-control" id="sortOrder" readonly>
                    </div>

                    <div class="form-group">
                        <label>场次号</label>
                        <input type="text" class="form-control" id="sceneId" readonly>
                    </div>

                    <div class="form-group">
                        <label>镜号</label>
                        <input type="text" class="form-control" id="shotId" readonly>
                    </div>

                    <div class="form-group">
                        <label>地点</label>
                        <input type="text" class="form-control" id="location" readonly>
                    </div>

                    <div class="form-group">
                        <label>时间</label>
                        <input type="text" class="form-control" id="time" readonly>
                    </div>

                    <div class="form-group">
                        <label>天气</label>
                        <input type="text" class="form-control" id="weather" readonly>
                    </div>

                    <div class="form-group">
                        <label>景别</label>
                        <input type="text" class="form-control" id="shotTypeInput" readonly>
                    </div>

                    <div class="form-group">
                        <label>时长(秒)</label>
                        <input type="text" class="form-control" id="durationInput" readonly>
                    </div>
                </div>

                <!-- 内容描述 -->
                <div class="tab-pane" id="content">
                    <div class="form-group">
                        <label>内容</label>
                        <textarea class="form-control" rows="3" id="content" readonly></textarea>
                    </div>

                    <div class="form-group">
                        <label>备注</label>
                        <textarea class="form-control" rows="2" id="remark" readonly></textarea>
                    </div>

                    <div class="form-group">
                        <label>场景预期</label>
                        <textarea class="form-control" rows="2" id="sceneExpectation" readonly></textarea>
                    </div>

                    <div class="form-group">
                        <label>声音设计</label>
                        <textarea class="form-control" rows="2" id="sound" readonly></textarea>
                    </div>
                </div>

                <!-- 角色信息 -->
                <div class="tab-pane" id="characters">
                    <div class="form-group">
                        <label>角色清单</label>
                        <input type="text" class="form-control" id="characters" readonly>
                    </div>

                    <div class="form-group">
                        <label>各角色推荐服装</label>
                        <textarea class="form-control" rows="3" id="characterCostumes" readonly></textarea>
                    </div>

                    <div class="form-group">
                        <label>各角色推荐妆造</label>
                        <textarea class="form-control" rows="3" id="characterMakeup" readonly></textarea>
                    </div>

                    <div class="form-group">
                        <label>角色动作</label>
                        <textarea class="form-control" rows="2" id="characterActions" readonly></textarea>
                    </div>
                </div>

                <!-- 技术参数 -->
                <div class="tab-pane" id="technical">
                    <div class="form-group">
                        <label>摄像机角度</label>
                        <input type="text" class="form-control" id="cameraAngle" readonly>
                    </div>

                    <div class="form-group">
                        <label>构图与焦点</label>
                        <input type="text" class="form-control" id="compositionFocus" readonly>
                    </div>

                    <div class="form-group">
                        <label>运镜</label>
                        <input type="text" class="form-control" id="cameraMovementInput" readonly>
                    </div>

                    <div class="form-group">
                        <label>摄像机设备</label>
                        <input type="text" class="form-control" id="cameraEquipment" readonly>
                    </div>

                    <div class="form-group">
                        <label>镜头焦段</label>
                        <input type="text" class="form-control" id="lensFocalLength" readonly>
                    </div>

                    <div class="form-group">
                        <label>光线与色调</label>
                        <textarea class="form-control" rows="2" id="lightTone" readonly></textarea>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
    <script src="js/storyboard-detail.js"></script>
</body>

</html>
