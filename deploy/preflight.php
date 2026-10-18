<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit; // 体检脚本会暴露路径、驱动与配置状态，只允许命令行执行
}

/**
 * 蓝屏钙软件导航 —— 上线前体检
 *
 *   php deploy/preflight.php                     只查本机文件与数据库
 *   php deploy/preflight.php --url=https://x.com 追加一次真实 HTTP 探测（暴露面与响应头）
 *   php deploy/preflight.php --no-net            跳过一切外网探测
 *
 * 只读：不写库、不改文件，可以反复跑。退出码 0=可以上线（可能有 WARN），1=有 FAIL 必须先处理。
 */

$config = require dirname(__DIR__) . '/config/config.php';
require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/HttpTls.php';
require_once CORE_PATH . '/Installer.php';
require_once BASE_PATH . '/models/Setting.php';

$baseline = (int) require dirname(__DIR__) . '/database/version.php';

$options = parseOptions($argv);
$pass = 0;
$warns = [];
$fails = [];

echo "蓝屏钙软件导航 上线体检 · PHP " . PHP_VERSION . " · 基线 v{$baseline}\n";
echo str_repeat('=', 62) . "\n";

// 先看装没装：没装的话不能去连库 —— Database::pdo() 发现 SQLite 文件不存在会顺手建库导种子，
// 一个「只读体检」不该有这种副作用。
$installedNow = is_file(BASE_PATH . Installer::LOCK_FILE) || Installer::installed($config);

checkRuntime();
checkConfigFile();
checkInstallState($installedNow);
if ($installedNow) {
    $pdo = checkDatabase();
    if ($pdo !== null) {
        checkSchema($pdo, $baseline);
        checkAdminCredential($pdo);
        checkSiteSettings($pdo);
        checkAiConfig($pdo);
    }
} else {
    echo "\n-- 数据库 " . str_repeat('-', 53) . "\n  ·  站点尚未安装，跳过库内检查\n";
}
checkFileSystem();
if ($installedNow) {
    checkOpenApi();
}
if ($options['url'] !== '') {
    if ($options['no-net']) {
        echo "\n-- 线上探测已按 --no-net 跳过\n";
    } else {
        checkLiveSite($options['url']);
    }
}

echo str_repeat('=', 62) . "\n";
printf("通过 %d 项，警告 %d 项，失败 %d 项\n", $pass, count($warns), count($fails));
foreach ($warns as $message) {
    echo "  WARN  {$message}\n";
}
foreach ($fails as $message) {
    echo "  FAIL  {$message}\n";
}
if ($fails === []) {
    echo "\n没有阻塞项。把上面 WARN 逐条确认一遍再对外，尤其是 TLS 与文件权限。\n";
    exit(0);
}
echo "\n有 FAIL 项，先别上流量。逐条修好后重跑本脚本。\n";
exit(1);

function parseOptions(array $argv): array
{
    $out = ['url' => '', 'no-net' => false];
    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '--no-net') {
            $out['no-net'] = true;
        } elseif (preg_match('/^--url=(https?:\/\/.+)$/', $arg, $m)) {
            $out['url'] = rtrim($m[1], '/');
        } else {
            fwrite(STDERR, "无法识别的参数: {$arg}（支持 --url=https://域名 与 --no-net）\n");
            exit(1);
        }
    }
    return $out;
}

function record(bool $ok, string $label, string $detail = '', bool $failIsFatal = true): bool
{
    global $pass, $warns, $fails;
    if ($ok) {
        $pass++;
        echo "  ✓ " . $label . ($detail !== '' ? "（{$detail}）" : '') . "\n";
        return true;
    }
    if ($failIsFatal) {
        $fails[] = $label . ($detail !== '' ? "：{$detail}" : '');
        echo "  ✗ " . $label . ($detail !== '' ? "（{$detail}）" : '') . "\n";
    } else {
        $warns[] = $label . ($detail !== '' ? "：" . $detail : '');
        echo "  ! " . $label . ($detail !== '' ? "（{$detail}）" : '') . "\n";
    }
    return false;
}

function section(string $title): void
{
    echo "\n-- {$title} " . str_repeat('-', max(2, 56 - mb_strwidth($title, 'UTF-8'))) . "\n";
}

