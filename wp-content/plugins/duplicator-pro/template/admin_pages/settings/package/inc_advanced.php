<?php

/**
 * @package Duplicator
 */

defined("ABSPATH") or die("");

/**
 * Variables
 *
 * @var Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var Duplicator\Core\Views\TplMng $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Controllers\SettingsPageController;
use Duplicator\Libs\Snap\SnapIO;

$global  = DUP_PRO_Global_Entity::getInstance();
$sglobal = DUP_PRO_Secure_Global_Entity::getInstance();

$max_execution_time    = ini_get("max_execution_time");
$max_execution_time    = empty($max_execution_time) ? 30 : $max_execution_time;
$max_worker_cap_in_sec = (int) (0.7 * (float) $max_execution_time);
if ($max_worker_cap_in_sec > 180) {
    $max_worker_cap_in_sec = 180;
}

?>

<!-- ===============================
ADVANCED SETTINGS -->
<form id="dup-settings-form" 
    action="<?php echo Duplicator\Core\Controllers\ControllersManager::getCurrentLink(); ?>" 
    method="post" data-parsley-validate
>
    <?php $tplData['actions'][SettingsPageController::ACTION_PACKAGE_ADVANCED_SAVE]->getActionNonceFileds(); ?>

    <p class="description" style="color:maroon">
        <i class="fas fa-exclamation-circle"></i>
        <?php DUP_PRO_U::esc_html_e("Do not modify advanced settings unless you know the expected result or have talked to support."); ?>
    </p>
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Thread Lock"); ?></label></th>
            <td>
                <input 
                    type="radio" name="lock_mode" id="lock_mode_flock" value="<?php echo DUP_PRO_Thread_Lock_Mode::Flock; ?>" 
                    <?php checked($global->lock_mode, DUP_PRO_Thread_Lock_Mode::Flock); ?> 
                >
                <label for="lock_mode_flock"><?php DUP_PRO_U::esc_html_e("File"); ?></label> &nbsp;
                <input 
                    type="radio" name="lock_mode" id="lock_mode_sql" value="<?php echo DUP_PRO_Thread_Lock_Mode::SQL_Lock; ?>" 
                    <?php checked($global->lock_mode, DUP_PRO_Thread_Lock_Mode::SQL_Lock); ?> 
                >
                <label for="lock_mode_sql"><?php DUP_PRO_U::esc_html_e("SQL"); ?></label> &nbsp;
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Max Worker Time"); ?></label></th>
            <td>
                <input 
                    style="float:left;display:block;margin-right:6px;" 
                    data-parsley-required data-parsley-errors-container="#php_max_worker_time_in_sec_error_container" 
                    data-parsley-min="10" data-parsley-type="number" class="dup-narrow-input" 
                    type="text" name="php_max_worker_time_in_sec" 
                    id="php_max_worker_time_in_sec" 
                    value="<?php echo $global->php_max_worker_time_in_sec; ?>" 
                >
                <p style="margin-left:4px;"><?php DUP_PRO_U::esc_html_e('Seconds'); ?></p>
                <div id="php_max_worker_time_in_sec_error_container" class="duplicator-error-container"></div>
                <p class="description">
                    <?php
                    printf(
                        DUP_PRO_U::esc_html__('Lower is more reliable but slower. Recommended max is %1$s sec based on PHP setting %2$s.'),
                        $max_worker_cap_in_sec,
                        $max_execution_time
                    );
                    ?><br/>
                    <?php DUP_PRO_U::esc_html_e("Try a low value (30 seconds or lower) if the build fails with the recommended setting."); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e('Chunk Size'); ?></label></th>
            <td>
                <input type="number" name="_chunk_size" id="_chunk_size" value="<?php echo $global->chunk_size; ?>" minlength="4" min="1024"
                       data-parsley-required
                       data-parsley-minlength="10"
                       data-parsley-errors-container="#chunk_size_error_container" />
                <div id="chunk_size_error_container" class="duplicator-error-container"></div>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e('Archive upload chunk size'); ?>
                </p>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Ajax"); ?></label></th>
            <td>
                <input 
                    type="radio" id="ajax_protocol_1" name="ajax_protocol" class="ajax_protocol" value="admin" 
                    <?php checked($global->ajax_protocol, 'admin'); ?> />
                <label for="ajax_protocol_1"><?php DUP_PRO_U::esc_html_e("Auto"); ?></label> &nbsp;
                <input 
                    type="radio" id="ajax_protocol_2" name="ajax_protocol" class="ajax_protocol"
                    value="http" <?php checked($global->ajax_protocol == 'http'); ?> />
                <label for="ajax_protocol_2"><?php DUP_PRO_U::esc_html_e("HTTP"); ?></label> &nbsp;
                <input 
                    type="radio" id="ajax_protocol_3" name="ajax_protocol" class="ajax_protocol"  value="https" 
                    <?php checked($global->ajax_protocol == 'https'); ?> />
                <label for="ajax_protocol_3"><?php DUP_PRO_U::esc_html_e("HTTPS"); ?></label> &nbsp;
                <input 
                    type="radio" id="ajax_protocol_4" name="ajax_protocol" class="ajax_protocol" value="custom" 
                    <?php checked($global->ajax_protocol, 'custom'); ?> />
                <label for="ajax_protocol_4"><?php DUP_PRO_U::esc_html_e("Custom URL"); ?></label> <br/>
                <input 
                    style="width:600px" 
                    type="<?php echo ($global->ajax_protocol == 'custom' ? 'text' : 'hidden'); ?>" 
                    id="custom_ajax_url" name="custom_ajax_url" 
                    placeholder="<?php DUP_PRO_U::esc_attr_e('Consult support before changing.'); ?>" 
                    value="<?php echo $global->custom_ajax_url; ?>" 
                > 
                    <span id="custom_ajax_url_error" style="color: maroon; text-weight: bold; display: none"><?php DUP_PRO_U::esc_html_e("Bad URL!"); ?>
                </span>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e("Used to kick off build worker. Only change if packages get stuck at the start of a build."); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row">
                <label>Root path</label>
            </th>
            <td>
                <input 
                    type="checkbox" name="homepath_as_abspath" id="homepath_as_abspath" 
                    <?php disabled(DUP_PRO_Archive::isAbspathHomepathEquivalent()); ?> 
                    <?php checked($global->homepath_as_abspath); ?> value="1" 
                > 
                <label for="homepath_as_abspath">
                    <?php printf(DUP_PRO_U::__('Use ABSPATH <b>%s</b> as root path.'), esc_html(DUP_PRO_Archive::getArchiveListPaths('abs'))); ?><br>
                </label>
                <p class="description">
                    <?php
                    if (DUP_PRO_Archive::isAbspathHomepathEquivalent()) {
                        DUP_PRO_U::esc_html_e('Abspath and home path are equivalent so this option is disabled');
                    } else {
                        ?>
                        <?php
                            printf(
                                DUP_PRO_U::__(
                                    'In this installation the default root path is <b>%s</b>.'
                                ),
                                esc_html(SnapIO::safePathUntrailingslashit(get_home_path(), true))
                            ); ?><br>
                        <?php
                        DUP_PRO_U::esc_html_e(
                            'The path of the WordPress core is different. Activate this option if you want to consider ABSPATH as root path.'
                        );
                    }
                    ?>

                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e('Scan File Checks'); ?></label></th>
            <td>
                <input 
                    type="checkbox" name="_skip_archive_scan" id="_skip_archive_scan" 
                    <?php checked($global->skip_archive_scan); ?> 
                >
                <label for="_skip_archive_scan"><?php DUP_PRO_U::esc_html_e("Skip") ?> </label><br/>
                <p class="description">
                    <?php
                    DUP_PRO_U::esc_html_e(
                        'If enabled all files check on scan will be skipped before package creation. ' .
                        'In some cases, this option can be beneficial if the ' .
                        'scan process is having issues running or returning errors.'
                    );
                    ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e('Client-side Kickoff'); ?></label></th>
            <td>
                <input type="checkbox" name="_clientside_kickoff" id="_clientside_kickoff" <?php checked($global->clientside_kickoff); ?> />
                <label for="_clientside_kickoff"><?php DUP_PRO_U::esc_html_e("Enabled") ?> </label><br/>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e('Initiate package build from client. Only check this if instructed to by Duplicator support.'); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Basic Auth"); ?></label></th>
            <td>
                <input 
                    type="checkbox" 
                    name="_basic_auth_enabled" 
                    id="_basic_auth_enabled" 
                    value="1"
                    <?php checked($global->basic_auth_enabled); ?> 
                >
                <label for="_basic_auth_enabled"><?php DUP_PRO_U::esc_html_e("Enabled") ?> </label><br/>
                <input 
                    style="margin-top:8px;width:200px;" 
                    class="dup-wide-input" autocomplete="off" 
                    placeholder="<?php DUP_PRO_U::esc_attr_e('User'); ?>" 
                    type="text" name="basic_auth_user" 
                    id="basic_auth_user" 
                    value="<?php echo esc_attr($global->basic_auth_user); ?>" 
                >
                <input 
                    id='auth_password' 
                    autocomplete="off" 
                    style="width:200px;" 
                    class="dup-wide-input"  
                    placeholder="<?php DUP_PRO_U::esc_attr_e('Password'); ?>" 
                    type="password" 
                    name="basic_auth_password" 
                    id="basic_auth_password" 
                    value="<?php echo esc_attr($sglobal->basic_auth_password); ?>" 
                >
                <label for="auth_password">
                    <i class="dpro-edit-info">
                        <input type="checkbox" onclick="DupPro.UI.TogglePasswordDisplay(this.checked, 'auth_password');" >&nbsp;
                        <?php DUP_PRO_U::esc_html_e('Show Password') ?>
                    </i>
                </label>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e('Local Installer Name'); ?></label></th>
            <td>
                <input type="text" name="_installer_base_name" id="_installer_base_name" value="<?php echo esc_attr($global->installer_base_name); ?>"
                       data-parsley-required
                       data-parsley-minlength="10"
                       data-parsley-errors-container="#installer_base_name_error_container" />
                <div id="installer_base_name_error_container" class="duplicator-error-container"></div>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e('The base name of the installer file. Only change if host prevents using installer.php'); ?>
                </p>
            </td>
        </tr>
    </table>

    <p class="submit dpro-save-submit">
        <input 
            type="submit" name="submit" id="submit" class="button-primary" 
            value="<?php DUP_PRO_U::esc_attr_e('Save Advanced Package Settings') ?>" style="display: inline-block;" 
        >
    </p>
</form>

<script>
    (function ($) {
        var url_error = $('#custom_ajax_url_error');
        // Check URL is valid
        $.urlExists = function (url) {
            var http = new XMLHttpRequest();
            try {
                http.open('HEAD', url, false);
                http.send();
            } catch (err) {
                $('#custom_ajax_url_error').html(err.message);
                return false;
            }
            return http.status != 404;
        };
        var debounce;
        $('#custom_ajax_url').on('input keyup keydown change paste focus', function (e) {
            clearTimeout(debounce);
            var $this = $(this);
            debounce = setTimeout(function () {
                $this.css({'border': ''});
                url_error.hide();
                if (!$.urlExists($this.val()))
                {
                    $this.css({'border': 'maroon 1px solid'});
                    url_error.show();
                }
            }, 500);
        });

        (function ($this) {
            $this.css({'border': ''});
            url_error.hide();
            setTimeout(function () {
                var isCustomAjaxUrl = $('#ajax_protocol_4').is(':checked');
                if (isCustomAjaxUrl && !$.urlExists($this.val()))
                {
                    $this.css({'border': 'maroon 1px solid'});
                    url_error.show();
                }
                if (isCustomAjaxUrl) {
                    $('#custom_ajax_url').attr('data-parsley-required', 'true');
                } else {
                    $('#custom_ajax_url').removeAttr('data-parsley-required');
                }
            }, 0);
        }($('#custom_ajax_url')))

        /*
         * DISPLAY OR HIDE CUSTOM_AJAX_URL
         */
        $('.ajax_protocol').on('input click change select touchstart', function (e) {
            // Setup and collect value
            var $this = $(this),
                    value = $this.val(),
                    hideField = $('#custom_ajax_url'),
                    hideFieldState = hideField.attr('type'),
                    offset = 200;
            url_error.hide();
            if (value == 'custom')
            {
                // Display hidden field
                if (hideFieldState == 'hidden')
                {
                    hideField.hide().attr('type', 'text').fadeIn(offset).attr('data-parsley-required', 'true');
                    hideField.css({'border': ''});
                    url_error.hide();
                    if (!$.urlExists(hideField.val()))
                    {
                        hideField.css({'border': 'maroon 1px solid'});
                        url_error.show();
                    }
                }
            } else
            {
                // Hide field but keep it active for POST reading
                if (hideFieldState == 'text')
                {
                    var parsleyId = $('#custom_ajax_url').data('parsley-id');
                    var errorUlId = '#parsley-id-' + parsleyId;
                    if ($(errorUlId).length)
                        $(errorUlId).remove();
                    hideField.fadeOut(Math.round(offset / 2), function () {
                        $(this).attr('type', 'hidden').show();
                    }).removeAttr('data-parsley-required');
                    ;
                }
            }
        });
    }(window.jQuery || jQuery))
</script>
