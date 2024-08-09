<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

use VendorDuplicator\Amk\JsonSerialize\JsonSerialize;
use Duplicator\Core\Models\AbstractEntityList;
use Duplicator\Core\Models\UpdateFromInputInterface;
use Duplicator\Installer\Package\ArchiveDescriptor;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Package\Recovery\RecoveryStatus;
use Duplicator\Utils\Crypt\CryptBlowfish;
use Duplicator\Utils\Settings\ModelMigrateSettingsInterface;

class DUP_PRO_Package_Template_Entity extends AbstractEntityList implements UpdateFromInputInterface, ModelMigrateSettingsInterface
{
    /** @var string */
    public $name = '';
    /** @var string */
    public $notes = '';
    //MULTISITE:Filter
    /** @var int[] */
    public $filter_sites = array();
    //ARCHIVE:Files
    /** @var bool */
    public $archive_export_onlydb = false;
    /** @var bool */
    public $archive_filter_on = false;
    /** @var string */
    public $archive_filter_dirs = '';
    /** @var string */
    public $archive_filter_exts = '';
    /** @var string */
    public $archive_filter_files = '';
    /** @var bool */
    public $archive_filter_names = false;
    /** @var string[] */
    public $components = array();
    //ARCHIVE:Database
    /** @var bool */
    public $database_filter_on = false;  // Enable Table Filters
    /** @var bool */
    public $databasePrefixFilter = false;  // If true exclude tables without prefix
    /** @var bool */
    public $databasePrefixSubFilter = false;  // If true exclude unexisting subsite id tables
    /** @var string */
    public $database_filter_tables = ''; // List of filtered tables
    /** @var string */
    public $database_compatibility_modes = ''; // Older style sql compatibility
    //INSTALLER
    //Setup
    /** @var int */
    public $installer_opts_secure_on = 0;  // Enable Password Protection
    /** @var string */
    public $installer_opts_secure_pass = ''; // Old password Protection password, deprecated
    /** @var string */
    public $installerPassowrd = ''; // Password Protection password
    /** @var bool */
    public $installer_opts_skip_scan = false;  // Skip Scanner
    //Basic DB
    /** @var string */
    public $installer_opts_db_host = '';   // MySQL Server Host
    /** @var string */
    public $installer_opts_db_name = '';   // Database
    /** @var string */
    public $installer_opts_db_user = '';   // User
    //cPanel Login
    /** @var bool */
    public $installer_opts_cpnl_enable = false;
    /** @var string */
    public $installer_opts_cpnl_host = '';
    /** @var string */
    public $installer_opts_cpnl_user = '';
    /** @var string */
    public $installer_opts_cpnl_pass = '';
    //cPanel DB
    /** @var string */
    public $installer_opts_cpnl_db_action = 'create';
    /** @var string */
    public $installer_opts_cpnl_db_host = '';
    /** @var string */
    public $installer_opts_cpnl_db_name = '';
    /** @var string */
    public $installer_opts_cpnl_db_user = '';
    //Brand
    /** @var int */
    public $installer_opts_brand = -2;
    /** @var bool */
    public $is_default = false;
    /** @var bool */
    public $is_manual = false;

    /**
     * Class constructor
     */
    public function __construct()
    {
        $this->name       = DUP_PRO_U::__('New Template');
        $this->components = BuildComponents::COMPONENTS_DEFAULT;
    }

    /**
     * Entity type
     *
     * @return string
     */
    public static function getType()
    {
        return 'DUP_PRO_Package_Template_Entity';
    }

