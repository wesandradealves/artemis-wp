<?php

/**
 * Duplicator messages sections
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use Duplicator\Models\Storages\GDriveStorage;

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 * @var GDriveStorage $storage
 */
$storage = $tplData["storage"];
/** @var string */
$storageFolder = $tplData["storageFolder"];
/** @var int */
$maxPackages =  $tplData["maxPackages"];
/** @var ?Duplicator_Pro_Google_Service_Oauth2_Userinfoplus */
$userInfo = $tplData["userInfo"];
/** @var string */
$quotaString = $tplData["quotaString"];

$tplMng->render('admin_pages/storages/parts/provider_head');
?>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Authorization", 'duplicator-pro'); ?></label></th>
    <td class="gdrive-authorize">
    <?php if (!$storage->isAuthorized()) : ?>
            <div class='gdrive-authorization-state' id="gdrive-state-unauthorized">
                <!-- CONNECT -->
                <div id="dpro-gdrive-connect-btn-area">
                    <button 
                        id="dpro-gdrive-connect-btn" 
                        type="button" 
                        class="button button-large" 
                        onclick="DupPro.Storage.GDrive.GoogleGetAuthUrl();"
                    >
                        <i class="fa fa-plug"></i> <?php esc_html_e('Connect to Google Drive', 'duplicator-pro'); ?>
                        <img 
                            src="<?php echo esc_url(DUPLICATOR_PRO_IMG_URL . '/gdrive-24.png'); ?>" 
                            style='vertical-align: middle; margin:-2px 0 0 3px; height:18px; width:18px' 
                        />
                    </button>
                </div>
                <div class="authorization-state" id="dpro-gdrive-connect-progress">
                    <div style="padding:10px">
                        <i class="fas fa-circle-notch fa-spin"></i> <?php esc_html_e('Getting Google Drive Request Token...', 'duplicator-pro'); ?>
                    </div>
                </div>

                <!-- STEPS -->
                <div id="dpro-gdrive-steps">
                    <div>
                        <b><?php esc_html_e('Step 1:', 'duplicator-pro'); ?></b>&nbsp;
                        <?php
                        esc_html_e(
                            "Duplicator needs to authorize Google Drive. Make sure to allow all required permissions.",
                            'duplicator-pro'
                        ); ?>
                        <div class="auth-code-popup-note">
                            <?php
                            echo __(
                                'Note: Clicking the button below will open a new tab/window. Please be sure your browser does not block popups.',
                                'duplicator-pro'
                            ) . ' ' .
                            __('If a new tab/window does not open check your browsers address bar to allow popups from this URL.', 'duplicator-pro');
                            ?>
                        </div>
                        <button id="gdrive-auth-window-button" class="button" onclick="DupPro.Storage.GDrive.OpenAuthPage(); return false;">
                            <i class="fa fa-user"></i> <?php esc_html_e("Authorize Google Drive", 'duplicator-pro'); ?>
                        </button>
                    </div>

                    <div id="gdrive-auth-code-area">
                        <b><?php esc_html_e('Step 2:', 'duplicator-pro'); ?></b> 
                        <?php esc_html_e("Paste code from Google authorization page.", 'duplicator-pro'); ?> <br/>
                        <input style="width:400px" id="gdrive-auth-code" name="gdrive-auth-code" />
                    </div>

                    <b><?php esc_html_e('Step 3:', 'duplicator-pro'); ?></b> 
                    <?php esc_html_e('Finalize Google Drive setup by clicking the "Finalize Setup" button.', 'duplicator-pro') ?>
                    <br/>
                    <button 
                        id="gdrive-finalize-setup" 
                        type="button" 
                        class="button"
                    >
                        <i class="fa fa-check-square"></i> <?php esc_html_e('Finalize Setup', 'duplicator-pro'); ?>
                    </button>
                </div>
            </div>
    <?php else : ?>
            <div class='gdrive-authorization-state' id="gdrive-state-authorized" style="margin-top:-10px">
            <?php if ($userInfo != null) : ?>
                <h3>
                    <img src="<?php echo DUPLICATOR_PRO_IMG_URL ?>/gdrive-24.png" class="dup-store-auth-icon" alt=""  />
                    <?php esc_html_e('Google Drive Account', 'duplicator-pro'); ?><br/>
                    <i class="dpro-edit-info">
                        <?php esc_html_e('Duplicator has been authorized to access this user\'s Google Drive account', 'duplicator-pro'); ?>
                    </i>
                </h3>
                <div id="gdrive-account-info">
                    <label><?php esc_html_e('Name', 'duplicator-pro'); ?>:</label>
                    <?php echo esc_html($userInfo->givenName . ' ' . $userInfo->familyName); ?><br/>

                    <label><?php esc_html_e('Email', 'duplicator-pro'); ?>:</label> <?php echo esc_html($userInfo->email); ?>
                    <?php if (strlen($quotaString) > 0) { ?>
                        <br>
                        <label><?php esc_html_e('Quota', 'duplicator-pro'); ?>:</label> <?php echo esc_html($quotaString); ?>
                    <?php } ?>
                </div><br/>
            <?php else : ?>
                <div><?php esc_html_e('Error retrieving user information.', 'duplicator-pro'); ?></div>
            <?php endif ?>

                <button 
                    id="dup-gdrive-cancel-authorization"
                    type="button" 
                    class="button"
                >
                    <?php esc_html_e('Cancel Authorization', 'duplicator-pro'); ?>
                </button><br/>
                <i class="dpro-edit-info">
                    <?php
                    esc_html_e(
                        'Disassociates storage provider with the Google Drive account. Will require re-authorization.',
                        'duplicator-pro'
                    ); ?> 
                </i>
            </div>
    <?php endif ?>
    </td>
