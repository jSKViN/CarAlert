# CarAlert - 车辆监控预警系统

> 基于PHP开发的车辆监控预警系统，支持实时抓拍比对、关注车牌提醒、拉黑车牌预警等功能。

## 📋 功能特性

### 核心功能
- ✅ **实时抓拍比对** - 守护进程自动检测新抓拍记录
- ✅ **关注车牌提醒** - 关注车辆进入时发送通知
- ✅ **拉黑车牌预警** - 拉黑车辆进入时发送告警
- ✅ **多数据库同步** - 支持双数据库数据同步
- ✅ **企业微信通知** - Webhook实时推送

### 管理功能
- ✅ **拉黑车牌管理** - 添加、编辑、删除、查询
- ✅ **关注车牌管理** - 添加、启用/禁用、删除
- ✅ **操作日志记录** - 审计追溯
- ✅ **开机自启动** - Windows任务计划程序支持

## 🏗️ 技术架构

```
┌─────────────────────────────────────────────────────────────┐
│                     CarAlert 系统架构                        │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌──────────────┐    HTTP    ┌──────────────┐              │
│  │   前端页面    │───────────▶│   PHP API    │              │
│  │ (HTML+JS)    │            │ (RESTful)    │              │
│  └──────────────┘            └──────┬───────┘              │
│                                     │                       │
│                                     ▼                       │
│  ┌────────────────────────────────────────────────┐         │
│  │              MySQL 数据库                      │         │
│  │  - p_distinguish_log   (抓拍记录表)           │         │
│  │  - p_watch_plates      (关注车牌表)           │         │
│  │  - p_blacklist_plates  (拉黑车牌表)           │         │
│  │  - p_blacklist_log     (操作日志表)           │         │
│  │  - p_update_flag       (更新标记表)           │         │
│  └────────────────────────────────────────────────┘         │
│                                     │                       │
│                                     ▼                       │
│  ┌──────────────┐    轮询检测    ┌──────────────┐           │
│  │  守护进程    │◀───────────────│   新记录     │           │
│  │ (PHP Daemon) │                │              │           │
│  └──────┬───────┘                └──────────────┘           │
│         │                                                   │
│         ▼                                                   │
│  ┌──────────────┐                                          │
│  │ 企业微信     │                                          │
│  │ Webhook     │                                          │
│  └──────────────┘                                          │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

## 🛠️ 技术栈

| 分类 | 技术 | 版本 |
|---|---|---|
| 前端 | HTML5 | - |
| 样式 | Tailwind CSS | 3.x |
| 图标 | Font Awesome | 4.7 |
| 后端 | PHP | 7.4+ |
| 数据库 | MySQL | 5.7+ |
| 通知 | 企业微信 Webhook | - |

## 📁 项目结构

```
CarAlert/
├── api/                    # API接口目录
│   ├── DatabaseHelper.php  # 数据库助手类
│   ├── blacklist.php       # 拉黑车牌API
│   ├── blacklist_notify.php # 拉黑通知API
│   ├── records.php         # 记录查询API
│   ├── dingtalk_notify.php # 钉钉通知（已禁用）
│   ├── serverchan_notify.php # Server酱通知（已禁用）
│   └── wechat_work_webhook_notify.php # 企业微信通知
├── daemon/                 # 守护进程目录
│   ├── config.php          # 配置文件
│   ├── notify_daemon.php   # 守护进程主程序
│   ├── start_daemon.bat    # 启动脚本
│   ├── start_daemon_hidden.vbs # 隐藏启动脚本
│   ├── install_task_en.bat # 安装开机自启任务
│   └── uninstall_task_en.bat # 卸载开机自启任务
├── database/               # 数据库脚本
│   └── migration_blacklist.sql # 数据库迁移脚本
├── pages/                  # 前端页面
│   ├── blacklist.php       # 拉黑车牌管理页面
│   └── watch_plates.php    # 关注车牌管理页面
├── js/                     # 前端脚本
│   └── blacklist.js        # 拉黑管理页脚本
├── tests/                  # 测试文件
│   ├── BlacklistApiTest.php
│   ├── BlacklistIntegrationTest.php
│   ├── DatabaseHelperTest.php
│   └── run_tests.php
├── .gitignore              # Git忽略配置
├── index.php               # 首页
└── README.md               # 项目说明
```

## 🚀 快速开始

### 环境要求
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx Web服务器

### 安装步骤

1. **克隆项目**
```bash
git clone <repository-url>
cd CarAlert
```

2. **配置数据库**
```sql
-- 创建数据库
CREATE DATABASE car_alert CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 执行迁移脚本
SOURCE database/migration_blacklist.sql;
```

3. **配置连接信息**
编辑 `daemon/config.php`：
```php
// 数据库1配置
define('DB1_HOST', 'localhost');
define('DB1_USER', 'root');
define('DB1_PASS', 'your_password');
define('DB1_NAME', 'car_alert');

