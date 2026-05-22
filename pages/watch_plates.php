<?php
/**
 * 关注车牌管理页面
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../daemon/config.php';
require_once __DIR__ . '/../api/DatabaseHelper.php';

$conn = DatabaseHelper::getConnection(1);
$conn2 = DatabaseHelper::getConnection(2);
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);
        $remark = trim($_POST['remark'] ?? '');

        $success1 = false;
        $success2 = false;

        if ($conn) {
            $plate_esc = $conn->real_escape_string($plate);
            $remark_esc = $conn->real_escape_string($remark);
            $sql = "INSERT INTO p_watch_plates (plate_number, remark) VALUES ('{$plate_esc}', '{$remark_esc}')
                    ON DUPLICATE KEY UPDATE remark = '{$remark_esc}', is_active = 1";
            $success1 = $conn->query($sql);
        }

        if ($conn2) {
            $plate_esc = $conn2->real_escape_string($plate);
            $remark_esc = $conn2->real_escape_string($remark);
            $sql = "INSERT INTO p_watch_plates (plate_number, remark) VALUES ('{$plate_esc}', '{$remark_esc}')
                    ON DUPLICATE KEY UPDATE remark = '{$remark_esc}', is_active = 1";
            $success2 = $conn2->query($sql);
        }

        if ($success1 && $success2) {
            $message = "添加成功：{$plate}（已同步到两个数据库）";
        } elseif ($success1) {
            $message = "添加成功：{$plate}（仅数据库1）";
        } elseif ($success2) {
            $message = "添加成功：{$plate}（仅数据库2）";
        } else {
            $error = "添加失败";
        }
    }

    if ($_POST['action'] === 'delete' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);

        $success1 = false;
        $success2 = false;

        if ($conn) {
            $plate_esc = $conn->real_escape_string($plate);
            $success1 = $conn->query("DELETE FROM p_watch_plates WHERE plate_number = '{$plate_esc}'");
        }

        if ($conn2) {
            $plate_esc = $conn2->real_escape_string($plate);
            $success2 = $conn2->query("DELETE FROM p_watch_plates WHERE plate_number = '{$plate_esc}'");
        }

        if ($success1 && $success2) {
            $message = "删除成功（已同步到两个数据库）";
        } elseif ($success1 || $success2) {
            $message = "删除成功";
        } else {
            $error = "删除失败";
        }
    }

    if ($_POST['action'] === 'toggle' && !empty($_POST['plate_number'])) {
        $plate = trim($_POST['plate_number']);
        $newStatus = intval($_POST['new_status']);

        $success1 = false;
        $success2 = false;

        if ($conn) {
            $plate_esc = $conn->real_escape_string($plate);
            $success1 = $conn->query("UPDATE p_watch_plates SET is_active = {$newStatus} WHERE plate_number = '{$plate_esc}'");
        }

        if ($conn2) {
            $plate_esc = $conn2->real_escape_string($plate);
            $success2 = $conn2->query("UPDATE p_watch_plates SET is_active = {$newStatus} WHERE plate_number = '{$plate_esc}'");
        }

        if ($success1 && $success2) {
            $message = "状态更新成功（已同步到两个数据库）";
        } elseif ($success1 || $success2) {
            $message = "状态更新成功";
        } else {
            $error = "状态更新失败";
        }
    }
}

$watchPlates = [];
if ($conn) {
    $result = $conn->query("SELECT * FROM p_watch_plates ORDER BY created_at DESC");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $watchPlates[] = $row;
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

if ($conn) DatabaseHelper::closeConnection(1);
if ($conn2) DatabaseHelper::closeConnection(2);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="/caralert/" />
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
            <p class="text-gray-600">当关注的车牌被抓拍时，将自动发送微信通知（同步到两个数据库）</p>
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
                <h2 class="text-lg font-semibold text-gray-700">
                    <i class="fa fa-list text-blue-500 mr-2"></i>关注列表
                </h2>
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
                                <button type="submit" class="text-blue-600 hover:text-blue-800 mr-3">
                                    <i class="fa fa-<?php echo $plate['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $plate['is_active'] ? '禁用' : '启用'; ?>
                                </button>
                            </form>
                            <form method="POST" class="inline" onsubmit="return confirm('确定删除吗？');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="plate_number" value="<?php echo htmlspecialchars($plate['plate_number']); ?>">
                                <button type="submit" class="text-red-600 hover:text-red-800">
                                    <i class="fa fa-trash"></i> 删除
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>

        <div class="mt-6 text-center">
            <a href="../index.php" class="text-blue-600 hover:text-blue-800">
                <i class="fa fa-arrow-left mr-1"></i>返回首页
            </a>
        </div>
    </main>
</body>
</html>
