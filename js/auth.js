// auth.js - 公共认证JS库

// 防止checkLoginStatus函数被重复调用的标志
let isCheckingLoginStatus = false;

// 显示消息
function showMessage(text, type) {
    const message = document.getElementById('message');
    if (message) {
        message.textContent = text;
        message.className = 'message ' + type;
        message.style.display = 'block';
        
        // 3秒后隐藏消息
        setTimeout(() => {
            message.style.display = 'none';
        }, 3000);
    } else {
        console.log(type + ': ' + text);
    }
}

// 发送AJAX请求
function sendRequest(url, data, method = 'POST') {
    return new Promise((resolve, reject) => {
        const xhr = new XMLHttpRequest();
        xhr.open(method, url, true);
        
        if (method === 'POST') {
            xhr.setRequestHeader('Content-Type', 'application/json');
        }
        
        xhr.onload = function() {
            if (xhr.status >= 200 && xhr.status < 300) {
                try {
                    const response = JSON.parse(xhr.responseText);
                    resolve(response);
                } catch (e) {
                    reject(new Error('Invalid JSON response'));
                }
            } else {
                reject(new Error('Request failed with status: ' + xhr.status));
            }
        };
        
        xhr.onerror = function() {
            reject(new Error('Network error'));
        };
        
        if (method === 'POST') {
            xhr.send(JSON.stringify(data));
        } else {
            xhr.send();
        }
    });
}

// 加载页面内容（仅对已登录用户）
function loadPageContent(protectPage = false) {
    // 获取当前页面名称
    const currentPage = window.location.pathname.split('/').pop();
    
    // 发送请求获取页面内容授权
    sendRequest('auth_api.php?action=getPageContent', { page: currentPage }, 'POST')
        .then(response => {
            if (response.success) {
                // 已授权，更新用户信息并显示页面内容
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(response.user, true);
                }
                // 显示页面内容
                const pageContent = document.getElementById('pageContent');
                if (pageContent) {
                    pageContent.style.display = 'block';
                }
            } else {
                // 未授权，更新用户信息
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(null, false);
                }
                // 重定向到登录页面
                window.location.href = 'login.html';
            }
        })
        .catch(error => {
            // console.error('加载页面内容失败:', error);
            // 加载失败，重定向到登录页面
            window.location.href = 'login.html';
        });
}

// 检查登录状态并控制页面访问
function checkLoginStatus(protectPage = false) {
    // 防止重复调用
    if (isCheckingLoginStatus) {
        return;
    }
    isCheckingLoginStatus = true;
    
    sendRequest('auth_api.php?action=getCurrentUser', {}, 'GET')
        .then(response => {
            if (response.success) {
                // 已登录，更新用户信息显示
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(response.user, true);
                }
                // 如果是受保护页面，加载页面内容
                if (protectPage) {
                    loadPageContent();
                } else {
                    // 显示页面内容（如果有隐藏的话）
                    const pageContent = document.getElementById('pageContent');
                    if (pageContent) {
                        pageContent.style.display = 'block';
                    }
                }
            } else {
                // 未登录
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(null, false);
                }
                // 如果是受保护页面，重定向到登录页面
                if (protectPage) {
                    window.location.href = 'login.html';
                }
            }
        })
        .catch(error => {
            // console.error('检查登录状态失败:', error);
            // 检查失败时，默认视为未登录
            if (protectPage) {
                window.location.href = 'login.html';
            }
        })
        .finally(() => {
            // 无论成功或失败，都重置标志
            isCheckingLoginStatus = false;
        });
}

// 用户登录（密码登录）
function authLogin(identifier, password, type = 'username') {
    const data = {
        identifier: identifier,
        password: password,
        type: type
    };
    
    sendRequest('auth_api.php?action=login', data)
        .then(response => {
            if (response.success) {
                showMessage('登录成功', 'success');
                // 更新用户信息显示
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(response.user);
                }
                // 跳转到用户中心页面
                setTimeout(() => {
                    window.location.href = 'usercenter.php';
                }, 1500);
            } else {
                showMessage(response.message || '登录失败', 'error');
            }
        })
        .catch(error => {
            // console.error('登录失败:', error);
            showMessage('登录失败，请稍后重试', 'error');
        });
}

// 发送短信验证码
function sendSmsVerification_frontend(phone) {
    const data = {
        phone: phone
    };
    
    // 获取发送按钮
    let buttonId = 'send-phone-code';
    if (!document.getElementById(buttonId)) {
        buttonId = 'sendCodeBtn';
    }
    
    const button = document.getElementById(buttonId);
    if (button) {
        button.disabled = true;
        button.textContent = '发送中...';
    }
    
    sendRequest('auth_api.php?action=sendSms', data)
        .then(response => {
            if (response.success) {
                showMessage(response.message || '验证码发送成功', 'success');
                
                // 倒计时
                let countdown = 60;
                if (button) {
                    button.textContent = `${countdown}秒后重试`;
                    
                    const timer = setInterval(() => {
                        countdown--;
                        button.textContent = `${countdown}秒后重试`;
                        
                        if (countdown <= 0) {
                            clearInterval(timer);
                            button.disabled = false;
                            button.textContent = '发送验证码';
                        }
                    }, 1000);
                }
            } else {
                showMessage(response.message || '验证码发送失败', 'error');
                // 即使发送失败，也添加冷却期，防止重复发送
                if (button) {
                    let countdown = 30;
                    button.textContent = `${countdown}秒后重试`;
                    
                    const timer = setInterval(() => {
                        countdown--;
                        button.textContent = `${countdown}秒后重试`;
                        
                        if (countdown <= 0) {
                            clearInterval(timer);
                            button.disabled = false;
                            button.textContent = '发送验证码';
                        }
                    }, 1000);
                }
            }
        })
        .catch(error => {
            // console.error('发送验证码失败:', error);
            showMessage('发送验证码失败，请稍后重试', 'error');
            // 即使网络错误，也添加冷却期，防止重复发送
            if (button) {
                let countdown = 30;
                button.textContent = `${countdown}秒后重试`;
                
                const timer = setInterval(() => {
                    countdown--;
                    button.textContent = `${countdown}秒后重试`;
                    
                    if (countdown <= 0) {
                        clearInterval(timer);
                        button.disabled = false;
                        button.textContent = '发送验证码';
                    }
                }, 1000);
            }
        });
}

