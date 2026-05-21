<?php
/**
 * 拉黑车牌通知推送接口
 * 当车牌被加入拉黑列表或解除拉黑时发送通知
 */

date_default_timezone_set('Asia/Shanghai');

if (file_exists(__DIR__ . '/../daemon/config.php')) {
    require_once __DIR__ . '/../daemon/config.php';
}
require_once __DIR__ . '/wechat_work_webhook_notify.php';

function sendServerChanMessage($title, $desp = '')
{
    if (!defined('NOTIFY_SERVERCHAN') || !NOTIFY_SERVERCHAN || !defined('SERVERCHAN_SENDKEY')) {
        return ['success' => false, 'message' => 'Server酱通知未启用'];
    }

    $url = "https://sctapi.ftqq.com/" . SERVERCHAN_SENDKEY . ".send";

    $data = [
        'title' => $title,
        'desp' => $desp
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result && isset($result['code']) && $result['code'] === 0) {
        return ['success' => true, 'message' => '发送成功'];
    } else {
        return ['success' => false, 'error' => $result['message'] ?? '未知错误'];
    }
}

function sendDingTalkMessage($title, $content)
{
    if (!defined('NOTIFY_DINGTALK') || !NOTIFY_DINGTALK || !defined('DINGTALK_WEBHOOK')) {
        return ['success' => false, 'message' => '钉钉通知未启用'];
    }

    $webhook = DINGTALK_WEBHOOK;
    $secret = defined('DINGTALK_SECRET') ? DINGTALK_SECRET : '';

    if (!empty($secret)) {
        $timestamp = time() * 1000;
        $sign = base64_encode(hash_hmac('sha256', $timestamp . "\n" . $secret, $secret, true));
        $webhook .= '&timestamp=' . $timestamp . '&sign=' . urlencode($sign);
    }

    $data = [
        'msgtype' => 'markdown',
        'markdown' => [
            'title' => $title,
            'text' => $content
        ]
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $webhook);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);

    if ($result && isset($result['errcode']) && $result['errcode'] === 0) {
        return ['success' => true, 'message' => '发送成功'];
    } else {
        return ['success' => false, 'error' => $result['errmsg'] ?? '未知错误'];
    }
}

function notifyBlacklistChange($conn, $plateNumber, $actionType, $reason = '', $operator = 'system')
{
    $results = [];

    if ($actionType === 'ADD') {
        $typeText = '被拉黑';
        $emoji = '🔴';
        $title = "{$emoji} 车辆被拉黑 - {$plateNumber}";

        $sql = "SELECT blacklist_type, end_time FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $plateNumber);
        $stmt->execute();
        $result = $stmt->get_result();
        $plateInfo = $result->fetch_assoc();
        $stmt->close();

        $typeLabel = ($plateInfo['blacklist_type'] ?? 1) == 2 ? '永久拉黑' : '临时拉黑';
        $endTimeText = !empty($plateInfo['end_time']) ? date('Y-m-d H:i:s', strtotime($plateInfo['end_time'])) : '永久';

        $content = "车牌号：{$plateNumber}\n";
        $content .= "拉黑类型：{$typeLabel}\n";
        $content .= "结束时间：{$endTimeText}\n";
        if (!empty($reason)) {
            $content .= "拉黑原因：{$reason}\n";
        }
        $content .= "操作人：{$operator}\n";
        $content .= "操作时间：" . date('Y-m-d H:i:s') . "\n";
        $content .= "请注意该车辆的进出记录";

    } elseif ($actionType === 'REMOVE') {
        $typeText = '解除拉黑';
        $emoji = '🟢';
        $title = "{$emoji} 车辆解除拉黑 - {$plateNumber}";

        $content = "车牌号：{$plateNumber}\n";
        $content .= "操作类型：解除拉黑\n";
        if (!empty($reason)) {
            $content .= "原因：{$reason}\n";
        }
        $content .= "操作人：{$operator}\n";
        $content .= "操作时间：" . date('Y-m-d H:i:s') . "\n";
        $content .= "该车辆已恢复正常通行";

    } else {
        $typeText = '更新信息';
        $emoji = '📝';
        $title = "{$emoji} 车辆拉黑信息更新 - {$plateNumber}";

        $content = "车牌号：{$plateNumber}\n";
        $content .= "操作类型：信息更新\n";
        if (!empty($reason)) {
            $content .= "更新内容：{$reason}\n";
        }
        $content .= "操作人：{$operator}\n";
        $content .= "操作时间：" . date('Y-m-d H:i:s');
    }
    
    $serverChanResult = ['success' => false, 'message' => '已禁用'];
    if (defined('NOTIFY_SERVERCHAN') && NOTIFY_SERVERCHAN) {
        $serverChanResult = sendServerChanMessage($title, $content);
    }
    $results['serverchan'] = $serverChanResult;

    $dingTalkResult = ['success' => false, 'message' => '已禁用'];
    if (defined('NOTIFY_DINGTALK') && NOTIFY_DINGTALK) {
        $dingTalkResult = sendDingTalkMessage($title, $content);
    }
    $results['dingtalk'] = $dingTalkResult;

    $wechatResult = sendWechatWorkWebhookMessage($title, $content);
    $results['wechat_work'] = $wechatResult;

    return $results;
}

function testNotification()
{
    $results = [];

    $title = "🔔 CarAlert 通知测试";
    $content = "测试消息\n\n车辆监控系统已配置成功！\n\n测试时间：" . date('Y-m-d H:i:s') . "\n\n请确认您收到了这条消息。";

    $serverChanResult = ['success' => false, 'message' => '已禁用'];
    if (defined('NOTIFY_SERVERCHAN') && NOTIFY_SERVERCHAN) {
        $serverChanResult = sendServerChanMessage($title, $content);
    }
    $results['serverchan'] = $serverChanResult;

    $dingTalkResult = ['success' => false, 'message' => '已禁用'];
    if (defined('NOTIFY_DINGTALK') && NOTIFY_DINGTALK) {
        $dingTalkResult = sendDingTalkMessage($title, $content);
    }
    $results['dingtalk'] = $dingTalkResult;

    $wechatResult = sendWechatWorkWebhookMessage($title, $content);
    $results['wechat_work'] = $wechatResult;

    return $results;
}

if (isset($_SERVER['REQUEST_URI']) && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $action = $_GET['action'] ?? 'test';

    header('Content-Type: application/json; charset=utf-8');

    if ($action === 'test') {
        $results = testNotification();
        echo json_encode(['success' => true, 'results' => $results], JSON_UNESCAPED_UNICODE);
    }
}
