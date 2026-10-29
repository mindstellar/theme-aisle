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

global $aisle_related;

// Needs $aisle_related (API listings) from item.php. The recently viewed rail is filled by js/aisle-product.js.
?>
<?php if ($aisle_related !== []) { ?>
    <section class="sec">
        <div class="sec-head"><h2><?php _e('You may also like', 'aisle'); ?></h2></div>
        <div class="rail">
            <?php foreach ($aisle_related as $aisle_r) {
                echo aisle_card($aisle_r);
            } ?>
        </div>
    </section>
<?php } ?>
<section class="sec" data-recent hidden>
    <div class="sec-head"><h2><?php _e('Recently viewed', 'aisle'); ?></h2></div>
    <div class="rail" data-recent-list></div>
</section>
