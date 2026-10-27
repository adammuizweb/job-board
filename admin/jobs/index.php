<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/common.php';
$actorId = jyb_admin_guard($pdo, JYB_PERMISSION_READ);
$locale = function_exists('content_default_locale') ? content_default_locale() : 'en';
$q = jyb_scalar($_GET['q'] ?? '', 120);
$status = jyb_scalar($_GET['status'] ?? '', 20);
if ($status !== '' && !in_array($status, jyb_job_statuses(), true)) $status = '';

$where = ['t.locale=?'];
$params = [$locale];
if ($q !== '') {
    $where[] = '(t.title LIKE ? OR t.location_text LIKE ? OR c.name LIKE ?)';
    $like = '%' . $q . '%';
    array_push($params, $like, $like, $like);
}
if ($status !== '') {
    $where[] = 'j.status=?';
    $params[] = $status;
}

$stmt = $pdo->prepare('SELECT j.id,j.status,j.reference_code,j.featured,j.updated_at_utc,j.version,t.title,t.location_text,t.translation_status,c.name company_name FROM jyb_jobs j JOIN jyb_job_texts t ON t.job_id=j.id JOIN jyb_companies co ON co.id=j.company_id JOIN jyb_company_texts c ON c.company_id=co.id AND c.locale=co.source_locale WHERE ' . implode(' AND ', $where) . ' ORDER BY j.updated_at_utc DESC,j.id DESC LIMIT 100');
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

