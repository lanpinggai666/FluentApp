<!-- 结构化数据 SEO -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "<?= View::e($software['name']) ?>",
  "operatingSystem": "Windows",
  "applicationCategory": "<?= View::e($software['category_name'] ?? 'Utility') ?>",
  "description": "<?= View::e($heroDescription) ?>",
  "offers": {
    "@type": "Offer",
    "price": "<?= ($software['price_type'] ?? 'free') === 'free' ? '0' : '99' ?>",
    "priceCurrency": "CNY"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "<?= $software['rating_avg'] ?>",
    "ratingCount": "<?= max(1, (int)$software['rating_count']) ?>"
  }
}
</script>
