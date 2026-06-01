<?php
/**
 * 分镜数据API接口
 * 从数据库中获取场次和分镜数据，格式与原来的JSON文件一致
 */

// 禁用错误输出，防止HTML错误信息破坏JSON格式
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT & ~E_DEPRECATED);

// 设置响应头
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 设置缓存控制头
$cacheTime = 3600; // 缓存时间（秒）
header('Cache-Control: public, max-age=' . $cacheTime);
header('Expires: ' . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

// 处理OPTIONS请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// 引入必要的文件
try {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/Auth.php';
} catch (Exception $e) {
    error_log('storyboard_api.php: 引入文件失败: ' . $e->getMessage());
    echo json_encode(['error' => '服务器内部错误'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 初始化认证
$auth = new Auth();

// 检查用户是否登录
$user = $auth->checkLogin();
if (!$user['success']) {
    echo json_encode(['error' => '用户未登录'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

$userId = $user['data']['id'];

// 获取参数
$taskId = $_GET['task_id'] ?? '';
$sceneId = $_GET['scene_id'] ?? '';
$shotId = $_GET['shot_id'] ?? '';
$page = $_GET['page'] ?? 1;
$limit = $_GET['limit'] ?? 20;

// 验证必需参数
if (empty($taskId)) {
    echo json_encode(['error' => '缺少task_id参数'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

// 如果提供了shot_id，则必须提供scene_id
if (!empty($shotId) && empty($sceneId)) {
    echo json_encode(['error' => '当提供shot_id时，必须同时提供scene_id参数'], JSON_UNESCAPED_UNICODE);
    exit(0);
}

try {
    // 获取数据库连接
    $db = Database::getInstance();
    $pdo = $db->getPdo();
    
    // 检查当前用户是否有权限访问该任务
    $sql = "SELECT id FROM tasks WHERE task_id = :task_id AND user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    
    if (!$stmt->fetch()) {
        echo json_encode(['error' => '任务不存在或无权限访问'], JSON_UNESCAPED_UNICODE);
        exit(0);
    }
    
    // 添加调试信息
    error_log("storyboard_api.php: 获取task_id为 {$taskId} 的分镜数据");
    
    // 从scenes表获取场次数据，按sort_order排序
    $sql = "SELECT scene_id, scene_name, scenes_tags, sort_order FROM scenes WHERE task_id = :task_id ORDER BY sort_order ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
    $stmt->execute();
    $scenes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    error_log("storyboard_api.php: 找到 " . count($scenes) . " 个场次");
    
    $result = [
        'scenes' => []
    ];
    
    // 如果提供了scene_id和shot_id，只返回指定的分镜数据
    if (!empty($sceneId) && !empty($shotId)) {
        // 检查场次是否存在
        $sceneSql = "SELECT scene_id, scene_name, scenes_tags FROM scenes WHERE task_id = :task_id AND scene_id = :scene_id";
        $sceneStmt = $pdo->prepare($sceneSql);
        $sceneStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $sceneStmt->bindParam(':scene_id', $sceneId, PDO::PARAM_STR);
        $sceneStmt->execute();
        $scene = $sceneStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$scene) {
            echo json_encode(['error' => '场次不存在'], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        
        // 解析tags
        $tags = json_decode($scene['scenes_tags'], true) ?? [];
        
        // 从shots表获取指定分镜数据
        $shotSql = "SELECT 
                    shots_id as id, 
                    scenes_id as sceneId, 
                    shotType, 
                    duration, 
                    content, 
                    remark, 
                    sceneExpectation, 
                    sound, 
                    cameraAngle, 
                    cameraMovement, 
                    cameraEquipment, 
                    lensFocalLength, 
                    compositionFocus, 
                    lightTone, 
                    location, 
                    time, 
                    weather, 
                    dialogue, 
                    script, 
                    characters, 
                    characterCostumes, 
                    characterMakeup, 
                    characterActions, 
                    props, 
                    customContent,
                    imageUrl,
                    imageUrls,
                    video_image_Url,
                    videoCutUrl,
                    sort_order
                  FROM shots 
                  WHERE task_id = :task_id AND scenes_id = :scene_id AND shots_id = :shot_id";
        $shotStmt = $pdo->prepare($shotSql);
        $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $shotStmt->bindParam(':scene_id', $sceneId, PDO::PARAM_STR);
        $shotStmt->bindParam(':shot_id', $shotId, PDO::PARAM_STR);
        $shotStmt->execute();
        $shot = $shotStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shot) {
            echo json_encode(['error' => '分镜不存在'], JSON_UNESCAPED_UNICODE);
            exit(0);
        }
        
        error_log("storyboard_api.php: 找到指定分镜数据: 场次 {$sceneId}, 分镜 {$shotId}");
        
        // 构建场次数据
        $sceneData = [
            'id' => $scene['scene_id'],
            'name' => $scene['scene_name'],
            'tags' => $tags,
            'shots' => [$shot]
        ];
        
        $result['scenes'][] = $sceneData;
    } else {
        // 计算总分镜数
        $totalSql = "SELECT COUNT(*) as total FROM shots WHERE task_id = :task_id";
        $totalStmt = $pdo->prepare($totalSql);
        $totalStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
        $totalStmt->execute();
        $totalResult = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $totalShots = $totalResult['total'];
        
        // 计算偏移量
        $offset = ($page - 1) * $limit;
        
        // 遍历每个场次，获取对应的分镜数据
        foreach ($scenes as $scene) {
            // 解析tags
            $tags = json_decode($scene['scenes_tags'], true) ?? [];
            
            // 根据请求参数决定排序方式
            $sortBy = $_GET['sort_by'] ?? 'shots_id';
            $orderClause = '';
            
            if ($sortBy === 'sort_order') {
                $orderClause = 'ORDER BY sort_order ASC';
            } else {
                $orderClause = 'ORDER BY shots_id ASC';
            }
            
            // 从shots表获取分镜数据（支持分页）
            $shotSql = "SELECT 
                        shots_id as id, 
                        scenes_id as sceneId, 
                        shotType, 
                        duration, 
                        content, 
                        remark, 
                        sceneExpectation, 
                        sound, 
                        cameraAngle, 
                        cameraMovement, 
                        cameraEquipment, 
                        lensFocalLength, 
                        compositionFocus, 
                        lightTone, 
                        location, 
                        time, 
                        weather, 
                        dialogue, 
                        script, 
                        characters, 
                        characterCostumes, 
                        characterMakeup, 
                        characterActions, 
                        props, 
                        customContent,
                        imageUrl,
                        imageUrls,
                        video_image_Url,
                        videoCutUrl,
                        sort_order
                      FROM shots 
                      WHERE task_id = :task_id AND scenes_id = :scene_id 
                      " . $orderClause . " LIMIT :limit OFFSET :offset";
            $shotStmt = $pdo->prepare($shotSql);
            $shotStmt->bindParam(':task_id', $taskId, PDO::PARAM_STR);
            $shotStmt->bindParam(':scene_id', $scene['scene_id'], PDO::PARAM_STR);
            $shotStmt->bindParam(':limit', $limit, PDO::PARAM_INT);
            $shotStmt->bindParam(':offset', $offset, PDO::PARAM_INT);
            $shotStmt->execute();
            $shots = $shotStmt->fetchAll(PDO::FETCH_ASSOC);
            
            error_log("storyboard_api.php: 场次 " . $scene['scene_id'] . " 找到 " . count($shots) . " 个分镜");
            
            // 构建场次数据
            $sceneData = [
                'id' => $scene['scene_id'],
                'name' => $scene['scene_name'],
                'tags' => $tags,
                'shots' => $shots
            ];
            
            $result['scenes'][] = $sceneData;
        }
        
        // 添加分页信息
        $result['pagination'] = [
            'total' => $totalShots,
            'page' => (int)$page,
            'limit' => (int)$limit,
            'total_pages' => ceil($totalShots / $limit)
        ];
    }
    
    // 添加调试信息
    error_log("storyboard_api.php: 返回 " . count($result['scenes']) . " 个场次数据");
    
    // 返回JSON响应
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit(0);
    
} catch (Exception $e) {
    // 记录错误
    error_log("Storyboard API Error: " . $e->getMessage());
    // 返回错误响应
    echo json_encode(['error' => '获取分镜数据失败: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit(0);
}
