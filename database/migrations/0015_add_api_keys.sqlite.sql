-- v14 → v15：开放 API 的密钥表与调用日志表（SQLite）
CREATE TABLE IF NOT EXISTS api_keys (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name VARCHAR(64) NOT NULL,
  key_prefix VARCHAR(16) NOT NULL,
  key_hash CHAR(64) NOT NULL UNIQUE,
  scopes VARCHAR(120) NOT NULL DEFAULT 'read',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(64),
  last_used_at DATETIME,
  revoked_at DATETIME
);

CREATE TABLE IF NOT EXISTS api_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  api_key_id INTEGER,
  key_prefix VARCHAR(16),
  endpoint VARCHAR(120) NOT NULL,
  method VARCHAR(10) NOT NULL DEFAULT 'GET',
  http_status INTEGER NOT NULL DEFAULT 200,
  result VARCHAR(16) NOT NULL DEFAULT 'ok',
  detail VARCHAR(255),
  ip VARCHAR(45),
  user_agent VARCHAR(200),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_api_keys_prefix ON api_keys(key_prefix);
CREATE INDEX IF NOT EXISTS idx_api_logs_key_time ON api_logs(api_key_id, created_at);
CREATE INDEX IF NOT EXISTS idx_api_logs_time ON api_logs(created_at);
