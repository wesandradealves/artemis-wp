<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined('ABSPATH') || defined('DUPXABSPATH') || exit;

use Duplicator\Installer\Core\Hooks\HooksMng;

/**
 * Variables
 *
 *  @var string $paramView
 */

$archiveConfig = DUPX_ArchiveConfig::getInstance();
?>
<table cellspacing="0" class="header-wizard">
    <tr>
        <td style="width:100%;">
            <div class="dupx-branding-header">
                <?php
                if (isset($archiveConfig->brand['logo']) && !empty($archiveConfig->brand['logo'])) {
                    echo $archiveConfig->brand['logo'];
                } else {
                    ?>
                    <i class="fa fa-bolt fa-sm"></i> <?php echo HooksMng::getInstance()->applyFilters('dupx_main_header', 'Duplicator PRO'); ?>
                    <?php
                }
                ?>
            </div>
        </td>
        <td class="wiz-dupx-version">
            <?php dupxTplRender('parts/header-links/version-link'); ?>
            <div style="padding: 6px 0">
                <?php if ($paramView !== 'help') { ?>
                    <?php dupxTplRender('parts/header-links/log-link'); ?>
                    <span>&nbsp;|&nbsp;</span>
                    <?php dupxTplRender('parts/header-links/help-link', [ 'paramView' => $paramView ]); ?>
                <?php } else { ?>
                    &nbsp;
                <?php } ?>
            </div>
        </td>
    </tr>
</table>