// 发送邮件验证码
function sendEmailVerification(email) {
    const data = {
        email: email
    };
    
    // 获取发送按钮
    const button = document.getElementById('send-email-code');
    if (button) {
        button.disabled = true;
        button.textContent = '发送中...';
    }
    
    sendRequest('auth_api.php?action=sendEmail', data)
        .then(response => {
            if (response.success) {
                showMessage(response.message || '验证码发送成功', 'success');
                
                // 倒计时
                let countdown = 60;
                if (button) {
                    button.textContent = `${countdown}秒后重试`;
                    
                    const timer = setInterval(() => {
                        countdown--;
                        button.textContent = `${countdown}秒后重试`;
                        
                        if (countdown <= 0) {
                            clearInterval(timer);
                            button.disabled = false;
                            button.textContent = '发送验证码';
                        }
                    }, 1000);
                }
            } else {
                showMessage(response.message || '验证码发送失败', 'error');
                if (button) {
                    button.disabled = false;
                    button.textContent = '发送验证码';
                }
            }
        })
        .catch(error => {
            // console.error('发送验证码失败:', error);
            showMessage('发送验证码失败，请稍后重试', 'error');
            if (button) {
                button.disabled = false;
                button.textContent = '发送验证码';
            }
        });
}

// 发送验证码（统一入口）
function sendVerificationCode(target, type) {
    if (type === 'sms') {
        sendSmsVerification(target);
    } else if (type === 'email') {
        sendEmailVerification(target);
    } else {
        showMessage('无效的验证码类型', 'error');
    }
}

// 发送短信验证码（便捷方法）
function sendSmsCode(phone) {
    sendSmsVerification(phone);
}

// 用户注册
function registerUser(data) {
    const registerData = {
        username: data.username,
        password: data.password,
        confirm_password: data.password,
        phone: data.phone,
        email: data.email,
        phone_code: data.phone_code,
        email_code: data.email_code
    };
    
    sendRequest('auth_api.php?action=register', registerData)
        .then(response => {
            if (response.success) {
                showMessage('注册成功', 'success');
                // 跳转到用户中心页面
            setTimeout(() => {
                window.location.href = 'usercenter.php';
            }, 1500);
            } else {
                showMessage(response.message || '注册失败', 'error');
            }
        })
        .catch(error => {
            // console.error('注册失败:', error);
            showMessage('注册失败，请稍后重试', 'error');
        });
}

// 手机号验证码登录
function authLoginWithVerificationCode(phone, code) {
    const data = {
        phone: phone,
        code: code
    };
    
    sendRequest('auth_api.php?action=loginWithCode', data)
        .then(response => {
            if (response.success) {
                showMessage('登录成功', 'success');
                // 更新用户信息显示
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(response.user);
                }
                // 跳转到用户中心页面
                setTimeout(() => {
                    window.location.href = 'usercenter.php';
                }, 1500);
            } else {
                showMessage(response.message || '登录失败', 'error');
            }
        })
        .catch(error => {
            // console.error('登录失败:', error);
            showMessage('登录失败，请稍后重试', 'error');
        });
}

// 手机号一键登录
function authLoginWithOneClick(phone) {
    const data = {
        phone: phone
    };
    
    sendRequest('auth_api.php?action=oneClickLogin', data)
        .then(response => {
            if (response.success) {
                showMessage('登录成功', 'success');
                // 更新用户信息显示
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(response.user);
                }
                // 跳转到首页或其他页面
                setTimeout(() => {
                    window.location.href = 'usercenter.php';
                }, 1500);
            } else {
                showMessage(response.message || '登录失败', 'error');
            }
        })
        .catch(error => {
            // console.error('登录失败:', error);
            showMessage('登录失败，请稍后重试', 'error');
        });
}

// 用户退出
function authLogout() {
    sendRequest('auth_api.php?action=logout', {}, 'POST')
        .then(response => {
            if (response.success) {
                showMessage('退出成功', 'success');
                // 更新用户信息显示
                if (typeof updateUserInfo === 'function') {
                    updateUserInfo(null);
                }
                // 跳转到登录页面
                setTimeout(() => {
                    window.location.href = 'index.html';
                }, 1500);
            } else {
                showMessage(response.message || '退出失败', 'error');
            }
        })
        .catch(error => {
            // console.error('退出失败:', error);
            showMessage('退出失败，请稍后重试', 'error');
        });
}

// 页面加载完成后自动检查登录状态 - 确保只调用一次
function initAuth() {
    checkLoginStatus();
}

// 使用一次性事件监听器，确保只绑定一次
if (document.readyState === 'loading') {
    // 添加DOMContentLoaded事件监听器，使用once选项确保只调用一次
    document.addEventListener('DOMContentLoaded', initAuth, { once: true });
} else {
    // 如果DOM已经加载完成，直接调用一次
    initAuth();
}
