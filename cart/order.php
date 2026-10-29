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

// One order, at orders/<id>. Rendered by core's custom route inside the shop header and footer.
require_once AISLE_DIR . '/cart/order-card.php';

$aisle_css = aisle_asset('css/account.css');

if (!osc_is_web_user_logged_in()) {
    ?>
<link rel="stylesheet" href="<?php echo osc_esc_html($aisle_css); ?>">
<div class="wrap">
    <div class="acct-gate">
        <h1><?php _e('Your order', 'aisle'); ?></h1>
        <p><?php _e('Sign in to see your order.', 'aisle'); ?></p>
        <p><a class="btn btn-primary" href="<?php echo osc_esc_html(osc_user_login_url((string) osc_route_url('aisle-order', ['id' => Params::getParamInt('id')]))); ?>"><?php _e('Sign in', 'aisle'); ?></a></p>
    </div>
</div>
    <?php
    return;
}

$aisle_id    = Params::getParamInt('id');
$aisle_body  = $aisle_id > 0 ? aisle_api('account/orders/' . $aisle_id) : null;
$aisle_order = is_array($aisle_body['data'] ?? null) ? $aisle_body['data'] : null;
?>
<link rel="stylesheet" href="<?php echo osc_esc_html($aisle_css); ?>">
<div class="wrap">
    <div class="acct">
        <?php echo aisle_account_nav('orders'); ?>
        <div>
            <?php if ($aisle_order === null && aisle_api_failed()) {
                if (!headers_sent()) {
                    http_response_code(503);
                } ?>
                <h1><?php _e('Your order', 'aisle'); ?></h1>
                <?php echo aisle_api_error(); ?>
            <?php } elseif ($aisle_order === null) {
                if (!headers_sent()) {
                    http_response_code(404);
                } ?>
                <h1><?php _e('Order not found', 'aisle'); ?></h1>
                <p class="acct-empty"><?php _e('We could not find that order in your account.', 'aisle'); ?> <a href="<?php echo osc_esc_html((string) osc_billing_orders_url()); ?>"><?php _e('See all your orders', 'aisle'); ?></a></p>
            <?php } else {
                $aisle_paid = in_array($aisle_order['status'], ['paid', 'shipped', 'delivered'], true);
                ?>
                <h1><?php echo osc_esc_html(sprintf(__('Order %s', 'aisle'), (string) $aisle_order['number'])); ?></h1>
                <?php if (Params::getParamInt('placed') === 1) { ?>
                    <p class="acct-thanks" role="status"><?php echo aisle_icon('check', true); ?><span><?php echo osc_esc_html($aisle_paid ? __('Thank you, your order is placed.', 'aisle') : __('Thank you, your order is placed. We will start on it when payment arrives.', 'aisle')); ?></span></p>
                <?php } ?>
                <div data-orders>
                    <?php echo aisle_order_card($aisle_order, aisle_order_extras([$aisle_order])); ?>
                </div>
                <p><a class="btn btn-ghost" href="<?php echo osc_esc_html((string) osc_billing_orders_url()); ?>"><?php _e('All your orders', 'aisle'); ?></a></p>
            <?php } ?>
        </div>
    </div>
</div>
<script type="application/json" id="aisle-orders-i18n"><?php echo json_encode(aisle_orders_i18n(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo osc_esc_html(aisle_asset('js/aisle-orders.js')); ?>" defer></script>
