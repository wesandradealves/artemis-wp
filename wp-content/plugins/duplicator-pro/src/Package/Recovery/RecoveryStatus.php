<?php

namespace Duplicator\Package\Recovery;

use DUP_PRO_Package;
use DUP_PRO_Package_File_Type;
use DUP_PRO_Package_Importer;
use Exception;
use DUP_PRO_Package_Template_Entity;
use DUP_PRO_Schedule_Entity;
use DUP_PRO_U;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Models\Storages\StoragesUtil;
use Duplicator\Package\Create\BuildComponents;

/**
 * Class RecoveryStatus
 *
 * This class is designed to help control the various stages and associates
 * that are used to keep track of the RecoveryPoint statuses
 */
class RecoveryStatus
{
    const TYPE_PACKAGE  = 'PACKAGE';
    const TYPE_SCHEDULE = 'SCHEDULE';
    const TYPE_TEMPLATE = 'TEMPLATE';

    const COMPONENTS_REQUIRED = [
        BuildComponents::COMP_DB,
        BuildComponents::COMP_CORE,
        BuildComponents::COMP_PLUGINS,
        BuildComponents::COMP_THEMES,
        BuildComponents::COMP_UPLOADS,
    ];

    /** @var DUP_PRO_Package|DUP_PRO_Package_Template_Entity|DUP_PRO_Schedule_Entity */
    protected $object = null;
    /** @var string */
    protected $objectType = '';
    /** @var ?array{dbonly:bool,filterDirs:string[],filterTables:string[],components:string[]} */
    protected $filteredData = null;
    /** @var false|DUP_PRO_Package_Template_Entity */
    private $activeTemplate = false;

    /**
     * Class constructor
     *
     * @param DUP_PRO_Package|DUP_PRO_Package_Template_Entity|DUP_PRO_Schedule_Entity $object entity object
     */
    public function __construct($object)
    {
        if (!is_object($object)) {
            throw new Exception("Input must be of type object");
        }

        if ($object instanceof DUP_PRO_Package) {
            $this->objectType = self::TYPE_PACKAGE;
        } elseif ($object instanceof DUP_PRO_Schedule_Entity) {
            $this->objectType     = self::TYPE_SCHEDULE;
            $this->activeTemplate = DUP_PRO_Package_Template_Entity::getById($object->template_id);
        } elseif ($object instanceof DUP_PRO_Package_Template_Entity) {
            $this->objectType     = self::TYPE_TEMPLATE;
            $this->activeTemplate = $object;
        } else {
            throw new Exception('Object must be of a valid object');
        }
        $this->object = $object;

        // Init filtered data
        $this->getFilteredData();
    }

     /**
     * Get the literal type name based on the recovery status object being evaluated
     *
     * @return string Returns the recovery status object type literal
     */
    public function getType()
    {
        return $this->objectType;
    }

    /**
     * Retgurn recovery status object
     *
     * @return DUP_PRO_Package|DUP_PRO_Package_Template_Entity|DUP_PRO_Schedule_Entity
     */
    public function getObject()
    {
        return $this->object;
    }

    /**
     * Get the type name based on the recovery status object being evaluated
     *
     * @return string     Returns the recovery status object type by name PACKAGE | SCHEDULE | TEMPLATE
     */
    public function getTypeLabel()
    {
        switch ($this->objectType) {
            case self::TYPE_PACKAGE:
                return self::TYPE_PACKAGE;
            case self::TYPE_SCHEDULE:
                return self::TYPE_SCHEDULE;
            case self::TYPE_TEMPLATE:
                return self::TYPE_TEMPLATE;
        }

        return '';
    }

    /**
     * Return true if current object is recoveable
     *
     * @return bool
     */
    public function isRecoveable()
    {
        if (
            ($this->object instanceof DUP_PRO_Package) &&
            version_compare($this->object->Version, DUP_PRO_Package_Importer::IMPORT_ENABLE_MIN_VERSION, '<')
        ) {
            return false;
        }

        return (
            $this->isLocalStorageEnabled() &&
            $this->hasRequiredComponents() &&
            $this->isWordPressCoreComplete() &&
            $this->isDatabaseComplete()
        );
    }

