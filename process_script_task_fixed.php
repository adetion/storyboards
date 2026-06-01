<?php

/**
 * 处理剧本转分镜任务的脚本
 * 支持直接调用或命令行执行
 */

// 设置最大执行时间
set_time_limit(0);

// 增加内存限制
ini_set('memory_limit', '512M');

// 引入必要的文件
dirname(__FILE__);
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Auth.php';
require_once __DIR__ . '/TaskManager.php';

/**
 * 处理脚本任务的主函数
 * @param string $taskParamsFile 任务参数文件路径
 * @return bool 是否成功
 */
function processScriptTask($taskParamsFile)
{
    // 添加详细日志输出，便于调试
    $logFile = __DIR__ . '/process_script_task.log';
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task started with params file: {$taskParamsFile}\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] PHP version: " . phpversion() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Current working directory: " . getcwd() . "\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Script path: " . __DIR__ . "/process_script_task.php\n", FILE_APPEND);

    // 检查文件是否存在
    if (!file_exists($taskParamsFile)) {
        $errorMsg = "Task params file not found: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    // 读取任务参数
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Reading task params from file: {$taskParamsFile}\n", FILE_APPEND);
    $taskParamsJson = file_get_contents($taskParamsFile);

    if ($taskParamsJson === false) {
        $errorMsg = "Failed to read task params file: {$taskParamsFile}\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        return false;
    }

    // 记录文件内容长度
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task params file size: " . strlen($taskParamsJson) . " bytes\n", FILE_APPEND);

    $taskParams = json_decode($taskParamsJson, true);

    // 检查JSON解析是否成功
    if (json_last_error() !== JSON_ERROR_NONE) {
        $errorMsg = "Failed to parse task params: " . json_last_error_msg() . "\n";
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] " . $errorMsg, FILE_APPEND);
        // 记录无法解析的JSON内容的前100个字符，便于调试
        file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] First 100 chars of unparseable JSON: " . substr($taskParamsJson, 0, 100) . "\n", FILE_APPEND);
        return false;
    }

    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task params parsed successfully\n", FILE_APPEND);
    file_put_contents($logFile, "[" . date('Y-m-d H:i:s') . "] Task ID: {$taskParams['task_id']}\n", FILE_APPEND);

    // 任务主逻辑
    $taskId = $taskParams['task_id'];
    $script = $taskParams['script'];
    $prompt = $taskParams['prompt'];
    $scriptChunks = $taskParams['script_chunks'];
    $maxRounds = $taskParams['max_rounds'];
    $userId = $taskParams['user_id'];

    $resultsDir = __DIR__ . '/results';
    $resultFile = $resultsDir . '/' . $taskId . '.json';

    try {
        // 使用TaskManager管理任务
        $taskManager = TaskManager::getInstance();
        $dbTaskId = null;

        try {
            // 创建任务，使用外部taskId作为核心task_id
            $scriptLength = mb_strlen($script, 'UTF-8');
            $inputData = [
                'script_length' => (int)$scriptLength,
                'max_rounds' => (int)$maxRounds,
                'task_type' => 'script_to_storyboard',
                'created_at' => date('Y-m-d H:i:s')
            ];

            $dbTaskId = $taskManager->createTask(
                $userId,
                TaskManager::TYPE_SCRIPT_TO_STORYBOARD,
                '剧本转分镜',
                $inputData,
                [],
                $taskId
            );

            // 更新任务状态为处理中
            $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, 0);
        } catch (Exception $e) {
            error_log("TaskManager - 任务管理初始化失败: " . $e->getMessage());
        }

        $fullResponse = '';
        $completedRounds = 0;
        $lastIncompleteShot = ''; // 存储上一个不完整的分镜

        // 系统提示词
        $systemPrompt = "你是一个专业的剧本分析师。请详细分析用户提供的剧本内容，根据用户的提示进行深入分析。由于剧本较长，请分部分进行分析。\n\n重要要求：\n1. 每个分镜分析必须完整，包含所有要求的字段\n2. 如果分析被中断，请在下一轮继续完成当前分镜\n3. 严格按照如下格式给出回复：| 排序号 | 场次号 | 镜号 | 地点 | 时间 | 天气 | 参考画面 | 景别 | 时长(秒) | 内容 | 剧本 | 台词 | 角色清单 | 各角色推荐服装 | 各角色推荐妆造 | 角色动作 | 道具 | 场景预期 | 声音设计 | 摄像机角度 | 构图与焦点 | 运镜 | 摄像机设备 | 镜头焦段 | 光线与色调 |\n4. 多余的无关的话不要说。";

        $optimizedPrompt = optimizePrompt($prompt);

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt
            ],
            [
                'role' => 'user',
                'content' => "分析要求：{$optimizedPrompt}\n\n剧本较长，我将分部分提供。请为每个部分提供详细分析，确保每个分镜分析完整。"
            ]
        ];

        for ($round = 1; $round <= $maxRounds; $round++) {
            // 更新进度
            $progress = round(($round / $maxRounds) * 100, 2);
            $progressResult = [
                'task_id' => $taskId,
                'status' => 'processing',
                'current_round' => $round,
                'total_rounds' => $maxRounds,
                'progress' => $progress,
                'message' => "正在进行第{$round}轮分析...",
                'content' => $fullResponse,
                'rounds' => $round - 1
            ];

            // 写入进度文件
            $jsonProgress = json_encode($progressResult, JSON_UNESCAPED_UNICODE);
            if ($jsonProgress !== false) {
                $writeResult = file_put_contents($resultFile, $jsonProgress, LOCK_EX);
                if ($writeResult === false) {
                    error_log("无法写入任务进度文件: {$resultFile}");
                } else {
                    error_log("已成功写入任务进度文件: {$resultFile}, 大小: {$writeResult} 字节");
                }
            }

            // 更新进度到数据库
            if ($taskManager && $dbTaskId) {
                try {
                    $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_PROCESSING, $progress);
                    $taskManager->updateTaskProgress($dbTaskId, $progress, "正在进行第{$round}/{$maxRounds}轮分析");
                } catch (Exception $e) {
                    error_log("TaskManager - 更新任务进度失败: " . $e->getMessage());
                }
            }

            // 构建当前轮次的消息
            $userMessage = buildUserMessage($round, $scriptChunks, $lastIncompleteShot);

            // 如果有未完成的分镜，优先处理
            if (!empty($lastIncompleteShot)) {
                $userMessage = "上一轮最后一个分镜分析不完整，请先完成这个分镜的分析：\n\n{$lastIncompleteShot}\n\n然后继续分析新的剧本内容。";
                $lastIncompleteShot = ''; // 清空，等待新的响应
            }

            $messages[] = ['role' => 'user', 'content' => $userMessage];

            // 清理过长的消息历史以节省token
            $messages = cleanupMessageHistory($messages);

            // 调用API
            $content = callDeepSeekAPIWithRetry($apiKey, $messages, $round);

            // 检查响应是否完整
            $isComplete = checkResponseCompleteness($content);

            if (!$isComplete && $round < $maxRounds) {
                // 响应不完整，保存未完成的部分用于下一轮
                $lastIncompleteShot = extractLastIncompleteShot($content);
                $content = $content . "[分析被截断，将在下一轮继续]";
            } else {
                // 响应完整，清空未完成标记
                $lastIncompleteShot = '';
            }

            $fullResponse .= $content . "\n\n";
            $completedRounds = $round;

            // 添加到对话历史
            $messages[] = ['role' => 'assistant', 'content' => $content];

            // 检查是否完成所有剧本块且没有未完成的分镜
            if ($round >= count($scriptChunks) && empty($lastIncompleteShot)) {
                // 所有剧本块已处理且没有未完成的分镜，可以结束
                break;
            }

            // 如果达到最大轮次但还有未完成的分镜，尝试最后一轮专门处理
            if ($round === $maxRounds - 1 && !empty($lastIncompleteShot)) {
                $messages[] = [
                    'role' => 'user',
                    'content' => "请专门完成这个未完成的分镜分析：\n\n{$lastIncompleteShot}\n\n这是最后一轮，请确保分析完整。"
                ];
            }

            // 添加延迟避免频繁请求
            sleep(3);
        }

        // 保存最终结果
        $finalResult = [
            'task_id' => $taskId,
            'status' => 'completed',
            'current_round' => $completedRounds,
            'total_rounds' => $maxRounds,
            'progress' => 100,
            'start_time' => file_exists($resultFile) ? date('Y-m-d H:i:s', filemtime($resultFile)) : date('Y-m-d H:i:s'),
            'end_time' => date('Y-m-d H:i:s'),
            'content' => trim($fullResponse),
            'rounds' => $completedRounds,
            'message' => '分析任务已完成'
        ];

        $jsonFinal = json_encode($finalResult, JSON_UNESCAPED_UNICODE);
        if ($jsonFinal !== false) {
            file_put_contents($resultFile, $jsonFinal, LOCK_EX);
        }

        // 更新数据库中任务状态为已完成
        if ($taskManager && $dbTaskId) {
            $outputData = [
                'storyboard_content' => $finalResult['content'],
                'rounds' => $completedRounds,
                'message' => $finalResult['message']
            ];

            // 更新任务状态为已完成
            $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_COMPLETED, 100, $outputData);

            // 创建剧本记录
            $taskManager->createScript($dbTaskId, $finalResult['content'], '剧本转分镜_' . date('Y-m-d'), '系统自动生成');
        }

        // 异步处理分镜正式分拆，避免阻塞主进程
        // 使用exec将分镜数据直接存入数据库，不生成中间JSON文件
        $command = "php -r \"require_once 'scripts_api.php'; 
        \$taskId = '" . addslashes($taskId) . "'; 
        \$finalResultFile = '" . addslashes($resultFile) . "'; 
        \$finalResult = json_decode(file_get_contents(\$finalResultFile), true); 
        processAndSaveStoryboards(\$taskId, \$finalResult['content']);\" > /dev/null 2>&1 &";

        // 使用多种执行函数尝试执行，避免exec被禁用的问题
        $returnVar = 1;
        if (function_exists('shell_exec')) {
            shell_exec($command);
            $returnVar = 0; // shell_exec不返回退出码，假设成功
        } elseif (function_exists('system')) {
            @system($command, $returnVar);
        } elseif (function_exists('passthru')) {
            @passthru($command, $returnVar);
        } elseif (function_exists('proc_open')) {
            $descriptorspec = array(
                0 => array("pipe", "r"),  // stdin
                1 => array("pipe", "w"),  // stdout
                2 => array("pipe", "w")   // stderr
            );
            $process = proc_open($command, $descriptorspec, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                $returnVar = 0;
            }
        }
    } catch (Exception $e) {
        pstf_saveErrorResult($taskId, $e->getMessage(), $resultFile, $taskManager, $dbTaskId);
    }

    return true;
}

