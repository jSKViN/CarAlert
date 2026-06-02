# 守护进程脚本使用说明

## 文件列表

### 广场停车场脚本

| 文件 | 用途 |
|------|------|
| `start_daemon_plaza.bat` | 启动广场停车场守护进程（前台模式） |
| `start_daemon_plaza_hidden.vbs` | 启动广场停车场守护进程（隐藏模式，用于开机自启） |
| `install_task_plaza.bat` | 安装广场停车场守护进程开机自启任务 |
| `uninstall_task_plaza.bat` | 卸载广场停车场守护进程开机自启任务 |
| `clean_daemon_plaza.bat` | 停止并清理广场停车场守护进程（停止进程、删除日志和PID文件） |

### 地下车库脚本

| 文件 | 用途 |
|------|------|
| `start_daemon_garage.bat` | 启动地下车库守护进程（前台模式） |
| `start_daemon_garage_hidden.vbs` | 启动地下车库守护进程（隐藏模式，用于开机自启） |
| `install_task_garage.bat` | 安装地下车库守护进程开机自启任务 |
| `uninstall_task_garage.bat` | 卸载地下车库守护进程开机自启任务 |
| `clean_daemon_garage.bat` | 停止并清理地下车库守护进程（停止进程、删除日志和PID文件） |

## 使用场景

### 广场停车场（数据库1）
- 监控广场停车场的车辆抓拍记录
- 识别广场停车场的关注车牌和拉黑车牌
- 发送通知时带有 `[广场停车场]` 前缀

### 地下车库（数据库2）
- 监控星光大厦地下车库的车辆抓拍记录
- 识别地下车库的关注车牌和拉黑车牌
- 发送通知时带有 `[星光大厦地下车库]` 前缀

## 操作指南

### 1. 手动启动守护进程

**广场停车场：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
start_daemon_plaza.bat
```

**地下车库：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
start_daemon_garage.bat
```

### 2. 设置开机自启

> **注意**：需要以管理员身份运行

**广场停车场：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
install_task_plaza.bat
```

**地下车库：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
install_task_garage.bat
```

### 3. 取消开机自启

> **注意**：需要以管理员身份运行

**广场停车场：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
uninstall_task_plaza.bat
```

**地下车库：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
uninstall_task_garage.bat
```

### 4. 停止并清理守护进程

用于停止运行中的守护进程并删除日志和PID文件。

**广场停车场：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
clean_daemon_plaza.bat
```

**地下车库：**
```cmd
cd d:\xampp\htdocs\CarAlert\daemon
clean_daemon_garage.bat
```

## 任务计划程序中的任务名称

| 任务名称 | 对应系统 |
|----------|----------|
| `CarAlert_Daemon_Plaza` | 广场停车场 |
| `CarAlert_Daemon_Garage` | 地下车库 |

## 日志和PID文件

### 广场停车场
- 日志文件：`daemon.log`
- PID文件：`daemon.pid`

### 地下车库
- 日志文件：`daemon_db2.log`
- PID文件：`daemon_db2.pid`

## 注意事项

1. **两个守护进程是独立的**：广场停车场和地下车库的守护进程各自运行，互不影响
2. **启动顺序**：两个守护进程可以按任意顺序启动
3. **停止方式**：按 `Ctrl+C` 停止前台运行的守护进程，或删除对应的PID文件
4. **日志查看**：可以在运行时查看日志文件了解运行状态
5. **管理员权限**：安装/卸载开机自启任务需要管理员权限

## 故障排查

### 守护进程无法启动
1. 检查PHP是否在系统环境变量PATH中
2. 检查数据库连接配置是否正确（.env文件）
3. 查看日志文件了解详细错误信息

### 收不到通知
1. 检查企业微信Webhook配置是否正确
2. 检查网络连接是否正常
3. 查看守护进程日志确认通知是否发送

### 开机自启不生效
1. 确认已以管理员身份运行安装脚本
2. 检查任务计划程序中是否存在对应的任务
3. 检查任务是否设置为"最高权限运行"
