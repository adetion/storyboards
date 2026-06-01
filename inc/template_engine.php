<?php

class TemplateEngine {
    private $config;
    private $templatePath;
    private $functions = [];

    public function __construct($config) {
        $this->config = $config;
        $this->templatePath = __DIR__ . '/../'; // 模板文件位于根目录
        $this->registerDefaultFunctions();
    }

    private function registerDefaultFunctions() {
        // 注册默认函数组件
        $this->functions['fill_main_scene'] = [$this, 'fillMainScene'];
        $this->functions['fill_production_notes'] = [$this, 'fillProductionNotes'];
        $this->functions['fill_department_notices'] = [$this, 'fillDepartmentNotices'];
        $this->functions['fill_contact_info'] = [$this, 'fillContactInfo'];
        $this->functions['generate_shot_list'] = [$this, 'generateShotList'];
        $this->functions['generate_schedule_table'] = [$this, 'generateScheduleTable'];
    }

    public function render($templateName, $data) {
        // 读取模板文件
        $templateFile = $this->templatePath . '/' . $templateName;
        if (!file_exists($templateFile)) {
            throw new Exception("Template file not found: {$templateFile}");
        }

        // 读取HTML模板内容
        $template = file_get_contents($templateFile);

        // 替换静态数据
        $template = $this->replaceStaticData($template, $data);

        // 处理动态组件
        $template = $this->processComponents($template, $data);

        return $template;
    }

    private function replaceStaticData($template, $data) {
        // 替换所有 {{key}} 格式的数据
        preg_match_all('/{{(\w+)}}/', $template, $matches);
        foreach ($matches[1] as $key) {
            $value = isset($data[$key]) ? $data[$key] : '';
            $template = str_replace("{{{$key}}}", $value, $template);
        }
        
        return $template;
    }

    private function processComponents($template, $data) {
        // 处理 {%component_name%} 格式的组件
        preg_match_all('/{%(\w+)%}/', $template, $matches);
        foreach ($matches[1] as $component) {
            if (isset($this->functions[$component])) {
                $value = call_user_func($this->functions[$component], $data);
                $template = str_replace("{%{$component}%}", $value, $template);
            }
        }
        return $template;
    }

    // 填充主场景单元格：主场景：导演：摄影指导：星期 开机第 天 起床：出发：
    public function fillMainScene($data) {
        $mainScene = $data['main_scene'] ?? '主场景';
        $director = $data['director'] ?? '导演';
        $cinematographer = $data['cinematographer'] ?? '摄影指导';
        $weekday = date('l', strtotime($data['shooting_date'] ?? date('Y-m-d')));
        $day = $data['shooting_day'] ?? 1;
        $wake_up = $data['wake_up_time'] ?? '06:00';
        $departure = $data['departure_time'] ?? '07:00';

        // 转换星期为中文
        $weekdayMap = [
            'Monday' => '星期一',
            'Tuesday' => '星期二',
            'Wednesday' => '星期三',
            'Thursday' => '星期四',
            'Friday' => '星期五',
            'Saturday' => '星期六',
            'Sunday' => '星期日'
        ];
        $weekday = $weekdayMap[$weekday] ?? $weekday;

        return sprintf("主场景：%-15s导 演：%-15s摄影指导：%-15s星期 %s 开机第 %d 天       起床：%s         出发：%s",
            $mainScene, $director, $cinematographer, $weekday, $day, $wake_up, $departure);
    }

    // 填充制片提示：早餐时间、特殊情况如何处理
    public function fillProductionNotes($data) {
        $breakfastTime = $data['breakfast_time'] ?? '07:30';
        $specialNotes = $data['special_notes'] ?? '无特殊情况';
        return "制片提示：早餐时间 {$breakfastTime}，特殊情况：{$specialNotes}";
    }

    // 填充各部门提示
    public function fillDepartmentNotices($data) {
        $html = '';
        if (isset($data['departments'])) {
            foreach ($data['departments'] as $dept) {
                $html .= "<div>{$dept['name']}：{$dept['notice']}</div>";
            }
        }
        return $html;
    }

    // 填充剧组关键岗位成员的联系方式
    public function fillContactInfo($data) {
        $html = '';
        if (isset($data['contacts'])) {
            $first = true;
            foreach ($data['contacts'] as $contact) {
                if (!$first) {
                    $html .= "    ";
                }
                $position = $contact['position'] ?? '未知职位';
                $name = $contact['name'] ?? '未知姓名';
                $phone = $contact['phone'] ?? '无电话';
                $html .= "{$position} {$name} 电话：{$phone}";
                $first = false;
            }
        }
        return $html;
    }

    // 生成分镜列表
    public function generateShotList($data) {
        $shots = $data['shots'] ?? [];
        $html = '<div class="table-container">';
        $html .= '<table class="shot-list-table">';
        $html .= '<thead><tr><th>镜号</th><th>景别</th><th>时长</th><th>内容</th><th>备注</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($shots as $shot) {
            $html .= "<tr>";
            $html .= "<td>{$shot['shot_id']}</td>";
            $html .= "<td>{$shot['shot_type']}</td>";
            $html .= "<td>{$shot['duration']}秒</td>";
            $html .= "<td>{$shot['content']}</td>";
            $html .= "<td>{$shot['notes']}</td>";
            $html .= "</tr>";
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        return $html;
    }

    // 生成拍摄时间表
    public function generateScheduleTable($data) {
        $schedule = $data['schedule'] ?? [];
        $html = '<div class="table-container">';
        $html .= '<table class="schedule-table">';
        $html .= '<thead><tr><th>场次</th><th>时段</th><th>内/外景</th><th>拍摄地点</th><th>拍摄时间</th><th>备注</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($schedule as $item) {
            $html .= "<tr>";
            $html .= "<td>{$item['scene_number']}</td>";
            $html .= "<td>{$item['time_slot']}</td>";
            $html .= "<td>{$item['indoor_outdoor']}</td>";
            $html .= "<td>{$item['location']}</td>";
            $html .= "<td>{$item['shooting_time']}</td>";
            $html .= "<td>{$item['notes']}</td>";
            $html .= "</tr>";
        }
        
        $html .= '</tbody></table>';
        $html .= '</div>';
        return $html;
    }
}
