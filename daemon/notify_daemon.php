<?php
/**
 * 车辆监控通知守护进程
 * 轮询检测新数据，发现关注车牌或拉黑车牌时发送通知
 */

// 优先加载本地配置
if (file_exists(__DIR__ . '/config.local.php')) {
    require_once __DIR__ . '/config.local.php';
} else {
    require_once __DIR__ . '/config.php';
}
require_once __DIR__ . '/../api/serverchan_notify.php';
require_once __DIR__ . '/../api/dingtalk_notify.php';
require_once __DIR__ . '/../api/wechat_work_webhook_notify.php';

// 处理命令行参数
$options = getopt('2');
$useDb2 = isset($options['2']);

// 日志文件和PID文件 - 根据使用的数据库区分
$logFile = $useDb2 ? __DIR__ . '/daemon_db2.log' : __DIR__ . '/daemon.log';
$pidFile = $useDb2 ? __DIR__ . '/daemon_db2.pid' : __DIR__ . '/daemon.pid';

/**
 * 写入日志
 * @param mixed $message 日志消息（可以是字符串、数组、对象等）
 * @return void
 */
function writeLog($message): void {
    global $logFile;
    
    // 确保 message 是字符串类型
    if (is_array($message)) {
        $message = json_encode($message, JSON_UNESCAPED_UNICODE);
    } elseif (is_object($message)) {
        $message = print_r($message, true);
    } elseif ($message === null) {
        $message = 'null';
    } elseif (!is_string($message)) {
        $message = strval($message);
    }
    
    $time = date('Y-m-d H:i:s');
    $log = "[{$time}] {$message}\n";
    
    // 确保日志以 UTF-8 编码写入
    if (!file_exists($logFile)) {
        // 新文件写入 UTF-8 BOM 头
        $bom = chr(239) . chr(187) . chr(191);
        file_put_contents($logFile, $bom);
    }
    
    if (function_exists('mb_convert_encoding')) {
        $log = mb_convert_encoding($log, 'UTF-8', mb_detect_encoding($log));
    }
    
    file_put_contents($logFile, $log, FILE_APPEND | LOCK_EX);
    echo $log;
}

/**
 * 获取数据库连接
 * @return mysqli|null
 */
function getDbConnection(): ?mysqli {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        writeLog("数据库连接失败: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * 获取第二数据库连接
 * @return mysqli|null
 */
function getDb2Connection(): ?mysqli {
    if (!defined('DB2_HOST')) {
        return null;
    }
    $conn = new mysqli(DB2_HOST, DB2_USER, DB2_PASS, DB2_NAME, DB2_PORT);
    if ($conn->connect_error) {
        writeLog("第二数据库连接失败: " . $conn->connect_error);
        return null;
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * 检查是否有新数据
 * @param mysqli $conn 数据库连接
 * @return int
 */
function checkNewData(mysqli $conn): int {
    $sql = "SELECT last_record_id FROM p_update_flag WHERE id = 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return intval($row['last_record_id']);
    }
    return 0;
}

/**
 * 获取车库信息
 * @param mysqli $conn 数据库连接
 * @return array ['garage_id' => xxx, 'garage_name' => 'xxx']
 */
function getGarageInfo(mysqli $conn): array {
    $sql = "SELECT garage_id, garage_name FROM p_garage LIMIT 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return [
            'garage_id' => $row['garage_id'],
            'garage_name' => $row['garage_name']
        ];
    }
    return ['garage_id' => 0, 'garage_name' => '未知系统'];
}

/**
 * 获取关注的车牌列表
 * @param mysqli $conn 数据库连接
 * @param int|null $garageId 车库ID，NULL表示所有车库
 * @return array
 */
function getWatchPlates(mysqli $conn, ?int $garageId = null): array {
    $plates = [];
    if ($garageId !== null) {
        $sql = "SELECT plate_number FROM p_watch_plates WHERE is_active = 1 AND (garage_id IS NULL OR garage_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $garageId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT plate_number FROM p_watch_plates WHERE is_active = 1";
        $result = $conn->query($sql);
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plates[] = $row['plate_number'];
        }
    }
    if (isset($stmt)) {
        $stmt->close();
    }
    return $plates;
}

