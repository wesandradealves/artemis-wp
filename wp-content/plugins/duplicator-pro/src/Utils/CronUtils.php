<?php

namespace Duplicator\Utils;

class CronUtils
{
    const INTERVAL_DAILTY  = 'duplicator_daily_cron';
    const INTERVAL_WEEKLY  = 'duplicator_weekly_cron';
    const INTERVAL_MONTHLY = 'duplicator_monthly_cron';

    /**
     * Init WordPress hooks
     *
     * @return void
     */
    public static function init()
    {
        add_filter('cron_schedules', array(__CLASS__, 'defaultCronIntervals'));
    }

    /**
     * Add duplicator pro cron schedules
     *
     * @return array<string, array<string,int|string>>
     */
    public static function defaultCronIntervals()
    {
        $schedules[self::INTERVAL_DAILTY] = [
            'interval' => DAY_IN_SECONDS,
            'display'  => __('Once a Day', 'duplicator-pro'),
        ];

        $schedules[self::INTERVAL_WEEKLY] = [
            'interval' => WEEK_IN_SECONDS,
            'display'  => __('Once a Week', 'duplicator-pro'),
        ];

        $schedules[self::INTERVAL_MONTHLY] = [
            'interval' => MONTH_IN_SECONDS,
            'display'  => __('Once a Month', 'duplicator-pro'),
        ];

        return $schedules;
    }
}
