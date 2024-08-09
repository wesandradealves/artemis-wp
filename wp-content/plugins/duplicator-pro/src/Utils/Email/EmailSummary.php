<?php

namespace Duplicator\Utils\Email;

use Exception;
use DUP_PRO_Log;
use DUP_PRO_Package;
use DUP_PRO_Global_Entity;
use Duplicator\Core\CapMng;
use DUP_PRO_Schedule_Entity;
use Duplicator\Libs\Snap\SnapWP;
use Duplicator\Core\Views\TplMng;
use Duplicator\Core\Models\AbstractEntitySingleton;
use Duplicator\Models\Storages\AbstractStorageEntity;
use Duplicator\Controllers\EmailSummaryPreviewPageController;
use Duplicator\Utils\CronUtils;
use WP_User;

/**
 * Email Summary
 */
class EmailSummary extends AbstractEntitySingleton
{
    const SEND_FREQ_NEVER   = 'never';
    const SEND_FREQ_DAILY   = 'daily';
    const SEND_FREQ_WEEKLY  = 'weekly';
    const SEND_FREQ_MONTHLY = 'monthly';

    const PREVIEW_SLUG = 'duplicator-pro-email-summary-preview';
    const CRON_HOOK    = 'duplicator_pro_email_summary_cron';

    /** @var int[] Manual package ids */
    private $manualPackageIds = [];

    /** @var array<array<int, int[]>> Scheduled package ids */
    private $scheduledPackageIds = [];

    /** @var int[] Failed package ids */
    private $failedPackageIds = [];

    /** @var int[] info about created schedules*/
    private $scheduleIds = [];

    /** @var int[] info about created storages*/
    private $storageIds = [];

    /**
     * Constructor
     */
    protected function __construct()
    {
        //do nothing
    }

    /**
     * Init class
     *
     * @return void
     */
    public function init()
    {
        //Storage hooks
        add_action('duplicator_pro_after_storage_create', [$this, 'addStorage']);
        add_action('duplicator_pro_after_storage_delete', [$this, 'removeStorage']);

        //Schedule hooks
        add_action('duplicator_pro_after_schedule_create', [$this, 'addSchedule']);
        add_action('duplicator_pro_after_schedule_delete', [$this, 'removeSchedule']);

        //Package hooks
        add_action('duplicator_pro_build_completed', [$this, 'addPackage']);
        add_action('duplicator_pro_build_fail', [$this, 'addFailed']);

        //Set cron action
        add_action(self::CRON_HOOK, [$this, 'send']);
    }

    /**
     * Init static hooks
     *
     * @return void
     */
    public static function initHooks()
    {
        //Init Cron hooks
        add_action('duplicator_pro_after_activation', [__CLASS__, 'activationAction']);
        add_action('duplicator_pro_after_deactivation', [__CLASS__, 'deactivationAction']);
    }

    /**
     * Updates the WP Cron job base on frequency or settings
     *
     * @param string $frequency The frequency
     *
     * @return bool True if the cron was updated or false on error
     */
    private static function updateCron($frequency = '')
    {
        if (strlen($frequency) === 0) {
            $frequency = DUP_PRO_Global_Entity::getInstance()->getEmailSummaryFrequency();
        }

        if ($frequency === self::SEND_FREQ_NEVER) {
            if (wp_next_scheduled(self::CRON_HOOK)) {
                return is_int(wp_clear_scheduled_hook(self::CRON_HOOK));
            } else {
                return true;
            }
        } else {
            if (wp_next_scheduled(self::CRON_HOOK) && !is_int(wp_clear_scheduled_hook(self::CRON_HOOK))) {
                return false;
            }

            return (wp_schedule_event(
                self::getFirstRunTime($frequency),
                self::getCronSchedule($frequency),
                self::CRON_HOOK
            ) === true);
        }
    }

