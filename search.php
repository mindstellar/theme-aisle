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

require_once AISLE_DIR . '/common/catalogue.php';

$aisle_state = aisle_catalogue_state();

// Category, its parents and its searchable fields.
$aisle_tree   = aisle_api('categories', ['tree' => 'true']);
$aisle_found  = $aisle_state['slug'] !== '' ? aisle_category_find((array) ($aisle_tree['data'] ?? []), $aisle_state['slug']) : null;
$aisle_cat    = $aisle_found['cat'] ?? null;
$aisle_trail  = $aisle_found['trail'] ?? [];
if ($aisle_cat === null) {
    $aisle_state['slug'] = '';
} else {
    $aisle_state['slug'] = (string) $aisle_cat['slug'];
}

$aisle_fields = [];
if ($aisle_cat !== null) {
    $aisle_body = aisle_api('custom-fields', ['category' => $aisle_state['slug']]);
    foreach ((array) ($aisle_body['data'] ?? []) as $aisle_f) {
        if (is_array($aisle_f) && !empty($aisle_f['searchable']) && isset($aisle_f['id'], $aisle_f['name'])) {
            $aisle_fields[] = $aisle_f;
        }
    }
}
// Drop custom-field filters the category cannot take, or values its list does not offer.
$aisle_known = [];
$aisle_state['multi'] = [];
foreach ($aisle_fields as $aisle_f) {
    $aisle_known[(int) $aisle_f['id']] = array_values(array_filter((array) ($aisle_f['options'] ?? []), 'is_string'));
}
foreach ($aisle_state['meta'] as $aisle_id => $aisle_vs) {
    if (!isset($aisle_known[$aisle_id])) {
        unset($aisle_state['meta'][$aisle_id]);
        continue;
    }
    if ($aisle_known[$aisle_id] !== []) {
        $aisle_state['meta'][$aisle_id] = array_values(array_intersect($aisle_vs, $aisle_known[$aisle_id]));
        $aisle_state['multi'][$aisle_id] = true;
        if ($aisle_state['meta'][$aisle_id] === []) {
            unset($aisle_state['meta'][$aisle_id]);
        }
    }
}

$aisle_query = aisle_catalogue_query($aisle_state);
$aisle_res   = aisle_api('listings', $aisle_query);
// The head reuses this list for its ItemList when it asks for the same page.
$GLOBALS['aisle_catalogue'] = [$aisle_query, $aisle_res];
$aisle_items = array_values(array_filter((array) ($aisle_res['data'] ?? []), 'is_array'));
$aisle_total = (int) ($aisle_res['meta']['total'] ?? count($aisle_items));
$aisle_pages = max(1, (int) ($aisle_res['meta']['pages'] ?? (int) ceil($aisle_total / aisle_per_page())));
$aisle_page_no = (int) $aisle_state['page'];

$aisle_clear_url = aisle_catalogue_url($aisle_state, ['pmin' => null, 'pmax' => null, 'stock' => false, 'sale' => false, 'meta' => [], 'opt' => []]);
$aisle_nfilters  = aisle_filter_count($aisle_state);
$aisle_title     = $aisle_cat !== null ? (string) $aisle_cat['name'] : ($aisle_state['q'] !== '' ? sprintf(__('Results for “%s”', 'aisle'), $aisle_state['q']) : __('All products', 'aisle'));
$aisle_subs      = $aisle_cat !== null ? array_values(array_filter((array) ($aisle_cat['children'] ?? []), 'is_array')) : [];

aisle_page_data_set('page', $aisle_cat !== null && $aisle_state['q'] === '' ? 'category' : 'search');
aisle_page_data_set('list', ['id' => $aisle_state['slug'] !== '' ? $aisle_state['slug'] : 'all', 'name' => $aisle_title]);
aisle_page_data_set('items', aisle_page_items($aisle_items, ($aisle_page_no - 1) * aisle_per_page()));
if ($aisle_state['q'] !== '') {
    aisle_page_data_set('search_term', $aisle_state['q']);
}

osc_enqueue_style('aisle-catalogue', aisle_asset('css/catalogue.css'));
osc_register_script('aisle-catalogue', aisle_asset('js/aisle-catalogue.js'));
osc_enqueue_script('aisle-catalogue');

osc_current_web_theme_path('common/header.php');

$aisle_ctx = [
    'aisle_state'     => $aisle_state,
    'aisle_items'     => $aisle_items,
    'aisle_fields'    => $aisle_fields,
    'aisle_clear_url' => $aisle_clear_url,
    'aisle_pages'     => $aisle_pages,
];

