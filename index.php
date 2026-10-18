<?php
/**
 * 根目录引导文件（路由脚本模式）
 *
 * 用法（二选一）：
 *   php -S localhost:8000 -t public      ← 推荐，静态资源由内置服务器原生处理
 *   php -S localhost:8000 index.php      ← 本文件作为路由脚本
 *
 * 说明：内置服务器在「未开启 -t」时，并不会为缺失的路径回退到上级 index.php，
 * 因此若要直接使用 `php -S localhost:8000`（以项目根为文档根），
 * 必须显式把本文件指定为路由脚本。
 */

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';

$publicDir = realpath(__DIR__ . '/public');

// public/ 下也有一个 index.php，若不排除本路径，会被下面的静态判断命中，
// 请求原样回吐成一个 200 空白页而无法进入主入口。
if ($uri !== '/' && $uri !== '/index.php' && $publicDir !== false) {
    $candidate = realpath($publicDir . $uri);

    // 命中 public 下的真实文件时，交还给内置服务器处理（正确的 MIME / Range / 缓存头）
    if (
        $candidate !== false
        && is_file($candidate)
        && str_starts_with($candidate, $publicDir . DIRECTORY_SEPARATOR)
    ) {
        return false;
    }
}

// 其余请求统一交给主入口分发
require __DIR__ . '/public/index.php';
