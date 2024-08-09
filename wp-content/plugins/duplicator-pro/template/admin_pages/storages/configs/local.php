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
/** @var int  */
$isFilderProtection = $tplData["isFilderProtection"];
/** @var string */
$storageFolder = $tplData["storageFolder"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr valign="top">
    <th scope="row">
        <?php $home_path = duplicator_pro_get_home_path(); ?>
        <label onclick="jQuery('#_local_storage_folder').val('<?php echo esc_js($home_path); ?>')">
        <?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>
        </label>
    </th>
    <td>
        <input 
            data-parsley-errors-container="#_local_storage_folder_error_container" 
            data-parsley-required="true"  
            type="text" 
            id="_local_storage_folder" 
            class="dup-empty-field-on-submit" 
            name="_local_storage_folder" 
            data-parsley-pattern=".*" 
            data-parsley-not-core-paths="true" 
            value="<?php echo esc_attr($storageFolder); ?>" 
        >&nbsp;
        <i class="fas fa-question-circle fa-sm" 
            data-tooltip-title="<?php esc_attr_e('Server storage folder', 'duplicator-pro'); ?>" 
            data-tooltip="<?php $tplMng->renderEscAttr('admin_pages/storages/configs/local_storage_folder_tooltip'); ?>">
        </i>
        <div id="_local_storage_folder_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="local_filter_protection"><?php esc_html_e("Filter Protection", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="_local_filter_protection" 
            name="_local_filter_protection" 
            type="checkbox" <?php checked($isFilderProtection); ?> 
            onchange="DupPro.Storage.LocalFilterToggle()"
        >&nbsp;
        <label for="_local_filter_protection">
        <?php esc_html_e("Filter the Storage Folder (recommended)", 'duplicator-pro'); ?>
        </label>
        <div style="padding-top:6px">
            <i>
                <?php
                esc_html_e(
                    "When checked this will exclude the 'Storage Folder' and all of its content and sub-folders from package builds.",
                    'duplicator-pro'
                ); ?>
            </i>
            <div id="_local_filter_protection_message" style="display:none; color:maroon">
                <i>
                    <?php
                    esc_html_e(
                        "Unchecking filter protection is not recommended. This setting helps to prevents packages from getting bundled in other packages.",
                        'duplicator-pro'
                    ); ?>
                </i>
            </div>
        </div>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="local_max_files">
            <input 
                data-parsley-errors-container="#local_max_files_error_container" 
                id="local_max_files" 
                name="local_max_files" 
                type="text" 
                value="<?php echo $maxPackages; ?>" 
                maxlength="4"
            >
            &nbsp;
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?><br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="local_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>

<script>
    jQuery(document).ready(function ($) {

        let validatorMsg = <?php
            echo json_encode(
                __(
                    'Storage Folder should not be root directory path, content directory path and upload directory path',
                    'duplicator-pro'
                )
            ); ?>;
        window.Parsley.addValidator('notCorePaths', {
            requirementType: 'string',
            validateString: function(value) {
                <?php
                $home_path             = duplicator_pro_get_home_path();
                $wp_upload_dir         = wp_upload_dir();
                $wp_upload_dir_basedir = str_replace('\\', '/', $wp_upload_dir['basedir']);
                ?>
                var corePaths = [
                            "<?php echo $home_path;?>",
                            "<?php echo untrailingslashit($home_path);?>",

                            "<?php echo $home_path . 'wp-content';?>",
                            "<?php echo $home_path . 'wp-content/';?>",

                            "<?php echo $home_path . 'wp-admin';?>",
                            "<?php echo $home_path . 'wp-admin/';?>",

                            "<?php echo $home_path . 'wp-includes';?>",
                            "<?php echo $home_path . 'wp-includes/';?>",

                            "<?php echo $wp_upload_dir_basedir;?>",
                            "<?php echo trailingslashit($wp_upload_dir_basedir);?>"
                        ];
                // console.log(value);

                for (var i = 0; i < corePaths.length; i++) {
                    if (value === corePaths[i]) {
                        return false;
                    }
                }                            
                return true;                            
            },
            messages: {
                en: validatorMsg
            }
        });

        DupPro.Storage.LocalFilterToggle = function ()
        {
            $("#_local_filter_protection").is(":checked")
                    ? $("#_local_filter_protection_message").hide(400)
                    : $("#_local_filter_protection_message").show(400);

        };
        //Init
        DupPro.Storage.LocalFilterToggle();
    });
</script>
