-- v18：AI 补全从「请求里同步跑」改成「提交后入队、异步跑」，并给 AI 加搜索/开网页工具的记录位。
-- SQLite 方言：列级 COMMENT 只有 MySQL 支持，这里一律不写。

CREATE TABLE IF NOT EXISTS ai_jobs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  software_id INTEGER NOT NULL,
  purpose VARCHAR(40) NOT NULL DEFAULT 'submit_enrich',
  status VARCHAR(16) NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  source VARCHAR(16) NOT NULL DEFAULT '',
  claim_token VARCHAR(32) DEFAULT NULL,
  claimed_at DATETIME DEFAULT NULL,
  finished_at DATETIME DEFAULT NULL,
  error VARCHAR(255) DEFAULT NULL,
  filled_fields VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ai_jobs_status ON ai_jobs(status, created_at);
CREATE INDEX IF NOT EXISTS idx_ai_jobs_software ON ai_jobs(software_id, id);

ALTER TABLE softwares ADD COLUMN ai_sources TEXT;
ALTER TABLE ai_logs ADD COLUMN tool_rounds INTEGER NOT NULL DEFAULT 0;
ALTER TABLE ai_logs ADD COLUMN tool_calls INTEGER NOT NULL DEFAULT 0;
