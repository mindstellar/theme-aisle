<?php
/*
 * This file is part of Aisle, a Shopclass (Mindstellar) theme.
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

declare(strict_types=1);

/** @var array<string,mixed> $config */
/** @var array<string,mixed> $row */

$aisle_heading = trim((string) ($config['heading'] ?? ''));
if ($aisle_heading === '') {
    return;
}
$aisle_text   = trim((string) ($config['text'] ?? ''));
$aisle_button = trim((string) ($config['button_label'] ?? ''));
if ($aisle_button === '') {
    $aisle_button = __('Sign up', 'aisle');
}
$aisle_action = aisle_safe_url((string) osc_apply_filter('aisle_newsletter_action', ''));
$aisle_off    = $aisle_action === '';

$aisle_status_texts = [
    'sent'      => __('Check your inbox to confirm your e-mail.', 'aisle'),
    'confirmed' => __('You are signed up. Thank you.', 'aisle'),
    'exists'    => __('This e-mail is already signed up.', 'aisle'),
    'invalid'   => __('That e-mail does not look right. Please try again.', 'aisle'),
    'wait'      => __('Please wait a moment before trying again.', 'aisle'),
];
$aisle_status_key = Params::getParamString('newsletter');
$aisle_status     = !$aisle_off && isset($aisle_status_texts[$aisle_status_key]) ? $aisle_status_texts[$aisle_status_key] : '';
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Newsletter', 'aisle')); ?>>
    <div class="wrap">
        <div class="news">
            <div>
                <h2<?php echo aisle_field_attr('heading'); ?>><?php echo osc_esc_html($aisle_heading); ?></h2>
                <?php if ($aisle_text !== '') { ?><p<?php echo aisle_field_attr('text'); ?>><?php echo osc_esc_html($aisle_text); ?></p><?php } ?>
            </div>
            <div>
            <form<?php echo $aisle_off ? '' : ' method="post" action="' . osc_esc_html($aisle_action) . '"'; ?>>
                <label class="sr" for="aisle-news-email"><?php _e('E-mail', 'aisle'); ?></label>
                <input id="aisle-news-email" name="email" type="email" placeholder="<?php echo osc_esc_html(__('Your e-mail', 'aisle')); ?>" autocomplete="email"<?php echo $aisle_off ? ' disabled' : ' required'; ?>>
                <button class="btn btn-ink" type="submit"<?php echo $aisle_off ? ' disabled' : ''; ?>><?php echo osc_esc_html($aisle_button); ?></button>
            </form>
            <?php if ($aisle_status !== '') { ?>
                <p class="news-status" role="status"><?php echo osc_esc_html($aisle_status); ?></p>
            <?php } ?>
            </div>
        </div>
    </div>
</section>
