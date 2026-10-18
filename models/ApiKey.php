<?php
/**
 * 开放 API 的访问密钥。
 *
 * 明文密钥只在生成那一刻返回一次，库里只存 SHA-256 与识别前缀：
 * 数据库被读走也拿不到可用凭据，吊销后也无法“猜回”原密钥。
 */

class ApiKey
{
    public const PREFIX_LENGTH = 12;

    /** @return array{key:string,row:array} 明文密钥只在这里出现一次 */
    public static function issue(string $name, array $scopes = ['read'], string $createdBy = ''): array
    {
        $name = mb_substr(trim($name), 0, 64);
        if ($name === '') {
            throw new RuntimeException('密钥必须有一个用途名称，例如「安卓 App」');
        }
        $plain = 'nav_' . bin2hex(random_bytes(20));
        $prefix = substr($plain, 0, self::PREFIX_LENGTH);
        $hash = hash('sha256', $plain);

        $id = Database::insert('api_keys', [
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => $hash,
            'scopes' => self::normalizeScopes($scopes),
            'created_at' => date('Y-m-d H:i:s'),
            'created_by' => $createdBy !== '' ? $createdBy : null,
            'last_used_at' => null,
            'revoked_at' => null,
        ]);

        return ['key' => $plain, 'row' => self::findById($id)];
    }

    /** @return array<int,array> 不含 key_hash */
    public static function listAll(bool $includeRevoked = true): array
    {
        $sql = 'SELECT id, name, key_prefix, scopes, created_at, created_by, last_used_at, revoked_at FROM api_keys'
            . ($includeRevoked ? '' : ' WHERE revoked_at IS NULL')
            . ' ORDER BY id DESC';
        return Database::fetchAll($sql);
    }

    public static function findById(int $id): ?array
    {
        return Database::fetch('SELECT id, name, key_prefix, scopes, created_at, created_by, last_used_at, revoked_at FROM api_keys WHERE id = :id',
            ['id' => $id]);
    }

    /** 吊销：只置时间戳，不删行 —— 调用日志还要靠 id 追溯是谁调的 */
    public static function revoke(int $id): bool
    {
        $row = self::findById($id);
        if (!$row || $row['revoked_at'] !== null) {
            return false;
        }
        Database::update('api_keys', ['revoked_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        return true;
    }

    /**
     * 按识别前缀取候选，再对哈希做常数时间比较。
     * 直接用哈希当查询条件也能work，但先按前缀缩小范围、再常数时间比对，
     * 不会因为数据库比较耗时而泄露“前缀对不对”。
     */
    public static function verify(string $plainKey): ?array
    {
        $plainKey = trim($plainKey);
        if (strlen($plainKey) < 20 || !str_starts_with($plainKey, 'nav_')) {
            return null;
        }
        $prefix = substr($plainKey, 0, self::PREFIX_LENGTH);
        $candidates = Database::fetchAll('SELECT * FROM api_keys WHERE key_prefix = :p', ['p' => $prefix]);
        $hash = hash('sha256', $plainKey);
        foreach ($candidates as $row) {
            if (hash_equals((string) $row['key_hash'], $hash)) {
                return $row['revoked_at'] === null ? $row : null;
            }
        }
        return null;
    }

    public static function hasScope(array $row, string $scope): bool
    {
        $scopes = explode(',', (string) ($row['scopes'] ?? ''));
        return in_array($scope, array_map('trim', $scopes), true);
    }

    /** 最后使用时间只在一分钟前之后才更新，避免每次调用都写一行 */
    public static function touch(int $id, ?string $lastUsedAt): void
    {
        if ($lastUsedAt !== null && strtotime($lastUsedAt) > time() - 60) {
            return;
        }
        Database::update('api_keys', ['last_used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
    }

    /** 表单里的「开/关」值形态各异（1/on/true/yes），统一成布尔 */
    public static function trueish($value): bool
    {
        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function normalizeScopes($scopes): string
    {
        if (is_string($scopes)) {
            $scopes = explode(',', $scopes);
        }
        $clean = [];
        foreach ((array) $scopes as $scope) {
            $scope = strtolower(trim((string) $scope));
            if (in_array($scope, ['read', 'write'], true) && !in_array($scope, $clean, true)) {
                $clean[] = $scope;
            }
        }
        return $clean === [] ? 'read' : implode(',', $clean);
    }

    public static function activeCount(): int
    {
        return (int) Database::fetch('SELECT COUNT(*) AS c FROM api_keys WHERE revoked_at IS NULL')['c'];
    }
}
