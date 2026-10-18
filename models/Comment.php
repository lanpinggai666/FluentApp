<?php
/**
 * 蓝屏钙软件导航网 - 评价与评分模型
 */

class Comment {
    public static function getBySoftware(int $softwareId, int $limit = 50): array {
        $sql = "SELECT * FROM comments 
                WHERE software_id = :sid AND status = 'approved' 
                ORDER BY id DESC LIMIT {$limit}";
        return Database::fetchAll($sql, ['sid' => $softwareId]);
    }

    public static function add(int $softwareId, string $nickname, int $rating, string $content, string $ip, ?string $title = null, ?int $userId = null): int {
        $id = Database::insert('comments', [
            'software_id' => $softwareId,
            'user_id' => $userId,
            'nickname' => $nickname ?: '访客',
            'rating' => max(1, min(5, $rating)),
            'title' => $title ?: null,
            'content' => $content,
            'ip_address' => $ip,
            'status' => 'approved'
        ]);

        Software::updateRatingStats($softwareId);
        return $id;
    }

    public static function vote(int $id, string $type = 'like'): array {
        $field = ($type === 'dislike') ? 'dislikes' : 'likes';
        $pdo = Database::pdo();
        $stmt = $pdo->prepare("UPDATE comments SET {$field} = {$field} + 1 WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = Database::fetch("SELECT likes, dislikes FROM comments WHERE id = :id", ['id' => $id]);
        return $row ?: ['likes' => 0, 'dislikes' => 0];
    }

    public static function getAllAdmin(int $limit = 50, int $offset = 0): array {
        $sql = "SELECT c.*, s.name as software_name 
                FROM comments c 
                LEFT JOIN softwares s ON c.software_id = s.id 
                ORDER BY c.id DESC LIMIT {$limit} OFFSET {$offset}";
        return Database::fetchAll($sql);
    }

    public static function delete(int $id): int {
        $comment = Database::fetch("SELECT software_id FROM comments WHERE id = :id", ['id' => $id]);
        $rows = Database::delete('comments', 'id = :id', ['id' => $id]);
        if ($comment) {
            Software::updateRatingStats((int)$comment['software_id']);
        }
        return $rows;
    }

    /**
     * 检查用户或IP是否已发表过评价
     */
    public static function hasUserCommented(int $softwareId, ?int $userId, string $ip): bool {
        if ($userId && $userId > 0) {
            $row = Database::fetch(
                "SELECT id FROM comments WHERE software_id = :sid AND user_id = :uid LIMIT 1",
                ['sid' => $softwareId, 'uid' => $userId]
            );
            return !empty($row);
        }
        $row = Database::fetch(
            "SELECT id FROM comments WHERE software_id = :sid AND ip_address = :ip AND user_id IS NULL LIMIT 1",
            ['sid' => $softwareId, 'ip' => $ip]
        );
        return !empty($row);
    }

    /**
     * 获取软件准确的星级分布与统计
     */
    public static function getRatingDistribution(int $softwareId): array {
        $rows = Database::fetchAll(
            "SELECT rating, COUNT(*) as cnt FROM comments WHERE software_id = :sid AND status = 'approved' GROUP BY rating",
            ['sid' => $softwareId]
        );
        $counts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        $total = 0;
        $sum = 0;
        foreach ($rows as $r) {
            $star = (int)$r['rating'];
            $cnt = (int)$r['cnt'];
            if ($star >= 1 && $star <= 5) {
                $counts[$star] = $cnt;
                $total += $cnt;
                $sum += $star * $cnt;
            }
        }
        $avg = $total > 0 ? round($sum / $total, 1) : 0.0;
        return [
            'counts' => $counts,
            'total' => $total,
            'avg' => $avg
        ];
    }
}
