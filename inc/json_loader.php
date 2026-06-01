<?php

class JsonLoader {
    private $config;
    private $jsonPath;
    private $defaultJsonPath;

    public function __construct($config) {
        $this->config = $config;
        $this->jsonPath = __DIR__ . '/../results/'; // 结果JSON文件路径
        $this->defaultJsonPath = __DIR__ . '/../json/'; // 默认JSON文件路径
    }

    public function loadJson($taskId = null, $date = null, $preserveOriginal = false) {
        $jsonFile = '';
        $originalData = null;
        
        // 1. 如果提供了taskId，尝试从results目录加载对应JSON文件
        if ($taskId) {
            // 尝试加载announcement_main.js期望的命名规则：{taskId}_announcement.json
            $jsonFile = $this->jsonPath . "{$taskId}_announcement.json";
            if (file_exists($jsonFile)) {
                if ($preserveOriginal) {
                    // 直接返回原始JSON数据
                    return json_decode(file_get_contents($jsonFile), true);
                } else {
                    return $this->processAnnouncementJson($jsonFile, $date);
                }
            }
            
            // 尝试加载默认命名规则：{taskId}.json
            $jsonFile = $this->jsonPath . "{$taskId}.json";
            if (file_exists($jsonFile)) {
                if ($preserveOriginal) {
                    // 直接返回原始JSON数据
                    return json_decode(file_get_contents($jsonFile), true);
                } else {
                    return $this->processJson($jsonFile);
                }
            }
        }
        
        // 2. 尝试加载默认JSON文件
        $jsonFile = $this->defaultJsonPath . 'default.json';
        if (file_exists($jsonFile)) {
            if ($preserveOriginal) {
                // 直接返回原始JSON数据
                return json_decode(file_get_contents($jsonFile), true);
            } else {
                return $this->processJson($jsonFile);
            }
        }
        
        // 3. 尝试加载announcement_main.js使用的默认JSON文件：announcement-data.json
        $jsonFile = $this->defaultJsonPath . 'announcement-data.json';
        if (file_exists($jsonFile)) {
            if ($preserveOriginal) {
                // 直接返回原始JSON数据
                return json_decode(file_get_contents($jsonFile), true);
            } else {
                return $this->processAnnouncementJson($jsonFile, $date);
            }
        }
        
        // 4. 尝试加载示例JSON文件
        $jsonFile = $this->defaultJsonPath . 'sample.json';
        if (file_exists($jsonFile)) {
            if ($preserveOriginal) {
                // 直接返回原始JSON数据
                return json_decode(file_get_contents($jsonFile), true);
            } else {
                return $this->processJson($jsonFile);
            }
        }
        
        // 5. 如果没有找到任何JSON文件，返回默认数据结构
        if ($preserveOriginal) {
            return ['shootingDays' => [], 'project' => ['name' => '拍摄通告']];
        } else {
            return $this->getDefaultData();
        }
    }

    private function processJson($jsonFile) {
        $jsonData = file_get_contents($jsonFile);
        $originalData = json_decode($jsonData, true);
        $data = $originalData;
        
        // 验证JSON数据结构
        if (!$this->validateJson($data)) {
            throw new Exception("Invalid JSON data structure in file: {$jsonFile}");
        }
        
        // 处理和转换数据
        $data = $this->transformData($data);
        
        // 保留原始数据
        $data['original_data'] = $originalData;
        
        return $data;
    }

    private function validateJson($data) {
        // 基本验证：确保是数组
        if (!is_array($data)) {
            return false;
        }
        
        // 可以添加更详细的验证逻辑
        return true;
    }

    private function transformData($data) {
        // 添加默认值
        $data = $this->addDefaultValues($data);
        
        // 转换数据格式
        $data = $this->convertDataFormats($data);
        
        // 确保数据完整性
        $data = $this->ensureDataIntegrity($data);
        
        return $data;
    }