function checkRuntime(): void
{
    section('运行时');
    record(PHP_VERSION_ID >= 80100, 'PHP 版本 ≥ 8.1', PHP_VERSION);
    $driver = $GLOBALS['config']['db']['default'];
    $needed = ['pdo', 'curl', 'mbstring', 'json', 'session', $driver === 'mysql' ? 'pdo_mysql' : 'pdo_sqlite'];
    $missing = array_values(array_filter($needed, fn($ext) => !extension_loaded($ext)));
    record($missing === [], '必需扩展齐全', $missing === [] ? implode(', ', $needed) : '缺 ' . implode(', ', $missing));

    // CLI 会按设计强制打开调试回显，所以这条只反映 php.ini，线上表现以 --url 探测为准
    record(!filter_var(ini_get('display_errors'), FILTER_VALIDATE_BOOLEAN),
        'php.ini 的 display_errors 为 Off', '当前=' . ini_get('display_errors'), false);
    record(!filter_var(ini_get('expose_php'), FILTER_VALIDATE_BOOLEAN),
        'php.ini 的 expose_php 为 Off', '当前=' . ini_get('expose_php'), false);

    $memoryLimit = toBytes((string) ini_get('memory_limit'));
    record($memoryLimit <= 0 || $memoryLimit >= 64 * 1024 * 1024, 'memory_limit 不低于 64M',
        ini_get('memory_limit') ?: '未设置', false);

    // 发信走 stream_socket_client + openssl（本站不用 mail()，所以 blacklist 里禁 mail 无所谓）
    record(extension_loaded('openssl'), 'openssl 扩展已加载（STARTTLS / 隐式 TLS 发信必需）',
        extension_loaded('openssl') ? '' : '未加载：SMTP 加密连接必然失败，请在 php.ini 打开 extension=openssl', false);
    record(function_exists('stream_socket_client'), 'stream_socket_client 未被 disable_functions 禁用',
        function_exists('stream_socket_client') ? '' : '被禁用：后台「发送测试邮件」和所有通知邮件都发不出去', false);
    $execTime = (int) ini_get('max_execution_time');
    record($execTime <= 0 || $execTime >= 25, 'max_execution_time 够跑完一次 SMTP 握手',
        $execTime <= 0 ? 'CLI 或不限' : '当前 ' . $execTime . 's，低于握手所需时间会被 PHP 掐断成白屏/500', false);

    $opcacheOn = function_exists('opcache_get_status') && (bool) @opcache_get_status(false)['enabled'];
    record($opcacheOn, 'OPcache 已开启', $opcacheOn ? 'enabled' : '没开的话每次请求都要重新编译模板', false);

    $savePath = (string) ini_get('session.save_path');
    record($savePath === '' || is_writable($savePath), 'session 落盘目录可写',
        $savePath === '' ? '使用 PHP 默认目录' : $savePath);
}

/** php.ini 的 "128M" 之类写成字节数，方便和阈值比较。 */
function toBytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $unit = strtolower($value[strlen($value) - 1]);
    $number = (int) $value;
    return match ($unit) {
        'g' => $number * 1024 * 1024 * 1024,
        'm' => $number * 1024 * 1024,
        'k' => $number * 1024,
        default => $number,
    };
}

function checkConfigFile(): void
{
    section('配置');
    $envFile = BASE_PATH . '/config/.env';
    if (is_file($envFile)) {
        $perm = @fileperms($envFile) & 0777;
        // Windows 上 fileperms 只能表达可读/可写， POSIX 位没意义，所以这条只作提醒
        record(($perm & 0077) === 0, 'config/.env 权限收紧到 640 以内', substr(sprintf('%o', $perm), -4), false);
    } else {
        echo "  · 没有 config/.env，配置来自环境变量与默认值\n";
    }
    $default = $GLOBALS['config']['db']['default'];
    record(in_array($default, ['sqlite', 'mysql', 'auto'], true), 'DB_CONNECTION 取值合法', $default);
    if ($default === 'mysql') {
        $mc = $GLOBALS['config']['db']['mysql'];
        record($mc['password'] !== '', 'MySQL 已设置密码', "账号 {$mc['username']}（生产不要用 root，也不要用空密码）");
        record($mc['username'] !== 'root', 'MySQL 未使用 root 账号', $mc['username'], false);
    }
    $sqlitePath = $GLOBALS['config']['db']['sqlite']['path'];
    record(!str_starts_with(str_replace('\\', '/', $sqlitePath), str_replace('\\', '/', BASE_PATH . '/public')),
        'SQLite 库文件不在 web 可达目录里', $sqlitePath, false);
}

