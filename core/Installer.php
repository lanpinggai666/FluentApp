<?php
/**
 * 安装/初始化的公共逻辑。
 *
 * CLI（database/install.php）与网页向导（public/install.php）都调这里，
 * 两条路必须建出完全相同的库 —— 以前各写一份就会出现「网页装的站体检不过」这类事。
 *
 * 这个类不碰超全局、不输出任何东西，只返回结构化结果，由两个入口各自决定怎么展示。
 */

class Installer
{
    /** 安装完成标记：有它就代表这台机器已经装过，网页向导不再工作。 */
    public const LOCK_FILE = '/config/install.lock';

    /** 允许由向导写入/合并的 .env 键，其他键保持原样。 */
    private const MANAGED_ENV_KEYS = ['APP_ENV', 'DB_CONNECTION', 'DB_SQLITE_PATH',
        'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD'];

    /** @return string[] 需要人工处理的问题，空数组代表可以装 */
    public static function problems(string $driver, string $sqlitePath, array $mysql): array
    {
        $problems = [];

        if (PHP_VERSION_ID < 80100) {
            $problems[] = 'PHP 版本过低（当前 ' . PHP_VERSION . '，需要 8.1 及以上）';
        }
        foreach (['pdo', 'curl', 'mbstring', 'json'] as $ext) {
            if (!extension_loaded($ext)) {
                $problems[] = '缺少 PHP 扩展：' . $ext;
            }
        }
        $pdoDriver = $driver === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite';
        if (!extension_loaded($pdoDriver)) {
            $problems[] = '缺少 PDO 驱动：' . $pdoDriver;
        }

        if ($driver === 'sqlite') {
            $dir = dirname($sqlitePath);
            if (!is_dir($dir) && !@mkdir($dir, 0750, true)) {
                $problems[] = '数据库目录不存在且无法创建：' . $dir;
            } elseif (!is_writable($dir)) {
                $problems[] = '数据库目录不可写：' . $dir . '（SQLite 要写库文件与 -wal/-shm）';
            }
            if (is_file($sqlitePath) && !is_writable($sqlitePath)) {
                $problems[] = '库文件存在但不可写：' . $sqlitePath;
            }
        } else {
            if (($mysql['username'] ?? '') === '') {
                $problems[] = 'MySQL 需要填写用户名';
            }
            if (!preg_match('/^[A-Za-z0-9_.$-]{1,64}$/', (string) ($mysql['database'] ?? ''))) {
                $problems[] = 'MySQL 库名只能用字母数字与 _ . $ -（长度 1-64），因为要拼进建库语句';
            }
        }

        $savePath = (string) ini_get('session.save_path');
        if ($savePath !== '' && !is_writable($savePath)) {
            $problems[] = 'session 落盘目录不可写：' . $savePath;
        }

        return $problems;
    }

    /** 建一个尚未写入任何结构的连接，失败时抛异常由调用方展示。 */
    public static function connect(string $driver, string $sqlitePath, array $mysql, bool $createDatabase = false): PDO
    {
        $pdoOptions = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];

        if ($driver === 'mysql') {
            $host = (string) ($mysql['host'] ?? '127.0.0.1');
            $port = (int) ($mysql['port'] ?? 3306);
            $database = (string) ($mysql['database'] ?? '');
            if ($createDatabase) {
                $server = new PDO("mysql:host={$host};port={$port};charset=utf8mb4",
                    (string) ($mysql['username'] ?? ''), (string) ($mysql['password'] ?? ''), $pdoOptions);
                // 库名已由 problems() 限死字符集，这里再包一层反引号
                $server->exec('CREATE DATABASE IF NOT EXISTS `' . $database . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
            }
            return new PDO("mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4",
                (string) ($mysql['username'] ?? ''), (string) ($mysql['password'] ?? ''), $pdoOptions);
        }

        $pdo = new PDO('sqlite:' . $sqlitePath, null, null, $pdoOptions);
        foreach (['journal_mode = WAL', 'synchronous = NORMAL', 'busy_timeout = 5000'] as $pragma) {
            $pdo->exec('PRAGMA ' . $pragma);
        }
        return $pdo;
    }

    /** @return array<string,int> 表 => 行数，用于判断「这库是不是已经装过了」 */
    public static function businessRows(PDO $pdo): array
    {
        $counts = [];
        foreach (['softwares', 'categories', 'settings', 'users'] as $table) {
            try {
                $counts[$table] = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            } catch (Throwable $e) {
                $counts[$table] = 0; // 表不存在 = 干净库
            }
        }
        return $counts;
    }

    public static function totalRows(PDO $pdo): int
    {
        return array_sum(self::businessRows($pdo));
    }

