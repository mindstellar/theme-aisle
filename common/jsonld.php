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

if (!function_exists('aisle_jsonld')) {
    /**
     * Print one JSON-LD block. "<", ">" and "&" are escaped so the data cannot close the script tag.
     *
     * @param array<string,mixed> $data
     */
    function aisle_jsonld(array $data): void
    {
        if (($data['@type'] ?? '') === 'Product') {
            $data = aisle_jsonld_reviews($data);
        }
        $json = json_encode(
            ['@context' => 'https://schema.org'] + $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (is_string($json)) {
            echo '<script type="application/ld+json">' . $json . '</script>' . PHP_EOL;
        }
    }

    /**
     * Adds AggregateRating and Review to a Product from the listing and review page item.php loaded.
     * Nothing is added when no one has rated it.
     *
     * @param array<string,mixed> $product
     *
     * @return array<string,mixed>
     */
    function aisle_jsonld_reviews(array $product): array
    {
        $listing = $GLOBALS['aisle_listing'] ?? null;
        $rating  = is_array($listing) && is_array($listing['rating'] ?? null) ? $listing['rating'] : [];
        $count   = (int) ($rating['count'] ?? 0);
        $average = $rating['average'] ?? null;
        if ($count < 1 || !is_numeric($average) || !aisle_ratings_on()) {
            return $product;
        }

        $product['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => round((float) $average, 1),
            'ratingCount' => $count,
            'bestRating'  => 5,
            'worstRating' => 1,
        ];

        $page    = $GLOBALS['aisle_reviews'] ?? null;
        $reviews = [];
        foreach (is_array($page) && is_array($page['data'] ?? null) ? $page['data'] : [] as $c) {
            if (!is_array($c) || !isset($c['rating']) || (int) $c['rating'] < 1) {
                continue;
            }
            $name = trim((string) ($c['author']['name'] ?? ''));
            $item = [
                '@type'        => 'Review',
                'reviewRating' => ['@type' => 'Rating', 'ratingValue' => (int) $c['rating'], 'bestRating' => 5, 'worstRating' => 1],
                'author'       => ['@type' => 'Person', 'name' => $name !== '' ? $name : 'Customer'],
            ];
            if (trim((string) ($c['title'] ?? '')) !== '') {
                $item['name'] = trim((string) $c['title']);
            }
            if (trim((string) ($c['body'] ?? '')) !== '') {
                $item['reviewBody'] = trim((string) $c['body']);
            }
            if (!empty($c['published_at'])) {
                $item['datePublished'] = (string) $c['published_at'];
            }
            $reviews[] = $item;
        }
        if ($reviews !== []) {
            $product['review'] = $reviews;
        }

        return $product;
    }

    /**
     * A BreadcrumbList from the seo route's breadcrumbs.
     *
     * @param array<int,mixed> $crumbs
     *
     * @return array<string,mixed>|null
     */
    function aisle_jsonld_breadcrumbs(array $crumbs): ?array
    {
        $items = [];
        foreach (array_values(array_filter($crumbs, 'is_array')) as $i => $crumb) {
            $items[] = [
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => (string) ($crumb['label'] ?? ''),
                'item'     => (string) ($crumb['url'] ?? ''),
            ];
        }

        return $items === [] ? null : ['@type' => 'BreadcrumbList', 'itemListElement' => $items];
    }
}
