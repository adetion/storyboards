<?php
require_once __DIR__ . '/config.php';
require_once 'schedule_class.php';
require_once __DIR__ . '/Auth.php';
header('Content-Type: application/json');

// 初始化认证
$auth = new Auth();

// 检查用户是否登录
$user = $auth->checkLogin();
if (!$user['success']) {
    die("错误：用户未登录");
}

$userId = $user['data']['id'];

$file_path = "results/";
$task_id = $_GET["task_id"] ?? '';

// 如果没有提供task_id，尝试从session和数据库获取当前任务号
if (empty($task_id)) {
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
            $task_id = $crewResult['current_task_id'];
        } else {
            // 2. 如果crew表中没有，则查询用户的"storyboard_management"历史任务
            $sql = "SELECT task_id FROM tasks WHERE user_id = :user_id AND task_type IN ('storyboard_management', 'script_to_storyboard') AND task_id IS NOT NULL ORDER BY created_at DESC LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($result && !empty($result['task_id'])) {
                $task_id = $result['task_id'];
            }
        }
    } catch (Exception $e) {
        // 数据库查询失败时，记录错误但继续执行
        error_log("获取当前task_id失败: " . $e->getMessage());
    }
    
    if (empty($task_id)) {
        die("错误：缺少 task_id 参数");
    }
}

// 检查当前用户是否有权限访问该任务
try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    $sql = "SELECT id FROM tasks WHERE task_id = :task_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo json_encode(['error' => '任务不存在或无权限访问']);
        exit;
    }
} catch (Exception $e) {
    echo json_encode(['error' => '验证任务权限失败: ' . $e->getMessage()]);
    exit;
}

$scheduleJson_file = $file_path . $task_id . "_schedule.json";
$supplementalJson_file = $file_path . $task_id . "_tmp.json";

// 强制重新生成$supplementalJson_file文件
// 确保结果目录存在
if (!is_dir($file_path)) {
    mkdir($file_path, 0755, true);
}

// 从数据库中获取数据
$projectData = [];
$crewData = [];
$equipmentData = [];
$transportationData = [];
$mealsData = [];
$safetyData = [];
$notesData = [];

