-- 已部署旧版数据库时执行本增量脚本
USE `tubed`;

CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '密钥ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '所属用户ID',
    `name` VARCHAR(60) NOT NULL COMMENT '密钥名称',
    `key_prefix` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '密钥前缀',
    `key_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256密钥摘要',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态：1启用，0停用',
    `rate_limit` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT '窗口内最大请求数',
    `rate_window` INT UNSIGNED NOT NULL DEFAULT 60 COMMENT '限流窗口秒数',
    `total_limit` BIGINT UNSIGNED NOT NULL DEFAULT 10000 COMMENT '总调用额度，0为不限',
    `used_count` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '累计调用次数',
    `last_used_at` DATETIME NULL COMMENT '最后调用时间',
    `expires_at` DATETIME NULL COMMENT '过期时间，空为永不过期',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_api_keys_hash` (`key_hash`),
    KEY `idx_api_keys_user_status` (`user_id`, `status`),
    KEY `idx_api_keys_expires` (`expires_at`),
    CONSTRAINT `fk_api_keys_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='开放API密钥表';

CREATE TABLE IF NOT EXISTS `api_rate_buckets` (
    `api_key_id` BIGINT UNSIGNED NOT NULL COMMENT '密钥ID',
    `window_start` DATETIME NOT NULL COMMENT '限流窗口开始时间',
    `request_count` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '窗口内请求数',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`api_key_id`, `window_start`),
    KEY `idx_api_rate_window` (`window_start`),
    CONSTRAINT `fk_api_rate_key`
        FOREIGN KEY (`api_key_id`) REFERENCES `api_keys` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='开放API限流计数表';

CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '配置ID',
    `setting_key` VARCHAR(80) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '配置键',
    `setting_value` VARCHAR(500) NOT NULL COMMENT '配置值',
    `value_type` VARCHAR(20) NOT NULL DEFAULT 'string' COMMENT '值类型',
    `description` VARCHAR(255) NULL COMMENT '配置说明',
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_system_settings_key` (`setting_key`)
) ENGINE=InnoDB COMMENT='系统配置表';

INSERT IGNORE INTO `system_settings`
    (`setting_key`, `setting_value`, `value_type`, `description`)
VALUES
    ('open_api_enabled', '1', 'boolean', '是否启用开放API'),
    ('open_api_upload_enabled', '1', 'boolean', '是否允许开放API上传图片'),
    ('open_api_default_rate_limit', '60', 'integer', '新密钥默认窗口请求数'),
    ('open_api_default_rate_window', '60', 'integer', '新密钥默认限流窗口秒数'),
    ('open_api_default_total_limit', '10000', 'integer', '新密钥默认总调用额度'),
    ('open_api_max_keys_per_user', '5', 'integer', '每个用户最多创建的API密钥数');
