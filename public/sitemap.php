<?php
/**
 * 蓝屏钙软件导航网 - 动态 XML 网站地图 (Sitemap)
 */

defined('BASE_PATH') || define('BASE_PATH', dirname(__DIR__));
defined('CORE_PATH') || define('CORE_PATH', BASE_PATH . '/core');
defined('MODEL_PATH') || define('MODEL_PATH', BASE_PATH . '/models');

require_once CORE_PATH . '/Database.php';
require_once CORE_PATH . '/Url.php';
require_once MODEL_PATH . '/Software.php';
require_once MODEL_PATH . '/Category.php';
require_once MODEL_PATH . '/Setting.php';

header('Content-Type: application/xml; charset=utf-8');

// sitemap 里的地址必须是搜索引擎能访问的规范地址，优先取后台配置的站点基址
$baseUrl = Url::siteBase();

echo '<?xml version="1.0" encoding="UTF-8"?>';
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <!-- 首页 -->
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?r=home') ?></loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>

  <!-- 提交页 -->
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/?r=submit') ?></loc>
    <changefreq>monthly</changefreq>
    <priority>0.5</priority>
  </url>

  <!-- 分类列表 -->
  <?php
  try {
      $categories = Category::getAll();
      foreach ($categories as $cat) {
          echo "<url>\n";
          echo "  <loc>" . htmlspecialchars($baseUrl . '/?r=home&cat=' . urlencode($cat['slug'])) . "</loc>\n";
          echo "  <changefreq>weekly</changefreq>\n";
          echo "  <priority>0.8</priority>\n";
          echo "</url>\n";
      }
  } catch (Throwable $e) {}
  ?>

  <!-- 已审核收录软件详情页 -->
  <?php
  try {
      $softwares = Software::getList(['limit' => 1000]);
      foreach ($softwares as $app) {
          $lastMod = !empty($app['updated_at']) ? substr($app['updated_at'], 0, 10) : date('Y-m-d');
          echo "<url>\n";
          echo "  <loc>" . htmlspecialchars(Url::detail($app['slug'], true)) . "</loc>\n";
          echo "  <lastmod>{$lastMod}</lastmod>\n";
          echo "  <changefreq>weekly</changefreq>\n";
          echo "  <priority>0.9</priority>\n";
          echo "</url>\n";
      }
  } catch (Throwable $e) {}
  ?>
</urlset>
