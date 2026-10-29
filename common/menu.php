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

$aisle_menu_items = aisle_menu('main');
$aisle_menu_i18n  = [
    'products'    => __('Products', 'aisle'),
    'seeAll'      => __('See all results for “%s”', 'aisle'),
    'noMatch'     => __('No products match “%s”. Try a shorter word.', 'aisle'),
    'scheme'      => __('Colour scheme', 'aisle'),
    'system'      => __('System', 'aisle'),
    'light'       => __('Light', 'aisle'),
    'dark'        => __('Dark', 'aisle'),
];
?>
<style>
.menu { position: fixed; inset: 0; z-index: 60; }
.menu[hidden] { display: none; }
.menu-scrim { position: absolute; inset: 0; background: rgb(0 0 0 / .45); }
.menu-panel { position: absolute; inset: 0 auto 0 0; width: min(320px, 86%); background: var(--raised); color: var(--ink); box-shadow: var(--shadow-float); padding: 12px 16px 24px; overflow-y: auto; display: flex; flex-direction: column; gap: 4px; }
.menu-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; font-family: var(--display); font-weight: 700; font-size: 18px; }
.menu-panel a:not(.iconbtn) { padding: 12px 8px; border-radius: var(--r-s); text-decoration: none; color: var(--ink); font-weight: 500; }
.menu-panel a:not(.iconbtn):hover { background: var(--band); }
.menu-panel a[aria-current] { background: var(--band); font-weight: 700; box-shadow: inset 3px 0 0 var(--brand); }
.menu-panel a.sale { color: var(--brand); }
@media (prefers-reduced-motion: no-preference) {
  .menu:not([hidden]) .menu-panel { animation: aisle-menu-in .2s var(--ease); }
}
@keyframes aisle-menu-in { from { transform: translateX(-24px); opacity: 0; } }
@container app (min-width: 701px) { .menu { display: none; } }
</style>
<script type="application/json" id="aisle-i18n"><?php echo json_encode($aisle_menu_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<script src="<?php echo osc_esc_html(aisle_asset('js/api.js')); ?>" defer></script>
<div class="menu" id="aisle-menu" data-menu hidden>
    <div class="menu-scrim" data-menu-close></div>
    <div class="menu-panel" role="dialog" aria-modal="true" aria-label="<?php echo osc_esc_html(__('Menu', 'aisle')); ?>">
        <div class="menu-head">
            <span><?php echo osc_esc_html((string) osc_get_preference('pageTitle')); ?></span>
            <button class="iconbtn" type="button" data-menu-close aria-label="<?php echo osc_esc_html(__('Close', 'aisle')); ?>"><?php echo aisle_icon('x'); ?></button>
        </div>
        <a class="sale" href="<?php echo osc_esc_html(osc_search_url(['sale' => 1])); ?>"><?php _e('Deals', 'aisle'); ?></a>
        <?php foreach ($aisle_menu_items as $aisle_menu_item) { ?>
            <a href="<?php echo osc_esc_html((string) $aisle_menu_item['url']); ?>"<?php echo aisle_menu_current((string) $aisle_menu_item['url']) ? ' aria-current="page"' : ''; ?>><?php echo osc_esc_html((string) $aisle_menu_item['label']); ?></a>
        <?php } ?>
        <a href="<?php echo osc_esc_html(osc_search_url(['sOrder' => 'dt_pub_date', 'iOrderType' => 'desc'])); ?>"><?php _e('New in', 'aisle'); ?></a>
        <?php if (osc_users_enabled()) { ?>
            <a href="<?php echo osc_esc_html(osc_is_web_user_logged_in() ? osc_user_dashboard_url() : osc_user_login_url()); ?>"><?php _e('Account', 'aisle'); ?></a>
        <?php } ?>
    </div>
</div>