</tr>
<tr>
    <th scope="row">
        <label for="_gdrive_storage_folder">
            <?php esc_html_e("Storage Folder", 'duplicator-pro'); ?>
        </label>
    </th>
    <td>
        <b>//Google Drive/</b>
        <input 
            id="_gdrive_storage_folder" 
            name="_gdrive_storage_folder" 
            type="text" 
            value="<?php echo esc_attr($storageFolder); ?>"  
            class="dpro-storeage-folder-path"
        >
        <p>
            <i>
                <?php
                esc_html_e(
                    "Folder where packages will be stored. This should be unique for each web-site using Duplicator.",
                    'duplicator-pro'
                ); ?>
            </i>
            <?php
            $tipContent = __(
                'If the directory path above is already in Google Drive before connecting then a duplicate folder name will be made in the same path.',
                'duplicator-pro'
            ) . ' ' . __('This is because the plugin only has rights to folders it creates.', 'duplicator-pro');
            ?>
            <i 
                class="fas fa-question-circle fa-sm"
                data-tooltip-title="<?php esc_attr_e("Storage Folder Notice", 'duplicator-pro'); ?>"
                data-tooltip="<?php esc_attr($tipContent); ?>"
                >
            </i>

        </p>
    </td>
</tr>
<tr>
    <th scope="row"><label for=""><?php esc_html_e("Max Packages", 'duplicator-pro'); ?></label></th>
    <td>
        <label for="gdrive_max_files">
            <input 
                id="gdrive_max_files" 
                data-parsley-errors-container="#gdrive_max_files_error_container"
                name="gdrive_max_files" 
                type="text" value="<?php echo $maxPackages; ?>" maxlength="4"
            >&nbsp;
            <?php esc_html_e("Number of packages to keep in folder.", 'duplicator-pro'); ?> <br/>
            <i><?php esc_html_e("When this limit is exceeded, the oldest package will be deleted. Set to 0 for no limit.", 'duplicator-pro'); ?></i>
        </label>
        <div id="gdrive_max_files_error_container" class="duplicator-error-container"></div>
    </td>
</tr>
<?php
$tplMng->render('admin_pages/storages/parts/provider_foot');

// Alerts for Google Drive
$alertConnStatus          = new DUP_PRO_UI_Dialog();
$alertConnStatus->title   = __('Google Drive Authorization Error', 'duplicator-pro');
$alertConnStatus->message = ''; // javascript inserted message
$alertConnStatus->initAlert();

?>
<script>
    jQuery(document).ready(function ($) {

        $('#dup-gdrive-cancel-authorization').click(function (event) {
            event.stopPropagation();
            DupPro.Storage.RevokeAuth(<?php echo $storage->getId(); ?>);
        });

        DupPro.Storage.GDrive.GoogleGetAuthUrl = function ()
        {
            $('#dpro-gdrive-connect-btn-area').hide();
            $('#dpro-gdrive-steps').show();
            DupPro.Storage.GDrive.AuthUrl = <?php echo json_encode($storage->getAuthorizationUrl()); ?>;
        }

        DupPro.Storage.GDrive.OpenAuthPage = function ()
        {
            window.open(DupPro.Storage.GDrive.AuthUrl, '_blank');
        }

        $('#gdrive-finalize-setup').click(function (event) {
            event.stopPropagation();

            if ($('#gdrive-auth-code').val().length > 5) {
                DupPro.Storage.PrepareForSubmit();

                //$("#dup-storage-form").submit();

                DupPro.Storage.Authorize(
                    <?php echo $storage->getId(); ?>, 
                    <?php echo $storage->getSType(); ?>, 
                    {
                        'name': $('#name').val(),
                        'notes': $('#notes').val(),
                        'storage_folder': $('#_gdrive_storage_folder').val(),
                        'max_packages': $('#gdrive_max_files').val(),
                        'auth_code' : $('#gdrive-auth-code').val()
                    }
                );
            } else {
                <?php $alertConnStatus->showAlert(); ?>
                let alertMsg = "<i class='fas fa-exclamation-triangle'></i> " + 
                    "<?php esc_html_e('Please enter your Google Drive authorization code!', 'duplicator-pro'); ?>";
                <?php $alertConnStatus->updateMessage("alertMsg"); ?>
            }

            return false;
        });
    });
</script>