// 从数据库获取项目信息
try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 获取项目信息 - 直接从 crew 表获取（因为没有 projects 表）
    $project = null;
    $projectName = null;
    
    // 从 crew 表获取项目相关信息
    // 尝试不同的查询条件，因为可能表中没有 task_id 列
    $crewInfo = null;
    
    // 1. 尝试使用 current_task_id 列查询
    try {
        $sql = "SELECT * FROM crew WHERE current_task_id = :task_id LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_STR);
        $stmt->execute();
        $crewInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // 2. 如果 current_task_id 列查询失败，尝试使用 admin_user_id
        try {
            $sql = "SELECT * FROM crew WHERE admin_user_id = :user_id LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->execute();
            $crewInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // 3. 如果仍然失败，尝试不使用条件
            try {
                $sql = "SELECT * FROM crew LIMIT 1";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $crewInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                // 如果仍然失败，记录错误但继续执行
                error_log("获取 crew 表信息失败: " . $e->getMessage());
            }
        }
    }
    
    if ($crewInfo) {
        // 从 crew 表获取项目名称
        $projectName = $crewInfo['film_name'] ?? null;
        
        // 如果 film_name 为空，尝试从其他字段获取有意义的名称
        if (empty($projectName)) {
            $projectName = $crewInfo['name'] ?? null;
        }
        
        
        // 构建项目信息
        $project = [
            'name' => $projectName,
            'start_date' => $crewInfo['start_date'] ?? date('Y-m-d'),
            'end_date' => $crewInfo['end_date'] ?? date('Y-m-d', strtotime('+30 days')),
            'total_scenes' => $crewInfo['total_scenes'] ?? 0,
            'total_shots' => $crewInfo['total_shots'] ?? 0,
            'estimated_days' => $crewInfo['estimated_days'] ?? 15,
            'actual_days' => $crewInfo['actual_days'] ?? 0,
            'days_completed' => $crewInfo['days_completed'] ?? 0,
            'completion_rate' => $crewInfo['completion_rate'] ?? 0
        ];
    } else {
        // 如果 crew 表中没有数据，使用默认值
        $projectName = '未定名项目';
        
        
        $project = [
            'name' => $projectName,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days')),
            'total_scenes' => 0,
            'total_shots' => 0,
            'estimated_days' => 15,
            'actual_days' => 0,
            'days_completed' => 0,
            'completion_rate' => 0
        ];
    }
    
    // 构建项目数据（$project 总是存在，因为前面的逻辑已经确保了这一点）
    $projectData = [
        'name' => $project['name'] ?? '未命名项目',
        'startDate' => $project['start_date'] ?? date('Y-m-d'),
        'endDate' => $project['end_date'] ?? date('Y-m-d', strtotime('+30 days')),
        'totalScenes' => $project['total_scenes'] ?? 0,
        'totalShots' => $project['total_shots'] ?? 0,
        'estimatedDays' => $project['estimated_days'] ?? 15,
        'actualDays' => $project['actual_days'] ?? 0,
        'daysCompleted' => $project['days_completed'] ?? 0,
        'completionRate' => $project['completion_rate'] ?? 0
    ];
    
    // 获取剧组信息 - 只从 crew_organization 表获取
    $crewMembers = [];
    
    // 从 crew_organization 表获取（关联 crew_id 字段）
    try {
        // 尝试使用 task_id 条件查询
        $sql = "SELECT co.*, c.role, c.name, c.phone, c.email, c.call_time, c.department FROM crew_organization co LEFT JOIN crew c ON co.crew_id = c.id WHERE co.task_id = :task_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_STR);
        $stmt->execute();
        $crewMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // 如果使用 task_id 查询失败，尝试不使用条件
        error_log("crew_organization 表 task_id 查询失败: " . $e->getMessage());
        
        try {
            $sql = "SELECT co.*, c.role, c.name, c.phone, c.email, c.call_time, c.department FROM crew_organization co LEFT JOIN crew c ON co.crew_id = c.id LIMIT 10";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $crewMembers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            // 如果所有查询都失败，记录错误
            error_log("所有 crew_organization 表查询都失败: " . $e->getMessage());
        }
    }
    
    // 2. 如果没有，使用默认的剧组成员数据
    if (empty($crewMembers)) {
        $crewMembers = [
            [
                'role' => '编剧',
                'name' => '李鹏',
                'phone' => '13800138006',
                'email' => 'www000@storyboards.cn',
                'call_time' => '',
                'department' => '编剧组'
            ],
            [
                'role' => '特邀',
                'name' => '18082899522',
                'phone' => '18082899522',
                'email' => '',
                'call_time' => '',
                'department' => '演员组'
            ],
            [
                'role' => '导演',
                'name' => '颜峰',
                'phone' => '13900000001',
                'email' => '',
                'call_time' => '',
                'department' => '导演组'
            ],
            [
                'role' => '副导演',
                'name' => '王杰',
                'phone' => '13900000002',
                'email' => '',
                'call_time' => '',
                'department' => '导演组'
            ],
            [
                'role' => '执行导演',
                'name' => '李小冉',
                'phone' => '13900000003',
                'email' => '',
                'call_time' => '',
                'department' => '导演组'
            ],
            [
                'role' => '制片主任',
                'name' => '谢文娜',
                'phone' => '13900000004',
                'email' => '',
                'call_time' => '',
                'department' => '制片组'
            ],
            [
                'role' => '摄影师',
                'name' => '刘晓冉',
                'phone' => '13900000005',
                'email' => '',
                'call_time' => '',
                'department' => '摄影组'
            ],
            [
                'role' => '现场制片',
                'name' => '李依晓',
                'phone' => '13900000006',
                'email' => '',
                'call_time' => '',
                'department' => '制片组'
            ],
            [
                'role' => '生活制片',
                'name' => '高晓慧',
                'phone' => '13900000007',
                'email' => '',
                'call_time' => '',
                'department' => '制片组'
            ],
            [
                'role' => '编剧',
                'name' => '饺子',
                'phone' => '13900000008',
                'email' => '',
                'call_time' => '',
                'department' => '编剧组'
            ],
            [
                'role' => '外联制片',
                'name' => '李大鹏',
                'phone' => '13900000009',
                'email' => '',
                'call_time' => '',
                'department' => '制片组'
            ],
            [
                'role' => '服装师',
                'name' => '唐丽敏',
                'phone' => '13900000010',
                'email' => '',
                'call_time' => '',
                'department' => '服装组'
            ],
            [
                'role' => '化妆师',
                'name' => '童真惠',
                'phone' => '13900000011',
                'email' => '',
                'call_time' => '',
                'department' => '化妆组'
            ],
            [
                'role' => '道具师',
                'name' => '白百合',
                'phone' => '13900000012',
                'email' => '',
                'call_time' => '',
                'department' => '道具组'
            ],
            [
                'role' => '美术师',
                'name' => '马优龙',
                'phone' => '13900000013',
                'email' => '',
                'call_time' => '',
                'department' => '美术组'
            ],
            [
                'role' => '录音师',
                'name' => '陈英',
                'phone' => '13900000015',
                'email' => '',
                'call_time' => '',
                'department' => '录音组'
            ]
        ];
    }
    
    $keyCrew = [];
    $departments = [];
    $departmentMembers = [];
    
    foreach ($crewMembers as $member) {
        $keyCrew[] = [
            'role' => $member['role'] ?? '',
            'name' => $member['name'] ?? '',
            'phone' => $member['phone'] ?? '',
            'email' => $member['email'] ?? '',
            'callTime' => $member['call_time'] ?? ''
        ];
        
        // 按部门分组
        $department = $member['department'] ?? '其他';
        if (!isset($departmentMembers[$department])) {
            $departmentMembers[$department] = [];
        }
        $departmentMembers[$department][] = $member['name'] . ' (' . $member['role'] . ')';
    }
    
    // 构建部门信息
    foreach ($departmentMembers as $departmentName => $members) {
        $departments[] = [
            'name' => $departmentName,
            'members' => $members
        ];
    }
    
    $crewData = [
        'keyCrew' => $keyCrew,
        'departments' => $departments
    ];
    
    // 设备信息 - 直接使用默认值（因为没有 equipment 表）
    $equipmentData = [
        'cameras' => [
            [
                'item' => 'ARRI ALEXA Mini LF',
                'quantity' => 1,
                'assignedTo' => '王强',
                'status' => 'in-use'
            ]
        ],
        'lenses' => [
            [
                'item' => '35mm f/1.4 定焦镜头',
                'quantity' => 2,
                'assignedTo' => '摄影组',
                'status' => 'in-use'
            ]
        ],
        'lighting' => [
            [
                'item' => 'ARRI SkyPanel S60 天空灯',
                'quantity' => 2,
                'assignedTo' => '李师傅',
                'status' => 'in-use'
            ]
        ],
        'sound' => [
            [
                'item' => 'Sony UWP-D无线麦克风系统',
                'quantity' => 4,
                'assignedTo' => '赵工',
                'status' => 'in-use'
            ]
        ],
        'support' => [
            [
                'item' => 'Manfrotto MVH502A 三脚架',
                'quantity' => 3,
                'assignedTo' => '摄影组',
                'status' => 'in-use'
            ]
        ]
    ];
    
    // 交通信息 - 直接使用默认值（因为没有 transportation 表）
    $transportationData = [
        'crewTransport' => '剧组大巴车',
        'castTransport' => '演员专车',
        'departureTime' => '06:30',
        'pickupPoints' => [
            [
                'location' => '公司总部',
                'time' => '06:30'
            ]
        ]
    ];
    
    // 餐饮信息 - 直接使用默认值（因为没有 meals 表）
    $mealsData = [
        'breakfast' => [
            'time' => '07:00',
            'location' => '化妆间',
            'provided' => true
        ],
        'lunch' => [
            'time' => '12:00',
            'location' => '拍摄现场',
            'provided' => true
        ],
        'dinner' => [
            'time' => '18:30',
            'location' => '餐厅',
            'provided' => false
        ]
    ];
    
    // 安全信息 - 直接使用默认值（因为没有 safety 表）
    $safetyData = [
        'safetyOfficer' => '王安全',
        'emergencyContact' => '138****9999',
        'firstAidStation' => '现场医疗点',
        'evacuationPlan' => '紧急出口位于场地东侧'
    ];
    
    // 备注信息 - 直接使用默认值（因为没有 notes 表）
    $notesData = [
        'productionNotes' => [
            '所有演员请提前1小时到达化妆间'
        ],
        'directorNotes' => [
            '重点表现角色情绪变化'
        ],
        'cinematographerNotes' => [
            '注意光线变化，及时调整曝光'
        ],
        'castingNotes' => [
            '注意演员情绪状态，确保表演连贯性'
        ],
        'costumeNotes' => [
            '所有服装需要提前熨烫，确保上镜效果'
        ],
        'makeupNotes' => [
            '注意补妆，特别是在出汗后要及时处理'
        ],
        'propsNotes' => [
            '所有道具需要检查安全性，避免意外发生'
        ]
    ];
    
} catch (Exception $e) {
    // 数据库查询失败时，使用默认值
    error_log("获取数据库信息失败: " . $e->getMessage());
    
    // 使用默认值
    $projectData = [
        'name' => '项目 ' . $task_id,
        'startDate' => date('Y-m-d'),
        'endDate' => date('Y-m-d', strtotime('+30 days')),
        'totalScenes' => 0,
        'totalShots' => 0,
        'estimatedDays' => 15,
        'actualDays' => 0,
        'daysCompleted' => 0,
        'completionRate' => 0
    ];
    
    // 使用默认剧组成员数据
    $keyCrew = [
        [
            'role' => '编剧',
            'name' => '李鹏',
            'phone' => '13800138006',
            'email' => 'www000@storyboards.cn',
            'callTime' => ''
        ],
        [
            'role' => '特邀',
            'name' => '18082899522',
            'phone' => '18082899522',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '导演',
            'name' => '颜峰',
            'phone' => '13900000001',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '副导演',
            'name' => '王杰',
            'phone' => '13900000002',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '执行导演',
            'name' => '李小冉',
            'phone' => '13900000003',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '制片主任',
            'name' => '谢文娜',
            'phone' => '13900000004',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '摄影师',
            'name' => '刘晓冉',
            'phone' => '13900000005',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '现场制片',
            'name' => '李依晓',
            'phone' => '13900000006',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '生活制片',
            'name' => '高晓慧',
            'phone' => '13900000007',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '编剧',
            'name' => '饺子',
            'phone' => '13900000008',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '外联制片',
            'name' => '李大鹏',
            'phone' => '13900000009',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '服装师',
            'name' => '唐丽敏',
            'phone' => '13900000010',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '化妆师',
            'name' => '童真惠',
            'phone' => '13900000011',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '道具师',
            'name' => '白百合',
            'phone' => '13900000012',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '美术师',
            'name' => '马优龙',
            'phone' => '13900000013',
            'email' => '',
            'callTime' => ''
        ],
        [
            'role' => '录音师',
            'name' => '陈英',
            'phone' => '13900000015',
            'email' => '',
            'callTime' => ''
        ]
    ];
    
    $crewData = [
        'keyCrew' => $keyCrew,
        'departments' => [
            [
                'name' => '导演组',
                'members' => [
                    '颜峰 (导演)',
                    '王杰 (副导演)',
                    '李小冉 (执行导演)'
                ]
            ],
            [
                'name' => '制片组',
                'members' => [
                    '谢文娜 (制片主任)',
                    '李依晓 (现场制片)',
                    '高晓慧 (生活制片)',
                    '李大鹏 (外联制片)'
                ]
            ],
            [
                'name' => '摄影组',
                'members' => [
                    '刘晓冉 (摄影师)'
                ]
            ],
            [
                'name' => '编剧组',
                'members' => [
                    '李鹏 (编剧)',
                    '饺子 (编剧)'
                ]
            ],
            [
                'name' => '服装组',
                'members' => [
                    '唐丽敏 (服装师)'
                ]
            ],
            [
                'name' => '化妆组',
                'members' => [
                    '童真惠 (化妆师)'
                ]
            ],
            [
                'name' => '道具组',
                'members' => [
                    '白百合 (道具师)'
                ]
            ],
            [
                'name' => '美术组',
                'members' => [
                    '马优龙 (美术师)'
                ]
            ],
            [
                'name' => '录音组',
                'members' => [
                    '陈英 (录音师)'
                ]
            ],
            [
                'name' => '演员组',
                'members' => [
                    '18082899522 (特邀)'
                ]
            ]
        ]
    ];
    
    // 使用默认设备数据
    $equipmentData = [
        'cameras' => [
            [
                'item' => 'ARRI ALEXA Mini LF',
                'quantity' => 1,
                'assignedTo' => '王强',
                'status' => 'in-use'
            ]
        ],
        'lenses' => [
            [
                'item' => '35mm f/1.4 定焦镜头',
                'quantity' => 2,
                'assignedTo' => '摄影组',
                'status' => 'in-use'
            ]
        ],
        'lighting' => [
            [
                'item' => 'ARRI SkyPanel S60 天空灯',
                'quantity' => 2,
                'assignedTo' => '李师傅',
                'status' => 'in-use'
            ]
        ],
        'sound' => [
            [
                'item' => 'Sony UWP-D无线麦克风系统',
                'quantity' => 4,
                'assignedTo' => '赵工',
                'status' => 'in-use'
            ]
        ],
        'support' => [
            [
                'item' => 'Manfrotto MVH502A 三脚架',
                'quantity' => 3,
                'assignedTo' => '摄影组',
                'status' => 'in-use'
            ]
        ]
    ];
    
    $transportationData = [
        'crewTransport' => '剧组大巴车',
        'castTransport' => '演员专车',
        'departureTime' => '06:30',
        'pickupPoints' => [
            [
                'location' => '公司总部',
                'time' => '06:30'
            ]
        ]
    ];
    
    $mealsData = [
        'breakfast' => [
            'time' => '07:00',
            'location' => '化妆间',
            'provided' => true
        ],
        'lunch' => [
            'time' => '12:00',
            'location' => '拍摄现场',
            'provided' => true
        ],
        'dinner' => [
            'time' => '18:30',
            'location' => '餐厅',
            'provided' => false
        ]
    ];
    
    $safetyData = [
        'safetyOfficer' => '王安全',
        'emergencyContact' => '138****9999',
        'firstAidStation' => '现场医疗点',
        'evacuationPlan' => '紧急出口位于场地东侧'
    ];
    
    $notesData = [
        'productionNotes' => [
            '所有演员请提前1小时到达化妆间'
        ],
        'directorNotes' => [
            '重点表现角色情绪变化'
        ],
        'cinematographerNotes' => [
            '注意光线变化，及时调整曝光'
        ],
        'castingNotes' => [
            '注意演员情绪状态，确保表演连贯性'
        ],
        'costumeNotes' => [
            '所有服装需要提前熨烫，确保上镜效果'
        ],
        'makeupNotes' => [
            '注意补妆，特别是在出汗后要及时处理'
        ],
        'propsNotes' => [
            '所有道具需要检查安全性，避免意外发生'
        ]
    ];
}

