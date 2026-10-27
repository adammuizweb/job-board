<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_CATEGORIES);
$rows = jyb_admin_categories($pdo);
$editId = jyb_positive_id($_GET['id'] ?? 0);
$category = $editId > 0 ? jyb_category($pdo, $editId) : null;
if ($editId > 0 && !is_array($category)) { adiwira_render_404(); return; }
$category ??= ['id'=>0, 'version'=>0, 'source_locale'=>jyb_default_content_locale(), 'status'=>'active', 'texts'=>[]];
if (!jyb_locale_edit_allowed($pdo, $jybActorId, (string)$category['source_locale'])) { adiwira_render_404(); return; }
$texts = [];
foreach ($category['texts'] as $text) $texts[$text['locale']] = $text;
$editableLocales = jyb_editable_locales($pdo, $jybActorId);
$locales = array_values(array_unique(array_merge([(string)$category['source_locale']], $editableLocales)));
$locales = array_values(array_filter($locales, static fn(string $candidate): bool => in_array($candidate, $editableLocales, true)));
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'categories'); ?>
  <header class="jyb-admin-head">
    <div class="jyb-admin-heading"><p><?= jyb_h(jyb_t('Taxonomy')) ?></p><h1><?= jyb_h(jyb_t('Categories')) ?></h1><p><?= jyb_h(jyb_t('Create localized reusable job categories.')) ?></p></div>
    <?php if ($editId): ?><div class="jyb-admin-actions"><a class="adam-cancle" href="<?= jyb_h(jyb_admin_url('categories')) ?>"><?= jyb_h(jyb_t('Add category')) ?></a></div><?php endif; ?>
  </header>

  <form class="jyb-card jyb-form-stack" method="post" action="<?= jyb_h(jyb_admin_url('categories/save')) ?>">
    <input type="hidden" name="csrf_token" value="<?= jyb_h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$category['id'] ?>">
    <input type="hidden" name="version" value="<?= (int)$category['version'] ?>">
    <div class="jyb-card-head"><div class="jyb-admin-heading"><h2><?= jyb_h($editId ? jyb_t('Edit category') : jyb_t('Add category')) ?></h2><p><?= jyb_h(jyb_t('Category text must be published before it appears publicly.')) ?></p></div></div>
    <div class="jyb-grid two">
      <label><?= jyb_h(jyb_t('Source locale')) ?><input class="inp" name="source_locale" maxlength="12" value="<?= jyb_h($category['source_locale']) ?>" <?= $editId ? 'readonly' : '' ?> required></label>
      <label><?= jyb_h(jyb_t('Status')) ?><select class="inp" name="status"><option value="active" <?= $category['status'] === 'active' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Active')) ?></option><option value="inactive" <?= $category['status'] === 'inactive' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Inactive')) ?></option></select></label>
    </div>
    <?php foreach ($locales as $locale): ?>
      <?php $text = $texts[$locale] ?? ['name'=>'', 'slug'=>'', 'translation_status'=>'draft']; ?>
      <section class="jyb-locale-card">
        <div class="jyb-locale-card-head"><h3><?= jyb_h(jyb_t('%s text', strtoupper($locale))) ?><?= $locale === $category['source_locale'] ? ' *' : '' ?></h3><span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$text['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$text['translation_status']))) ?></span></div>
        <input type="hidden" name="texts[<?= jyb_h($locale) ?>][locale]" value="<?= jyb_h($locale) ?>">
        <div class="jyb-grid two">
          <label><?= jyb_h(jyb_t('Name')) ?><input class="inp" name="texts[<?= jyb_h($locale) ?>][name]" maxlength="191" value="<?= jyb_h($text['name']) ?>" <?= $locale === $category['source_locale'] ? 'required' : '' ?>></label>
          <label><?= jyb_h(jyb_t('Localized slug')) ?><input class="inp" name="texts[<?= jyb_h($locale) ?>][slug]" maxlength="191" value="<?= jyb_h($text['slug']) ?>" <?= $locale === $category['source_locale'] ? 'required' : '' ?>></label>
          <label><?= jyb_h(jyb_t('Translation status')) ?><select class="inp" name="texts[<?= jyb_h($locale) ?>][translation_status]"><option value="draft" <?= $text['translation_status'] === 'draft' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Draft')) ?></option><option value="published" <?= $text['translation_status'] === 'published' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Published')) ?></option></select></label>
        </div>
      </section>
    <?php endforeach; ?>
    <div class="jyb-form-actions"><button class="adam-btn" type="submit"><?= jyb_h($editId ? jyb_t('Save category') : jyb_t('Add category')) ?></button><?php if ($editId): ?><a class="adam-cancle" href="<?= jyb_h(jyb_admin_url('categories')) ?>"><?= jyb_h(jyb_t('Cancel')) ?></a><?php endif; ?></div>
  </form>

  <section class="jyb-card jyb-card--flush">
    <div class="jyb-table">
      <table class="adam-table jyb-table-responsive">
        <thead><tr><th><?= jyb_h(jyb_t('Category')) ?></th><th><?= jyb_h(jyb_t('Locale')) ?></th><th><?= jyb_h(jyb_t('Status')) ?></th><th><?= jyb_h(jyb_t('Updated')) ?></th><th class="jyb-table-actions"><?= jyb_h(jyb_t('Actions')) ?></th></tr></thead>
        <tbody>
          <?php if ($rows === []): ?><tr><td class="jyb-empty" colspan="5"><strong><?= jyb_h(jyb_t('No categories have been created.')) ?></strong><span><?= jyb_h(jyb_t('Add a category to organize vacancies.')) ?></span></td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php $id = (int)$row['id']; $menuId = 'jyb-category-actions-' . $id; ?>
            <tr>
              <td data-label="<?= jyb_h(jyb_t('Category')) ?>"><div class="jyb-title-cell"><a href="<?= jyb_h(jyb_admin_url('categories', ['id'=>$id])) ?>"><?= jyb_h($row['name']) ?></a><small><?= jyb_h($row['slug']) ?></small></div></td>
              <td data-label="<?= jyb_h(jyb_t('Locale')) ?>"><?= jyb_h(strtoupper((string)$row['source_locale'])) ?></td>
              <td data-label="<?= jyb_h(jyb_t('Status')) ?>"><div class="jyb-title-cell"><span class="jyb-status <?= $row['status'] === 'active' ? 'jyb-status--published' : 'jyb-status--draft' ?>"><?= jyb_h(jyb_t(ucfirst((string)$row['status']))) ?></span><small><span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$row['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$row['translation_status']))) ?></span></small></div></td>
              <td data-label="<?= jyb_h(jyb_t('Updated')) ?>"><?= jyb_h(jyb_admin_datetime((string)$row['updated_at_utc'])) ?></td>
              <td class="jyb-table-actions" data-label="<?= jyb_h(jyb_t('Actions')) ?>"><div class="adam-actions"><?php jyb_admin_actions_trigger($menuId); ?><div class="adam-actions__menu" id="<?= jyb_h($menuId) ?>" role="menu" hidden><a role="menuitem" href="<?= jyb_h(jyb_admin_url('categories', ['id'=>$id])) ?>"><?= jyb_h(jyb_t('Edit category')) ?></a></div></div></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</section>
