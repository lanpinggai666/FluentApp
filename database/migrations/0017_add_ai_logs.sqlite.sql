-- v16 → v17：AI 调用日志
-- 之前 AI 外呼只在出错时 error_log 一句，没有耗时、没有 token 用量、没有「切了几个线路」的记录，
-- 计费与「AI 突然变笨」都无从对账。这张表按一次 HTTP 调用一行（容灾切换会写多行）。
-- 注意：SQLite 不支持列级 COMMENT，注释只写在 MySQL 版本里。
CREATE TABLE IF NOT EXISTS ai_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  provider VARCHAR(60) NOT NULL DEFAULT '',
  api_base VARCHAR(200) NOT NULL DEFAULT '',
  model VARCHAR(80) NOT NULL DEFAULT '',
  purpose VARCHAR(40) NOT NULL DEFAULT 'chat',
  http_status INTEGER NOT NULL DEFAULT 0,
  result VARCHAR(16) NOT NULL DEFAULT 'ok',
  latency_ms INTEGER NOT NULL DEFAULT 0,
  prompt_tokens INTEGER DEFAULT NULL,
  completion_tokens INTEGER DEFAULT NULL,
  total_tokens INTEGER DEFAULT NULL,
  detail VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ai_logs_time ON ai_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_ai_logs_purpose ON ai_logs(purpose, created_at);
