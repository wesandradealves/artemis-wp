<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AmazonS3Storage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AmazonS3Storage $storage
 */
$storage = $tplData["storage"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var string */
$accessKey = $tplData["accessKey"];
/** @var string */
$bucket = $tplData["bucket"];
/** @var string */
$region = $tplData["region"];
/** @var string */
$secretKey = $tplData["secretKey"];
/** @var string */
$storageClass = $tplData["storageClass"];
/** @var string */
$endpoint = $tplData["endpoint"];
/** @var string */
$aclFullControl = $tplData["aclFullControl"];
/** @var array<string,string> */
$regionOptions = $tplData["regionOptions"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td colspan="2" style="padding-left:0">
        <i>
            <?php
            printf(
                _x(
                    'Amazon S3 Setup Guide: %1$sStep-by-Step%2$s and %3$sUser Bucket Policy%4$s.',
                    '1,2 represents <a> tag, 3,4 represents </a> tag',
                    'duplicator-pro'
                ),
                '<a target="_blank" href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
                '</a>',
                '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-policy-setup" target="_blank">',
                '</a>'
            );
            ?>
        </i>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="dup-s3-auth-account">
        <h3>
            <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/aws.svg" class="dup-store-auth-icon" alt="" />
            <?php esc_html_e('Amazon Account', 'duplicator-pro'); ?><br/>
        </h3>
        <table class="dup-form-sub-area">
            <tr>
                <th scope="row"><label for="s3_access_key_amazon"><?php esc_html_e("Access Key", 'duplicator-pro'); ?>:</label></th>
                <td>
                    <input 
                        id="s3_access_key_amazon" 
                        name="s3_access_key" 
                        data-parsley-errors-container="#s3_access_key_amazon_error_container" 
                        type="text" 
                        autocomplete="off" 
                        value="<?php echo esc_attr($accessKey); ?>"
                    >
                    <div id="s3_access_key_amazon_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="s3_secret_key_amazon"><?php esc_html_e("Secret Key", 'duplicator-pro'); ?>:</label>
                </th>

                <td>
                    <input
                        id="s3_secret_key_amazon"
                        name="s3_secret_key"
                        type="password"
                        placeholder="<?php echo str_repeat("*", strlen($secretKey)); ?>"
                        data-parsley-errors-container="#s3_secret_key_amazon_error_container"
                        autocomplete="off"
                        value=""
                    >
                    <div id="s3_secret_key_amazon_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
        </table>
    </td>
</tr>            
<tr>
    <th scope="row"></th>
    <td>
        <table class="dup-form-sub-area dup-s3-auth-provider">
            <tr>
                <th><label for="s3_region_amazon"><?php esc_html_e("Region", 'duplicator-pro'); ?>:</label></th>
                <td>
                    <select id="s3_region_amazon" name="s3_region">
                        <?php
                        foreach ($regionOptions as $value => $label) {
                            ?>
                            <option
                                <?php selected($region, $value); ?>
                                value="<?php echo esc_attr($value); ?>"
                            >
                                <?php echo esc_html($label . " - '" . $value . "'"); ?>
                            </option>
                            <?php
                        }
                        ?>                                    
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="s3_storage_class_amazon"><?php esc_html_e("Storage Class", 'duplicator-pro'); ?>:</label></th>
                <td>
                    <select id="s3_storage_class_amazon" name="s3_storage_class">
                        <option <?php selected($storageClass == 'REDUCED_REDUNDANCY'); ?> value="REDUCED_REDUNDANCY">
                            <?php esc_html_e("Reduced Redundancy", 'duplicator-pro'); ?>
                        </option>
                        <option <?php selected($storageClass == 'STANDARD'); ?> value="STANDARD">
                            <?php esc_html_e("Standard", 'duplicator-pro'); ?>
                        </option>
                        <option <?php selected($storageClass == 'STANDARD_IA'); ?> value="STANDARD_IA">
                            <?php esc_html_e("Standard IA", 'duplicator-pro'); ?>
                        </option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="_s3_storage_folder_amazon"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>:</label></th>
                <td>
                    <input 
                        id="_s3_storage_folder_amazon" 
                        name="_s3_storage_folder" 
                        type="text" 
                        value="<?php echo esc_attr($storageFolder); ?>"
                    >
                    <p>
                        <i>
                            <?php esc_html_e(
                                "Folder where packages will be stored. This should be unique for each web-site using Duplicator.",
                                'duplicator-pro'
                            ); ?>
                        </i>
                    </p>
                </td>
            </tr>
        </table>

    </td>
</tr>
<tr>
    <th scope="row"><label for="s3_bucket_amazon"><?php esc_html_e("Bucket", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="s3_bucket_amazon" name="s3_bucket" type="text" value="<?php echo esc_attr($bucket); ?>">
        <p><i><?php esc_html_e("S3 Bucket where you want to save the backups.", 'duplicator-pro'); ?></i></p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="s3_max_files_amazon"><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="s3_max_files_amazon">
            <input 
                id="s3_max_files_amazon" 
                class="s3_max_files" 
                name="s3_max_files" 
                data-parsley-errors-container="#s3_max_files_amazon_error_container"
                type="text" 
                value="<?php echo absint($maxPackages); ?>"
            >
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?><br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="s3_max_files_amazon_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
