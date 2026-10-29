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

$aisle_promises = [];
foreach ([1 => 'truck', 2 => 'return', 3 => 'cash', 4 => 'shield'] as $aisle_n => $aisle_default) {
    $aisle_title = trim((string) ($config[$aisle_n . '_title'] ?? ''));
    if ($aisle_title === '') {
        continue;
    }
    $aisle_icon       = (string) ($config[$aisle_n . '_icon'] ?? $aisle_default);
    $aisle_promises[] = [
        'icon'  => in_array($aisle_icon, AISLE_PROMISE_ICONS, true) ? $aisle_icon : $aisle_default,
        'title' => $aisle_title,
        'text'  => trim((string) ($config[$aisle_n . '_text'] ?? '')),
        'n'     => $aisle_n,
    ];
}
if ($aisle_promises === [] && !aisle_is_preview()) {
    return;
}
?>
<section class="promise"<?php echo aisle_sec_attrs($row, __('Promise row', 'aisle')); ?>>
    <div class="wrap">
        <?php if ($aisle_promises === []) { ?>
            <p><?php echo osc_esc_html(__('Add a title to a promise to show this row.', 'aisle')); ?></p>
        <?php } ?>
        <?php foreach ($aisle_promises as $aisle_p) { ?>
            <div>
                <?php echo aisle_icon($aisle_p['icon']); ?>
                <p><b<?php echo aisle_field_attr($aisle_p['n'] . '_title'); ?>><?php echo osc_esc_html($aisle_p['title']); ?></b><?php if ($aisle_p['text'] !== '') { ?><span<?php echo aisle_field_attr($aisle_p['n'] . '_text'); ?>><?php echo osc_esc_html($aisle_p['text']); ?></span><?php } ?></p>
            </div>
        <?php } ?>
    </div>
</section>
