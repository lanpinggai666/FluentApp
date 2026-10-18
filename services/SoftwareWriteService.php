<?php
/**
 * 软件资料写入的统一校验与组装。
 *
 * 后台表单和开放 API 都走这里，避免出现「网页那条路挡了非法链接、API 那条路忘了挡」——
 * 之前 saveSoftware 的白名单校验就只存在于控制器里，App 想调用只能重抄一遍，重抄就会抄漏。
 */

class SoftwareWriteService
{
    private const STATUSES = ['pending', 'published', 'rejected', 'offline'];
    private const PRICE_TYPES = ['free', 'freemium', 'paid'];
    /** 与 MySQL 建表列宽一致：publisher VARCHAR(100)、slug VARCHAR(150)。summary 是派生列，不在此收集 */
    private const TEXT_MAX = ['publisher' => 100];
    private const TEXT_LABEL = ['publisher' => '发布机构'];

    /**
     * 把来源数据组装成可写库的字段数组。
     *
     * @param int $id 更新时的软件 id，用于 slug 唯一性排除自己
     * @return array{ok:bool,errors:string[],data:array}
     */
    public static function build(array $src, int $id = 0): array
    {
        $errors = [];

        $priceType = in_array((string) ($src['price_type'] ?? ''), self::PRICE_TYPES, true) ? (string) $src['price_type'] : 'free';
        $isOpenSource = !empty($src['is_open_source']) ? 1 : 0;
        $priceDesc = trim((string) ($src['price_desc'] ?? ''));

        $data = [
            'name' => trim((string) ($src['name'] ?? '')),
            'icon_url' => trim((string) ($src['icon_url'] ?? '')),
            'official_url' => trim((string) ($src['official_url'] ?? '')),
            'github_url' => trim((string) ($src['github_url'] ?? '')) ?: null,
            'category_id' => (int) ($src['category_id'] ?? 1),
            'special_notes' => trim((string) ($src['special_notes'] ?? '')),
            'description_md' => trim((string) ($src['description_md'] ?? '')),
            'status' => in_array((string) ($src['status'] ?? ''), self::STATUSES, true) ? (string) $src['status'] : 'published',
            'is_featured' => !empty($src['is_featured']) ? 1 : 0,
            'price_type' => $priceType,
            'price_desc' => ($priceType !== 'free' && $priceDesc !== '') ? $priceDesc : null,
            'is_open_source' => $isOpenSource,
            'license' => $isOpenSource ? (trim((string) ($src['license'] ?? '')) ?: null) : null,
            'developer' => trim((string) ($src['developer'] ?? '')) ?: null,
            'tags' => trim((string) ($src['tags'] ?? '')) ?: null,
            'screenshot_url' => trim((string) ($src['screenshot_url'] ?? '')) ?: null,
        ];

        // 三个必填项：空名字的条目会把列表页与 JSON-LD 一起带坏
        foreach (['name' => '软件名称', 'icon_url' => '图标链接', 'official_url' => '官网链接'] as $key => $label) {
            if ($data[$key] === '' || $data[$key] === null) {
                $errors[] = $label . '不能为空';
            }
        }
        if (mb_strlen($data['name']) > 100) {
            $errors[] = '软件名称过长（不超过 100 字）';
        }

        $links = ['图标链接' => $data['icon_url'], '官网链接' => $data['official_url'],
            'GitHub 地址' => $data['github_url'], '截图地址' => $data['screenshot_url']];
        foreach ($links as $label => $value) {
            if ($value !== null && $value !== '' && !Url::isSafe($value)) {
                $errors[] = $label . '必须是有效的 http/https 地址，且不能包含空格或引号';
            }
        }

        $channels = self::channels($src, $errors);
        if ($channels !== null) {
            $data['other_urls'] = $channels === [] ? null : json_encode($channels, JSON_UNESCAPED_UNICODE);
        }

        // 这些字段「没提交就不碰」，而不是按 ?? '' 组装成空值：
        // 后台编辑表单里没有这几个输入框，无条件组装会让每次后台保存都把发布机构洗成空，
        // 也让 App 只想改 description_md 时必须连带重发它没打算动的字段。
        foreach (self::TEXT_MAX as $key => $max) {
            if (!array_key_exists($key, $src)) {
                continue;
            }
            $value = trim((string) $src[$key]);
            if (mb_strlen($value) > $max) {
                $errors[] = self::TEXT_LABEL[$key] . '过长（不超过 ' . $max . ' 字）';
                continue;
            }
            $data[$key] = $value !== '' ? $value : null;
        }

        if (array_key_exists('slug', $src)) {
            $slug = mb_strtolower(trim((string) $src['slug']));
            // 传空 = 交回系统按名称生成：新建时由 Software::create 补，更新时保持原 slug
            if ($slug !== '') {
                if (!preg_match('/^[a-z0-9](?:[a-z0-9\-]{0,148}[a-z0-9])?$/', $slug)) {
                    $errors[] = 'slug 只能用小写字母、数字与连字符，且首尾不能是连字符（不超过 150 字符）';
                } elseif (self::slugTaken($slug, $id)) {
                    $errors[] = 'slug 已被其它软件占用：' . $slug;
                } else {
                    $data['slug'] = $slug;
                }
            }
        }

        return ['ok' => $errors === [], 'errors' => $errors, 'data' => $data];
    }

