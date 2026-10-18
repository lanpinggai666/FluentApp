<?php
/**
 * 蓝屏钙软件导航网 - 第三方视频教程与扩展资源模型
 */

class SoftwareResource {
    public static function getBySoftwareId(int $softwareId, string $status = 'published'): array {
        return Database::fetchAll(
            "SELECT * FROM software_resources WHERE software_id = ? AND status = ? ORDER BY id DESC",
            [$softwareId, $status]
        );
    }

    public static function create(array $data): int {
        return Database::insert('software_resources', [
            'software_id' => (int)$data['software_id'],
            'type' => $data['type'] ?? 'video',
            'title' => trim($data['title']),
            'url' => trim($data['url']),
            'author' => trim($data['author'] ?? '') ?: null,
            'description' => trim($data['description'] ?? '') ?: null,
            'submitter_ip' => $data['submitter_ip'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            'status' => $data['status'] ?? 'published'
        ]);
    }

    public static function delete(int $id): bool {
        return Database::delete('software_resources', 'id = :id', ['id' => $id]) > 0;
    }
}