// 创建完整的补充JSON文件，包含所有必要的字段
$defaultData = [
    'project' => $projectData,
    'crew' => $crewData,
    'equipment' => $equipmentData,
    'transportation' => $transportationData,
    'meals' => $mealsData,
    'safety' => $safetyData,
    'notes' => $notesData,
    'config' => [
        'shotTypePriority' => [
            '大远景' => 1,
            '远景' => 2,
            '全景' => 3,
            '中全景' => 4,
            '航拍' => 5,
            '中景' => 6,
            '中近景' => 7,
            '特写' => 8,
            '大特写' => 9
        ],
        'shootingTime' => [
            'callTime' => '06:00',
            'dayShootStart' => '08:00',
            'lunchBreak' => ['12:00', '13:00'],
            'dinnerBreak' => ['18:00', '19:00'],
            'nightShootStart' => '20:00',
            'wrapTime' => '24:00',
            'maxWrapTime' => '02:00',
            'maxShootingHours' => 12,
            'maxWorkingHours' => 16
        ],
        'scenePriority' => [
            '最高' => ['min_actors' => 3, 'exterior' => true],
            '高' => ['min_actors' => 2, 'exterior' => true],
            '中' => ['min_actors' => 1, 'exterior' => false],
            '低' => ['min_actors' => 1, 'exterior' => false],
            '最低' => ['min_actors' => 0]
        ],
        'priorityMap' => [
            '最高' => 1,
            '高' => 2,
            '中' => 3,
            '低' => 4,
            '最低' => 5
        ],
        'shootingEfficiency' => 4
    ],
    'characterActorMap' => [],
    'actorSchedules' => [],
    'locationAvailability' => []
];

