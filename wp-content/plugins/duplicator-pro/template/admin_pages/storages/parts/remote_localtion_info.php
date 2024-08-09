<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\StoragePageController;
use Duplicator\Models\Storages\AbstractStorageEntity;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AbstractStorageEntity $storage
 */
$storage = $tplData["storage"];
/** @var bool */
$failed = $tplData["failed"];
/** @var bool */
$cancelled = $tplData["cancelled"];

$containerClasses = ['dup-dlg-store-endpoint'];
if ($failed) {
    $containerClasses[] = 'dup-dlg-store-endpoint-failed';
}
if ($cancelled) {
    $containerClasses[] = 'dup-dlg-store-endpoint-cancelled';
}
?>
<div class="<?php echo implode(' ', $containerClasses); ?>" >
    <h4 class="dup-dlg-store-names">
        <?php echo $storage->getStypeIcon(); ?>&nbsp;
        <?php echo $storage->getStypeName() ?>:&nbsp;
        <span>
            <?php echo esc_html($storage->getName()); ?> 
            <?php if ($failed) { ?>
                &nbsp;<i>(<?php esc_html_e('failed', 'duplicator-pro'); ?>)</i>
            <?php } ?>
            <?php if ($cancelled) { ?>
                &nbsp;<i>(<?php esc_html_e('cancelled', 'duplicator-pro'); ?>)</i>
            <?php } ?>
        </span>
    </h4>
    <div class="dup-dlg-store-links">
        <?php echo $storage->getHtmlLocationLink(); ?>
    </div>
    <div class="dup-dlg-store-test">
        <a href="<?php echo esc_url(StoragePageController::getEditUrl($storage)); ?>" target='_blank'>
            [ <?php esc_html_e('Test Storage', 'duplicator-pro'); ?> ]
        </a>
    </div>
</div>