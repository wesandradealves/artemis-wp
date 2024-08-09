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

<?php esc_html_e("Where to store on the server hosting this site.", 'duplicator-pro'); ?><br>
<?php
    printf(
        esc_html__('The folder can be either a child of the home directory (<b>%1$s</b>) or be outside it as well..', 'duplicator-pro'),
        duplicator_pro_get_home_path()
    ); ?><br>
<?php esc_html_e("On Linux servers start with '/' (e.g. /mypath). On Windows use drive letters (e.g. E:/mypath).", 'duplicator-pro'); ?><br>
<?php esc_html_e("If you are unsure of the path, contact your hosting provider.", 'duplicator-pro'); ?><br>
<br>
<b><?php esc_html_e('Note: This will not store to your local computer unless that is where this web-site is hosted.', 'duplicator-pro'); ?></b><br>

