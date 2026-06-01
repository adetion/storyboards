<?php
require_once 'config.php';

// 设置响应头，以便下载Word文档
header('Content-Type: application/vnd.ms-word');
header('Content-Disposition: attachment; filename="拍摄通告.doc"');
header('Cache-Control: max-age=0');

// 获取task_id和date参数
$taskId = isset($_GET['task_id']) ? $_GET['task_id'] : null;
$date = isset($_GET['date']) ? $_GET['date'] : null;

// 构建JSON文件路径
$jsonPath = $taskId 
    ? __DIR__ . "/results/{$taskId}_announcement.json"
    : __DIR__ . '/json/announcement-data.json';

// 检查文件是否存在
if (!file_exists($jsonPath)) {
    die('拍摄通告数据文件不存在');
}

// 读取并解析JSON数据
$jsonData = file_get_contents($jsonPath);
$announcementData = json_decode($jsonData, true);

if (!$announcementData) {
    die('无法解析拍摄通告数据');
}

// 生成Word文档内容
echo generateWordDocument($announcementData, $date);

/**
 * 生成Word文档内容
 */
function generateWordDocument($data, $selectedDate = null) {
    $project = $data['project'];
    
    // 获取所有拍摄日期
    $shootingDays = $data['shootingDays'];
    
    // 如果没有指定日期，则使用第一个日期
    if (!$selectedDate && !empty($shootingDays)) {
        reset($shootingDays);
        $selectedDate = key($shootingDays);
    }
    
    // Word文档HTML结构
    $html = '
    <!DOCTYPE html>
    <html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40">
    <head>
        <meta charset="UTF-8">
        <title>拍摄通告</title>
        <!--[if gte mso 9]>
        <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
        </xml>
        <![endif]-->
        <style>
            @page {
                size: A4 landscape;
                margin: 1.2cm;
            }
            
            body {
                font-family: "宋体", SimSun, serif;
                font-size: 12pt;
                line-height: 1.3;
                margin: 0;
                padding: 0;
            }
            
            .page {
                page-break-after: always;
            }
            
            .page:last-child {
                page-break-after: avoid;
            }
            
            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
                font-size: 12pt;
                margin-bottom: 10pt;
            }
            
            td, th {
                border: 1pt solid #000;
                padding: 2pt 4pt;
                vertical-align: middle;
            }
            
            th {
                text-align: center;
                font-weight: bold;
                background-color: #eee;
            }
            
            .text-center {
                text-align: center;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-bold {
                font-weight: bold;
            }
            
            .header-section {
                margin-bottom: 10pt;
            }
            
            .header-title {
                text-align: center;
                font-size: 18pt;
                font-weight: bold;
                margin-bottom: 8pt;
            }
            
            .header-subtitle {
                text-align: center;
                font-size: 14pt;
                margin-bottom: 15pt;
            }
            
            .info-table {
                margin-bottom: 15pt;
            }
            
            .info-table td {
                border: none;
                padding: 1pt 3pt;
            }
            
            .info-table .label {
                text-align: right;
                font-weight: bold;
                width: 15%;
            }
            
            .info-table .value {
                border-bottom: 1pt solid #000;
                width: 35%;
            }
            
            .scene-table {
                margin-bottom: 15pt;
            }
            
            .scene-table th {
                background-color: #eee;
            }
            
            .scene-table .scene-number {
                width: 8%;
            }
            
            .scene-table .scene-name {
                width: 20%;
            }
            
            .scene-table .page-numbers {
                width: 10%;
            }
            
            .scene-table .location {
                width: 8%;
            }
            
            .scene-table .setting {
                width: 8%;
            }
            
            .scene-table .duration {
                width: 8%;
            }
            
            .scene-table .script-notes {
                width: 28%;
            }
            
            .cast-table {
                margin-bottom: 15pt;
            }
            
            .cast-table th {
                background-color: #eee;
            }
            
            .cast-table .character {
                width: 12%;
            }
            
            .cast-table .actor {
                width: 12%;
            }
            
            .cast-table .call-time {
                width: 10%;
            }
            
            .cast-table .arrival-time {
                width: 10%;
            }
            
            .cast-table .costume {
                width: 23%;
            }
            
            .cast-table .makeup {
                width: 23%;
            }
            
            .cast-table .notes {
                width: 10%;
            }
            
            .crew-table {
                margin-bottom: 15pt;
            }
            
            .crew-table th {
                background-color: #eee;
            }
            
            .crew-table .position {
                width: 15%;
            }
            
            .crew-table .name {
                width: 15%;
            }
            
            .crew-table .phone {
                width: 15%;
            }
            
            .crew-table .call-time {
                width: 15%;
            }
            
            .crew-table .notes {
                width: 40%;
            }
            
            .equipment-table {
                margin-bottom: 15pt;
            }
            
            .equipment-table th {
                background-color: #eee;
            }
            
            .equipment-table .type {
                width: 20%;
            }
            
            .equipment-table .model {
                width: 20%;
            }
            
            .equipment-table .quantity {
                width: 10%;
            }
            
            .equipment-table .notes {
                width: 50%;
            }
            
            .props-table {
                margin-bottom: 15pt;
            }
            
            .props-table th {
                background-color: #eee;
            }
            
            .notes-section {
                margin: 15pt 0;
            }
            
            .notes-title {
                font-weight: bold;
                margin-bottom: 5pt;
                text-decoration: underline;
            }
            
            .signature-section {
                margin-top: 30pt;
                display: flex;
                justify-content: space-between;
            }
            
            .signature-block {
                flex: 1;
                text-align: center;
            }
            
            .signature-line {
                height: 30pt;
                border-bottom: 1pt solid #000;
                margin-top: 5pt;
            }
        </style>
    </head>
    <body>';
    
    // 如果指定了日期，则只生成该日期的通告，否则生成所有日期的通告
    $datesToProcess = [];
    if ($selectedDate && isset($shootingDays[$selectedDate])) {
        $datesToProcess[$selectedDate] = $shootingDays[$selectedDate];
    } else {
        $datesToProcess = $shootingDays;
    }
    
    // 为每个日期生成通告
    foreach ($datesToProcess as $date => $dayData) {
        $shootDay = $dayData['shootDay'];
        $scenes = $dayData['scenes'];
        
        $html .= '
        <div class="page">
            <div class="header-section">
                <div class="header-title">拍 摄 通 告</div>
                <div class="header-subtitle">' . htmlspecialchars($project['name']) . '</div>
                
                <table class="info-table">
                    <tr>
                        <td class="label">日期：</td>
                        <td class="value">' . htmlspecialchars($shootDay['date']) . ' ' . htmlspecialchars($shootDay['dayOfWeek']) . '</td>
                        <td class="label">第______场</td>
                        <td class="value"></td>
                    </tr>
                    <tr>
                        <td class="label">集合：</td>
                        <td class="value">' . htmlspecialchars($shootDay['callTime']) . '</td>
                        <td class="label">开机：</td>
                        <td class="value">' . htmlspecialchars($shootDay['shootTime']) . '</td>
                    </tr>
                    <tr>
                        <td class="label">收工：</td>
                        <td class="value">' . htmlspecialchars($shootDay['wrapTime']) . '</td>
                        <td class="label">午餐：</td>
                        <td class="value">' . implode(' - ', $shootDay['breaks']['lunch']) . '</td>
                    </tr>
                    <tr>
                        <td class="label">天气：</td>
                        <td class="value">' . htmlspecialchars($shootDay['weather']) . '</td>
                        <td class="label">地点：</td>
                        <td class="value">' . htmlspecialchars($shootDay['location']) . '</td>
                    </tr>
                </table>
            </div>
            
            <table class="scene-table">
                <thead>
                    <tr>
                        <th class="scene-number">场次</th>
                        <th class="scene-name">场景名称</th>
                        <th class="page-numbers">页码</th>
                        <th class="location">内外景</th>
                        <th class="setting">日/夜</th>
                        <th class="duration">时长</th>
                        <th class="script-notes">内容</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($scenes as $scene) {
            $html .= '
                    <tr>
                        <td class="text-center">' . htmlspecialchars($scene['sceneId']) . '</td>
                        <td>' . htmlspecialchars($scene['sceneName']) . '</td>
                        <td class="text-center">' . htmlspecialchars($scene['pageNumbers']) . '</td>
                        <td class="text-center">' . htmlspecialchars($scene['location']) . '</td>
                        <td class="text-center">' . htmlspecialchars($scene['setting']) . '</td>
                        <td class="text-center">' . $scene['estimatedDuration'] . '小时</td>
                        <td>' . htmlspecialchars($scene['scriptNotes']) . '</td>
                    </tr>';
        }
        
        $html .= '
                </tbody>
            </table>
            
            <table class="cast-table">
                <thead>
                    <tr>
                        <th class="character">角色</th>
                        <th class="actor">演员</th>
                        <th class="call-time">集合时间</th>
                        <th class="arrival-time">到达时间</th>
                        <th class="costume">服装</th>
                        <th class="makeup">化妆</th>
                        <th class="notes">备注</th>
                    </tr>
                </thead>
                <tbody>';
        
        // 收集所有演员信息
        $allActors = [];
        foreach ($scenes as $scene) {
            if (!empty($scene['cast'])) {
                foreach ($scene['cast'] as $cast) {
                    $key = $cast['character'];
                    if (!isset($allActors[$key])) {
                        $allActors[$key] = $cast;
                    }
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
                </tbody>
            </table>
            
            <table class="crew-table">
                <thead>
                    <tr>
                        <th class="position">职位</th>
                        <th class="name">姓名</th>
                        <th class="phone">联系电话</th>
                        <th class="call-time">集合时间</th>
                        <th class="notes">备注</th>
                    </tr>
                </thead>
                <tbody>';
        
        // 工作人员信息
        $crewMembers = [
            ['position' => '导演', 'name' => $project['director']],
            ['position' => '制片人', 'name' => $project['producer']],
            ['position' => '摄影师', 'name' => $project['cinematographer']],
            ['position' => '场记', 'name' => $project['scriptSupervisor']],
            ['position' => '美术指导', 'name' => $project['productionDesigner']],
            ['position' => '服装师', 'name' => $project['costumeDesigner']],
            ['position' => '副导演', 'name' => $project['assistantDirector']],
            ['position' => '器械师', 'name' => $project['keyGrip']],
            ['position' => '助理', 'name' => $project['bestBoy']],
        ];
        
        foreach ($crewMembers as $member) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($member['position']) . '</td>
                        <td>' . htmlspecialchars($member['name']) . '</td>
                        <td></td>
                        <td></td>
                        <td></td>
                    </tr>';
        }
        
        // 添加化妆师、道具师等工作人员
        if (!empty($dayData['crew'])) {
            foreach ($dayData['crew'] as $crewMember) {
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($crewMember['role']) . '</td>
                        <td>' . htmlspecialchars($crewMember['name']) . '</td>
                        <td>' . htmlspecialchars($crewMember['phone']) . '</td>
                        <td>' . htmlspecialchars($crewMember['callTime']) . '</td>
                        <td>' . htmlspecialchars($crewMember['notes'] ?? '') . '</td>
                    </tr>';
            }
        }
        
        $html .= '
                </tbody>
            </table>';
        
        // 设备清单
        if (!empty($dayData['equipment'])) {
            if (!empty($dayData['equipment']['camera'])) {
                $html .= '
            <div class="notes-title">摄影设备</div>
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th class="type">设备类型</th>
                        <th class="model">型号</th>
                        <th class="quantity">数量</th>
                        <th class="notes">备注</th>
                    </tr>
                </thead>
                <tbody>';
                
                foreach ($dayData['equipment']['camera'] as $camera) {
                    $html .= '
                    <tr>
                        <td>摄像机</td>
                        <td>' . htmlspecialchars($camera['type']) . '</td>
                        <td class="text-center">' . htmlspecialchars($camera['quantity']) . '</td>
                        <td>' . htmlspecialchars($camera['notes'] ?? '') . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>';
            }
            
            if (!empty($dayData['equipment']['lighting'])) {
                $html .= '
            <div class="notes-title">灯光设备</div>
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th class="type">设备类型</th>
                        <th class="model">型号</th>
                        <th class="quantity">数量</th>
                        <th class="notes">备注</th>
                    </tr>
                </thead>
                <tbody>';
                
                foreach ($dayData['equipment']['lighting'] as $light) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($light['type']) . '</td>
                        <td>' . htmlspecialchars($light['model'] ?? '') . '</td>
                        <td class="text-center">' . htmlspecialchars($light['quantity']) . '</td>
                        <td>' . htmlspecialchars($light['notes'] ?? '') . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>';
            }
            
            if (!empty($dayData['equipment']['audio'])) {
                $html .= '
            <div class="notes-title">音响设备</div>
            <table class="equipment-table">
                <thead>
                    <tr>
                        <th class="type">设备类型</th>
                        <th class="model">型号</th>
                        <th class="quantity">数量</th>
                        <th class="notes">备注</th>
                    </tr>
                </thead>
                <tbody>';
                
                foreach ($dayData['equipment']['audio'] as $audio) {
                    $html .= '
                    <tr>
                        <td>' . htmlspecialchars($audio['type']) . '</td>
                        <td>' . htmlspecialchars($audio['model'] ?? '') . '</td>
                        <td class="text-center">' . htmlspecialchars($audio['quantity']) . '</td>
                        <td>' . htmlspecialchars($audio['notes'] ?? '') . '</td>
                    </tr>';
                }
                
                $html .= '
                </tbody>
            </table>';
            }
        }
        
        // 道具清单
        $html .= '
            <div class="notes-title">道具清单</div>';
        
        if (!empty($dayData['props'])) {
            $html .= '
            <table class="props-table">
                <thead>
                    <tr>
                        <th>道具名称</th>
                    </tr>
                </thead>
                <tbody>';
            
            foreach ($dayData['props'] as $prop) {
                $html .= '
                    <tr>
                        <td>' . htmlspecialchars($prop) . '</td>
                    </tr>';
            }
            
            $html .= '
                </tbody>
            </table>';
        } else {
            $html .= '<p>暂无道具信息</p>';
        }
        
        // 备注和安全信息
        if (!empty($dayData['notes'])) {
            $html .= '
            <div class="notes-section">';
            
            if (!empty($dayData['notes']['directorNotes'])) {
                $html .= '
                <div class="notes-title">导演备注</div>
                <div>';
                foreach ($dayData['notes']['directorNotes'] as $note) {
                    $html .= htmlspecialchars($note) . '<br>';
                }
                $html .= '</div>';
            }
            
            if (!empty($dayData['notes']['cinematographerNotes'])) {
                $html .= '
                <div class="notes-title">摄影备注</div>
                <div>';
                foreach ($dayData['notes']['cinematographerNotes'] as $note) {
                    $html .= htmlspecialchars($note) . '<br>';
                }
                $html .= '</div>';
            }
            
            if (!empty($dayData['notes']['castingNotes'])) {
                $html .= '
                <div class="notes-title">演员备注</div>
                <div>';
                foreach ($dayData['notes']['castingNotes'] as $note) {
                    $html .= htmlspecialchars($note) . '<br>';
                }
                $html .= '</div>';
            }
            
            $html .= '
            </div>';
        }
        
        // 安全信息
        if (!empty($dayData['safety'])) {
            $html .= '
            <div class="notes-section">
                <div class="notes-title">安全须知</div>
                <div>安全员: ' . htmlspecialchars($dayData['safety']['safetyOfficer']) . '</div>
                <div>紧急联系电话: ' . htmlspecialchars($dayData['safety']['emergencyContact']) . '</div>
                <div>急救站: ' . htmlspecialchars($dayData['safety']['firstAidStation']) . '</div>
                <div>疏散路线: ' . htmlspecialchars($dayData['safety']['evacuationPlan']) . '</div>
            </div>';
        }
        
        // 签字区域
        $html .= '
            <div class="signature-section">
                <div class="signature-block">
                    <div>制片主任签字：</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-block">
                    <div>导演签字：</div>
                    <div class="signature-line"></div>
                </div>
                <div class="signature-block">
                    <div>日期：</div>
                    <div class="signature-line"></div>
                </div>
            </div>
        </div>';
    }
    
    $html .= '
    </body>
    </html>';
    
    return $html;
}
?>