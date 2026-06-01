<?php
require_once 'config.php';

// 设置响应头，以便下载Word文档
header('Content-Type: application/vnd.ms-word');
header('Content-Disposition: attachment; filename="拍摄计划.doc"');
header('Cache-Control: max-age=0');

// 获取task_id参数
$taskId = isset($_GET['task_id']) ? $_GET['task_id'] : null;

// 构建JSON文件路径
$jsonPath = $taskId 
    ? __DIR__ . "/results/{$taskId}_schedule.json"
    : __DIR__ . '/json/schedule-data.json';

// 检查文件是否存在
if (!file_exists($jsonPath)) {
    die('拍摄计划数据文件不存在');
}

// 读取并解析JSON数据
$jsonData = file_get_contents($jsonPath);
$scheduleData = json_decode($jsonData, true);

if (!$scheduleData) {
    die('无法解析拍摄计划数据');
}

// 生成Word文档内容
echo generateWordDocument($scheduleData);

/**
 * 生成Word文档内容
 */
function generateWordDocument($data) {
    $project = $data['project'];
    $schedule = $data['schedule'];
    
    // Word文档HTML结构
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>拍摄计划</title>
        <style>
            body {
                font-family: "Microsoft YaHei", sans-serif;
                font-size: 12pt;
                line-height: 1.5;
                margin: 2cm;
            }
            
            h1 {
                text-align: center;
                font-size: 20pt;
                margin-bottom: 10pt;
                border-bottom: 2pt solid #000;
                padding-bottom: 5pt;
            }
            
            h2 {
                font-size: 16pt;
                margin-top: 20pt;
                margin-bottom: 10pt;
                border-left: 4pt solid #333;
                padding-left: 10pt;
            }
            
            h3 {
                font-size: 14pt;
                margin-top: 15pt;
                margin-bottom: 8pt;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 10pt 0;
                font-size: 11pt;
            }
            
            th, td {
                border: 1pt solid #000;
                padding: 6pt;
                text-align: left;
                vertical-align: top;
            }
            
            th {
                background-color: #eee;
                font-weight: bold;
                text-align: center;
            }
            
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
            
            .project-info {
                margin-bottom: 20pt;
            }
            
            .project-info table {
                width: 100%;
            }
            
            .summary-stats {
                margin: 15pt 0;
                text-align: center;
            }
            
            .summary-stats table {
                display: inline-block;
                width: auto;
            }
            
            .page-break {
                page-break-before: always;
            }
        </style>
    </head>
    <body>
        <h1>拍摄计划</h1>
        
        <div class="project-info">
            <table>
                <tr>
                    <th colspan="4">项目基本信息</th>
                </tr>
                <tr>
                    <td><strong>项目名称</strong></td>
                    <td>' . htmlspecialchars($project['name']) . '</td>
                    <td><strong>导演</strong></td>
                    <td>' . htmlspecialchars($project['director']) . '</td>
                </tr>
                <tr>
                    <td><strong>制片人</strong></td>
                    <td>' . htmlspecialchars($project['producer']) . '</td>
                    <td><strong>摄影师</strong></td>
                    <td>' . htmlspecialchars($project['cinematographer']) . '</td>
                </tr>
                <tr>
                    <td><strong>计划拍摄天数</strong></td>
                    <td>' . $project['estimatedDays'] . '天</td>
                    <td><strong>总场次</strong></td>
                    <td>' . $project['totalScenes'] . '场</td>
                </tr>
                <tr>
                    <td><strong>总镜头数</strong></td>
                    <td>' . $project['totalShots'] . '个</td>
                    <td><strong>计划周期</strong></td>
                    <td>' . htmlspecialchars($project['startDate']) . ' 至 ' . htmlspecialchars($project['endDate']) . '</td>
                </tr>
            </table>
        </div>
        
        <div class="summary-stats">
            <table>
                <tr>
                    <th>已完成天数</th>
                    <th>完成率</th>
                    <th>总拍摄天数</th>
                    <th>实际拍摄天数</th>
                </tr>
                <tr>
                    <td class="text-center">' . $project['daysCompleted'] . '天</td>
                    <td class="text-center">' . $project['completionRate'] . '%</td>
                    <td class="text-center">' . $project['estimatedDays'] . '天</td>
                    <td class="text-center">' . $project['actualDays'] . '天</td>
                </tr>
            </table>
        </div>';
    
    // 按日期排序
    uksort($schedule, function($a, $b) {
        return strtotime($a) - strtotime($b);
    });
    
    // 为每一天生成详细计划
    foreach ($schedule as $date => $daySchedule) {
        $html .= '
        <div class="page-break">
            <h2>' . htmlspecialchars($date) . ' (' . htmlspecialchars($daySchedule['dayOfWeek']) . ') 拍摄计划</h2>';
        
        // 天气信息
        $html .= '
            <p><strong>天气预报：</strong>' . htmlspecialchars($daySchedule['weather']) . '</p>';
        
        // 时间安排
        $html .= '
            <table>
                <tr>
                    <th>集合时间</th>
                    <th>开机时间</th>
                    <th>收工时间</th>
                    <th>总拍摄时长</th>
                    <th>有效工作时长</th>
                </tr>
                <tr>
                    <td class="text-center">' . htmlspecialchars($daySchedule['callTime']) . '</td>
                    <td class="text-center">' . htmlspecialchars($daySchedule['shootTime']) . '</td>
                    <td class="text-center">' . htmlspecialchars($daySchedule['wrapTime']) . '</td>
                    <td class="text-center">' . $daySchedule['totalShootingHours'] . '小时</td>
                    <td class="text-center">' . $daySchedule['workingHours'] . '小时</td>
                </tr>
            </table>';
        
        // 午餐和晚餐时间
        $html .= '
            <table>
                <tr>
                    <th>午餐时间</th>
                    <th>晚餐时间</th>
                </tr>
                <tr>
                    <td class="text-center">' . implode(' - ', $daySchedule['breaks']['lunch']) . '</td>
                    <td class="text-center">' . implode(' - ', $daySchedule['breaks']['dinner']) . '</td>
                </tr>
            </table>';
        
        // 场次信息
        $html .= '
            <h3>拍摄场次详情</h3>
            <table>
                <tr>
                    <th>场次</th>
                    <th>场景名称</th>
                    <th>页码</th>
                    <th>内外景</th>
                    <th>日/夜</th>
                    <th>镜头数</th>
                    <th>预计时长(分钟)</th>
                    <th>主要演员</th>
                    <th>道具</th>
                </tr>';
        
        foreach ($daySchedule['scenes'] as $scene) {
            // 计算镜头数和预计时长
            $shotCount = count($scene['shots']);
            $estimatedDuration = 0;
            foreach ($scene['shots'] as $shot) {
                $estimatedDuration += $shot['duration'];
            }
            // 转换为分钟
            $estimatedMinutes = ceil($estimatedDuration / 60);
            
            // 获取主要演员
            $actors = [];
            foreach ($scene['actors'] as $actor) {
                $actors[] = $actor['character'];
            }
            $actorsList = implode(', ', $actors);
            
            // 获取道具
            $propsList = implode(', ', $scene['props']);
            
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($scene['sceneId']) . '</td>
                    <td>' . htmlspecialchars($scene['sceneName']) . '</td>
                    <td>' . htmlspecialchars($scene['pageNumbers']) . '</td>
                    <td>' . htmlspecialchars($scene['location']) . '</td>
                    <td>' . htmlspecialchars($scene['setting']) . '</td>
                    <td class="text-center">' . $shotCount . '</td>
                    <td class="text-center">' . $estimatedMinutes . '</td>
                    <td>' . htmlspecialchars($actorsList) . '</td>
                    <td>' . htmlspecialchars($propsList) . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
        
        // 演员详细信息
        $html .= '
            <h3>演员详细安排</h3>
            <table>
                <tr>
                    <th>角色</th>
                    <th>演员</th>
                    <th>集合时间</th>
                    <th>到达时间</th>
                    <th>戏服</th>
                    <th>妆造</th>
                    <th>备注</th>
                </tr>';
        
        // 收集所有演员信息
        $allActors = [];
        foreach ($daySchedule['scenes'] as $scene) {
            foreach ($scene['actors'] as $actor) {
                $key = $actor['character'];
                if (!isset($allActors[$key])) {
                    $allActors[$key] = $actor;
                }
            }
        }
        
        foreach ($allActors as $actor) {
            $html .= '
                <tr>
                    <td>' . htmlspecialchars($actor['character']) . '</td>
                    <td>' . htmlspecialchars($actor['actor'] ?: '待定') . '</td>
                    <td class="text-center">' . htmlspecialchars($actor['callTime']) . '</td>
                    <td class="text-center">' . htmlspecialchars($actor['arrivalTime']) . '</td>
                    <td>' . htmlspecialchars($actor['costume']) . '</td>
                    <td>' . htmlspecialchars($actor['makeup']) . '</td>
                    <td>' . htmlspecialchars($actor['notes'] ?: '') . '</td>
                </tr>';
        }
        
        $html .= '
            </table>';
        
        $html .= '
        </div>';
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}
?>