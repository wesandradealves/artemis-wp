<?php

namespace Duplicator\Views;

use Closure;
use DUP_PRO_CTRL_Tools;
use DUP_PRO_U;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Models\RecommendedFix;
use Duplicator\Models\SystemGlobalEntity;
use Duplicator\Utils\Autoloader;
use Exception;

/**
 * Admin notices class, Used to display notices in the WordPress Admin area
 *
 * DUP_PRO_UI_Notice
 */
class AdminNotices
{
    const OPTION_KEY_INSTALLER_HASH_NOTICE          = 'duplicator_pro_inst_hash_notice';
    const OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL = 'duplicator_pro_activate_plugins_after_installation';
    const OPTION_KEY_MIGRATION_SUCCESS_NOTICE       = 'duplicator_pro_migration_success';
    const OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE  = 'duplicator_pro_s3_contents_fetch_fail';
    const QUICK_FIX_NOTICE                          = 'duplicator_pro_quick_fix_notice';
    const FAILED_SCHEDULE_NOTICE                    = 'duplicator_pro_failed_schedule_notice';
    const GEN_INFO_NOTICE                           = 0;
    const GEN_SUCCESS_NOTICE                        = 1;
    const GEN_WARNING_NOTICE                        = 2;
    const GEN_ERROR_NOTICE                          = 3;

    /**
     * init notice actions
     *
     * @return void
     */
    public static function init()
    {
        add_action('admin_init', array(__CLASS__, 'adminInit'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'unhookThirdPartyNotices'), 99999, 1);
    }

    /**
     * HOOK admin_init
     *
     * @return void
     */
    public static function adminInit()
    {
        $notices       = array();
        $notices[]     = array(
            __CLASS__,
            'migrationSuccessNotice',
        ); // BEFORE MIGRATION SUCCESS NOTICE
        $notices[]     = array(
            __CLASS__,
            's3ContentsFetchFailNotice',
        );
        $notices[]     = array(
            __CLASS__,
            'addonInitFailNotice',
        );
        $notices[]     = array(
            __CLASS__,
            'activatePluginsAfterInstall',
        );
        $system_global = SystemGlobalEntity::getInstance();
        foreach ($system_global->recommended_fixes as $fix) {
            if ($fix->recommended_fix_type === RecommendedFix::TYPE_TEXT || $fix->recommended_fix_type === RecommendedFix::TYPE_FIX) {
                $notices[] = array(
                    __CLASS__,
                    'showQuickFixNotice',
                );
            }
        }
        if ($system_global->schedule_failed) {
            $notices[] = array(
                __CLASS__,
                'showFailedSchedule',
            );
        }

        $action = is_multisite() ? 'network_admin_notices' : 'admin_notices';
        foreach ($notices as $notice) {
            add_action($action, $notice);
        }
    }

