<?php
/**
 * 用户处罚（封禁 / 限制权限 / 解除）与身份标签的统一写入口。
 *
 * 规则原先只写在 AdminController::users() 里 —— 网页那条路有「不能动管理员」「不能对自己下手」
 * 「到期时间必须合法」，API 要复用就只能重抄一遍，重抄就会抄漏。这里一份规则，两边都走它。
 */

class UserAdminService
{
    /** 与 views/admin/users.php 的复选框值一一对应，越界的权限项写进库也不会被任何代码认出来 */
    private const PERMISSIONS = ['comment', 'submit_software', 'submit_resource', 'correction', 'report'];
    private const BADGE_MAX_COUNT = 5;
    private const BADGE_MAX_LENGTH = 20;
    private const REASON_MAX_LENGTH = 200;

    /**
     * @param array $src user_id / permanent / banned_until / ban_reason / restricted_permissions
     * @param int $operatorId 操作者用户 id；API 没有会话，传 0 表示跳过「不能对自己」这条（管理员账号本来就整体豁免）
     * @return array{ok:bool,code:string,message:string,data:array}
     */
    public static function punish(array $src, int $operatorId = 0): array
    {
        $userId = (int) ($src['user_id'] ?? 0);
        $target = self::findTarget($userId);
        if (!$target['ok']) {
            return $target;
        }
        if ($operatorId > 0 && $userId === $operatorId) {
            return self::fail('self_target', '不能对自己执行封禁或限制操作');
        }

        $permanent = !empty($src['permanent']);
        $bannedUntil = trim((string) ($src['banned_until'] ?? ''));
        $reason = trim((string) ($src['ban_reason'] ?? ''));

        if (mb_strlen($reason) > self::REASON_MAX_LENGTH) {
            return self::fail('invalid_reason', '封禁原因过长（不超过 ' . self::REASON_MAX_LENGTH . ' 字）');
        }

        $permissions = self::cleanPermissions($src['restricted_permissions'] ?? [], $permissionError);
        if ($permissionError !== null) {
            return self::fail('invalid_permission', $permissionError);
        }

        // 临时封禁的到期时间要能解析且还没过：否则 User::punish 会写下「封禁至 一个过去的时间」，
        // 用户既没被拦、通知里还挂着一条已失效的期限，后台也看不出问题
        if (!$permanent && $bannedUntil !== '') {
            $timestamp = strtotime($bannedUntil);
            if ($timestamp === false) {
                return self::fail('invalid_period', '封禁到期时间无法解析，请用 2026-10-05 或 2026-10-05 18:00:00 这种写法');
            }
            if ($timestamp <= time()) {
                return self::fail('invalid_period', '封禁到期时间必须晚于当前时间（' . date('Y-m-d H:i:s', $timestamp) . ' 已经过去了）');
            }
            $bannedUntil = date('Y-m-d H:i:s', $timestamp);
        }

        if (!$permanent && $bannedUntil === '' && $permissions === []) {
            return self::fail('empty_punish',
                '永久封禁、到期时间、受限权限至少要给一项；什么都不想限制请改用解除（unpunish）');
        }

        User::punish($userId, $permanent, $bannedUntil !== '' ? $bannedUntil : null, $reason, $permissions);

        return self::done($userId, '处罚已生效并已通知用户', [
            'permanent' => $permanent,
            'banned_until' => $bannedUntil !== '' ? $bannedUntil : null,
            'ban_reason' => $reason !== '' ? $reason : null,
            'restricted_permissions' => $permissions,
        ]);
    }

    /** @return array{ok:bool,code:string,message:string,data:array} */
    public static function unpunish(int $userId): array
    {
        $target = self::findTarget($userId, false);
        if (!$target['ok']) {
            return $target;
        }
        User::unpunish($userId);

        return self::done($userId, '处罚限制已解除', [
            'permanent' => false,
            'banned_until' => null,
            'ban_reason' => null,
            'restricted_permissions' => [],
        ]);
    }

    /**
     * @param array|string $badges 数组，或后台表单那种英文逗号分隔字符串
     * @return array{ok:bool,code:string,message:string,data:array}
     */
    public static function setBadges(int $userId, $badges): array
    {
        $target = self::findTarget($userId, false);
        if (!$target['ok']) {
            return $target;
        }

        $raw = is_array($badges) ? $badges : explode(',', (string) $badges);
        $clean = [];
        foreach ($raw as $badge) {
            $badge = trim((string) $badge);
            if ($badge === '' || in_array($badge, $clean, true)) {
                continue;
            }
            if (mb_strlen($badge) > self::BADGE_MAX_LENGTH) {
                return self::fail('invalid_badge', '身份标签「' . mb_substr($badge, 0, 12) . '…」过长（每个不超过 '
                    . self::BADGE_MAX_LENGTH . ' 字）');
            }
            $clean[] = $badge;
        }
        if (count($clean) > self::BADGE_MAX_COUNT) {
            return self::fail('too_many_badges', '身份标签最多 ' . self::BADGE_MAX_COUNT . ' 个，当前 ' . count($clean) . ' 个');
        }

        User::setBadges($userId, $clean);

        return self::done($userId, '用户标签已更新', ['badges' => $clean]);
    }

    /** 统一读回落库后的状态，避免调用方拿「请求里写了什么」当成「库里现在是什么」 */
    private static function done(int $userId, string $message, array $echo): array
    {
        $row = User::findById($userId) ?: [];
        return self::ok([
            'user_id' => $userId,
            'username' => $row['username'] ?? '',
            'is_banned' => (int) ($row['is_banned'] ?? 0),
            'banned_until' => $row['banned_until'] ?? null,
            'ban_reason' => $row['ban_reason'] ?? null,
            'restricted_permissions' => self::decodeList($row['restricted_permissions'] ?? null),
            'badges' => self::decodeList($row['badges'] ?? null),
            'status' => $row['status'] ?? '',
            'echo' => $echo,
        ], $message);
    }

    /** @return array{ok:bool,code:string,message:string,data:array} */
    private static function findTarget(int $userId, bool $blockAdmin = true): array
    {
        if ($userId <= 0) {
            return self::fail('invalid_user_id', 'user_id 必须是正整数');
        }
        $user = User::findById($userId);
        if (!$user) {
            return self::fail('not_found', '用户不存在（id ' . $userId . '）');
        }
        if ($blockAdmin && ($user['role'] ?? '') === 'admin') {
            // 管理员被限权后没人能在页面上解开，等同于把自己关在门外
            return self::fail('admin_target', '禁止对管理员账号执行封禁或限制操作');
        }
        return self::ok(['user_id' => $userId, 'username' => $user['username'] ?? ''], '目标用户可用');
    }

    private static function cleanPermissions($value, ?string &$error): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            $error = 'restricted_permissions 必须是数组';
            return [];
        }
        $out = [];
        foreach ($value as $permission) {
            $permission = trim((string) $permission);
            if ($permission === '') {
                continue;
            }
            if (!in_array($permission, self::PERMISSIONS, true)) {
                $error = '未知的受限权限项：' . $permission . '（可选：' . implode(' / ', self::PERMISSIONS) . '）';
                return [];
            }
            if (!in_array($permission, $out, true)) {
                $out[] = $permission;
            }
        }
        $error = null;
        return $out;
    }

    private static function decodeList($raw): array
    {
        if (!is_string($raw) || $raw === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? array_values(array_map('strval', $decoded)) : [];
    }

    private static function ok(array $data, string $message): array
    {
        return ['ok' => true, 'code' => 'ok', 'message' => $message, 'data' => $data];
    }

    private static function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message, 'data' => []];
    }
}
