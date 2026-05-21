<?php
/**
 * 拉黑车牌API接口
 * 提供拉黑车牌的增删改查功能
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../daemon/config.php';
require_once __DIR__ . '/DatabaseHelper.php';
require_once __DIR__ . '/blacklist_notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

function jsonResponse($success, $data = null, $message = '', $code = 200)
{
    http_response_code($code);
    echo json_encode([
        'success' => $success,
        'data' => $data,
        'message' => $message,
        'timestamp' => time() * 1000
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function getRequestBody()
{
    $rawInput = file_get_contents('php://input');
    return json_decode($rawInput, true) ?? [];
}

function getBlacklistPlates($conn, $filters = [])
{
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';

    if (!empty($filters['plate_number'])) {
        $whereClause .= " AND plate_number LIKE ?";
        $params[] = '%' . $filters['plate_number'] . '%';
        $types .= 's';
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $whereClause .= " AND is_active = ?";
        $params[] = intval($filters['is_active']);
        $types .= 'i';
    }

    if (isset($filters['blacklist_type']) && $filters['blacklist_type'] !== '') {
        $whereClause .= " AND blacklist_type = ?";
        $params[] = intval($filters['blacklist_type']);
        $types .= 'i';
    }

    $sql = "SELECT * FROM p_blacklist_plates {$whereClause} ORDER BY created_at DESC";

    if (!empty($filters['limit'])) {
        $sql .= " LIMIT " . intval($filters['limit']);
        if (!empty($filters['offset'])) {
            $sql .= " OFFSET " . intval($filters['offset']);
        }
    }

    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();

    $records = [];
    while ($row = $result->fetch_assoc()) {
        $records[] = $row;
    }
    $stmt->close();

    return $records;
}

function getBlacklistCount($conn, $filters = [])
{
    $whereClause = "WHERE 1=1";
    $params = [];
    $types = '';

    if (!empty($filters['plate_number'])) {
        $whereClause .= " AND plate_number LIKE ?";
        $params[] = '%' . $filters['plate_number'] . '%';
        $types .= 's';
    }

    if (isset($filters['is_active']) && $filters['is_active'] !== '') {
        $whereClause .= " AND is_active = ?";
        $params[] = intval($filters['is_active']);
        $types .= 'i';
    }

    if (isset($filters['blacklist_type']) && $filters['blacklist_type'] !== '') {
        $whereClause .= " AND blacklist_type = ?";
        $params[] = intval($filters['blacklist_type']);
        $types .= 'i';
    }

    $sql = "SELECT COUNT(*) as count FROM p_blacklist_plates {$whereClause}";
    $stmt = $conn->prepare($sql);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return intval($row['count']);
}

function addBlacklistPlate($conn, $data)
{
    $plateNumber = trim($data['plate_number'] ?? '');
    $reason = trim($data['reason'] ?? '');
    $operator = trim($data['operator'] ?? 'system');
    $blacklistType = intval($data['blacklist_type'] ?? 1);

    if (empty($plateNumber)) {
        return ['success' => false, 'message' => '车牌号不能为空'];
    }

    if (strlen($plateNumber) > 20) {
        return ['success' => false, 'message' => '车牌号过长'];
    }

    $checkSql = "SELECT id, is_active FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 1";
    $stmt = $conn->prepare($checkSql);
    $stmt->bind_param('s', $plateNumber);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $stmt->close();
        return ['success' => false, 'message' => '该车牌已在拉黑列表中'];
    }
    $stmt->close();

    $endTime = null;
    if ($blacklistType == 1) {
        $days = intval($data['days'] ?? 30);
        $endTime = date('Y-m-d H:i:s', strtotime("+{$days} days"));
    }

    $sql = "INSERT INTO p_blacklist_plates (plate_number, reason, operator, blacklist_type, end_time)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sssis', $plateNumber, $reason, $operator, $blacklistType, $endTime);

    if ($stmt->execute()) {
        $insertId = $conn->insert_id;
        $stmt->close();

        logBlacklistAction($conn, $plateNumber, 'ADD', $reason, $operator);

        $notifyResult = notifyBlacklistChange($conn, $plateNumber, 'ADD', $reason, $operator);

        return [
            'success' => true,
            'message' => '车牌已加入拉黑列表',
            'data' => ['id' => $insertId],
            'notify_result' => $notifyResult
        ];
    }

    $stmt->close();
    return ['success' => false, 'message' => '添加失败: ' . $conn->error];
}

function removeBlacklistPlate($conn, $plateNumber, $operator = 'system')
{
    $plateNumber = trim($plateNumber);
    if (empty($plateNumber)) {
        return ['success' => false, 'message' => '车牌号不能为空'];
    }

    // 先删除已存在的 is_active=0 的记录（避免唯一键冲突）
    $deleteSql = "DELETE FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 0";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param('s', $plateNumber);
    $stmt->execute();
    $stmt->close();

    $sql = "UPDATE p_blacklist_plates SET is_active = 0, updated_at = NOW() WHERE plate_number = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $plateNumber);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows > 0) {
            logBlacklistAction($conn, $plateNumber, 'REMOVE', '手动解除拉黑', $operator);
            notifyBlacklistChange($conn, $plateNumber, 'REMOVE', '手动解除拉黑', $operator);
            return ['success' => true, 'message' => '拉黑已解除'];
        }
        return ['success' => false, 'message' => '该车牌不在拉黑列表中'];
    }

    $stmt->close();
    return ['success' => false, 'message' => '解除失败: ' . $conn->error];
}

function updateBlacklistPlate($conn, $plateNumber, $data)
{
    $plateNumber = trim($plateNumber);
    if (empty($plateNumber)) {
        return ['success' => false, 'message' => '车牌号不能为空'];
    }

    $updateFields = [];
    $params = [];
    $types = '';

    if (isset($data['reason'])) {
        $updateFields[] = "reason = ?";
        $params[] = trim($data['reason']);
        $types .= 's';
    }

    if (isset($data['operator'])) {
        $updateFields[] = "operator = ?";
        $params[] = trim($data['operator']);
        $types .= 's';
    }

    if (isset($data['blacklist_type'])) {
        $updateFields[] = "blacklist_type = ?";
        $params[] = intval($data['blacklist_type']);
        $types .= 'i';

        if (intval($data['blacklist_type']) == 1 && isset($data['days'])) {
            $days = intval($data['days']);
            $endTime = date('Y-m-d H:i:s', strtotime("+{$days} days"));
            $updateFields[] = "end_time = ?";
            $params[] = $endTime;
            $types .= 's';
        } elseif (intval($data['blacklist_type']) == 2) {
            $updateFields[] = "end_time = NULL";
        }
    }

    if (empty($updateFields)) {
        return ['success' => false, 'message' => '没有需要更新的字段'];
    }

    $params[] = $plateNumber;
    $types .= 's';

    $sql = "UPDATE p_blacklist_plates SET " . implode(', ', $updateFields) . " WHERE plate_number = ? AND is_active = 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows > 0) {
            logBlacklistAction($conn, $plateNumber, 'UPDATE', $data['reason'] ?? '', $data['operator'] ?? 'system');
            return ['success' => true, 'message' => '更新成功'];
        }
        return ['success' => false, 'message' => '该车牌不在拉黑列表中'];
    }

    $stmt->close();
    return ['success' => false, 'message' => '更新失败: ' . $conn->error];
}

function logBlacklistAction($conn, $plateNumber, $actionType, $reason, $operator)
{
    $sql = "INSERT INTO p_blacklist_log (plate_number, action_type, reason, operator) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssss', $plateNumber, $actionType, $reason, $operator);
    $stmt->execute();
    $stmt->close();
}

function getBlacklistLogs($conn, $plateNumber = null, $limit = 50)
{
    $whereClause = '';
    if (!empty($plateNumber)) {
        $whereClause = "WHERE plate_number = ?";
        $limit = "LIMIT " . intval($limit);
        $stmt = $conn->prepare("SELECT * FROM p_blacklist_log {$whereClause} ORDER BY created_at DESC {$limit}");
        $stmt->bind_param('s', $plateNumber);
    } else {
        $limit = "LIMIT " . intval($limit);
        $stmt = $conn->prepare("SELECT * FROM p_blacklist_log ORDER BY created_at DESC {$limit}");
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $stmt->close();

    return $logs;
}

function checkPlateInBlacklist($conn, $plateNumber)
{
    $plateNumber = trim($plateNumber);
    if (empty($plateNumber)) {
        return false;
    }

    $sql = "SELECT id FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 1
            AND (blacklist_type = 2 OR (blacklist_type = 1 AND (end_time IS NULL OR end_time > NOW())))";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('s', $plateNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result && $result->num_rows > 0;
    $stmt->close();

    return $exists;
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

$conn = DatabaseHelper::getConnection(1);
if (!$conn) {
    jsonResponse(false, null, '数据库连接失败', 500);
}

try {
    switch ($method) {
        case 'GET':
            switch ($action) {
                case 'list':
                    $filters = [
                        'plate_number' => $_GET['plate_number'] ?? '',
                        'is_active' => $_GET['is_active'] ?? '',
                        'blacklist_type' => $_GET['blacklist_type'] ?? '',
                        'limit' => $_GET['limit'] ?? 100,
                        'offset' => $_GET['offset'] ?? 0
                    ];
                    $data = getBlacklistPlates($conn, $filters);
                    $total = getBlacklistCount($conn, $filters);
                    jsonResponse(true, ['items' => $data, 'total' => $total]);

                case 'get':
                    $plateNumber = $_GET['plate_number'] ?? '';
                    $sql = "SELECT * FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 1";
                    $stmt = $conn->prepare($sql);
                    $stmt->bind_param('s', $plateNumber);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $data = $result->fetch_assoc();
                    $stmt->close();
                    if ($data) {
                        jsonResponse(true, $data);
                    } else {
                        jsonResponse(false, null, '未找到该车牌', 404);
                    }

                case 'logs':
                    $plateNumber = $_GET['plate_number'] ?? null;
                    $limit = $_GET['limit'] ?? 50;
                    $data = getBlacklistLogs($conn, $plateNumber, $limit);
                    jsonResponse(true, $data);

                case 'check':
                    $plateNumber = $_GET['plate_number'] ?? '';
                    $exists = checkPlateInBlacklist($conn, $plateNumber);
                    jsonResponse(true, ['is_blacklisted' => $exists]);

                case 'stats':
                    $stats = [];
                    $result = $conn->query("SELECT COUNT(*) as total FROM p_blacklist_plates WHERE is_active = 1");
                    $stats['total'] = $result->fetch_assoc()['total'] ?? 0;

                    $result = $conn->query("SELECT COUNT(*) as temporary FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 1");
                    $stats['temporary'] = $result->fetch_assoc()['temporary'] ?? 0;

                    $result = $conn->query("SELECT COUNT(*) as permanent FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 2");
                    $stats['permanent'] = $result->fetch_assoc()['permanent'] ?? 0;

                    $result = $conn->query("SELECT COUNT(*) as expired FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 1 AND end_time <= NOW()");
                    $stats['expired'] = $result->fetch_assoc()['expired'] ?? 0;

                    jsonResponse(true, $stats);

                default:
                    jsonResponse(false, null, '未知操作', 400);
            }
            break;

        case 'POST':
            $data = getRequestBody();
            switch ($action) {
                case 'add':
                    $result = addBlacklistPlate($conn, $data);
                    if ($result['success']) {
                        jsonResponse(true, $result['data'] ?? null, $result['message']);
                    } else {
                        jsonResponse(false, null, $result['message'], 400);
                    }

                case 'remove':
                    $plateNumber = $data['plate_number'] ?? '';
                    $operator = $data['operator'] ?? 'system';
                    $result = removeBlacklistPlate($conn, $plateNumber, $operator);
                    if ($result['success']) {
                        jsonResponse(true, null, $result['message']);
                    } else {
                        jsonResponse(false, null, $result['message'], 400);
                    }

                case 'check':
                    $plateNumber = $data['plate_number'] ?? '';
                    $exists = checkPlateInBlacklist($conn, $plateNumber);
                    jsonResponse(true, ['is_blacklisted' => $exists]);

                default:
                    jsonResponse(false, null, '未知操作', 400);
            }
            break;

        case 'PUT':
            $data = getRequestBody();
            $plateNumber = $data['plate_number'] ?? '';
            $result = updateBlacklistPlate($conn, $plateNumber, $data);
            if ($result['success']) {
                jsonResponse(true, null, $result['message']);
            } else {
                jsonResponse(false, null, $result['message'], 400);
            }
            break;

        case 'DELETE':
            $plateNumber = $_GET['plate_number'] ?? '';
            $operator = $_GET['operator'] ?? 'system';
            $result = removeBlacklistPlate($conn, $plateNumber, $operator);
            if ($result['success']) {
                jsonResponse(true, null, $result['message']);
            } else {
                jsonResponse(false, null, $result['message'], 400);
            }
            break;

        default:
            jsonResponse(false, null, '不支持的请求方法', 405);
    }
} catch (Exception $e) {
    jsonResponse(false, null, '服务器错误: ' . $e->getMessage(), 500);
} finally {
    DatabaseHelper::closeConnection(1);
}