    /** slug 列是 UNIQUE，撞了会在写库时才抛 SQL 异常，这里提前拦下来换成可读的 422 */
    private static function slugTaken(string $slug, int $excludeId): bool
    {
        $row = Database::fetch("SELECT id FROM softwares WHERE slug = :s", ['s' => $slug]);
        return !empty($row) && (int) $row['id'] !== $excludeId;
    }

    /** @return array{ok:bool,errors:string[],id:int,data:array} */
    public static function save(array $src, int $id = 0): array
    {
        $built = self::build($src, $id);
        if (!$built['ok']) {
            return ['ok' => false, 'errors' => $built['errors'], 'id' => $id, 'data' => $built['data']];
        }

        if ($id > 0) {
            if (!Software::find($id)) {
                return ['ok' => false, 'errors' => ['软件不存在（id ' . $id . '）'], 'id' => $id, 'data' => $built['data']];
            }
            Software::update($id, $built['data']);
            return ['ok' => true, 'errors' => [], 'id' => $id, 'data' => $built['data']];
        }

        $newId = Software::create($built['data']);
        return ['ok' => true, 'errors' => [], 'id' => (int) $newId, 'data' => $built['data']];
    }

    /**
     * 解析「其它获取渠道」。
     * 支持两种提交形态：逐项数组（channel_url[]/channel_type[]/channel_name[]）与整包 JSON（other_urls）。
     * 返回 null 表示本次提交没动渠道字段（更新时保持原值）。
     */
    private static function channels(array $src, array &$errors): ?array
    {
        if (isset($src['channel_url']) && is_array($src['channel_url'])) {
            $out = [];
            $types = (array) ($src['channel_type'] ?? []);
            $names = (array) ($src['channel_name'] ?? []);
            foreach (array_values($src['channel_url']) as $index => $rawUrl) {
                $url = trim((string) $rawUrl);
                if ($url === '') {
                    continue;
                }
                if (!Url::isSafe($url)) {
                    $errors[] = '下载渠道包含无效网址';
                    continue;
                }
                $type = trim((string) ($types[$index] ?? '第三方下载')) ?: '第三方下载';
                $name = trim((string) ($names[$index] ?? ''));
                $out[] = ['type' => $type, 'name' => $name !== '' ? $name : ($type === '第三方下载站' ? '网盘下载' : $type), 'url' => $url];
            }
            return $out;
        }

        if (isset($src['other_urls'])) {
            $raw = is_string($src['other_urls']) ? json_decode($src['other_urls'], true) : $src['other_urls'];
            if (!is_array($raw)) {
                $errors[] = 'other_urls 必须是渠道数组或对应的 JSON 字符串';
                return null;
            }
            $out = [];
            foreach ($raw as $channel) {
                $url = trim((string) (is_array($channel) ? ($channel['url'] ?? '') : $channel));
                if ($url === '') {
                    continue;
                }
                if (!Url::isSafe($url)) {
                    $errors[] = '下载渠道包含无效网址';
                    return [];
                }
                $out[] = [
                    'type' => trim((string) (is_array($channel) ? ($channel['type'] ?? '第三方下载') : '第三方下载')) ?: '第三方下载',
                    'name' => trim((string) (is_array($channel) ? ($channel['name'] ?? '') : '')),
                    'url' => $url,
                ];
            }
            return $out;
        }

        return null;
    }
}
