<?php
/**
 * 蓝屏钙软件导航网 - Microsoft Fluent System Icons 官方纯矢量引擎
 * 直接引用并渲染自微软官方 github.com/microsoft/fluentui-system-icons 源码库
 */

class FluentIcon {
    private static array $cache = [];

    public static function render(string $name, string $className = 'fluent-icon'): string {
        $name = strtolower(trim($name));

        // 别名与映射
        $aliases = [
            'store' => 'microsoft-store',
            'star_filled' => 'star-filled',
            'free' => 'money',
            'open_source' => 'code',
            'developer' => 'person'
        ];
        if (isset($aliases[$name])) {
            $name = $aliases[$name];
        }

        if (!isset(self::$cache[$name])) {
            $iconPath = BASE_PATH . '/public/assets/fluent-icons/' . $name . '.svg';
            if (file_exists($iconPath)) {
                $svg = file_get_contents($iconPath);
                $svg = preg_replace('/<\?xml.*?\?>/i', '', $svg);
                $svg = str_ireplace('fill="#212121"', 'fill="currentColor"', $svg);
                self::$cache[$name] = trim($svg);
            } elseif ($name === 'github') {
                self::$cache[$name] = '<svg viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" clip-rule="evenodd" d="M10 2C5.58 2 2 5.58 2 10c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.012 8.012 0 0 0 18 10c0-4.42-3.58-8-8-8Z"/></svg>';
            } else {
                $defaultPath = BASE_PATH . '/public/assets/fluent-icons/folder.svg';
                self::$cache[$name] = file_exists($defaultPath) ? trim(file_get_contents($defaultPath)) : '<svg viewBox="0 0 20 20" fill="currentColor"><path d="M3.5 4A1.5 1.5 0 0 0 2 5.5v9A1.5 1.5 0 0 0 3.5 16h13a1.5 1.5 0 0 0 1.5-1.5v-7A1.5 1.5 0 0 0 16.5 6h-6.28l-1.9-1.63A1.5 1.5 0 0 0 7.34 4H3.5Z"/></svg>';
            }
        }

        $svg = self::$cache[$name];
        if ($className && str_contains($svg, '<svg')) {
            return preg_replace('/<svg\s+/i', '<svg class="' . htmlspecialchars($className) . '" ', $svg, 1);
        }
        return $svg;
    }
}
