<?php
/**
 * 部署链路：安装程序 → 装好的站点 → 版本化迁移 → 上线体检 → 生产打包
 *
 * 全部在临时目录与沙箱副本里跑，绝不碰项目里的文件与库。
 * 每组用各自的库文件，避免上一组的结构改动影响下一组的判断。
 */

group('38 安装程序');

$work = Sandbox::path(sys_get_temp_dir() . '/softnav_deploy_' . bin2hex(random_bytes(4)));
mkdir($work, 0755, true);

$installPw = 'Deploy-Test-Pw-9';
$installed = $work . '/inst.sqlite';
$installBaseline = (int) require BASE_PATH . '/database/version.php';

$installRun = cliRun(BASE_PATH . '/database/install.php', [
    '--sqlite-path=' . $installed, '--admin=navadmin', '--password=' . $installPw,
    '--base-url=https://nav.example.com',
]);
ok('安装成功（退出码 ' . $installRun['code'] . '）', $installRun['code'] === 0 && str_contains($installRun['out'], '安装完成'));

$idb = new PDO('sqlite:' . $installed);
$hash = (string) $idb->query("SELECT key_value FROM settings WHERE key_name='admin_password_hash'")->fetchColumn();
$user = (string) $idb->query("SELECT key_value FROM settings WHERE key_name='admin_username'")->fetchColumn();
$base = (string) $idb->query("SELECT key_value FROM settings WHERE key_name='site_base_url'")->fetchColumn();
$version = (int) $idb->query('SELECT MAX(version + 0) FROM schema_migrations')->fetchColumn();
$tables = (int) $idb->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchColumn();
$softwares = (int) $idb->query('SELECT COUNT(*) FROM softwares')->fetchColumn();
$wal = strtolower((string) $idb->query('PRAGMA journal_mode')->fetchColumn());
$idb = null;

ok('管理员账号写为 navadmin', $user === 'navadmin');
ok('指定口令可用 bcrypt 校验', password_verify($installPw, $hash));
ok('种子默认口令 admin123 已被覆盖失效', !password_verify('admin123', $hash));
ok('site_base_url 落库', $base === 'https://nav.example.com');
ok('结构版本登记为当前基线 v' . $version, $version === (int) require BASE_PATH . '/database/version.php');
ok('建了 ' . $tables . ' 张表并导入种子（软件 ' . $softwares . ' 条）', $tables === deploySchemaTableCount() && $softwares === 10);
ok('新建的库处于 WAL 模式', $wal === 'wal');
ok('CLI 装机也写下安装标记，网页向导随之关闭', is_file(BASE_PATH . '/config/install.lock')
    && str_contains((string) file_get_contents(BASE_PATH . '/config/install.lock'), '"installed_via":"cli"'));

$again = cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $installed]);
ok('库里已有数据时拒绝执行（要 --force）', $again['code'] === 1 && str_contains($again['out'], '为避免误删'));

$weak = cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $work . '/weak.sqlite', '--password=abc']);
ok('短口令被拒（退出码 ' . $weak['code'] . '）', $weak['code'] === 1 && str_contains($weak['out'], '至少 10 位'));

$badArg = cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $work . '/bad.sqlite', '--nope=1']);
ok('未知参数被拒而不是被忽略', $badArg['code'] === 1 && str_contains($badArg['out'], '不支持的参数'));

$randomPw = cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $work . '/rand.sqlite', '--no-seed']);
$randDb = new PDO('sqlite:' . $work . '/rand.sqlite');
$randHash = (string) $randDb->query("SELECT key_value FROM settings WHERE key_name='admin_password_hash'")->fetchColumn();
$randSoftwares = (int) $randDb->query('SELECT COUNT(*) FROM softwares')->fetchColumn();
$randSiteName = (string) $randDb->query("SELECT key_value FROM settings WHERE key_name='site_name'")->fetchColumn();
$randDb = null;
ok('--no-seed 装成空站（不导示例软件，但保留基础设置）',
    $randomPw['code'] === 0 && $randSoftwares === 0 && $randSiteName !== '');
ok('随机口令只打印一次且已落库为 bcrypt', str_contains($randomPw['out'], '管理员密码')
    && strlen($randHash) === 60 && password_get_info($randHash)['algoName'] !== 'unknown');
foreach (['weak', 'bad', 'rand'] as $junk) {
    @unlink($work . '/' . $junk . '.sqlite');
}

group('39 装好的站点真的能用');

