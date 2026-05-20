<?php
/**
 * Server酱消息推送接口
 */

date_default_timezone_set('Asia/Shanghai');

if (file_exists(__DIR__ . '/../daemon/config.php')) {
    require_once __DIR__ . '/../daemon/config.php';
}

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

function sendServerChanVehicleNotification($record)
{
    $directionText = $record['direction'] === 'IN' || $record['direction'] === '进入' ? '入口抓拍' : '出口抓拍';
    $time = date('Y-m-d H:i:s', strtotime($record['timestamp']));

    $title = "🚗 车辆{$directionText} - {$record['licensePlate']}";

    $desp = "## 车辆监控通知\n\n";
    $desp .= "**车牌号**：{$record['licensePlate']}\n\n";
    $desp .= "**抓拍类型**：{$directionText}\n\n";
    $desp .= "**时间**：{$time}\n\n";
    $desp .= "**车道**：{$record['laneName']}\n\n";

    if (!empty($record['ownerName'])) {
        $desp .= "**姓名**：{$record['ownerName']}\n\n";
    }

    if (!empty($record['ownerDepartment'])) {
        $desp .= "**单位部门**：{$record['ownerDepartment']}\n\n";
    }

    if (!empty($record['amount']) && $record['amount'] > 0) {
        $desp .= "**金额**：{$record['amount']}元\n\n";
    }

    if (!empty($record['fullPictureUrl'])) {
        $desp .= "![抓拍图片]({$record['fullPictureUrl']})\n\n";
    }

    return sendServerChanMessage($title, $desp);
}

function sendBlacklistNotification($alertData)
{
    $directionText = $alertData['direction'] === 'IN' || $alertData['direction'] === '进入' ? '进入' : '离开';
    $time = date('Y-m-d H:i:s', strtotime($alertData['timestamp']));

    $title = "⛔ 拉黑车牌预警 - {$alertData['licensePlate']}";

    $desp = "## ⚠️ 警告：发现拉黑车辆{$directionText}\n\n";
    $desp .= "**车牌号**：{$alertData['licensePlate']}\n\n";
    $desp .= "**拉黑类型**：{$alertData['blacklistType']}\n\n";
    $desp .= "**拉黑原因**：{$alertData['reason']}\n\n";
    $desp .= "**抓拍时间**：{$time}\n\n";
    $desp .= "**车道**：{$alertData['laneName']}\n\n";
    $desp .= "**方向**：{$directionText}\n\n";

    return sendServerChanMessage($title, $desp);
}

if (isset($_SERVER['REQUEST_URI']) && basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    $action = $_GET['action'] ?? 'test';

    header('Content-Type: application/json; charset=utf-8');

    switch ($action) {
        case 'test':
            $result = sendServerChanMessage(
                '🎉 车辆监控系统测试',
                "## 测试消息\n\n车辆监控系统已配置成功！\n\n**测试时间**：" . date('Y-m-d H:i:s') . "\n\n请确认您收到了这条消息。"
            );
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => '未知操作']);
    }
}
