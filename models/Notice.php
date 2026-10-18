<?php
/**
 * 蓝屏钙软件导航网 - 公告模型
 */

class Notice {
    public static function getActiveFirst(): ?array {
        return Database::fetch("SELECT * FROM notices WHERE is_active = 1 ORDER BY sort_order ASC, id DESC LIMIT 1");
    }

    public static function getAll(): array {
        return Database::fetchAll("SELECT * FROM notices ORDER BY sort_order ASC, id DESC");
    }

    public static function add(string $title, string $content, int $isActive = 1, int $sortOrder = 0): int {
        return Database::insert('notices', [
            'title' => $title,
            'content' => $content,
            'is_active' => $isActive,
            'sort_order' => $sortOrder
        ]);
    }

    public static function update(int $id, array $data): int {
        return Database::update('notices', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int {
        return Database::delete('notices', 'id = :id', ['id' => $id]);
    }
}
