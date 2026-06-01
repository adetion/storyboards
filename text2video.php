<?php
// 启动会话
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 检查用户是否登录
if (!isset($_SESSION['user_id']) || !isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // 未登录用户，重定向到首页
    header('Location: index.html');
    exit(0);
}

// 引入统一任务管理器和配置文件
require_once __DIR__ . '/TaskManager.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/VideoGenerator.php';

// 初始化任务管理器和视频生成器
$taskManager = TaskManager::getInstance();
$videoGenerator = VideoGenerator::getInstance();

// 获取当前用户ID
$userId = $_SESSION['user_id'];

// 流程步骤详情
$processStepDetails = [
    '1. 文本生成剧本（文生文）：将输入的文本描述转换为标准剧本格式',
    '2. 剧本生成分镜（文生文）：将剧本分析并转换为分镜脚本',
    '3. 分镜提炼所有角色三视图（文生图）：从分镜中提取角色信息并生成正面、侧面、背面视图',
    '4. 分镜提炼所有分镜场景图（文生图）：从分镜中提取场景信息并生成场景参考图',
    '5. 单分镜生成参考图（文生图）：为单个分镜生成视觉参考图片',
    '6. 单分镜参考图生成多宫格图（图生图）：将单分镜参考图生成为多宫格图',
    '7. 单分镜多宫格图分割成分镜切片图（图片切割）：将多宫格图分割成单个切片图',
    '8. 单分镜多宫格图切片图切片提示词生成（图片识别）：为每个切片图生成对应的提示词',
    '9. 单分镜多宫格切片图与切片提示词一对一对应生成连贯切片视频（图生视频）：为每个切片生成视频片段',
    '10. 单分镜连贯切片视频合并（视频合成）：将多个切片视频合并为完整的分镜视频'
];

?>

