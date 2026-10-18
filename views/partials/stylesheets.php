<?php
/**
 * 应用样式表清单。
 *
 * 这些文件原本是单个 style.css，按分节拆开后必须仍按原顺序加载 ——
 * 层叠优先级依赖顺序（末尾的移动端适配层就是靠位置赢优先级的）。
 */
foreach (['style.css', 'store-detail.css', 'motion.css', 'admin.css', 'auth-responsive.css'] as $css) {
    $file = BASE_PATH . '/public/assets/css/' . $css;
    echo '  <link rel="stylesheet" href="' . Url::basePath() . 'assets/css/' . $css . '?v=' . filemtime($file) . '">' . "\n";
}
