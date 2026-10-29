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

$aisle_error = View::getInstance()->_get('error');
if (!is_string($aisle_error) || $aisle_error === '') {
    $aisle_error = __('Something went wrong. Please try again in a moment.', 'aisle');
}
osc_current_web_theme_path('common/header.php');
?>
<div class="wrap">
    <section class="error-page">
        <h1><?php _e('Something went wrong', 'aisle'); ?></h1>
        <p><?php echo osc_esc_html($aisle_error); ?></p>
        <a class="btn btn-primary" href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Back to the shop', 'aisle'); ?></a>
    </section>
</div>
<?php osc_current_web_theme_path('common/footer.php'); ?>
