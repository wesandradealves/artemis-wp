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

use Duplicator\Installer\Core\InstState;

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

class DUPX_Validation_test_owrinstall extends DUPX_Validation_abstract_item
{
    protected function runTest()
    {
        if (
            InstState::isRecoveryMode() ||
            InstState::isImportFromBackendMode()
        ) {
            return self::LV_SKIP;
        }

        if (InstState::getInstance()->getMode() === InstState::MODE_OVR_INSTALL) {
            return self::LV_SOFT_WARNING;
        } else {
            return self::LV_GOOD;
        }
    }

    public function getTitle()
    {
        return 'Overwrite Install';
    }

    protected function swarnContent()
    {
        return dupxTplRender('parts/validation/tests/overwrite-install', array(), false);
    }

    protected function goodContent()
    {
        return dupxTplRender('parts/validation/tests/overwrite-install', array(), false);
    }
}
