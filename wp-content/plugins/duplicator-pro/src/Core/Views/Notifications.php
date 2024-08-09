<?php

namespace Duplicator\Core\Views;

use DUP_PRO_Global_Entity;
use Duplicator\Addons\ProBase\License\License;
use Duplicator\Core\CapMng;
use Duplicator\Core\Upgrade\UpgradePlugin;
use Duplicator\Core\Views\TplMng;

/**
 * Notifications.
 */
class Notifications
{
    /**
     * Source of notifications content.
     *
     * @var string
     */
    const SOURCE_URL = 'https://notifications.duplicator.com/dp-notifications.json';

    /**
     * WordPress option key containing notification data
     *
     * @var string
     */
    const DUPLICATOR_PRO_NOTIFICATIONS_OPT_KEY = 'duplicator_pro_notifications';

    /**
     * WordPress option key containing notification data
     *
     * @var string
     */
    const DUPLICATOR_PRO_BEFORE_PACKAGES_HOOK = 'duplicator_pro_before_packages_table_action';

    /**
     * Duplicator notifications dismiss nonce key
     *
     * @var string
     */
    const DUPLICATOR_NOTIFICATION_NONCE_KEY = 'duplicator-notification-dismiss';

    /**
     * Option value.
     *
     * @var bool|array{update: int, feed: mixed[], events: mixed[], dismissed: mixed[]}
     */
    private static $option = false;

    /**
     * Initialize class.
     *
     * @return void
     */
    public static function init()
    {
        if (
            !CapMng::can(CapMng::CAP_LICENSE, false) ||
            !DUP_PRO_Global_Entity::getInstance()->isAmNoticesEnabled()
        ) {
            return;
        }

        self::update();

        add_action(self::DUPLICATOR_PRO_BEFORE_PACKAGES_HOOK, array(__CLASS__, 'output'));
    }

    /**
     * Dismis notification.
     *
     * @param string $id Notification id.
     *
     * @return bool
     */
    public static function dismiss($id)
    {
        $type   = is_numeric($id) ? 'feed' : 'events';
        $option = self::getOption();

        $option['dismissed'][] = $id;
        $option['dismissed']   = array_unique($option['dismissed']);

        // Remove notification.
        if (!is_array($option[$type]) || empty($option[$type])) {
            throw new \Exception('Notification type not set.');
        }

        foreach ($option[$type] as $key => $notification) {
            if ((string)$notification['id'] === (string)$id) {
                unset($option[$type][$key]);

                break;
            }
        }
        return update_option(self::DUPLICATOR_PRO_NOTIFICATIONS_OPT_KEY, $option);
    }

    /**
     * Get option value.
     *
     * @param bool $cache Reference property cache if available.
     *
     * @return array{update: int, feed: mixed[], events: mixed[], dismissed: mixed[]}
     */
    private static function getOption($cache = true)
    {
        if (self::$option && $cache) {
            return self::$option;
        }

        self::$option = get_option(self::DUPLICATOR_PRO_NOTIFICATIONS_OPT_KEY, [
            'update'    => 0,
            'feed'      => [],
            'events'    => [],
            'dismissed' => [],
        ]);

        return self::$option;
    }

    /**
     * Fetch notifications from feed.
     *
     * @return mixed[]
     */
    private static function fetchFeed()
    {
        $response = wp_remote_get(
            self::SOURCE_URL,
            array(
                'timeout'    => 10,
                'user-agent' => self::getUserAgent(),
            )
        );

        if (is_wp_error($response)) {
            return array();
        }

        $body = wp_remote_retrieve_body($response);

        if (empty($body)) {
            return array();
        }

        return self::verify(json_decode($body, true));
    }

    /**
     * Verify notification data before it is saved.
     *
     * @param mixed[] $notifications Array of notifications items to verify.
     *
     * @return mixed[]
     */
    private static function verify($notifications)
    {
        $data = array();
        if (!is_array($notifications) || empty($notifications)) {
            return $data;
        }

        foreach ($notifications as $notification) {
            // Ignore if one of the conditional checks is true:
            //
            // 1. notification message is empty.
            // 2. license type does not match.
            // 3. notification is expired.
            // 4. notification has already been dismissed.
            // 5. notification existed before installing Duplicator.
            // (Prevents bombarding the user with notifications after activation).
            if (
                empty($notification['content']) ||
                !self::isLicenseTypeMatch($notification) ||
                self::isExpired($notification) ||
                self::isDismissed($notification) ||
                self::isExisted($notification)
            ) {
                continue;
            }

            $data[] = $notification;
        }

        return $data;
    }

