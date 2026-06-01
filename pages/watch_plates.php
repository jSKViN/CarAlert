<?php
/**
 * 关注车牌管理页面
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../daemon/config.php';
require_once __DIR__ . '/../api/DatabaseHelper.php';

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

$message = '';
$error = '';

// 获取车库列表
$garageList = [];
$conn1 = DatabaseHelper::getConnection(1);
if ($conn1) {
    $result = $conn1->query("SELECT garage_id, garage_name FROM p_garage");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $garageList[] = $row;
        }
    }
    DatabaseHelper::closeConnection(1);
}
$conn2Helper = DatabaseHelper::getConnection(2);
if ($conn2Helper) {
    $result = $conn2Helper->query("SELECT garage_id, garage_name FROM p_garage");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $garageList[] = $row;
        }
    }
    DatabaseHelper::closeConnection(2);
}

// 获取当前选中的车库
$selectedGarageId = $_POST['garage_id'] ?? $_GET['garage_id'] ?? '';
$conn = getConnectionByGarageId($selectedGarageId);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);
        $remark = trim($_POST['remark'] ?? '');
        $garageId = $_POST['garage_id'] ?? '';
        
        $messages = [];
        $errors = [];
        
        if (empty($garageId)) {
            $conn1 = DatabaseHelper::getConnection(1);
            $conn2 = DatabaseHelper::getConnection(2);
            
            $success = true;
            
            if ($conn1) {
                $plate_esc = $conn1->real_escape_string($plate);
                $remark_esc = $conn1->real_escape_string($remark);
                $sql = "INSERT INTO p_watch_plates (plate_number, remark, garage_id) VALUES ('{$plate_esc}', '{$remark_esc}', '1738360658409357314')
                        ON DUPLICATE KEY UPDATE remark = '{$remark_esc}', garage_id = '1738360658409357314', is_active = 1";
                if ($conn1->query($sql)) {
                    $messages[] = "广场停车场: 成功";
                } else {
                    $errors[] = "广场停车场: " . $conn1->error;
                    $success = false;
                }
            }
            
            if ($conn2) {
                $plate_esc = $conn2->real_escape_string($plate);
                $remark_esc = $conn2->real_escape_string($remark);
                $sql = "INSERT INTO p_watch_plates (plate_number, remark, garage_id) VALUES ('{$plate_esc}', '{$remark_esc}', '1730496648745910274')
                        ON DUPLICATE KEY UPDATE remark = '{$remark_esc}', garage_id = '1730496648745910274', is_active = 1";
                if ($conn2->query($sql)) {
                    $messages[] = "地下车库: 成功";
                } else {
                    $errors[] = "地下车库: " . $conn2->error;
                    $success = false;
                }
            }
            
            if ($success) {
                $message = "已添加到两个系统: {$plate}";
            } else {
                $error = "部分失败: " . implode('; ', $errors);
            }
        } else {
            $conn = getConnectionByGarageId($garageId);
            if ($conn) {
                $plate_esc = $conn->real_escape_string($plate);
                $remark_esc = $conn->real_escape_string($remark);
                $garageId_esc = $conn->real_escape_string($garageId);
                
                $sql = "INSERT INTO p_watch_plates (plate_number, remark, garage_id) VALUES ('{$plate_esc}', '{$remark_esc}', '{$garageId_esc}')
                        ON DUPLICATE KEY UPDATE remark = '{$remark_esc}', garage_id = '{$garageId_esc}', is_active = 1";
                $success = $conn->query($sql);
                
                if ($success) {
                    $message = "添加成功：{$plate}";
                } else {
                    $error = "添加失败：" . $conn->error;
                }
            }
        }
    }

    if ($_POST['action'] === 'delete' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);
        $garageId = $_POST['garage_id'] ?? '';

        if (empty($garageId)) {
            $conn1 = DatabaseHelper::getConnection(1);
            $conn2 = DatabaseHelper::getConnection(2);
            
            $success = true;
            if ($conn1) {
                $plate_esc = $conn1->real_escape_string($plate);
                $sql = "DELETE FROM p_watch_plates WHERE plate_number = '{$plate_esc}'";
                if (!$conn1->query($sql)) $success = false;
            }
            if ($conn2) {
                $plate_esc = $conn2->real_escape_string($plate);
                $sql = "DELETE FROM p_watch_plates WHERE plate_number = '{$plate_esc}'";
                if (!$conn2->query($sql)) $success = false;
            }
            
            if ($success) {
                $message = "已从两个系统删除";
            } else {
                $error = "部分删除失败";
            }
        } else {
            $conn = getConnectionByGarageId($garageId);
            if ($conn) {
                $plate_esc = $conn->real_escape_string($plate);
                $garageId_esc = $conn->real_escape_string($garageId);
                
                $sql = "DELETE FROM p_watch_plates WHERE plate_number = '{$plate_esc}' AND (garage_id IS NULL OR garage_id = '{$garageId_esc}')";
                $success = $conn->query($sql);
                
                if ($success) {
                    $message = "删除成功";
                } else {
                    $error = "删除失败";
                }
            }
        }
    }

    if ($_POST['action'] === 'toggle' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);
        $newStatus = intval($_POST['new_status']);
        $garageId = $_POST['garage_id'] ?? '';

        if (empty($garageId)) {
            $conn1 = DatabaseHelper::getConnection(1);
            $conn2 = DatabaseHelper::getConnection(2);
            
            $success = true;
            if ($conn1) {
                $plate_esc = $conn1->real_escape_string($plate);
                $sql = "UPDATE p_watch_plates SET is_active = {$newStatus} WHERE plate_number = '{$plate_esc}'";
                if (!$conn1->query($sql)) $success = false;
            }
            if ($conn2) {
                $plate_esc = $conn2->real_escape_string($plate);
                $sql = "UPDATE p_watch_plates SET is_active = {$newStatus} WHERE plate_number = '{$plate_esc}'";
                if (!$conn2->query($sql)) $success = false;
            }
            
            if ($success) {
                $message = "两个系统状态已更新";
            } else {
                $error = "部分更新失败";
            }
        } else {
            $conn = getConnectionByGarageId($garageId);
            if ($conn) {
                $plate_esc = $conn->real_escape_string($plate);
                $garageId_esc = $conn->real_escape_string($garageId);
                
                $sql = "UPDATE p_watch_plates SET is_active = {$newStatus} WHERE plate_number = '{$plate_esc}' AND (garage_id IS NULL OR garage_id = '{$garageId_esc}')";
                $success = $conn->query($sql);
                
                if ($success) {
                    $message = "状态更新成功";
                } else {
                    $error = "状态更新失败";
                }
            }
        }
    }
}

// 获取关注车牌列表
$watchPlates = [];
if (empty($selectedGarageId)) {
    $conn1 = DatabaseHelper::getConnection(1);
    $conn2 = DatabaseHelper::getConnection(2);
    
    if ($conn1) {
        $result = $conn1->query("SELECT * FROM p_watch_plates ORDER BY created_at DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['data_source'] = '广场停车场';
                $watchPlates[] = $row;
            }
        }
    }
    if ($conn2) {
        $result = $conn2->query("SELECT * FROM p_watch_plates ORDER BY created_at DESC");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $row['data_source'] = '星光大厦地下停车库';
                $watchPlates[] = $row;
            }
        }
    }
    
    usort($watchPlates, function($a, $b) {
        return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
    });
} else {
    $conn = getConnectionByGarageId($selectedGarageId);
    if ($conn) {
        $garageId_esc = $conn->real_escape_string($selectedGarageId);
        $where = " WHERE garage_id IS NULL OR garage_id = '{$garageId_esc}'";
        
        $sql = "SELECT * FROM p_watch_plates {$where} ORDER BY created_at DESC";
        $result = $conn->query($sql);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($selectedGarageId == '1738360658409357314') {
                    $row['data_source'] = '广场停车场';
                } elseif ($selectedGarageId == '1730496648745910274') {
                    $row['data_source'] = '星光大厦地下停车库';
                }
                $watchPlates[] = $row;
            }
        }
    }
}

$stats = [];
if ($conn) {
    $result = $conn->query("SELECT notify_count FROM p_update_flag WHERE id = 1");
    if ($result && $row = $result->fetch_assoc()) {
        $stats = $row;
    }
}

// 获取启用中的关注车牌数量
$activeCount = 0;
foreach ($watchPlates as $plate) {
    if ($plate['is_active']) {
        $activeCount++;
    }
}

// 获取当前车库名称
$currentGarageName = '';
foreach ($garageList as $g) {
    if ($g['garage_id'] == $selectedGarageId) {
        $currentGarageName = $g['garage_name'];
        break;
    }
}
if (empty($currentGarageName) && !empty($selectedGarageId)) {
    $currentGarageName = '全部车库';
}

if ($conn) DatabaseHelper::closeConnection(1);
if (isset($conn2)) DatabaseHelper::closeConnection(2);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="../" />
    <title>关注车牌管理 - CarAlert</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold">
                <i class="fa fa-bell mr-2"></i>关注车牌管理
            </h1>
            <div class="flex items-center space-x-4">
                <a href="index.php" class="bg-blue-700 hover:bg-blue-800 px-3 py-1 rounded-md text-sm">
                    <i class="fa fa-home mr-1"></i>返回首页
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6 max-w-4xl">
        <div class="mb-6">
            <p class="text-gray-600">当关注的车牌被抓拍时，将自动发送微信通知</p>
            <?php if (!empty($currentGarageName)): ?>
            <p class="text-blue-600 font-semibold mt-2">
                <i class="fa fa-map-marker mr-1"></i>当前车库：<?php echo htmlspecialchars($currentGarageName); ?>
            </p>
            <?php endif; ?>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">关注车牌数</div>
                <div class="text-2xl font-bold text-blue-600"><?php echo count($watchPlates); ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">已启用数量</div>
                <div class="text-2xl font-bold text-green-600"><?php echo $activeCount; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">已发送通知</div>
                <div class="text-2xl font-bold text-purple-600"><?php echo $stats['notify_count'] ?? 0; ?></div>
            </div>
        </div>

        <?php if ($message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
            <i class="fa fa-check-circle mr-2"></i><?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
            <i class="fa fa-exclamation-circle mr-2"></i><?php echo htmlspecialchars($error); ?>
        </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-700 mb-4">
                <i class="fa fa-plus-circle text-green-500 mr-2"></i>添加关注车牌
            </h2>
            <form method="POST" class="flex flex-col md:flex-row gap-4">
                <input type="hidden" name="action" value="add">
                <div class="w-48">
                    <select name="garage_id" id="add-garage-id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">选择车库</option>
                        <?php foreach ($garageList as $garage): ?>
                        <option value="<?php echo htmlspecialchars($garage['garage_id']); ?>" <?php echo $garage['garage_id'] == $selectedGarageId ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($garage['garage_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <input type="text" name="plate_number" placeholder="输入车牌号（如：渝A12345）" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div class="flex-1">
                    <input type="text" name="remark" placeholder="备注说明（可选）"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa fa-plus mr-2"></i>添加
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h2 class="text-lg font-semibold text-gray-700">
                        <i class="fa fa-list text-blue-500 mr-2"></i>关注列表
                    </h2>
                    <div class="flex items-center space-x-2">
                        <select id="filter-garage" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">全部车库</option>
                            <?php foreach ($garageList as $garage): ?>
                            <option value="<?php echo htmlspecialchars($garage['garage_id']); ?>" <?php echo $garage['garage_id'] == $selectedGarageId ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($garage['garage_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (empty($watchPlates)): ?>
            <div class="px-6 py-8 text-center text-gray-500">
                <i class="fa fa-inbox text-4xl mb-4"></i>
                <p>暂无关注的车牌</p>
                <p class="text-sm mt-2">添加关注车牌后，当这些车辆被抓拍时将收到微信通知</p>
            </div>
            <?php else: ?>
            <table class="w-full">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">车牌号</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">备注</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">状态</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">添加时间</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($watchPlates as $plate): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="text-lg font-semibold text-gray-800"><?php echo htmlspecialchars($plate['plate_number']); ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-gray-600">
                            <?php echo htmlspecialchars($plate['remark'] ?: '-'); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php if ($plate['is_active']): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">启用中</span>
                            <?php else: ?>
                            <span class="px-2 py-1 bg-gray-100 text-gray-800 rounded-full text-xs">已禁用</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?php echo $plate['created_at']; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($plate['plate_number']); ?>">
                                <input type="hidden" name="new_status" value="<?php echo $plate['is_active'] ? 0 : 1; ?>">
                                <input type="hidden" name="garage_id" value="<?php echo htmlspecialchars($selectedGarageId); ?>">
                                <button type="submit" class="text-blue-600 hover:text-blue-800 mr-3">
                                    <i class="fa fa-<?php echo $plate['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $plate['is_active'] ? '禁用' : '启用'; ?>
                                </button>
                            </form>
                            <form method="POST" class="inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($plate['plate_number']); ?>">
                                <input type="hidden" name="garage_id" value="<?php echo htmlspecialchars($selectedGarageId); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800" onclick="return confirm('确定要删除这个车牌吗？')">
                                    <i class="fa fa-trash"></i>删除
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </main>

    <script>
        document.getElementById('filter-garage').addEventListener('change', function() {
            const garageId = this.value;
            let url = window.location.pathname;
            if (garageId) {
                url += '?garage_id=' + encodeURIComponent(garageId);
            }
            window.location.href = url;
        });
    </script>
</body>
</html>
