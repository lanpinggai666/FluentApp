# 前向迁移目录

这里的每个 `<编号>_<说明>.sql` 描述**从一个结构版本升到下一个版本**的变更，由 `php database/migrate.php` 按编号从小到大、每个版本只执行一次地应用（已应用的版本记在 `schema_migrations` 表里）。

当前随代码发布的基线见 `database/version.php`（v14）。**新编号必须大于基线号**，否则新装的库会去补一段结构里已经有的变更。

## 命名

| 文件名 | 何时用 |
| --- | --- |
| `0015_add_size_hint.sql` | 两个方言都能直接跑的语句（加可空列等） |
| `0015_add_size_hint.sqlite.sql` | 只有 SQLite 能跑的写法（如 `CREATE INDEX IF NOT EXISTS`） |
| `0015_add_size_hint.mysql.sql` | 只有 MySQL 能跑的写法（如带 `ALGORITHM=INPLACE` 的变更） |

同一版本的通用文件先执行，方言专属文件后执行；跑在别的驱动上时专属文件会被忽略。

## 加一条迁移怎么做

假设要给软件表加一列 `size_hint`：

1. 同时改 `database/schema_sqlite.sql` 与 `database/schema_mysql.sql` —— 新库直接建最终结构，两份必须同构（`tests/cases/09_schema_test.php` 会比对表名与列集合）。
2. 把 `database/version.php` 的基线号 +1（这里改成 `15`）。
3. 新建 `database/migrations/0015_add_size_hint.sql`，内容只写「从 14 到 15」的增量：

   ```sql
   ALTER TABLE softwares ADD COLUMN size_hint VARCHAR(64);
   ```

   MySQL 8 的 `ADD COLUMN` 语法相同，所以一个通用文件就够；若某步两边写法不同，就拆成 `.sqlite.sql` 与 `.mysql.sql` 两份同编号文件。
4. 本地验证：

   ```bash
   php database/migrate.php --dry-run   # 看将要执行什么
   php database/migrate.php             # 在测试库上真的跑一次
   php tests/run.php                    # 全量回归
   ```

## 约定

- 一个文件只做一件事，方便排查与回滚。
- 含 `DROP TABLE` / `DROP COLUMN` / `TRUNCATE` / `RENAME TABLE` 的迁移，执行器会要求 `--yes`，因为不可回滚。
- 迁移只描述**结构**，不要往里塞业务数据；基础数据写进 `database/seed.sql`（新库用）。
- 已经发布过的迁移文件**不要改、不要改名**，那会让线上库的版本记录对不上。要修正就加一条新编号的迁移。
- 万一某条迁移在别的机器上已经人工执行过，用 `php database/migrate.php --stamp=15` 只登记版本、不执行。
- v13 及更早的一次性脚本在 `database/legacy/`，只给老库补齐用，新库不要跑。