/** 装机状态：没装好的站谈不上安全，装完还开着的向导则是现成的接管入口。 */
function checkInstallState(bool $installedNow): void
{
    section('安装状态');
    $lock = BASE_PATH . Installer::LOCK_FILE;
    record($installedNow, '站点已完成安装',
        $installedNow ? '' : '打开 /install.php 走网页向导，或 php database/install.php —— 装好之前别对外');

    if (is_file($lock)) {
        $meta = json_decode(trim((string) preg_replace('/^<\?php exit; \?>/', '', (string) file_get_contents($lock))), true);
        $baseline = (int) ($GLOBALS['baseline'] ?? 0);
        $lockedBaseline = is_array($meta) ? (int) ($meta['schema_baseline'] ?? 0) : 0;
        record($lockedBaseline === $baseline, '安装标记里的结构版本与代码一致',
            $lockedBaseline === $baseline ? "v{$baseline}" : "装机时 v{$lockedBaseline}，代码 v{$baseline}，请跑 php database/migrate.php", false);
    } else {
        record(false, '存在 config/install.lock 安装标记', '缺失时网页向导只靠「库里已有数据」判断已安装，建议补一个', false);
    }

    if (is_file(BASE_PATH . '/public/install.php')) {
        record(true, '安装向导文件仍在（已装机后它只会返回 404）', '确认无误后可以直接删掉 public/install.php', false);
    }
}

/** @return PDO|null 连不上就不继续查库内项 */
function checkDatabase(): ?PDO
{
    section('数据库');
    try {
        $pdo = Database::pdo();
    } catch (Throwable $e) {
        record(false, '数据库连接', $e->getMessage());
        return null;
    }
    record(true, '数据库连接成功', Database::getDriver());

    $driver = Database::getDriver();
    if ($driver === 'sqlite') {
        $mode = strtolower((string) $pdo->query('PRAGMA journal_mode')->fetchColumn());
        record($mode === 'wal', 'SQLite 处于 WAL 模式', $mode === 'wal' ? 'wal' : "当前={$mode}，非 WAL 时读写互堵、详情页偶发 500", false);
        $dir = dirname($GLOBALS['config']['db']['sqlite']['path']);
        record(is_writable($dir), '库所在目录可写', $dir . '（WAL 需要写 -wal/-shm）');
    }
    return $pdo;
}

function checkSchema(PDO $pdo, int $baseline): void
{
    section('结构');
    $driver = Database::getDriver();
    $tables = $driver === 'sqlite'
        ? $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)
        : $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $tables = array_values(array_map('strval', $tables));
    record(count($tables) >= 15, '表数量符合预期', count($tables) . ' 张');

    $required = ['settings', 'softwares', 'categories', 'comments', 'reports', 'users', 'notifications', 'schema_migrations'];
    $missingTables = array_values(array_diff($required, $tables));
    record($missingTables === [], '关键表齐全', $missingTables === [] ? implode(', ', $required) : '缺 ' . implode(', ', $missingTables));

    // MySQL 的索引写在 CREATE TABLE 里的 KEY 子句，SQLite 才是独立的 CREATE INDEX 语句
    $schemaSql = (string) file_get_contents(BASE_PATH . '/database/schema_' . $driver . '.sql');
    $expectedIndexes = [];
    if (preg_match_all('/CREATE INDEX (?:IF NOT EXISTS )?[`\']?(\w+)/i', $schemaSql, $m)) {
        $expectedIndexes = array_merge($expectedIndexes, $m[1]);
    }
    if (preg_match_all('/^\s*(?:UNIQUE\s+)?KEY\s+`(\w+)`\s*\(/mi', $schemaSql, $m)) {
        $expectedIndexes = array_merge($expectedIndexes, $m[1]);
    }
    $expectedIndexes = array_values(array_unique(array_filter($expectedIndexes, fn($n) => $n !== 'PRIMARY')));
    $liveIndexes = [];
    if ($driver === 'sqlite') {
        $liveIndexes = array_map('strval', $pdo->query("SELECT name FROM sqlite_master WHERE type='index'")->fetchAll(PDO::FETCH_COLUMN));
    } else {
        foreach ($tables as $table) {
            foreach ($pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll() as $row) {
                $liveIndexes[] = (string) $row['Key_name'];
            }
        }
    }
    $liveIndexes = array_values(array_unique($liveIndexes));
    $missingIndexes = array_values(array_diff($expectedIndexes, $liveIndexes));
    record($missingIndexes === [], 'schema 声明的索引都已在库中',
        $missingIndexes === [] ? count($expectedIndexes) . ' 个' : '缺 ' . implode(', ', $missingIndexes), false);

    try {
        $rows = $pdo->query('SELECT version FROM schema_migrations ORDER BY version+0 DESC')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        record(false, 'schema_migrations 可读', '这个库不是 database/install.php 装的，或结构没升级到位');
        return;
    }
    $current = count($rows) > 0 ? (int) $rows[0] : 0;
    record($current > 0, '库里有结构版本记录', $current > 0 ? "当前 v{$current}" : '无记录（请用 install.php 重装，或 migrate.php --stamp=baseline 登记）');
    record($current >= $baseline, '结构版本不低于代码基线',
        $current >= $baseline ? "v{$current} ≥ v{$baseline}" : "库 v{$current} < 代码 v{$baseline}，请跑 php database/migrate.php", false);
}

