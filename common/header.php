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

$aisle_brand  = aisle_brand();
$aisle_note   = trim((string) aisle_setting('announcement', ''));
$aisle_main   = aisle_menu('main');
$aisle_query  = osc_is_search_page() ? (string) osc_search_pattern() : '';
$aisle_account = osc_is_web_user_logged_in() ? osc_user_dashboard_url() : osc_user_login_url();
$aisle_deals_url = osc_search_url(['sale' => 1]);
$aisle_new_url   = osc_search_url(['sOrder' => 'dt_pub_date', 'iOrderType' => 'desc']);
$aisle_wish_i18n = [
    'saved'      => __('Saved to your wishlist', 'aisle'),
    'removed'    => __('Removed from your wishlist', 'aisle'),
    'failed'     => __('Could not update your wishlist', 'aisle'),
    'save'       => __('Save', 'aisle'),
    'savedLabel' => __('Saved', 'aisle'),
];
?><!DOCTYPE html>
<html lang="<?php echo osc_esc_html(str_replace('_', '-', osc_current_user_locale())); ?>" dir="<?php echo osc_locale_text_direction() === 'rtl' ? 'rtl' : 'ltr'; ?>" data-theme="<?php echo osc_esc_html(aisle_scheme()); ?>"<?php echo $aisle_brand !== '' ? ' data-brand="' . osc_esc_html($aisle_brand) . '"' : ''; ?><?php echo aisle_html_attrs(); ?>>
<head>
<?php osc_current_web_theme_path('common/head.php'); ?>
</head>
<body<?php echo aisle_is_full_width() ? ' class="layout-full-width"' : ''; ?>>
<?php osc_current_web_theme_path('common/icons.php'); ?>
<a class="skip" href="#main"><?php _e('Skip to content', 'aisle'); ?></a>
<div class="app">
<?php if ($aisle_note !== '') { ?>
    <div class="announce"><div class="wrap"><?php echo osc_esc_html($aisle_note); ?></div></div>
<?php } ?>
<header class="hdr" id="hdr">
    <div class="wrap">
        <div class="hdr-row">
            <button class="iconbtn menu-btn" type="button" data-menu-toggle aria-expanded="false" aria-controls="aisle-menu" aria-label="<?php echo osc_esc_html(__('Menu', 'aisle')); ?>"><?php echo aisle_icon('menu'); ?></button>
            <?php echo aisle_logo(); ?>
            <form class="search nocsrf" role="search" action="<?php echo osc_esc_html(osc_base_url(true)); ?>" method="get" data-search>
                <input type="hidden" name="page" value="search">
                <?php echo aisle_icon('search'); ?>
                <label class="sr" for="aisle-q"><?php _e('Search products', 'aisle'); ?></label>
                <input id="aisle-q" type="search" name="sPattern" value="<?php echo osc_esc_html($aisle_query); ?>" placeholder="<?php echo osc_esc_html(__('Search products', 'aisle')); ?>" autocomplete="off" data-search-input>
                <div class="suggest" data-suggest hidden></div>
            </form>
            <div class="hdr-actions">
                <button class="iconbtn" type="button" data-scheme-toggle aria-pressed="false" aria-label="<?php echo osc_esc_html(__('Colour scheme', 'aisle')); ?>"><?php echo aisle_icon('contrast'); ?></button>
                <a class="iconbtn hide-ph" href="<?php echo osc_esc_html(aisle_wishlist_url()); ?>" data-wishlist aria-label="<?php echo osc_esc_html(__('Wishlist', 'aisle')); ?>"><?php echo aisle_icon('heart'); ?><span class="count num" data-wishlist-count hidden>0</span></a>
                <button class="iconbtn" type="button" data-cart-open aria-haspopup="dialog" aria-label="<?php echo osc_esc_html(__('Cart', 'aisle')); ?>"><?php echo aisle_icon('bag'); ?><span class="count num" data-cart-count hidden>0</span></button>
                <script type="application/json" id="aisle-wish-i18n"><?php echo json_encode($aisle_wish_i18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?></script>
                <?php if (osc_users_enabled()) { ?>
                    <a class="iconbtn hide-ph" href="<?php echo osc_esc_html($aisle_account); ?>" aria-label="<?php echo osc_esc_html(__('Account', 'aisle')); ?>"><?php echo aisle_icon('user'); ?></a>
                <?php } ?>
            </div>
        </div>
        <nav class="catnav" data-catnav aria-label="<?php echo osc_esc_html(__('Categories', 'aisle')); ?>">
            <a class="sale" href="<?php echo osc_esc_html($aisle_deals_url); ?>"><?php _e('Deals', 'aisle'); ?></a>
            <?php foreach ($aisle_main as $aisle_item) { ?>
                <a href="<?php echo osc_esc_html((string) $aisle_item['url']); ?>"<?php echo aisle_menu_current((string) $aisle_item['url']) ? ' aria-current="page"' : ''; ?>><?php echo osc_esc_html((string) $aisle_item['label']); ?></a>
            <?php } ?>
            <a href="<?php echo osc_esc_html($aisle_new_url); ?>"><?php _e('New in', 'aisle'); ?></a>
        </nav>
    </div>
</header>
<?php
if (file_exists(AISLE_DIR . '/common/menu.php')) {
    osc_current_web_theme_path('common/menu.php');
}
ob_start();
osc_show_flash_message();
$aisle_flash = trim((string) ob_get_clean());
if ($aisle_flash !== '') {
    echo '<div class="wrap">' . $aisle_flash . '</div>';
}
?>
<main id="main">
