<?php
/**
 * 钉钉机器人通知接口
 */

date_default_timezone_set('Asia/Shanghai');

if (file_exists(__DIR__ . '/../daemon/config.php')) {
    require_once __DIR__ . '/../daemon/config.php';
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

function sendDingTalkVehicleNotification($record)
{
    $directionText = $record['direction'] === 'IN' || $record['direction'] === '进入' ? '入口抓拍' : '出口抓拍';
    $time = date('Y-m-d H:i:s', strtotime($record['timestamp']));

    $title = "🚗 车辆{$directionText} - {$record['licensePlate']}";

    $content = "### 车辆监控通知\n\n";
    $content .= "**车牌号**：{$record['licensePlate']}\n\n";
    $content .= "**抓拍类型**：{$directionText}\n\n";
    $content .= "**时间**：{$time}\n\n";
    $content .= "**车道**：{$record['laneName']}\n\n";

    if (!empty($record['ownerName'])) {
        $content .= "**姓名**：{$record['ownerName']}\n\n";
    }

    if (!empty($record['ownerDepartment'])) {
        $content .= "**单位部门**：{$record['ownerDepartment']}\n\n";
    }

    return sendDingTalkMessage($title, $content);
}

function sendDingTalkBlacklistAlert($alertData)
{
    $directionText = $alertData['direction'] === 'IN' || $alertData['direction'] === '进入' ? '进入' : '离开';
    $time = date('Y-m-d H:i:s', strtotime($alertData['timestamp']));

    $title = "⛔ 拉黑车牌预警 - {$alertData['licensePlate']}";

    $content = "### ⚠️ 警告：发现拉黑车辆{$directionText}\n\n";
    $content .= "**车牌号**：{$alertData['licensePlate']}\n\n";
    $content .= "**拉黑类型**：{$alertData['blacklistType']}\n\n";
    $content .= "**拉黑原因**：{$alertData['reason']}\n\n";
    $content .= "**抓拍时间**：{$time}\n\n";
    $content .= "**车道**：{$alertData['laneName']}\n\n";
    $content .= "**方向**：{$directionText}\n\n";

    return sendDingTalkMessage($title, $content);
}

if (isset($_SERVER['REQUEST_URI']) && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $action = $_GET['action'] ?? 'test';

    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {
        case 'test':
            $result = sendDingTalkMessage(
                '🎉 车辆监控系统测试',
                "### 测试消息\n\n车辆监控系统已配置成功！\n\n**测试时间**：" . date('Y-m-d H:i:s') . "\n\n请确认您收到了这条消息。"
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
}
