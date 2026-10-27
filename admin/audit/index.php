<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_AUDIT);
$page = jyb_bounded_int($_GET['p'] ?? 1, 1, 1000000, 1);
$perPage = 50;
$total = (int)$pdo->query('SELECT COUNT(*) FROM jyb_audit')->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$rows = $pdo->query('SELECT * FROM jyb_audit ORDER BY id DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage))->fetchAll(PDO::FETCH_ASSOC) ?: [];
?>
<section class="jyb-admin">
  <?php jyb_admin_nav($pdo, $jybActorId, 'audit'); ?>
  <header class="jyb-admin-head">
    <div class="jyb-admin-heading"><p><?= jyb_h(jyb_t('Governance')) ?></p><h1><?= jyb_h(jyb_t('Audit trail')) ?></h1><p><?= jyb_h(jyb_t('Review Job Board changes recorded in the same transaction as each mutation.')) ?></p></div>
    <div class="jyb-admin-actions"><span class="jyb-status jyb-status--draft"><?= jyb_h(jyb_t('%d events', $total)) ?></span></div>
  </header>

  <section class="jyb-card jyb-card--flush">
    <div class="jyb-table">
      <table class="adam-table jyb-table-responsive">
        <thead><tr><th><?= jyb_h(jyb_t('Event')) ?></th><th><?= jyb_h(jyb_t('Entity')) ?></th><th><?= jyb_h(jyb_t('Actor')) ?></th><th><?= jyb_h(jyb_t('Time')) ?></th><th><?= jyb_h(jyb_t('Metadata')) ?></th></tr></thead>
        <tbody>
          <?php if ($rows === []): ?><tr><td class="jyb-empty" colspan="5"><strong><?= jyb_h(jyb_t('No audit events have been recorded.')) ?></strong><span><?= jyb_h(jyb_t('Job Board mutations will appear here.')) ?></span></td></tr><?php endif; ?>
          <?php foreach ($rows as $row): ?>
            <?php
            $before = $row['before_json'] ? json_encode(json_decode((string)$row['before_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            $after = $row['after_json'] ? json_encode(json_decode((string)$row['after_json'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null;
            ?>
            <tr>
              <td data-label="<?= jyb_h(jyb_t('Event')) ?>"><strong><?= jyb_h(ucwords(str_replace('.', ' ', (string)$row['action']))) ?></strong></td>
              <td data-label="<?= jyb_h(jyb_t('Entity')) ?>"><?= jyb_h($row['entity_type']) ?><?= isset($row['entity_id']) ? ' #' . (int)$row['entity_id'] : '' ?></td>
              <td data-label="<?= jyb_h(jyb_t('Actor')) ?>"><?= jyb_h(jyb_t('Actor #%d', (int)$row['actor_id'])) ?></td>
              <td data-label="<?= jyb_h(jyb_t('Time')) ?>"><time datetime="<?= jyb_h(str_replace(' ', 'T', (string)$row['created_at_utc']) . 'Z') ?>"><?= jyb_h(jyb_admin_datetime((string)$row['created_at_utc'])) ?></time></td>
              <td data-label="<?= jyb_h(jyb_t('Metadata')) ?>">
                <?php if ($before !== null || $after !== null): ?>
                  <details class="jyb-details"><summary><?= jyb_h(jyb_t('View change')) ?></summary><?php if ($before !== null): ?><div class="jyb-field"><span class="jyb-label"><?= jyb_h(jyb_t('Before')) ?></span><pre class="jyb-json"><?= jyb_h($before) ?></pre></div><?php endif; ?><?php if ($after !== null): ?><div class="jyb-field"><span class="jyb-label"><?= jyb_h(jyb_t('After')) ?></span><pre class="jyb-json"><?= jyb_h($after) ?></pre></div><?php endif; ?></details>
                <?php else: ?><span class="jyb-muted">—</span><?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <?php if ($pages > 1): ?>
    <nav class="jyb-pagination" aria-label="<?= jyb_h(jyb_t('Audit pagination')) ?>"><span><?= jyb_h(jyb_t('Page %d of %d', $page, $pages)) ?></span><?php if ($page > 1): ?><a href="<?= jyb_h(jyb_admin_url('audit', ['p'=>$page - 1])) ?>"><?= jyb_h(jyb_t('Previous')) ?></a><?php endif; ?><?php if ($page < $pages): ?><a href="<?= jyb_h(jyb_admin_url('audit', ['p'=>$page + 1])) ?>"><?= jyb_h(jyb_t('Next')) ?></a><?php endif; ?></nav>
  <?php endif; ?>
</section>
