# CarAlert - 车辆监控预警系统

&gt; 基于PHP开发的车辆监控预警系统，支持实时抓拍比对、关注车牌提醒、拉黑车牌预警等功能。

## 📋 功能特性

### 核心功能
- ✅ **双数据库支持** - 支持两个独立停车场系统（广场 + 地下车库）
- ✅ **实时抓拍比对** - 双守护进程独立监控各自数据库
- ✅ **关注车牌提醒** - 关注车辆被抓拍时发送企业微信通知
- ✅ **拉黑车牌预警** - 拉黑车辆被抓拍时发送企业微信告警
- ✅ **全部车库操作** - 支持同时添加/删除两个系统的车牌
- ✅ **企业微信通知** - 支持测试环境和正式环境独立配置

### 管理功能
- ✅ **拉黑车牌管理** - 添加、编辑、删除、查询（支持临时/永久拉黑）
- ✅ **关注车牌管理** - 添加、启用/禁用、删除
- ✅ **系统切换** - 管理界面支持选择不同停车场系统
- ✅ **操作日志记录** - 拉黑/关注操作审计追溯
- ✅ **开机自启动** - Windows任务计划程序支持

### 技术特性
- ✅ **CDN资源加载** - 使用Tailwind CSS和Font Awesome CDN
- ✅ **Windows兼容** - 守护进程支持Windows系统
- ✅ **UTF-8编码** - 日志和数据库支持中文显示

## 🏗️ 技术架构

```
┌─────────────────────────────────────────────────────────────────────────┐
│                          CarAlert 系统架构                              │
├─────────────────────────────────────────────────────────────────────────┤
│                                                                         │
│  ┌─────────────────────────────┐   ┌─────────────────────────────┐   │
│  │     广场停车场系统          │   │   星光大厦地下车库系统      │   │
│  ├─────────────────────────────┤   ├─────────────────────────────┤   │
│  │  数据库1: bs_park_client   │   │  数据库2: bs_park_client    │   │
│  │  - p_distinguish_log       │   │  - p_distinguish_log       │   │
│  │  - p_watch_plates          │   │  - p_watch_plates          │   │
│  │  - p_blacklist_plates      │   │  - p_blacklist_plates      │   │
│  │  - p_update_flag           │   │  - p_update_flag           │   │
│  │  - p_garage (garage_id:1)  │   │  - p_garage (garage_id:2)  │   │
│  └──────────┬─────────────────┘   └──────────┬─────────────────┘   │
│             │                                  │                    │
│             │ 守护进程监控                      │ 守护进程监控        │
│             ▼                                  ▼                    │
│  ┌─────────────────────────────┐   ┌─────────────────────────────┐   │
│  │  notify_daemon (广场)       │   │  notify_daemon (地下车库)   │   │
│  │  - start_daemon_plaza.bat  │   │  - start_daemon_garage.bat │   │
│  │  - 日志: daemon_plaza.log   │   │  - 日志: daemon_garage.log  │   │
│  │  - PID: daemon_plaza.pid    │   │  - PID: daemon_garage.pid   │   │
│  └──────────┬─────────────────┘   └──────────┬─────────────────┘   │
│             │                                  │                    │
│             └──────────────────┬──────────────────┘                    │
│                                ▼                                       │
│                   ┌─────────────────────────────┐                   │
│                   │      企业微信 Webhook       │                   │
│                   │      [广场] / [地下车库]     │                   │
│                   └─────────────────────────────┘                   │
│                                                                         │
│  ┌───────────────────────────────────────────────────────────────────┐   │
│  │                          管理界面层                                │   │
│  │  ┌──────────────────────────┐   ┌──────────────────────────┐   │   │
│  │  │ pages/blacklist.php      │   │ pages/watch_plates.php   │   │   │
│  │  │ - 支持系统选择           │   │ - 支持系统选择           │   │   │
│  │  │ - 支持"全部车库"操作     │   │ - 支持"全部车库"操作     │   │   │
│  │  └──────────────────────────┘   └──────────────────────────┘   │   │
│  │  ┌───────────────────────────────────────────────────────────┐   │   │
│  │  │         api/DatabaseHelper.php (多数据库连接管理)          │   │   │
│  │  │  - getConnection(1/2): 获取数据库连接                      │   │   │
│  │  │  - GARAGE_ID_MAP: garage_id 映射到数据库                  │   │   │
│  │  └───────────────────────────────────────────────────────────┘   │   │
│  └───────────────────────────────────────────────────────────────────┘   │
│                                                                         │
└─────────────────────────────────────────────────────────────────────────┘
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
│   ├── DatabaseHelper.php  # 数据库助手类（双数据库支持）
│   ├── blacklist.php       # 拉黑车牌API（支持全部车库）
│   ├── blacklist_notify.php # 拉黑通知API
│   ├── records.php         # 记录查询API
│   ├── dingtalk_notify.php # 钉钉通知（已禁用）
│   ├── serverchan_notify.php # Server酱通知（已禁用）
│   └── wechat_work_webhook_notify.php # 企业微信通知
├── daemon/                 # 守护进程目录
│   ├── config.template.php # 配置模板
│   ├── env_loader.php      # .env 文件加载器
│   ├── notify_daemon.php   # 守护进程主程序（支持双数据库）
│   ├── start_daemon.bat    # 原启动脚本（兼容旧版本）
│   ├── start_daemon_plaza.bat # 广场停车场守护进程启动
│   ├── start_daemon_garage.bat # 地下车库守护进程启动
│   ├── start_daemon_hidden.vbs # 隐藏启动脚本
│   ├── install_task_en.bat # 安装开机自启任务
│   └── uninstall_task_en.bat # 卸载开机自启任务
├── database/               # 数据库脚本
│   └── migration_blacklist.sql # 数据库迁移脚本（添加garage_id）
├── docs/                   # 技术文档
│   ├── README.md           # 文档说明
│   ├── 01-系统架构.md      # 系统架构说明
│   ├── 02-守护进程详解.md   # 守护进程工作原理
│   ├── 03-数据库表结构.md   # 数据库设计文档
│   ├── 04-通知系统配置.md   # 通知配置说明
│   ├── 05-API接口文档.md    # API接口规范
│   ├── 06-部署与运维.md     # 部署运维指南
│   └── 07-Git学习指南.md    # Git使用说明
├── pages/                  # 前端页面
│   ├── blacklist.php       # 拉黑车牌管理页面（双数据库支持）
│   └── watch_plates.php    # 关注车牌管理页面（双数据库支持）
├── js/                     # 前端脚本
│   └── blacklist.js        # 拉黑管理页脚本
├── .env                    # 环境配置文件（示例）
├── .gitignore              # Git忽略配置
├── README.md               # 项目说明
└── index.php               # 首页
```

