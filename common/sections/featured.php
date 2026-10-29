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

$aisle_id = (int) ($config['listing'] ?? 0);
if ($aisle_id <= 0) {
    return;
}
$aisle_res     = aisle_api_memo('listings/' . $aisle_id, ['include' => 'rating']);
$aisle_listing = is_array($aisle_res['data'] ?? null) ? $aisle_res['data'] : null;
if ($aisle_listing === null) {
    return;
}
$aisle_heading = trim((string) ($config['heading'] ?? ''));
$aisle_text    = trim((string) ($config['text'] ?? ''));
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Featured product', 'aisle')); ?>>
    <div class="wrap">
        <div class="featured">
            <div class="featured-copy">
                <?php if ($aisle_heading !== '') { ?><h2<?php echo aisle_field_attr('heading'); ?>><?php echo osc_esc_html($aisle_heading); ?></h2><?php } ?>
                <?php if ($aisle_text !== '') { ?><p<?php echo aisle_field_attr('text'); ?>><?php echo osc_esc_html($aisle_text); ?></p><?php } ?>
            </div>
            <?php echo aisle_card($aisle_listing, ['eager' => false]); ?>
        </div>
    </div>
</section>
