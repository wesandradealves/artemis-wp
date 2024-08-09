<?php

namespace Duplicator\Models\Storages;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Package_Upload_Info;
use DUP_PRO_Schedule_Entity;
use DUP_PRO_Storage_Entity;
use Duplicator\Core\Views\TplMng;
use Duplicator\Libs\Snap\SnapIO;
use Duplicator\Libs\Snap\SnapURL;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\IncrementalStatusMessage;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;
use Exception;
use ReflectionClass;
use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;

abstract class AbstractStorageEntity extends DUP_PRO_Storage_Entity implements AbstractStorageEntityInterface, ModelMigrateSettingsInterface
{
    /** @var array<int,string> Class list registered */
    private static $storageTypes = [];

    /** @var string */
    protected $name = '';
    /** @var string */
    protected $notes = '';
    /** @var int */
    protected $storage_type = 0;
    /** @var string */
    protected $version = DUPLICATOR_PRO_VERSION;
    /** @var array<string,scalar>  Storage configuration data */
    protected $config = [];
    /** @var bool this value is true on wakeup of old storages entities, for new storages is false*/
    protected $legacyEntity = true;
    /** @var IncrementalStatusMessage Inclemental messages system */
    protected $testLog = null;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name         = __('New Storage', "duplicator-pro");
        $this->storage_type = static::getSType();
        $this->legacyEntity = false;
        $this->testLog      = new IncrementalStatusMessage();
        $this->config       = static::getDefaultCoinfig();
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType()
    {
        return 'DUP_PRO_Storage_Entity';
    }

    /**
     * Initizalize entity from JSON
     *
     * @param string     $json           JSON string
     * @param int<0,max> $rowId          Entity row id
     * @param ?string    $overwriteClass Overwrite class object, class must extend AbstractEntity
     *
     * @return static
     */
    protected static function getEntityFromJson($json, $rowId, $overwriteClass = null)
    {
        if ($overwriteClass === null) {
            $tmp            = JsonSerialize::unserialize($json);
            $overwriteClass = AbstractStorageEntity::getSTypePHPClass($tmp);
        }
        return parent::getEntityFromJson($json, $rowId, $overwriteClass);
    }

    /**
     * Get default config
     *
     * @return array<string,scalar>
     */
    protected static function getDefaultCoinfig()
    {
        return [
            'storage_folder' => self::getDefaultStorageFolder(),
            'max_packages'   => 10,
        ];
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        // Update storage version on save
        $this->version = DUPLICATOR_PRO_VERSION;

        $data = parent::__serialize();

        if (DUP_PRO_Global_Entity::getInstance()->crypt) {
            if (($dataString = JsonSerialize::serialize($data['config'])) == false) {
                throw new Exception('Error serialize storage config');
            }
            $data['config'] = CryptBlowfish::encrypt($dataString, null, true);
        }

        unset($data['testLog']);
        return $data;
    }

    /**
     * Serialize
     *
     * Wakeup method.
     *
     * @return void
     */
    public function __wakeup()
    {
        parent::__wakeup();

        if (is_string($this->config)) {
            // if si encrypted config is a string else is an array
            $config = CryptBlowfish::decrypt($this->config, null, true);
            $config = JsonSerialize::unserialize($config);

            $this->config = static::getDefaultCoinfig();
            // Update only existing keys
            foreach (array_keys($this->config) as $key) {
                if (!isset($config[$key])) {
                    continue;
                }
                $this->config[$key] = $config[$key];
            }
        }
        $this->testLog = new IncrementalStatusMessage();
    }

    /**
     * Get name
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Get notes
     *
     * @return string
     */
    public function getNotes()
    {
        return $this->notes;
    }

    /**
     * Get priority, used to sort storages.
     * 100 is neutral value, 0 is the highest priority
     *
     * @return int
     */
    public static function getPriority()
    {
        return 100;
    }

    /**
     * Register storage type
     *
     * @return void
     */
    public static function registerType()
    {
        if (isset(self::$storageTypes[static::getSType()])) {
            throw new Exception("Storage type " . static::getSType() . " already registered with class " . self::$storageTypes[static::getSType()]);
        }
        self::$storageTypes[static::getSType()] = static::class;
    }

    /**
     * Get storages types
     *
     * @return int[]
     */
    final public static function getResisteredTypes()
    {
        return array_keys(self::$storageTypes);
    }

    /**
     * Get storages types sorted by priority
     *
     * @return int[]
     */
    final public static function getResisteredTypesByPriority()
    {
        $types = self::getResisteredTypes();
        usort($types, function ($a, $b) {
            $aClass = self::$storageTypes[$a];
            $bClass = self::$storageTypes[$b];

            if ($aClass::getPriority() == $bClass::getPriority()) {
                return 0;
            }

            return ($aClass::getPriority() <  $bClass::getPriority()) ? -1 : 1;
        });
        return $types;
    }