/** 默认口令与种子哈希是最常见的「装完就破」入口。 */
function checkAdminCredential(PDO $pdo): void
{
    section('后台凭据');
    $stmt = $pdo->prepare('SELECT key_value FROM settings WHERE key_name = ?');
    $stmt->execute(['admin_password_hash']);
    $hash = (string) $stmt->fetchColumn();
    $stmt->execute(['admin_username']);
    $username = (string) $stmt->fetchColumn();

    $account = $username !== '' ? $username : '(空)';
    if ($hash === '') {
        record(false, '管理员口令哈希存在', "账号 {$account}：没有哈希时引导口令 admin123 直接生效");
        record(false, '管理员口令不是默认口令 admin123', '重跑 php database/install.php --password=... 或在后台改密');
        return;
    }
    record(true, '管理员口令哈希存在', "账号 {$account}");
    record(!password_verify('admin123', $hash), '管理员口令不是默认口令 admin123',
        '仍是种子哈希，重跑 install.php --password=... 或在后台改密');
}

/**
 * AI 到底能不能跑起来 —— 「资料全是占位内容」的原因通常是配置，不是模型：
 * 开关没开、没有一条线路填了密钥、或者预算比 PHP 的执行时限还长（当场补全会被掐断）。
 * 只读配置，不发外呼。
 */
function checkAiConfig(PDO $pdo): void
{
    section('AI 补全');
    $keys = "'ai_enabled','ai_providers_json','ai_api_key','ai_retries','ai_total_timeout','ai_web_search_enabled','ai_tool_use','captcha_provider'";
    $rows = $pdo->query("SELECT key_name, key_value FROM settings WHERE key_name IN ({$keys})")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[(string) $row['key_name']] = trim((string) ($row['key_value'] ?? ''));
    }

    $enabled = ($settings['ai_enabled'] ?? '1') !== '0';
    record($enabled, 'AI 补全总开关已开启', $enabled ? '' : '关闭时所有补全都走本地规则，详情页看到的是占位内容', false);

    $usable = 0;
    $decoded = json_decode((string) ($settings['ai_providers_json'] ?? ''), true);
    if (is_array($decoded)) {
        foreach ($decoded as $p) {
            if (!empty($p['enabled']) && trim((string) ($p['api_key'] ?? '')) !== '') {
                $usable++;
            }
        }
    }
    if ($usable === 0 && trim((string) ($settings['ai_api_key'] ?? '')) !== '') {
        $usable = 1;   // 旧版单线路配置
    }
    record($usable > 0, '至少有一条「已启用且填了密钥」的 AI 线路',
        $usable > 0 ? $usable . ' 条可用' : '一条都没有 —— 密钥框是只写不读的，页面上看着线路都在，实际没密钥就必然落本地兜底', false);

    $budget = max(10, min(120, (int) ($settings['ai_total_timeout'] ?? 20)));
    $limit = (int) trim((string) ini_get('max_execution_time'));
    $tooLong = $limit > 0 && $budget >= $limit;
    record(!$tooLong, 'AI 总时长预算小于 PHP 执行时限',
        $tooLong ? "ai_total_timeout={$budget}s，而 max_execution_time={$limit}s —— 审核时的当场补全会被 PHP 掐断（发布本身不受影响，先发布后补全）"
                 : "预算 {$budget}s，max_execution_time " . ($limit > 0 ? $limit . 's' : '未限制'), false);

    $captcha = ($settings['captcha_provider'] ?? 'local') ?: 'local';
    record($captcha !== 'none', '人机验证不是「关闭」状态',
        $captcha === 'none' ? 'captcha_provider=none 时服务端 verify() 直接放行，评论/提交/举报的验证码等于没装' : $captcha, false);
}

