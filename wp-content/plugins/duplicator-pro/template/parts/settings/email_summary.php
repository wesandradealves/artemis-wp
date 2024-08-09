<?php

/**
 * Admin Notifications content.
 *
 * Variables
 *
 * @var \Duplicator\Core\Views\TplMng  $tplMng
 * @var array<string, mixed> $tplData
 */

use Duplicator\Utils\Email\EmailSummary;

defined('ABSPATH') || exit;

$global = DUP_PRO_Global_Entity::getInstance();
?>

<h3 class="title"><?php _e('Email Summary', 'duplicator-pro') ?></h3>
<hr size="1" />
<table class="dup-capabilities-selector-wrapper form-table">
    <tr valign="top">
        <th scope="row"><label><?php _e('Frequency', 'duplicator-pro'); ?></label></th>
        <td>
            <select id="email-summary-frequency" name="_email_summary_frequency">
                <?php foreach (EmailSummary::getAllFrequencyOptions() as $key => $label) : ?>
                    <option value="<?php echo esc_attr((string) $key); ?>" <?php selected($global->getEmailSummaryFrequency(), $key); ?>>
                        <?php echo esc_html($label); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <p class="description">
                <?php
                printf(
                    _x(
                        'You can view the email summary example %1$shere%2$s.',
                        '%1$s and %2$s are the opening and close <a> tags to the summary preview link',
                        'duplicator-pro'
                    ),
                    '<a href="' . EmailSummary::getPreviewLink() . '" target="_blank">',
                    '</a>'
                );
                ?>
            </p>
        </td>
    </tr>
    <tr valign="top">
        <th scope="row"><label><?php _e('Recipients', 'duplicator-pro'); ?></label></th>
        <td>
            <select id="email-summary-recipients" name="_email_summary_recipients[]" multiple>
                <?php foreach ($global->getEmailSummaryRecipients() as $email) : ?>
                    <option value="<?php echo esc_attr($email); ?>" selected><?php echo esc_html($email); ?></option>
                <?php endforeach; ?>
                <?php foreach (EmailSummary::getRecipientSuggestions() as $email) : ?>
                    <option value="<?php echo esc_attr($email); ?>"><?php echo esc_html($email); ?></option>
                <?php endforeach; ?>
            </select>
            <?php if (count($global->getEmailSummaryRecipients()) === 0) : ?>
            <p class="descriptionred">
                <em>
                    <span class="maroon">
                        <?php _e('No recipients entered. Email summary won\'t be send.', 'duplicator-pro') ?>
                    </span>
                </em>
            </p>
            <?php endif; ?>
        </td>
    </tr>
</table>
<script type="text/javascript">
    jQuery(document).ready(function ($) {
        $('#email-summary-recipients').select2({
            tags: true,
            tokenSeparators: [',', ' '],
            placeholder: '<?php esc_attr_e('Enter email addresses', 'duplicator-pro'); ?>',
            minimumInputLength: 3,
        });
    });
</script>
