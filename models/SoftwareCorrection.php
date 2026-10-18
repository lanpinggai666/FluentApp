<?php
/**
 * 软件资料修改申请审核模型。
 * 用户在详情页编辑当前完整资料，提交后进入审核，通过后才覆盖正式软件数据。
 */
class SoftwareCorrection {
    private const EDITABLE_FIELDS = [
        'name', 'icon_url', 'official_url', 'github_url',
        'category_id', 'special_notes', 'description_md',
        'price_type', 'is_open_source', 'license', 'developer', 'publisher',
        'tags', 'screenshot_url', 'download_channels', 'contact'
    ];

    public static function create(array $data): int {
        $row = [
            'software_id' => (int)($data['software_id'] ?? 0),
            'submitter_email' => trim((string)($data['submitter_email'] ?? '')),
            'submitter_ip' => (string)($data['submitter_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')),
            'user_id' => !empty($data['user_id']) ? (int)$data['user_id'] : null,
            'status' => 'pending',
            'notification_status' => 'pending'
        ];

        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            $row[$field] = is_string($value) ? trim($value) : $value;
        }

        return Database::insert('software_corrections', $row);
    }

    public static function find(int $id): ?array {
        return Database::fetch(
            "SELECT sc.*, s.name AS software_name, s.slug AS software_slug
             FROM software_corrections sc
             LEFT JOIN softwares s ON s.id = sc.software_id
             WHERE sc.id = :id",
            ['id' => $id]
        );
    }

    public static function getPending(): array {
        return Database::fetchAll(
            "SELECT sc.*, s.name AS software_name, s.slug AS software_slug
             FROM software_corrections sc
             LEFT JOIN softwares s ON s.id = sc.software_id
             WHERE sc.status = 'pending'
             ORDER BY sc.created_at ASC, sc.id ASC"
        );
    }

    public static function countPending(): int {
        $row = Database::fetch("SELECT COUNT(*) AS c FROM software_corrections WHERE status = 'pending'");
        return (int)($row['c'] ?? 0);
    }

    public static function approve(int $id, ?string $reviewNote = null): bool {
        $pdo = Database::pdo();
        $pdo->beginTransaction();

        try {
            $correction = self::find($id);
            if (!$correction || $correction['status'] !== 'pending') {
                $pdo->rollBack();
                return false;
            }

            $software = Software::find((int)$correction['software_id']);
            if (!$software) {
                $pdo->rollBack();
                return false;
            }

            $updates = [];
            $textFields = [
                'name', 'icon_url', 'official_url', 'github_url',
                'special_notes', 'description_md', 'price_type',
                'license', 'developer', 'publisher', 'tags', 'screenshot_url'
            ];

            foreach ($textFields as $field) {
                if (array_key_exists($field, $correction) && $correction[$field] !== null) {
                    $updates[$field] = trim((string)$correction[$field]);
                }
            }

            foreach (['category_id', 'is_open_source'] as $field) {
                if (array_key_exists($field, $correction) && $correction[$field] !== null) {
                    $updates[$field] = (int)$correction[$field];
                }
            }

            if (isset($updates['name']) && $updates['name'] !== $software['name']) {
                $updates['slug'] = Software::generateSlug($updates['name']);
            }

            if (array_key_exists('download_channels', $correction) && $correction['download_channels'] !== null) {
                $channels = json_decode((string)$correction['download_channels'], true);
                $updates['other_urls'] = json_encode(
                    self::normalizeChannels(is_array($channels) ? $channels : []),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            }

            // 先以条件更新原子认领审核记录，避免两个管理员同时通过同一申请。
            $claimed = Database::update('software_corrections', [
                'status' => 'approved',
                'review_note' => trim((string)$reviewNote) ?: null,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = :id AND status = :pending', [
                'id' => $id,
                'pending' => 'pending'
            ]);

            if ($claimed !== 1) {
                $pdo->rollBack();
                return false;
            }

            Software::update((int)$software['id'], $updates);

            $pdo->commit();
            return true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function reject(int $id, ?string $reviewNote = null): bool {
        return Database::update('software_corrections', [
            'status' => 'rejected',
            'review_note' => trim((string)$reviewNote) ?: null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id AND status = :pending', [
            'id' => $id,
            'pending' => 'pending'
        ]) > 0;
    }

    public static function createEditToken(int $id, int $ttlSeconds = 604800): ?string {
        $correction = self::find($id);
        if (!$correction || ($correction['status'] ?? '') !== 'rejected') {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $updated = Database::update('software_corrections', [
            'edit_token_hash' => hash('sha256', $token),
            'edit_token_expires_at' => date('Y-m-d H:i:s', time() + max(300, $ttlSeconds)),
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id AND status = :status', [
            'id' => $id,
            'status' => 'rejected'
        ]);

        return $updated === 1 ? $token : null;
    }

    public static function findByEditToken(string $token): ?array {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        return Database::fetch(
            "SELECT sc.*, s.name AS software_name, s.slug AS software_slug
             FROM software_corrections sc
             LEFT JOIN softwares s ON s.id = sc.software_id
             WHERE sc.edit_token_hash = :token_hash
               AND sc.edit_token_expires_at IS NOT NULL
               AND sc.edit_token_expires_at > :now
               AND sc.status = 'rejected'
             LIMIT 1",
            [
                'token_hash' => hash('sha256', $token),
                'now' => date('Y-m-d H:i:s')
            ]
        );
    }

    public static function updateNotification(int $id, string $status, ?string $error = null): bool {
        return Database::update('software_corrections', [
            'notification_status' => $status,
            'notification_error' => $error,
            'notified_at' => $status === 'sent' ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]) > 0;
    }

    public static function resubmitWithToken(string $token, array $data): bool {
        $correction = self::findByEditToken($token);
        if (!$correction) {
            return false;
        }

        $updates = [
            'status' => 'pending',
            'review_note' => null,
            'reviewed_at' => null,
            'edit_token_hash' => null,
            'edit_token_expires_at' => null,
            'notification_status' => 'pending',
            'notification_error' => null,
            'notified_at' => null,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if (array_key_exists('submitter_email', $data)) {
            $updates['submitter_email'] = trim((string)$data['submitter_email']);
        }

        foreach (self::EDITABLE_FIELDS as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $value = $data[$field];
            $updates[$field] = is_string($value) ? trim($value) : $value;
        }

        return Database::update('software_corrections', $updates, 'id = :id AND status = :status AND edit_token_hash = :token_hash', [
            'id' => (int)$correction['id'],
            'status' => 'rejected',
            'token_hash' => hash('sha256', $token)
        ]) === 1;
    }

    public static function normalizeForDisplay(array $channels): array {
        return self::normalizeChannels($channels);
    }

    private static function normalizeChannels(array $channels): array {
        $result = [];
        $seen = [];
        foreach ($channels as $channel) {
            if (!is_array($channel)) {
                continue;
            }
            $url = trim((string)($channel['url'] ?? ''));
            if ($url === '' || !Url::isSafe($url)) {
                continue;
            }
            $key = strtolower(rtrim($url, '/'));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $type = trim((string)($channel['type'] ?? '第三方下载')) ?: '第三方下载';
            $result[] = [
                'type' => $type,
                'name' => trim((string)($channel['name'] ?? '')) ?: $type,
                'url' => $url,
            ];
        }
        return $result;
    }
}