function checkSiteSettings(PDO $pdo): void
{
    section('站点设置');
    $keys = "'site_base_url','smtp_enabled','captcha_provider','ai_enabled',"
        . "'smtp_host','smtp_port','smtp_encryption','smtp_username','smtp_from_email','admin_email'";
    $rows = $pdo->query("SELECT key_name, key_value FROM settings WHERE key_name IN ({$keys})")->fetchAll();
    $settings = [];
    foreach ($rows as $row) {
        $settings[(string) $row['key_name']] = trim((string) ($row['key_value'] ?? ''));
    }
    $baseUrl = $settings['site_base_url'] ?? '';
    record($baseUrl !== '', 'site_base_url 已填写',
        $baseUrl === '' ? 'OAuth 回调、邮件内链与 sitemap 会退回按请求头猜测的域名，多域名或反代下会出错' : $baseUrl, false);
    if ($baseUrl !== '') {
        record(str_starts_with($baseUrl, 'https://'), 'site_base_url 使用 https', $baseUrl, false);
    }
    // 发信链路：以前体检只看 smtp_enabled 这个开关，配了一半（缺 host / 缺发件邮箱 / 空串击穿默认值）
    // 一律看不出来，用户只能收到一句「SMTP 配置不完整」。这里逐项点名缺哪个。
    $smtpOn = ($settings['smtp_enabled'] ?? '0') === '1';
    if (!$smtpOn) {
        record(false, 'SMTP 邮件通知未启用',
            '审核结果、驳回链接与举报反馈都不会发信；需要发信请在后台【系统与AI设置】填好并开启', false);
    } else {
        $smtpMissing = [];
        foreach (['smtp_host' => '服务器地址', 'smtp_username' => 'SMTP 账号'] as $smtpKey => $smtpLabel) {
            if (($settings[$smtpKey] ?? '') === '') {
                $smtpMissing[] = $smtpLabel;
            }
        }
        $port = (int) ($settings['smtp_port'] ?? 0);
        if ($port < 1 || $port > 65535) {
            $smtpMissing[] = '端口（常见 465=SSL / 587=STARTTLS）';
        }
        $from = $settings['smtp_from_email'] ?? '';
        if ($from === '') {
            $from = $settings['smtp_username'] ?? '';
        }
        if (!filter_var($from, FILTER_VALIDATE_EMAIL)) {
            $smtpMissing[] = '发件邮箱（留空时取 SMTP 账号，所以账号必须是完整邮箱）';
        }
        record($smtpMissing === [], 'SMTP 配置齐全',
            $smtpMissing === []
                ? ($settings['smtp_host'] ?? '') . ':' . ($settings['smtp_port'] ?? '') . ' / ' . ($settings['smtp_encryption'] ?: '默认 STARTTLS')
                : '缺少：' . implode('、', $smtpMissing), false);
        record(!empty($settings['admin_email']), '管理员邮箱已填写',
            empty($settings['admin_email']) ? '「发送测试邮件」留空收件人时没有兜底地址' : $settings['admin_email'], false);
    }
    echo "  ·  captcha_provider=" . ($settings['captcha_provider'] ?? '未设置')
        . "，smtp_enabled=" . ($settings['smtp_enabled'] ?? '未设置')
        . "，ai_enabled=" . ($settings['ai_enabled'] ?? '未设置') . "\n";
}