    /**
     * Is the local storage type enabled for the various object types
     *
     * @return bool Returns true if the object type has a local default storage associated with it
     *
     * @notes:
     * Templates do not have local storage associations so the result will always be true for that type
     */
    public function isLocalStorageEnabled()
    {
        $isEnabled = false;

        if ($this->object instanceof DUP_PRO_Package) {
            $isEnabled = ($this->object->getLocalPackageFilePath(DUP_PRO_Package_File_Type::Archive) !== false);
        } elseif ($this->object instanceof DUP_PRO_Schedule_Entity) {
            if (in_array(StoragesUtil::getDefaultStorageId(), $this->object->storage_ids)) {
                $isEnabled = true;
            } else {
                foreach ($this->object->storage_ids as $id) {
                    if (($storage = AbstractStorageEntity::getById($id)) === false) {
                        continue;
                    }
                    if ($storage->isLocal()) {
                        $isEnabled = true;
                        break;
                    }
                }
            }
        } elseif ($this->object instanceof DUP_PRO_Package_Template_Entity) {
            $isEnabled = true;
        }

        return $isEnabled;
    }

    /**
     * Returns true of the package components are set to their default value
     *
     * @return bool
     */
    public function hasRequiredComponents()
    {
        return array_intersect(self::COMPONENTS_REQUIRED, $this->filteredData['components']) === self::COMPONENTS_REQUIRED;
    }

    /**
     * Returns pacjage has component
     *
     * @param string $component component name
     *
     * @return bool
     */
    public function hasComponent($component)
    {
        return in_array($component, $this->filteredData['components']);
    }

    /**
     * Is the object type filtering out any of the WordPress core directories
     *
     * @return bool     Returns true if the object type has all the proper WordPress core folders
     *
     * @notes:
     *  - The WP core directories include WP -> admin, content and includes
     */
    public function isWordPressCoreComplete()
    {
        return ($this->filteredData['dbonly'] == false && count($this->filteredData['filterDirs']) == 0);
    }

    /**
     * Is the object type filtering out any Database tables that have the WordPress prefix
     *
     * @return bool Returns true if the object type filters out any database tables
     */
    public function isDatabaseComplete()
    {
        return (count($this->filteredData['filterTables']) == 0);
    }

    /**
     * Return filtered datat from entity
     *
     * @return array{dbonly:bool,filterDirs:string[],filterTables:string[],components:string[]}
     */
    public function getFilteredData()
    {
        if ($this->filteredData === null) {
            $dbOnly       = false;
            $components   = [];
            $filterDirs   = [];
            $filterTables = [];


            switch (get_class($this->object)) {
                case DUP_PRO_Package::class:
                    $dbOnly     = $this->object->isDBOnly();
                    $components = $this->object->components;

                    if (filter_var($this->object->Archive->FilterOn, FILTER_VALIDATE_BOOLEAN) && strlen($this->object->Archive->FilterDirs) > 0) {
                        $filterDirs = explode(';', $this->object->Archive->FilterDirs);
                        $filterDirs = array_intersect($filterDirs, DUP_PRO_U::getWPCoreDirs());
                    }

                    if (
                        filter_var($this->object->Database->FilterOn, FILTER_VALIDATE_BOOLEAN) &&
                        strlen($this->object->Database->FilterTables) > 0
                    ) {
                        $filterTables = SnapWP::getTablesWithPrefix(explode(',', $this->object->Database->FilterTables));
                    }
                    break;
                case DUP_PRO_Schedule_Entity::class:
                case DUP_PRO_Package_Template_Entity::class:
                    if ($this->activeTemplate === false) {
                        break;
                    }
                    $dbOnly     = BuildComponents::isDBOnly($this->activeTemplate->components);
                    $components = $this->activeTemplate->components;

                    if (
                        filter_var(
                            $this->activeTemplate->archive_filter_on,
                            FILTER_VALIDATE_BOOLEAN
                        ) &&
                        strlen($this->activeTemplate->archive_filter_dirs) > 0
                    ) {
                        $filterDirs = explode(';', $this->activeTemplate->archive_filter_dirs);
                        $filterDirs = array_intersect($filterDirs, DUP_PRO_U::getWPCoreDirs());
                    }

                    if (
                        filter_var($this->activeTemplate->database_filter_on, FILTER_VALIDATE_BOOLEAN) &&
                        strlen($this->activeTemplate->database_filter_tables) > 0
                    ) {
                        $filterTables = SnapWP::getTablesWithPrefix(explode(',', $this->activeTemplate->database_filter_tables));
                    }
                    break;
            }

            $this->filteredData = array(
                'dbonly'       => $dbOnly,
                'filterDirs'   => $filterDirs,
                'filterTables' => $filterTables,
                'components'   => $components,
            );
        }

        return $this->filteredData;
    }
}
