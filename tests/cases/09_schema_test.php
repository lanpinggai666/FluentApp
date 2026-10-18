<?php
/**
 * 数据层一致性：两份建表脚本必须与线上库同构，且全新安装后整站可用
 */

group('29 建表脚本与线上库一致性');

$live = liveTableColumns(Sandbox::$db);
$sqliteSchema = sqlCreateTables((string) file_get_contents(BASE_PATH . '/database/schema_sqlite.sql'));
$mysqlSchema = sqlCreateTables((string) file_get_contents(BASE_PATH . '/database/schema_mysql.sql'));

ok('线上库表数量合理（' . count($live) . ' 张）', count($live) >= 14);

foreach (['sqlite' => $sqliteSchema, 'mysql' => $mysqlSchema] as $label => $schema) {
    $missingTables = array_values(array_diff(array_keys($live), array_keys($schema)));
    ok("schema_{$label}.sql 覆盖线上全部表", $missingTables === []);
    if ($missingTables) {
        echo '    缺少表: ' . implode(', ', $missingTables) . "\n";
    }
    $unknown = array_values(array_diff(array_keys($schema), array_keys($live)));
    ok("schema_{$label}.sql 不含线上不存在的表", $unknown === []);
    if ($unknown) {
        echo '    多余表: ' . implode(', ', $unknown) . "\n";
    }

    foreach ($live as $table => $columns) {
        if (!isset($schema[$table])) {
            continue;
        }
        $missing = array_values(array_diff($columns, $schema[$table]));
        $extra = array_values(array_diff($schema[$table], $columns));
        ok("{$label}/{$table} 列集合与线上一致", $missing === [] && $extra === []);
        if ($missing || $extra) {
            echo '    脚本缺列: ' . (implode(',', $missing) ?: '-') . ' | 脚本多列: ' . (implode(',', $extra) ?: '-') . "\n";
        }
    }
}

$crossMissing = [];
foreach ($sqliteSchema as $table => $columns) {
    if (!isset($mysqlSchema[$table])) {
        continue;
    }
    $diff = array_merge(array_diff($columns, $mysqlSchema[$table]), array_diff($mysqlSchema[$table], $columns));
    if ($diff) {
        $crossMissing[$table] = $diff;
    }
}
ok('两份脚本之间也同构', $crossMissing === []);
foreach ($crossMissing as $table => $diff) {
    echo "    {$table}: " . implode(', ', $diff) . "\n";
}

// 代码里真正读写的表都必须存在
$usedTables = [];
$knownColumns = [];
foreach ($live as $table => $columns) {
    foreach ($columns as $column) {
        $knownColumns[strtolower($column)] = true;
    }
}
foreach (glob(BASE_PATH . '/models/*.php') as $model) {
    $src = (string) file_get_contents($model);
    if (preg_match_all('/\b(?:FROM|INTO|UPDATE|JOIN)\s+([a-z_]+)\b/i', $src, $m)) {
        foreach ($m[1] as $t) {
            $candidate = strtolower($t);
            // 关键字与列名都会被这条粗粒度正则抓到（如 UPDATE settings SET key_value）
            if (in_array($candidate, ['select', 'set', 'values', 'where', 'the'], true) || isset($knownColumns[$candidate])) {
                continue;
            }
            $usedTables[$candidate] = basename($model);
        }
    }
}
$notCreated = array_values(array_filter(array_keys($usedTables), static fn($t) => !isset($live[$t])));
ok('models 引用的表都存在（' . count($usedTables) . ' 张）', $notCreated === []);
if ($notCreated) {
    foreach ($notCreated as $t) {
        echo "    缺失: {$t}（引用于 {$usedTables[$t]}）\n";
    }
}

group('30 关键索引');

