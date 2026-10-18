-- 蓝屏钙软件导航网 - MySQL 数据库结构
-- 针对庞大软件索引设计：高效率索引，InnoDB 引擎，utf8mb4 编码

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- 1. 分类表
CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '分类名称',
  `slug` VARCHAR(50) NOT NULL COMMENT '别名/英文标识',
  `icon` VARCHAR(50) NOT NULL DEFAULT 'folder' COMMENT 'Fluent Icon名称',
  `sort_order` INT NOT NULL DEFAULT 0 COMMENT '排序权重',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='软件分类表';

-- 2. 软件主表 (重点索引优化)
CREATE TABLE IF NOT EXISTS `softwares` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT '软件名称',
  `slug` VARCHAR(120) NOT NULL COMMENT 'URL别名',
  `icon_url` VARCHAR(500) NOT NULL COMMENT '图标URL',
  `official_url` VARCHAR(500) NOT NULL COMMENT '官网地址',
  `github_url` VARCHAR(500) NULL DEFAULT NULL COMMENT 'GitHub仓库地址',
  `github_release_keyword` VARCHAR(100) NULL DEFAULT 'x64' COMMENT 'Release文件名过滤关键字',
  `other_urls` TEXT NULL COMMENT '其它渠道JSON [{name,url}]',
  `category_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '分类ID',
  `summary` VARCHAR(300) NULL DEFAULT NULL COMMENT '软件精炼简介',
  `special_notes` TEXT NULL COMMENT '特别说明/注意事项(一句话纯文本)',
  `description_md` TEXT NULL COMMENT '软件说明(Markdown 长文)',
  `price_type` ENUM('free', 'paid', 'freemium') NOT NULL DEFAULT 'free' COMMENT '收费类型: free免费, paid收费, freemium免费增值',
  `is_open_source` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否开源: 1是, 0否',
  `license` VARCHAR(50) NULL DEFAULT '开源' COMMENT '许可协议',
  `developer` VARCHAR(100) NULL DEFAULT NULL COMMENT '开发者',
  `publisher` VARCHAR(100) NULL DEFAULT NULL COMMENT '发布者',
  `tags` VARCHAR(500) NULL DEFAULT NULL COMMENT '标签列表逗号分隔',
  `screenshot_url` VARCHAR(500) NULL DEFAULT NULL COMMENT '实机截图链接',
  `theme_color` VARCHAR(50) NULL DEFAULT NULL COMMENT '从软件图标取样的代表色 Hex',
  `is_ai_generated` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否由AI补全生成',
  `ai_generated_fields` VARCHAR(255) NULL DEFAULT NULL COMMENT 'AI生成的字段列表JSON',
  `ai_sources` TEXT NULL DEFAULT NULL COMMENT 'AI 生成时引用的联网来源 JSON',
  `status` ENUM('pending', 'published', 'rejected', 'offline') NOT NULL DEFAULT 'published' COMMENT '审核状态 pending待审/published已发布/rejected已驳回/offline已下架',
  `rating_avg` DECIMAL(3,2) NOT NULL DEFAULT 5.00 COMMENT '平均评分1-5',
  `rating_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '评分总次数',
  `views_count` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览次数',
  `download_clicks` BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '下载点击数',
  `is_featured` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否推荐',
  `submitter_ip` VARCHAR(45) NULL DEFAULT NULL COMMENT '提交者IP',
  `submitter_email` VARCHAR(254) NULL DEFAULT NULL COMMENT '提交者邮箱',
  `owner_user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '软件归属账号，NULL 表示由游客提交',
  `price_desc` VARCHAR(200) NULL DEFAULT NULL COMMENT '收费方式与价格说明',
  `install_type` VARCHAR(100) NULL DEFAULT NULL COMMENT '安装方式',
  `supported_languages` VARCHAR(200) NULL DEFAULT NULL COMMENT '支持语言',
  `os_requirements` VARCHAR(255) NULL DEFAULT NULL COMMENT '系统要求',
  `review_note` VARCHAR(500) NULL DEFAULT NULL COMMENT '审核说明（驳回理由等）',
  `reviewed_at` DATETIME NULL DEFAULT NULL COMMENT '最近一次审核时间',
  `edit_token_hash` CHAR(64) NULL DEFAULT NULL COMMENT '一次性修改链接令牌的 SHA-256 摘要',
  `edit_token_expires_at` DATETIME NULL DEFAULT NULL COMMENT '修改链接过期时间',
  `notification_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '审核结果通知发送状态',
  `notification_error` TEXT NULL COMMENT '通知发送失败原因',
  `notified_at` DATETIME NULL DEFAULT NULL COMMENT '通知发送时间',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_status_featured_created` (`status`, `is_featured`, `created_at`),
  KEY `idx_status_category` (`status`, `category_id`),
  KEY `idx_status_views` (`status`, `views_count`),
  KEY `idx_status_rating` (`status`, `rating_avg`),
  KEY `idx_owner_status` (`owner_user_id`, `status`),
  KEY `idx_category` (`category_id`),
  KEY `idx_edit_token` (`edit_token_hash`, `edit_token_expires_at`),
  FULLTEXT KEY `ft_name_summary` (`name`, `summary`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='软件信息表';

-- 3. 评价/评论表
CREATE TABLE IF NOT EXISTS `comments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL,
  `nickname` VARCHAR(50) NOT NULL DEFAULT '访客',
  `rating` TINYINT UNSIGNED NOT NULL DEFAULT 5 COMMENT '评分1-5',
  `content` VARCHAR(600) NOT NULL COMMENT '评价内容',
  `title` VARCHAR(100) NULL DEFAULT NULL COMMENT '评价标题',
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '登录用户发表的评价，NULL 为游客',
  `likes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '有用数',
  `dislikes` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '没用数',
  `ip_address` VARCHAR(45) NOT NULL,
  `status` ENUM('approved', 'pending', 'spam') NOT NULL DEFAULT 'approved',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_software_status_created` (`software_id`, `status`, `created_at`),
  KEY `idx_comments_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='软件评价表';

-- 4. 举报反馈表
CREATE TABLE IF NOT EXISTS `reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL,
  `resource_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '针对某条下载渠道时指向 software_resources.id',
  `reported_type` VARCHAR(20) NOT NULL DEFAULT 'software' COMMENT '举报对象类型: software/resource',
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '登录用户提交的举报，NULL 为游客',
  `reason` VARCHAR(50) NOT NULL COMMENT '举报理由',
  `details` VARCHAR(500) NULL DEFAULT NULL COMMENT '详细说明',
  `email` VARCHAR(254) NULL DEFAULT NULL COMMENT '处理结果邮箱',
  `contact` VARCHAR(100) NULL DEFAULT NULL COMMENT '其它联系方式',
  `ip_address` VARCHAR(45) NOT NULL,
  `status` ENUM('pending', 'resolved', 'dismissed', 'rejected') NOT NULL DEFAULT 'pending',
  `review_reason` VARCHAR(500) NULL DEFAULT NULL COMMENT '处理时写给举报人的说明',
  `notification_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `notification_error` TEXT NULL,
  `notified_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_reports_status_created` (`status`, `created_at`),
  KEY `idx_reports_resource` (`resource_id`),
  KEY `idx_reports_user` (`user_id`),
  KEY `idx_reports_software_status` (`software_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='举报反馈表';

-- 5. GitHub 镜像配置表
CREATE TABLE IF NOT EXISTS `github_mirrors` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '节点名称',
  `url_template` VARCHAR(255) NOT NULL COMMENT '替换前缀或模板，如 https://ghproxy.net/',
  `type` VARCHAR(20) NOT NULL DEFAULT 'download' COMMENT '接管类型: download直链反代 / site网页替换域名',
  `sample_url` VARCHAR(500) NULL DEFAULT NULL COMMENT '识别时留下的真实加速样例',
  `url_style` VARCHAR(12) NOT NULL DEFAULT '' COMMENT '学到的映射方式：replace/prefix/direct，空=按域名清单猜',
  `sample_original` VARCHAR(500) DEFAULT NULL COMMENT '学习这条映射时用的原始 GitHub 链接',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='GitHub镜像源表';

-- 6. 公告表
CREATE TABLE IF NOT EXISTS `notices` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `title` VARCHAR(150) NOT NULL COMMENT '公告标题',
  `content` TEXT NOT NULL COMMENT '公告详情内容',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_active_sort` (`is_active`, `sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='网站公告表';

-- 7. 全局设置表 (KV存储)
CREATE TABLE IF NOT EXISTS `settings` (
  `key_name` VARCHAR(60) NOT NULL,
  `key_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表';

-- 8. 敏感词违禁词库
CREATE TABLE IF NOT EXISTS `sensitive_words` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `word` VARCHAR(100) NOT NULL,
  `replacement` VARCHAR(20) NOT NULL DEFAULT '***',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='敏感词过滤库';

SET FOREIGN_KEY_CHECKS = 1;


-- ----------------------------
-- 第三方扩展资源与视频教程表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `software_resources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL COMMENT '关联软件ID（必须与 softwares.id 同类型，否则 MySQL 拒绝建外键）',
  `type` VARCHAR(32) NOT NULL DEFAULT 'video' COMMENT '类型: video/article/config/patch',
  `title` VARCHAR(255) NOT NULL COMMENT '资源标题',
  `url` TEXT NOT NULL COMMENT '资源外链URL',
  `author` VARCHAR(64) DEFAULT NULL COMMENT '作者或平台UP主',
  `description` TEXT DEFAULT NULL COMMENT '简要说明',
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '提交人账号，NULL 为游客',
  `submitter_ip` VARCHAR(45) DEFAULT NULL,
  `status` ENUM('pending', 'published', 'rejected', 'reported') NOT NULL DEFAULT 'published' COMMENT 'reported 表示已被举报下架',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_software_id` (`software_id`),
  KEY `idx_resources_user` (`user_id`, `status`),
  CONSTRAINT `fk_resource_software` FOREIGN KEY (`software_id`) REFERENCES `softwares` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方扩展资源与教程表';

-- ----------------------------
-- 软件资料修改申请审核表
-- ----------------------------
CREATE TABLE IF NOT EXISTS `software_corrections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NULL DEFAULT NULL,
  `icon_url` VARCHAR(500) NULL DEFAULT NULL,
  `official_url` VARCHAR(500) NULL DEFAULT NULL,
  `github_url` VARCHAR(500) NULL DEFAULT NULL,
  `github_release_keyword` VARCHAR(100) NULL DEFAULT NULL,
  `category_id` INT UNSIGNED NULL DEFAULT NULL,
  `summary` VARCHAR(500) NULL DEFAULT NULL,
  `special_notes` TEXT NULL,
  `description_md` TEXT NULL,
  `price_type` ENUM('free', 'paid', 'freemium') NULL DEFAULT NULL,
  `is_open_source` TINYINT(1) NULL DEFAULT NULL,
  `license` VARCHAR(100) NULL DEFAULT NULL,
  `developer` VARCHAR(100) NULL DEFAULT NULL,
  `publisher` VARCHAR(100) NULL DEFAULT NULL,
  `tags` VARCHAR(500) NULL DEFAULT NULL,
  `screenshot_url` VARCHAR(500) NULL DEFAULT NULL,
  `download_channels` TEXT NULL,
  `contact` VARCHAR(200) NULL DEFAULT NULL,
  `submitter_email` VARCHAR(254) NULL DEFAULT NULL,
  `submitter_ip` VARCHAR(45) NOT NULL,
  `user_id` BIGINT UNSIGNED NULL DEFAULT NULL COMMENT '提交修改申请的账号，NULL 为游客',
  `status` ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  `review_note` VARCHAR(500) NULL DEFAULT NULL,
  `reviewed_at` DATETIME NULL DEFAULT NULL,
  `edit_token_hash` CHAR(64) NULL DEFAULT NULL,
  `edit_token_expires_at` DATETIME NULL DEFAULT NULL,
  `notification_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
  `notification_error` TEXT NULL,
  `notified_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_created` (`status`, `created_at`),
  KEY `idx_software_status` (`software_id`, `status`),
  KEY `idx_corrections_edit_token` (`edit_token_hash`, `edit_token_expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户提交的软件补充信息审核表';

-- ----------------------------
-- 账号体系（与 schema_sqlite.sql 同构；新增表都放在这里，避免改动上面已有表序）
-- ----------------------------

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `email` VARCHAR(254) NOT NULL,
  `password_hash` VARCHAR(255) NULL DEFAULT NULL COMMENT '第三方登录账号可为空',
  `avatar_url` VARCHAR(500) NOT NULL DEFAULT '',
  `role` VARCHAR(20) NOT NULL DEFAULT 'user' COMMENT 'user / admin',
  `status` VARCHAR(20) NOT NULL DEFAULT 'active' COMMENT 'active / banned / pending',
  `badges` TEXT NULL COMMENT '荣誉徽章 JSON 数组',
  `is_banned` TINYINT(1) NOT NULL DEFAULT 0,
  `banned_until` DATETIME NULL DEFAULT NULL COMMENT 'NULL 且 is_banned=1 表示永久封禁',
  `ban_reason` VARCHAR(255) NULL DEFAULT NULL,
  `restricted_permissions` TEXT NULL COMMENT '受限权限项 JSON 数组',
  `last_login_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_email` (`email`),
  KEY `idx_users_username` (`username`),
  KEY `idx_users_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='登录用户表';

CREATE TABLE IF NOT EXISTS `oauth_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `provider` VARCHAR(20) NOT NULL COMMENT 'microsoft / google / github',
  `provider_user_id` VARCHAR(190) NOT NULL,
  `provider_email` VARCHAR(254) NULL DEFAULT NULL,
  `provider_name` VARCHAR(100) NULL DEFAULT NULL,
  `provider_avatar` VARCHAR(500) NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_provider_identity` (`provider`, `provider_user_id`),
  KEY `idx_oauth_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='第三方账号绑定表';

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `type` VARCHAR(50) NOT NULL DEFAULT 'system' COMMENT 'system / report / correction / punish',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifications_user_read` (`user_id`, `is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='站内信表';

CREATE TABLE IF NOT EXISTS `email_verification_codes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(254) NOT NULL,
  `code` VARCHAR(10) NOT NULL COMMENT '6 位数字，明文存储以便按值比对',
  `action` VARCHAR(50) NOT NULL COMMENT 'register / reset_pwd',
  `expires_at` DATETIME NOT NULL,
  `is_used` TINYINT NOT NULL DEFAULT 0 COMMENT '0未用 / 1已用 / 2被新码作废',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_email_codes_lookup` (`email`, `action`, `is_used`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='邮箱验证码表';

-- ----------------------------
-- 结构版本表：database/migrate.php 用它做前向升级
-- ----------------------------
CREATE TABLE IF NOT EXISTS `schema_migrations` (
  `version` VARCHAR(64) NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='数据库结构版本记录';

-- ----------------------------
-- 开放 API 密钥与调用日志
-- ----------------------------
CREATE TABLE IF NOT EXISTS `api_keys` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(64) NOT NULL COMMENT '用途备注，例如「安卓 App」',
  `key_prefix` VARCHAR(16) NOT NULL COMMENT '明文密钥的前若干位，仅用于人工识别',
  `key_hash` CHAR(64) NOT NULL COMMENT '密钥的 SHA-256，明文不落库',
  `scopes` VARCHAR(120) NOT NULL DEFAULT 'read' COMMENT '逗号分隔：read,write',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` VARCHAR(64) DEFAULT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL COMMENT '非 NULL 即已吊销',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_api_keys_hash` (`key_hash`),
  KEY `idx_api_keys_prefix` (`key_prefix`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放 API 访问密钥';

CREATE TABLE IF NOT EXISTS `api_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `api_key_id` INT UNSIGNED DEFAULT NULL,
  `key_prefix` VARCHAR(16) DEFAULT NULL,
  `endpoint` VARCHAR(120) NOT NULL,
  `method` VARCHAR(10) NOT NULL DEFAULT 'GET',
  `http_status` SMALLINT NOT NULL DEFAULT 200,
  `result` VARCHAR(16) NOT NULL DEFAULT 'ok' COMMENT 'ok/denied/error',
  `detail` VARCHAR(255) DEFAULT NULL,
  `ip` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(200) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_api_logs_key_time` (`api_key_id`, `created_at`),
  KEY `idx_api_logs_time` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开放 API 调用日志';

CREATE TABLE IF NOT EXISTS `ai_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `provider` VARCHAR(60) NOT NULL DEFAULT '' COMMENT '提供商名称',
  `api_base` VARCHAR(200) NOT NULL DEFAULT '',
  `model` VARCHAR(80) NOT NULL DEFAULT '',
  `purpose` VARCHAR(40) NOT NULL DEFAULT 'chat' COMMENT 'submit_enrich/approve_ai/test_provider',
  `http_status` SMALLINT NOT NULL DEFAULT 0,
  `result` VARCHAR(16) NOT NULL DEFAULT 'ok' COMMENT 'ok/error',
  `latency_ms` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '单次 HTTP 往返耗时',
  `prompt_tokens` INT UNSIGNED DEFAULT NULL,
  `completion_tokens` INT UNSIGNED DEFAULT NULL,
  `total_tokens` INT UNSIGNED DEFAULT NULL,
  `tool_rounds` SMALLINT NOT NULL DEFAULT 0 COMMENT '工具调用轮数',
  `tool_calls` SMALLINT NOT NULL DEFAULT 0 COMMENT '工具调用次数',
  `detail` VARCHAR(255) DEFAULT NULL COMMENT '错误摘要，绝不含密钥',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_logs_time` (`created_at`),
  KEY `idx_ai_logs_purpose` (`purpose`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI 调用日志';