    /**
     * Init cron on activation
     *
     * @return void
     */
    public static function activationAction()
    {
        $frequency = DUP_PRO_Global_Entity::getInstance()->getEmailSummaryFrequency();
        if ($frequency === self::SEND_FREQ_NEVER) {
            return;
        }

        if (self::updateCron($frequency) == false) {
            DUP_PRO_Log::trace("FAILED TO INIT EMAIL SUMMARY CRON. Frequency: {$frequency}");
        }
    }

    /**
     * Removes cron on deactivation
     *
     * @return void
     */
    public static function deactivationAction()
    {
        if (self::updateCron(self::SEND_FREQ_NEVER) == false) {
            DUP_PRO_Log::trace("FAILED TO REMOVE EMAIL SUMMARY CRON.");
        }
    }

    /**
     * Update next send time on frequency setting change
     *
     * @param string $oldFrequency The old frequency
     * @param string $newFrequency The new frequency
     *
     * @return bool True if the cron was updated or false on error
     */
    public static function updateFrequency($oldFrequency, $newFrequency)
    {
        if ($oldFrequency === $newFrequency) {
            return true;
        }

        return self::updateCron($newFrequency);
    }

    /**
     * Get the cron schedule
     *
     * @param string $frequency The frequency
     *
     * @return string
     */
    private static function getCronSchedule($frequency)
    {
        switch ($frequency) {
            case self::SEND_FREQ_DAILY:
                return CronUtils::INTERVAL_DAILTY;
            case self::SEND_FREQ_WEEKLY:
                return CronUtils::INTERVAL_WEEKLY;
            case self::SEND_FREQ_MONTHLY:
                return CronUtils::INTERVAL_MONTHLY;
            default:
                throw new Exception("Unknown frequency: " . $frequency);
        }
    }

    /**
     * Set next send time based on frequency
     *
     * @param string $frequency Frequency
     *
     * @return int
     */
    private static function getFirstRunTime($frequency)
    {
        switch ($frequency) {
            case self::SEND_FREQ_DAILY:
                $firstRunTime = strtotime('tomorrow 14:00');
                break;
            case self::SEND_FREQ_WEEKLY:
                $firstRunTime = strtotime('next monday 14:00');
                break;
            case self::SEND_FREQ_MONTHLY:
                $firstRunTime = strtotime('first day of next month 14:00');
                break;
            case self::SEND_FREQ_NEVER:
                return 0;
            default:
                throw new Exception("Unknown frequency: " . $frequency);
        }

        return $firstRunTime - SnapWP::getGMTOffset();
    }

    /**
     * Render the page.
     *
     * @return void
     */
    public function renderPreview()
    {
        TplMng::getInstance()->render('mail/email_summary', [
            'packages'  => $this->getPackagesInfo(),
            'storages'  => $this->getStoragesInfo(),
            'schedules' => $this->getSchedulesInfo(),
        ]);
    }

    /**
     * Returns the preview link
     *
     * @return string
     */
    public static function getPreviewLink()
    {
        return EmailSummaryPreviewPageController::getInstance()->getPageUrl();
    }


    /**
     * Add storage info
     *
     * @param int $storageId Storage id
     *
     * @return void
     */
    public function addStorage($storageId)
    {
        $this->storageIds[] = $storageId;
        $this->save();
    }

    /**
     * Remove storage info
     *
     * @param int $storageId Storage id to remove
     *
     * @return void
     */
    public function removeStorage($storageId)
    {
        $key = array_search($storageId, $this->storageIds);
        if ($key !== false) {
            array_splice($this->storageIds, $key, 1);
        }

        $this->save();
    }

    /**
     * Add schedule id
     *
     * @param DUP_PRO_Schedule_Entity $schedule Storage entity
     *
     * @return void
     */
    public function addSchedule(DUP_PRO_Schedule_Entity $schedule)
    {
        $this->scheduleIds[] = $schedule->getId();
        $this->save();
    }

    /**
     * Remove schedule id
     *
     * @param int $scheduleId Schedule id to remove
     *
     * @return void
     */
    public function removeSchedule($scheduleId)
    {
        $key = array_search($scheduleId, $this->scheduleIds);
        if ($key !== false) {
            array_splice($this->scheduleIds, $key, 1);
        }

        $this->save();
    }

