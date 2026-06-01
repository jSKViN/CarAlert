<?php
/**
 * 拉黑车牌管理页面
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/../daemon/config.php';
require_once __DIR__ . '/../api/DatabaseHelper.php';

$conn = DatabaseHelper::getConnection(1);
$message = '';
$error = '';

$stats = ['total' => 0, 'temporary' => 0, 'permanent' => 0, 'expired' => 0];
if ($conn) {
    $result = $conn->query("SELECT COUNT(*) as total FROM p_blacklist_plates WHERE is_active = 1");
    $stats['total'] = $result->fetch_assoc()['total'] ?? 0;

    $result = $conn->query("SELECT COUNT(*) as temporary FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 1");
    $stats['temporary'] = $result->fetch_assoc()['temporary'] ?? 0;

    $result = $conn->query("SELECT COUNT(*) as permanent FROM p_blacklist_plates WHERE is_active = 1 AND blacklist_type = 2");
    $stats['permanent'] = $result->fetch_assoc()['permanent'] ?? 0;
}

DatabaseHelper::closeConnection(1);

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
$conn2 = DatabaseHelper::getConnection(2);
if ($conn2) {
    $result = $conn2->query("SELECT garage_id, garage_name FROM p_garage");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $garageList[] = $row;
        }
    }
    DatabaseHelper::closeConnection(2);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="../" />
    <title>拉黑车牌管理 - CarAlert</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
    <style>
        .modal { display: none; }
        .modal.active { display: flex; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <header class="bg-red-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold">
                <i class="fa fa-ban mr-2"></i>拉黑车牌管理
            </h1>
            <div class="flex items-center space-x-4">
                <span id="connection-status" class="flex items-center text-sm">
                    <i class="fa fa-circle text-green-400 mr-2"></i>
                    <span>系统正常</span>
                </span>
                <a href="index.php" class="bg-red-700 hover:bg-red-800 px-3 py-1 rounded-md text-sm">
                    <i class="fa fa-home mr-1"></i>返回首页
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">当前拉黑总数</div>
                <div class="text-2xl font-bold text-red-600" id="stat-total"><?php echo $stats['total']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">临时拉黑</div>
                <div class="text-2xl font-bold text-orange-500" id="stat-temporary"><?php echo $stats['temporary']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">永久拉黑</div>
                <div class="text-2xl font-bold text-purple-600" id="stat-permanent"><?php echo $stats['permanent']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">最近操作</div>
                <div class="text-2xl font-bold text-blue-600" id="stat-recent">-</div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-4">
                <h2 class="text-lg font-semibold text-gray-700">
                    <i class="fa fa-plus-circle text-green-500 mr-2"></i>添加拉黑车牌
                </h2>
                <div class="flex items-center space-x-2">
                    <button id="test-notify-btn" class="bg-gray-500 hover:bg-gray-600 text-white px-4 py-2 rounded-lg text-sm">
                        <i class="fa fa-bell mr-1"></i>测试通知
                    </button>
                </div>
            </div>

            <form id="add-form" class="flex flex-col md:flex-row gap-4">
                <div class="w-48">
                    <select id="garage-id" name="garage_id"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="">全部车库</option>
                        <?php foreach ($garageList as $garage): ?>
                        <option value="<?php echo htmlspecialchars($garage['garage_id']); ?>"><?php echo htmlspecialchars($garage['garage_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex-1">
                    <input type="text" id="plate-number" name="plate_number" placeholder="输入车牌号（如：渝A12345）" required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="flex-1">
                    <input type="text" id="reason" name="reason" placeholder="拉黑原因（可选）"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="w-32">
                    <select id="blacklist-type" name="blacklist_type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="1">临时拉黑</option>
                        <option value="2">永久拉黑</option>
                    </select>
                </div>
                <div class="w-32" id="days-container">
                    <input type="number" id="days" name="days" value="30" min="1" max="365" placeholder="天数"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="w-32">
                    <input type="text" id="operator" name="operator" placeholder="操作人" value="admin"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <button type="submit" class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fa fa-ban mr-2"></i>添加拉黑
                </button>
            </form>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <h2 class="text-lg font-semibold text-gray-700">
                        <i class="fa fa-list text-blue-500 mr-2"></i>拉黑列表
                    </h2>
                    <div class="flex items-center space-x-2">
                        <select id="filter-garage" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">全部车库</option>
                            <?php foreach ($garageList as $garage): ?>
                            <option value="<?php echo htmlspecialchars($garage['garage_id']); ?>"><?php echo htmlspecialchars($garage['garage_name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" id="search-input" placeholder="搜索车牌号"
                               class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <select id="filter-type" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">全部类型</option>
                            <option value="1">临时拉黑</option>
                            <option value="2">永久拉黑</option>
                        </select>
                        <select id="filter-status" class="px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">全部状态</option>
                            <option value="1" selected>生效中</option>
                            <option value="0">已解除</option>
                        </select>
                        <button id="search-btn" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                            <i class="fa fa-search mr-1"></i>搜索
                        </button>
                    </div>
                </div>
            </div>

            <div id="plate-list" class="divide-y divide-gray-200">
                <div class="px-6 py-8 text-center text-gray-500">
                    <i class="fa fa-spinner fa-spin text-2xl mb-2"></i>
                    <p>加载中...</p>
                </div>
            </div>

            <div id="pagination" class="px-6 py-4 border-t border-gray-200 flex justify-between items-center">
                <div class="text-sm text-gray-500">
                    共 <span id="total-count">0</span> 条记录
                </div>
                <div class="flex items-center space-x-2" id="pagination-buttons">
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-700">
                    <i class="fa fa-history text-gray-500 mr-2"></i>操作日志
                </h2>
            </div>
            <div id="log-list" class="divide-y divide-gray-200 max-h-96 overflow-y-auto">
                <div class="px-6 py-4 text-center text-gray-500">
                    <i class="fa fa-spinner fa-spin text-xl mb-2"></i>
                    <p>加载中...</p>
                </div>
            </div>
        </div>
    </main>

    <div id="edit-modal" class="modal fixed inset-0 bg-black bg-opacity-50 z-50 items-center justify-center">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-semibold text-gray-700">编辑拉黑信息</h3>
                <button id="close-edit-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <form id="edit-form" class="p-6">
                <input type="hidden" id="edit-plate-number" name="plate_number">
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">拉黑原因</label>
                    <input type="text" id="edit-reason" name="reason"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">拉黑类型</label>
                    <select id="edit-type" name="blacklist_type"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                        <option value="1">临时拉黑</option>
                        <option value="2">永久拉黑</option>
                    </select>
                </div>
                <div class="mb-4" id="edit-days-container">
                    <label class="block text-gray-700 text-sm font-bold mb-2">拉黑天数</label>
                    <input type="number" id="edit-days" name="days" value="30" min="1" max="365"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2">操作人</label>
                    <input type="text" id="edit-operator" name="operator"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500">
                </div>
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-edit" class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        取消
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        保存修改
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="fixed bottom-4 right-4 px-6 py-3 rounded-lg shadow-lg transform translate-y-20 opacity-0 transition-all duration-300">
    </div>

    <script src="js/blacklist.js"></script>
</body>
</html>
