<?php

/**
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Installer\Package;

use Duplicator\Installer\Addons\ProBase\AbstractLicense;

class ArchiveDescriptor
{
    const SECURE_MODE_NONE        = 0;
    const SECURE_MODE_INST_PWD    = 1;
    const SECURE_MODE_ARC_ENCRYPT = 2;

    /** @var string */
    public $dup_type = 'pro';
    /** @var string */
    public $created = '';
    /** @var string */
    public $version_dup = '';
    /** @var string */
    public $version_wp = '';
    /** @var string */
    public $version_db = '';
    /** @var string */
    public $version_php = '';
    /** @var string */
    public $version_os = '';
    /** @var string */
    public $blogname = '';
    /** @var bool */
    public $exportOnlyDB = false;
    /** @var int<0,2> */
    public $secure_on = self::SECURE_MODE_NONE;
    /** @var string */
    public $secure_pass = '';
    /** @var ?string */
    public $dbhost = null;
    /** @var ?string */
    public $dbname = null;
    /** @var ?string */
    public $dbuser = null;
    /** @var ?string */
    public $cpnl_host = null;
    /** @var ?string */
    public $cpnl_user = null;
    /** @var ?string */
    public $cpnl_pass = null;
    /** @var ?string */
    public $cpnl_enable = null;
    /** @var string */
    public $wp_tableprefix = '';
    /** @var int<0, 2> */
    public $mu_mode = 0;
    /** @var int<0, 2> */
    public $mu_generation = 0;
    /** @var string[] */
    public $mu_siteadmins = [];
    /** @var DescriptorSubsite[] */
    public $subsites = [];
    /** @var int */
    public $main_site_id = 1;
    /** @var bool */
    public $mu_is_filtered = false;
    /** @var int */
    public $license_limit = 0;
    /** @var int */
    public $license_type = AbstractLicense::TYPE_UNLICENSED;
    /** @var ?DescriptorDBInfo */
    public $dbInfo = null;
    /** @var ?DescriptorPackageInfo */
    public $packInfo = null;
    /** @var ?DescriptorFileInfo*/
    public $fileInfo = null;
    /** @var ?DescriptorWpInfo */
    public $wpInfo = null;
    /** @var int<-1,max> */
    public $defaultStorageId = -1;
    /** @var string[] */
    public $components = [];
    /** @var string[] */
    public $opts_delete = [];
    /** @var array<string, mixed> */
    public $brand = [];
    /** @var array<string, mixed> */
    public $overwriteInstallerParams = [];
    /** @var string */
    public $installer_base_name = '';
    /** @var string */
    public $installer_backup_name = '';
    /** @var string */
    public $package_name = '';
    /** @var string */
    public $package_hash = '';
    /** @var string */
    public $package_notes = '';
}