    /**
     * Add package id
     *
     * @param DUP_PRO_Package $package The package
     *
     * @return void
     */
    public function addPackage(DUP_PRO_Package $package)
    {
        if ($package->schedule_id > 0) {
            $this->scheduledPackageIds[$package->schedule_id][] = $package->ID;
        } else {
            $this->manualPackageIds[] = $package->ID;
        }

        $this->save();
    }

    /**
     * Add package id
     *
     * @param DUP_PRO_Package $package The package
     *
     * @return void
     */
    public function addFailed(DUP_PRO_Package $package)
    {
        $this->failedPackageIds[] = $package->ID;
        $this->save();
    }

    /**
     * Returns info about created packages
     *
     * @return array<int|string, array<string, string|int>>
     */
    private function getPackagesInfo()
    {
        $packagesInfo = [];
        foreach ($this->scheduledPackageIds as $scheduleId => $packageIds) {
            if (($scheduleInfo = $this->getSingleScheduleInfo($scheduleId)) === false) {
                $scheduleInfo = [
                    'name'     => __('[Schedule Deleted]', 'duplicator-pro'),
                    'storages' => __('N/A', 'duplicator-pro'),
                ];
            }

            $packagesInfo[] = array_merge($scheduleInfo, ['count' => count($packageIds)]);
        }

        if (count($this->manualPackageIds) > 0) {
            $packagesInfo['manual'] = [
                'name'     => __('Manual', 'duplicator-pro'),
                'storages' => __('N/A', 'duplicator-pro'),
                'count'    => count($this->manualPackageIds),
            ];
        }

        if (count($this->failedPackageIds) > 0) {
            $packagesInfo['failed'] = [
                'name'     => __('Failed', 'duplicator-pro'),
                'storages' => __('N/A', 'duplicator-pro'),
                'count'    => count($this->failedPackageIds),
            ];
        }

        return $packagesInfo;
    }

    /**
     * Returns info about created schedules
     *
     * @return array<array{'name': string, 'storages': string}>
     */
    private function getSchedulesInfo()
    {
        $schedulesInfo = [];
        foreach ($this->scheduleIds as $scheduleId) {
            if (($scheduleInfo = $this->getSingleScheduleInfo($scheduleId)) === false) {
                DUP_PRO_Log::trace("A Schedule with the ID {$scheduleId} was not found.");
                continue;
            }

            $schedulesInfo[] = $scheduleInfo;
        }

        return $schedulesInfo;
    }

    /**
     * Get schedule info or false if it doesn't exist
     *
     * @param int $scheduleId The schedule id
     *
     * @return array{'name': string, 'storages': string}|false
     */
    private function getSingleScheduleInfo($scheduleId)
    {
        if (($schedule = DUP_PRO_Schedule_Entity::getById($scheduleId)) === false) {
            return false;
        }

        $result['name']     = $schedule->name;
        $result['storages'] = '';
        foreach ($schedule->storage_ids as $i => $storageId) {
            if (($storageInfo = $this->getSingleStorageInfo($storageId)) === false) {
                continue;
            }

            $seperator           = ($i == count($schedule->storage_ids) - 1) ? '' : ', ';
            $result['storages'] .= $storageInfo['name'] . $seperator;
        }

        return $result;
    }

    /**
     * Returns info about created storages
     *
     * @return array<array{'name': string, 'type': string}>
     */
    private function getStoragesInfo()
    {
        $storagesInfo = [];
        foreach ($this->storageIds as $storageId) {
            if (($storageInfo = $this->getSingleStorageInfo($storageId)) === false) {
                DUP_PRO_Log::trace("A Storage with the ID {$storageId} was not found.");
                continue;
            }

            $storagesInfo[] = $storageInfo;
        }

        return $storagesInfo;
    }

