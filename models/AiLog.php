<?php
/**
 * AI 调用日志：一次 HTTP 往返一行（容灾切换会有多行）。
 *
 * 记的是「花了多久、哪个线路、什么用途、成功没、错误原话、token 用量」；
 * 绝不记 prompt 正文与 API 密钥 —— 提示词里可能带用户提交的内容，密钥一旦落库就等于泄露面翻倍。
 */

class AiLog
{
    public static function write(array $row): void
    {
        try {
            Database::insert('ai_logs', [
                'provider' => mb_substr((string) ($row['provider'] ?? ''), 0, 60),
                'api_base' => mb_substr((string) ($row['api_base'] ?? ''), 0, 200),
                'model' => mb_substr((string) ($row['model'] ?? ''), 0, 80),
                'purpose' => mb_substr((string) ($row['purpose'] ?? 'chat'), 0, 40),
                'http_status' => (int) ($row['http_status'] ?? 0),
                'result' => ($row['result'] ?? 'ok') === 'ok' ? 'ok' : 'error',
                'latency_ms' => max(0, (int) ($row['latency_ms'] ?? 0)),
                'prompt_tokens' => isset($row['prompt_tokens']) ? (int) $row['prompt_tokens'] : null,
                'completion_tokens' => isset($row['completion_tokens']) ? (int) $row['completion_tokens'] : null,
                'total_tokens' => isset($row['total_tokens']) ? (int) $row['total_tokens'] : null,
                // 工具轮数与调用次数：看不出这两项，就没法解释一次补全为什么比上次慢十倍
                'tool_rounds' => max(0, (int) ($row['tool_rounds'] ?? 0)),
                'tool_calls' => max(0, (int) ($row['tool_calls'] ?? 0)),
                'detail' => isset($row['detail']) && $row['detail'] !== ''
                    ? mb_substr(preg_replace('/\s+/u', ' ', (string) $row['detail']), 0, 255) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            // 与 api_logs 同样的低概率顺手清理：没人配 cron 也不会无限涨
            if (random_int(1, 50) === 1) {
                self::prune(30);
            }
        } catch (Throwable $e) {
            // 日志失败不能拖垮一次 AI 调用
            error_log('[SoftNav] ai log failed: ' . $e->getMessage());
        }
    }

    /** @return array<int,array> */
    public static function recent(int $limit = 30): array
    {
        $limit = max(1, min(200, $limit));
        return Database::fetchAll('SELECT * FROM ai_logs ORDER BY id DESC LIMIT ' . $limit);
    }

    /** 最近 $days 天的汇总：调用数、失败数、平均耗时、token 合计 */
    public static function summary(int $days = 7): array
    {
        $since = date('Y-m-d H:i:s', time() - max(1, $days) * 86400);
        $row = Database::fetch(
            'SELECT COUNT(*) AS calls,
                    SUM(CASE WHEN result <> :ok THEN 1 ELSE 0 END) AS failures,
                    ROUND(AVG(latency_ms)) AS avg_latency_ms,
                    SUM(COALESCE(total_tokens, 0)) AS total_tokens
             FROM ai_logs WHERE created_at >= :since',
            [':ok' => 'ok', ':since' => $since]
        ) ?: [];
        return [
            'days' => $days,
            'calls' => (int) ($row['calls'] ?? 0),
            'failures' => (int) ($row['failures'] ?? 0),
            'avg_latency_ms' => (int) ($row['avg_latency_ms'] ?? 0),
            'total_tokens' => (int) ($row['total_tokens'] ?? 0),
        ];
    }

    public static function prune(int $keepDays = 30): int
    {
        return Database::delete('ai_logs', 'created_at < :t',
            ['t' => date('Y-m-d H:i:s', time() - max(1, $keepDays) * 86400)]);
    }
}
