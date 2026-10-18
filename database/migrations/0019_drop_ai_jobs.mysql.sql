-- v19：撤掉 AI 补全队列。补全改回「审核通过时当场生成」，不再有待办表、worker 与响应内异步。
-- DROP TABLE 不可回滚（MySQL 的 DDL 还会隐式提交，失败点之前的部分不会回退）：
-- 先 mysqldump 备份，再带 --yes 执行，或在后台【结构升级】页勾上确认框。
-- 表里未跑完的待办会一起消失，但不影响数据本身：下一次审核通过或后台点「重新生成」时会当场补全。
-- softwares.ai_sources 与 ai_logs 的 tool_rounds/tool_calls 保留，它们服务的是联网搜索与工具调用。

DROP TABLE IF EXISTS `ai_jobs`;

DELETE FROM `settings` WHERE `key_name` IN ('ai_async_inline', 'ai_job_attempts');
