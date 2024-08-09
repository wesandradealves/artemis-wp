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
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Libs\Shell\Shell;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\ZipArchiveExtended;

$global                = DUP_PRO_Global_Entity::getInstance();
$action_updated        = null;
$action_response       = DUP_PRO_U::__("Package Settings Saved");
$isZipArchiveAvailable = ZipArchiveExtended::isPhpZipAvaiable();
$isShellZipAvailable   = (DUP_PRO_Zip_U::getShellExecZipPath() != null);
$is_shellexec_on       = Shell::test();
$user_id               = get_current_user_id();
$package_ui_created    = is_numeric(get_user_meta($user_id, 'duplicator_pro_created_format', true)) ?
    get_user_meta($user_id, 'duplicator_pro_created_format', true) : 1;
//Old option was $global->package_ui_created

if (isset($_REQUEST['_package_mysqldump_path'])) {
    $mysqldump_exe_file                  = SnapUtil::sanitizeNSCharsNewlineTrim($_REQUEST['_package_mysqldump_path']);
    $mysqldump_exe_file                  = preg_match('/^([A-Za-z]\:)?[\/\\\\]/', $mysqldump_exe_file) ? $mysqldump_exe_file : '';
    $mysqldump_exe_file                  = preg_replace('/[\'";]/m', '', $mysqldump_exe_file);
    $_REQUEST['_package_mysqldump_path'] = SnapIO::safePathUntrailingslashit($mysqldump_exe_file);
}


$mysqlDumpPath     = DUP_PRO_DB::getMySqlDumpPath();
$mysqlDumpFound    = ($mysqlDumpPath) ? true : false;
$installerNameMode = $global->installer_name_mode;

?>

