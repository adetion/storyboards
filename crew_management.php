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

// 获取当前用户信息
$userId = $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? '用户';

// 引入配置文件
require_once 'config.php';
$db = Database::getInstance();
$pdo = $db->getPdo();
?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>剧组组织架构管理 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
    <style>
        /* 剧组管理页面样式 */
        .crew-management-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        .content-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }
        
        .content-header h2 {
            margin: 0;
            color: #333;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            background: #4CAF50;
            color: white;
            cursor: pointer;
            font-size: 14px;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #45a049;
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .btn-danger {
            background: #dc3545;
        }
        
        .btn-danger:hover {
            background: #c82333;
        }
        
        /* 标签页样式 */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 1px solid #eee;
        }
        
        .tab {
            padding: 10px 20px;
            background: #f5f5f5;
            border: none;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #fff;
            border-bottom: 2px solid #4CAF50;
            font-weight: bold;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* 表格样式 */
        .record-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background: #fff;
        }
        
        .record-table th,
        .record-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .record-table th {
            background: #f5f5f5;
            font-weight: bold;
            color: #333;
        }
        
        .record-table tr:hover {
            background: #f9f9f9;
        }
        
        /* 搜索和筛选 */
        .search-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .search-filter input,
        .search-filter select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* 模态框样式 */
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: #fff;
            border-radius: 8px;
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            background: #f5f5f5;
            border-bottom: 1px solid #ddd;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
        }
        
        .close {
            font-size: 24px;
            cursor: pointer;
            color: #666;
            line-height: 1;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-body {
            padding: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #333;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            padding: 15px 20px;
            background: #f5f5f5;
            border-top: 1px solid #ddd;
        }
        
        /* 成员分组样式 */
        .group-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #4CAF50;
            color: white;
            border-radius: 4px;
            font-size: 12px;
        }
        
        /* 状态样式 */
        .status-active {
            color: #4CAF50;
        }
        
        .status-inactive {
            color: #dc3545;
        }
        
        /* 资源类型样式 */
        .resource-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 10px;
            margin: 10px 0;
        }
        
        .resource-type-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        /* 响应式设计 */
        @media (max-width: 768px) {
            .search-filter {
                flex-direction: column;
            }
            
            .tabs {
                overflow-x: auto;
            }
            
            .record-table {
                overflow-x: auto;
                display: block;
            }
        }
    </style>
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>
    
    <main>
        <div class="crew-management-container">
            <div class="content-header">
                <h2><i class="fas fa-users-cog"></i> 剧组组织架构管理</h2>
                <button class="btn" onclick="showCreateCrewModal()"><i class="fas fa-plus"></i> 创建新剧组</button>
            </div>
            
            <!-- 标签页 -->
            <div class="tabs">
                <button class="tab active" onclick="openTab(event, 'crew-list')">剧组列表</button>
                <button class="tab" onclick="openTab(event, 'member-management')">成员管理</button>
                <button class="tab" onclick="openTab(event, 'permission-management')">权限管理</button>
                <button class="tab" onclick="openTab(event, 'shared-resources')">共享资源</button>
            </div>
            
            <!-- 剧组列表 -->
            <div id="crew-list" class="tab-content active">
                <div class="crew-list-content">
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>剧组名称</th>
                                <th>描述</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="crew-list-body">
                            <!-- 动态加载剧组列表 -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 成员管理 -->
            <div id="member-management" class="tab-content">
                <div class="member-management-content">
                    <div class="search-filter">
                        <select id="crew-select" onchange="loadMembers()">
                            <!-- 动态加载剧组选项 -->
                        </select>
                        <input type="text" id="member-search" placeholder="搜索成员姓名或职务" oninput="loadMembers()">
                        <button class="btn" onclick="showAddMemberModal()"><i class="fas fa-user-plus"></i> 添加成员</button>
                    </div>
                    
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>姓名</th>
                                <th>性别</th>
                                <th>职务</th>
                                <th>分组</th>
                                <th>账号</th>
                                <th>授权状态</th>
                                <th>状态</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="member-list-body">
                            <!-- 动态加载成员列表 -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 权限管理 -->
            <div id="permission-management" class="tab-content">
                <div class="permission-management-content">
                    <div class="search-filter">
                        <select id="permission-crew-select" onchange="loadPermissions()">
                            <!-- 动态加载剧组选项 -->
                        </select>
                    </div>
                    
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>成员姓名</th>
                                <th>资源类型</th>
                                <th>编辑权限</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="permission-list-body">
                            <!-- 动态加载权限列表 -->
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- 共享资源 -->
            <div id="shared-resources" class="tab-content">
                <div class="shared-resources-content">
                    <div class="search-filter">
                        <select id="resource-crew-select" onchange="loadResources()">
                            <!-- 动态加载剧组选项 -->
                        </select>
                        <select id="resource-type-select" onchange="loadResources()">
                            <option value="">全部分类</option>
                            <option value="novel_to_script">小说转剧本</option>
                            <option value="script_to_storyboard">剧本转分镜</option>
                            <option value="storyboard">分镜管理</option>
                            <option value="shooting_plan">拍摄计划</option>
                            <option value="shooting_notice">拍摄通告</option>
                            <option value="text_to_image">文生图</option>
                            <option value="image_to_video">图生视频</option>
                        </select>
                    </div>
                    
                    <table class="record-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>资源标题</th>
                                <th>资源类型</th>
                                <th>剧组</th>
                                <th>创建时间</th>
                                <th>操作</th>
                            </tr>
                        </thead>
                        <tbody id="resource-list-body">
                            <!-- 动态加载资源列表 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
    
    <!-- 创建剧组模态框 -->
    <div id="create-crew-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>创建新剧组</h3>
                <span class="close" onclick="closeModal('create-crew-modal')">&times;</span>
            </div>
            <form id="create-crew-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="crew-name">剧组名称</label>
                        <input type="text" id="crew-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="crew-description">剧组描述</label>
                        <textarea id="crew-description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-crew-modal')">取消</button>
                    <button type="submit" class="btn">创建</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 添加成员模态框 -->
    <div id="add-member-modal" class="modal" style="display: none;">
        <div class="modal-content" style="max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>添加剧组成员</h3>
                <span class="close" onclick="closeModal('add-member-modal')">&times;</span>
            </div>
            <form id="add-member-form">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="member-crew-id">所属剧组</label>
                        <select id="member-crew-id" name="crew_id" required>
                            <!-- 动态加载剧组选项 -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-name">姓名</label>
                        <input type="text" id="member-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="member-gender">性别</label>
                        <select id="member-gender" name="gender">
                            <option value="男">男</option>
                            <option value="女">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-position">职务</label>
                        <select id="member-position" name="position" required>
                            <option value="">请选择职务</option>
                            <option value="导演">导演</option>
                            <option value="副导演">副导演</option>
                            <option value="执行导演">执行导演</option>
                            <option value="摄影指导">摄影指导</option>
                            <option value="摄影师">摄影师</option>
                            <option value="副摄影师">副摄影师</option>
                            <option value="制片主任">制片主任</option>
                            <option value="制片人">制片人</option>
                            <option value="现场制片">现场制片</option>
                            <option value="生活制片">生活制片</option>
                            <option value="编剧">编剧</option>
                            <option value="演员">演员</option>
                            <option value="主演">主演</option>
                            <option value="配角">配角</option>
                            <option value="群演">群演</option>
                            <option value="武行">武行</option>
                            <option value="特技">特技</option>
                            <option value="特邀">特邀</option>
                            <option value="客串">客串</option>
                            <option value="外联制片">外联制片</option>
                            <option value="招募专员">招募专员</option>
                            <option value="培训专员">培训专员</option>
                            <option value="培训师">培训师</option>
                            <option value="财务">财务</option>
                            <option value="场务">场务</option>
                            <option value="餐饮负责人">餐饮负责人</option>
                            <option value="车辆负责人">车辆负责人</option>
                            <option value="安保负责人">安保负责人</option>
                            <option value="服装师">服装师</option>
                            <option value="化妆师">化妆师</option>
                            <option value="道具师">道具师</option>
                            <option value="灯光师">灯光师</option>
                            <option value="烟火师">烟火师</option>
                            <option value="特效师">特效师</option>
                            <option value="剪辑师">剪辑师</option>
                            <option value="外宣负责人">外宣负责人</option>
                            <option value="统筹">统筹</option>
                            <option value="统筹专员">统筹专员</option>
                            <option value="美术指导">美术指导</option>
                            <option value="录音师">录音师</option>
                            <option value="场记">场记</option>
                            <option value="跟焦员">跟焦员</option>
                            <option value="调色师">调色师</option>
                            <option value="混音师">混音师</option>
                            <option value="医师">医师</option>
                            <option value="医生">医生</option>
                            <option value="护士">护士</option>
                            <option value="交通组长">交通组长</option>
                            <option value="司机">司机</option>
                            <option value="维修师">维修师</option>
                            <option value="歌手">歌手</option>
                            <option value="配乐师">配乐师</option>
                            <option value="剪辑员">剪辑员</option>
                            <option value="配音师">配音师</option>
                            <option value="音效师">音效师</option>
                            <option value="记者">记者</option>
                            <option value="舞蹈老师">舞蹈老师</option>
                            <option value="厨师">厨师</option>
                            <option value="帮厨">帮厨</option>
                            <option value="特勤组长">特勤组长</option>
                            <option value="特勤人员">特勤人员</option>
                            <option value="安保队长">安保队长</option>
                            <option value="安保员">安保员</option>
                            <option value="置景专员">置景专员</option>
                            <option value="选景专员">选景专员</option>
                            <option value="媒体人">媒体人</option>
                            <option value="外宣组长">外宣组长</option>
                            <option value="外宣代表">外宣代表</option>
                            <option value="外宣专员">外宣专员</option>
                            <option value="特情专员">特情专员</option>
                            <option value="舆情专员">舆情专员</option>
                            <option value="公关组长">公关组长</option>
                            <option value="公关专员">公关专员</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-group">分组</label>
                        <select id="member-group" name="group" required>
                            <option value="">请选择分组</option>
                            <option value="导演组">导演组</option>
                            <option value="摄影组">摄影组</option>
                            <option value="制片组">制片组</option>
                            <option value="编剧组">编剧组</option>
                            <option value="统筹组">统筹组</option>
                            <option value="美术组">美术组</option>
                            <option value="化妆组">化妆组</option>
                            <option value="服装组">服装组</option>
                            <option value="道具组">道具组</option>
                            <option value="灯光组">灯光组</option>
                            <option value="录音组">录音组</option>
                            <option value="场务组">场务组</option>
                            <option value="交通组">交通组</option>
                            <option value="医护组">医护组</option>
                            <option value="演员组">演员组</option>
                            <option value="培训组">培训组</option>
                            <option value="音乐组">音乐组</option>
                            <option value="外宣组">外宣组</option>
                            <option value="内勤组">内勤组</option>
                            <option value="餐饮组">餐饮组</option>
                            <option value="烟火组">烟火组</option>
                            <option value="舞美组">舞美组</option>
                            <option value="配音组">配音组</option>
                            <option value="特效组">特效组</option>
                            <option value="剪辑组">剪辑组</option>
                            <option value="记者组">记者组</option>
                            <option value="媒体组">媒体组</option>
                            <option value="特勤组">特勤组</option>
                            <option value="安保组">安保组</option>
                            <option value="特情组">特情组</option>
                            <option value="舆情组">舆情组</option>
                            <option value="公关组">公关组</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-responsibilities">职责</label>
                        <input type="text" id="member-responsibilities" name="responsibilities" placeholder="简要职责描述">
                    </div>
                    <div class="form-group">
                        <label for="member-phone">联系电话</label>
                        <input type="tel" id="member-phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="member-email">联系邮件</label>
                        <input type="email" id="member-email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="member-wechat">微信号</label>
                        <input type="text" id="member-wechat" name="wechat">
                    </div>
                    <div class="form-group">
                        <label for="member-account">登录账号</label>
                        <input type="text" id="member-account" name="account" placeholder="留空默认使用手机号">
                    </div>
                    <div class="form-group">
                        <label for="member-password">登录密码</label>
                        <input type="password" id="member-password" name="password" placeholder="留空默认123456">
                    </div>
                    <div class="form-group">
                        <label for="member-is-admin">是否管理员</label>
                        <select id="member-is-admin" name="is_admin">
                            <option value="0">否</option>
                            <option value="1">是</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-can-modify-password">允许管理员修改密码</label>
                        <select id="member-can-modify-password" name="can_modify_password">
                            <option value="1">允许</option>
                            <option value="0">禁止</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="member-is-authorized">授权可登录网站</label>
                        <select id="member-is-authorized" name="is_authorized">
                            <option value="0">未授权</option>
                            <option value="1">已授权</option>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">勾选后，该成员将获得网站登录权限，授权后无法撤销</small>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-member-modal')">取消</button>
                    <button type="submit" class="btn">添加</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 设置权限模态框 -->
    <div id="set-permission-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>设置成员权限</h3>
                <span class="close" onclick="closeModal('set-permission-modal')">&times;</span>
            </div>
            <form id="set-permission-form">
                <input type="hidden" id="permission-member-id" name="member_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="permission-crew">所属剧组</label>
                        <select id="permission-crew" name="crew_id" required>
                            <!-- 动态加载剧组选项 -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label>资源编辑权限</label>
                        <div class="resource-type-grid">
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="novel_to_script">
                                <label>小说转剧本</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="script_to_storyboard">
                                <label>剧本转分镜</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="storyboard">
                                <label>分镜管理</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="shooting_plan">
                                <label>拍摄计划</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="shooting_notice">
                                <label>拍摄通告</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="text_to_image">
                                <label>文生图</label>
                            </div>
                            <div class="resource-type-item">
                                <input type="checkbox" name="resource_types[]" value="image_to_video">
                                <label>图生视频</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('set-permission-modal')">取消</button>
                    <button type="submit" class="btn">保存</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- 编辑剧组模态框 -->
    <div id="edit-crew-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>编辑剧组</h3>
                <span class="close" onclick="closeModal('edit-crew-modal')">&times;</span>
            </div>
            <form id="edit-crew-form">
                <input type="hidden" id="edit-crew-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-crew-name">剧组名称</label>
                        <input type="text" id="edit-crew-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-crew-description">描述</label>
                        <textarea id="edit-crew-description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-crew-modal')">取消</button>
                    <button type="submit" class="btn">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 编辑成员模态框 -->
    <div id="edit-member-modal" class="modal" style="display: none;">
        <div class="modal-content" style="max-height: 80vh; overflow-y: auto;">
            <div class="modal-header">
                <h3>编辑剧组成员</h3>
                <span class="close" onclick="closeModal('edit-member-modal')">&times;</span>
            </div>
            <form id="edit-member-form">
                <input type="hidden" id="edit-member-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="edit-member-crew-id">所属剧组</label>
                        <select id="edit-member-crew-id" name="crew_id" required>
                            <!-- 动态加载剧组选项 -->
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-name">姓名</label>
                        <input type="text" id="edit-member-name" name="name" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-gender">性别</label>
                        <select id="edit-member-gender" name="gender">
                            <option value="男">男</option>
                            <option value="女">女</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-position">职务</label>
                        <select id="edit-member-position" name="position" required>
                            <option value="">请选择职务</option>
                            <option value="导演">导演</option>
                            <option value="副导演">副导演</option>
                            <option value="执行导演">执行导演</option>
                            <option value="摄影指导">摄影指导</option>
                            <option value="摄影师">摄影师</option>
                            <option value="副摄影师">副摄影师</option>
                            <option value="制片主任">制片主任</option>
                            <option value="制片人">制片人</option>
                            <option value="现场制片">现场制片</option>
                            <option value="生活制片">生活制片</option>
                            <option value="编剧">编剧</option>
                            <option value="演员">演员</option>
                            <option value="主演">主演</option>
                            <option value="配角">配角</option>
                            <option value="群演">群演</option>
                            <option value="武行">武行</option>
                            <option value="特技">特技</option>
                            <option value="特邀">特邀</option>
                            <option value="客串">客串</option>
                            <option value="外联制片">外联制片</option>
                            <option value="招募专员">招募专员</option>
                            <option value="培训专员">培训专员</option>
                            <option value="培训师">培训师</option>
                            <option value="财务">财务</option>
                            <option value="场务">场务</option>
                            <option value="餐饮负责人">餐饮负责人</option>
                            <option value="车辆负责人">车辆负责人</option>
                            <option value="安保负责人">安保负责人</option>
                            <option value="服装师">服装师</option>
                            <option value="化妆师">化妆师</option>
                            <option value="道具师">道具师</option>
                            <option value="灯光师">灯光师</option>
                            <option value="烟火师">烟火师</option>
                            <option value="特效师">特效师</option>
                            <option value="剪辑师">剪辑师</option>
                            <option value="外宣负责人">外宣负责人</option>
                            <option value="统筹">统筹</option>
                            <option value="统筹专员">统筹专员</option>
                            <option value="美术指导">美术指导</option>
                            <option value="录音师">录音师</option>
                            <option value="场记">场记</option>
                            <option value="跟焦员">跟焦员</option>
                            <option value="调色师">调色师</option>
                            <option value="混音师">混音师</option>
                            <option value="医师">医师</option>
                            <option value="医生">医生</option>
                            <option value="护士">护士</option>
                            <option value="交通组长">交通组长</option>
                            <option value="司机">司机</option>
                            <option value="维修师">维修师</option>
                            <option value="歌手">歌手</option>
                            <option value="配乐师">配乐师</option>
                            <option value="剪辑员">剪辑员</option>
                            <option value="配音师">配音师</option>
                            <option value="音效师">音效师</option>
                            <option value="记者">记者</option>
                            <option value="舞蹈老师">舞蹈老师</option>
                            <option value="厨师">厨师</option>
                            <option value="帮厨">帮厨</option>
                            <option value="特勤组长">特勤组长</option>
                            <option value="特勤人员">特勤人员</option>
                            <option value="安保队长">安保队长</option>
                            <option value="安保员">安保员</option>
                            <option value="置景专员">置景专员</option>
                            <option value="选景专员">选景专员</option>
                            <option value="媒体人">媒体人</option>
                            <option value="外宣组长">外宣组长</option>
                            <option value="外宣代表">外宣代表</option>
                            <option value="外宣专员">外宣专员</option>
                            <option value="特情专员">特情专员</option>
                            <option value="舆情专员">舆情专员</option>
                            <option value="公关组长">公关组长</option>
                            <option value="公关专员">公关专员</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-group">分组</label>
                        <select id="edit-member-group" name="group" required>
                            <option value="">请选择分组</option>
                            <option value="导演组">导演组</option>
                            <option value="摄影组">摄影组</option>
                            <option value="制片组">制片组</option>
                            <option value="编剧组">编剧组</option>
                            <option value="统筹组">统筹组</option>
                            <option value="美术组">美术组</option>
                            <option value="化妆组">化妆组</option>
                            <option value="服装组">服装组</option>
                            <option value="道具组">道具组</option>
                            <option value="灯光组">灯光组</option>
                            <option value="录音组">录音组</option>
                            <option value="场务组">场务组</option>
                            <option value="交通组">交通组</option>
                            <option value="医护组">医护组</option>
                            <option value="演员组">演员组</option>
                            <option value="培训组">培训组</option>
                            <option value="音乐组">音乐组</option>
                            <option value="外宣组">外宣组</option>
                            <option value="内勤组">内勤组</option>
                            <option value="餐饮组">餐饮组</option>
                            <option value="烟火组">烟火组</option>
                            <option value="舞美组">舞美组</option>
                            <option value="配音组">配音组</option>
                            <option value="特效组">特效组</option>
                            <option value="剪辑组">剪辑组</option>
                            <option value="记者组">记者组</option>
                            <option value="媒体组">媒体组</option>
                            <option value="特勤组">特勤组</option>
                            <option value="安保组">安保组</option>
                            <option value="特情组">特情组</option>
                            <option value="舆情组">舆情组</option>
                            <option value="公关组">公关组</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-responsibilities">职责</label>
                        <input type="text" id="edit-member-responsibilities" name="responsibilities" placeholder="简要职责描述">
                    </div>
                    <div class="form-group">
                        <label for="edit-member-phone">联系电话</label>
                        <input type="tel" id="edit-member-phone" name="phone">
                    </div>
                    <div class="form-group">
                        <label for="edit-member-email">联系邮件</label>
                        <input type="email" id="edit-member-email" name="email">
                    </div>
                    <div class="form-group">
                        <label for="edit-member-wechat">微信号</label>
                        <input type="text" id="edit-member-wechat" name="wechat">
                    </div>
                    <div class="form-group">
                        <label for="edit-member-account">登录账号</label>
                        <input type="text" id="edit-member-account" name="account" placeholder="留空默认使用手机号">
                    </div>
                    <div class="form-group">
                        <label for="edit-member-is-admin">是否管理员</label>
                        <select id="edit-member-is-admin" name="is_admin">
                            <option value="0">否</option>
                            <option value="1">是</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-can-modify-password">允许管理员修改密码</label>
                        <select id="edit-member-can-modify-password" name="can_modify_password">
                            <option value="1">允许</option>
                            <option value="0">禁止</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-is-authorized">授权可登录网站</label>
                        <select id="edit-member-is-authorized" name="is_authorized">
                            <option value="0">未授权</option>
                            <option value="1">已授权</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="edit-member-enabled">状态</label>
                        <select id="edit-member-enabled" name="enabled">
                            <option value="1">启用</option>
                            <option value="0">禁用</option>
                        </select>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-member-modal')">取消</button>
                    <button type="submit" class="btn">保存</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 重置密码模态框 -->
    <div id="reset-password-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3>重置密码</h3>
                <span class="close" onclick="closeModal('reset-password-modal')">&times;</span>
            </div>
            <form id="reset-password-form">
                <input type="hidden" id="reset-member-id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="new-password">新密码</label>
                        <input type="password" id="new-password" required>
                    </div>
                    <div class="form-group">
                        <label for="confirm-password">确认密码</label>
                        <input type="password" id="confirm-password" required>
                    </div>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reset-password-modal')">取消</button>
                    <button type="submit" class="btn">重置</button>
                </div>
            </form>
        </div>
    </div>
