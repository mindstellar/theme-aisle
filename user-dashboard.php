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

// A shopper's dashboard shows recent orders as the same cards as the orders page. Everyone else gets core's dashboard.
if (!function_exists('osc_is_shopper') || !osc_is_shopper()) {
    osc_gui_view('', ABS_PATH . 'oc-includes/osclass/gui/account/user-dashboard-content.php', [
        'heading' => _m('Dashboard'),
        'title'   => trim(_m('Dashboard') . ' — ' . osc_page_title(), ' —'),
    ]);

    return;
}

require_once AISLE_DIR . '/cart/order-card.php';

osc_current_web_theme_path('common/header.php');

$aisle_body   = osc_billing_enabled() ? aisle_api('account/orders', ['limit' => 5]) : null;
$aisle_orders = array_values(array_filter((array) ($aisle_body['data'] ?? []), 'is_array'));
$aisle_extras = aisle_order_extras($aisle_orders);
?>
<link rel="stylesheet" href="<?php echo osc_esc_html(aisle_asset('css/account.css')); ?>">
<div class="wrap">
    <div class="acct">
        <?php echo aisle_account_nav(''); ?>
        <div>
            <h1><?php _e('Your recent orders', 'aisle'); ?></h1>
            <?php osc_show_flash_message(); ?>
            <?php osc_run_hook('account_page_before', 'user-dashboard'); ?>
            <div data-orders>
                <?php foreach ($aisle_orders as $aisle_order) {
                    echo aisle_order_card($aisle_order, $aisle_extras, ['link' => true]);
                } ?>
            </div>
            <?php if ($aisle_orders === [] && $aisle_body !== null && aisle_api_failed()) {
                echo aisle_api_error();
            } elseif ($aisle_orders === []) { ?>
                <p class="acct-empty"><?php _e('You have no orders yet.', 'aisle'); ?> <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Keep browsing', 'aisle'); ?></a></p>
            <?php } else { ?>
                <p><a class="btn btn-ghost" href="<?php echo osc_esc_html((string) osc_billing_orders_url()); ?>"><?php _e('See all your orders', 'aisle'); ?></a></p>
            <?php } ?>
            <?php osc_run_hook('user_dashboard'); ?>
            <?php osc_run_hook('account_page_after', 'user-dashboard'); ?>
        </div>
    </div>
</div>
<script type="application/json" id="aisle-orders-i18n"><?php echo json_encode(aisle_orders_i18n(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo osc_esc_html(aisle_asset('js/aisle-orders.js')); ?>" defer></script>
<?php
osc_current_web_theme_path('common/footer.php');
