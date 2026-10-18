<?php
/**
 * 蓝屏钙软件导航 —— 网页安装向导（一次性）
 *
 * 上传代码后浏览器打开 https://你的域名/install.php 就能配数据库、站点信息与管理员账号。
 *
 * 安全边界（这个页面在装好之前对任何人可达，所以每一条都要真的生效）：
 *   1. 装完立刻写 config/install.lock 并自锁，之后再访问一律 404；
 *      即使锁文件被删，库里已有业务数据同样判为已安装 —— 不能被重来一遍。
 *   2. POST 必须带 CSRF token，且单独限流（默认 6 次/15 分钟）。
 *   3. 拒绝用种子里那个公开的 admin123 当管理员口令。
 *   4. 失败只回一句人话，堆栈进日志，不进响应。
 *   5. 升级结构不在这个页面做（预认证页面不该能改表），请用 php database/migrate.php。
 *
 * 装好后建议直接删掉这个文件；保留着也无妨，它会 404。
 */

$config = require dirname(__DIR__) . '/config/config.php';
require_once CORE_PATH . '/Installer.php';
require_once CORE_PATH . '/Auth.php';
require_once CORE_PATH . '/View.php';
require_once CORE_PATH . '/RateLimiter.php';

$baseline = (int) require BASE_PATH . '/database/version.php';
$locked = is_file(BASE_PATH . Installer::LOCK_FILE);

if ($locked || Installer::installed($config)) {
    // 已装机：不给表单、不给任何配置信息，只留一句删除提示
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo "<!doctype html><meta charset=utf-8><title>404</title>"
        . "<p style=\"font:16px/1.6 system-ui;max-width:40em;margin:4rem auto\">站点已经安装完成，安装向导已关闭。</p>"
        . "<p style=\"font:14px/1.6 system-ui;max-width:40em;margin:0 auto;color:#666\">"
        . "如果确实要重装，请改用命令行：<code>php database/install.php --force</code>（会先删光数据），"
        . "或先删除 <code>config/install.lock</code> 并清空数据库。<a href=\"/\">回到首页</a></p>";
    exit;
}

// 装机页在任何情况下都不回显堆栈：本地用 php -S 调试时 APP_DEBUG 是 true，
// 而这个页面装好之前对全网可达，异常细节只进日志。
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

Auth::start();
$errors = [];
$old = installDefaults($config);
$succeeded = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $limit = RateLimiter::consume('web-install', RateLimiter::clientIp(), 6, 900);
    if (!$limit['allowed']) {
        $errors[] = '安装尝试过于频繁，请 ' . (int) ceil($limit['retry_after'] / 60) . ' 分钟后再试。';
    } elseif (!Auth::verifyRequestCsrf()) {
        $errors[] = '安全令牌无效或已过期，请刷新页面后重新提交。';
    } else {
        $old = readForm($config);
        $errors = validateInstallForm($old, $config);
        if ($errors === [] && !is_writable(BASE_PATH . '/config')) {
            $errors[] = 'config/ 目录不可写，无法保存 config/.env 与安装标记。'
                . '请把该目录交给 web 进程用户写权限，或改用命令行安装：php database/install.php';
        }
        if ($errors === []) {
            try {
                $succeeded = performInstall($old, $baseline);
            } catch (Throwable $exception) {
                // 细节留在日志里，页面只说人话：这句话会包含数据库账号，别外泄
                error_log('[SoftNav] web install failed: ' . $exception->getMessage());
                $succeeded = ['ok' => false, 'error' => '装机过程中出现异常，细节已写入 PHP 错误日志。常见原因是数据库连不上或目录没有写权限。'];
            }
            if ($succeeded['ok']) {
                RateLimiter::clear('web-install', RateLimiter::clientIp());
            } else {
                $errors[] = $succeeded['error'];
            }
        }
    }
}

header('Content-Type: text/html; charset=utf-8');
renderInstallPage($old, $errors, $succeeded, $baseline, $config);
exit;

