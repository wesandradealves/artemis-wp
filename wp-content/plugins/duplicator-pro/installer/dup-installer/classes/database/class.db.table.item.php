<?php

/**
 * database table item descriptor
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\Descriptors\ParamDescMultisite;
use Duplicator\Installer\Core\Params\Descriptors\ParamDescUsers;
use Duplicator\Installer\Core\Params\Models\SiteOwrMap;
use Duplicator\Installer\Core\Params\PrmMng;
use Duplicator\Libs\Snap\SnapWP;

/**
 * This class manages the installer table, all table management refers to the table name in the original site.
 */
class DUPX_DB_Table_item
{
    /** @var string */
    protected $originalName = '';
    /** @var string */
    protected $tableWithoutPrefix = '';
    /** @var int */
    protected $rows = 0;
    /** @var int */
    protected $size = 0;
    /** @var bool */
    protected $havePrefix = false;
    /** @var int */
    protected $subsiteId = -1;
    /** @var string */
    protected $subsitePrefix = '';

    /**
     *
     * @param string $name table name
     * @param int    $rows number of rows
     * @param int    $size size in bytes
     */
    public function __construct($name, $rows = 0, $size = 0)
    {
        if (strlen($this->originalName = $name) == 0) {
            throw new Exception('The table name can\'t be empty.');
        }

        $this->rows = max(0, (int) $rows);
        $this->size = max(0, (int) $size);

        $oldPrefix = DUPX_ArchiveConfig::getInstance()->wp_tableprefix;
        if (strlen($oldPrefix) === 0) {
            $this->havePrefix         = true;
            $this->tableWithoutPrefix = $this->originalName;
        } if (strpos($this->originalName, $oldPrefix) === 0) {
            $this->havePrefix         = true;
            $this->tableWithoutPrefix = substr($this->originalName, strlen($oldPrefix));
        } else {
            $this->havePrefix         = false;
            $this->tableWithoutPrefix = $this->originalName;
        }

        if (DUPX_ArchiveConfig::getInstance()->isNetwork() && $this->havePrefix) {
            $matches = null;

            if (preg_match('/^(' . preg_quote($oldPrefix, '/') . '(\d+)_)(.+)/', $this->originalName, $matches)) {
                $this->subsitePrefix      = $matches[1];
                $this->subsiteId          = (int) $matches[2];
                $this->tableWithoutPrefix = $matches[3]; // update tabel without prefix without subsite prefix
            } elseif (in_array($this->tableWithoutPrefix, SnapWP::getMultisiteTables())) {
                $this->subsiteId = -1;
            } else {
                $this->subsiteId     = 1;
                $this->subsitePrefix = $oldPrefix;
            }
        } else {
            $this->subsiteId     = 1;
            $this->subsitePrefix = $oldPrefix;
        }
    }

    /**
     * return the original talbe name in source site
     *
     * @return string
     */
    public function getOriginalName()
    {
        return $this->originalName;
    }

    /**
     * return table name without prefix, if the table has no prefix then the original name returns.
     *
     * @param bool $includeSubsiteId if true then the subsite id is included in the name
     *
     * @return string
     */
    public function getNameWithoutPrefix($includeSubsiteId = false)
    {
        return (($includeSubsiteId && $this->subsiteId > 1) ? $this->subsiteId . '_' : '') . $this->tableWithoutPrefix;
    }

    /**
     *
     * @param array{oldPrefix:string,newPrefix:string,commonPart:string} $diffData output
     *
     * @return boolean
     */
    public function isDiffPrefix(&$diffData)
    {
        $oldPos = strlen(($oldName = $this->getOriginalName()));
        $newPos = strlen(($newName = $this->getNewName()));

        if ($oldName == $newName) {
            $diffData = array(
                'oldPrefix'  => '',
                'newPrefix'  => '',
                'commonPart' => $oldName,
            );
            return false;
        }

        while ($oldPos > 0 && $newPos > 0) {
            if ($oldName[$oldPos - 1] != $newName[$newPos - 1]) {
                break;
            }

            $oldPos--;
            $newPos--;
        }

        $diffData = array(
            'oldPrefix'  => substr($oldName, 0, $oldPos),
            'newPrefix'  => substr($newName, 0, $newPos),
            'commonPart' => substr($oldName, $oldPos),
        );
        return true;
    }

