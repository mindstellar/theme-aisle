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

$aisle_down = aisle_api_failed();
if ($aisle_down && !headers_sent()) {
    http_response_code(503);
}
osc_current_web_theme_path('common/header.php');
$aisle_cats = array_slice(aisle_menu('main'), 0, 7);
?>
<div class="wrap">
    <section class="error-page">
        <?php if ($aisle_down) { ?>
            <h1><?php _e('This page is not loading right now.', 'aisle'); ?></h1>
            <p><?php _e('The shop is slow to answer. Nothing is lost and your cart is safe. Try again in a moment.', 'aisle'); ?></p>
            <div class="actions">
                <a class="btn btn-primary" href=""><?php _e('Try again', 'aisle'); ?></a>
                <a class="btn btn-ghost" href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Back to the shop', 'aisle'); ?></a>
            </div>
        <?php } else { ?>
            <span class="code num" aria-hidden="true">404</span>
            <h1><?php _e('This page could not be found.', 'aisle'); ?></h1>
            <p><?php _e('The product may have sold out or moved, or the link may be mistyped. Try a search or pick a category.', 'aisle'); ?></p>
            <div class="actions">
                <a class="btn btn-primary" href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Back to the shop', 'aisle'); ?></a>
                <a class="btn btn-ghost" href="<?php echo osc_esc_html(osc_contact_url()); ?>"><?php _e('Contact us', 'aisle'); ?></a>
            </div>
        <?php } ?>
        <?php if (!$aisle_down && $aisle_cats !== []) { ?>
            <div class="chips">
                <?php foreach ($aisle_cats as $aisle_cat) { ?>
                    <a class="chip" href="<?php echo osc_esc_html((string) $aisle_cat['url']); ?>"><?php echo osc_esc_html((string) $aisle_cat['label']); ?></a>
                <?php } ?>
            </div>
        <?php } ?>
    </section>
</div>
<?php osc_current_web_theme_path('common/footer.php'); ?>