/** @return array<string,string> 表单初值 */
function installDefaults(array $config): array
{
    $mysql = (array) $config['db']['mysql'];
    return [
        'driver' => $config['db']['default'] === 'mysql' ? 'mysql' : 'sqlite',
        'sqlite_path' => (string) $config['db']['sqlite']['path'],
        'db_host' => (string) $mysql['host'],
        'db_port' => (string) ($mysql['port'] ?? 3306),
        'db_name' => (string) $mysql['database'],
        'db_user' => (string) $mysql['username'],
        'db_pass' => '',
        'create_database' => '0',
        'site_name' => '蓝屏钙软件导航',
        'site_subtitle' => '纯净 · 高效 · 权威软件下载渠道导航',
        'site_description' => '专门收集整理各类优质常用软件的官方网站、GitHub 仓库及可靠第三方分发渠道。',
        'site_keywords' => '软件导航,官网下载,GitHub镜像,纯净软件,工具软件',
        'base_url' => '',
        'admin_user' => 'admin',
        'admin_pass' => '',
        'admin_pass2' => '',
        'auto_password' => '1',
        'seed' => '1',
    ];
}

/** @return array<string,string> */
function readForm(array $config): array
{
    $defaults = installDefaults($config);
    $out = [];
    foreach ($defaults as $key => $value) {
        $out[$key] = is_string($_POST[$key] ?? null) ? trim((string) $_POST[$key]) : (string) $value;
    }
    $checkbox = static function (string $key) use (&$out): void {
        $out[$key] = !empty($_POST[$key]) ? '1' : '0';
    };
    $checkbox('create_database');
    $checkbox('auto_password');
    $checkbox('seed');
    // db_pass / admin_pass 留在服务端用于本次装机；渲染时那几个输入框永远写死 value=""
    return $out;
}

/** @return string[] 逐条可展示的中文错误 */
function validateInstallForm(array $form, array $config): array
{
    $errors = [];

    if (!in_array($form['driver'], ['sqlite', 'mysql'], true)) {
        $errors[] = '数据库类型只能是 SQLite 或 MySQL。';
    }
    if ($form['driver'] === 'sqlite' && $form['sqlite_path'] === '') {
        $errors[] = 'SQLite 需要填写库文件路径。';
    } elseif ($form['driver'] === 'sqlite'
        && str_starts_with(str_replace('\\', '/', resolveSqlitePath($form['sqlite_path'])),
                           str_replace('\\', '/', BASE_PATH . '/public'))) {
        $errors[] = 'SQLite 库不能放在 public/ 里：一旦 web 根误配到项目根，数据库文件会被直接下载。';
    }
    if ($form['driver'] === 'mysql') {
        if ($form['db_user'] === '') {
            $errors[] = 'MySQL 需要填写用户名。';
        }
        if ($form['db_name'] === '' || !preg_match('/^[A-Za-z0-9_.$-]{1,64}$/', $form['db_name'])) {
            $errors[] = 'MySQL 库名只能用字母、数字与 _ . $ -（1-64 位）。';
        }
        if ($form['db_port'] !== '' && ((int) $form['db_port'] < 1 || (int) $form['db_port'] > 65535)) {
            $errors[] = 'MySQL 端口不在 1-65535 之间。';
        }
        // 装机时最常见的是拿 root 空密码图省事，这里直接拦下
        if ($form['db_pass'] === '' && $form['db_user'] !== '') {
            $errors[] = 'MySQL 密码不能为空（生产环境不要用 root + 空密码）。';
        }
    }

    if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $form['admin_user'])) {
        $errors[] = '管理员账号只允许字母、数字与 _ . -，长度 3-64。';
    }
    $usesAuto = $form['auto_password'] === '1';
    if (!$usesAuto) {
        if (strlen($form['admin_pass']) < 10) {
            $errors[] = '管理员口令至少 10 位。后台暴露在公网，短口令等于没有口令。';
        } elseif (Installer::isPublicAdminPassword($form['admin_pass'])) {
            $errors[] = '不能继续使用种子里那个公开写在文档里的默认口令。';
        } elseif ($form['admin_pass'] !== $form['admin_pass2']) {
            $errors[] = '两次输入的管理员口令不一致。';
        }
    }

    if ($form['site_name'] === '' || mb_strlen($form['site_name']) > 60) {
        $errors[] = '站点名称不能为空，且不超过 60 字。';
    }
    if ($form['base_url'] !== '' && !preg_match('#^https?://[^\s\'"]+$#', $form['base_url'])) {
        $errors[] = '站点地址必须是完整的 http(s) 地址，且不含空格或引号。';
    }
    foreach (['site_subtitle' => 120, 'site_description' => 500, 'site_keywords' => 300] as $key => $max) {
        if (mb_strlen($form[$key]) > $max) {
            $errors[] = "「{$key}」超过 {$max} 字。";
        }
    }
    return $errors;
}

