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

if (!function_exists('aisle_card')) {
    /**
     * One product card from one API listing. Ask the API for `include=product` so the deal tag,
     * was-price and stock line can show; without it the card shows the price only.
     *
     * Options: `eager` (bool) loads the photo at once instead of lazily.
     *
     * @param array<string,mixed> $listing
     * @param array<string,mixed> $opts
     */
    function aisle_card(array $listing, array $opts = []): string
    {
        $title = (string) ($listing['title'] ?? '');
        $url   = (string) ($listing['url'] ?? '#');
        $price = is_array($listing['price'] ?? null) ? $listing['price'] : null;
        $photo = $listing['photos'][0] ?? null;
        $thumb = is_array($photo) ? (string) ($photo['preview'] ?? $photo['thumbnail'] ?? '') : '';
        $small = is_array($photo) ? (string) ($photo['thumbnail'] ?? '') : '';

        $variants = $listing['product']['variants'] ?? [];
        $variant  = is_array($variants) && isset($variants[0]) && is_array($variants[0]) ? $variants[0] : [];

        $now     = $price !== null ? (float) ($price['amount'] ?? 0) : 0.0;
        $was     = isset($variant['compare_at']) && is_numeric($variant['compare_at']) ? (float) $variant['compare_at'] : 0.0;
        $on_sale = $was > $now && $now > 0 && ($variant['on_sale'] ?? true) !== false;
        $off     = $on_sale ? (int) round((1 - $now / $was) * 100) : 0;

        $low_raw  = $variant['low_stock'] ?? $listing['product']['low_stock'] ?? null;
        $low_left = is_numeric($low_raw) ? (int) $low_raw : 0;
        $out      = $variants !== [] && ($variant['in_stock'] ?? true) === false;

        if (!function_exists('aisle_stars')) {
            require_once AISLE_DIR . '/common/stars.php';
        }
        $rating     = is_array($listing['rating'] ?? null) ? $listing['rating'] : [];
        $rate_count = (int) ($rating['count'] ?? 0);
        $rate_avg   = isset($rating['average']) && is_numeric($rating['average']) ? (float) $rating['average'] : null;
        $id         = (int) ($listing['id'] ?? 0);

        // Quick add only when there is nothing to choose: one variant, in stock.
        $quick = is_array($variants) && count($variants) === 1 && ($variant['in_stock'] ?? true) !== false && (int) ($variant['id'] ?? 0) > 0 ? (int) $variant['id'] : 0;

        // More than one variant and one in stock: the shopper has to choose, so the button opens the product.
        $choose = is_array($variants) && count($variants) > 1 && in_array(true, array_column($variants, 'in_stock'), true);

        // Only the first four photos of a page load at once; the rest wait their turn.
        static $shown = 0;
        $eager        = !empty($opts['eager']) && $shown++ < 4;

        ob_start();
        ?>
<article class="card">
    <div class="ph">
        <?php if ($thumb !== '') { ?>
            <img src="<?php echo osc_esc_html($thumb); ?>"<?php if ($small !== '' && $small !== $thumb) { ?> srcset="<?php echo osc_esc_html($small); ?> 240w, <?php echo osc_esc_html($thumb); ?> 800w" sizes="(min-width: 1100px) 280px, (min-width: 761px) 46vw, 120px"<?php } ?> alt="" loading="<?php echo $eager ? 'eager' : 'lazy'; ?>" decoding="async">
        <?php } else { ?>
            <span class="ph-none" aria-hidden="true"><svg class="ico"><use href="#i-bag"/></svg></span>
        <?php } ?>
        <?php if ($off >= 1) { ?>
            <span class="deal num"><?php echo osc_esc_html('−' . $off . '%'); ?></span>
        <?php } ?>
        <?php if ($quick > 0) { ?>
            <button class="btn btn-primary quick" type="button" data-quick-add="<?php echo $quick; ?>"><?php _e('Add to cart', 'aisle'); ?></button>
        <?php } ?>
        <?php if ($choose) { ?>
            <a class="btn btn-primary quick" href="<?php echo osc_esc_html($url); ?>"><?php _e('Choose options', 'aisle'); ?></a>
        <?php } ?>
        <?php if ($id > 0) { ?>
            <button class="iconbtn fav" type="button" data-fav="<?php echo $id; ?>" aria-pressed="false" aria-label="<?php echo osc_esc_html(sprintf(__('Save %s', 'aisle'), $title)); ?>"><?php echo aisle_icon('heart', true); ?></button>
        <?php } ?>
    </div>
    <a class="t" href="<?php echo osc_esc_html($url); ?>"><?php echo osc_esc_html($title); ?></a>
    <?php if ($rate_count > 0 && $rate_avg !== null && aisle_ratings_on()) { ?>
        <div class="meta"><?php echo aisle_stars($rate_avg, $rate_count); ?></div>
    <?php } ?>
    <?php if ($price !== null) { ?>
        <div class="price">
            <b><?php echo aisle_price($price); ?></b>
            <?php if ($on_sale) { ?>
                <s class="num"><?php echo osc_esc_html(aisle_money((string) $variant['compare_at'], $price['currency'] ?? null)); ?></s>
                <span class="off num"><?php echo osc_esc_html(sprintf(__('%d%% off', 'aisle'), $off)); ?></span>
            <?php } ?>
        </div>
    <?php } ?>
    <?php if ($out) { ?>
        <span class="soldout"><?php _e('Out of stock', 'aisle'); ?></span>
    <?php } elseif ($low_left > 0) { ?>
        <span class="low num"><?php echo osc_esc_html(sprintf(__('Only %d left', 'aisle'), $low_left)); ?></span>
    <?php } ?>
</article>
        <?php
        return (string) ob_get_clean();
    }
}
