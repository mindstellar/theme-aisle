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

global $aisle_listing, $aisle_title, $aisle_off, $aisle_photos;

// Needs $aisle_listing, $aisle_title, $aisle_off and $aisle_photos from item.php.
osc_current_web_theme_path('common/product/variants.php');
$aisle_gs = aisle_variant_state($aisle_listing);
if ($aisle_gs['rows'] !== []) {
    $aisle_off = (int) $aisle_gs['rows'][$aisle_gs['default']]['off'];
}
$aisle_main = $aisle_photos[0] ?? null;
?>
<div class="gallery" data-gallery>
    <?php if (count($aisle_photos) > 1) { ?>
        <div class="thumbs">
            <?php foreach ($aisle_photos as $aisle_i => $aisle_p) { ?>
                <button type="button" data-view="<?php echo $aisle_i; ?>" data-photo-id="<?php echo (int) ($aisle_p['id'] ?? 0); ?>" data-src="<?php echo osc_esc_html((string) ($aisle_p['normal'] ?? '')); ?>" aria-current="<?php echo $aisle_i === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo osc_esc_html(sprintf(__('Photo %d', 'aisle'), $aisle_i + 1)); ?>"><img src="<?php echo osc_esc_html((string) ($aisle_p['thumbnail'] ?? '')); ?>" alt="" loading="lazy"></button>
            <?php } ?>
        </div>
    <?php } ?>
    <div>
        <?php if (is_array($aisle_main)) { ?>
        <button type="button" class="mainimg" data-main aria-pressed="false" aria-label="<?php echo osc_esc_html(__('Zoom in on the photo', 'aisle')); ?>">
        <?php } else { ?>
        <div class="mainimg" data-main>
        <?php } ?>
            <?php if (is_array($aisle_main)) { ?>
                <img src="<?php echo osc_esc_html((string) ($aisle_main['normal'] ?? $aisle_main['preview'] ?? '')); ?>" alt="<?php echo osc_esc_html($aisle_title); ?>" fetchpriority="high" decoding="async">
            <?php } ?>
            <span class="deal num" data-deal<?php echo $aisle_off >= 1 ? '' : ' hidden'; ?>><?php echo osc_esc_html('−' . $aisle_off . '%'); ?></span>
        <?php echo is_array($aisle_main) ? '</button>' : '</div>'; ?>
        <?php if (count($aisle_photos) > 1) { ?>
            <div class="dots">
                <?php foreach ($aisle_photos as $aisle_i => $aisle_p) { ?>
                    <button type="button" data-view="<?php echo $aisle_i; ?>" aria-current="<?php echo $aisle_i === 0 ? 'true' : 'false'; ?>" aria-label="<?php echo osc_esc_html(sprintf(__('Photo %d', 'aisle'), $aisle_i + 1)); ?>"></button>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
</div>
