-- 蓝屏钙软件导航网 - SQLite 结构（与线上库对齐，可直接建库）
-- 由线上库 sqlite_master 导出；表与列的增减请同步 schema_mysql.sql 与 migrate_v*.php

CREATE TABLE IF NOT EXISTS categories (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  icon TEXT NOT NULL DEFAULT 'folder',
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS softwares (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  slug TEXT NOT NULL UNIQUE,
  icon_url TEXT NOT NULL,
  official_url TEXT NOT NULL,
  github_url TEXT,
  github_release_keyword TEXT DEFAULT 'x64',
  other_urls TEXT,
  category_id INTEGER NOT NULL DEFAULT 1,
  summary TEXT,
  special_notes TEXT,
  is_ai_generated INTEGER NOT NULL DEFAULT 0,
  ai_generated_fields TEXT,
  ai_sources TEXT,
  status TEXT NOT NULL DEFAULT 'published',
  rating_avg REAL NOT NULL DEFAULT 5.0,
  rating_count INTEGER NOT NULL DEFAULT 0,
  views_count INTEGER NOT NULL DEFAULT 0,
  download_clicks INTEGER NOT NULL DEFAULT 0,
  is_featured INTEGER NOT NULL DEFAULT 0,
  submitter_ip TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
, price_type VARCHAR(20) DEFAULT 'free', is_open_source TINYINT(1) DEFAULT 1, license VARCHAR(50) DEFAULT '开源', developer VARCHAR(100) DEFAULT NULL, publisher VARCHAR(100) DEFAULT NULL, tags VARCHAR(500) DEFAULT NULL, screenshot_url VARCHAR(500) DEFAULT NULL, price_desc VARCHAR(100), install_type VARCHAR(100) DEFAULT NULL, supported_languages VARCHAR(100) DEFAULT NULL, os_requirements VARCHAR(150) DEFAULT NULL, theme_color VARCHAR(50) DEFAULT NULL, submitter_email TEXT, review_note TEXT, reviewed_at DATETIME, edit_token_hash TEXT, edit_token_expires_at DATETIME, notification_status TEXT NOT NULL DEFAULT 'pending', notification_error TEXT, notified_at DATETIME, description_md TEXT, owner_user_id INTEGER DEFAULT NULL);

CREATE TABLE IF NOT EXISTS comments (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  software_id INTEGER NOT NULL,
  nickname TEXT NOT NULL DEFAULT '访客',
  rating INTEGER NOT NULL DEFAULT 5,
  content TEXT NOT NULL,
  ip_address TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'approved',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, title VARCHAR(100) DEFAULT NULL, likes INTEGER DEFAULT 0, dislikes INTEGER DEFAULT 0, user_id INTEGER DEFAULT NULL);

CREATE TABLE IF NOT EXISTS reports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  software_id INTEGER NOT NULL,
  reason TEXT NOT NULL,
  details TEXT,
  contact TEXT,
  ip_address TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, email TEXT, notification_status TEXT NOT NULL DEFAULT 'pending', notification_error TEXT, notified_at DATETIME, review_reason TEXT, resource_id INTEGER DEFAULT NULL, reported_type TEXT DEFAULT 'software', user_id INTEGER DEFAULT NULL);

CREATE TABLE IF NOT EXISTS github_mirrors (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  name TEXT NOT NULL,
  url_template TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, type VARCHAR(20) DEFAULT 'download', sample_url TEXT DEFAULT NULL, url_style VARCHAR(12) NOT NULL DEFAULT '', sample_original VARCHAR(500) DEFAULT NULL);

