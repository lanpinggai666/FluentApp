-- v16 → v17：AI 调用日志
-- 之前 AI 外呼只在出错时 error_log 一句，没有耗时、没有 token 用量、没有「切了几个线路」的记录，
-- 计费与「AI 突然变笨」都无从对账。这张表按一次 HTTP 调用一行（容灾切换会写多行）。
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
  `detail` VARCHAR(255) DEFAULT NULL COMMENT '错误摘要，绝不含密钥',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_logs_time` (`created_at`),
  KEY `idx_ai_logs_purpose` (`purpose`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI 调用日志';
