<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

/**
 * Variables
 *
 * @var string $htmlTitle
 * @var ?bool $showSwitchView
 * @var ?bool $showHeaderLinks
 */

$showSwitchView  = !isset($showSwitchView) ? false : $showSwitchView;
$showHeaderLinks = !isset($showHeaderLinks) ? false : $showHeaderLinks;
?>
<div id="header-main-wrapper" >
    <div class="hdr-main">
        <?php echo $htmlTitle; ?>
    </div>
    <div class="hdr-secodary">
        <?php
        if ($showHeaderLinks) {
            ?>
            <div class="wiz-dupx-version" >
                <?php dupxTplRender('parts/header-links/version-link'); ?>
                <span>&nbsp;|&nbsp;</span>
                <?php dupxTplRender('parts/header-links/log-link'); ?>
                <span>&nbsp;|&nbsp;</span>
                <?php dupxTplRender('parts/header-links/help-link'); ?>
            </div>
            <?php
        }
        if ($showSwitchView) {
            dupxTplRender('pages-parts/step1/actions/switch-template');
        }
        ?>
    </div>
</div>