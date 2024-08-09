<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Views\AdminNotices;

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 * @var bool $blur
 */
$blur = $tplData['blur'];
/** @var int */
$storage_id = $tplData["storage_id"];
/** @var Duplicator\Models\Storages\AbstractStorageEntity */
$storage = $tplData["storage"];
/** @var ?string */
$error_message = $tplData["error_message"];
/** @var ?string */
$success_message = $tplData["success_message"];

$relativeEditUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    array('inner_page' => 'edit')
);

$fullEditUrl = ControllersManager::getMenuLink(
    ControllersManager::STORAGE_SUBMENU_SLUG,
    SettingsPageController::L2_SLUG_STORAGE,
    null,
    array('inner_page' => 'edit'),
    false
);

?>
<form 
    id="dup-storage-form" 
    class="<?php echo ($blur ? 'dup-mock-blur' : ''); ?>"
    action="<?php echo $relativeEditUrl; ?>" 
    method="post" 
    data-parsley-ui-enabled="true" 
    target="_self"
>
    <?php $tplData['actions']['save']->getActionNonceFileds(); ?>
    <input type="hidden" name="storage_id" id="storage_id" value="<?php echo $storage->getId(); ?>">

    <?php
    $tplMng->render('admin_pages/storages/parts/edit_toolbar');

    if (!is_null($error_message)) {
        AdminNotices::displayGeneralAdminNotice($error_message, AdminNotices::GEN_ERROR_NOTICE, true);
    } elseif (!is_null($success_message)) {
        AdminNotices::displayGeneralAdminNotice($success_message, AdminNotices::GEN_SUCCESS_NOTICE, true);
    }
    ?>
    <table class="form-table top-entry">
        <tr valign="top">
            <th scope="row">
                <label><?php esc_html_e("Name", 'duplicator-pro'); ?></label>
            </th>
            <td>
                <?php if ($storage->isDefault()) {
                    esc_html_e('Default', 'duplicator-pro');
                    $tCont = __('The "Default" storage type is a built in type that cannot be removed.', 'duplicator-pro') . ' ' .
                    __(' This storage type is used by default should no other storage types be available.', 'duplicator-pro') . ' ' .
                    __('This storage type is always stored to the local server.', 'duplicator-pro');
                    ?>
                    <i 
                        class="fas fa-question-circle fa-sm"
                        data-tooltip-title="<?php esc_attr_e("Default Storage Type", 'duplicator-pro'); ?>"
                        data-tooltip="<?php echo esc_attr($tCont); ?>"
                    >
                    </i>
                <?php } else { ?>
                    <input 
                        data-parsley-errors-container="#name_error_container" 
                        type="text" 
                        id="name" 
                        name="name" 
                        value="<?php echo esc_attr($storage->getName()); ?>" autocomplete="off" 
                    >
                <?php } ?>
                <div id="name_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e("Notes", 'duplicator-pro'); ?></label></th>
            <td>
                <textarea id="notes" name="notes" style="width:100%; max-width: 500px"><?php echo esc_html($storage->getNotes()); ?></textarea>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php esc_html_e("Type", 'duplicator-pro'); ?></label></th>
            <td>
                <?php $tplMng->render('admin_pages/storages/parts/storage_type_select'); ?>
            </td>
        </tr>
    </table>
    <hr size="1" />
    <?php
    if ($storage->getId() > 0) {
        $storage->renderConfigFields();
    } else {
        $types = AbstractStorageEntity::getResisteredTypes();
        foreach ($types as $type) {
            AbstractStorageEntity::renderSTypeConfigFields($type);
        }
    }

    $tplMng->render('admin_pages/storages/parts/test_button');
    ?>
    <br style="clear:both" />
    <button 
        id="button_save_provider" 
        class="button button-primary" 
        type="submit"
        <?php disabled(($storage->getId() > 0)); ?>
    >
        <?php esc_html_e('Save Provider', 'duplicator-pro'); ?>
    </button>
