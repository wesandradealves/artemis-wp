<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\FTPStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var FTPStorage $storage
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
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var int */
$timeout = $tplData["timeout"];
/** @var bool */
$useCurl = $tplData["useCurl"];
/** @var bool */
$isPassive = $tplData["isPassive"];
/** @var bool */
$useSSL = $tplData["useSSL"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <td class="dpro-sub-title" colspan="2"><b><?php esc_html_e("Credentials", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="ftp_server"><?php esc_html_e("Server", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="ftp_server" class="dup-empty-field-on-submit" name="ftp_server" data-parsley-errors-container="#ftp_server_error_container" 
            type="text" autocomplete="off" value="<?php echo esc_attr($server); ?>">
        <label for="ftp_server">
            <?php esc_html_e("Port", 'duplicator-pro'); ?>
        </label>
        <input 
            name="ftp_port" 
            id="ftp_port" 
            data-parsley-errors-container="#ftp_server_error_container" 
            type="text" 
            style="width:75px"  
            value="<?php echo $port; ?>"
        >
        <div id="ftp_server_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_username"><?php esc_html_e("Username", 'duplicator-pro'); ?></label></th>
    <td>
        <input id="ftp_username" class="dup-empty-field-on-submit" 
            name="ftp_username" type="text" autocomplete="off" value="<?php echo esc_attr($username); ?>" />
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_password"><?php esc_html_e("Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="ftp_password"
            name="ftp_password" 
            type="password" 
            class="dup-empty-field-on-submit"
            placeholder="<?php echo str_repeat("*", strlen($password)); ?>"
            autocomplete="off" 
            value="" 
        >
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_password2"><?php esc_html_e("Retype Password", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="ftp_password2" 
            class="dup-empty-field-on-submit" 
            name="ftp_password2" 
            type="password" 
            placeholder="<?php echo str_repeat("*", strlen($password)); ?>"
            autocomplete="off" 
            value="" 
            data-parsley-errors-container="#ftp_password2_error_container"  
            data-parsley-trigger="change" data-parsley-equalto="#ftp_password" 
            data-parsley-equalto-message="<?php esc_html_e("Passwords do not match", 'duplicator-pro'); ?>"
        ><br/>
        <div id="ftp_password2_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <td class="dpro-sub-title" colspan="2"><b><?php esc_html_e("Settings", 'duplicator-pro'); ?></b></td>
</tr>
<tr>
    <th scope="row"><label for="_ftp_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            id="_ftp_storage_folder" 
            name="_ftp_storage_folder" 
            type="text" 
            value="<?php echo esc_attr($storageFolder); ?>" 
        >
        <p>
            <i>
                <?php
                esc_html_e(
                    "Folder where packages will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                ); ?>
            </i>
        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_max_files"><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="ftp_max_files">
            <input 
                id="ftp_max_files"
                name="ftp_max_files" 
                data-parsley-errors-container="#ftp_max_files_error_container" 
                type="text" 
                value="<?php echo $maxPackages; ?>" 
            >
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?> <br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit. ", 'duplicator-pro'); ?></i>
        </label>
        <div id="ftp_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_timeout_in_secs"><?php esc_html_e("Timeout", 'duplicator-pro'); ?></label></th>
    <td>

        <label for="ftp_timeout_in_secs">
                <input 
                    id="ftp_timeout" 
                    name="ftp_timeout_in_secs" 
                    data-parsley-errors-container="#ftp_timeout_error_container" 
                    type="text" 
                    value="<?php echo $timeout; ?>"
                > 
                <label for="ftp_timeout_in_secs">
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
        <div id="ftp_timeout_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="ftp_ssl"><?php esc_html_e("Explicit SSL", 'duplicator-pro'); ?></label></th>
    <td>
        <input name="_ftp_ssl" <?php checked($useSSL); ?> class="checkbox" value="1" type="checkbox" id="_ftp_ssl" >
        <label for="_ftp_ssl"><?php esc_html_e("Enable", 'duplicator-pro'); ?></label>
    </td>
</tr>
<tr>
    <th scope="row"><label for="_ftp_passive_mode"><?php esc_html_e("Passive Mode", 'duplicator-pro'); ?></label></th>
    <td>
        <input 
            <?php checked($isPassive); ?> 
            class="checkbox" 
            value="1" 
            type="checkbox" 
            name="_ftp_passive_mode" 
            id="_ftp_passive_mode"
        >
        <label for="_ftp_passive_mode"><?php esc_html_e("Enable", 'duplicator-pro'); ?></label>
    </td>
</tr>
<tr>
    <th scope="row"><label for="_ftp_use_curl"><?php esc_html_e("cURL", 'duplicator-pro'); ?></label></th>
    <td>
        <input <?php checked($useCurl); ?> class="checkbox" value="1" type="checkbox" name="_ftp_use_curl" id="_ftp_use_curl">
        <label for="_ftp_use_curl"><?php esc_html_e("Enable", 'duplicator-pro'); ?></label>
        <p><i><?php esc_html_e("PHP cURL. Only check if connection test recommends it.", 'duplicator-pro'); ?></i></p>
    </td>
</tr>
<tr>
    <th scope="row"><label>&nbsp;</label></th>
    <td>
        <p>
            <?php
            echo wp_kses(
                __(
                    "<b>Note:</b> This setting is for FTP and FTPS (FTP/SSL) only. 
                    To use SFTP (SSH File Transfer Protocol) change the type dropdown above.",
                    'duplicator-pro'
                ),
                array(
                    'b' => array(),
                )
            );
            ?>
        </p>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
