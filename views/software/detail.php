<?php
/**
 * 蓝屏钙软件导航网 - 软件详情页 (严格复刻 Microsoft Store 现代化 Fluent 布局)
 */

$currentUser = Auth::currentUser();
$priceMap = [
    'free' => '免费获取',
    'paid' => '付费购买',
    'freemium' => '免费 (含增值服务/内购)'
];
$priceLabel = $priceMap[$software['price_type'] ?? 'free'] ?? '免费获取';
$isOpenSource = !empty($software['is_open_source']);
$licenseText = $software['license'] ?: ($isOpenSource ? '开源协议' : '商业专有');
$developerName = $software['developer'] ?: '官方开发团队';
$publisherName = $software['publisher'] ?: $developerName;

// 解析标签
$rawTags = $software['tags'] ?? '';
$tagList = !empty($rawTags) ? array_filter(array_map('trim', explode(',', $rawTags))) : [];

// 特别说明：一句话纯文本，展示在下载按钮下方（不使用 Markdown）
$specialNotesRaw = (string)($software['special_notes'] ?? '');
$hasSpecialNotes = trim($specialNotesRaw) !== '';
// 软件说明：Markdown 长文（简介），展示在「说明」卡片
$descriptionMdRaw = (string)($software['description_md'] ?? '');
$descriptionMdHtml = View::markdown($descriptionMdRaw);
$descriptionMdText = View::excerpt($descriptionMdRaw, 110);
$hasDescriptionMd = trim($descriptionMdHtml) !== '';
$readmeText = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string)($readmeHtml ?? ''), ENT_QUOTES, 'UTF-8'))));
$heroDescription = $descriptionMdText !== ''
    ? $descriptionMdText
    : ($readmeText !== ''
        ? View::excerpt($readmeText, 110)
        : (View::excerpt($specialNotesRaw, 110) ?: '官方可靠分发渠道，提供安全稳定的下载安装体验。'));

// 解析其它渠道：区分 Microsoft Store、GitHub 与普通第三方渠道。
// 关键修复：任何渠道都不得被静默丢弃——即使 github_url 已有值，
// Github 类型渠道也必须保留展示，否则用户在页面上看不到已录入的下载来源。
$msStoreUrl = null;
$msStoreProductId = null;
$githubUrl = $software['github_url'] ?? null;
if (empty($githubUrl) && !empty($software['official_url']) && str_contains($software['official_url'], 'github.com')) {
    $githubUrl = $software['official_url'];
}
$thirdPartyUrls = [];
$githubChannels = [];
$seenChannelUrls = [];

foreach ($otherUrls as $other) {
    $url = trim((string)($other['url'] ?? ''));
    if ($url === '') {
        continue;
    }

    $key = strtolower(rtrim($url, '/'));
    if (isset($seenChannelUrls[$key])) {
        continue;
    }
    $seenChannelUrls[$key] = true;

    $type = trim((string)($other['type'] ?? '第三方下载站'));
    $name = trim((string)($other['name'] ?? '')) ?: $type;
    $isStore = ($type === 'Microsoft Store') || str_contains($url, 'apps.microsoft.com');
    $isGithub = ($type === 'Github') || str_contains($url, 'github.com');

    if ($isStore) {
        $msStoreUrl = $url;
        if (preg_match('#(?:detail|productId|store/apps)/([a-zA-Z0-9]{9,14})#i', $url, $m)) {
            $msStoreProductId = strtoupper($m[1]);
        } elseif (preg_match('/^[a-zA-Z0-9]{9,14}$/', $url)) {
            $msStoreProductId = strtoupper($url);
        }
        continue;
    }

    if ($isGithub) {
        $githubChannels[] = ['type' => $type, 'name' => $name, 'url' => $url];
        if (empty($githubUrl)) {
            $githubUrl = $url;
        }
        continue;
    }

    $thirdPartyUrls[] = ['type' => $type, 'name' => $name, 'url' => $url];
}

// Hero 区域最多展示 3 个第三方渠道，其余在下载渠道区完整列出
$heroChannels = array_slice($thirdPartyUrls, 0, 3);
$allChannels = array_merge($githubChannels, $thirdPartyUrls);

[$themeR, $themeG, $themeB] = Software::getThemeRgb($software);
$themeRgbStr = "{$themeR}, {$themeG}, {$themeB}";
?>

<div class="ms-store-layout" data-slug="<?= View::e($software['slug']) ?>" style="--hero-sample-rgb: <?= $themeRgbStr ?>;">

<?php require __DIR__ . '/parts/hero.php'; ?>
<?php require __DIR__ . '/parts/main.php'; ?>
<?php require __DIR__ . '/parts/sticky-bar.php'; ?>
<?php require __DIR__ . '/parts/modal-channels.php'; ?>
<?php require __DIR__ . '/parts/modal-mirror.php'; ?>
<?php require __DIR__ . '/parts/modal-correction.php'; ?>
<?php require __DIR__ . '/parts/modal-resource.php'; ?>
<?php require __DIR__ . '/parts/modal-tag.php'; ?>
<?php require __DIR__ . '/parts/modal-report.php'; ?>
<?php require __DIR__ . '/parts/scripts.php'; ?>
<?php require __DIR__ . '/parts/json-ld.php'; ?>
