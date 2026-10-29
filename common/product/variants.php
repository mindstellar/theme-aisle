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

if (!function_exists('aisle_variant_state')) {
    /**
     * What the buy block and the gallery show for a product: option groups, display rows for
     * every variant, and the variant picked first (the first one in stock).
     *
     * @param array<string,mixed> $listing
     *
     * @return array{options:array<int,array{name:string,values:array<int,string>}>,rows:array<int,array<string,mixed>>,default:int}
     */
    function aisle_variant_state(array $listing): array
    {
        $product = is_array($listing['product'] ?? null) ? $listing['product'] : [];
        $vars    = array_values(array_filter((array) ($product['variants'] ?? []), 'is_array'));
        $price   = is_array($listing['price'] ?? null) ? $listing['price'] : [];
        $cur     = isset($price['currency']) ? (string) $price['currency'] : null;
        $now     = (float) ($price['amount'] ?? 0);

        $photos = [];
        foreach ((array) ($listing['photos'] ?? []) as $i => $p) {
            if (is_array($p) && isset($p['id'])) {
                $photos[(int) $p['id']] = ['view' => (int) $i, 'normal' => (string) ($p['normal'] ?? ''), 'thumb' => (string) ($p['thumbnail'] ?? '')];
            }
        }

        $rows = [];
        foreach ($vars as $v) {
            $opts = [];
            foreach ((array) ($v['options'] ?? []) as $k => $val) {
                if (is_scalar($val)) {
                    $opts[(string) $k] = (string) $val;
                }
            }
            $amount = is_numeric($v['price'] ?? null) ? (float) $v['price'] : $now;
            $was    = is_numeric($v['compare_at'] ?? null) ? (float) $v['compare_at'] : 0.0;
            $off    = $was > $amount && $amount > 0 && ($v['on_sale'] ?? true) !== false ? (int) round((1 - $amount / $was) * 100) : 0;
            $pid    = is_array($v['photo'] ?? null) ? (int) ($v['photo']['id'] ?? 0) : (int) ($v['photo_id'] ?? 0);
            $photo  = null;
            if (isset($photos[$pid])) {
                $photo = ['id' => $pid] + $photos[$pid];
            } elseif (is_array($v['photo'] ?? null) && !empty($v['photo']['normal'])) {
                $photo = ['id' => $pid, 'view' => null, 'normal' => (string) $v['photo']['normal'], 'thumb' => (string) ($v['photo']['thumbnail'] ?? '')];
            }
            $rows[] = [
                'id'       => (int) ($v['id'] ?? 0),
                'options'  => $opts,
                'price'    => is_numeric($v['price'] ?? null) ? aisle_money((string) $v['price'], $cur) : (string) ($price['formatted'] ?? ''),
                'was'      => $off >= 1 ? aisle_money((string) $v['compare_at'], $cur) : '',
                'off'      => $off,
                'in_stock' => ($v['in_stock'] ?? true) !== false,
                'low'      => is_numeric($v['low_stock'] ?? null) ? (int) $v['low_stock'] : 0,
                'photo'    => $photo,
            ];
        }

        $options = [];
        foreach ((array) ($product['options'] ?? []) as $o) {
            if (is_array($o) && isset($o['name']) && is_array($o['values'] ?? null)) {
                $options[] = ['name' => (string) $o['name'], 'values' => array_map('strval', array_values($o['values']))];
            }
        }
        if ($options === []) {
            $seen = [];
            foreach ($rows as $r) {
                foreach ($r['options'] as $name => $val) {
                    if (!in_array($val, $seen[$name] ?? [], true)) {
                        $seen[$name][] = $val;
                    }
                }
            }
            foreach ($seen as $name => $values) {
                $options[] = ['name' => (string) $name, 'values' => $values];
            }
        }

        $default = 0;
        foreach ($rows as $i => $r) {
            if ($r['in_stock']) {
                $default = $i;
                break;
            }
        }

        return ['options' => $options, 'rows' => $rows, 'default' => $default];
    }
}

if (!function_exists('aisle_is_colour')) {
    function aisle_is_colour(string $name): bool
    {
        return preg_match('/colou?r/i', $name) === 1;
    }
}