## 🚀 快速开始

### 环境要求
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx Web服务器

### 安装步骤

1. **克隆项目**
```bash
git clone &lt;repository-url&gt;
cd CarAlert
```

2. **配置环境变量**

复制 `.env` 配置文件：
```bash
# Windows
copy .env.example .env
```

编辑 `.env`，填写数据库和企业微信配置：
```env
# ======================
# 数据库1配置 (广场停车场)
# ======================
DB_HOST=127.0.0.1
DB_PORT=33306
DB_USER=root
DB_PASS=your_password
DB_NAME=bs_park_client

# ======================
# 数据库2配置 (星光大厦地下车库)
# ======================
DB2_HOST=192.168.2.190
DB2_PORT=33306
DB2_USER=root
DB2_PASS=your_password
DB2_NAME=bs_park_client

# ======================
# 企业微信Webhook配置
# ======================
WECHAT_WORK_WEBHOOK=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your_test_key
WECHAT_WORK_WEBHOOK_PROD=https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=your_prod_key

# 启用哪个环境? (prod=true则使用正式环境, false则使用测试环境)
USE_PROD_WEBHOOK=false
```

3. **执行数据库迁移**

确保两个数据库都执行了迁移脚本：
```sql
-- 连接数据库1
SOURCE database/migration_blacklist.sql;

-- 连接数据库2
SOURCE database/migration_blacklist.sql;
```

4. **启动守护进程**

**方式A：分别启动两个守护进程（推荐）**

```cmd
# 启动广场停车场守护进程
cd daemon
start_daemon_plaza.bat

# 启动地下车库守护进程（新开一个窗口）
cd daemon
start_daemon_garage.bat
```

**方式B：使用旧的启动脚本（兼容单数据库模式）**

```cmd
cd daemon
start_daemon.bat
```

5. **访问管理页面**
- 首页: http://localhost/CarAlert/
- 拉黑管理: http://localhost/CarAlert/pages/blacklist.php
- 关注管理: http://localhost/CarAlert/pages/watch_plates.php