<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>智影工场 - 文本/图片转视频</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/scripts_style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="css/menu.css">
    <style>
        /* 全局样式重置和基础设置 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* 输入方法组样式 */
        .input-method-group {
            margin-bottom: 25px;
        }
        
        .input-method-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        
        /* 单选组样式 */
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        /* 表单组样式 */
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        
        /* 表单控件样式 */
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 15px;
            transition: all 0.3s ease;
            background-color: #ffffff;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #007bff;
            box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 180px;
            line-height: 1.5;
        }
        
        /* 字符计数 */
        .char-count {
            margin-top: 8px;
            font-size: 14px;
            color: #666;
            text-align: right;
        }
        
        /* 单选标签样式 */
        .radio-options {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            transition: all 0.3s ease;
            background-color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .radio-option:hover {
            border-color: #007bff;
            background-color: #f0f8ff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
        }
        
        .radio-option input[type="radio"] {
            display: none;
        }
        
        .radio-option input[type="radio"]:checked {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        
        .radio-option input[type="radio"]:checked + .radio-label {
            font-weight: 600;
            color: #007bff;
        }
        
        .radio-option input[type="radio"]:checked + .radio-label::before {
            content: '';
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: #007bff;
            margin-right: 8px;
        }
        
        /* 滑动条样式 */
        .slider-container {
            margin-top: 12px;
        }
        
        .slider {
            width: 100%;
            height: 10px;
            border-radius: 5px;
            background: #e2e8f0;
            outline: none;
            -webkit-appearance: none;
            appearance: none;
            transition: all 0.3s ease;
        }
        
        .slider:hover {
            background: #cbd5e0;
        }
        
        .slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            appearance: none;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #007bff;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
        }
        
        .slider::-webkit-slider-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 6px rgba(0, 123, 255, 0.3);
        }
        
        .slider::-moz-range-thumb {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background: #007bff;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.2);
        }
        
        .slider::-moz-range-thumb:hover {
            transform: scale(1.1);
            box-shadow: 0 3px 6px rgba(0, 123, 255, 0.3);
        }
        
        .slider-value {
            margin-top: 12px;
            font-size: 15px;
            font-weight: 600;
            color: #007bff;
            text-align: center;
            background-color: #f8f9fa;
            padding: 8px 16px;
            border-radius: 20px;
            display: inline-block;
        }
        
        /* 复选框样式 */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 12px;
        }
        
        .checkbox-option {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            padding: 10px 20px;
            border: 2px solid #e2e8f0;
            border-radius: 25px;
            transition: all 0.3s ease;
            background-color: #ffffff;
            font-size: 14px;
            font-weight: 500;
            color: #4a5568;
        }
        
        .checkbox-option:hover {
            border-color: #007bff;
            background-color: #f0f8ff;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 123, 255, 0.1);
        }
        
        .checkbox-option input[type="checkbox"] {
            display: none;
        }
        
        .checkbox-custom {
            width: 20px;
            height: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 4px;
            display: inline-block;
            position: relative;
            transition: all 0.3s ease;
            background-color: #ffffff;
        }
        
        .checkbox-option:hover .checkbox-custom {
            border-color: #007bff;
        }
        
        .checkbox-option input[type="checkbox"]:checked + .checkbox-custom {
            background-color: #007bff;
            border-color: #007bff;
        }
        
        .checkbox-option input[type="checkbox"]:checked + .checkbox-custom::after {
            content: '✓';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 14px;
            font-weight: bold;
        }
        
        .checkbox-option input[type="checkbox"]:checked {
            border-color: #007bff;
            background-color: #e3f2fd;
        }
        
        /* 任务进度样式 */
        .step-status {
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .step-status.pending {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .step-status.processing {
            background-color: #d1ecf1;
            color: #0c5460;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(12, 84, 96, 0.4);
            }
            70% {
                box-shadow: 0 0 0 10px rgba(12, 84, 96, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(12, 84, 96, 0);
            }
        }
        
        .step-status.completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .step-status.failed {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        /* 文件上传样式 */
        .file-upload-section {
            margin-top: 15px;
            padding: 20px;
            border: 2px dashed #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }
        
        .file-upload-section:hover {
            border-color: #007bff;
            background-color: #f0f8ff;
        }
        
        .file-upload-header {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .custom-file-upload {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 123, 255, 0.2);
        }
        
        .custom-file-upload:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.3);
        }
        
        input[type="file"] {
            display: none;
        }
        
        #uploadedImageName {
            flex: 1;
            font-weight: 600;
            color: #28a745;
            font-size: 15px;
        }
        
        .file-info {
            margin-top: 10px;
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        
        /* 图片预览样式 */
        .preview-section {
            margin-top: 20px;
            padding: 20px;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .preview-section h4 {
            margin-bottom: 15px;
            color: #333;
            font-size: 16px;
            font-weight: 600;
        }
        
        .image-preview-container {
            text-align: center;
            padding: 20px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }
        
        #imagePreview {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        #imagePreview:hover {
            transform: scale(1.02);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        #imageInfo {
            margin-top: 15px;
            font-size: 14px;
            color: #666;
            line-height: 1.4;
        }
        
        /* 积分信息样式 */
        .points-info {
            margin: 20px 0;
            padding: 15px 20px;
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .points-info i {
            color: #f59e0b;
            font-size: 20px;
        }
        
        .points-info strong {
            color: #d68910;
            font-weight: 600;
        }
        
        /* 内容布局 */
        .content {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }
        
        .input-section {
            flex: 1;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        .result-section {
            flex: 1;
            background-color: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            border: 1px solid #e2e8f0;
        }
        
        /* 按钮样式 */
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.3);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #5a6268 0%, #495057 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(108, 117, 125, 0.3);
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .btn-danger:hover {
            background: linear-gradient(135deg, #c82333 0%, #a71e2a 100%);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(220, 53, 69, 0.3);
        }
        
        /* 按钮组 */
        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        /* 提示信息样式 */
        .error {
            margin: 15px 0;
            padding: 15px 20px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            color: #721c24;
            display: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .success {
            margin: 15px 0;
            padding: 15px 20px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            color: #155724;
            display: none;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* 处理状态样式 */
        .processing-state {
            margin: 25px 0;
            padding: 25px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: none;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .spinner {
            border: 4px solid rgba(0, 123, 255, 0.1);
            border-radius: 50%;
            border-top: 4px solid #007bff;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 25px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .processing-content h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .processing-content p {
            margin-bottom: 20px;
            color: #666;
        }
        
        /* 进度条样式 */
        .progress-bar {
            width: 100%;
            height: 12px;
            background: #e9ecef;
            border-radius: 6px;
            overflow: hidden;
            margin: 15px 0;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #007bff 0%, #0056b3 100%);
            border-radius: 6px;
            width: 0%;
            transition: width 0.5s ease;
            box-shadow: 0 0 10px rgba(0, 123, 255, 0.3);
        }
        
        /* 小文本 */
        .small-text {
            font-size: 14px;
            color: #666;
            margin: 10px 0;
        }
        
        /* 实时结果 */
        .real-time-result {
            margin-top: 25px;
            padding: 20px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .real-time-result h4 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #333;
        }
        
        .real-time-result-content {
            min-height: 120px;
            border: 1px solid #f0f0f0;
            padding: 15px;
            border-radius: 8px;
            font-size: 15px;
            line-height: 1.6;
            background-color: #f8f9fa;
        }
        
        /* 处理操作 */
        .processing-actions {
            margin-top: 25px;
            display: flex;
            gap: 12px;
        }
        
        /* 结果区域 */
        .result-section h2 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .result-container {
            padding: 25px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            min-height: 300px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .result-container p {
            color: #6c757d;
            font-size: 16px;
            line-height: 1.5;
        }
        
        /* 视频结果 */
        .video-result {
            margin-top: 25px;
            padding: 25px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .video-player {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
        }
        
        .video-player video {
            width: 100%;
            height: auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        /* 标签容器 */
        .tab-container {
            margin-top: 25px;
        }
        
        /* 标签样式 */
        .tabs {
            display: flex;
            border-bottom: 2px solid var(--border-color);
            margin-bottom: var(--spacing-xl);
            gap: 2px;
        }
        
        .tab {
            padding: var(--spacing-sm) var(--spacing-lg);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 500;
            color: var(--text-secondary);
            background-color: var(--background-light);
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }
        
        .tab:hover {
            background-color: var(--background-hover);
            color: var(--text-primary);
        }
        
        .tab.active {
            border-bottom-color: var(--primary-color);
            font-weight: 600;
            color: var(--primary-color);
            background-color: var(--background-light);
        }
        
        /* 标签内容 */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* 历史任务 */
        .history-list {
            margin-top: var(--spacing-lg);
        }
        
        .history-item {
            padding: var(--spacing-md);
            background: var(--background-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .history-item:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-1px);
        }
        
        .history-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--spacing-md);
        }
        
        .history-item-title {
            font-weight: 600;
            font-size: 16px;
            color: var(--text-primary);
        }
        
        .history-item-status {
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-completed {
            background: var(--success-background);
            color: var(--success-color);
        }
        
        .status-processing {
            background: var(--info-background);
            color: var(--info-color);
        }
        
        .status-failed {
            background: var(--error-background);
            color: var(--error-color);
        }
        
        .history-item-body {
            font-size: 15px;
            line-height: 1.6;
            color: var(--text-secondary);
        }
        
        .history-item-footer {
            margin-top: var(--spacing-md);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: var(--text-secondary);
        }
        
        .history-item-actions {
            display: flex;
            gap: var(--spacing-xs);
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-secondary);
            background-color: var(--background-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
        }
        
        .empty-state i {
            font-size: 48px;
            color: var(--border-color);
            margin-bottom: var(--spacing-md);
        }
        
        .empty-state h3 {
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }
        
        /* 操作按钮 */
        .action-buttons {
            margin-bottom: var(--spacing-lg);
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .secondary-button {
            padding: var(--spacing-xs) var(--spacing-md);
            background: var(--secondary-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .secondary-button:hover {
            background: var(--secondary-dark);
            transform: translateY(-1px);
        }
        
        .danger-button {
            padding: var(--spacing-xs) var(--spacing-md);
            background: var(--error-color);
            color: white;
            border: none;
            border-radius: var(--border-radius);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .danger-button:hover {
            background: var(--error-dark);
            transform: translateY(-1px);
        }
        
        /* 任务进度区域 */
        .task-progress-section {
            margin-top: var(--spacing-lg);
            padding: var(--spacing-lg);
            background-color: var(--background-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .task-progress-section h4 {
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
        }
        
        #taskProgressInfo {
            min-height: 120px;
            padding: var(--spacing-md);
            background-color: var(--background-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        #taskStepsList {
            margin-top: var(--spacing-md);
            display: none;
        }
        
        #taskStepsList h5 {
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
        }
        
        #taskStepsList ul {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: var(--background-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            overflow: hidden;
        }
        
        #taskStepsList li {
            padding: var(--spacing-sm);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        #taskStepsList li:hover {
            background-color: var(--background-hover);
        }
        
        #taskStepsList li:last-child {
            border-bottom: none;
        }
        
        /* 预览区域 */
        #previewSection {
            margin-top: var(--spacing-lg);
            padding: var(--spacing-lg);
            background-color: var(--background-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        #previewSection h3 {
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
        }
        
        #previewResult {
            min-height: 300px;
            padding: var(--spacing-lg);
            background: var(--background-light);
            border: 1px solid var(--border-color);
            border-radius: var(--border-radius);
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* 流程说明 */
        #process-steps h2 {
            margin-bottom: var(--spacing-lg);
            color: var(--text-primary);
        }
        
        .process-steps-container {
            padding: var(--spacing-lg);
            background: var(--background-light);
            border-radius: var(--border-radius-lg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .process-steps-container h3 {
            margin-bottom: var(--spacing-md);
            color: var(--text-primary);
        }
        
        .process-steps-container ol {
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .process-steps-container li {
            margin: var(--spacing-md) 0;
            color: var(--text-secondary);
        }
        
        .process-info {
            margin-top: var(--spacing-lg);
            padding: var(--spacing-md);
            background: var(--background-light);
            border-radius: var(--border-radius);
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .process-info h4 {
            margin-bottom: var(--spacing-sm);
            color: var(--text-primary);
        }
        
        .process-info p {
            margin-bottom: var(--spacing-sm);
            color: var(--text-secondary);
        }
        
        .process-info ul {
            margin-bottom: var(--spacing-sm);
            padding-left: 20px;
        }
        
        .process-info li {
            margin: 8px 0;
            color: var(--text-secondary);
        }
        
        /* 响应式设计 */
        @media (max-width: 992px) {
            .content {
                flex-direction: column;
                gap: var(--spacing-md);
            }
            
            .input-section,
            .result-section {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: var(--spacing-sm);
            }
            
            .input-section,
            .result-section {
                padding: var(--spacing-md);
            }
            
            .radio-group {
                flex-direction: column;
                gap: var(--spacing-sm);
            }
            
            .radio-options {
                gap: var(--spacing-xs);
            }
            
            .radio-option {
                padding: var(--spacing-xs) var(--spacing-md);
                font-size: 13px;
            }
            
            .checkbox-group {
                gap: var(--spacing-xs);
            }
            
            .checkbox-option {
                padding: var(--spacing-xs) var(--spacing-md);
                font-size: 13px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .processing-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .history-item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .history-item-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .history-item-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .tab {
                flex: 0 0 auto;
                padding: var(--spacing-xs) var(--spacing-md);
                font-size: 14px;
            }
            
            .video-player {
                max-width: 100%;
            }
            
            .file-upload-header {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .custom-file-upload {
                width: 100%;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .function-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .function-right {
                width: 100%;
            }
            
            .form-control {
                font-size: 16px;
            }
            
            .file-upload-section {
                padding: var(--spacing-sm);
            }
            
            .image-preview-container {
                padding: var(--spacing-sm);
            }
            
            #imagePreview {
                max-height: 200px;
            }
            
            .progress-bar {
                height: 10px;
            }
            
            .processing-content {
                padding: var(--spacing-md);
            }
            
            .real-time-result-content {
                font-size: 16px;
            }
            
            .task-progress-section {
                padding: var(--spacing-md);
            }
            
            #taskProgressInfo {
                padding: var(--spacing-sm);
            }
        }
     .tabs {
            display: flex;
            border-bottom: 2px solid #e2e8f0;
            margin-bottom: 30px;
            gap: 2px;
        }
        
        .tab {
            padding: 15px 25px;
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
            font-size: 16px;
            font-weight: 500;
            color: #666;
            background-color: #f8f9fa;
            border-radius: 8px 8px 0 0;
        }
        
        .tab:hover {
            background-color: #e9ecef;
            color: #333;
        }
        
        .tab.active {
            border-bottom-color: #007bff;
            font-weight: 600;
            color: #007bff;
            background-color: #ffffff;
        }
        
        /* 标签内容 */
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        /* 历史任务 */
        .history-list {
            margin-top: 25px;
        }
        
        .history-item {
            padding: 20px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .history-item:hover {
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .history-item-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .history-item-title {
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }
        
        .history-item-status {
            padding: 6px 14px;
            border-radius: 16px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-processing {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }
        
        .history-item-body {
            font-size: 15px;
            line-height: 1.6;
            color: #4a5568;
        }
        
        .history-item-footer {
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            color: #666;
        }
        
        .history-item-actions {
            display: flex;
            gap: 10px;
        }
        
        /* 空状态 */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
            background-color: #f8f9fa;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        
        .empty-state i {
            font-size: 48px;
            color: #ced4da;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            color: #495057;
        }
        
        /* 操作按钮 */
        .action-buttons {
            margin-bottom: 25px;
            display: flex;
            gap: 12px;
        }
        
        .secondary-button {
            padding: 10px 20px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .secondary-button:hover {
            background: #5a6268;
            transform: translateY(-1px);
        }
        
        .danger-button {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .danger-button:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
        /* 任务进度区域 */
        .task-progress-section {
            margin-top: 25px;
            padding: 25px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .task-progress-section h4 {
            margin-bottom: 20px;
            color: #333;
        }
        
        #taskProgressInfo {
            min-height: 120px;
            padding: 20px;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        #taskStepsList {
            margin-top: 20px;
            display: none;
        }
        
        #taskStepsList h5 {
            margin-bottom: 15px;
            color: #4a5568;
        }
        
        #taskStepsList ul {
            list-style: none;
            padding: 0;
            margin: 0;
            background-color: white;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            overflow: hidden;
        }
        
        #taskStepsList li {
            padding: 12px 16px;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }
        
        #taskStepsList li:hover {
            background-color: #f8f9fa;
        }
        
        #taskStepsList li:last-child {
            border-bottom: none;
        }
        
        /* 预览区域 */
        #previewSection {
            margin-top: 25px;
            padding: 25px;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        #previewSection h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        #previewResult {
            min-height: 300px;
            padding: 25px;
            background: white;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            display: flex;
            justify-content: center;
            align-items: center;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        /* 流程说明 */
        #process-steps h2 {
            margin-bottom: 25px;
            color: #333;
        }
        
        .process-steps-container {
            padding: 25px;
            background: #f8f9fa;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .process-steps-container h3 {
            margin-bottom: 20px;
            color: #333;
        }
        
        .process-steps-container ol {
            line-height: 1.8;
            padding-left: 20px;
        }
        
        .process-steps-container li {
            margin: 15px 0;
            color: #4a5568;
        }
        
        .process-info {
            margin-top: 25px;
            padding: 20px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .process-info h4 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .process-info p {
            margin-bottom: 15px;
            color: #4a5568;
        }
        
        .process-info ul {
            margin-bottom: 15px;
            padding-left: 20px;
        }
        
        .process-info li {
            margin: 8px 0;
            color: #4a5568;
        }
        
        /* 响应式设计 */
        @media (max-width: 992px) {
            .content {
                flex-direction: column;
                gap: 20px;
            }
            
            .input-section,
            .result-section {
                width: 100%;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                padding: 15px;
            }
            
            .input-section,
            .result-section {
                padding: 20px;
            }
            
            .radio-group {
                flex-direction: column;
                gap: 12px;
            }
            
            .radio-options {
                gap: 10px;
            }
            
            .radio-option {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .checkbox-group {
                gap: 10px;
            }
            
            .checkbox-option {
                padding: 8px 16px;
                font-size: 13px;
            }
            
            .btn-group {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .processing-actions {
                flex-direction: column;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .history-item-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .history-item-footer {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
            
            .history-item-actions {
                width: 100%;
                flex-direction: column;
            }
            
            .tabs {
                overflow-x: auto;
                white-space: nowrap;
            }
            
            .tab {
                flex: 0 0 auto;
                padding: 10px 16px;
                font-size: 14px;
            }
            
            .video-player {
                max-width: 100%;
            }
            
            .file-upload-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .custom-file-upload {
                width: 100%;
                text-align: center;
            }
        }
        
        @media (max-width: 480px) {
            .function-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .function-right {
                width: 100%;
            }
            
            .form-control {
                font-size: 16px;
            }
            
            .file-upload-section {
                padding: 15px;
            }
            
            .image-preview-container {
                padding: 15px;
            }
            
            #imagePreview {
                max-height: 200px;
            }
            
            .progress-bar {
                height: 10px;
            }
            
            .processing-content {
                padding: 20px;
            }
            
            .real-time-result-content {
                font-size: 16px;
            }
            
            .task-progress-section {
                padding: 20px;
            }
            
            #taskProgressInfo {
                padding: 15px;
            }
        }
    </style>
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>

    <!-- 功能区 -->
    <div class="function-bar">
        <div class="function-left">
            <div class="function-tab active">文本/图片转视频</div>
        </div>
        <div class="function-right">
            <div class="btn-group">
            </div>
        </div>
    </div>

    <div class="main-content">
        <div class="content">
            <div class="input-section">
                <div class="tab-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="new-task">创建任务</div>
                        <div class="tab" data-tab="process-steps">流程说明</div>
                        <div class="tab" data-tab="history">历史任务</div>
                    </div>

                    <div class="tab-content active" id="new-task">
                        <h2>输入区域</h2>

                        <!-- 题材选择 -->
                        <div class="form-group">
                            <label>题材选择（可多选）：</label>
                            <div class="checkbox-group">
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="action">
                                    <span class="checkbox-custom"></span>
                                    动作
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="comedy">
                                    <span class="checkbox-custom"></span>
                                    喜剧
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="drama">
                                    <span class="checkbox-custom"></span>
                                    剧情
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="scifi">
                                    <span class="checkbox-custom"></span>
                                    科幻
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="fantasy">
                                    <span class="checkbox-custom"></span>
                                    奇幻
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="romance">
                                    <span class="checkbox-custom"></span>
                                    爱情
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="horror">
                                    <span class="checkbox-custom"></span>
                                    恐怖
                                </label>
                                <label class="checkbox-option">
                                    <input type="checkbox" name="genres" value="animation">
                                    <span class="checkbox-custom"></span>
                                    动画
                                </label>
                            </div>
                        </div>

                        <!-- 输入类型选择 -->
                        <div class="input-method-group">
                            <label>输入类型：</label>
                            <div class="radio-group">
                                <label class="radio-option">
                                    <input type="radio" name="inputType" value="text" id="textInputType" checked>
                                    <span class="radio-custom"></span>
                                    文本描述
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="inputType" value="image" id="imageInputType">
                                    <span class="radio-custom"></span>
                                    图片上传
                                </label>
                            </div>
                        </div>

                        <!-- 文本描述输入区域（默认显示） -->
                        <div class="form-group" id="textInputSection">
                            <label for="textPrompt">视频描述：</label>
                            <textarea id="textPrompt" class="form-control" placeholder="请输入详细的视频描述，例如：黑神话悟空邂逅黑神话钟馗，二人发起了一场辩论大赛，辩论主题是：论黑神话的爹到底是谁？">黑神话悟空邂逅黑神话钟馗，二人发起了一场辩论大赛，辩论主题是：论黑神话的爹到底是谁？</textarea>
                            <div class="char-count">
                                <span id="charCount">0</span> 字符
                            </div>
                        </div>

                        <!-- 图片上传区域（默认隐藏） -->
                        <div class="form-group" id="imageInputSection" style="display: none;">
                            <label>上传图片：</label>
                            <div class="file-upload-section" style="position: relative; z-index: 1;">
                                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                                    <label for="imageFile" class="custom-file-upload" style="flex: 0 0 auto;">
                                        <i class="fas fa-file-image"></i> 选择图片
                                    </label>
                                    <div id="uploadedImageName" style="flex: 1; align-self: center; font-weight: 600; color: #28a745;"></div>
                                </div>
                                <input type="file" id="imageFile" accept=".jpg,.jpeg,.png,.gif">
                                <div class="file-info">支持JPG、JPEG、PNG、GIF格式图片，单张不超过5MB</div>
                                <div class="preview-section" id="imagePreviewSection" style="margin-top: 20px; display: none;">
                                    <h4>图片预览：</h4>
                                    <div class="image-preview-container" id="imagePreviewContainer">
                                        <img id="imagePreview" src="" alt="图片预览">
                                        <div id="imageInfo"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 视频配置 -->
                        <div class="form-group">
                            <label>宫格模式：</label>
                            <div class="radio-options">
                                <label class="radio-option">
                                    <input type="radio" name="gridSize" value="2x2" data-count="4">
                                    <span class="radio-label">四宫格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gridSize" value="3x3" data-count="9" checked>
                                    <span class="radio-label">九宫格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gridSize" value="4x4" data-count="16">
                                    <span class="radio-label">十六宫格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gridSize" value="5x5" data-count="25">
                                    <span class="radio-label">二十五宫格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="gridSize" value="6x6" data-count="36">
                                    <span class="radio-label">三十六宫格</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="sliceDuration">每个切片时长（秒）：</label>
                            <div class="slider-container">
                                <input type="range" id="sliceDuration" class="slider" min="5" max="12" value="8" step="0.5">
                                <div class="slider-value">当前值：<span id="sliceDurationValue">8</span>秒</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>视频风格：</label>
                            <div class="radio-options">
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="realistic" checked>
                                    <span class="radio-label">写实风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="animation">
                                    <span class="radio-label">动画风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="cinematic">
                                    <span class="radio-label">电影风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="artistic">
                                    <span class="radio-label">艺术风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="fantasy">
                                    <span class="radio-label">奇幻风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="sci-fi">
                                    <span class="radio-label">科幻风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="vintage">
                                    <span class="radio-label">复古风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="comic">
                                    <span class="radio-label">漫画风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="anime">
                                    <span class="radio-label">动漫风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="digital-art">
                                    <span class="radio-label">数字艺术</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="oil-painting">
                                    <span class="radio-label">油画风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="watercolor">
                                    <span class="radio-label">水彩风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="sketch">
                                    <span class="radio-label">素描风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="pixel-art">
                                    <span class="radio-label">像素风格</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoStyle" value="3d-render">
                                    <span class="radio-label">3D渲染</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>视频分辨率：</label>
                            <div class="radio-options">
                                <label class="radio-option">
                                    <input type="radio" name="videoResolution" value="720p" checked>
                                    <span class="radio-label">720p (1280x720)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoResolution" value="1080p">
                                    <span class="radio-label">1080p (1920x1080)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoResolution" value="480p">
                                    <span class="radio-label">480p (854x480)</span>
                                </label>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>视频宽高比：</label>
                            <div class="radio-options">
                                <label class="radio-option">
                                    <input type="radio" name="videoAspectRatio" value="16:9" checked>
                                    <span class="radio-label">16:9 (宽屏)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoAspectRatio" value="9:16">
                                    <span class="radio-label">9:16 (竖屏)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoAspectRatio" value="1:1">
                                    <span class="radio-label">1:1 (正方形)</span>
                                </label>
                                <label class="radio-option">
                                    <input type="radio" name="videoAspectRatio" value="4:3">
                                    <span class="radio-label">4:3 (标准)</span>
                                </label>
                            </div>
                        </div>

                        <div class="points-info">
                            <i class="fas fa-coins"></i> 视频生成每次消耗 <strong><?php echo Config::VIDEO_GENERATION_COST; ?> 积分</strong>
                        </div>
                        
                        <!-- 任务进度显示区域 -->
                        <div class="task-progress-section">
                            <h4>任务进度</h4>
                            <div id="taskProgressInfo">
                                <p style="color: var(--text-secondary); text-align: center;">尚未开始任务</p>
                            </div>
                            <div id="taskStepsList">
                                <h5>执行步骤</h5>
                                <ul>
                                    <li>
                                        <span>1. 文本生成剧本</span>
                                        <span id="step1-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>2. 剧本生成分镜</span>
                                        <span id="step2-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>3. 生成角色三视图</span>
                                        <span id="step3-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>4. 生成场景图</span>
                                        <span id="step4-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>5. 生成分镜参考图</span>
                                        <span id="step5-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>6. 生成多宫格图</span>
                                        <span id="step6-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>7. 分割图片</span>
                                        <span id="step7-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>8. 生成切片提示词</span>
                                        <span id="step8-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>9. 生成切片视频</span>
                                        <span id="step9-status" class="step-status">等待中</span>
                                    </li>
                                    <li>
                                        <span>10. 合并视频</span>
                                        <span id="step10-status" class="step-status">等待中</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <div style="display: flex; gap: 10px; margin-top: 15px;">
                            <button id="previewBtn" class="btn btn-secondary">预览效果</button>
                            <button id="generateBtn" class="btn btn-primary">生成视频</button>
                        </div>
                        
                        <div id="previewSection" style="margin-top: 20px; display: none;">
                            <h3>视频预览</h3>
                            <div id="previewResult" style="min-height: 200px; border: 1px solid var(--border-color); border-radius: var(--border-radius); padding: var(--spacing-md); background: var(--background-light);">
                                <p style="color: var(--text-secondary); text-align: center;">点击"预览效果"按钮生成视频预览</p>
                            </div>
                        </div>

                        <div class="processing-state" id="progress" style="display: none;">
                            <div class="processing-content">
                                <div class="spinner"></div>
                                <h3>视频生成中</h3>
                                <p id="progressInfo">正在启动视频生成任务...</p>
                                <div class="progress-bar">
                                    <div class="progress-fill" id="progressBar" style="width: 0%"></div>
                                </div>
                                <div class="process-steps-status" style="margin-top: 20px;">
                                    <h4>当前流程步骤：</h4>
                                    <ul id="processStepsList" style="list-style: none; padding: 0;">
                                        <?php foreach ($processStepDetails as $index => $step): ?>
                                            <li id="step-<?php echo $index + 1; ?>" style="margin: 5px 0; padding: 5px; border-radius: var(--border-radius-sm); font-size: 12px;">
                                                <span><?php echo $step; ?></span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                                <p class="small-text">任务ID: <span id="processingTaskId"></span></p>
                                <p class="small-text">您可以保存此Task ID，稍后回来查询结果</p>
                                <div class="processing-actions">
                                    <button type="button" class="btn btn-secondary" onclick="checkStatusAgain()">
                                        <i class="fas fa-sync-alt"></i> 刷新状态
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="copyProcessingTaskId()">
                                        <i class="fas fa-copy"></i> 复制Task ID
                                    </button>
                                    <button type="button" class="btn btn-danger" onclick="cancelTask()">
                                        <i class="fas fa-times"></i> 取消任务
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="error" id="error"></div>
                        <div class="success" id="success">视频生成完成！结果已显示在右侧。</div>
                    </div>

                    <div class="tab-content" id="process-steps">
                        <h2>流程说明</h2>
                        <div class="process-steps-container">
                            <h3>视频生成完整流程</h3>
                            <ol style="line-height: 1.8;">
                                <?php foreach ($processStepDetails as $step): ?>
                                    <li style="margin: 10px 0;"><?php echo $step; ?></li>
                                <?php endforeach; ?>
                            </ol>
                            <div class="process-info">
                                <h4>流程说明：</h4>
                                <p>1. 整个流程分为两个主要阶段：</p>
                                <ul>
                                    <li><strong>第一阶段（步骤1-4）</strong>：从输入内容生成基础素材，包括剧本、分镜、角色和场景图</li>
                                    <li><strong>第二阶段（步骤5-10）</strong>：对每个分镜进行单独处理，最终生成完整视频</li>
                                </ul>
                                <p>2. 处理时间：</p>
                                <ul>
                                    <li>整个流程可能需要较长时间，具体取决于输入内容的复杂度和分镜数量</li>
                                    <li>请耐心等待，系统会自动完成所有步骤</li>
                                </ul>
                                <p>3. 注意事项：</p>
                                <ul>
                                    <li>输入描述越详细，生成结果越符合预期</li>
                                    <li>图片输入会先进行识别，然后转换为文字描述再进入流程</li>
                                    <li>生成过程中请勿关闭浏览器窗口，您可以最小化窗口等待结果</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="tab-content" id="history">
                        <h2>历史任务记录</h2>
                        <div class="action-buttons">
                            <button id="refreshHistoryBtn" class="secondary-button">刷新</button>
                            <button id="deleteAllBtn" class="danger-button">删除全部</button>
                        </div>
                        <div style="display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap;">
                            <div style="flex: 1; min-width: 200px;">
                                <input type="text" id="searchInput" placeholder="搜索任务..." class="form-control">
                            </div>
                            <div style="flex: 0 0 auto;">
                                <select id="statusFilter" class="form-control">
                                    <option value="all">所有状态</option>
                                    <option value="pending">待处理</option>
                                    <option value="processing">处理中</option>
                                    <option value="completed">已完成</option>
                                    <option value="failed">失败</option>
                                    <option value="cancelled">已取消</option>
                                </select>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <select id="priorityFilter" class="form-control">
                                    <option value="all">所有优先级</option>
                                    <option value="high">高</option>
                                    <option value="normal">正常</option>
                                    <option value="low">低</option>
                                </select>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <button id="applyFiltersBtn" class="btn btn-secondary" style="height: 38px;">应用筛选</button>
                            </div>
                        </div>
                        <div class="history-list" id="historyList">
                            <div class="empty-state">暂无历史任务</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="result-section">
                <h2>生成结果</h2>
                <div class="result-container" id="result">
                    <p style="color: #7f8c8d; text-align: center; margin-top: 50px; font-size: 0.9rem;">
                        生成的视频结果将在此处显示...
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- 底部版权声明栏 -->
    <?php include 'footer.html'; ?>
</div>

<script>
    // 获取当前用户ID
    window.currentUserId = <?php echo $userId; ?>;

    // 页面加载完成后初始化
    document.addEventListener('DOMContentLoaded', function() {
        // 检查是否有保存的任务ID，如果有则恢复任务
        const savedTaskId = localStorage.getItem('text2video_current_task');
        if (savedTaskId) {
            // 显示处理状态
            const progress = document.getElementById('progress');
            progress.style.display = 'block';
            document.getElementById('processingTaskId').textContent = savedTaskId;
            
            // 恢复任务状态监控
            pollTaskStatus(savedTaskId);
        }
        
        // 输入类型切换
        const textInputType = document.getElementById('textInputType');
        const imageInputType = document.getElementById('imageInputType');
        const textInputSection = document.getElementById('textInputSection');
        const imageInputSection = document.getElementById('imageInputSection');

        textInputType.addEventListener('change', function() {
            if (this.checked) {
                textInputSection.style.display = 'block';
                imageInputSection.style.display = 'none';
            }
        });

        imageInputType.addEventListener('change', function() {
            if (this.checked) {
                textInputSection.style.display = 'none';
                imageInputSection.style.display = 'block';
            }
        });

        // 文本描述字符计数
        const textPrompt = document.getElementById('textPrompt');
        const charCount = document.getElementById('charCount');

        textPrompt.addEventListener('input', function() {
            charCount.textContent = this.value.length;
        });

        // 图片上传预览
        const imageFile = document.getElementById('imageFile');
        const uploadedImageName = document.getElementById('uploadedImageName');
        const imagePreviewSection = document.getElementById('imagePreviewSection');
        const imagePreview = document.getElementById('imagePreview');
        const imageInfo = document.getElementById('imageInfo');

        imageFile.addEventListener('change', function(e) {
            if (e.target.files && e.target.files[0]) {
                const file = e.target.files[0];
                uploadedImageName.textContent = file.name;
                
                // 显示文件大小
                const fileSize = (file.size / 1024 / 1024).toFixed(2);
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    imagePreview.src = e.target.result;
                    
                    // 创建临时图片对象获取尺寸
                    const tempImg = new Image();
                    tempImg.onload = function() {
                        const width = tempImg.width;
                        const height = tempImg.height;
                        imageInfo.textContent = `尺寸: ${width} × ${height} | 大小: ${fileSize} MB | 格式: ${file.type}`;
                    };
                    tempImg.src = e.target.result;
                    
                    imagePreviewSection.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                uploadedImageName.textContent = '';
                imagePreviewSection.style.display = 'none';
            }
        });

        // 切片时长滑动条事件
        const sliceDuration = document.getElementById('sliceDuration');
        const sliceDurationValue = document.getElementById('sliceDurationValue');

        sliceDuration.addEventListener('input', function() {
            sliceDurationValue.textContent = this.value;
        });

        // 标签切换
        const tabs = document.querySelectorAll('.tab');
        tabs.forEach(tab => {
            tab.addEventListener('click', function() {
                const tabId = this.getAttribute('data-tab');
                
                // 切换标签样式
                tabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                
                // 切换内容
                const tabContents = document.querySelectorAll('.tab-content');
                tabContents.forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabId).classList.add('active');
                
                // 如果切换到历史任务标签，加载历史任务
                if (tabId === 'history') {
                    loadHistoryTasks();
                }
            });
        });

        // 生成视频按钮点击事件
        const generateBtn = document.getElementById('generateBtn');
        generateBtn.addEventListener('click', function() {
            generateVideo();
        });

        // 预览效果按钮点击事件
        const previewBtn = document.getElementById('previewBtn');
        previewBtn.addEventListener('click', function() {
            previewVideo();
        });

        // 刷新历史任务按钮点击事件
        const refreshHistoryBtn = document.getElementById('refreshHistoryBtn');
        refreshHistoryBtn.addEventListener('click', function() {
            loadHistoryTasks();
        });

        // 删除全部历史任务按钮点击事件
        const deleteAllBtn = document.getElementById('deleteAllBtn');
        deleteAllBtn.addEventListener('click', function() {
            showConfirm('确定要删除所有历史任务吗？此操作不可恢复。', deleteAllHistoryTasks);
        });

        // 应用筛选按钮点击事件
        const applyFiltersBtn = document.getElementById('applyFiltersBtn');
        applyFiltersBtn.addEventListener('click', function() {
            loadHistoryTasks();
        });

        // 搜索输入框回车事件
        const searchInput = document.getElementById('searchInput');
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                loadHistoryTasks();
            }
        });
    });

    // 生成视频
    function generateVideo() {
        const inputType = document.querySelector('input[name="inputType"]:checked').value;
        const genres = Array.from(document.querySelectorAll('input[name="genres"]:checked')).map(cb => cb.value);
        const gridSize = document.querySelector('input[name="gridSize"]:checked').value;
        const gridCount = document.querySelector('input[name="gridSize"]:checked').getAttribute('data-count');
        const sliceDuration = document.getElementById('sliceDuration').value;
        const videoStyle = document.querySelector('input[name="videoStyle"]:checked').value;
        const videoResolution = document.querySelector('input[name="videoResolution"]:checked').value;
        const videoAspectRatio = document.querySelector('input[name="videoAspectRatio"]:checked').value;
        
        let prompt = '';
        let imageUrl = '';
        
        if (inputType === 'text') {
            prompt = document.getElementById('textPrompt').value.trim();
            if (!prompt) {
                showError('请输入视频描述');
                return;
            }
            if (prompt.length < 10) {
                showError('视频描述至少需要10个字符');
                return;
            }
            if (prompt.length > 1000) {
                showError('视频描述不能超过1000个字符');
                return;
            }
        } else if (inputType === 'image') {
                const imageFile = document.getElementById('imageFile').files[0];
                if (!imageFile) {
                    showError('请上传图片');
                    return;
                }
                
                // 验证图片大小
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (imageFile.size > maxSize) {
                    showError('图片大小不能超过5MB');
                    return;
                }
                
                // 验证图片格式
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!allowedTypes.includes(imageFile.type)) {
                    showError('只支持JPG、PNG、GIF格式的图片');
                    return;
                }
                
                // 上传图片，获取图片URL
                const formData = new FormData();
                formData.append('image', imageFile);
                formData.append('action', 'upload_image');
                
                const progress = document.getElementById('progress');
                const progressInfo = document.getElementById('progressInfo');
                const progressBar = document.getElementById('progressBar');
                
                progress.style.display = 'block';
                progressInfo.textContent = '正在上传图片...';
                progressBar.style.width = '0%';
                
                // 使用XMLHttpRequest以支持上传进度
                const xhr = new XMLHttpRequest();
                
                // 监听上传进度
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percentComplete = (e.loaded / e.total) * 100;
                        progressBar.style.width = percentComplete + '%';
                        progressInfo.textContent = '正在上传图片... ' + Math.round(percentComplete) + '%';
                    }
                });
                
                // 监听请求完成
                xhr.addEventListener('load', function() {
                    if (xhr.status === 200) {
                        try {
                            const data = JSON.parse(xhr.responseText);
                            if (data.success) {
                                prompt = data.image_url;
                                proceedWithVideoGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio);
                            } else {
                                showError('图片上传失败：' + (data.error || '未知错误'));
                                progress.style.display = 'none';
                            }
                        } catch (error) {
                            console.error('解析响应失败:', error);
                            showError('图片上传失败，请稍后重试');
                            progress.style.display = 'none';
                        }
                    } else {
                        showError('图片上传失败，请稍后重试');
                        progress.style.display = 'none';
                    }
                });
                
                // 监听网络错误
                xhr.addEventListener('error', function() {
                    console.error('网络错误');
                    showError('图片上传失败，请检查网络连接');
                    progress.style.display = 'none';
                });
                
                // 发送请求
                xhr.open('POST', 'text2video_api.php');
                xhr.send(formData);
                
                return;
            }
            
            // 继续视频生成
            proceedWithVideoGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio);
        }
        
        // 视频生成函数
        function proceedWithVideoGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio) {
            // 显示处理状态
            const progress = document.getElementById('progress');
            const progressInfo = document.getElementById('progressInfo');
            const progressBar = document.getElementById('progressBar');
            const processingTaskId = document.getElementById('processingTaskId');
            
            progress.style.display = 'block';
            progressInfo.textContent = '正在创建视频生成任务...';
            progressBar.style.width = '0%';
            processingTaskId.textContent = '';
            
            // 隐藏错误和成功提示
            hideError();
            hideSuccess();
            
            // 创建视频生成任务
            fetch('text2video_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    input_type: inputType,
                    prompt: prompt,
                    genres: genres,
                    grid_size: gridSize,
                    grid_count: gridCount,
                    slice_duration: sliceDuration,
                    style: videoStyle,
                    resolution: videoResolution,
                    aspect_ratio: videoAspectRatio
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const taskId = data.task_id;
                    processingTaskId.textContent = taskId;
                    
                    // 开始轮询任务状态
                    progressInfo.textContent = '视频生成中，请稍候...';
                    progressBar.style.width = '10%';
                    
                    pollTaskStatus(taskId);
                } else {
                    showError(data.error || '创建任务失败');
                    progress.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('生成视频失败:', error);
                showError('生成视频失败，请稍后重试');
                progress.style.display = 'none';
            });
        }

    // 轮询任务状态
    function pollTaskStatus(taskId) {
        let retries = 0;
        const maxRetries = 120; // 最多轮询120次（约10分钟）
        const interval = 5000; // 每5秒轮询一次
        
        const progressInfo = document.getElementById('progressInfo');
        const progressBar = document.getElementById('progressBar');
        const taskProgressInfo = document.getElementById('taskProgressInfo');
        const taskStepsList = document.getElementById('taskStepsList');
        
        // 初始化任务进度显示
        taskProgressInfo.innerHTML = '<p style="color: #007bff; text-align: center;"><i class="fas fa-spinner fa-spin"></i> 正在创建任务...</p>';
        taskStepsList.style.display = 'block';
        
        function checkStatus() {
            fetch(`text2video_api.php?task_id=${taskId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const status = data.status;
                        const progress = data.progress || 0;
                        const currentStep = data.current_step || 0;
                        const stepInfo = data.step_info || '';
                        
                        progressBar.style.width = `${progress}%`;
                        
                        // 更新流程步骤状态
                        updateProcessStepsStatus(currentStep, stepInfo);
                        
                        // 更新任务进度显示
                        taskProgressInfo.innerHTML = `
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><strong>任务状态：</strong>${getStatusText(status)}</span>
                                    <span><strong>进度：</strong>${progress}%</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><strong>当前步骤：</strong>${currentStep > 0 ? currentStep : '准备中'}</span>
                                    <span><strong>步骤信息：</strong>${stepInfo || '无'}</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <span><strong>任务ID：</strong>${taskId}</span>
                                    <span><strong>重试次数：</strong>${retries}/${maxRetries}</span>
                                </div>
                            </div>
                        `;
                        
                        // 更新步骤状态
                        for (let i = 1; i <= 10; i++) {
                            const stepStatusElement = document.getElementById(`step${i}-status`);
                            if (stepStatusElement) {
                                if (i < currentStep) {
                                    stepStatusElement.textContent = '已完成';
                                    stepStatusElement.className = 'step-status completed';
                                } else if (i === currentStep) {
                                    stepStatusElement.textContent = '处理中';
                                    stepStatusElement.className = 'step-status processing';
                                } else {
                                    stepStatusElement.textContent = '等待中';
                                    stepStatusElement.className = 'step-status pending';
                                }
                            }
                        }
                        
                        switch (status) {
                            case 'processing':
                                progressInfo.textContent = `视频生成中，进度：${progress}% - ${stepInfo}`;
                                retries++;
                                if (retries < maxRetries) {
                                    setTimeout(checkStatus, interval);
                                } else {
                                    showError('视频生成超时，请稍后重试');
                                    document.getElementById('progress').style.display = 'none';
                                    taskProgressInfo.innerHTML = '<p style="color: #dc3545; text-align: center;">视频生成超时，请稍后重试</p>';
                                }
                                break;
                            case 'completed':
                                progressInfo.textContent = '视频生成完成！';
                                progressBar.style.width = '100%';
                                
                                // 显示所有步骤为完成状态
                                for (let i = 1; i <= 10; i++) {
                                    markStepAsCompleted(i);
                                    const stepStatusElement = document.getElementById(`step${i}-status`);
                                    if (stepStatusElement) {
                                        stepStatusElement.textContent = '已完成';
                                        stepStatusElement.className = 'step-status completed';
                                    }
                                }
                                
                                // 显示视频结果
                                displayVideoResult(data.video_url);
                                
                                // 更新任务进度显示
                                taskProgressInfo.innerHTML = `
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <div style="text-align: center; color: #28a745;">
                                            <i class="fas fa-check-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                                            <h5 style="margin: 0;">任务已完成！</h5>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span><strong>任务状态：</strong>已完成</span>
                                            <span><strong>进度：</strong>100%</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span><strong>任务ID：</strong>${taskId}</span>
                                            <span><strong>视频URL：</strong><a href="${data.video_url}" target="_blank">查看</a></span>
                                        </div>
                                    </div>
                                `;
                                
                                // 隐藏处理状态
                                setTimeout(() => {
                                    document.getElementById('progress').style.display = 'none';
                                    showSuccess('视频生成完成！结果已显示在右侧。');
                                }, 1000);
                                break;
                            case 'failed':
                                showError(data.error || '视频生成失败，请稍后重试');
                                document.getElementById('progress').style.display = 'none';
                                
                                // 更新任务进度显示
                                taskProgressInfo.innerHTML = `
                                    <div style="display: flex; flex-direction: column; gap: 10px;">
                                        <div style="text-align: center; color: #dc3545;">
                                            <i class="fas fa-times-circle" style="font-size: 24px; margin-bottom: 10px;"></i>
                                            <h5 style="margin: 0;">任务失败</h5>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span><strong>任务状态：</strong>失败</span>
                                            <span><strong>错误信息：</strong>${data.error || '未知错误'}</span>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center;">
                                            <span><strong>任务ID：</strong>${taskId}</span>
                                            <span><strong>当前步骤：</strong>${currentStep}</span>
                                        </div>
                                    </div>
                                `;
                                
                                // 更新步骤状态
                                for (let i = 1; i <= 10; i++) {
                                    const stepStatusElement = document.getElementById(`step${i}-status`);
                                    if (stepStatusElement) {
                                        if (i < currentStep) {
                                            stepStatusElement.textContent = '已完成';
                                            stepStatusElement.className = 'step-status completed';
                                        } else if (i === currentStep) {
                                            stepStatusElement.textContent = '失败';
                                            stepStatusElement.className = 'step-status failed';
                                        } else {
                                            stepStatusElement.textContent = '未执行';
                                            stepStatusElement.className = 'step-status pending';
                                        }
                                    }
                                }
                                break;
                            default:
                                progressInfo.textContent = `状态：${status} - ${stepInfo}`;
                                retries++;
                                if (retries < maxRetries) {
                                    setTimeout(checkStatus, interval);
                                } else {
                                    showError('视频生成超时，请稍后重试');
                                    document.getElementById('progress').style.display = 'none';
                                    taskProgressInfo.innerHTML = '<p style="color: #dc3545; text-align: center;">视频生成超时，请稍后重试</p>';
                                }
                                break;
                        }
                    } else {
                        showError(data.error || '获取任务状态失败');
                        document.getElementById('progress').style.display = 'none';
                        taskProgressInfo.innerHTML = `<p style="color: #dc3545; text-align: center;">获取任务状态失败：${data.error || '未知错误'}</p>`;
                    }
                })
                .catch(error => {
                    console.error('获取任务状态失败:', error);
                    retries++;
                    if (retries < maxRetries) {
                        setTimeout(checkStatus, interval);
                    } else {
                        showError('视频生成超时，请稍后重试');
                        document.getElementById('progress').style.display = 'none';
                        taskProgressInfo.innerHTML = '<p style="color: #dc3545; text-align: center;">视频生成超时，请稍后重试</p>';
                    }
                });
        }
        
        // 开始轮询
        checkStatus();
    }
    
    // 获取状态文本
    function getStatusText(status) {
        const statusMap = {
            'pending': '待处理',
            'processing': '处理中',
            'completed': '已完成',
            'failed': '失败',
            'cancelled': '已取消'
        };
        return statusMap[status] || status;
    }

    // 更新流程步骤状态
    function updateProcessStepsStatus(currentStep, stepInfo) {
        // 重置所有步骤状态
        for (let i = 1; i <= 10; i++) {
            const stepElement = document.getElementById(`step-${i}`);
            if (stepElement) {
                if (i < currentStep) {
                    // 已完成的步骤
                    markStepAsCompleted(i);
                } else if (i === currentStep) {
                    // 当前步骤
                    markStepAsCurrent(i, stepInfo);
                } else {
                    // 未开始的步骤
                    markStepAsPending(i);
                }
            }
        }
    }

    // 标记步骤为已完成
    function markStepAsCompleted(stepNumber) {
        const stepElement = document.getElementById(`step-${stepNumber}`);
        if (stepElement) {
            stepElement.style.backgroundColor = '#d4edda';
            stepElement.style.borderLeft = '4px solid #28a745';
            stepElement.innerHTML = `<span><i class="fas fa-check"></i> ${stepElement.querySelector('span').textContent}</span>`;
        }
    }

    // 标记步骤为当前进行中
    function markStepAsCurrent(stepNumber, stepInfo) {
        const stepElement = document.getElementById(`step-${stepNumber}`);
        if (stepElement) {
            stepElement.style.backgroundColor = '#d1ecf1';
            stepElement.style.borderLeft = '4px solid #17a2b8';
            stepElement.innerHTML = `<span><i class="fas fa-spinner fa-spin"></i> ${stepElement.querySelector('span').textContent} - ${stepInfo}</span>`;
        }
    }

    // 标记步骤为待处理
    function markStepAsPending(stepNumber) {
        const stepElement = document.getElementById(`step-${stepNumber}`);
        if (stepElement) {
            stepElement.style.backgroundColor = '#f8f9fa';
            stepElement.style.borderLeft = '4px solid #6c757d';
            stepElement.innerHTML = `<span>${stepElement.querySelector('span').textContent}</span>`;
        }
    }

    // 检查状态（手动刷新）
    function checkStatusAgain() {
        const taskId = document.getElementById('processingTaskId').textContent;
        if (taskId) {
            pollTaskStatus(taskId);
        }
    }

    // 复制任务ID
    function copyProcessingTaskId() {
        const taskId = document.getElementById('processingTaskId').textContent;
        if (taskId) {
            navigator.clipboard.writeText(taskId)
                .then(() => {
                    alert('任务ID已复制到剪贴板');
                })
                .catch(err => {
                    console.error('复制失败:', err);
                });
        }
    }

    // 显示视频结果
    function displayVideoResult(videoUrl) {
        const resultContainer = document.getElementById('result');
        
        resultContainer.innerHTML = `
            <div class="video-result">
                <h3>生成的视频</h3>
                <div class="video-player">
                    <video controls>
                        <source src="${videoUrl}" type="video/mp4">
                        您的浏览器不支持视频播放。
                    </video>
                </div>
                <div class="video-actions" style="margin-top: 20px;">
                    <a href="${videoUrl}" class="btn btn-primary" download>下载视频</a>
                </div>
            </div>
        `;
    }

    // 加载历史任务
    function loadHistoryTasks() {
        const historyList = document.getElementById('historyList');
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const priorityFilter = document.getElementById('priorityFilter');
        
        const search = searchInput ? searchInput.value.trim() : '';
        const status = statusFilter ? statusFilter.value : 'all';
        const priority = priorityFilter ? priorityFilter.value : 'all';
        
        historyList.innerHTML = '<div class="empty-state">加载中...</div>';
        
        // 构建查询参数
        let queryParams = 'action=history';
        if (search) {
            queryParams += '&search=' + encodeURIComponent(search);
        }
        if (status !== 'all') {
            queryParams += '&status=' + encodeURIComponent(status);
        }
        if (priority !== 'all') {
            queryParams += '&priority=' + encodeURIComponent(priority);
        }
        
        fetch('text2video_api.php?' + queryParams)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tasks = data.tasks;
                    if (tasks.length > 0) {
                        let html = '';
                        tasks.forEach(task => {
                            const statusClass = task.status === 'completed' ? 'status-completed' : 
                                               task.status === 'processing' ? 'status-processing' : 'status-failed';
                            const statusText = task.status === 'completed' ? '已完成' : 
                                              task.status === 'processing' ? '处理中' : 
                                              task.status === 'pending' ? '待处理' : 
                                              task.status === 'cancelled' ? '已取消' : '失败';
                            const priorityText = task.priority === 'high' ? '高' : 
                                                task.priority === 'low' ? '低' : '正常';
                            
                            html += `
                                <div class="history-item">
                                    <div class="history-item-header">
                                        <div class="history-item-title">任务 ${task.task_id}</div>
                                        <div class="history-item-status ${statusClass}">${statusText}</div>
                                    </div>
                                    <div class="history-item-body">
                                        <p><strong>输入类型：</strong>${task.input_type === 'text' ? '文本描述' : '图片上传'}</p>
                                        <p><strong>描述：</strong>${task.prompt}</p>
                                        <p><strong>时长：</strong>${task.duration}秒</p>
                                        <p><strong>风格：</strong>${task.style}</p>
                                        ${task.resolution ? `<p><strong>分辨率：</strong>${task.resolution}</p>` : ''}
                                        ${task.fps ? `<p><strong>帧率：</strong>${task.fps} FPS</p>` : ''}
                                        ${task.aspect_ratio ? `<p><strong>宽高比：</strong>${task.aspect_ratio}</p>` : ''}
                                        ${task.priority ? `<p><strong>优先级：</strong>${priorityText}</p>` : ''}
                                        ${task.video_url ? `<p><strong>视频：</strong><a href="${task.video_url}" target="_blank">查看视频</a></p>` : ''}
                                    </div>
                                    <div class="history-item-footer">
                                            <span>创建时间：${task.created_at}</span>
                                            <div class="history-item-actions">
                                                ${task.status === 'completed' && task.video_url ? `<a href="${task.video_url}" class="btn btn-secondary" download>下载</a>` : ''}
                                                <button class="btn btn-secondary" onclick="regenerateTask('${task.task_id}')">重新生成</button>
                                                <button class="btn btn-secondary" onclick="deleteHistoryTask('${task.task_id}')">删除</button>
                                            </div>
                                        </div>
                                </div>
                            `;
                        });
                        historyList.innerHTML = html;
                    } else {
                        historyList.innerHTML = '<div class="empty-state">暂无历史任务</div>';
                    }
                } else {
                    historyList.innerHTML = '<div class="empty-state">加载历史任务失败</div>';
                }
            })
            .catch(error => {
                console.error('加载历史任务失败:', error);
                historyList.innerHTML = '<div class="empty-state">加载历史任务失败</div>';
            });
    }

    // 删除历史任务
    function deleteHistoryTask(taskId) {
        showConfirm('确定要删除这个历史任务吗？此操作不可恢复。', () => {
            const loading = showLoading('删除任务中...');
            
            fetch('text2video_api.php?action=delete&task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        showNotification('任务删除成功', 'success');
                        loadHistoryTasks();
                    } else {
                        showNotification('删除失败：' + (data.error || '未知错误'), 'error');
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('删除历史任务失败:', error);
                    showNotification('删除失败，请稍后重试', 'error');
                });
        });
    }

    // 删除所有历史任务
    function deleteAllHistoryTasks() {
        const loading = showLoading('删除所有任务中...');
        
        fetch('text2video_api.php?action=delete_all')
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success) {
                    showNotification('所有任务删除成功', 'success');
                    loadHistoryTasks();
                } else {
                    showNotification('删除失败：' + (data.error || '未知错误'), 'error');
                }
            })
            .catch(error => {
                hideLoading();
                console.error('删除所有历史任务失败:', error);
                showNotification('删除失败，请稍后重试', 'error');
            });
    }

    // 显示错误信息
    function showError(message) {
        const error = document.getElementById('error');
        error.textContent = message;
        error.style.display = 'block';
        
        // 3秒后自动隐藏错误信息
        setTimeout(() => {
            hideError();
        }, 5000);
    }

    // 隐藏错误信息
    function hideError() {
        const error = document.getElementById('error');
        error.style.display = 'none';
    }

    // 显示成功信息
    function showSuccess(message) {
        const success = document.getElementById('success');
        success.textContent = message;
        success.style.display = 'block';
        
        // 3秒后自动隐藏成功信息
        setTimeout(() => {
            hideSuccess();
        }, 5000);
    }

    // 隐藏成功信息
    function hideSuccess() {
        const success = document.getElementById('success');
        success.style.display = 'none';
    }

    // 显示加载提示
    function showLoading(message = '处理中...') {
        const loading = document.createElement('div');
        loading.id = 'loadingIndicator';
        loading.style.position = 'fixed';
        loading.style.top = '50%';
        loading.style.left = '50%';
        loading.style.transform = 'translate(-50%, -50%)';
        loading.style.background = 'rgba(255, 255, 255, 0.9)';
        loading.style.padding = '20px';
        loading.style.borderRadius = '4px';
        loading.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
        loading.style.zIndex = '10000';
        loading.style.display = 'flex';
        loading.style.alignItems = 'center';
        loading.style.gap = '10px';
        loading.innerHTML = `
            <div class="spinner" style="width: 20px; height: 20px; border-width: 3px;"></div>
            <span>${message}</span>
        `;
        document.body.appendChild(loading);
        return loading;
    }

    // 隐藏加载提示
    function hideLoading() {
        const loading = document.getElementById('loadingIndicator');
        if (loading) {
            loading.remove();
        }
    }

    // 显示确认对话框
    function showConfirm(message, callback) {
        if (confirm(message)) {
            callback();
        }
    }

    // 显示通知
    function showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.style.position = 'fixed';
        notification.style.top = '20px';
        notification.style.right = '20px';
        notification.style.padding = '15px';
        notification.style.borderRadius = '4px';
        notification.style.boxShadow = '0 2px 10px rgba(0, 0, 0, 0.2)';
        notification.style.zIndex = '10000';
        notification.style.transition = 'all 0.3s ease';
        notification.style.transform = 'translateX(100%)';
        notification.style.opacity = '0';
        notification.textContent = message;
        
        // 根据类型设置样式
        switch (type) {
            case 'success':
                notification.style.background = '#d4edda';
                notification.style.border = '1px solid #c3e6cb';
                notification.style.color = '#155724';
                break;
            case 'error':
                notification.style.background = '#f8d7da';
                notification.style.border = '1px solid #f5c6cb';
                notification.style.color = '#721c24';
                break;
            case 'warning':
                notification.style.background = '#fff3cd';
                notification.style.border = '1px solid #ffeeba';
                notification.style.color = '#856404';
                break;
            default:
                notification.style.background = '#d1ecf1';
                notification.style.border = '1px solid #bee5eb';
                notification.style.color = '#0c5460';
        }
        
        document.body.appendChild(notification);
        
        // 显示通知
        setTimeout(() => {
            notification.style.transform = 'translateX(0)';
            notification.style.opacity = '1';
        }, 100);
        
        // 3秒后隐藏通知
        setTimeout(() => {
            notification.style.transform = 'translateX(100%)';
            notification.style.opacity = '0';
            setTimeout(() => {
                notification.remove();
            }, 300);
        }, 3000);
    }
    
    // 视频预览
    function previewVideo() {
        const inputType = document.querySelector('input[name="inputType"]:checked').value;
        const genres = Array.from(document.querySelectorAll('input[name="genres"]:checked')).map(cb => cb.value);
        const gridSize = document.querySelector('input[name="gridSize"]:checked').value;
        const gridCount = document.querySelector('input[name="gridSize"]:checked').getAttribute('data-count');
        const sliceDuration = document.getElementById('sliceDuration').value;
        const videoStyle = document.querySelector('input[name="videoStyle"]:checked').value;
        const videoResolution = document.querySelector('input[name="videoResolution"]:checked').value;
        const videoAspectRatio = document.querySelector('input[name="videoAspectRatio"]:checked').value;
        
        let prompt = '';
        
        if (inputType === 'text') {
            prompt = document.getElementById('textPrompt').value.trim();
            if (!prompt) {
                showError('请输入视频描述');
                return;
            }
            if (prompt.length < 10) {
                showError('视频描述至少需要10个字符');
                return;
            }
            if (prompt.length > 1000) {
                showError('视频描述不能超过1000个字符');
                return;
            }
        } else if (inputType === 'image') {
            const imageFile = document.getElementById('imageFile').files[0];
            if (!imageFile) {
                showError('请上传图片');
                return;
            }
            
            // 验证图片大小
            const maxSize = 5 * 1024 * 1024; // 5MB
            if (imageFile.size > maxSize) {
                showError('图片大小不能超过5MB');
                return;
            }
            
            // 验证图片格式
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
            if (!allowedTypes.includes(imageFile.type)) {
                showError('只支持JPG、PNG、GIF格式的图片');
                return;
            }
            
            // 上传图片，获取图片URL
            const formData = new FormData();
            formData.append('image', imageFile);
            formData.append('action', 'upload_image');
            
            const loading = showLoading('上传图片中...');
            
            // 使用XMLHttpRequest以支持上传进度
            const xhr = new XMLHttpRequest();
            
            // 监听请求完成
            xhr.addEventListener('load', function() {
                hideLoading();
                if (xhr.status === 200) {
                    try {
                        const data = JSON.parse(xhr.responseText);
                        if (data.success) {
                            prompt = data.image_url;
                            proceedWithPreviewGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio);
                        } else {
                            showError('图片上传失败：' + (data.error || '未知错误'));
                        }
                    } catch (error) {
                        console.error('解析响应失败:', error);
                        showError('图片上传失败，请稍后重试');
                    }
                } else {
                    showError('图片上传失败，请稍后重试');
                }
            });
            
            // 监听网络错误
            xhr.addEventListener('error', function() {
                hideLoading();
                console.error('网络错误');
                showError('图片上传失败，请检查网络连接');
            });
            
            // 发送请求
            xhr.open('POST', 'text2video_api.php');
            xhr.send(formData);
            
            return;
        }
        
        // 继续预览生成
        proceedWithPreviewGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio);
    }

    // 预览生成函数
    function proceedWithPreviewGeneration(inputType, prompt, genres, gridSize, gridCount, sliceDuration, videoStyle, videoResolution, videoAspectRatio) {
        const loading = showLoading('生成预览中...');
        const previewSection = document.getElementById('previewSection');
        const previewResult = document.getElementById('previewResult');
        
        previewSection.style.display = 'block';
        previewResult.innerHTML = '<p style="color: #7f8c8d; text-align: center;">生成预览中，请稍候...</p>';
        
        // 隐藏错误和成功提示
        hideError();
        hideSuccess();
        
        // 创建预览生成任务
        fetch('text2video_api.php?action=preview', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                input_type: inputType,
                prompt: prompt,
                genres: genres,
                grid_size: gridSize,
                grid_count: gridCount,
                slice_duration: sliceDuration,
                style: videoStyle,
                resolution: videoResolution,
                aspect_ratio: videoAspectRatio
            })
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if (data.success) {
                if (data.preview_url) {
                    previewResult.innerHTML = `
                        <div class="video-result">
                            <div class="video-player">
                                <video controls>
                                    <source src="${data.preview_url}" type="video/mp4">
                                    您的浏览器不支持视频播放。
                                </video>
                            </div>
                            <p style="margin-top: 10px; font-size: 14px; color: #666;">预览生成完成，点击"生成视频"按钮创建完整视频</p>
                        </div>
                    `;
                } else if (data.preview_image) {
                    previewResult.innerHTML = `
                        <div style="text-align: center;">
                            <img src="${data.preview_image}" alt="视频预览" style="max-width: 100%; max-height: 300px; border-radius: 4px;">
                            <p style="margin-top: 10px; font-size: 14px; color: #666;">预览图片生成完成，点击"生成视频"按钮创建完整视频</p>
                        </div>
                    `;
                } else {
                    previewResult.innerHTML = `
                        <div style="padding: 20px;">
                            <h4>预览信息</h4>
                            <p><strong>输入类型：</strong>${inputType === 'text' ? '文本描述' : '图片上传'}</p>
                            <p><strong>宫格模式：</strong>${gridSize}</p>
                            <p><strong>每个切片时长：</strong>${sliceDuration}秒</p>
                            <p><strong>视频风格：</strong>${videoStyle}</p>
                            <p><strong>描述：</strong>${prompt}</p>
                            <p style="margin-top: 15px; font-size: 14px; color: #666;">预览信息生成完成，点击"生成视频"按钮创建完整视频</p>
                        </div>
                    `;
                }
            } else {
                previewResult.innerHTML = `<p style="color: #e74c3c; text-align: center;">预览生成失败：${data.error || '未知错误'}</p>`;
                showError('预览生成失败：' + (data.error || '未知错误'));
            }
        })
        .catch(error => {
            hideLoading();
            console.error('生成预览失败:', error);
            previewResult.innerHTML = '<p style="color: #e74c3c; text-align: center;">预览生成失败，请稍后重试</p>';
            showError('预览生成失败，请稍后重试');
        });
    }

    // 取消任务
    function cancelTask() {
        const taskId = document.getElementById('processingTaskId').textContent;
        if (taskId) {
            showConfirm('确定要取消这个任务吗？此操作不可恢复。', () => {
                const loading = showLoading('取消任务中...');
                
                fetch('text2video_api.php?action=cancel&task_id=' + taskId)
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showNotification('任务已成功取消', 'success');
                            document.getElementById('progress').style.display = 'none';
                        } else {
                            showNotification('取消任务失败：' + (data.error || '未知错误'), 'error');
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        console.error('取消任务失败:', error);
                        showNotification('取消任务失败，请稍后重试', 'error');
                    });
            });
        }
    }
    
    // 重新生成任务
    function regenerateTask(taskId) {
        const loading = showLoading('加载任务信息中...');
        
        // 获取任务详情
        fetch(`text2video_api.php?task_id=${taskId}`)
            .then(response => response.json())
            .then(data => {
                hideLoading();
                if (data.success && data.task_info) {
                    const taskInfo = data.task_info;
                    
                    // 切换到创建任务标签
                    document.querySelector('.tab[data-tab="new-task"]').click();
                    
                    // 填充表单数据
                    if (taskInfo.input_type === 'text') {
                        // 选择文本输入类型
                        document.getElementById('textInputType').checked = true;
                        document.getElementById('imageInputType').checked = false;
                        document.getElementById('textInputSection').style.display = 'block';
                        document.getElementById('imageInputSection').style.display = 'none';
                        
                        // 填充文本描述
                        document.getElementById('textPrompt').value = taskInfo.prompt;
                        document.getElementById('charCount').textContent = taskInfo.prompt.length;
                    } else {
                        // 选择图片输入类型
                        document.getElementById('textInputType').checked = false;
                        document.getElementById('imageInputType').checked = true;
                        document.getElementById('textInputSection').style.display = 'none';
                        document.getElementById('imageInputSection').style.display = 'block';
                    }
                    
                    // 设置宫格模式
                    const gridSizeRadios = document.querySelectorAll('input[name="gridSize"]');
                    gridSizeRadios.forEach(radio => {
                        if (radio.value === taskInfo.grid_size) {
                            radio.checked = true;
                        }
                    });
                    
                    // 设置切片时长
                    document.getElementById('sliceDuration').value = taskInfo.slice_duration;
                    document.getElementById('sliceDurationValue').textContent = taskInfo.slice_duration;
                    
                    // 设置视频风格
                    const styleRadios = document.querySelectorAll('input[name="videoStyle"]');
                    styleRadios.forEach(radio => {
                        if (radio.value === taskInfo.style) {
                            radio.checked = true;
                        }
                    });
                    
                    // 设置视频分辨率
                    const resolutionRadios = document.querySelectorAll('input[name="videoResolution"]');
                    resolutionRadios.forEach(radio => {
                        if (radio.value === taskInfo.resolution) {
                            radio.checked = true;
                        }
                    });
                    
                    // 设置视频宽高比
                    const aspectRatioRadios = document.querySelectorAll('input[name="videoAspectRatio"]');
                    aspectRatioRadios.forEach(radio => {
                        if (radio.value === taskInfo.aspect_ratio) {
                            radio.checked = true;
                        }
                    });
                    
                    // 显示提示信息
                    showNotification('任务信息已加载，请修改后重新生成', 'info');
                } else {
                    showError('加载任务信息失败：' + (data.error || '未知错误'));
                }
            })
            .catch(error => {
                hideLoading();
                console.error('加载任务信息失败:', error);
                showError('加载任务信息失败，请稍后重试');
            });
    }
</script>
</body>

</html>
