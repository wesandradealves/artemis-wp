<?php

defined("ABSPATH") or die("");
/**
 * Utility class working with date time values
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    DUP_PRO
 * @subpackage classes/utilities
 * @copyright  (c) 2017, Snapcreek LLC
 * @license    https://opensource.org/licenses/GPL-3.0 GNU Public License
 * @since      3.0.0
 *
 * @todo Finish Docs
 */
class DUP_PRO_DATE
{
    /**
     * Get local time from GMT
     *
     * @param int $timestamp timestamp
     *
     * @return string
     */
    public static function getLocalTimeFromGMT($timestamp)
    {
        $local_ticks  = self::getLocalTicksFromGMT($timestamp);
        $date_portion = date('M j,', $local_ticks);
        $time_portion = date('g:i:s a', $local_ticks);
        return "$date_portion $time_portion";
    }

    /**
     * Get local ticks from GMT
     *
     * @param int $timestamp timestamp
     *
     * @return int
     */
    public static function getLocalTicksFromGMT($timestamp)
    {
        return $timestamp + \Duplicator\Libs\Snap\SnapWP::getGMTOffset();
    }


    /**
     * Get local time from GMT ticks
     *
     * @param int $ticks timestamp
     *
     * @return string
     */
    public static function getLocalTimeFromGMTTicks($ticks)
    {
        return self::getStandardTime($ticks + \Duplicator\Libs\Snap\SnapWP::getGMTOffset());
    }

    /**
     * Get the current GMT time in ticks
     *
     * @param int $ticks timestamp
     *
     * @return string
     */
    public static function getStandardTime($ticks)
    {
        //return date('D, d M Y H:i:s', $ticks);
        return date('D, d M H:i:s', $ticks);
    }

    /**
     * Returns a string representation of the GMT time in the format of the WP date and time settings
     *
     * @param int  $timestamp   The GMT timestamp
     * @param bool $includeDate Whether to include the date portion
     * @param bool $includeTime Whether to include the time portion
     *
     * @return string
     */
    public static function getWPTimeFromGMTTime($timestamp, $includeDate = true, $includeTime = true)
    {
        $ticks       = self::getLocalTicksFromGMT($timestamp);
        $date_format = get_option('date_format');
        $time_format = get_option('time_format');
        if ($includeDate) {
            $date_portion = date($date_format, $ticks);
        } else {
            $date_portion = '';
        }

        if ($includeTime) {
            $time_portion = date($time_format, $ticks);
        } else {
            $time_portion = '';
        }

        if ($includeDate && $includeTime) {
            $seperator = ' ';
        } else {
            $seperator = '';
        }

        return "$date_portion$seperator$time_portion";
    }
}
