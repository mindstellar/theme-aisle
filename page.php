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

$aisle_pid  = (int) osc_static_page_id();
$aisle_body = $aisle_pid > 0 ? aisle_api('pages/' . $aisle_pid) : null;
$aisle_page = is_array($aisle_body['data'] ?? null) ? $aisle_body['data'] : null;

if ($aisle_page === null) {
    http_response_code(404);
    osc_current_web_theme_path('404.php');

    return;
}

$aisle_tokens = ['{WEB_URL}', '{WEB_TITLE}'];
$aisle_values = [osc_base_url(), osc_page_title()];
$aisle_page['title'] = str_ireplace($aisle_tokens, $aisle_values, (string) $aisle_page['title']);
$aisle_page['text']  = str_ireplace($aisle_tokens, $aisle_values, (string) $aisle_page['text']);

osc_enqueue_style('aisle-page', aisle_asset('css/page.css'));
osc_current_web_theme_path('common/header.php');
?>
<div class="wrap">
    <article class="page">
        <nav class="crumbs" aria-label="<?php echo osc_esc_html(__('Breadcrumb', 'aisle')); ?>">
            <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Home', 'aisle'); ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo osc_esc_html((string) $aisle_page['title']); ?></span>
        </nav>
        <h1><?php echo osc_esc_html((string) $aisle_page['title']); ?></h1>
        <div class="page-body">
            <?php echo (string) $aisle_page['text']; ?>
        </div>
    </article>
</div>
<?php osc_current_web_theme_path('common/footer.php'); ?>
