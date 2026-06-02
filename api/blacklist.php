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

    if (isset($filters['garage_id']) && $filters['garage_id'] !== '' && $filters['garage_id'] !== null) {
        $whereClause .= " AND (garage_id IS NULL OR garage_id = ?)";
        $params[] = intval($filters['garage_id']);
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
    $garageId = isset($data['garage_id']) && $data['garage_id'] !== '' ? (string)$data['garage_id'] : null;

    if (empty($plateNumber)) {
        return ['success' => false, 'message' => '车牌号不能为空'];
    }

    if (strlen($plateNumber) > 20) {
        return ['success' => false, 'message' => '车牌号过长'];
    }

    $checkSql = "SELECT id, is_active FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 1";
    if (!is_null($garageId)) {
        $checkSql .= " AND (garage_id IS NULL OR garage_id = ?)";
    }
    $stmt = $conn->prepare($checkSql);
    if (!is_null($garageId)) {
        $stmt->bind_param('ss', $plateNumber, $garageId);
    } else {
        $stmt->bind_param('s', $plateNumber);
    }
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

    $sql = "INSERT INTO p_blacklist_plates (plate_number, garage_id, reason, operator, blacklist_type, end_time)
            VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ssssss', $plateNumber, $garageId, $reason, $operator, $blacklistType, $endTime);

    if ($stmt->execute()) {
        $insertId = $conn->insert_id;
        $stmt->close();

        logBlacklistAction($conn, $plateNumber, $garageId, 'ADD', $reason, $operator);

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

function removeBlacklistPlate($conn, $plateNumber, $garageId = null, $operator = 'system')
{
    $plateNumber = trim($plateNumber);
    if (empty($plateNumber)) {
        return ['success' => false, 'message' => '车牌号不能为空'];
    }

    if ($garageId !== null) {
        $garageId = (string)$garageId;
    }

    $deleteSql = "DELETE FROM p_blacklist_plates WHERE plate_number = ? AND is_active = 0 AND (garage_id IS NULL OR garage_id = ?)";
    $stmt = $conn->prepare($deleteSql);
    $stmt->bind_param('ss', $plateNumber, $garageId);
    $stmt->execute();
    $stmt->close();

    $sql = "UPDATE p_blacklist_plates SET is_active = 0, updated_at = NOW() WHERE plate_number = ? AND is_active = 1 AND (garage_id IS NULL OR garage_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $plateNumber, $garageId);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows > 0) {
            logBlacklistAction($conn, $plateNumber, $garageId, 'REMOVE', '手动解除拉黑', $operator);
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

    $garageId = isset($data['garage_id']) && $data['garage_id'] !== '' ? (string)$data['garage_id'] : null;

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
        $params[] = (string)intval($data['blacklist_type']);
        $types .= 's';

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

    if (isset($data['garage_id'])) {
        $updateFields[] = "garage_id = ?";
        $params[] = $garageId;
        $types .= 's';
    }

    if (empty($updateFields)) {
        return ['success' => false, 'message' => '没有需要更新的字段'];
    }

    $params[] = $plateNumber;
    $types .= 's';
    $params[] = $garageId;
    $types .= 's';

    $sql = "UPDATE p_blacklist_plates SET " . implode(', ', $updateFields) . " WHERE plate_number = ? AND is_active = 1 AND (garage_id IS NULL OR garage_id = ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);

    if ($stmt->execute()) {
        $affectedRows = $stmt->affected_rows;
        $stmt->close();

        if ($affectedRows > 0) {
            logBlacklistAction($conn, $plateNumber, $garageId, 'UPDATE', $data['reason'] ?? '', $data['operator'] ?? 'system');
            notifyBlacklistChange($conn, $plateNumber, 'UPDATE', $data['reason'] ?? '', $data['operator'] ?? 'system');
            return ['success' => true, 'message' => '更新成功'];
        }
        return ['success' => false, 'message' => '该车牌不在拉黑列表中'];
    }

    $stmt->close();
    return ['success' => false, 'message' => '更新失败: ' . $conn->error];
}

function logBlacklistAction($conn, $plateNumber, $garageId, $actionType, $reason, $operator)
{
    $sql = "INSERT INTO p_blacklist_log (plate_number, garage_id, action_type, reason, operator) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('sisss', $plateNumber, $garageId, $actionType, $reason, $operator);
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

// 车库ID与数据库的映射
define('GARAGE_ID_MAP', [
    '1738360658409357314' => 1,  // 广场停车场 -> 主数据库
    '1730496648745910274' => 2   // 星光大厦地下停车库 -> 第二数据库
]);

function getConnectionByGarageId($garageId)
{
    $garageId = (string)$garageId;
    $dbNumber = isset(GARAGE_ID_MAP[$garageId]) ? GARAGE_ID_MAP[$garageId] : 1;
    return DatabaseHelper::getConnection($dbNumber);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

// 获取 garage_id 参数
$garageId = isset($_GET['garage_id']) ? $_GET['garage_id'] : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body = getRequestBody();
    if (isset($body['garage_id'])) {
        $garageId = $body['garage_id'];
    }
}

// 根据 garage_id 选择数据库连接
$conn = getConnectionByGarageId($garageId);
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
                        'garage_id' => $_GET['garage_id'] ?? null,
                        'limit' => $_GET['limit'] ?? 100,
                        'offset' => $_GET['offset'] ?? 0
                    ];
                    
                    if (empty($filters['garage_id'])) {
                        $allItems = [];
                        $allTotal = 0;
                        $conn1 = DatabaseHelper::getConnection(1);
                        $conn2 = DatabaseHelper::getConnection(2);
                        
                        if ($conn1) {
                            $data1 = getBlacklistPlates($conn1, $filters);
                            $total1 = getBlacklistCount($conn1, $filters);
                            foreach ($data1 as &$item) {
                                $item['data_source'] = '广场停车场';
                            }
                            $allItems = array_merge($allItems, $data1);
                            $allTotal += $total1;
                        }
                        if ($conn2) {
                            $data2 = getBlacklistPlates($conn2, $filters);
                            $total2 = getBlacklistCount($conn2, $filters);
                            foreach ($data2 as &$item) {
                                $item['data_source'] = '星光大厦地下停车库';
                            }
                            $allItems = array_merge($allItems, $data2);
                            $allTotal += $total2;
                        }
                        
                        usort($allItems, function($a, $b) {
                            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
                        });
                        
                        $limit = intval($filters['limit']);
                        $offset = intval($filters['offset']);
                        $paginatedItems = array_slice($allItems, $offset, $limit);
                        
                        jsonResponse(true, ['items' => $paginatedItems, 'total' => $allTotal]);
                    } else {
                        $data = getBlacklistPlates($conn, $filters);
                        $total = getBlacklistCount($conn, $filters);
                        $sourceName = '';
                        if ($filters['garage_id'] == '1738360658409357314') {
                            $sourceName = '广场停车场';
                        } elseif ($filters['garage_id'] == '1730496648745910274') {
                            $sourceName = '星光大厦地下停车库';
                        }
                        foreach ($data as &$item) {
                            $item['data_source'] = $sourceName;
                        }
                        jsonResponse(true, ['items' => $data, 'total' => $total]);
                    }

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
                    $garageId = $_GET['garage_id'] ?? null;
                    
                    $allLogs = [];
                    $conn1 = DatabaseHelper::getConnection(1);
                    $conn2 = DatabaseHelper::getConnection(2);
                    
                    if (empty($garageId)) {
                        if ($conn1) {
                            $logs1 = getBlacklistLogs($conn1, $plateNumber, $limit);
                            foreach ($logs1 as &$log) {
                                $log['data_source'] = '广场停车场';
                            }
                            $allLogs = array_merge($allLogs, $logs1);
                        }
                        if ($conn2) {
                            $logs2 = getBlacklistLogs($conn2, $plateNumber, $limit);
                            foreach ($logs2 as &$log) {
                                $log['data_source'] = '星光大厦地下停车库';
                            }
                            $allLogs = array_merge($allLogs, $logs2);
                        }
                        
                        usort($allLogs, function($a, $b) {
                            return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
                        });
                        
                        $allLogs = array_slice($allLogs, 0, intval($limit));
                        jsonResponse(true, $allLogs);
                    } else {
                        $data = getBlacklistLogs($conn, $plateNumber, $limit);
                        $sourceName = '';
                        if ($garageId == '1738360658409357314') {
                            $sourceName = '广场停车场';
                        } elseif ($garageId == '1730496648745910274') {
                            $sourceName = '星光大厦地下停车库';
                        }
                        foreach ($data as &$log) {
                            $log['data_source'] = $sourceName;
                        }
                        jsonResponse(true, $data);
                    }

                case 'garages':
                    // 获取所有车库列表（从两个数据库查询）
                    $garages = [];
                    $conn1 = DatabaseHelper::getConnection(1);
                    if ($conn1) {
                        $result = $conn1->query("SELECT garage_id, garage_name FROM p_garage LIMIT 1");
                        if ($result && $row = $result->fetch_assoc()) {
                            $garages[] = $row;
                        }
                    }
                    $conn2 = DatabaseHelper::getConnection(2);
                    if ($conn2) {
                        $result = $conn2->query("SELECT garage_id, garage_name FROM p_garage LIMIT 1");
                        if ($result && $row = $result->fetch_assoc()) {
                            $garages[] = $row;
                        }
                    }
                    jsonResponse(true, $garages);

                case 'check':
                    $plateNumber = $_GET['plate_number'] ?? '';
                    $exists = checkPlateInBlacklist($conn, $plateNumber);
                    jsonResponse(true, ['is_blacklisted' => $exists]);

                case 'stats':
                    $stats = ['total' => 0, 'temporary' => 0, 'permanent' => 0, 'expired' => 0];
                    $conn1 = DatabaseHelper::getConnection(1);
                    $conn2 = DatabaseHelper::getConnection(2);
                    
                    $connections = [];
                    if ($conn1) $connections[] = $conn1;
                    if ($conn2) $connections[] = $conn2;
                    
                    foreach ($connections as $dbConn) {
                        $result = $dbConn->query("SELECT COUNT(*) as total FROM p_blacklist_plates WHERE is_active = 1");
                        $stats['total'] += $result->fetch_assoc()['total'] ?? 0;

                        $result = $dbConn->query("SELECT COUNT(*) as temporary FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 1");
                        $stats['temporary'] += $result->fetch_assoc()['temporary'] ?? 0;

                        $result = $dbConn->query("SELECT COUNT(*) as permanent FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 2");
                        $stats['permanent'] += $result->fetch_assoc()['permanent'] ?? 0;

                        $result = $dbConn->query("SELECT COUNT(*) as expired FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 1 AND end_time <= NOW()");
                        $stats['expired'] += $result->fetch_assoc()['expired'] ?? 0;
                    }
                    
                    jsonResponse(true, $stats);

                default:
                    jsonResponse(false, null, '未知操作', 400);
            }
            break;

        case 'POST':
            $data = getRequestBody();
            switch ($action) {
                case 'add':
                    $dataGarageId = isset($data['garage_id']) && $data['garage_id'] !== '' ? $data['garage_id'] : null;
                    
                    // 如果未指定车库或指定"全部"，则同时添加到两个数据库
                    if (empty($dataGarageId)) {
                        $results = [];
                        $allSuccess = true;
                        $messages = [];
                        
                        $conn1 = DatabaseHelper::getConnection(1);
                        $conn2 = DatabaseHelper::getConnection(2);
                        
                        if ($conn1) {
                            $data1 = $data;
                            $data1['garage_id'] = '1738360658409357314';
                            $r1 = addBlacklistPlate($conn1, $data1);
                            $results['plaza'] = $r1;
                            if (!$r1['success']) $allSuccess = false;
                            $messages[] = '广场停车场: ' . $r1['message'];
                        }
                        
                        if ($conn2) {
                            $data2 = $data;
                            $data2['garage_id'] = '1730496648745910274';
                            $r2 = addBlacklistPlate($conn2, $data2);
                            $results['garage'] = $r2;
                            if (!$r2['success']) $allSuccess = false;
                            $messages[] = '地下车库: ' . $r2['message'];
                        }
                        
                        if ($allSuccess) {
                            jsonResponse(true, $results, '车牌已同时添加到两个系统');
                        } else {
                            jsonResponse(false, $results, implode('; ', $messages), 400);
                        }
                    } else {
                        $result = addBlacklistPlate($conn, $data);
                        if ($result['success']) {
                            jsonResponse(true, $result['data'] ?? null, $result['message']);
                        } else {
                            jsonResponse(false, null, $result['message'], 400);
                        }
                    }

                case 'remove':
                    $plateNumber = $data['plate_number'] ?? '';
                    $garageId = isset($data['garage_id']) && $data['garage_id'] !== '' ? $data['garage_id'] : null;
                    $operator = $data['operator'] ?? 'system';
                    
                    if (empty($garageId)) {
                        $results = [];
                        $allSuccess = true;
                        $messages = [];
                        
                        $conn1 = DatabaseHelper::getConnection(1);
                        $conn2 = DatabaseHelper::getConnection(2);
                        
                        if ($conn1) {
                            $r1 = removeBlacklistPlate($conn1, $plateNumber, '1738360658409357314', $operator);
                            $results['plaza'] = $r1;
                            if (!$r1['success']) $allSuccess = false;
                            $messages[] = '广场停车场: ' . $r1['message'];
                        }
                        
                        if ($conn2) {
                            $r2 = removeBlacklistPlate($conn2, $plateNumber, '1730496648745910274', $operator);
                            $results['garage'] = $r2;
                            if (!$r2['success']) $allSuccess = false;
                            $messages[] = '地下车库: ' . $r2['message'];
                        }
                        
                        if ($allSuccess) {
                            jsonResponse(true, $results, '已从两个系统解除拉黑');
                        } else {
                            jsonResponse(false, $results, implode('; ', $messages), 400);
                        }
                    } else {
                        $result = removeBlacklistPlate($conn, $plateNumber, $garageId, $operator);
                        if ($result['success']) {
                            jsonResponse(true, null, $result['message']);
                        } else {
                            jsonResponse(false, null, $result['message'], 400);
                        }
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
