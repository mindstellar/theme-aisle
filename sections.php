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

if (!defined('ABS_PATH')) {
    exit('Direct access is not allowed.');
}

/** The promise icons an owner can pick. */
const AISLE_PROMISE_ICONS = ['truck', 'return', 'cash', 'shield', 'box', 'star'];

/**
 * Read one API route once per request, so the hero tag and a deals grid share one call.
 *
 * @param array<string,mixed> $query
 *
 * @return array<mixed>|null
 */
function aisle_api_memo(string $path, array $query = []): ?array
{
    static $memo = [];

    ksort($query);
    $key = $path . '?' . http_build_query($query);
    if (!array_key_exists($key, $memo)) {
        $memo[$key] = aisle_api($path, $query);
    }

    return $memo[$key];
}

/**
 * The listings of one API query, or [].
 *
 * @param array<string,mixed> $query
 *
 * @return array<int,array<string,mixed>>
 */
function aisle_section_listings(array $query): array
{
    $body = aisle_api_memo('listings', $query + ['fields' => AISLE_CARD_FIELDS]);

    return is_array($body['data'] ?? null) ? array_values(array_filter($body['data'], 'is_array')) : [];
}

/**
 * The listings of the on-sale query the hero and the deals grid both use.
 *
 * @return array<int,array<string,mixed>>
 */
function aisle_section_deals(int $limit = 8): array
{
    return aisle_section_listings(['on_sale' => 'true', 'limit' => $limit, 'include' => 'rating']);
}

/**
 * The top-level categories with their cover photo.
 *
 * @return array<int,array<string,mixed>>
 */
function aisle_section_categories(): array
{
    $body = aisle_api_memo('categories', ['include' => 'cover', 'roots' => 'true']);

    return array_values(array_filter(
        is_array($body['data'] ?? null) ? $body['data'] : [],
        static fn ($c): bool => is_array($c) && empty($c['parent_id'])
    ));
}

/**
 * An address on this site's own origin as a path ("/oc-content/..."); anything else unchanged.
 */
function aisle_site_path(string $url): string
{
    $url = trim($url);
    if (!function_exists('osc_base_url') || preg_match('#^https?://#i', $url) !== 1) {
        return $url;
    }
    $base = (string) osc_base_url();
    $path = (string) parse_url($base, PHP_URL_PATH);
    if ($base === '' || stripos($url, $base) !== 0) {
        return $url;
    }
    $short = rtrim($path, '/') . '/' . ltrim(substr($url, strlen($base)), '/');

    return preg_match('#^/(?![/\\\\])#', $short) === 1 && !preg_match('/[\x00-\x20\x7f"<>\\\\]/', $short) ? $short : $url;
}

/**
 * A stored site path as a full address on the current origin; any other value unchanged.
 */
function aisle_absolute_url(string $url): string
{
    if (!function_exists('osc_base_url') || preg_match('#^/(?![/\\\\])#', $url) !== 1) {
        return $url;
    }
    $parts = parse_url((string) osc_base_url());
    if (empty($parts['scheme']) || empty($parts['host'])) {
        return $url;
    }

    return $parts['scheme'] . '://' . $parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : '') . $url;
}

/**
 * A link target the owner typed: an http(s) address or a path on this site, else ''.
 * A site path comes back as a full address.
 */
function aisle_safe_url(string $url): string
{
    $url = trim($url);

    return preg_match('#^(https?://|/(?![/\\\\]))#i', $url) === 1 && !preg_match('/[\x00-\x20\x7f"<>\\\\]/', $url) ? aisle_absolute_url($url) : '';
}

/**
 * An image address the owner picked: an http(s) address or a path on this site, else ''.
 * A site path comes back as a full address.
 */
function aisle_safe_image(string $url): string
{
    $url = trim($url);

    return preg_match('#^(https?://|/(?![/\\\\]))#i', $url) === 1 && !preg_match('/[\x00-\x20"<>\\\\]/', $url) ? aisle_absolute_url($url) : '';
}

/**
 * Text of a number setting kept between two limits.
 *
 * @param mixed $value
 */
