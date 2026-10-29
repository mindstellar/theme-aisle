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

require_once AISLE_DIR . '/common/jsonld.php';

if (!function_exists('aisle_seo_context')) {
    /**
     * Which seo route the current page maps to: [for, id, q]. 'for' is '' for pages without one.
     *
     * @return array{0:string,1:string,2:string}
     */
    function aisle_seo_context(): array
    {
        if (osc_is_ad_page()) {
            return ['listing', (string) (int) osc_item_id(), ''];
        }
        if (osc_is_static_page()) {
            return ['page', (string) (int) osc_static_page_id(), ''];
        }
        if (osc_is_search_page()) {
            require_once AISLE_DIR . '/common/catalogue.php';
            $state = aisle_catalogue_state();
            if ($state['slug'] !== '' && $state['q'] === '') {
                return ['category', $state['slug'], ''];
            }

            return ['search', '', $state['q']];
        }
        if (osc_is_home_page()) {
            return ['home', '', ''];
        }

        return ['', '', ''];
    }

    /**
     * Print a <meta> or <link> tag; skipped when the value is empty.
     *
     * @param array<string,string> $attrs
     */
    function aisle_head_tag(string $tag, array $attrs): void
    {
        foreach ($attrs as $value) {
            if ($value === '') {
                return;
            }
        }
        $html = '';
        foreach ($attrs as $name => $value) {
            $html .= ' ' . $name . '="' . osc_esc_html($value) . '"';
        }
        echo '<' . $tag . $html . '>' . PHP_EOL;
    }

    /**
     * Plain text of an HTML description, cut at 300 characters.
     */
    function aisle_plain(string $html): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8')));

        return mb_strlen($text, 'UTF-8') > 300 ? mb_substr($text, 0, 299, 'UTF-8') . '…' : $text;
    }
}

[$aisle_for, $aisle_seo_id, $aisle_seo_q] = aisle_seo_context();
$aisle_site = (string) osc_get_preference('pageTitle');
$aisle_seo  = null;
if ($aisle_for !== '') {
    $aisle_seo = aisle_api('seo', array_filter(['for' => $aisle_for, 'id' => $aisle_seo_id, 'q' => $aisle_seo_q], static fn ($v): bool => $v !== ''));
    $aisle_seo = is_array($aisle_seo['data'] ?? null) ? $aisle_seo['data'] : null;
}
$aisle_missing = http_response_code() === 404 || osc_is_404();

if ($aisle_seo === null) {
    // Pages the seo route does not cover (account, login, contact, errors): core's title, never indexed.
    $aisle_title = (string) meta_title();
    if ($aisle_missing) {
        $aisle_title = sprintf(__('Page not found - %s', 'aisle'), $aisle_site);
    }
    echo '<title>' . osc_esc_html($aisle_title !== '' ? $aisle_title : $aisle_site) . '</title>' . PHP_EOL;
    echo '<meta name="robots" content="noindex, follow">' . PHP_EOL;

    return;
}

$aisle_og = is_array($aisle_seo['og'] ?? null) ? $aisle_seo['og'] : [];

echo '<title>' . osc_esc_html($aisle_missing ? sprintf(__('Page not found - %s', 'aisle'), $aisle_site) : (string) ($aisle_seo['title'] ?? $aisle_site)) . '</title>' . PHP_EOL;
aisle_head_tag('meta', ['name' => 'description', 'content' => aisle_plain((string) ($aisle_seo['description'] ?? ''))]);
aisle_head_tag('link', ['rel' => 'canonical', 'href' => (string) ($aisle_seo['canonical'] ?? '')]);
aisle_head_tag('meta', ['name' => 'robots', 'content' => $aisle_missing ? 'noindex, follow' : (string) ($aisle_seo['robots'] ?? '')]);
foreach ((array) ($aisle_seo['hreflang'] ?? []) as $aisle_alt) {
    if (is_array($aisle_alt)) {
        aisle_head_tag('link', ['rel' => 'alternate', 'hreflang' => str_replace('_', '-', (string) ($aisle_alt['locale'] ?? '')), 'href' => (string) ($aisle_alt['url'] ?? '')]);
    }
}

aisle_head_tag('meta', ['property' => 'og:type', 'content' => (string) ($aisle_og['type'] ?? 'website')]);
aisle_head_tag('meta', ['property' => 'og:title', 'content' => (string) ($aisle_og['title'] ?? '')]);
aisle_head_tag('meta', ['property' => 'og:description', 'content' => aisle_plain((string) ($aisle_og['description'] ?? ''))]);
aisle_head_tag('meta', ['property' => 'og:url', 'content' => (string) ($aisle_og['url'] ?? '')]);
aisle_head_tag('meta', ['property' => 'og:site_name', 'content' => (string) ($aisle_og['site_name'] ?? $aisle_site)]);
aisle_head_tag('meta', ['property' => 'og:locale', 'content' => (string) ($aisle_og['locale'] ?? '')]);
aisle_head_tag('meta', ['property' => 'og:image', 'content' => (string) ($aisle_og['image'] ?? '')]);
aisle_head_tag('meta', ['name' => 'twitter:card', 'content' => !empty($aisle_og['image']) ? 'summary_large_image' : 'summary']);

