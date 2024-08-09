<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use Duplicator\Core\Upgrade\UpgradeFunctions;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapUtil;

class DefaultLocalStorage extends LocalStorage
{
    // Used on old packages before 4.5.13
    const OLD_VIRTUAL_STORAGE_ID = -2;
    /**
     * Class constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->name  = __('Default', "duplicator-pro");
        $this->notes = __('The default location for storage on this server.', "duplicator-pro");
    }

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultCoinfig()
    {
        $config = parent::getDefaultCoinfig();
        $config = array_merge(
            $config,
            [
                'storage_folder'    => DUPLICATOR_PRO_SSDIR_PATH,
                'max_packages'      => 20,
                'purge_packages'    => false,
                'filter_protection' => true,
            ]
        );
        return $config;
    }

    /**
     * Wakeup method
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();
        // Force storage folder value, it can be changed by user and must be updated after migration
        $this->config['storage_folder'] = DUPLICATOR_PRO_SSDIR_PATH;
    }

    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return -2;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Default Local', 'duplicator-pro');
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '<i class="far fa-hdd fa-fw"></i>';
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 0;
    }

    /**
     * Is editable
     *
     * @return bool
     */
    public static function isDefault()
    {
        return true;
    }

    /**
     * Is type selectable
     *
     * @return bool
     */
    public static function isSelectable()
    {
        return false;
    }

    /**
     * Render form config fields
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function renderConfigFields($echo = true)
    {
        return TplMng::getInstance()->render(
            'admin_pages/storages/configs/local_default',
            [
                'storage'       => $this,
                'maxPackages'   => $this->config['max_packages'],
                'purgePackages' => $this->config['purge_packages'],
                'storageFolder' => $this->config['storage_folder'],
            ],
            $echo
        );
    }

    /**
     * Update data from http request, this method don't save data, just update object properties
     *
     * @param string $message Message
     *
     * @return bool True if success and all data is valid, false otherwise
     */
    public function updateFromHttpRequest(&$message = '')
    {
        // Don't call parent, default properties are not editable
        $this->notes                    = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
        $this->config['max_packages']   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'max_default_store_files', 20);
        $this->config['purge_packages'] = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'purge_default_package_record', false);

        $message = __('Default local storage updated.', 'duplicator-pro');
        return true;
    }

    /**
     * Copy from default
     *
     * @param DUP_PRO_Package             $package     the package
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    public function copyFromDefault(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info)
    {
        DUP_PRO_Log::infoTrace("SUCCESS: package is in default location: " . DUPLICATOR_PRO_SSDIR_PATH);
        // It's the default local storage location so do nothing - it's already there
        $upload_info->copied_archive   = true;
        $upload_info->copied_installer = true;
        $package->update();
    }

    /**
     * Delete current entity
     *
     * @return bool True on success, or false on error.
     */
    public function delete()
    {
        // Don't delete default storage
        return false;
    }

    /**
     * Update properties from Global entity values.
     * Used to update properties from global entity after update from old version
     *
     * @param false|string $currentVersion current Duplicator version
     *
     * @return void
     */
    public function updateFromGlobal($currentVersion)
    {
        if ($currentVersion == false || version_compare($currentVersion, UpgradeFunctions::LAST_VERSION_OLD_STORAGE_MONOLITHIC, '>')) {
            return;
        }

        $global                         = DUP_PRO_Global_Entity::getInstance();
        $this->config['max_packages']   = $global->max_default_store_files;
        $this->config['purge_packages'] = $global->purge_default_package_record;
    }

    /**
     * Creates the snapshot directory if it doesn't already exists
     *
     * @param bool $skipIfExists If true it will skip creating the directory if it already exists
     *
     * @return bool True if success, false otherwise
     */
    public function initStorageDirectory($skipIfExists = false)
    {
        $exists = is_dir($this->config['storage_folder']);

        if (parent::initStorageDirectory($skipIfExists) === false) {
            return false;
        }

        if ($skipIfExists && $exists) {
            return true;
        }

        $path_ssdir_tmp        = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP);
        $path_ssdir_tmp_import = SnapIO::safePath(DUPLICATOR_PRO_SSDIR_PATH_TMP_IMPORT);
        $path_plugin           = SnapIO::safePath(DUPLICATOR____PATH);
        $path_import           = SnapIO::safePath(DUPLICATOR_PRO_PATH_IMPORTS);

        //snapshot tmp directory
        wp_mkdir_p($path_ssdir_tmp);
        SnapIO::chmod($path_ssdir_tmp, 'u+rwx');

        wp_mkdir_p($path_ssdir_tmp_import);
        SnapIO::chmod($path_ssdir_tmp_import, 'u+rwx');

        wp_mkdir_p($path_import);
        SnapIO::chmod($path_import, 'u+rwx');

        //plugins dir/files
        SnapIO::chmod($path_plugin . 'files', 'u+rwx');

        return true;
    }
}