    /**
     * Verify saved notification data for active notifications.
     *
     * @param mixed[] $notifications Array of notifications items to verify.
     *
     * @return mixed[]
     */
    private static function verifyActive($notifications)
    {
        if (!is_array($notifications) || empty($notifications)) {
            return array();
        }

        $current_timestamp = time();

        // Remove notifications that are not active.
        foreach ($notifications as $key => $notification) {
            if (
                (!empty($notification['start']) && $current_timestamp < strtotime($notification['start'])) ||
                (!empty($notification['end']) && $current_timestamp > strtotime($notification['end']))
            ) {
                unset($notifications[$key]);
            }
        }

        return $notifications;
    }

    /**
     * Get notification data.
     *
     * @return mixed[]
     */
    private static function get()
    {
        $option = self::getOption();

        $feed   = !empty($option['feed']) ? self::verifyActive($option['feed']) : array();
        $events = !empty($option['events']) ? self::verifyActive($option['events']) : array();

        return array_merge($feed, $events);
    }

    /**
     * Get notification count.
     *
     * @return int
     */
    private static function getCount()
    {
        return count(self::get());
    }

    /**
     * Add a new Event Driven notification.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return void
     */
    public static function add($notification)
    {
        if (!self::isValid($notification)) {
            return;
        }

        $option = self::getOption();

        // Notification ID already exists.
        if (!empty($option['events'][$notification['id']])) {
            return;
        }

        $notification = self::verify(array($notification));
        update_option(
            self::DUPLICATOR_PRO_NOTIFICATIONS_OPT_KEY,
            array(
                'update'    => $option['update'],
                'feed'      => $option['feed'],
                'events'    => array_merge($notification, $option['events']),
                'dismissed' => $option['dismissed'],
            )
        );
    }

    /**
     * Determine if notification data is valid.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return bool
     */
    private static function isValid($notification)
    {
        if (empty($notification['id'])) {
            return false;
        }

        return count(self::verify(array($notification))) > 0;
    }

    /**
     * Determine if notification has already been dismissed.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return bool
     */
    private static function isDismissed($notification)
    {
        $option = self::getOption();

        return !empty($option['dismissed']) && in_array($notification['id'], $option['dismissed']);
    }

    /**
     * Determine if license type is match.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return bool
     */
    private static function isLicenseTypeMatch($notification)
    {
        // A specific license type is not required.
        $notification['type'] = (array)$notification['type'];
        if (empty($notification['type'])) {
            return false;
        }

        if (in_array('any', $notification['type']) || in_array('pro', $notification['type'])) {
            return true;
        }

        return in_array(self::getLicenseType(), $notification['type'], true);
    }

    /**
     * Determine if notification is expired.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return bool
     */
    private static function isExpired($notification)
    {
        return !empty($notification['end']) && time() > strtotime($notification['end']);
    }

    /**
     * Determine if notification existed before installing Duplicator Pro.
     *
     * @param mixed[] $notification Notification data.
     *
     * @return bool
     */
    private static function isExisted($notification)
    {
        return UpgradePlugin::getInstallTime() > strtotime($notification['start']);
    }

    /**
     * Update notification data from feed.
     *
     * @return void
     */
    private static function update()
    {
        $option = self::getOption();

        //Only update twice daily
        if (time() < $option['update'] + DAY_IN_SECONDS / 2) {
            return;
        }

        $data = array(
            'update'    => time(),
            'feed'      => self::fetchFeed(),
            'events'    => $option['events'],
            'dismissed' => $option['dismissed'],
        );

        /**
         * Allow changing notification data before it will be updated in database.
         *
         * @param array $data New notification data.
         */
        $data = (array)apply_filters('duplicator_admin_notifications_update_data', $data);

        update_option(self::DUPLICATOR_PRO_NOTIFICATIONS_OPT_KEY, $data);
    }