    /**
     * Get storage info
     *
     * @param int $storageId The storage id
     *
     * @return array{'name': string, 'type': string}|false
     */
    private function getSingleStorageInfo($storageId)
    {
        if (($storage = AbstractStorageEntity::getById($storageId)) === false) {
            return false;
        }

        return [
            'name' => $storage->getName(),
            'type' => $storage->getStypeName(),
        ];
    }

    /**
     * Send email
     *
     * @return void
     */
    public function send()
    {
        $recipients = DUP_PRO_Global_Entity::getInstance()->getEmailSummaryRecipients();
        $frequency  = DUP_PRO_Global_Entity::getInstance()->getEmailSummaryFrequency();
        if (count($recipients) === 0 || $frequency === self::SEND_FREQ_NEVER) {
            return;
        }

        $parsedHomeUrl = wp_parse_url(home_url());
        $siteDomain    = $parsedHomeUrl['host'];

        if (is_multisite() && isset($parsedHomeUrl['path'])) {
            $siteDomain .= $parsedHomeUrl['path'];
        }

        $subject = sprintf(
            esc_html_x(
                'Your Weekly Duplicator Summary for %s',
                '%s is the site domain',
                'duplicator-pro'
            ),
            $siteDomain
        );

        $content = TplMng::getInstance()->render('mail/email_summary', [
            'packages'  => $this->getPackagesInfo(),
            'storages'  => $this->getStoragesInfo(),
            'schedules' => $this->getSchedulesInfo(),
        ], false);

        add_filter('wp_mail_content_type', [$this, 'getMailContentType']);
        if (!wp_mail($recipients, $subject, $content)) {
            DUP_PRO_Log::trace("FAILED TO SEND EMAIL SUMMARY.");
            DUP_PRO_Log::traceObject("RECIPIENTS: ", $recipients);
            return;
        }

        $this->reset();
    }

    /**
     * Get mail content type
     *
     * @return string
     */
    public function getMailContentType()
    {
        return 'text/html';
    }

    /**
     * Get default recipient emails
     *
     * @return array<string>
     */
    public static function getDefaultRecipients()
    {
        $recipients = [];

        $adminEmail = get_option('admin_email');
        if (!empty($adminEmail)) {
            $recipients[] = $adminEmail;
        }

        return $recipients;
    }

    /**
     * Get default recipient emails
     *
     * @return array<string>
     */
    public static function getRecipientSuggestions()
    {
        $recipients = [];
        foreach (self::getDefaultRecipients() as $recipient) {
            if (in_array($recipient, DUP_PRO_Global_Entity::getInstance()->getEmailSummaryRecipients())) {
                continue;
            }

            $recipients[] = $recipient;
        }

        return $recipients;
    }

    /**
     * Get all frequency options
     *
     * @return array<string, string>
     */
    public static function getAllFrequencyOptions()
    {
        return [
            self::SEND_FREQ_NEVER   => esc_html__('Never', 'duplicator-pro'),
            self::SEND_FREQ_DAILY   => esc_html__('Daily', 'duplicator-pro'),
            self::SEND_FREQ_WEEKLY  => esc_html__('Weekly', 'duplicator-pro'),
            self::SEND_FREQ_MONTHLY => esc_html__('Monthly', 'duplicator-pro'),
        ];
    }

    /**
     * Get the frequency text displayed in the email
     *
     * @return string
     */
    public static function getFrequencyText()
    {
        switch (DUP_PRO_Global_Entity::getInstance()->getEmailSummaryFrequency()) {
            case self::SEND_FREQ_DAILY:
                return esc_html__('day', 'duplicator-pro');
            case self::SEND_FREQ_MONTHLY:
                return esc_html__('month', 'duplicator-pro');
            case self::SEND_FREQ_WEEKLY:
            default:
                return esc_html__('week', 'duplicator-pro');
        }
    }


    /**
     * Return entity type identifier
     *
     * @return string
     */
    public static function getType()
    {
        return 'EmailSummary';
    }
}
