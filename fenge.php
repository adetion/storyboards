<?php
header('Content-Type: application/json; charset=utf-8');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$response = [
    'success' => false,
    'message' => '',
    'data' => []
];

try {
    // 检查请求方法
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('只支持POST请求');
    }

    // 获取POST数据（JSON格式）
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('无效的JSON数据');
    }

    if (!isset($data['image_url'])) {
        throw new Exception('缺少image_url参数');
    }

    $image_url = $data['image_url'];

    // 如果传入了grid_type则使用传入的值，否则尝试自动检测
    if (isset($data['grid_type']) && is_numeric($data['grid_type'])) {
        $grid_type = (int)$data['grid_type'];
        $auto_detected = false;
        $detection_info = [
            'auto_detected' => false,
            'detected_grid_type' => $grid_type,
            'detection_method' => 'manual_input',
            'confidence' => 100
        ];
    } else {
        $grid_type = null;
        $auto_detected = true;
    }

    // 检查GD库
    if (!extension_loaded('gd')) {
        throw new Exception('PHP GD库未安装。请运行: sudo apt-get install php-gd');
    }

    // 创建目录
    $output_dir = __DIR__ . '/outputs/';
    $temp_dir = __DIR__ . '/temp/';

    if (!file_exists($output_dir)) {
        if (!mkdir($output_dir, 0777, true)) {
            throw new Exception('无法创建输出目录');
        }
    }

    if (!file_exists($temp_dir)) {
        if (!mkdir($temp_dir, 0777, true)) {
            throw new Exception('无法创建临时目录');
        }
    }

    // 下载图片
    $temp_file = $temp_dir . uniqid() . '_' . basename(parse_url($image_url, PHP_URL_PATH));

    // 使用cURL下载图片
    $ch = curl_init($image_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $image_data = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code !== 200 || empty($image_data)) {
        throw new Exception('无法下载图片，HTTP代码: ' . $http_code);
    }

    // 保存临时文件
    file_put_contents($temp_file, $image_data);

    // 检查文件类型
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $temp_file);
    finfo_close($finfo);

    if (!in_array($mime_type, $allowed_types)) {
        unlink($temp_file);
        throw new Exception('不支持的文件类型: ' . $mime_type);
    }

    // 加载图片
    $image_info = getimagesize($temp_file);
    if (!$image_info) {
        unlink($temp_file);
        throw new Exception('无法读取图片');
    }

    $mime = $image_info['mime'];

    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $image = imagecreatefromjpeg($temp_file);
            break;
        case 'image/png':
            $image = imagecreatefrompng($temp_file);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($temp_file);
            break;
        default:
            unlink($temp_file);
            throw new Exception('不支持的图片格式');
    }

    if (!$image) {
        unlink($temp_file);
        throw new Exception('无法创建图片资源');
    }

    $width = imagesx($image);
    $height = imagesy($image);

    // 自动检测宫格数（如果未传入grid_type）
    if ($auto_detected) {
        // 使用更可靠的方法：基于图像尺寸比例的智能猜测
        $detection_result = smartGridDetection($image, $width, $height);
        $grid_type = $detection_result['grid_type'];
        $detection_info = [
            'auto_detected' => true,
            'detected_grid_type' => $grid_type,
            'detection_method' => $detection_result['method'],
            'confidence' => $detection_result['confidence'],
            'details' => $detection_result['details'],
            'recommendation' => '建议使用手动指定grid_type参数以获得准确结果'
        ];

        // 自动检测的准确率不高，默认使用9宫格，但提供检测信息
        if ($detection_result['confidence'] < 70) {
            // 如果置信度低，使用默认的9宫格
            $grid_type = 9;
            $detection_info['final_decision'] = '使用默认9宫格（置信度过低）';
        }
    }

    // 验证宫格数
    if (!in_array($grid_type, [4, 6, 9, 16, 25])) {
        $grid_type = 9; // 默认值
    }

    // 计算宫格布局
    $layouts = [
        4 => [2, 2],
        6 => [2, 3],
        9 => [3, 3],
        16 => [4, 4],
        25 => [5, 5]
    ];

    list($rows, $cols) = $layouts[$grid_type];

    // 计算格子大小
    $cell_width = floor($width / $cols);
    $cell_height = floor($height / $rows);

    // 确保格子尺寸至少为1像素
    $cell_width = max(1, $cell_width);
    $cell_height = max(1, $cell_height);

    // 创建唯一输出目录
    $unique_id = uniqid('grid_');
    $output_path = $output_dir . $unique_id . '/';
    mkdir($output_path, 0777, true);

    // 分割图片
    $tiles = [];

    for ($row = 0; $row < $rows; $row++) {
        for ($col = 0; $col < $cols; $col++) {
            $index = $row * $cols + $col + 1;

            // 创建小图
            $tile = imagecreatetruecolor($cell_width, $cell_height);

            // 设置背景色（用于透明图片）
            $background = imagecolorallocate($tile, 255, 255, 255);
            imagefill($tile, 0, 0, $background);

            // 计算源坐标
            $src_x = $col * $cell_width;
            $src_y = $row * $cell_height;

            // 确保源坐标在图片范围内
            $src_x = min($src_x, $width - $cell_width);
            $src_y = min($src_y, $height - $cell_height);

            // 复制部分
            imagecopyresampled(
                $tile,
                $image,
                0,
                0,
                $src_x,
                $src_y,
                $cell_width,
                $cell_height,
                $cell_width,
                $cell_height
            );

            // 保存小图
            $filename = "tile_{$index}.jpg";
            $filepath = $output_path . $filename;

            // 确保保存成功
            if (!imagejpeg($tile, $filepath, 85)) {
                // 尝试使用PNG格式
                $filename = "tile_{$index}.png";
                $filepath = $output_path . $filename;
                imagepng($tile, $filepath);
            }

            imagedestroy($tile);

            // 检查文件是否存在
            if (file_exists($filepath)) {
                $filesize = filesize($filepath);
            } else {
                $filesize = 0;
            }

            $tiles[] = [
                'index' => $index,
                'row' => $row + 1,
                'col' => $col + 1,
                'filename' => $filename,
                'url' => "https://files.yourdomain.com/{$unique_id}/{$filename}",
                'width' => $cell_width,
                'height' => $cell_height,
                'size_bytes' => $filesize
            ];
        }
    }

    // 清理
    imagedestroy($image);
    unlink($temp_file);

    // 生成预览信息
    $preview_info = generatePreviewInfo($unique_id, $tiles);

    // 成功响应
    $response['success'] = true;
    $response['message'] = $auto_detected ?
        '图片分割成功（使用自动检测的宫格数，准确性有限）' :
        '图片分割成功';

    $response['data'] = [
        'grid_type' => $grid_type,
        'grid_layout' => ['rows' => $rows, 'cols' => $cols],
        'total_tiles' => count($tiles),
        'original_size' => ['width' => $width, 'height' => $height, 'ratio' => round($width / $height, 2)],
        'tile_size' => ['width' => $cell_width, 'height' => $cell_height],
        'tiles' => $tiles,
        'output_dir' => $unique_id,
        'detection_info' => $detection_info,
        'preview' => $preview_info,
        'access_urls' => [
            'grid_view' => "https://files.yourdomain.com/{$unique_id}/",
            'api_endpoint' => $_SERVER['REQUEST_URI']
        ],
        'tips' => [
            'auto_detection_notice' => '自动检测宫格数的准确性有限，建议手动指定grid_type参数',
            'supported_grids' => '支持的宫格类型：4(2×2), 6(2×3), 9(3×3), 16(4×4), 25(5×5)',
            'usage_example' => '{"image_url":"URL","grid_type":9}'
        ]
    ];
} catch (Exception $e) {
    http_response_code(400);
    $response['message'] = $e->getMessage();
    // 生产环境隐藏详细错误
    if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        $response['trace'] = $e->getTraceAsString();
    }
}

echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

// ==================== 智能宫格检测函数 ====================

function smartGridDetection($image, $width, $height)
{
    // 方法1：基于图像比例的智能猜测
    $ratio = $width / $height;
    $total_pixels = $width * $height;

    // 常见宫格图片的特征分析
    $results = [];

    // 1. 检查是否接近标准比例
    if (abs($ratio - 1) < 0.1) {
        // 接近正方形
        $results[] = ['grid' => 9, 'score' => 80, 'reason' => '接近正方形(1:1)'];
        $results[] = ['grid' => 4, 'score' => 70, 'reason' => '接近正方形(1:1)'];
        $results[] = ['grid' => 16, 'score' => 60, 'reason' => '接近正方形(1:1)'];
    } elseif ($ratio > 1.4) {
        // 宽图，可能是6宫格(2x3)或16宫格(4x4)
        $results[] = ['grid' => 6, 'score' => 75, 'reason' => '宽图比例(' . round($ratio, 2) . ':1)'];
        $results[] = ['grid' => 16, 'score' => 65, 'reason' => '宽图比例(' . round($ratio, 2) . ':1)'];
    } elseif ($ratio < 0.7) {
        // 长图，可能是6宫格(3x2)或9宫格(3x3)
        $results[] = ['grid' => 6, 'score' => 75, 'reason' => '长图比例(' . round($ratio, 2) . ':1)'];
        $results[] = ['grid' => 9, 'score' => 65, 'reason' => '长图比例(' . round($ratio, 2) . ':1)'];
    } else {
        // 中等比例，可能是4、9、16宫格
        $results[] = ['grid' => 9, 'score' => 70, 'reason' => '中等比例(' . round($ratio, 2) . ':1)'];
        $results[] = ['grid' => 4, 'score' => 65, 'reason' => '中等比例(' . round($ratio, 2) . ':1)'];
        $results[] = ['grid' => 16, 'score' => 55, 'reason' => '中等比例(' . round($ratio, 2) . ':1)'];
    }

    // 2. 基于像素总数猜测
    if ($total_pixels < 500000) {
        // 小图片可能宫格数较少
        foreach ($results as &$result) {
            if ($result['grid'] <= 9) {
                $result['score'] += 10;
            } else {
                $result['score'] -= 10;
            }
        }
    } elseif ($total_pixels > 2000000) {
        // 大图片可能宫格数较多
        foreach ($results as &$result) {
            if ($result['grid'] >= 9) {
                $result['score'] += 10;
            } else {
                $result['score'] -= 10;
            }
        }
    }

    // 3. 简单边缘检测（检查是否有明显边界）
    $edge_score = simpleEdgeDetection($image, $width, $height);
    if ($edge_score > 0.3) {
        // 有明显边界，可能是多宫格
        foreach ($results as &$result) {
            if ($result['grid'] >= 9) {
                $result['score'] += 15;
            }
        }
    }

    // 按分数排序
    usort($results, function ($a, $b) {
        return $b['score'] - $a['score'];
    });

    $best_result = $results[0];

    return [
        'grid_type' => $best_result['grid'],
        'method' => 'ratio_based_estimation',
        'confidence' => min(100, $best_result['score']),
        'details' => [
            'image_ratio' => round($ratio, 3),
            'total_pixels' => $total_pixels,
            'edge_score' => $edge_score,
            'all_candidates' => $results,
            'note' => '自动检测仅供参考，准确率有限'
        ]
    ];
}