### 开机自启动配置

以管理员身份运行：
```cmd
# 安装广场停车场任务
daemon\install_task_plaza.bat

# 安装地下车库任务
daemon\install_task_garage.bat
```

## 📖 使用说明

### "全部车库"功能

系统支持同时操作两个停车场系统，使用"全部车库"选项：

1. **添加车牌到两个系统**
   - 在管理界面选择"全部车库"
   - 输入车牌信息并点击"添加"
   - 系统会自动同时写入两个数据库

2. **删除车牌（全部车库）**
   - 在"全部车库"列表中选择要删除的车牌
   - 点击"删除"
   - 系统会同时从两个数据库中删除

3. **查看全部车库数据**
   - 选择"全部车库"可以同时查看两个系统的数据
   - 数据来源会在界面上标注（广场/地下车库）

### 添加拉黑车牌

**方式1：通过管理页面**
- 选择目标系统（广场/地下车库/全部车库）
- 输入车牌号和拉黑原因
- 选择拉黑类型（临时/永久）
- 点击"添加"

**方式2：通过SQL**
```sql
-- 添加到广场停车场 (garage_id=1738360658409357314)
INSERT INTO p_blacklist_plates (plate_number, garage_id, reason, blacklist_type, operator)
VALUES ('川G9A502', '1738360658409357314', '违规车辆', 2, 'admin');

-- 添加到地下车库 (garage_id=1730496648745910274)
INSERT INTO p_blacklist_plates (plate_number, garage_id, reason, blacklist_type, operator)
VALUES ('川G9A502', '1730496648745910274', '违规车辆', 2, 'admin');
```

### 添加关注车牌

```sql
-- 添加到广场停车场
INSERT INTO p_watch_plates (plate_number, garage_id, remark)
VALUES ('渝A205HW', '1738360658409357314', '重要客户车辆');

-- 添加到地下车库
INSERT INTO p_watch_plates (plate_number, garage_id, remark)
VALUES ('渝A205HW', '1730496648745910274', '重要客户车辆');
```

### 测试抓拍记录

```sql
-- 插入测试抓拍记录（广场数据库）
INSERT INTO p_distinguish_log (plate_number, create_time, real_time_info, access_info, lane_id)
VALUES (
    '川G9A502',
    NOW(),
    '{"carType":"轿车","fullPictureUrl":""}',
    '{"garageId":1738360658409357314,"fee":0}',
    1
);

-- 更新标记触发检测
UPDATE p_update_flag SET last_record_id = (SELECT MAX(id) FROM p_distinguish_log);
```

## 📊 数据库表结构

### 1. p_garage（车库信息表）

**说明**：存储车库系统的基本信息，用于识别不同的停车场系统。

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `garage_id` | BIGINT | 车库ID（唯一标识） |
| `garage_name` | VARCHAR(100) | 车库名称 |
| `parking_id` | BIGINT | 关联的停车场ID |
| `create_time` | TIMESTAMP | 创建时间 |
| `update_time` | TIMESTAMP | 更新时间 |

**示例数据**：
| garage_id | garage_name |
|-----------|-------------|
| 1738360658409357314 | 广场停车场 |
| 1730496648745910274 | 星光大厦地下车库 |

### 2. p_blacklist_plates（拉黑车牌表）

增加了 `garage_id` 字段：

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | INT | 自增ID |
| `plate_number` | VARCHAR(20) | 车牌号 |
| `garage_id` | BIGINT | 所属车库ID（NULL表示不限制） |
| `reason` | VARCHAR(500) | 拉黑原因 |
| `operator` | VARCHAR(50) | 操作人 |
| `blacklist_type` | TINYINT | 类型：1-临时，2-永久 |
| `start_time` | TIMESTAMP | 拉黑开始时间 |
| `end_time` | TIMESTAMP | 拉黑结束时间（永久为NULL） |
| `is_active` | TINYINT | 是否生效：0-已解除，1-生效中 |
| `created_at` | TIMESTAMP | 创建时间 |
| `updated_at` | TIMESTAMP | 更新时间 |

### 3. p_watch_plates（关注车牌表）

增加了 `garage_id` 字段：

