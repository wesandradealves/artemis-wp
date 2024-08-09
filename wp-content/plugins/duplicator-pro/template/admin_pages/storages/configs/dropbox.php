<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\DropboxStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var DropboxStorage $storage
 */
$storage = $tplData["storage"];
/** @var false|object */
$accountInfo = $tplData["accountInfo"];
/** @var false|array{used:int,total:int,perc:float,available:string} */
$quotaInfo = $tplData["quotaInfo"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var string */
$accessToken = $tplData["accessToken"];
/** @var string */
$accessTokenSecret = $tplData["accessTokenSecret"];
/** @var string */
$v2AccessToken = $tplData["v2AccessToken"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <th scope="row"><label><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="dropbox-authorize">
        <div class="authorization-state" id="state-unauthorized">
            <!-- CONNECT -->
            <button id="dpro-dropbox-connect-btn" type="button" class="button button-large" onclick="DupPro.Storage.Dropbox.DropboxGetAuthUrl();">
                <i class="fa fa-plug"></i> <?php esc_html_e('Connect to Dropbox', 'duplicator-pro'); ?>
                <img 
                    src="<?php echo esc_url(DUPLICATOR_PRO_IMG_URL . '/dropbox-24.png'); ?>" 
                    style='vertical-align: middle; margin:-2px 0 0 3px; height:18px; width:18px' 
                >
            </button>
        </div>

        <div class="authorization-state" id="state-waiting-for-request-token">
            <div style="padding:10px">
                <i class="fas fa-circle-notch fa-spin"></i> <?php esc_html_e('Getting Dropbox request token...', 'duplicator-pro'); ?>
            </div>
        </div>

        <div class="authorization-state" id="state-waiting-for-auth-button-click">
            <!-- STEP 2 -->
            <b><?php esc_html_e("Step 1:", 'duplicator-pro'); ?></b>&nbsp;
            <?php esc_html_e(' Duplicator needs to authorize at the Dropbox.com website.', 'duplicator-pro'); ?>
            <div class="auth-code-popup-note">
                <?php
                echo __(
                    'Note: Clicking the button below will open a new tab/window. Please be sure your browser does not block popups.',
                    'duplicator-pro'
                ) . ' ' .
                __('If a new tab/window does not open check your browsers address bar to allow popups from this URL.', 'duplicator-pro');
                ?>
            </div>
            <button id="auth-redirect" type="button" class="button button-large" onclick="DupPro.Storage.Dropbox.OpenAuthPage(); return false;">
                <i class="fa fa-user"></i> <?php esc_html_e('Authorize Dropbox', 'duplicator-pro'); ?>
            </button>
            <br/><br/>

            <div id="dropbox-auth-code-area">
                <b><?php esc_html_e('Step 2:', 'duplicator-pro'); ?></b> 
                <?php esc_html_e("Paste code from Dropbox authorization page.", 'duplicator-pro'); ?> <br/>
                <input style="width:400px" id="dropbox-auth-code" name="dropbox-auth-code" />
            </div>

            <!-- STEP 3 -->
            <b><?php esc_html_e("Step 3:", 'duplicator-pro'); ?></b>&nbsp;
            <?php esc_html_e('Finalize Dropbox validation by clicking the "Finalize Setup" button.', 'duplicator-pro'); ?>
            <br/>
            <button id="dropbox-finalize-setup" type="button" class="button" >
                <i class="fa fa-check-square"></i> <?php esc_html_e('Finalize Setup', 'duplicator-pro'); ?>
            </button>
        </div>

        <div class="authorization-state" id="state-waiting-for-access-token">
            <div>
                <i class="fas fa-circle-notch fa-spin"></i> 
                <?php esc_html_e('Performing final authorization...Please wait', 'duplicator-pro'); ?>
            </div>
        </div>

        <div class="authorization-state" id="state-authorized" style="margin-top:-5px">
        <?php if ($storage->isAuthorized() && is_object($accountInfo)) : ?>
            <h3>
                <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/dropbox-24.png" class="dup-store-auth-icon" alt="" />
                <?php esc_html_e('Dropbox Account', 'duplicator-pro'); ?><br/>
                <i class="dpro-edit-info">
                    <?php esc_html_e('Duplicator has been authorized to access this user\'s Dropbox account', 'duplicator-pro'); ?>
                </i>
            </h3>
            <div id="dropbox-account-info">
                <label><?php esc_html_e('Name', 'duplicator-pro'); ?>:</label>
                <?php echo esc_html($accountInfo->name->display_name); ?><br/>

                <label><?php esc_html_e('Email', 'duplicator-pro'); ?>:</label>
                <?php echo esc_html($accountInfo->email); ?>
                <?php if ($quotaInfo) { ?>
                    <br/>
                    <label><?php esc_html_e('Quota Usage', 'duplicator-pro'); ?>:</label>
                    <?php
                    printf(__('%1$s %% used, %2$s available', 'duplicator-pro'), $quotaInfo['perc'], $quotaInfo['available']);
                }
                ?>
            </div>
        <?php endif; ?>
            <br/>
            <button type="button" class="button" onclick='DupPro.Storage.Dropbox.CancelAuthorization();'>
                <?php esc_html_e('Cancel Authorization', 'duplicator-pro'); ?>
            </button><br/>
            <i class="dpro-edit-info">
                <?php esc_html_e('Disassociates storage provider with the Dropbox account. Will require re-authorization.', 'duplicator-pro'); ?>
            </i>
        </div>
    </td>
</tr>
<tr>
    <th scope="row"><label for="_dropbox_storage_folder"><?php esc_html_e("Storage Folder", 'duplicator-pro'); ?></label></th>
    <td>
        <b>//Dropbox/Apps/Duplicator Pro/</b>
        <input id="_dropbox_storage_folder" name="_dropbox_storage_folder" 
            type="text" value="<?php echo esc_attr($storageFolder); ?>" class="dpro-storeage-folder-path" />
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
    <th scope="row"><label for=""><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="dropbox_max_files">
            <input data-parsley-errors-container="#dropbox_max_files_error_container" id="dropbox_max_files" 
                name="dropbox_max_files" type="text" value="<?php echo $maxPackages; ?>" maxlength="4">
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?> <br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="dropbox_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php $tplMng->render('admin_pages/storages/parts/provider_foot'); ?>
<?php
$alertConnStatus          = new DUP_PRO_UI_Dialog();
$alertConnStatus->title   = __('Dropbox Connection Status', 'duplicator-pro');
$alertConnStatus->message = ''; // javascript inserted message
$alertConnStatus->initAlert();
?>
<script>
    jQuery(document).ready(function ($) {
        // DROPBOX RELATED METHODS
        DupPro.Storage.Dropbox.AuthorizationStates = {
            UNAUTHORIZED: 0,
            WAITING_FOR_REQUEST_TOKEN: 1,
            WAITING_FOR_AUTH_BUTTON_CLICK: 2,
            WAITING_FOR_ACCESS_TOKEN: 3,
            AUTHORIZED: 4
        }

        DupPro.Storage.Dropbox.authorizationState = <?php echo ($storage->isAuthorized() ? 4 : 0); ?>;

        DupPro.Storage.Dropbox.CancelAuthorization = function () {
            DupPro.Storage.RevokeAuth(<?php echo $storage->getId(); ?>);
        }

        DupPro.Storage.Dropbox.DropboxGetAuthUrl = function ()
        {
            DupPro.Storage.Dropbox.AuthUrl = <?php echo json_encode($storage->getAuthorizationUrl()); ?>;
            jQuery("#state-waiting-for-auth-button-click").show();
        };

        DupPro.Storage.Dropbox.TransitionAuthorizationState = function (newState)
        {
            jQuery('.authorization-state').hide();
            jQuery('.dropbox_access_type').prop('disabled', true);
            jQuery('.button_dropbox_test').prop('disabled', true);

            switch (newState) {
                case DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED:
                    jQuery('.dropbox_access_type').prop('disabled', false);
                    $("#dropbox_authorization_state").val(DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED);
                    DupPro.Storage.Dropbox.requestToken = null;
                    jQuery("#state-unauthorized").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_REQUEST_TOKEN:
                    DupPro.Storage.Dropbox.GetRequestToken();
                    jQuery("#state-waiting-for-request-token").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_AUTH_BUTTON_CLICK:
                    // Nothing to do here other than show the button and wait
                    jQuery("#state-waiting-for-auth-button-click").show();
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.WAITING_FOR_ACCESS_TOKEN:
                    jQuery("#state-waiting-for-access-token").show();
                    if (DupPro.Storage.Dropbox.requestToken != null) {
                        DupPro.Storage.Dropbox.GetAccessToken();
                    } else {
                        <?php $alertConnStatus->showAlert(); ?>
                        let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + 
                            "<?php esc_html_e('Tried transitioning to auth button click but don\'t have the request token!', 'duplicator-pro'); ?>";
                        <?php $alertConnStatus->updateMessage("alertMsg"); ?>
                        DupPro.Storage.Dropbox.TransitionAuthorizationState(DupPro.Storage.Dropbox.AuthorizationStates.UNAUTHORIZED);
                    }
                    break;

                case DupPro.Storage.Dropbox.AuthorizationStates.AUTHORIZED:
                    var token = $("#dropbox_access_token").val();
                    var token_secret = $("#dropbox_access_token_secret").val();
                    DupPro.Storage.Dropbox.accessToken = {};
                    DupPro.Storage.Dropbox.accessToken.t = token;
                    DupPro.Storage.Dropbox.accessToken.s = token_secret;
                    jQuery("#state-authorized").show();
                    jQuery('.button_dropbox_test').prop('disabled', false);
                    break;
            }

            DupPro.Storage.Dropbox.authorizationState = newState;
        }
        
        DupPro.Storage.Dropbox.OpenAuthPage = function ()
        {
            window.open(DupPro.Storage.Dropbox.AuthUrl, '_blank');
        }

        $('#dropbox-finalize-setup').click(function (event) {
            event.stopPropagation();

            if ($('#dropbox-auth-code').val().length > 5) {
                DupPro.Storage.PrepareForSubmit();

                //$("#dup-storage-form").submit();

                DupPro.Storage.Authorize(
                    <?php echo $storage->getId(); ?>, 
                    <?php echo $storage->getSType(); ?>, 
                    {
                        'name': $('#name').val(),
                        'notes': $('#notes').val(),
                        'storage_folder': $('#_dropbox_storage_folder').val(),
                        'max_packages': $('#dropbox_max_files').val(),
                        'auth_code' : $('#dropbox-auth-code').val()
                    }
                );
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + 
                    "<?php esc_html_e('Please enter your Dropbox authorization code!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }

            return false;
        });

        DupPro.Storage.Dropbox.TransitionAuthorizationState(DupPro.Storage.Dropbox.authorizationState);
        $('button#auth-validate').prop('disabled', true);
    });
</script>