/**
 * 获取拉黑的车牌列表（包含拉黑原因）
 * @param mysqli $conn 数据库连接
 * @param int|null $garageId 车库ID，NULL表示所有车库
 * @return array
 */
function getBlacklistPlates(mysqli $conn, ?int $garageId = null): array {
    $plates = [];
    if ($garageId !== null) {
        $sql = "SELECT plate_number, reason, blacklist_type, garage_id FROM p_blacklist_plates
                WHERE is_active = 1 AND (blacklist_type = 2 OR (blacklist_type = 1 AND end_time > NOW()))
                AND (garage_id IS NULL OR garage_id = ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $garageId);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $sql = "SELECT plate_number, reason, blacklist_type, garage_id FROM p_blacklist_plates
                WHERE is_active = 1 AND (blacklist_type = 2 OR (blacklist_type = 1 AND end_time > NOW()))";
        $result = $conn->query($sql);
    }
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plates[$row['plate_number']] = [
                'reason' => $row['reason'],
                'type' => $row['blacklist_type'] == 2 ? '永久' : '临时',
                'garage_id' => $row['garage_id']
            ];
        }
    }
    if (isset($stmt)) {
        $stmt->close();
    }
    return $plates;
}

/**
 * 获取车牌的车主信息
 * @param mysqli $conn 数据库连接
 * @param string $plateNumber 车牌号
 * @return array
 */
function getPlateOwnerInfo(mysqli $conn, string $plateNumber): array {
    $info = ['name' => '', 'department' => ''];
    $sql = "SELECT coc.owner_name, co.dept_name 
            FROM charge_order_car coc 
            LEFT JOIN charge_order co ON coc.charge_order_id = co.charge_order_id 
            WHERE coc.plate_number = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $plateNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $row = $result->fetch_assoc()) {
        $info['name'] = $row['owner_name'] ?? '';
        $info['department'] = $row['dept_name'] ?? '';
    }
    $stmt->close();
    return $info;
}

/**
 * 获取指定ID之后的记录
 * @param mysqli $conn 数据库连接
 * @param int $lastId 最后处理的记录ID
 * @return array
 */
function getRecordsAfterId(mysqli $conn, int $lastId): array {
    $records = [];
    
    // 验证输入参数
    if (!($conn instanceof mysqli) || $lastId === null || !is_numeric($lastId)) {
        return $records;
    }
    
    $sql = "SELECT 
                d.id,
                d.plate_number,
                d.create_time,
                d.real_time_info,
                d.access_info,
                l.lane_name,
                l.direction as lane_direction
            FROM p_distinguish_log d
            LEFT JOIN p_lane l ON d.lane_id = l.lane_id
            WHERE d.id > ?
            ORDER BY d.id ASC";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        writeLog("SQL准备失败: " . $conn->error);
        return $records;
    }
    
    $lastId = intval($lastId);
    $stmt->bind_param('i', $lastId);
    
    if (!$stmt->execute()) {
        writeLog("SQL执行失败: " . $stmt->error);
        $stmt->close();
        return $records;
    }
    
    $result = $stmt->get_result();
    if ($result && $result instanceof mysqli_result) {
        while ($row = $result->fetch_assoc()) {
            if (is_array($row)) {
                $records[] = $row;
            }
        }
    }
    
    $stmt->close();
    return $records;
}

/**
 * 解析记录数据
 * @param array $record 数据库记录
 * @return array
 */
