<?php
/**
 * 环境变量加载器
 * 
 * 自动检测当前环境并加载对应的 .env 文件
 * 开发环境：.env.dev
 * 生产环境：.env
 * 
 * 使用方式：
 * require_once 'env_loader.php';
 * $value = env('DB_HOST', 'default_value');
 */

function env($key, $default = null) {
    return isset($_ENV[$key]) ? $_ENV[$key] : $default;
}

function env_bool($key, $default = false) {
    $value = env($key, $default);
    return in_array(strtolower((string)$value), ['true', '1', 'yes', 'on']);
}

function env_int($key, $default = 0) {
    $value = env($key, $default);
    return (int)$value;
}

function load_env($env_path = null) {
    // 自动检测环境
    if ($env_path === null) {
        $env_path = getenv('APP_ENV') === 'production' ? '.env' : '.env.dev';
    }
    
    // 检查文件是否存在
    if (!file_exists($env_path)) {
        // 尝试从 daemon 目录查找
        $daemon_env_path = dirname(__FILE__) . '/../' . $env_path;
        if (file_exists($daemon_env_path)) {
            $env_path = $daemon_env_path;
        } else {
            error_log("Warning: Environment file not found: $env_path");
            return false;
        }
    }
    
    // 读取并解析 .env 文件
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $line) {
        // 跳过注释行
        if (strpos(trim($line), '#') === 0) {
            continue;
        }
        
        // 解析 key=value
        list($key, $value) = explode('=', $line, 2);
        
        // 移除引号
        $value = trim($value);
        if ((strpos($value, '"') === 0 && substr($value, -1) === '"') ||
            (strpos($value, "'") === 0 && substr($value, -1) === "'")) {
            $value = substr($value, 1, -1);
        }
        
        // 设置环境变量
        putenv("$key=$value");
        $_ENV[$key] = $value;
    }
    
    return true;
}

// 自动加载环境变量
load_env();
