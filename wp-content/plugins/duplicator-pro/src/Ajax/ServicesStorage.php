<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Ajax;

use DUP_PRO_Handler;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Schedule_Entity;
use DUP_PRO_U;
use Duplicator\Controllers\SchedulePageController;
use Duplicator\Core\CapMng;
use Duplicator\Libs\Snap\SnapLog;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\OneDriveStorage;
use Duplicator\Models\Storages\StorageAuthInterface;
use Duplicator\Models\Storages\UnknownStorage;
use Exception;

class ServicesStorage extends AbstractAjaxService
{
    const STORAGE_BULK_DELETE   = 1;
    const STORAGE_GET_SCHEDULES = 5;

    /**
     * Init ajax calls
     *
     * @return void
     */
    public function init()
    {
        $this->addAjaxCall("wp_ajax_duplicator_pro_storage_bulk_actions", "bulkActions");
        $this->addAjaxCall('wp_ajax_duplicator_pro_get_storage_details', 'packageStoragesDetails');
        $this->addAjaxCall("wp_ajax_duplicator_pro_storage_test", "testStorage");
        $this->addAjaxCall("wp_ajax_duplicator_pro_auth_storage", "authorizeStorage");
        $this->addAjaxCall("wp_ajax_duplicator_pro_revoke_storage", "revokeStorage");

        $this->addAjaxCall("wp_ajax_duplicator_pro_onedrive_all_perms_update", "onedriveAllPermsUpdate");
    }

    /**
     * Storage bulk actions handler
     *
     * @return void
     * @throws \Exception
     */
    public function bulkActions()
    {
        DUP_PRO_Handler::init_error_handler();
        check_ajax_referer('duplicator_pro_storage_bulk_actions', 'nonce');

        $json       = array(
            'success'   => false,
            'message'   => '',
            'schedules' => array(),
        );
        $isValid    = true;
        $inputData  = filter_input_array(INPUT_POST, array(
            'storage_ids' => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_ARRAY,
                'options' => array('default' => false),
            ),
            'perform'     => array(
                'filter'  => FILTER_VALIDATE_INT,
                'flags'   => FILTER_REQUIRE_SCALAR,
                'options' => array('default' => false),
            ),
        ));
        $storageIDs = $inputData['storage_ids'];
        $action     = $inputData['perform'];

        if (empty($storageIDs) || in_array(false, $storageIDs) || $action === false) {
            $isValid = false;
        }

        try {
            CapMng::can(CapMng::CAP_STORAGE);

            if (!$isValid) {
                throw new \Exception(DUP_PRO_U::__("Invalid Request."));
            }

            foreach ($storageIDs as $id) {
                switch ($action) {
                    case self::STORAGE_BULK_DELETE:
                        AbstractStorageEntity::deleteById($id);
                        break;
                    case self::STORAGE_GET_SCHEDULES:
                        foreach (DUP_PRO_Schedule_Entity::get_schedules_by_storage_id($id) as $schedule) {
                            $json["schedules"][] = array(
                                "id"            => $schedule->getId(),
                                "name"          => $schedule->name,
                                "hasOneStorage" => count($schedule->storage_ids) <= 1,
                                "editURL"       => SchedulePageController::getInstance()->getEditUrl($schedule->getId()),
                            );
                        }
                        break;
                    default:
                        throw new \Exception("Invalid action.");
                }
            }
            //SORT_REGULAR allows to do array_unique on multidimensional arrays
            $json["schedules"] = array_unique($json["schedules"], SORT_REGULAR);
            $json["success"]   = true;
        } catch (\Exception $ex) {
            $json['message'] = $ex->getMessage();
        }

