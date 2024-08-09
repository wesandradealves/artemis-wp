<?php

/**
 * @package Duplicator
 */

use Duplicator\Core\CapMng;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var ?DUP_PRO_Package $package
 */

$package            = $tplData['package'];
$storage_problem    = $package->transferWasInterrupted();
$remote_style       = ($storage_problem) ? 'remote-data-fail' : '';
$txt_NoRemoteStores = __("No remote storage configured for package! ", 'duplicator-pro');

if (!CapMng::can(CapMng::CAP_STORAGE, false)) {
    ?>
    <td></td>
    <?php
    return;
}

if ($storage_problem) { ?>
    <td class="dup-cell-btns dup-cell-store-btn"
        aria-label="<?php DUP_PRO_U::esc_attr_e("Remote Storages") ?>"
        onclick="DupPro.Pack.ShowRemote(<?php echo "$package->ID, '$package->NameHash'"; ?>);"
        title="<?php DUP_PRO_U::esc_attr_e("Error during storage transfer.") ?>">
        <span class="button button-link">
            <i class="fas fa-server <?php echo ($remote_style); ?>"></i>
        </span>
    </td>
<?php } else { ?>
    <td class="dup-cell-btns dup-cell-store-btn"
        onclick="DupPro.Pack.ShowRemote(<?php echo "$package->ID, '$package->NameHash'"; ?>);"
        aria-label="<?php DUP_PRO_U::esc_attr_e("Remote Storages") ?>">
        <span class="button button-link">
            <i class="fas fa-server <?php echo ($remote_style); ?>"></i>
        </span>
    </td>
<?php } ?>
</td>