<?php

/**
 * Impost installer page controller
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

namespace Duplicator\Controllers;

use Duplicator\Core\CapMng;
use Duplicator\Core\Controllers\AbstractBlankPageController;
use Duplicator\Utils\Email\EmailSummary;

class EmailSummaryPreviewPageController extends AbstractBlankPageController
{
    /**
     * Class constructor
     */
    protected function __construct()
    {
        $this->pageSlug     = EmailSummary::PREVIEW_SLUG;
        $this->capatibility = CapMng::CAP_SETTINGS;

        add_action('duplicator_render_page_content_' . $this->pageSlug, array($this, 'renderContent'));
    }

    /**
     * Render page content
     *
     * @param string[] $currentLevelSlugs current page menu levels slugs
     *
     * @return void
     */
    public function renderContent($currentLevelSlugs)
    {
        EmailSummary::getInstance()->renderPreview();
    }
}
