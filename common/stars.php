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

if (!function_exists('aisle_stars_row')) {
    /**
     * Five star icons, the first $filled lit, as one labelled image.
     */
    function aisle_stars_row(int $filled, string $value): string
    {
        $out = '<span class="stars" role="img" aria-label="' . osc_esc_html(sprintf(__('Rated %s out of 5', 'aisle'), $value)) . '">';
        for ($i = 1; $i <= 5; $i++) {
            $out .= '<svg class="ico sm' . ($i <= $filled ? ' on' : '') . '" aria-hidden="true"><use href="#i-star"/></svg>';
        }

        return $out . '</span>';
    }
}

if (!function_exists('aisle_stars')) {
    /**
     * Five stars (filled to the rounded average), the average, and the count.
     * With $link the count reads "N reviews" and links to the reviews band; without, "(N)".
     * Empty when there is no average.
     */
    function aisle_stars(?float $average, ?int $count = null, bool $link = false): string
    {
        if ($average === null || $average <= 0) {
            return '';
        }
        $average = min(5.0, $average);
        $shown = number_format($average, 1, '.', '');
        $out   = aisle_stars_row((int) round($average), $shown) . '<b class="num">' . osc_esc_html($shown) . '</b>';

        if ($count !== null && $count > 0) {
            if ($link) {
                $out .= '<a href="#reviews" class="num">' . osc_esc_html(sprintf(_n('%d review', '%d reviews', $count, 'aisle'), $count)) . '</a>';
            } else {
                $out .= '<span class="num">(' . $count . ')</span>';
            }
        }

        return $out;
    }
}
