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

/*
 * Routes of the shop pages. Their files live beside this one (cart/checkout.php, cart/order.php).
 * They go through render targets so they still resolve when a child of Aisle is the active theme.
 */
$aisle_checkout_file = 'cart/checkout.php';
$aisle_order_file    = 'cart/order.php';
if (function_exists('osc_register_render_target')) {
    osc_register_render_target('aisle/checkout', AISLE_DIR . '/cart/checkout.php');
    osc_register_render_target('aisle/order', AISLE_DIR . '/cart/order.php');
    [$aisle_checkout_file, $aisle_order_file] = ['aisle/checkout', 'aisle/order'];
}
osc_add_route('aisle-checkout', '^checkout/?(?:\?.*)?$', 'checkout', $aisle_checkout_file, false, 'checkout', 'checkout', 'Checkout');
osc_add_route('aisle-order', '^orders/(\d+)/?(?:\?.*)?$', 'orders/{id}', $aisle_order_file, false, 'orders', 'orders', 'Order');

/**
 * Address of the checkout page.
 */
function aisle_checkout_url(): string
{
    return (string) osc_route_url('aisle-checkout');
}

/**
 * An order's page as the buyer sees it right after placing or paying: the thank-you line and the
 * purchase event show only there.
 */
function aisle_order_placed_url(int $orderId): string
{
    $url = (string) osc_route_url('aisle-order', ['id' => $orderId]);

    return $url . (str_contains($url, '?') ? '&' : '?') . 'placed=1';
}

// A core without the typed event calls the filter with ($url, $orderId).
if (class_exists('mindstellar\\billing\\event\\OrderReturnUrlResolving')) {
    osc_add_filter('billing_order_return_url', static function (\mindstellar\billing\event\OrderReturnUrlResolving $event): void {
        if ($event->orderId() > 0) {
            $event->setUrl(aisle_order_placed_url($event->orderId()));
        }
    });
} else {
    osc_add_filter('billing_order_return_url', static function ($url, $orderId = 0) {
        return (int) $orderId > 0 ? aisle_order_placed_url((int) $orderId) : $url;
    });
}

if (!OC_ADMIN) {
    osc_add_hook('header', 'aisle_enqueue_cart', 6);
    osc_add_filter('user_menu_filter', static function ($options) {
        $options = is_array($options) ? $options : [];
        $has = false;
        foreach ($options as $option) {
            if (is_array($option) && ($option['class'] ?? '') === 'opt_billing_orders') {
                $has = true;
            }
        }
        if (!$has && function_exists('osc_billing_enabled') && osc_billing_enabled()) {
            $options[] = ['name' => __('Your orders', 'aisle'), 'url' => (string) osc_billing_orders_url(), 'class' => 'opt_billing_orders'];
        }

        return $options;
    });
}

/**
 * The cart script and styles, on every page (the drawer is in the footer).
 */
function aisle_enqueue_cart(): void
{
    osc_enqueue_style('aisle-cart', aisle_asset('css/cart.css'));
    osc_register_script('aisle-cart', aisle_asset('js/aisle-cart.js'));
    osc_enqueue_script('aisle-cart');
}

/**
 * Words the cart script shows.
 *
 * @return array<string,string>
 */
function aisle_cart_i18n(): array
{
    return [
        'added'    => __('Added', 'aisle'),
        'add'      => __('Add to cart', 'aisle'),
        'failed'   => __('Could not update your cart', 'aisle'),
        'sold'     => __('Sold out', 'aisle'),
        'unbuyable' => __('This item cannot be bought right now and was removed from your cart.', 'aisle'),
        'left'     => __('Only %d left', 'aisle'),
        'less'     => __('Less', 'aisle'),
        'more'     => __('More', 'aisle'),
        'remove'   => __('Remove', 'aisle'),
        'free'     => __('You get free delivery', 'aisle'),
        'more_for' => __('Add %s more for free delivery', 'aisle'),
        'items'    => __('(%d)', 'aisle'),
        'checkout' => __('Checkout', 'aisle'),
        /* translators: 1: product name, 2: old price, 3: new price */
        'price_changed' => __('The price of %1$s changed from %2$s to %3$s.', 'aisle'),
        'ok'       => __('OK', 'aisle'),
    ];
}
