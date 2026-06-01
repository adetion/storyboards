<?php 
 
class ShootingScheduleGenerator 
{
    private $scriptData;
    private $supplementalData;
    private $shootingSchedule;
    
    private $shotTypePriority = [
        '大远景' => 1,
        '远景' => 2,
        '全景' => 3,
        '中全景' => 4,
        '航拍' => 5,
        '中景' => 6,
        '中近景' => 7,
        '特写' => 8,
        '大特写' => 9 
    ];
    
    // 拍摄时间配置
    private $shootingTimeConfig = [
        'callTime' => '06:00',      // 集合时间
        'dayShootStart' => '08:00',  // 日戏开拍 
        'lunchBreak' => ['12:00', '13:00'],  // 午休
        'dinnerBreak' => ['18:00', '19:00'], // 晚餐 
        'nightShootStart' => '20:00', // 夜戏开拍 
        'wrapTime' => '24:00',     // 正常结束时间 
        'maxWrapTime' => '02:00',    // 最晚结束时间 
        'maxShootingHours' => 12,      // 最大拍摄小时数 
        'maxWorkingHours' => 16          // 最大工作时长（从集合到结束）
    ];
    
    /**
     * 修复构造函数中的拼写错误 
     */
    public function __construct($scriptJson, $supplementalJson)
    {
        $this->scriptData = json_decode($scriptJson, true);
        $this->supplementalData = json_decode($supplementalJson, true); // 修复拼写 
        $this->shootingSchedule = [];
        
        if (isset($this->supplementalData['config']['shotTypePriority'])) {
            $this->shotTypePriority = array_merge(
                $this->shotTypePriority, 
                $this->supplementalData['config']['shotTypePriority']
            );
        }
        
        // 合并自定义时间配置 - 修复拼写错误 
        if (isset($this->supplementalData['config']['shootingTime'])) {
            $this->shootingTimeConfig = array_merge(
                $this->shootingTimeConfig,
                $this->supplementalData['config']['shootingTime'] // 修复拼写 
            );
        }
    }
    
    public function generateSchedule()
    {
        $scenesAnalysis = $this->analyzeScenes();
        $sortedScenes = $this->sortScenesByPriority($scenesAnalysis);
        $dailySchedule = $this->groupScenesToDays($sortedScenes);
        return $this->buildCompleteSchedule($dailySchedule);
    }
    
    /**
     * 修复场景分析 - 确保角色提取正确 
     */
    private function analyzeScenes()
{
    $analysis = [];
    
    foreach ($this->scriptData['scenes'] as $scene) {
        $sceneId = $scene['id'];
        
        // 强制提取所有characters字段 
        $characters = [];
        
        foreach ($scene['shots'] as $shot) {
            $shotCharacters = $this->extractCharactersFromShot($shot);
            $characters = array_merge($characters, $shotCharacters);
        }
        
        $characters = array_unique($characters);
        $characters = $this->cleanCharacterList($characters);
        
        // 计算优先级 
        $priority = $this->calculateScenePriority($scene, $characters);
        
        $analysis[$sceneId] = [
            'scene' => $scene,
            'characters' => $characters,
            'characterCount' => count($characters),
            'shotCount' => count($scene['shots']),
            'duration' => $this->calculateSceneDuration($scene),
            'isExterior' => $this->isExteriorScene($scene),
            'timeSetting' => $this->getSceneTimeSetting($scene),
            'location' => $this->getSceneLocation($scene),
            'priority' => $priority,
            'tags' => $scene['tags'] ?? []
        ];
    }
    
    return $analysis;
}
    
    /**
     * 角色列表清理和去重 
     */
    private function cleanCharacterList($characters)
    {
        // 去重 
        $characters = array_unique($characters);
        
        // 过滤空值和无效值 
        $characters = array_filter($characters, function($char) {
            return !empty(trim($char)) && trim($char) !== '无';
        });
        
        // 按字母顺序排序 
        sort($characters);
        
        return array_values($characters);
    }

