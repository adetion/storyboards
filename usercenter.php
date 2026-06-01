<?php
// 引入配置文件
require_once 'config.php';

// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 微信授权获取openid逻辑
$openid = null;

// 检查session中是否已有openid
if (isset($_SESSION['wx_openid'])) {
    $openid = $_SESSION['wx_openid'];
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到登录页面
    header('Location: index.html');
    exit(0);
}

// 获取当前用户ID
$user_id = $_SESSION['user_id'];


// 初始化数据库连接
$db = Database::getInstance();

// 获取用户余额
$balance_result = $db->queryOne("SELECT balance FROM user_balances WHERE user_id = ?", [$user_id]);
$user_balance = $balance_result ? $balance_result['balance'] : 0.00;

// 获取用户积分
$points_result = $db->queryOne("SELECT points FROM user_points WHERE user_id = ?", [$user_id]);
$user_points = $points_result ? $points_result['points'] : 0;

// 获取用户等级
$level_result = $db->queryOne("SELECT level FROM users WHERE id = ? LIMIT 1", [$user_id]);
$user_level = $level_result ? $level_result['level'] : 1;


?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户中心 - 智影工场</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
    <link rel="stylesheet" href="css/usercenter.css">
    <link rel="stylesheet" href="css/genre_style.css">
    <script type="text/javascript" src="js/usercenter.js"></script>
    <script>
        // 切换API密钥可见性
        function toggleApiKeyVisibility(apiType) {
            const input = document.getElementById(apiType + '_api_key');
            const icon = input.nextElementSibling.querySelector('i');

            if (input.type === 'password') {
                input.type = 'text';
                icon.className = 'fas fa-eye';
            } else {
                input.type = 'password';
                icon.className = 'fas fa-eye-slash';
            }
        }

        // 保存API设置
        function saveApiKey() {
            // 这里应该添加保存API设置的逻辑
            alert('API设置已保存');
            return false;
        }
    </script>
</head>