/** @return array{ok:bool,error:?string,result?:array<string,mixed>,env?:string} */
function performInstall(array $form, int $baseline): array
{
    $mysql = [
        'host' => $form['db_host'] !== '' ? $form['db_host'] : '127.0.0.1',
        'port' => (int) ($form['db_port'] !== '' ? $form['db_port'] : 3306),
        'database' => $form['db_name'],
        'username' => $form['db_user'],
        'password' => $form['db_pass'],
        'charset' => 'utf8mb4',
    ];

    $result = Installer::run([
        'driver' => $form['driver'],
        'sqlitePath' => $form['driver'] === 'sqlite' ? resolveSqlitePath($form['sqlite_path']) : '',
        'mysql' => $mysql,
        'createDatabase' => $form['create_database'] === '1',
        'seed' => $form['seed'] === '1',
        'admin' => $form['admin_user'],
        'password' => $form['auto_password'] === '1' ? '' : $form['admin_pass'],
        'baseUrl' => rtrim($form['base_url'], '/'),
        'site' => [
            'site_name' => $form['site_name'],
            'site_subtitle' => $form['site_subtitle'],
            'site_description' => $form['site_description'],
            'site_keywords' => $form['site_keywords'],
        ],
        'baseline' => $baseline,
        // 网页向导永远不覆盖已有数据 —— 上面已经拦过「已安装」，这里是第二道闸
        'reset' => false,
    ]);

    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'result' => $result];
    }

    $env = Installer::writeEnvFile([
        'APP_ENV' => 'production',
        'DB_CONNECTION' => $result['driver'],
        'DB_SQLITE_PATH' => $result['driver'] === 'sqlite' ? resolveSqlitePath($form['sqlite_path']) : '',
        'DB_HOST' => $result['driver'] === 'mysql' ? $mysql['host'] : '',
        'DB_PORT' => $result['driver'] === 'mysql' ? (string) $mysql['port'] : '',
        'DB_DATABASE' => $result['driver'] === 'mysql' ? $mysql['database'] : '',
        'DB_USERNAME' => $result['driver'] === 'mysql' ? $mysql['username'] : '',
        'DB_PASSWORD' => $result['driver'] === 'mysql' ? $mysql['password'] : '',
    ]);
    if (!$env['ok']) {
        return ['ok' => false, 'error' => $env['error'], 'result' => $result];
    }

    $lock = Installer::writeLock([
        'installed_via' => 'web',
        'schema_baseline' => $baseline,
        'driver' => $result['driver'],
        'admin_username' => $result['admin']['username'],
    ]);
    if (!$lock['ok']) {
        return ['ok' => false, 'error' => $lock['error'], 'result' => $result];
    }

    return ['ok' => true, 'error' => null, 'result' => $result, 'env' => $env['path']];
}

