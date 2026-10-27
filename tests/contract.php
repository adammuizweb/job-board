<?php
declare(strict_types=1);
$root=dirname(__DIR__);$failures=[];$check=static function(bool $ok,string $message)use(&$failures):void{echo($ok?'PASS':'FAIL').' '.$message.PHP_EOL;if(!$ok)$failures[]=$message;};
$manifest=json_decode((string)file_get_contents($root.'/plugin.json'),true,512,JSON_THROW_ON_ERROR);
$check(($manifest['name']??'')==='job-board'&&($manifest['version']??'')==='0.1.1','manifest identity and version are exact');
$check(($manifest['icon']??'')==='icon.svg'&&is_file($root.'/icon.svg'),'manifest declares the packaged SVG icon');
$check(($manifest['author']??'')==='Adam Muiz'
    &&($manifest['author_uri']??'')==='https://jyavani.com/member/adammuiz/'
    &&($manifest['plugin_uri']??'')==='https://jyavani.com/plugin/job-board/'
    &&($manifest['homepage']??'')==='https://github.com/adammuizweb/job-board'
    &&($manifest['github_url']??'')==='https://github.com/adammuizweb/job-board'
    &&($manifest['store']??null)===['url'=>'https://jyavani.com/plugin-store','slug'=>'job-board'],
    'manifest declares canonical author, repository, listing, and Store identity');
$check(($manifest['requires']['jyavani']??'')==='>=2.3.187'&&($manifest['requires']['php']??'')==='>=8.1','runtime requirements are exact');
$expected=['plugin.job-board.workspace.read','plugin.job-board.jobs.create','plugin.job-board.jobs.edit','plugin.job-board.jobs.publish','plugin.job-board.companies.manage','plugin.job-board.categories.manage','plugin.job-board.settings.manage','plugin.job-board.audit.view'];
$check(array_column($manifest['permissions']??[],'key')===$expected,'manifest declares all granular permissions in order');
$permissions=[];foreach($manifest['permissions']??[] as $permission)$permissions[$permission['key']]=$permission;
$check(count(array_filter($permissions,static fn(array $p):bool=>($p['delegable']??false)===true))===7
    && ($permissions['plugin.job-board.settings.manage']['delegable']??true)===false,
    'operational permissions are delegable while route settings remain owner-controlled');
$routes=array_column($manifest['admin']['pages']??[],'route');$check(count($routes)===14&&count(array_unique($routes))===14,'manifest has fourteen unique exact admin routes');
$check(($manifest['admin']['nav'][0]['page']??'')==='admin/tools/job-board'&&($manifest['admin']['nav'][0]['parent']??'')==='tools','manifest navigation targets the workspace');
$staticSources=array_column($manifest['static']['copy']??[],'from');
$check(count($staticSources)===4&&in_array('assets/js/admin.js',$staticSources,true),'manifest publishes only four allowlisted static assets including the admin controller');
$check(($manifest['requires']['plugins']??null)===[],'Content Translation is not a hard dependency');
$entry=(string)file_get_contents($root.'/plugin.php');$shortcodes=(string)file_get_contents($root.'/includes/shortcodes.php');
$check(str_contains($entry,"const JYB_VERSION = '0.1.1'")&&str_contains($entry,'register_frontend_route'),'entrypoint version and frontend route are registered');
$check(str_contains($shortcodes,"register_shortcode_source_provider('job_board_jobs'")&&str_contains($shortcodes,"'owner'=>'job-board'")&&str_contains($shortcodes,"'job_board_list'"),'Preset provider and namespaced widget identities are exact');
$check(str_contains($shortcodes,"isset(\$GLOBALS['_widget_shortcode_handlers']['job_board_list'])")&&str_contains($entry,'shortcode integration collided'),'widget collisions fail rather than overwrite');
$package=(string)file_get_contents($root.'/tools/build-package.php');
$check(str_contains($package,"'languages/id.php'")&&str_contains($package,"'languages/de.php'"),'package allowlist includes both bundled translation catalogs');
$check(str_contains($package,"'assets/js/admin.js'"),'package allowlist includes the admin controller');
$check(str_contains($package,"'icon.svg'"),'package allowlist includes the plugin icon');
$check(str_contains($package,"addFile(\$real,\$relative)")&&!str_contains($package,"'job-board/'.\$relative"),'package builder places plugin.json and all files at the ZIP root');
$adminSource='';foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/admin',FilesystemIterator::SKIP_DOTS)) as $file)if($file->isFile()&&$file->getExtension()==='php')$adminSource.=(string)file_get_contents($file->getPathname());
$adminCss=(string)file_get_contents($root.'/assets/css/admin.css');$adminJs=(string)file_get_contents($root.'/assets/js/admin.js');$jobsIndex=(string)file_get_contents($root.'/admin/jobs/index.php');
$check(!str_contains($adminSource,'adam-input')&&!str_contains($adminSource,'adam-button')&&str_contains($adminSource,'class="inp"'),'admin forms use Core input and button classes');
$check(str_contains($adminCss,'var(--adam-card)')&&str_contains($adminCss,'var(--adam-surface-2)')&&!str_contains($adminCss,'var(--adam-surface,'),'admin surfaces use current Core theme tokens without light fallbacks');
$check(str_contains($adminSource,'adam-actions__trigger')&&str_contains($jobsIndex,'name="id"')&&str_contains($jobsIndex,'name="status"'),'job row overflow actions submit the transition endpoint contract');
$check(str_contains($adminJs,'NewNotifConfirm')&&str_contains($adminJs,'window.confirm'),'admin workflow confirmations use the Core dialog with a browser fallback');
$tracked='';$sourceTree=new RecursiveCallbackFilterIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),static fn(SplFileInfo $file):bool=>$file->getFilename()!=='.git');foreach(new RecursiveIteratorIterator($sourceTree) as $file)if($file->isFile())$tracked.=(string)file_get_contents($file->getPathname());
preg_match_all('/https?:\/\/(?!schema\.org|example\.)[a-z0-9.-]+\.[a-z]{2,}/i',$tracked,$externalOrigins);
$check(array_diff(array_values(array_unique($externalOrigins[0]??[])),['http://www.w3.org','https://jyavani.com','https://github.com'])===[],'source URLs are limited to the SVG namespace and canonical upstream release identity');
if($failures!==[]){fwrite(STDERR,count($failures)." contract check(s) failed.\n");exit(1);}echo "Job Board contract passed.\n";
