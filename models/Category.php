<?php
/**
 * 蓝屏钙软件导航网 - 分类模型
 */

class Category {
    public static function getAll(): array {
        return Database::fetchAll("SELECT * FROM categories ORDER BY sort_order ASC, id ASC");
    }

    public static function getAllWithCounts(): array {
        $sql = "SELECT c.*, COUNT(s.id) as software_count 
                FROM categories c 
                LEFT JOIN softwares s ON c.id = s.category_id AND s.status = 'published'
                GROUP BY c.id
                ORDER BY c.sort_order ASC, c.id ASC";
        return Database::fetchAll($sql);
    }

    public static function find(int $id): ?array {
        return Database::fetch("SELECT * FROM categories WHERE id = :id", ['id' => $id]);
    }

    public static function findBySlug(string $slug): ?array {
        return Database::fetch("SELECT * FROM categories WHERE slug = :slug", ['slug' => $slug]);
    }

    public static function create(string $name, string $slug, string $icon = 'folder', int $sortOrder = 0): int {
        return Database::insert('categories', [
            'name' => $name,
            'slug' => $slug,
            'icon' => $icon,
            'sort_order' => $sortOrder
        ]);
    }

    public static function update(int $id, array $data): int {
        return Database::update('categories', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int {
        return Database::delete('categories', 'id = :id', ['id' => $id]);
    }
}