/** 相对路径按项目根解析，并统一成分隔符，避免 .env 里出现 C:\a/b 这种混搭。 */
function resolveSqlitePath(string $path): string
{
    if ($path === '') {
        $path = 'database/nav.sqlite';
    }
    if (!($path[0] === '/' || preg_match('#^[A-Za-z]:[\\\\/]|^\\\\#', $path))) {
        $path = BASE_PATH . '/' . $path;
    }
    return DIRECTORY_SEPARATOR === '\\' ? str_replace('/', '\\', $path) : str_replace('\\', '/', $path);
}

/** 装机结果页 —— 生成的口令只在这里出现一次。 */
function renderInstallPage(array $old, array $errors, ?array $succeeded, int $baseline, array $config): void
{
    $e = static fn($v) => View::e((string) $v);
    ?>
<!doctype html>
<html lang="zh-CN">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>安装向导 - <?= $e($old['site_name']) ?></title>
<style>
  :root { --line:#e3e8ef; --ink:#1c2530; --sub:#5b6775; --brand:#0067b8; --bad:#c0392b; --ok:#1a7f4b; }
  * { box-sizing: border-box; }
  body { margin:0; padding:32px 16px 64px; background:#f6f8fb; color:var(--ink);
         font:15px/1.65 system-ui,-apple-system,"Segoe UI","Microsoft YaHei",sans-serif; }
  main { max-width:820px; margin:0 auto; background:#fff; border:1px solid var(--line);
         border-radius:14px; padding:28px 30px 34px; }
  h1 { margin:0 0 4px; font-size:1.4rem; }
  h2 { margin:28px 0 10px; font-size:1rem; padding-bottom:6px; border-bottom:1px solid var(--line); }
  .lead { color:var(--sub); margin:0 0 20px; font-size:.9rem; }
  label { display:block; margin:12px 0 4px; font-size:.86rem; color:var(--sub); }
  input[type=text],input[type=password],input[type=number],textarea,select {
    width:100%; padding:9px 11px; border:1px solid var(--line); border-radius:8px; font:inherit; background:#fff; }
  textarea { min-height:64px; }
  .row { display:grid; gap:14px; grid-template-columns:1fr 1fr; }
  .check { display:flex; gap:8px; align-items:flex-start; margin:14px 0; font-size:.9rem; }
  .check input { margin-top:4px; }
  .btn { margin-top:22px; padding:11px 22px; border:0; border-radius:8px; background:var(--brand);
         color:#fff; font-size:1rem; cursor:pointer; }
  ul.msgs { list-style:none; padding:0; margin:0 0 18px; }
  ul.msgs li { padding:9px 12px; border-radius:8px; margin-bottom:8px; font-size:.9rem; }
  ul.msgs li.bad { background:#fdeceb; color:var(--bad); border:1px solid #f6c9c5; }
  ul.msgs li.ok { background:#eaf7ef; color:var(--ok); border:1px solid #c2e8d0; }
  .pw { font-family:ui-monospace,Consolas,monospace; font-size:1.15rem; background:#f2f6fb;
        border:1px dashed var(--brand); padding:12px 14px; border-radius:8px; word-break:break-all; }
  .hint { font-size:.82rem; color:var(--sub); margin-top:4px; }
  code { background:#f2f4f8; padding:1px 5px; border-radius:4px; }
  .grid2 { display:grid; grid-template-columns:1fr; gap:0; }
</style>
</head>
<body>
<main>
<?php if ($succeeded !== null && $succeeded['ok']): $admin = $succeeded['result']['admin']; ?>
  <h1>安装完成</h1>
  <p class="lead">结构基线 v<?= $baseline ?> · <?= $e($succeeded['result']['driver']) === 'mysql' ? 'MySQL' : 'SQLite' ?>
     · <?= (int) $succeeded['result']['tables'] ?> 张表 · 软件 <?= (int) $succeeded['result']['softwares'] ?> 条</p>
  <ul class="msgs">
    <li class="ok">数据库已建好，配置写入 <code><?= $e($succeeded['env']) ?></code>，安装向导已自锁。</li>
  </ul>

  <h2>管理员账号</h2>
  <p>账号：<strong><?= $e($admin['username']) ?></strong></p>
  <?php if ($admin['generated']): ?>
    <p class="pw"><?= $e($admin['password']) ?></p>
    <p class="hint">这串口令只出现这一次，关掉页面就查不到。请立刻存进密码管理器。</p>
  <?php else: ?>
    <p class="hint">口令是你自己填的，这里不回显。</p>
  <?php endif; ?>

  <h2>接下来</h2>
  <ol class="hint" style="font-size:.9rem;line-height:1.9">
    <li><a href="/">打开前台首页</a> 确认样式与图片正常（样式全 404 说明 web 根没指到 <code>public/</code>）。</li>
    <li><a href="/?r=admin/login">登录后台</a>，在【系统与AI设置】里补 SMTP、验证码、AI 线路与 GitHub 镜像源。</li>
    <li>服务器上跑一次 <code>php deploy/preflight.php</code>，把 WARN 逐条看完。</li>
    <li>建议删掉本文件：<code>rm public/install.php</code>（留着也会 404，但少一个入口总归更好）。</li>
    <li>以后升级：<code>php database/migrate.php</code>，说明见 <code>database/migrations/README.md</code>。</li>
  </ol>
<?php else: ?>
  <h1>安装向导</h1>
  <p class="lead">蓝屏钙软件导航 · 结构基线 v<?= $baseline ?>。装完本页会自动关闭，之后要改配置请进后台。</p>

  <?php if ($errors !== []): ?>
  <ul class="msgs"><?php foreach ($errors as $error): ?><li class="bad"><?= $e($error) ?></li><?php endforeach; ?></ul>
  <?php endif; ?>

  <form method="post" action="install.php" autocomplete="off">
    <?= Auth::csrfField() ?>

    <h2>1. 站点信息</h2>
    <div class="row">
      <div><label for="site_name">站点名称 *</label><input type="text" id="site_name" name="site_name" value="<?= $e($old['site_name']) ?>" required></div>
      <div><label for="site_subtitle">副标题</label><input type="text" id="site_subtitle" name="site_subtitle" value="<?= $e($old['site_subtitle']) ?>"></div>
    </div>
    <label for="site_description">站点简介（用于首页与 SEO description）</label>
    <textarea id="site_description" name="site_description"><?= $e($old['site_description']) ?></textarea>
    <label for="site_keywords">关键词（逗号分隔）</label>
    <input type="text" id="site_keywords" name="site_keywords" value="<?= $e($old['site_keywords']) ?>">
    <label for="base_url">站点地址 site_base_url</label>
    <input type="text" id="base_url" name="base_url" value="<?= $e($old['base_url']) ?>" placeholder="https://example.com">
    <p class="hint">OAuth 回调、审核邮件里的链接、sitemap 都以它为准。这里<b>建议填</b>：留空的话程序会按请求头临时推断域名，
      换域名或被外部探测时就会生成错链接（向导本身不读请求头，所以不替你猜）。</p>

    <h2>2. 数据库</h2>
    <div class="check">
      <input type="radio" id="driver_sqlite" name="driver" value="sqlite" <?= $old['driver'] === 'sqlite' ? 'checked' : '' ?>
             onclick="document.getElementById('box-sqlite').style.display='block';document.getElementById('box-mysql').style.display='none'">
      <label for="driver_sqlite" style="margin:0;color:var(--ink)">SQLite（单机、零配置，默认，毫秒级响应）</label>
    </div>
    <div class="check">
      <input type="radio" id="driver_mysql" name="driver" value="mysql" <?= $old['driver'] === 'mysql' ? 'checked' : '' ?>
             onclick="document.getElementById('box-sqlite').style.display='none';document.getElementById('box-mysql').style.display='block'">
      <label for="driver_mysql" style="margin:0;color:var(--ink)">MySQL（多进程、大数据量）</label>
    </div>

    <div id="box-sqlite" style="<?= $old['driver'] === 'sqlite' ? '' : 'display:none' ?>">
      <label for="sqlite_path">库文件路径</label>
      <input type="text" id="sqlite_path" name="sqlite_path" value="<?= $e($old['sqlite_path']) ?>">
      <p class="hint">相对路径按项目根解析。想更安全就放到 web 根之外，例如 <code>../data/softnav.sqlite</code> 的绝对路径；
                    所在目录必须给 web 进程写权限（WAL 要写 <code>-wal</code> / <code>-shm</code>）。</p>
    </div>

    <div id="box-mysql" class="row" style="<?= $old['driver'] === 'mysql' ? '' : 'display:none' ?>">
      <div><label for="db_host">主机</label><input type="text" id="db_host" name="db_host" value="<?= $e($old['db_host']) ?>"></div>
      <div><label for="db_port">端口</label><input type="text" id="db_port" name="db_port" value="<?= $e($old['db_port']) ?>"></div>
      <div><label for="db_name">数据库名</label><input type="text" id="db_name" name="db_name" value="<?= $e($old['db_name']) ?>"></div>
      <div><label for="db_user">用户名</label><input type="text" id="db_user" name="db_user" value="<?= $e($old['db_user']) ?>"></div>
      <div><label for="db_pass">密码</label><input type="password" id="db_pass" name="db_pass" value=""></div>
      <div class="check" style="margin-top:26px">
        <input type="checkbox" id="create_database" name="create_database" value="1" <?= $old['create_database'] === '1' ? 'checked' : '' ?>>
        <label for="create_database" style="margin:0;color:var(--ink)">数据库不存在时自动创建（utf8mb4_unicode_ci）</label>
      </div>
    </div>

    <h2>3. 管理员账号</h2>
    <div class="row">
      <div><label for="admin_user">登录账号 *</label><input type="text" id="admin_user" name="admin_user" value="<?= $e($old['admin_user']) ?>"></div>
      <div class="check" style="margin-top:34px">
        <input type="checkbox" id="auto_password" name="auto_password" value="1" <?= $old['auto_password'] === '1' ? 'checked' : '' ?>
               onclick="document.getElementById('pwbox').style.display=this.checked?'none':'block'">
        <label for="auto_password" style="margin:0;color:var(--ink)">自动生成强口令（推荐，装完只展示一次）</label>
      </div>
    </div>
    <div id="pwbox" class="row" style="<?= $old['auto_password'] === '1' ? 'display:none' : '' ?>">
      <div><label for="admin_pass">管理员口令（≥10 位）</label><input type="password" id="admin_pass" name="admin_pass" value=""></div>
      <div><label for="admin_pass2">再输一次</label><input type="password" id="admin_pass2" name="admin_pass2" value=""></div>
    </div>
    <p class="hint">种子里的默认口令 <code>admin123</code> 会被彻底覆盖 —— 它写在公开文档里，留着等于把后台送出去。</p>

    <h2>4. 初始数据</h2>
    <div class="check">
      <input type="checkbox" id="seed" name="seed" value="1" <?= $old['seed'] === '1' ? 'checked' : '' ?>>
      <label for="seed" style="margin:0;color:var(--ink)">导入示例数据（12 个分类、5 个 GitHub 镜像源、公告、示例软件 10 条）</label>
    </div>
    <p class="hint">不勾则只建表并写入最小必要配置，分类和软件之后在后台自己录。</p>

    <button class="btn" type="submit">安装</button>
    <p class="hint">提交后本页会写入 <code>config/.env</code> 与 <code>config/install.lock</code>，随后自动关闭。</p>
  </form>
<?php endif; ?>
</main>
</body>
</html>
<?php
}
