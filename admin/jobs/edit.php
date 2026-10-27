<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$route = trim(jyb_scalar($_GET['page'] ?? ''), '/');
$creating = $route === 'admin/tools/job-board/jobs/create';
$jybActorId = jyb_admin_guard($pdo, $creating ? JYB_PERMISSION_CREATE : JYB_PERMISSION_EDIT);
$id = $creating ? 0 : jyb_positive_id($_GET['id'] ?? 0);
$job = $id > 0 ? jyb_job($pdo, $id) : null;
if (!$creating && !is_array($job)) { adiwira_render_404(); return; }
$canPublish = jyb_admin_can($pdo, $jybActorId, JYB_PERMISSION_PUBLISH);
if (!$creating && (($job['status'] === 'published' && !$canPublish) || $job['status'] === 'archived')) { adiwira_render_404(); return; }

$locale = jyb_default_content_locale();
$job ??= [
    'id'=>0, 'version'=>0, 'company_id'=>0, 'source_locale'=>$locale, 'status'=>'draft',
    'employment_type'=>'full-time', 'workplace_type'=>'on-site', 'reference_code'=>'',
    'application_type'=>'url', 'application_target'=>'', 'featured'=>0, 'opens_at_utc'=>null,
    'closes_at_utc'=>null, 'slug'=>'', 'title'=>'', 'summary'=>'', 'description'=>'',
    'location_text'=>'', 'translation_status'=>'draft', 'texts'=>[], 'category_ids'=>[],
];

