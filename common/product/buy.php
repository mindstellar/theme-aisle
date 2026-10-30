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

global $aisle_listing, $aisle_title, $aisle_price, $aisle_off, $aisle_variant, $aisle_low, $aisle_out, $aisle_ratingline;

// Needs $aisle_listing, $aisle_title, $aisle_price, $aisle_was, $aisle_off, $aisle_variant, $aisle_low, $aisle_out from item.php.
osc_current_web_theme_path('common/product/variants.php');
$aisle_state = aisle_variant_state($aisle_listing);
$aisle_row   = $aisle_state['rows'][$aisle_state['default']] ?? null;
$aisle_i18n  = [
    'out'     => __('Out of stock', 'aisle'),
    'ok'      => __('In stock', 'aisle'),
    'low'     => __('Only %d left', 'aisle'),
    'lowin'   => __('Only %d left in %s', 'aisle'),
    'off'     => str_replace('%%', '%', __('%d%% off', 'aisle')),
    'sold'    => __('%s, sold out', 'aisle'),
    'added'   => __('Added', 'aisle'),
    'saved'   => __('We will e-mail you', 'aisle'),
    'exists'  => __('Already set', 'aisle'),
    'failed'  => __('Could not save that, try again', 'aisle'),
];
$aisle_jsonf = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE;
$aisle_cat = is_array($aisle_listing['category'] ?? null) ? $aisle_listing['category'] : [];
?>
<div class="info" data-listing="<?php echo (int) ($aisle_listing['id'] ?? 0); ?>" data-login="<?php echo osc_esc_html(osc_user_login_url()); ?>" data-i18n="<?php echo osc_esc_html((string) json_encode($aisle_i18n, $aisle_jsonf)); ?>"<?php echo $aisle_state['rows'] !== [] ? ' data-variants="' . osc_esc_html((string) json_encode($aisle_state['rows'], $aisle_jsonf)) . '"' : ''; ?>>
    <div>
        <?php if (!empty($aisle_cat['name']) && !empty($aisle_cat['slug'])) { ?>
            <p class="brand"><a href="<?php echo osc_esc_html(osc_search_url(['sCategory' => (string) $aisle_cat['slug']])); ?>"><?php echo osc_esc_html((string) $aisle_cat['name']); ?></a></p>
        <?php } ?>
        <h1><?php echo osc_esc_html($aisle_title); ?></h1>
        <?php $aisle_place = aisle_item_location(); ?>
        <?php if ($aisle_place !== '') { ?>
            <p class="place"><?php echo aisle_icon('pin', true); ?><span><?php echo osc_esc_html($aisle_place); ?></span></p>
        <?php } ?>
    </div>
    <?php if (($aisle_ratingline ?? '') !== '') { ?>
        <div class="rating"><?php echo $aisle_ratingline; ?></div>
    <?php } ?>
    <?php if ($aisle_price !== null) { ?>
        <div class="bigprice">
            <b data-price><span class="num"><?php echo osc_esc_html($aisle_row !== null ? $aisle_row['price'] : (string) ($aisle_price['formatted'] ?? '')); ?></span></b>
            <?php if ($aisle_row !== null) { ?>
                <s class="num" data-was<?php echo $aisle_row['off'] >= 1 ? '' : ' hidden'; ?>><?php echo osc_esc_html($aisle_row['was']); ?></s>
                <span class="off num" data-off<?php echo $aisle_row['off'] >= 1 ? '' : ' hidden'; ?>><?php echo osc_esc_html(sprintf(__('%d%% off', 'aisle'), $aisle_row['off'])); ?></span>
            <?php } ?>
        </div>
    <?php } ?>
    <?php foreach ($aisle_state['options'] as $aisle_oi => $aisle_o) { ?>
        <?php $aisle_colour = aisle_is_colour($aisle_o['name']); ?>
        <div class="opt" data-option="<?php echo osc_esc_html($aisle_o['name']); ?>">
            <div class="opt-h"><span><?php echo osc_esc_html($aisle_o['name']); ?>: <b data-option-value><?php echo osc_esc_html((string) ($aisle_row['options'][$aisle_o['name']] ?? '')); ?></b></span></div>
            <div class="<?php echo $aisle_colour ? 'colors' : 'sizes'; ?>">
                <?php foreach ($aisle_o['values'] as $aisle_val) { ?>
                    <?php
                    $aisle_picked = ($aisle_row['options'][$aisle_o['name']] ?? null) === $aisle_val;
                    $aisle_avail  = $aisle_oi === 0;
                    foreach ($aisle_state['rows'] as $aisle_r) {
                        $aisle_ok = $aisle_r['in_stock'] && ($aisle_r['options'][$aisle_o['name']] ?? null) === $aisle_val;
                        for ($aisle_j = 0; $aisle_ok && $aisle_j < $aisle_oi; $aisle_j++) {
                            $aisle_pn = $aisle_state['options'][$aisle_j]['name'];
                            $aisle_ok = ($aisle_r['options'][$aisle_pn] ?? null) === ($aisle_row['options'][$aisle_pn] ?? null);
                        }
                        if ($aisle_ok) {
                            $aisle_avail = true;
                            break;
                        }
                    }
                    $aisle_img = '';
                    if ($aisle_colour) {
                        foreach ($aisle_state['rows'] as $aisle_r) {
                            if (($aisle_r['options'][$aisle_o['name']] ?? null) === $aisle_val && $aisle_r['photo'] !== null) {
                                $aisle_img = $aisle_r['photo']['thumb'];
                                break;
                            }
                        }
                    }
                    ?>
                    <button type="button" data-value="<?php echo osc_esc_html($aisle_val); ?>" aria-pressed="<?php echo $aisle_picked ? 'true' : 'false'; ?>"<?php echo $aisle_avail ? '' : ' class="sold"'; ?><?php
                    if ($aisle_colour) {
                        echo ' aria-label="' . osc_esc_html($aisle_val) . '"';
                    } elseif (!$aisle_avail) {
                        echo ' aria-label="' . osc_esc_html(sprintf(__('%s, sold out', 'aisle'), $aisle_val)) . '"';
                    }
                    ?>><?php
                    if ($aisle_colour && $aisle_img !== '') {
                        echo '<img src="' . osc_esc_html($aisle_img) . '" alt="" loading="lazy">';
                    } elseif ($aisle_colour) {
                        echo '<span aria-hidden="true">' . osc_esc_html(mb_strtoupper(mb_substr($aisle_val, 0, 1))) . '</span>';
                    } else {
                        echo osc_esc_html($aisle_val);
                    }
                    ?></button>
                <?php } ?>
            </div>
        </div>
    <?php } ?>
    <?php if ($aisle_row !== null) { ?>
        <?php
        $aisle_lastopt = $aisle_state['options'] !== [] ? (string) ($aisle_row['options'][end($aisle_state['options'])['name']] ?? '') : '';
        if (!$aisle_row['in_stock']) {
            $aisle_cls = 'out';
            $aisle_ic  = 'box';
            $aisle_txt = __('Out of stock', 'aisle');
        } elseif ($aisle_row['low'] > 0) {
            $aisle_cls = 'lowq';
            $aisle_ic  = 'box';
            $aisle_txt = $aisle_lastopt !== '' ? sprintf(__('Only %d left in %s', 'aisle'), $aisle_row['low'], $aisle_lastopt) : sprintf(__('Only %d left', 'aisle'), $aisle_row['low']);
        } else {
            $aisle_cls = 'ok';
            $aisle_ic  = 'check';
            $aisle_txt = __('In stock', 'aisle');
        }
        ?>
        <p class="stock <?php echo $aisle_cls; ?>" data-stock aria-live="polite"><?php echo aisle_icon($aisle_ic, true); ?><span class="num"><?php echo osc_esc_html($aisle_txt); ?></span></p>
        <div class="buy" data-buy>
            <div class="qty" data-qty role="group" aria-label="<?php echo osc_esc_html(__('Quantity', 'aisle')); ?>">
                <button type="button" data-qty-minus aria-label="<?php echo osc_esc_html(__('Less', 'aisle')); ?>"><?php echo aisle_icon('minus', true); ?></button>
                <output class="num" data-qty-value aria-live="polite">1</output>
                <button type="button" data-qty-plus aria-label="<?php echo osc_esc_html(__('More', 'aisle')); ?>"><?php echo aisle_icon('plus', true); ?></button>
            </div>
            <button class="btn btn-primary" type="button" data-add<?php echo $aisle_row['in_stock'] ? '' : ' disabled'; ?>><?php echo aisle_icon('bag', true); ?><span data-add-label><?php _e('Add to cart', 'aisle'); ?></span></button>
            <button class="btn btn-ink" type="button" data-buy-now<?php echo $aisle_row['in_stock'] ? '' : ' disabled'; ?>><?php _e('Buy now', 'aisle'); ?></button>
            <button class="btn btn-primary" type="button" data-notify<?php echo $aisle_row['in_stock'] ? ' hidden' : ''; ?>><span><?php _e('Notify me when it is back', 'aisle'); ?></span></button>
        </div>
        <?php
        // A download is not sent, so it gets no PIN box.
        $aisle_quote = aisle_api('cart/quote', ['lines' => (int) $aisle_row['id'] . ':1']);
        if (($aisle_quote['data']['needs_shipping'] ?? null) !== false) {
            osc_current_web_theme_path('common/product/deliver.php');
        }
        ?>
        <div class="alerts">
            <button class="btn btn-ghost" type="button" data-pricedrop><span><?php _e('Tell me if the price drops', 'aisle'); ?></span></button>
        </div>
    <?php } ?>
    <?php
    $aisle_share_url = (string) ($aisle_listing['url'] ?? '');
    $aisle_share_u   = rawurlencode($aisle_share_url);
    $aisle_share_t   = rawurlencode((string) $aisle_title);
    ?>
    <div class="share">
        <button class="btn btn-ghost" type="button" data-share aria-haspopup="true" aria-expanded="false" aria-controls="share-menu" data-copied="<?php echo osc_esc_html(__('Link copied', 'aisle')); ?>" data-title="<?php echo osc_esc_html($aisle_title); ?>" data-url="<?php echo osc_esc_html($aisle_share_url); ?>"><?php echo aisle_icon('share', true); ?><span><?php _e('Share', 'aisle'); ?></span></button>
        <div class="share-menu" id="share-menu" data-share-menu hidden>
            <button type="button" data-share-copy><?php _e('Copy link', 'aisle'); ?></button>
            <a href="https://wa.me/?text=<?php echo osc_esc_html($aisle_share_t . '%20' . $aisle_share_u); ?>" target="_blank" rel="noopener">WhatsApp</a>
            <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo osc_esc_html($aisle_share_u); ?>" target="_blank" rel="noopener">Facebook</a>
            <a href="https://x.com/intent/post?url=<?php echo osc_esc_html($aisle_share_u); ?>&amp;text=<?php echo osc_esc_html($aisle_share_t); ?>" target="_blank" rel="noopener">X</a>
            <a href="mailto:?subject=<?php echo osc_esc_html($aisle_share_t); ?>&amp;body=<?php echo osc_esc_html($aisle_share_u); ?>"><?php _e('E-mail', 'aisle'); ?></a>
        </div>
    </div>
    <?php osc_current_web_theme_path('common/product/details.php'); ?>
</div>
<?php if ($aisle_row !== null) { ?>
    <div class="sticky-buy" data-sticky-buy>
        <div><b class="num" data-sticky-price><?php echo osc_esc_html($aisle_row['price']); ?></b><span data-sticky-variant><?php echo osc_esc_html(implode(' · ', array_map('strval', array_values($aisle_row['options'] ?? [])))); ?></span></div>
        <button class="btn btn-primary" type="button" data-add<?php echo $aisle_row['in_stock'] ? '' : ' disabled'; ?>><span data-add-label><?php _e('Add to cart', 'aisle'); ?></span></button>
    </div>
<?php } ?>