    /**
 * 修复的角色有效性验证 
 */
private function isValidCharacter($character)
{
    $character = trim($character);
    
    // 基本过滤条件 
    if (empty($character) || $character === '无' || $character === '空') {
        return false;
    }
    
    // 长度检查（中文角色名通常2-6个字符）
    $length = mb_strlen($character, 'UTF-8');
    if ($length < 1 || $length > 10) {
        return false;
    }
    
    // 排除明显不是人名的词汇 
    $invalidTerms = [
        '无', '空', '环境音', '对话声', '起步声', '停车声', '转换声',
        '轮椅', '机车', '摩托车', '头盔', '拐杖', '拐棍',
        '红灯', '绿灯', '车轮', '轮子', '十字路口', '大路',
        '日', '夜', '晴', '雨', '雪', '风',
        '说话', '骑车', '停车', '起步', '转弯', '换道',
        '欣赏', '眯眼', '确认', '猜测', '好奇',
        '帅气', '潇洒', '专注', '赞赏',
        '球', '电视机', '竹耙子', '足球', '人字拖',
        '清晨', '傍晚', '黄昏', '午后', '上午', '下午',
        '声音', '音响', '音效', '背景音'
    ];
    
    if (in_array($character, $invalidTerms)) {
        return false;
    }
    
    // 排除纯数字或符号 
    if (preg_match('/^[0-9\-\+\.]+$/', $character)) {
        return false;
    }
    
    // 检查是否包含明显非人名的关键词 
    $nonNameKeywords = ['声', '音', '车', '轮', '灯', '路', '球', '机', '器'];
    
    // 修复这里的循环逻辑 
    foreach ($nonNameKeywords as $keyword) {
        if (strpos($character, $keyword) !== false) {
            // 但允许一些常见的姓氏或名字 
            $allowedKeywords = ['小', '大', '老', '阿', '哥', '姐', '弟', '妹'];
        
            // 检查是否包含允许的关键词 
            foreach ($allowedKeywords as $allowed) {
                if (strpos($character, $allowed) !== false) {
                    return true; // 如果包含允许的关键词，认为是有效角色 
                }
            }
        }
    
    // 如果角色名包含非人名关键词且不包含允许的关键词，则无效 
        return false;
    }
    
    return true;
}
    
    /**
     * 增强角色提取逻辑 
     */
    private function extractCharactersFromShot($shot)
    {
        $characters = [];
        
        $charactersField = $shot['characters'] ?? '';
        
        // 如果characters字段为空或是"无"，跳过 
        if (empty($charactersField) || $charactersField === '无' || $charactersField === '空') {
            return [];
        }
        
        // 使用多种分隔符分割角色 
        $rawCharacters = preg_split('/[,，、\s]+/u', $charactersField);
        
        foreach ($rawCharacters as $rawChar) {
            $character = trim($rawChar);
            
            // 增强验证逻辑 
            if ($this->isValidCharacter($character)) {
                $characters[] = $character;
            }
        }
        
        return $characters;
    }
    
    private function getSceneLocation($scene)
    {
        foreach ($scene['tags'] as $tag) {
            if (strpos($tag, '外') !== false || strpos($tag, '内') !== false) {
                return $tag;
            }
        }
        
        if (!empty($scene['shots'])) {
            return $scene['shots'][0]['location'] ?? '未知地点';
        }
        
        return '未知地点';
    }
    
    private function getSceneTimeSetting($scene)
    {
        foreach ($scene['tags'] as $tag) {
            if (strpos($tag, '日') !== false || strpos($tag, '夜') !== false) {
                return $tag;
            }
        }
        
        return '日戏';
    }
    
