<?php
/**
 * 蓝屏钙软件导航网 - 高性能数据库核心驱动
 * 支持 MySQL (主推荐生产库) 与 SQLite (快速开箱即用开发模式)
 */

class Database {
    private static ?PDO $pdo = null;
    private static string $driver = 'mysql';

    public static function pdo(): PDO {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $config = require BASE_PATH . '/config/config.php';
        $default = $config['db']['default'] ?? 'auto';

        if ($default === 'mysql' || $default === 'auto') {
            try {
                $mc = $config['db']['mysql'];
                $dsn = "mysql:host={$mc['host']};port={$mc['port']};dbname={$mc['database']};charset={$mc['charset']}";
                $pdo = new PDO($dsn, $mc['username'], $mc['password'], $mc['options'] ?? []);
                self::$pdo = $pdo;
                self::$driver = 'mysql';
                return self::$pdo;
            } catch (Throwable $e) {
                if ($default === 'mysql') {
                    throw new RuntimeException("MySQL 连接失败: " . $e->getMessage());
                }
                // auto 模式下优雅回退至 SQLite
            }
        }

        // SQLite 模式初始化
        $sc = $config['db']['sqlite'];
        $dbPath = $sc['path'];
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0750, true);
        }

        $isNew = !file_exists($dbPath);
        $pdo = new PDO("sqlite:" . $dbPath, null, null, $sc['options'] ?? []);
        self::$pdo = $pdo;
        self::$driver = 'sqlite';

        self::tuneSqlite($pdo);

        // 若为新创建的 SQLite 库，自动导入基础结构与种子数据。
        // 只允许命令行这么做：网页侧「随便谁访问一次首页就建出一个带默认口令的库」，
        // 等于把装机权交给爬虫，之后安装向导还会因为「库里已有数据」而自锁。
        if ($isNew) {
            if (PHP_SAPI === 'cli') {
                self::initSqliteDatabase($pdo);
            } else {
                self::$pdo = null;
                unset($pdo);
                @unlink($dbPath);
                throw new RuntimeException('站点尚未安装数据库，请打开 /install.php 完成安装，或执行 php database/install.php');
            }
        }

        return self::$pdo;
    }

    /**
     * 默认的 rollback journal + 无等待，在「每次访问详情页都要 UPDATE views_count」这类
     * 读写混合场景下会直接抛 SQLITE_BUSY；WAL 让读不阻塞写，busy_timeout 让偶发的写冲突
     * 排队而不是把 500 甩给用户。网络文件系统或只读目录会让 WAL 建不起来，那种情况退回到
     * 原有模式并留日志，不能让站点直接打不开。
     */
    private static function tuneSqlite(PDO $pdo): void {
        foreach (['journal_mode = WAL', 'synchronous = NORMAL', 'busy_timeout = 5000'] as $pragma) {
            try {
                $pdo->exec('PRAGMA ' . $pragma);
            } catch (Throwable $e) {
                error_log('[Database] SQLite PRAGMA ' . $pragma . ' 设置失败: ' . $e->getMessage());
            }
        }
    }

    public static function getDriver(): string {
        self::pdo();
        return self::$driver;
    }

    /** 释放连接（长生命周期脚本与测试用完需要显式断开，否则文件句柄会一直占着）。 */
    public static function close(): void {
        self::$pdo = null;
        self::$driver = 'mysql';
    }

    private static function initSqliteDatabase(PDO $pdo): void {
        $schemaPath = BASE_PATH . '/database/schema_sqlite.sql';
        $seedPath = BASE_PATH . '/database/seed.sql';

        if (file_exists($schemaPath)) {
            $sql = file_get_contents($schemaPath);
            $pdo->exec($sql);
        }

        if (file_exists($seedPath)) {
            $sql = file_get_contents($seedPath);
            // 批量执行
            try {
                $pdo->exec($sql);
            } catch (Throwable $e) {
                // 忽略非致命种子冲突
            }
        }
    }

    public static function query(string $sql, array $params = []): PDOStatement {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public static function fetch(string $sql, array $params = []): ?array {
        $stmt = self::query($sql, $params);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    public static function fetchAll(string $sql, array $params = []): array {
        $stmt = self::query($sql, $params);
        return $stmt->fetchAll();
    }

    public static function insert(string $table, array $data): int {
        $keys = array_keys($data);
        $fields = implode(', ', array_map(fn($k) => "`$k`", $keys));
        if (self::getDriver() === 'sqlite') {
            $fields = implode(', ', $keys);
        }
        $placeholders = implode(', ', array_map(fn($k) => ":$k", $keys));
        $sql = "INSERT INTO {$table} ({$fields}) VALUES ({$placeholders})";
        self::query($sql, $data);
        return (int) self::pdo()->lastInsertId();
    }

    public static function update(string $table, array $data, string $where, array $whereParams = []): int {
        $setClauses = [];
        $params = [];
        foreach ($data as $key => $val) {
            $setClauses[] = (self::getDriver() === 'sqlite' ? $key : "`$key`") . " = :set_$key";
            $params["set_$key"] = $val;
        }
        $setStr = implode(', ', $setClauses);
        $sql = "UPDATE {$table} SET {$setStr} WHERE {$where}";
        $stmt = self::query($sql, array_merge($params, $whereParams));
        return $stmt->rowCount();
    }

    public static function delete(string $table, string $where, array $params = []): int {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = self::query($sql, $params);
        return $stmt->rowCount();
    }
}
