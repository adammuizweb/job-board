<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_COMPANIES);
$rows = jyb_admin_companies($pdo);
$activeCount = count(array_filter($rows, static fn(array $row): bool => $row['status'] === 'active'));
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'companies'); ?>
  <header class="jyb-admin-head">
    <div class="jyb-admin-heading">
      <p><?= jyb_h(jyb_t('Directory')) ?></p>
      <h1><?= jyb_h(jyb_t('Companies')) ?></h1>
      <p><?= jyb_h(jyb_t('Only active companies with published text can appear publicly.')) ?></p>
    </div>
    <div class="jyb-admin-actions"><a class="adam-btn" href="<?= jyb_h(jyb_admin_url('companies/edit')) ?>"><?= jyb_h(jyb_t('Add company')) ?></a></div>
  </header>

  <section class="jyb-stats">
    <article class="jyb-stat"><span><?= jyb_h(jyb_t('Total companies')) ?></span><strong><?= count($rows) ?></strong></article>
    <article class="jyb-stat"><span><?= jyb_h(jyb_t('Active')) ?></span><strong><?= $activeCount ?></strong></article>
  </section>

  <section class="jyb-card jyb-card--flush">
    <div class="jyb-table">
      <table class="adam-table jyb-table-responsive">
        <thead><tr><th><?= jyb_h(jyb_t('Company')) ?></th><th><?= jyb_h(jyb_t('Locale')) ?></th><th><?= jyb_h(jyb_t('Status')) ?></th><th><?= jyb_h(jyb_t('Updated')) ?></th><th class="jyb-table-actions"><?= jyb_h(jyb_t('Actions')) ?></th></tr></thead>
        <tbody>
          <?php if ($rows === []): ?><tr><td class="jyb-empty" colspan="5"><strong><?= jyb_h(jyb_t('No companies have been created.')) ?></strong><span><?= jyb_h(jyb_t('Add a company before creating jobs.')) ?></span></td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php $id = (int)$row['id']; $menuId = 'jyb-company-actions-' . $id; ?>
            <tr>
              <td data-label="<?= jyb_h(jyb_t('Company')) ?>"><div class="jyb-title-cell"><a href="<?= jyb_h(jyb_admin_url('companies/edit', ['id'=>$id])) ?>"><?= jyb_h($row['name']) ?></a><small><span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$row['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$row['translation_status']))) ?></span></small></div></td>
              <td data-label="<?= jyb_h(jyb_t('Locale')) ?>"><?= jyb_h(strtoupper((string)$row['source_locale'])) ?></td>
              <td data-label="<?= jyb_h(jyb_t('Status')) ?>"><span class="jyb-status <?= $row['status'] === 'active' ? 'jyb-status--published' : 'jyb-status--draft' ?>"><?= jyb_h(jyb_t(ucfirst((string)$row['status']))) ?></span></td>
              <td data-label="<?= jyb_h(jyb_t('Updated')) ?>"><?= jyb_h(jyb_admin_datetime((string)$row['updated_at_utc'])) ?></td>
              <td class="jyb-table-actions" data-label="<?= jyb_h(jyb_t('Actions')) ?>"><div class="adam-actions"><?php jyb_admin_actions_trigger($menuId); ?><div class="adam-actions__menu" id="<?= jyb_h($menuId) ?>" role="menu" hidden><a role="menuitem" href="<?= jyb_h(jyb_admin_url('companies/edit', ['id'=>$id])) ?>"><?= jyb_h(jyb_t('Edit company')) ?></a></div></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>