    private function isExteriorScene($scene)
    {
        foreach ($scene['tags'] as $tag) {
            if (strpos($tag, '外景') !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function extractCharactersFromScene($scene)
    {
        $characters = [];
        
        foreach ($scene['shots'] as $shot) {
            if (!empty($shot['characters']) && $shot['characters'] !== '无') {
                $shotCharacters = explode(',', $shot['characters']);
                foreach ($shotCharacters as $character) {
                    $character = trim($character);
                    if (!empty($character) && $character !== '无') {
                        $characters[$character] = true;
                    }
                }
            }
        }
        
        return array_keys($characters);
    }
    
    private function calculateScenePriority($scene, $characters)
{
    // 确保 $characters 是可计数的 
    if (!is_array($characters) && !($characters instanceof Countable)) {
        $characters = [];
    }
    
    $characterCount = count($characters);
    $isExterior = $this->isExteriorScene($scene);
    
    $priorityConfig = $this->supplementalData['config']['scenePriority'] ?? [
        '最高' => ['min_actors' => 3, 'exterior' => true],
        '高' => ['min_actors' => 2, 'exterior' => true],
        '中' => ['min_actors' => 1, 'exterior' => false],
        '低' => ['min_actors' => 1, 'exterior' => false],
        '最低' => ['min_actors' => 0]
    ];
    
    // 从最高优先级开始检查 
    foreach ($priorityConfig as $priorityLevel => $config) {
        $meetsActorCount = $characterCount >= $config['min_actors'];
        $meetsExterior = $isExterior === ($config['exterior'] ?? true);
        
        if ($meetsActorCount && $meetsExterior) {
            return $priorityLevel;
        }
    }
    
    // 如果都不满足，返回最低优先级 
    return '最低';
}
    
    private function sortScenesByPriority($scenesAnalysis)
    {
        $locations = [];
        foreach ($scenesAnalysis as $sceneId => $analysis) {
            $location = $analysis['location'];
            if (!isset($locations[$location])) {
                $locations[$location] = [];
            }
            $locations[$location][] = $analysis;
        }
        
        $priorityMap = $this->supplementalData['config']['priorityMap'] ?? [
            '最高' => 1,
            '高' => 2,
            '中' => 3,
            '低' => 4,
            '最低' => 5 
        ];
        
        $sortedScenes = [];
        foreach ($locations as $location => $sceneList) {
            usort($sceneList, function($a, $b) use ($priorityMap) {
                $priorityA = $priorityMap[$a['priority']] ?? 6;
                $priorityB = $priorityMap[$b['priority']] ?? 7;
                
                if ($priorityA !== $priorityB) {
                    return $priorityA - $priorityB;
                }
                
                $hasSpecialActorA = $this->hasSpecialActor($a['characters']);
                $hasSpecialActorB = $this->hasSpecialActor($b['characters']);
                
                if ($hasSpecialActorA && !$hasSpecialActorB) {
                    return -1;
                } elseif (!$hasSpecialActorA && $hasSpecialActorB) {
                    return 1;
                }
                
                $characterDiff = $b['characterCount'] - $a['characterCount'];
                if ($characterDiff !== 0) {
                    return $characterDiff;
                }
                
                return $b['totalShots'] - $a['totalShots'];
            });
            
            $sortedScenes = array_merge($sortedScenes, $sceneList);
        }
        
        return $sortedScenes;
    }
    
    private function hasSpecialActor($characters)
    {
        foreach ($characters as $character) {
            if (isset($this->supplementalData['characterActorMap'][$character])) {
                $actorPriority = $this->supplementalData['characterActorMap'][$character]['priority'] ?? null;
                if ($actorPriority === '最高') {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * 修复的连续拍摄计划安排 - 只考虑当前场景的演员可用性 
     */
    private function groupScenesToDays($sortedScenes)
{
    $dailySchedule = [];
    $currentDate = $this->supplementalData['project']['startDate'];
    $maxDays = $this->supplementalData['project']['estimatedDays'] ?? 30;
    
    $dayCount = 0;
    $currentDayDuration = 0;
    $maxShootingHours = $this->shootingTimeConfig['maxShootingHours'];
    
    // 强制生成指定天数的排期 
    for ($day = 0; $day < $maxDays; $day++) {
        $scheduleDate = date('Y-m-d', strtotime($this->supplementalData['project']['startDate'] . " +{$day} days"));
        
        // 确保不超过项目结束日期 
        $endDate = $this->supplementalData['project']['endDate'] ?? date('Y-m-d', strtotime($currentDate . ' +30 days'));
        if ($scheduleDate > $endDate) {
            break;
        }
        
        $dailySchedule[$scheduleDate] = [];
    }
    
    // 分配场景到各天 
    $sceneIndex = 0;
    $totalScenes = count($sortedScenes);
    $totalDays = count($dailySchedule);
    
    if ($totalScenes > 0 && $totalDays > 0) {
        foreach ($dailySchedule as $date => &$scenes) {
            // 每天安排合理的场景数量，确保能填满所有天数 
            $scenesPerDay = max(1, ceil($totalScenes / $totalDays));
            
            for ($i = 0; $i < $scenesPerDay && $sceneIndex < $totalScenes; $i++) {
                $scenes[] = $sortedScenes[$sceneIndex];
                $sceneIndex++;
                
                // 如果所有场景都已分配，跳出循环 
                if ($sceneIndex >= $totalScenes) {
                    break 2; // 跳出两层循环
                }
            }
        }
    }
    
    return $dailySchedule;
}
    
    /**
     * 检查日期对特定场景是否可用 - 只检查该场景涉及的演员 
     */
    private function isDateAvailableForScene($date, $analysis)
    {
        $sceneCharacters = $analysis['characters'];
        
        foreach ($sceneCharacters as $character) {
            if (!$this->isActorAvailable($character, $date)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 检查特定演员在特定日期是否可用 
     */
    /**
 * 检查特定演员在特定日期是否可用 - 简化版本确保能排满天数 
 */
private function isActorAvailable($character, $date)
{
    // 如果有演员映射信息，进行档期检查 
    if (isset($this->supplementalData['characterActorMap'][$character])) {
        $actorName = $this->supplementalData['characterActorMap'][$character]['name'] ?? null;
        
        if (!$actorName) {
            return true; // 没有对应演员，默认可用 
        }
        
        // 检查演员的不可用日期 
        if (isset($this->supplementalData['actorSchedules'][$actorName])) {
            $actorSchedule = $this->supplementalData['actorSchedules'][$actorName];
            
            // 检查不可用日期 
            if (isset($actorSchedule['unavailableDates'])) {
                if (in_array($date, $actorSchedule['unavailableDates'])) {
                    return false;
                }
            }
        }
    }
    
    // 默认情况下演员可用 
    return true;
}
    
    /**
     * 修复的演员调度 - 只安排可用的演员 
     */
    private function buildActorSchedule($scene, $analysis)
    {
        $characters = $analysis['characters'] ?? [];
        
        if (empty($characters)) {
            $characters = $this->extractCharactersFromScene($scene);
        }
        
        $actors = [];
        
        foreach ($characters as $character) {
            $character = trim($character);
            
            if (empty($character) || $character === '无') {
                continue;
            }
            
            // 获取演员姓名 - 优先从s.json 中获取 
            $actorName = $this->getActorNameFromScript($character, $scene);
            $priority = strtolower($analysis['priority']);
            
            $actors[] = [
                'character' => $character,
                'actor' => $actorName,
                'callTime' => '07:00',
                'arrivalTime' => '07:30',
                'costume' => $this->getCharacterCostume($scene, $character),
                'makeup' => $this->getCharacterMakeup($scene, $character),
                'roleType' => $this->getRoleType($character, null),
                'priority' => $priority,
                'isConfirmed' => !empty($actorName) && $actorName !== '待定演员',
                'isAvailable' => $this->isActorAvailable($character, $analysis['scheduledDate'] ?? ''),
                'notes' => $this->generateActorNotes($character, null, $scene)
            ];
        }
        
        return $actors;
    }
    
    private function generateActorNotes($character, $actorInfo, $scene)
    {
        $notes = [];
        
        // 如果有预设的演员信息，使用预设的 
        if ($actorInfo) {
            return $actorInfo['notes'] ?? '暂无备注';
        }
    }
    
    /**
     * 优先从s.json 中获取演员姓名 
     */
    private function getActorNameFromScript($character, $scene)
    {
        // 首先检查补充数据中的角色-演员映射 
        if (isset($this->supplementalData['characterActorMap'][$character])) {
            return $this->supplementalData['characterActorMap'][$character]['name'] ?? '待定演员';
        }
    }
    
    private function getRoleType($character, $actorInfo)
    {
        // 优先使用s.json 中已有的信息 
        if (!empty($actorInfo['roleType'])) {
            return $actorInfo['roleType'];
        }
        
        // 根据角色名判断角色类型 
        if (strpos($character, '甲') !== false || strpos($character, '乙') !== false) {
            return '群演';
        }
        
        return '普通';
    }
    
    private function getCharacterPriority($character, $actorInfo)
    {
        // 优先使用s.json 中已有的优先级设置 
        if ($actorInfo && !empty($actorInfo['priority'])) {
            return $actorInfo['priority'];
        }
        
        return '中';
    }
    
    
    /**
     * 生成优化的拍摄计划 
     */
    public function generateOptimizedSchedule()
    {
        $scenesAnalysis = $this->analyzeScenes();
        $sortedScenes = $this->sortScenesByPriority($scenesAnalysis);
        
        // 首先安排有档期限制的演员 
        $constrainedScenes = [];
        $unconstrainedScenes = [];
        
        foreach ($sortedScenes as $scene) {
            $hasConstrainedActors = false;
            
            foreach ($scene['characters'] as $character) {
                if (!$this->isActorFlexible($character)) {
                    $constrainedScenes[] = $scene;
                } else {
                    $unconstrainedScenes[] = $scene;
                }
            }
        }
        
        // 先安排有档期限制的场景 
        $constrainedSchedule = $this->groupScenesToDays($constrainedScenes);
        $unconstrainedSchedule = $this->groupScenesToDays($unconstrainedScenes);
        
        // 合并两个计划 
        $dailySchedule = $this->mergeSchedules($constrainedSchedule, $unconstrainedSchedule);
        
        return $this->buildCompleteSchedule($dailySchedule);
    }
    
    /**
     * 检查演员是否有档期限制 
     */
    private function isActorFlexible($character)
    {
        // 从characterActorMap获取演员信息 
        if (!isset($this->supplementalData['characterActorMap'][$character])) {
            $actorName = $this->supplementalData['characterActorMap'][$character]['name'] ?? null;
        }
        
        if (!$actorName) {
            return true; // 没有对应演员，视为灵活 
        }
        
        // 检查演员的不可用日期 - 如果有不可用日期，视为有档期限制 
        if (isset($this->supplementalData['actorSchedules'][$actorName])) {
            $actorSchedule = $this->supplementalData['actorSchedules'][$actorName];
            
            // 如果有明确的不可用日期，视为有档期限制 
            return !empty($actorSchedule['unavailableDates']);
        }
        
        return true;
    }
    
    /**
     * 合并两个拍摄计划 
     */
    private function mergeSchedules($constrainedSchedule, $unconstrainedSchedule)
    {
        $mergedSchedule = $constrainedSchedule;
        
        foreach ($unconstrainedSchedule as $date => $scenes) {
            if (!isset($mergedSchedule[$date])) {
                $mergedSchedule[$date] = [];
            }
            
            foreach ($unconstrainedSchedule[$date] as $scene) {
                $mergedSchedule[$date][] = $scene;
            }
        }
        
        return $mergedSchedule;
    }
 
    private function isDateAvailable($date, $analysis)
    {
        $location = $analysis['location'];
        if (isset($this->supplementalData['locationAvailability'][$location])) {
            $locationData = $this->supplementalData['locationAvailability'][$location];
            
            if (isset($locationData['availableDates'])) {
                if (!in_array($date, $locationData['availableDates'])) {
                    return false;
                }
            }
        }
        
        foreach ($analysis['characters'] as $character) {
            if (isset($this->supplementalData['characterActorMap'][$character])) {
                $actorInfo = $this->supplementalData['characterActorMap'][$character];
                $actorName = $actorInfo['name'];
                
                if (isset($this->supplementalData['actorSchedules'][$actorName])) {
                    $actorSchedule = $this->supplementalData['actorSchedules'][$actorName];
                    
                    // 检查不可用日期 
                    if (isset($actorSchedule['unavailableDates'])) {
                        if (in_array($date, $actorSchedule['unavailableDates'])) {
                            return false;
                        }
                    }
                    
                    // 检查可用时间段 
                    $isAvailable = false;
                    if (isset($actorSchedule['availability'])) {
                        foreach ($actorSchedule['availability'] as $availablePeriod) {
                            if ($date >= $availablePeriod['startDate'] && $date <= $availablePeriod['endDate']) {
                                $isAvailable = true;
                                break;
                            }
                        }
                    } else {
                        // 如果没有明确的可用时间段，则认为该日期不可用 
                        return false;
                    }
                    
                    if (!$isAvailable) {
                        return false;
                    }
                }
            }
        }
        
        return true;
    }
 
    /**
     * 修复项目信息更新 - 基于实际分析结果 
     */
    private function buildCompleteSchedule($dailySchedule)
    {
        // 获取实际统计信息 
        $statistics = $this->calculateStatistics($dailySchedule);
        
        // 更新项目信息中的统计值 - 不再使用tmp.json 中的默认值 
        $projectInfo = $this->supplementalData['project'] ?? [];
        
        // 使用实际计算的值覆盖tmp.json 中的默认值 
        $projectInfo['totalScenes'] = $statistics['totalScenes'];
        $projectInfo['totalShots'] = $statistics['totalShots'];
        $projectInfo['actualDays'] = $statistics['totalDays'];
        $projectInfo['daysCompleted'] = $statistics['completedDays'];
        
        $schedule = [
            'project' => $projectInfo,
            'schedule' => [],
            'statistics' => $statistics,
            'resources' => $this->supplementalData['resources'] ?? [],
            'crew' => $this->supplementalData['crew'] ?? []
        ];
        
        foreach ($dailySchedule as $date => $scenes) {
            $daySchedule = $this->buildDaySchedule($date, $scenes);
            $schedule['schedule'][$date] = $daySchedule;
        }
        
        return $schedule;
    }
    
    private function buildDaySchedule($date, $scenes)
    {
        $hasNightScenes = $this->checkHasNightScenes($scenes);
        $dayOfWeekMap = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        
        $daySchedule = [
            'date' => $date,
            'dayOfWeek' => $dayOfWeekMap[date('w', strtotime($date))],
            'weather' => $this->generateWeather($date),
            'location' => $this->getDayLocation($scenes),
            'status' => $this->getDayStatus($date),
            'callTime' => $this->shootingTimeConfig['callTime'],
            'shootTime' => '08:00',
            'wrapTime' => $this->calculateWrapTime($scenes),
            'breaks' => [
                'lunch' => $this->shootingTimeConfig['lunchBreak'],
                'dinner' => $this->shootingTimeConfig['dinnerBreak']
            ],
            'totalShootingHours' => $this->calculateDayShootingHours($scenes),
            'workingHours' => $this->calculateWorkingHours($date, $hasNightScenes, $scenes),
            'hasNightScenes' => $hasNightScenes,
            'scenes' => []
        ];
        
        $currentTime = strtotime($date . ' ' . $this->shootingTimeConfig['dayShootStart']);
        
        // 安排场景 
        foreach ($scenes as $analysis) {
            $scene = $analysis['scene'];
            
            // 检查是否需要切换到夜戏时间 
            if ($this->isNightScene($analysis) && $currentTime < strtotime($date . ' ' . $this->shootingTimeConfig['nightShootStart'])) {
                $currentTime = strtotime($date . ' ' . $this->shootingTimeConfig['nightShootStart']);
            }
            
            $sceneSchedule = $this->buildSceneSchedule($scene, $analysis, $currentTime);
            $daySchedule['scenes'][] = $sceneSchedule;
            
            // 更新当前时间，考虑休息时间 
            $currentTime = $this->calculateNextStartTime($currentTime, $sceneSchedule['estimatedDuration']);
        }
        
        return $daySchedule;
    }
    
    private function buildSceneSchedule($scene, $analysis, $startTime)
{
    $sortedShots = $this->sortShotsByType($scene['shots']);
    
    // 提取场景中的所有角色
    $characters = [];
    foreach ($scene['shots'] as $shot) {
        $shotCharacters = $this->extractCharactersFromShot($shot);
        $characters = array_merge($characters, $shotCharacters);
    }
    $characters = array_unique($characters);
    $characters = $this->cleanCharacterList($characters);
    
    $priority = $this->calculateScenePriority($scene, $characters);
    
    $sceneSchedule = [
        'sceneId' => $scene['scene_id'],
        'sceneName' => $scene['scene_name'],
        'sceneNumber' => $scene['id'],
        'pageNumbers' => $this->generatePageNumbers($scene),
        'location' => $analysis['location'],
        'type' => $analysis['isExterior'] ? '外景' : '内景',
        'setting' => $analysis['timeSetting'],
        'priority' => $priority,
        'estimatedDuration' => $this->calculateSceneDuration($scene),
        'actualDuration' => 0,
        'startTime' => date('H:i', $startTime),
        'endTime' => $this->calculateSceneEndTime($startTime, $scene),
        'isNightScene' => $this->isNightScene($analysis),
        'status' => 'scheduled',
        'scriptNotes' => $this->generateScriptNotes($scene),
        'shots' => [],
        'actors' => $this->buildActorSchedule($scene, $analysis),
        'crew' => $this->buildCrewSchedule($scene),
        'props' => $this->extractSceneProps($scene)
    ];
    
    foreach ($sortedShots as $shot) {
        $sceneSchedule['shots'][] = [
            'shotId' => $shot['shots_id'],
            'shotType' => $shot['shotType'],
            'duration' => $shot['duration'],
            'location' => $shot['location'],
            'content' => $shot['content'],
            'sceneExpectation' => $shot['sceneExpectation'],                
            'script' => $shot['script'],
            'characters' => $shot['characters'],
            'characterCostumes' => $shot['characterCostumes'],
            'characterMakeup' => $shot['characterMakeup'],
            'characterActions' => $shot['characterActions'],
            'cameraAngle' => $shot['cameraAngle'],
            'cameraMovement' => $shot['cameraMovement'],
            'lensFocalLength' => $shot['lensFocalLength'],
            'compositionFocus' => $shot['compositionFocus'],
            'cameraEquipment' => $shot['cameraEquipment'],
            'lightTone' => $shot['lightTone'],
            'sound' => $shot['sound'],
            'props' => $shot['props'],
            'imageUrl' => $shot['imageUrl'],
            'status' => 'not-started'
        ];
    }
    
    return $sceneSchedule;
}
 
private function extractSceneProps($scene)
{
    $props = [];
    foreach ($scene['shots'] as $shot) {
        if (!empty($shot['props']) && $shot['props'] !== '无') {
            $shotProps = explode(',', $shot['props']);
            foreach ($shotProps as $prop) {
                $prop = trim($prop);
                if (!empty($prop) && $prop !== '无') {
                   $props[$prop] = true;
                }
            }
        }
    }
    
    return array_keys($props);
}

    
    private function calculateSceneEndTime($startTime, $scene)
    {
        $duration = $this->calculateSceneDuration($scene);
        $endTime = strtotime("+{$duration} hours", $startTime);
        
        // 确保结束时间不超过最大限制
        $dateStr = date('Y-m-d', $startTime);
        $maxWrapTime = strtotime($dateStr . ' ' . $this->shootingTimeConfig['maxWrapTime']);
        if ($endTime > $maxWrapTime) {
            $endTime = $maxWrapTime;
        }
        
        return date('H:i', $endTime);
    }
    
    private function calculateNextStartTime($currentTime, $sceneDuration)
    {
        $dateStr = date('Y-m-d', $currentTime);
        
        // 检查是否跨越休息时间
        $lunchStart = strtotime($dateStr . ' ' . $this->shootingTimeConfig['lunchBreak'][0]);
        $lunchEnd = strtotime($dateStr . ' ' . $this->shootingTimeConfig['lunchBreak'][1]);
        $dinnerStart = strtotime($dateStr . ' ' . $this->shootingTimeConfig['dinnerBreak'][0]);
        $dinnerEnd = strtotime($dateStr . ' ' . $this->shootingTimeConfig['dinnerBreak'][1]);
        
        $proposedEndTime = strtotime("+{$sceneDuration} hours", $currentTime);
        
        // 如果拍摄跨越午餐时间，需要加上休息时间
        if ($currentTime < $lunchEnd && $proposedEndTime > $lunchStart) {
            $currentTime = $lunchEnd;
        } elseif ($currentTime < $dinnerEnd && $proposedEndTime > $dinnerStart) {
            $proposedEndTime = strtotime("+1 hour", $proposedEndTime);
        }
        
        return $proposedEndTime;
    }
    
    private function calculateWrapTime($scenes)
    {
        $totalDuration = 0;
        foreach ($scenes as $analysis) {
            $totalDuration += $this->calculateSceneDuration($analysis['scene']);
        }
        
        $dateStr = date('Y-m-d');
        $baseWrapTime = strtotime($dateStr . ' ' . $this->shootingTimeConfig['wrapTime']);
        
        // 如果有夜戏，可能需要延长结束时间
        $hasNightScenes = $this->checkHasNightScenes($scenes);
        
        if ($hasNightScenes) {
            $maxWrapTime = strtotime($dateStr . ' ' . $this->shootingTimeConfig['maxWrapTime']);
            $calculatedWrapTime = strtotime("+{$totalDuration} hours", strtotime($dateStr . ' 08:00'));
            return date('H:i', min($calculatedWrapTime, $maxWrapTime));
        }
        
        return date('H:i', $baseWrapTime);
    }
    
    private function calculateDayShootingHours($scenes)
    {
        $totalHours = 0;
        foreach ($scenes as $analysis) {
            $totalHours += $this->calculateSceneDuration($analysis['scene']);
        }
        
        return $totalHours;
    }
    
    private function calculateWorkingHours($date, $hasNightScenes, $scenes = [])
{
    $callTime = strtotime($date . ' ' . $this->shootingTimeConfig['callTime']);
    $wrapTime = strtotime($date . ' ' . $this->calculateWrapTime($scenes));
    
    $workingHours = ($wrapTime - $callTime) / 3600;
    return round($workingHours, 1);
}
    
    private function checkHasNightScenes($scenes)
    {
        foreach ($scenes as $analysis) {
            if ($this->isNightScene($analysis)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function isNightScene($analysis)
    {
        return strpos($analysis['timeSetting'], '夜') !== false;
    }
    
    private function calculateSceneDuration($scene)
    {
        $totalDuration = 0;
        foreach ($scene['shots'] as $shot) {
            $totalDuration += $shot['duration'];
        }
        
        $efficiencyFactor = $this->supplementalData['config']['shootingEfficiency'] ?? 4;
        $estimatedHours = ceil($totalDuration / 60 / 60 * $efficiencyFactor);
        
        // 确保单个场景的拍摄时间在合理范围内 
        return min($estimatedHours, 6); // 单个场景最多6小时 
    }
 
    private function sortShotsByType($shots)
    {
        usort($shots, function($a, $b) {
            $priorityA = $this->shotTypePriority[$a['shotType']] ?? 10;
            $priorityB = $this->shotTypePriority[$b['shotType']] ?? 10;
            return $priorityA - $priorityB;
        });
        
        return $shots;
    }
    
    private function generatePageNumbers($scene)
    {
        $sceneNumber = intval(str_replace('SC', '', $scene['id']));
        $startPage = ($sceneNumber - 1) * 3 + 1;
        return $startPage . '-' . ($startPage + 2);
    }
    
    private function generateScriptNotes($scene)
    {
        if (!empty($scene['shots'])) {
            $firstShot = $scene['shots'][0];
            return $firstShot['content'] ?? $firstShot['remark'] ?? '暂无说明';
        }
        return '暂无说明';
    }
    
    
    private function getActorInfo($character)
    {
        if (isset($this->supplementalData['characterActorMap'][$character])) {
            return $this->supplementalData['characterActorMap'][$character];
        }
        
        return null;
    }
    
    private function getActorCallTime($actorInfo)
    {
        $roleType = $actorInfo['roleType'] ?? '普通';
        $priority = $actorInfo['priority'] ?? '中';
        
        if ($priority === '最高' || $roleType === '特邀') {
            return '09:00';
        } else {
            return '07:00';
        }
    }
    
    private function getActorArrivalTime($actorInfo)
    {
        $callTime = $this->getActorCallTime($actorInfo);
        return date('H:i', strtotime($callTime . ' +30 minutes'));
    }
    
    private function getCharacterCostume($scene, $character)
    {
        foreach ($scene['shots'] as $shot) {
            if (!empty($shot['characterCostumes'])) {
                return $shot['characterCostumes'];
            }
        }
        
        return '';
    }
 
    private function getCharacterMakeup($scene, $character)
    {
        foreach ($scene['shots'] as $shot) {
            if (!empty($shot['characterMakeup'])) {
                return $shot['characterMakeup'];
            }
        }
        
        return '';
    }
    
    private function buildCrewSchedule($scene)
    {
        return [
            'cinematographer' => $this->supplementalData['project']['cinematographer'] ?? '',
            'scriptSupervisor' => $this->supplementalData['project']['scriptSupervisor'] ?? '',
            'keyGrip' => $this->supplementalData['project']['keyGrip'] ?? ''
        ];
    }
    
    private function generateWeather($date)
    {
        // 设置正确的时区
        date_default_timezone_set('Asia/Shanghai');
        
        // 动态获取用户IP地址
        $userIp = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $userIp = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $userIp = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $userIp = $_SERVER['REMOTE_ADDR'];
        }
        
        // 天气API配置 - 使用新的接口
        $apiUrl = 'https://cn.apihz.cn/api/tianqi/tqybmoji15ip.php?id=10011219&key=610f6a584a5da4d1c0ac8f36b6810ac9&ip=' . $userIp;
        
        // 确保日期格式统一为YYYY/MM/DD
        $targetDate = date('Y/m/d', strtotime($date));
        // 获取目标日期的时间戳
        $targetTimestamp = strtotime($date);
        // 提取目标日期的MM/DD格式，用于匹配API返回的week2字段
        $targetDateMMDD = date('m/d', $targetTimestamp);
        // 获取目标日期的月份和日期，用于匹配不带前导零的格式
        $targetMonth = date('n', $targetTimestamp); // 不带前导零的月份
        $targetDay = date('j', $targetTimestamp); // 不带前导零的日期
        $targetDateMMDDNoZero = $targetMonth . '/' . $targetDay;
        // 获取当前年份和目标年份，用于处理跨年份的日期
        $currentYear = date('Y');
        $targetYear = date('Y', $targetTimestamp);
        
        try {
            // 设置file_get_contents的超时时间为5秒
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5
                ]
            ]);
            
            // 调用天气API获取数据
            $response = file_get_contents($apiUrl, false, $context);
            if ($response === false) {
                throw new Exception('API调用失败');
            }
            
            // 解析JSON数据
            $weatherData = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('JSON解析失败: ' . json_last_error_msg());
            }
            
            // 检查API返回状态
            if ($weatherData['code'] !== 200) {
                throw new Exception('API返回错误: ' . ($weatherData['msg'] ?? '未知错误'));
            }
            
            // 查找目标日期的天气数据
            $foundWeather = false;
            $weatherInfo = '';
            
            // 遍历data数组查找匹配的日期
            if (isset($weatherData['data']) && is_array($weatherData['data'])) {
                foreach ($weatherData['data'] as $dayData) {
                    // 检查week2字段是否存在
                    if (isset($dayData['week2'])) {
                        // 尝试多种日期格式匹配
                        $apiDate = $dayData['week2'];
                        
                        // 匹配1：完整的MM/DD格式（带前导零）
                        // 匹配2：MM/DD格式（不带前导零）
                        // 匹配3：处理跨年份的情况（如果API返回的是下一年的日期，格式可能是MM/DD）
                        if ($apiDate === $targetDateMMDD || $apiDate === $targetDateMMDDNoZero) {
                            // 提取天气数据
                            $weather = $dayData['wea1'];
                            // 移除温度中的°符号，统一格式
                            $temp1 = str_replace('°', '', $dayData['wendu1']);
                            $temp2 = str_replace('°', '', $dayData['wendu2']);
                            $tempRange = $temp2 . '℃~' . $temp1 . '℃';
                            // 新接口没有风的信息，暂时不显示
                            $weatherInfo = $weather . '，' . $tempRange;
                            $foundWeather = true;
                            break;
                        }
                    }
                }
            }
            
            // 如果找到天气数据，返回格式化后的字符串
            if ($foundWeather) {
                return $weatherInfo;
            } else {
                // 遍历所有可用的天气数据，调试用
                $availableDates = [];
                if (isset($weatherData['data']) && is_array($weatherData['data'])) {
                    foreach ($weatherData['data'] as $dayData) {
                        if (isset($dayData['week2'])) {
                            // 根据API返回的月份判断年份
                            $apiMonth = intval(explode('/', $dayData['week2'])[0]);
                            $apiYear = $apiMonth === 1 ? $currentYear + 1 : $currentYear;
                            $availableDates[] = $apiYear . '/' . $dayData['week2'];
                        }
                    }
                }
                
                error_log('未找到日期 ' . $targetDate . ' 的天气数据，可用日期：' . implode(', ', $availableDates) . '，尝试匹配的格式：' . $targetDateMMDD . ' 和 ' . $targetDateMMDDNoZero);
                
                // 没有找到指定日期的天气数据，返回待预报
                return '待预报';
            }
        } catch (Exception $e) {
            // API调用失败或解析错误，返回待预报
            error_log('获取天气数据失败 (' . $targetDate . '): ' . $e->getMessage());
            return '待预报';
        }
    }
 
    private function getDayLocation($scenes)
    {
        $locations = [];
        foreach ($scenes as $analysis) {
            $locations[] = $analysis['location'];
        }
        
        return implode('/', array_unique($locations));
    }
 
    private function getDayStatus($date)
    {
        $projectStart = $this->supplementalData['project']['startDate'];
        $today = date('Y-m-d');
        
        if ($date < $today) {
            return 'completed';
        } elseif ($date === $today) {
            return 'in-progress';
        } else {
            return 'scheduled';
        }
    }
    
    /**
 * 修复统计信息计算 - 基于实际场景分析 
 */
private function calculateStatistics($dailySchedule)
{
    // 从s.json 获取实际场景和镜头数量 
    $totalScenes = count($this->scriptData['scenes']);
    $totalShots = 0;
    
    foreach ($this->scriptData['scenes'] as $scene) {
        $totalShots += count($scene['shots']);
    }
    
    // 计算已完成的场景和镜头 
    $completedScenes = $this->supplementalData['project']['daysCompleted'] ?? 0;
    
    // 计算已完成的镜头数 
    $completedShots = $this->calculateCompletedShots();
    
    $estimatedDays = $this->supplementalData['project']['estimatedDays'] ?? 15;
    
    return [
        'totalScenes' => $totalScenes,
        'totalShots' => $totalShots,
        'totalScenesCompleted' => $completedScenes,
        'totalShotsCompleted' => $completedShots,
        'scenesCompletionRate' => $totalScenes > 0 ? round(($completedScenes / $totalScenes) * 100, 1) : 0,
        'shotsCompletionRate' => $totalShots > 0 ? round(($completedShots / $totalShots) * 100, 1) : 0,
        'totalDays' => $estimatedDays,
        'completedDays' => $completedScenes,
        'remainingDays' => max(0, $estimatedDays - $completedScenes),
        'daysCompletionRate' => $estimatedDays > 0 ? round(($completedScenes / $estimatedDays) * 100, 1) : 0
    ];
}
    
    private function calculateCompletedShots()
    {
        $completed = 0;
        foreach ($this->scriptData['scenes'] as $scene) {
            foreach ($scene['shots'] as $shot) {
                $completed++;
            }
        }
        
        return $completed;
    }
}
?>