function parseRecord(array $record): array {
    $realTimeInfo = json_decode($record['real_time_info'] ?? '{}', true);
    if (!is_array($realTimeInfo)) {
        $realTimeInfo = [];
    }

    $accessInfo = json_decode($record['access_info'] ?? '{}', true);
    if (!is_array($accessInfo)) {
        $accessInfo = [];
    }

    return [
        'id' => $record['id'],
        'licensePlate' => $record['plate_number'] ?? '',
        'timestamp' => $record['create_time'] ?? '',
        'laneName' => $record['lane_name'] ?? '未知车道',
        'direction' => ($record['lane_direction'] ?? 0) == 0 ? '进入' : '离开',
        'carType' => $realTimeInfo['carType'] ?? '',
        'amount' => $accessInfo['fee'] ?? 0,
        'fullPictureUrl' => $realTimeInfo['fullPictureUrl'] ?? '',
        'garageId' => $accessInfo['garageId'] ?? 0,
        'parkingName' => $accessInfo['parkingName'] ?? ''
    ];
}

/**
 * 更新最后处理ID
 * @param mysqli $conn 数据库连接
 * @param int $recordId 记录ID
 * @return bool
 */
function updateLastRecordId(mysqli $conn, int $recordId): bool {
    // 验证输入参数
    if (!($conn instanceof mysqli) || $recordId === null || !is_numeric($recordId)) {
        return false;
    }
    
    $sql = "UPDATE p_update_flag SET last_record_id = ? WHERE id = 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        writeLog("SQL准备失败: " . $conn->error);
        return false;
    }
    
    $recordId = intval($recordId);
    $stmt->bind_param('i', $recordId);
    
    $result = $stmt->execute();
    $stmt->close();
    
    return $result;
}

/**
 * 增加通知计数
 * @param mysqli $conn 数据库连接
 * @return bool
 */
function incrementNotifyCount(mysqli $conn): bool {
    $sql = "UPDATE p_update_flag SET notify_count = notify_count + 1 WHERE id = 1";
    return $conn->query($sql);
}

/**
 * 发送拉黑预警通知
 * @param array $record 记录数据
 * @param array $blacklistInfo 拉黑信息
 * @param string $systemName 系统名称
 * @return array
 */
function sendBlacklistAlert(array $record, array $blacklistInfo, string $systemName = ''): array {
    $notifySuccess = false;
    $details = [];

    if (!is_array($record)) {
        return ['success' => false, 'error' => 'record 不是数组'];
    }
    if (!is_array($blacklistInfo)) {
        return ['success' => false, 'error' => 'blacklistInfo 不是数组'];
    }

    $licensePlate = $record['licensePlate'] ?? '';

    $alertData = [
        'licensePlate' => $licensePlate,
        'timestamp' => $record['timestamp'] ?? '',
        'laneName' => $record['laneName'] ?? '',
        'direction' => $record['direction'] ?? '',
        'reason' => $blacklistInfo['reason'] ?? '',
        'blacklistType' => $blacklistInfo['type'] ?? '',
        'systemName' => $systemName,
        'garageId' => $record['garageId'] ?? 0,
        'parkingName' => $record['parkingName'] ?? ''
    ];
    
    // 发送企业微信 Webhook 通知（测试环境或正式环境任一启用即可）
    if (NOTIFY_WECHAT_WORK_WEBHOOK || NOTIFY_WECHAT_WORK_WEBHOOK_PROD) {
        $result = sendWeChatWorkWebhookBlacklistAlert($alertData);
        if (is_array($result) && $result['success']) {
            writeLog("企业微信Webhook拉黑预警发送成功: {$licensePlate}");
            $notifySuccess = true;
            // 收集详细结果
            if (isset($result['details']) && is_array($result['details'])) {
                $details = array_merge($details, $result['details']);
            }
        } else {
            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
            writeLog("企业微信Webhook拉黑预警发送失败: " . $error);
        }
    }
    
    // 发送钉钉通知
    if (NOTIFY_DINGTALK) {
        $result = sendDingTalkBlacklistAlert($alertData);
        if (is_array($result) && $result['success']) {
            writeLog("钉钉拉黑预警发送成功: {$licensePlate}");
            $notifySuccess = true;
        } else {
            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
            writeLog("钉钉拉黑预警发送失败: " . $error);
        }
    }
    
    // 发送 Server酱 通知
    if (NOTIFY_SERVERCHAN) {
        $result = sendBlacklistNotification($alertData);
        if (is_array($result) && $result['success']) {
            writeLog("Server酱拉黑预警发送成功: {$licensePlate}");
            $notifySuccess = true;
        } else {
            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
            writeLog("Server酱拉黑预警发送失败: " . $error);
        }
    }
    
    return ['success' => $notifySuccess, 'details' => $details];
}

