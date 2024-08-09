<?php

/**
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Package\Create;

use DUP_PRO_Log;
use Duplicator\Installer\Package\DescriptorDBInfo;
use Duplicator\Installer\Package\DescriptorDBTableInfo;
use Exception;
use ReflectionClass;

/**
 * Database info
 */
class DatabaseInfo extends DescriptorDBInfo
{
    /**
     * Classs constructor
     */
    public function __construct()
    {
    }

    /**
     * add table info in list
     *
     * @param string           $name           table name
     * @param int<0,max>       $inaccurateRows This data is intended as a preliminary count and therefore not necessarily accurate
     * @param int<0,max>       $size           This data is intended as a preliminary count and therefore not necessarily accurate
     * @param int<0,max>|false $insertedRows   This value, if other than false, is the exact line value inserted into the dump file
     *
     * @return void
     */
    public function addTableInList($name, $inaccurateRows, $size, $insertedRows = false)
    {
        $this->tablesList[$name] = new DescriptorDBTableInfo($inaccurateRows, $size, $insertedRows);
    }

    /**
     * Set inserted words
     *
     * @param string     $name  table name
     * @param int<0,max> $count the real inseret rows cont for table
     *
     * @return void
     */
    public function addInsertedRowsInTableList($name, $count)
    {
        if (!isset($this->tablesList[$name])) {
            throw new Exception('No found table ' . $name . ' in table info');
        } else {
            $this->tablesList[$name]->insertedRows = (int) $count;
        }
    }

    /**
     * Add triggers to list
     *
     * @return array<string,array{event:string,table:string,timing:string,create:string}>
     */
    public function addTriggers()
    {
        global $wpdb;
        $this->triggerList = array();

        if (!is_array($triggers = $wpdb->get_results("SHOW TRIGGERS", ARRAY_A))) {
            return $this->triggerList;
        }

        foreach ($triggers as $trigger) {
            $name                     = (string) $trigger["Trigger"];
            $create                   = $wpdb->get_row("SHOW CREATE TRIGGER `{$name}`", ARRAY_N);
            $this->triggerList[$name] = array(
                "event"  => $trigger["Event"],
                "table"  => $trigger["Table"],
                "timing" => $trigger["Timing"],
                "create" => "DELIMITER ;;\n" . $create[2] . ";;\nDELIMITER ;",
            );
        }

        return $this->triggerList;
    }

    /**
     * Clone current object to DescriptorDBInfo
     *
     * @return DescriptorDBInfo
     */
    public function cloneToArchiveDbInfo()
    {
        $relcect = new ReflectionClass(DescriptorDBInfo::class);
        $props   = $relcect->getProperties();
        $result  = new DescriptorDBInfo();
        foreach ($props as $prop) {
            $prop->setAccessible(true);
            $prop->setValue($result, $prop->getValue($this));
        }
        return $result;
    }
}
