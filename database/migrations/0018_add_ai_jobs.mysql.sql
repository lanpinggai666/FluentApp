-- v18：AI 补全从「请求里同步跑」改成「提交后入队、异步跑」，并给 AI 加搜索/开网页工具的记录位。
-- 与 schema_mysql.sql 的最终结构保持一致；software_id 不建外键，删软件时不该被日志型表拖住。

CREATE TABLE IF NOT EXISTS `ai_jobs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `software_id` BIGINT UNSIGNED NOT NULL COMMENT '要补全的软件',
  `purpose` VARCHAR(40) NOT NULL DEFAULT 'submit_enrich' COMMENT 'submit_enrich/approve_ai/manual_retry',
  `status` VARCHAR(16) NOT NULL DEFAULT 'pending' COMMENT 'pending/running/done/failed',
  `attempts` SMALLINT NOT NULL DEFAULT 0 COMMENT '已执行的轮数',
  `source` VARCHAR(16) NOT NULL DEFAULT '' COMMENT '结果来源：ai/offline/none',
  `claim_token` VARCHAR(32) DEFAULT NULL COMMENT '认领标记，防两个 worker 抢同一条',
  `claimed_at` DATETIME DEFAULT NULL,
  `finished_at` DATETIME DEFAULT NULL,
  `error` VARCHAR(255) DEFAULT NULL COMMENT '失败摘要，绝不含密钥',
  `filled_fields` VARCHAR(255) DEFAULT NULL COMMENT '本次补上的字段 JSON',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ai_jobs_status` (`status`, `created_at`),
  KEY `idx_ai_jobs_software` (`software_id`, `id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='AI 补全任务队列';

ALTER TABLE `softwares` ADD COLUMN `ai_sources` TEXT NULL DEFAULT NULL COMMENT 'AI 生成时引用的联网来源 JSON';
ALTER TABLE `ai_logs` ADD COLUMN `tool_rounds` SMALLINT NOT NULL DEFAULT 0 COMMENT '工具调用轮数';
ALTER TABLE `ai_logs` ADD COLUMN `tool_calls` SMALLINT NOT NULL DEFAULT 0 COMMENT '工具调用次数';
