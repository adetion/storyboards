<?php

/**
 * 根据当前登录用户生成剧组信息JSON文件
 * 兼容PHP 7.4版本
 */

// 引入必要的配置和数据库类
require_once 'config.php';

// 假设已有的用户认证机制，此处简化处理
// 实际应用中应该从会话或其他认证系统获取用户ID
$admin_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 1;
// 接收task_id参数，优先使用该参数生成文件名
$task_id = isset($_GET['task_id']) ? $_GET['task_id'] : null;

try {
    // 获取数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    // 获取当前用户管理的剧组信息
    $crewQuery = "SELECT * 
                  FROM crew 
                  WHERE admin_user_id = ? 
                  LIMIT 1";

    $stmt = $pdo->prepare($crewQuery);
    $stmt->execute([$admin_user_id]);
    $crewData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$crewData) {
        throw new Exception("未找到该用户管理的剧组信息");
    }

    // 构建项目信息
    $project = [
        "name" => $crewData['name'],
        "startDate" => $crewData['startDate'],
        "endDate" => $crewData['endDate'],
        "totalScenes" => $crewData['totalScenes'], // 默认值
        "totalShots" => $crewData['totalShots'], // 默认值
        "estimatedDays" => $crewData['estimatedDays'], // 默认值
        "actualDays" => $crewData['actualDays'], // 默认值
        "daysCompleted" => $crewData['daysCompleted'], // 默认值
        "completionRate" => $crewData['completionRate'] // 默认值
    ];
    // 从crew表中获取当前任务号
    $currentTaskId = $crewData['current_task_id'];

    // 从crew_organization表获取关键剧组成员信息
    $organizationQuery = "SELECT name, position, phone, email, wechat 
                          FROM crew_organization 
                          WHERE crew_id = ? AND admin_user_id = ?";

    $stmt = $pdo->prepare($organizationQuery);
    $stmt->execute([$crewData['id'], $admin_user_id]);
    $organizationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 构建crew.keyCrew数组
    $keyCrew = [];
    foreach ($organizationData as $member) {
        $keyCrew[] = [
            "role" => $member['position'],
            "name" => $member['name'],
            "phone" => $member['phone'] ?? "",
            "email" => $member['email'] ?? "",
            "callTime" => "" // 默认为空
        ];
    }

    // 构建完整的JSON结构
    $jsonData = [
        "project" => $project,
        "crew" => [
            "keyCrew" => $keyCrew,
            "departments" => [] // 简化处理，实际可以根据职位分组
        ]
    ];

    // 输出JSON到文件
    $outputFile = __DIR__ . '/results/' . $currentTaskId . '_tmp.json';
    file_put_contents($outputFile, json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

    echo "JSON文件已成功生成: " . $outputFile . "\n";
    echo "内容预览:\n";
    echo json_encode($jsonData, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "错误: " . $e->getMessage() . "\n";
}