/**
 * 主循环
 * @param bool $useDb2 是否使用第二数据库
 * @return void
 */
function mainLoop(bool $useDb2 = false): void {
    global $pidFile;

    file_put_contents($pidFile, getmypid());

    $dbName = $useDb2 ? '第二数据库' : '主数据库';
    writeLog("守护进程启动 (PID: " . getmypid() . ") - 监听{$dbName}");

    $conn = $useDb2 ? getDb2Connection() : getDbConnection();
    if (!$conn) {
        writeLog("无法连接数据库，守护进程退出");
        exit(1);
    }

    $garageInfo = getGarageInfo($conn);
    $garageId = $garageInfo['garage_id'];
    $garageName = $garageInfo['garage_name'];
    writeLog("系统信息: garage_id={$garageId}, garage_name={$garageName}");

    $lastProcessedId = checkNewData($conn);
    writeLog("初始 last_record_id: {$lastProcessedId}");

    $watchPlates = getWatchPlates($conn, $garageId);
    $blacklistPlates = getBlacklistPlates($conn, $garageId);
    writeLog("关注车牌数: " . count($watchPlates) . ", 拉黑车牌数: " . count($blacklistPlates));

    $enabledEnvs = [];
    if (defined('NOTIFY_WECHAT_WORK_WEBHOOK') && NOTIFY_WECHAT_WORK_WEBHOOK) {
        $enabledEnvs[] = '测试环境';
    }
    if (defined('NOTIFY_WECHAT_WORK_WEBHOOK_PROD') && NOTIFY_WECHAT_WORK_WEBHOOK_PROD) {
        $enabledEnvs[] = '正式环境';
    }
    $envStr = empty($enabledEnvs) ? '无' : implode('、', $enabledEnvs);
    writeLog("通知目标：{$envStr}");

    while (true) {
        if (!file_exists($pidFile) || file_get_contents($pidFile) != getmypid()) {
            writeLog("收到退出信号，守护进程停止");
            break;
        }

        $watchPlates = getWatchPlates($conn, $garageId);
        $blacklistPlates = getBlacklistPlates($conn, $garageId);

        if (!is_array($watchPlates)) {
            $watchPlates = [];
        }
        if (!is_array($blacklistPlates)) {
            $blacklistPlates = [];
        }

        if (empty($watchPlates) && empty($blacklistPlates)) {
            sleep(POLL_INTERVAL);
            continue;
        }

        $currentLastId = checkNewData($conn);

        if ($currentLastId > $lastProcessedId) {
            writeLog("检测到新的车辆抓拍记录 (ID: {$currentLastId})");

            $newRecords = getRecordsAfterId($conn, $lastProcessedId);

            if (!is_array($newRecords)) {
                $newRecords = [];
            }

            foreach ($newRecords as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $parsedRecord = parseRecord($record);

                if (!is_array($parsedRecord)) {
                    continue;
                }

                $licensePlate = $parsedRecord['licensePlate'] ?? '';

                if (!empty($licensePlate) && isset($blacklistPlates[$licensePlate])) {
                    writeLog("拉黑车牌预警触发: {$licensePlate} [{$garageName}]");

                    $blacklistInfo = $blacklistPlates[$licensePlate];
                    $notifyResult = sendBlacklistAlert($parsedRecord, $blacklistInfo, $garageName);
                    $notifySuccess = false;

                    if (isset($notifyResult['success']) && $notifyResult['success']) {
                        $notifySuccess = true;
                        if (isset($notifyResult['details']) && is_array($notifyResult['details'])) {
                            foreach ($notifyResult['details'] as $env => $r) {
                                if (is_array($r) && $r['success']) {
                                    writeLog("企业微信[{$env}]拉黑预警已发送: {$licensePlate}");
                                } else {
                                    $error = is_array($r) ? ($r['error'] ?? '未知错误') : '未知错误';
                                    writeLog("企业微信[{$env}]拉黑预警发送失败: " . $error);
                                }
                            }
                        } else {
                            writeLog("企业微信拉黑预警已发送: {$licensePlate}");
                        }
                        incrementNotifyCount($conn);
                    } else {
                        $errorMsg = is_array($notifyResult) ? ($notifyResult['error'] ?? '未知错误') : '未知错误';
                        writeLog("企业微信拉黑预警发送失败: " . $errorMsg);
                    }
                }

                if (!empty($licensePlate) && is_array($watchPlates) && in_array($licensePlate, $watchPlates)) {
                    writeLog("关注车牌提醒触发: {$licensePlate} [{$garageName}]");

                    $ownerInfo = getPlateOwnerInfo($conn, $licensePlate);
                    $parsedRecord['ownerName'] = $ownerInfo['name'];
                    $parsedRecord['ownerDepartment'] = $ownerInfo['department'];
                    $parsedRecord['systemName'] = $garageName;

                    $notifySuccess = false;

                    if (NOTIFY_SERVERCHAN) {
                        $result = sendServerChanVehicleNotification($parsedRecord);
                        if (is_array($result) && $result['success']) {
                            writeLog("Server酱通知已发送: {$licensePlate}");
                            $notifySuccess = true;
                        } else {
                            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
                            writeLog("Server酱通知发送失败: " . $error);
                        }
                    }

                    if (NOTIFY_DINGTALK) {
                        $result = sendDingTalkVehicleNotification($parsedRecord);
                        if (is_array($result) && $result['success']) {
                            writeLog("钉钉通知已发送: {$licensePlate}");
                            $notifySuccess = true;
                        } else {
                            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
                            writeLog("钉钉通知发送失败: " . $error);
                        }
                    }

                    if (NOTIFY_WECHAT_WORK_WEBHOOK || NOTIFY_WECHAT_WORK_WEBHOOK_PROD) {
                        $result = sendWeChatWorkWebhookVehicleNotification($parsedRecord);
                        if (is_array($result) && isset($result['success']) && $result['success']) {
                            $notifySuccess = true;
                            if (isset($result['details']) && is_array($result['details'])) {
                                foreach ($result['details'] as $env => $r) {
                                    if (is_array($r) && $r['success']) {
                                        writeLog("企业微信[{$env}]通知已发送: {$licensePlate}");
                                    } else {
                                        $error = is_array($r) ? ($r['error'] ?? '未知错误') : '未知错误';
                                        writeLog("企业微信[{$env}]通知发送失败: " . $error);
                                    }
                                }
                            } else {
                                writeLog("企业微信通知已发送: {$licensePlate}");
                            }
                        } else {
                            $error = is_array($result) ? ($result['error'] ?? '未知错误') : '未知错误';
                            writeLog("企业微信通知发送失败: " . $error);
                        }
                    }

                    if ($notifySuccess) {
                        incrementNotifyCount($conn);
                    }
                }

                $lastProcessedId = $record['id'] ?? 0;
                updateLastRecordId($conn, $lastProcessedId);
            }
        }

        sleep(POLL_INTERVAL);
    }

    $conn->close();
    writeLog("守护进程正常退出");
}

// 检查是否已经在运行
if (file_exists($pidFile)) {
    $pid = trim(file_get_contents($pidFile));
    $isRunning = false;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows: 使用 tasklist 检查进程
        exec("tasklist /FI \"PID eq {$pid}\"", $output, $returnCode);
        $isRunning = ($returnCode === 0) && (count($output) > 1);
    } else {
        // Linux/Unix: 使用 posix_kill
        $isRunning = function_exists('posix_kill') && posix_kill($pid, 0);
    }
    
    if ($isRunning) {
        writeLog("守护进程已经在运行 (PID: {$pid})");
        exit(0);
    } else {
        unlink($pidFile);
    }
}

// 启动主循环
mainLoop($useDb2);
?>