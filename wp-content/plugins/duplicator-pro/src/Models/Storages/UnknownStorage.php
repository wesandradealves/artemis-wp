<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use Duplicator\Core\Views\TplMng;

class UnknownStorage extends AbstractStorageEntity
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType()
    {
        return -1000;
    }

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon()
    {
        return '';
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 10000;
    }

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName()
    {
        return __('Unknown', 'duplicator-pro');
    }

    /**
     * Get location string
     *
     * @return string
     */
    public function getLocationString()
    {
        return __('Unknown', 'duplicator-pro');
    }

    /**
     * Get HTML location link
     *
     * @return string
     */
    public function getHtmlLocationLink()
    {
        return __('Unknown', 'duplicator-pro');
    }

    /**
     * Save entity
     *
     * @return bool True on success, or false on error.
     */
    public function save()
    {
        // Isn't possibile save unknown storage
        return false;
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    public function isValid()
    {
        return false;
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
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    public function getListQuickView($echo = true)
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Unknown storage type', 'duplicator-pro') ?></label>
        </div>
        <?php
        if ($echo) {
            ob_end_flush();
            return '';
        } else {
            return (string) ob_get_clean();
        }
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
        DUP_PRO_Log::infoTrace("Copyng to Storage " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getStypeName());
        DUP_PRO_Log::infoTrace('Do nothing sot unknown storage type');
    }

    /**
     * Purge old packages
     *
     * @return bool true if success, false otherwise
     */
    public function purgeOldPackages()
    {
        if ($this->config['max_packages'] <= 0) {
            return true;
        }

        DUP_PRO_Log::infoTrace("Attempting to purge old packages at " . $this->name . '[ID: ' . $this->id . '] type:' . $this->getSTypeName());
        DUP_PRO_Log::infoTrace('Do nothing sot unknown storage type');

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
            'admin_pages/storages/configs/unknown',
            ['storage' => $this],
            $echo
        );
    }
}
