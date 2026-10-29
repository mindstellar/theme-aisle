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
 * Numbered pages with real URLs. Expects $aisle_state and $aisle_pages (page count).
 */
if ($aisle_pages > 1) {
    $aisle_cur = (int) $aisle_state['page'];
    $aisle_show = array_unique(array_filter([1, $aisle_cur - 1, $aisle_cur, $aisle_cur + 1, $aisle_pages], static fn ($p) => $p >= 1 && $p <= $aisle_pages));
    sort($aisle_show);
    ?>
    <nav class="pages" aria-label="<?php echo osc_esc_html(__('Pages', 'aisle')); ?>">
        <?php if ($aisle_cur > 1) { ?>
            <a href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['page' => $aisle_cur - 1])); ?>" rel="prev"><?php _e('Previous', 'aisle'); ?></a>
        <?php } ?>
        <?php $aisle_last = 0; foreach ($aisle_show as $aisle_p) { ?>
            <?php if ($aisle_p - $aisle_last > 1) { ?><span aria-hidden="true">…</span><?php } ?>
            <?php if ($aisle_p === $aisle_cur) { ?>
                <a class="on" href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['page' => $aisle_p])); ?>" aria-current="page"><?php echo $aisle_p; ?></a>
            <?php } else { ?>
                <a href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['page' => $aisle_p])); ?>"><?php echo $aisle_p; ?></a>
            <?php } ?>
            <?php $aisle_last = $aisle_p; } ?>
        <?php if ($aisle_cur < $aisle_pages) { ?>
            <a href="<?php echo osc_esc_html(aisle_catalogue_url($aisle_state, ['page' => $aisle_cur + 1])); ?>" rel="next"><?php _e('Next', 'aisle'); ?></a>
        <?php } ?>
    </nav>
<?php }
