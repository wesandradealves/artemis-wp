<?php

/**
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") || exit;

/**
 * Variables
 *
 * @var \Duplicator\Core\Controllers\ControllersManager $ctrlMng
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */
?>
<div>
    <b>
        <?php _e('All information sent to the server is anonymous except the license key and email.', 'duplicator-pro'); ?><br>
        <?php _e('No information about storage or package\'s content are sent.', 'duplicator-pro'); ?>
    </b>
</div>
<br>
<div>
    <?php
        _e(
            'Usage tracking for Duplicator helps us better understand our users and their website needs by looking 
            at a range of server and website environments.',
            'duplicator-pro'
        );
        ?>
    <b>
        <?php _e('This allows us to continuously improve our product as well as our Q&A / testing process.', 'duplicator-pro'); ?>
    </b>
    <?php _e('Below is the list of information that Duplicator collects as part of the usage tracking:', 'duplicator-pro'); ?>
</div>
<ul>
    <li>
        <?php
        _e(
            '<b>PHP Version:</b> so we know which PHP versions we have to test against (no one likes whitescreens or log files full of errors).',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>WordPress Version:</b> so we know which WordPress versions to support and test against.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>MySQL Version:</b> so we know which versions of MySQL to support and test against for our custom tables.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Duplicator Version:</b> so we know which versions of Duplicator are potentially responsible for issues when we get bug reports, 
            allowing us to identify issues and release solutions much faster.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Plugins and Themes infos:</b> so we can figure out which ones I can generate compatibility errors with Duplicator.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Site info:</b> General information about the site such as database, file size, number of users, and sites in case it is a multisite. 
            This is useful for us to understand the critical issues of package creation.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Packages infos:</b> Information about the packages created and the type of components included.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Storagesd infos:</b> Information about the type of storage used, 
            this data is useful for us to understand how to improve our support for external storages.(Only anonymized data is sent).',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Templates infos:</b> Information about the template components.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>Schedules infos:</b> Information on how schedules are used.',
            'duplicator-pro'
        );
        ?>
    </li>
    <li>
        <?php
        _e(
            '<b>License key and email and url:</b> If you’re an Duplicator customer, then we use this to determine if there’s an issue with your specific license key, and to link the profile of your site with the configuration of authentication to allow us to determine if there’s issues with your Duplicator authentication.', // phpcs:ignore Generic.Files.LineLength 
            'duplicator-pro'
        );
        ?>
    </li>
</ul>