<?php

/**
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Package;

use stdClass;

/**
 * The wp descriptor is used to store the wp meta data
 */
class DescriptorWpInfo
{
    /** @var string */
    public $version = '';
    /** @var bool */
    public $is_multisite = false;
    /** @var int */
    public $network_id = 1;
    /** @var string */
    public $targetRoot = '';
    /** @var string[] */
    public $targetPaths = [];
    /** @var array<object{ID: int, user_login: string}> */
    public $adminUsers = [];
    /** @var ?stdClass */
    public $configs = null;
    /** @var DescriptorPlugin[] */
    public $plugins = [];
    /** @var DescriptorTheme[] */
    public $themes = [];

    /**
     * Class constructor.
     */
    public function __construct()
    {
        $this->configs             = new stdClass();
        $this->configs->defines    = new stdClass();
        $this->configs->realValues = new stdClass();
    }
}
