-- v14 → v15：开放 API 的密钥表与调用日志表（MySQL）
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