$emptyData = json_encode($defaultData, JSON_UNESCAPED_UNICODE);
file_put_contents($supplementalJson_file, $emptyData);

// 从数据库获取分镜数据
$scriptJson = '';
try {
    // 创建数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 获取场景数据
    $sql = "SELECT * FROM scenes WHERE task_id = :task_id ORDER BY scene_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':task_id', $task_id, PDO::PARAM_STR);
    $stmt->execute();
    $scenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($scenes)){

// 清除所有之前的输出
ob_clean();

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 输出空的 JSON 结构
echo '{
  "project": {},
  "schedule": {},
  "statistics": {},
  "resources": {
    "actors": [],
    "equipment": []
  }
}';

exit;


    }
    
    // 为每个场景获取对应的分镜数据，并转换字段名以匹配 schedule_class.php 的期望格式
    foreach ($scenes as &$scene) {
        // 添加 'id' 字段，与 'scene_id' 保持一致，以匹配 schedule_class.php 的期望格式
        $scene['id'] = $scene['scene_id'];
        
        // 确保场景名称字段存在
        if (!isset($scene['scene_name'])) {
            $scene['scene_name'] = $scene['name'] ?? '未命名场景';
        }
        
        // 确保 tags 字段存在
        if (!isset($scene['tags'])) {
            $scene['tags'] = [];
        }
        
        $sql = "SELECT * FROM shots WHERE task_id = :task_id AND scenes_id = :scene_id ORDER BY shots_id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':task_id', $task_id, PDO::PARAM_STR);
        $stmt->bindParam(':scene_id', $scene['scene_id'], PDO::PARAM_STR);
        $stmt->execute();
        $shots = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // 处理每个镜头数据，确保必要的字段存在
        foreach ($shots as &$shot) {
            // 确保 duration 字段存在，默认为 60 秒
            if (!isset($shot['duration']) || empty($shot['duration'])) {
                $shot['duration'] = 60;
            }
            
            // 确保 characterCostumes 字段存在
            if (!isset($shot['characterCostumes'])) {
                $shot['characterCostumes'] = '';
            }
            
            // 确保 characterMakeup 字段存在
            if (!isset($shot['characterMakeup'])) {
                $shot['characterMakeup'] = '';
            }
            
            // 确保 characterActions 字段存在
            if (!isset($shot['characterActions'])) {
                $shot['characterActions'] = '';
            }
            
            // 确保 cameraAngle 字段存在
            if (!isset($shot['cameraAngle'])) {
                $shot['cameraAngle'] = '';
            }
            
            // 确保 cameraMovement 字段存在
            if (!isset($shot['cameraMovement'])) {
                $shot['cameraMovement'] = '';
            }
            
            // 确保 lensFocalLength 字段存在
            if (!isset($shot['lensFocalLength'])) {
                $shot['lensFocalLength'] = '';
            }
            
            // 确保 compositionFocus 字段存在
            if (!isset($shot['compositionFocus'])) {
                $shot['compositionFocus'] = '';
            }
            
            // 确保 cameraEquipment 字段存在
            if (!isset($shot['cameraEquipment'])) {
                $shot['cameraEquipment'] = '';
            }
            
            // 确保 lightTone 字段存在
            if (!isset($shot['lightTone'])) {
                $shot['lightTone'] = '';
            }
            
            // 确保 sound 字段存在
            if (!isset($shot['sound'])) {
                $shot['sound'] = '';
            }
            
            // 确保 props 字段存在
            if (!isset($shot['props'])) {
                $shot['props'] = '';
            }
            
            // 确保 imageUrl 字段存在
            if (!isset($shot['imageUrl'])) {
                $shot['imageUrl'] = '';
            }
        }
        
        $scene['shots'] = $shots;
    }
    
    // 构造与原JSON文件相同格式的数据
    $scriptData = [
        'scenes' => $scenes
    ];
    
    $scriptJson = json_encode($scriptData, JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    die("错误：从数据库获取分镜数据失败: " . $e->getMessage());
}

// 读取补充JSON文件
$supplementalJson = file_get_contents($supplementalJson_file);

$generator = new ShootingScheduleGenerator($scriptJson, $supplementalJson);
$shootingSchedule = $generator->generateSchedule();

$new_json = json_encode($shootingSchedule, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// 检查并保存日程文件
if (!file_exists($scheduleJson_file)) {
    if (!is_dir($file_path)) {
        mkdir($file_path, 0755, true);
    }
    file_put_contents($scheduleJson_file, $new_json);
}

// 读取日程文件
$scheduleContent = file_get_contents($scheduleJson_file);
echo $scheduleContent;