    /**
     * Get storage type class
     *
     * @param int|array<string,mixed> $data Storage data or storage type id
     *
     * @return string
     */
    final public static function getSTypePHPClass($data)
    {
        if (is_array($data)) {
            $type  = (isset($data['storage_type']) ? $data['storage_type'] : UnknownStorage::getSType());
            $class = isset(self::$storageTypes[$type]) ? self::$storageTypes[$type] : UnknownStorage::class;
        } else {
            $type  = (int) $data;
            $class = isset(self::$storageTypes[$type]) ? self::$storageTypes[$type] : UnknownStorage::class;
            $data  = [];
        }
        return apply_filters('duplicator_pro_storage_type_class', $class, $type, $data);
    }

    /**
     * Get new storage object by type
     *
     * @param int $type Storage type
     *
     * @return self
     */
    final public static function getNewStorageByType($type)
    {
        $class = self::getSTypePHPClass($type);
        /** @var self */
        return new $class();
    }

    /**
     * Render config fields by storage type
     *
     * @param int|self $type Storage type or storage object
     * @param bool     $echo Echo or return
     *
     * @return string
     */
    final public static function renderSTypeConfigFields($type, $echo = true)
    {
        if ($type instanceof self) {
            $storage = $type;
        } else {
            $class = self::getSTypePHPClass($type);
            /** @var self */
            $storage = new $class();
        }
        return $storage->renderConfigFields($echo);
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
        $this->name = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, 'name', '');
        if (strlen($this->name) == 0) {
            $message = __('Storage name is required.', 'duplicator-pro');
            return false;
        }
        $this->notes = SnapUtil::sanitizeDefaultInput(SnapUtil::INPUT_REQUEST, 'notes', '');
        return true;
    }

    /**
     * Sanitize storage folder
     *
     * @param string $inputKey Input key
     * @param string $root     add,remove,none (add root, remove root, do nothing)
     *
     * @return string
     */
    protected static function getSanitizedInputFolder($inputKey, $root = 'none')
    {
        $folder = SnapUtil::sanitizeTextInput(SnapUtil::INPUT_REQUEST, $inputKey, '');
        $folder = trim(stripslashes($folder));
        $folder = SnapIO::safePathUntrailingslashit($folder);
        $folder = ltrim($folder, '/\\');

        switch ($root) {
            case 'add':
                $folder = ltrim($folder, '/\\');
                $folder = '/' . $folder;
                break;
            case 'remove':
                $folder = ltrim($folder, '/\\');
                break;
            case 'none':
            default:
                break;
        }

        return $folder;
    }

    /**
     * Is type selectable, if false the storage can't be selected so can't be created new storage of this type
     *
     * @return bool
     */
    public static function isSelectable()
    {
        return true;
    }

    /**
     * If storage is default can't be deleted and the name can't be changed
     *
     * @return bool
     */
    public static function isDefault()
    {
        return false;
    }

    /**
     * Check if storage is supported
     *
     * @return bool
     */
    public static function isSupported()
    {
        return true;
    }

    /**
     * Get supported notice, displayed if storage isn't supported
     *
     * @return string html string or empty if storage is supported
     */
    public static function getNotSupportedNotice()
    {
        if (self::isSupported()) {
            return '';
        }

        $result = sprintf(
            __(
                'The Storage %s is not supported on this server.',
                'duplicator-pro'
            ),
            static::getStypeName()
        );
        return esc_html($result);
    }

    /**
     * Returns true if storage type is local
     *
     * @return bool
     */
    public static function isLocal()
    {
        return false;
    }

    /**
     * Get storage folder
     *
     * @return string
     */
    protected function getStorageFolder()
    {
        /** @var string */
        return $this->config['storage_folder'];
    }

    /**
     * Get storage location string
     *
     * @return string
     */
    abstract public function getLocationString();

    /**
     * Returns an html anchor tag of location
     *
     * @return string Returns an html anchor tag with the storage location as a hyperlink.
     *
     * @example
     * OneDrive Example return
     * <a target="_blank" href="https://1drv.ms/f/sAFrQtasdrewasyghg">https://1drv.ms/f/sAFrQtasdrewasyghg</a>
     */
    public function getHtmlLocationLink()
    {
        return '<a href="' . esc_url($this->getLocationString()) . '" target="_blank" >' . esc_html($this->getLocationString()) . '</a>';
    }

    /**
     * Check if storage is valid
     *
     * @return bool Return true if storage is valid and ready to use, false otherwise
     */
    abstract public function isValid();

    /**
     * Return max storage packages, 0 unlimited
     *
     * @return int<0,max>
     */
    public function getMaxPackages()
    {
        /** @var int<0,max> */
        return $this->config['max_packages'];
    }

    /**
     * Copies the package files from the default local storage to another local storage location
     *
     * @param DUP_PRO_Package             $package     the package
     * @param DUP_PRO_Package_Upload_Info $upload_info the upload info
     *
     * @return void
     */
    abstract public function copyFromDefault(DUP_PRO_Package $package, DUP_PRO_Package_Upload_Info $upload_info);

    /**
     * Purge old packages
     *
     * @return bool true if success, false otherwise
     */
    abstract public function purgeOldPackages();


    /**
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getListQuickView($echo = true)
    {
        ob_start();
        ?>
        <div>
            <label><?php esc_html_e('Location', 'duplicator-pro') ?>:</label>
            <?php echo esc_html($this->getLocationString()); ?>
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
     * List quick view
     *
     * @param bool $echo Echo or return
     *
     * @return string HTML string
     */
    public function getDeleteView($echo = true)
    {
        ob_start();
        ?>
        <div class="item">
            <span class="lbl">Name:</span><?php echo esc_html($this->getName()); ?><br>
            <span class="lbl">Type:</span>&nbsp;<?php echo $this->getStypeIcon(); ?>&nbsp;<?php echo esc_html($this->getStypeName()); ?>
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
     * Get action key text
     *
     * @param string $key Key name (action, pending, failed, cancelled, success)
     *
     * @return string
     */
    protected function getActionKeyText($key)
    {
        switch ($key) {
            case 'action':
                return sprintf(
                    __('Transferring to %1$s folder:<br/> <i>%2$s</i>', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'pending':
                return sprintf(
                    __('Transfer to %1$s folder %2$s is pending', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'failed':
                return sprintf(
                    __('Failed to transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'cancelled':
                return sprintf(
                    __('Cancelled before could transfer to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            case 'success':
                return sprintf(
                    __('Transferred package to %1$s folder %2$s', "duplicator-pro"),
                    $this->getStypeName(),
                    $this->getStorageFolder()
                );
            default:
                throw new Exception('Invalid key');
        }
    }

    /**
     * Get action text
     *
     * @return string
     */
    public function getActionText()
    {
        return $this->getActionKeyText('action');
    }

    /**
     * Get pending action text
     *
     * @return string
     */
    public function getPendingText()
    {
        return $this->getActionKeyText('pending');
    }

    /**
     * Returns the text to display when the package has failed to copy to the storage location
     *
     * @return string
     */
    public function getFailedText()
    {
        return $this->getActionKeyText('failed');
    }

    /**
     * Returns the text to display when the package has been cancelled before it could be copied to the storage location
     *
     * @return string
     */
    public function getCancelledText()
    {
        return $this->getActionKeyText('cancelled');
    }

    /**
     * Returns the text to display when the package has been successfully copied to the storage location
     *
     * @return string
     */
    public function getSuccessText()
    {
        return $this->getActionKeyText('success');
    }

    /**
     *
     * @return string
     */
    protected static function getDefaultStorageFolder()
    {
        /** @var array<string,scalar> */
        $parsetUrl = SnapURL::parseUrl(get_home_url());
        if (is_string($parsetUrl['host']) && strlen($parsetUrl['host']) > 0) {
            $parsetUrl['host'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['host']);
        }
        $parsetUrl['scheme']   = false;
        $parsetUrl['port']     = false;
        $parsetUrl['query']    = false;
        $parsetUrl['fragment'] = false;
        $parsetUrl['user']     = false;
        $parsetUrl['pass']     = false;
        if (is_string($parsetUrl['path']) && strlen($parsetUrl['path']) > 0) {
            $parsetUrl['path'] = preg_replace("([^\w\d\-_~,;\[\]\(\)\/\.])", '', $parsetUrl['path']);
        }
        return ltrim(SnapURL::buildUrl($parsetUrl), '/\\');
    }

    /**
     * Render form config fields
     *
     * @param bool $echo Echo or return
     *
     * @return string
     */
    abstract public function renderConfigFields($echo = true);

    /**
     * Render remote localtion info
     *
     * @param bool $failed    Failed upload
     * @param bool $cancelled Cancelled upload
     * @param bool $echo      Echo or return
     *
     * @return string
     */
    public function renderRemoteLocationInfo($failed = false, $cancelled = false, $echo = true)
    {
        return TplMng::getInstance()->render(
            'admin_pages/storages/parts/remote_localtion_info',
            [
                'failed'    => $failed,
                'cancelled' => $cancelled,
                'storage'   => $this,
            ],
            $echo
        );
    }

    /**
     * Storages test
     *
     * @param string $message Test message
     *
     * @return bool return true if success, false otherwise
     */
    public function test(&$message = '')
    {
        $this->testLog->reset();
        $message = sprintf(__('Testing %s storage...', 'duplicator-pro'), $this->getStypeName());
        $this->testLog->addMessage($message);

        if ($this->isSupported() == false) {
            $message = sprintf(__('Storage %s isn\'t supported on current server', 'duplicator-pro'), $this->getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }
        if ($this->isValid() == false) {
            $message = sprintf(__('Storage %s config data isn\'t valid', 'duplicator-pro'), $this->getStypeName());
            $this->testLog->addMessage($message);
            return false;
        }
        return true;
    }

    /**
     * Get last test messages
     *
     * @return string
     */
    public function getTestLog()
    {
        return (string) $this->testLog;
    }

    /**
     * Get copied storage from source id.
     * If destId is existing storage is accepted source id with only the same type
     *
     * @param int $sourceId Source storage id
     * @param int $targetId Target storage id, if <= 0 create new storage
     *
     * @return false|static Return false on failure or storage object with updated value
     */
    public static function getCopyStorage($sourceId, $targetId = -1)
    {
        if (($source = static::getById($sourceId)) === false) {
            return false;
        }

        if ($targetId <= 0) {
            $class = get_class($source);
            /** @var static */
            $target = new $class();
        } else {
            /** @var false|static */
            $target = static::getById($targetId);
            if ($target == false) {
                return false;
            }
            if ($source->getSType() != $target->getSType()) {
                return false;
            }
        }

        $skipProps = [
            'id',
            'testLog',
        ];

        $reflect = new ReflectionClass($source);
        foreach ($reflect->getProperties() as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if ($prop->isStatic()) {
                continue;
            }
            $prop->setAccessible(true);
            if ($prop->getName() == 'name') {
                $newName = sprintf(__('%1$s - Copy', "duplicator-pro"), $prop->getValue($source));
                $prop->setValue($target, $newName);
            } else {
                $prop->setValue($target, $prop->getValue($source));
            }
        }

        return $target;
    }

    /**
     * Get all storages by type
     *
     * @param int $sType Storage type
     *
     * @return self[]|false return entities list of false on failure
     */
    public static function getAllBySType($sType)
    {
        return self::getAll(0, 0, null, function (self $storage) use ($sType) {
            return ($storage->getSType() == $sType);
        });
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport()
    {
        $data = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        unset($data['testLog']);
        return $data;
    }

    /**
     * Update object properties from import data
     *
     * @param array<string, mixed> $data        data to import
     * @param string               $dataVersion version of data
     * @param array<string, mixed> $extraData   extra data, useful form id mapping etc.
     *
     * @return bool True if success, otherwise false
     */
    public function settingsImport($data, $dataVersion, array $extraData = [])
    {
        $skipProps = ['id'];

        $reflect = new ReflectionClass(self::class);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            if (!isset($data[$prop->getName()])) {
                continue;
            }
            $prop->setAccessible(true);
            $prop->setValue($this, $data[$prop->getName()]);
        }

        return true;
    }

    /**
     * Save new storage to DB
     *
     * @return int|false The id or false on failure
     */
    protected function insert()
    {
        if (($id = parent::insert()) === false) {
            return false;
        }

        do_action('duplicator_pro_after_storage_create', $id);
        return $id;
    }

    /**
     * Delete this storage
     *
     * @return bool True on success, or false on error.
     */
    public function delete()
    {
        $id = $this->id;

        if (parent::delete() === false) {
            return false;
        }

        DUP_PRO_Package::by_status_callback(function (DUP_PRO_Package $package) use ($id) {
            foreach ($package->upload_infos as $key => $upload_info) {
                if ($upload_info->getStorageId() == $id) {
                    DUP_PRO_Log::traceObject("deleting uploadinfo from package $package->ID", $upload_info);
                    unset($package->upload_infos[$key]);
                    $package->save();
                    break;
                }
            }
        });

        DUP_PRO_Schedule_Entity::listCallback(function (DUP_PRO_Schedule_Entity $schedule) use ($id) {
            if (($key = array_search($id, $schedule->storage_ids)) !== false) {
                $key = (int) $key;
                //use array_splice() instead of unset() to reset keys
                array_splice($schedule->storage_ids, $key, 1);
                if (count($schedule->storage_ids) === 0) {
                    $schedule->active = false;
                }
                $schedule->save();
            }
        });

        do_action('duplicator_pro_after_storage_delete', $id);

        return true;
    }
}
