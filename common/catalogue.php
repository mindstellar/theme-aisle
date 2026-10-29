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

/**
 * Products on one page of results: core's setting, so page numbers line up with core's own.
 */
function aisle_per_page(): int
{
    return max(4, min(48, (int) osc_default_results_per_page_at_search()));
}

if (!function_exists('aisle_catalogue_state')) {
    /**
     * Include a partial from common/ with its variables in scope.
     *
     * @param array<string,mixed> $vars
     */
    function aisle_partial(string $file, array $vars): void
    {
        extract($vars, EXTR_SKIP);
        include AISLE_DIR . '/common/' . $file;
    }

    /**
     * The visitor's filters, read from core's search parameters and cast. Nothing here is
     * printed raw; every value is a string, an integer or a bool.
     *
     * @return array{slug:string,q:string,pmin:?int,pmax:?int,stock:bool,sale:bool,meta:array<int,string[]>,opt:array<string,string[]>,sort:string,page:int}
     */
    function aisle_catalogue_state(): array
    {
        $slug = Params::getParamString('sCategory');
        if (is_string($slug) && $slug !== '') {
            $parts = array_values(array_filter(explode('/', trim(explode(',', $slug)[0], '/'))));
            $slug  = $parts !== [] ? (string) end($parts) : '';
        } else {
            $slug = '';
        }

        $price = static function (string $key): ?int {
            $raw = Params::getParamString($key);

            return is_string($raw) && preg_match('/^\d{1,12}$/', $raw) === 1 ? (int) $raw : null;
        };

        $meta = [];
        foreach ((array) Params::getParamArray('meta') as $id => $value) {
            $values = aisle_clean_values($value, 20, 100);
            if ((int) $id > 0 && $values !== []) {
                $meta[(int) $id] = $values;
            }
        }

        // Only names and values the owner listed in the setting; anything else is dropped.
        $opt   = [];
        $known = aisle_filter_options();
        $given = (array) Params::getParamArray('opt');
        // The theme's own links carry the same choice as one plain value, "Colour:Cherry,Navy;Size:M",
        // which core keeps in a friendly URL (it drops list parameters).
        $packed = Params::getParamString('opts');
        foreach (is_string($packed) ? explode(';', $packed, 4) : [] as $part) {
            $pair = explode(':', $part, 2);
            if (count($pair) === 2) {
                $given[$pair[0]] = explode(',', $pair[1], 11);
            }
        }
        foreach ($given as $name => $value) {
            $name = (string) $name;
            if (!isset($known[$name])) {
                continue;
            }
            $values = array_values(array_intersect(aisle_clean_values($value, 10, 40), $known[$name]));
            if ($values !== []) {
                $opt[$name] = $values;
            }
        }

        $order = Params::getParamString('sOrder');
        $dir   = strtolower((string) Params::getParamString('iOrderType')) === 'asc' ? 'asc' : 'desc';
        $sort  = '';
        if ($order === 'i_price') {
            $sort = $dir === 'asc' ? 'price_asc' : 'price_desc';
        } elseif ($order === 'dt_pub_date') {
            $sort = 'new';
        }

        $q = Params::getParamString('sPattern');

        return [
            'slug'  => preg_match('/^[A-Za-z0-9._~-]{1,100}$/', $slug) === 1 ? $slug : '',
            'q'     => is_string($q) ? mb_substr(trim($q), 0, 100) : '',
            'pmin'  => $price('sPriceMin'),
            'pmax'  => $price('sPriceMax'),
            'stock' => Params::getParamString('stock') === '1',
            'sale'  => Params::getParamString('sale') === '1',
            'meta'  => $meta,
            'opt'   => $opt,
            'sort'  => $sort,
            'page'  => max(1, min(10000, (int) Params::getParamInt('iPage'))),
        ];
    }

    /**
     * The owner's option filters from the theme setting `filter_options`, one line per option:
     * "Colour: Sunflower, Cherry, Navy". At most 3 names and 10 values each, as the API takes.
     *
     * @return array<string,string[]>
     */
    function aisle_filter_options(): array
    {
        static $groups = null;
        if ($groups !== null) {
            return $groups;
        }
        $groups = [];
        foreach (preg_split('/\R/', (string) aisle_setting('filter_options', '')) ?: [] as $line) {
            $pos = strpos($line, ':');
            if ($pos === false) {
                continue;
            }
            // These characters separate the packed form used in URLs.
            $line   = str_replace([';', '/'], ' ', $line);
            $name   = mb_substr(trim(substr($line, 0, $pos)), 0, 30);
            $values = [];
            foreach (explode(',', substr($line, $pos + 1)) as $value) {
                $value = mb_substr(trim($value), 0, 40);
                if ($value !== '' && !in_array($value, $values, true)) {
                    $values[] = $value;
                }
            }
            if ($name !== '' && $values !== [] && !isset($groups[$name]) && count($groups) < 3) {
                $groups[$name] = array_slice($values, 0, 10);
            }
        }

        return $groups;
    }

    /**
     * A request value (string or list of strings) as a clean list: trimmed, non-empty, unique, capped.
     *
     * @param mixed $value
     *
     * @return string[]
     */
    function aisle_clean_values($value, int $max, int $length): array
    {
        $out = [];
        foreach (is_array($value) ? $value : [$value] as $one) {
            if (!is_scalar($one)) {
                continue;
            }
            $one = mb_substr(trim((string) $one), 0, $length);
            if ($one !== '' && !in_array($one, $out, true) && count($out) < $max) {
                $out[] = $one;
            }
        }

        return $out;
    }

    /**
     * Total values in a name => list map.
     *
     * @param array<int|string,string[]> $map
     */
    function aisle_count_values(array $map): int
    {
        $n = 0;
        foreach ($map as $values) {
            $n += count($values);
        }

        return $n;
    }

    /**
     * True when an option group is shown as colour swatches.
     */
    function aisle_is_colour(string $name): bool
    {
        return preg_match('/^colou?rs?$/i', $name) === 1;
    }

    /**
     * The option values the products on the page offer, by option name.
     *
     * @param array<int,array<string,mixed>> $items
     *
     * @return array<string,array<string,true>>
     */
    function aisle_items_options(array $items): array
    {
        $out = [];
        foreach ($items as $item) {
            foreach ((array) ($item['product']['variants'] ?? []) as $variant) {
                foreach ((array) ($variant['options'] ?? []) as $name => $value) {
                    if (is_string($value)) {
                        $out[(string) $name][$value] = true;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * A CSS colour for a colour name or hex value, or null when it is not one we know.
     */
    function aisle_colour_css(string $value): ?string
    {
        static $map = [
            'black' => '#1a1a1a', 'white' => '#ffffff', 'grey' => '#8a8f98', 'gray' => '#8a8f98', 'silver' => '#c0c4cc',
            'red' => '#d93025', 'cherry' => '#a4172f', 'burgundy' => '#6d1a2e', 'maroon' => '#6d1a2e', 'pink' => '#f08fb0',
            'orange' => '#f28c28', 'yellow' => '#f6c90e', 'sunflower' => '#f4b400', 'mustard' => '#d9a21b', 'gold' => '#c9a227',
            'green' => '#2e8b57', 'olive' => '#6b7a2c', 'sage' => '#9caf88', 'mint' => '#98d8b8', 'teal' => '#1f8a8a',
            'blue' => '#2b6cd9', 'navy' => '#1c2a5a', 'sky' => '#87ceeb', 'purple' => '#7b4bb7', 'lavender' => '#b9a5e0',
            'brown' => '#7a5230', 'tan' => '#c8a47e', 'beige' => '#d8c7a3', 'cream' => '#f3ead2', 'ivory' => '#f6f1e3', 'charcoal' => '#3a3d44',
        ];
        $key = mb_strtolower(trim($value));
        if (preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/', $key) === 1) {
            return $key;
        }

        return $map[$key] ?? null;
    }

    /**
     * Photo URLs for an option's values, taken from the variants of the products on the page.
     *
     * @param array<int,array<string,mixed>> $items
     *
     * @return array<string,string> value => url
     */
    function aisle_swatch_photos(array $items, string $name): array
    {
        $out = [];
        foreach ($items as $item) {
            foreach ((array) ($item['product']['variants'] ?? []) as $variant) {
                $value = $variant['options'][$name] ?? null;
                $photo = $variant['photo'] ?? null;
                if (is_string($value) && is_array($photo) && !isset($out[$value])) {
                    $url = (string) ($photo['thumbnail'] ?? $photo['preview'] ?? '');
                    if ($url !== '') {
                        $out[$value] = $url;
                    }
                }
            }
        }

        return $out;
    }

    /**
     * Find a category by slug or id in the tree. Returns the category and its parents, top first.
     *
     * @param array<int,array<string,mixed>> $tree
     *
     * @return array{cat:array<string,mixed>,trail:array<int,array<string,mixed>>}|null
     */
    function aisle_category_find(array $tree, string $needle, array $trail = []): ?array
    {
        foreach ($tree as $cat) {
            if (!is_array($cat)) {
                continue;
            }
            if ((string) ($cat['slug'] ?? '') === $needle || (string) ($cat['id'] ?? '') === $needle) {
                return ['cat' => $cat, 'trail' => $trail];
            }
            $found = aisle_category_find((array) ($cat['children'] ?? []), $needle, array_merge($trail, [$cat]));
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }

    /**
     * The URL of a results page for a state, with $change applied. Page 1 carries no iPage.
     *
     * @param array<string,mixed> $state
     * @param array<string,mixed> $change
     */
    function aisle_catalogue_url(array $state, array $change = []): string
    {
        $s = array_merge($state, $change);
        if (!array_key_exists('page', $change)) {
            $s['page'] = 1;
        }

        $args = [];
        if ($s['slug'] !== '') {
            $args['sCategory'] = $s['slug'];
        }
        if ($s['q'] !== '') {
            $args['sPattern'] = $s['q'];
        }
        if ($s['pmin'] !== null) {
            $args['sPriceMin'] = $s['pmin'];
        }
        if ($s['pmax'] !== null) {
            $args['sPriceMax'] = $s['pmax'];
        }
        if ($s['stock']) {
            $args['stock'] = 1;
        }
        if ($s['sale']) {
            $args['sale'] = 1;
        }
        if ($s['meta'] !== []) {
            $args['meta'] = $s['meta'];
        }
        // Core's friendly URLs drop list parameters, so the options travel as one plain value.
        $packed = [];
        foreach ((array) ($s['opt'] ?? []) as $name => $values) {
            if ($values !== []) {
                $packed[] = $name . ':' . implode(',', $values);
            }
        }
        if ($packed !== []) {
            $args['opts'] = implode(';', $packed);
        }
        if ($s['sort'] === 'price_asc') {
            $args['sOrder'] = 'i_price';
            $args['iOrderType'] = 'asc';
        } elseif ($s['sort'] === 'price_desc') {
            $args['sOrder'] = 'i_price';
            $args['iOrderType'] = 'desc';
        } elseif ($s['sort'] === 'new') {
            $args['sOrder'] = 'dt_pub_date';
            $args['iOrderType'] = 'desc';
        }
        if ((int) $s['page'] > 1) {
            $args['iPage'] = (int) $s['page'];
        }

        return $args === [] ? osc_search_url() : osc_search_url($args);
    }

    /**
     * How many filters are applied (price counts once). The search word and the category are not filters.
     *
     * @param array<string,mixed> $state
     */
    function aisle_filter_count(array $state): int
    {
        return ($state['pmin'] !== null || $state['pmax'] !== null ? 1 : 0)
            + ($state['stock'] ? 1 : 0)
            + ($state['sale'] ? 1 : 0)
            + aisle_count_values($state['meta'])
            + aisle_count_values($state['opt'] ?? []);
    }

    /**
     * The API query for a state.
     *
     * @param array<string,mixed> $state
     *
     * @return array<string,mixed>
     */
    function aisle_catalogue_query(array $state): array
    {
        $query = [
            'limit'   => aisle_per_page(),
            'page'    => $state['page'],
            'count'   => 'true',
            'include' => 'rating',
            'fields'  => AISLE_CARD_FIELDS,
        ];
        if ($state['slug'] !== '') {
            $query['category'] = $state['slug'];
        }
        if ($state['q'] !== '') {
            $query['q'] = $state['q'];
        }
        if ($state['pmin'] !== null) {
            $query['price_min'] = $state['pmin'];
        }
        if ($state['pmax'] !== null) {
            $query['price_max'] = $state['pmax'];
        }
        if ($state['stock']) {
            $query['in_stock'] = 'true';
        }
        if ($state['sale']) {
            $query['on_sale'] = 'true';
        }
        foreach ($state['meta'] as $id => $values) {
            // A dropdown field takes a list (any of); a text or checkbox field takes one value.
            $query['custom_field'][$id] = !empty($state['multi'][$id]) ? $values : $values[0];
        }
        foreach (($state['opt'] ?? []) as $name => $values) {
            $query['option'][$name] = $values;
        }
        if ($state['sort'] === 'price_asc' || $state['sort'] === 'price_desc') {
            $query['sort']  = 'price';
            $query['order'] = $state['sort'] === 'price_asc' ? 'asc' : 'desc';
        } elseif ($state['sort'] === 'new' || $state['q'] === '') {
            $query['sort']  = 'created';
            $query['order'] = 'desc';
        } else {
            $query['sort'] = 'relevance';
        }

        return $query;
    }
}
