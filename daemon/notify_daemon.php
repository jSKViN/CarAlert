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

// 日志文件和PID文件
$useDb2 = false;
$logFile = __DIR__ . '/daemon.log';
$pidFile = __DIR__ . '/daemon.pid';

/**
 * 写入日志
 */
function writeLog($message) {
    global $logFile;
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
 */
function getDbConnection() {
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
 */
function getDb2Connection() {
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
 */
function checkNewData($conn) {
    $sql = "SELECT last_record_id FROM p_update_flag WHERE id = 1";
    $result = $conn->query($sql);
    if ($result && $row = $result->fetch_assoc()) {
        return intval($row['last_record_id']);
    }
    return 0;
}

/**
 * 获取关注的车牌列表
 */
function getWatchPlates($conn) {
    $plates = [];
    $sql = "SELECT plate_number FROM p_watch_plates WHERE is_active = 1";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plates[] = $row['plate_number'];
        }
    }
    return $plates;
}

/**
 * 获取拉黑的车牌列表（包含拉黑原因）
 */
function getBlacklistPlates($conn) {
    $plates = [];
    $sql = "SELECT plate_number, reason, blacklist_type FROM p_blacklist_plates 
            WHERE is_active = 1 AND (blacklist_type = 2 OR (blacklist_type = 1 AND end_time > NOW()))";
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $plates[$row['plate_number']] = [
                'reason' => $row['reason'],
                'type' => $row['blacklist_type'] == 2 ? '永久' : '临时'
            ];
        }
    }
    return $plates;
}

/**
 * 获取车牌的车主信息
 */
function getPlateOwnerInfo($conn, $plateNumber) {
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
 */
function getRecordsAfterId($conn, $lastId) {
    $records = [];
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
            WHERE d.id > {$lastId}
            ORDER BY d.id ASC";
    
    $result = $conn->query($sql);
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $records[] = $row;
        }
    }
    return $records;
}

/**
 * 解析记录数据
 */
function parseRecord($record) {
    $realTimeInfo = json_decode($record['real_time_info'], true);
    $accessInfo = json_decode($record['access_info'], true);
    
    return [
        'id' => $record['id'],
        'licensePlate' => $record['plate_number'],
        'timestamp' => $record['create_time'],
        'laneName' => $record['lane_name'] ?? '未知车道',
        'direction' => ($record['lane_direction'] ?? 0) == 0 ? '进入' : '离开',
        'carType' => $realTimeInfo['carType'] ?? '',
        'amount' => $accessInfo['fee'] ?? 0,
        'fullPictureUrl' => $realTimeInfo['fullPictureUrl'] ?? ''
    ];
}

/**
 * 更新最后处理ID
 */
function updateLastRecordId($conn, $recordId) {
    $sql = "UPDATE p_update_flag SET last_record_id = {$recordId} WHERE id = 1";
    return $conn->query($sql);
}

/**
 * 增加通知计数
 */
function incrementNotifyCount($conn) {
    $sql = "UPDATE p_update_flag SET notify_count = notify_count + 1 WHERE id = 1";
    return $conn->query($sql);
}

/**
 * 发送拉黑预警通知
 */
function sendBlacklistAlert($record, $blacklistInfo) {
    $notifySuccess = false;
    
    $alertData = [
        'licensePlate' => $record['licensePlate'],
        'timestamp' => $record['timestamp'],
        'laneName' => $record['laneName'],
        'direction' => $record['direction'],
        'reason' => $blacklistInfo['reason'],
        'blacklistType' => $blacklistInfo['type']
    ];
    
    // 发送企业微信 Webhook 通知
    if (NOTIFY_WECHAT_WORK_WEBHOOK) {
        $result = sendWeChatWorkWebhookBlacklistAlert($alertData);
        if ($result['success']) {
            writeLog("企业微信Webhook拉黑预警发送成功: {$record['licensePlate']}");
            $notifySuccess = true;
        } else {
            writeLog("企业微信Webhook拉黑预警发送失败: " . ($result['error'] ?? '未知错误'));
        }
    }
    
    // 发送钉钉通知
    if (NOTIFY_DINGTALK) {
        $result = sendDingTalkBlacklistAlert($alertData);
        if ($result['success']) {
            writeLog("钉钉拉黑预警发送成功: {$record['licensePlate']}");
            $notifySuccess = true;
        } else {
            writeLog("钉钉拉黑预警发送失败: " . ($result['error'] ?? '未知错误'));
        }
    }
    
    // 发送 Server酱 通知
    if (NOTIFY_SERVERCHAN) {
        $result = sendBlacklistNotification($alertData);
        if ($result['success']) {
            writeLog("Server酱拉黑预警发送成功: {$record['licensePlate']}");
            $notifySuccess = true;
        } else {
            writeLog("Server酱拉黑预警发送失败: " . ($result['error'] ?? '未知错误'));
        }
    }
    
    return $notifySuccess;
}

