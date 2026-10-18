<?php
/**
 * 蓝屏钙软件导航网 - GitHub 镜像源模型
 */

class GithubMirror {
    public static function getActive(): array {
        return Database::fetchAll("SELECT * FROM github_mirrors WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    }

    public static function getAll(): array {
        return Database::fetchAll("SELECT * FROM github_mirrors ORDER BY sort_order ASC, id ASC");
    }

    public static function add(string $name, string $urlTemplate, int $sortOrder = 0, string $type = 'download', ?string $sampleUrl = null, string $urlStyle = '', ?string $sampleOriginal = null): int {
        return Database::insert('github_mirrors', [
            'name' => $name,
            'url_template' => $urlTemplate,
            'is_active' => 1,
            'sort_order' => $sortOrder,
            'type' => $type,
            'sample_url' => $sampleUrl,
            'url_style' => $urlStyle,
            'sample_original' => $sampleOriginal
        ]);
    }

    public static function update(int $id, array $data): int {
        return Database::update('github_mirrors', $data, 'id = :id', ['id' => $id]);
    }

    public static function delete(int $id): int {
        return Database::delete('github_mirrors', 'id = :id', ['id' => $id]);
    }
}
