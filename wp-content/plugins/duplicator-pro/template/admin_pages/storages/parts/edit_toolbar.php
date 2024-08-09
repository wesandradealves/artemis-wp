<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\DefaultLocalStorage;
use Duplicator\Models\Storages\StoragesUtil;

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
/** @var int */
$storage_id = $tplData["storage_id"];

$storage_tab_url = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE
);

$baseCopyUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    array(
        'inner_page' => 'edit',
        'action'     => $tplData['actions']['copy-storage']->getKey(),
        '_wpnonce'   => $tplData['actions']['copy-storage']->getNonce(),
        'storage_id' => $storage_id,
    )
);

if ($storage->getId() > 0) {
    $storages = AbstractStorageEntity::getAllBySType($storage->getSType());
} else {
    $storages = AbstractStorageEntity::getAll(0, 0, [StoragesUtil::class, 'sortByPriority']);
}

$storages = array_filter($storages, function (AbstractStorageEntity $s) use ($storage) {
    if ($s->getId() == $storage->getId()) {
        return false;
    }
    if ($s->getSType() == DefaultLocalStorage::getSType()) {
        return false;
    }
    return true;
});

$storage_count = count($storages);
?>
<table class="dpro-edit-toolbar">
    <tr>
        <td>
            <select 
                id="dup-copy-source-id-select" 
                name="duppro-source-storage-id"
                <?php disabled($storage_count, 0); ?>
            >
                <option value="-1" selected="selected" disabled="true">
                    <?php _e('Copy From', 'duplicator-pro'); ?>
                </option>
                <?php foreach ($storages as $copy_storage) { ?>
                    <option value="<?php echo $copy_storage->getId(); ?>">
                        <?php echo esc_html($copy_storage->getName()); ?> [<?php echo esc_html($copy_storage->getStypeName()); ?>]
                    </option>
                <?php } ?>
            </select>
            <input 
                type="button" 
                class="button action" 
                value="<?php esc_attr_e("Apply", 'duplicator-pro') ?>" 
                onclick="DupPro.Storage.Copy()"
                <?php disabled($storage_count, 0); ?>
            >
        </td>
        <td>
            <div class="btnnav">
                <a href="<?php echo $storage_tab_url; ?>" class="button"> 
                    <i class="fas fa-server fa-sm"></i> <?php esc_html_e('Providers', 'duplicator-pro'); ?>
                </a>
                <?php if ($storage_id != -1) :
                    $add_storage_url = admin_url('admin.php?page=duplicator-pro-storage&tab=storage&inner_page=edit');
                    ?>
                    <a href="<?php echo $add_storage_url;?>" class="button"><?php esc_html_e("Add New", 'duplicator-pro'); ?></a>
                <?php endif; ?>
            </div>
        </td>
    </tr>
</table>
<hr class="dpro-edit-toolbar-divider"/>
<script>
    jQuery(document).ready(function ($) {
        // COMMON STORAGE RELATED METHODS
        DupPro.Storage.Copy = function ()
        {
            document.location.href = <?php echo json_encode($baseCopyUrl); ?> + 
                '&duppro-source-storage-id=' + $("#dup-copy-source-id-select option:selected").val();
        };
    });    
</script>