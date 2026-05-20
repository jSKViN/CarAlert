<?php
/**
 * 车辆抓拍记录API
 * 获取最新的车辆抓拍记录
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../daemon/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

function jsonResponse($success, $data = null, $message = '') {
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getDbConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);
    if ($conn->connect_error) {
        return null;
    }
    $conn->set_charset('utf8mb4');
    return $conn;
}

function formatRecord($record) {
    $accessInfo = json_decode($record['access_info'] ?? '{}', true);
    $realTimeInfo = json_decode($record['real_time_info'] ?? '{}', true);

    $direction = 'IN';
    if (isset($record['lane_direction'])) {
        $direction = $record['lane_direction'] == 1 ? 'OUT' : 'IN';
    } elseif (isset($accessInfo['isOut'])) {
        $direction = $accessInfo['isOut'] ? 'OUT' : 'IN';
    }

    $laneName = $record['lane_name'] ?? '';
    if (empty($laneName)) {
        $laneName = $accessInfo['inLaneName'] ?? $accessInfo['outLaneName'] ?? '';
    }

    $fullPictureUrl = $realTimeInfo['fullPictureUrl'] ?? '';
    $platePicture = $realTimeInfo['platePicture'] ?? '';
    $platePictureUrl = '';

    if (!empty($platePicture) && !empty($fullPictureUrl)) {
        $baseUrl = dirname($fullPictureUrl);
        $platePictureUrl = $baseUrl . '/' . basename($platePicture);
    }

    $timestamp = strtotime($record['create_time']);
    $isoTime = date('c', $timestamp);

    return [
        'id' => (string)$record['id'],
        'licensePlate' => trim($record['plate_number'] ?? ''),
        'timestamp' => $isoTime,
        'direction' => $direction,
        'status' => 'SUCCESS',
        'parkingName' => $accessInfo['parkingName'] ?? '',
        'laneName' => $laneName,
        'carType' => $accessInfo['carType'] ?? '',
        'amount' => floatval($accessInfo['amount'] ?? 0),
        'platePictureUrl' => $platePictureUrl,
        'fullPictureUrl' => $fullPictureUrl,
        'carColor' => intval($record['car_color'] ?? 0)
    ];
}

function getLatestRecords($limit = 50, $lastId = null, $offset = 0, $plateNumber = null) {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }

    $limit = min(max(1, intval($limit)), 100);
    $offset = max(0, intval($offset));

    $whereClause = '';
    if ($plateNumber) {
        $plateNumber = $conn->real_escape_string($plateNumber);
        $whereClause = " WHERE d.plate_number LIKE '%$plateNumber%'";
    }

    $sql = "SELECT * FROM (
                SELECT
                    d.*,
                    l.lane_name,
                    l.direction as lane_direction
                FROM p_distinguish_log d
                LEFT JOIN p_lane l ON d.lane_id = l.lane_id
                $whereClause
                ORDER BY d.create_time DESC
                LIMIT $limit OFFSET $offset
            ) AS sub
            ORDER BY create_time DESC";

    $result = $conn->query($sql);
    $records = [];

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $records[] = formatRecord($row);
        }
    }

    $conn->close();

    return $records;
}

function getStatistics() {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }

    $today = date('Y-m-d') . ' 00:00:00';

    $sql = "SELECT COUNT(*) as count
            FROM p_distinguish_log d
            LEFT JOIN p_lane l ON d.lane_id = l.lane_id
            WHERE d.create_time >= '{$today}' AND l.direction = 0";
    $result = $conn->query($sql);
    $todayIn = $result ? $result->fetch_assoc()['count'] : 0;

    $sql = "SELECT COUNT(*) as count
            FROM p_distinguish_log d
            LEFT JOIN p_lane l ON d.lane_id = l.lane_id
            WHERE d.create_time >= '{$today}' AND l.direction = 1";
    $result = $conn->query($sql);
    $todayOut = $result ? $result->fetch_assoc()['count'] : 0;

    $sql = "SELECT COUNT(*) as count FROM p_distinguish_log WHERE create_time >= '{$today}'";
    $result = $conn->query($sql);
    $currentCount = $result ? $result->fetch_assoc()['count'] : 0;

    $sql = "SELECT COUNT(*) as count FROM p_distinguish_log";
    $result = $conn->query($sql);
    $totalCount = $result ? $result->fetch_assoc()['count'] : 0;

    $conn->close();

    return [
        'todayIn' => intval($todayIn),
        'todayOut' => intval($todayOut),
        'currentCount' => intval($currentCount),
        'totalCount' => intval($totalCount)
    ];
}

function getLatestByDirection($direction) {
    $conn = getDbConnection();
    if (!$conn) {
        return null;
    }

    $directionValue = $direction === 'out' ? 1 : 0;

    $sql = "SELECT
                d.*,
                l.lane_name,
                l.direction as lane_direction
            FROM p_distinguish_log d
            LEFT JOIN p_lane l ON d.lane_id = l.lane_id
            WHERE l.direction = $directionValue
            ORDER BY d.create_time DESC
            LIMIT 1";

    $result = $conn->query($sql);
    $record = null;

    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $record = formatRecord($row);
    }

    $conn->close();

    return $record;
}

$action = $_GET['action'] ?? 'list';

switch ($action) {
    case 'list':
        $limit = $_GET['limit'] ?? 50;
        $offset = $_GET['offset'] ?? 0;
        $plateNumber = $_GET['plate'] ?? null;
        $records = getLatestRecords($limit, null, $offset, $plateNumber);
        if ($records !== null) {
            jsonResponse(true, $records);
        } else {
            jsonResponse(false, null, '获取记录失败');
        }
        break;

    case 'stats':
        $stats = getStatistics();
        if ($stats !== null) {
            jsonResponse(true, $stats);
        } else {
            jsonResponse(false, null, '获取统计失败');
        }
        break;

    case 'latest_in':
        $record = getLatestByDirection('in');
        jsonResponse(true, $record);

    case 'latest_out':
        $record = getLatestByDirection('out');
        jsonResponse(true, $record);

    default:
        jsonResponse(false, null, '未知操作');
}
