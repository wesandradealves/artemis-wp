<?php

namespace Duplicator\Core\Models;

use DUP_PRO_Log;
use Duplicator\Libs\Snap\SnapLog;
use Error;
use Exception;
use wpdb;

/**
 * Entity than have multiple items in database
 */
class AbstractEntityList extends AbstractEntity
{
    /**
     * Get entity by id
     *
     * @param int $id entity id
     *
     * @return static|false Return entity istance or false on failure
     */
    public static function getById($id)
    {
        if ($id < 0) {
            return false;
        }

        /** @var wpdb $wpdb */
        global $wpdb;

        $query = $wpdb->prepare("SELECT * FROM " . self::getTableName() . " WHERE ID = %d", $id);
        if (($row = $wpdb->get_row($query, ARRAY_A)) === null) {
            return false;
        }

        if ($row['type'] !== static::getType()) {
            return false;
        }

        return static::getEntityFromJson($row['data'], (int) $row['id']);
    }

    /**
     * Check if entity id exists
     *
     * @param int $id entity id
     *
     * @return bool true if exists false otherwise
     */
    public static function exists($id)
    {
        /** @var wpdb $wpdb */
        global $wpdb;

        $query = $wpdb->prepare("SELECT COUNT(*) FROM " . self::getTableName() . " WHERE ID = %d AND type = %s", $id, static::getType());
        if (($count = $wpdb->get_var($query)) === null) {
            return false;
        }

        return $count > 0;
    }

    /**
     * Return the number of entities of current type
     *
     * @return int<0, max>
     */
    public static function count()
    {
        return (int) parent::countItemsFromDatabase();
    }

    /**
     * Delete entity by id
     *
     * @param int $id entity id
     *
     * @return bool true on success of false on failure
     */
    public static function deleteById($id)
    {
        if ($id < 0) {
            return true;
        }

        if (($entity = self::getById($id)) === false) {
            return true;
        }

        return $entity->delete();
    }

    /**
     * Get entities of current type
     *
     * @param int<0, max>                          $page           current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max>                          $pageSize       page size, 0 return all entities
     * @param ?callable                            $sortCallback   sort function on items result
     * @param ?callable                            $filterCallback filter on items result
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
        return parent::getItemsFromDatabase($page, $pageSize, $sortCallback, $filterCallback, $orderby);
    }

    /**
     * Get entities ids of current type
     *
     * @param int<0, max>                          $page           current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max>                          $pageSize       page size, 0 return all entities
     * @param ?callable                            $sortCallback   sort function on items result
     * @param ?callable                            $filterCallback filter on items result
     * @param array{'col': string, 'mode': string} $orderby        query ordder by
     *
     * @return int[]|false return entities list of false on failure
     */
    public static function getIds(
        $page = 0,
        $pageSize = 0,
        $sortCallback = null,
        $filterCallback = null,
        $orderby = [
            'col'  => 'id',
            'mode' => 'ASC',
        ]
    ) {
        return parent::getIdsFromDatabase($page, $pageSize, $sortCallback, $filterCallback, $orderby);
    }

    /**
     * Execute a callback on selected entities
     *
     * @param callable                             $callback       callback function
     * @param int<0, max>                          $page           current page, if $pageSize is 0 o 1 $pase is the offset
     * @param int<0, max>                          $pageSize       page size, 0 return all entities
     * @param ?callable                            $sortCallback   sort function on items result
     * @param ?callable                            $filterCallback filter on items result
     * @param array{'col': string, 'mode': string} $orderby        query ordder by
     *
     * @return bool return true on success or false on failure
     */
    public static function listCallback(
        $callback,
        $page = 0,
        $pageSize = 0,
        $sortCallback = null,
        $filterCallback = null,
        $orderby = [
            'col'  => 'id',
            'mode' => 'ASC',
        ]
    ) {
        try {
            if (!is_callable($callback)) {
                throw new Exception('Callback is not callable');
            }

            $ids = static::getIds($page, $pageSize, $sortCallback, $filterCallback, $orderby);
            if ($ids === false) {
                throw new Exception('Error getting ids');
            }

            foreach ($ids as $id) {
                $entity = static::getById($id);
                if ($entity === false) {
                    continue;
                }
                call_user_func($callback, $entity);
            }
        } catch (Exception $e) {
            DUP_PRO_Log::trace(SnapLog::getTextException($e));
            return false;
        } catch (Error $e) {
            DUP_PRO_Log::trace(SnapLog::getTextException($e));
            return false;
        }

        return true;
    }

    /**
     * Delete all entity of current type
     *
     * @return int<0,max>|false The number of rows updated, or false on error.
     */
    public static function deleteAll()
    {
        $numDeleted = 0;

        $result = self::listCallback(function ($entity) use ($numDeleted) {
            /** @var static $entity */
            if ($entity->delete() === false) {
                DUP_PRO_Log::trace('Can\'t delete entity ' . $entity->getId());
            } else {
                $numDeleted++;
            }
        });

        return ($result ? $numDeleted : false);
    }
}
