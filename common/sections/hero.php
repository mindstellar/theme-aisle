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

$aisle_deals = aisle_section_deals();

$aisle_title_default = __('Good things, *easy to find.*', 'aisle');
$aisle_title         = trim((string) ($config['title'] ?? ''));
if ($aisle_title === '') {
    $aisle_title = $aisle_title_default;
}
$aisle_title_html = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', osc_esc_html($aisle_title), 1);
$aisle_text       = trim((string) ($config['text'] ?? ''));
$aisle_image      = aisle_safe_image((string) ($config['image'] ?? ''));
if ($aisle_image === '') {
    $aisle_image = osc_settings_image_url('aisle', 'hero_image');
}
if ($aisle_image === '') {
    $aisle_image = osc_current_web_theme_url('img/hero.jpg');
}
$aisle_bundled = strpos($aisle_image, osc_current_web_theme_url('img/hero.jpg')) === 0;

$aisle_shop_url = osc_base_url(true) . '?page=search';
$aisle_btn1     = trim((string) ($config['cta_label'] ?? ''));
$aisle_btn1_url = aisle_safe_url((string) ($config['cta_url'] ?? ''));
$aisle_btn2     = trim((string) ($config['cta2_label'] ?? ''));
$aisle_btn2_url = aisle_safe_url((string) ($config['cta2_url'] ?? ''));
if ($aisle_btn1 === '') {
    $aisle_btn1 = $aisle_deals !== [] ? __('Shop the deals', 'aisle') : __('Shop now', 'aisle');
    if ($aisle_btn1_url === '') {
        $aisle_btn1_url = $aisle_deals !== [] ? $aisle_shop_url . '&sale=1' : $aisle_shop_url;
    }
    if ($aisle_btn2 === '' && $aisle_deals !== []) {
        $aisle_btn2 = __('New in', 'aisle');
        if ($aisle_btn2_url === '') {
            $aisle_btn2_url = $aisle_shop_url . '&sOrder=dt_pub_date&iOrderType=desc';
        }
    }
}
$aisle_btn1_url = $aisle_btn1_url !== '' ? $aisle_btn1_url : $aisle_shop_url;
$aisle_btn2_url = $aisle_btn2_url !== '' ? $aisle_btn2_url : $aisle_shop_url;

$aisle_perks = [];
foreach ([1 => 'truck', 2 => 'return', 3 => 'cash'] as $aisle_n => $aisle_icon) {
    $aisle_perk = trim((string) ($config['perk_' . $aisle_n] ?? ''));
    if ($aisle_perk !== '') {
        $aisle_perks[] = ['title' => $aisle_perk, 'icon' => $aisle_icon];
    }
}

// A listing number wins over "The first deal"; only "None" hides the card.
$aisle_tag  = null;
$aisle_mode = (string) ($config['tag'] ?? 'first_deal');
$aisle_pick = (int) ($config['tag_listing'] ?? 0);
if ($aisle_mode !== 'none' && $aisle_pick > 0) {
    $aisle_one = aisle_api_memo('listings/' . $aisle_pick, ['include' => 'rating']);
    $aisle_tag = is_array($aisle_one['data'] ?? null) ? $aisle_one['data'] : null;
}
if ($aisle_tag === null && $aisle_mode !== 'none') {
    $aisle_tag = $aisle_deals[0] ?? null;
}
$aisle_tag_img = is_array($aisle_tag) ? (string) ($aisle_tag['photos'][0]['thumbnail'] ?? '') : '';
?>
<section class="hero"<?php echo aisle_sec_attrs($row, __('Hero banner', 'aisle')); ?>>
    <div class="wrap">
        <div class="hero-grid">
            <div class="hero-copy">
                <h1<?php echo aisle_field_attr('title', $aisle_title_default); ?>><?php echo $aisle_title_html; ?></h1>
                <?php if ($aisle_text !== '') { ?>
                    <p<?php echo aisle_field_attr('text'); ?>><?php echo osc_esc_html($aisle_text); ?></p>
                <?php } ?>
                <div class="hero-cta">
                    <a class="btn btn-primary" href="<?php echo osc_esc_html($aisle_btn1_url); ?>"><?php echo osc_esc_html($aisle_btn1); ?></a>
                    <?php if ($aisle_btn2 !== '') { ?>
                        <a class="btn btn-ghost" href="<?php echo osc_esc_html($aisle_btn2_url); ?>"><?php echo osc_esc_html($aisle_btn2); ?></a>
                    <?php } ?>
                </div>
                <?php if ($aisle_perks !== []) { ?>
                    <div class="hero-perks">
                        <?php foreach ($aisle_perks as $aisle_perk) { ?>
                            <span><?php echo aisle_icon($aisle_perk['icon'], true) . osc_esc_html($aisle_perk['title']); ?></span>
                        <?php } ?>
                    </div>
                <?php } ?>
            </div>
            <div class="hero-img">
                <?php if ($aisle_bundled) { ?>
                    <picture>
                        <source type="image/webp" srcset="<?php echo osc_esc_html(osc_current_web_theme_url('img/hero-800.webp')); ?> 800w, <?php echo osc_esc_html(osc_current_web_theme_url('img/hero-1200.webp')); ?> 1200w" sizes="(min-width: 900px) 600px, 100vw">
                        <img src="<?php echo osc_esc_html($aisle_image); ?>" width="1200" height="672" alt="" fetchpriority="high" decoding="async">
                    </picture>
                <?php } else { ?>
                    <img src="<?php echo osc_esc_html($aisle_image); ?>" alt="" fetchpriority="high" decoding="async">
                <?php } ?>
                <?php if (is_array($aisle_tag)) { ?>
                    <a class="hero-tag" href="<?php echo osc_esc_html((string) ($aisle_tag['url'] ?? '#')); ?>">
                        <?php if ($aisle_tag_img !== '') { ?><img src="<?php echo osc_esc_html($aisle_tag_img); ?>" alt=""><?php } ?>
                        <span><b><?php echo osc_esc_html((string) ($aisle_tag['title'] ?? '')); ?></b><?php echo aisle_price(is_array($aisle_tag['price'] ?? null) ? $aisle_tag['price'] : null); ?></span>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