// 企业微信Webhook配置
define('NOTIFY_WECHAT_WORK_WEBHOOK', true);
define('WECHAT_WORK_WEBHOOK', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your_key');
```

4. **启动守护进程**
```cmd
cd daemon
start_daemon.bat
```

5. **访问管理页面**
- 首页: http://localhost/CarAlert/
- 拉黑管理: http://localhost/CarAlert/pages/blacklist.php
- 关注管理: http://localhost/CarAlert/pages/watch_plates.php

### 开机自启配置

以管理员身份运行：
```cmd
daemon/install_task_en.bat
```

## 📖 使用说明

### 添加拉黑车牌

**方式1：通过管理页面**
1. 访问 http://localhost/CarAlert/pages/blacklist.php
2. 输入车牌号和拉黑原因
3. 选择拉黑类型（临时/永久）
4. 点击"添加"

**方式2：通过SQL**
```sql
INSERT INTO p_blacklist_plates (plate_number, reason, blacklist_type)
VALUES ('川G9A502', '违规车辆', 2); -- 2=永久拉黑
```

### 添加关注车牌

```sql
INSERT INTO p_watch_plates (plate_number, remark)
VALUES ('渝A205HW', '重要车辆');
```

### 测试抓拍记录

```sql
-- 插入测试抓拍记录
INSERT INTO p_distinguish_log (plate_number, create_time, real_time_info, access_info, lane_id)
VALUES (
    '川G9A502',
    NOW(),
    '{"carType":"轿车","fullPictureUrl":""}',
    '{"fee":0}',
    1
);

-- 更新标记触发检测
UPDATE p_update_flag SET last_record_id = (SELECT MAX(id) FROM p_distinguish_log);
```

## 📊 数据库表结构

### 拉黑车牌表 (p_blacklist_plates)
| 字段 | 类型 | 说明 |
|---|---|---|
| id | INT | 自增ID |
| plate_number | VARCHAR(20) | 车牌号 |
| reason | VARCHAR(500) | 拉黑原因 |
| operator | VARCHAR(50) | 操作人 |
| blacklist_type | TINYINT | 类型：1-临时，2-永久 |
| start_time | TIMESTAMP | 开始时间 |
| end_time | TIMESTAMP | 结束时间（永久为NULL） |
| is_active | TINYINT | 是否生效 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

### 关注车牌表 (p_watch_plates)
| 字段 | 类型 | 说明 |
|---|---|---|
| id | INT | 自增ID |
| plate_number | VARCHAR(20) | 车牌号 |
| remark | VARCHAR(500) | 备注 |
| is_active | TINYINT | 是否启用 |
| created_at | TIMESTAMP | 创建时间 |
| updated_at | TIMESTAMP | 更新时间 |

## 🔧 配置说明

### 通知渠道配置

```php
// 企业微信Webhook（推荐）
define('NOTIFY_WECHAT_WORK_WEBHOOK', true);
define('WECHAT_WORK_WEBHOOK', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=xxx');

// 钉钉（已禁用）
define('NOTIFY_DINGTALK', false);

// Server酱（已禁用）
define('NOTIFY_SERVERCHAN', false);
```

### 守护进程配置

```php
// 轮询间隔（秒）
define('POLL_INTERVAL', 1);

// 日志文件
$logFile = __DIR__ . '/daemon.log';
$pidFile = __DIR__ . '/daemon.pid';
```

## 📝 API 接口

### 拉黑车牌接口

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /api/blacklist.php | 查询拉黑列表 |
| POST | /api/blacklist.php?action=add | 添加拉黑车牌 |
| POST | /api/blacklist.php?action=update | 更新拉黑车牌 |
| POST | /api/blacklist.php?action=delete | 删除拉黑车牌 |

### 通知测试接口

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /api/wechat_work_webhook_notify.php?action=test | 测试企业微信通知 |

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License

## 📞 联系方式

如有问题，请联系项目管理员。