    /** 只在调用方已确认要推倒重来时调用（CLI 的 --force）。 */
    public static function dropAll(PDO $pdo): void
    {
        $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
        if ($driver === 'sqlite') {
            $pdo->exec('PRAGMA foreign_keys = OFF');
            $names = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($names as $name) {
                $pdo->exec('DROP TABLE IF EXISTS "' . str_replace('"', '""', $name) . '"');
            }
            $pdo->exec('PRAGMA foreign_keys = ON');
            return;
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $pdo->exec('DROP TABLE IF EXISTS `' . $name . '`');
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    }

    /**
     * 执行安装。$options：
     *   driver / sqlitePath / mysql / seed(bool) / admin(string) / password(string，空则随机生成)
     *   baseUrl / site(array：site_name、site_subtitle、site_description、site_keywords)
     *   baseline(int，结构版本) / reset(bool，先清空已有表)
     *
     * @return array{ok:bool,error:?string,steps:string[],admin:array{username:string,password:string,generated:bool},tables:int,softwares:int,driver:string}
     */
    public static function run(array $options): array
    {
        $driver = (string) ($options['driver'] ?? 'sqlite');
        $steps = [];

        $problems = self::problems($driver, (string) ($options['sqlitePath'] ?? ''), (array) ($options['mysql'] ?? []));
        if ($problems !== []) {
            return self::failed($problems[0], $steps, $driver);
        }

        try {
            $pdo = self::connect($driver, (string) ($options['sqlitePath'] ?? ''),
                (array) ($options['mysql'] ?? []), (bool) ($options['createDatabase'] ?? false));
        } catch (Throwable $e) {
            return self::failed('数据库连接失败：' . $e->getMessage(), $steps, $driver);
        }

        if ($driver === 'mysql') {
            $mysql = (array) ($options['mysql'] ?? []);
            $steps[] = '已连接 MySQL ' . ($mysql['host'] ?? '') . ':' . ($mysql['port'] ?? 3306) . '/' . ($mysql['database'] ?? '');
        } else {
            $steps[] = '已准备 SQLite 库 ' . (realpath((string) $options['sqlitePath']) ?: $options['sqlitePath']) . '（WAL）';
        }

        $existing = self::totalRows($pdo);
        if ($existing > 0) {
            if (empty($options['reset'])) {
                return self::failed("目标库里已有 {$existing} 行业务数据，为避免误删不做任何改动。", $steps, $driver);
            }
            self::dropAll($pdo);
            $steps[] = "已清空原有 " . $existing . " 行数据并重建";
        }

        $schema = BASE_PATH . '/database/schema_' . $driver . '.sql';
        if (!is_file($schema)) {
            return self::failed('缺少建表脚本 ' . basename($schema), $steps, $driver);
        }
        try {
            $pdo->exec((string) file_get_contents($schema));
        } catch (Throwable $e) {
            return self::failed('建表失败：' . $e->getMessage(), $steps, $driver);
        }
        $steps[] = '结构已建立（' . basename($schema) . '）';

        if (!empty($options['seed'])) {
            try {
                $pdo->exec((string) file_get_contents(BASE_PATH . '/database/seed.sql'));
                $steps[] = '种子数据已导入（分类、镜像源、公告、基础设置与示例软件）';
            } catch (Throwable $e) {
                // 与 Database::initSqliteDatabase 一致：种子冲突不致命，但要知道
                $steps[] = '种子数据部分导入失败：' . $e->getMessage();
            }
        } else {
            foreach (['site_name' => '蓝屏钙软件导航', 'admin_username' => 'admin',
                'captcha_provider' => 'local', 'ai_enabled' => '0'] as $key => $value) {
                self::putSetting($pdo, $key, $value);
            }
            $steps[] = '已跳过示例软件，仅写入最小必要配置';
        }

        $admin = self::applyAdminAccount($pdo, (string) ($options['admin'] ?? ''), (string) ($options['password'] ?? ''));
        if (!empty($admin['error'])) {
            return self::failed($admin['error'], $steps, $driver);
        }
        $steps[] = "管理员账号 {$admin['username']} 已设置（bcrypt，种子里的默认口令已失效）";

        $site = (array) ($options['site'] ?? []);
        foreach (['site_name', 'site_subtitle', 'site_description', 'site_keywords', 'site_base_url'] as $key) {
            if (isset($site[$key]) && trim((string) $site[$key]) !== '') {
                self::putSetting($pdo, $key, trim((string) $site[$key]));
            }
        }
        if (trim((string) ($options['baseUrl'] ?? '')) !== '') {
            self::putSetting($pdo, 'site_base_url', trim((string) $options['baseUrl']));
            $steps[] = '站点地址写入 ' . trim((string) $options['baseUrl']) . '（OAuth 回调、邮件内链与 sitemap 以它为准）';
        }

        // 基线本身 + 所有编号不超过基线的历史迁移，都要一并登记为「已包含在 schema 里」。
        // 否则新装的库（记 v16）会看到 migrations/0015_*.sql 处于待执行状态，被要求重跑一遍已有的结构。
        $baseline = (string) (int) ($options['baseline'] ?? 0);
        $record = static function (string $version, string $name) use ($pdo): void {
            $exists = $pdo->prepare('SELECT COUNT(*) FROM schema_migrations WHERE version = ?');
            $exists->execute([$version]);
            if ((int) $exists->fetchColumn() > 0) {
                return;
            }
            $insert = $pdo->prepare('INSERT INTO schema_migrations (version, name, applied_at) VALUES (?, ?, ?)');
            $insert->execute([$version, $name, date('Y-m-d H:i:s')]);
        };
        $record($baseline, 'baseline（随 schema_*.sql 发布的结构）');
        foreach (glob(BASE_PATH . '/database/migrations/*.sql') ?: [] as $file) {
            if (preg_match('/^(\d+)_/', basename((string) $file), $m) && (int) $m[1] <= (int) $baseline) {
                $record((string) (int) $m[1], 'v' . (int) $m[1] . ' 已包含在安装时的 schema 里');
            }
        }
        $steps[] = '结构版本记为 v' . $baseline . '，以后升级交给 php database/migrate.php';

        return [
            'ok' => true,
            'error' => null,
            'steps' => $steps,
            'admin' => $admin,
            'tables' => self::countTables($pdo),
            'softwares' => (int) $pdo->query('SELECT COUNT(*) FROM softwares')->fetchColumn(),
            'driver' => $driver,
        ];
    }

    /**
     * 种子里那个公开的管理员口令哈希（对应 admin123）。
     * 从 seed.sql 现读而不是写死，避免两处走神漂移。
     */
    public static function seedAdminHash(): ?string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached === '' ? null : $cached;
        }
        $seed = @file_get_contents(BASE_PATH . '/database/seed.sql');
        $cached = $seed !== false && preg_match("/admin_password_hash',\s*'([^']+)'/", $seed, $m) ? $m[1] : '';
        return $cached === '' ? null : $cached;
    }

    /** 是不是那个写在公开文档里的口令。 */
    public static function isPublicAdminPassword(string $password): bool
    {
        $hash = self::seedAdminHash();
        return $hash !== null && password_verify($password, $hash);
    }

    /** @return array{username:string,password:string,generated:bool,error?:string} */
    public static function applyAdminAccount(PDO $pdo, string $username, string $password): array
    {
        $username = $username !== '' ? $username : 'admin';
        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            return ['username' => $username, 'password' => '', 'generated' => false,
                'error' => '管理员账号只允许字母、数字与 _ . -，长度 3-64'];
        }
        $generated = $password === '';
        if (strlen($password) > 0 && strlen($password) < 10) {
            return ['username' => $username, 'password' => '', 'generated' => false,
                'error' => '管理员口令至少 10 位。后台暴露在公网，短口令等于没有口令。'];
        }
        if (strlen($password) > 0 && self::isPublicAdminPassword($password)) {
            return ['username' => $username, 'password' => '', 'generated' => false,
                'error' => '管理员口令不能用种子里那个公开写在文档里的默认值。'];
        }
        $password = $generated ? self::generatePassword() : $password;
        $hash = password_hash($password, PASSWORD_DEFAULT);
        if (!is_string($hash) || !password_verify($password, $hash)) {
            return ['username' => $username, 'password' => '', 'generated' => false,
                'error' => 'password_hash 自校验失败，请检查 PHP 的密码扩展'];
        }
        self::putSetting($pdo, 'admin_username', $username);
        self::putSetting($pdo, 'admin_password_hash', $hash);
        return ['username' => $username, 'password' => $password, 'generated' => $generated];
    }

    /** 去掉 0/O/1/l/I 这类容易抄错的字符；20 位、必带一位数字。 */
    public static function generatePassword(int $length = 20): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        do {
            $out = '';
            for ($i = 0; $i < $length; $i++) {
                $out .= $alphabet[random_int(0, $max)];
            }
        } while (!preg_match('/[0-9]/', $out));
        return $out;
    }

    public static function putSetting(PDO $pdo, string $key, string $value): void
    {
        if ($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $stmt = $pdo->prepare('INSERT INTO settings (key_name, key_value, updated_at) VALUES (?, ?, ?)
                ON CONFLICT(key_name) DO UPDATE SET key_value = excluded.key_value, updated_at = excluded.updated_at');
            $stmt->execute([$key, $value, date('Y-m-d H:i:s')]);
            return;
        }
        $stmt = $pdo->prepare('INSERT INTO settings (key_name, key_value) VALUES (?, ?)
            ON DUPLICATE KEY UPDATE key_value = VALUES(key_value)');
        $stmt->execute([$key, $value]);
    }

    public static function countTables(PDO $pdo): int
    {
        $sql = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite'
            ? "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
            : 'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()';
        return (int) $pdo->query($sql)->fetchColumn();
    }

    /**
     * 这台机器是否已经装过。
     * 判据有两个：安装锁文件，以及配置里的库是否已有业务数据 —— 后者保证「用 CLI 装过的站」
     * 也不会被网页向导重来一遍。
     */
    public static function installed(array $config): bool
    {
        if (is_file(BASE_PATH . self::LOCK_FILE)) {
            return true;
        }
        $driver = (string) ($config['db']['default'] ?? 'sqlite');
        $sqlitePath = (string) ($config['db']['sqlite']['path'] ?? '');
        // 关键：不能为了「查一下装没装」就去开 SQLite —— 那会凭空建出一个空库文件，
        // 而 Database::pdo() 只在文件不存在时才建表，空库会把站点带进「no such table」。
        if ($driver !== 'mysql' && ($sqlitePath === '' || !is_file($sqlitePath))) {
            return false;
        }
        try {
            $pdo = self::connect($driver, $sqlitePath, (array) ($config['db']['mysql'] ?? []));
            return self::totalRows($pdo) > 0;
        } catch (Throwable $e) {
            return false; // 连不上就当没装，向导会给出真正的连接错误
        }
    }

    public static function writeLock(array $meta): array
    {
        $file = BASE_PATH . self::LOCK_FILE;
        $content = '<?php exit; ?>' . "\n" . json_encode($meta + ['installed_at' => date('c')], JSON_UNESCAPED_UNICODE) . "\n";
        if (file_put_contents($file, $content) === false) {
            return ['ok' => false, 'error' => '安装标记写入失败：' . $file . ' 不可写，请手工创建（内容 <?php exit; ?> 开头即可）'];
        }
        @chmod($file, 0640);
        return ['ok' => true, 'error' => null];
    }

    /**
     * 合并写 config/.env：只改我们管的键，保留运维自己加的注释与其他键。
     * @return array{ok:bool,error:?string,path:string}
     */
    public static function writeEnvFile(array $values): array
    {
        $file = BASE_PATH . '/config/.env';
        $existing = is_file($file) ? (string) file_get_contents($file) : '';

        // 显式列换行符而不是 /\R/：值里带中文（比如 .env 里写中文站名）时 0x85 会被当成换行而劈坏字节
        $lines = preg_split('/\r\n|\r|\n/', $existing) ?: [];
        $result = [];
        $seen = [];
        foreach ($lines as $line) {
            if (preg_match('/^\s*([A-Za-z0-9_]+)\s*=/', $line, $m)) {
                $key = $m[1];
                $seen[$key] = true;
                if (array_key_exists($key, $values)) {
                    $result[] = $key . '=' . self::envQuote((string) $values[$key]);
                    continue;
                }
            }
            $result[] = $line;
        }
        // 首部没出现的键追加在后面（保持注释在最前）
        foreach ($values as $key => $value) {
            if (empty($seen[$key])) {
                $result[] = $key . '=' . self::envQuote((string) $value);
            }
        }
        // 原来没有 .env 时不要留出一个空行
        while ($result !== [] && trim((string) $result[0]) === '') {
            array_shift($result);
        }

        if (file_put_contents($file, implode("\n", array_map(fn($l) => rtrim((string) $l), $result)) . "\n") === false) {
            return ['ok' => false, 'error' => 'config/.env 写入失败：请把 config/ 目录给 web 进程写权限，或改用命令行安装 php database/install.php', 'path' => $file];
        }
        @chmod($file, 0640);
        return ['ok' => true, 'error' => null, 'path' => $file];
    }

    /** 含空格或特殊字符的值加引号，避免 INI 解析歧义。 */
    private static function envQuote(string $value): string
    {
        if ($value === '' || preg_match('/^[A-Za-z0-9_.:\/\\@-]+$/', $value)) {
            return $value;
        }
        return '"' . str_replace('"', '\\"', $value) . '"';
    }

    private static function failed(string $error, array $steps, string $driver): array
    {
        return ['ok' => false, 'error' => $error, 'steps' => $steps,
            'admin' => ['username' => '', 'password' => '', 'generated' => false],
            'tables' => 0, 'softwares' => 0, 'driver' => $driver];
    }
}
