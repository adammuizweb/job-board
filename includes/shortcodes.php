<?php
declare(strict_types=1);

function jyb_provider_validate(array $config, array $context, ?PDO $pdo): array
{
    $errors = [];
    $unsupported = [
        'type'=>'article','category'=>'','author'=>null,'offset'=>0,'order_by'=>'created_at',
        'order_dir'=>'DESC','include_children'=>'1','date_from'=>null,'date_to'=>null,
    ];
    foreach ($unsupported as $key => $expected) {
        if (array_key_exists($key, $config) && $config[$key] !== $expected && (string)$config[$key] !== (string)$expected) {
            $errors[] = jyb_t('Job Board presets do not support Core post filters, offset, custom ordering, or date ranges.');
            break;
        }
    }
    $config['company'] = jyb_positive_id($config['company'] ?? 0);
    $config['job_category'] = jyb_positive_id($config['job_category'] ?? 0);
    $config['employment'] = in_array((string)($config['employment'] ?? ''), jyb_employment_types(), true) ? (string)$config['employment'] : '';
    $config['workplace'] = in_array((string)($config['workplace'] ?? ''), jyb_workplace_types(), true) ? (string)$config['workplace'] : '';
    $config['featured'] = (string)($config['featured'] ?? '0') === '1' ? '1' : '0';
    $config['open'] = (string)($config['open'] ?? '1') === '0' ? '0' : '1';
    $limit = filter_var($config['limit'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>24]]);
    if ($limit === false) $errors[] = jyb_t('Job Board limit must be between 1 and 24.'); else $config['limit'] = (int)$limit;
    $config['max_items'] = $config['limit'] ?? 6;
    $config['pagination'] = '0';
    return ['config'=>$config,'errors'=>$errors];
}

function jyb_provider_fetch(PDO $pdo, array $attrs, array $context = []): array
{
    $locale = in_array((string)($context['mode'] ?? ''), ['inline','stored'], true)
        ? jyb_default_content_locale()
        : jyb_current_locale();
    $filters = ['company'=>$attrs['company'] ?? 0,'job_category'=>$attrs['job_category'] ?? 0,'employment'=>$attrs['employment'] ?? '','workplace'=>$attrs['workplace'] ?? '','featured'=>$attrs['featured'] ?? '','open'=>$attrs['open'] ?? '1'];
    $result = jyb_public_job_page($pdo, $locale, $filters, 1, min(24, max(1, (int)($attrs['limit'] ?? 6))));
    $base = jyb_base_path($pdo); $items = [];
    foreach ($result['rows'] as $job) {
        $items[] = ['kind'=>'job','title'=>(string)$job['title'],'url'=>jyb_localized_path($base . '/' . rawurlencode((string)$job['slug']), $locale),'thumb'=>'','desc'=>(string)($job['summary'] ?? ''),'date_iso'=>!empty($job['published_at_utc']) ? str_replace(' ','T',(string)$job['published_at_utc']) . 'Z' : '','date_label'=>'','company'=>(string)$job['company_name'],'employment_type'=>(string)$job['employment_type'],'workplace_type'=>(string)$job['workplace_type'],'location'=>(string)($job['location_text'] ?? ''),'featured'=>(int)$job['featured'],'raw'=>$job];
    }
    return ['items'=>$items,'empty_message'=>jyb_t('No open jobs match these filters.')];
}

