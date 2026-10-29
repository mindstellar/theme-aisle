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

if (!function_exists('aisle_stars_row')) {
    require_once AISLE_DIR . '/common/stars.php';
}

$aisle_score  = trim((string) ($config['score'] ?? ''));
$aisle_line   = trim((string) ($config['score_line'] ?? ''));
$aisle_quotes = [];
foreach ([1, 2, 3] as $aisle_n) {
    $aisle_quote = trim((string) ($config[$aisle_n . '_text'] ?? ''));
    if ($aisle_quote !== '') {
        $aisle_quotes[] = [
            'text'  => $aisle_quote,
            'name'  => trim((string) ($config[$aisle_n . '_name'] ?? '')),
            'stars' => aisle_clamp($config[$aisle_n . '_stars'] ?? 5, 1, 5, 5),
            'n'     => $aisle_n,
        ];
    }
}
if ($aisle_score === '' && $aisle_quotes === []) {
    return;
}
$aisle_score_stars = is_numeric($aisle_score) ? aisle_clamp((int) round((float) $aisle_score), 1, 5, 5) : 5;
?>
<section class="sec"<?php echo aisle_sec_attrs($row, __('Reviews', 'aisle')); ?>>
    <div class="wrap">
        <div class="reviews">
            <?php if ($aisle_score !== '') { ?>
                <div>
                    <span class="score num"<?php echo aisle_field_attr('score'); ?>><?php echo osc_esc_html($aisle_score); ?></span>
                    <div class="stars-row"><?php echo aisle_stars_row($aisle_score_stars, $aisle_score); ?></div>
                    <?php if ($aisle_line !== '') { ?><p class="num"<?php echo aisle_field_attr('score_line'); ?>><?php echo osc_esc_html($aisle_line); ?></p><?php } ?>
                </div>
            <?php } ?>
            <?php if ($aisle_quotes !== []) { ?>
                <div class="quotes">
                    <?php foreach ($aisle_quotes as $aisle_q) { ?>
                        <blockquote class="quote">
                            <?php echo aisle_stars_row($aisle_q['stars'], (string) $aisle_q['stars']); ?>
                            <p<?php echo aisle_field_attr($aisle_q['n'] . '_text'); ?>><?php echo osc_esc_html($aisle_q['text']); ?></p>
                            <?php if ($aisle_q['name'] !== '') { ?><footer<?php echo aisle_field_attr($aisle_q['n'] . '_name'); ?>><?php echo osc_esc_html($aisle_q['name']); ?></footer><?php } ?>
                        </blockquote>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>
    </div>
</section>