function checkFileSystem(): void
{
    section('暴露面与残留');
    $forbiddenInWebRoot = [
        '/config/config.php' => '配置文件（含数据库账号来源）',
        '/database/schema_mysql.sql' => '建表脚本',
        '/tests/run.php' => '测试入口',
        '/tools/dev/inspect_state.php' => '开发期诊断脚本',
    ];
    foreach ($forbiddenInWebRoot as $path => $label) {
        $file = BASE_PATH . $path;
        if (is_file($file)) {
            echo "  ·  {$path} 存在于代码目录（web 根指向 public/ 时不可达；" . $label . "）\n";
        }
    }
    record(!is_file(BASE_PATH . '/public/config.php') && !is_file(BASE_PATH . '/public/.env'),
        'public/ 下没有配置文件或 .env');
    record(is_file(BASE_PATH . '/database/.htaccess') && is_file(BASE_PATH . '/config/.htaccess'),
        'Apache 误配兜底 .htaccess 存在');
    $staleFound = [];
    foreach (['/database/nav.sqlite', '/database/nav.sqlite-wal', '/database/nav.sqlite-shm'] as $stale) {
        if (is_file(BASE_PATH . $stale)) {
            $staleFound[] = $stale . '(' . round(filesize(BASE_PATH . $stale) / 1024) . 'KB)';
        }
    }
    record($staleFound === [], '目录里没有开发期数据库文件',
        $staleFound === [] ? '' : implode(', ', $staleFound) . '：文档根正确时不可达，但部署包不该带，也别和线上库混在一起', false);
    if (is_dir(BASE_PATH . '/.agent')) {
        echo "  ·  本地工具目录 .agent 存在（" . round(dirSize(BASE_PATH . '/.agent') / 1048576, 1) . "MB），不要上传\n";
    }
    record(is_file(BASE_PATH . '/public/index.php'), '前端入口 public/index.php 就位');
}

/** 开放 API 的姿态：表在不在、有没有在跑的密钥、有没有放宽 HTTPS。 */
function checkOpenApi(): void
{
    section('开放 API');
    if (!is_file(BASE_PATH . '/controllers/ApiadminController.php')) {
        echo "  ·  未发现 API 控制器，跳过\n";
        return;
    }
    try {
        $active = (int) Database::fetch('SELECT COUNT(*) AS c FROM api_keys WHERE revoked_at IS NULL')['c'];
        $total = (int) Database::fetch('SELECT COUNT(*) AS c FROM api_keys')['c'];
        $denied = (int) Database::fetch("SELECT COUNT(*) AS c FROM api_logs WHERE result <> 'ok'")['c'];
        record(true, 'api_keys 表可用', "共 {$total} 把，有效 {$active} 把；异常调用 {$denied} 次", false);
        if ($active > 0) {
            $stale = Database::fetchAll('SELECT name, last_used_at FROM api_keys WHERE revoked_at IS NULL AND last_used_at IS NULL');
            record($stale === [], '没有「发出来但从没使用过」的密钥', $stale === [] ? '' : count($stale)
                . ' 把密钥一次都没用过（' . implode(', ', array_column($stale, 'name')) . '），确认不是漏删的测试密钥', false);
        }
    } catch (Throwable $e) {
        record(false, 'api_keys 表存在', '代码已经有开放 API，但库里没有对应表 —— 跑 php database/migrate.php 补上');
    }

    if (Setting::get('api_allow_insecure', '0') === '1') {
        record(false, '未放宽 HTTPS 要求', 'api_allow_insecure=1：管理员口令会随明文 HTTP 上网，只在临时内网调试时这么干');
    } else {
        record(true, '未放宽 HTTPS 要求（API 强制走 TLS）');
    }
    record(Setting::get('api_enabled', '1') === '1', '开放 API 总闸是开的',
        '关闭状态下 App 会全部拿到 503', false);
}

function dirSize(string $dir): int
{
    $total = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST);
    foreach ($it as $item) {
        if ($item->isFile()) {
            $total += $item->getSize();
        }
    }
    return $total;
}

/**
 * 真实打一次站点：证明伪静态、文档根与响应头都对，而不是只看本机文件。
 *
 * 这里不用 SafeHttp —— 它按设计拒绝内网地址（防 SSRF），而体检的目标域名
 * 常常就是 127.0.0.1 或内网机器。地址由运维自己敲进来，不存在被诱导的问题。
 * 证书校验仍然打开，等于顺带验证 TLS 配好了。
 */
