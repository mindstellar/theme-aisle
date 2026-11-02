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

// The postcode box. Needs $aisle_row (the picked variant) from buy.php. The script asks GET cart/quote
// for the delivery date and shows nothing until the shopper clicks Check or a saved postcode exists.
// With one country the box takes that country's postcode label and pattern; a country with no postcode gets no box.

$aisle_dl_codes = [];
foreach ((array) osc_get_countries() as $aisle_dl_row) {
    $aisle_dl_code = strtoupper((string) ($aisle_dl_row['pk_c_code'] ?? ''));
    if ($aisle_dl_code !== '') {
        $aisle_dl_codes[$aisle_dl_code] = true;
    }
}
$aisle_dl_country = count($aisle_dl_codes) === 1 ? (string) array_key_first($aisle_dl_codes) : '';
$aisle_dl_label = '';
$aisle_dl_pattern = '';
$aisle_dl_example = '';
if ($aisle_dl_country !== '' && class_exists('mindstellar\\user\\AddressFormat')) {
    $aisle_dl_fmt = \mindstellar\user\AddressFormat::for($aisle_dl_country);
    if ((string) $aisle_dl_fmt['postcode'] === '') {
        return;
    }
    $aisle_dl_label = (string) $aisle_dl_fmt['postcode'];
    $aisle_dl_pattern = (string) ($aisle_dl_fmt['pattern'] ?? '');
    $aisle_dl_example = (string) ($aisle_dl_fmt['example'] ?? '');
}
$aisle_dl_i18n = [
    'pin_zip'  => $aisle_dl_example !== ''
        ? sprintf(__('Enter a valid postcode, such as %s.', 'aisle'), $aisle_dl_example)
        : __('Enter a valid postcode.', 'aisle'),
    'get_by'   => __('Get it by %s', 'aisle'),
    'method'   => __('%1$s · %2$s', 'aisle'),
    'free'     => __('Free', 'aisle'),
    'free_over' => __('Free delivery on orders over %s', 'aisle'),
    'cod'      => __('Cash on delivery available', 'aisle'),
    'no_serve' => __('We do not deliver to this address yet.', 'aisle'),
    'checking' => __('Checking…', 'aisle'),
];
?>
<div class="deliver" data-deliver data-variant="<?php echo (int) ($aisle_row['id'] ?? 0); ?>" data-country="<?php echo osc_esc_html($aisle_dl_country); ?>"<?php if ($aisle_dl_pattern !== '') { ?> data-postcode-pattern="<?php echo osc_esc_html($aisle_dl_pattern); ?>"<?php } ?><?php if ($aisle_dl_example !== '') { ?> data-postcode-example="<?php echo osc_esc_html($aisle_dl_example); ?>"<?php } ?> data-i18n="<?php echo osc_esc_html((string) json_encode($aisle_dl_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)); ?>">
    <form data-deliver-form novalidate>
        <label class="sr" for="deliver-pin"><?php echo osc_esc_html($aisle_dl_label !== '' ? $aisle_dl_label : __('Postcode', 'aisle')); ?></label>
        <input id="deliver-pin" name="pin" autocomplete="postal-code" maxlength="10" placeholder="<?php echo osc_esc_html($aisle_dl_label !== '' ? $aisle_dl_label : __('Enter postcode', 'aisle')); ?>">
        <button class="btn btn-ghost" type="submit"><?php _e('Check', 'aisle'); ?></button>
    </form>
    <p class="deliver-err" data-deliver-err role="alert" hidden></p>
    <div data-deliver-rows aria-live="polite"></div>
</div>