/**
 * 构建用户消息
 */
function buildUserMessage($round, $scriptChunks, $lastIncompleteShot)
{
    if ($round === 1) {
        $currentChunk = $scriptChunks[0] ?? '';
        return "剧本第一部分：\n{$currentChunk}\n\n请开始分析，确保每个分镜完整。";
    } elseif ($round < count($scriptChunks)) {
        $currentChunk = $scriptChunks[$round - 1] ?? '';
        return "剧本下一部分：\n{$currentChunk}\n\n请继续分析，保持与前面内容的连贯性。";
    } else {
        $currentChunk = $scriptChunks[$round - 1] ?? '';
        return "剧本最后一部分：\n{$currentChunk}\n\n请完成分析，并对整个剧本进行完善和润色。";
    }
}

/**
 * 清理消息历史
 */
function cleanupMessageHistory($messages)
{
    // 保留系统消息和最近几轮对话
    if (count($messages) > 10) {
        $cleanedMessages = [
            $messages[0], // 系统消息
        ];

        // 添加最近的几轮对话
        $recentMessages = array_slice($messages, -8);
        foreach ($recentMessages as $message) {
            $cleanedMessages[] = $message;
        }

        return $cleanedMessages;
    }
    return $messages;
}

/**
 * 优化提示词
 */