</form>
<script>
    jQuery(document).ready(function ($) {
        let storageEditBaseUrl = <?php echo json_encode($fullEditUrl); ?>;

        // Quick fix for submint/enter error
        $(window).on('keyup keydown', function (e) {
            if (!$(e.target).is('textarea'))
            {
                var keycode = (typeof e.keyCode != 'undefined' && e.keyCode > -1 ? e.keyCode : e.which);
                if ((keycode === 13)) {
                    e.preventDefault();
                    return false;
                }
            }
        });

        // Removes the values of hidden input fields marked with class dup-empty-field-on-submit
        DupPro.Storage.EmptyValues = function () {
            $(':hidden .dup-empty-field-on-submit').val('');
        }

        // Removes tags marked with class dup-remove-on-submit-if-hidden, if they are hidden
        DupPro.Storage.RemoveMarkedHiddenTags = function () {
            $('.dup-remove-on-submit-if-hidden:hidden').each(function() {
                $(this).remove();
            });
        }

        DupPro.Storage.PrepareForSubmit = function () {
            DupPro.Storage.EmptyValues();
            if ($('#dup-storage-form').parsley().isValid()) {
                // The form is about to be submitted.                
                DupPro.Storage.RemoveMarkedHiddenTags();
            }
        }

        $('#dup-storage-form').submit(DupPro.Storage.PrepareForSubmit);

        DupPro.Storage.AuthMessages = function () {
            let reloadUrl = new URL(window.location.href);
            let authMessage = reloadUrl.searchParams.get('dup-auth-message');
            if (authMessage) {
                DupPro.addAdminMessage(authMessage, 'notice');
            }
            let revokeMessage = reloadUrl.searchParams.get('dup-revoke-message');
            if (revokeMessage) {
                DupPro.addAdminMessage(revokeMessage, 'notice');
            }
        }
        
        DupPro.Storage.RevokeAuth = function (storageId)
        {
            Duplicator.Util.ajaxWrapper(
                {
                    action: 'duplicator_pro_revoke_storage',
                    storage_id: storageId,
                    nonce: '<?php echo wp_create_nonce('duplicator_pro_revoke_storage'); ?>'
                },
                function (result, data, funcData, textStatus, jqXHR) {
                    if (funcData.success) {
                        let reloadUrl = new URL(storageEditBaseUrl);
                        reloadUrl.searchParams.set('storage_id', storageId);
                        reloadUrl.searchParams.set('dup-revoke-message', funcData.message);
                        window.location.href = reloadUrl.href;
                        //location.reload();
                    } else {
                        DupPro.addAdminMessage(funcData.message, 'error');
                    }
                    return '';
                }
            );
        }

        DupPro.Storage.Authorize = function (storageId, storageType, extraData)
        {
            extraData.action       = 'duplicator_pro_auth_storage';
            extraData.storage_id   = storageId;
            extraData.storage_type = storageType;
            extraData.nonce = '<?php echo wp_create_nonce('duplicator_pro_auth_storage'); ?>';

            Duplicator.Util.ajaxWrapper(
                extraData,
                function (result, data, funcData, textStatus, jqXHR) {
                    if (funcData.success) {
                        let reloadUrl = new URL(storageEditBaseUrl);
                        reloadUrl.searchParams.set('storage_id', funcData.storage_id);
                        reloadUrl.searchParams.set('dup-auth-message', funcData.message);
                        window.location.href = reloadUrl.href;
                        //location.reload();
                    } else {
                        DupPro.addAdminMessage(funcData.message, 'error');
                    }
                    return '';
                }
            );

            return false;
        }

        // Toggles Save Provider button for existing Storages only
        DupPro.UI.formOnChangeValues($('#dup-storage-form'), function() {
            $('#button_save_provider').prop('disabled', false);
            $('#button_file_test').prop('disabled', true);
        });

        //Init
        DupPro.Storage.AuthMessages();
        jQuery('#name').focus().select();
    });
    
</script>
