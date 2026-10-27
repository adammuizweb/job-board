<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/includes/validation.php';$root=dirname(__DIR__);$failures=[];$check=static function(bool $ok,string $message)use(&$failures):void{echo($ok?'PASS':'FAIL').' '.$message.PHP_EOL;if(!$ok)$failures[]=$message;};
$check(jyb_slug('Example Role')==='example-role'&&jyb_slug('../')===null,'slugs normalize without traversal');
$check(jyb_locale('en_US')==='en-US'&&jyb_locale('pt-BR')==='pt-BR'&&jyb_locale('bad/value')===null,'locales are bounded while preserving registered identifier case');
$check(jyb_https_url('https://example.test/apply')==='https://example.test/apply'&&jyb_https_url('http://example.test')===null&&jyb_https_url('javascript:alert(1)')===null,'external URLs require HTTPS');
$check(jyb_application_target('email','jobs@example.test')!==null&&jyb_application_target('email',"x@example.test\r\nBcc:a@example.test")===null,'application email validates and blocks header injection');
$check(jyb_application_target('url','https://example.test/apply')!==null&&jyb_application_target('url','mailto:x@example.test')===null,'application target is exactly one typed destination');
$check(jyb_datetime_utc('2026-10-26T12:30')==='2026-10-26 12:30:00'&&jyb_datetime_utc('not-a-date')===null,'UTC datetime-local input validates strictly');
$check(jyb_job_statuses()===['draft','published','closed','archived'],'dashboard and workflow share the canonical job statuses');
$filters=jyb_public_filters(['q'=>['bad'],'company'=>'999999999999999999999','employment'=>'invalid','workplace'=>'remote','featured'=>'1']);$check($filters['q']===''&&$filters['company']===0&&$filters['employment']===''&&$filters['workplace']==='remote'&&$filters['featured']==='1','public filters reject arrays and invalid bounded values');
$mutations=['admin/jobs/save.php','admin/jobs/transition.php','admin/companies/save.php','admin/categories/save.php','admin/settings/save.php'];foreach($mutations as $file){$source=(string)file_get_contents($root.'/'.$file);$check(str_contains($source,'jyb_admin_require_post')&&str_contains($source,'jyb_admin_reauthorize')&&str_contains($source,'jyb_audit')&&str_contains($source,'rollBack'),'mutation security contract: '.$file);}
$save=(string)file_get_contents($root.'/admin/jobs/save.php');$check(str_contains($save,'FOR UPDATE')&&str_contains($save,'version=version+1')&&str_contains($save,'JYB_PERMISSION_PUBLISH'),'job save locks rows, versions writes, and gates live content');
$transition=(string)file_get_contents($root.'/admin/jobs/transition.php');
$check(!str_contains($save,"\$_POST['status']")&&str_contains($save,"VALUES (?,?,'draft'")&&str_contains($transition,'UPDATE jyb_jobs SET status=?'),'workflow state is writable only through the transition endpoint');
$check(str_contains($save,'if (!$creating && $id < 1)'),'edit-save cannot fall through to the create branch');
$check(str_contains($transition,"'draft'=>['published','archived']")&&str_contains($transition,"'archived'=>['draft']")&&str_contains($transition,'jyb_application_target'),'transitions are allowlisted and publication revalidates the application target');
$companies=(string)file_get_contents($root.'/admin/companies/save.php');$categories=(string)file_get_contents($root.'/admin/categories/save.php');
$check(str_contains($companies,'jyb_locale_edit_allowed')&&str_contains($categories,'jyb_locale_edit_allowed')&&str_contains($save,'jyb_locale_edit_allowed'),'all localized mutations enforce transaction-time locale authority');
$check(str_contains($companies,"status='published' LIMIT 1 FOR UPDATE")&&str_contains($categories,"j.status='published'"),'published jobs prevent company or category deactivation');
$repository=(string)file_get_contents($root.'/includes/repository.php');
$settings=(string)file_get_contents($root.'/admin/settings/save.php');
$check(str_contains($repository,'content_route_path_conflict')&&str_contains($repository,'SELECT c.id,t.name,t.slug'),'base paths use the complete Core conflict contract and detail categories select a real identity column');
$check(str_contains($settings,'jyb_assert_base_path_available($pdo, $base, true)')&&str_contains($settings,'ORDER BY `key` FOR UPDATE'),'settings revalidate base-path ownership under deterministic route-setting locks');
$check(!is_file($root.'/admin/jobs/delete.php'),'ordinary job hard-delete UI is absent');
if($failures!==[]){fwrite(STDERR,count($failures)." validation/security check(s) failed.\n");exit(1);}echo "Job Board validation and security contract passed.\n";
