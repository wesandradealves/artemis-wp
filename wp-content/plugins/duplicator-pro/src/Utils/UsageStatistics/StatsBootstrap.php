<?php

namespace Duplicator\Utils\UsageStatistics;

use DUP_PRO_Global_Entity;
use DUP_PRO_Log;
use DUP_PRO_Package;
use Duplicator\Package\Create\BuildComponents;
use Duplicator\Utils\CronUtils;

/**
 * StatsBootstrap
 */
class StatsBootstrap
{
    const USAGE_TRACKING_CRON_HOOK = 'duplicator_usage_tracking_cron';

    /**
     * Init WordPress hooks
     *
     * @return void
     */
    public static function init()
    {
        add_action('duplicator_pro_after_activation', [__CLASS__, 'activationAction']);
        add_action('duplicator_pro_after_deactivation', [__CLASS__, 'deactivationAction']);
        add_action('duplicator_pro_package_transfer_completed', [__CLASS__, 'addPackageBuild']);
        add_action('duplicator_pro_build_fail', [__CLASS__, 'addPackageBuild']);
        add_action('duplicator_after_scan_report', [__CLASS__, 'addSiteSizes'], 10, 2);
        add_action('duplicator_usage_tracking_cron', [__CLASS__, 'sendPluginStatCron']);
    }

    /**
     * Activation action
     *
     * @return void
     */
    public static function activationAction()
    {
        // Set cron
        if (!wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK)) {
            $randomTracking = wp_rand(0, WEEK_IN_SECONDS);
            $timeToStart    = strtotime('next sunday') + $randomTracking;
            wp_schedule_event($timeToStart, CronUtils::INTERVAL_WEEKLY, self::USAGE_TRACKING_CRON_HOOK);
        }

        if (PluginData::getInstance()->getStatus() !== PluginData::PLUGIN_STATUS_ACTIVE) {
            PluginData::getInstance()->setStatus(PluginData::PLUGIN_STATUS_ACTIVE);
            CommStats::pluginSend();
        }
    }

    /**
     * Deactivation action
     *
     * @return void
     */
    public static function deactivationAction()
    {
        // Unschedule custom cron event for cleanup if it's scheduled
        if (wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK)) {
            $timestamp = wp_next_scheduled(self::USAGE_TRACKING_CRON_HOOK);
            wp_unschedule_event($timestamp, self::USAGE_TRACKING_CRON_HOOK);
        }

        PluginData::getInstance()->setStatus(PluginData::PLUGIN_STATUS_INACTIVE);
        CommStats::pluginSend();
    }

    /**
     * Add package build,
     * don't use PluginData::getInstance()->addPackageBuild() directly in hook to avoid useless init
     *
     * @param DUP_PRO_Package $package Package
     *
     * @return void
     */
    public static function addPackageBuild(DUP_PRO_Package $package)
    {
        PluginData::getInstance()->addPackageBuild($package);
    }

    /**
     * Add site size statistics
     *
     * @param DUP_PRO_Package      $package Package
     * @param array<string, mixed> $report  Scan report
     *
     * @return void
     */
    public static function addSiteSizes(DUP_PRO_Package $package, $report)
    {
        $minComponents = [
            BuildComponents::COMP_DB,
            BuildComponents::COMP_CORE,
            BuildComponents::COMP_PLUGINS,
            BuildComponents::COMP_THEMES,
            BuildComponents::COMP_UPLOADS,
            BuildComponents::COMP_OTHER,
        ];

        $componentes = array_intersect($minComponents, $package->components);
        if (array_diff($minComponents, $componentes) !== []) {
            return;
        }

        PluginData::getInstance()->setSiteSize(
            $report['ARC']['USize'],
            $report['ARC']['UFullCount'],
            $report['DB']['SizeInBytes'],
            $report['DB']['TableCount']
        );
    }

    /**
     * Is tracking allowed
     *
     * @return bool
     */
    public static function isTrackingAllowed()
    {
        if (DUPLICATOR_USTATS_DISALLOW) { // @phpstan-ignore-line
            return false;
        }

        return DUP_PRO_Global_Entity::getInstance()->getUsageTracking();
    }

    /**
     * Send plugin statistics
     *
     * @return void
     */
    public static function sendPluginStatCron()
    {
        if (!self::isTrackingAllowed()) {
            return;
        }

        DUP_PRO_Log::trace("CRON: Sending plugin statistics");
        CommStats::pluginSend();
    }
}
