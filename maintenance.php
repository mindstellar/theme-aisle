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

$aisle_site   = (string) osc_get_preference('pageTitle');
$aisle_note   = function_exists('osc_sanitize_maintenance_message') ? osc_sanitize_maintenance_message(osc_get_preference('maintenance_message')) : '';
$aisle_scheme = function_exists('aisle_scheme') ? aisle_scheme() : '';
$aisle_brand  = function_exists('aisle_brand') ? aisle_brand() : '';
$aisle_font   = function_exists('aisle_font') ? aisle_font() : '';
$aisle_files  = function_exists('aisle_font_files') ? aisle_font_files() : [];
?><!DOCTYPE html>
<html lang="<?php echo osc_esc_html(str_replace('_', '-', osc_current_user_locale())); ?>"<?php echo $aisle_scheme !== '' ? ' data-theme="' . osc_esc_html($aisle_scheme) . '"' : ''; ?><?php echo $aisle_brand !== '' ? ' data-brand="' . osc_esc_html($aisle_brand) . '"' : ''; ?><?php echo $aisle_font !== '' ? ' data-font="' . osc_esc_html($aisle_font) . '"' : ''; ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?php echo osc_esc_html($aisle_site . ' - ' . __('back soon', 'aisle')); ?></title>
<?php foreach ($aisle_files as $aisle_file) { ?>
    <link rel="preload" href="<?php echo osc_esc_html(osc_current_web_theme_url($aisle_file)); ?>" as="font" type="font/woff2" crossorigin>
<?php } ?>
    <link rel="stylesheet" href="<?php echo osc_esc_html(osc_current_web_theme_url('css/tokens.css')); ?>">
    <style>
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; text-align: center; padding: 32px; background: var(--bg); color: var(--ink); font: 400 17px/1.6 var(--body); }
        main { max-width: 520px; display: grid; gap: 12px; justify-items: center; }
        .mark { width: 40px; height: 40px; border-radius: 12px; background: var(--brand); display: grid; place-items: center; color: var(--brand-ink); font: 800 22px var(--display); }
        h1 { margin: 0; font: 800 clamp(28px, 6vw, 44px)/1.1 var(--display); letter-spacing: -0.03em; }
        p { margin: 0; color: var(--muted); }
    </style>
    <script>
        (function () { try { var v = localStorage.getItem('aisle-scheme'); if (v === 'light' || v === 'dark') { document.documentElement.setAttribute('data-theme', v); } else if (v === 'system') { document.documentElement.removeAttribute('data-theme'); } } catch (e) {} })();
    </script>
</head>
<body>
<main>
    <span class="mark" aria-hidden="true"><?php echo osc_esc_html($aisle_site !== '' ? mb_strtoupper(mb_substr($aisle_site, 0, 1, 'UTF-8'), 'UTF-8') : 'A'); ?></span>
    <h1><?php _e('We will be back soon', 'aisle'); ?></h1>
    <p><?php echo $aisle_note !== '' ? nl2br(osc_esc_html($aisle_note), false) : osc_esc_html(__('The shop is being updated. Please check again in a few minutes.', 'aisle')); ?></p>
    <p><?php echo osc_esc_html($aisle_site); ?></p>
</main>
</body>
</html>