function aisle_clamp($value, int $min, int $max, int $default): int
{
    $number = is_numeric($value) ? (int) $value : $default;

    return max($min, min($max, $number > 0 ? $number : $default));
}

/**
 * The data attributes that let the customiser select a section in its preview frame.
 *
 * @param array<string,mixed> $row
 */
function aisle_sec_attrs(array $row, string $label): string
{
    if (!aisle_is_preview()) {
        return '';
    }
    $id = (string) ($row['s_key'] ?? $row['pk_i_id'] ?? '');

    return ' data-sec="' . osc_esc_html($id) . '" data-sec-name="' . osc_esc_html($label) . '"';
}

/**
 * The data attributes that let the customiser rewrite a text field live, with the text shown when it is empty.
 */
function aisle_field_attr(string $name, string $default = ''): string
{
    if (!aisle_is_preview()) {
        return '';
    }

    return ' data-field="' . osc_esc_html($name) . '"' . ($default !== '' ? ' data-default="' . osc_esc_html($default) . '"' : '');
}

/**
 * Run a section template with the owner's config and return what it prints.
 *
 * @param array<string,mixed> $config
 * @param array<string,mixed> $row
 */
function aisle_render_section(string $name, array $config, array $row): string
{
    ob_start();
    require AISLE_DIR . '/common/sections/' . $name . '.php';

    return trim((string) ob_get_clean());
}

/**
 * Top-level category slugs for the products section's category choice.
 *
 * @return array<string,string>
 */
function aisle_category_options(): array
{
    $options = ['' => __('Choose a category', 'aisle')];
    foreach (aisle_section_categories() as $cat) {
        $slug = (string) ($cat['slug'] ?? '');
        if ($slug !== '') {
            $options[$slug] = (string) ($cat['name'] ?? $slug);
        }
    }

    return $options;
}

/**
 * The icon choices for a promise.
 *
 * @return array<string,string>
 */
function aisle_promise_icon_options(): array
{
    return [
        'truck'  => __('Delivery', 'aisle'),
        'return' => __('Returns', 'aisle'),
        'cash'   => __('Cash', 'aisle'),
        'shield' => __('Secure', 'aisle'),
        'box'    => __('Parcel', 'aisle'),
        'star'   => __('Star', 'aisle'),
    ];
}

/**
 * Register the home page section types.
 */