    /**
     * Addon init fail notice
     *
     * @return void
     */
    public static function addonInitFailNotice()
    {
        if (\Duplicator\Core\Addons\AddonsManager::getInstance()->isAddonsReady()) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }
        ob_start();
        ?>
        <strong>Duplicator Pro</strong>
        <hr>
        <p>
            <?php _e(
                'The plugin cannot be activated due to problems during initialization. Please reinstall the plugin deleting the current installation',
                'duplicator-pro'
            ); ?>
        </p>
        <?php
        $content = ob_get_clean();
        self::displayGeneralAdminNotice($content, self::GEN_ERROR_NOTICE, false);
    }


    /**
     * Remove all notices coming from other plugins
     *
     * @param string $hook Hook string
     *
     * @return void
     */
    public static function unhookThirdPartyNotices($hook)
    {
        if (!ControllersManager::getInstance()->isDuplicatorPage()) {
            return;
        }

        global $wp_filter;
        $filterHooks = [
            'user_admin_notices',
            'admin_notices',
            'all_admin_notices',
            'network_admin_notices',
        ];
        foreach ($filterHooks as $filterHook) {
            if (empty($wp_filter[$filterHook]->callbacks) || !is_array($wp_filter[$filterHook]->callbacks)) {
                continue;
            }

            foreach ($wp_filter[$filterHook]->callbacks as $priority => $hooks) {
                foreach ($hooks as $name => $arr) {
                    if (is_object($arr['function']) && $arr['function'] instanceof Closure) {
                        unset($wp_filter[$filterHook]->callbacks[$priority][$name]);
                        continue;
                    }
                    if (
                        !empty($arr['function'][0]) &&
                        is_object($arr['function'][0]) &&
                        strpos(get_class($arr['function'][0]), Autoloader::ROOT_NAMESPACE) === 0
                    ) {
                        continue;
                    }
                    if (!empty($name) && strpos($name, Autoloader::ROOT_NAMESPACE) !== 0) {
                        unset($wp_filter[$filterHook]->callbacks[$priority][$name]);
                    }
                }
            }
        }
    }

    /**
     * Shows notice in case we were enable to fetch contents of S3 bucket
     *
     * @throws Exception
     * @return void
     */
    public static function s3ContentsFetchFailNotice()
    {
        if (
            get_option(self::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE, false) != true ||
            !ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)
        ) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_CREATE, false)) {
            return;
        }

        $errorMessage = DUP_PRO_U::__(
            '<strong>Duplicator Pro</strong> was unable to fetch the contents of the S3 bucket to remove old packages.'
        ) . "<hr><br>" .
        sprintf(
            DUP_PRO_U::__(
                '<strong>RECOMMENDATION:</strong> Please make sure your ' .
                'S3 bucket settings are aligned with our %1sStep-by-Step guide%2s and %3sUser Bucket Policy%4s.'
            ),
            '<a target="_blank" href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
            '</a>',
            '<a target="_blank" href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'amazon-s3-step-by-step">',
            '</a>'
        );

        self::displayGeneralAdminNotice(
            $errorMessage,
            self::GEN_ERROR_NOTICE,
            true,
            array('dup-pro-quick-fix-notice'),
            array(
                'data-to-dismiss' => self::OPTION_KEY_S3_CONTENTS_FETCH_FAIL_NOTICE,
            )
        );
    }


    /**
     * Shows a display message in the wp-admin if any reserved files are found
     *
     * @return void
     */
    public static function migrationSuccessNotice()
    {
        if (get_option(self::OPTION_KEY_MIGRATION_SUCCESS_NOTICE) != true) {
            return;
        }

        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        if (!DUP_PRO_CTRL_Tools::isDiagnosticPage()) {
            TplMng::getInstance()->render('parts/migration/almost-complete');
        }
    }

    /**
     * Shows the scheduled failed alert
     *
     * @return void
     */
    public static function showFailedSchedule()
    {
        if (!CapMng::can(CapMng::CAP_SCHEDULE, false)) {
            return;
        }
        $img_url   = plugins_url('duplicator-pro/assets/img/warning.png');
        $clear_url = SnapURL::getCurrentUrl();
        $clear_url = SnapURL::appendQueryValue($clear_url, 'dup_pro_clear_schedule_failure', 1);
        $html      = "<img src='" . esc_url($img_url) . "' style='float:left; padding:0 10px 0 5px' />" .
            sprintf(DUP_PRO_U::esc_html__('%sWarning! A Duplicator Pro scheduled backup has failed.%s'), '<b>', '</b> <br/>') .
            sprintf(
                DUP_PRO_U::esc_html__(
                    'This message will continue to be displayed until a %sscheduled build%s successfully runs. '
                ),
                "<a href='admin.php?page=duplicator-pro-schedules'>",
                '</a> '
            ) .
            sprintf(DUP_PRO_U::esc_html__('To ignore and clear this message %sclick here%s'), "<a href='" . esc_url($clear_url) . "'>", '</a>.<br/>');

        self::displayGeneralAdminNotice(
            $html,
            self::GEN_ERROR_NOTICE,
            true,
            array('dup-pro-quick-fix-notice'),
            array(
                'data-to-dismiss' => self::FAILED_SCHEDULE_NOTICE,
            )
        );
    }

    /**
     * Shows the quick fix notice
     *
     * @return void
     */
    public static function showQuickFixNotice()
    {
        if (!ControllersManager::isCurrentPage(ControllersManager::PACKAGES_SUBMENU_SLUG)) {
            return;
        }
        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }

        $system_global = SystemGlobalEntity::getInstance();
        $html          = '<b class="title"><i class="fa fa-exclamation-circle fa-3 color-alert" ></i> ' .
            DUP_PRO_U::__('Duplicator Pro Errors Detected') .
            '</b></br>';
        $html         .= '<p>' . DUP_PRO_U::__('Package build error(s) were encountered.  Click the button(s) in the') .
            ' <i>' . DUP_PRO_U::__('Necessary Actions') . '</i> ' . DUP_PRO_U::__('section to reconfigure Duplicator Pro.') . "</p>";
        $html         .= '<p>';
        $html         .= '<b>' . DUP_PRO_U::__('Error(s):') . ' </b>';
        $html         .= '<ul style="list-style: disc; padding-left: 40px">';
        foreach ($system_global->recommended_fixes as $fix) {
            $html .= '<li>' . $fix->error_text . '</li>';
        }
        $html .= '</ul>';
        $html .= '</p>';
        $html .= '<b>' . DUP_PRO_U::__('Necessary Action(s):') . ' </b>' . '<br/>';
        foreach ($system_global->recommended_fixes as $fix) {
            if ($fix->recommended_fix_type == RecommendedFix::TYPE_FIX) {
                $html .= '<p id ="quick-fix-' . $fix->id . '">'
                    . '<button id="quick-fix-' . $fix->id . '-button" class="dup-pro-quick-fix button" '
                    . 'type="button" class="button button-primary" '
                    . 'data-param="' . esc_attr(json_encode($fix->parameter2)) . '" data-id="' . $fix->id . '" data-toggle="#quick-fix-' . $fix->id . '">'
                    . "<i class='fa fa-wrench' aria-hidden='true'></i>&nbsp; "
                    . DUP_PRO_U::__('Resolve This')
                    . '</button>'
                    . $fix->parameter1
                    . '</p>';
            } elseif ($fix->recommended_fix_type == RecommendedFix::TYPE_TEXT) {
                    $html .= "<p><i class='fa fa-question-circle color-alert' data-tooltip='" .
                    esc_attr($fix->error_text) . "'></i>&nbsp; " . $fix->parameter1 . "</br></p>";
            }
        }

        self::displayGeneralAdminNotice(
            $html,
            self::GEN_ERROR_NOTICE,
            true,
            array('dup-pro-quick-fix-notice'),
            array(
                'data-to-dismiss' => self::QUICK_FIX_NOTICE,
            )
        );
    }

    /**
     * display genral admin notice by printing it
     *
     * @param string              $htmlMsg       html code to be printed
     * @param integer             $noticeType    constant value of SELF::GEN_
     * @param boolean             $isDismissible whether the notice is dismissable or not. Default is true
     * @param string|string[]     $extraClasses  add more classes to the notice div
     * @param array<string,mixed> $extraAtts     assosiate array in which key as attr and value as value of the attr
     * @param bool                $blockContent  if false wraps htmlMsg in <p> otherwise allows to use block tags e.g. <div>
     *
     * @return void
     */
    public static function displayGeneralAdminNotice(
        $htmlMsg,
        $noticeType,
        $isDismissible = true,
        $extraClasses = array(),
        $extraAtts = array(),
        $blockContent = false
    ) {
        if (empty($extraClasses)) {
            $classes = array();
        } elseif (is_array($extraClasses)) {
            $classes = $extraClasses;
        } else {
            $classes = array($extraClasses);
        }

        $classes[] = 'notice';
        switch ($noticeType) {
            case self::GEN_INFO_NOTICE:
                $classes[] = 'notice-info';
                break;
            case self::GEN_SUCCESS_NOTICE:
                $classes[] = 'notice-success';
                break;
            case self::GEN_WARNING_NOTICE:
                $classes[] = 'notice-warning';
                break;
            case self::GEN_ERROR_NOTICE:
                $classes[] = 'notice-error';
                break;
            default:
                throw new Exception('Invalid Admin notice type!');
        }
        $classes[] = 'dpro-admin-notice';

        if ($isDismissible) {
            $classes[] = 'is-dismissible';
        }

        $classesStr = implode(' ', $classes);
        $attsStr    = '';
        if (!empty($extraAtts)) {
            $attsStrArr = array();
            foreach ($extraAtts as $att => $attVal) {
                $attsStrArr[] = $att . '="' . $attVal . '"';
            }
            $attsStr = implode(' ', $attsStrArr);
        }

        // $htmlMsg = self::GEN_ERROR_NOTICE == $noticeType ? "<i class='fa fa-exclamation-triangle'></i>&nbsp;" . $htmlMsg : $htmlMsg;
        $htmlMsg = !$blockContent ? "<p>" . $htmlMsg . "</p>" : $htmlMsg;
        ?>
        <div class="<?php echo esc_attr($classesStr); ?>" <?php echo $attsStr; ?>>
            <?php echo $htmlMsg; ?>
        </div>
        <?php
    }

    /**
     * Displays notice for plugins deactivated during install,
     * and removes already activated from DB
     *
     * @return void
     */
    public static function activatePluginsAfterInstall()
    {
        if (!CapMng::can(CapMng::CAP_BASIC, false)) {
            return;
        }
        $pluginsToActive = get_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL, false);
        if (!is_array($pluginsToActive) || empty($pluginsToActive)) {
            return;
        }

        $shouldBeActivated = array();
        $allPlugins        = get_plugins();
        foreach ($pluginsToActive as $index => $pluginSlug) {
            if (!isset($allPlugins[$pluginSlug])) {
                unset($pluginsToActive[$index]);
                continue;
            }

            if (is_multisite()) {
                $isActive = is_plugin_active_for_network($pluginSlug);
            } else {
                $isActive = is_plugin_active($pluginSlug);
            }

            if (!$isActive) {
                $shouldBeActivated[$pluginSlug] = $allPlugins[$pluginSlug]['Name'];
            } else {
                unset($pluginsToActive[$index]);
            }
        }

        if (empty($shouldBeActivated)) {
            delete_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL);
            return;
        } else {
            update_option(AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL, $pluginsToActive);
        }

        $html = "<img src='" . esc_url(plugins_url('duplicator-pro/assets/img/warning.png')) . "' style='float:left; padding:0 10px 0 5px' />" .
                "<div style='margin-left: 70px;'><p><b>" .
                __('Warning!', 'duplicator-pro') . "</b> " . __('Migration Almost Complete!', 'duplicator-pro') . "<br/>" .
               __('Plugin(s) listed here must be activated, Please activate them:', 'duplicator-pro') . "</p><ul>";
        foreach ($shouldBeActivated as $slug => $title) {
            if (is_multisite()) {
                $activateURL = network_admin_url('plugins.php?action=activate&plugin=' . $slug);
            } else {
                $activateURL = admin_url('plugins.php?action=activate&plugin=' . $slug);
            }
            $activateURL = wp_nonce_url($activateURL, 'activate-plugin_' . $slug);
            $anchorTitle = sprintf(__('Activate %s', 'duplicator-pro'), $title);
            $html       .= '<li><a href="' .
                DUP_PRO_U::esc_attr__($activateURL) . '" title="' . DUP_PRO_U::esc_attr__($anchorTitle) . '">' .
                DUP_PRO_U::esc_attr__($title) . '</a></li>';
        }

        $html .= "</ul></div>";
        AdminNotices::displayGeneralAdminNotice(
            $html,
            AdminNotices::GEN_WARNING_NOTICE,
            true,
            array('dpro-yellow-border'),
            array(
                'data-to-dismiss' => AdminNotices::OPTION_KEY_ACTIVATE_PLUGINS_AFTER_INSTALL,
            ),
            true
        );
    }
}
