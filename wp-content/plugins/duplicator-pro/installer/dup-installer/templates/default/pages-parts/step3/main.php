<?php

/**
 *
 * @package templates/default
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\InstState;
use Duplicator\Installer\Core\Params\PrmMng;

$paramsManager = PrmMng::getInstance();
?>

<!-- =========================================
VIEW: STEP 3- INPUT -->
<form id='s3-input-form' method="post" class="content-form" autocomplete="off">
    <div class="main-form-content" >
        <?php
        if (!InstState::dbDoNothing()) {
            dupxTplRender('pages-parts/step3/options');
        } else {
            dupxTplRender('pages-parts/step3/options-disabled');
        } ?>
    </div>
    <div class="footer-buttons margin-top-2">
        <div class="content-left">
        </div>
        <div class="content-right" >
            <button id="s3-next" type="button"  onclick="DUPX.runSiteUpdate()" class="default-btn"> Next <i class="fa fa-caret-right"></i> </button>
        </div>
    </div>
</form>
