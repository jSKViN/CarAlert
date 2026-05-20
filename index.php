<?php
/**
 * CarAlert - 车辆监控预警系统
 * 主页面
 */

date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/daemon/config.php';
require_once __DIR__ . '/api/DatabaseHelper.php';

function getDbConnection() {
    return DatabaseHelper::getConnection(1);
}

$conn = getDbConnection();
$stats = ['today_in' => 0, 'today_out' => 0, 'current_count' => 0, 'total_count' => 0];

if ($conn) {
    $today = date('Y-m-d') . ' 00:00:00';

    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM p_distinguish_log WHERE create_time >= '{$today}' AND lane_direction = 0");
        $stats['today_in'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } catch (Exception $e) {
        $stats['today_in'] = 0;
    }

    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM p_distinguish_log WHERE create_time >= '{$today}' AND lane_direction = 1");
        $stats['today_out'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } catch (Exception $e) {
        $stats['today_out'] = 0;
    }

    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM p_distinguish_log WHERE create_time >= '{$today}'");
        $stats['current_count'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } catch (Exception $e) {
        $stats['current_count'] = 0;
    }

    try {
        $result = $conn->query("SELECT COUNT(*) as count FROM p_distinguish_log");
        $stats['total_count'] = $result ? ($result->fetch_assoc()['count'] ?? 0) : 0;
    } catch (Exception $e) {
        $stats['total_count'] = 0;
    }

    DatabaseHelper::closeConnection(1);
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CarAlert - 车辆监控预警系统</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/font-awesome@4.7.0/css/font-awesome.min.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <header class="bg-blue-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3 flex justify-between items-center">
            <h1 class="text-xl font-bold">
                <i class="fa fa-car mr-2"></i>CarAlert - 车辆监控预警系统
            </h1>
            <div class="flex items-center space-x-4">
                <span id="connection-status" class="flex items-center text-sm">
                    <i class="fa fa-circle text-green-400 mr-2"></i>
                    <span>系统正常</span>
                </span>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-6">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">今日入口抓拍</div>
                <div class="text-2xl font-bold text-blue-600" id="today-in"><?php echo $stats['today_in']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">今日出口抓拍</div>
                <div class="text-2xl font-bold text-red-600" id="today-out"><?php echo $stats['today_out']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">今日总抓拍</div>
                <div class="text-2xl font-bold text-green-600" id="current-count"><?php echo $stats['current_count']; ?></div>
            </div>
            <div class="bg-white rounded-lg shadow p-4">
                <div class="text-gray-500 text-sm">总记录数</div>
                <div class="text-2xl font-bold text-purple-600" id="total-count"><?php echo $stats['total_count']; ?></div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">
                    <i class="fa fa-ban text-red-500 mr-2"></i>拉黑车牌管理
                </h2>
                <p class="text-gray-500 mb-4">管理黑名单车辆，当黑名单车辆进出时会触发预警通知</p>
                <div class="flex gap-3">
                    <a href="pages/blacklist.php" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                        <i class="fa fa-list mr-2"></i>查看拉黑列表
                    </a>
                    <a href="pages/blacklist.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-plus mr-2"></i>添加拉黑
                    </a>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">
                    <i class="fa fa-bell text-yellow-500 mr-2"></i>关注车牌管理
                </h2>
                <p class="text-gray-500 mb-4">管理关注车辆，当关注车辆进出时会发送微信通知</p>
                <div class="flex gap-3">
                    <a href="pages/watch_plates.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                        <i class="fa fa-list mr-2"></i>查看关注列表
                    </a>
                    <a href="pages/watch_plates.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                        <i class="fa fa-plus mr-2"></i>添加关注
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">
                    <i class="fa fa-server text-blue-500 mr-2"></i>系统状态
                </h2>
                <div class="space-y-3">
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">数据库连接</span>
                        <span class="text-green-600"><i class="fa fa-check-circle mr-1"></i>正常</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">WebSocket服务</span>
                        <span class="text-gray-500"><i class="fa fa-question-circle mr-1"></i>未检测</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b">
                        <span class="text-gray-600">通知服务</span>
                        <span class="text-gray-500"><i class="fa fa-question-circle mr-1"></i>未检测</span>
                    </div>
                    <div class="flex justify-between items-center py-2">
                        <span class="text-gray-600">系统时间</span>
                        <span class="text-gray-800"><?php echo date('Y-m-d H:i:s'); ?></span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold text-gray-700 mb-4">
                    <i class="fa fa-info-circle text-blue-500 mr-2"></i>使用说明
                </h2>
                <div class="text-gray-600 space-y-2 text-sm">
                    <p><i class="fa fa-angle-right mr-2 text-blue-500"></i><strong>拉黑车牌</strong>：将被限制通行的车辆加入黑名单，触发实时预警</p>
                    <p><i class="fa fa-angle-right mr-2 text-blue-500"></i><strong>关注车牌</strong>：重点关注车辆的进出记录，触发微信通知</p>
                    <p><i class="fa fa-angle-right mr-2 text-blue-500"></i><strong>实时监控</strong>：查看车辆进出抓拍记录和实时画面</p>
                    <p><i class="fa fa-angle-right mr-2 text-blue-500"></i><strong>通知推送</strong>：支持Server酱、钉钉、企业微信多种通知方式</p>
                </div>
            </div>
        </div>
    </main>

    <footer class="bg-gray-800 text-white py-4 mt-8">
        <div class="container mx-auto px-4 text-center text-sm">
            <p>CarAlert - 车辆监控预警系统 &copy; <?php echo date('Y'); ?></p>
        </div>
    </footer>
</body>
</html>
