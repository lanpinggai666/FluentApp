<?php
/**
 * 蓝屏钙软件导航网 - 举报反馈模型
 */

class Report {
    public static function add(int $softwareId, string $reason, ?string $details, ?string $email, ?string $contact, string $ip, ?int $resourceId = null, string $reportedType = 'software', ?int $userId = null): int {
        return Database::insert('reports', [
            'software_id' => $softwareId,
            'resource_id' => $resourceId,
            'reported_type' => $reportedType,
            'user_id' => $userId,
            'reason' => $reason,
            'details' => $details,
            'email' => $email,
            'contact' => $contact,
            'ip_address' => $ip,
            'status' => 'pending',
            'notification_status' => 'pending'
        ]);
    }

    public static function find(int $id): ?array {
        $sql = "SELECT r.*, 
                       s.name as software_name, s.slug as software_slug, s.status as software_status, s.owner_user_id, s.official_url,
                       u.username as reporter_username, u.email as reporter_account_email,
                       res.title as resource_title, res.url as resource_url, res.type as resource_type, res.status as resource_status
                FROM reports r 
                LEFT JOIN softwares s ON r.software_id = s.id 
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN software_resources res ON r.resource_id = res.id
                WHERE r.id = :id LIMIT 1";
        return Database::fetch($sql, ['id' => $id]) ?: null;
    }

    public static function getAllAdmin(?string $status = null, ?string $type = null): array {
        $where = ["1=1"];
        $params = [];

        if (!empty($status) && $status !== 'all') {
            if ($status === 'dismissed') {
                $where[] = "(r.status = 'dismissed' OR r.status = 'rejected')";
            } else {
                $where[] = "r.status = :status";
                $params['status'] = $status;
            }
        }

        if (!empty($type) && $type !== 'all') {
            if ($type === 'resource') {
                $where[] = "(r.reported_type = 'resource' OR r.resource_id IS NOT NULL)";
            } elseif ($type === 'software') {
                $where[] = "(r.reported_type = 'software' AND r.resource_id IS NULL)";
            }
        }

        $whereClause = implode(' AND ', $where);
        $sql = "SELECT r.*, 
                       s.name as software_name, s.slug as software_slug, s.status as software_status, s.owner_user_id, s.official_url,
                       s.icon_url, s.github_url, s.category_id, s.special_notes, s.description_md, s.tags, s.developer, s.publisher,
                       u.username as reporter_username,
                       res.title as resource_title, res.url as resource_url, res.type as resource_type, res.status as resource_status
                FROM reports r 
                LEFT JOIN softwares s ON r.software_id = s.id 
                LEFT JOIN users u ON r.user_id = u.id
                LEFT JOIN software_resources res ON r.resource_id = res.id
                WHERE {$whereClause} 
                ORDER BY CASE WHEN r.status = 'pending' THEN 0 ELSE 1 END, r.id DESC";
        return Database::fetchAll($sql, $params);
    }

    public static function getStats(): array {
        $row = Database::fetch("SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved,
            SUM(CASE WHEN status = 'dismissed' OR status = 'rejected' THEN 1 ELSE 0 END) as dismissed,
            SUM(CASE WHEN reported_type = 'resource' OR resource_id IS NOT NULL THEN 1 ELSE 0 END) as resource_count,
            SUM(CASE WHEN reported_type = 'software' AND resource_id IS NULL THEN 1 ELSE 0 END) as software_count
            FROM reports");
        return [
            'total' => (int)($row['total'] ?? 0),
            'pending' => (int)($row['pending'] ?? 0),
            'resolved' => (int)($row['resolved'] ?? 0),
            'dismissed' => (int)($row['dismissed'] ?? 0),
            'resource_count' => (int)($row['resource_count'] ?? 0),
            'software_count' => (int)($row['software_count'] ?? 0),
        ];
    }

    public static function updateStatus(int $id, string $status, ?string $reviewReason = null): int {
        $data = [
            'status' => $status,
            'notified_at' => date('Y-m-d H:i:s')
        ];
        if ($reviewReason !== null) {
            $data['review_reason'] = $reviewReason;
        }
        return Database::update('reports', $data, 'id = :id', ['id' => $id]);
    }

    public static function updateNotification(int $id, string $status, ?string $error = null): int {
        return Database::update('reports', [
            'notification_status' => $status,
            'notification_error' => $error,
            'notified_at' => date('Y-m-d H:i:s')
        ], 'id = :id', ['id' => $id]);
    }

    public static function countPending(): int {
        $res = Database::fetch("SELECT COUNT(*) as cnt FROM reports WHERE status = 'pending'");
        return (int) ($res['cnt'] ?? 0);
    }

    public static function delete(int $id): bool {
        return Database::delete('reports', 'id = :id', ['id' => $id]) > 0;
    }
}
