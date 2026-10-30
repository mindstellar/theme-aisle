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

// Rendered by core's custom route. The page is a shell: the summary, delivery methods and payment
// methods come from GET cart/quote, and the script places the order with POST account/orders.

$aisle_co_css = aisle_asset('css/checkout.css');
$aisle_co_js  = aisle_asset('js/aisle-checkout.js');

if (!osc_is_web_user_logged_in()) {
    // Both links carry this page as `return`, so signing in or registering comes back here.
    ?>
<link rel="stylesheet" href="<?php echo osc_esc_html($aisle_co_css); ?>">
<div class="wrap">
    <div class="co-gate">
        <h1><?php _e('Checkout', 'aisle'); ?></h1>
        <p><?php _e('Sign in to place your order. Your cart stays as it is.', 'aisle'); ?></p>
        <p class="co-gate-links">
            <a class="btn btn-primary" href="<?php echo osc_esc_html(osc_user_login_url(aisle_checkout_url())); ?>"><?php _e('Sign in', 'aisle'); ?></a>
            <a class="btn btn-ghost" href="<?php echo osc_esc_html(osc_register_account_url(aisle_checkout_url())); ?>"><?php _e('Create an account', 'aisle'); ?></a>
        </p>
    </div>
</div>
    <?php
    return;
}

$aisle_me   = (array) ((aisle_api('account') ?? [])['data'] ?? []);
$aisle_loc  = (array) ($aisle_me['location'] ?? []);
$aisle_pick = static function ($place): string {
    if (is_array($place)) {
        return trim((string) ($place['name'] ?? ''));
    }

    return trim((string) $place);
};
$aisle_country = $aisle_pick($aisle_loc['country'] ?? '');
$aisle_countries = [];
foreach ((array) osc_get_countries() as $aisle_row) {
    $aisle_code = (string) ($aisle_row['pk_c_code'] ?? '');
    if ($aisle_code !== '') {
        $aisle_countries[$aisle_code] = (string) ($aisle_row['s_name'] ?? $aisle_code);
    }
}
$aisle_country_code = '';
if (is_array($aisle_loc['country'] ?? null)) {
    $aisle_country_code = strtoupper((string) ($aisle_loc['country']['code'] ?? $aisle_loc['country']['id'] ?? ''));
} elseif (isset($aisle_countries[strtoupper($aisle_country)])) {
    $aisle_country_code = strtoupper($aisle_country);
}
if (!isset($aisle_countries[$aisle_country_code])) {
    $aisle_country_code = (string) array_key_first($aisle_countries);
}