CREATE TABLE IF NOT EXISTS notices (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  title TEXT NOT NULL,
  content TEXT NOT NULL,
  is_active INTEGER NOT NULL DEFAULT 1,
  sort_order INTEGER NOT NULL DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS settings (
  key_name TEXT PRIMARY KEY,
  key_value TEXT,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sensitive_words (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  word TEXT NOT NULL UNIQUE,
  replacement TEXT NOT NULL DEFAULT '***',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS software_resources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    software_id INTEGER NOT NULL,
    type VARCHAR(32) NOT NULL DEFAULT 'video',
    title VARCHAR(255) NOT NULL,
    url TEXT NOT NULL,
    author VARCHAR(64) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    submitter_ip VARCHAR(45) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'published',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
, user_id INTEGER DEFAULT NULL);

CREATE TABLE IF NOT EXISTS software_corrections (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  software_id INTEGER NOT NULL,
  summary TEXT,
  special_notes TEXT,
  official_url TEXT,
  github_url TEXT,
  developer TEXT,
  tags TEXT,
  download_channels TEXT,
  contact TEXT,
  submitter_ip TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  review_note TEXT,
  reviewed_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
, name TEXT, icon_url TEXT, github_release_keyword TEXT, category_id INTEGER, price_type TEXT, is_open_source INTEGER, license TEXT, publisher TEXT, screenshot_url TEXT, submitter_email TEXT, edit_token_hash TEXT, edit_token_expires_at DATETIME, notification_status TEXT NOT NULL DEFAULT 'pending', notification_error TEXT, notified_at DATETIME, description_md TEXT, user_id INTEGER DEFAULT NULL);

CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  username TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT,
  avatar_url TEXT DEFAULT '',
  role TEXT NOT NULL DEFAULT 'user',
  status TEXT NOT NULL DEFAULT 'active',
  last_login_at DATETIME,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
, badges TEXT DEFAULT NULL, is_banned INTEGER DEFAULT 0, banned_until DATETIME DEFAULT NULL, ban_reason TEXT DEFAULT NULL, restricted_permissions TEXT DEFAULT NULL);

CREATE TABLE IF NOT EXISTS oauth_accounts (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id INTEGER NOT NULL,
  provider TEXT NOT NULL,
  provider_user_id TEXT NOT NULL,
  provider_email TEXT,
  provider_name TEXT,
  provider_avatar TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(provider, provider_user_id)
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    type VARCHAR(50) DEFAULT 'system',
    is_read TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS email_verification_codes (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    action VARCHAR(50) NOT NULL,
    expires_at DATETIME NOT NULL,
    is_used TINYINT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 索引（查询热点：状态过滤、分类、归属人、令牌查找）
CREATE INDEX IF NOT EXISTS idx_comments_software ON comments(software_id, status);
CREATE INDEX IF NOT EXISTS idx_email_codes_lookup ON email_verification_codes(email, action, is_used);
CREATE INDEX IF NOT EXISTS idx_notifications_user_read ON notifications(user_id, is_read);
CREATE INDEX IF NOT EXISTS idx_oauth_user_id ON oauth_accounts(user_id);
CREATE INDEX IF NOT EXISTS idx_corrections_edit_token ON software_corrections(edit_token_hash, edit_token_expires_at);
CREATE INDEX IF NOT EXISTS idx_software_corrections_software ON software_corrections(software_id, status);
CREATE INDEX IF NOT EXISTS idx_software_corrections_status ON software_corrections(status, created_at);
CREATE INDEX IF NOT EXISTS idx_resources_software ON software_resources(software_id);
CREATE INDEX IF NOT EXISTS idx_softwares_category ON softwares(status, category_id);
CREATE INDEX IF NOT EXISTS idx_softwares_edit_token ON softwares(edit_token_hash, edit_token_expires_at);
CREATE INDEX IF NOT EXISTS idx_softwares_status ON softwares(status, is_featured, created_at);
CREATE INDEX IF NOT EXISTS idx_softwares_views ON softwares(status, views_count);
CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
CREATE INDEX IF NOT EXISTS idx_users_status ON users(status);
CREATE INDEX IF NOT EXISTS idx_softwares_owner ON softwares(owner_user_id, status);
CREATE INDEX IF NOT EXISTS idx_softwares_category_only ON softwares(category_id);
CREATE INDEX IF NOT EXISTS idx_comments_user ON comments(user_id);
CREATE INDEX IF NOT EXISTS idx_reports_status ON reports(status, created_at);
CREATE INDEX IF NOT EXISTS idx_reports_resource ON reports(resource_id);
CREATE INDEX IF NOT EXISTS idx_reports_user ON reports(user_id);
CREATE INDEX IF NOT EXISTS idx_reports_software ON reports(software_id, status);
CREATE INDEX IF NOT EXISTS idx_resources_user ON software_resources(user_id, status);

-- 结构版本表：database/migrate.php 用它做前向升级，记录已经跑过哪些迁移
CREATE TABLE IF NOT EXISTS schema_migrations (
  version VARCHAR(64) PRIMARY KEY,
  name TEXT NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- 开放 API 的访问密钥：只存哈希与识别前缀，明文密钥不落库、查库也还原不出来
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

-- 每次调用记一行：密钥泄露时靠它回溯是谁、什么时候、动了哪些端点
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
  tool_rounds INTEGER NOT NULL DEFAULT 0,
  tool_calls INTEGER NOT NULL DEFAULT 0,
  detail VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX IF NOT EXISTS idx_ai_logs_time ON ai_logs(created_at);
CREATE INDEX IF NOT EXISTS idx_ai_logs_purpose ON ai_logs(purpose, created_at);