function aisle_register_sections(): void
{
    $shop   = function_exists('osc_is_shop_mode') && osc_is_shop_mode();
    $render = static function (string $name): callable {
        return static function ($config, $row = []) use ($name): string {
            return aisle_render_section($name, is_array($config) ? $config : [], is_array($row) ? $row : []);
        };
    };

    osc_register_widget('aisle.hero', [
        'label'       => __('Hero banner', 'aisle'),
        'description' => __('A big heading, a short text, two buttons and a photo.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('hero'),
        'fields'      => [
            ['name' => 'title', 'label' => __('Heading (put *stars* around the word to colour)', 'aisle'), 'type' => 'text'],
            ['name' => 'text', 'label' => __('Text', 'aisle'), 'type' => 'textarea'],
            ['name' => 'image', 'label' => __('Photo', 'aisle'), 'type' => 'image'],
            ['name' => 'cta_label', 'label' => __('Button label', 'aisle'), 'type' => 'text'],
            ['name' => 'cta_url', 'label' => __('Button link', 'aisle'), 'type' => 'text'],
            ['name' => 'cta2_label', 'label' => __('Second button label', 'aisle'), 'type' => 'text'],
            ['name' => 'cta2_url', 'label' => __('Second button link', 'aisle'), 'type' => 'text'],
            [
                'name'    => 'tag',
                'label'   => __('Product card on the photo', 'aisle'),
                'type'    => 'select',
                'default' => 'first_deal',
                'options' => [
                    'first_deal' => __('The first deal', 'aisle'),
                    'listing'    => $shop ? __('A product I choose', 'aisle') : __('A listing I choose', 'aisle'),
                    'none'       => __('None', 'aisle'),
                ],
            ],
            ['name' => 'tag_listing', 'label' => $shop ? __('Product number for the card (0 shows the first deal)', 'aisle') : __('Listing number for the card (0 shows the first deal)', 'aisle'), 'type' => 'number'],
            ['name' => 'perk_1', 'label' => __('Short promise 1', 'aisle'), 'type' => 'text'],
            ['name' => 'perk_2', 'label' => __('Short promise 2', 'aisle'), 'type' => 'text'],
            ['name' => 'perk_3', 'label' => __('Short promise 3', 'aisle'), 'type' => 'text'],
        ],
    ]);

    osc_register_widget('aisle.categories', [
        'label'       => __('Category row', 'aisle'),
        'description' => __('Round photos of your top categories.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('categories'),
        'fields'      => [
            ['name' => 'title', 'label' => __('Heading', 'aisle'), 'type' => 'text'],
            ['name' => 'count', 'label' => __('How many (3 to 12)', 'aisle'), 'type' => 'number', 'default' => 7],
        ],
    ]);

    osc_register_widget('aisle.products', [
        'label'       => __('Products', 'aisle'),
        'description' => __('A grid or a row of deals, new arrivals or one category.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('products'),
        'fields'      => [
            ['name' => 'title', 'label' => __('Heading', 'aisle'), 'type' => 'text'],
            [
                'name'    => 'source',
                'label'   => __('Show', 'aisle'),
                'type'    => 'select',
                'default' => 'on_sale',
                'options' => [
                    'on_sale'  => __('Deals', 'aisle'),
                    'newest'   => __('Newest', 'aisle'),
                    'category' => __('One category', 'aisle'),
                ],
            ],
            ['name' => 'category', 'label' => __('Category', 'aisle'), 'type' => 'select', 'default' => '', 'options' => 'aisle_category_options'],
            ['name' => 'count', 'label' => __('How many (4 to 12)', 'aisle'), 'type' => 'number', 'default' => 8],
            [
                'name'    => 'layout',
                'label'   => __('Layout', 'aisle'),
                'type'    => 'select',
                'default' => 'grid',
                'options' => ['grid' => __('Grid', 'aisle'), 'rail' => __('Scrolling row', 'aisle')],
            ],
            ['name' => 'link_label', 'label' => __('Link text', 'aisle'), 'type' => 'text'],
        ],
    ]);

    $promise = [];
    foreach ([1 => 'truck', 2 => 'return', 3 => 'cash', 4 => 'shield'] as $n => $icon) {
        $promise[] = ['name' => $n . '_title', 'label' => sprintf(__('Promise %d: title', 'aisle'), $n), 'type' => 'text'];
        $promise[] = ['name' => $n . '_text', 'label' => sprintf(__('Promise %d: text', 'aisle'), $n), 'type' => 'text'];
        $promise[] = [
            'name'    => $n . '_icon',
            'label'   => sprintf(__('Promise %d: icon', 'aisle'), $n),
            'type'    => 'select',
            'default' => $icon,
            'options' => 'aisle_promise_icon_options',
        ];
    }
    osc_register_widget('aisle.promise', [
        'label'       => __('Promise row', 'aisle'),
        'description' => __('Up to four short promises with an icon, such as free delivery.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('promise'),
        'fields'      => $promise,
    ]);

    osc_register_widget('aisle.banner', [
        'label'       => __('Banner', 'aisle'),
        'description' => __('A wide photo with a heading, a line of text and a button.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('banner'),
        'fields'      => [
            ['name' => 'image', 'label' => __('Photo', 'aisle'), 'type' => 'image'],
            ['name' => 'heading', 'label' => __('Heading', 'aisle'), 'type' => 'text'],
            ['name' => 'text', 'label' => __('Text', 'aisle'), 'type' => 'text'],
            ['name' => 'button_label', 'label' => __('Button label', 'aisle'), 'type' => 'text'],
            ['name' => 'button_url', 'label' => __('Button link', 'aisle'), 'type' => 'text'],
            [
                'name'    => 'align',
                'label'   => __('Text position', 'aisle'),
                'type'    => 'select',
                'default' => 'left',
                'options' => ['left' => __('Left', 'aisle'), 'center' => __('Centre', 'aisle'), 'right' => __('Right', 'aisle')],
            ],
        ],
    ]);

    osc_register_widget('aisle.featured', [
        'label'       => __('Featured product', 'aisle'),
        'description' => __('One product with a heading and a few words beside it.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('featured'),
        'fields'      => [
            ['name' => 'listing', 'label' => $shop ? __('Product number', 'aisle') : __('Listing number', 'aisle'), 'type' => 'number'],
            ['name' => 'heading', 'label' => __('Heading', 'aisle'), 'type' => 'text'],
            ['name' => 'text', 'label' => __('Text', 'aisle'), 'type' => 'textarea'],
        ],
    ]);

    $reviews = [
        ['name' => 'score', 'label' => __('Score, for example 4.7', 'aisle'), 'type' => 'text'],
        ['name' => 'score_line', 'label' => __('Line under the score', 'aisle'), 'type' => 'text'],
    ];
    foreach ([1, 2, 3] as $n) {
        $reviews[] = ['name' => $n . '_text', 'label' => sprintf(__('Quote %d: text', 'aisle'), $n), 'type' => 'textarea'];
        $reviews[] = ['name' => $n . '_name', 'label' => sprintf(__('Quote %d: name and city', 'aisle'), $n), 'type' => 'text'];
        $reviews[] = ['name' => $n . '_stars', 'label' => sprintf(__('Quote %d: stars (1 to 5)', 'aisle'), $n), 'type' => 'number', 'default' => 5];
    }
    osc_register_widget('aisle.reviews', [
        'label'       => __('Reviews', 'aisle'),
        'description' => __('A score and up to three customer quotes.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('reviews'),
        'fields'      => $reviews,
    ]);

    osc_register_widget('aisle.newsletter', [
        'label'       => __('Newsletter', 'aisle'),
        'description' => __('A heading, one line and an e-mail field.', 'aisle'),
        'group'       => 'Aisle',
        'locations'   => ['home'],
        'render'      => $render('newsletter'),
        'fields'      => [
            ['name' => 'heading', 'label' => __('Heading', 'aisle'), 'type' => 'text'],
            ['name' => 'text', 'label' => __('Text', 'aisle'), 'type' => 'text'],
            ['name' => 'button_label', 'label' => __('Button label', 'aisle'), 'type' => 'text'],
        ],
    ]);
}
osc_add_hook('init', 'aisle_register_sections');

/**
 * The light-scheme colour of each brand preset, read from tokens.css so the colours live in one file.
 * Only the admin screen prints them.
 *
 * @return array<string,string>
 */
function aisle_brand_swatches(): array
{
    $css = is_readable(AISLE_DIR . '/css/tokens.css') ? (string) file_get_contents(AISLE_DIR . '/css/tokens.css') : '';
    $out = [];
    if (preg_match('/^:root\s*\{[^}]*?--brand:\s*(#[0-9a-f]{6})/mi', $css, $m) === 1) {
        $out['raspberry'] = strtolower($m[1]);
    }
    foreach (AISLE_BRANDS as $brand) {
        if ($brand !== 'raspberry' && preg_match('/^:root\[data-brand="' . $brand . '"\]\s*\{\s*--brand:\s*(#[0-9a-f]{6})/mi', $css, $m) === 1) {
            $out[$brand] = strtolower($m[1]);
        }
    }

    return $out;
}

/**
 * Tell core what the owner can change in the customiser, and where the theme shows menus.
 */
function aisle_customiser_support(): void
{
    osc_add_theme_support('customiser', [
        'locations' => [
            'home' => [
                'label'   => __('Home page', 'aisle'),
                'types'   => [
                    'aisle.hero', 'aisle.categories', 'aisle.products', 'aisle.promise', 'aisle.banner',
                    'aisle.featured', 'aisle.reviews', 'aisle.newsletter', 'core.rich_text',
                ],
                'preview' => osc_base_url(),
            ],
        ],
        'settings'  => [
            'page'   => 'aisle',
            'fields' => ['brand', 'scheme', 'font', 'logo', 'logo_dark', 'announcement', 'footer_note'],
        ],
        'swatches'  => ['brand' => defined('OC_ADMIN') && OC_ADMIN === true ? aisle_brand_swatches() : []],
        'live'      => ['brand' => 'data-brand', 'scheme' => 'data-theme', 'font' => 'data-font'],
    ]);
    osc_add_theme_support('menus', [
        'main'   => ['label' => __('Top bar', 'aisle'), 'depth' => 1],
        'footer' => ['label' => __('Footer', 'aisle'), 'depth' => 1],
    ]);
}
osc_add_hook('init', 'aisle_customiser_support');

/**
 * Seed the home page sections on an existing install so the home page stays as it was.
 * Does nothing when the home location already holds rows.
 */
function aisle_seed_home_sections(): void
{
    if (!class_exists('mindstellar\\widgets\\WidgetStore') || \mindstellar\widgets\WidgetStore::idsAt('home') !== []) {
        return;
    }

    $old = static function (string $key): string {
        return trim((string) osc_get_preference($key, 'aisle'));
    };

    $promise = [];
    foreach ([1 => 'truck', 2 => 'return', 3 => 'cash', 4 => 'shield'] as $n => $icon) {
        $promise[$n . '_title'] = $old('promise_' . $n . '_title');
        $promise[$n . '_text']  = $old('promise_' . $n . '_text');
        $promise[$n . '_icon']  = $icon;
    }

    $image = function_exists('osc_settings_image_url') ? aisle_safe_image((string) osc_settings_image_url('aisle', 'hero_image')) : '';
    if ($image === '' && function_exists('osc_current_web_theme_url')) {
        $image = aisle_safe_image((string) osc_current_web_theme_url('img/hero.jpg'));
    }
    $image = aisle_site_path($image);
    $title = $old('hero_title');
    if ($title === '') {
        $title = __('Good things, *easy to find.*', 'aisle');
    }
    $shop    = osc_base_url(true) . '?page=search';
    $hasSale = function_exists('aisle_api') && aisle_section_deals(1) !== [];
    $buttons = $hasSale
        ? [__('Shop the deals', 'aisle'), $shop . '&sale=1', __('New in', 'aisle'), $shop . '&sOrder=dt_pub_date&iOrderType=desc']
        : [__('Shop now', 'aisle'), $shop, '', ''];
    foreach ([1, 3] as $at) {
        $buttons[$at] = aisle_site_path($buttons[$at]);
    }

    $rows = [
        ['aisle.hero', 'Hero', [
            'title' => $title, 'text' => $old('hero_text'), 'image' => $image,
            'cta_label' => $buttons[0], 'cta_url' => $buttons[1], 'cta2_label' => $buttons[2], 'cta2_url' => $buttons[3],
            'tag' => 'first_deal', 'tag_listing' => 0,
            'perk_1' => $promise['1_title'], 'perk_2' => $promise['2_title'], 'perk_3' => $promise['3_title'],
        ]],
        ['aisle.categories', 'Categories', ['title' => '', 'count' => 7]],
        ['aisle.products', 'Deals', ['title' => '', 'source' => 'on_sale', 'category' => '', 'count' => 8, 'layout' => 'grid', 'link_label' => '']],
        ['aisle.promise', 'Promise', $promise],
        ['aisle.products', 'New in', ['title' => '', 'source' => 'newest', 'category' => '', 'count' => 6, 'layout' => 'rail', 'link_label' => '']],
    ];
    foreach ($rows as $i => [$type, $description, $config]) {
        \mindstellar\widgets\WidgetStore::add([
            's_description' => $description,
            's_location'    => 'home',
            'e_kind'        => 'HTML',
            's_content'     => '',
            'i_order'       => $i,
            's_type'        => $type,
            's_config'      => (string) json_encode($config, JSON_UNESCAPED_UNICODE),
        ]);
    }
}