    /**
     * Will be called, automatically, when Serialize
     *
     * @return array<string, mixed>
     */
    public function __serialize() // phpcs:ignore PHPCompatibility.FunctionNameRestrictions.NewMagicMethods.__serializeFound
    {
        $data                               = JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
        $data['installer_opts_secure_pass'] = '';
        $data['installerPassowrd']          = CryptBlowfish::encrypt($this->installerPassowrd, null, true);
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
        /*if ($obj->installer_opts_secure_on == ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT && !SettingsUtils::isArchiveEncryptionAvaiable()) {
            $obj->installer_opts_secure_on = ArchiveDescriptor::SECURE_MODE_INST_PWD;
        }*/

        if (strlen($this->installer_opts_secure_pass) > 0) {
            $this->installerPassowrd = base64_decode($this->installer_opts_secure_pass);
        } elseif (strlen($this->installerPassowrd) > 0) {
            $this->installerPassowrd = CryptBlowfish::decrypt($this->installerPassowrd, null, true);
        }

        $this->installer_opts_secure_pass = '';
        $this->archive_filter_dirs        = (string) $this->archive_filter_dirs;
        $this->archive_filter_files       = (string) $this->archive_filter_files;
        $this->archive_filter_exts        = (string) $this->archive_filter_exts;
        $this->archive_filter_on          = filter_var($this->archive_filter_on, FILTER_VALIDATE_BOOLEAN);
        $this->database_filter_on         = filter_var($this->database_filter_on, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * To export data
     *
     * @return array<string, mixed>
     */
    public function settingsExport()
    {
        return JsonSerialize::serializeToData($this, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
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

        if (!isset($data['components'])) {
            // Allow import of older templsates that did not have package components
            if ($this->archive_export_onlydb) {
                $this->components = [BuildComponents::COMP_DB];
            } else {
                $this->components = BuildComponents::COMPONENTS_DEFAULT;
            }
        }

        return true;
    }

    /**
     * Create default template
     *
     * @return void
     */
    public static function create_default()
    {
        if (self::get_default_template() == null) {
            $template = new self();

            $template->name       = DUP_PRO_U::__('Default');
            $template->notes      = DUP_PRO_U::__('The default template.');
            $template->is_default = true;

            $template->save();
            DUP_PRO_Log::trace('Created default template');
        } else {
            // Update it
            DUP_PRO_Log::trace('Default template already exists so not creating');
        }
    }

    /**
     * Create manual mode template
     *
     * @return void
     */
    public static function create_manual()
    {
        if (self::get_manual_template() == null) {
            $template = new self();

            $template->name      = DUP_PRO_U::__('[Manual Mode]');
            $template->notes     = '';
            $template->is_manual = true;

            // Copy over the old temporary template settings into this - required for legacy manual
            $temp_package = DUP_PRO_Package::get_temporary_package(false);

            if ($temp_package != null) {
                DUP_PRO_Log::trace('SET TEMPLATE FROM TEMP PACKAGE pwd ' . $temp_package->Installer->passowrd);
                $template->components           = $temp_package->components;
                $template->filter_sites         = $temp_package->Multisite->FilterSites;
                $template->archive_filter_on    = $temp_package->Archive->FilterOn;
                $template->archive_filter_dirs  = $temp_package->Archive->FilterDirs;
                $template->archive_filter_exts  = $temp_package->Archive->FilterExts;
                $template->archive_filter_files = $temp_package->Archive->FilterFiles;
                $template->archive_filter_names = $temp_package->Archive->FilterNames;

                $template->installer_opts_brand = $temp_package->Brand_ID;

                $template->database_filter_on           = $temp_package->Database->FilterOn;
                $template->databasePrefixFilter         = $temp_package->Database->prefixFilter;
                $template->databasePrefixSubFilter      = $temp_package->Database->prefixSubFilter;
                $template->database_filter_tables       = $temp_package->Database->FilterTables;
                $template->database_compatibility_modes = $temp_package->Database->Compatible;

                $template->installer_opts_db_host   = $temp_package->Installer->OptsDBHost;
                $template->installer_opts_db_name   = $temp_package->Installer->OptsDBName;
                $template->installer_opts_db_user   = $temp_package->Installer->OptsDBUser;
                $template->installer_opts_secure_on = $temp_package->Installer->OptsSecureOn;
                $template->installerPassowrd        = $temp_package->Installer->passowrd;
                $template->installer_opts_skip_scan = $temp_package->Installer->OptsSkipScan;

                $global = DUP_PRO_Global_Entity::getInstance();

                $storageIds = [];
                foreach ($temp_package->get_storages() as $storage) {
                    $storageIds[] = $storage->getId();
                }
                $global->setManualModeStorageIds($storageIds);
                $global->save();
            }

            $template->save();
            DUP_PRO_Log::trace('Created manual mode template');
        } else {
            // Update it
            DUP_PRO_Log::trace('Manual mode template already exists so not creating');
        }
    }

    /**
     *
     * @return bool
     */
    public function isRecoveable()
    {
        $status = new RecoveryStatus($this);
        return $status->isRecoveable();
    }

    /**
     * Display HTML info
     *
     * @param bool $isList is list
     *
     * @return void
     */
    public function recoveableHtmlInfo($isList = false)
    {
        $template = $this;
        require DUPLICATOR____PATH . '/views/tools/templates/widget/recoveable-template-info.php';
    }

    /**
     * Set data from query input
     *
     * @param int $type One of INPUT_GET, INPUT_POST, INPUT_COOKIE, INPUT_SERVER, or INPUT_ENV, SnapUtil::INPUT_REQUEST
     *
     * @return bool true on success or false on failure
     */
    public function setFromInput($type)
    {
        $input = SnapUtil::getInputFromType($type);
        $this->setFromArrayKey(
            $input,
            function ($key, $val) {
                if (is_string($val)) {
                    $val = stripslashes($val);
                }
                return (is_scalar($val) ? SnapUtil::sanitizeNSChars($val) : $val);
            }
        );
        $this->components = BuildComponents::getFromInput($input);

        $this->database_filter_tables = isset($input['dbtables-list']) ? SnapUtil::sanitizeNSCharsNewlineTrim($input['dbtables-list']) : '';

        if (isset($input['filter-paths'])) {
            $filterPaths                = SnapUtil::sanitizeNSChars($input['filter-paths']);
            $this->archive_filter_dirs  = DUP_PRO_Archive::parseDirectoryFilter($filterPaths);
            $this->archive_filter_files = DUP_PRO_Archive::parseFileFilter($filterPaths);
        } else {
            $this->archive_filter_dirs  = '';
            $this->archive_filter_files = '';
        }

        if (isset($input['filter-exts'])) {
            $post_filter_exts          = SnapUtil::sanitizeNSCharsNewlineTrim($input['filter-exts']);
            $this->archive_filter_exts = DUP_PRO_Archive::parseExtensionFilter($post_filter_exts);
        } else {
            $this->archive_filter_exts = '';
        }


        $this->filter_sites = !empty($input['_mu_exclude']) ? $input['_mu_exclude'] : '';

        //Archive
        $this->archive_filter_on       = isset($input['filter-on']);
        $this->database_filter_on      = isset($input['dbfilter-on']);
        $this->databasePrefixFilter    = isset($input['db-prefix-filter']);
        $this->databasePrefixSubFilter = isset($input['db-prefix-sub-filter']);
        $this->archive_filter_names    = isset($input['archive_filter_names']);

        //Installer
        $this->installer_opts_secure_on = filter_input(INPUT_POST, 'secure-on', FILTER_VALIDATE_INT);
        switch ($this->installer_opts_secure_on) {
            case ArchiveDescriptor::SECURE_MODE_NONE:
            case ArchiveDescriptor::SECURE_MODE_INST_PWD:
            case ArchiveDescriptor::SECURE_MODE_ARC_ENCRYPT:
                break;
            default:
                throw new Exception(__('Select valid secure mode', 'duplicator-pro'));
        }
        $this->installer_opts_skip_scan   = isset($input['_installer_opts_skip_scan']);
        $this->installer_opts_cpnl_enable = isset($input['installer_opts_cpnl_enable']);

        $this->installerPassowrd = SnapUtil::sanitizeNSCharsNewline(stripslashes($input['secure-pass']));
        $this->notes             = SnapUtil::sanitizeNSCharsNewlineTrim(stripslashes($input['notes']));

        return true;
    }

    /**
     * Copy template from id
     *
     * @param int<0, max> $templateId template id
     *
     * @return void
     */
    public function copy_from_source_id($templateId)
    {
        if (($source = self::getById($templateId)) === false) {
            throw new Exception('Can\'t get tempalte id' . $templateId);
        }

        $skipProps = [
            'id',
            'is_manual',
            'is_default',
        ];

        $reflect = new ReflectionClass($this);
        $props   = $reflect->getProperties();

        foreach ($props as $prop) {
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            $prop->setAccessible(true);
            $prop->setValue($this, $prop->getValue($source));
        }

        $source_template_name = $source->is_manual ? DUP_PRO_U::__("Active Build Settings") : $source->name;
        $this->name           = sprintf(DUP_PRO_U::__('%1$s - Copy'), $source_template_name);
    }

    /**
     * Gets a list of core WordPress folders that have been filtered
     *
     * @return string[] Returns and array of folders paths
     */
    public function getWordPressCoreFilteredFoldersList()
    {
        return array_intersect(explode(';', $this->archive_filter_dirs), DUP_PRO_U::getWPCoreDirs());
    }

    /**
     * Is any of the WordPress core folders in the folder filter list
     *
     * @return bool    Returns true if a WordPress core path is being filtered
     */
    public function isWordPressCoreFolderFiltered()
    {
        return count($this->getWordPressCoreFilteredFoldersList()) > 0;
    }

    /**
     * Get all entities of current type
     *
     * @param int<0, max>                          $page           current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max>                          $pageSize       page size, 0 return all entities
     * @param callable                             $sortCallback   sort function on items result
     * @param callable                             $filterCallback filter on items result
     * @param array{'col': string, 'mode': string} $orderby        query ordder by
     *
     * @return static[]|false return entities list of false on failure
     */
    public static function getAll(
        $page = 0,
        $pageSize = 0,
        $sortCallback = null,
        $filterCallback = null,
        $orderby = [
            'col'  => 'id',
            'mode' => 'ASC',
        ]
    ) {
        if (is_null($sortCallback)) {
            $sortCallback = function (self $a, self $b) {
                if ($a->is_default) {
                    return -1;
                } elseif ($b->is_default) {
                    return 1;
                } else {
                    return strcasecmp($a->name, $b->name);
                }
            };
        }
        return parent::getAll($page, $pageSize, $sortCallback, $filterCallback, $orderby);
    }

    /**
     * Return list template json encoded data for javascript
     *
     * @return string
     */
    public static function getTemplatesFrontendListData()
    {
        $templates = self::getAll();
        return JsonSerialize::serialize($templates, JsonSerialize::JSON_SKIP_MAGIC_METHODS |  JsonSerialize::JSON_SKIP_CLASS_NAME);
    }

    /**
     * Get all entities of current type
     *
     * @param int<0, max> $page     current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max> $pageSize page size, 0 return all entities
     *
     * @return static[]|false return entities list of false on failure
     */
    public static function getAllWithoutManualMode(
        $page = 0,
        $pageSize = 0
    ) {
        $filterManualCallback = function (self $obj) {
            return ($obj->is_manual === false);
        };
        return self::getAll($page, $pageSize, null, $filterManualCallback);
    }

    /**
     * Get default template if exists
     *
     * @return null|self
     */
    public static function get_default_template()
    {
        $templates = self::getAll();

        foreach ($templates as $template) {
            if ($template->is_default) {
                return $template;
            }
        }
        return null;
    }

    /**
     * Return manual template entity if exists
     *
     * @return null|self
     */
    public static function get_manual_template()
    {
        $templates = self::getAll();

        foreach ($templates as $template) {
            if ($template->is_manual) {
                return $template;
            }
        }

        return null;
    }
}