    /**
     *
     * @return bool
     */
    public function havePrefix()
    {
        return $this->havePrefix;
    }

    /**
     * return new name extracted on target site
     *
     * @return string
     */
    public function getNewName()
    {
        if (!$this->canBeExctracted()) {
            return '';
        }

        if (!$this->havePrefix) {
            return $this->originalName;
        }

        $paramsManager = PrmMng::getInstance();

        switch (InstState::getInstType()) {
            case InstState::TYPE_SINGLE:
            case InstState::TYPE_MSUBDOMAIN:
            case InstState::TYPE_MSUBFOLDER:
            case InstState::TYPE_RBACKUP_SINGLE:
            case InstState::TYPE_RBACKUP_MSUBDOMAIN:
            case InstState::TYPE_RBACKUP_MSUBFOLDER:
            case InstState::TYPE_RECOVERY_SINGLE:
            case InstState::TYPE_RECOVERY_MSUBDOMAIN:
            case InstState::TYPE_RECOVERY_MSUBFOLDER:
                return $paramsManager->getValue(PrmMng::PARAM_DB_TABLE_PREFIX) . $this->getNameWithoutPrefix(true);
            case InstState::TYPE_STANDALONE:
                if (
                    $this->subsiteId === $paramsManager->getValue(PrmMng::PARAM_SUBSITE_ID) &&
                    $this->subsiteId > 1
                ) {
                    // convert standalon subsite prefix
                    return $paramsManager->getValue(PrmMng::PARAM_DB_TABLE_PREFIX) . $this->getNameWithoutPrefix(false);
                } else {
                    return $paramsManager->getValue(PrmMng::PARAM_DB_TABLE_PREFIX) . $this->getNameWithoutPrefix(true);
                }
            case InstState::TYPE_SINGLE_ON_SUBDOMAIN:
            case InstState::TYPE_SINGLE_ON_SUBFOLDER:
            case InstState::TYPE_SUBSITE_ON_SUBDOMAIN:
            case InstState::TYPE_SUBSITE_ON_SUBFOLDER:
                if ($this->isUserTable()) {
                    return $paramsManager->getValue(PrmMng::PARAM_DB_TABLE_PREFIX) . $this->getNameWithoutPrefix(false);
                }

                if ($this->subsiteId <= 0) {
                    throw new Exception('Curretn talbe site id isn\'t defined');
                }

                if (($map = ParamDescMultisite::getOwrMapBySourceId($this->subsiteId)) == false) {
                    throw new Exception('Map by id ' . $this->subsiteId . ' don\'t exists');
                }

                switch ($map->getTargetId()) {
                    case SiteOwrMap::NEW_SUBSITE_WITH_SLUG:
                    case SiteOwrMap::NEW_SUBSITE_WITH_FULL_DOMAIN:
                        // Site must be created
                        return '';
                    default:
                        break;
                }

                if (($targetInfo = $map->getTargetSiteInfo()) == false) {
                    throw new Exception('Target site info ' . $map->getTargetId() . ' don\'t exists');
                }

                return $targetInfo['blog_prefix'] . $this->getNameWithoutPrefix(false);
            case InstState::TYPE_NOT_SET:
                throw new Exception('Cannot change setup with current installation type [' . InstState::getInstType() . ']');
            default:
                throw new Exception('Unknown mode');
        }
    }

    /**
     *
     * @return int
     */
    public function getRows()
    {
        return $this->rows;
    }

    /**
     * Return table size
     *
     * @param bool $formatted if true then return size in human readable format
     *
     * @return int|string
     */
    public function getSize($formatted = false)
    {
        return $formatted ? DUPX_U::readableByteSize($this->size) : $this->size;
    }

