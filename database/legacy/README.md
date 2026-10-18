# 历史迁移脚本（只用于升级老库）

这里的 `migrate_v2.php … migrate_v13.php`、`migrate_users.php` 是 v13 之前逐步累积的一次性结构变更脚本。
**全新安装不要跑它们** —— `database/schema_sqlite.sql` / `database/schema_mysql.sql` 已经是 v13 的完整结构。

只有当线上库比 v13 更老、且没有 `schema_migrations` 表时，才按版本号从小到大依次执行：

```bash
for f in database/legacy/migrate_v2.php database/legacy/migrate_v3.php database/legacy/migrate_v4.php \
         database/legacy/migrate_v5.php database/legacy/migrate_v6.php database/legacy/migrate_v7.php \
         database/legacy/migrate_v8.php database/legacy/migrate_v9.php database/legacy/migrate_v10.php \
         database/legacy/migrate_v11.php database/legacy/migrate_v12.php database/legacy/migrate_v13.php; do
    php "$f" || break
done
php database/migrate.php --stamp=baseline   # 打完基线后，以后的升级走 migrations/
```

执行前务必备份数据库。这些脚本按当时的线上结构写成，部分依赖列已存在的假设，跨大版本跳级可能失败。
