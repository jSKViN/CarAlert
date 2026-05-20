<?php
/**
 * 企业微信 Webhook 机器人通知接口
 */

date_default_timezone_set('Asia/Shanghai');

if (file_exists(__DIR__ . '/../daemon/config.php')) {
    require_once __DIR__ . '/../daemon/config.php';
}

function sendWechatWorkWebhookMessage($title, $content)
{
    $data = [
        'msgtype' => 'text',
        'text' => [
            'content' => "{$title}\n\n{$content}"
        ]
    ];

    $webhooks = [];
    
    // 测试环境
    if (defined('NOTIFY_WECHAT_WORK_WEBHOOK') && NOTIFY_WECHAT_WORK_WEBHOOK && defined('WECHAT_WORK_WEBHOOK') && WECHAT_WORK_WEBHOOK) {
        $webhooks['测试环境'] = WECHAT_WORK_WEBHOOK;
    }
    
    // 正式环境（独立开关控制）
    if (defined('NOTIFY_WECHAT_WORK_WEBHOOK_PROD') && NOTIFY_WECHAT_WORK_WEBHOOK_PROD && defined('WECHAT_WORK_WEBHOOK_PROD') && WECHAT_WORK_WEBHOOK_PROD) {
        $webhooks['正式环境'] = WECHAT_WORK_WEBHOOK_PROD;
    }

    if (empty($webhooks)) {
        return ['success' => false, 'message' => '企业微信通知未启用'];
    }

    $results = [];
    foreach ($webhooks as $env => $webhookUrl) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhookUrl);
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
            $results[$env] = ['success' => true, 'message' => '发送成功'];
        } else {
            $results[$env] = ['success' => false, 'error' => $result['errmsg'] ?? '未知错误'];
        }
    }

    $allSuccess = true;
    foreach ($results as $r) {
        if (!$r['success']) {
            $allSuccess = false;
            break;
        }
    }

    return [
        'success' => $allSuccess,
        'message' => $allSuccess ? '已发送到所有启用的环境' : '部分环境发送失败',
        'details' => $results
    ];
}

function sendVehicleNotification($record)
{
    $directionText = $record['direction'] === 'IN' || $record['direction'] === '进入' ? '入口抓拍' : '出口抓拍';
    $time = date('Y-m-d H:i:s', strtotime($record['timestamp']));

    $title = "🚗 车辆{$directionText} - {$record['licensePlate']}";

    $content = "车牌号：{$record['licensePlate']}\n";
    $content .= "抓拍类型：{$directionText}\n";
    $content .= "时间：{$time}\n";
    $content .= "车道：{$record['laneName']}\n";

    if (!empty($record['ownerName'])) {
        $content .= "姓名：{$record['ownerName']}\n";
    }

    if (!empty($record['ownerDepartment'])) {
        $content .= "单位部门：{$record['ownerDepartment']}\n";
    }

    return sendWechatWorkWebhookMessage($title, $content);
}

function sendWeChatWorkWebhookVehicleNotification($record)
{
    return sendVehicleNotification($record);
}

function sendWeChatWorkWebhookBlacklistAlert($alertData)
{
    $directionText = $alertData['direction'] === 'IN' || $alertData['direction'] === '进入' ? '进入' : '离开';
    $time = date('Y-m-d H:i:s', strtotime($alertData['timestamp']));

    $title = "⛔ 拉黑车牌预警 - {$alertData['licensePlate']}";

    $content = "⚠️ 警告：发现拉黑车辆{$directionText}\n";
    $content .= "车牌号：{$alertData['licensePlate']}\n";
    $content .= "拉黑类型：{$alertData['blacklistType']}\n";
    $content .= "拉黑原因：{$alertData['reason']}\n";
    $content .= "抓拍时间：{$time}\n";
    $content .= "车道：{$alertData['laneName']}\n";
    $content .= "方向：{$directionText}\n";

    return sendWechatWorkWebhookMessage($title, $content);
}

if (isset($_SERVER['REQUEST_URI']) && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $action = $_GET['action'] ?? 'test';

    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {
        case 'test':
            $result = sendWechatWorkWebhookMessage(
                '🎉 车辆监控系统测试',
                "**测试消息**\n\n车辆监控系统已配置成功！\n\n**测试时间**：" . date('Y-m-d H:i:s') . "\n\n请确认您收到了这条消息。"
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
}
