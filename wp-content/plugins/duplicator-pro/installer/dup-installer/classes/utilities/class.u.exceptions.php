<?php

/**
 * Custom exceptions
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Dup installer custom exception
 */
class DupxException extends Exception
{
    /** @var string formatted html string */
    protected $longMsg = '';
    /** @var false|array{url: string, label: string} */
    protected $faqLink = false;

    /**
     * Class constructor
     *
     * @param string    $shortMsg     Short message
     * @param string    $longMsg      Long message
     * @param string    $faqLinkUrl   FAQ link URL
     * @param string    $faqLinkLabel FAQ link label
     * @param int       $code         Exception code
     * @param Exception $previous     Previous exception
     */
    public function __construct($shortMsg, $longMsg = '', $faqLinkUrl = '', $faqLinkLabel = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($shortMsg, $code, $previous);
        $this->longMsg = (string) $longMsg;
        if (strlen($faqLinkUrl) > 0) {
            $this->faqLink = array(
                'url'   => $faqLinkUrl,
                'label' => $faqLinkLabel,
            );
        }
    }

    /**
     * Get the long message
     *
     * @return string
     */
    public function getLongMsg()
    {
        return $this->longMsg;
    }

    /**
     * Check is have faq link
     *
     * @return bool
     */
    public function haveFaqLink()
    {
        return $this->faqLink !== false;
    }

    /**
     * Get FAQ URL
     *
     * @return string
     */
    public function getFaqLinkUrl()
    {
        if ($this->haveFaqLink()) {
            return $this->faqLink['url'];
        } else {
            return '';
        }
    }

    /**
     * Get FAQ label
     *
     * @return string
     */
    public function getFaqLinkLabel()
    {
        if ($this->haveFaqLink()) {
            return $this->faqLink['label'];
        } else {
            return '';
        }
    }

    /**
     * Custom string representation of object
     *
     * @return string
     */
    public function __toString()
    {
        $result = __CLASS__ . ": [{$this->code}]: {$this->message}";
        if ($this->haveFaqLink()) {
            $result .= "\n\tSee FAQ " . $this->faqLink['label'] . ': ' . $this->faqLink['url'];
        }
        if (!empty($this->longMsg)) {
            $result .= "\n\t" . strip_tags($this->longMsg);
        }
        $result .= "\n";
        return $result;
    }
}