    /**
     * Enqueue assets on Form Overview admin page.
     *
     * @return void
     */
    private static function enqueues()
    {
        if (!self::getCount()) {
            return;
        }

        wp_enqueue_style(
            'dup-admin-notifications',
            DUPLICATOR_PRO_PLUGIN_URL . "assets/css/admin-notifications.css",
            array(),
            DUPLICATOR_PRO_VERSION
        );

        wp_enqueue_script(
            'dup-admin-notifications',
            DUPLICATOR_PRO_PLUGIN_URL . "assets/js/admin-notifications.js",
            array('jquery'),
            DUPLICATOR_PRO_VERSION,
            true
        );

        wp_localize_script(
            'dup-admin-notifications',
            'dup_admin_notifications',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce(self::DUPLICATOR_NOTIFICATION_NONCE_KEY),
            )
        );
    }

    /**
     * Output notifications on Form Overview admin area.
     *
     * @return void
     */
    public static function output()
    {
        $notificationsData = self::get();

        if (empty($notificationsData)) {
            return;
        }

        $content_allowed_tags = array(
            'br'     => array(),
            'em'     => array(),
            'strong' => array(),
            'span'   => array(
                'style' => array(),
            ),
            'p'      => array(
                'id'    => array(),
                'class' => array(),
            ),
            'a'      => array(
                'href'   => array(),
                'target' => array(),
                'rel'    => array(),
            ),
        );

        $notifications = [];
        foreach ($notificationsData as $notificationData) {
            // Prepare required arguments.
            $notificationData = wp_parse_args(
                $notificationData,
                array(
                    'id'      => 0,
                    'title'   => '',
                    'content' => '',
                    'video'   => '',
                )
            );

            $title   = self::getComponentData($notificationData['title']);
            $content = self::getComponentData($notificationData['content']);

            if (!$title && !$content) {
                continue;
            }

            $notifications[] = array(
                'id'        => $notificationData['id'],
                'title'     => $title,
                'btns'      => self::getButtonsData($notificationData),
                'content'   => wp_kses(wpautop($content), $content_allowed_tags),
                'video_url' => wp_http_validate_url(self::getComponentData($notificationData['video'])),
            );
        }

        self::enqueues();
        TplMng::getInstance()->render(
            'parts/Notifications/main',
            array('notifications' => $notifications)
        );
    }

    /**
     * Retrieve notification's buttons.
     *
     * @param array<string, mixed> $notification Notification data.
     *
     * @return array<int, mixed>
     */
    private static function getButtonsData($notification)
    {
        if (empty($notification['btn']) || !is_array($notification['btn'])) {
            return [];
        }

        $buttons = [];
        if (!empty($notification['btn']['main_text']) && !empty($notification['btn']['main_url'])) {
            $buttons[] = array(
                'type'   => 'primary',
                'text'   => $notification['btn']['main_text'],
                'url'    => self::prepareBtnUrl($notification['btn']['main_url']),
                'target' => '_blank',
            );
        }

        if (!empty($notification['btn']['alt_text']) && !empty($notification['btn']['alt_url'])) {
            $buttons[] = array(
                'type'   => 'secondary',
                'text'   => $notification['btn']['alt_text'],
                'url'    => self::prepareBtnUrl($notification['btn']['alt_url']),
                'target' => '_blank',
            );
        }

        return $buttons;
    }

    /**
     * Retrieve notification's component data by a license type.
     *
     * @param mixed $data Component data.
     *
     * @return false|mixed
     */
    private static function getComponentData($data)
    {
        if (empty($data['license'])) {
            return $data;
        }

        if (!empty($data['license']['pro'])) {
            return $data['license']['pro'];
        }

        $license_type = self::getLicenseType();
        return !empty($data['license'][$license_type]) ? $data['license'][$license_type] : false;
    }

    /**
     * Retrieve the current installation license type (always lowercase).
     *
     * @return string
     */
    private static function getLicenseType()
    {
        return strtolower(License::getLicenseToString());
    }

    /**
     * Prepare button URL.
     *
     * @param string $btnUrl Button url.
     *
     * @return string
     */
    private static function prepareBtnUrl($btnUrl)
    {
        if (empty($btnUrl)) {
            return '';
        }

        $replace_tags = array(
            '{admin_url}' => admin_url(),
        );

        return wp_http_validate_url(str_replace(array_keys($replace_tags), array_values($replace_tags), $btnUrl));
    }

    /**
     * User agent that will be used for the request
     *
     * @return string
     */
    private static function getUserAgent()
    {
        return 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url') . '; Duplicator/Lite-' . DUPLICATOR_PRO_VERSION;
    }
}