</body>

<script>
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
            console.error('获取剧组数据失败:', error);
            return { success: false, data: [] };
        }
    }

    // 清除剧组缓存的函数（在数据更新时调用）
    function clearCrewsCache() {
        crewsCache = null;
        crewCache.clear();
    }

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        loadCrews();
        loadMemberCrewOptions();
        loadEditMemberCrewOptions();
        loadPermissionCrewOptions();
        loadResourceCrewOptions();
        
        // 表单提交事件
        document.getElementById('create-crew-form').addEventListener('submit', handleCreateCrew);
        document.getElementById('edit-crew-form').addEventListener('submit', handleEditCrew);
        document.getElementById('add-member-form').addEventListener('submit', handleAddMember);
        document.getElementById('edit-member-form').addEventListener('submit', handleEditMember);
        document.getElementById('set-permission-form').addEventListener('submit', handleSetPermission);
        document.getElementById('reset-password-form').addEventListener('submit', handleResetPassword);
    });
    
    // 打开标签页
    function openTab(event, tabName) {
        // 隐藏所有标签页内容
        const tabContents = document.querySelectorAll('.tab-content');
        tabContents.forEach(content => {
            content.classList.remove('active');
        });
        
        // 移除所有标签的active类
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.classList.remove('active');
        });
        
        // 显示当前标签页内容
        document.getElementById(tabName).classList.add('active');
        event.currentTarget.classList.add('active');
        
        // 加载对应数据
        if (tabName === 'crew-list') {
            loadCrews();
        } else if (tabName === 'member-management') {
            loadMembers();
        } else if (tabName === 'permission-management') {
            loadPermissions();
        } else if (tabName === 'shared-resources') {
            loadResources();
        }
    }
    
    // 显示模态框
    function showCreateCrewModal() {
        document.getElementById('create-crew-modal').style.display = 'flex';
    }
    
    function showAddMemberModal() {
        document.getElementById('add-member-modal').style.display = 'flex';
    }
    
    // 关闭模态框
    function closeModal(modalId) {
        document.getElementById(modalId).style.display = 'none';
        // 重置表单
        const form = document.getElementById(modalId.replace('-modal', '-form'));
        if (form) {
            form.reset();
        }
    }
    
    // 加载剧组列表
    async function loadCrews() {
        try {
            // 使用缓存机制获取剧组数据
            const data = await getCrews();
            if (data.success) {
                const tbody = document.getElementById('crew-list-body');
                tbody.innerHTML = data.data.map(crew => `
                    <tr>
                        <td>${crew.id}</td>
                        <td>${crew.name}</td>
                        <td>${crew.description || ''}</td>
                        <td>${crew.created_at}</td>
                        <td>
                            <button class="btn btn-secondary" onclick="showEditCrew(${crew.id})"><i class="fas fa-edit"></i> 编辑</button>
                            <button class="btn btn-danger" onclick="deleteCrew(${crew.id})"><i class="fas fa-trash"></i> 删除</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('加载剧组列表失败:', error);
        }
    }
    
    // 加载成员列表
    async function loadMembers() {
        const crewId = document.getElementById('crew-select').value;
        const search = document.getElementById('member-search').value;
        try {
            const response = await fetch(`api/crew_api.php?action=get_members&crew_id=${crewId}&search=${encodeURIComponent(search)}`);
            const data = await response.json();
            if (data.success) {
                const tbody = document.getElementById('member-list-body');
                tbody.innerHTML = data.data.map(member => `
                    <tr>
                        <td>${member.id}</td>
                        <td>${member.name}</td>
                        <td>${member.gender}</td>
                        <td>${member.position}</td>
                        <td><span class="group-badge">${member.group}</span></td>
                        <td>${member.account}</td>
                        <td class="${member.is_authorized ? 'status-active' : 'status-inactive'}">
                            ${member.is_authorized ? '<i class="fas fa-check-circle"></i> 已授权' : '<i class="fas fa-times-circle"></i> 未授权'}
                        </td>
                        <td class="${member.enabled ? 'status-active' : 'status-inactive'}">
                            ${member.enabled ? '<i class="fas fa-check-circle"></i> 启用' : '<i class="fas fa-times-circle"></i> 禁用'}
                        </td>
                        <td>
                            <button class="btn" onclick="showEditMember(${member.id})"><i class="fas fa-edit"></i> 编辑</button>
                            ${!member.is_authorized ? `<button class="btn btn-primary" onclick="authorizeMember(${member.id})"><i class="fas fa-user-check"></i> 授权登录</button>` : ''}
                            <button class="btn btn-secondary" onclick="resetMemberPassword(${member.id}, ${member.can_modify_password})"><i class="fas fa-key"></i> 重置密码</button>
                            <button class="btn btn-danger" onclick="deleteMember(${member.id})"><i class="fas fa-trash"></i> 删除</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('加载成员列表失败:', error);
        }
    }
    
    // 加载权限列表
    async function loadPermissions() {
        const crewId = document.getElementById('permission-crew-select').value;
        try {
            const response = await fetch(`api/crew_api.php?action=get_permissions&crew_id=${crewId}`);
            const data = await response.json();
            if (data.success) {
                const tbody = document.getElementById('permission-list-body');
                tbody.innerHTML = data.data.map(permission => `
                    <tr>
                        <td>${permission.id}</td>
                        <td>${permission.member_name || permission.member_id}</td>
                        <td>${permission.resource_type}</td>
                        <td>${permission.can_edit ? '允许' : '禁止'}</td>
                        <td>
                            <button class="btn" onclick="showSetPermission(${permission.member_id})"><i class="fas fa-edit"></i> 编辑</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('加载权限列表失败:', error);
        }
    }
    
    // 加载资源列表
    async function loadResources() {
        const crewId = document.getElementById('resource-crew-select').value;
        const resourceType = document.getElementById('resource-type-select').value;
        try {
            const response = await fetch(`api/crew_api.php?action=get_resources&crew_id=${crewId}&resource_type=${resourceType}`);
            const data = await response.json();
            if (data.success) {
                const tbody = document.getElementById('resource-list-body');
                tbody.innerHTML = data.data.map(resource => `
                    <tr>
                        <td>${resource.id}</td>
                        <td>${resource.title}</td>
                        <td>${resource.resource_type}</td>
                        <td>${resource.crew_id}</td>
                        <td>${resource.created_at}</td>
                        <td>
                            <button class="btn" onclick="viewResource(${resource.id}, '${resource.resource_type}')"><i class="fas fa-eye"></i> 查看</button>
                            <button class="btn btn-danger" onclick="unshareResource(${resource.id})"><i class="fas fa-share-slash"></i> 取消共享</button>
                        </td>
                    </tr>
                `).join('');
            }
        } catch (error) {
            console.error('加载资源列表失败:', error);
        }
    }
    
    // 加载成员剧组选项
    async function loadMemberCrewOptions() {
        try {
            // 使用缓存机制获取剧组数据
            const data = await getCrews();
            if (data.success) {
                // 过滤显示自己创建的和自己从属的剧组
                const userCrews = data.data.filter(crew => crew.is_creator || crew.is_member);
                
                // 加载到添加成员模态框
                const select1 = document.getElementById('member-crew-id');
                select1.innerHTML = userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
                // 加载到成员管理标签页
                const select2 = document.getElementById('crew-select');
                select2.innerHTML = '<option value="">选择剧组</option>' + userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
                
                // 默认选中最后一个添加的剧组
                if (userCrews.length > 0) {
                    const lastCrewId = userCrews[0].id; // 按created_at DESC排序，第一个就是最后添加的
                    select2.value = lastCrewId;
                    loadMembers();
                }
            }
        } catch (error) {
            console.error('加载剧组选项失败:', error);
        }
    }
    
    // 加载权限剧组选项
    async function loadPermissionCrewOptions() {
        try {
            // 使用缓存机制获取剧组数据
            const data = await getCrews();
            if (data.success) {
                // 过滤只显示自己创建的剧组
                const userCrews = data.data.filter(crew => crew.admin_user_id === window.user_id);
                
                // 加载到设置权限模态框
                const select1 = document.getElementById('permission-crew');
                select1.innerHTML = userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
                // 加载到权限管理标签页
                const select2 = document.getElementById('permission-crew-select');
                select2.innerHTML = '<option value="">选择剧组</option>' + userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
                
                // 默认选中最后一个添加的剧组
                if (userCrews.length > 0) {
                    const lastCrewId = userCrews[0].id; // 按created_at DESC排序，第一个就是最后添加的
                    select2.value = lastCrewId;
                    loadPermissions();
                }
            }
        } catch (error) {
            console.error('加载权限剧组选项失败:', error);
        }
    }
    
    // 加载资源剧组选项
    async function loadResourceCrewOptions() {
        try {
            // 使用缓存机制获取剧组数据
            const data = await getCrews();
            if (data.success) {
                // 过滤只显示自己创建的剧组
                const userCrews = data.data.filter(crew => crew.admin_user_id === window.user_id);
                
                const select = document.getElementById('resource-crew-select');
                select.innerHTML = '<option value="">选择剧组</option>' + userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
                
                // 默认选中最后一个添加的剧组
                if (userCrews.length > 0) {
                    const lastCrewId = userCrews[0].id; // 按created_at DESC排序，第一个就是最后添加的
                    select.value = lastCrewId;
                    loadResources();
                }
            }
        } catch (error) {
            console.error('加载资源剧组选项失败:', error);
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
                alert(data.message);
                closeModal('create-crew-modal');
                loadCrews();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('创建剧组失败:', error);
            alert('创建剧组失败，请重试');
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
                alert(data.message);
                closeModal('add-member-modal');
                loadMembers();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('添加成员失败:', error);
            alert('添加成员失败，请重试');
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
        
        try {
            // 保存每个资源类型的权限
            for (const resourceType of resourceTypes) {
                const response = await fetch(`api/crew_api.php?action=save_permission&member_id=${memberId}&crew_id=${crewId}&resource_type=${resourceType}&can_edit=1`);
                const data = await response.json();
                if (!data.success) {
                    throw new Error(data.message);
                }
            }
            alert('权限保存成功');
            closeModal('set-permission-modal');
            loadPermissions();
        } catch (error) {
            console.error('设置权限失败:', error);
            alert('设置权限失败，请重试');
        }
    }
    
    // 处理重置密码
    async function handleResetPassword(event) {
        event.preventDefault();
        const memberId = document.getElementById('reset-member-id').value;
        const newPassword = document.getElementById('new-password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        
        if (newPassword !== confirmPassword) {
            alert('两次输入的密码不一致');
            return;
        }
        
        try {
            const response = await fetch(`api/crew_api.php?action=reset_password&id=${memberId}&password=${encodeURIComponent(newPassword)}`);
            const data = await response.json();
            if (data.success) {
                alert(data.message);
                closeModal('reset-password-modal');
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('重置密码失败:', error);
            alert('重置密码失败，请重试');
        }
    }
    
    // 重置成员密码
    function resetMemberPassword(memberId, canModifyPassword) {
        if (!canModifyPassword) {
            alert('该成员禁止管理员修改密码');
            return;
        }
        document.getElementById('reset-member-id').value = memberId;
        document.getElementById('reset-password-modal').style.display = 'flex';
    }
    
    // 查看资源
    function viewResource(resourceId, resourceType) {
        alert(`查看资源：${resourceType} - ${resourceId}`);
        // 这里可以添加跳转到资源详情页的逻辑
    }
    
    // 取消共享资源
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
                console.error('取消共享资源失败:', error);
                alert('取消共享资源失败，请重试');
            }
        }
    }
    
    // 加载编辑成员剧组选项
    async function loadEditMemberCrewOptions() {
        try {
            // 使用缓存机制获取剧组数据
            const data = await getCrews();
            if (data.success) {
                const select = document.getElementById('edit-member-crew-id');
                // 过滤只显示自己创建的剧组
                const userCrews = data.data.filter(crew => crew.admin_user_id === window.user_id);
                select.innerHTML = userCrews.map(crew => `<option value="${crew.id}">${crew.name}</option>`).join('');
            }
        } catch (error) {
            console.error('加载编辑成员剧组选项失败:', error);
        }
    }
    
    // 显示编辑剧组模态框
    async function showEditCrew(crewId) {
        try {
            const response = await fetch(`api/crew_api.php?action=get_crew&id=${crewId}`);
            const data = await response.json();
            if (data.success) {
                const crew = data.data;
                document.getElementById('edit-crew-id').value = crew.id;
                document.getElementById('edit-crew-name').value = crew.name;
                document.getElementById('edit-crew-description').value = crew.description || '';
                document.getElementById('edit-crew-modal').style.display = 'flex';
            }
        } catch (error) {
            console.error('获取剧组信息失败:', error);
            alert('获取剧组信息失败，请重试');
        }
    }
    
    // 处理编辑剧组
    async function handleEditCrew(event) {
        event.preventDefault();
        const crewId = document.getElementById('edit-crew-id').value;
        const name = document.getElementById('edit-crew-name').value;
        const description = document.getElementById('edit-crew-description').value;
        
        try {
            const response = await fetch(`api/crew_api.php?action=update_crew&id=${crewId}&name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`);
            const data = await response.json();
            if (data.success) {
                alert(data.message);
                closeModal('edit-crew-modal');
                loadCrews();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('编辑剧组失败:', error);
            alert('编辑剧组失败，请重试');
        }
    }
    
    // 删除剧组
    async function deleteCrew(crewId) {
        if (confirm('确定要删除该剧组吗？删除后将无法恢复。')) {
            try {
                const response = await fetch(`api/crew_api.php?action=delete_crew&id=${crewId}`);
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    loadCrews();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('删除剧组失败:', error);
                alert('删除剧组失败，请重试');
            }
        }
    }
    
    // 显示编辑成员模态框
    async function showEditMember(memberId) {
        try {
            const response = await fetch(`api/crew_api.php?action=get_member&id=${memberId}`);
            const data = await response.json();
            if (data.success) {
                const member = data.data;
                document.getElementById('edit-member-id').value = member.id;
                document.getElementById('edit-member-crew-id').value = member.crew_id;
                document.getElementById('edit-member-name').value = member.name;
                document.getElementById('edit-member-gender').value = member.gender;
                document.getElementById('edit-member-position').value = member.position;
                document.getElementById('edit-member-group').value = member.group;
                document.getElementById('edit-member-responsibilities').value = member.responsibilities || '';
                document.getElementById('edit-member-phone').value = member.phone || '';
                document.getElementById('edit-member-email').value = member.email || '';
                document.getElementById('edit-member-wechat').value = member.wechat || '';
                document.getElementById('edit-member-account').value = member.account;
                document.getElementById('edit-member-is-admin').value = member.is_admin;
                document.getElementById('edit-member-can-modify-password').value = member.can_modify_password;
                document.getElementById('edit-member-is-authorized').value = member.is_authorized;
                document.getElementById('edit-member-enabled').value = member.enabled;
                document.getElementById('edit-member-modal').style.display = 'flex';
            }
        } catch (error) {
            console.error('获取成员信息失败:', error);
            alert('获取成员信息失败，请重试');
        }
    }
    
    // 处理编辑成员
    async function handleEditMember(event) {
        event.preventDefault();
        const memberId = document.getElementById('edit-member-id').value;
        const form = event.target;
        const formData = new FormData(form);
        const params = new URLSearchParams(formData).toString();
        
        try {
            const response = await fetch(`api/crew_api.php?action=update_member&id=${memberId}&${params}`);
            const data = await response.json();
            if (data.success) {
                alert(data.message);
                closeModal('edit-member-modal');
                loadMembers();
            } else {
                alert(data.message);
            }
        } catch (error) {
            console.error('编辑成员失败:', error);
            alert('编辑成员失败，请重试');
        }
    }
    
    // 授权成员登录
    async function authorizeMember(memberId) {
        if (confirm('确定要授权该成员登录网站吗？授权后无法撤销。')) {
            try {
                const response = await fetch(`api/crew_api.php?action=authorize_member&id=${memberId}`);
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    loadMembers();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('授权成员失败:', error);
                alert('授权成员失败，请重试');
            }
        }
    }

    // 删除成员
    async function deleteMember(memberId) {
        if (confirm('确定要删除该成员吗？删除后将无法恢复。')) {
            try {
                const response = await fetch(`api/crew_api.php?action=delete_member&id=${memberId}`);
                const data = await response.json();
                if (data.success) {
                    alert(data.message);
                    loadMembers();
                } else {
                    alert(data.message);
                }
            } catch (error) {
                console.error('删除成员失败:', error);
                alert('删除成员失败，请重试');
            }
        }
    }
</script>
</html>