    private function addDefaultValues($data) {
        // 添加必要的默认值
        $defaults = [
            'title' => '拍摄通告',
            'shooting_date' => date('Y-m-d'),
            'shooting_day' => 1,
            'location' => '摄影棚',
            'weather' => '晴',
            'director' => '未指定',
            'cinematographer' => '未指定',
            'main_scene' => '主场景',
            'wake_up_time' => '06:00',
            'departure_time' => '07:00',
            'breakfast_time' => '07:30',
            'special_notes' => '无特殊情况',
            'departments' => [],
            'contacts' => [],
            'shots' => [],
            'schedule' => []
        ];
        
        return array_merge($defaults, $data);
    }

    private function convertDataFormats($data) {
        // 转换拍摄日期为标准格式
        if (isset($data['shooting_date'])) {
            $data['shooting_date'] = date('Y-m-d', strtotime($data['shooting_date']));
        }
        
        // 确保拍摄天数为整数
        if (isset($data['shooting_day'])) {
            $data['shooting_day'] = intval($data['shooting_day']);
        }
        
        return $data;
    }

    private function ensureDataIntegrity($data) {
        // 确保部门数据格式正确
        if (isset($data['departments']) && is_array($data['departments'])) {
            foreach ($data['departments'] as &$dept) {
                if (!isset($dept['name'])) {
                    $dept['name'] = '未命名部门';
                }
                if (!isset($dept['notice'])) {
                    $dept['notice'] = '';
                }
            }
        }
        
        // 确保联系方式数据格式正确
        if (isset($data['contacts']) && is_array($data['contacts'])) {
            foreach ($data['contacts'] as &$contact) {
                if (!isset($contact['position'])) {
                    $contact['position'] = '未指定职位';
                }
                if (!isset($contact['name'])) {
                    $contact['name'] = '未指定姓名';
                }
                if (!isset($contact['phone'])) {
                    $contact['phone'] = '未提供电话';
                }
            }
        }
        
        // 确保分镜数据格式正确
        if (isset($data['shots']) && is_array($data['shots'])) {
            foreach ($data['shots'] as &$shot) {
                $shot = array_merge([
                    'shot_id' => '001-01-A',
                    'scene_number' => '001',
                    'shot_type' => '中景',
                    'duration' => 10,
                    'content' => '未指定内容',
                    'notes' => ''
                ], $shot);
            }
        }
        
        // 确保拍摄计划数据格式正确
        if (isset($data['schedule']) && is_array($data['schedule'])) {
            foreach ($data['schedule'] as &$item) {
                $item = array_merge([
                    'scene_number' => '001',
                    'time_slot' => '上午',
                    'indoor_outdoor' => '内景',
                    'location' => '摄影棚',
                    'shooting_time' => '08:00',
                    'notes' => ''
                ], $item);
            }
        }
        
        return $data;
    }

    /**
     * 处理announcement_main.js使用的JSON数据格式
     */
    private function processAnnouncementJson($jsonFile, $date = null) {
        $jsonData = file_get_contents($jsonFile);
        $data = json_decode($jsonData, true);
        
        // 验证JSON数据结构
        if (!$this->validateJson($data)) {
            throw new Exception("Invalid JSON data structure in file: {$jsonFile}");
        }
        
        // 转换数据格式，适配模板引擎
        $convertedData = $this->convertAnnouncementData($data, $date);
        
        // 处理和转换数据
        $convertedData = $this->transformData($convertedData);
        
        return $convertedData;
    }
    
