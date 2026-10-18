<?php
/**
 * 蓝屏钙软件导航网 - 主入口
 */

// 定义根目录
define('BASE_PATH', dirname(__DIR__));
define('VIEW_PATH', BASE_PATH . '/views');
define('CORE_PATH', BASE_PATH . '/core');
define('MODEL_PATH', BASE_PATH . '/models');
define('SERVICE_PATH', BASE_PATH . '/services');
define('CONTROLLER_PATH', BASE_PATH . '/controllers');

// 载入全局配置，确定 APP_ENV / APP_DEBUG（Router 与视图都依赖这两个常量）
$config = require_once BASE_PATH . '/config/config.php';

// SQLite 且库还不存在 = 这台机器没装过。给一个明确的引导页：
// 既不抛 500 白屏，也绝不「顺手建库」—— 否则谁先访问一次首页，谁就把站点装成了默认口令。
if (($config['db']['default'] ?? 'sqlite') === 'sqlite' && !is_file((string) $config['db']['sqlite']['path'])) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('X-Robots-Tag: noindex, nofollow');
    echo '<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>需要安装</title><body style="font:16px/1.7 system-ui,-apple-system,\'Microsoft YaHei\',sans-serif;'
        . 'max-width:38em;margin:5rem auto;padding:0 1rem;color:#1c2530">'
        . '<h1 style="font-size:1.3rem">站点尚未安装</h1>'
        . '<p>这是第一次在这台机器上运行，还没有数据库。打开 <a href="install.php">安装向导</a> 配好数据库、站点信息与管理员账号即可，'
        . '或者在服务器上执行 <code style="background:#f2f4f8;padding:2px 6px;border-radius:4px">php database/install.php</code>。</p>'
        . '<p style="color:#5b6775;font-size:.9rem">安装完成后本提示自动消失。为防误触发，向导只能使用一次。</p></body>';
    exit;
}

// 调试模式才在页面回显错误，生产一律只进日志，避免堆栈与 SQL 外泄
error_reporting(E_ALL);
ini_set('display_errors', APP_DEBUG ? '1' : '0');
if (!APP_DEBUG) {
    ini_set('log_errors', '1');
}

// 自动加载类定义
spl_autoload_register(function ($class) {
    $directories = [
        CORE_PATH,
        MODEL_PATH,
        SERVICE_PATH,
        CONTROLLER_PATH
    ];

    foreach ($directories as $dir) {
        $file = $dir . '/' . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// 初始化 Session
Auth::start();

// 调度路由
Router::dispatch();
