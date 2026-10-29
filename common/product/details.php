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

global $aisle_listing;

// Description (HTML as the API gives it) and custom fields. Needs $aisle_listing from item.php.
$aisle_desc   = trim((string) ($aisle_listing['description'] ?? ''));
$aisle_fields = [];
foreach ((array) ($aisle_listing['custom_fields'] ?? []) as $aisle_f) {
    if (is_array($aisle_f) && isset($aisle_f['name']) && (string) ($aisle_f['value'] ?? '') !== '') {
        $aisle_fields[] = $aisle_f;
    }
}
if ($aisle_desc === '' && $aisle_fields === []) {
    return;
}
?>
<div class="accs">
    <?php if ($aisle_desc !== '') { ?>
        <details class="acc" open>
            <summary><?php _e('Details', 'aisle'); ?><?php echo aisle_icon('plus', true); ?></summary>
            <div class="rich"><?php echo $aisle_desc; ?></div>
        </details>
    <?php } ?>
    <?php if ($aisle_fields !== []) { ?>
        <details class="acc"<?php echo $aisle_desc === '' ? ' open' : ''; ?>>
            <summary><?php _e('Specifications', 'aisle'); ?><?php echo aisle_icon('plus', true); ?></summary>
            <div>
                <dl class="specs">
                    <?php foreach ($aisle_fields as $aisle_f) {
                        $aisle_v = $aisle_f['value'];
                        $aisle_v = is_array($aisle_v) ? implode(', ', array_map('strval', $aisle_v)) : (string) $aisle_v; ?>
                        <dt><?php echo osc_esc_html((string) $aisle_f['name']); ?></dt>
                        <dd><?php echo osc_esc_html($aisle_v); ?></dd>
                    <?php } ?>
                </dl>
            </div>
        </details>
    <?php } ?>
</div>
