<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\AbstractStorageEntity;

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

$buttonDisabled = ($storage->getId() < 0 || $storage->isValid() == false);
?>
<table class="form-table">    
    <tr>
        <th scope="row">
            <label for=""><?php DUP_PRO_U::esc_html_e("Validation"); ?></label>
        </th>
        <td>
            <button 
                id="button_file_test" 
                class="button button-large button_file_test" 
                type="button" 
                onclick="DupPro.Storage.Test(); return false;"
                <?php disabled($buttonDisabled); ?>
            >
                <i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test Storage'); ?>
            </button>
            <p>
                <i><?php DUP_PRO_U::esc_html_e("Test creating and deleting a small file on storage."); ?></i>
            </p>
        </td>
    </tr>
</table>
<?php

$alertStorageStatus          = new DUP_PRO_UI_Dialog();
$alertStorageStatus->title   = __('Storage Status', 'duplicator-pro');
$alertStorageStatus->height  = 185;
$alertStorageStatus->message = 'testings'; // javascript inserted message
$alertStorageStatus->initAlert();

$alertStorageStatusLong               = new DUP_PRO_UI_Dialog();
$alertStorageStatusLong->title        = __('Storage Status', 'duplicator-pro');
$alertStorageStatusLong->width        = 800;
$alertStorageStatusLong->height       = 520;
$alertStorageStatusLong->showTextArea = true;
$alertStorageStatusLong->textAreaRows = 15;
$alertStorageStatusLong->textAreaCols = 100;
$alertStorageStatusLong->message      = ''; // javascript inserted message
$alertStorageStatusLong->initAlert();

?>

<script>
    jQuery(document).ready(function ($) {

        DupPro.Storage.Test = function ()
        {
            var $test_button = $('#button_file_test');
            $test_button.html('<i class="fas fa-circle-notch fa-spin"></i> <?php DUP_PRO_U::esc_html_e('Attempting to test storage'); ?>');

            Duplicator.Util.ajaxWrapper(
                {
                    action: 'duplicator_pro_storage_test',
                    storage_id: <?php echo $storage->getId(); ?>,
                    nonce: '<?php echo wp_create_nonce('duplicator_pro_storage_test'); ?>'
                },
                function (result, data, funcData, textStatus, jqXHR) {
                    console.log('Func data',funcData);
                    if (funcData.success) {
                        if (funcData.status_msgs.length==0) {
                            <?php $alertStorageStatus->showAlert(); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>" + 
                                funcData.message+"</b></span>";
                            <?php $alertStorageStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertStorageStatusLong->showAlert(); ?>
                            <?php $alertStorageStatusLong->updateTextareaMessage("funcData.status_msgs"); ?>
                            let alertMsg = "<span style='color:green'><b><input type='checkbox' class='checkbox' checked disabled='disabled'>" + 
                                funcData.message+"</b></span>";
                            <?php $alertStorageStatusLong->updateMessage("alertMsg"); ?>
                        }
                    } else {
                        if (funcData.status_msgs.length==0) {
                            <?php $alertStorageStatus->showAlert(); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+funcData.message;
                            <?php $alertStorageStatus->updateMessage("alertMsg"); ?>
                        } else {
                            <?php $alertStorageStatusLong->showAlert(); ?>
                            <?php $alertStorageStatusLong->updateTextareaMessage("funcData.status_msgs"); ?>
                            let alertMsg = "<i class='fas fa-exclamation-triangle'></i> "+funcData.message;
                            <?php $alertStorageStatusLong->updateMessage("alertMsg"); ?>
                        }
                    }
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test Storage'); ?>');
                    return '';
                },
                function (result, data, funcData, textStatus, jqXHR) {
                    $test_button.html('<i class="fas fa-cloud-upload-alt fa-sm"></i> <?php DUP_PRO_U::esc_html_e('Test Storage'); ?>');
                    <?php $alertStorageStatus->showAlert(); ?>
                    let alertMsg = "<i class='fas fa-exclamation-triangle'></i> <?php DUP_PRO_U::esc_html_e('AJAX error while testing storage'); ?>";
                    <?php $alertStorageStatus->updateMessage("alertMsg"); ?>
                    console.log(parsedData);
                    return '';
                }
            );
        }
    });
</script>