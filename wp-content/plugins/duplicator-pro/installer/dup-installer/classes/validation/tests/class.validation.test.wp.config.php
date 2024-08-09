<?php

/**
 * Validation object
 *
 * Standard: PSR-2
 *
 * @link http://www.php-fig.org/psr/psr-2 Full Documentation
 *
 * @package SC\DUPX\U
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\Deploy\ServerConfigs;
use Duplicator\Installer\Core\InstState;

class DUPX_Validation_test_wp_config extends DUPX_Validation_abstract_item
{
    /**
     * @return int
     * @throws Exception
     */
    protected function runTest()
    {
        if (!InstState::isClassicInstall()) {
            return self::LV_SKIP;
        }

        if (DUPX_WPConfig::isSourceWpConfigValid()) {
            return self::LV_PASS;
        } else {
            return self::LV_SOFT_WARNING;
        }
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return 'Wordpress Configuration';
    }

    /**
     * @return string
     */
    protected function swarnContent()
    {
        return dupxTplRender('parts/validation/tests/wp-config-check', array(
            'testResult' => $this->testResult,
            'configPath' => ServerConfigs::getSourceWpConfigPath(),
        ), false);
    }

    /**
     * @return string
     */
    protected function passContent()
    {
        return $this->swarnContent();
    }
}
