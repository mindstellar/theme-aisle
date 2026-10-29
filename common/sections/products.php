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

/** @var array<string,mixed> $config */
/** @var array<string,mixed> $row */

$aisle_source = (string) ($config['source'] ?? 'on_sale');
$aisle_limit  = aisle_clamp($config['count'] ?? 8, 4, 12, 8);
$aisle_rail   = ($config['layout'] ?? 'grid') === 'rail';
$aisle_search = osc_base_url(true) . '?page=search';

if ($aisle_source === 'newest') {
    $aisle_query = ['sort' => 'created', 'order' => 'desc', 'limit' => $aisle_limit, 'include' => 'rating'];
    $aisle_title = __('New in', 'aisle');
    $aisle_label = __('See all', 'aisle');
    $aisle_url   = $aisle_search . '&sOrder=dt_pub_date&iOrderType=desc';
} elseif ($aisle_source === 'category') {
    $aisle_slug = (string) ($config['category'] ?? '');
    if ($aisle_slug === '') {
        return;
    }
    $aisle_query = ['category' => $aisle_slug, 'limit' => $aisle_limit, 'include' => 'rating'];
    $aisle_title = '';
    foreach (aisle_section_categories() as $aisle_cat) {
        if ((string) ($aisle_cat['slug'] ?? '') === $aisle_slug) {
            $aisle_title = (string) ($aisle_cat['name'] ?? '');
        }
    }
    $aisle_label = __('See all', 'aisle');
    $aisle_url   = osc_search_url(['sCategory' => $aisle_slug]);
} else {
    $aisle_query = ['on_sale' => 'true', 'limit' => $aisle_limit, 'include' => 'rating'];
    $aisle_title = __('Deals this week', 'aisle');
    $aisle_label = __('See all deals', 'aisle');
    $aisle_url   = $aisle_search . '&sale=1';
}

$aisle_listings = aisle_section_listings($aisle_query);
if ($aisle_listings === []) {
    echo aisle_api_error(true);

    return;
}
$aisle_custom = trim((string) ($config['title'] ?? ''));
$aisle_title_default = $aisle_title;
$aisle_title  = $aisle_custom !== '' ? $aisle_custom : $aisle_title;
$aisle_custom = trim((string) ($config['link_label'] ?? ''));
$aisle_label  = $aisle_custom !== '' ? $aisle_custom : $aisle_label;
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Products', 'aisle')); ?>>
    <div class="wrap">
        <div class="sec-head"><h2<?php echo aisle_field_attr('title', $aisle_title_default); ?>><?php echo osc_esc_html($aisle_title); ?></h2><a href="<?php echo osc_esc_html($aisle_url); ?>"><?php echo osc_esc_html($aisle_label); ?></a></div>
        <div class="<?php echo $aisle_rail ? 'rail' : 'grid'; ?>">
            <?php foreach ($aisle_listings as $aisle_i => $aisle_listing) {
                echo aisle_card($aisle_listing, ['eager' => $aisle_rail || $aisle_i < 4]);
            } ?>
        </div>
    </div>
</section>
