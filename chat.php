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
?>
<!DOCTYPE html>
<html lang="zh-CN">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>智影工场 - AI助手</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="assets/fontawesome/css/all.min.css">

    <link rel="stylesheet" href="css/menu.css">
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --bot-bg: #f1f1f1;
            --user-text: white;
            --error-color: #e74c3c;
            --success-color: #4CAF50;
            --code-bg: #2d2d2d;
            --code-text: #f8f8f2;
            --warning-color: #ff9800;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            padding: 0;
            display: flex;
            flex-direction: column;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            border-radius: 0;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            flex: 1;
            display: flex;
            flex-direction: column;
            height: 100vh;
        }

        .header {
            background: var(--primary-gradient);
            color: white;
            padding: 1.5rem 1rem;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 10;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: clamp(1.8rem, 5vw, 2.5rem);
            margin: 0;
        }

        .session-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }

        .session-id {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-family: monospace;
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.8rem;
        }

        .header-controls {
            display: flex;
            gap: 0.5rem;
        }

        .control-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1rem;
            border-radius: 1rem;
            cursor: pointer;
            font-size: 0.8rem;
            transition: all 0.3s;
        }

        .control-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .control-btn.warning {
            background: rgba(255, 152, 0, 0.3);
        }

        .header p {
            font-size: clamp(0.9rem, 3vw, 1.1rem);
            opacity: 0.9;
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            flex: 1;
            min-height: 0;
            overflow: hidden;
        }

        .chat-history {
            flex: 1;
            padding: 1rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .message {
            max-width: 85%;
            padding: 0.875rem 1rem;
            border-radius: 1.125rem;
            position: relative;
            animation: fadeIn 0.3s ease-out;
            line-height: 1.4;
            word-wrap: break-word;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .user-message {
            align-self: flex-end;
            background: var(--primary-gradient);
            color: var(--user-text);
            border-bottom-right-radius: 0.5rem;
        }

        .bot-message {
            align-self: flex-start;
            background: var(--bot-bg);
            color: #333;
            border-bottom-left-radius: 0.5rem;
        }

        .message-actions {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            opacity: 0.7;
            transition: opacity 0.3s;
            display: flex;
            gap: 0.25rem;
        }

        .bot-message:hover .message-actions {
            opacity: 1;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.8);
            border: none;
            border-radius: 0.25rem;
            width: 1.75rem;
            height: 1.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 0.75rem;
            color: #333;
            transition: all 0.2s;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 1);
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
        }

        .copy-notification {
            position: fixed;
            top: 1rem;
            right: 1rem;
            background: var(--success-color);
            color: white;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            display: none;
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .typing-indicator {
            align-self: flex-start;
            background: var(--bot-bg);
            padding: 0.875rem 1rem;
            border-radius: 1.125rem;
            display: none;
        }

        .typing-indicator span {
            height: 0.625rem;
            width: 0.625rem;
            float: left;
            margin: 0 0.125rem;
            background-color: #9E9EA1;
            display: block;
            border-radius: 50%;
            opacity: 0.4;
        }

        .typing-indicator span:nth-of-type(1) {
            animation: typing 1s infinite;
        }

        .typing-indicator span:nth-of-type(2) {
            animation: typing 1s infinite 0.2s;
        }

        .typing-indicator span:nth-of-type(3) {
            animation: typing 1s infinite 0.4s;
        }

        @keyframes typing {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-0.3125rem);
            }

            100% {
                transform: translateY(0px);
            }
        }

        /* Markdown 样式 */
        .markdown-content {
            line-height: 1.6;
        }

        .markdown-content h1,
        .markdown-content h2,
        .markdown-content h3 {
            margin: 1rem 0 0.5rem 0;
        }

        .markdown-content p {
            margin: 0.5rem 0;
        }

        .markdown-content ul,
        .markdown-content ol {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }

        .markdown-content li {
            margin: 0.25rem 0;
        }

        .markdown-content blockquote {
            border-left: 4px solid #667eea;
            padding-left: 1rem;
            margin: 1rem 0;
            background-color: #f9f9f9;
            padding: 0.5rem 1rem;
            border-radius: 0.25rem;
        }

        .markdown-content table {
            border-collapse: collapse;
            width: 100%;
            margin: 1rem 0;
        }

        .markdown-content th,
        .markdown-content td {
            border: 1px solid #ddd;
            padding: 0.5rem;
        }

        .markdown-content th {
            background-color: #f2f2f2;
        }

        /* 代码块样式 */
        .code-block {
            position: relative;
            margin: 1rem 0;
            border-radius: 0.5rem;
            overflow: hidden;
        }

        .code-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #2d2d2d;
            color: #f8f8f2;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            border-bottom: 1px solid #444;
        }

        .code-language {
            font-weight: bold;
        }

        .code-copy-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #f8f8f2;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all 0.2s;
        }

        .code-copy-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .code-content {
            background-color: var(--code-bg);
            color: var(--code-text);
            padding: 1rem;
            overflow-x: auto;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.875rem;
            line-height: 1.4;
            margin: 0;
        }

        .code-content pre {
            margin: 0;
            white-space: pre-wrap;
            word-break: break-all;
        }

        .input-area {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            display: flex;
            padding: 1rem;
            background: white;
            border-top: 1px solid #eee;
            z-index: 100;
        }

        .input-area textarea {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid #eee;
            border-radius: 1.5rem;
            resize: none;
            font-size: 1rem;
            outline: none;
            transition: border-color 0.3s;
        }

        .input-area textarea:focus {
            border-color: #667eea;
        }

        .input-area button {
            margin-left: 0.75rem;
            padding: 0 1.5rem;
            background: var(--primary-gradient);
            color: white;
            border: none;
            border-radius: 1.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: bold;
            transition: transform 0.2s, box-shadow 0.2s;
            min-width: 4.5rem;
        }

        .input-area button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .input-area button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .error-message {
            color: var(--error-color);
            text-align: center;
            padding: 0.75rem;
            margin: 0.5rem;
            border-radius: 0.5rem;
            background-color: #fdf2f2;
            border: 1px solid #f5c6cb;
            display: none;
        }

        .context-indicator {
            background: rgba(102, 126, 234, 0.1);
            border: 1px solid rgba(102, 126, 234, 0.3);
            padding: 0.5rem;
            margin: 0.5rem;
            border-radius: 0.5rem;
            font-size: 0.8rem;
            color: #666;
            text-align: center;
            display: none;
        }

        /* 移动端优化 */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }

            .container {
                border-radius: 0;
                height: 100vh;
            }

            .header {
                padding: 1rem 0.75rem;
                position: relative;
            }

            .header-top {
                flex-direction: column;
                gap: 0.5rem;
            }

            .session-info {
                flex-direction: column;
                gap: 0.5rem;
            }

            .chat-history {
                padding: 0.75rem;
                gap: 0.75rem;
            }

            .message {
                max-width: 90%;
                padding: 0.75rem;
            }

            .input-area {
                padding: 0.75rem;
            }

            .input-area button {
                padding: 0 1.25rem;
            }

            .message-actions {
                opacity: 1;
            }
        }

        /* 平板优化 */
        @media (min-width: 769px) and (max-width: 1024px) {
            .container {
                margin: 1rem auto;
                border-radius: 1rem;
                max-width: 90%;
            }

            .input-area {
                padding-left: max(1rem, env(safe-area-inset-left));
                padding-right: max(1rem, env(safe-area-inset-right));
                padding-bottom: max(1rem, env(safe-area-inset-bottom));
            }
        }

        /* 桌面优化 */
        @media (min-width: 1025px) {
            .container {
                margin: 2rem auto;
                border-radius: 1rem;
            }
        }
    </style>
