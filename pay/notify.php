<?php
// pay/notify.php - 微信支付回调处理
header('Content-Type: text/xml; charset=utf-8');

// 引入配置
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Auth.php';

// 设置时区
date_default_timezone_set('Asia/Shanghai');

// 日志函数
function wxLog($message, $level = 'INFO') {
    if (!Config::WX_LOG_ENABLED) return;

    $logDir = dirname(Config::WX_LOG_PATH);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] [{$level}] {$message}\n";
    file_put_contents(Config::WX_LOG_PATH, $log, FILE_APPEND);
}

// 生成签名（V2接口用）
function makeSign($params) {
    foreach ($params as $k => $v) {
        if ($v === '' || $k === 'sign') {
            unset($params[$k]);
        }
    }
    ksort($params);
    $string = '';
    foreach ($params as $k => $v) {
        $string .= "{$k}={$v}&";
    }
    $string .= "key=" . Config::WX_KEY;
    return strtoupper(md5($string));
}

// 数组转XML
function arrayToXml($arr) {
    $xml = "<xml>";
    foreach ($arr as $key => $val) {
        if (is_numeric($val)) {
            $xml .= "<{$key}>{$val}</{$key}>";
        } else {
            $xml .= "<{$key}><![CDATA[{$val}]]></{$key}>";
        }
    }
    $xml .= "</xml>";
    return $xml;
}

// XML转数组 - 使用XMLReader（最安全）
function xmlToArray($xml)
{
    if (!$xml) return [];

    $reader = new XMLReader();

    try {
        // 设置安全选项
        $reader->xml($xml, null, LIBXML_NONET | LIBXML_NOCDATA);

        // 禁用外部实体（关键安全设置）
        $reader->setParserProperty(XMLReader::SUBST_ENTITIES, false);

        // 读取XML
        $result = [];
        while ($reader->read()) {
            // 这里可以根据需要处理节点 
            // 简单示例：只处理元素节点
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $nodeName = $reader->name;
                // 读取节点值 
                $reader->read();
                if ($reader->nodeType === XMLReader::TEXT) {
                    $result[$nodeName] = $reader->value;
                }
            }
        }

        $reader->close();
        return $result;
    } catch (Exception $e) {
        if (isset($reader)) {
            $reader->close();
        }
        return [];
    }
}

// 返回微信确认结果
function returnWechatResult($returnCode, $returnMsg = '') {
    $result = [
        'return_code' => $returnCode,
        'return_msg' => $returnMsg,
    ];
    echo arrayToXml($result);
    exit;
}

// 处理充值业务
function handleRecharge($userId, $amount, $orderNo) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $auth = new Auth();
        
        // 开始事务
        $pdo->beginTransaction();
        
        // 根据金额计算积分
        $yuanAmount = $amount / 100;
        if ($yuanAmount == 1) {
            $points = 100;
        } elseif ($yuanAmount == 99) {
            $points = 10000 + 2000; // 额外送2000积分
        } elseif ($yuanAmount == 599) {
            $points = 70000 + 1000; // 额外送1000积分
        } else {
            $points = $yuanAmount * Config::RECHARGE_RATE;
        }
        
        // 更新充值记录状态
        $sql = "UPDATE recharge_records SET status = 1, paid_at = CURRENT_TIMESTAMP WHERE order_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNo]);
        
        // 更新用户积分
        $auth->addUserPoints($userId, $points, '充值', 'wechat_pay', $orderNo, "微信充值 {$yuanAmount} 元，获得 {$points} 积分");
        
        // 提交事务
        $pdo->commit();
        
        wxLog("充值成功 - 用户ID: {$userId}, 订单号: {$orderNo}, 金额: {$yuanAmount}元, 积分: {$points}");
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        wxLog("充值失败 - 用户ID: {$userId}, 订单号: {$orderNo}, 错误: {$e->getMessage()}", 'ERROR');
        return false;
    }
}

