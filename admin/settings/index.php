<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_SETTINGS);
$settings = ['base_path'=>jyb_base_path($pdo), 'per_page'=>jyb_per_page($pdo)];
$hash = hash('sha256', json_encode($settings, JSON_THROW_ON_ERROR));
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'settings'); ?>
  <header class="jyb-admin-head">
    <div class="jyb-admin-heading"><p><?= jyb_h(jyb_t('Configuration')) ?></p><h1><?= jyb_h(jyb_t('Job Board settings')) ?></h1><p><?= jyb_h(jyb_t('Configure the public listing route and page size.')) ?></p></div>
  </header>
  <form class="jyb-card jyb-form-stack" method="post" action="<?= jyb_h(jyb_admin_url('settings/save')) ?>">
    <input type="hidden" name="csrf_token" value="<?= jyb_h(csrf_token()) ?>">
    <input type="hidden" name="expected_hash" value="<?= jyb_h($hash) ?>">
    <div class="jyb-card-head"><div class="jyb-admin-heading"><h2><?= jyb_h(jyb_t('Public listing')) ?></h2><p><?= jyb_h(jyb_t('Changing the base path changes the public Job Board URL.')) ?></p></div></div>
    <div class="jyb-grid two">
      <label><?= jyb_h(jyb_t('Public base path')) ?><input class="inp" name="base_path" maxlength="191" required value="<?= jyb_h($settings['base_path']) ?>"><small><?= jyb_h(jyb_t('Use a neutral path segment without slashes.')) ?></small></label>
      <label><?= jyb_h(jyb_t('Jobs per page')) ?><input class="inp" type="number" name="per_page" min="1" max="48" value="<?= (int)$settings['per_page'] ?>"><small><?= jyb_h(jyb_t('Choose between 1 and 48 jobs per page.')) ?></small></label>
    </div>
    <div class="jyb-form-actions"><button class="adam-btn" type="submit"><?= jyb_h(jyb_t('Save settings')) ?></button></div>
  </form>
</section>