<body>
    <?php include 'header.html'; ?>
    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="function-tab active">个人中心</div>
        </div>
        <div class="function-right">
            <div class="btn-group">
            </div>
        </div>
    </div>

    <!-- 导航悬浮按钮（移动端） -->
    <button class="nav-toggle-btn" onclick="toggleNavToggle()">
        <i class="fas fa-bars"></i>
    </button>

    <main class="main-content" id="pageContent" style="display: none;">
        <div class="user-center-container">
            <div class="user-main-content">
                <!-- 左侧导航菜单 -->
                <aside class="user-nav">
                    <!-- 导航折叠按钮（仅在非移动端显示） -->
                    <button class="nav-collapse-btn" onclick="toggleNavCollapse()">
                        <i class="fas fa-chevron-right"></i>
                    </button>

                    <nav class="nav-menu">
                        <!-- 核心功能区 -->
                        <div class="nav-section">
                            <div class="nav-section-title">核心功能</div>
                            <a href="#" class="nav-item active" data-tab="dashboard">
                                <i class="fas fa-chart-line"></i>
                                <span>数据概览</span>
                            </a>

                            <!-- <a href="#" class="nav-item" data-tab="tasks">
                                <i class="fas fa-tasks"></i>
                                <span>任务管理</span>
                            </a> -->
                        </div>

                        <!-- 剧组管理区 -->
                        <div class="nav-section">
                            <div class="nav-section-title">剧组管理</div>
                            <a href="#" class="nav-item" data-tab="organization">
                                <i class="fas fa-users-cog"></i>
                                <span>组织架构</span>
                            </a>
                            <a href="#" class="nav-item" data-tab="crew-resources">
                                <i class="fas fa-box-open"></i>
                                <span>共享资源</span>
                            </a>
                        </div>

                        <!-- 财务管理区 -->
                        <div class="nav-section">
                            <div class="nav-section-title">财务管理</div>
                            <a href="#" class="nav-item" data-tab="balance">
                                <i class="fas fa-wallet"></i>
                                <span>我的余额</span>
                            </a>
                            <a href="#" class="nav-item" data-tab="points">
                                <i class="fas fa-coins"></i>
                                <span>我的积分</span>
                            </a>
                        </div>

                        <!-- 个人设置区 -->
                        <div class="nav-section">
                            <div class="nav-section-title">个人设置</div>
                            <a href="#" class="nav-item" data-tab="profile">
                                <i class="fas fa-user"></i>
                                <span>个人资料</span>
                            </a>
                            <?php if ($user_level != 2): ?>
                            <a href="#" class="nav-item" data-tab="settings">
                                <i class="fas fa-cog"></i>
                                <span>接口设定</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </nav>
                </aside>

                <!-- 右侧内容区域 -->
                <section class="user-content">
                    <!-- 数据概览标签页 -->
                    <div class="tab-content active" id="dashboard">
                        <div class="content-header">
                            <h3><i class="fas fa-chart-line"></i> 数据概览</h3>
                        </div>
                        <div class="dashboard-content">
                            <!-- 快捷操作区 -->
                            <div class="quick-actions-grid">
                                <a href="#" class="quick-action-card" onclick="switchToOrganizationTab()">
                                    <i class="fas fa-users-cog"></i>
                                    <h4>➊ 剧组管理</h4>
                                    <p>管理你的剧组和成员</p>
                                </a>
                                <a href="novel.php" class="quick-action-card">
                                    <i class="fas fa-book"></i>
                                    <h4>➋ 构建剧本</h4>
                                    <p>小说转剧本，快速生成剧本</p>
                                </a>
                                <a href="scripts.php" class="quick-action-card">
                                    <i class="fas fa-file-alt"></i>
                                    <h4>➌ 构建分镜</h4>
                                    <p>剧本转分镜，可视化分镜生成</p>
                                </a>
                                <a href="storyboards.php" class="quick-action-card">
                                    <i class="fas fa-th-large"></i>
                                    <h4>➍ 分镜管理</h4>
                                    <p>管理和编辑你的分镜</p>
                                </a>
                                <a href="#" class="quick-action-card" onclick="document.querySelector('.payment-tab:nth-of-type(2)').click(); scrollToMembershipSection();">
                                    <i class="fas fa-plus-circle"></i>
                                    <h4>➎ 我要充值</h4>
                                    <p>充值积分，解锁更多功能</p>
                                </a>
                                <a href="#" class="quick-action-card" onclick="document.querySelector('.payment-tab:nth-of-type(1)').click(); scrollToMembershipSection();">
                                    <i class="fas fa-crown"></i>
                                    <h4>➏ 升级会员</h4>
                                    <p>享受更多专属特权</p>
                                </a>
                            </div>

                            <!-- 会员权益营销区域 -->
                            <div class="membership-benefits-section">
                                <h3>成为会员，解锁更多特权！</h3>
                                <div class="benefits-grid">
                                    <div class="benefit-item">
                                        <div class="benefit-icon">
                                            <i class="fas fa-trophy"></i>
                                        </div>
                                        <div class="benefit-content">
                                            <h4>专属特权</h4>
                                            <p>优先体验新功能，享受专属服务通道</p>
                                        </div>
                                    </div>
                                    <div class="benefit-item">
                                        <div class="benefit-icon">
                                            <i class="fas fa-percentage"></i>
                                        </div>
                                        <div class="benefit-content">
                                            <h4>充值优惠</h4>
                                            <p>会员充值享额外积分赠送，最高可达20%</p>
                                        </div>
                                    </div>
                                    <div class="benefit-item">
                                        <div class="benefit-icon">
                                            <i class="fas fa-rocket"></i>
                                        </div>
                                        <div class="benefit-content">
                                            <h4>更快速度</h4>
                                            <p>专属服务器，处理速度提升50%以上</p>
                                        </div>
                                    </div>
                                    <div class="benefit-item">
                                        <div class="benefit-icon">
                                            <i class="fas fa-users"></i>
                                        </div>
                                        <div class="benefit-content">
                                            <h4>团队协作</h4>
                                            <p>支持多人协作，共享资源，提高工作效率</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- 卡片切换标签 -->
                                <div class="payment-tabs">
                                    <button class="payment-tab  active" onclick="showPaymentTab('vip')">
                                        <i class="fas fa-crown"></i>
                                        购买会员
                                    </button>
                                    <button class="payment-tab" onclick="showPaymentTab('recharge')">
                                        <i class="fas fa-plus-circle"></i>
                                        我要充值
                                    </button>
                                </div>

                                <!-- 卡片切换内容 -->
                                <div class="payment-cards">
                                    <!-- 会员购买卡片 -->
                                    <div class="payment-card active" id="vip-card">
                                        <div class="vip-options">
                                            <div class="vip-tier">
                                                <h5>月度会员</h5>
                                                <div class="vip-option" onclick="selectVipOption(29, 1, 1, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥29.00/月</span>
                                                        <span class="vip-points">6000积分/月 + 赠500积分/月</span>
                                                    </div>
                                                    <div class="vip-description">普通会员，适合个人创作者</div>
                                                </div>
                                                <div class="vip-option" onclick="selectVipOption(59, 1, 2, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥59.00/月</span>
                                                        <span class="vip-points">20000积分/月 + 赠2000积分/月</span>
                                                    </div>
                                                    <div class="vip-description">高级会员，适合专业创作者</div>
                                                </div>
                                                <div class="vip-option" onclick="selectVipOption(299, 1, 3, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥299.00/月</span>
                                                        <span class="vip-points">150000积分/月 + 赠10000积分/月</span>
                                                    </div>
                                                    <div class="vip-description">贵宾会员，适合工作室团队</div>
                                                </div>
                                            </div>

                                            <div class="vip-tier">
                                                <h5>年度会员（加赠两个月）</h5>
                                                <div class="vip-option" onclick="selectVipOption(299, 2, 1, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥299.00/年</span>
                                                        <span class="vip-points">6000积分/月 + 赠500积分/月</span>
                                                    </div>
                                                    <div class="vip-description">年度普通会员，加赠两个月，更划算</div>
                                                    <div class="vip-badge">推荐</div>
                                                </div>
                                                <div class="vip-option" onclick="selectVipOption(599, 2, 2, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥599.00/年</span>
                                                        <span class="vip-points">20000积分/月 + 赠2000积分/月</span>
                                                    </div>
                                                    <div class="vip-description">年度高级会员，加赠两个月，更划算</div>
                                                </div>
                                                <div class="vip-option" onclick="selectVipOption(2990, 2, 3, this)">
                                                    <div class="vip-option-header">
                                                        <span class="vip-amount">¥2990.00/年</span>
                                                        <span class="vip-points">150000积分/月 + 赠10000积分/月</span>
                                                    </div>
                                                    <div class="vip-description">年度定制会员，加赠两个月，适合大型团队</div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="selected-vip" style="margin-top: 20px; padding: 15px; background: var(--light-color); border-radius: var(--border-radius); border: 2px solid var(--border-color);">
                                            <div class="selected-info">
                                                <span class="selected-label">已选择：</span>
                                                <span id="selected-vip-type">请选择会员类型</span>
                                                <span id="selected-vip-amount">¥0.00</span>
                                            </div>
                                        </div>

                                        <div class="form-actions">
                                            <button type="button" class="btn-primary btn-large" onclick="confirmVipPurchase()">
                                                <i class="fas fa-check-circle"></i>
                                                确认购买
                                            </button>
                                        </div>
                                    </div>

                                    <!-- 充值卡片 -->
                                    <div class="payment-card" id="recharge-card" style="display:none;">
                                        <div class="recharge-options">
                                            <div class="recharge-option" onclick="selectRechargeOption(1, 100, this)">
                                                <div class="recharge-option-header">
                                                    <span class="recharge-amount">¥1.00</span>
                                                    <span class="recharge-points">100积分</span>
                                                </div>
                                                <div class="recharge-description">基础充值</div>
                                            </div>

                                            <div class="recharge-option" onclick="selectRechargeOption(99, 12000, this)">
                                                <div class="recharge-option-header">
                                                    <span class="recharge-amount">¥99.00</span>
                                                    <span class="recharge-points">12000积分</span>
                                                </div>
                                                <div class="recharge-description">额外赠送2000积分</div>
                                                <div class="recharge-badge">推荐</div>
                                            </div>

                                            <div class="recharge-option" onclick="selectRechargeOption(599, 71000, this)">
                                                <div class="recharge-option-header">
                                                    <span class="recharge-amount">¥599.00</span>
                                                    <span class="recharge-points">71000积分</span>
                                                </div>
                                                <div class="recharge-description">额外赠送1000积分</div>
                                            </div>
                                        </div>

                                        <div class="selected-recharge" style="margin-top: 20px; padding: 15px; background: var(--light-color); border-radius: var(--border-radius); border: 2px solid var(--border-color);">
                                            <div class="selected-info">
                                                <span class="selected-label">已选择：</span>
                                                <span id="selected-amount">¥0.00</span>
                                                <span id="selected-points">0积分</span>
                                            </div>
                                        </div>

                                        <div class="form-actions">
                                            <button type="button" class="btn-primary btn-large" onclick="confirmRecharge()">
                                                <i class="fas fa-check-circle"></i>
                                                确认充值
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- 数据统计区 -->
                            <div class="stats-grid">

                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-users"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" id="total-crew-members">0</div>
                                        <div class="stat-label">剧组成员</div>
                                    </div>
                                </div>
                                <div class="stat-card">
                                    <div class="stat-icon">
                                        <i class="fas fa-coins"></i>
                                    </div>
                                    <div class="stat-content">
                                        <div class="stat-value" id="total-points">0</div>
                                        <div class="stat-label">可用积分</div>
                                    </div>
                                </div>
                            </div>

                            <!-- 最近活动区 -->
                            <div class="recent-activities">
                                <h3>最近活动</h3>
                                <div class="activity-list" id="recent-activities-list">
                                    <!-- 动态加载最近活动 -->
                                    <div class="no-data">暂无活动记录</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 个人资料标签页 -->
                    <div class="tab-content" id="profile">
                        <div class="content-header">
                            <h3><i class="fas fa-user"></i> 个人资料</h3>
                        </div>
                        <div class="profile-content">
                            <div class="profile-info">
                                <div class="profile-avatar">
                                    <img src="assets/default-avatar.png" alt="用户头像" id="avatar-img">
                                    <div class="avatar-upload">
                                        <input type="file" id="avatar-upload" accept="image/*">
                                        <label for="avatar-upload">
                                            <i class="fas fa-camera"></i>
                                        </label>
                                    </div>
                                </div>
                                <div class="profile-details">
                                    <div class="detail-item">
                                        <label>用户名</label>
                                        <span id="username-display">用户名</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>昵称</label>
                                        <div class="nickname-edit">
                                            <span id="nickname-display">昵称</span>
                                            <button class="btn-edit" onclick="toggleNicknameEdit()">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <div class="nickname-edit-form" id="nickname-edit-form" style="display: none;">
                                                <input type="text" id="nickname-input" placeholder="请输入昵称">
                                                <div class="edit-buttons">
                                                    <button class="btn-save" onclick="saveNickname()">保存</button>
                                                    <button class="btn-cancel" onclick="cancelNicknameEdit()">取消</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <label>手机号</label>
                                        <span id="phone-display">手机号</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>邮箱</label>
                                        <span id="email-display">邮箱</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>注册时间</label>
                                        <span id="created-at-display">注册时间</span>
                                    </div>
                                    <div class="detail-item">
                                        <label>所属剧组</label>
                                        <div class="crew-info">
                                            <span id="crew-name-display">无</span>
                                            <div id="leave-crew-section" style="display: none; margin-top: 10px;">
                                                <button class="btn btn-secondary" id="leave-crew-btn" onclick="leaveCrew()">
                                                    <i class="fas fa-sign-out-alt"></i> 脱离剧组
                                                </button>
                                                <small style="color: #666; margin-left: 10px;">脱离后将无法查看该剧组资源</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <label>会员等级</label>
                                        <div class="membership-info">
                                            <span id="membership-level-display">普通用户</span>
                                            <span id="membership-expire-display" style="color: #666; font-size: 14px;"></span>
                                        </div>
                                    </div>
                                    <div class="detail-item" style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                                        <label>账号安全</label>
                                        <div class="security-section">
                                            <div class="security-item">
                                                <div class="security-label">
                                                    <h4>重置密码</h4>
                                                    <p>为了您的账号安全，建议定期更换密码</p>
                                                </div>
                                                <button class="btn-reset" onclick="showResetPasswordModal()">
                                                    <i class="fas fa-key"></i>
                                                    重置密码
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 我的余额标签页 -->
                    <div class="tab-content" id="balance">
                        <div class="content-header">
                            <h3>我的余额</h3>
                        </div>
                        <div class="balance-content">
                            <div class="balance-summary">
                                <div class="balance-item">
                                    <span class="balance-label">当前余额</span>
                                    <span class="balance-value" id="balance-display">¥<?php echo number_format($user_balance, 2); ?></span>
                                </div>
                            </div>

                            <div class="balance-tabs">
                                <button class="balance-tab active" data-balance-tab="recharge">充值记录</button>
                                <button class="balance-tab" data-balance-tab="consumption">消费记录</button>
                            </div>

                            <div class="balance-records">
                                <!-- 充值记录 -->
                                <div class="record-list active" id="recharge-records">
                                    <table class="record-table">
                                        <thead>
                                            <tr>
                                                <th>时间</th>
                                                <th>金额</th>
                                                <th>支付方式</th>
                                                <th>状态</th>
                                            </tr>
                                        </thead>
                                        <tbody id="recharge-records-body">
                                            <tr>
                                                <td colspan="4" class="no-data">暂无充值记录</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div id="recharge-pagination"></div>
                                </div>

                                <!-- 消费记录 -->
                                <div class="record-list" id="consumption-records">
                                    <table class="record-table">
                                        <thead>
                                            <tr>
                                                <th>时间</th>
                                                <th>金额</th>
                                                <th>消费类型</th>
                                                <th>描述</th>
                                            </tr>
                                        </thead>
                                        <tbody id="consumption-records-body">
                                            <tr>
                                                <td colspan="4" class="no-data">暂无消费记录</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    <div id="consumption-pagination"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 我的积分标签页 -->
                    <div class="tab-content" id="points">
                        <div class="content-header">
                            <h3>我的积分</h3>
                        </div>
                        <div class="points-content">
                            <div class="points-summary">
                                <div class="points-item">
                                    <span class="points-label">当前积分</span>
                                    <span class="points-value" id="points-display"><?php echo $user_points; ?></span>
                                </div>
                            </div>

                            <div class="points-records">
                                <table class="record-table">
                                    <thead>
                                        <tr>
                                            <th>时间</th>
                                            <th>积分变动</th>
                                            <th>来源</th>
                                            <th>任务ID</th>
                                            <th>操作</th>
                                        </tr>
                                    </thead>
                                    <tbody id="points-records-body">
                                        <tr>
                                            <td colspan="5" class="no-data">暂无积分记录</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div id="points-pagination"></div>
                            </div>
                        </div>
                    </div>

                    <!-- 历史任务标签页 -->
                    <div class="tab-content" id="tasks">
                        <div class="content-header">
                            <h3>历史任务</h3>
                        </div>
                        <div class="tasks-content">
                            <div class="tasks-container">
                                <!-- 左侧任务号列表 -->
                                <div class="tasks-left-panel">
                                    <div class="panel-header">
                                        <h4>任务列表</h4>
                                    </div>
                                    <div class="task-numbers-list" id="task-numbers-list">
                                        <div class="no-data">暂无任务号</div>
                                    </div>
                                </div>

                                <!-- 右侧任务详情 -->
                                <div class="tasks-right-panel">
                                    <div class="panel-header">
                                        <h4 id="selected-task-number-title">所有任务</h4>
                                    </div>
                                    <div class="tasks-records">
                                        <table class="record-table">
                                            <thead>
                                                <tr>
                                                    <th>序号</th>
                                                    <th>类型</th>
                                                    <th>标题</th>
                                                    <th>结果</th>
                                                    <th>状态</th>
                                                    <th>进度</th>
                                                    <th>创建时间</th>
                                                    <th>当前任务</th>
                                                    <th>操作</th>
                                                </tr>
                                            </thead>
                                            <tbody id="tasks-records-body">
                                                <tr>
                                                    <td colspan="8" class="no-data">暂无历史任务</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- 组织架构管理标签页 -->
                    <div class="tab-content" id="organization">
                        <div class="content-header">
                            <h3>组织架构管理</h3>
                        </div>
                        <div class="profile-content">
                            <div class="crew-management-container">
                                <div class="content-header">
                                    <h2><i class="fas fa-users-cog"></i> 剧组组织架构管理</h2>
                                    <button class="btn" onclick="showCreateCrewModal()"><i class="fas fa-plus"></i> 创建新剧组</button>
                                </div>

                                <!-- 标签页 -->
                                <div class="tabs">
                                    <button class="tab active" onclick="openOrganizationTab(event, 'crew-list')">剧组列表</button>
                                    <button class="tab" onclick="openOrganizationTab(event, 'member-management')">成员管理</button>
                                    <!-- <button class="tab" onclick="openOrganizationTab(event, 'permission-management')">权限管理</button>
                                    <button class="tab" onclick="openOrganizationTab(event, 'shared-resources')">共享资源</button> -->
                                </div>

                                <!-- 剧组列表 -->
                                <div id="crew-list" class="tab-content active">
                                    <div class="crew-list-content">
                                        <table class="record-table">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>剧组名称</th>
                                                    <th>片名</th>
                                                    <th>当前任务</th>
                                                    <th>描述</th>
                                                    <th>预计开拍日期</th>
                                                    <th>预计杀青日期</th>
                                                    <th>总拍摄天数</th>
                                                    <th>总场次数</th>
                                                    <th>总分镜数</th>
                                                    <th>实拍天数</th>
                                                    <th>已拍天数</th>
                                                    <th>达成率</th>
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
                        </div>
                    </div>

                    <!-- 组织架构管理模态框 -->
                    <!-- 创建剧组模态框 -->
                    <div id="create-crew-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>创建新剧组</h3>
                                <span class="close" onclick="closeModal('create-crew-modal')">&times;</span>
                            </div>
                            <form id="create-crew-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="crew-name">剧组名称</label>
                                            <input type="text" id="crew-name" name="name" required>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="film-name">片名</label>
                                            <input type="text" id="film-name" name="film_name">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="start-date">预计开拍日期</label>
                                            <input type="date" id="start-date" name="startDate">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="end-date">预计杀青日期</label>
                                            <input type="date" id="end-date" name="endDate">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="estimated-days">总拍摄天数</label>
                                            <input type="number" id="estimated-days" name="estimatedDays" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="total-scenes">总场次数</label>
                                            <input type="number" id="total-scenes" name="totalScenes" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="total-shots">总分镜数</label>
                                            <input type="number" id="total-shots" name="totalShots" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="actual-days">实拍天数</label>
                                            <input type="number" id="actual-days" name="actualDays" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="days-completed">已拍天数</label>
                                            <input type="number" id="days-completed" name="daysCompleted" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="completion-rate">达成率 (%)</label>
                                            <input type="number" id="completion-rate" name="completionRate" min="0" max="100" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="crew-description">剧组描述</label>
                                        <textarea id="crew-description" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>剧本题材（多选）</label>
                                        <div class="genre-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(80px, 1fr)); gap: 8px; margin-top: 10px;">
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="当代">当代</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="现代">现代</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="历史">历史</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="军旅">军旅</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="警匪">警匪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="军事">军事</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="战争">战争</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="世情">世情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="农村">农村</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="反腐">反腐</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="武侠">武侠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="神话">神话</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="科幻">科幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="奇幻">奇幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="玄幻">玄幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="商战">商战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="涉案">涉案</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="情感">情感</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="偶像">偶像</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="情景">情景</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="青少">青少</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="传记">传记</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="都市">都市</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="灾变">灾变</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="灾难">灾难</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="宫廷">宫廷</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="后宫">后宫</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="穿越">穿越</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="移民">移民</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="悬疑">悬疑</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="恐怖">恐怖</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="枪战">枪战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="犯罪">犯罪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="剧情">剧情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="冒险">冒险</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="宗教">宗教</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="家庭">家庭</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="社会">社会</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="权谋">权谋</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动作">动作</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="职场">职场</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="未来">未来</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="末世">末世</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="复仇">复仇</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="言情">言情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="豪门">豪门</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="甜宠">甜宠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动漫">动漫</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="爱情">爱情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="亲情">亲情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="友情">友情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="古风">古风</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="重生">重生</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="逆袭">逆袭</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="传承">传承</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="伦理">伦理</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="校园">校园</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="志怪">志怪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="乡村">乡村</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="刑侦">刑侦</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="古装">古装</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="谍战">谍战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="青春">青春</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="婚姻">婚姻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="推理">推理</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="搞笑">搞笑</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="仙侠">仙侠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="美食">美食</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="惊悚">惊悚</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="史诗">史诗</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="西部">西部</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="喜剧">喜剧</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="悲剧">悲剧</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动画">动画</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="少儿">少儿</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="歌舞">歌舞</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="戏曲">戏曲</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="记录">记录</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('create-crew-modal')">取消</button>
                                    <button type="submit" class="btn btn-secondary">创建</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 编辑剧组模态框 -->
                    <div id="edit-crew-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>编辑剧组</h3>
                                <span class="close" onclick="closeModal('edit-crew-modal')">&times;</span>
                            </div>
                            <form id="edit-crew-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <input type="hidden" id="edit-crew-id" name="id">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-crew-name">剧组名称</label>
                                            <input type="text" id="edit-crew-name" name="name" required>
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit-film-name">片名</label>
                                            <input type="text" id="edit-film-name" name="film_name">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit-current-task">当前任务</label>
                                            <select id="edit-current-task" name="current_task_id">
                                                <option value="">-- 请选择 --</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-start-date">预计开拍日期</label>
                                            <input type="date" id="edit-start-date" name="startDate">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit-end-date">预计杀青日期</label>
                                            <input type="date" id="edit-end-date" name="endDate">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-estimated-days">总拍摄天数</label>
                                            <input type="number" id="edit-estimated-days" name="estimatedDays" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-total-scenes">总场次数</label>
                                            <input type="number" id="edit-total-scenes" name="totalScenes" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit-total-shots">总分镜数</label>
                                            <input type="number" id="edit-total-shots" name="totalShots" min="0" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-actual-days">实拍天数</label>
                                            <input type="number" id="edit-actual-days" name="actualDays" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1; margin-right: 10px;">
                                            <label for="edit-days-completed">已拍天数</label>
                                            <input type="number" id="edit-days-completed" name="daysCompleted" min="0" placeholder="0">
                                        </div>
                                        <div class="form-group" style="flex: 1;">
                                            <label for="edit-completion-rate">达成率 (%)</label>
                                            <input type="number" id="edit-completion-rate" name="completionRate" min="0" max="100" placeholder="0">
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-crew-description">剧组描述</label>
                                        <textarea id="edit-crew-description" name="description" rows="3"></textarea>
                                    </div>
                                    <div class="form-group">
                                        <label>剧本题材（多选）</label>
                                        <div class="genre-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(120px, 1fr)); gap: 10px; margin-top: 10px;">
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="当代">当代</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="现代">现代</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="历史">历史</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="军旅">军旅</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="警匪">警匪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="军事">军事</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="战争">战争</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="世情">世情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="农村">农村</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="反腐">反腐</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="武侠">武侠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="神话">神话</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="科幻">科幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="奇幻">奇幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="玄幻">玄幻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="商战">商战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="涉案">涉案</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="情感">情感</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="偶像">偶像</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="情景">情景</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="青少">青少</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="传记">传记</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="都市">都市</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="灾变">灾变</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="灾难">灾难</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="宫廷">宫廷</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="后宫">后宫</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="穿越">穿越</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="移民">移民</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="悬疑">悬疑</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="恐怖">恐怖</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="枪战">枪战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="犯罪">犯罪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="剧情">剧情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="冒险">冒险</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="宗教">宗教</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="家庭">家庭</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="社会">社会</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="权谋">权谋</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动作">动作</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="职场">职场</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="未来">未来</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="末世">末世</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="复仇">复仇</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="言情">言情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="豪门">豪门</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="甜宠">甜宠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动漫">动漫</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="爱情">爱情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="亲情">亲情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="友情">友情</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="古风">古风</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="重生">重生</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="逆袭">逆袭</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="传承">传承</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="伦理">伦理</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="校园">校园</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="志怪">志怪</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="乡村">乡村</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="刑侦">刑侦</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="古装">古装</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="谍战">谍战</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="青春">青春</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="婚姻">婚姻</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="推理">推理</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="搞笑">搞笑</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="仙侠">仙侠</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="美食">美食</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="惊悚">惊悚</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="史诗">史诗</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="西部">西部</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="喜剧">喜剧</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="悲剧">悲剧</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="动画">动画</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="少儿">少儿</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="歌舞">歌舞</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="戏曲">戏曲</label>
                                            <label style="display: flex; align-items: center; gap: 5px; cursor: pointer;"><input type="checkbox" name="genres[]" value="记录">记录</label>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-crew-modal')">取消</button>
                                    <button type="submit" class="btn btn-secondary">保存修改</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 添加成员模态框 -->
                    <div id="add-member-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>添加剧组成员</h3>
                                <span class="close" onclick="closeModal('add-member-modal')">&times;</span>
                            </div>
                            <form id="add-member-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
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
                                            <option value="美术师">美术师</option>
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
                                    <div class="form-group" style="display: none;">
                                        <label for="member-email">联系邮件</label>
                                        <input type="email" id="member-email" name="email">
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="member-wechat">微信号</label>
                                        <input type="text" id="member-wechat" name="wechat">
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="member-account">登录账号</label>
                                        <input type="text" id="member-account" name="account" placeholder="留空默认使用手机号">
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="member-password">登录密码</label>
                                        <input type="password" autocomplete="off" id="member-password" name="password" placeholder="留空默认123456">
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="member-is-admin">是否管理员</label>
                                        <select id="member-is-admin" name="is_admin">
                                            <option value="0" selected>否</option>
                                            <option value="1">是</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="member-can-modify-password">允许管理员修改密码</label>
                                        <select id="member-can-modify-password" name="can_modify_password">
                                            <option value="1" selected>允许</option>
                                            <option value="0">禁止</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="member-is-authorized">授权可登录网站</label>
                                        <select id="member-is-authorized" name="is_authorized">
                                            <option value="0" selected>未授权</option>
                                            <option value="1">已授权</option>
                                        </select>
                                        <small style="color: #666; display: block; margin-top: 5px;">勾选后，该成员将获得网站登录权限，授权后无法撤销</small>
                                    </div>
                                </div>
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('add-member-modal')">取消</button>
                                    <button type="submit" class="btn">添加</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 设置权限模态框 -->
                    <div id="set-permission-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>设置成员权限</h3>
                                <span class="close" onclick="closeModal('set-permission-modal')">&times;</span>
                            </div>
                            <form id="set-permission-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <input type="hidden" id="permission-member-id" name="member_id">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
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
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('set-permission-modal')">取消</button>
                                    <button type="submit" class="btn">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 编辑成员模态框 -->
                    <div id="edit-member-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>编辑成员</h3>
                                <span class="close" onclick="closeModal('edit-member-modal')">&times;</span>
                            </div>
                            <form id="edit-member-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <input type="hidden" id="edit-member-id" name="id">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
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
                                            <!-- 动态加载职务选项 -->
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="edit-member-group">分组</label>
                                        <select id="edit-member-group" name="group" required>
                                            <!-- 动态加载分组选项 -->
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
                                    <div class="form-group" style="display: none;">
                                        <label for="edit-member-password">登录密码</label>
                                        <input type="password" autocomplete="off" id="edit-member-password" name="password" placeholder="留空则不修改密码">
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="edit-member-is-admin">是否管理员</label>
                                        <select id="edit-member-is-admin" name="is_admin">
                                            <option value="0" selected>否</option>
                                            <option value="1">是</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="edit-member-can-modify-password">允许管理员修改密码</label>
                                        <select id="edit-member-can-modify-password" name="can_modify_password">
                                            <option value="1" selected>允许</option>
                                            <option value="0">禁止</option>
                                        </select>
                                    </div>
                                    <div class="form-group" style="display: none;">
                                        <label for="edit-member-is-authorized">授权可登录网站</label>
                                        <select id="edit-member-is-authorized" name="is_authorized">
                                            <option value="0" selected>未授权</option>
                                            <option value="1">已授权</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('edit-member-modal')">取消</button>
                                    <button type="submit" class="btn">保存</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 重置密码模态框 -->
                    <div id="reset-password-modal" class="modal" style="display: none;">
                        <div class="modal-content" style="max-height: 80vh; height: auto; max-width: 600px; position: relative; display: flex; flex-direction: column;">
                            <div class="modal-header" style="flex-shrink: 0; background-color: white; z-index: 10; border-bottom: 1px solid #ddd;">
                                <h3>重置密码</h3>
                                <span class="close" onclick="closeModal('reset-password-modal')">&times;</span>
                            </div>
                            <form id="reset-password-form" style="flex: 1; display: flex; flex-direction: column; overflow: hidden;">
                                <input type="hidden" id="reset-member-id" name="member_id">
                                <div class="modal-body" style="flex: 1; overflow-y: auto; padding: 15px;">
                                    <div class="form-group">
                                        <label for="new-password">新密码</label>
                                        <input type="password" autocomplete="off" id="new-password" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="confirm-password">确认密码</label>
                                        <input type="password" autocomplete="off" id="confirm-password" required>
                                    </div>
                                </div>
                                <div class="form-actions" style="flex-shrink: 0; padding: 15px; background-color: white; border-top: 1px solid #ddd; display: flex; justify-content: center; gap: 10px;">
                                    <button type="button" class="btn btn-secondary" onclick="closeModal('reset-password-modal')">取消</button>
                                    <button type="submit" class="btn">重置</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- 接口设定标签页 -->
                    <?php if ($user_level != 2): ?>
                    <div class="tab-content" id="settings">
                        <div class="content-header">
                            <h3>接口设定</h3>
                        </div>
                        <div class="settings-content">
   
                            <div class="settings-section" style="background: #ffffff; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05); padding: 30px;">
                                <h4 style="margin: 0 0 30px 0; font-size: 20px; font-weight: 600; color: #333; display: flex; align-items: center;">
                                    <i class="fas fa-cogs" style="margin-right: 10px; color: #4CAF50;"></i>
                                    API管理 &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<span style="font-size:12px;color:var(--primary-color)">【备注】目前暂支持"<a href="https://console.volcengine.com/auth/login?redirectURI=https%3A%2F%2Fwop.cc" target="_blank"><span style='font-size:16px;color:red;'>火山引擎</span></a>"、"DeepSeek"所有模型</span>
                                </h4>
                                <div class="api-key-section">
                                    <form id="api-key-form" onsubmit="return saveApiKey()" style="width: 100%;">
                                        <!-- 文本分析 -->
                                        <div style="margin-bottom: 40px; padding: 25px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                            <h5 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333; display: flex; align-items: center;">
                                                <i class="fas fa-file-alt" style="margin-right: 10px; color: #3b82f6;"></i>
                                                文本分析
                                            </h5>
                                            <div class="form-group" style="margin-bottom: 20px;">
                                                <label for="text_analysis_api_url" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_URL</label>
                                                <input type="text" value="https://ark.cn-beijing.volces.com/api/v3/chat/completions" autocomplete="off" id="text_analysis_api_url" name="text_analysis_api_url" placeholder="请输入文本分析API_URL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 20px; position: relative;">
                                                <label for="text_analysis_api_key" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_KEY</label>
                                                <div style="position: relative;">
                                                    <input type="password" autocomplete="off" id="text_analysis_api_key" name="text_analysis_api_key" placeholder="请输入文本分析API_KEY" style="width: 100%; padding: 12px 16px; padding-right: 45px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                                    <span onclick="toggleApiKeyVisibility('text_analysis')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 16px; transition: color 0.3s ease;">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="text_analysis_api_model" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_MODEL</label>
                                                <input type="text" value="deepseek-v3-1-250821" autocomplete="off" id="text_analysis_api_model" name="text_analysis_api_model" placeholder="请输入文本分析API_MODEL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                        </div>

                                        <!-- 文(图)生图 -->
                                        <div style="margin-bottom: 40px; padding: 25px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                            <h5 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333; display: flex; align-items: center;">
                                                <i class="fas fa-image" style="margin-right: 10px; color: #8b5cf6;"></i>
                                                文（图）生图
                                            </h5>
                                            <div class="form-group" style="margin-bottom: 20px;">
                                                <label for="text_to_image_api_url" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_URL</label>
                                                <input type="text" value="https://ark.cn-beijing.volces.com/api/v3/images/generations" autocomplete="off" id="text_to_image_api_url" name="text_to_image_api_url" placeholder="请输入文(图)生图API_URL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 20px; position: relative;">
                                                <label for="text_to_image_api_key" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_KEY</label>
                                                <div style="position: relative;">
                                                    <input type="password" autocomplete="off" id="text_to_image_api_key" name="text_to_image_api_key" placeholder="请输入文(图)生图API_KEY" style="width: 100%; padding: 12px 16px; padding-right: 45px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                                    <span onclick="toggleApiKeyVisibility('text_to_image')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 16px; transition: color 0.3s ease;">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="text_to_image_api_model" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_MODEL</label>
                                                <input type="text" value="doubao-seedream-4-5-251128" autocomplete="off" id="text_to_image_api_model" name="text_to_image_api_model" placeholder="请输入文(图)生图API_MODEL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                        </div>

                                        <!-- 图生视频 -->
                                        <div style="margin-bottom: 30px; padding: 25px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                            <h5 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333; display: flex; align-items: center;">
                                                <i class="fas fa-video" style="margin-right: 10px; color: #ec4899;"></i>
                                                图（文）生视频
                                            </h5>
                                            <div class="form-group" style="margin-bottom: 20px;">
                                                <label for="image_to_video_api_url" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_URL</label>
                                                <input type="text" value="https://ark.cn-beijing.volces.com/api/v3/contents/generations/tasks" autocomplete="off" id="image_to_video_api_url" name="image_to_video_api_url" placeholder="请输入图生视频API_URL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 20px; position: relative;">
                                                <label for="image_to_video_api_key" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_KEY</label>
                                                <div style="position: relative;">
                                                    <input type="password" autocomplete="off" id="image_to_video_api_key" name="image_to_video_api_key" placeholder="请输入图生视频API_KEY" style="width: 100%; padding: 12px 16px; padding-right: 45px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                                    <span onclick="toggleApiKeyVisibility('image_to_video')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 16px; transition: color 0.3s ease;">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="image_to_video_api_model" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_MODEL</label>
                                                <input type="text" value="doubao-seedance-1-5-pro-251215" autocomplete="off" id="image_to_video_api_model" name="image_to_video_api_model" placeholder="请输入图生视频API_MODEL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                        </div>

                                        <!-- 图片理解 -->
                                        <div style="margin-bottom: 30px; padding: 25px; background: #f9fafb; border-radius: 10px; border: 1px solid #e5e7eb; transition: all 0.3s ease;">
                                            <h5 style="margin: 0 0 20px 0; font-size: 16px; font-weight: 600; color: #333; display: flex; align-items: center;">
                                                <i class="fas fa-brain" style="margin-right: 10px; color: #f59e0b;"></i>
                   
                                                图片识别 | 理解
                                            </h5>
                                            <div class="form-group" style="margin-bottom: 20px;">
                                                <label for="img2text_api_url" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_URL</label>
                                                <input type="text" value="https://ark.cn-beijing.volces.com/api/v3/responses" autocomplete="off" id="img2text_api_url" name="img2text_api_url" placeholder="请输入图片理解API_URL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                            <div class="form-group" style="margin-bottom: 20px; position: relative;">
                                                <label for="img2text_api_key" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_KEY</label>
                                                <div style="position: relative;">
                                                    <input type="password" autocomplete="off" id="img2text_api_key" name="img2text_api_key" placeholder="请输入图片理解API_KEY" style="width: 100%; padding: 12px 16px; padding-right: 45px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                                    <span onclick="toggleApiKeyVisibility('img2text')" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #666; font-size: 16px; transition: color 0.3s ease;">
                                                        <i class="fas fa-eye-slash"></i>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="form-group" style="margin-bottom: 0;">
                                                <label for="img2text_api_model" style="display: block; margin-bottom: 8px; font-weight: 500; color: #555; font-size: 14px;">API_MODEL</label>
                                                <input type="text" value="doubao-seed-1-6-251015" autocomplete="off" id="img2text_api_model" name="img2text_api_model" placeholder="请输入图片理解API_MODEL" style="width: 100%; padding: 12px 16px; border: 2px solid #e5e7eb; border-radius: 8px; font-size: 14px; transition: all 0.3s ease; background: #ffffff;">
                                            </div>
                                        </div>

                                        <div class="form-actions" style="text-align: center; margin-top: 40px;">
                                            <button type="submit" class="btn-save" style="padding: 14px 30px; background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 500; display: inline-flex; align-items: center; transition: all 0.3s ease; box-shadow: 0 4px 12px rgba(76, 175, 80, 0.2);">
                                                <i class="fas fa-save" style="margin-right: 10px;"></i>
                                                保存API设置
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <!-- 响应式样式 -->
                            <style>
                                @media (max-width: 768px) {
                                    .settings-section {
                                        padding: 20px !important;
                                    }

                                    .settings-section h4 {
                                        font-size: 18px !important;
                                    }

                                    .settings-section>div>form>div {
                                        padding: 20px !important;
                                        margin-bottom: 30px !important;
                                    }

                                    .settings-section>div>form>div h5 {
                                        font-size: 15px !important;
                                    }

                                    .form-group input {
                                        padding: 10px 14px !important;
                                        font-size: 13px !important;
                                    }

                                    .form-actions button {
                                        padding: 12px 24px !important;
                                        font-size: 15px !important;
                                    }
                                }

                                @media (max-width: 480px) {
                                    .settings-section {
                                        padding: 15px !important;
                                    }

                                    .settings-section h4 {
                                        font-size: 16px !important;
                                    }

                                    .settings-section>div>form>div {
                                        padding: 15px !important;
                                        margin-bottom: 20px !important;
                                    }

                                    .settings-section>div>form>div h5 {
                                        font-size: 14px !important;
                                    }

                                    .form-group label {
                                        font-size: 13px !important;
                                    }

                                    .form-group input {
                                        padding: 9px 12px !important;
                                        font-size: 12px !important;
                                    }

                                    .form-actions button {
                                        padding: 10px 20px !important;
                                        font-size: 14px !important;
                                        width: 100%;
                                    }
                                }

                                /* 输入框焦点效果 */
                                .form-group input:focus {
                                    outline: none;
                                    border-color: #4CAF50 !important;
                                    box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1);
                                }

                                /* 按钮悬停效果 */
                                .btn-save:hover {
                                    transform: translateY(-2px);
                                    box-shadow: 0 6px 16px rgba(76, 175, 80, 0.3) !important;
                                    background: linear-gradient(135deg, #45a049 0%, #4CAF50 100%) !important;
                                }

                                /* 卡片悬停效果 */
                                .settings-section>div>form>div:hover {
                                    transform: translateY(-2px);
                                    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
                                }

                                /* 图标悬停效果 */
                                .settings-section>div>form>div span:hover {
                                    color: #4CAF50 !important;
                                }
                            </style>
                        </div>
                    </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- 重置密码模态框 -->
            <div class="modal" id="resetPasswordModal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>重置密码</h4>
                        <button class="modal-close" onclick="hideResetPasswordModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <form id="resetPasswordForm">
                            <div class="form-group">
                                <label for="user-new-password">新密码</label>
                                <input type="password" autocomplete="off" id="user-new-password" placeholder="请输入新密码">
                            </div>
                            <div class="form-group">
                                <label for="user-confirm-password">确认密码</label>
                                <input type="password" autocomplete="off" id="user-confirm-password" placeholder="请确认新密码">
                            </div>
                            <div class="form-actions">
                                <button type="button" class="btn-cancel" onclick="hideResetPasswordModal()">取消</button>
                                <button type="submit" class="btn-save">保存</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- 充值模态框 -->
            <div class="modal" id="rechargeModal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>账户充值</h4>
                        <button class="modal-close" onclick="hideRechargeModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="recharge-options">
                            <div class="recharge-option" onclick="selectRechargeOption(1, 100, this)">
                                <div class="recharge-option-header">
                                    <span class="recharge-amount">¥1.00</span>
                                    <span class="recharge-points">100积分</span>
                                </div>
                                <div class="recharge-description">基础充值</div>
                            </div>

                            <div class="recharge-option" onclick="selectRechargeOption(99, 12000, this)">
                                <div class="recharge-option-header">
                                    <span class="recharge-amount">¥99.00</span>
                                    <span class="recharge-points">12000积分</span>
                                </div>
                                <div class="recharge-description">额外赠送2000积分</div>
                                <div class="recharge-badge">推荐</div>
                            </div>

                            <div class="recharge-option" onclick="selectRechargeOption(599, 71000, this)">
                                <div class="recharge-option-header">
                                    <span class="recharge-amount">¥599.00</span>
                                    <span class="recharge-points">71000积分</span>
                                </div>
                                <div class="recharge-description">额外赠送1000积分</div>
                            </div>
                        </div>

                        <div class="selected-recharge" style="margin-top: 20px; padding: 15px; background: var(--light-color); border-radius: var(--border-radius); border: 2px solid var(--border-color);">
                            <div class="selected-info">
                                <span class="selected-label">已选择：</span>
                                <span id="selected-amount">¥0.00</span>
                                <span id="selected-points">0积分</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideRechargeModal()">取消</button>
                            <button type="button" class="btn-save" onclick="confirmRecharge()">确认充值</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 会员购买模态框 -->
            <div class="modal" id="vipModal" style="display: none;">
                <div class="modal-content">
                    <div class="modal-header">
                        <h4>购买会员</h4>
                        <button class="modal-close" onclick="hideVipModal()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <div class="vip-options">
                            <div class="vip-tier">
                                <h5>个人会员</h5>
                                <div class="vip-option" onclick="selectVipOption(9.9, 1, 1, this)">
                                    <div class="vip-option-header">
                                        <span class="vip-amount">¥9.90/月</span>
                                        <span class="vip-points">500积分/月</span>
                                    </div>
                                    <div class="vip-description">适合个人创作者</div>
                                </div>
                                <div class="vip-option" onclick="selectVipOption(99, 1, 2, this)">
                                    <div class="vip-option-header">
                                        <span class="vip-amount">¥99.00/年</span>
                                        <span class="vip-points">500积分/月</span>
                                    </div>
                                    <div class="vip-description">年度会员更划算</div>
                                    <div class="vip-badge">推荐</div>
                                </div>
                            </div>

                            <div class="vip-tier">
                                <h5>团队会员</h5>
                                <div class="vip-option" onclick="selectVipOption(299, 2, 1, this)">
                                    <div class="vip-option-header">
                                        <span class="vip-amount">¥299.00/月</span>
                                        <span class="vip-points">5000积分/月</span>
                                    </div>
                                    <div class="vip-description">适合团队协作</div>
                                </div>
                                <div class="vip-option" onclick="selectVipOption(2999, 2, 2, this)">
                                    <div class="vip-option-header">
                                        <span class="vip-amount">¥2999.00/年</span>
                                        <span class="vip-points">5000积分/月</span>
                                    </div>
                                    <div class="vip-description">年度会员更划算</div>
                                </div>
                            </div>
                        </div>

                        <div class="selected-vip" style="margin-top: 20px; padding: 15px; background: var(--light-color); border-radius: var(--border-radius); border: 2px solid var(--border-color);">
                            <div class="selected-info">
                                <span class="selected-label">已选择：</span>
                                <span id="selected-vip-amount">¥0.00</span>
                                <span id="selected-vip-type">请选择会员类型</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn-cancel" onclick="hideVipModal()">取消</button>
                            <button type="button" class="btn-save" onclick="confirmVipPurchase()">确认购买</button>
                        </div>
                    </div>
                </div>
            </div>
    </main>

    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>

    <script type="text/javascript" src="js/usercenter_main.js"></script>
</body>

</html>