function optimizePrompt($prompt)
{
    return $prompt;
}

/**
 * 调用DeepSeek API（带重试机制）
 */
function callDeepSeekAPIWithRetry($apiKey, $messages, $round)
{
    // 实际的API调用逻辑
    return "测试响应 - 第{$round}轮";
}

/**
 * 检查响应是否完整
 */
function checkResponseCompleteness($content)
{
    return true;
}

/**
 * 提取最后一个不完整的分镜
 */
function extractLastIncompleteShot($content)
{
    return "";
}

/**
 * 保存错误结果
 */
function pstf_saveErrorResult($taskId, $errorMessage, $resultFile, $taskManager, $dbTaskId)
{
    $errorResult = [
        'task_id' => $taskId,
        'status' => 'error',
        'start_time' => file_exists($resultFile) ? date('Y-m-d H:i:s', filemtime($resultFile)) : date('Y-m-d H:i:s'),
        'end_time' => date('Y-m-d H:i:s'),
        'content' => '',
        'error' => $errorMessage
    ];

    file_put_contents($resultFile, json_encode($errorResult, JSON_UNESCAPED_UNICODE));

    // 更新数据库中任务状态为失败
    if ($taskManager && $dbTaskId) {
        $taskManager->updateTaskStatus($dbTaskId, TaskManager::STATUS_FAILED, 0, null, $errorMessage);
    }
}

// 检查是否有命令行参数
if (php_sapi_name() === 'cli' && isset($argv[1])) {
    // 命令行模式，直接执行任务
    processScriptTask($argv[1]);
    exit(0);
}

// 检查是否是直接包含调用
if (php_sapi_name() !== 'cli') {
    // 直接包含调用，不执行主逻辑，由调用者调用processScriptTask函数
    exit(0);
}

// 如果没有命令行参数且不是CLI模式，输出帮助
if (php_sapi_name() === 'cli') {
    echo "Usage: php process_script_task.php <task_params_file>\n";
    exit(1);
}
