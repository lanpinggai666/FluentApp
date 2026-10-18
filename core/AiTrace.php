<?php
/**
 * AI 一次补全的实时事件流（后台「AI 智能补全」点下去之后，页面上那张对话面板就读这里）。
 *
 * 为什么不是流式响应：补全是同步跑在这次 POST 里的，而站点在 Cloudflare 与 php-fpm 后面，
 * 中间任何一层攒着不发，前端就只能等到最后一刻 —— 表现和转圈圈没区别。
 * 改成「POST 照旧同步跑 + 前端每秒问一次进度」，任何主机形态都能work，也不需要常驻进程。
 *
 * 有一道限制要守住：这里落的是**临时文件**（共享主机上别的进程也可能读到），
 * 所以只写形状不写正文 —— 提示词一个字都不进这里，AI 的回复也只在解析完成后
 * 以「写了哪几个字段、值是什么」的形态出现（那些值本来就要写进库，不构成新的暴露）。
 */
class AiTrace
{
    /** 事件文件留多久：面板早就关了的孤儿文件靠这个清掉 */
    private const TTL = 1800;

    /** 当前请求正在往哪个 run 写（非补全请求为空，push() 直接 no-op） */
    private static string $run = '';

    /** 本次请求已写了几条：靠计数器挡失控循环，不去每写一条都数一遍文件行数 */
    private static int $count = 0;

    public static function begin(string $run): string
    {
        if (!preg_match('/^[a-f0-9]{16,48}$/i', $run)) {
            return '';
        }
        self::$run = strtolower($run);
        self::$count = 0;
        self::sweepStale();
        self::push('start', '补全请求已受理', []);
        return self::$run;
    }

    public static function active(): bool
    {
        return self::$run !== '';
    }

    /**
     * @param string $kind 事件类型（面板按它决定图标与文案）：start/plan/switch/request/assistant/tool/
     *                     retry/applied/fallback/error/done
     * @param array<string,mixed> $meta 只放可公开的结构化信息
     */
    public static function push(string $kind, string $text, array $meta = []): void
    {
        if (self::$run === '') {
            return;
        }
        $file = self::file(self::$run);
        // 上限只挡「某个循环疯了」把 temp 写爆；done 必须落进去，
        // 它没了前端就只能一直轮到 stale 判定才收场
        if (self::$count >= 200 && $kind !== 'done') {
            return;
        }
        $line = json_encode([
            'at' => round(microtime(true), 3),
            'kind' => $kind,
            'text' => mb_substr($text, 0, 300),
            'meta' => $meta,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        if (@file_put_contents($file, $line . "\n", FILE_APPEND | LOCK_EX) !== false) {
            self::$count++;
        }
    }

    /**
     * 读第 $after 条之后的事件。
     * @return array{events:array<int,array<string,mixed>>, next:int, done:bool, stale:bool}
     */
    public static function read(string $run, int $after = 0): array
    {
        if (!preg_match('/^[a-f0-9]{16,48}$/i', $run)) {
            return ['events' => [], 'next' => $after, 'done' => true, 'stale' => false];
        }
        $file = self::file(strtolower($run));
        if (!is_file($file)) {
            // 文件还没被那个 POST 建出来 —— 前端第一次轮询往往正好落在这个瞬间。
            // 这里绝不能报 stale：面板会当成「这一轮没回音」当场停止，功能就永远显示不出来了。
            return ['events' => [], 'next' => $after, 'done' => false, 'stale' => false];
        }
        $lines = preg_split('/\n/', rtrim((string) file_get_contents($file), "\n")) ?: [];
        $events = [];
        $done = false;
        foreach ($lines as $i => $line) {
            if ($i < $after || count($events) >= 40) {
                continue;
            }
            $row = json_decode((string) $line, true);
            if (!is_array($row)) {
                continue;
            }
            $row['seq'] = $i + 1;
            $done = $done || ($row['kind'] ?? '') === 'done';
            $events[] = $row;
        }
        return [
            'events' => $events,
            'next' => min(count($lines), $after + 40),
            'done' => $done,
            // 没有 done 事件又三分钟没动静：PHP 被 max_execution_time 掐了，别再让前端轮询
            'stale' => !$done && (time() - (int) filemtime($file) > 180),
        ];
    }

    /** 补全请求结束的标记（成功失败都要有，否则前端一直轮到超时） */
    public static function finish(array $summary): void
    {
        self::push('done', (string) ($summary['note'] ?? '完成'), $summary);
        self::$run = '';
    }

    private static function file(string $run): string
    {
        $dir = rtrim((string) (ini_get('upload_tmp_dir') ?: sys_get_temp_dir()), '\\/') . '/softnav_ai';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        return $dir . '/trace-' . $run . '.ndjson';
    }

    /** 顺手清掉过期文件：只在 begin 时跑，避免每次 push 都扫目录 */
    private static function sweepStale(): void
    {
        $dir = dirname(self::file('0'));
        foreach (glob($dir . '/trace-*.ndjson') ?: [] as $file) {
            if (time() - (int) filemtime($file) > self::TTL) {
                @unlink($file);
            }
        }
    }
}
