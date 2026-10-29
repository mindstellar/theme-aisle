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

$aisle_cats = aisle_section_categories();
if ($aisle_cats === []) {
    echo aisle_api_error(true);

    return;
}
$aisle_cat_urls = [];
foreach (aisle_menu('main') as $aisle_m) {
    $aisle_cat_urls[(string) $aisle_m['label']] = (string) $aisle_m['url'];
}
$aisle_heading_default = __('Shop by category', 'aisle');
$aisle_heading         = trim((string) ($config['title'] ?? '')) ?: $aisle_heading_default;
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Category row', 'aisle')); ?>>
    <div class="wrap">
        <div class="sec-head"><h2<?php echo aisle_field_attr('title', $aisle_heading_default); ?>><?php echo osc_esc_html($aisle_heading); ?></h2><a href="<?php echo osc_esc_html(osc_base_url(true) . '?page=search'); ?>"><?php _e('All products', 'aisle'); ?></a></div>
        <div class="cats">
            <?php foreach (array_slice($aisle_cats, 0, aisle_clamp($config['count'] ?? 7, 3, 12, 7)) as $aisle_cat) {
                $name  = (string) ($aisle_cat['name'] ?? '');
                $url   = $aisle_cat_urls[$name] ?? osc_search_url(['sCategory' => (string) ($aisle_cat['slug'] ?? '')]);
                $cover = (string) ($aisle_cat['cover']['preview'] ?? $aisle_cat['cover']['thumbnail'] ?? '');
                $cover_small = (string) ($aisle_cat['cover']['thumbnail'] ?? '');
                $count = (int) ($aisle_cat['listings_count'] ?? 0); ?>
                <a class="cat" href="<?php echo osc_esc_html($url); ?>">
                    <span class="ph">
                        <?php if ($cover !== '') { ?>
                            <img src="<?php echo osc_esc_html($cover); ?>"<?php if ($cover_small !== '' && $cover_small !== $cover) { ?> srcset="<?php echo osc_esc_html($cover_small); ?> 240w, <?php echo osc_esc_html($cover); ?> 800w" sizes="(min-width: 900px) 170px, 30vw"<?php } ?> alt="" loading="lazy" decoding="async">
                        <?php } else { ?>
                            <b aria-hidden="true"><?php echo osc_esc_html(mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8')); ?></b>
                        <?php } ?>
                    </span>
                    <?php echo osc_esc_html($name); ?>
                    <?php if ($count > 0) { ?>
                        <small class="num"><?php echo osc_esc_html(sprintf(_n('%d item', '%d items', $count, 'aisle'), $count)); ?></small>
                    <?php } ?>
                </a>
            <?php } ?>
        </div>
    </div>
</section>
