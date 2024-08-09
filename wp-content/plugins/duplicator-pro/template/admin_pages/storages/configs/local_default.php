<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\LocalStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var LocalStorage $storage
 */
$storage = $tplData["storage"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var bool  */
$purgePackages = $tplData["purgePackages"];
/** @var string */
$storageFolder = $tplData["storageFolder"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr valign="top">
    <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Location"); ?></label></th>
    <td><?php echo esc_html($storageFolder); ?></td>
</tr>  
<tr>
<th scope="row"><label for=""><?php DUP_PRO_U::esc_html_e("Max Packages"); ?></label></th>
    <td>
        <label for="max_default_store_files">
            <input 
                data-parsley-errors-container="#max_default_store_files_error_container" 
                id="max_default_store_files" 
                name="max_default_store_files" 
                type="text" 
                data-parsley-type="number" 
                data-parsley-min="0" 
                data-parsley-required="true" 
                value="<?php echo intval($maxPackages); ?>" 
                maxlength="4"
            >
            &nbsp;<?php DUP_PRO_U::esc_html_e("Number of packages to keep in folder. "); ?><br/>
            <i><?php DUP_PRO_U::esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit."); ?></i>
        </label>
        <div id="max_default_store_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""></label></th>
    <td>
        <label for="purge_default_package_record">
            <input 
                name="purge_default_package_record"
                <?php checked($purgePackages); ?> 
                class="checkbox" 
                value="1" 
                type="checkbox" 
                id="purge_default_package_record" 
            >
            <i>
            <?php DUP_PRO_U::esc_html_e("Delete associated package record when Max Packages limit is exceeded."); ?></i>
        </label>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>