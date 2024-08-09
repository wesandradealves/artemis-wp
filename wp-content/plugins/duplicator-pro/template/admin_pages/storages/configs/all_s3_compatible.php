<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AmazonS3CompatibleStorage;
use Duplicator\Models\Storages\AmazonS3Storage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var AmazonS3CompatibleStorage $storage
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
/** @var array<int,array<string,string>> */
$documentationLinks = $tplData["documentationLinks"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td colspan="2" style="padding-left:0">
        <i><?php printf(
            _x(
                'S3 Setup Guide: %1$sStep-by-Step%2$s and %3$sUser Bucket Policy%4$s.',
                '1%$s and %3$s are opening and %2$s and %4$s are closing <a> tags',
                'duplicator-pro'
            ),
            '<a target="_blank" href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
            '</a>',
            '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-policy-setup" target="_blank">',
            '</a>'
        ); ?>
        </i>
        <i>
        <?php if (count($documentationLinks) > 0) {
                printf(
                    _x(
                        'Documentation for %s: ',
                        '%s is the provider name',
                        'duplicator-pro'
                    ),
                    $storage->getStypeName()
                );

                echo implode(', ', array_map(function ($link) {
                    return '<a target="_blank" href="' . $link['url'] . '">' . $link['label'] . '</a>';
                }, $documentationLinks));
        } ?>
        </i>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="dup-s3-auth-account">
        <h3>
            <?php
            if ($storage->getId() < 0) {
                echo $storage->getStypeIcon();
            }
            ?>
            <?php echo $storage->getStypeName() . ' ' . esc_html__('Account', 'duplicator-pro'); ?>
        </h3>
        <?php if ($storage->getSType() === AmazonS3CompatibleStorage::getSType()) {
            $tplMng->render('admin_pages/storages/parts/s3_compatible_msg');
        } ?>
        <table class="dup-form-sub-area margin-top-1">
            <tr>
                <th scope="row">
                    <label for="s3_access_key_<?php echo $storage->getSType(); ?>"><?php echo $storage->getFieldLabel('accessKey'); ?>:</label>
                </th>
                <td>
                    <input 
                        id="s3_access_key_<?php echo $storage->getSType(); ?>" 
                        name="s3_access_key" 
                        data-parsley-errors-container="#s3_access_key_<?php echo $storage->getSType(); ?>_error_container" 
                        type="text" 
                        autocomplete="off" 
                        value="<?php echo esc_attr($accessKey); ?>"
                    >
                    <div id="s3_access_key_<?php echo $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
                </td>
            </tr>
            <tr>
                <th scope="row">
                    <label for="s3_secret_key_<?php echo $storage->getSType(); ?>"><?php echo $storage->getFieldLabel('secretKey'); ?>:</label>
                </th>

                <td>
                    <input
                        id="s3_secret_key_<?php echo $storage->getSType(); ?>"
                        name="s3_secret_key"
                        type="password"
                        placeholder="<?php echo str_repeat("*", strlen($secretKey)); ?>"
                        data-parsley-errors-container="#s3_secret_key_<?php echo $storage->getSType(); ?>_error_container"
                        autocomplete="off"
                        value=""
                    >
                    <div id="s3_secret_key_<?php echo $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
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
                <th><label for="s3_endpoint_<?php echo $storage->getSType(); ?>"><?php echo $storage->getFieldLabel('endpoint'); ?>:</label></th>
                <td>
                    <input 
                        type="text" 
                        id="s3_endpoint_<?php echo $storage->getSType(); ?>" 
                        name="s3_endpoint" 
                        value="<?php echo esc_attr($endpoint); ?>"
                        <?php echo $storage->isAutofillEndpoint() ? 'readonly="true"' : ''; ?>
                        >
                    <?php if ($storage->isAutofillEndpoint()) : ?>
                    <p class="description">
                        <?php esc_html_e('The endpoint URL will be autofilled based on the region.', 'duplicator-pro'); ?>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <th><label for="s3_region_<?php echo $storage->getSType(); ?>"><?php echo $storage->getFieldLabel('region'); ?>:</label></th>
                <td>
                    <input 
                        type="text" 
                        id="s3_region_<?php echo $storage->getSType(); ?>" 
                        name="s3_region" 
                        <?php echo $storage->isAutofillRegion() ? 'readonly="true"' : ''; ?>
                        value="<?php echo esc_attr($region); ?>"
                    >
                    <?php if ($storage->isAutofillRegion()) : ?>
                    <p class="description">
                        <?php esc_html_e('The region will be autodetected from the endpoint URL.', 'duplicator-pro'); ?>
                    </p>
                    <?php endif; ?>
                </td>
            </tr>
            <tr class="invisible_out_of_screen">
                <th><label for="s3_storage_class_<?php echo $storage->getSType(); ?>"><?php esc_html_e("Storage Class", 'duplicator-pro'); ?>:</label></th>
                <td>
                    <select id="s3_storage_class_<?php echo $storage->getSType(); ?>" name="s3_storage_class">
                        <option <?php selected(true); ?> value="STANDARD"><?php esc_html_e("Standard", 'duplicator-pro'); ?></option>
                    </select>
                </td>
            </tr>                        
            <tr>
                <th scope="row"><label for="s3_bucket_<?php echo $storage->getSType(); ?>"><?php echo $storage->getFieldLabel('bucket'); ?></label></th>
                <td>
                    <input id="s3_bucket_<?php echo $storage->getSType(); ?>" name="s3_bucket" type="text" value="<?php echo esc_attr($bucket); ?>">
                    <p><i><?php esc_html_e("S3 Bucket where you want to save the backups.", 'duplicator-pro'); ?></i></p>
                </td>
            </tr>
        </table>
    </td>
</tr>
<tr>
    <th><label for="_s3_storage_folder_<?php echo $storage->getSType(); ?>"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>:</label></th>
    <td>
        <input 
            id="_s3_storage_folder_<?php echo $storage->getSType(); ?>" 
            name="_s3_storage_folder" 
            type="text" 
            value="<?php echo esc_attr($storageFolder); ?>"
        >
        <p>
            <i>
                <?php
                esc_html_e(
                    "Folder where packages will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                );
                ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="s3_max_files_<?php echo $storage->getSType(); ?>"><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="s3_max_files_<?php echo $storage->getSType(); ?>">
            <input 
                id="s3_max_files_<?php echo $storage->getSType(); ?>" 
                class="s3_max_files" 
                name="s3_max_files" 
                data-parsley-errors-container="#s3_max_files_<?php echo $storage->getSType(); ?>_error_container" 
                type="text" 
                value="<?php echo absint($maxPackages); ?>"
            >
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?><br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="s3_max_files_<?php echo $storage->getSType(); ?>_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr class="s3-acl-row" valign="top">
    <th scope="row"><label><?php echo $storage->getFieldLabel('aclFullControl'); ?></label></th>
    <td>
        <input 
            type="checkbox" 
            name="s3_ACL_full_control" 
            id="s3_ACL_full_control_<?php echo $storage->getSType(); ?>" 
            value="1" 
            <?php echo $storage->isACLSupported() ? '' : 'disabled="disabled"'; ?>
            <?php checked($aclFullControl, true); ?> 
        >
        <label for="s3_ACL_full_control_<?php echo $storage->getSType(); ?>"><?php esc_html_e("Enable full control ACL", 'duplicator-pro'); ?> </label><br />
        <?php if ($storage->isACLSupported()) : ?>
        <p class="description">
            <?php esc_html_e("Only uncheck if object-level ACLs are not supported.", 'duplicator-pro'); ?>
        </p>
        <?php else : ?>
        <p class="description">
            <?php esc_html_e("ACL is not supported.", 'duplicator-pro'); ?>
        </p>
        <?php endif; ?>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot');