// 处理会员购买业务
function handleMembership($userId, $amount, $orderNo, $attach) {
    try {
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        $auth = new Auth();
        
        // 开始事务
        $pdo->beginTransaction();
        
        $yuanAmount = $amount / 100;
        $level = 0;
        $points = 0;
        $description = '';
        
        // 根据金额确定会员等级和赠送积分
        if ($yuanAmount == 9.9) {
            $level = 1; // 个人用户月卡
            $points = 500; // 每月送500积分
            $description = "购买个人会员月卡 {$yuanAmount} 元";
        } elseif ($yuanAmount == 99) {
            $level = 1; // 个人用户年卡
            $points = 500; // 每月送500积分
            $description = "购买个人会员年卡 {$yuanAmount} 元";
        } elseif ($yuanAmount == 299) {
            $level = 3; // 贵宾用户月卡
            $points = 5000; // 每月送5000积分
            $description = "购买贵宾会员月卡 {$yuanAmount} 元";
        } elseif ($yuanAmount == 2999 || $yuanAmount == 2990) {
            $level = 3; // 贵宾用户年卡
            $points = 5000; // 每月送5000积分
            $description = "购买贵宾会员年卡 {$yuanAmount} 元";
        }
        
        // 更新消费记录
        $sql = "INSERT INTO consumption_records (user_id, amount, order_no, item_type, description, created_at) VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId, $yuanAmount, $orderNo, 'membership', $description]);
        
        // 更新用户等级
        $sql = "UPDATE users SET level = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$level, $userId]);
        
        // 赠送积分
        if ($points > 0) {
            $auth->addUserPoints($userId, $points, '会员赠送', 'membership', $orderNo, "会员赠送 {$points} 积分");
        }
        
        // 提交事务
        $pdo->commit();
        
        wxLog("会员购买成功 - 用户ID: {$userId}, 订单号: {$orderNo}, 金额: {$yuanAmount}元, 等级: {$level}, 积分: {$points}");
        return true;
    } catch (Exception $e) {
        $pdo->rollback();
        wxLog("会员购买失败 - 用户ID: {$userId}, 订单号: {$orderNo}, 错误: {$e->getMessage()}", 'ERROR');
        return false;
    }
}

// 主处理逻辑
try {
    // 获取微信回调数据
    $xml = file_get_contents('php://input');
    wxLog("收到微信回调: {$xml}");
    
    // 解析XML
    $data = xmlToArray($xml);
    
    // 验证签名
    $sign = $data['sign'];
    unset($data['sign']);
    $newSign = makeSign($data);
    
    if ($sign !== $newSign) {
        wxLog("签名验证失败: 微信签名={$sign}, 计算签名={$newSign}", 'ERROR');
        returnWechatResult('FAIL', '签名验证失败');
    }
    
    // 检查通信状态
    if ($data['return_code'] !== 'SUCCESS') {
        wxLog("通信失败: {$data['return_msg']}", 'ERROR');
        returnWechatResult('FAIL', $data['return_msg']);
    }
    
    // 检查业务状态
    if ($data['result_code'] !== 'SUCCESS') {
        wxLog("业务失败: {$data['err_code']} - {$data['err_code_des']}", 'ERROR');
        returnWechatResult('FAIL', $data['err_code_des']);
    }
    
    // 获取订单信息
    $orderNo = $data['out_trade_no'];
    $amount = $data['total_fee'];
    $attach = $data['attach'] ?? 'recharge';
    $openid = $data['openid'];
    
    // 根据attach确定业务类型
    if ($attach == 'recharge') {
        // 充值业务
        // 从数据库获取用户ID
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 假设我们在创建充值记录时已经保存了user_id和order_no的对应关系
        $sql = "SELECT user_id FROM recharge_records WHERE order_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['user_id']) {
            $userId = $result['user_id'];
            handleRecharge($userId, $amount, $orderNo);
        } else {
            wxLog("充值失败 - 未找到用户ID: 订单号={$orderNo}", 'ERROR');
        }
    } elseif ($attach == 'membership') {
        // 会员购买业务
        $db = Database::getInstance();
        $pdo = $db->getPdo();
        
        // 从数据库获取用户ID
        $sql = "SELECT user_id FROM consumption_records WHERE order_no = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$orderNo]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && $result['user_id']) {
            $userId = $result['user_id'];
            handleMembership($userId, $amount, $orderNo, $attach);
        } else {
            wxLog("会员购买失败 - 未找到用户ID: 订单号={$orderNo}", 'ERROR');
        }
    }
    
    // 返回成功响应
    returnWechatResult('SUCCESS', 'OK');
} catch (Exception $e) {
    wxLog("回调处理异常: {$e->getMessage()}", 'ERROR');
    returnWechatResult('FAIL', '处理异常');
}
?>