    /**
     * 将announcement_main.js使用的JSON数据格式转换为模板引擎期望的格式
     */
    private function convertAnnouncementData($data, $date = null) {
        $converted = [];
        
        // 1. 保留原始数据的所有信息
        $converted['original_data'] = $data;
        
        // 2. 获取项目名称
        $converted['title'] = isset($data['project']['name']) ? $data['project']['name'] : '拍摄通告';
        
        // 3. 获取拍摄日期
        $shootingDates = array_keys($data['shootingDays'] ?? []);
        $selectedDate = $date ?? (count($shootingDates) > 0 ? $shootingDates[0] : date('Y-m-d'));
        $converted['shooting_date'] = $selectedDate;
        
        // 4. 获取当天的拍摄数据
        $dayData = $data['shootingDays'][$selectedDate] ?? null;
        
        if ($dayData) {
            // 拍摄基本信息
            $shootDay = $dayData['shootDay'] ?? [];
            $converted['location'] = $shootDay['location'] ?? '摄影棚';
            $converted['weather'] = $shootDay['weather'] ?? '晴';
            $converted['wake_up_time'] = $shootDay['callTime'] ?? '07:30';
            $converted['departure_time'] = $shootDay['shootTime'] ?? '08:00';
            $converted['breakfast_time'] = date('H:i', strtotime($shootDay['callTime'] . '-30 minutes')) ?? '07:00';
            
            // 导演和摄影指导
            $crew = $dayData['crew'] ?? [];
            $keyCrew = $crew['keyCrew'] ?? [];
            
            foreach ($keyCrew as $member) {
                if ($member['role'] === '导演' || $member['role'] === 'Director') {
                    $converted['director'] = $member['name'];
                }
                if ($member['role'] === '摄影指导' || $member['role'] === 'Cinematographer') {
                    $converted['cinematographer'] = $member['name'];
                }
            }
            
            // 分镜列表
            $shots = [];
            $scenes = $dayData['scenes'] ?? [];
            
            foreach ($scenes as $scene) {
                $sceneShots = $scene['shots'] ?? [];
                
                foreach ($sceneShots as $shot) {
                    $shots[] = [
                        'shot_id' => $shot['originalShotId'] ?? $shot['shotId'] ?? '',
                        'scene_number' => $scene['sceneId'] ?? '',
                        'shot_type' => $shot['shotType'] ?? '中景',
                        'duration' => $shot['duration'] ?? 10,
                        'content' => $scene['sceneName'] ?? '未指定内容',
                        'notes' => $shot['notes'] ?? '',
                        'original_shot' => $shot // 保留原始镜头数据
                    ];
                }
            }
            
            $converted['shots'] = $shots;
            $converted['original_scenes'] = $scenes; // 保留原始场次数据
            
            // 拍摄计划
            $schedule = [];
            
            foreach ($scenes as $scene) {
                $schedule[] = [
                    'scene_number' => $scene['sceneId'] ?? '',
                    'time_slot' => $this->getTimeSlot($scene['estimatedTime'] ?? ''),
                    'indoor_outdoor' => $scene['setting'] ?? '内景',
                    'location' => $scene['location'] ?? '摄影棚',
                    'shooting_time' => $scene['estimatedTime'] ?? '08:00',
                    'notes' => $scene['pageNumbers'] ?? '',
                    'original_scene' => $scene // 保留原始场次数据
                ];
            }
            
            $converted['schedule'] = $schedule;
            
            // 联系方式
            $contacts = [];
            
            foreach ($keyCrew as $member) {
                $contacts[] = [
                    'position' => $member['role'] ?? '未指定职位',
                    'name' => $member['name'] ?? '未指定姓名',
                    'phone' => $member['phone'] ?? '未提供电话',
                    'original_member' => $member // 保留原始成员数据
                ];
            }
            
            $converted['contacts'] = $contacts;
            $converted['original_crew'] = $crew; // 保留原始剧组数据
            
            // 部门提示
            $departments = [];
            
            // 从原始数据中获取部门信息
            if (isset($crew['departments'])) {
                foreach ($crew['departments'] as $dept) {
                    $departments[] = [
                        'name' => $dept['name'] ?? '未命名部门',
                        'notice' => $dept['notice'] ?? '',
                        'original_department' => $dept // 保留原始部门数据
                    ];
                }
            }
            
            // 如果没有部门信息，使用默认值
            if (empty($departments)) {
                $departments = [
                    ['name' => '导演组', 'notice' => '请提前30分钟到场'],
                    ['name' => '摄影组', 'notice' => '检查器材设备'],
                    ['name' => '灯光组', 'notice' => '准备好所有灯具'],
                    ['name' => '美术组', 'notice' => '确认场景布置'],
                    ['name' => '服化道', 'notice' => '准备好演员服装和道具'],
                    ['name' => '演员组', 'notice' => '带好剧本和台词'],
                    ['name' => '制片组', 'notice' => '安排好车辆和餐饮'],
                    ['name' => '场务组', 'notice' => '准备好拍摄所需物品']
                ];
            }
            
            $converted['departments'] = $departments;
            
            // 保留拍摄日的完整数据
            $converted['original_day_data'] = $dayData;
        }
        
        // 保留所有拍摄日期数据
        $converted['all_shooting_dates'] = $shootingDates;
        $converted['original_shooting_days'] = $data['shootingDays'] ?? [];
        
        return $converted;
    }
    
