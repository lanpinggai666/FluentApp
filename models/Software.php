<?php
/**
 * 蓝屏钙软件导航网 - 软件模型 (支持百万级索引性能优化)
 */

class Software {
    public static function find(int $id): ?array {
        $sql = "SELECT s.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE s.id = :id";
        return Database::fetch($sql, ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array {
        $sql = "SELECT s.*, c.name as category_name, c.slug as category_slug, c.icon as category_icon 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE s.slug = :slug";
        return Database::fetch($sql, ['slug' => $slug]);
    }

    /**
     * 列表与计数共用的筛选条件。
     *
     * 两处以前各写一份 WHERE，count 少了 developer 与 featured_only，
     * 搜索结果集和分页总数对不上，翻下一页会出现「说有数据其实是空的」。
     *
     * @return array{0: list<string>, 1: array<string, mixed>}
     */
    private static function filter(array $options): array {
        $where = ["s.status = 'published'"];
        $params = [];

        if (!empty($options['category_id'])) {
            $where[] = 's.category_id = :cat_id';
            $params['cat_id'] = (int) $options['category_id'];
        }

        if (!empty($options['search'])) {
            $keyword = '%' . trim((string) $options['search']) . '%';
            $where[] = '(s.name LIKE :kw_name OR s.special_notes LIKE :kw_notes OR s.tags LIKE :kw_tags OR s.developer LIKE :kw_dev)';
            $params['kw_name'] = $keyword;
            $params['kw_notes'] = $keyword;
            $params['kw_tags'] = $keyword;
            $params['kw_dev'] = $keyword;
        }

        if (!empty($options['featured_only'])) {
            $where[] = 's.is_featured = 1';
        }

        return [$where, $params];
    }

    public static function getList(array $options = []): array {
        [$where, $params] = self::filter($options);
        $whereClause = implode(' AND ', $where);

        $sort = $options['sort'] ?? 'random';
        $driver = Database::getDriver();

        switch ($sort) {
            case 'views':
                $orderBy = "s.views_count DESC, s.id DESC";
                break;
            case 'rating':
                $orderBy = "s.rating_avg DESC, s.rating_count DESC";
                break;
            case 'latest':
                $orderBy = "s.id DESC";
                break;
            case 'random':
            default:
                $orderBy = ($driver === 'sqlite') ? "RANDOM()" : "RAND()";
                break;
        }

        $limit = (int) ($options['limit'] ?? 24);
        $offset = (int) ($options['offset'] ?? 0);

        $sql = "SELECT s.*, c.name as category_name, c.slug as category_slug 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE {$whereClause} 
                ORDER BY {$orderBy} 
                LIMIT {$limit} OFFSET {$offset}";

        return Database::fetchAll($sql, $params);
    }

    public static function count(array $options = []): int {
        [$where, $params] = self::filter($options);
        $whereClause = implode(' AND ', $where);

        $res = Database::fetch("SELECT COUNT(*) as total FROM softwares s WHERE {$whereClause}", $params);
        return (int) ($res['total'] ?? 0);
    }

    public static function getRelated(int $categoryId, int $excludeId, int $limit = 4): array {
        $driver = Database::getDriver();
        $randomOrder = ($driver === 'sqlite') ? "RANDOM()" : "RAND()";
        $sql = "SELECT s.*, c.name as category_name 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE s.category_id = :cat_id AND s.id != :ex_id AND s.status = 'published'
                ORDER BY {$randomOrder} 
                LIMIT {$limit}";
        return Database::fetchAll($sql, ['cat_id' => $categoryId, 'ex_id' => $excludeId]);
    }

    public static function getPendingList(): array {
        $sql = "SELECT s.*, c.name as category_name 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                WHERE s.status = 'pending' 
                ORDER BY s.id DESC";
        return Database::fetchAll($sql);
    }

    public static function getAllAdmin(int $limit = 50, int $offset = 0): array {
        $sql = "SELECT s.*, c.name as category_name 
                FROM softwares s 
                LEFT JOIN categories c ON s.category_id = c.id 
                ORDER BY s.id DESC 
                LIMIT {$limit} OFFSET {$offset}";
        return Database::fetchAll($sql);
    }

    public static function create(array $data): int {
        if (empty($data['slug'])) {
            $data['slug'] = self::generateSlug($data['name']);
        }
        $data = self::deriveSummary($data);
        return Database::insert('softwares', $data);
    }

    public static function update(int $id, array $data): int {
        $data = self::deriveSummary($data);
        return Database::update('softwares', $data, 'id = :id', ['id' => $id]);
    }

    /**
     * summary 不再由表单收集，而是从软件说明（description_md）的首句派生。
     * 只在本次写入确实带了 description_md 时才重算，避免局部更新把简介抹掉。
     */
    private static function deriveSummary(array $data): array {
        if (!array_key_exists('description_md', $data)) {
            return $data;
        }
        $summary = Markdown::summaryFrom((string) ($data['description_md'] ?? ''), 300);
        $data['summary'] = $summary !== '' ? $summary : null;
        return $data;
    }

    /**
     * 为被驳回的软件提交生成一次性安全修改令牌。
     * 仅保存 SHA-256 摘要，明文只在生成时通过邮件发送给提交者。
     */
    public static function createEditToken(int $id, int $ttlSeconds = 604800): ?string {
        $software = self::find($id);
        if (!$software || ($software['status'] ?? '') !== 'rejected') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $updated = Database::update('softwares', [
            'edit_token_hash' => hash('sha256', $token),
            'edit_token_expires_at' => date('Y-m-d H:i:s', time() + max(300, $ttlSeconds)),
        ], 'id = :id AND status = :status', ['id' => $id, 'status' => 'rejected']);

        return $updated === 1 ? $token : null;
    }

    /** 通过明文令牌定位被驳回的提交，令牌一次性消费且有有效期。 */
    public static function findByEditToken(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Database::fetch(
            "SELECT s.*, c.name as category_name, c.slug as category_slug
             FROM softwares s
             LEFT JOIN categories c ON s.category_id = c.id
             WHERE s.edit_token_hash = :token_hash
               AND s.edit_token_expires_at IS NOT NULL
               AND s.edit_token_expires_at > :now
               AND s.status = 'rejected'
             LIMIT 1",
            [
                'token_hash' => hash('sha256', $token),
                'now' => date('Y-m-d H:i:s')
            ]
        );
    }

    /**
     * 提交者通过安全链接修改并重新提交。
     * 令牌在同一次 UPDATE 中被清空，确保只能使用一次。
     */
    public static function resubmitWithToken(string $token, array $data): bool {
        $software = self::findByEditToken($token);
        if (!$software) {
            return false;
        }

        $updates = array_merge($data, [
            'status' => 'pending',
            'review_note' => null,
            'reviewed_at' => null,
            'edit_token_hash' => null,
            'edit_token_expires_at' => null,
            'notification_status' => 'pending',
            'notification_error' => null,
            'notified_at' => null,
        ]);

        return Database::update('softwares', $updates, 'id = :id AND status = :status AND edit_token_hash = :token_hash', [
            'id' => (int)$software['id'],
            'status' => 'rejected',
            'token_hash' => hash('sha256', $token)
        ]) === 1;
    }

    /** 记录审核结果邮件的发送状态，便于后台排查投递失败原因。 */
    public static function updateNotification(int $id, string $status, ?string $error = null): bool {
        return Database::update('softwares', [
            'notification_status' => $status,
            'notification_error' => $error !== null && $error !== '' ? $error : null,
            'notified_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public static function delete(int $id): int {
        return Database::delete('softwares', 'id = :id', ['id' => $id]);
    }

    /**
     * 增加浏览量。
     * 统计属于旁路写入：失败（如只读数据库、并发锁）不应影响详情页渲染。
     */
    public static function incrementViews(int $id): void {
        try {
            Database::query("UPDATE softwares SET views_count = views_count + 1 WHERE id = :id", ['id' => $id]);
        } catch (Throwable $e) {
            error_log('[SoftNav] incrementViews failed: ' . $e->getMessage());
        }
    }

    /**
     * 按「同一会话同一软件只计一次」登记浏览量。
     *
     * 详情页原本是每次 GET 都发一条 UPDATE：刷新一次就 +1 让统计失真，更要紧的是
     * 高频写会和并发读抢 SQLite 的写锁。会话去重把写频降成「一人一次」，
     * 又不需要引入新的存储或延迟队列。
     */
    public static function registerView(int $softwareId): void {
        if ($softwareId <= 0) {
            return;
        }

        Auth::start();
        $seen = $_SESSION['viewed_softwares'] ?? [];
        if (!is_array($seen)) {
            $seen = [];
        }
        if (in_array($softwareId, $seen, true)) {
            return;
        }
        // 长期挂着的会话会一直累积，只保留最近 500 条
        if (count($seen) >= 500) {
            $seen = array_slice($seen, -499);
        }
        $seen[] = $softwareId;
        $_SESSION['viewed_softwares'] = $seen;

        self::incrementViews($softwareId);
    }

    /**
     * 记录前端从软件图标实际取样出的代表色（样本经 iconProxy 同源代理取得）。
     * 旧库若无 theme_color 列则抛出，由调用方吞掉，不影响主流程。
     */
    public static function updateThemeColor(string $slug, string $hex): void {
        Database::update('softwares', ['theme_color' => $hex], 'slug = :slug', ['slug' => $slug]);
    }

    public static function updateRatingStats(int $softwareId): void {
        $stats = Database::fetch("SELECT COUNT(*) as cnt, AVG(rating) as avg_rating FROM comments WHERE software_id = :id AND status = 'approved'", ['id' => $softwareId]);
        $cnt = (int) ($stats['cnt'] ?? 0);
        $avg = $cnt > 0 ? round((float) $stats['avg_rating'], 1) : 0.0;

        Database::update('softwares', [
            'rating_count' => $cnt,
            'rating_avg' => $avg
        ], 'id = :id', ['id' => $softwareId]);
    }

    public static function generateSlug(string $name): string {
        $clean = preg_replace('/[^\p{L}\p{N}\-_]+/u', '-', mb_strtolower(trim($name)));
        $clean = trim($clean, '-');
        if (empty($clean)) {
            $clean = 'app-' . time();
        }
        // 检查唯一性
        $slug = $clean;
        $counter = 1;
        while (Database::fetch("SELECT id FROM softwares WHERE slug = :s", ['s' => $slug])) {
            $slug = $clean . '-' . $counter;
            $counter++;
        }
        return $slug;
    }

    public static function addTag(int $softwareId, string $newTag): array {
        $tag = trim($newTag);
        if (empty($tag) || mb_strlen($tag) > 20) {
            return [];
        }

        $row = Database::fetch("SELECT tags FROM softwares WHERE id = :id", ['id' => $softwareId]);
        if (!$row) return [];

        $existing = !empty($row['tags']) ? array_filter(array_map('trim', explode(',', $row['tags']))) : [];
        if (!in_array($tag, $existing)) {
            $existing[] = $tag;
            $newTagsStr = implode(',', $existing);
            Database::update('softwares', ['tags' => $newTagsStr], 'id = :id', ['id' => $softwareId]);
        }
        return $existing;
    }

    public static function getThemeRgb(array $software): array {
        $hex = trim((string)($software['theme_color'] ?? ''));
        if (empty($hex) || !preg_match('/^#?([a-f0-9]{6}|[a-f0-9]{3})$/i', $hex)) {
            $presets = [
                [0, 120, 212],   // 微软蓝
                [59, 130, 246],  // 天蓝
                [30, 41, 59],    // 极简深灰黑
                [249, 115, 22],  // 暖橙
                [16, 124, 65],   // 微软翠绿
                [239, 68, 68],   // 珊瑚红
                [6, 182, 212],   // 青蓝
                [37, 99, 235],   // 宝蓝
                [71, 85, 105],   // 石板冷灰
                [16, 185, 129],  // 薄荷绿
            ];
            $idx = abs(crc32($software['name'] ?? 'app')) % count($presets);
            return $presets[$idx];
        }

        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
            $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
            $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
        } else {
            $r = hexdec(substr($hex, 0, 2));
            $g = hexdec(substr($hex, 2, 2));
            $b = hexdec(substr($hex, 4, 2));
        }
        return [$r, $g, $b];
    }
}