$aisle_sorts = [
    ''           => __('Recommended', 'aisle'),
    'price_asc'  => __('Price: low to high', 'aisle'),
    'price_desc' => __('Price: high to low', 'aisle'),
    'new'        => __('Newest', 'aisle'),
];
?>
<div class="wrap">
    <nav class="crumbs" aria-label="<?php echo osc_esc_html(__('Breadcrumb', 'aisle')); ?>">
        <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Home', 'aisle'); ?></a>
        <?php foreach ($aisle_trail as $aisle_t) { ?>
            <span>/</span><a href="<?php echo osc_esc_html(osc_search_url(['sCategory' => (string) $aisle_t['slug']])); ?>"><?php echo osc_esc_html((string) $aisle_t['name']); ?></a>
        <?php } ?>
        <span>/</span><span><?php echo osc_esc_html($aisle_title); ?></span>
    </nav>
    <div class="cat-head">
        <div>
            <h1><?php echo osc_esc_html($aisle_title); ?></h1>
            <p class="num"><?php echo osc_esc_html(sprintf(_n('%d product', '%d products', $aisle_total, 'aisle'), $aisle_total)); ?></p>
        </div>
    </div>
    <?php if ($aisle_subs !== []) { ?>
        <div class="subcats">
            <a class="chip on" href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state)); ?>"><?php _e('All', 'aisle'); ?></a>
            <?php foreach ($aisle_subs as $aisle_s) { ?>
                <a class="chip" href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['slug' => (string) $aisle_s['slug'], 'meta' => [], 'opt' => []])); ?>"><?php echo osc_esc_html((string) $aisle_s['name']); ?></a>
            <?php } ?>
        </div>
    <?php } ?>
    <div class="shop">
        <aside class="filters" id="filters" aria-label="<?php echo osc_esc_html(__('Filters', 'aisle')); ?>" data-filters-home>
            <?php aisle_partial('filters.php', $aisle_ctx); ?>
        </aside>
        <div>
            <div class="toolbar">
                <button class="btn btn-ghost filter-btn" type="button" data-sheet-open aria-haspopup="dialog"><?php echo aisle_icon('filter', true); ?><?php _e('Filters', 'aisle'); ?> <?php if ($aisle_nfilters > 0) { ?><span class="num">(<?php echo $aisle_nfilters; ?>)</span><?php } ?></button>
                <?php aisle_partial('chips.php', $aisle_ctx); ?>
                <label class="sr" for="aisle-sort"><?php _e('Sort', 'aisle'); ?></label>
                <select class="sort" id="aisle-sort" data-sort>
                    <?php foreach ($aisle_sorts as $aisle_key => $aisle_label) { ?>
                        <option value="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['sort' => $aisle_key])); ?>"<?php echo $aisle_key === $aisle_state['sort'] ? ' selected' : ''; ?>><?php echo osc_esc_html($aisle_label); ?></option>
                    <?php } ?>
                </select>
            </div>
            <?php if ($aisle_items === [] && aisle_api_failed()) {
                echo aisle_api_error();
            } elseif ($aisle_items === []) { ?>
                <p class="empty-line">
                    <?php _e('No products match.', 'aisle'); ?>
                    <?php if ($aisle_nfilters > 0) { ?>
                        <a href="<?php echo osc_esc_html($aisle_clear_url); ?>"><?php _e('Clear the filters', 'aisle'); ?></a>
                    <?php } elseif ($aisle_cat !== null) { ?>
                        <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Back to the shop', 'aisle'); ?></a>
                    <?php } else { ?>
                        <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Back to the shop', 'aisle'); ?></a>
                    <?php } ?>
                </p>
            <?php } else { ?>
                <div class="grid" data-grid>
                    <?php foreach ($aisle_items as $aisle_i => $aisle_item) {
                        echo aisle_card($aisle_item, ['eager' => $aisle_i < 4]);
                    } ?>
                </div>
                <?php
                $aisle_shown = min($aisle_total, ($aisle_page_no - 1) * aisle_per_page() + count($aisle_items));
                ?>
                <div class="more" data-more>
                    <span class="num" data-shown><?php echo osc_esc_html(sprintf(__('Showing %1$d of %2$d', 'aisle'), $aisle_shown, $aisle_total)); ?></span>
                    <div class="progress" aria-hidden="true"><i style="width:<?php echo $aisle_total > 0 ? (int) round($aisle_shown / $aisle_total * 100) : 0; ?>%"></i></div>
                    <?php if ($aisle_page_no < $aisle_pages) { ?>
                        <a class="btn btn-ghost" href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['page' => $aisle_page_no + 1])); ?>" data-loadmore data-page="<?php echo $aisle_page_no; ?>" data-pages="<?php echo $aisle_pages; ?>" data-total="<?php echo $aisle_total; ?>"><?php _e('Show more', 'aisle'); ?></a>
                    <?php } ?>
                    <?php aisle_partial('pagination.php', $aisle_ctx); ?>
                </div>
            <?php } ?>
        </div>
    </div>
</div>
<div class="scrim" data-scrim></div>
<aside class="sheet" id="aisle-sheet" role="dialog" aria-modal="true" aria-label="<?php echo osc_esc_html(__('Filters', 'aisle')); ?>" aria-hidden="true" data-sheet
       data-category="<?php echo osc_esc_html($aisle_state['slug']); ?>" data-q="<?php echo osc_esc_html($aisle_state['q']); ?>">
    <div class="grab"></div>
    <div class="sheet-h"><h2><?php _e('Filters', 'aisle'); ?></h2><button class="iconbtn" type="button" data-sheet-close aria-label="<?php echo osc_esc_html(__('Close filters', 'aisle')); ?>"><?php echo aisle_icon('x'); ?></button></div>
    <div class="sheet-b" data-sheet-body></div>
    <div class="sheet-f"><a class="btn btn-ghost" href="<?php echo osc_esc_html($aisle_clear_url); ?>" data-sheet-clear><?php _e('Clear', 'aisle'); ?></a><button class="btn btn-primary" type="submit" form="aisle-filters" data-sheet-apply><?php _e('Show products', 'aisle'); ?></button></div>
</aside>
<script type="application/json" id="aisle-cat-i18n"><?php echo json_encode([
    'show'     => __('Show %d products', 'aisle'),
    'showNone' => __('No products match', 'aisle'),
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
<?php osc_current_web_theme_path('common/footer.php'); ?>
