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

if (!defined('ABS_PATH')) {
    exit('Direct access is not allowed.');
}

osc_enqueue_style('aisle-home', aisle_asset('css/home.css'));

aisle_page_data_set('page', 'home');

osc_current_web_theme_path('common/header.php');

echo '<div class="home-sections">';
$aisle_rows = aisle_is_preview() ? osc_customiser_preview_rows('home') : null;
if ($aisle_rows === null) {
    osc_show_widgets('home');
} else {
    foreach ($aisle_rows as $aisle_row) {
        if (($aisle_row['s_type'] ?? '') !== 'core.rich_text') {
            osc_render_widget($aisle_row);
            continue;
        }
        ob_start();
        osc_render_widget($aisle_row);
        $aisle_html = trim((string) ob_get_clean());
        if ($aisle_html !== '') {
            echo '<div class="wrap"' . aisle_sec_attrs($aisle_row, __('Text block', 'aisle')) . '>' . $aisle_html . '</div>';
        }
    }
}
echo '</div>';

osc_current_web_theme_path('common/footer.php');
