<?php
/**
 * 蓝屏钙软件导航网 - 系统配置模型
 */

class Setting {
    private static array $cache = [];

    public static function loadAll(): array {
        if (!empty(self::$cache)) {
            return self::$cache;
        }

        try {
            $rows = Database::fetchAll("SELECT key_name, key_value FROM settings");
            foreach ($rows as $row) {
                self::$cache[$row['key_name']] = $row['key_value'];
            }
        } catch (Throwable $e) {
            // 忽略未建表阶段
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string {
        self::loadAll();
        return self::$cache[$key] ?? $default;
    }

    public static function set(string $key, ?string $value): void {
        self::$cache[$key] = $value;
        $driver = Database::getDriver();

        if ($driver === 'sqlite') {
            Database::query("INSERT INTO settings (key_name, key_value) VALUES (:k, :v) ON CONFLICT(key_name) DO UPDATE SET key_value = :v2, updated_at = CURRENT_TIMESTAMP", [
                'k' => $key,
                'v' => $value,
                'v2' => $value
            ]);
        } else {
            Database::query("INSERT INTO settings (key_name, key_value) VALUES (:k, :v) ON DUPLICATE KEY UPDATE key_value = :v2", [
                'k' => $key,
                'v' => $value,
                'v2' => $value
            ]);
        }
    }
}
