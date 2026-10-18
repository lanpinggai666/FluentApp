-- v19：撤掉 AI 补全队列。补全改回「审核通过时当场生成」，不再有待办表、worker 与响应内异步。
-- DROP TABLE 不可回滚：先备份数据库（SQLite 用 .backup 或连同 -wal 一起复制；MySQL 用 mysqldump），
-- 再带 --yes 执行（或在后台【结构升级】页勾上确认框）。
-- 表里未跑完的待办会一起消失，但不影响数据本身：下一次审核通过或后台点「重新生成」时会当场补全。
-- 属于本功能的两个软保留：softwares.ai_sources（AI 引用来源）与 ai_logs 的 tool_rounds/tool_calls 都留着，
-- 它们服务的是联网搜索与工具调用，不是队列。

DROP TABLE IF EXISTS ai_jobs;

-- 队列专属的两个开关已经没有读取方，顺手清掉，免得库里留着没人认识的键
DELETE FROM settings WHERE key_name IN ('ai_async_inline', 'ai_job_attempts');
