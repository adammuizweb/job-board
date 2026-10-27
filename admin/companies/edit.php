<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_COMPANIES);
$id = jyb_positive_id($_GET['id'] ?? 0);
$company = null;
$texts = [];
if ($id > 0) {
    $statement = $pdo->prepare('SELECT * FROM jyb_companies WHERE id=?');
    $statement->execute([$id]);
    $company = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($company)) { adiwira_render_404(); return; }
    $statement = $pdo->prepare('SELECT * FROM jyb_company_texts WHERE company_id=? ORDER BY locale');
    $statement->execute([$id]);
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) $texts[$row['locale']] = $row;
}
$company ??= ['id'=>0, 'version'=>0, 'source_locale'=>jyb_default_content_locale(), 'status'=>'active', 'website_url'=>''];
if (!jyb_locale_edit_allowed($pdo, $jybActorId, (string)$company['source_locale'])) { adiwira_render_404(); return; }
$editableLocales = jyb_editable_locales($pdo, $jybActorId);
$locales = array_values(array_unique(array_merge([(string)$company['source_locale']], $editableLocales)));
$locales = array_values(array_filter($locales, static fn(string $candidate): bool => in_array($candidate, $editableLocales, true)));
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'companies'); ?>
  <header class="jyb-admin-head">
    <div class="jyb-admin-heading"><p><?= jyb_h(jyb_t('Company editor')) ?></p><h1><?= jyb_h($id ? jyb_t('Edit company') : jyb_t('Add company')) ?></h1></div>
    <div class="jyb-admin-actions"><a class="adam-cancle" href="<?= jyb_h(jyb_admin_url('companies')) ?>"><?= jyb_h(jyb_t('Back to companies')) ?></a></div>
  </header>

  <form class="jyb-form-stack" method="post" action="<?= jyb_h(jyb_admin_url('companies/save')) ?>">
    <input type="hidden" name="csrf_token" value="<?= jyb_h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="version" value="<?= (int)$company['version'] ?>">

    <section class="jyb-card">
      <div class="jyb-card-head"><div class="jyb-admin-heading"><h2><?= jyb_h(jyb_t('Company details')) ?></h2><p><?= jyb_h(jyb_t('Control the company identity and public availability.')) ?></p></div></div>
      <div class="jyb-grid two">
        <label><?= jyb_h(jyb_t('Source locale')) ?><input class="inp" name="source_locale" maxlength="12" value="<?= jyb_h($company['source_locale']) ?>" <?= $id ? 'readonly' : '' ?> required></label>
        <label><?= jyb_h(jyb_t('Status')) ?><select class="inp" name="status"><option value="active" <?= $company['status'] === 'active' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Active')) ?></option><option value="inactive" <?= $company['status'] === 'inactive' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Inactive')) ?></option></select></label>
        <label class="wide"><?= jyb_h(jyb_t('Website HTTPS URL')) ?><input class="inp" type="url" name="website_url" maxlength="2048" value="<?= jyb_h($company['website_url']) ?>"></label>
      </div>
    </section>

    <?php foreach ($locales as $locale): ?>
      <?php $text = $texts[$locale] ?? ['name'=>'', 'description'=>'', 'translation_status'=>'draft']; ?>
      <section class="jyb-card">
        <div class="jyb-card-head"><div class="jyb-admin-heading"><h2><?= jyb_h(jyb_t('%s text', strtoupper($locale))) ?><?= $locale === $company['source_locale'] ? ' *' : '' ?></h2><p><?= jyb_h($locale === $company['source_locale'] ? jyb_t('Source content') : jyb_t('Localized content')) ?></p></div><span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$text['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$text['translation_status']))) ?></span></div>
        <input type="hidden" name="texts[<?= jyb_h($locale) ?>][locale]" value="<?= jyb_h($locale) ?>">
        <div class="jyb-grid two">
          <label><?= jyb_h(jyb_t('Name')) ?><input class="inp" name="texts[<?= jyb_h($locale) ?>][name]" maxlength="191" value="<?= jyb_h($text['name']) ?>" <?= $locale === $company['source_locale'] ? 'required' : '' ?>></label>
          <label><?= jyb_h(jyb_t('Translation status')) ?><select class="inp" name="texts[<?= jyb_h($locale) ?>][translation_status]"><option value="draft" <?= $text['translation_status'] === 'draft' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Draft')) ?></option><option value="published" <?= $text['translation_status'] === 'published' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Published')) ?></option></select></label>
          <label class="wide"><?= jyb_h(jyb_t('Description')) ?><textarea class="inp" name="texts[<?= jyb_h($locale) ?>][description]" maxlength="10000" rows="6"><?= jyb_h($text['description']) ?></textarea></label>
        </div>
      </section>
    <?php endforeach; ?>

    <section class="jyb-card"><div class="jyb-form-actions"><button class="adam-btn" type="submit"><?= jyb_h(jyb_t('Save company')) ?></button><a class="adam-cancle" href="<?= jyb_h(jyb_admin_url('companies')) ?>"><?= jyb_h(jyb_t('Cancel')) ?></a></div></section>
  </form>
</section>