/**
 * 主循环
 */
function mainLoop($useDb2 = false) {
    global $pidFile;
    
    // 写入PID文件
    file_put_contents($pidFile, getmypid());
    
    $dbName = $useDb2 ? '第二数据库' : '主数据库';
    writeLog("守护进程启动 (PID: " . getmypid() . ") - 监听{$dbName}");
    
    $conn = $useDb2 ? getDb2Connection() : getDbConnection();
    if (!$conn) {
        writeLog("无法连接数据库，守护进程退出");
        exit(1);
    }
    
    $lastProcessedId = checkNewData($conn);
    writeLog("初始 last_record_id: {$lastProcessedId}");
    
    $watchPlates = getWatchPlates($conn);
    $blacklistPlates = getBlacklistPlates($conn);
    writeLog("关注车牌数: " . count($watchPlates) . ", 拉黑车牌数: " . count($blacklistPlates));
    
    while (true) {
        // 检查是否需要退出
        if (!file_exists($pidFile) || file_get_contents($pidFile) != getmypid()) {
            writeLog("收到退出信号，守护进程停止");
            break;
        }
        
        // 重新获取关注和拉黑车牌（可能已更新）
        $watchPlates = getWatchPlates($conn);
        $blacklistPlates = getBlacklistPlates($conn);
        
        if (empty($watchPlates) && empty($blacklistPlates)) {
            sleep(POLL_INTERVAL);
            continue;
        }
        
        // 检查新数据
        $currentLastId = checkNewData($conn);
        
        if ($currentLastId > $lastProcessedId) {
            writeLog("发现新数据，last_record_id: {$currentLastId}");
            
            // 获取新记录
            $newRecords = getRecordsAfterId($conn, $lastProcessedId);
            
            foreach ($newRecords as $record) {
                $parsedRecord = parseRecord($record);
                
                // 检查是否是拉黑车牌（优先处理）
                if (isset($blacklistPlates[$parsedRecord['licensePlate']])) {
                    writeLog("发现拉黑车牌: {$parsedRecord['licensePlate']}");
                    
                    $blacklistInfo = $blacklistPlates[$parsedRecord['licensePlate']];
                    $notifySuccess = sendBlacklistAlert($parsedRecord, $blacklistInfo);
                    
                    if ($notifySuccess) {
                        incrementNotifyCount($conn);
                    }
                }
                
                // 检查是否是关注车牌
                if (in_array($parsedRecord['licensePlate'], $watchPlates)) {
                    writeLog("发现关注车牌: {$parsedRecord['licensePlate']}");
                    
                    // 查询车主信息
                    $ownerInfo = getPlateOwnerInfo($conn, $parsedRecord['licensePlate']);
                    $parsedRecord['ownerName'] = $ownerInfo['name'];
                    $parsedRecord['ownerDepartment'] = $ownerInfo['department'];
                    
                    $notifySuccess = false;
                    
                    // 发送 Server酱 通知
                    if (NOTIFY_SERVERCHAN) {
                        $result = sendServerChanVehicleNotification($parsedRecord);
                        if ($result['success']) {
                            writeLog("Server酱通知发送成功: {$parsedRecord['licensePlate']}");
                            $notifySuccess = true;
                        } else {
                            writeLog("Server酱通知发送失败: " . ($result['error'] ?? '未知错误'));
                        }
                    }
                    
                    // 发送钉钉通知
                    if (NOTIFY_DINGTALK) {
                        $result = sendDingTalkVehicleNotification($parsedRecord);
                        if ($result['success']) {
                            writeLog("钉钉通知发送成功: {$parsedRecord['licensePlate']}");
                            $notifySuccess = true;
                        } else {
                            writeLog("钉钉通知发送失败: " . ($result['error'] ?? '未知错误'));
                        }
                    }
                    
                    // 发送企业微信 Webhook 通知
                    if (NOTIFY_WECHAT_WORK_WEBHOOK) {
                        $result = sendWeChatWorkWebhookVehicleNotification($parsedRecord);
                        if ($result['success']) {
                            writeLog("企业微信Webhook通知发送成功: {$parsedRecord['licensePlate']}");
                            $notifySuccess = true;
                        } else {
                            writeLog("企业微信Webhook通知发送失败: " . ($result['error'] ?? '未知错误'));
                        }
                    }
                    
                    if ($notifySuccess) {
                        incrementNotifyCount($conn);
                    }
                }
                
                // 更新最后处理ID
                $lastProcessedId = $record['id'];
                updateLastRecordId($conn, $lastProcessedId);
            }
        }
        
        sleep(POLL_INTERVAL);
    }
    
    $conn->close();
    writeLog("守护进程正常退出");
}

// 处理命令行参数
$options = getopt('2');
$useDb2 = isset($options['2']);

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