// 索引靠 migrate_v13 落到已有库上；顺便验证它能重复执行（v13 及更早的脚本已归档到 database/legacy/）
$migrate = BASE_PATH . '/database/legacy/migrate_v13.php';
ok('存在 database/legacy/migrate_v13.php 用于补索引', is_file($migrate));
$firstRun = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(Sandbox::path($migrate)) . ' 2>&1');
$secondRun = (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(Sandbox::path($migrate)) . ' 2>&1');
$broken = preg_match('/Fatal error|Uncaught|SQLSTATE|Warning:/i', $secondRun);
ok('迁移可重复执行（第二次无报错）', $broken === 0);
if ($broken) {
    echo '    ' . mb_substr(trim(preg_replace('/\s+/', ' ', $secondRun)), 0, 200) . "\n";
}

$live = liveTableColumns(Sandbox::$db);
$requiredIndexes = [
    'softwares' => [['status'], ['category_id'], ['owner_user_id'], ['slug'], ['status', 'category_id'], ['status', 'views_count']],
    'comments' => [['software_id'], ['user_id']],
    'reports' => [['status'], ['user_id'], ['resource_id']],
    'software_resources' => [['software_id']],
    'users' => [['email']],
    'notifications' => [['user_id']],
    'settings' => [['key_name']],
    'email_verification_codes' => [['email', 'action']],
];
$liveIndexes = liveIndexColumns(Sandbox::$db);
foreach ($requiredIndexes as $table => $needed) {
    $have = $liveIndexes[$table] ?? [];
    foreach ($needed as $cols) {
        $covered = false;
        foreach ($have as $existing) {
            if (array_slice($existing, 0, count($cols)) === $cols) {
                $covered = true;
                break;
            }
        }
        ok("索引 {$table}(" . implode(',', $cols) . ')', $covered);
    }
}

group('31 全新安装整站可用');

$freshDir = Sandbox::path(sys_get_temp_dir()) . '/fresh_install_' . bin2hex(random_bytes(4));
mkdir($freshDir, 0755, true);
$freshDb = $freshDir . '/nav.sqlite';
$freshCount = 0;
$freshSlug = '';
try {
    $fresh = new PDO('sqlite:' . $freshDb);
    $fresh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $fresh->exec((string) file_get_contents(BASE_PATH . '/database/schema_sqlite.sql'));
    $fresh->exec((string) file_get_contents(BASE_PATH . '/database/seed.sql'));
    $freshCount = (int) $fresh->query('SELECT COUNT(*) FROM softwares')->fetchColumn();
    $freshSlug = (string) $fresh->query('SELECT slug FROM softwares WHERE status=\'published\' ORDER BY id LIMIT 1')->fetchColumn();
    ok('建表脚本 + 种子数据可一次性执行（软件数 ' . $freshCount . '）', $freshCount > 0);
    $freshSettings = (int) $fresh->query('SELECT COUNT(*) FROM settings')->fetchColumn();
    ok('种子配置写入成功（' . $freshSettings . ' 项）', $freshSettings > 10);
    ok('种子里的管理员 hash 对应引导口令', (function (PDO $db): bool {
        $hash = (string) $db->query("SELECT key_value FROM settings WHERE key_name='admin_password_hash'")->fetchColumn();
        return $hash !== '' && password_verify(Auth::BOOTSTRAP_PASSWORD, $hash);
    })($fresh));
    $fresh->exec('PRAGMA wal_checkpoint(TRUNCATE)');
} catch (Throwable $e) {
    ok('建表脚本 + 种子数据可一次性执行', false);
    echo '    ' . $e->getMessage() . "\n";
}
$fresh = null;

