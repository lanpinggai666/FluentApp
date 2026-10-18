<?php
/**
 * 蓝屏钙软件导航网 - 站内信模型
 */

class Notification {
    /**
     * 发送站内信
     */
    public static function send(int $userId, string $title, string $content, string $type = 'system'): int {
        return Database::insert('notifications', [
            'user_id' => $userId,
            'title' => trim($title),
            'content' => trim($content),
            'type' => $type,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * 获取用户站内信列表
     */
    public static function getByUserId(int $userId, int $limit = 50, int $offset = 0): array {
        return Database::fetchAll(
            "SELECT * FROM notifications WHERE user_id = :uid ORDER BY is_read ASC, id DESC LIMIT :limit OFFSET :offset",
            ['uid' => $userId, 'limit' => $limit, 'offset' => $offset]
        );
    }

    /**
     * 获取未读消息数
     */
    public static function getUnreadCount(int $userId): int {
        $row = Database::fetch(
            "SELECT COUNT(*) AS c FROM notifications WHERE user_id = :uid AND is_read = 0",
            ['uid' => $userId]
        );
        return (int)($row['c'] ?? 0);
    }

    /**
     * 标记单条已读
     */
    public static function markAsRead(int $id, int $userId): bool {
        return Database::update('notifications', ['is_read' => 1], 'id = :id AND user_id = :uid', [
            'id' => $id,
            'uid' => $userId
        ]) > 0;
    }

    /**
     * 全部标为已读
     */
    public static function markAllAsRead(int $userId): bool {
        return Database::update('notifications', ['is_read' => 1], 'user_id = :uid AND is_read = 0', [
            'uid' => $userId
        ]) > 0;
    }

    /**
     * 删除单条站内信
     */
    public static function delete(int $id, int $userId): bool {
        return Database::delete('notifications', 'id = :id AND user_id = :uid', [
            'id' => $id,
            'uid' => $userId
        ]) > 0;
    }

    /**
     * 删除用户所有已读站内信
     */
    public static function deleteRead(int $userId): bool {
        return Database::delete('notifications', 'user_id = :uid AND is_read = 1', [
            'uid' => $userId
        ]) > 0;
    }
}
