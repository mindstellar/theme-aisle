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

$aisle_heading = trim((string) ($config['heading'] ?? ''));
$aisle_image   = aisle_safe_image((string) ($config['image'] ?? ''));
if ($aisle_heading === '' && $aisle_image === '') {
    return;
}
$aisle_text  = trim((string) ($config['text'] ?? ''));
$aisle_label = trim((string) ($config['button_label'] ?? ''));
$aisle_url   = aisle_safe_url((string) ($config['button_url'] ?? ''));
$aisle_align = in_array($config['align'] ?? 'left', ['left', 'center', 'right'], true) ? (string) $config['align'] : 'left';
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Banner', 'aisle')); ?>>
    <div class="wrap">
        <div class="banner banner-<?php echo $aisle_align; ?>">
            <?php if ($aisle_image !== '') { ?>
                <img src="<?php echo osc_esc_html($aisle_image); ?>" alt="" loading="lazy" decoding="async">
            <?php } ?>
            <div class="banner-copy">
                <?php if ($aisle_heading !== '') { ?><h2<?php echo aisle_field_attr('heading'); ?>><?php echo osc_esc_html($aisle_heading); ?></h2><?php } ?>
                <?php if ($aisle_text !== '') { ?><p<?php echo aisle_field_attr('text'); ?>><?php echo osc_esc_html($aisle_text); ?></p><?php } ?>
                <?php if ($aisle_label !== '' && $aisle_url !== '') { ?>
                    <a class="btn btn-primary" href="<?php echo osc_esc_html($aisle_url); ?>"><?php echo osc_esc_html($aisle_label); ?></a>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
