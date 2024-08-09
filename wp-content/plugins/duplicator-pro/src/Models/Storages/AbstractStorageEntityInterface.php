<?php

namespace Duplicator\Models\Storages;

/**
 * Used this interface to avoit errors in PHP 5.6 because isn't possibile declare an abstract static method.
 */
interface AbstractStorageEntityInterface
{
    /**
     * Return the storage type
     *
     * @return int
     */
    public static function getSType();

    /**
     * Returns the FontAwesome storage type icon.
     *
     * @return string Returns the font-awesome icon
     */
    public static function getStypeIcon();

    /**
     * Returns the storage type name.
     *
     * @return string
     */
    public static function getStypeName();
}