<form id="dup-settings-form" class="dup-settings-pack-basic" action="<?php echo ControllersManager::getCurrentLink(); ?>" method="post" data-parsley-validate>
    <?php $tplData['actions'][SettingsPageController::ACTION_PACKAGE_BASIC_SAVE]->getActionNonceFileds(); ?>

    <!-- ===============================
    DATABASE -->
    <h3 class="title"><?php DUP_PRO_U::esc_html_e("Database") ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("SQL Mode"); ?></label></th>
            <td>

                <div class="engine-radio <?php echo ($is_shellexec_on) ? '' : 'engine-radio-disabled'; ?>">
                    <input 
                        type="radio" name="_package_dbmode" value="mysql" id="package_mysqldump" 
                        <?php checked($global->package_mysqldump); ?>  onclick="DupPro.UI.SetDBEngineMode();" 
                    >
                    <label for="package_mysqldump"><?php DUP_PRO_U::esc_html_e("Mysqldump"); ?> </label> &nbsp; &nbsp; &nbsp;
                </div>

                <div class="engine-radio">
                    <input 
                        type="radio" name="_package_dbmode" id="package_phpdump" value="php" 
                        <?php checked(!$global->package_mysqldump); ?>  onclick="DupPro.UI.SetDBEngineMode();"  
                    >
                    <label for="package_phpdump"><?php DUP_PRO_U::esc_html_e("PHP Code"); ?></label>
                </div>

                <br style="clear:both"/><br/>

                <!-- SHELL EXEC  -->
                <div class="engine-sub-opts" id="dbengine-details-1" style="display:none">
                    <!-- MYSQLDUMP IN-ACTIVE -->
                    <?php if (!$is_shellexec_on) :
                        ?>
                        <div class="dup-feature-notfound">
                            <?php
                            _e('In order to use Mysqldump, one of the PHP functions has to be enabled: popen/pclose, exec or shell_exec.', 'duplicator-pro');
                            echo ' ';
                            _e('Please contact your host or server admin to enable one or more these functions.', 'duplicator-pro');
                            echo ' ';
                            printf(
                                _x(
                                    'For a list of approved providers that support these functions, %1$sclick here%2$s.',
                                    '%1$s and %2$s are the opening and closing tags of a link.',
                                    'duplicator-pro'
                                ),
                                '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'what-host-providers-are-recommended-for-duplicator/" target="_blank">',
                                '</a>'
                            );
                            echo ' ';
                            _e('The "PHP Code" setting will be used until this issue is resolved by your hosting provider.', 'duplicator-pro');
                            ?>
                            <p>
                                <?php
                                _e('Below is a list of possible functions to activate to solve the problem.', 'duplicator-pro');
                                echo ' ';
                                _e('If the problem persists, look at the log for a more thorough analysis.', 'duplicator-pro');
                                ?>
                            </p>
                            <br/>
                            <b><?php _e('Disabled Functions:', 'duplicator-pro'); ?></b>
                            <code class="display-block margin-bottom-1">
                                <?php
                                foreach (['escapeshellarg', 'escapeshellcmd', 'extension_loaded', 'exec', 'popen', 'pclose', 'shell_exec'] as $func) {
                                    if (Shell::hasDisabledFunctions($func)) {
                                        echo $func;
                                        echo '<br>';
                                    }
                                }
                                ?>
                            </code>
                            <?php
                            printf(
                                _x(
                                    'FAQ: %1$sHow to enable disabled PHP functions.%2$s',
                                    '%1$s and %2$s are the opening and closing tags of a link.',
                                    'duplicator-pro'
                                ),
                                '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-resolve-dependency-checks" target="_blank">',
                                '</a>'
                            );
                            ?>
                        </div>
                        <!-- MYSQLDUMP ACTIVE -->
                        <?php
                    else :
                        $tipContent =  DUP_PRO_U::esc_attr__(
                            'Add a custom path if the path to mysqldump is not properly detected.   For all paths use a forward slash as the ' .
                            'path seperator.  On Linux systems use mysqldump for Windows systems use mysqldump.exe. ' .
                            ' If the path tried does not work please contact your hosting ' .
                             'provider for details on the correct path.'
                        );
                        ?>
                        <label><?php DUP_PRO_U::esc_html_e("Current Path:"); ?></label>
                        <?php
                        SettingsPageController::getMySQLDumpMessage(
                            $mysqlDumpFound,
                            (!empty($mysqlDumpPath) ? $mysqlDumpPath : $global->package_mysqldump_path)
                        ); ?><br>
                        <label><?php DUP_PRO_U::esc_html_e("Custom Path:"); ?></label>
                        <input 
                            class="dup-wide-input" 
                            type="text" 
                            name="_package_mysqldump_path" 
                            id="_package_mysqldump_path" 
                            value="<?php echo esc_attr($global->package_mysqldump_path); ?>" 
                            placeholder="<?php DUP_PRO_U::esc_attr_e("/usr/bin/mypath/mysqldump"); ?>" 
                        >&nbsp;
                        <i class="fas fa-question-circle fa-sm"
                           data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("mysqldump"); ?>"
                           data-tooltip="<?php echo $tipContent; ?>">
                        </i><br>

                        <label><?php DUP_PRO_U::esc_html_e("Switch-Options:"); ?></label>
                        <div class="dup-group-option-wrapper">
                            <?php
                                $mysqldumpOptions = $global->getMysqldumpOptions();
                            foreach ($mysqldumpOptions as $key => $option) {
                                ?>
                                <div 
                                    class="dup-group-option-item"><input type="checkbox" name="<?php echo $option->getInputName(); ?>" 
                                    id="<?php echo $option->getInputName(); ?>"
                                    <?php checked($option->getEnabled()); ?>
                                >
                            --<?php echo $option->getOptionName(); ?></div>
                            <?php } ?>
                        </div>
                        <?php
                    endif; ?>
                </div>

                <!-- PHP OPTION -->
                <div class="engine-sub-opts" id="dbengine-details-2" style="display:none; line-height: 35px; margin-top:-5px">
                    <label><?php DUP_PRO_U::esc_html_e("Process Mode"); ?></label>
                    <select name="_phpdump_mode">
                        <option 
                            <?php selected($global->package_phpdump_mode, DUP_PRO_DB::PHPDUMP_MODE_MULTI); ?>
                            value="<?php echo DUP_PRO_DB::PHPDUMP_MODE_MULTI; ?>"
                        >
                            <?php DUP_PRO_U::esc_html_e("Multi-Threaded"); ?>
                        </option>
                        <option 
                            <?php selected($global->package_phpdump_mode, DUP_PRO_DB::PHPDUMP_MODE_SINGLE); ?>
                            value="<?php echo DUP_PRO_DB::PHPDUMP_MODE_SINGLE; ?>"
                        >
                            <?php DUP_PRO_U::esc_html_e("Single-Threaded"); ?>
                        </option>
                    </select>&nbsp;

                    <i style="margin-right:7px;" class="fas fa-question-circle fa-sm"
                       data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("PHP Code Mode"); ?>"
                       data-tooltip="<?php
                        DUP_PRO_U::esc_attr_e(
                            'Single-Threaded mode attempts to create the entire database script in one request. ' .
                            ' Multi-Threaded mode allows the database script ' .
                            'to be chunked over multiple requests. ' .
                            ' Multi-Threaded mode is typically slower but much more reliable especially for larger databases.'
                        );
                        ?>"></i>
                </div>
            </td>
        </tr>
        <tr>
            <th scope="row"><label for="_package_mysqldump_qrylimit"><?php DUP_PRO_U::esc_html_e("Query Size"); ?></label></th>
            <td>
                <select name="_package_mysqldump_qrylimit" id="_package_mysqldump_qrylimit" style="width:70px">
                    <?php
                    foreach (DUP_PRO_Constants::getMysqlDumpChunkSizes() as $value => $label) {
                        $selected = ( $global->package_mysqldump_qrylimit == $value ? "selected='selected'" : '' );
                        echo "<option {$selected} value='" . $value . "'>" . esc_html($label) . '</option>';
                    }
                    ?>
                </select>&nbsp;
                <?php $tipContent = DUP_PRO_U::esc_attr__(
                    'A higher limit size will speed up the database build time, however it will use more memory. ' .
                    'If your host has memory caps start off low.'
                ); ?>
                <i style="margin-right:7px" class="fas fa-question-circle fa-sm"
                   data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("MYSQL Query Limit Size"); ?>"
                   data-tooltip="<?php echo $tipContent; ?>">
                </i>
            </td>
        </tr>                        
    </table>

    <!-- ===========================
    ARCHIVE -->
    <h3 class="title"><?php DUP_PRO_U::esc_html_e("Archive") ?> </h3>
    <hr size="1" />

    <!-- ===========================
    ARCHIVE ENGINE -->
    <table class="form-table" id="archive-build-manual">
        <tr>
            <th scope="row">
                <label><?php DUP_PRO_U::esc_html_e("Compression"); ?></label>
            </th>
            <td>
                <input type="radio" name="archive_compression" id="archive_compression_off" value="0" 
                    <?php checked($global->archive_compression, false); ?> />
                <label for="archive_compression_off"><?php DUP_PRO_U::esc_html_e("Off"); ?></label> &nbsp;
                <input type="radio" name="archive_compression"  id="archive_compression_on" value="1" 
                    <?php checked($global->archive_compression); ?>  />
                <label for="archive_compression_on"><?php DUP_PRO_U::esc_html_e("On"); ?></label>
                <?php $tipContent = DUP_PRO_U::esc_attr__(
                    'This setting controls archive compression. The setting apply to all Archive Engine formats. ' .
                    'For ZipArchive this setting only works on PHP 7.0 or higher.'
                ); ?>&nbsp;
                <i style="margin-right:7px;" class="fas fa-question-circle fa-sm"
                   data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("Archive Compression"); ?>"
                   data-tooltip="<?php echo $tipContent; ?>">
                </i>
            </td>
        </tr>
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Archive Engine"); ?></label></th>
            <td>
                <div class="engine-radio">
                    <input 
                        onclick="DupPro.UI.SetArchiveOptionStates();" 
                        type="radio" 
                        name="archive_build_mode" id="archive_build_mode3"  
                        value="<?php echo DUP_PRO_Archive_Build_Mode::DupArchive; ?>" 
                        <?php checked($global->getBuildMode() == DUP_PRO_Archive_Build_Mode::DupArchive); ?> 
                        <?php disabled(!$global->isBuildModeAvaiable(DUP_PRO_Archive_Build_Mode::DupArchive)) ?>
                    >
                    <label for="archive_build_mode3"><?php DUP_PRO_U::esc_html_e("DupArchive"); ?></label> &nbsp; &nbsp;
                </div>
                <div class="engine-radio <?php echo ($isShellZipAvailable) ? '' : 'engine-radio-disabled'; ?>">
                    <input 
                        onclick="DupPro.UI.SetArchiveOptionStates();" 
                        type="radio" 
                        name="archive_build_mode" 
                        id="archive_build_mode1"
                        value="<?php echo DUP_PRO_Archive_Build_Mode::Shell_Exec; ?>"
                        <?php checked($global->getBuildMode() == DUP_PRO_Archive_Build_Mode::Shell_Exec); ?> 
                        <?php disabled(!$global->isBuildModeAvaiable(DUP_PRO_Archive_Build_Mode::Shell_Exec)) ?>
                    >
                    <label for="archive_build_mode1"><?php DUP_PRO_U::esc_html_e("Shell Zip"); ?></label>
                </div>
                <div class="engine-radio">
                    <input 
                        onclick="DupPro.UI.SetArchiveOptionStates();" 
                        type="radio" 
                        name="archive_build_mode" 
                        id="archive_build_mode2"  
                        value="<?php echo DUP_PRO_Archive_Build_Mode::ZipArchive; ?>" 
                        <?php checked($global->getBuildMode() == DUP_PRO_Archive_Build_Mode::ZipArchive); ?> 
                        <?php disabled(!$global->isBuildModeAvaiable(DUP_PRO_Archive_Build_Mode::ZipArchive)) ?>
                    >
                    <label for="archive_build_mode2"><?php DUP_PRO_U::esc_html_e("ZipArchive"); ?></label>
                </div>

                <br style="clear:both"/>

                <!-- DUPARCHIVE -->
                <div class="engine-sub-opts" id="engine-details-3" style="display:none">
                    <?php
                    DUP_PRO_U::esc_html_e('This option creates a custom Duplicator Archive Format (.daf) archive file.');
                    echo '<br/>  ';
                    DUP_PRO_U::esc_html_e('This option is fully multi-threaded and recommended for large sites or throttled servers.');
                    echo '<br/>  ';
                    printf(
                        '%s <a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL
                        . 'how-to-work-with-daf-files-and-the-duparchive-extraction-tool" target="_blank">%s</a> ',
                        DUP_PRO_U::__('For details on how to use and manually extract the DAF format please see the '),
                        DUP_PRO_U::__('online documentation.')
                    );
                    ?>
                </div>

                <!-- SHELL EXEC  -->
                <div class="engine-sub-opts" id="engine-details-1" style="display:none">
                    <?php SettingsPageController::getShellZipMessage($isShellZipAvailable); ?>
                </div>

                <!-- ZIP ARCHIVE -->
                <div class="engine-sub-opts" id="engine-details-2" style="display:none;">
                    <label><?php DUP_PRO_U::esc_html_e("Process Mode"); ?></label>
                    <select  name="ziparchive_mode" id="ziparchive_mode"  onchange="DupPro.UI.setZipArchiveMode();">
                        <option <?php selected($global->ziparchive_mode, DUP_PRO_ZipArchive_Mode::Multithreaded); ?> 
                            value="<?php echo DUP_PRO_ZipArchive_Mode::Multithreaded ?>">
                            <?php DUP_PRO_U::esc_html_e("Multi-Threaded"); ?>
                        </option>
                        <option <?php selected($global->ziparchive_mode == DUP_PRO_ZipArchive_Mode::SingleThread); ?> 
                            value="<?php echo DUP_PRO_ZipArchive_Mode::SingleThread ?>">
                            <?php DUP_PRO_U::esc_html_e("Single-Threaded"); ?>
                        </option>
                    </select>&nbsp;
                    <i style="margin-right:7px;" class="fas fa-question-circle fa-sm"
                       data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("PHP ZipArchive Mode"); ?>"
                       data-tooltip="<?php
                        DUP_PRO_U::esc_attr_e(
                            'Single-Threaded mode attempts to create the entire archive in one request.  Multi-Threaded mode allows the archive '
                            . 'to be chunked over multiple requests. ' .
                            ' Multi-Threaded mode is typically slower but much more reliable especially for larger sites.'
                        );
                        ?>"></i>

                    <div id="dpro-ziparchive-mode-st">
                        <input type="checkbox" id="ziparchive_validation" name="ziparchive_validation" 
                            <?php checked($global->ziparchive_validation); ?>
                        >
                        <label for="ziparchive_validation">Enable file validation</label>
                    </div>

                    <div id="dpro-ziparchive-mode-mt">
                        <label><?php DUP_PRO_U::esc_html_e("Buffer Size"); ?></label>
                        <input style="width:84px;" maxlength="4"
                               data-parsley-required data-parsley-errors-container="#ziparchive_chunk_size_error_container" 
                               data-parsley-min="5" data-parsley-type="number"
                               type="text" name="ziparchive_chunk_size_in_mb" id="ziparchive_chunk_size_in_mb" 
                               value="<?php echo $global->ziparchive_chunk_size_in_mb; ?>" 
                        >
                               <?php DUP_PRO_U::esc_html_e('MB'); ?>
                        <?php
                            $toolTipContent = 'Buffer size only applies to multi-threaded requests and indicates how large an archive ' .
                                'will get before a close is registered. ' .
                                'Higher values are faster but can be more unstable based on the hosts max_execution time.';
                        ?>
                        <i style="margin-right:7px" class="fas fa-question-circle fa-sm"
                           data-tooltip-title="<?php DUP_PRO_U::esc_attr_e("PHP ZipArchive Buffer"); ?>"
                           data-tooltip="<?php
                            DUP_PRO_U::esc_attr_e($toolTipContent);
                            ?>"
                        >
                        </i>
                        <div id="ziparchive_chunk_size_error_container" class="duplicator-error-container"></div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- ===============================
    PROCESSING -->
    <h3 class="title"><?php DUP_PRO_U::esc_html_e("Processing") ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Server Throttle"); ?></label></th>
            <td>
                <input type="radio" name="server_load_reduction" id="server_load_reduction_off" value="<?php echo DUP_PRO_Email_Build_Mode::No_Emails; ?>" 
                    <?php checked($global->server_load_reduction, DUP_PRO_Server_Load_Reduction::None); ?> />
                <label for="server_load_reduction_off"><?php DUP_PRO_U::esc_html_e("Off"); ?></label> &nbsp;
                <input type="radio" name="server_load_reduction" id="server_load_reduction_low" value="<?php echo DUP_PRO_Server_Load_Reduction::A_Bit; ?>" 
                    <?php checked($global->server_load_reduction, DUP_PRO_Server_Load_Reduction::A_Bit); ?> >
                <label for="server_load_reduction_low"><?php DUP_PRO_U::esc_html_e("Low"); ?></label> &nbsp;
                <input type="radio" name="server_load_reduction" id="server_load_reduction_medium" value="<?php echo DUP_PRO_Server_Load_Reduction::More; ?>" 
                    <?php checked($global->server_load_reduction, DUP_PRO_Server_Load_Reduction::More); ?> 
                >
                <label for="server_load_reduction_medium"><?php DUP_PRO_U::esc_html_e("Medium"); ?></label> &nbsp;
                <input 
                    type="radio" name="server_load_reduction" id="server_load_reduction_high" value="<?php echo DUP_PRO_Server_Load_Reduction::A_Lot ?>" 
                    <?php checked($global->server_load_reduction, DUP_PRO_Server_Load_Reduction::A_Lot); ?> 
                >
                <label for="server_load_reduction_high"><?php DUP_PRO_U::esc_html_e("High"); ?></label> &nbsp;
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e("Throttle to prevent resource complaints on budget hosts. The higher the value the slower the backup."); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Max Build Time"); ?></label></th>
            <td>
                <input 
                    style="float:left;display:block;margin-right:6px;" 
                    data-parsley-required data-parsley-errors-container="#max_package_runtime_in_min_error_container" 
                    data-parsley-min="0" 
                    data-parsley-type="number" 
                    class="dup-narrow-input" 
                    type="text" 
                    name="max_package_runtime_in_min" 
                    id="max_package_runtime_in_min" 
                    value="<?php echo $global->max_package_runtime_in_min; ?>" 
                >
                <p style="margin-left:4px;"><?php DUP_PRO_U::esc_html_e('Minutes'); ?></p>
                <div id="max_package_runtime_in_min_error_container" class="duplicator-error-container"></div>
                <p class="description">  
                    <?php DUP_PRO_U::esc_html_e('Max build and storage time until package is auto-cancelled. Set to 0 for no limit.'); ?>  
                </p>
            </td>
        </tr>
    </table>

    <!-- ===============================
    INSTALLER SETTINGS -->
    <h3 id="duplicator-pro-installer-settings" class="title"><?php DUP_PRO_U::esc_html_e("Installer Settings"); ?></h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Name"); ?></label></th>
            <td id="installer-name-mode-option" >
                <b><?php DUP_PRO_U::esc_html_e("Default 'Save as' name:"); ?></b> <br/>
                <label>
                    <i class='fas fa-lock lock-info fa-fw'></i>
                    <input type="radio" name="installer_name_mode"
                           value="<?php echo DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_WITH_HASH; ?>"
                           <?php checked($installerNameMode === DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_WITH_HASH); ?> />
                    [name]_[hash]_[date]_installer.php <i>(<?php DUP_PRO_U::esc_html_e("recommended"); ?>)</i>
                </label><br>
                <label>
                    <i class='fas fa-lock-open lock-info fa-fw'></i>
                    <input type="radio" name="installer_name_mode"
                           value="<?php echo DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_SIMPLE; ?>"
                           <?php checked($installerNameMode === DUP_PRO_Global_Entity::INSTALLER_NAME_MODE_SIMPLE); ?> />
                           <?php echo DUP_PRO_Installer::DEFAULT_INSTALLER_FILE_NAME_WITHOUT_HASH; ?>
                </label>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e("To understand the importance and usage of the installer name, please") ?>
                    <a href="javascript:void(0)" onclick="jQuery('#dup-pro-inst-mode-details').toggle()">
                        <?php DUP_PRO_U::esc_html_e("read this section") ?> 
                    </a>.
                </p>
                <div id="dup-pro-inst-mode-details">
                    <p>
                        <i>
                            <?php DUP_PRO_U::esc_html_e(
                                'Using the full hashed format provides a higher level of ' .
                                'security by helping to prevent the discovery of the installer file.'
                            ); ?>
                        </i> <br/>
                        <b><?php DUP_PRO_U::esc_html_e('Hashed example'); ?>:</b>  my-name_64fc6df76c17f2023225_19990101010101_installer.php
                    </p>
                    <p>
                        <?php
                        DUP_PRO_U::esc_html_e(
                            'The Installer \'Name\' setting specifies the name of the installer used at download-time. ' .
                            'It\'s recommended you choose the hashed name to better protect the installer file.  '
                            . 'Independent of the value of this setting, you can always change the name in the \'Save as\'' .
                            ' file dialog at download-time. If you choose to use a custom name, use a filename that is '
                            . 'known only to you. Installer filenames	must end in \'.php\'.'
                        );
                        ?>
                    </p>
                    <p>
                        <?php
                        DUP_PRO_U::esc_html_e('It\'s important not to leave the installer files on the destination server longer than necessary.  '
                            . 'After installing the migrated or restored site, just logon as a WordPress administrator and follow ' .
                            'the prompts to have the plugin remove the files.  '
                            . 'Alternatively, you can remove them manually.');
                        ?>
                    </p>
                    <p>
                        <i class="fas fa-info-circle"></i>
                        <?php
                        DUP_PRO_U::esc_html_e(
                            'Tip: Each row on the packages screen includes a copy button that copies the installer name to the clipboard. ' .
                            'After clicking this button, paste the installer '  .
                            'name into the URL you\'re using to install the destination site. This feature is handy when using the hashed installer name.'
                        );
                        ?>
                    </p>
                </div>
            </td>
        </tr>
    </table>



    <!-- ===============================
    Installer Cleanup -->
    <h3 class="title"><?php DUP_PRO_U::esc_html_e("Installer Cleanup") ?> </h3>
    <hr size="1" />
    <table class="form-table">
        <tr>
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Mode"); ?></label></th>
            <td>
                <input 
                    type="radio" 
                    name="cleanup_mode" 
                    id="cleanup_mode_Cleanup_Off" 
                    value="<?php echo DUP_PRO_Global_Entity::CLEANUP_MODE_OFF; ?>" 
                    <?php checked($global->cleanup_mode, DUP_PRO_Global_Entity::CLEANUP_MODE_OFF); ?> 
                >
                <label for="cleanup_mode_Cleanup_Off"><?php DUP_PRO_U::esc_html_e("Off"); ?></label> &nbsp;
                <input 
                    type="radio" 
                    name="cleanup_mode" 
                    id="cleanup_mode_Email_Notice" 
                    value="<?php echo DUP_PRO_Global_Entity::CLEANUP_MODE_MAIL; ?>" 
                    <?php checked($global->cleanup_mode, DUP_PRO_Global_Entity::CLEANUP_MODE_MAIL); ?> 
                >
                <label for="cleanup_mode_Email_Notice"><?php DUP_PRO_U::esc_html_e("Email Notice"); ?></label> &nbsp;
                <input 
                    type="radio" 
                    name="cleanup_mode" 
                    id="cleanup_mode_Auto_Cleanup" 
                    value="<?php echo DUP_PRO_Global_Entity::CLEANUP_MODE_AUTO; ?>" 
                    <?php checked($global->cleanup_mode, DUP_PRO_Global_Entity::CLEANUP_MODE_AUTO); ?>
                >
                <label for="cleanup_mode_Auto_Cleanup"><?php DUP_PRO_U::esc_html_e("Auto Cleanup"); ?></label> &nbsp;
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e("Email Notice: An email will be sent daily until the installer files are removed."); ?>
                </p>
                <p class="description">
                    <?php DUP_PRO_U::esc_html_e("Auto Cleanup: Installer files will be cleaned up automatically based on setting below."); ?>
                </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Auto Cleanup"); ?></label></th>
            <td>
                <input 
                    data-parsley-required
                    data-parsley-errors-container="#auto_cleanup_hours_error_container"
                    data-parsley-min="1"
                    data-parsley-type="number"
                    class="dup-narrow-input"
                    type="text" 
                    name="auto_cleanup_hours" id="auto_cleanup_hours"
                    value="<?php echo $global->auto_cleanup_hours; ?>"
                    size="7"/>
                <?php DUP_PRO_U::esc_html_e('Hours'); ?>
                <div id="auto_cleanup_hours_error_container" class="duplicator-error-container"></div>
                <p class="description">  <?php DUP_PRO_U::esc_html_e('Auto cleanup will run every N hours based on value above.'); ?>  </p>
            </td>
        </tr>
        <tr valign="top">
            <th scope="row"><label><?php DUP_PRO_U::esc_html_e("Email Address"); ?></label></th>
            <td>
                <input
                    data-parsley-errors-container="#cleanup_email_error_container"
                    data-parsley-type="email"
                    type="email"
                    name="cleanup_email" 
                    id="cleanup_email"
                    value="<?php echo esc_attr($global->cleanup_email); ?>"
                    size="75" />
                <p class="description"><?php DUP_PRO_U::esc_html_e('WordPress administration email address will be used if empty.'); ?></p>
                <div id="cleanup_email_error_container" class="duplicator-error-container"></div>
            </td>
        </tr>
    </table>

    <p class="submit dpro-save-submit">
        <input 
            type="submit" name="submit" id="submit" class="button-primary" 
            value="<?php DUP_PRO_U::esc_attr_e('Save Basic Package Settings') ?>" style="display: inline-block;" 
        >
    </p>