// 用安装程序产出的库起一个实例，验证「装完即可访问」，而不是只验证 SQL
$instance = Sandbox::spawnWithDb($installed);
Sandbox::resetRateLimits();
foreach (['/' => '首页', '/?r=software/view&slug=vscode' => '详情页', '/?r=submit' => '提交页', '/?r=admin/login' => '后台登录页'] as $path => $label) {
    [$code, $body] = Sandbox::http($path, [], [], null, $instance);
    ok($label . ' 可访问（' . $code . '，' . strlen($body) . 'B）', $code === 200 && strlen($body) > 1500
        && !preg_match('/Fatal error|SQLSTATE|Warning:/i', $body));
}

[$badCode, $badBody] = deployLogin($instance, 'navadmin', 'admin123');
ok('默认口令进不去（HTTP ' . $badCode . '，页面回了错误提示）',
    $badCode !== 302 && (str_contains($badBody, '用户名或密码错误') || str_contains($badBody, '过于频繁')));
[$goodCode, , $goodJar] = deployLogin($instance, 'navadmin', $installPw);
[$dashCode, $dashBody] = Sandbox::http('/?r=admin/dashboard', [], [], $goodJar, $instance);
ok('安装时设定的口令可登录并进后台（' . $goodCode . '/' . $dashCode . '）',
    $goodCode === 302 && $dashCode === 200 && str_contains($dashBody, '退出登录'));

group('40 版本化迁移');

$migDb = $work . '/mig.sqlite';
cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $migDb, '--password=' . $installPw]);
// 用 config/.env 告诉 CLI 目标库，顺带验证这条链路真的生效（服务器上就是这么配的）
file_put_contents(BASE_PATH . '/config/.env', "DB_CONNECTION=sqlite\nDB_SQLITE_PATH=\"" . $migDb . "\"\nAPP_ENV=production\n");

$dry = cliRun(BASE_PATH . '/database/migrate.php', ['--dry-run']);
ok('migrate 经 config/.env 连到目标库（v' . $installBaseline . '，无待执行项）',
    str_contains($dry['out'], 'v' . $installBaseline) && str_contains($dry['out'], '结构已是最新'));

file_put_contents(BASE_PATH . '/database/migrations/9001_add_deploy_probe.sql', "ALTER TABLE softwares ADD COLUMN deploy_probe VARCHAR(32);\n");
file_put_contents(BASE_PATH . '/database/migrations/9001_add_deploy_probe.mysql.sql', "ALTER TABLE softwares ADD COLUMN deploy_probe_mysql VARCHAR(32);\n");
file_put_contents(BASE_PATH . '/database/migrations/no_number_prefix.sql', "-- 命名不合规，应被跳过并提示\n");
file_put_contents(BASE_PATH . '/database/migrations/notes.txt', "非 sql 文件不应被执行\n");

$dryRun = cliRun(BASE_PATH . '/database/migrate.php', ['--dry-run']);
ok('--dry-run 列出待执行迁移但不改库', str_contains($dryRun['out'], '9001') && !deployColumnExists($migDb, 'deploy_probe'));

$apply = cliRun(BASE_PATH . '/database/migrate.php', []);
ok('通用迁移已应用、方言专属迁移被跳过（退出码 ' . $apply['code'] . '）',
    $apply['code'] === 0 && deployColumnExists($migDb, 'deploy_probe') && !deployColumnExists($migDb, 'deploy_probe_mysql'));
ok('命名不合规的 .sql 被跳过并点名提示', str_contains($apply['out'], 'no_number_prefix.sql'));
ok('非 .sql 文件被忽略且不报警告', !str_contains($apply['out'], 'notes.txt'));

$second = cliRun(BASE_PATH . '/database/migrate.php', []);
ok('重复执行是幂等的（不会重跑 9001）', str_contains($second['out'], '结构已是最新'));

$stamp = cliRun(BASE_PATH . '/database/migrate.php', ['--stamp=9002']);
ok('--stamp 只登记版本、不动结构', str_contains($stamp['out'], '登记') && !deployColumnExists($migDb, 'deploy_probe2'));

file_put_contents(BASE_PATH . '/database/migrations/9003_drop_probe.sql', "DROP TABLE notices;\n");
$danger = cliRun(BASE_PATH . '/database/migrate.php', []);
ok('含 DROP TABLE 的迁移未经确认不会执行', $danger['code'] === 1 && str_contains($danger['out'], '--yes') && deployTableExists($migDb, 'notices'));
$dangerYes = cliRun(BASE_PATH . '/database/migrate.php', ['--yes']);
ok('加 --yes 后才执行破坏性迁移', $dangerYes['code'] === 0 && !deployTableExists($migDb, 'notices'));

