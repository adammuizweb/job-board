<?php
declare(strict_types=1);
?>
<div class="jyb-widget-list"><?php if($items===[]):?><p><?=jyb_h(jyb_t('No open jobs are available.'))?></p><?php else:?><ul><?php foreach($items as $item):?><li><article><p><?=jyb_h($item['company'])?></p><h3><a href="<?=jyb_h($item['url'])?>"><?=jyb_h($item['title'])?></a></h3><span><?=jyb_h(jyb_t(ucwords(str_replace('-',' ',(string)$item['employment_type']))))?> &middot; <?=jyb_h(jyb_t(ucwords(str_replace('-',' ',(string)$item['workplace_type']))))?></span><?php if($item['desc']):?><p><?=jyb_h($item['desc'])?></p><?php endif;?></article></li><?php endforeach;?></ul><?php endif;?></div>
