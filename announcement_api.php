<?php

class AnnouncementGenerator
{
    private $sourceData;
    private $templateData;
    private $taskId;
    private $characterIdMap;
    private $outputDir;
    
    public function __construct($sourceJson, $templateJson, $taskId, $outputDir = './results')
    {
        // 先清理 JSON 字符串中的 UTF-8 问题字符
        $sourceJson = $this->cleanJsonString($sourceJson);
        $templateJson = $this->cleanJsonString($templateJson);
        
        $this->sourceData = json_decode($sourceJson, true);
        $this->templateData = json_decode($templateJson, true);
        $this->taskId = $taskId;
        $this->characterIdMap = [];
        $this->outputDir = $outputDir;
        
        // 确保输出目录存在
        if (!is_dir($this->outputDir)) {
            mkdir($this->outputDir, 0755, true);
        }
        
        if ($this->sourceData === null) {
            throw new Exception("源JSON数据解析失败: " . json_last_error_msg());
        }
        if ($this->templateData === null) {
            throw new Exception("模板JSON数据解析失败: " . json_last_error_msg());
        }
        
        // 递归清理数据中的 UTF-8 问题字符
        $this->sourceData = $this->cleanArray($this->sourceData);
        $this->templateData = $this->cleanArray($this->templateData);
    }
    
    /**
     * 清理 JSON 字符串中的 UTF-8 问题字符
     */
    private function cleanJsonString($string)
    {
        // 移除 BOM 头
        $string = str_replace("\xEF\xBB\xBF", '', $string);
        
        // 清理无效的 UTF-8 字符
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        
        // 移除其他可能的问题字符
        $string = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]+/u', '', $string);
        
