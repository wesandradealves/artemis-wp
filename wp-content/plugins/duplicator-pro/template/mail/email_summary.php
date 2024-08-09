<?php

/**
 * Duplicator schedule success mail
 *
 * @package   Duplicator
 * @copyright (c) 2022, Snap Creek LLC
 */

defined("ABSPATH") or die("");

use Duplicator\Utils\Email\EmailHelper;
use Duplicator\Utils\Email\EmailSummary;

/**
 * Variables
 *
 * @var array<string, mixed> $tplData
 */

$scheduleStorageMsg = '';
if (count($tplData['schedules']) > 0 && count($tplData['storages']) > 0) {
    $scheduleStorageMsg = __('There were new storages and schedules created!', 'duplicator-pro');
} elseif (count($tplData['schedules']) > 0) {
    $scheduleStorageMsg = __('There were new schedules created!', 'duplicator-pro');
} elseif (count($tplData['storages']) > 0) {
    $scheduleStorageMsg = __('There were new storages created!', 'duplicator-pro');
}
?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width">
        <title><?php _e('Duplicator Pro', 'duplicator-pro'); ?></title>
        <style type="text/css">
            a {
              text-decoration: none;
            }

            @media only screen and (max-width: 599px) {
              table.body .main-tbl {
                width: 95% !important;
              }

              .header {
                padding: 15px 15px 12px 15px !important;
              }

              .header img {
                width: 200px !important;
                height: auto !important;
              }
              .content {
                padding: 30px 40px 20px 40px !important;
              }
            }
        </style>
    </head>
    <body <?php EmailHelper::printStyle('body'); ?>>
        <table <?php EmailHelper::printStyle('table body'); ?>>
            <tr <?php EmailHelper::printStyle('tr'); ?>>
                <td <?php EmailHelper::printStyle('td'); ?>>
                    <table <?php EmailHelper::printStyle('table main-tbl'); ?>>
                        <tr <?php EmailHelper::printStyle('tr'); ?>>
                            <td <?php EmailHelper::printStyle('td logo txt-center'); ?>>
                               <img 
                                    src="<?php echo DUPLICATOR_PRO_PLUGIN_URL . 'assets/img/email-logo.png'; ?>"
                                    alt="logo"
                                    <?php EmailHelper::printStyle('img'); ?>
                                > 
                            </td>
                        </tr>
                        <tr <?php EmailHelper::printStyle('tr'); ?>>
                            <td <?php EmailHelper::printStyle('td content'); ?>>
                                <table <?php EmailHelper::printStyle('table main-tbl-child'); ?>>
                                    <tr <?php EmailHelper::printStyle('tr'); ?>>
                                        <td <?php EmailHelper::printStyle('td'); ?>>
                                            <h6 <?php EmailHelper::printStyle('h6'); ?>>Hi there!</h6>
                                            <p <?php EmailHelper::printStyle('p subtitle'); ?>>
                                                <?php
                                                printf(
                                                    _x(
                                                        'Here\'s a quick overview of your backups in the past %s.',
                                                        '%s is the frequency of email summaries.',
                                                        'duplicator-pro'
                                                    ),
                                                    EmailSummary::getFrequencyText()
                                                );
                                                ?>
                                            </p>
                                            <?php if (count($tplData['packages']) > 0) : ?>
                                            <table <?php EmailHelper::printStyle('table stats-tbl'); ?>>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('By', 'duplicator-pro'); ?>
                                                    </th>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('Storage(s)', 'duplicator-pro'); ?>
                                                    </th>
                                                    <th <?php EmailHelper::printStyle('th stats-count-cell'); ?>>
                                                        <?php _e('Backups', 'duplicator-pro'); ?>
                                                    </th>
                                                </tr>
                                                <?php foreach ($tplData['packages'] as $id => $packageInfo) : ?>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $packageInfo['name']; ?>
                                                    </td>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $packageInfo['storages']; ?>
                                                    </td>
                                                    <td <?php EmailHelper::printStyle('td stats-cell stats-count-cell'); ?>>
                                                        <?php if ($id !== 'failed') : ?>
                                                            <span <?php EmailHelper::printStyle('txt-orange'); ?>>
                                                                <?php echo $packageInfo['count']; ?>
                                                            </span>
                                                        <?php else : ?>
                                                            <?php echo $packageInfo['count']; ?>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </table>
                                            <?php else : ?>
                                            <p <?php EmailHelper::printStyle('p'); ?>>
                                                <?php echo __('No backups were created in the past week.', 'duplicator-pro'); ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if (strlen($scheduleStorageMsg) > 0) : ?>
                                            <p <?php EmailHelper::printStyle('p subtitle'); ?>>
                                                <?php echo $scheduleStorageMsg; ?>
                                            </p>
                                            <?php endif; ?>
                                            <?php if (count($tplData['schedules']) > 0) : ?>
                                            <p <?php EmailHelper::printStyle('p stats-title'); ?>>
                                                <strong><?php _e('New Schedules:', 'duplicator-pro'); ?></strong>
                                            </p>
                                            <table <?php EmailHelper::printStyle('table stats-tbl'); ?>>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('Schedule Name', 'duplicator-pro'); ?>
                                                    </th>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('Storage(s)', 'duplicator-pro'); ?>
                                                    </th>
                                                </tr>
                                                <?php foreach ($tplData['schedules'] as $scheduleInfo) : ?>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $scheduleInfo['name']; ?>
                                                    </td>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $scheduleInfo['storages']; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </table>
                                            <?php endif; ?>
                                            <?php if (count($tplData['storages']) > 0) : ?>
                                            <p <?php EmailHelper::printStyle('p stats-title'); ?>>
                                            <strong><?php _e('New Storages:', 'duplicator-pro'); ?></strong>
                                            </p>
                                            <table <?php EmailHelper::printStyle('table stats-tbl'); ?>>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('Storage Name', 'duplicator-pro'); ?>
                                                    </th>
                                                    <th <?php EmailHelper::printStyle('th'); ?>>
                                                        <?php _e('Provider', 'duplicator-pro'); ?>
                                                    </th>
                                                </tr>
                                                <?php foreach ($tplData['storages'] as $storageInfo) : ?>
                                                <tr <?php EmailHelper::printStyle('tr'); ?>>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $storageInfo['name']; ?>
                                                    </td>
                                                    <td <?php EmailHelper::printStyle('td stats-cell'); ?>>
                                                        <?php echo $storageInfo['type']; ?>
                                                    </td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </table>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td <?php EmailHelper::printStyle('td unsubscribe'); ?>>
                                <?php
                                printf(
                                    _x(
                                        'This email was auto-generated and sent from %s.',
                                        '%s is an <a> tag with a link to the current website.',
                                        'duplicator-pro'
                                    ),
                                    '<a href="' . get_site_url() . '" ' .
                                    'style="' . EmailHelper::getStyle('footer-link') . '">'
                                    . wp_specialchars_decode(get_bloginfo('name')) . '</a>'
                                );
                                ?>

                                <?php
                                printf(
                                    esc_html_x(
                                        'Learn %1$show to disable%2$s.',
                                        '%1$s and %2$s are opening and closing link tags to the documentation.',
                                        'duplicator-pro'
                                    ),
                                    '<a href="' . DUPLICATOR_PRO_DUPLICATOR_DOCS_URL . 'how-to-disable-email-summaries/" ' .
                                    'style="' . EmailHelper::getStyle('footer-link') . '">',
                                    '</a>'
                                );
                                ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
</html>
    
