<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Models\Storages;

interface StorageAuthInterface
{
    /**
     * Is authorized
     *
     * @return bool
     */
    public function isAuthorized();

    /**
     * Authorized from HTTP request
     *
     * @param string $message Message
     *
     * @return bool True if authorized, false if failed
     */
    public function authorizeFromRequest(&$message = '');

    /**
     * Revokes authorization
     *
     * @param string $message Message
     *
     * @return bool True if revoked, false if failed
     */
    public function revokeAuthorization(&$message = '');
}
