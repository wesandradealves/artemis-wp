<?php

namespace Duplicator\Utils\UsageStatistics;

use DUP_PRO_Archive_Build_Mode;
use DUP_PRO_DB;
use DUP_PRO_Global_Entity;
use DUP_PRO_ZipArchive_Mode;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Installer\Core\InstState;
use Duplicator\Libs\Snap\SnapUtil;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Package\Create\BuildComponents;
use Exception;

class StatsUtil
{
    /**
     * Get server type
     *
     * @return string
     */
    public static function getServerType()
    {
        if (empty($_SERVER['SERVER_SOFTWARE'])) {
            return 'unknown';
        }
        return SnapUtil::sanitizeNSCharsNewlineTrim(wp_unslash($_SERVER['SERVER_SOFTWARE']));
    }

    /**
     * Get db mode
     *
     * @return string
     */
    public static function getDbBuildMode()
    {
        switch (DUP_PRO_DB::getBuildMode()) {
            case DUP_PRO_DB::BUILD_MODE_MYSQLDUMP:
                return 'mysqldump';
            case DUP_PRO_DB::BUILD_MODE_PHP_MULTI_THREAD:
                return 'php-multi';
            case DUP_PRO_DB::BUILD_MODE_PHP_SINGLE_THREAD:
                return 'php-single';
            default:
                throw new Exception('Unknown db build mode');
        }
    }

    /**
     * Get archive mode
     *
     * @return string
     */
    public static function getArchiveBuildMode()
    {
        $global = DUP_PRO_Global_Entity::getInstance();
        switch ($global->archive_build_mode) {
            case DUP_PRO_Archive_Build_Mode::ZipArchive:
                if ($global->ziparchive_mode == DUP_PRO_ZipArchive_Mode::Multithreaded) {
                    return 'zip-multi';
                } else {
                    return 'zip-single';
                }
            case DUP_PRO_Archive_Build_Mode::DupArchive:
                return 'dup';
            default:
                return 'shellzip';
        }
    }

    /**
     * Return license types
     *
     * @param ?int $type License type, if null will use current license type
     *
     * @return string
     */
    public static function getLicenseType($type = null)
    {
        if ($type == null) {
            $type = License::getType();
        }
        switch ($type) {
            case License::TYPE_PERSONAL:
            case License::TYPE_PERSONAL_AUTO:
                return 'personal';
            case License::TYPE_FREELANCER:
            case License::TYPE_FREELANCER_AUTO:
                return 'freelancer';
            case License::TYPE_BUSINESS:
            case License::TYPE_BUSINESS_AUTO:
                return 'business';
            case License::TYPE_GOLD:
                return 'gold';
            case License::TYPE_BASIC:
                return 'basic';
            case License::TYPE_PLUS:
                return 'plus';
            case License::TYPE_PRO:
                return 'pro';
            case License::TYPE_ELITE:
                return 'elite';
            case License::TYPE_UNLICENSED:
            case License::TYPE_UNKNOWN:
            default:
                return 'unlicensed';
        }
    }

    /**
     * Return license status
     *
     * @return string
     */
    public static function getLicenseStatus()
    {
        switch (License::getLicenseStatus()) {
            case License::STATUS_VALID:
                return 'valid';
            case License::STATUS_INVALID:
                return 'invalid';
            case License::STATUS_INACTIVE:
                return 'inactive';
            case License::STATUS_DISABLED:
                return 'disabled';
            case License::STATUS_SITE_INACTIVE:
                return 'site-inactive';
            case License::STATUS_EXPIRED:
                return 'expired';
            case License::STATUS_OUT_OF_LICENSES:
                return 'out-of-licenses';
            case License::STATUS_UNCACHED:
                return 'uncached';
            case License::STATUS_UNKNOWN:
                return 'unknown';
            default:
                return 'unknown';
        }
    }

    /**
     * Get install type
     *
     * @param int $type Install type
     *
     * @return string
     */
    public static function getInstallType($type)
    {
        switch ($type) {
            case InstState::TYPE_SINGLE:
                return 'single';
            case InstState::TYPE_STANDALONE:
                return 'standalone';
            case InstState::TYPE_MSUBDOMAIN:
                return 'msubdomain';
            case InstState::TYPE_MSUBFOLDER:
                return 'msubfolder';
            case InstState::TYPE_SINGLE_ON_SUBDOMAIN:
                return 'single_on_subdomain';
            case InstState::TYPE_SINGLE_ON_SUBFOLDER:
                return 'single_on_subfolder';
            case InstState::TYPE_SUBSITE_ON_SUBDOMAIN:
                return 'subsite_on_subdomain';
            case InstState::TYPE_SUBSITE_ON_SUBFOLDER:
                return 'subsite_on_subfolder';
            case InstState::TYPE_RBACKUP_SINGLE:
                return 'rbackup_single';
            case InstState::TYPE_RBACKUP_MSUBDOMAIN:
                return 'rbackup_msubdomain';
            case InstState::TYPE_RBACKUP_MSUBFOLDER:
                return 'rbackup_msubfolder';
            case InstState::TYPE_RECOVERY_SINGLE:
                return 'recovery_single';
            case InstState::TYPE_RECOVERY_MSUBDOMAIN:
                return 'recovery_msubdomain';
            case InstState::TYPE_RECOVERY_MSUBFOLDER:
                return 'recovery_msubfolder';
            default:
                return 'not_set';
        }
    }

