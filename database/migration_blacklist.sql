-- ============================================
-- CarAlert 系统数据库迁移脚本
-- 包含拉黑车牌表和原有表的创建
-- ============================================

-- 创建拉黑车牌表
CREATE TABLE IF NOT EXISTS p_blacklist_plates (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    plate_number VARCHAR(20) NOT NULL COMMENT '被拉黑的车牌号',
    reason VARCHAR(500) DEFAULT '' COMMENT '拉黑原因',
    operator VARCHAR(50) DEFAULT '' COMMENT '操作人',
    blacklist_type TINYINT(1) DEFAULT 1 COMMENT '拉黑类型：1-临时拉黑，2-永久拉黑',
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '拉黑开始时间',
    end_time TIMESTAMP NULL COMMENT '拉黑结束时间（永久拉黑为NULL）',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否生效：0-已解除，1-生效中',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '记录创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '最后更新时间',
    UNIQUE KEY uk_plate_active (plate_number, is_active),
    KEY idx_plate_number (plate_number),
    KEY idx_is_active (is_active),
    KEY idx_end_time (end_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拉黑车牌列表';

-- 创建拉黑车牌操作日志表（用于审计）
CREATE TABLE IF NOT EXISTS p_blacklist_log (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    plate_number VARCHAR(20) NOT NULL COMMENT '车牌号',
    action_type VARCHAR(20) NOT NULL COMMENT '操作类型：ADD-添加拉黑，REMOVE-解除拉黑，UPDATE-更新信息',
    reason VARCHAR(500) DEFAULT '' COMMENT '原因/备注',
    operator VARCHAR(50) DEFAULT '' COMMENT '操作人',
    old_data JSON DEFAULT NULL COMMENT '操作前的数据',
    new_data JSON DEFAULT NULL COMMENT '操作后的数据',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    KEY idx_plate_number (plate_number),
    KEY idx_action_type (action_type),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='拉黑车牌操作日志';

-- 保留原有关注车牌表（兼容旧系统）
CREATE TABLE IF NOT EXISTS p_watch_plates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    plate_number VARCHAR(20) NOT NULL COMMENT '关注的车牌号',
    remark VARCHAR(100) DEFAULT '' COMMENT '备注说明',
    is_active TINYINT(1) DEFAULT 1 COMMENT '是否启用：0-禁用，1-启用',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_plate (plate_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='关注车牌列表';

-- 保留原有更新标记表
CREATE TABLE IF NOT EXISTS p_update_flag (
    id INT PRIMARY KEY DEFAULT 1,
    last_record_id BIGINT DEFAULT 0 COMMENT '最后处理的distinguish_id',
    last_update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notify_count INT DEFAULT 0 COMMENT '已发送通知次数'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='数据更新标记表';

-- 初始化标记表
INSERT INTO p_update_flag (id, last_record_id, notify_count)
VALUES (1, 0, 0)
ON DUPLICATE KEY UPDATE id = id;

-- 创建关注车牌操作日志表
CREATE TABLE IF NOT EXISTS p_watch_log (
    id INT AUTO_INCREMENT PRIMARY KEY COMMENT '自增ID',
    plate_number VARCHAR(20) NOT NULL COMMENT '车牌号',
    action_type VARCHAR(20) NOT NULL COMMENT '操作类型：ADD-添加，ENABLE-启用，DISABLE-禁用，DELETE-删除',
    reason VARCHAR(500) DEFAULT '' COMMENT '原因/备注',
    operator VARCHAR(50) DEFAULT '' COMMENT '操作人',
    old_data JSON DEFAULT NULL COMMENT '操作前的数据',
    new_data JSON DEFAULT NULL COMMENT '操作后的数据',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '操作时间',
    KEY idx_plate_number (plate_number),
    KEY idx_action_type (action_type),
    KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='关注车牌操作日志';

-- 初始化关注车牌操作日志（记录当前已存在的关注记录）
INSERT IGNORE INTO p_watch_log (plate_number, action_type, reason, operator)
SELECT plate_number, 'ADD', remark, 'system'
FROM p_watch_plates
WHERE is_active = 1;
