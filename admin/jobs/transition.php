<?php
declare(strict_types=1);

require dirname(__DIR__) . '/common.php';
$jybActorId = jyb_admin_guard($pdo, JYB_PERMISSION_PUBLISH);
jyb_admin_require_post();
$id = jyb_positive_id($_POST['id'] ?? 0);
$version = jyb_bounded_int($_POST['version'] ?? 0, 1, PHP_INT_MAX, 0);
$target = jyb_scalar($_POST['status'] ?? '');

try {
    $allowed = [
        'draft'=>['published','archived'],
        'published'=>['closed','archived'],
        'closed'=>['published','archived'],
        'archived'=>['draft'],
    ];
    $pdo->beginTransaction();
    jyb_admin_reauthorize($pdo, $jybActorId, JYB_PERMISSION_PUBLISH);
    $hint = $pdo->prepare('SELECT company_id,source_locale FROM jyb_jobs WHERE id=? LIMIT 1');
    $hint->execute([$id]);
    $jobHint = $hint->fetch(PDO::FETCH_ASSOC);
    if (!is_array($jobHint)) throw new DomainException(jyb_t('This job changed after it was loaded. Reload before trying again.'));
    $company = $pdo->prepare('SELECT status FROM jyb_companies WHERE id=? FOR UPDATE');
    $company->execute([(int)$jobHint['company_id']]);
    $companyStatus = $company->fetchColumn();
    $companyText = $pdo->prepare('SELECT translation_status FROM jyb_company_texts WHERE company_id=? AND locale=? LIMIT 1 FOR UPDATE');
    $companyText->execute([(int)$jobHint['company_id'], (string)$jobHint['source_locale']]);
    $companyTextStatus = $companyText->fetchColumn();
    $statement = $pdo->prepare('SELECT * FROM jyb_jobs WHERE id=? FOR UPDATE');
    $statement->execute([$id]);
    $job = $statement->fetch(PDO::FETCH_ASSOC);
    if (!is_array($job) || (int)$job['version'] !== $version
        || (int)$job['company_id'] !== (int)$jobHint['company_id']
        || (string)$job['source_locale'] !== (string)$jobHint['source_locale']) {
        throw new DomainException(jyb_t('This job changed after it was loaded. Reload before trying again.'));
    }
    if (!in_array($target, $allowed[$job['status']] ?? [], true)) throw new DomainException(jyb_t('That job transition is not allowed.'));
    if ($target === 'published') {
        if (jyb_application_target($job['application_type'], $job['application_target']) === null) {
            throw new DomainException(jyb_t('A valid application target is required before publishing.'));
        }
        $jobText = $pdo->prepare("SELECT 1 FROM jyb_job_texts WHERE job_id=? AND locale=? AND translation_status='published' LIMIT 1 FOR UPDATE");
        $jobText->execute([$id,$job['source_locale']]);
        if ($companyStatus !== 'active' || $companyTextStatus !== 'published' || !$jobText->fetchColumn()) {
            throw new DomainException(jyb_t('The source job and company translations must be published before publishing.'));
        }
    }
    $now = jyb_now_utc();
    $update = $pdo->prepare("UPDATE jyb_jobs SET status=?,published_at_utc=CASE WHEN ?='published' THEN ? ELSE published_at_utc END,closed_at_utc=CASE WHEN ?='closed' THEN ? WHEN ?='published' THEN NULL ELSE closed_at_utc END,archived_at_utc=CASE WHEN ?='archived' THEN ? WHEN ?<>'archived' THEN NULL ELSE archived_at_utc END,version=version+1,updated_by=?,updated_at_utc=? WHERE id=? AND version=?");
    $update->execute([$target,$target,$now,$target,$now,$target,$target,$now,$target,$jybActorId,$now,$id,$version]);
    if ($update->rowCount() !== 1) throw new DomainException(jyb_t('This job changed after it was loaded.'));
    jyb_audit($pdo,$jybActorId,'job.transitioned','job',$id,['status'=>$job['status']],['status'=>$target]);
    $pdo->commit();
    jyb_admin_redirect('success', jyb_t('Job status updated.'));
} catch (Throwable $error) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if (!$error instanceof DomainException) error_log('[job-board] transition failed: ' . $error->getMessage());
    jyb_admin_redirect('error', $error instanceof DomainException ? $error->getMessage() : jyb_t('The job status could not be updated.'));
}
