<?php
/**
 * 数据库结构升级执行器（CLI 的 database/migrate.php 与后台【结构升级】页共用这一份）。
 *
 * 为什么要抽出来：以前只有 CLI 入口，PHP 装在容器里就不好跑，只能让人手工贴 SQL ——
 * 手工执行最容易漏掉 schema_migrations 的登记，下一次迁移就会在错的基础上跑。
 *
 * 安全边界：执行的语句只来自仓库里的 database/migrations/*.sql，不接受任何外部输入；
 * 网页入口另外要求后台会话 + CSRF，且含 DROP/TRUNCATE 的迁移必须显式勾选确认。
 */

class Migrator
{
    /** @return array{driver:string,baseline:int} */
    public static function status(): array
    {
        return ['driver' => Database::getDriver(), 'baseline' => (int) self::baseline()];
    }

    public static function baseline(): int
    {
        return (int) require BASE_PATH . '/database/version.php';
    }

    /** 老库里没有这张表时先补上，否则执行器自己会读不到记录。 */
    public static function ensureTable(PDO $pdo): void
    {
        $sql = Database::getDriver() === 'sqlite'
            ? 'CREATE TABLE IF NOT EXISTS schema_migrations (
                 version VARCHAR(64) PRIMARY KEY,
                 name TEXT NOT NULL,
                 applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
               )'
            : 'CREATE TABLE IF NOT EXISTS `schema_migrations` (
                 `version` VARCHAR(64) NOT NULL,
                 `name` VARCHAR(255) NOT NULL,
                 `applied_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                 PRIMARY KEY (`version`)
               ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
        $pdo->exec($sql);
    }

    /** @return array<string,string> version => name */
    public static function readApplied(PDO $pdo): array
    {
        $rows = $pdo->query('SELECT version, name FROM schema_migrations ORDER BY version')->fetchAll();
        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['version']] = (string) $row['name'];
        }
        return $out;
    }

    /**
     * 待执行迁移：version => 该版本要跑的文件列表（先通用后方言专属）。
     * @return array<int,array<int,string>>
     */
    public static function pending(PDO $pdo, array &$skipped = []): array
    {
        $applied = self::readApplied($pdo);
        $driver = Database::getDriver();
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        $collected = [];
        $skipped = [];

        foreach ($files as $file) {
            if (!preg_match('/^(\d+)_([A-Za-z0-9_-]+)(?:\.(sqlite|mysql))?\.sql$/', basename((string) $file), $m)) {
                // 命名不合规的文件只报告，不猜它该在哪个版本执行
                $skipped[] = basename((string) $file);
                continue;
            }
            $version = (int) $m[1];
            $dialect = $m[3] ?? '';
            if (isset($applied[(string) $version]) || ($dialect !== '' && $dialect !== $driver)) {
                continue;
            }
            $collected[$version][$dialect === '' ? 'any' : $dialect][] = (string) $file;
        }

        $pending = [];
        foreach ($collected as $version => $groups) {
            $pending[$version] = array_merge($groups['any'] ?? [], $groups[$driver] ?? []);
        }
        ksort($pending, SORT_NUMERIC);
        return $pending;
    }

    /** @return array<string,string> 文件路径 => SQL 正文 */
    public static function statements(array $files): array
    {
        $out = [];
        foreach ($files as $file) {
            $out[(string) $file] = (string) file_get_contents((string) $file);
        }
        return $out;
    }

    public static function label(array $statements): string
    {
        return implode(' + ', array_map('basename', array_keys($statements)));
    }

    public static function isDestructive(string $sql): bool
    {
        return (bool) preg_match('/\b(drop\s+table|drop\s+column|truncate|rename\s+table)\b/i', $sql);
    }

    /**
     * @param array<string,string> $statements
     * @return array{ok:bool,error:?string}
     */
    public static function apply(PDO $pdo, int $version, string $names, array $statements): array
    {
        $inTransaction = Database::getDriver() === 'sqlite';
        if ($inTransaction) {
            $pdo->beginTransaction();
        }
        try {
            foreach ($statements as $sql) {
                $pdo->exec($sql);
            }
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, ?)');
            $stmt->execute([(string) $version, $names, date('Y-m-d H:i:s')]);
            if ($inTransaction) {
                $pdo->commit();
            }
            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            if ($inTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /** 只登记版本号、不改表。用于「结构我已经手工对齐了」的情况。 */
    public static function stamp(PDO $pdo, string $requested): array
    {
        $version = $requested === 'baseline' ? (string) self::baseline() : trim($requested);
        if (!preg_match('/^\d+$/', $version)) {
            return ['ok' => false, 'error' => '只接受数字版本号或 baseline（当前: ' . $requested . '）'];
        }
        if (isset(self::readApplied($pdo)[$version])) {
            return ['ok' => true, 'error' => null, 'note' => "v{$version} 已在记录里，无需重复登记"];
        }
        $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, ?)');
        $stmt->execute([$version, '人工登记', date('Y-m-d H:i:s')]);
        return ['ok' => true, 'error' => null, 'note' => "已把当前结构登记为 v{$version}（只写记录，没改任何表）"];
    }

    /**
     * 把安装锁里的结构版本对齐到当前基线。
     * @return ?string 给用户看的一行说明（容器里代码目录只读时也只是提示，不算失败）
     */
    public static function syncLock(PDO $pdo, bool $dryRun = false): ?string
    {
        if ($dryRun) {
            return null;
        }
        $lock = BASE_PATH . Installer::LOCK_FILE;
        if (!is_file($lock)) {
            return '没有 config/install.lock（网页向导靠「库里已有数据」也会自锁），跳过版本同步。';
        }
        $meta = json_decode(trim((string) preg_replace('/^<\?php exit; \?>/', '', (string) file_get_contents($lock))), true);
        // 锁里要记的是「这个库现在到第几版」，不是「代码支持到第几版」。
        // 按代码基线写会出错：用 DB_SQLITE_PATH 把迁移打到另一个库（副本、另一套环境）时，
        // 项目自己的 install.lock 会被抬成一个它从没到过的版本，体检反而被误导。
        $versions = [];
        foreach (self::readApplied($pdo) as $version => $name) {
            if (preg_match('/^\d+$/', (string) $version)) {
                $versions[] = (int) $version;
            }
        }
        $baseline = $versions === [] ? (int) self::baseline() : max($versions);
        if (!is_array($meta) || (int) ($meta['schema_baseline'] ?? 0) === $baseline) {
            return null;
        }
        $meta['schema_baseline'] = $baseline;
        $written = @file_put_contents($lock, '<?php exit; ?>' . "\n" . json_encode($meta, JSON_UNESCAPED_UNICODE) . "\n");
        return $written === false
            ? '安装标记不可写（容器里代码目录常是只读），体检会把「结构版本与代码不一致」报成 WARN，不影响站点运行。'
            : "安装标记里的结构版本已同步为 v{$baseline}";
    }
}
