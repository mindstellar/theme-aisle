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

$aisle_id      = (int) osc_item_id();
$aisle_res     = $aisle_id > 0 ? aisle_api('listings/' . $aisle_id, ['include' => 'custom_fields,rating', 'fields' => AISLE_CARD_FIELDS . ',description,custom_fields,ext']) : null;
$aisle_listing = is_array($aisle_res['data'] ?? null) ? $aisle_res['data'] : null;

if ($aisle_listing === null) {
    http_response_code(404);
    osc_current_web_theme_path('404.php');

    return;
}

$aisle_title  = (string) ($aisle_listing['title'] ?? '');
$aisle_price  = is_array($aisle_listing['price'] ?? null) ? $aisle_listing['price'] : null;
$aisle_photos = array_values(array_filter((array) ($aisle_listing['photos'] ?? []), 'is_array'));
$aisle_vars   = $aisle_listing['product']['variants'] ?? [];
$aisle_variant = is_array($aisle_vars) && isset($aisle_vars[0]) && is_array($aisle_vars[0]) ? $aisle_vars[0] : [];
$aisle_now    = $aisle_price !== null ? (float) ($aisle_price['amount'] ?? 0) : 0.0;
$aisle_was    = isset($aisle_variant['compare_at']) && is_numeric($aisle_variant['compare_at']) ? (float) $aisle_variant['compare_at'] : 0.0;
$aisle_off    = $aisle_was > $aisle_now && $aisle_now > 0 && ($aisle_variant['on_sale'] ?? true) !== false ? (int) round((1 - $aisle_now / $aisle_was) * 100) : 0;
$aisle_low    = is_numeric($aisle_variant['low_stock'] ?? null) ? (int) $aisle_variant['low_stock'] : 0;
$aisle_out    = $aisle_variant !== [] && ($aisle_variant['in_stock'] ?? true) === false;

require_once AISLE_DIR . '/common/stars.php';

$aisle_rating     = is_array($aisle_listing['rating'] ?? null) ? $aisle_listing['rating'] : [];
$aisle_rating_avg = isset($aisle_rating['average']) && is_numeric($aisle_rating['average']) ? (float) $aisle_rating['average'] : null;
$aisle_rating_n   = (int) ($aisle_rating['count'] ?? 0);
$aisle_ratingline = $aisle_rating_n > 0 && aisle_ratings_on() ? aisle_stars($aisle_rating_avg, $aisle_rating_n, true) : '';
$aisle_comments   = osc_comments_enabled();
$aisle_reviews    = $aisle_comments ? aisle_api('listings/' . $aisle_id . '/comments', ['sort' => 'newest', 'limit' => 6]) : null;

$aisle_rel     = aisle_api('listings/' . $aisle_id . '/related', ['limit' => 6, 'include' => 'rating', 'fields' => AISLE_CARD_FIELDS]);
$aisle_related = array_values(array_filter((array) ($aisle_rel['data'] ?? []), 'is_array'));

$aisle_cat   = is_array($aisle_listing['category'] ?? null) ? $aisle_listing['category'] : [];
$aisle_trail = array_values(array_filter((array) ($aisle_cat['path'] ?? []), 'is_array'));
if (!empty($aisle_cat['slug'])) {
    $aisle_trail[] = $aisle_cat;
}

// What the recently viewed rail needs, read by js/aisle-product.js.
$aisle_recent = [
    'id'    => $aisle_id,
    'title' => $aisle_title,
    'url'   => (string) ($aisle_listing['url'] ?? ''),
    'price' => $aisle_price !== null ? (string) ($aisle_price['formatted'] ?? '') : '',
    'photo' => (string) ($aisle_photos[0]['preview'] ?? $aisle_photos[0]['thumbnail'] ?? ''),
];

aisle_page_data_set('page', 'product');
aisle_page_data_set('items', [aisle_page_item($aisle_listing, 0)]);

osc_enqueue_style('aisle-product', aisle_asset('css/product.css'));
osc_register_script('aisle-product', aisle_asset('js/aisle-product.js'));
osc_enqueue_script('aisle-product');
osc_enqueue_style('aisle-reviews', aisle_asset('css/reviews.css'));
if ($aisle_comments) {
    osc_register_script('aisle-reviews', aisle_asset('js/aisle-reviews.js'));
    osc_enqueue_script('aisle-reviews');
}

// The partials read these as globals, as osc_current_web_theme_path() includes them inside a function.
foreach (compact('aisle_listing', 'aisle_title', 'aisle_price', 'aisle_photos', 'aisle_variant', 'aisle_off', 'aisle_low', 'aisle_out', 'aisle_related', 'aisle_reviews', 'aisle_ratingline') as $aisle_k => $aisle_v) {
    $GLOBALS[$aisle_k] = $aisle_v;
}

osc_current_web_theme_path('common/header.php');
?>
<div class="wrap" data-product="<?php echo osc_esc_html((string) json_encode($aisle_recent, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); ?>">
    <nav class="crumbs" aria-label="<?php echo osc_esc_html(__('Breadcrumb', 'aisle')); ?>">
        <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Home', 'aisle'); ?></a>
        <?php foreach ($aisle_trail as $aisle_t) { ?>
            <span>/</span><a href="<?php echo osc_esc_html(osc_search_url(['sCategory' => (string) ($aisle_t['slug'] ?? '')])); ?>"><?php echo osc_esc_html((string) ($aisle_t['name'] ?? '')); ?></a>
        <?php } ?>
        <span>/</span><span><?php echo osc_esc_html($aisle_title); ?></span>
    </nav>
    <div class="pdp">
        <?php osc_current_web_theme_path('common/product/gallery.php'); ?>
        <?php osc_current_web_theme_path('common/product/buy.php'); ?>
    </div>
    <?php
    if ($aisle_comments) {
        osc_current_web_theme_path('common/product/reviews.php');
    }
    osc_current_web_theme_path('common/product/related.php');
    ?>
</div>
<?php osc_current_web_theme_path('common/footer.php'); ?>