function checkLiveSite(string $url): void
{
    section('线上探测 ' . $url);
    // 先查向导：它是最关键的「有没有对外敞开」信号，也避免后面的请求改变状态后看不出真实情况
    $wizard = probe($url . '/install.php');
    $wizardOpen = $wizard['status'] === 200 && str_contains($wizard['body'], '安装向导');
    record(!$wizardOpen, '安装向导已关闭',
        $wizardOpen ? '装机还没走完，此刻任何人都能建管理员账号 —— 先完成安装再放流量' : 'HTTP ' . $wizard['status']);

    $home = probe($url . '/');
    record(($home['status'] ?? 0) === 200, '首页可访问', 'HTTP ' . ($home['status'] ?? 0) . '，' . strlen($home['body']) . 'B');

    // 伪静态：首页里出现 /software/<slug> 形态的链接就说明后台开了「URL 伪静态」开关，
    // 那这些链接必须真能打开 —— 开关先于 Nginx/Apache 规则打开时，全站详情链接是 404 的
    if (preg_match('#href="(/software/[^"?]+)#', $home['body'], $prettyMatch) === 1) {
        $pretty = probe($url . $prettyMatch[1]);
        record(($pretty['status'] ?? 0) === 200, '伪静态地址可直达（重写规则已生效）',
            $prettyMatch[1] . ' → HTTP ' . ($pretty['status'] ?? 0));
    } else {
        echo "  ·  站内详情链接仍是 ?r= 形态：后台「URL 伪静态」未开启（配好重写规则再开，见 DEPLOY.md 5.1）\n";
    }

    record(!preg_match('/Fatal error|SQLSTATE\[|Uncaught/i', $home['body']),
        '首页没有回显 PHP 错误或 SQL', substr(preg_replace('/\s+/', ' ', strip_tags($home['body'])), 0, 80) ?: '');

    foreach (['/config/config.php' => '配置', '/database/nav.sqlite' => '数据库', '/database/seed.sql' => '种子',
        '/tools/dev/inspect_state.php' => '诊断脚本', '/tests/run.php' => '测试入口'] as $path => $label) {
        $probe = probe($url . $path);
        $leaked = ($probe['status'] ?? 0) === 200 && strlen($probe['body']) > 0;
        record(!$leaked, $label . ' 无法被公网下载', 'HTTP ' . ($probe['status'] ?? 0));
    }

    $asset = probe($url . '/assets/css/style.css');
    record(($asset['status'] ?? 0) === 200, '静态资源可访问（文档根指向 public/）', 'HTTP ' . ($asset['status'] ?? 0));
    if (($asset['status'] ?? 0) === 200) {
        record(stripos($asset['type'], 'text/css') !== false, '静态资源 MIME 正确', $asset['type'], false);
    }

    $login = probe($url . '/?r=admin/login');
    record(($login['status'] ?? 0) === 200, '后台登录页可达', 'HTTP ' . ($login['status'] ?? 0), false);
    $cookie = '';
    foreach ($login['headers'] as $line) {
        if (stripos($line, 'set-cookie:') === 0) {
            $cookie = $line;
        }
    }
    if ($cookie !== '') {
        record(stripos($cookie, 'httponly') !== false && stripos($cookie, 'samesite') !== false,
            '会话 Cookie 带 HttpOnly 与 SameSite', trim(substr($cookie, 13, 90)), !(bool) preg_match('#^https://#', $url));
    }
    $hasHsts = false;
    foreach ($home['headers'] as $line) {
        if (stripos($line, 'strict-transport-security:') === 0) {
            $hasHsts = true;
        }
    }
    if (str_starts_with($url, 'https://')) {
        record($hasHsts, 'HTTPS 站点下发 HSTS', '没有也不影响功能，但建议加上', false);
    }
}

/** @return array{status:int,body:string,type:string,headers:array<int,string>} */
function probe(string $url): array
{
    $headers = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$headers) {
            // 必须按 curl 传进来的长度原样回执（含 CRLF），返回值偏小会被当成写入失败而中断传输
            $trimmed = rtrim($line, "\r\n");
            if ($trimmed !== '') {
                $headers[] = $trimmed;
            }
            return strlen($line);
        },
    ] + HttpTls::curlOptions());
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($error !== '') {
        echo "  ·  探测 {$url} 失败: {$error}\n";
    }
    return ['status' => $status, 'body' => $body, 'type' => $type, 'headers' => $headers];
}