| 字段名 | 类型 | 说明 |
|--------|------|------|
| `id` | INT | 自增ID |
| `plate_number` | VARCHAR(20) | 车牌号（唯一） |
| `garage_id` | BIGINT | 所属车库ID（NULL表示不限制） |
| `remark` | VARCHAR(100) | 备注说明 |
| `is_active` | TINYINT(1) | 是否启用：0-禁用，1-启用 |
| `created_at` | TIMESTAMP | 创建时间 |
| `updated_at` | TIMESTAMP | 更新时间 |

### 其他表

- `p_distinguish_log` - 车辆抓拍记录表（无变化）
- `p_blacklist_log` - 拉黑操作日志表（无变化）
- `p_watch_log` - 关注操作日志表（无变化）
- `p_update_flag` - 更新标记表（无变化）

## 🔧 配置说明

### 数据库配置

**使用 `DatabaseHelper` 类**：
```php
// 获取广场数据库连接 (数据库1)
$conn1 = DatabaseHelper::getConnection(1);

// 获取地下车库数据库连接 (数据库2)
$conn2 = DatabaseHelper::getConnection(2);

// garage_id 映射到数据库
$dbIndex = DatabaseHelper::getDbIndexByGarageId($garageId);
$conn = DatabaseHelper::getConnection($dbIndex);

// 关闭连接
DatabaseHelper::closeConnection(1);
DatabaseHelper::closeConnection(2);
```

### 通知系统配置

```php
// 测试环境（开发测试用）
define('WECHAT_WORK_WEBHOOK', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=测试key');

// 正式环境（生产环境用）
define('WECHAT_WORK_WEBHOOK_PROD', 'https://qyapi.weixin.qq.com/cgi-bin/webhook/send?key=正式key');

// 环境切换（在 .env 中配置）
define('USE_PROD_WEBHOOK', false); // false=测试环境, true=正式环境
```

### 守护进程配置

**广场停车场守护进程** (`start_daemon_plaza.bat`)：
- 数据库连接：数据库1（DB_HOST）
- 日志文件：`daemon_plaza.log`
- PID文件：`daemon_plaza.pid`

**地下车库守护进程** (`start_daemon_garage.bat`)：
- 数据库连接：数据库2（DB2_HOST）
- 日志文件：`daemon_garage.log`
- PID文件：`daemon_garage.pid`

## 📝 API 接口

### 拉黑车牌接口

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /api/blacklist.php | 查询拉黑列表（支持 garage_id 参数） |
| POST | /api/blacklist.php?action=add | 添加拉黑车牌（支持全部车库） |
| POST | /api/blacklist.php?action=update | 更新拉黑车牌 |
| POST | /api/blacklist.php?action=delete | 删除拉黑车牌（支持全部车库） |

### 关注车牌页面

| 功能 | 说明 |
|---|---|
| 添加车牌 | 支持添加到单个系统或全部车库 |
| 删除车牌 | 支持从单个系统或全部车库删除 |
| 启用/禁用 | 支持同时切换两个系统的状态 |
| 查看列表 | 支持筛选单个系统或查看全部 |

### 通知测试接口

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | /api/wechat_work_webhook_notify.php?action=test | 测试企业微信通知 |

## 🎯 多数据库架构说明

### 核心设计

系统采用"两个独立数据库 + 两个独立守护进程"的架构：

1. **数据分离**：两个数据库完全独立，各自存储自己的抓拍记录、黑名单、关注列表
2. **进程分离**：两个守护进程独立运行，分别监控各自数据库
3. **管理统一**：管理界面可以同时操作两个数据库，提供"全部车库"选项
4. **通知统一**：通知都发送到同一个企业微信群，前缀区分来源

### 车库ID映射

| garage_id | 车库名称 | 数据库索引 |
|-----------|----------|-----------|
| 1738360658409357314 | 广场停车场 | 1（DB_* 配置） |
| 1730496648745910274 | 星光大厦地下车库 | 2（DB2_* 配置） |

### "全部车库"操作流程

```
用户选择"全部车库"
        ↓
获取两个数据库连接（conn1, conn2）
        ↓
分别操作 conn1 和 conn2
        ↓
汇总结果返回给用户
```

## 🤝 贡献指南

欢迎提交 Issue 和 Pull Request！

## 📄 许可证

MIT License

## 📞 联系方式

如有问题，请检查以下资源：
1. 查看日志文件（daemon_plaza.log, daemon_garage.log）
2. 查看本文档
3. 查看代码注释
4. 联系开发人员