        return $string;
    }
    
    /**
     * 递归清理数组中的 UTF-8 问题字符
     */
    private function cleanArray($array)
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->cleanArray($value);
            } elseif (is_string($value)) {
                $array[$key] = $this->cleanString($value);
            }
        }
        return $array;
    }
    
    /**
     * 清理字符串中的 UTF-8 问题字符
     */
    private function cleanString($string)
    {
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // 移除控制字符（除了换行符和制表符）
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);
        
        return $string;
    }
    
    public function generateAnnouncement()
    {
        //echo "开始生成公告数据...\n";
        
        $announcement = [];
        
        try {
            // 1. 生成项目基础信息
            //echo "生成项目信息...\n";
            $announcement['project'] = $this->buildProjectInfo();
            //echo "项目信息生成完成\n";
            
            // 2. 生成每日拍摄公告
            //echo "生成拍摄日程...\n";
            $announcement['shootingDays'] = [];
            
            foreach ($this->sourceData['schedule'] as $date => $daySchedule) {
                //echo "处理日期: " . $date . "\n";
                $announcement['shootingDays'][$date] = $this->generateDailyAnnouncement($date, $daySchedule);
            }
            //echo "拍摄日程生成完成，共 " . count($announcement['shootingDays']) . " 天\n";
            
            // 3. 生成统计信息
            //echo "生成统计信息...\n";
            $announcement['statistics'] = $this->buildStatistics();
            //echo "统计信息生成完成\n";
            
            // 4. 版本信息
            $announcement['version'] = "1.2";
            $announcement['lastUpdated'] = date('Y-m-d\TH:i:s\Z');
            $announcement['totalDays'] = count($this->sourceData['schedule']);
            $announcement['generatedFor'] = $this->taskId;
            
            // 在返回前清理整个公告数据
            $announcement = $this->cleanArray($announcement);
            
            $dataSize = strlen(json_encode($announcement, JSON_UNESCAPED_UNICODE));
            //echo "公告数据生成完成，总大小: " . $dataSize . " 字节\n";
            
        } catch (Exception $e) {
            //echo "生成过程中出错: " . $e->getMessage() . "\n";
            throw $e;
        }
        
        return $announcement;
    }
    
    private function buildProjectInfo()
    {
        //echo "构建项目信息...\n";
        
        if (!isset($this->templateData['project'])) {
            throw new Exception("模板数据中缺少project字段");
        }
        if (!isset($this->sourceData['project'])) {
            throw new Exception("源数据中缺少project字段");
        }
        
        $project = $this->templateData['project'];
        $sourceProject = $this->sourceData['project'];
        
        // 从源数据填充所有项目信息
        $project['name'] = $sourceProject['name'] ?? '未命名项目';
        $project['startDate'] = $sourceProject['startDate'] ?? '';
        $project['endDate'] = $sourceProject['endDate'] ?? '';
        $project['totalScenes'] = $sourceProject['totalScenes'] ?? 0;
        $project['totalShots'] = $sourceProject['totalShots'] ?? 0;
        $project['estimatedDays'] = $sourceProject['estimatedDays'] ?? 0;
        $project['actualDays'] = $sourceProject['actualDays'] ?? 0;
        $project['daysCompleted'] = $sourceProject['daysCompleted'] ?? 0;
        $project['completionRate'] = $sourceProject['completionRate'] ?? 0;
        
        // 从源数据填充人员信息
        $project['director'] = $sourceProject['director'] ?? '待定';
        $project['producer'] = $sourceProject['producer'] ?? '待定';
        $project['cinematographer'] = $sourceProject['cinematographer'] ?? '待定';
        $project['scriptSupervisor'] = $sourceProject['scriptSupervisor'] ?? '待定';
        $project['productionDesigner'] = $sourceProject['productionDesigner'] ?? '待定';
        $project['costumeDesigner'] = $sourceProject['costumeDesigner'] ?? '待定';
        $project['castingDirector'] = $sourceProject['castingDirector'] ?? '待定';
        $project['assistantDirector'] = $sourceProject['assistantDirector'] ?? '待定';
        $project['keyGrip'] = $sourceProject['keyGrip'] ?? '待定';
        $project['bestBoy'] = $sourceProject['bestBoy'] ?? '待定';
        
        //echo "项目信息构建完成: " . $project['name'] . "\n";
        
        return $project;
    }
    
    private function generateDailyAnnouncement($date, $daySchedule)
    {
        //echo "生成每日公告: " . $date . "\n";
        
        $daily = [];
        
        // 1. shootDay 基本信息
        $daily['shootDay'] = [
            'date' => $date,
            'dayOfWeek' => $daySchedule['dayOfWeek'] ?? '未知',
            'callTime' => $daySchedule['callTime'] ?? '09:00',
            'shootTime' => $daySchedule['shootTime'] ?? '10:00',
            'wrapTime' => $daySchedule['wrapTime'] ?? '18:00',
            'estimatedDuration' => $daySchedule['totalShootingHours'] ?? 8,
            'workingHours' => $daySchedule['workingHours'] ?? '09:00-18:00',
            'weather' => $daySchedule['weather'] ?? '晴',
            'location' => $daySchedule['location'] ?? '未知地点',
            'status' => $this->mapStatus($daySchedule['status'] ?? 'scheduled'),
            'hasNightScenes' => $daySchedule['hasNightScenes'] ?? false,
            'breaks' => $daySchedule['breaks'] ?? []
        ];
        
        // 2. scenes 部分
        $daily['scenes'] = $this->buildScenesSection($daySchedule['scenes'] ?? []);
        
        // 3. crew 部分
        $daily['crew'] = $this->buildDailyCrewSection($date);
        
        // 4. equipment 部分
        $daily['equipment'] = $this->buildDailyEquipmentSection($date, $daySchedule['scenes'] ?? []);
        
        // 5. 运输安排
        $daily['transportation'] = $this->buildDailyTransportationSection($date);
        
        // 6. 餐饮安排
        $daily['meals'] = $this->buildDailyMealsSection($date);
        
        // 7. 安全信息
        $daily['safety'] = $this->buildSafetySection();
        
        // 8. 每日备注
        $daily['notes'] = $this->buildDailyNotesSection($date, $daySchedule);
        
        // 9. 当日统计
        $daily['dailyStatistics'] = $this->buildDailyStatistics($daySchedule['scenes'] ?? []);
        
        //echo "每日公告生成完成: " . $date . "，包含 " . count($daily['scenes']) . " 个场景\n";
        
        return $daily;
    }
    
    private function buildScenesSection($scenes)
    {
        //echo "构建场景部分，共 " . count($scenes) . " 个场景\n";
        
        $processedScenes = [];
        
        foreach ($scenes as $index => $scene) {
            //echo "处理场景 " . ($index + 1) . ": " . ($scene['sceneName'] ?? '未知场景') . "\n";
            
            // 清理场景数据
            $scene = $this->cleanArray($scene);
            
            $processedScene = [
                'sceneId' => $scene['sceneId'] ?? 'scene_' . ($index + 1),
                'sceneName' => $scene['sceneName'] ?? '未知场景',
                'sceneNumber' => $scene['sceneNumber'] ?? $index + 1,
                'pageNumbers' => $scene['pageNumbers'] ?? '1',
                'location' => $scene['location'] ?? '未知地点',
                'setting' => $scene['setting'] ?? '日/内',
                'type' => $scene['type'] ?? '常规',
                'intExt' => $this->determineIntExt($scene['type'] ?? '常规'),
                'dayNight' => $this->determineDayNight($scene['setting'] ?? '日/内'),
                'estimatedDuration' => $scene['estimatedDuration'] ?? 1,
                'actualDuration' => $scene['actualDuration'] ?? 0,
                'startTime' => $scene['startTime'] ?? '10:00',
                'endTime' => $scene['endTime'] ?? '11:00',
                'isNightScene' => $scene['isNightScene'] ?? false,
                'estimatedTime' => ($scene['startTime'] ?? '10:00') . '-' . ($scene['endTime'] ?? '11:00'),
                'actualTime' => '',
                'status' => $this->mapStatus($scene['status'] ?? 'scheduled'),
                'priority' => $this->mapPriority($scene['priority'] ?? '中'),
                'scriptNotes' => $scene['scriptNotes'] ?? '',
                'shots' => $this->buildShotsSection($scene['shots'] ?? [], $scene['sceneId'] ?? 'scene_' . ($index + 1)),
                'cast' => $this->buildCastSection($scene['actors'] ?? [], $scene['sceneId'] ?? 'scene_' . ($index + 1)),
                'stunts' => $scene['stunts'] ?? [],
                'specialEquipment' => $this->extractSpecialEquipmentFromScene($scene),
                'setDressing' => $this->extractSetDressingFromScene($scene),
                'sound' => $this->buildSoundSection($scene),
                'crew' => $scene['crew'] ?? [],
                'props' => $this->extractPropsFromScene($scene) // 直接从场景数据中提取道具
            ];
            
            $processedScenes[] = $processedScene;
        }
        
        return $processedScenes;
    }
    
    private function buildShotsSection($shots, $sceneId)
    {
        $processedShots = [];
        $shotId = 1;
        
        foreach ($shots as $shot) {
            // 清理镜头数据
            $shot = $this->cleanArray($shot);
            
            $processedShot = [
                'shotId' => $shotId++,
                'originalShotId' => $shot['shotId'] ?? 'shot_' . $shotId,
                'sceneExpectation' => $shot['sceneExpectation'] ?? '无',
                'script' => $shot['script'] ?? '无',
                'location' => $shot['location'] ?? '未知地点',
                'characterCostumes' => $shot['characterCostumes'] ?? [],
                'characterMakeup' => $shot['characterMakeup'] ?? [],
                'characterActions' => $shot['characterActions'] ?? [],
                'shotType' => $shot['shotType'] ?? '中景',
                'lens' => $shot['lensFocalLength'] ?? '35mm',
                'angle' =>$shot['cameraAngle'] ?? '平视',
                'movement' => $shot['cameraMovement'] ?? '静态',
                'description' => $shot['content'] ?? '无',
                'duration' => $shot['duration'] ?? 30,
                'characters' => $shot['characters'] ?? ['待定'],
                'sound' => $shot['sound'] ?? '无',
                'props' => $shot['props'] ?? [], // 直接从镜头数据中获取道具
                'equipment' => $shot['cameraEquipment'] ?? [],
                'focus' => $shot['compositionFocus'] ?? '无',
                'lighting' => $shot['lightTone'] ?? '无',
                'imageUrl' => $shot['imageUrl'] ?? '',
                'status' => $this->mapStatus($shot['status'] ?? 'scheduled'),
                'sceneId' => $sceneId
            ];
            
            $processedShots[] = $processedShot;
        }
        
        return $processedShots;
    }
    
    private function buildCastSection($actors, $sceneId)
    {
        $processedCast = [];
        
        foreach ($actors as $index => $actor) {
            // 清理演员数据
            $actor = $this->cleanArray($actor);
            
            $characterName = $actor['character'] ?? '角色' . ($index + 1);
            
            if ($characterName && $characterName !== '无') {
                $characterKey = $this->getStandardCharacterId($characterName);
                
                $processedActor = [
                    'character' => $characterName,
                    'actor' => $actor['actor'] ?? '待定',
                    'roleId' => $characterKey,
                    'actorId' => 'act' . sprintf('%03d', count($processedCast) + 1),
                    'callTime' => $actor['callTime'] ?? '08:00',
                    'arrivalTime' => $actor['arrivalTime'] ?? '08:30',
                    'costume' => $this->parseCostumeDescription($actor['costume'] ?? '日常服装', $characterName),
                    'makeup' => $this->parseMakeupDescription($actor['makeup'] ?? '自然妆容', $characterName),
                    'roleType' => $actor['roleType'] ?? '配角',
                    'priority' => $this->mapPriority($actor['priority'] ?? '中'),
                    'isConfirmed' => $actor['isConfirmed'] ?? false,
                    'isAvailable' => $actor['isAvailable'] ?? true,
                    'notes' => $actor['notes'] ?? '请准时到达化妆间',
                    'sceneId' => $sceneId
                ];
                
                $processedCast[] = $processedActor;
            }
        }
        
        return $processedCast;
    }
    
    /**
     * 从场景数据中提取道具
     */
    private function extractPropsFromScene($scene)
    {
        $props = [];
        
        // 1. 直接从场景的props字段获取
        if (isset($scene['props']) && is_array($scene['props'])) {
            $props = array_merge($props, $scene['props']);
        }
        
        // 2. 从剧本备注中提取道具信息
        if (isset($scene['scriptNotes']) && !empty($scene['scriptNotes'])) {
            $extractedProps = $this->extractPropsFromNotes($scene['scriptNotes']);
            $props = array_merge($props, $extractedProps);
        }
        
        // 3. 从场景名称和描述中提取
        if (isset($scene['sceneName']) && !empty($scene['sceneName'])) {
            $extractedProps = $this->extractPropsFromText($scene['sceneName']);
            $props = array_merge($props, $extractedProps);
        }
        
        // 去重并过滤空值
        $props = array_filter(array_unique($props));
        
        return array_values($props);
    }
    
    /**
     * 从剧本备注中提取道具
     */
    private function extractPropsFromNotes($scriptNotes)
    {
        $props = [];
        
        // 常见的道具关键词
        $propKeywords = [
            '道具', '物品', '工具', '设备', '武器', '服装', '配饰', '书籍', '文件',
            '手机', '电脑', '钥匙', '钱包', '背包', '箱子', '盒子', '瓶子', '杯子',
            '餐具', '家具', '装饰', '画', '照片', '信件', '日记', '地图', '珠宝',
            '钟表', '灯具', '镜子', '伞', '手套', '帽子', '围巾', '眼镜', '手表'
        ];
        
        foreach ($propKeywords as $keyword) {
            if (strpos($scriptNotes, $keyword) !== false) {
                // 简单的提取逻辑，可以根据需要增强
                $pattern = '/[' . $keyword . '][^，。；！？]*?[道具物品工具设备]/u';
                if (preg_match_all($pattern, $scriptNotes, $matches)) {
                    $props = array_merge($props, $matches[0]);
                }
            }
        }
        
        return $props;
    }
    
    /**
     * 从文本中提取道具
     */
    private function extractPropsFromText($text)
    {
        $props = [];
        
        // 基于场景名称的简单提取
        $locationBasedProps = [
            '考古' => ['考古工具', '刷子', '铲子', '测量仪器'],
            '博物馆' => ['展品', '文物', '展示柜', '标签'],
            '办公室' => ['办公桌', '电脑', '文件', '电话'],
            '实验室' => ['实验设备', '试管', '显微镜', '培养皿'],
            '医院' => ['医疗设备', '病床', '药品', '监护仪'],
            '公寓' => ['家具', '家电', '日常用品'],
            '战场' => ['武器', '盔甲', '战旗', '装备']
        ];
        
        foreach ($locationBasedProps as $location => $locationProps) {
            if (strpos($text, $location) !== false) {
                $props = array_merge($props, $locationProps);
            }
        }
        
        return $props;
    }
    
    /**
     * 从场景数据中提取特殊设备
     */
    private function extractSpecialEquipmentFromScene($scene)
    {
        $equipment = [];
        
        // 1. 直接从场景数据中获取
        if (isset($scene['specialEquipment']) && is_array($scene['specialEquipment'])) {
            $equipment = array_merge($equipment, $scene['specialEquipment']);
        }
        
        // 2. 从道具中提取设备类道具
        $props = $this->extractPropsFromScene($scene);
        $equipmentKeywords = ['摄像机', '灯光', '三脚架', '稳定器', '轨道', '摇臂', '滑轨', '录音设备'];
        
        foreach ($props as $prop) {
            foreach ($equipmentKeywords as $keyword) {
                if (strpos($prop, $keyword) !== false) {
                    $equipment[] = $prop;
                    break;
                }
            }
        }
        
        // 去重并过滤空值
        $equipment = array_filter(array_unique($equipment));
        
        return array_values($equipment);
    }
    
    /**
     * 从场景数据中提取布景道具
     */
    private function extractSetDressingFromScene($scene)
    {
        $setDressing = [];
        
        // 1. 直接从场景数据中获取
        if (isset($scene['setDressing']) && is_array($scene['setDressing'])) {
            $setDressing = array_merge($setDressing, $scene['setDressing']);
        }
        
        // 2. 从道具中提取布景类道具
        $props = $this->extractPropsFromScene($scene);
        $dressingKeywords = ['家具', '装饰', '布景', '壁画', '窗帘', '地毯', '灯具', '花瓶'];
        
        foreach ($props as $prop) {
            foreach ($dressingKeywords as $keyword) {
                if (strpos($prop, $keyword) !== false) {
                    $setDressing[] = $prop;
                    break;
                }
            }
        }
        
        // 去重并过滤空值
        $setDressing = array_filter(array_unique($setDressing));
        
        return array_values($setDressing);
    }
    
    // ... 其他方法保持不变 ...
    
    private function getStandardCharacterId($characterName)
    {
        // 完全通用的角色ID生成，使用哈希确保唯一性
        if (!isset($this->characterIdMap[$characterName])) {
            $hash = substr(md5(trim($characterName)), 0, 10);
            $this->characterIdMap[$characterName] = 'char_' . $hash;
        }
        
        return $this->characterIdMap[$characterName];
    }
    
    private function buildDailyCrewSection($date)
    {
        // 直接从模板获取crew信息
        return $this->templateData['crew'];
    }
    
    private function buildDailyEquipmentSection($date, $scenes)
    {
        // 直接从模板获取设备信息
        return $this->templateData['equipment'];
    }
    
    private function buildDailyTransportationSection($date)
    {
        // 直接从模板获取运输信息
        return $this->templateData['transportation'];
    }
    
    private function buildDailyMealsSection($date)
    {
        // 直接从模板获取餐饮信息
        return $this->templateData['meals'];
    }
    
    private function buildSafetySection()
    {
        // 直接从模板获取安全信息
        return $this->templateData['safety'];
    }
    
    private function buildDailyNotesSection($date, $daySchedule)
    {
        $notes = $this->templateData['notes'];
        
        // 基于当天的实际数据生成备注
        $sceneIds = array_column($daySchedule['scenes'], 'sceneId');
        $sceneNames = array_column($daySchedule['scenes'], 'sceneName');
        
        $productionNotes = [
            "所有演员请提前1小时到达化妆间",
            "今日拍摄场次：" . implode('、', $sceneIds),
            "重点关注场景：" . implode('、', array_slice($sceneNames, 0, 3)),
            "天气情况：" . $daySchedule['weather'] . "，请做好相应准备"
        ];
        
        if ($daySchedule['hasNightScenes']) {
            $productionNotes[] = "今日有夜戏拍摄，请相关人员做好准备";
        }
        
        if (strpos($daySchedule['location'], '外景') !== false) {
            $productionNotes[] = "外景拍摄，注意天气变化和现场安全";
        }
        
        $notes['productionNotes'] = $productionNotes;
        
        return $notes;
    }
    
    private function buildStatistics()
    {
        // 直接从源数据获取统计信息
        return $this->sourceData['statistics'];
    }
    
    private function buildDailyStatistics($scenes)
    {
        $totalScenes = count($scenes);
        $totalShots = 0;
        $completedScenes = 0;
        $completedShots = 0;
        
        foreach ($scenes as $scene) {
            $totalShots += count($scene['shots'] ?? []);
            if (($scene['status'] ?? '') === 'completed') {
                $completedScenes++;
            }
            
            foreach ($scene['shots'] ?? [] as $shot) {
                if (($shot['status'] ?? '') === 'completed') {
                    $completedShots++;
                }
            }
        }
        
        return [
            'totalScenes' => $totalScenes,
            'totalShots' => $totalShots,
            'completedScenes' => $completedScenes,
            'completedShots' => $completedShots,
            'scenesCompletionRate' => $totalScenes > 0 ? round(($completedScenes / $totalScenes) * 100, 1) : 0,
            'shotsCompletionRate' => $totalShots > 0 ? round(($completedShots / $totalShots) * 100, 1) : 0
        ];
    }
    
    // 通用的辅助方法
    private function mapStatus($status)
    {
        $statusMap = [
            'completed' => 'completed',
            'scheduled' => 'scheduled',
            'not-started' => 'not-started'
        ];
        
        return $statusMap[$status] ?? 'scheduled';
    }
    
    private function mapPriority($priority)
    {
        $priorityMap = [
            '最低' => 'low',
            '低' => 'low',
            '中' => 'medium',
            '高' => 'high',
            '最高' => 'high'
        ];
        
        return $priorityMap[$priority] ?? 'medium';
    }
    
    private function determineIntExt($type)
    {
        return strpos($type, '外景') !== false ? '外' : '内';
    }
    
    private function determineDayNight($setting)
    {
        return strpos($setting, '夜戏') !== false ? '夜' : '日';
    }
    
    private function determineLens($shotType)
    {
        $lensMap = [
            '全景' => '24mm',
            '中景' => '35mm',
            '中近景' => '50mm',
            '特写' => '85mm',
            '大远景' => '14mm',
            '中全景' => '35mm'
        ];
        
        return $lensMap[$shotType] ?? '35mm';
    }
    
    private function determineAngle($shotType)
    {
        return '平视'; // 通用默认值
    }
    
    private function determineMovement($shotType)
    {
        $movementMap = [
            '全景' => '固定',
            '中景' => '摇镜',
            '中近景' => '推镜',
            '特写' => '固定',
            '大远景' => '拉镜'
        ];
        
        return $movementMap[$shotType] ?? '固定';
    }
    
    private function generateShotDescription($shotType, $sceneId)
    {
        $descriptions = [
            '全景' => '场景整体展示',
            '中景' => '人物动作表现',
            '中近景' => '人物表情特写',
            '特写' => '细节特征展示',
            '大远景' => '环境氛围营造'
        ];
        
        return $descriptions[$shotType] ?? $shotType . '镜头拍摄';
    }
    
    private function determineEquipment($shotType)
    {
        $baseEquipment = [
            'camera' => 'ARRI ALEXA Mini LF',
            'lens' => $this->determineLens($shotType),
            'support' => '三脚架'
        ];
        
        if ($shotType === '特写' || $shotType === '中近景') {
            $baseEquipment['support'] = '手持稳定器';
        }
        
        return $baseEquipment;
    }
    
    private function determineFocus($shotType)
    {
        $focusMap = [
            '全景' => '对称构图，焦点在场景整体',
            '中景' => '三分法，焦点在人物动作',
            '中近景' => '三分法，焦点在人物表情',
            '特写' => '中心构图，焦点在细节特征',
            '大远景' => '广角构图，焦点在环境氛围'
        ];
        
        return $focusMap[$shotType] ?? '三分法构图';
    }
    
    private function determineLighting($shotType)
    {
        $lightingMap = [
            '全景' => '自然光，高调，整体照明',
            '中景' => '侧光，自然，人物轮廓',
            '中近景' => '侧光，柔和，人物表情',
            '特写' => '逆光，细腻，细节特征',
            '大远景' => '自然光，环境光，氛围营造'
        ];
        
        return $lightingMap[$shotType] ?? '侧光，自然';
    }
    
    private function buildSoundSection($scene)
    {
        $requirements = ['环境音'];
        
        if (strpos($scene['scriptNotes'] ?? '', '对话') !== false) {
            $requirements[] = '对话声';
        }
        
        return [
            'requirements' => $requirements,
            'source' => strpos($scene['type'] ?? '', '外景') !== false ? '现场收音+后期补录' : '现场收音',
            'special' => '注意收音环境质量，避免背景噪音'
        ];
    }
    
    private function parseCostumeDescription($costume, $character)
    {
        if (strpos($costume, $character) !== false) {
            $pattern = '/' . $character . '：([^；]+)/';
            if (preg_match($pattern, $costume, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $costume !== '无' ? $costume : '日常服装';
    }
    
    private function parseMakeupDescription($makeup, $character)
    {
        if (strpos($makeup, $character) !== false) {
            $pattern = '/' . $character . '：([^；]+)/';
            if (preg_match($pattern, $makeup, $matches)) {
                return trim($matches[1]);
            }
        }
        
        return $makeup !== '无' ? $makeup : '自然妆容';
    }
    
    public function saveToFile($announcementData)
    {
        $filename = $this->outputDir . '/' . $this->taskId . "_announcement.json";
        
        //echo "准备保存文件: " . $filename . "\n";
        
        if (empty($announcementData)) {
            throw new Exception("要保存的数据为空");
        }
        
        // 最终清理数据
        $announcementData = $this->cleanArray($announcementData);
        
        // 测试 JSON 编码
        $testJson = json_encode($announcementData, JSON_UNESCAPED_UNICODE);
        if ($testJson === false) {
            //echo "JSON 编码测试失败: " . json_last_error_msg() . "\n";
            throw new Exception("JSON编码失败: " . json_last_error_msg());
        }
        
        $dataSize = strlen($testJson);
        //echo "数据大小: " . $dataSize . " 字节\n";
        
        $json = json_encode($announcementData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($json === false) {
            throw new Exception("JSON编码失败: " . json_last_error_msg());
        }
        
        $result = file_put_contents($filename, $json);
        
        if ($result === false) {
            throw new Exception("无法写入文件: " . $filename);
        }
        
        //echo "文件保存成功，大小: " . $result . " 字节\n";
        
        return $filename;
    }
    
    /**
     * 尝试找出导致 JSON 编码失败的问题数据
     */
    private function findProblematicData($data, $path = 'root')
    {
        foreach ($data as $key => $value) {
            $currentPath = $path . '.' . $key;
            
            if (is_array($value)) {
                $this->findProblematicData($value, $currentPath);
            } elseif (is_string($value)) {
                if (!mb_check_encoding($value, 'UTF-8')) {
                    //echo "发现编码问题在: " . $currentPath . "\n";
                    //echo "问题内容: " . substr($value, 0, 100) . "\n";
                }
            }
        }
    }
}

function generateAnnouncementFile($sourceJson, $templateJson, $taskId)
{
    try {
        //echo "初始化公告生成器...\n";
        $generator = new AnnouncementGenerator($sourceJson, $templateJson, $taskId, './results');
        
        //echo "开始生成公告数据...\n";
        $announcementData = $generator->generateAnnouncement();
        
        //echo "准备保存文件...\n";
        $filename = $generator->saveToFile($announcementData);
        
        if ($filename) {
            //echo "成功生成多日期公告文件: " . $filename . "\n";
            
            // 安全地获取文件大小
            if (file_exists($filename)) {
                $fileSize = filesize($filename);
                //echo "文件大小: " . $fileSize . " 字节\n";
            } else {
                //echo "文件大小: 无法获取文件大小（文件不存在）\n";
            }
            
            //echo "包含 " . count($announcementData['shootingDays']) . " 天的拍摄数据\n";
            
            // 安全地获取真实路径
            $realPath = realpath($filename);
            if ($realPath) {
                //echo "文件已保存为: " . $realPath . "\n";
            } else {
               // echo "文件已保存为: " . $filename . "（相对路径）\n";
            }
            
            return $filename;
        } else {
            //echo "生成文件失败\n";
            return false;
        }
    } catch (Exception $e) {
        // echo "错误: " . $e->getMessage() . "\n";
        return false;
    }
}

// 主执行程序
if (isset($_GET["task_id"])) {
    $taskId = $_GET["task_id"];
    //echo "任务ID: " . $taskId . "\n";
    
    // 读取源JSON文件
    $sourceJson = file_get_contents('results/'.$taskId.'_schedule.json');
    if (!$sourceJson) {
        die("无法读取源JSON文件 results/".$taskId."_schedule.json\n");
    }
    
    // 读取模板JSON文件
    $templateJson = file_get_contents('results/'.$taskId.'_tmp.json');
    if (!$templateJson) {
        die("无法读取模板JSON文件 results/{task_id}_tmp.json\n");
    }
    
    //echo "源文件大小: " . strlen($sourceJson) . " 字节\n";
    //echo "模板文件大小: " . strlen($templateJson) . " 字节\n";
    
    $filename = generateAnnouncementFile($sourceJson, $templateJson, $taskId);
    if ($filename) {
        // echo "文件生成成功！\n";
        
        // 验证生成的文件内容
        if (file_exists($filename)) {
            $fileContent = file_get_contents($filename);
            $fileData = json_decode($fileContent, true);
            if ($fileData) {
                // echo "文件验证成功！\n";
                // echo "项目名称: " . ($fileData['project']['name'] ?? '未知') . "\n";
                // echo "拍摄天数: " . ($fileData['totalDays'] ?? 0) . "\n";
                // echo "版本: " . ($fileData['version'] ?? '未知') . "\n";
                
                // 检查道具数据
                $totalProps = 0;
                foreach ($fileData['shootingDays'] as $date => $dayData) {
                    foreach ($dayData['scenes'] as $scene) {
                        $totalProps += count($scene['props'] ?? []);
                    }
                }
                //echo "总道具数量: " . $totalProps . "\n";
            } else {
                // echo "警告: 生成的文件无法解析为JSON\n";
            }
        } else {
            // echo "警告: 生成的文件不存在\n";
        }
    } else {
        // echo "文件生成失败\n";
    }
} else {
     // echo "错误: 未提供task_id参数\n";
}
