<?php

/**
 * Storage page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use DUP_PRO_Log;
use DUP_PRO_U;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\ControllersManager;
use Duplicator\Core\Controllers\AbstractMenuPageController;
use Duplicator\Core\Controllers\PageAction;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Models\Storages\UnknownStorage;
use Exception;

class StoragePageController extends AbstractMenuPageController
{
    const INNER_PAGE_LIST = 'storage';
    const INNER_PAGE_EDIT = 'edit';

    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->parentSlug   = ControllersManager::MAIN_MENU_SLUG;
        $this->pageSlug     = ControllersManager::STORAGE_SUBMENU_SLUG;
        $this->pageTitle    = __('Storage', 'duplicator-pro');
        $this->menuLabel    = __('Storage', 'duplicator-pro');
        $this->capatibility = CapMng::CAP_STORAGE;
        $this->menuPos      = 40;

        add_filter('duplicator_page_actions_' . $this->pageSlug, array($this, 'pageActions'));
        add_action('duplicator_after_run_actions_' . $this->pageSlug, array($this, 'pageAfterActions'));
        add_action('duplicator_render_page_content_' . $this->pageSlug, array($this, 'renderContent'));
    }

    /**
     * Return actions for current page
     *
     * @param PageAction[] $actions actions lists
     *
     * @return PageAction[]
     */
    public function pageActions($actions)
    {
        $actions[] = new PageAction(
            'save',
            [
                $this,
                'actionEditSave',
            ],
            [$this->pageSlug],
            'edit'
        );
        $actions[] = new PageAction(
            'copy-storage',
            [
                $this,
                'actionEditCopyStorage',
            ],
            [$this->pageSlug],
            'edit'
        );
        return $actions;
    }

    /**
     * Return storage edit url
     *
     * @param AbstractStorageEntity $storage storage entity
     *
     * @return string
     */
    public static function getEditUrl(AbstractStorageEntity $storage)
    {
        return ControllersManager::getMenuLink(
            ControllersManager::STORAGE_SUBMENU_SLUG,
            null,
            null,
            array(
                'inner_page' => 'edit',
                'storage_id' => $storage->getId(),
            )
        );
    }

    /**
     * Page after actions hook
     *
     * @param bool $isActionCalled true if one actions is called,false if no actions
     *
     * @return void
     */
    public function pageAfterActions($isActionCalled)
    {
        $tplMng = TplMng::getInstance();
        if ($this->getCurrentInnerPage() == 'edit' && $tplMng->hasGlobalValue('storage_id') == false) {
            $storageId = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
            $storage   = ($storageId == -1 ? StoragesUtil::getDefaultNewStorage() : AbstractStorageEntity::getById($storageId));
            if ($storage === false) {
                $storageId = -1;
                $storage   = StoragesUtil::getDefaultNewStorage();
            }

            $tplMng->setGlobalValue('storage_id', $storageId);
            $tplMng->setGlobalValue('storage', $storage);
            $tplMng->setGlobalValue('error_message', null);
            $tplMng->setGlobalValue('success_message', null);
        }
    }

    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current page menu levels slugs
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs)
    {
        try {
            switch ($this->getCurrentInnerPage()) {
                case self::INNER_PAGE_EDIT:
                    TplMng::getInstance()->render(
                        'admin_pages/storages/storage_edit',
                        [
                            'blur' => !License::can(License::CAPABILITY_PRO_BASE),
                        ]
                    );
                    break;
                case self::INNER_PAGE_LIST:
                default:
                    // I left the global try catch for security but the exceptions should be managed inside the list.
                    TplMng::getInstance()->render(
                        'admin_pages/storages/storage_list',
                        [
                            'blur' => !License::can(License::CAPABILITY_PRO_BASE),
                        ]
                    );
                    break;
            }
        } catch (Exception $e) {
            echo self::getErrorMsg($e);
        }
    }

    /**
     * Gt exception error message
     *
     * @param Exception $e exception error
     *
     * @return string
     */
    public static function getErrorMsg(Exception $e)
    {
        $settings_url = ControllersManager::getMenuLink(ControllersManager::SETTINGS_SUBMENU_SLUG);

        $storage_error_msg  = '<div class="error-txt" style="margin:10px 0 20px 0; max-width:750px">';
        $storage_error_msg .= DUP_PRO_U::esc_html__('An error has occurred while trying to read a storage item!  ');
        $storage_error_msg .= DUP_PRO_U::esc_html__('To resolve this issue delete the storage item and re-enter its information.  ');
        $storage_error_msg .= '<br/><br/>';
        $storage_error_msg .= DUP_PRO_U::esc_html__(
            'This problem can be due to a security plugin changing keys in wp-config.php, ' .
            'causing the storage information to become unreadable.  '
        );
        $storage_error_msg .= DUP_PRO_U::esc_html__(
            'If such a plugin is doing this then either disable ' .
            'the key changing functionality in the security plugin or go to '
        );
        $storage_error_msg .= "<a href='{$settings_url}'>";
        $storage_error_msg .= DUP_PRO_U::esc_html__('Duplicator Pro > Settings');
        $storage_error_msg .= '</a>';
        $storage_error_msg .= DUP_PRO_U::esc_html__(' and disable settings encryption.  ');
        $storage_error_msg .= '<br/><br/>';
        $storage_error_msg .= DUP_PRO_U::esc_html__('If the problem persists after doing these things then please contact the support team.');
        $storage_error_msg .= '</div>';
        $storage_error_msg .= '<a href="javascript:void(0)" onclick="jQuery(\'#dup-store-err-details\').toggle();">';
        $storage_error_msg .= DUP_PRO_U::esc_html__('Show Details');
        $storage_error_msg .= '</a>';
        $storage_error_msg .= '<div id="dup-store-err-details" >' . esc_html($e->getMessage()) .
        "<br/><br/><pre>" .
        esc_html($e->getTraceAsString()) .
        "</pre></div>";
        return $storage_error_msg;
    }

    /**
     * Save storage
     *
     * @return array{storage_id:int,storage:AbstractStorageEntity,error_message:?string,success_message:?string}
     */
    public function actionEditSave()
    {
        $error_message   = null;
        $success_message = null;

        $storageId   = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);
        $storageType = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_type', UnknownStorage::getSType());
        $storage     = ($storageId == -1 ? AbstractStorageEntity::getNewStorageByType($storageType) : AbstractStorageEntity::getById($storageId));
        if ($storage === false) {
            $error_message = DUP_PRO_U::__('Unable to load storage item');
        }
        $message = '';

        if ($storage->updateFromHttpRequest($message) === false) {
            $error_message = $message;
            DUP_PRO_Log::trace('Storage update failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName() . ' Message:' . $message);
        } elseif ($storage->save() === false) {
            $error_message   = DUP_PRO_U::__('Unable to save storage item');
            $success_message = '';
            DUP_PRO_Log::trace('Storage save failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
        } else {
            DUP_PRO_Log::trace('Storage updated successfully ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
            $success_message = $message;
        }

        return [
            "storage_id"      => $storageId,
            "storage"         => $storage,
            "error_message"   => $error_message,
            "success_message" => $success_message,
        ];
    }

    /**
     * Save storage
     *
     * @return array{storage_id:int,storage:AbstractStorageEntity,error_message:?string,success_message:?string}
     */
    public function actionEditCopyStorage()
    {
        $error_message   = null;
        $success_message = null;
        $sourceId        = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'duppro-source-storage-id', -1);
        $targetId        = SnapUtil::sanitizeIntInput(SnapUtil::INPUT_REQUEST, 'storage_id', -1);

        if (($storage = AbstractStorageEntity::getCopyStorage($sourceId, $targetId)) === false) {
            $error_message = DUP_PRO_U::__('Unable to copy storage item');
            $storage       = AbstractStorageEntity::getById($targetId);
        } elseif ($storage->save() === false) {
            $error_message   = DUP_PRO_U::__('Unable to copy storage item');
            $success_message = '';
            DUP_PRO_Log::trace('Storage save failed ID:' . $storage->getId() . ' Type:' . $storage->getStypeName());
        } else {
            $success_message = __('Storage Copied Successfully.', 'duplicator-pro');
        }

        return [
            "storage_id"      => $targetId,
            "storage"         => $storage,
            "error_message"   => $error_message,
            "success_message" => $success_message,
        ];
    }
}