        die(json_encode($json));
    }

    /**
     * Test storage connection
     *
     * @return void
     */
    public function packageStoragesDetails()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'packageStoragesDetailsCallback',
            ),
            'duplicator_pro_get_storage_details',
            $_POST['nonce'],
            CapMng::CAP_CREATE
        );
    }

    /**
     * Hook ajax wp_ajax_duplicator_pro_get_storage_details
     *
     * @return array<string,mixed>
     */
    public static function packageStoragesDetailsCallback()
    {
        $result = array(
            'success'           => false,
            'message'           => '',
            'logURL'            => '',
            'storage_providers' => array(),
        );

        try {
            if (($package_id = SnapUtil::sanitizeIntInput(INPUT_POST, 'package_id', -1)) < 0) {
                throw new Exception(DUP_PRO_U::__("Invalid Request."));
            }

            $package = DUP_PRO_Package::get_by_id($package_id);
            if ($package == false) {
                throw new Exception(sprintf(DUP_PRO_U::__('Unknown package %1$d'), $package_id));
            }

            $providers = array();
            foreach ($package->upload_infos as $upload_info) {
                if (($storage = AbstractStorageEntity::getById($upload_info->getStorageId())) === false) {
                    continue;
                }
                $storageInfo              = [];
                $storageInfo["failed"]    = $upload_info->failed;
                $storageInfo["cancelled"] = $upload_info->cancelled;
                $storageInfo["infoHTML"]  = $storage->renderRemoteLocationInfo(
                    $upload_info->failed,
                    $upload_info->cancelled,
                    false
                );
                // Newest storage upload infos will supercede earlier attempts to the same storage
                $providers[$upload_info->getStorageId()] = $storageInfo;
            }

            $result['success']           = true;
            $result['message']           = DUP_PRO_U::__('Retrieved storage information');
            $result['logURL']            = $package->getLocalPackageFileURL(DUP_PRO_Package_File_Type::Log);
            $result['storage_providers'] = $providers;
        } catch (Exception $ex) {
            $result['success'] = false;
            $result['message'] = $ex->getMessage();
            DUP_PRO_Log::traceError($ex->getMessage());
        }

        return $result;
    }

    /**
     * Test storage connection
     *
     * @return void
     */
    public function testStorage()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'testStorageCallback',
            ),
            'duplicator_pro_storage_test',
            $_POST['nonce'],
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Test storage callback
     *
     * @return array<string,mixed>
     */
    public static function testStorageCallback()
    {
        $result = array(
            'success'     => false,
            'message'     => '',
            'status_msgs' => '',
        );

        $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0 || ($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message']     = __('Invalid storage', 'duplicator-pro');
            $result['status_msgs'] = __('Invalid storage', 'duplicator-pro');
        } else {
            $result['success']     = $storage->test($result['message']);
            $result['status_msgs'] = $storage->getTestLog();
        }

        return $result;
    }

    /**
     * Authorize storage
     *
     * @return void
     */
    public function authorizeStorage()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'authorizeStorageCallback',
            ),
            'duplicator_pro_auth_storage',
            $_POST['nonce'],
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Authorize storage callback
     *
     * @return mixed[]
     */
    public static function authorizeStorageCallback()
    {
        $result = array(
            'success'    => false,
            'storage_id' => -1,
            'message'    => '',
        );

        $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0) {
            // New storage
            $intMin      = (PHP_INT_MAX * -1 - 1); // On php 5.6 PHP_INT_MIN don't exists
            $storageType = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_type', $intMin);
            $storage     = AbstractStorageEntity::getNewStorageByType($storageType);
            if ($storage instanceof UnknownStorage) {
                $result['message'] = __('Invalid storage type', 'duplicator-pro');
                return $result;
            }
        } elseif (($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message'] = __('Invalid storage', 'duplicator-pro');
            return $result;
        } else {
            $result['storage_id'] = $storage->getId();
        }

        DUP_PRO_Log::trace("Auth storage: " . $storage->getName() . "[ID:" . $storage->getId() . "] type: " . $storage->getStypeName());
        if (!$storage instanceof StorageAuthInterface) {
            $result['message'] = __('Storage does not support authorization', 'duplicator-pro');
            return $result;
        }

        if ($storage->authorizeFromRequest($result['message'])) {
            if (($result['success'] = $storage->save()) == false) {
                $result['message'] = __('Failed to update storage', 'duplicator-pro');
            }
        }

        // Make suge storage id is set for new storage
        $result['storage_id'] = $storage->getId();
        DUP_PRO_Log::trace('Auth result: ' . SnapLog::v2str($result['success']) . ' msg: ' . $result['message']);
        return $result;
    }

    /**
     * Revoke storage
     *
     * @return void
     */
    public function revokeStorage()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'revokeStorageCallback',
            ),
            'duplicator_pro_revoke_storage',
            $_POST['nonce'],
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Revoke storage callback
     *
     * @return mixed[]
     */
    public static function revokeStorageCallback()
    {
        $result = array(
            'success' => false,
            'message' => '',
        );

        $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0 || ($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message'] = __('Invalid storage', 'duplicator-pro');
            return $result;
        }

        DUP_PRO_Log::trace("Revoke storage: " . $storage->getName() . "[ID:" . $storage->getId() . "] type: " . $storage->getStypeName());
        if (!$storage instanceof StorageAuthInterface) {
            $result['message'] = __('Storage does not support authorization', 'duplicator-pro');
            DUP_PRO_Log::trace($result['message']);
            return $result;
        }

        if ($storage->revokeAuthorization($result['message'])) {
            if (($result['success'] = $storage->save()) == false) {
                $result['message'] = __('Failed to update storage', 'duplicator-pro');
            }
        }
        DUP_PRO_Log::trace('Revoke result: ' . SnapLog::v2str($result['success']) . ' msg: ' . $result['message']);
        return $result;
    }

    /**
     * Update OneDrive permissions
     *
     * @return void
     */
    public function onedriveAllPermsUpdate()
    {
        AjaxWrapper::json(
            array(
                __CLASS__,
                'onedriveAllPermsUpdateCallback',
            ),
            'duplicator_pro_onedrive_all_perms_update',
            $_POST['nonce'],
            CapMng::CAP_STORAGE
        );
    }

    /**
     * Update OneDrive permissions callback
     *
     * @return mixed[]
     */
    public static function onedriveAllPermsUpdateCallback()
    {
        $result = array(
            'success'  => false,
            'message'  => '',
            'auth_url' => '',
        );

        $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        if ($storageId < 0 || ($storage = AbstractStorageEntity::getById($storageId)) === false) {
            $result['message'] = __('Invalid storage', 'duplicator-pro');
            return $result;
        }

        DUP_PRO_Log::trace("Update OneDrive permissions: " . $storage->getName() . " [ID:" . $storage->getId() . "] type: " . $storage->getStypeName());
        if (!$storage instanceof OneDriveStorage) {
            $result['message'] = __('Stroage isn\'t OneDrive storage', 'duplicator-pro');
            DUP_PRO_Log::trace($result['message']);
            return $result;
        }

        $allPerm = SnapUtil::sanitizeBoolInput(SnapUtil::INPUT_REQUEST, 'all_perms', false);

        if ($storage->setAllPermissions($allPerm) == false) {
            $result['message'] = __('Failed to set all permissions', 'duplicator-pro');
            return $result;
        }

        $result['success']  = true;
        $result['auth_url'] = $storage->getAuthorizationUrl();
        return $result;
    }
}