</form>

<script>
    jQuery(document).ready(function ($)
    {

        DupPro.UI.SetDBEngineMode = function ()
        {
            var isMysqlDump = $('#package_mysqldump').is(':checked');
            var isPHPMode = $('#package_phpdump').is(':checked');
            var isPHPChunkMode = $('#package_phpchunkingdump').is(':checked');

            $('#dbengine-details-1, #dbengine-details-2').hide();
            switch (true) {
                case isMysqlDump :
                    $('#dbengine-details-1').show();
                    break;
                case isPHPMode  :
                case isPHPChunkMode :
                    $('#dbengine-details-2').show();
                    break;
            }
        }

        DupPro.UI.setZipArchiveMode = function ()
        {
            $('#dpro-ziparchive-mode-st, #dpro-ziparchive-mode-mt').hide();
            if ($('#ziparchive_mode').val() == 0) {
                $('#dpro-ziparchive-mode-mt').show();
            } else {
                $('#dpro-ziparchive-mode-st').show();
            }
        }

        DupPro.UI.SetArchiveOptionStates = function ()
        {
            var php70 = <?php echo (version_compare(PHP_VERSION, '7', '>=') ? 'true' : 'false'); ?>;
            var isShellZipSelected = $('#archive_build_mode1').is(':checked');
            var isZipArchiveSelected = $('#archive_build_mode2').is(':checked');
            var isDupArchiveSelected = $('#archive_build_mode3').is(':checked');

            if (isShellZipSelected || isDupArchiveSelected) {
                $("[name='archive_compression']").prop('disabled', false);
                $("[name='ziparchive_mode']").prop('disabled', true);
            } else {
                $("[name='ziparchive_mode']").prop('disabled', false);
                if (php70) {
                    $("[name='archive_compression']").prop('disabled', false);
                } else {
                    $('#archive_compression_on').prop('checked', true);
                    $("[name='archive_compression']").prop('disabled', true);
                }
            }

            $('#engine-details-1, #engine-details-2, #engine-details-3').hide();
            switch (true) {
                case isShellZipSelected       :
                    $('#engine-details-1').show();
                    break;
                case isZipArchiveSelected   :
                    $('#engine-details-2').show();
                    break;
                case isDupArchiveSelected   :
                    $('#engine-details-3').show();
                    break;
            }
            DupPro.UI.setZipArchiveMode();
        }

        //INIT
        DupPro.UI.SetArchiveOptionStates();
        DupPro.UI.SetDBEngineMode();

        DupPro.UI.cleanupModeRadioSwitched = function() {
            if ($('#cleanup_mode_Cleanup_Off').is(":checked")){
                $('#auto_cleanup_hours').attr('readonly','readonly');
                $('#cleanup_email').attr('readonly','readonly');
            } else if ($('#cleanup_mode_Email_Notice').is(":checked")) {
                $('#auto_cleanup_hours').attr('readonly','readonly');
                $("#cleanup_email").removeAttr('readonly');
            } else if ($('#cleanup_mode_Auto_Cleanup').is(":checked")) {
                $("#auto_cleanup_hours").removeAttr('readonly');
                $("#cleanup_email").removeAttr('readonly');
            }
        }
        
        $('input[type=radio][name=cleanup_mode]').change(function () {
            DupPro.UI.cleanupModeRadioSwitched();
        });
        // We must call this also once in the beginning, after UI is loaded
        DupPro.UI.cleanupModeRadioSwitched();

    });
</script>
