<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\SFTPStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var SFTPStorage $storage
 */
$storage = $tplData["storage"];
/** @var string */
$server = $tplData["server"];
/** @var int */
$port = $tplData["port"];
/** @var string */
$username = $tplData["username"];
/** @var string */
$password = $tplData["password"];
/** @var string */
$privateKey = $tplData["privateKey"];
/** @var string */
$privateKeyPwd = $tplData["privateKeyPwd"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var int */
$timeout = $tplData["timeout"];
/** @var bool */
$chunking = $tplData["chunking"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td class="dpro-sub-title" colspan="2"><b><?php esc_html_e("Credentials", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="sftp_server"><?php esc_html_e("Server", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="sftp_server" class="dup-empty-field-on-submit" name="sftp_server" 
            data-parsley-errors-container="#sftp_server_error_container" type="text" a
            utocomplete="off" value="<?php echo esc_attr($server); ?>">
        <label for="sftp_server">
            <?php esc_html_e("Port", 'duplicator-pro'); ?>
        </label> 
        <input 
            name="sftp_port" 
            id="sftp_port" 
            data-parsley-errors-container="#sftp_server_error_container" 
            type="text" 
            style="width:75px"  
            value="<?php echo $port; ?>"
        >
        <div id="sftp_server_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_username"><?php esc_html_e("Username", 'duplicator-pro'); ?></label></th>
    <td><input id="sftp_username" class="dup-empty-field-on-submit" name="sftp_username" 
        type="text" autocomplete="off" value="<?php echo esc_attr($username); ?>" /></td>
</tr>
<tr>
    <th scope="row"><label for="sftp_password"><?php esc_html_e("Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="sftp_password" 
            class="dup-empty-field-on-submit"
            name="sftp_password" 
            type="password"
            placeholder="<?php echo str_repeat("*", strlen($password)); ?>"
            autocomplete="off" 
            value="" 
        >
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_password2"><?php esc_html_e("Retype Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="sftp_password2" 
            class="dup-empty-field-on-submit" 
            name="sftp_password2" 
            type="password"
            placeholder="<?php echo str_repeat("*", strlen($password)); ?>"
            autocomplete="off" 
            value="" 
            data-parsley-errors-container="#sftp_password2_error_container"  
            data-parsley-trigger="change" 
            data-parsley-equalto="#sftp_password" 
            data-parsley-equalto-message="<?php esc_attr_e("Passwords do not match", 'duplicator-pro'); ?>"
        ><br/>
        <div id="sftp_password2_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_private_key"><?php esc_html_e("Private Key (PuTTY)", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="sftp_private_key_file" 
            class="dup-empty-field-on-submit"
            name="sftp_private_key_file"
            onchange="DupPro.Storage.SFTP.ReadPrivateKey(this);" 
            type="file"  
            accept="ppk" 
            value="" 
            data-parsley-errors-container="#sftp_private_key_error_container" 
        ><br/>
        <input type="hidden" name="sftp_private_key" id="sftp_private_key" value="<?php echo esc_attr($privateKey); ?>" />
        <div id="sftp_private_key_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_private_key_password"><?php esc_html_e("Private Key Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="sftp_private_key_password" 
            class="dup-empty-field-on-submit" 
            name="sftp_private_key_password" 
            type="password" 
            placeholder="<?php echo str_repeat("*", strlen($privateKeyPwd)); ?>"
            autocomplete="off" 
            value="" 
            data-parsley-errors-container="#sftp_private_key_password_error_container" 
        >
        <br/>
        <div id="sftp_private_key_password_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_private_key_password2"><?php esc_html_e("Private Key Retype Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="sftp_private_key_password2" 
            class="dup-empty-field-on-submit" 
            name="sftp_private_key_password2" 
            type="password"
            placeholder="<?php echo str_repeat("*", strlen($privateKeyPwd)); ?>"
            autocomplete="off" 
            value="" 
            data-parsley-errors-container="#sftp_private_key_password2_error_container" 
            data-parsley-trigger="change" 
            data-parsley-equalto="#sftp_private_key_password" 
            data-parsley-equalto-message="<?php esc_html_e("Passwords do not match", 'duplicator-pro'); ?>"
        ><br/>
        <div id="sftp_private_key_password2_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <td class="dpro-sub-title" colspan="2"><b><?php esc_html_e("Settings", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="_sftp_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="_sftp_storage_folder" name="_sftp_storage_folder" type="text" value="<?php echo esc_attr($storageFolder); ?>">
        <p>
            <i>
                <?php
                echo __('Folder where packages will be stored.', 'duplicator-pro') . ' ' .
                    __('This should be <b>an absolute path, not a relative path</b> and be unique for each web-site using Duplicator.', 'duplicator-pro');
                ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_max_files"><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="sftp_max_files">
            <input id="sftp_max_files" name="sftp_max_files" data-parsley-errors-container="#sftp_max_files_error_container" 
                type="text" value="<?php echo $maxPackages; ?>">
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?> <br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="sftp_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="sftp_timeout_in_secs"><?php esc_html_e("Timeout", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="sftp_timeout_in_secs">
            <input 
                id="sftp_timeout" 
                name="sftp_timeout_in_secs" 
                data-parsley-errors-container="#sftp_timeout_error_container" 
                type="text" 
                value="<?php echo $timeout; ?>"
            >
            <label for="sftp_timeout_in_secs">
                <?php esc_html_e("seconds", 'duplicator-pro'); ?>
            </label>
            <br>
            <i>
                <?php
                esc_html_e(
                    "Do not modify this setting unless you know the expected result or have talked to support.",
                    'duplicator-pro'
                ); ?>
            </i>
        </label>
        <div id="sftp_timeout_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
<th scope="row"><label for="sftp_chunking_mode"><?php esc_html_e("Chunking Mode", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="sftp_chunking_mode" name="sftp_chunking_mode" type="checkbox" 
            class="checkbox" value="1" <?php checked($chunking); ?>>
        <label for="sftp_chunking_mode"><?php esc_html_e("Enabled", 'duplicator-pro'); ?></label>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
<script>
    jQuery(document).ready(function ($) {
        DupPro.Storage.SFTP.ReadPrivateKey = function (file_obj)
        {
            var files = file_obj.files;
            var private_key = files[0];
            var reader = new FileReader();
            reader.onload = function (e) {
                $("#sftp_private_key").val(e.target.result);
            }
            reader.readAsText(private_key);
        }
    });
</script>