    /**
     * Get table subsite id
     *
     * @return int if -1 isn't a subsite sable
     */
    public function getSubsisteId()
    {
        return $this->subsiteId;
    }

    /**
     *
     * @return boolean
     */
    public function canBeExctracted()
    {
        if (InstState::isInstType(InstState::TYPE_STANDALONE)) {
            return $this->standAloneExtractCheck();
        }

        if (InstState::isAddSiteOnMultisite()) {
            return $this->addSiteOnMultisiteCheck();
        }

        return true;
    }

    /**
     * If false the current table create query is skipped
     *
     * @return bool
     */
    public function createTable()
    {
        if ($this->usersTablesCreateCheck() === false) {
            return false;
        }

        return true;
    }

    /**
     * Check if create users table
     *
     * @return bool
     */
    protected function usersTablesCreateCheck()
    {
        if (!$this->isUserTable()) {
            return true;
        }

        return (ParamDescUsers::getUsersMode() !== ParamDescUsers::USER_MODE_IMPORT_USERS);
    }

    /**
     * Return true if current table is user or usermeta table
     *
     * @return boolean
     */
    public function isUserTable()
    {
        return ($this->havePrefix && in_array($this->tableWithoutPrefix, array('users', 'usermeta')));
    }

    /**
     *
     * @return boolean
     */
    protected function standAloneExtractCheck()
    {
        if ($this->isUserTable()) {
            return true;
        }

        // extract tables without prefix
        if (!$this->havePrefix) {
            return true;
        }

        $standaloneId = PrmMng::getInstance()->getValue(PrmMng::PARAM_SUBSITE_ID);

        // exclude multisite tables
        if ($this->subsiteId < 0) {
            return false;
        }

        if ($standaloneId == 1) {
            // exclude all subsites tables
            if ($this->subsiteId > 1) {
                return false;
            }
        } else {
            if ($this->subsiteId > 1) {
                // exclude all subsite tables except tables with id 1
                if ($this->subsiteId != $standaloneId) {
                    return false;
                }
            } else {
                if (in_array($this->tableWithoutPrefix, SnapWP::getSiteCoreTables())) {
                    // exclude wordpress common main tables
                    return false;
                }

                if (in_array($this->tableWithoutPrefix, DUPX_DB_Tables::getInstance()->getStandaoneTablesWithoutPrefix())) {
                    // I exclude the tables of the standalone site that will be converted into main tables
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * returns true if the table is to be extracted
     *
     * @return boolean
     */
    protected function addSiteOnMultisiteCheck()
    {
        if ($this->isUserTable()) {
            return true;
        }

        $originalPrefix = DUPX_ArchiveConfig::getInstance()->wp_tableprefix;

        if ($this->originalName == DUPX_DB_Functions::getEntitiesTableName($originalPrefix)) {
            return false;
        }

        if ($this->originalName == DUPX_DB_Functions::getPackagesTableName($originalPrefix)) {
            return false;
        }

        return (ParamDescMultisite::getOwrMapBySourceId($this->subsiteId) !== false);
    }

    /**
     * returns true if the table is to be extracted
     *
     * @return boolean
     */
    public function extract()
    {
        if (!$this->canBeExctracted()) {
            return false;
        }

        $tablesVals = PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_TABLES);
        if (!isset($tablesVals[$this->originalName])) {
            throw new Exception('Table ' . $this->originalName . ' not in table vals');
        }

        return $tablesVals[$this->originalName]['extract'];
    }

    /**
     * returns true if a search and replace is to be performed
     *
     * @return boolean
     */
    public function replaceEngine()
    {
        if (!$this->extract()) {
            return false;
        }

        $tablesVals = PrmMng::getInstance()->getValue(PrmMng::PARAM_DB_TABLES);
        if (!isset($tablesVals[$this->originalName])) {
            throw new Exception('Table ' . $this->originalName . ' not in table vals');
        }

        return $tablesVals[$this->originalName]['replace'];
    }
}
