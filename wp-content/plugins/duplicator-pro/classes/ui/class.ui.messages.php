<?php

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Used to generate a thick box inline dialog such as an alert or confirm pop-up
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2
 *
 * @package    Duplicator
 * @subpackage classes/ui
 * @copyright  (c) 2017, Snapcreek LLC
 */

use Duplicator\Libs\Snap\SnapJson;

class DUP_PRO_UI_Messages
{
    const UNIQUE_ID_PREFIX = 'dup_ui_msg_';
    const NOTICE           = 'updated';
    const WARNING          = 'update-nag';
    const ERROR            = 'error';

    /** @var int */
    private static $unique_id = 0;
    /** @var string */
    private $id = '';
    /** @var string */
    public $type = self::NOTICE;
    /** @var string */
    public $content = '';
    /** @var string */
    public $wrap_tag = 'p';
    /** @var string */
    public $wrap_cont_tag = 'p';
    /** @var bool */
    public $hide_on_init = true;
    /** @var bool */
    public $is_dismissible = false;
    /** @var int delay in milliseconds */
    public $auto_hide_delay = 0;
    /** @var string */
    public $callback_on_show = '';
    /** @var string */
    public $callback_on_hide = '';

    /**
     * Class constructor
     *
     * @param string $content Content of the message
     * @param string $type    Type of the message (NOTICE, WARNING, ERROR)
     */
    public function __construct($content = '', $type = self::NOTICE)
    {
        self::$unique_id++;
        $this->id = self::UNIQUE_ID_PREFIX . self::$unique_id;

        $this->content = (string) $content;
        $this->type    = $type;
    }

    /**
     * Get the classes for the notice
     *
     * @param string[] $classes Additional classes
     *
     * @return string
     */
    protected function get_notice_classes($classes = array())
    {
        if (is_string($classes)) {
            $classes = explode(' ', $classes);
        } elseif (is_array($classes)) {
        } else {
            $classes = array();
        }

        if ($this->is_dismissible) {
            $classes[] = 'is-dismissible';
        }

        $result = array_merge(array('notice', $this->type), $classes);
        return trim(implode(' ', $result));
    }

    /**
     * Initialize the message
     *
     * @param bool $jsBodyAppend If true, the message will be appended to the body tag
     *
     * @return void
     */
    public function initMessage($jsBodyAppend = false)
    {
        $classes = array();
        if ($this->hide_on_init) {
            $classes[] = 'no_display';
        }

        $this->wrap_tag = empty($this->wrap_tag) ? 'p' : $this->wrap_tag;
        $result         = '<div id="' . $this->id . '" class="' . $this->get_notice_classes($classes) . '">' .
            '<' . $this->wrap_cont_tag . ' class="msg-content">' .
            $this->content .
            '</' . $this->wrap_cont_tag . '>' .
            '</div>';

        if ($jsBodyAppend) {
            echo '$("body").append(' . SnapJson::jsonEncode($result) . ');';
        } else {
            echo $result;
        }
    }

    /**
     * Update the message content
     *
     * @param string $jsVarName Name of the variable containing the new content
     * @param bool   $echo      If true, the result will be echoed
     *
     * @return string
     */
    public function updateMessage($jsVarName, $echo = true)
    {
        $result = 'jQuery("#' . $this->id . ' > .msg-content").html(' . $jsVarName . ');';

        if ($echo) {
            echo $result;
            return '';
        } else {
            return $result;
        }
    }

    /**
     * Show the message
     *
     * @param bool $echo If true, the result will be echoed
     *
     * @return string
     */
    public function showMessage($echo = true)
    {
        $callStr = (strlen($this->callback_on_show) ? $this->callback_on_show . ';' : '');
        $result  = 'jQuery("body, html").animate({ scrollTop: 0 }, 500 );';
        $result .= 'jQuery("#' . $this->id . '").fadeIn( "slow", function() { jQuery(this).removeClass("no_display");' . $callStr . ' });';

        if ($this->auto_hide_delay > 0) {
            $result .= 'setTimeout(function () { ' . $this->hideMessage(false) . ' }, ' . $this->auto_hide_delay . ');';
        }

        if ($echo) {
            echo $result;
            return '';
        } else {
            return $result;
        }
    }

    /**
     * Hide the message
     *
     * @param bool $echo If true, the result will be echoed
     *
     * @return string
     */
    public function hideMessage($echo = true)
    {
        $callStr = (strlen($this->callback_on_hide) ? $this->callback_on_hide . ';' : '');
        $result  = 'jQuery("#' . $this->id . '").fadeOut( "slow", function() { jQuery(this).addClass("no_display");' . $callStr . ' });';

        if ($echo) {
            echo $result;
            return '';
        } else {
            return $result;
        }
    }
}
