<?php
/**
 * 数据库配置文件 - 模板
 * 
 * 复制此文件为 config.php 并填入真实配置
 * config.php 已被 .gitignore 忽略，不会被提交
 */

// 广场停车场数据库配置
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_USER', 'your_username');
define('DB_PASS', 'your_password');
define('DB_NAME', 'your_database');

// 地下车库数据库配置
define('DB2_HOST', 'localhost');
define('DB2_PORT', 3306);
define('DB2_USER', 'your_username');
define('DB2_PASS', 'your_password');
define('DB2_NAME', 'your_database');

// Server酱通知
define('NOTIFY_SERVERCHAN', false);
define('SERVERCHAN_SENDKEY', 'YOUR_SERVERCHAN_SENDKEY');

// 钉钉通知
define('NOTIFY_DINGTALK', false);
define('DINGTALK_WEBHOOK', 'https://oapi.dingtalk.com/robot/send?access_token=YOUR_ACCESS_TOKEN');
define('DINGTALK_SECRET', '');

// 企业微信 Webhook 通知（测试环境）
define('NOTIFY_WECHAT_WORK_WEBHOOK', false);
define('WECHAT_WORK_WEBHOOK', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=YOUR_WEBHOOK_KEY');

// 企业微信 Webhook 通知（正式环境）
define('NOTIFY_WECHAT_WORK_WEBHOOK_PROD', false);
define('WECHAT_WORK_WEBHOOK_PROD', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=YOUR_WEBHOOK_KEY');

// 企业微信 API（可选）
define('WECHAT_WORK_CORP_ID', '');
define('WECHAT_WORK_SECRET', '');
define('WECHAT_WORK_AGENT_ID', '');
define('WECHAT_WORK_TOUSER', '@all');

// 轮询间隔（秒）
define('POLL_INTERVAL', 1);
