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

// Core's orders page, drawn in the shop's own style. Gateways send the buyer back here after paying;
// ?placed=<id> adds a thank-you line.
require_once AISLE_DIR . '/cart/order-card.php';

osc_current_web_theme_path('common/header.php');

$aisle_cursor = Params::getParamString('cursor');
if (preg_match('/^[A-Za-z0-9_=.-]{1,512}$/', $aisle_cursor) !== 1) {
    $aisle_cursor = '';
}
$aisle_body   = aisle_api('account/orders', ['limit' => 10, 'cursor' => $aisle_cursor]);
$aisle_orders = array_values(array_filter((array) ($aisle_body['data'] ?? []), 'is_array'));
$aisle_extras = aisle_order_extras($aisle_orders);
$aisle_next   = '';
if (is_string($aisle_body['links']['next'] ?? null)) {
    parse_str((string) parse_url($aisle_body['links']['next'], PHP_URL_QUERY), $aisle_q);
    $aisle_after = is_string($aisle_q['cursor'] ?? null) ? $aisle_q['cursor'] : '';
    if (preg_match('/^[A-Za-z0-9_=.-]{1,512}$/', $aisle_after) === 1) {
        $aisle_next = osc_billing_orders_url() . (str_contains((string) osc_billing_orders_url(), '?') ? '&' : '?') . 'cursor=' . rawurlencode($aisle_after);
    }
}

$aisle_placed = Params::getParamInt('placed');
$aisle_thanks = '';
foreach ($aisle_orders as $aisle_order) {
    if ($aisle_placed > 0 && (int) $aisle_order['id'] === $aisle_placed) {
        $aisle_thanks = in_array($aisle_order['status'], ['paid', 'shipped', 'delivered'], true)
            ? __('Thank you, your order is placed.', 'aisle')
            : __('Thank you, your order is placed. We will start on it when payment arrives.', 'aisle');
    }
}
?>
<link rel="stylesheet" href="<?php echo osc_esc_html(aisle_asset('css/account.css')); ?>">
<div class="wrap">
    <div class="acct">
        <?php echo aisle_account_nav('orders'); ?>
        <div>
            <h1><?php _e('Your orders', 'aisle'); ?></h1>
            <?php if ($aisle_thanks !== '') { ?>
                <p class="acct-thanks" role="status"><?php echo aisle_icon('check', true); ?><span><?php echo osc_esc_html($aisle_thanks); ?></span></p>
            <?php } ?>
            <div data-orders>
                <?php foreach ($aisle_orders as $aisle_order) {
                    echo aisle_order_card($aisle_order, $aisle_extras, ['link' => true]);
                } ?>
            </div>
            <?php if ($aisle_orders === [] && aisle_api_failed()) {
                echo aisle_api_error();
            } elseif ($aisle_orders === []) { ?>
                <p class="acct-empty"><?php _e('You have no orders yet.', 'aisle'); ?> <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Keep browsing', 'aisle'); ?></a></p>
            <?php } ?>
            <?php if ($aisle_next !== '') { ?>
                <p><a class="btn btn-ghost" href="<?php echo osc_esc_html($aisle_next); ?>"><?php _e('Older orders', 'aisle'); ?></a></p>
            <?php } ?>
        </div>
    </div>
</div>
<script type="application/json" id="aisle-orders-i18n"><?php echo json_encode(aisle_orders_i18n(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo osc_esc_html(aisle_asset('js/aisle-orders.js')); ?>" defer></script>
<?php
osc_current_web_theme_path('common/footer.php');
