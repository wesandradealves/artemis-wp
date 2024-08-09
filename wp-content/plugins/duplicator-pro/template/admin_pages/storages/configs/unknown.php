<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\UnknownStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var UnknownStorage $storage
 */
$storage = $tplData["storage"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr valign="top">
    <th scope="row">
        <label>
            <?php esc_html_e("Unknown Storage Type", 'duplicator-pro'); ?>
        </label>
    </th>
    <td>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot');

