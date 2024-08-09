<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

 use Duplicator\Models\Storages\OneDriveStorage;

 defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var OneDriveStorage $storage
 */
$storage = $tplData["storage"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var bool */
$allFolderPers = $tplData["allFolderPers"];
/** @var false|object */
$accountInfo = $tplData["accountInfo"];
/** @var false|object */
$stateToken = $tplData["stateToken"];
/** @var string */
$externalRevokeUrl = $tplData["externalRevokeUrl"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <th scope="row"><label><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="onedrive-authorize">
        <?php if (!$storage->isAuthorized()) : ?>
            <div class='onedrive-msgraph-authorization-state' id="onedrive-msgraph-state-unauthorized">
                <div id="dup-all-onedrive-allperms-wrapper" >
                    <?php esc_html_e('All folders read write permission:', 'duplicator-pro'); ?>
                    <label class="switch">
                    <input 
                        id="onedrive_msgraph_all_folders_read_write_perm" 
                        name="onedrive_msgraph_all_folders_read_write_perm" 
                        type="checkbox" 
                        value="1" 
                        <?php checked($allFolderPers); ?>
                    >
                        <span class="slider round"></span>
                    </label>
                    <div class="auth-code-popup-note" style="margin-top:1px; margin-left: 0;">
                        <?php
                        echo esc_html__('There is only Apps folder permission scope by default.', 'duplicator-pro') . ' ' .
                        esc_html__('If your OneDrive Business is not working, Please switch on this option.', 'duplicator-pro'); ?>
                    </div>
                </div>

                <!-- CONNECT -->
                <button 
                    id="dpro-onedrive-msgraph-connect-btn" 
                    type="button" 
                    class="button button-large" 
                    onclick="DupPro.Storage.OneDrive.GetAuthUrl(); return false;"
                >
                    <i class="fa fa-plug"></i> <?php esc_html_e('Connect to OneDrive', 'duplicator-pro'); ?>
                </button>

                <div class='onedrive-msgraph-auth-container' style="display: none;">
                    <!-- STEP 2 -->
                    <b><?php esc_html_e("Step 1:", 'duplicator-pro'); ?></b>&nbsp;
                    <?php esc_html_e(' Duplicator needs to authorize at OneDrive.', 'duplicator-pro'); ?>
                    <div class="auth-code-popup-note" style="margin-top:1px">
                    <?php
                    echo __('Note: Clicking the button below will open a new tab/window.', 'duplicator-pro') . ' ' .
                    __('Please be sure your browser does not block popups.', 'duplicator-pro') . ' ' .
                    __('If a new tab/window does not open check your browsers address bar to allow popups from this URL.', 'duplicator-pro'); ?>
                    </div>
                    <button 
                        id="dpro-onedrive-msgraph-auth-btn" 
                        type="button" 
                        class="button button-large" 
                        data-auth-url="<?php echo esc_attr($storage->getAuthorizationUrl()); ?>"
                    >
                        <i class="fa fa-user"></i> <?php esc_html_e('Authorize Onedrive', 'duplicator-pro'); ?>
                    </button>
                    <br/><br/>

                    <div id="onedrive-msgraph-auth-container">
                        <b><?php esc_html_e('Step 2:', 'duplicator-pro'); ?></b> 
                        <?php esc_html_e("Paste code from OneDrive authorization page.", 'duplicator-pro'); ?> <br/>
                        <input style="width:400px" id="onedrive-msgraph-auth-code" name="onedrive-msgraph-auth-code" />
                    </div>
                    <br><br>
                    <!-- STEP 3 -->
                    <b><?php esc_html_e("Step 3:", 'duplicator-pro'); ?></b>&nbsp;
                    <?php esc_html_e('Finalize OneDrive validation by clicking the "Finalize Setup" button.', 'duplicator-pro'); ?>
                    <br/>
                    <button 
                        type="button" 
                        id="onedrive-msgraph-finalize-setup"
                        class="button"
                    >
                        <i class="fa fa-check-square"></i> <?php esc_html_e('Finalize Setup', 'duplicator-pro'); ?>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="onedrive-msgraph-authorization-state" id="onedrive-msgraph-state-authorized">
        <?php if ($storage->isAuthorized()) : ?>
                <h3>
                    <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/onedrive-24.png" style='vertical-align: bottom' />
                    <?php esc_html_e('OneDrive Account', 'duplicator-pro'); ?><br/>
                    <i class="dpro-edit-info">
                        <?php esc_html_e('Duplicator has been authorized to access this user\'s OneDrive account', 'duplicator-pro'); ?>
                    </i>
                </h3>
                
                <?php
                if ($accountInfo !== false) {
                    ?>
                    <div id="onedrive-account-info">
                        <label><?php esc_html_e('Name', 'duplicator-pro'); ?>:</label>
                        <?php echo esc_html($accountInfo->displayName); ?> <br/>
                    </div>
                </div>
                    <?php
                } elseif (isset($stateToken->data->error)) {
                    ?>
                    <div class="error-txt">
                        <?php
                        printf(esc_html__('Error: %s', 'duplicator-pro'), $stateToken->data->error_description); // @phpstan-ignore-line
                        echo '<br/><strong>';
                        esc_html_e('Please click on the "Cancel Authorization" button and reauthorize the OneDrive storage', 'duplicator-pro');
                        echo '</strong>';
                        ?>
                    </div>
                    <?php
                }
                ?>
                <br/>
                <button type="button" class="button" onclick='DupPro.Storage.OneDrive.CancelAuthorization();'>
                    <?php esc_html_e('Cancel Authorization', 'duplicator-pro'); ?>
                </button><br/>
                <i class="dpro-edit-info">
                    <?php
                    esc_html_e(
                        'Disassociates storage provider with the OneDrive account. Will require re-authorization.',
                        'duplicator-pro'
                    );
                    ?>
                </i>
        <?php endif; ?>
        </div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="_onedrive_msgraph_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <b>//OneDrive/Apps/Duplicator Pro/</b>
        <input 
            id="_onedrive_msgraph_storage_folder" 
            name="_onedrive_msgraph_storage_folder" 
            type="text" 
            value="<?php echo esc_attr($storageFolder); ?>"
            class="dpro-storeage-folder-path" data-parsley-pattern="^((?!\:).)*[^\.\:]$"
            data-parsley-errors-container="#onedrive_msgraph_storage_folder_error_container"
            data-parsley-pattern-message="<?php
                echo esc_attr__(
                    'The folder path shouldn\'t include the special character colon(":") or shouldn\'t end with a dot(".").',
                    'duplicator-pro'
                ); ?>" 
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
        <div id="onedrive_msgraph_storage_folder_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="onedrive_msgraph_max_files">
            <input 
                data-parsley-errors-container="#onedrive_msgraph_max_files_error_container" 
                id="onedrive_msgraph_max_files" 
                name="onedrive_msgraph_max_files" 
                type="text" 
                value="<?php echo absint($maxPackages); ?>" maxlength="4"
            >
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?> <br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="onedrive_msgraph_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot');

// Alerts for OneDrive
$alertConnStatus          = new DUP_PRO_UI_Dialog();
$alertConnStatus->title   = __('OneDrive Connection Status', 'duplicator-pro');
$alertConnStatus->message = ''; // javascript inserted message
$alertConnStatus->initAlert();
?>
<script>
    jQuery(document).ready(function ($) {
        let storageId = <?php echo $storage->getId(); ?>;

        DupPro.Storage.OneDrive.GetAuthUrl = function ()
        {
            $("#dpro-onedrive-msgraph-connect-btn").hide();
            $(".onedrive-msgraph-auth-container").show();
        };

        $('#dpro-onedrive-msgraph-auth-btn').click(function() {
            let authUrl = $(this).data('auth-url');
            // console.log(DupPro.Storage.OneDrive.AuthUrl);
            window.open(authUrl, '_blank');           
        });

        DupPro.Storage.OneDrive.CancelAuthorization = function ()
        {
            window.open(<?php echo json_encode($externalRevokeUrl); ?>, '_blank');
            DupPro.Storage.RevokeAuth(storageId);
        }

        DupPro.Storage.OneDrive.FinalizeSetup = function () {
            if ($('#onedrive-msgraph-auth-code').val().length > 5) {
                $("#dup-storage-form").submit();
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + 
                    "<?php esc_html_e('Please enter your OneDrive authorization code!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }
        }

        $('#onedrive_msgraph_all_folders_read_write_perm').change(function (event) {
            event.stopPropagation();
            let allPerms = $(this).is(':checked');
            Duplicator.Util.ajaxWrapper(
                {
                    action: 'duplicator_pro_onedrive_all_perms_update',
                    storage_id: storageId,
                    all_perms: allPerms,
                    nonce: '<?php echo wp_create_nonce('duplicator_pro_onedrive_all_perms_update'); ?>'
                },
                function (result, data, funcData, textStatus, jqXHR) {
                    if (funcData.success) {
                        $('#dpro-onedrive-msgraph-auth-btn').data('auth-url', funcData.auth_url);
                    } else {
                        DupPro.addAdminMessage(funcData.message, 'error');
                    }
                    return '';
                }
            );
        });

        $('#onedrive-msgraph-finalize-setup').click(function (event) {
            event.stopPropagation();

            if ($('#onedrive-msgraph-auth-code').val().length > 5) {
                DupPro.Storage.PrepareForSubmit();

                //$("#dup-storage-form").submit();

                DupPro.Storage.Authorize(
                    <?php echo $storage->getId(); ?>, 
                    <?php echo $storage->getSType(); ?>, 
                    {
                        'name': $('#name').val(),
                        'notes': $('#notes').val(),
                        'storage_folder': $('#_onedrive_msgraph_storage_folder').val(),
                        'max_packages': $('#onedrive_msgraph_max_files').val(),
                        'auth_code' : $('#onedrive-msgraph-auth-code').val()
                    }
                );
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + 
                    "<?php esc_html_e('Please enter your Onedrive authorization code!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }

            return false;
        });
    });
</script>
