<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use DUP_PRO_Environment_Checker;
use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package_Template_Entity;
use DUP_PRO_Secure_Global_Entity;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Core\CapMng;
use Duplicator\Core\MigrationMng;
use Duplicator\Core\Models\AbstractEntity;
use Duplicator\Models\Storages\StoragesUtil;

/**
 * Upgrade logic of plugin resides here
 *
 * DUP_PRO_Plugin_Upgrade
 */
class UpgradePlugin
{
    const DUP_VERSION_OPT_KEY          = 'duplicator_pro_plugin_version';
    const DUP_NEW_INSTALL_INFO_OPT_KEY = 'duplicator_pro_install_info';

    const DUP_PRO_INSTALL_TIME = 'duplicator_pro_install_time';

    /**
     * Perform activation action.
     *
     * @return void
     */
    public static function onActivationAction()
    {
        // Init capabilities
        CapMng::getInstance();

        self::setInstallTime();

        if (($oldDupVersion = get_option(self::DUP_VERSION_OPT_KEY, false)) === false) {
            self::newInstallation();
        } else {
            self::updateInstallation($oldDupVersion);
        }

        //Rename installer files if exists
        MigrationMng::renameInstallersPhpFiles();

        do_action('duplicator_pro_after_activation');
    }

    /**
     * Set install time if not set.
     *
     * @return void
     */
    private static function setInstallTime()
    {
        if (get_option(self::DUP_PRO_INSTALL_TIME, false) !== false) {
            return;
        }

        update_option(self::DUP_PRO_INSTALL_TIME, time());
    }

    /**
     * Get install time.
     *
     * @return int|false
     */
    public static function getInstallTime()
    {
        self::setInstallTime();

        return get_option(self::DUP_PRO_INSTALL_TIME);
    }

    /**
     * Perform new installation.
     *
     * @return void
     */
    protected static function newInstallation()
    {
        self::environmentChecks();

        self::updateDatabase();

        UpgradeFunctions::performUpgrade(false, DUPLICATOR_PRO_VERSION);

        // WordPress Options Hooks
        self::updateOptionVersion();
        self::setNewInstallInfo();
    }

    /**
     * Set install info.
     *
     * @return void
     */
    public static function setNewInstallInfo()
    {
        $install_info = [
            'version' => DUPLICATOR_PRO_VERSION,
            'time'    => time(),
        ];
        delete_option(self::DUP_NEW_INSTALL_INFO_OPT_KEY);
        update_option(self::DUP_NEW_INSTALL_INFO_OPT_KEY, $install_info, false);
    }

    /**
     * Get install info.
     *
     * @return false|array{version:string,time:int}
     */
    public static function getNewInstallInfo()
    {
        return get_option(self::DUP_NEW_INSTALL_INFO_OPT_KEY, false);
    }

    /**
     * Perform update installation.
     *
     * @param string $oldVersion Old version.
     *
     * @return void
     */
    protected static function updateInstallation($oldVersion)
    {
        self::environmentChecks();

        self::updateDatabase();

        UpgradeFunctions::performUpgrade($oldVersion, DUPLICATOR_PRO_VERSION);

        //WordPress Options Hooks
        self::updateOptionVersion();
    }

    /**
     * Update option version.
     *
     * @return void
     */
    protected static function updateOptionVersion()
    {
        // WordPress Options Hooks
        if (update_option(self::DUP_VERSION_OPT_KEY, DUPLICATOR_PRO_VERSION, true) === false) {
            DUP_PRO_Log::trace("Couldn't update duplicator_pro_plugin_version so deleting it.");

            delete_option(self::DUP_VERSION_OPT_KEY);

            if (update_option(self::DUP_VERSION_OPT_KEY, DUPLICATOR_PRO_VERSION, true) === false) {
                DUP_PRO_Log::trace("Still couldn\'t update the option!");
            } else { // @phpstan-ignore-line
                DUP_PRO_Log::trace("Option updated.");
            }
        }
    }

    /**
     * Update database.
     *
     * @return void
     */
    public static function updateDatabase()
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $table_name      = $wpdb->base_prefix . "duplicator_pro_packages";

        //PRIMARY KEY must have 2 spaces before for dbDelta to work
        $sql = "CREATE TABLE `{$table_name}` (
			   id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			   name VARCHAR(250) NOT NULL,
			   hash VARCHAR(50) NOT NULL,
			   status INT(11) NOT NULL,
			   created DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			   owner VARCHAR(60) NOT NULL,
			   package LONGTEXT NOT NULL,
			   PRIMARY KEY  (id),
			   KEY hash (hash)) 
               $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        SnapWP::dbDelta($sql);

        AbstractEntity::initTable();
        DUP_PRO_Global_Entity::getInstance()->save();
        DUP_PRO_Secure_Global_Entity::getInstance()->save();
        StoragesUtil::initDefaultStorage();
        DUP_PRO_Package_Template_Entity::create_default();
        DUP_PRO_Package_Template_Entity::create_manual();
    }

    /**
     * Environment checks.
     *
     * @return void
     */
    protected static function environmentChecks()
    {
        require_once(DUPLICATOR____PATH . '/classes/environment/class.environment.checker.php');

        $env_checker = new DUP_PRO_Environment_Checker();

        $status = $env_checker->check();

        $messages = $env_checker->getHelperMessages();

        if (!$status) {
            if (!empty($messages)) {
                $msg_str = '';
                foreach ($messages as $id => $msgs) {
                    foreach ($msgs as $key => $msg) {
                        $msg_str .= '<br/>' . $msg;
                    }
                }
                die($msg_str);
            }
        }
    }
}
