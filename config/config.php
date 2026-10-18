<?php
/**
 * 蓝屏钙软件导航网 - 全局配置文件
 */

// 基础目录
defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('VIEW_PATH') || define('VIEW_PATH', BASE_PATH . '/views');
defined('CORE_PATH') || define('CORE_PATH', BASE_PATH . '/core');
defined('MODEL_PATH') || define('MODEL_PATH', BASE_PATH . '/models');
defined('SERVICE_PATH') || define('SERVICE_PATH', BASE_PATH . '/services');
defined('CONTROLLER_PATH') || define('CONTROLLER_PATH', BASE_PATH . '/controllers');

// 运行模式与环境
// 读取顺序：真实环境变量 > config/.env > 代码内默认值。
// .env 是给「不方便配环境变量」的机器（Nginx+PHP-FPM、宝塔、共享主机）准备的落盘配置，
// 容器里用 -e 注入即可覆盖，不必改文件。
$softnavEnvFile = __DIR__ . '/.env';
$softnavEnv = is_file($softnavEnvFile) ? (parse_ini_file($softnavEnvFile, false, INI_SCANNER_RAW) ?: []) : [];
$env = static function (string $key) use ($softnavEnv): string {
    $fromSystem = getenv($key);
    if ($fromSystem !== false && $fromSystem !== '') {
        return $fromSystem;
    }
    $value = $softnavEnv[$key] ?? '';
    // INI_SCANNER_RAW 保留原样字符串，这里只剥掉成对的引号
    if (strlen($value) > 1 && ($value[0] === '"' || $value[0] === "'") && $value[strlen($value) - 1] === $value[0]) {
        $value = substr($value, 1, -1);
    }
    return trim($value);
};

defined('APP_ENV') || define('APP_ENV', $env('APP_ENV') ?: 'production');
// 调试回显：生产默认关闭，只有显式 APP_DEBUG=1 / APP_ENV=development，或本地内置服务器（cli / cli-server）才开启
defined('APP_DEBUG') || define('APP_DEBUG', filter_var($env('APP_DEBUG'), FILTER_VALIDATE_BOOLEAN)
    || in_array(APP_ENV, ['development', 'local'], true)
    || in_array(PHP_SAPI, ['cli', 'cli-server'], true));

// 数据库配置 (优先使用 SQLite 保证极速毫秒级响应，支持通过环境变量切换为 MySQL)
return [
    'db' => [
        'default' => $env('DB_CONNECTION') ?: 'sqlite', // 'sqlite', 'mysql', 'auto'
        'mysql' => [
            'host' => $env('DB_HOST') ?: '127.0.0.1',
            'port' => (int) ($env('DB_PORT') ?: 3306),
            'database' => $env('DB_DATABASE') ?: 'software_nav',
            'username' => $env('DB_USERNAME') ?: 'root',
            'password' => $env('DB_PASSWORD'),
            'charset' => 'utf8mb4',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        ],
        'sqlite' => [
            // 建议生产写到 web 根之外（例如 /var/www/data/softnav.sqlite），
            // 这样即使 web 根误配成项目根，数据库文件也不在可下载路径里。
            'path' => $env('DB_SQLITE_PATH') ?: BASE_PATH . '/database/nav.sqlite',
            'options' => [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        ]
    ],
    'app' => [
        'name' => '蓝屏钙软件导航',
        'version' => '1.0.0',
        'admin_session_key' => 'admin_user_session',
    ]
];