$aisle_order_url = aisle_order_placed_url(987654321);
$aisle_order_url = str_replace('987654321', '{id}', $aisle_order_url);
$aisle_co_i18n   = [
    'required'   => __('This field is required.', 'aisle'),
    'pin'        => __('PIN code needs 6 digits.', 'aisle'),
    'phone'      => __('Enter a mobile number with at least 8 digits.', 'aisle'),
    'pin_label'  => __('PIN code', 'aisle'),
    'zip_label'  => __('Postcode', 'aisle'),
    'free'       => __('Free', 'aisle'),
    'empty'      => __('Your cart is empty.', 'aisle'),
    'back'       => __('Back to the shop', 'aisle'),
    'qty'        => __('Qty %d', 'aisle'),
    'left'       => __('Only %d left', 'aisle'),
    'sold'       => __('Sold out', 'aisle'),
    'fix_cart'   => __('Some items need your attention. Change them in your cart.', 'aisle'),
    'no_pay'     => __('No payment method is available right now.', 'aisle'),
    'fee'        => __('+%s', 'aisle'),
    'placing'    => __('Placing your order…', 'aisle'),
    'place'      => __('Place order', 'aisle'),
    'failed'     => __('We could not place your order. Please try again.', 'aisle'),
    'quote_fail' => __('We could not price your cart. Please try again.', 'aisle'),
    'offline'    => __('You are offline. Check your connection and try again.', 'aisle'),
    'slow'       => __('Too many tries. Wait a minute and try again.', 'aisle'),
    'retry'      => __('Try again', 'aisle'),
    'unavail'    => __('That payment method is not available. Pick another.', 'aisle'),
    'see_orders' => __('See your orders', 'aisle'),
    /* translators: 1: unpaid bank transfer orders held, 2: most that may be held */
    'open_one'   => _n('You have %1$d unpaid bank transfer order. You can hold %2$d at once.', 'You have %1$d unpaid bank transfer orders. You can hold %2$d at once.', 1, 'aisle'),
    /* translators: 1: unpaid bank transfer orders held, 2: most that may be held */
    'open_many'  => _n('You have %1$d unpaid bank transfer order. You can hold %2$d at once.', 'You have %1$d unpaid bank transfer orders. You can hold %2$d at once.', 2, 'aisle'),
    'open_blocked' => __('Pay for or cancel an unpaid bank transfer order first.', 'aisle'),
    'l_subtotal' => __('Subtotal', 'aisle'),
    'l_delivery' => __('Delivery', 'aisle'),
    'l_discount' => __('Discount', 'aisle'),
    'l_tax'      => __('Tax', 'aisle'),
    'l_fee'      => __('Fee', 'aisle'),
    'l_total'    => __('Total', 'aisle'),
    'redirect'   => __('Taking you to payment…', 'aisle'),
    'coupon_bad' => __('Enter a valid coupon code.', 'aisle'),
    /* translators: 1: tax name such as GST, 2: amount */
    'includes'   => __('Includes %1$s %2$s', 'aisle'),
    'tax_name'   => __('tax', 'aisle'),
    'tax_later'  => __('Tax is worked out once you choose your state.', 'aisle'),
    'state_pick' => __('Choose…', 'aisle'),
];
?>
<link rel="stylesheet" href="<?php echo osc_esc_html($aisle_co_css); ?>">
<div class="wrap">
    <form class="co" data-checkout data-order-url="<?php echo osc_esc_html($aisle_order_url); ?>" data-orders-url="<?php echo osc_esc_html((string) osc_billing_orders_url()); ?>" data-shop="<?php echo osc_esc_html(osc_base_url()); ?>" novalidate>
        <div class="co-steps">
            <h1><?php _e('Checkout', 'aisle'); ?></h1>
            <p class="co-top-err" data-top-err role="alert" tabindex="-1" hidden></p>
            <p class="co-open-orders" data-open-orders hidden></p>
            <a class="co-top" href="#co-summary" data-co-top hidden><span><?php _e('Total', 'aisle'); ?></span><b class="num" data-co-top-total></b></a>

            <section class="co-sec" data-step="contact">
                <h2><i>1</i><?php _e('Contact', 'aisle'); ?></h2>
                <div class="fields">
                    <label class="field full"><?php _e('Email', 'aisle'); ?>
                        <input type="email" name="email" value="<?php echo osc_esc_html((string) ($aisle_me['email'] ?? osc_logged_user_email())); ?>" autocomplete="email" readonly>
                    </label>
                </div>
            </section>

            <section class="co-sec" data-step="address">
                <h2><i>2</i><?php _e('Delivery address', 'aisle'); ?></h2>
                <?php if (function_exists('osc_address_picker')) {
                    osc_address_picker();
                } ?>
                <div class="fields" data-address-form>
                    <label class="field" data-field="name"><?php _e('Full name', 'aisle'); ?>
                        <input name="name" value="<?php echo osc_esc_html((string) ($aisle_me['name'] ?? '')); ?>" autocomplete="name" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field" data-field="phone"><?php _e('Mobile', 'aisle'); ?>
                        <input name="phone" value="<?php echo osc_esc_html((string) ($aisle_me['phone_mobile'] ?? '')); ?>" inputmode="tel" autocomplete="tel" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field full" data-field="line1"><?php _e('Address', 'aisle'); ?>
                        <input name="line1" value="<?php echo osc_esc_html((string) ($aisle_me['address'] ?? '')); ?>" autocomplete="street-address" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field" data-field="postcode"><span data-postcode-label><?php _e('PIN code', 'aisle'); ?></span>
                        <input name="postcode" value="<?php echo osc_esc_html((string) ($aisle_me['zip'] ?? '')); ?>" inputmode="numeric" autocomplete="postal-code" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field" data-field="city"><?php _e('City', 'aisle'); ?>
                        <input name="city" value="<?php echo osc_esc_html($aisle_pick($aisle_loc['city'] ?? '')); ?>" autocomplete="address-level2" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field" data-field="state"><?php _e('State', 'aisle'); ?>
                        <input name="state" value="<?php echo osc_esc_html($aisle_pick($aisle_loc['region'] ?? '')); ?>" autocomplete="address-level1" required>
                        <span class="err" hidden></span>
                    </label>
                    <label class="field" data-field="country"><?php _e('Country', 'aisle'); ?>
                        <select name="country" autocomplete="country">
                            <?php foreach ($aisle_countries as $aisle_code => $aisle_name) { ?>
                                <option value="<?php echo osc_esc_html($aisle_code); ?>"<?php echo $aisle_code === $aisle_country_code ? ' selected' : ''; ?>><?php echo osc_esc_html($aisle_name); ?></option>
                            <?php } ?>
                        </select>
                        <span class="err" hidden></span>
                    </label>
                    <?php if (function_exists('osc_address_save_field')) {
                        osc_address_save_field();
                    } ?>
                </div>
            </section>

            <section class="co-sec" data-step="delivery">
                <h2><i>3</i><?php _e('Delivery', 'aisle'); ?></h2>
                <div class="radios" data-shipping></div>
            </section>

            <section class="co-sec" data-step="payment">
                <h2><i>4</i><?php _e('Payment', 'aisle'); ?></h2>
                <div class="radios" data-gateways></div>
                <p class="err field-err" data-gateway-err hidden></p>
            </section>
        </div>

        <aside class="summary" id="co-summary" data-summary aria-label="<?php echo osc_esc_html(__('Order summary', 'aisle')); ?>">
            <h2><?php _e('Your order', 'aisle'); ?></h2>
            <div data-price-notes hidden></div>
            <div class="co-lines" data-lines></div>
            <hr class="hr">
            <div class="coupon" data-coupon>
                <label class="sr" for="co-code"><?php _e('Coupon code', 'aisle'); ?></label>
                <input id="co-code" data-coupon-input placeholder="<?php echo osc_esc_html(__('Coupon code', 'aisle')); ?>" autocomplete="off" autocapitalize="characters" maxlength="40" aria-describedby="co-code-err">
                <button class="btn btn-ghost" type="button" data-coupon-apply><?php _e('Apply', 'aisle'); ?></button>
            </div>
            <p class="err field-err" id="co-code-err" data-coupon-err role="alert" hidden></p>
            <div class="applied-code" data-coupon-ok hidden>
                <span data-coupon-label></span>
                <span class="num" data-coupon-amt></span>
                <button class="linkbtn" type="button" data-coupon-remove><?php _e('Remove', 'aisle'); ?></button>
            </div>
            <div data-totals></div>
            <p class="co-note" data-tax-note hidden></p>
            <p class="co-alert" data-alert role="alert" hidden></p>
            <button class="btn btn-primary btn-block" type="submit" data-place>
                <svg class="ico sm" aria-hidden="true"><use href="#i-lock"/></svg><span><?php _e('Place order', 'aisle'); ?></span>
            </button>
            <p class="secure" data-secure hidden><svg class="ico sm" aria-hidden="true"><use href="#i-shield"/></svg><?php _e('Your payment details never touch our server.', 'aisle'); ?></p>
            <noscript><p class="co-alert"><?php _e('Checkout needs JavaScript. Please turn it on to place your order.', 'aisle'); ?></p></noscript>
        </aside>
    </form>
</div>
<script type="application/json" id="aisle-checkout-i18n"><?php echo json_encode($aisle_co_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo osc_esc_html(aisle_asset('js/aisle-address.js')); ?>" defer></script>
<script src="<?php echo osc_esc_html($aisle_co_js); ?>" defer></script>
