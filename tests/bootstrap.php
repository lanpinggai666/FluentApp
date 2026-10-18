<?php
/**
 * 测试基础设施：把项目复制到临时目录、用内置服务器跑起来，所有用例只碰副本库。
 *
 * 用法：php tests/run.php            跑全部用例
 *      php tests/run.php --no-net   跳过需要外网的用例
 */

final class Tally
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static int $skip = 0;
    public static array $failures = [];
    public static string $group = '';
}

function group(string $name): void
{
    Tally::$group = $name;
    echo "\n\033[1m== {$name} ==\033[0m\n";
}

function ok(string $name, bool $cond): void
{
    if ($cond) {
        Tally::$pass++;
        echo "  [PASS] {$name}\n";
        return;
    }
    Tally::$fail++;
    Tally::$failures[] = Tally::$group . ' / ' . $name;
    echo "  [FAIL] {$name}\n";
}

function skip(string $name, string $why): void
{
    Tally::$skip++;
    echo "  [SKIP] {$name}（{$why}）\n";
}

/**
 * 从建表脚本里解析出「表名 => 列名列表」。
 * 需要跳过字符串里的括号与逗号，否则 COMMENT '...(a,b)' 这类内容会打乱切分。
 *
 * @return array<string, list<string>>
 */
function sqlCreateTables(string $sql): array
{
    $result = [];
    if (!preg_match_all('/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?[`"\[]?([A-Za-z0-9_]+)[`"\]]?\s*\(/i', $sql, $heads, PREG_OFFSET_CAPTURE)) {
        return $result;
    }
    foreach ($heads[1] as $head) {
        $name = $head[0];
        $open = strpos($sql, '(', $head[1]);
        $depth = 0;
        $inString = false;
        $close = $open;
        $len = strlen($sql);
        for ($i = $open; $i < $len; $i++) {
            $ch = $sql[$i];
            if ($inString) {
                if ($ch === "'") {
                    $inString = false;
                }
                continue;
            }
            if ($ch === "'") {
                $inString = true;
            } elseif ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    $close = $i;
                    break;
                }
            }
        }
        $body = substr($sql, $open + 1, $close - $open - 1);
        $result[$name] = sqlColumnNames($body);
    }
    return $result;
}

/** @return list<string> */
function sqlColumnNames(string $body): array
{
    $columns = [];
    $parts = [];
    $buffer = '';
    $depth = 0;
    $inString = false;
    for ($i = 0, $len = strlen($body); $i < $len; $i++) {
        $ch = $body[$i];
        if ($inString) {
            $buffer .= $ch;
            if ($ch === "'") {
                $inString = false;
            }
            continue;
        }
        if ($ch === "'") {
            $inString = true;
            $buffer .= $ch;
            continue;
        }
        if ($ch === '(') {
            $depth++;
        } elseif ($ch === ')') {
            $depth--;
        } elseif ($ch === ',' && $depth === 0) {
            $parts[] = $buffer;
            $buffer = '';
            continue;
        }
        $buffer .= $ch;
    }
    if (trim($buffer) !== '') {
        $parts[] = $buffer;
    }

    $tableLevel = ['primary', 'unique', 'key', 'index', 'constraint', 'foreign', 'fulltext', 'check'];
    foreach ($parts as $part) {
        $part = trim($part);
        if ($part === '' || str_starts_with($part, '--')) {
            continue;
        }
        if (!preg_match('/^[`"\[]?([A-Za-z0-9_]+)[`"\]]?/', $part, $m)) {
            continue;
        }
        if (in_array(strtolower($m[1]), $tableLevel, true)) {
            continue;
        }
        $columns[] = $m[1];
    }
    return $columns;
}

/** 从 SQLite 实际库里取出「表 => 列」 */
/**
 * 解析建表脚本里的列类型：table => column => 归一化类型（如 'BIGINT UNSIGNED'、'VARCHAR(254)'）。
 * 只看列名不够 —— MySQL 8 要求外键两端类型完全一致（含 UNSIGNED），
 * 类型漂移（software_id 写成 INT 而 softwares.id 是 BIGINT）就是靠这个抓出来的。
 *
 * @return array<string,array<string,string>>
 */
