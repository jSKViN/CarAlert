# API 接口文档

## 基础信息

- **基础URL**: `http://localhost/CarAlert/api/`
- **响应格式**: JSON
- **字符编码**: UTF-8

## 通用响应格式

```json
{
    "success": true,
    "data": {},
    "message": "",
    "timestamp": 1778997125000
}
```

| 字段 | 类型 | 说明 |
|------|------|------|
| `success` | boolean | 是否成功 |
| `data` | object/array | 返回数据 |
| `message` | string | 提示信息 |
| `timestamp` | number | 时间戳（毫秒） |

---

## 拉黑车牌接口

### 1. 查询拉黑列表

**请求**:
```
GET /blacklist.php?action=list
```

**参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `action` | string | 是 | 固定值：list |
| `plate_number` | string | 否 | 车牌号模糊查询 |
| `blacklist_type` | int | 否 | 拉黑类型：1-临时，2-永久 |
| `is_active` | int | 否 | 状态：0-已解除，1-生效中 |
| `limit` | int | 否 | 每页数量，默认100 |
| `offset` | int | 否 | 偏移量，默认0 |

**响应示例**:
```json
{
    "success": true,
    "data": {
        "items": [
            {
                "id": 1,
                "plate_number": "川G9A502",
                "reason": "违规车辆",
                "operator": "admin",
                "blacklist_type": 2,
                "start_time": "2026-05-20 10:00:00",
                "end_time": null,
                "is_active": 1,
                "created_at": "2026-05-20 10:00:00",
                "updated_at": "2026-05-20 10:00:00"
            }
        ],
        "total": 6
    },
    "message": "",
    "timestamp": 1778997125000
}
```

---

### 2. 添加拉黑车牌

**请求**:
```
POST /blacklist.php?action=add
```

**请求体**:
```json
{
    "plate_number": "川G9A502",
    "reason": "违规车辆",
    "operator": "admin",
    "blacklist_type": 2,
    "days": 30
}
```

**参数说明**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `plate_number` | string | 是 | 车牌号 |
| `reason` | string | 否 | 拉黑原因 |
| `operator` | string | 否 | 操作人，默认system |
| `blacklist_type` | int | 否 | 类型：1-临时，2-永久，默认1 |
| `days` | int | 否 | 临时拉黑天数，默认30 |

**响应示例**:
```json
{
    "success": true,
    "data": {
        "id": 7
    },
    "message": "车牌已加入拉黑列表",
    "timestamp": 1778997125000
}
```

---

### 3. 解除拉黑

**请求**:
```
POST /blacklist.php?action=remove
```

**请求体**:
```json
{
    "plate_number": "川G9A502",
    "operator": "admin"
}
```

**参数说明**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `plate_number` | string | 是 | 车牌号 |
| `operator` | string | 否 | 操作人 |

**响应示例**:
```json
{
    "success": true,
    "data": null,
    "message": "拉黑已解除",
    "timestamp": 1778997125000
}
```

---

### 4. 更新拉黑信息

**请求**:
```
PUT /blacklist.php
```

**请求体**:
```json
{
    "plate_number": "川G9A502",
    "reason": "更新原因",
    "operator": "admin",
    "blacklist_type": 1,
    "days": 60
}
```

**参数说明**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `plate_number` | string | 是 | 车牌号 |
| `reason` | string | 否 | 拉黑原因 |
| `operator` | string | 否 | 操作人 |
| `blacklist_type` | int | 否 | 类型：1-临时，2-永久 |
| `days` | int | 否 | 临时拉黑天数（仅当 type=1 时有效） |

**响应示例**:
```json
{
    "success": true,
    "data": null,
    "message": "更新成功",
    "timestamp": 1778997125000
}
```

> **注意**：更新操作会同时发送企业微信通知，通知内容包含更新的信息。

---

### 5. 查询统计信息

**请求**:
```
GET /blacklist.php?action=stats
```

**响应示例**:
```json
{
    "success": true,
    "data": {
        "total": 6,
        "temporary": 2,
        "permanent": 4,
        "expired": 0
    },
    "message": "",
    "timestamp": 1778997125000
}
```

---

### 6. 查询操作日志

**请求**:
```
GET /blacklist.php?action=logs&limit=50
```

**参数**:

| 参数名 | 类型 | 必填 | 说明 |
|--------|------|------|------|
| `action` | string | 是 | 固定值：logs |
| `plate_number` | string | 否 | 指定车牌号 |
| `limit` | int | 否 | 数量限制，默认50 |

**响应示例**:
```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "plate_number": "川G9A502",
            "action_type": "ADD",
            "reason": "违规车辆",
            "operator": "admin",
            "created_at": "2026-05-20 10:00:00"
        }
    ],
    "message": "",
    "timestamp": 1778997125000
}
```

---

### 7. 检查车牌是否在黑名单

**请求**:
```
GET /blacklist.php?action=check&plate_number=川G9A502
```

**响应示例**:
```json
{
    "success": true,
    "data": {
        "is_blacklisted": true
    },
    "message": "",
    "timestamp": 1778997125000
}
```

---

## 企业微信通知接口

### 测试通知

**请求**:
```
GET /wechat_work_webhook_notify.php?action=test
```

**响应示例**:
```json
{
    "success": true,
    "message": "已发送到所有启用的环境",
    "details": {
        "测试环境": {
            "success": true,
            "message": "发送成功"
        }
    }
}
```

---

## 错误码说明

| HTTP状态码 | 说明 |
|------------|------|
| 200 | 请求成功 |
| 400 | 请求参数错误 |
| 404 | 资源不存在 |
| 405 | 请求方法不允许 |
| 500 | 服务器内部错误 |

---

## 使用示例

### cURL 示例

```bash
# 查询拉黑列表
curl "http://localhost/CarAlert/api/blacklist.php?action=list"

# 添加拉黑车牌
curl -X POST "http://localhost/CarAlert/api/blacklist.php?action=add" \
  -H "Content-Type: application/json" \
  -d '{"plate_number":"川G9A502","reason":"测试","blacklist_type":2}'

# 解除拉黑
curl -X POST "http://localhost/CarAlert/api/blacklist.php?action=remove" \
  -H "Content-Type: application/json" \
  -d '{"plate_number":"川G9A502"}'

# 测试通知
curl "http://localhost/CarAlert/api/wechat_work_webhook_notify.php?action=test"
```

### JavaScript 示例

```javascript
// 查询拉黑列表
fetch('/CarAlert/api/blacklist.php?action=list')
  .then(res => res.json())
  .then(data => console.log(data));

// 添加拉黑车牌
fetch('/CarAlert/api/blacklist.php?action=add', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    plate_number: '川G9A502',
    reason: '测试',
    blacklist_type: 2
  })
})
.then(res => res.json())
.then(data => console.log(data));
```