$counts = array_fill_keys(jyb_job_statuses(), 0);
foreach ($pdo->query('SELECT status,COUNT(*) total FROM jyb_jobs GROUP BY status')->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
    if (isset($counts[$row['status']])) $counts[$row['status']] = (int)$row['total'];
}
$canCreate = jyb_admin_can($pdo, $actorId, JYB_PERMISSION_CREATE);
$canEdit = jyb_admin_can($pdo, $actorId, JYB_PERMISSION_EDIT);
$canPublish = jyb_admin_can($pdo, $actorId, JYB_PERMISSION_PUBLISH);
?>
<div class="jyb-admin">
  <?php jyb_admin_nav($pdo, $actorId, 'jobs'); ?>

  <header class="jyb-admin-head">
    <div class="jyb-admin-heading">
      <h1><?= jyb_h(jyb_t('Jobs')) ?></h1>
      <p><?= jyb_h(jyb_t('Create and manage localized vacancies.')) ?></p>
    </div>
    <?php if ($canCreate): ?>
      <div class="jyb-admin-actions">
        <a class="adam-btn" href="<?= jyb_h(jyb_admin_url('jobs/create')) ?>"><?= jyb_h(jyb_t('Add job')) ?></a>
      </div>
    <?php endif; ?>
  </header>

  <section class="jyb-stats" aria-label="<?= jyb_h(jyb_t('Job status summary')) ?>">
    <?php foreach (jyb_job_statuses() as $itemStatus): ?>
      <article class="jyb-stat">
        <span><?= jyb_h(jyb_t(ucfirst($itemStatus))) ?></span>
        <strong><?= $counts[$itemStatus] ?></strong>
      </article>
    <?php endforeach; ?>
  </section>

  <section class="jyb-card">
    <form class="jyb-filters" method="get">
      <input type="hidden" name="page" value="admin/tools/job-board">
      <label>
        <?= jyb_h(jyb_t('Search jobs')) ?>
        <input class="inp" name="q" value="<?= jyb_h($q) ?>" placeholder="<?= jyb_h(jyb_t('Title, company, or location')) ?>">
      </label>
      <label>
        <?= jyb_h(jyb_t('Status')) ?>
        <select class="inp" name="status">
          <option value=""><?= jyb_h(jyb_t('All statuses')) ?></option>
          <?php foreach (jyb_job_statuses() as $itemStatus): ?>
            <option value="<?= jyb_h($itemStatus) ?>" <?= $status === $itemStatus ? 'selected' : '' ?>><?= jyb_h(jyb_t(ucfirst($itemStatus))) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <button class="adam-btn" type="submit"><?= jyb_h(jyb_t('Filter')) ?></button>
    </form>
  </section>

  <section class="jyb-card jyb-card--flush">
    <div class="jyb-table">
      <table class="adam-table jyb-table-responsive">
        <thead>
          <tr>
            <th><?= jyb_h(jyb_t('Job')) ?></th>
            <th><?= jyb_h(jyb_t('Company')) ?></th>
            <th><?= jyb_h(jyb_t('Location')) ?></th>
            <th><?= jyb_h(jyb_t('Status')) ?></th>
            <th><?= jyb_h(jyb_t('Updated')) ?></th>
            <th class="jyb-table-actions"><?= jyb_h(jyb_t('Actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php if ($jobs === []): ?>
            <tr><td class="jyb-empty" colspan="6"><strong><?= jyb_h(jyb_t('No jobs found.')) ?></strong><span><?= jyb_h(jyb_t('Try changing the filters or create a new vacancy.')) ?></span></td></tr>
          <?php endif; ?>
          <?php foreach ($jobs as $job): ?>
            <?php
            $id = (int)$job['id'];
            $menuId = 'jyb-job-actions-' . $id;
            $hasActions = $canEdit || $canPublish;
            $workflowTargets = [
                'draft'=>['published'=>'Publish', 'archived'=>'Archive'],
                'published'=>['closed'=>'Close', 'archived'=>'Archive'],
                'closed'=>['published'=>'Publish again', 'archived'=>'Archive'],
                'archived'=>['draft'=>'Restore to draft'],
            ];
            ?>
            <tr>
              <td data-label="<?= jyb_h(jyb_t('Job')) ?>">
                <div class="jyb-title-cell">
                  <?php if ($canEdit): ?>
                    <a href="<?= jyb_h(jyb_admin_url('jobs/edit', ['id'=>$id])) ?>"><?= jyb_h($job['title']) ?></a>
                  <?php else: ?>
                    <strong><?= jyb_h($job['title']) ?></strong>
                  <?php endif; ?>
                  <small><?= jyb_h((string)($job['reference_code'] ?: '#' . $id)) ?><?= (int)$job['featured'] === 1 ? ' · ' . jyb_h(jyb_t('Featured')) : '' ?></small>
                </div>
              </td>
              <td data-label="<?= jyb_h(jyb_t('Company')) ?>"><?= jyb_h($job['company_name']) ?></td>
              <td data-label="<?= jyb_h(jyb_t('Location')) ?>"><?= jyb_h($job['location_text'] ?: '—') ?></td>
              <td data-label="<?= jyb_h(jyb_t('Status')) ?>">
                <span class="jyb-status <?= jyb_h(jyb_admin_status_class((string)$job['status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$job['status']))) ?></span>
              </td>
              <td data-label="<?= jyb_h(jyb_t('Updated')) ?>">
                <div class="jyb-title-cell">
                  <span><?= jyb_h(jyb_admin_datetime((string)$job['updated_at_utc'])) ?></span>
                  <small><span class="jyb-translation <?= jyb_h(jyb_admin_translation_class((string)$job['translation_status'])) ?>"><?= jyb_h(jyb_t(ucfirst((string)$job['translation_status']))) ?></span></small>
                </div>
              </td>
              <td class="jyb-table-actions" data-label="<?= jyb_h(jyb_t('Actions')) ?>">
                <?php if ($hasActions): ?>
                  <div class="adam-actions">
                    <?php jyb_admin_actions_trigger($menuId); ?>
                    <div class="adam-actions__menu" id="<?= jyb_h($menuId) ?>" role="menu" hidden>
                      <?php if ($canEdit): ?>
                        <a role="menuitem" href="<?= jyb_h(jyb_admin_url('jobs/edit', ['id'=>$id])) ?>"><?= jyb_h(jyb_t('Edit job')) ?></a>
                      <?php endif; ?>
                      <?php if ($canPublish): ?>
                        <?php foreach ($workflowTargets[$job['status']] ?? [] as $target => $targetLabel): ?>
                          <?php $danger = $target === 'archived'; ?>
                          <form method="post" action="<?= jyb_h(jyb_admin_url('jobs/transition')) ?>" data-jyb-confirm="<?= jyb_h(jyb_t('%s this job?', $targetLabel)) ?>" data-jyb-confirm-title="<?= jyb_h(jyb_t('Update job status')) ?>" data-jyb-confirm-text="<?= jyb_h(jyb_t($targetLabel)) ?>" data-jyb-confirm-variant="<?= $danger ? 'danger' : 'warning' ?>">
                            <input type="hidden" name="csrf_token" value="<?= jyb_h(csrf_token()) ?>">
                            <input type="hidden" name="id" value="<?= $id ?>">
                            <input type="hidden" name="version" value="<?= (int)$job['version'] ?>">
                            <input type="hidden" name="status" value="<?= jyb_h($target) ?>">
                            <button class="<?= $danger ? 'is-danger' : '' ?>" role="menuitem" type="submit"><?= jyb_h(jyb_t($targetLabel)) ?></button>
                          </form>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </div>
                  </div>
                <?php else: ?>
                  <span class="jyb-muted">—</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>
</div>
