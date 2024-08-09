<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;

class StoragesUtil
{
    /**
     * Init Default storage.
     * Create default storage if not exists.
     *
     * @return bool true if success false otherwise
     */
    public static function initDefaultStorage()
    {
        $storage = self::getDefaultStorage();
        if ($storage->save() === false) {
            DUP_PRO_Log::trace("Error saving default storage");
            return false;
        }
        if ($storage->initStorageDirectory() === false) {
            DUP_PRO_Log::trace("Error init default storage directory");
            return false;
        }
        return true;
    }

    /**
     * Get default local storage, if don't exists create it
     *
     * @return DefaultLocalStorage
     */
    public static function getDefaultStorage()
    {
        static $defaultStorage = null;

        if ($defaultStorage === null) {
            if (($storages = AbstractStorageEntity::getAll()) !== false) {
                foreach ($storages as $storage) {
                    if ($storage->getSType() !== DefaultLocalStorage::getSType()) {
                        continue;
                    }
                    /** @var DefaultLocalStorage */
                    $defaultStorage = $storage;
                    break;
                }
            }

            if (is_null($defaultStorage)) {
                $defaultStorage = new DefaultLocalStorage();
                $defaultStorage->save();
            }
        }

        return $defaultStorage;
    }

    /**
     * Get default local storage id
     *
     * @return int
     */
    public static function getDefaultStorageId()
    {
        return self::getDefaultStorage()->getId();
    }

    /**
     * Get default new storage
     *
     * @return LocalStorage
     */
    public static function getDefaultNewStorage()
    {
        return new LocalStorage();
    }

    /**
     * Purge old S3 multipart uploads
     *
     * @return void
     */
    public static function purgeOldS3MultipartUploads()
    {
        if (($storages = AbstractStorageEntity::getAll()) == false) {
            return;
        }

        foreach ($storages as $storage) {
            if (!$storage instanceof AmazonS3Storage) {
                continue;
            }
            $storage->purgeMultipartUpload();
        }
    }

    /**
     * Process the package
     *
     * @param DUP_PRO_Package             $package     The package to process
     * @param DUP_PRO_Package_Upload_Info $upload_info The upload info
     *
     * @return void
     */
    public static function processPackage(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info)
    {
        $package->active_storage_id = $upload_info->getStorageId();
        if (($storage = AbstractStorageEntity::getById($package->active_storage_id)) === false) {
            DUP_PRO_Log::error("Storage id " . $package->active_storage_id . "not found for package $package->ID");
            return;
        }
        DUP_PRO_Log::infoTrace('** ' . strtoupper($storage->getStypeName()) . " [Name: {$storage->getName()}] [ID: $package->active_storage_id] **");
        $storage->copyFromDefault($package, $upload_info);
    }

    /**
     * Sort storages with default first other by id
     *
     * @param AbstractStorageEntity $a Storage a
     * @param AbstractStorageEntity $b Storage b
     *
     * @return int
     */
    public static function sortDefaultFirst(AbstractStorageEntity $a, AbstractStorageEntity $b)
    {
        if ($a->getId() == $b->getId()) {
            return 0;
        }
        if ($a->getSType() == DefaultLocalStorage::getSType()) {
            return -1;
        }
        if ($b->getSType() == DefaultLocalStorage::getSType()) {
            return 1;
        }
        return ($a->getId() < $b->getId()) ? -1 : 1;
    }

    /**
     * Sort storages by priority, type and id
     *
     * @param AbstractStorageEntity $a Storage a
     * @param AbstractStorageEntity $b Storage b
     *
     * @return int
     */
    public static function sortByPriority(AbstractStorageEntity $a, AbstractStorageEntity $b)
    {
        $aPriority = $a->getPriority();
        $bPriority = $b->getPriority();

        if ($aPriority == $bPriority) {
            if ($a->getSType() == $b->getSType()) {
                if ($a->getId() == $b->getId()) {
                    return 0;
                } else {
                    return ($a->getId() < $b->getId()) ? -1 : 1;
                }
            } else {
                return ($a->getSType() < $b->getSType()) ? -1 : 1;
            }
        }

        return ($aPriority < $bPriority) ? -1 : 1;
    }

    /**
     * Register all storages
     *
     * @return void
     */
    public static function registerTypes()
    {
        UnknownStorage::registerType();
        LocalStorage::registerType();
        DefaultLocalStorage::registerType();
        FTPStorage::registerType();
        SFTPStorage::registerType();
        DropboxStorage::registerType();
        OneDriveStorage::registerType();
        GDriveStorage::registerType();
        AmazonS3Storage::registerType();
        GoogleCloudStorage::registerType();
        CloudflareStorage::registerType();
        BackblazeStorage::registerType();
        WasabiStorage::registerType();
        DreamStorage::registerType();
        DigitalOceanStorage::registerType();
        VultrStorage::registerType();
        AmazonS3CompatibleStorage::registerType();
        /** @todo move types on hook action */
        do_action('duplicator_pro_register_storage_types');
    }
}
