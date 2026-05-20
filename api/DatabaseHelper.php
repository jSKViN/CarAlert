<?php
/**
 * 数据库辅助类
 */

class DatabaseHelper
{
    private static $instances = [];

    public static function getConnection($dbNumber = 1)
    {
        $key = "db_{$dbNumber}";
        if (!isset(self::$instances[$key])) {
            $host = ($dbNumber == 1) ? DB_HOST : DB2_HOST;
            $port = ($dbNumber == 1) ? DB_PORT : DB2_PORT;
            $user = ($dbNumber == 1) ? DB_USER : DB2_USER;
            $pass = ($dbNumber == 1) ? DB_PASS : DB2_PASS;
            $name = ($dbNumber == 1) ? DB_NAME : DB2_NAME;

            if ($dbNumber == 2 && !defined('DB2_HOST')) {
                return null;
            }

            $conn = new mysqli($host, $user, $pass, $name, $port);
            if ($conn->connect_error) {
                error_log("数据库{$dbNumber}连接失败: " . $conn->connect_error);
                return null;
            }
            $conn->set_charset("utf8mb4");
            self::$instances[$key] = $conn;
        }
        return self::$instances[$key];
    }

    public static function closeConnection($dbNumber = 1)
    {
        $key = "db_{$dbNumber}";
        if (isset(self::$instances[$key])) {
            self::$instances[$key]->close();
            unset(self::$instances[$key]);
        }
    }

    public static function closeAllConnections()
    {
        foreach (self::$instances as $conn) {
            $conn->close();
        }
        self::$instances = [];
    }
}