$texts = [];
foreach ($job['texts'] as $text) $texts[$text['locale']] = $text;
if ($texts === []) {
    $texts[$job['source_locale']] = [
        'locale'=>$job['source_locale'], 'slug'=>$job['slug'], 'title'=>$job['title'],
        'summary'=>$job['summary'], 'description'=>$job['description'],
        'location_text'=>$job['location_text'], 'translation_status'=>$job['translation_status'],
    ];
}
if (!jyb_locale_edit_allowed($pdo, $jybActorId, (string)$job['source_locale'])) { adiwira_render_404(); return; }
$editableLocales = jyb_editable_locales($pdo, $jybActorId);
$locales = array_values(array_unique(array_merge([(string)$job['source_locale']], $editableLocales)));
$locales = array_values(array_filter($locales, static fn(string $candidate): bool => in_array($candidate, $editableLocales, true)));
$companies = jyb_admin_companies($pdo);
$categories = array_values(array_filter(jyb_admin_categories($pdo), static fn(array $category): bool => ($category['status'] ?? '') === 'active'));
$action = $creating ? 'jobs/create-save' : 'jobs/save';
$timezone = function_exists('app_timezone_id') ? app_timezone_id() : 'UTC';
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'jobs'); ?>

  <header class="jyb-admin-head">
    <div class="jyb-admin-heading">
      <p><?= jyb_h(jyb_t('Job editor')) ?></p>
      <h1><?= jyb_h($creating ? jyb_t('Create job') : jyb_t('Edit job')) ?></h1>
    </div>
    <div class="jyb-admin-actions">
      <a class="adam-cancle" href="<?= jyb_h(jyb_admin_url()) ?>"><?= jyb_h(jyb_t('Back to jobs')) ?></a>
    </div>
  </header>

  <?php if ($companies === []): ?>
    <div class="jyb-banner"><?= jyb_h(jyb_t('Create a company before creating a job.')) ?></div>
  <?php endif; ?>

  <form method="post" action="<?= jyb_h(jyb_admin_url($action)) ?>">
    <input type="hidden" name="csrf_token" value="<?= jyb_h(csrf_token()) ?>">
    <input type="hidden" name="id" value="<?= (int)$job['id'] ?>">
    <input type="hidden" name="version" value="<?= (int)$job['version'] ?>">

    <div class="jyb-form-stack">
      <section class="jyb-card">
        <div class="jyb-card-head">
          <div class="jyb-admin-heading">
            <h2><?= jyb_h(jyb_t('Job details')) ?></h2>
            <p><?= jyb_h(jyb_t('Set the vacancy owner, work arrangement, and publication window.')) ?></p>
          </div>
          <span class="jyb-status <?= jyb_h(jyb_admin_status_class((string)$job['status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$job['status']))) ?></span>
        </div>
        <div class="jyb-grid two">
          <label><?= jyb_h(jyb_t('Company')) ?><select class="inp" name="company_id" required><option value=""><?= jyb_h(jyb_t('Select company')) ?></option><?php foreach ($companies as $company): ?><option value="<?= (int)$company['id'] ?>" <?= (int)$job['company_id'] === (int)$company['id'] ? 'selected' : '' ?>><?= jyb_h($company['name']) ?></option><?php endforeach; ?></select></label>
          <label><?= jyb_h(jyb_t('Source locale')) ?><input class="inp" name="source_locale" maxlength="12" value="<?= jyb_h($job['source_locale']) ?>" <?= $creating ? '' : 'readonly' ?> required></label>
          <label><?= jyb_h(jyb_t('Employment type')) ?><select class="inp" name="employment_type"><?php foreach (jyb_employment_types() as $type): ?><option value="<?= jyb_h($type) ?>" <?= $job['employment_type'] === $type ? 'selected' : '' ?>><?= jyb_h(jyb_t(ucwords(str_replace('-', ' ', $type)))) ?></option><?php endforeach; ?></select></label>
          <label><?= jyb_h(jyb_t('Workplace type')) ?><select class="inp" name="workplace_type"><?php foreach (jyb_workplace_types() as $type): ?><option value="<?= jyb_h($type) ?>" <?= $job['workplace_type'] === $type ? 'selected' : '' ?>><?= jyb_h(jyb_t(ucwords(str_replace('-', ' ', $type)))) ?></option><?php endforeach; ?></select></label>
          <label><?= jyb_h(jyb_t('Reference code')) ?><input class="inp" name="reference_code" maxlength="100" value="<?= jyb_h($job['reference_code']) ?>"></label>
          <label class="jyb-check"><input type="checkbox" name="featured" value="1" <?= $job['featured'] ? 'checked' : '' ?>> <?= jyb_h(jyb_t('Featured job')) ?></label>
          <label><?= jyb_h(jyb_t('Opens at (%s)', $timezone)) ?><input class="inp" type="datetime-local" name="opens_at_utc" value="<?= jyb_h(jyb_admin_datetime_input($job['opens_at_utc'])) ?>"></label>
          <label><?= jyb_h(jyb_t('Closes at (%s)', $timezone)) ?><input class="inp" type="datetime-local" name="closes_at_utc" value="<?= jyb_h(jyb_admin_datetime_input($job['closes_at_utc'])) ?>"></label>
        </div>
      </section>

      <section class="jyb-card">
        <div class="jyb-card-head">
          <div class="jyb-admin-heading">
            <h2><?= jyb_h(jyb_t('Application target')) ?></h2>
            <p><?= jyb_h(jyb_t('Choose exactly one HTTPS URL or email address. Job Board does not collect applications.')) ?></p>
          </div>
        </div>
        <div class="jyb-grid two">
          <label><?= jyb_h(jyb_t('Target type')) ?><select class="inp" name="application_type"><option value="url" <?= $job['application_type'] === 'url' ? 'selected' : '' ?>><?= jyb_h(jyb_t('HTTPS URL')) ?></option><option value="email" <?= $job['application_type'] === 'email' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Email')) ?></option></select></label>
          <label><?= jyb_h(jyb_t('Application target')) ?><input class="inp" name="application_target" maxlength="2048" required value="<?= jyb_h($job['application_target']) ?>"></label>
        </div>
      </section>

      <section class="jyb-card">
        <div class="jyb-card-head"><h2><?= jyb_h(jyb_t('Categories')) ?></h2></div>
        <?php if ($categories === []): ?>
          <p class="jyb-muted"><?= jyb_h(jyb_t('No active categories are available.')) ?></p>
        <?php else: ?>
          <div class="jyb-checks"><?php foreach ($categories as $category): ?><label class="jyb-check"><input type="checkbox" name="category_ids[]" value="<?= (int)$category['id'] ?>" <?= in_array((int)$category['id'], $job['category_ids'], true) ? 'checked' : '' ?>> <?= jyb_h($category['name']) ?></label><?php endforeach; ?></div>
        <?php endif; ?>
      </section>

      <?php foreach ($locales as $textLocale): ?>
        <?php $text = $texts[$textLocale] ?? ['slug'=>'', 'title'=>'', 'summary'=>'', 'description'=>'', 'location_text'=>'', 'translation_status'=>'draft']; ?>
        <section class="jyb-card">
          <div class="jyb-card-head">
            <div class="jyb-admin-heading">
              <h2><?= jyb_h(jyb_t('%s content', strtoupper($textLocale))) ?><?= $textLocale === $job['source_locale'] ? ' *' : '' ?></h2>
              <p><?= jyb_h($textLocale === $job['source_locale'] ? jyb_t('Source content') : jyb_t('Localized content')) ?></p>
            </div>
            <span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$text['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$text['translation_status']))) ?></span>
          </div>
          <input type="hidden" name="texts[<?= jyb_h($textLocale) ?>][locale]" value="<?= jyb_h($textLocale) ?>">
          <div class="jyb-grid two">
            <label><?= jyb_h(jyb_t('Title')) ?><input class="inp" name="texts[<?= jyb_h($textLocale) ?>][title]" maxlength="191" value="<?= jyb_h($text['title']) ?>" <?= $textLocale === $job['source_locale'] ? 'required' : '' ?>></label>
            <label><?= jyb_h(jyb_t('Localized slug')) ?><input class="inp" name="texts[<?= jyb_h($textLocale) ?>][slug]" maxlength="191" value="<?= jyb_h($text['slug']) ?>" <?= $textLocale === $job['source_locale'] ? 'required' : '' ?>></label>
            <label><?= jyb_h(jyb_t('Location')) ?><input class="inp" name="texts[<?= jyb_h($textLocale) ?>][location_text]" maxlength="191" value="<?= jyb_h($text['location_text']) ?>"></label>
            <label><?= jyb_h(jyb_t('Translation status')) ?><select class="inp" name="texts[<?= jyb_h($textLocale) ?>][translation_status]"><option value="draft" <?= $text['translation_status'] === 'draft' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Draft')) ?></option><option value="published" <?= $text['translation_status'] === 'published' ? 'selected' : '' ?>><?= jyb_h(jyb_t('Published')) ?></option></select></label>
            <label class="wide"><?= jyb_h(jyb_t('Summary')) ?><textarea class="inp" name="texts[<?= jyb_h($textLocale) ?>][summary]" maxlength="1000" rows="3"><?= jyb_h($text['summary']) ?></textarea></label>
            <label class="wide"><?= jyb_h(jyb_t('Description')) ?><textarea class="inp" name="texts[<?= jyb_h($textLocale) ?>][description]" maxlength="30000" rows="10" <?= $textLocale === $job['source_locale'] ? 'required' : '' ?>><?= jyb_h($text['description']) ?></textarea></label>
          </div>
        </section>
      <?php endforeach; ?>

      <section class="jyb-card">
        <div class="jyb-card-head">
          <div class="jyb-admin-heading">
            <h2><?= jyb_h(jyb_t('Save changes')) ?></h2>
            <p><?= jyb_h(jyb_t('Saving content does not change the job workflow status.')) ?></p>
          </div>
        </div>
        <div class="jyb-form-actions">
          <button class="adam-btn" type="submit" <?= $companies === [] ? 'disabled' : '' ?>><?= jyb_h(jyb_t('Save job')) ?></button>
          <a class="adam-cancle" href="<?= jyb_h(jyb_admin_url()) ?>"><?= jyb_h(jyb_t('Cancel')) ?></a>
        </div>
      </section>
    </div>
  </form>
</section>