    /**
     * 根据时间字符串获取时段（上午、下午）
     */
    private function getTimeSlot($timeString) {
        if (empty($timeString)) {
            return '上午';
        }
        
        if (strpos($timeString, '上午') !== false || strpos($timeString, 'AM') !== false) {
            return '上午';
        }
        if (strpos($timeString, '下午') !== false || strpos($timeString, 'PM') !== false) {
            return '下午';
        }
        
        // 解析时间字符串
        if (preg_match('/^(\d{1,2}):(\d{2})/', $timeString, $matches)) {
            $hour = intval($matches[1]);
            return $hour < 12 ? '上午' : '下午';
        }
        
        return '上午';
    }
    
    private function getDefaultData() {
        // 返回默认数据结构
        return [
            'title' => '拍摄通告',
            'shooting_date' => date('Y-m-d'),
            'shooting_day' => 1,
            'location' => '摄影棚',
            'weather' => '晴',
            'director' => '未指定',
            'cinematographer' => '未指定',
            'main_scene' => '主场景',
            'wake_up_time' => '06:00',
            'departure_time' => '07:00',
            'breakfast_time' => '07:30',
            'special_notes' => '无特殊情况',
            'departments' => [
                ['name' => '导演组', 'notice' => '请提前30分钟到场'],
                ['name' => '摄影组', 'notice' => '检查器材设备'],
                ['name' => '灯光组', 'notice' => '准备好所有灯具'],
                ['name' => '美术组', 'notice' => '确认场景布置'],
                ['name' => '服化道', 'notice' => '准备好演员服装和道具'],
                ['name' => '演员组', 'notice' => '带好剧本和台词'],
                ['name' => '制片组', 'notice' => '安排好车辆和餐饮'],
                ['name' => '场务组', 'notice' => '准备好拍摄所需物品']
            ],
            'contacts' => [
                ['position' => '导演', 'name' => '张三', 'phone' => '13800138001'],
                ['position' => '摄影指导', 'name' => '李四', 'phone' => '13800138002'],
                ['position' => '制片主任', 'name' => '王五', 'phone' => '13800138003'],
                ['position' => '现场制片', 'name' => '赵六', 'phone' => '13800138004']
            ],
            'shots' => [
                [
                    'shot_id' => 'D001-01-A',
                    'scene_number' => 'D001',
                    'shot_type' => '全景',
                    'duration' => 15,
                    'content' => '演员入场，开始对话',
                    'notes' => '注意演员走位'
                ],
                [
                    'shot_id' => 'D001-02-A',
                    'scene_number' => 'D001',
                    'shot_type' => '中景',
                    'duration' => 10,
                    'content' => '演员特写，情绪表达',
                    'notes' => '注意灯光效果'
                ],
                [
                    'shot_id' => 'D001-03-A',
                    'scene_number' => 'D001',
                    'shot_type' => '特写',
                    'duration' => 8,
                    'content' => '道具细节展示',
                    'notes' => '使用微距镜头'
                ]
            ],
            'schedule' => [
                [
                    'scene_number' => 'D001',
                    'time_slot' => '上午',
                    'indoor_outdoor' => '内景',
                    'location' => '摄影棚1',
                    'shooting_time' => '08:30',
                    'notes' => '第一场拍摄'
                ],
                [
                    'scene_number' => 'D002',
                    'time_slot' => '下午',
                    'indoor_outdoor' => '外景',
                    'location' => '公园',
                    'shooting_time' => '14:00',
                    'notes' => '第二场拍摄'
                ]
            ]
        ];
    }
}