if ($aisle_missing) {
    return;
}

$aisle_crumbs = aisle_jsonld_breadcrumbs((array) ($aisle_seo['breadcrumbs'] ?? []));

if ($aisle_for === 'home') {
    $aisle_org = ['@type' => 'Organization', 'name' => $aisle_site, 'url' => osc_base_url()];
    $aisle_img = (string) ($aisle_og['image'] ?? '');
    if ($aisle_img !== '') {
        $aisle_org['logo'] = $aisle_img;
    }
    aisle_jsonld($aisle_org);
    aisle_jsonld([
        '@type'           => 'WebSite',
        'name'            => $aisle_site,
        'url'             => osc_base_url(),
        'potentialAction' => [
            '@type'       => 'SearchAction',
            'target'      => ['@type' => 'EntryPoint', 'urlTemplate' => osc_base_url(true) . '?page=search&sPattern={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ]);
} elseif ($aisle_for === 'listing') {
    $aisle_res = $GLOBALS['aisle_listing'] ?? null;
    if (!is_array($aisle_res)) {
        $aisle_body = aisle_api('listings/' . (int) $aisle_seo_id);
        $aisle_res  = is_array($aisle_body['data'] ?? null) ? $aisle_body['data'] : null;
    }
    if ($aisle_res !== null) {
        $aisle_photos = array_values(array_filter((array) ($aisle_res['photos'] ?? []), 'is_array'));
        $aisle_vars   = (array) ($aisle_res['product']['variants'] ?? []);
        $aisle_var    = isset($aisle_vars[0]) && is_array($aisle_vars[0]) ? $aisle_vars[0] : [];
        $aisle_price  = is_array($aisle_res['price'] ?? null) ? $aisle_res['price'] : null;
        $aisle_prod   = [
            '@type'       => 'Product',
            'name'        => (string) ($aisle_res['title'] ?? ''),
            'description' => aisle_plain((string) ($aisle_res['description'] ?? '')),
            'url'         => (string) ($aisle_res['url'] ?? ''),
        ];
        if ($aisle_photos !== []) {
            $aisle_prod['image'] = array_values(array_filter(array_map(static fn (array $p): string => (string) ($p['normal'] ?? $p['original'] ?? ''), $aisle_photos)));
        }
        if (!empty($aisle_var['sku'])) {
            $aisle_prod['sku'] = (string) $aisle_var['sku'];
        }
        if ($aisle_price !== null && isset($aisle_price['amount'])) {
            $aisle_prod['offers'] = [
                '@type'         => 'Offer',
                'price'         => (string) $aisle_price['amount'],
                'priceCurrency' => (string) ($aisle_price['currency'] ?? ''),
                'availability'  => ($aisle_var['in_stock'] ?? true) === false ? 'https://schema.org/OutOfStock' : 'https://schema.org/InStock',
                'url'           => (string) ($aisle_res['url'] ?? ''),
            ];
        }
        aisle_jsonld($aisle_prod);
    }
} elseif ($aisle_for === 'category') {
    $aisle_want = ['category' => $aisle_seo_id, 'sort' => 'created', 'order' => 'desc', 'limit' => aisle_per_page()];
    [$aisle_had, $aisle_list] = $GLOBALS['aisle_catalogue'] ?? [null, null];
    if (is_array($aisle_had)) {
        unset($aisle_had['count'], $aisle_had['include'], $aisle_had['fields']);
        if ((int) ($aisle_had['page'] ?? 1) === 1) {
            unset($aisle_had['page']);
        }
        ksort($aisle_had);
        ksort($aisle_want);
    }
    if ($aisle_had !== $aisle_want) {
        $aisle_list = aisle_api('listings', $aisle_want);
    }
    $aisle_els  = [];
    foreach (array_values(array_filter((array) ($aisle_list['data'] ?? []), 'is_array')) as $aisle_i => $aisle_row) {
        $aisle_els[] = ['@type' => 'ListItem', 'position' => $aisle_i + 1, 'url' => (string) ($aisle_row['url'] ?? ''), 'name' => (string) ($aisle_row['title'] ?? '')];
    }
    if ($aisle_els !== []) {
        aisle_jsonld(['@type' => 'ItemList', 'itemListElement' => $aisle_els]);
    }
}

if ($aisle_crumbs !== null && in_array($aisle_for, ['listing', 'category', 'page'], true)) {
    aisle_jsonld($aisle_crumbs);
}