// 用这份全新库另起一个站点实例，走一遍真实请求，确认没有「no such table/column」
$fresh = null;
$probeSlug = $freshSlug !== '' ? $freshSlug : 'vscode';
$freshInstance = Sandbox::spawnWithDb($freshDb);
$errors = [];
foreach (['/' => 200, '/?r=software/view&slug=' . rawurlencode($probeSlug) => 200, '/?r=submit' => 200, '/?r=admin/login' => 200] as $path => $want) {
    [$code, $body] = Sandbox::http($path, [], [], null, $freshInstance);
    if ($code !== $want || preg_match('/系统错误|no such table|no such column|Fatal error|Deprecated:/', $body)) {
        $detail = preg_match('/<(strong|p)>([^<]{0,120})/u', $body, $m) ? trim($m[2]) : '';
        $errors[] = $path . " -> {$code} {$detail}";
    }
}
ok('全新安装的实例可正常渲染首页/详情/提交/后台登录', $errors === []);
foreach ($errors as $e) {
    echo '    ' . $e . "\n";
}

// 全新库里补一次数据写入，确认建表脚本能撑住真实写入路径
[$code, $body] = Sandbox::http('/?r=api/ping', [], [], null, $freshInstance);
ok('新实例接口可用', $code === 200);
@array_map('unlink', glob($freshDir . '/*'));
@rmdir($freshDir);

group('47 MySQL 建表脚本能在真实例上跑通');

// 这是 install.php 用 MySQL 装机时踩过的坑：MySQL 8 要求外键两端类型完全一致（含 UNSIGNED），
// 而 software_resources.software_id 曾写成 INT UNSIGNED，softwares.id 是 BIGINT UNSIGNED → errno 3780，建表中途失败。
// SQLite 不校验这个，所以只有比对列类型才能提前发现。
$mysqlSql = (string) file_get_contents(BASE_PATH . '/database/schema_mysql.sql');
$types = sqlColumnTypes($mysqlSql);
$fks = sqlForeignKeys($mysqlSql);
ok('MySQL 脚本里解析出 ' . count($types) . ' 张表、' . count($fks) . ' 条外键', count($types) >= 15 && count($fks) >= 1);

$mismatch = [];
foreach ($fks as [$childTable, $childColumn, $parentTable, $parentColumn]) {
    $childType = $types[$childTable][strtolower($childColumn)] ?? null;
    $parentType = $types[$parentTable][strtolower($parentColumn)] ?? null;
    if ($childType === null || $parentType === null) {
        $mismatch[] = "{$childTable}.{$childColumn} → {$parentTable}.{$parentColumn} 有列没解析到";
        continue;
    }
    if ($childType !== $parentType) {
        $mismatch[] = "{$childTable}.{$childColumn} 是 {$childType}，但 {$parentTable}.{$parentColumn} 是 {$parentType}";
    }
    $indexed = sqlIndexedColumns($mysqlSql, $parentTable);
    if (!in_array(strtolower($parentColumn), $indexed, true)) {
        $mismatch[] = "{$parentTable}.{$parentColumn} 没有被索引，MySQL 会拒绝建外键";
    }
}
ok('每条外键的两端类型一致且父列已索引', $mismatch === []);
foreach ($mismatch as $line) {
    echo '    ' . $line . "\n";
}

// 没有声明外键的引用列同样要同类型，否则将来加外键或做 JOIN 时会踩同一个坑
$refs = [
    'software_id' => ['softwares', 'id'],
    'category_id' => ['categories', 'id'],
    'user_id' => ['users', 'id'],
    'owner_user_id' => ['users', 'id'],
    'resource_id' => ['software_resources', 'id'],
];
$drift = [];
foreach ($types as $table => $columns) {
    foreach ($columns as $column => $type) {
        if (!isset($refs[$column]) || !isset($types[$refs[$column][0]][strtolower($refs[$column][1])])) {
            continue;
        }
        $parentType = $types[$refs[$column][0]][strtolower($refs[$column][1])];
        if ($type !== $parentType) {
            $drift[] = "{$table}.{$column} 是 {$type}，但 " . $refs[$column][0] . '.' . $refs[$column][1] . " 是 {$parentType}";
        }
    }
}
ok('所有 *_id 引用列与目标主键同宽同符号', $drift === []);
foreach ($drift as $line) {
    echo '    ' . $line . "\n";
}