function jyb_register_shortcode_integrations(): bool
{
    if (isset($GLOBALS['_widget_shortcode_handlers']['job_board_list'])) return false;
    $provider = register_shortcode_source_provider('job_board_jobs', [
        'owner'=>'job-board','label'=>jyb_t('Job Board jobs'),'fetch'=>'jyb_provider_fetch','validate'=>'jyb_provider_validate',
        'defaults'=>['type'=>'article','category'=>'','author'=>null,'offset'=>0,'order_by'=>'created_at','order_dir'=>'DESC','include_children'=>'1','date_from'=>null,'date_to'=>null,'company'=>0,'job_category'=>0,'employment'=>'','workplace'=>'','featured'=>'0','open'=>'1','limit'=>6,'max_items'=>6,'pagination'=>'0'],
        'client_fields'=>['company','job_category','employment','workplace','featured','open','max_items','pagination'],
        'public_override_fields'=>['company','job_category','employment','workplace','featured','open','max_items','pagination'],
    ]);
    if (!$provider) return false;
    register_widget_shortcode_handler('job_board_list', static function (PDO $pdo, array $attrs, array $context = []): string {
        $GLOBALS['jyb_rendering'] = true;
        $validation = jyb_provider_validate(array_merge(['limit'=>6], $attrs), ['trust'=>'public_runtime'], $pdo);
        if ($validation['errors'] !== []) return '';
        $items = jyb_provider_fetch($pdo, $validation['config'], $context)['items'];
        return jyb_frontend_asset_tag() . jyb_render_template('widget-list', ['items'=>$items,'attrs'=>$validation['config']]);
    }, ['limit'=>6,'featured'=>'0','open'=>'1']);
    return isset($GLOBALS['_widget_shortcode_handlers']['job_board_list']);
}

function jyb_preset_editor_fields(array $config, array $preset, PDO $pdo, array $context): void
{
    $esc = static fn(mixed $v): string => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $active = ($config['source'] ?? '') === 'job_board_jobs';
    echo '<fieldset class="adam-card jyb-preset-fields" data-job-board-preset' . ($active ? '' : ' hidden disabled') . '><legend>' . $esc(jyb_t('Job Board filters')) . '</legend>';
    $locale = jyb_default_content_locale();
    $options = jyb_schema_ready($pdo) ? jyb_public_filter_options($pdo, $locale) : ['companies'=>[],'categories'=>[]];
    echo '<label>' . $esc(jyb_t('Company')) . '<select class="inpud" name="preset_config[company]"><option value="0">' . $esc(jyb_t('All companies')) . '</option>';
    foreach ($options['companies'] as $company) echo '<option value="' . (int)$company['id'] . '"' . ((int)($config['company'] ?? 0) === (int)$company['id'] ? ' selected' : '') . '>' . $esc($company['name']) . '</option>';
    echo '</select></label><label>' . $esc(jyb_t('Category')) . '<select class="inpud" name="preset_config[job_category]"><option value="0">' . $esc(jyb_t('All categories')) . '</option>';
    foreach ($options['categories'] as $category) echo '<option value="' . (int)$category['id'] . '"' . ((int)($config['job_category'] ?? 0) === (int)$category['id'] ? ' selected' : '') . '>' . $esc($category['name']) . '</option>';
    echo '</select></label>';
    foreach ([['employment','Employment type',jyb_employment_types()],['workplace','Workplace type',jyb_workplace_types()]] as [$key,$label,$values]) {
        echo '<label>' . $esc(jyb_t($label)) . '<select class="inpud" name="preset_config[' . $key . ']"><option value="">' . $esc(jyb_t($key === 'employment' ? 'Any employment type' : 'Any workplace')) . '</option>';
        foreach ($values as $value) echo '<option value="' . $esc($value) . '"' . ((string)($config[$key] ?? '') === $value ? ' selected' : '') . '>' . $esc(jyb_t(ucwords(str_replace('-', ' ', $value)))) . '</option>';
        echo '</select></label>';
    }
    foreach ([['featured','Featured only'],['open','Open only']] as [$key,$label]) echo '<label>' . $esc(jyb_t($label)) . '<select class="inpud" name="preset_config[' . $key . ']"><option value="0">' . $esc(jyb_t('No')) . '</option><option value="1"' . ((string)($config[$key] ?? '') === '1' ? ' selected' : '') . '>' . $esc(jyb_t('Yes')) . '</option></select></label>';
    echo '<script src="/static/plugins/job-board/preset-editor.js?v=' . rawurlencode(JYB_VERSION) . '" defer></script></fieldset>';
}