    /**
     * Get stats components
     *
     * @param string[] $components Components
     *
     * @return string
     */
    public static function getStatsComponents($components)
    {
        $result = [];
        foreach ($components as $component) {
            switch ($component) {
                case BuildComponents::COMP_DB:
                    $result[] = 'db';
                    break;
                case BuildComponents::COMP_CORE:
                    $result[] = 'core';
                    break;
                case BuildComponents::COMP_PLUGINS:
                    $result[] = 'plugins';
                    break;
                case BuildComponents::COMP_PLUGINS_ACTIVE:
                    $result[] = 'plugins_active';
                    break;
                case BuildComponents::COMP_THEMES:
                    $result[] = 'themes';
                    break;
                case BuildComponents::COMP_THEMES_ACTIVE:
                    $result[] = 'themes_active';
                    break;
                case BuildComponents::COMP_UPLOADS:
                    $result[] = 'uploads';
                    break;
                case BuildComponents::COMP_OTHER:
                    $result[] = 'other';
                    break;
            }
        }
        return implode(',', $result);
    }

    /**
     * Get am family plugins
     *
     * @return string
     */
    public static function getAmFamily()
    {
        $result   = [];
        $result[] = 'dup-pro';
        if (SnapWP::isPluginInstalled('duplicator/duplicator.php')) {
            $result[] = 'dup-lite';
        }

        return implode(',', $result);
    }

    /**
     * Get logic modes
     *
     * @param string[] $modes Logic modes
     *
     * @return string
     */
    public static function getLogicModes($modes)
    {
        $result = [];
        foreach ($modes as $mode) {
            switch ($mode) {
                case InstState::LOGIC_MODE_IMPORT:
                    $result[] = 'IMPORT';
                    break;
                case InstState::LOGIC_MODE_RECOVERY:
                    $result[] = 'RECOVERY';
                    break;
                case InstState::LOGIC_MODE_CLASSIC:
                    $result[] = 'CLASSIC';
                    break;
                case InstState::LOGIC_MODE_OVERWRITE:
                    $result[] = 'OVERWRITE';
                    break;
                case InstState::LOGIC_MODE_BRIDGE:
                    $result[] = 'BRIDGE';
                    break;
                case InstState::LOGIC_MODE_RESTORE_BACKUP:
                    $result[] = 'RESTORE';
                    break;
            }
        }
        return implode(',', $result);
    }

    /**
     * Get template
     *
     * @param string $template Template
     *
     * @return string
     */
    public static function getTemplate($template)
    {
        switch ($template) {
            case 'base':
                return 'CLASSIC_BASE';
            case 'import-base':
                return 'IMPORT_BASE';
            case 'import-advanced':
                return 'IMPORT_ADV';
            case 'recovery':
                return 'RECOVERY';
            case 'default':
            default:
                return 'CLASSIC_ADV';
        }
    }

    /**
     * Sanitize fields with rule string
     * [nullable][type][|max:number]
     * - ?string|max:25
     * - int
     *
     * @param array<string, mixed>  $data  Data
     * @param array<string, string> $rules Rules
     *
     * @return array<string, mixed>
     */
    public static function sanitizeFields($data, $rules)
    {
        foreach ($data as $key => $val) {
            if (!isset($rules[$key])) {
                continue;
            }

            $matches = null;
            if (preg_match('/(\??)(int|float|bool|string)(?:\|max:(\d+))?/', $rules[$key], $matches) !== 1) {
                throw new Exception("Invalid sanitize rule: {$rules[$key]}");
            }

            $nullable = $matches[1] === '?';
            $type     = $matches[2];
            $max      = isset($matches[3]) ? (int) $matches[3] : PHP_INT_MAX;

            if ($nullable && $val === null) {
                continue;
            }

            switch ($type) {
                case 'int':
                    $data[$key] = (int) $val;
                    break;
                case 'float':
                    $data[$key] = (float) $val;
                    break;
                case 'bool':
                    $data[$key] = (bool) $val;
                    break;
                case 'string':
                    $data[$key] = substr((string) $val, 0, $max);
                    break;
                default:
                    throw new Exception("Unknown sanitize rule: {$rules[$key]}");
            }
        }

        return $data;
    }
}