function simpleEdgeDetection($image, $width, $height)
{
    // 简化的边缘检测，计算颜色变化强度
    $sample_size = 1000;
    $total_diff = 0;
    $samples = 0;

    // 随机采样
    for ($i = 0; $i < $sample_size; $i++) {
        $x = rand(1, $width - 2);
        $y = rand(1, $height - 2);

        // 获取中心点颜色
        $center = getPixelColor($image, $x, $y);

        // 获取上下左右四个点的颜色
        $up = getPixelColor($image, $x, $y - 1);
        $down = getPixelColor($image, $x, $y + 1);
        $left = getPixelColor($image, $x - 1, $y);
        $right = getPixelColor($image, $x + 1, $y);

        // 计算平均差异
        $diff_up = colorDifference($center, $up);
        $diff_down = colorDifference($center, $down);
        $diff_left = colorDifference($center, $left);
        $diff_right = colorDifference($center, $right);

        $avg_diff = ($diff_up + $diff_down + $diff_left + $diff_right) / 4;
        $total_diff += $avg_diff;
        $samples++;
    }

    if ($samples == 0) return 0;

    $avg_total_diff = $total_diff / $samples;

    // 标准化到0-1范围
    return min(1.0, $avg_total_diff / 100);
}

function getPixelColor($image, $x, $y)
{
    if ($x < 0 || $x >= imagesx($image) || $y < 0 || $y >= imagesy($image)) {
        return ['r' => 0, 'g' => 0, 'b' => 0];
    }

    $color = imagecolorat($image, $x, $y);
    return [
        'r' => ($color >> 16) & 0xFF,
        'g' => ($color >> 8) & 0xFF,
        'b' => $color & 0xFF
    ];
}

function colorDifference($color1, $color2)
{
    // 计算颜色差异（欧几里得距离）
    $r_diff = $color1['r'] - $color2['r'];
    $g_diff = $color1['g'] - $color2['g'];
    $b_diff = $color1['b'] - $color2['b'];

    return sqrt($r_diff * $r_diff + $g_diff * $g_diff + $b_diff * $b_diff);
}

function generatePreviewInfo($unique_id, $tiles)
{
    // 生成预览信息
    $preview_html = '<div style="display: grid; grid-template-columns: repeat(' . ceil(sqrt(count($tiles))) . ', 1fr); gap: 2px;">';

    foreach ($tiles as $tile) {
        $preview_html .= sprintf(
            '<div style="border: 1px solid #ccc; padding: 5px; text-align: center;">
                <img src="%s" style="max-width: 100px; max-height: 100px;"/><br>
                <small>%d (%d,%d)</small>
            </div>',
            $tile['url'],
            $tile['index'],
            $tile['row'],
            $tile['col']
        );
    }

    $preview_html .= '</div>';

    return [
        'html' => $preview_html,
        'grid_view_url' => "https://files.yourdomain.com/{$unique_id}/",
        'download_all' => "https://files.yourdomain.com/{$unique_id}/download_all.zip"
    ];
}