foreach (['9001_add_deploy_probe.sql', '9001_add_deploy_probe.mysql.sql', 'no_number_prefix.sql', 'notes.txt', '9003_drop_probe.sql'] as $tmp) {
    @unlink(BASE_PATH . '/database/migrations/' . $tmp);
}
@unlink(BASE_PATH . '/config/.env');

group('41 上线体检');

$checkDb = $work . '/check.sqlite';
cliRun(BASE_PATH . '/database/install.php', ['--sqlite-path=' . $checkDb, '--password=' . $installPw, '--base-url=https://nav.example.com']);
file_put_contents(BASE_PATH . '/config/.env', "DB_CONNECTION=sqlite\nDB_SQLITE_PATH=\"" . $checkDb . "\"\n");

$check = cliRun(BASE_PATH . '/deploy/preflight.php', []);
ok('刚装好的库体检 0 失败（退出码 ' . $check['code'] . '）', $check['code'] === 0 && str_contains($check['out'], '失败 0 项'));
ok('体检逐项检查了表/索引/凭据/暴露面',
    str_contains($check['out'], '关键表齐全') && str_contains($check['out'], '索引都已在库中')
    && str_contains($check['out'], '管理员口令不是默认口令'));

$pdo = new PDO('sqlite:' . $checkDb);
$pdo->prepare("UPDATE settings SET key_value=? WHERE key_name='admin_password_hash'")
    ->execute(['$2y$10$achoy/GGwNkU2UtCE1kXgORO0dnvTgHGiQIsTx/4bhq3sAqyZNPti']);
$pdo = null;
$checkBad = cliRun(BASE_PATH . '/deploy/preflight.php', []);
ok('退回默认口令时体检失败并点名 admin123（退出码 ' . $checkBad['code'] . '）',
    $checkBad['code'] === 1 && str_contains($checkBad['out'], 'FAIL') && str_contains($checkBad['out'], 'admin123'));

$pdo = new PDO('sqlite:' . $checkDb);
$pdo->exec('DROP TABLE schema_migrations');
$pdo = null;
$checkNoSchema = cliRun(BASE_PATH . '/deploy/preflight.php', []);
ok('缺结构版本记录时判定为未通过', $checkNoSchema['code'] === 1 && str_contains($checkNoSchema['out'], 'schema_migrations'));
@unlink(BASE_PATH . '/config/.env');

group('42 生产部署包');

// 复制一份「开发工作区」：既有生产文件，也故意放上 dev 残留，验证白名单真的挡住
$pkgSource = $work . '/app';
mkdir($pkgSource, 0755, true);
deployCopy(BASE_PATH, $pkgSource, ['config']);
deployMkdir($pkgSource . '/config');
foreach (scandir(BASE_PATH . '/config') as $entry) {
    if ($entry !== '.' && $entry !== '..') {
        copy(BASE_PATH . '/config/' . $entry, $pkgSource . '/config/' . $entry);
    }
}
file_put_contents($pkgSource . '/config/.env', "DB_PASSWORD=不应进包的密码\n");
file_put_contents($pkgSource . '/config/install.lock', "<?php exit; ?>\n{\"schema_baseline\":14}\n");
file_put_contents($pkgSource . '/database/nav.sqlite', '开发库不应进包');
deployMkdir($pkgSource . '/tests');
file_put_contents($pkgSource . '/tests/run.php', "<?php // 测试入口不应进包");
deployMkdir($pkgSource . '/tools/dev');
file_put_contents($pkgSource . '/tools/dev/probe.php', "<?php // 诊断脚本不应进包");
file_put_contents($pkgSource . '/deploy/local-note.md', '本地说明不应进包');
file_put_contents($pkgSource . '/Agent.md', '开发约定不应进包');

$packed = cliRun($pkgSource . '/deploy/package.php', ['--out=' . $work . '/dist', '--no-tar']);
$staged = $work . '/dist/softnav-1.0.0-b' . (int) require BASE_PATH . '/database/version.php';
ok('打包成功（退出码 ' . $packed['code'] . '）', $packed['code'] === 0 && is_dir($staged));

