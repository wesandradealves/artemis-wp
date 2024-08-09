<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Core\Models;

use Error;
use Exception;
use ReflectionClass;
use wpdb;

/**
 * Singletion entity class
 */
abstract class AbstractEntitySingleton extends AbstractEntity
{
    /** @var static[] */
    private static $instances = array();

    /**
     * Get instance
     *
     * @return static
     */
    public static function getInstance()
    {
        $class = get_called_class();
        if (!isset(self::$instances[$class])) {
            $items = static::getItemsFromDatabase();
            if (empty($items)) {
                self::$instances[$class] = new static();
                try {
                    self::$instances[$class]->firstIstanceInit();
                    // I save the instance before initializing the values in case they require ajax calls
                    // that would otherwise re-initialize the singletom object
                    self::$instances[$class]->save();
                } catch (Exception $e) {
                    // Prevent save error on cron events edge cases
                    self::$instances[$class] = new static();
                } catch (Error $e) {
                    // Prevent save error on cron events edge cases
                    self::$instances[$class] = new static();
                }
            } else {
                if (count($items) > 1) {
                    self::deleteExcessRows();
                }
                self::$instances[$class] = $items[0];
            }
        }
        return self::$instances[$class];
    }

    /**
     * Class constructor
     */
    abstract protected function __construct();

    /**
     * This function is called on first istance of singletion object
     * Can be extended and used to set dynamic properties values
     *
     * @return void
     */
    protected function firstIstanceInit()
    {
    }

    /**
     * Deletion of excess rows, keeping only the row with the lowest ID
     *
     * @return bool True on success, or false on error.
     */
    protected static function deleteExcessRows()
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT ID FROM " . self::getTableName() . " WHERE type = %s ORDER BY ID ASC",
            static::getType()
        );
        $rows  = $wpdb->get_results($query, ARRAY_A);
        if (count($rows) <= 1) {
            return true;
        }
        $ids   = array_column($rows, 'ID');
        $ids   = array_slice($ids, 1);
        $query = $wpdb->prepare(
            "DELETE FROM " . self::getTableName() . " WHERE ID IN (" . implode(',', $ids) . ")"
        );
        return $wpdb->query($query) !== false;
    }

    /**
     * Delete current entity
     *
     * @return bool True on success, or false on error.
     */
    public function delete()
    {
        throw new Exception('Isn\'t possibile delete singleton entity, use reset to reset values');
    }

    /**
     * Reset entity values
     *
     * @param string[]  $skipProps     the list of props to maintain
     * @param ?callable $setCallback   set callback function ($propName, $propValue): mixed
     * @param ?callable $afterCallback callaback called before save
     *
     * @return bool True on success, or false on error.
     */
    public function reset($skipProps = [], $setCallback = null, $afterCallback = null)
    {
        // Clean singleton instance
        $newIstance = new static();
        $reflect    = new ReflectionClass($newIstance);
        foreach ($reflect->getProperties() as $prop) {
            if ($prop->getName() === 'id') {
                continue;
            }
            if (in_array($prop->getName(), $skipProps)) {
                continue;
            }
            $prop->setAccessible(true);
            $newVal = (
                is_callable($setCallback) ?
                    call_user_func($setCallback, $prop->getName(), $prop->getValue($newIstance)) :
                    $prop->getValue($newIstance)
                );
            $prop->setValue($this, $newVal);
        }
        if (is_callable($afterCallback)) {
            call_user_func($afterCallback);
        }
        return $this->save();
    }
}