</head>

<body>
    <!-- 顶部导航栏 -->
    <?php include 'header.html'; ?>
    <div class="container">
        <div class="header">
            <div class="header-top">
                <h1>智影工场 - AI助手</h1>
                <div class="header-controls">
                    <button class="control-btn" id="resetConversation">重置对话</button>
                    <button class="control-btn" id="showSessionInfo">会话信息</button>
                    <button class="control-btn warning" id="clearHistory">清空历史</button>
                    <button class="control-btn" id="exportConversation">导出对话</button>
                </div>
                <div class="session-info">
                    <span>会话ID: <span id="sessionIdDisplay">新会话</span></span>
                    <span>历史记录: <span id="historyCount">0</span> 条</span>
                    <span>上下文Token: <span id="tokenCount">0</span></span>
                </div>
            </div>
            <div class="error-message" id="errorMessage"></div>
            <div class="context-indicator" id="contextIndicator">
                正在使用对话上下文...
            </div>
            <div class="chat-container">
                <div class="chat-history" id="chatHistory">
                    <div class="message bot-message">
                        您好！我是您的AI助手。
                    </div>
                </div>
                <div class="typing-indicator" id="typingIndicator">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
            <div class="input-area">
                <textarea id="messageInput" placeholder="请输入您的问题..." rows="1"></textarea>
                <button id="sendButton">发送</button>
            </div>
        </div>
        <div class="copy-notification" id="copyNotification">
            已复制到剪贴板
        </div>
        <script>
            // 获取当前用户ID
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            
            class ChatApp {
                constructor() {
                    this.chatHistory = document.getElementById('chatHistory');
                    this.messageInput = document.getElementById('messageInput');
                    this.sendButton = document.getElementById('sendButton');
                    this.typingIndicator = document.getElementById('typingIndicator');
                    this.errorMessage = document.getElementById('errorMessage');
                    this.copyNotification = document.getElementById('copyNotification');
                    this.contextIndicator = document.getElementById('contextIndicator');
                    this.sessionIdDisplay = document.getElementById('sessionIdDisplay');
                    this.historyCount = document.getElementById('historyCount');
                    this.tokenCount = document.getElementById('tokenCount');

                    // 会话管理
                    this.userId = currentUserId;
                    this.sessionId = localStorage.getItem(`user_${this.userId}_currentSessionId`) || this.generateSessionId();
                    this.conversationHistory = JSON.parse(localStorage.getItem(`user_${this.userId}_session_${this.sessionId}`)) || [];
                    this.isUsingContext = false;

                    this.initEventListeners();
                    this.autoResizeTextarea();
                    this.adjustChatHistoryHeight();
                    this.updateSessionDisplay();
                }

                initEventListeners() {
                    // 发送按钮点击事件
                    this.sendButton.addEventListener('click', () => {
                        this.sendMessage();
                    });

                    // 回车发送消息（Ctrl+Enter换行）
                    this.messageInput.addEventListener('keydown', (e) => {
                        if (e.key === 'Enter' && !e.ctrlKey && !e.shiftKey) {
                            e.preventDefault();
                            this.sendMessage();
                        }
                    });

                    // 输入框内容变化时调整高度
                    this.messageInput.addEventListener('input', () => {
                        this.autoResizeTextarea();
                    });

                    // 重置对话按钮
                    document.getElementById('resetConversation').addEventListener('click', () => {
                        this.resetConversation();
                    });

                    // 显示会话信息按钮
                    document.getElementById('showSessionInfo').addEventListener('click', () => {
                        this.showSessionInfo();
                    });

                    // 清空历史按钮
                    document.getElementById('clearHistory').addEventListener('click', () => {
                        this.clearHistory();
                    });

                    // 导出对话按钮
                    document.getElementById('exportConversation').addEventListener('click', () => {
                        this.exportConversation();
                    });

                    // 窗口大小变化时调整布局
                    window.addEventListener('resize', () => {
                        this.autoResizeTextarea();
                        this.adjustChatHistoryHeight();
                    });
                }

                generateSessionId() {
                    return 'sess_' + Date.now() + '_' + Math.random().toString(36).substring(2, 11);
                }

                autoResizeTextarea() {
                    this.messageInput.style.height = 'auto';
                    const maxHeight = window.innerWidth < 768 ? 120 : 150;
                    this.messageInput.style.height = (this.messageInput.scrollHeight > maxHeight ? maxHeight : this.messageInput.scrollHeight) + 'px';
                }

                adjustChatHistoryHeight() {
                    const inputAreaHeight = document.querySelector('.input-area').offsetHeight;
                    this.chatHistory.style.paddingBottom = inputAreaHeight + 'px';
                    this.scrollToBottom();
                }

                async sendMessage() {
                    const message = this.messageInput.value.trim();

                    if (!message) {
                        this.showError(' 请输入问题内容');
                        return;
                    }

                    // 添加用户消息到聊天记录
                    this.addMessageToHistory(message, 'user');

                    // 清空输入框并禁用
                    this.messageInput.value = '';
                    this.messageInput.disabled = true;
                    this.sendButton.disabled = true;
                    this.autoResizeTextarea();

                    // 显示打字指示器
                    this.showTypingIndicator();
                    this.hideError();

                    try {
                        // 调用API
                        const response = await this.callChatAPI(message);
                        const data = await response.json();

                        // 隐藏打字指示器
                        this.hideTypingIndicator();

                        // 获取AI回复内容
                        const botResponse = data.choices[0].message.content;

                        // 更新会话数据
                        this.conversationHistory.push({
                            role: 'user',
                            content: message,
                            timestamp: new Date().toISOString()
                        });

                        // 模拟流式输出效果
                        await this.simulateStreamingResponse(botResponse);

                        // 保存到本地存储
                        this.saveSessionData();

                    } catch (error) {
                        this.hideTypingIndicator();
                        console.error('API 调用错误:', error);
                        this.showError(' 网络错误，请稍后重试');
                        this.addMessageToHistory(' 抱歉，网络连接出现问题，请稍后重试。', 'bot');
                    } finally {
                        // 启用输入框
                        this.messageInput.disabled = false;
                        this.sendButton.disabled = false;
                        this.messageInput.focus();
                    }
                }

                async callChatAPI(userMessage) {
                    // 构建消息数组 
                    const messages = [{
                            role: "system",
                            content: "你是一个有用的AI助手，支持多轮对话。"
                        },
                        ...this.conversationHistory, // 展开历史消息 
                        {
                            role: "user",
                            content: userMessage
                        }
                    ];

                    const requestBody = {
                        messages: messages,
                        session_id: this.sessionId
                    };

                    // 上下文指示器逻辑 
                    if (this.conversationHistory.length > 0) {
                        this.isUsingContext = true;
                        this.showContextIndicator();
                    }

                    const response = await fetch('chat_api.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify(requestBody)
                    });

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }

                    return response;
                }

                // 模拟流式输出效果
                async simulateStreamingResponse(text) {
                    // 创建机器人消息元素
                    const botMessageDiv = document.createElement('div');
                    botMessageDiv.classList.add('message', 'bot-message');

                    // 添加复制按钮
                    const messageActions = document.createElement('div');
                    messageActions.classList.add('message-actions');

                    const copyButton = document.createElement('button');
                    copyButton.classList.add('copy-btn');
                    copyButton.title = "复制内容";
                    copyButton.innerHTML = '<svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg"><path  d="M8 4V16C8 16.5304 8.21071 17.0391 8.58579 17.4142C8.96086 17.7893 9.46957 18 10 18H18C18.5304 18 19.0391 17.7893 19.4142 17.4142C19.7893 17.0391 20 16.5304 20 16V7C20 6.46957 19.7893 5.96086 19.4142 5.58579C19.0391 5.21071 18.5304 5 18 5H10C9.46957 5 8.96086 5.21071 8.58579 5.58579C8.21071 5.96086 8 6.46957 8 7V4H8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/><path d="M16 4V16C16 16.5304 15.7893 17.0391 15.4142 17.4142C15.0391 17.7893 14.5304 18 14 18H6C5.46957 18 4.96086 17.7893 4.58579 17.4142C4.21071 17.0391 4 16.5304 4 16V4H16Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';

                    copyButton.addEventListener('click', (e) => {
                        e.stopPropagation();
                        this.copyToClipboard(text);
                    });

                    messageActions.appendChild(copyButton);
                    botMessageDiv.appendChild(messageActions);
                    this.chatHistory.appendChild(botMessageDiv);

                    // 逐字显示文本，模拟流式效果
                    let displayedText = '';
                    const characters = text.split('');

                    for (let i = 0; i < characters.length; i++) {
                        displayedText += characters[i];

                        // 解析Markdown并渲染
                        this.renderMarkdownContent(botMessageDiv, displayedText);
                        this.scrollToBottom();

                        // 控制显示速度，随机间隔创造更自然的效果
                        await new Promise(resolve => setTimeout(resolve, 20 + Math.random() * 30));
                    }

                    // 添加助手消息到历史记录
                    this.conversationHistory.push({
                        role: 'assistant',
                        content: text,
                        timestamp: new Date().toISOString()
                    });

                    this.scrollToBottom();
                }

                // 渲染Markdown内容
                renderMarkdownContent(container, text) {
                    // 简单的Markdown解析器
                    const markdownContent = document.createElement('div');
                    markdownContent.classList.add('markdown-content');

                    // 处理代码块
                    const codeBlockRegex = /```(\w+)?\n([\s\S]*?)```/g;
                    let processedText = text;

                    // 解析代码块
                    processedText = processedText.replace(codeBlockRegex, (match, language, code) => {
                        const codeBlock = document.createElement('div');
                        codeBlock.classList.add('code-block');

                        // 添加代码头
                        const codeHeader = document.createElement('div');
                        codeHeader.classList.add('code-header');

                        const langSpan = document.createElement('span');
                        langSpan.classList.add('code-language');
                        langSpan.textContent = language || 'code';

                        const codeCopyBtn = document.createElement('button');
                        codeCopyBtn.classList.add('code-copy-btn');
                        codeCopyBtn.textContent = '复制代码';

                        codeCopyBtn.addEventListener('click', (e) => {
                            e.stopPropagation();
                            this.copyToClipboard(code.trim());
                        });

                        codeHeader.appendChild(langSpan);
                        codeHeader.appendChild(codeCopyBtn);

                        const codeContent = document.createElement('div');
                        codeContent.classList.add('code-content');

                        const pre = document.createElement('pre');
                        pre.textContent = code.trim();
                        codeContent.appendChild(pre);

                        codeBlock.appendChild(codeHeader);
                        codeBlock.appendChild(codeContent);

                        return codeBlock.outerHTML;
                    });

                    // 处理其他Markdown元素
                    processedText = processedText
                        .replace(/\n#\s+(.*)/g, '<h1>$1</h1>')
                        .replace(/\n##\s+(.*)/g, '<h2>$1</h2>')
                        .replace(/\n\*\s+(.*)/g, '<li>$1</li>')
                        .replace(/\*\*(.*)\*\*/g, '<strong>$1</strong>')
                        .replace(/\*(.*)\*/g, '<em>$1</em>')
                        .replace(/\n>\s+(.*)/g, '<blockquote>$1</blockquote>');

                    markdownContent.innerHTML = processedText;
                    container.innerHTML = '';
                    container.appendChild(markdownContent);
                }

                // 复制到剪贴板
                copyToClipboard(text) {
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(text).then(() => {
                            this.showCopyNotification();
                        }).catch(err => {
                            console.error(' 复制失败:', err);
                            this.fallbackCopyText(text);
                        });
                    } else {
                        this.fallbackCopyText(text);
                    }
                }

                fallbackCopyText(text) {
                    const textArea = document.createElement('textarea');
                    textArea.value = text;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.select();
                    document.execCommand('copy');
                    document.body.removeChild(textArea);
                    this.showCopyNotification();
                }

                showCopyNotification() {
                    this.copyNotification.style.display = 'block';
                    setTimeout(() => {
                        this.copyNotification.style.display = 'none';
                    }, 2000);
                }

                addMessageToHistory(content, sender) {
                    const messageDiv = document.createElement('div');
                    messageDiv.classList.add('message');
                    messageDiv.classList.add(sender === 'user' ? 'user-message' : 'bot-message');
                    messageDiv.textContent = content;

                    this.chatHistory.appendChild(messageDiv);

                    // 滚动到底部
                    this.scrollToBottom();
                }

                showTypingIndicator() {
                    this.typingIndicator.style.display = 'flex';
                    this.scrollToBottom();
                }

                hideTypingIndicator() {
                    this.typingIndicator.style.display = 'none';
                }

                showContextIndicator() {
                    this.contextIndicator.style.display = 'block';
                    setTimeout(() => {
                        this.contextIndicator.style.display = 'none';
                    }, 1500);
                }

                showError(message) {
                    this.errorMessage.textContent = message;
                    this.errorMessage.style.display = 'block';

                    // 3秒后自动隐藏错误信息
                    setTimeout(() => {
                        this.hideError();
                    }, 3000);
                }

                hideError() {
                    this.errorMessage.style.display = 'none';
                }

                scrollToBottom() {
                    requestAnimationFrame(() => {
                        this.chatHistory.scrollTop = this.chatHistory.scrollHeight;
                    });
                }

                // 会话管理方法
                resetConversation() {
                    const userConfirmed = confirm('确定要重置当前对话吗？这将清除所有对话历史并开始新的会话。');

                    if (userConfirmed) {
                        try {
                            // 创建新的会话ID 
                            this.sessionId = this.generateSessionId();
                            this.conversationHistory = [];
                            this.chatHistory.innerHTML = '';

                            // 添加初始欢迎消息 
                            this.addMessageToHistory(' 您好！我是您的AI助手，我支持多轮对话，可以记住我们之前的聊天内容。', 'bot');

                            this.saveSessionData();
                            this.updateSessionDisplay();

                            console.log(' 对话已重置，新会话ID：', this.sessionId);
                        } catch (error) {
                            console.error(' 重置对话时发生错误：', error);
                            alert('重置对话失败，请重试。');
                        }
                    }
                }

                clearHistory() {
                    const userConfirmed = confirm('确定要清空所有对话历史吗？这将永久删除所有会话数据，且无法恢复。');

                    if (userConfirmed) {
                        try {
                            // 清除所有会话数据 
                            this.sessionId = this.generateSessionId();
                            this.conversationHistory = [];
                            this.chatHistory.innerHTML = '';

                            // 添加初始欢迎消息 
                            this.addMessageToHistory(' 您好！我是您的AI助手，我支持多轮对话，可以记住我们之前的聊天内容。', 'bot');

                            // 保存空的历史
                            this.saveSessionData();
                            this.updateSessionDisplay();

                            console.log(' 所有对话历史已清空');
                        } catch (error) {
                            console.error(' 清空历史时发生错误：', error);
                            alert('清空历史失败，请重试。');
                        }
                    }
                }

                showSessionInfo() {
                    const totalTokens = this.calculateTotalTokens();
                    const info = `会话信息： 
• 会话ID: ${this.sessionId} 
• 历史记录: ${this.conversationHistory.length}  条消息
• 上下文Token: ${totalTokens}`;

                    alert(info);
                }

                exportConversation() {
                    const conversationData = {
                        sessionId: this.sessionId,
                        exportTime: new Date().toISOString(),
                        history: this.conversationHistory
                    };

                    const blob = new Blob([JSON.stringify(conversationData, null, 2)], {
                        type: 'application/json'
                    });

                    const url = URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `conversation_${this.sessionId}_${new  Date().getTime()}.json`;
                    document.body.appendChild(a);
                    a.click();
                    document.body.removeChild(a);
                    URL.revokeObjectURL(url);

                    alert('对话已导出为JSON文件');
                }

                saveSessionData() {
                    // 保存当前会话 
                    localStorage.setItem(`user_${this.userId}_session_${this.sessionId}`, JSON.stringify(this.conversationHistory));
                    localStorage.setItem(`user_${this.userId}_currentSessionId`, this.sessionId);
                }

                updateSessionDisplay() {
                    this.sessionIdDisplay.textContent = this.sessionId;
                    this.historyCount.textContent = this.conversationHistory.length;
                    this.tokenCount.textContent = this.calculateTotalTokens();
                }

                calculateTotalTokens() {
                    return this.conversationHistory.reduce((total, msg) => total + (msg.content ? msg.content.length : 0), 0);
                }
            }

            // 初始化应用 
            document.addEventListener('DOMContentLoaded', () => {
                const app = new ChatApp();

                // 初始调整高度 
                setTimeout(() => {
                    app.adjustChatHistoryHeight();
                }, 100);
            });
        </script>
    </div>
</body>

</html>
