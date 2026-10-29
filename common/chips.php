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

/*
 * Applied filters as removable chips. Expects $aisle_state, $aisle_fields, $aisle_clear_url.
 * Each chip is a link to the same results without that one filter.
 */
$aisle_chips = [];
if ($aisle_state['pmin'] !== null || $aisle_state['pmax'] !== null) {
    $aisle_lo = $aisle_state['pmin'] ?? 0;
    $aisle_label = $aisle_state['pmax'] === null
        ? sprintf(__('From %s', 'aisle'), aisle_money((string) $aisle_lo, (string) osc_currency()))
        : sprintf(__('%1$s to %2$s', 'aisle'), aisle_money((string) $aisle_lo, (string) osc_currency()), aisle_money((string) $aisle_state['pmax'], (string) osc_currency()));
    $aisle_chips[] = [$aisle_label, aisle_catalogue_url($aisle_state, ['pmin' => null, 'pmax' => null])];
}
if ($aisle_state['stock']) {
    $aisle_chips[] = [__('In stock', 'aisle'), aisle_catalogue_url($aisle_state, ['stock' => false])];
}
if ($aisle_state['sale']) {
    $aisle_chips[] = [__('On sale', 'aisle'), aisle_catalogue_url($aisle_state, ['sale' => false])];
}
foreach ($aisle_state['opt'] ?? [] as $aisle_name => $aisle_values) {
    foreach ($aisle_values as $aisle_value) {
        $aisle_rest = $aisle_state['opt'];
        $aisle_rest[$aisle_name] = array_values(array_diff($aisle_values, [$aisle_value]));
        if ($aisle_rest[$aisle_name] === []) {
            unset($aisle_rest[$aisle_name]);
        }
        $aisle_chips[] = [$aisle_name . ': ' . $aisle_value, aisle_catalogue_url($aisle_state, ['opt' => $aisle_rest])];
    }
}
foreach ($aisle_state['meta'] as $aisle_id => $aisle_values) {
    $aisle_name = '';
    foreach ($aisle_fields as $aisle_f) {
        if ((int) $aisle_f['id'] === $aisle_id) {
            $aisle_name = (string) $aisle_f['name'];
        }
    }
    foreach ($aisle_values as $aisle_value) {
        $aisle_rest = $aisle_state['meta'];
        $aisle_rest[$aisle_id] = array_values(array_diff($aisle_values, [$aisle_value]));
        if ($aisle_rest[$aisle_id] === []) {
            unset($aisle_rest[$aisle_id]);
        }
        $aisle_chips[] = [($aisle_name !== '' ? $aisle_name . ': ' : '') . ($aisle_value === '1' ? __('Yes', 'aisle') : $aisle_value), aisle_catalogue_url($aisle_state, ['meta' => $aisle_rest])];
    }
}
if ($aisle_chips !== []) { ?>
    <div class="applied" data-applied>
        <?php foreach ($aisle_chips as $aisle_chip) { ?>
            <a class="chip" href="<?php echo osc_esc_html($aisle_chip[1]); ?>"><?php echo osc_esc_html($aisle_chip[0]); ?><span class="sr"> <?php _e('Remove', 'aisle'); ?></span><?php echo aisle_icon('x', true); ?></a>
        <?php } ?>
        <a class="clear" href="<?php echo osc_esc_html($aisle_clear_url); ?>"><?php _e('Clear all', 'aisle'); ?></a>
    </div>
<?php }
