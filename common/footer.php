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

$aisle_site  = (string) osc_get_preference('pageTitle');
$aisle_shop  = array_slice(aisle_menu('main'), 0, 7);
$aisle_pages = aisle_menu('footer');
$aisle_note  = trim((string) aisle_setting('footer_note', __('Everyday things for your home, kitchen and wardrobe, packed with care and sent to your door.', 'aisle')));
$aisle_pay   = array_values(array_filter(array_map('trim', explode(',', (string) aisle_setting('payment_methods', ''))), static fn (string $m): bool => $m !== ''));
?>
</main>
<footer class="foot" id="foot">
    <div class="wrap">
        <div class="foot-grid">
            <div>
                <?php echo aisle_logo(); ?>
                <?php if ($aisle_note !== '') { ?>
                    <p class="foot-note"><?php echo osc_esc_html($aisle_note); ?></p>
                <?php } ?>
                <?php if ($aisle_pay !== []) { ?>
                    <ul class="pay" aria-label="<?php echo osc_esc_html(__('Ways to pay', 'aisle')); ?>">
                        <?php foreach ($aisle_pay as $aisle_method) { ?>
                            <li><?php echo osc_esc_html($aisle_method); ?></li>
                        <?php } ?>
                    </ul>
                <?php } ?>
            </div>
            <?php if ($aisle_shop !== []) { ?>
                <div>
                    <h3><?php _e('Shop', 'aisle'); ?></h3>
                    <ul>
                        <?php foreach ($aisle_shop as $aisle_item) { ?>
                            <li><a href="<?php echo osc_esc_html((string) $aisle_item['url']); ?>"><?php echo osc_esc_html((string) $aisle_item['label']); ?></a></li>
                        <?php } ?>
                    </ul>
                </div>
            <?php } ?>
            <div>
                <h3><?php _e('Help', 'aisle'); ?></h3>
                <ul>
                    <?php foreach ($aisle_pages as $aisle_item) { ?>
                        <li><a href="<?php echo osc_esc_html((string) $aisle_item['url']); ?>"><?php echo osc_esc_html((string) $aisle_item['label']); ?></a></li>
                    <?php } ?>
                    <li><a href="<?php echo osc_esc_html(osc_contact_url()); ?>"><?php _e('Contact us', 'aisle'); ?></a></li>
                </ul>
            </div>
        </div>
        <?php aisle_widget_zone('footer', 'foot-widgets'); ?>
        <div class="foot-base">
            <p><?php echo osc_esc_html('© ' . date('Y') . ' ' . $aisle_site); ?></p>
            <?php if (osc_count_web_enabled_locales() > 1) {
                $aisle_current = osc_current_user_locale();
                osc_goto_first_locale(); ?>
                <nav class="foot-lang" aria-label="<?php echo osc_esc_html(__('Language', 'aisle')); ?>">
                    <?php while (osc_has_web_enabled_locales()) { ?>
                        <a href="<?php echo osc_esc_html(osc_change_language_url(osc_locale_code())); ?>" lang="<?php echo osc_esc_html(str_replace('_', '-', osc_locale_code())); ?>"<?php echo osc_locale_code() === $aisle_current ? ' class="on" aria-current="true"' : ''; ?>><?php echo osc_esc_html(osc_locale_name()); ?></a>
                    <?php } ?>
                </nav>
            <?php } ?>
        </div>
    </div>
</footer>
</div>
<div class="cart-scrim" data-cart-scrim></div>
<aside class="drawer" data-cart-drawer role="dialog" aria-modal="true" aria-labelledby="aisle-cart-title" aria-hidden="true" inert data-checkout="<?php echo osc_esc_html(aisle_checkout_url()); ?>" data-shop="<?php echo osc_esc_html(osc_base_url()); ?>" data-empty="<?php echo osc_esc_html(__('Your cart is empty.', 'aisle')); ?>" data-empty-cta="<?php echo osc_esc_html(__('Start shopping', 'aisle')); ?>" data-subtotal="<?php echo osc_esc_html(__('Subtotal', 'aisle')); ?>" data-note="<?php echo osc_esc_html(__('Delivery is worked out at checkout.', 'aisle')); ?>" data-keep="<?php echo osc_esc_html(__('Keep shopping', 'aisle')); ?>" data-retry="<?php echo osc_esc_html(__('Try again', 'aisle')); ?>">
    <div class="dr-head">
        <h2 id="aisle-cart-title"><?php _e('Your cart', 'aisle'); ?> <span class="num" data-cart-heading-count></span></h2>
        <button class="iconbtn" type="button" data-cart-close aria-label="<?php echo osc_esc_html(__('Close cart', 'aisle')); ?>"><?php echo aisle_icon('x'); ?></button>
    </div>
    <div class="ship-meter" data-cart-meter hidden></div>
    <div class="lines" data-cart-lines aria-live="polite"></div>
    <div class="dr-foot" data-cart-foot></div>
</aside>
<script type="application/json" id="aisle-cart-i18n"><?php echo json_encode(aisle_cart_i18n(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<?php osc_run_hook('footer'); ?>
</body>
</html>