foreach (['config/.env' => '环境密钥', 'config/install.lock' => '安装标记', 'database/nav.sqlite' => '开发库',
    'tests/run.php' => '测试入口', 'tools/dev/probe.php' => '诊断脚本', 'deploy/local-note.md' => '本地说明',
    'Agent.md' => '开发约定', 'deploy/package.php' => '打包脚本自己'] as $rel => $label) {
    ok($label . ' 没进包（' . $rel . '）', !file_exists($staged . '/' . $rel));
}
foreach (['public/index.php', 'public/install.php', 'public/.htaccess', 'core/Installer.php',
    'config/config.php', 'config/.env.example',
    'database/install.php', 'database/migrate.php', 'database/schema_sqlite.sql', 'database/schema_mysql.sql',
    'database/seed.sql', 'database/migrations/README.md', 'database/legacy/README.md',
    'deploy/preflight.php', 'deploy/nginx.conf.example', 'DEPLOY.md', 'API.md', 'VERSION', 'views/software/parts/hero.php'] as $rel) {
    ok('运行与升级所需文件在包里（' . $rel . '）', is_file($staged . '/' . $rel));
}

$manifest = (string) file_get_contents($staged . '/MANIFEST.txt');
$listed = [];
foreach (explode("\n", $manifest) as $line) {
    if (preg_match('/^\s*\d+\s+[0-9A-F]{16}\s+(\S+)$/', $line, $m)) {
        $listed[] = $m[1];
    }
}
$stagedFiles = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($staged, FilesystemIterator::SKIP_DOTS));
foreach ($it as $item) {
    if ($item->isFile() && $item->getFilename() !== 'MANIFEST.txt') {
        $stagedFiles[] = str_replace('\\', '/', substr(Sandbox::path($item->getPathname()), strlen($staged) + 1));
    }
}
sort($stagedFiles);
sort($listed);
ok('MANIFEST 逐个文件登记，且与落盘文件一致（' . count($listed) . ' 条）', $stagedFiles === $listed && count($listed) > 150);
ok('包里每个 PHP 文件都能通过语法检查', deployLintAll($staged));

deployRmrf($work);

/**
 * 走一次真实后台登录（先取 CSRF token 再 POST）。
 * @return array{0:int,1:string,2:string} [状态码, 响应体, cookie jar 路径]
 */
function deployLogin(string $base, string $user, string $password): array
{
    $jar = Sandbox::newJar();
    [, $page] = Sandbox::http('/?r=admin/login', [], [], $jar, $base);
    [$code, $body] = Sandbox::http('/?r=admin/login', [
        'username' => $user,
        'password' => $password,
        'csrf_token' => (string) Sandbox::formToken($page),
    ], [], $jar, $base);
    return [$code, $body, $jar];
}

function deployColumnExists(string $dbFile, string $column): bool
{
    $pdo = new PDO('sqlite:' . $dbFile);
    $cols = array_map(fn($r) => $r['name'], $pdo->query('PRAGMA table_info(softwares)')->fetchAll(PDO::FETCH_ASSOC));
    $pdo = null;
    return in_array($column, $cols, true);
}

function deployTableExists(string $dbFile, string $table): bool
{
    $pdo = new PDO('sqlite:' . $dbFile);
    $found = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='{$table}'")->fetchColumn();
    $pdo = null;
    return $found > 0;
}

/** 建表脚本里声明了多少张表（跟着 schema 走，加表不用改测试） */
function deploySchemaTableCount(): int
{
    return (int) preg_match_all('/^CREATE TABLE /mi', (string) file_get_contents(BASE_PATH . '/database/schema_sqlite.sql'));
}

function deployMkdir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

function deployCopy(string $from, string $to, array $skipTop = []): void
{
    if (is_dir($from)) {
        if (!is_dir($to)) {
            mkdir($to, 0755, true);
        }
        foreach (scandir($from) as $entry) {
            if ($entry === '.' || $entry === '..' || in_array($entry, $skipTop, true)) {
                continue;
            }
            deployCopy($from . '/' . $entry, $to . '/' . $entry, []);
        }
        return;
    }
    copy($from, $to);
}

function deployRmrf(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (scandir($dir) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $path = $dir . '/' . $entry;
        is_dir($path) ? deployRmrf($path) : @unlink($path);
    }
    @rmdir($dir);
}

function deployLintAll(string $dir): bool
{
    $bad = 0;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $item) {
        if ($item->isFile() && str_ends_with($item->getFilename(), '.php')) {
            exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg(Sandbox::path($item->getPathname())) . ' 2>&1', $out, $code);
            $bad += $code === 0 ? 0 : 1;
            $out = [];
        }
    }
    return $bad === 0;
}
