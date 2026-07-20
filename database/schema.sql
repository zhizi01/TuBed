-- TuBed 图床系统 MySQL 8 初始化脚本
-- 生产环境建议先创建专用数据库账号，再以该账号执行建表语句。

CREATE DATABASE IF NOT EXISTS `tubed`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_0900_ai_ci;

USE `tubed`;

CREATE TABLE IF NOT EXISTS `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
    `username` VARCHAR(32) NOT NULL COMMENT '用户名',
    `email` VARCHAR(190) NULL COMMENT '邮箱',
    `password_hash` VARCHAR(255) NOT NULL COMMENT '密码哈希',
    `role` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT '角色：admin/user',
    `status` TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '状态：1正常，0禁用',
    `storage_quota` BIGINT UNSIGNED NOT NULL DEFAULT 5368709120 COMMENT '存储配额（字节）',
    `storage_used` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '已用空间（字节）',
    `last_login_at` DATETIME NULL COMMENT '最后登录时间',
    `last_login_ip` VARCHAR(45) NULL COMMENT '最后登录IP',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_username` (`username`),
    UNIQUE KEY `uk_users_email` (`email`),
    KEY `idx_users_status` (`status`)
) ENGINE=InnoDB COMMENT='用户表';

CREATE TABLE IF NOT EXISTS `user_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '令牌ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '用户ID',
    `name` VARCHAR(60) NOT NULL DEFAULT 'web' COMMENT '令牌用途',
    `token_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'SHA-256令牌摘要',
    `last_used_at` DATETIME NULL COMMENT '最后使用时间',
    `expires_at` DATETIME NOT NULL COMMENT '过期时间',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_user_tokens_hash` (`token_hash`),
    KEY `idx_user_tokens_user` (`user_id`),
    KEY `idx_user_tokens_expires` (`expires_at`),
    CONSTRAINT `fk_user_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='访问令牌表';

CREATE TABLE IF NOT EXISTS `albums` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '相册ID',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '所属用户ID',
    `name` VARCHAR(80) NOT NULL COMMENT '相册名称',
    `description` VARCHAR(500) NULL COMMENT '相册说明',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_albums_user_name` (`user_id`, `name`),
    KEY `idx_albums_user_created` (`user_id`, `created_at`),
    CONSTRAINT `fk_albums_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='相册表';

CREATE TABLE IF NOT EXISTS `images` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '图片ID',
    `public_id` CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '公开图片标识',
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT '所属用户ID',
    `album_id` BIGINT UNSIGNED NULL COMMENT '所属相册ID',
    `title` VARCHAR(100) NULL COMMENT '图片标题',
    `original_name` VARCHAR(255) NOT NULL COMMENT '上传时的原文件名',
    `storage_disk` VARCHAR(32) NOT NULL DEFAULT 'public' COMMENT '存储磁盘',
    `storage_path` VARCHAR(500) NOT NULL COMMENT '相对存储路径',
    `mime_type` VARCHAR(100) NOT NULL COMMENT '服务端检测的MIME',
    `extension` VARCHAR(10) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL COMMENT '规范扩展名',
    `file_size` BIGINT UNSIGNED NOT NULL COMMENT '文件大小（字节）',
    `width` INT UNSIGNED NOT NULL COMMENT '图片宽度',
    `height` INT UNSIGNED NOT NULL COMMENT '图片高度',
    `sha256` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT '文件SHA-256',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` DATETIME NULL COMMENT '软删除时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_images_public_id` (`public_id`),
    UNIQUE KEY `uk_images_storage_path` (`storage_path`),
    KEY `idx_images_user_created` (`user_id`, `created_at`),
    KEY `idx_images_album_created` (`album_id`, `created_at`),
    KEY `idx_images_user_sha256` (`user_id`, `sha256`),
    KEY `idx_images_deleted_at` (`deleted_at`),
    CONSTRAINT `fk_images_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_images_album`
        FOREIGN KEY (`album_id`) REFERENCES `albums` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB COMMENT='图片元数据表';
