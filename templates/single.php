<?php
declare(strict_types=1);

$listUrl = jyb_localized_path($base, $locale);
$applyUrl = $application['type'] === 'none'
    ? null
    : ($application['type'] === 'email' ? 'mailto:' . $application['target'] : $application['target']);
?>
<article class="jyb-public jyb-single">
  <nav class="jyb-crumb" aria-label="<?= jyb_h(jyb_t('Breadcrumb')) ?>">
    <a href="<?= jyb_h($listUrl) ?>"><?= jyb_h(jyb_t('Jobs')) ?></a>
    <span aria-hidden="true">/</span>
    <span><?= jyb_h($job['title']) ?></span>
  </nav>
  <header class="jyb-single-hero">
    <div>
      <p class="jyb-eyebrow"><?= jyb_h($job['company_name']) ?></p>
      <h1><?= jyb_h($job['title']) ?></h1>
      <?php if ($job['summary']): ?><p><?= jyb_h($job['summary']) ?></p><?php endif; ?>
      <div class="jyb-badges">
        <span><?= jyb_h(jyb_t(ucwords(str_replace('-', ' ', (string)$job['employment_type'])))) ?></span>
        <span><?= jyb_h(jyb_t(ucwords(str_replace('-', ' ', (string)$job['workplace_type'])))) ?></span>
        <?php if ($job['location_text']): ?><span><?= jyb_h($job['location_text']) ?></span><?php endif; ?>
      </div>
    </div>
    <aside>
      <?php if ($applyUrl !== null): ?>
        <a class="jyb-apply" href="<?= jyb_h($applyUrl) ?>" <?= $application['type'] === 'url' ? 'rel="noopener"' : '' ?>><?= jyb_h($application['type'] === 'email' ? jyb_t('Apply by email') : jyb_t('Apply now')) ?></a>
      <?php else: ?>
        <p><?= jyb_h(jyb_t('Application information was not provided.')) ?></p>
      <?php endif; ?>
      <?php if ($job['closes_at_utc']): ?>
        <p><?= jyb_h(jyb_t('Applications close')) ?> <time datetime="<?= jyb_h(str_replace(' ', 'T', (string)$job['closes_at_utc']) . 'Z') ?>"><?= jyb_h(substr((string)$job['closes_at_utc'], 0, 10)) ?></time></p>
      <?php endif; ?>
      <?php if ($job['reference_code']): ?><p><?= jyb_h(jyb_t('Reference')) ?>: <?= jyb_h($job['reference_code']) ?></p><?php endif; ?>
    </aside>
  </header>
  <div class="jyb-single-layout">
    <section>
      <h2><?= jyb_h(jyb_t('About the role')) ?></h2>
      <div class="jyb-description"><?= nl2br(jyb_h($job['description'])) ?></div>
    </section>
    <aside>
      <h2><?= jyb_h(jyb_t('About the company')) ?></h2>
      <?php if ($job['company_description']): ?><p><?= nl2br(jyb_h($job['company_description'])) ?></p><?php endif; ?>
      <?php if ($job['website_url'] && jyb_https_url($job['website_url']) !== null): ?><a href="<?= jyb_h($job['website_url']) ?>" rel="noopener"><?= jyb_h(jyb_t('Company website')) ?></a><?php endif; ?>
      <?php if ($job['categories'] !== []): ?>
        <h3><?= jyb_h(jyb_t('Categories')) ?></h3>
        <ul><?php foreach ($job['categories'] as $category): ?><li><?= jyb_h($category['name']) ?></li><?php endforeach; ?></ul>
      <?php endif; ?>
    </aside>
  </div>
  <?php if ($applyUrl !== null): ?>
    <footer class="jyb-single-footer">
      <a class="jyb-apply" href="<?= jyb_h($applyUrl) ?>" <?= $application['type'] === 'url' ? 'rel="noopener"' : '' ?>><?= jyb_h($application['type'] === 'email' ? jyb_t('Apply by email') : jyb_t('Apply now')) ?></a>
      <p><?= jyb_h(jyb_t('Applications are handled outside Job Board.')) ?></p>
    </footer>
  <?php endif; ?>
</article>
