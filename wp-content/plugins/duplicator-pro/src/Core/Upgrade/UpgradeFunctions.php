<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Upgrade;

use DUP_PRO_Global_Entity;
use DUP_PRO_Package;
use DUP_PRO_Package_Template_Entity;
use DUP_PRO_PackageStatus;
use DUP_PRO_Schedule_Entity;
use DUP_PRO_Secure_Global_Entity;
use DUP_PRO_U;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\DefaultLocalStorage;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Utils\Email\EmailSummary;

/**
 * Utility class managing when the plugin is updated
 *
 * DUP_PRO_Upgrade_U
 */
class UpgradeFunctions
{
    const LAST_VERSION_PACKAGE_DBONLY_FLAG    = '4.5.11.2';
    const LAST_VERSION_OLD_STORAGE_MONOLITHIC = '4.5.12.1';
    const LAST_VERSION_NO_EMAIL_SUMMARY       = '4.5.12.1';

    /**
     * This function is executed when the plugin is activated and
     * every time the version saved in the wp_options is different from the plugin version both in upgrade and downgrade.
     *
     * @param false|string $currentVersion current Duplicator version, false if is first installation
     * @param string       $newVersion     new Duplicator Version
     *
     * @return void
     */
    public static function performUpgrade($currentVersion, $newVersion)
    {
        // Setup All Directories
        self::storeDupSecureKey($currentVersion);
        self::updateStorages($currentVersion);
        self::updateTemplates($currentVersion);
        self::updatePackageComponents($currentVersion);
        self::initEmailSummaryRecipients($currentVersion);
        self::moveDataToSecureGlobal();

        License::clearVersionCache();

        // Schedule custom cron event for cleanup of installer files if it should be scheduled
        DUP_PRO_Global_Entity::cleanupScheduleSetup();
    }

    /**
     * Upate endpoints
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function updateStorages($currentVersion)
    {
        if ($currentVersion == false || version_compare($currentVersion, self::LAST_VERSION_OLD_STORAGE_MONOLITHIC, '>')) {
            return;
        }

        if (($storages = AbstractStorageEntity::getAll()) == false) {
            // Don't generare error, just return
            return;
        }

        foreach ($storages as $storage) {
            // Update storage endpoint
            $storage->save();
        }

        // Update default storage
        $defaultStorage = StoragesUtil::getDefaultStorage();
        $defaultStorage->updateFromGlobal($currentVersion);
        $defaultStorage->save();

        $oldDefaultId = DefaultLocalStorage::OLD_VIRTUAL_STORAGE_ID;
        $newDefaultId = StoragesUtil::getDefaultStorageId();

        $global     = DUP_PRO_Global_Entity::getInstance();
        $storageIds = $global->getManualModeStorageIds();

        if (in_array($oldDefaultId, $storageIds)) {
            $storageIds   = array_diff($storageIds, [$oldDefaultId]);
            $storageIds[] = $newDefaultId;
            $storageIds   = array_values($storageIds);
            $global->setManualModeStorageIds($storageIds);
            $global->save();
        }

        DUP_PRO_Schedule_Entity::listCallback(function (DUP_PRO_Schedule_Entity $schedule) use ($oldDefaultId, $newDefaultId) {
            if (!in_array($oldDefaultId, $schedule->storage_ids)) {
                return;
            }
            $schedule->storage_ids   = array_diff($schedule->storage_ids, [$oldDefaultId]);
            $schedule->storage_ids[] = $newDefaultId;
            $schedule->storage_ids   = array_values($schedule->storage_ids);
            $schedule->save();
        });

        DUP_PRO_Package::by_status_callback(
            function (DUP_PRO_Package $package) use ($oldDefaultId, $newDefaultId) {
                $save = false;

                if ($package->active_storage_id == $oldDefaultId) {
                    $package->active_storage_id = $newDefaultId;
                    $save                       = true;
                }

                foreach ($package->upload_infos as $key => $info) {
                    if ($info->getStorageId() != $oldDefaultId) {
                        continue;
                    }
                    $info->setStorageId($newDefaultId);
                    $save = true;
                }
                if ($save) {
                    $package->save();
                }
            },
            array(
                array(
                    'op'     => '>=',
                    'status' => DUP_PRO_PackageStatus::COMPLETE,
                ),
            )
        );
    }

    /**
     * Set default recipients for email summary
     *
     * @param false|string $currentVersion current version of plugin
     *
     * @return void
     */
    protected static function initEmailSummaryRecipients($currentVersion)
    {
        if ($currentVersion == false || version_compare($currentVersion, self::LAST_VERSION_NO_EMAIL_SUMMARY, '>')) {
            return;
        }

        $global = DUP_PRO_Global_Entity::getInstance();
        $global->setEmailSummaryRecipients(EmailSummary::getDefaultRecipients());
        $global->save();
    }

    /**
     * Implement defaults for package components
     *
     * @param string $currentVersion current version of plugin
     *
     * @return void
     */
    protected static function updatePackageComponents($currentVersion)
    {
        if ($currentVersion == false || version_compare($currentVersion, self::LAST_VERSION_PACKAGE_DBONLY_FLAG, '>')) {
            return;
        }

        DUP_PRO_Package_Template_Entity::listCallback(function (DUP_PRO_Package_Template_Entity $template) {
            if (count($template->components) > 0) {
                return;
            }
            if ($template->archive_export_onlydb) {
                $template->components = [BuildComponents::COMP_DB];
            } else {
                $template->components = BuildComponents::COMPONENTS_DEFAULT;
            }
            $template->save();
        });

        DUP_PRO_Package::by_status_callback(function (DUP_PRO_Package $package) {
            if (count($package->components) > 0) {
                return;
            }
            if ($package->Archive->ExportOnlyDB) {
                $package->components = [BuildComponents::COMP_DB];
            } else {
                $package->components = BuildComponents::COMPONENTS_DEFAULT;
            }
            $package->save();
        });
    }

    /**
     * Upate templates
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function updateTemplates($currentVersion)
    {
        // Update templates one when coming from 4.5.2 or below
        if ($currentVersion == false || version_compare($currentVersion, '4.5.3', '>=')) {
            return;
        }

        $templates = DUP_PRO_Package_Template_Entity::getAll();
        if (!is_array($templates)) {
            return;
        }

        foreach ($templates as $template) {
            $template->save();
        }
    }

    /**
     * Save DUP SECURE KEY
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    protected static function storeDupSecureKey($currentVersion)
    {
        if ($currentVersion !== false && SnapUtil::versionCompare($currentVersion, '4.5.0', '<=', 3)) {
            CryptBlowfish::createWpConfigSecureKey(true, true);
        } else {
            CryptBlowfish::createWpConfigSecureKey(false, false);
        }
    }

    /**
     * Move data tu secure global
     *
     * @return void
     */
    protected static function moveDataToSecureGlobal()
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        if (($global->lkp !== '') || ($global->basic_auth_password !== '')) {
            error_log('setting sglobal');
            $sglobal                      = DUP_PRO_Secure_Global_Entity::getInstance();
            $sglobal->lkp                 = $global->lkp;
            $sglobal->basic_auth_password = $global->basic_auth_password;
            $global->lkp                  = '';
            $global->basic_auth_password  = '';
            $sglobal->save();
            $global->save();
        }
    }
}