function sqlColumnTypes(string $sql): array
{
    $out = [];
    if (!preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\((.*?)\n\)\s*(?:ENGINE|;)/is', $sql, $tables, PREG_SET_ORDER)) {
        return $out;
    }
    foreach ($tables as $table) {
        foreach (preg_split("/\n/", $table[2]) as $line) {
            if (preg_match('/^\s+`?(\w+)`?\s+([A-Za-z]+(?:\([\d,]+\))?(?:\s+(?:UNSIGNED|SIGNED))?)\b/i', $line, $m)) {
                $out[$table[1]][strtolower($m[1])] = strtoupper(preg_replace('/\s+/', ' ', trim($m[2])));
            }
        }
    }
    return $out;
}

/** 解析脚本里声明的外键：[子表, 子列, 父表, 父列] */
function sqlForeignKeys(string $sql): array
{
    $out = [];
    if (!preg_match_all('/CREATE TABLE (?:IF NOT EXISTS )?`?(\w+)`?\s*\((.*?)\n\)\s*(?:ENGINE|;)/is', $sql, $tables, PREG_SET_ORDER)) {
        return $out;
    }
    foreach ($tables as $table) {
        if (!preg_match_all('/FOREIGN KEY\s*\(\s*`?(\w+)`?\s*\)\s*REFERENCES\s*`?(\w+)`?\s*\(\s*`?(\w+)`?\s*\)/i', $table[2], $fks, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($fks as $fk) {
            $out[] = [$table[1], $fk[1], $fk[2], $fk[3]];
        }
    }
    return $out;
}

/** 某张表里被索引覆盖的列（PRIMARY KEY / KEY / UNIQUE KEY），MySQL 建外键要求父列有索引 */
function sqlIndexedColumns(string $sql, string $table): array
{
    if (!preg_match('/CREATE TABLE (?:IF NOT EXISTS )?`?' . preg_quote($table, '/') . '`?\s*\((.*?)\n\)\s*(?:ENGINE|;)/is', $sql, $block)) {
        return [];
    }
    $cols = [];
    foreach (preg_split("/\n/", $block[1]) as $line) {
        if (preg_match('/^\s*(?:PRIMARY KEY|UNIQUE KEY|KEY|INDEX)\b[^()]*\(([^)]*)\)/i', $line, $m)) {
            foreach (explode(',', $m[1]) as $part) {
                $name = trim($part, " `\t");
                // 去掉 utf8mb4 下常见的前缀长度写法 col(191)
                $name = preg_replace('/\(\d+\)$/', '', $name);
                if ($name !== '') {
                    $cols[strtolower($name)] = true;
                }
            }
        }
    }
    return array_keys($cols);
}

function liveTableColumns(PDO $db): array
{
    $tables = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")
        ->fetchAll(PDO::FETCH_COLUMN);
    $out = [];
    foreach ($tables as $table) {
        $out[$table] = array_map(
            static fn(array $row): string => $row['name'],
            $db->query('PRAGMA table_info(' . str_replace('`', '', $table) . ')')->fetchAll(PDO::FETCH_ASSOC)
        );
    }
    return $out;
}

function liveIndexColumns(PDO $db): array
{
    $out = [];
    foreach ($db->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'") as $table) {
        foreach ($db->query('PRAGMA index_list(' . $table['name'] . ')') as $idx) {
            $cols = array_map(static fn(array $r): string => $r['name'], $db->query('PRAGMA index_info(' . $idx['name'] . ')')->fetchAll(PDO::FETCH_ASSOC));
            $out[$table['name']][] = $cols;
        }
    }
    return $out;
}

final class Sandbox
{
    public static string $root = '';
    public static string $url = '';
    public static string $misconfiguredUrl = '';
    public static ?PDO $db = null;
    public static bool $online = false;
    public static bool $offline = false;
    private static $process = null;
    private static $misconfiguredProcess = null;
    private static array $spawned = [];
    private static array $pipes = [];
    private static string $logFile = '';

    /**
     * 额外起一个「把项目根当 web 根」的服务器，用来验证误配部署下的兜底守卫。
     * 这个模式本身仍然能把 database/nav.sqlite 直接下载走，所以它只用于证明
     * 「迁移/诊断脚本不会被匿名执行」，不代表这种部署是安全的。
     */
    public static function startMisconfiguredServer(): string
    {
        if (self::$misconfiguredUrl !== '') {
            return self::$misconfiguredUrl;
        }
        $port = self::pickPort();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', self::path(self::$root)];
        self::$misconfiguredProcess = proc_open($cmd, [1 => ['file', self::$logFile, 'a'], 2 => ['file', self::$logFile, 'a']], $pipes);
        self::$misconfiguredUrl = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 60; $i++) {
            $sock = @fsockopen('127.0.0.1', $port, $e, $s, 0.3);
            if ($sock) {
                fclose($sock);
                break;
            }
            usleep(200000);
        }
        return self::$misconfiguredUrl;
    }

    /**
     * 复制一份「尚未装机」的应用并起服务，用来测网页安装向导。
     * 去掉库文件、config/.env 与安装标记，让它落到「向导可用」的状态。
     *
     * @return array{0:string,1:string} [实例地址, 实例目录]
     */
    public static function spawnBlank(): array
    {
        $dir = dirname(self::$root) . '/blank_' . bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        foreach (scandir(self::$root) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::copy(self::$root . '/' . $entry, $dir . '/' . $entry);
        }
        foreach (['nav.sqlite', 'nav.sqlite-wal', 'nav.sqlite-shm'] as $stale) {
            @unlink($dir . '/database/' . $stale);
        }
        @unlink($dir . '/config/.env');
        @unlink($dir . '/config/install.lock');

        $port = self::pickPort();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', self::path($dir . '/public')];
        $proc = proc_open($cmd, [1 => ['file', self::$logFile, 'a'], 2 => ['file', self::$logFile, 'a']], $pipes);
        self::$spawned[] = ['dir' => $dir, 'proc' => $proc];

        $url = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 60; $i++) {
            $sock = @fsockopen('127.0.0.1', $port, $e, $s, 0.3);
            if ($sock) {
                fclose($sock);
                break;
            }
            usleep(200000);
        }
        return [$url, $dir];
    }

    /** 用另一个 SQLite 文件再开一份站点实例，用来验证「全新安装能不能跑起来」。
     * 直接换文件在 Windows 上会被已打开的句柄挡住，所以开实例而不是替换。
     *
     * @return string 实例的基地址
     */
    public static function spawnWithDb(string $sqliteFile): string
    {
        $dir = dirname(self::$root) . '/instance_' . bin2hex(random_bytes(4));
        mkdir($dir, 0755, true);
        foreach (scandir(self::$root) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::copy(self::$root . '/' . $entry, $dir . '/' . $entry);
        }
        foreach (['nav.sqlite', 'nav.sqlite-wal', 'nav.sqlite-shm'] as $stale) {
            @unlink($dir . '/database/' . $stale);
        }
        copy($sqliteFile, $dir . '/database/nav.sqlite');

        $port = self::pickPort();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', self::path($dir . '/public')];
        $proc = proc_open($cmd, [1 => ['file', self::$logFile, 'a'], 2 => ['file', self::$logFile, 'a']], $pipes);
        self::$spawned[] = ['dir' => $dir, 'proc' => $proc];

        $url = 'http://127.0.0.1:' . $port;
        for ($i = 0; $i < 60; $i++) {
            $sock = @fsockopen('127.0.0.1', $port, $e, $s, 0.3);
            if ($sock) {
                fclose($sock);
                break;
            }
            usleep(200000);
        }
        return $url;
    }

    /**
     * 发一次带方法、请求体与自定义头的请求，返回 [状态码, 响应体, 响应头]。
     * 开放 API 用 JSON body + X-Api-Key，Sandbox::http() 的表单式提交覆盖不到这种形态。
     */
    public static function rawHttpBody(string $absoluteUrl, string $method = 'GET', ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($absoluteUrl);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 25, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HEADER => true, CURLOPT_CUSTOMREQUEST => strtoupper($method)];
        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }
        if ($headers !== []) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        curl_close($ch);
        if ($error !== '') {
            echo '    请求失败 ' . $absoluteUrl . '：' . $error . "\n";
        }
        return [$code, substr($raw, $headSize), substr($raw, 0, $headSize)];
    }

    /** 对指定绝对地址发一次请求（不经 Sandbox::$url 前缀） */
    public static function rawHttp(string $absoluteUrl): array
    {
        $ch = curl_init($absoluteUrl);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true]);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [$code, substr($raw, $hs), substr($raw, 0, $hs)];
    }

    /** 复制项目到临时目录并起一个内置服务器。 */
    public static function start(): void
    {
        self::sweepStaleSandboxes();

        $source = dirname(__DIR__);
        $tmp = self::tmpBase() . '/softnav_tests_' . bin2hex(random_bytes(6));
        $dir = $tmp . '/app';
        if (!mkdir($dir, 0755, true)) {
            throw new RuntimeException('无法创建临时沙箱目录: ' . $dir);
        }
        self::$root = $dir;

        foreach (scandir($source) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (in_array($entry, ['.agent', '.git', 'tests', 'dist'], true) || str_starts_with($entry, '.qoder')) {
                continue;
            }
            self::copy($source . '/' . $entry, $dir . '/' . $entry);
        }

        // 沙箱里不碰外网：AI 补全与发信全部关掉，否则每个提交都要等第三方超时
        // 库不再依赖仓库里带的开发数据文件，直接从 schema + seed 现建，保证「测的」和「装的」是同一份结构
        $dbFile = $dir . '/database/nav.sqlite';
        @unlink($dbFile);
        self::$db = new PDO('sqlite:' . self::path($dbFile));
        self::$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        self::$db->exec((string) file_get_contents($source . '/database/schema_sqlite.sql'));
        try {
            self::$db->exec((string) file_get_contents($source . '/database/seed.sql'));
        } catch (Throwable $e) {
            // 与 Database::initSqliteDatabase 一致：种子冲突不致命
        }
        foreach (['ai_enabled' => '0', 'smtp_enabled' => '0', 'captcha_provider' => 'local', 'ai_web_search_enabled' => '0'] as $k => $v) {
            self::$db->prepare('INSERT OR REPLACE INTO settings (key_name,key_value,updated_at) VALUES (?,?,?)')
                ->execute([$k, $v, date('Y-m-d H:i:s')]);
        }

        self::$online = self::detectNetwork();
        self::$logFile = $tmp . '/server.log';

        $port = self::pickPort();
        $cmd = [PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', self::path($dir . '/public')];
        self::$process = proc_open($cmd, [1 => ['file', self::$logFile, 'w'], 2 => ['file', self::$logFile, 'a']], self::$pipes);
        if (!is_resource(self::$process)) {
            throw new RuntimeException('无法启动测试服务器');
        }

        self::$url = 'http://127.0.0.1:' . $port;
        self::waitUntilReady();
    }

    public static function stop(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
        if (is_resource(self::$misconfiguredProcess)) {
            proc_terminate(self::$misconfiguredProcess);
            proc_close(self::$misconfiguredProcess);
            self::$misconfiguredProcess = null;
        }
        foreach (self::$spawned as $spawned) {
            if (is_resource($spawned['proc'])) {
                proc_terminate($spawned['proc']);
                proc_close($spawned['proc']);
            }
        }
        self::$spawned = [];
        // 必须先放掉所有进程内的 SQLite 句柄，否则 Windows 下删不掉文件，沙箱目录会残留
        if (class_exists('Database', false)) {
            Database::close();
        }
        self::$db = null;
        $parent = self::$root ? dirname(self::$root) : '';
        if ($parent !== '' && is_dir($parent)) {
            // 刚关掉的 SQLite/服务器句柄可能还没被系统释放，重试几次再放弃
            // （实在删不掉也没关系，下次运行开头会清扫）
            for ($attempt = 0; $attempt < 4 && is_dir($parent); $attempt++) {
                self::rmrf($parent);
                if (is_dir($parent)) {
                    usleep(150000);
                }
            }
        }
    }

    /** 让测试进程也能加载本站的类（断言直接用真实实现，不复制一份逻辑）。 */
    public static function autoload(): void
    {
        if (defined('BASE_PATH')) {
            return;
        }
        define('BASE_PATH', self::$root);
        define('VIEW_PATH', BASE_PATH . '/views');
        define('CORE_PATH', BASE_PATH . '/core');
        define('MODEL_PATH', BASE_PATH . '/models');
        define('SERVICE_PATH', BASE_PATH . '/services');
        define('CONTROLLER_PATH', BASE_PATH . '/controllers');
        require_once BASE_PATH . '/config/config.php';
        spl_autoload_register(function ($class) {
            foreach ([CORE_PATH, MODEL_PATH, SERVICE_PATH, CONTROLLER_PATH] as $dir) {
                if (is_file($dir . '/' . $class . '.php')) {
                    require_once $dir . '/' . $class . '.php';
                    return;
                }
            }
        });
    }

    /**
     * 清掉测试用到的限流计数桶。
     * 只删本套件会写的动作前缀，避免顺手把开发者本机正在跑的应用计数也抹掉。
     */
    public static function resetRateLimits(): void
    {
        $dir = sys_get_temp_dir() . '/softnav_rate';
        if (!is_dir($dir)) {
            return;
        }
        $prefixes = ['admin-login', 'user-login', 'user-login-ip', 'send-code-mail', 'send-code-ip',
            'verify-code', 'web-install', 'api-read', 'api-write', 'api-auth', 'submit-resource', 'unit-test'];
        foreach (glob($dir . '/*.json') as $file) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with(basename($file), $prefix . '-')) {
                    @unlink($file);
                    break;
                }
            }
        }
    }

    public static function newJar(): string
    {
        $file = self::tmpBase() . '/jar_' . bin2hex(random_bytes(6)) . '.txt';
        @unlink($file);
        return $file;
    }

    /** @return array{0:int,1:string,2:string} [status, body, headers] */
    public static function http(string $path, array $post = [], array $headers = [], ?string $jar = null, ?string $base = null): array
    {
        $jar = $jar ?? self::newJar();
        $ch = curl_init(($base ?? self::$url) . $path);
        $opts = [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $jar, CURLOPT_COOKIEJAR => $jar,
                 CURLOPT_TIMEOUT => 30, CURLOPT_FOLLOWLOCATION => false, CURLOPT_HEADER => true];
        if ($post) {
            $opts[CURLOPT_POST] = true;
            $opts[CURLOPT_POSTFIELDS] = http_build_query($post);
        }
        if ($headers) {
            $opts[CURLOPT_HTTPHEADER] = $headers;
        }
        curl_setopt_array($ch, $opts);
        $raw = (string) curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);
        return [$code, substr($raw, $headSize), substr($raw, 0, $headSize)];
    }

    public static function formToken(string $html): ?string
    {
        return preg_match('/name="csrf_token" value="([^"]+)"/', $html, $m) ? $m[1] : null;
    }

    public static function metaToken(string $html): ?string
    {
        return preg_match('/name="csrf-token" content="([^"]+)"/', $html, $m) ? $m[1] : null;
    }

    /** 本地算术题是一次性的，取指定 scope 的最后一道题的答案 */
    public static function mathAnswer(string $html, string $scope = 'submit'): ?int
    {
        preg_match_all('/name="captcha_name" value="([^"]*)"/', $html, $n);
        preg_match_all('/(\d+) \+ (\d+) = \?/u', $html, $mm, PREG_SET_ORDER);
        $answer = null;
        foreach ($mm as $i => $s) {
            if (($n[1][$i] ?? '') === $scope) {
                $answer = (int) $s[1] + (int) $s[2];
            }
        }
        return $answer;
    }

    /** 登录后台，返回带会话的 cookie jar */
    public static function adminJar(): string
    {
        $jar = self::newJar();
        [, $page] = self::http('/?r=admin/login', [], [], $jar);
        self::http('/?r=admin/login', [
            'username' => 'admin',
            'password' => 'admin123',
            'csrf_token' => (string) self::formToken($page),
        ], [], $jar);
        return $jar;
    }

    /** 前台用户登录（带本地算术题人机验证） */
    public static function userJar(string $account = 'admin', string $password = 'admin123', bool $remember = false): string
    {
        $jar = self::newJar();
        [, $page] = self::http('/?r=auth/login', [], [], $jar);
        self::http('/?r=auth/login', [
            'account' => $account,
            'password' => $password,
            'remember' => $remember ? '1' : '',
            'csrf_token' => (string) self::formToken($page),
            'captcha_name' => 'login',
            'captcha_math_input' => (string) self::mathAnswer($page, 'login'),
        ], [], $jar);
        return $jar;
    }

    public static function setting(string $key): ?string
    {
        $row = self::$db->prepare('SELECT key_value FROM settings WHERE key_name = ?');
        $row->execute([$key]);
        $value = $row->fetchColumn();
        return $value === false ? null : (string) $value;
    }

    public static function setSetting(string $key, string $value): void
    {
        self::$db->prepare('INSERT OR REPLACE INTO settings (key_name,key_value,updated_at) VALUES (?,?,?)')
            ->execute([$key, $value, date('Y-m-d H:i:s')]);
    }

    /** 统一成斜杠路径：Windows 下 PHP 与内置服务器都接受正斜杠，避免 DSN 里反斜杠被当转义 */
    public static function path(string $p): string
    {
        return str_replace('\\', '/', $p);
    }

    /** 清掉历史中断留下的沙箱目录（异常退出时 shutdown 未必来得及） */
    private static function sweepStaleSandboxes(): void
    {
        $base = self::tmpBase();
        foreach (glob($base . '/softnav_tests_*') as $dir) {
            self::rmrf($dir);
        }
        foreach (glob($base . '/fresh_install_*') as $dir) {
            self::rmrf($dir);
        }
    }

    private static function tmpBase(): string
    {
        return str_replace('\\', '/', sys_get_temp_dir());
    }

    /** 只做 TCP 连通性探测：用 HTTPS 探测会被证书校验干扰，测不出「有没有网」 */
    private static function detectNetwork(): bool
    {
        foreach (['example.com', 'www.google.com'] as $host) {
            $sock = @fsockopen($host, 443, $errno, $errstr, 2.5);
            if ($sock) {
                fclose($sock);
                return true;
            }
        }
        return false;
    }

    private static function pickPort(): int
    {
        for ($port = 8860; $port < 8920; $port++) {
            $sock = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
            if (!$sock) {
                return $port;
            }
            fclose($sock);
        }
        throw new RuntimeException('找不到可用的测试端口');
    }

    private static function waitUntilReady(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $sock = @fsockopen('127.0.0.1', (int) parse_url(self::$url, PHP_URL_PORT), $e, $s, 0.3);
            if ($sock) {
                fclose($sock);
                return;
            }
            usleep(200000);
        }
        throw new RuntimeException("测试服务器未能启动，日志:\n" . (string) @file_get_contents(self::$logFile));
    }

    private static function copy(string $from, string $to): void
    {
        if (is_dir($from)) {
            mkdir($to, 0755, true);
            foreach (scandir($from) as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                self::copy($from . '/' . $entry, $to . '/' . $entry);
            }
            return;
        }
        copy($from, $to);
    }

    private static function rmrf(string $dir): void
    {
        if (!is_dir($dir)) {
            @unlink($dir);
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            self::rmrf($dir . '/' . $entry);
        }
        @rmdir($dir);
    }
}

/**
 * 在沙箱副本里跑一个 CLI 运维脚本（install/migrate/preflight/package），返回退出码与合并输出。
 * 用例与 run.php 共享全局作用域，公共动作集中在这里，别在各用例里重复实现一份。
 *
 * @return array{code:int,out:string}
 */
function cliRun(string $script, array $args = []): array
{
    $log = (string) tempnam(sys_get_temp_dir(), 'softnav_cli');
    $proc = proc_open(array_merge([PHP_BINARY, Sandbox::path($script)], $args),
        [1 => ['file', $log, 'w'], 2 => ['file', $log, 'a']], $pipes);
    $code = is_resource($proc) ? proc_close($proc) : -1;
    $out = (string) file_get_contents($log);
    unlink($log);
    return ['code' => $code, 'out' => $out];
}

register_shutdown_function(function () {
    $error = error_get_last();
    Sandbox::stop();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_COMPILE_ERROR], true)) {
        fwrite(STDERR, "\n致命错误: " . $error['message'] . " @ " . $error['file'] . ':' . $error['line'] . "\n");
    }
});
