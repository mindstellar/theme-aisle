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

define('AISLE_VERSION', '1.1.0');
define('AISLE_DIR', __DIR__);
// Aisle's own folder, also when a child theme is the active one.
define('AISLE_URL', osc_base_url() . str_replace(osc_base_path(), '', osc_themes_path()) . 'aisle/');

/** The brand presets an owner can pick. The values live in css/tokens.css. */
const AISLE_BRANDS = ['raspberry', 'ocean', 'forest', 'marigold', 'plum'];

/** The font pairs an owner can pick. The stacks live in css/tokens.css; the first is the default. */
const AISLE_FONTS = ['bricolage-figtree', 'fraunces-inter', 'system'];

/** The logo heights an owner can pick, in pixels. */
const AISLE_LOGO_HEIGHTS = [24, 32, 40];

/** The listing members a product card and the page data block read; the API builds no others. */
const AISLE_CARD_FIELDS = 'id,title,url,price,category,photos,product,rating';

/**
 * Whether the admin lets reviews carry stars (Settings > Comments). Off hides every star the
 * theme draws. Core before 7.1 has no switch and always took a rating.
 */
function aisle_ratings_on(): bool
{
    return !function_exists('osc_comment_ratings_enabled') || osc_comment_ratings_enabled();
}

/*
 * Core draws account, login, contact and billing pages inside this header and footer. The head
 * title, description and canonical are left to the theme (printed from the seo route).
 */
osc_add_theme_support('chrome', [
    'header'  => 'common/header.php',
    'footer'  => 'common/footer.php',
    'account' => true,
]);
osc_add_theme_support('head', ['title' => false, 'description' => false, 'canonical' => false]);
// Core pages are styled by css/core-pages.css, so core skips its own sheet.
osc_add_theme_support('core-styles', false);

require_once AISLE_DIR . '/common/card.php';
require_once AISLE_DIR . '/cart/module.php';
require_once AISLE_DIR . '/sections.php';

if (!OC_ADMIN) {
    osc_add_filter('scripts_defer', static fn () => true);
    osc_add_hook('header', 'aisle_enqueue_base', 5);
}

/**
 * Versioned URL of a theme file: the active child's copy when it has one, else Aisle's.
 */
function aisle_asset(string $file): string
{
    return osc_asset_url_versioned(osc_current_web_theme_url($file));
}

/**
 * Styles and the core script, on every page.
 */
function aisle_enqueue_base(): void
{
    osc_enqueue_style('aisle-tokens', aisle_asset('css/tokens.css'));
    osc_enqueue_style('aisle', aisle_asset('css/aisle.css'));
    osc_enqueue_style('aisle-core-pages', aisle_asset('css/core-pages.css'));
    osc_register_script('aisle-core', aisle_asset('js/aisle-core.js'));
    osc_enqueue_script('aisle-core');
    osc_register_script('aisle-wishlist', aisle_asset('js/aisle-wishlist.js'));
    osc_enqueue_script('aisle-wishlist');
    osc_register_script('aisle-track', aisle_asset('js/aisle-track.js'));
    osc_enqueue_script('aisle-track');
    if (aisle_is_address_book()) {
        osc_register_script('aisle-address', aisle_asset('js/aisle-address.js'));
        osc_enqueue_script('aisle-address');
    }
    if (aisle_is_preview()) {
        osc_register_script('aisle-preview', aisle_asset('js/aisle-preview.js'));
        osc_enqueue_script('aisle-preview');
    }
}

/**
 * Whether this is the account address book, or its form posted back with errors.
 */
function aisle_is_address_book(): bool
{
    return osc_is_current_page('user', 'addresses') || osc_is_current_page('user', 'addresses_post');
}

if (!OC_ADMIN) {
    // The address book's State list and postcode label use the same words as checkout.
    osc_add_hook('footer', static function (): void {
        if (!aisle_is_address_book()) {
            return;
        }
        $text = [
            'pin_label'  => __('PIN code', 'aisle'),
            'zip_label'  => __('Postcode', 'aisle'),
            'state_pick' => __('Choose…', 'aisle'),
        ];
        echo '<script type="application/json" id="aisle-address-i18n">'
            . json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)
            . '</script>' . PHP_EOL;
    });
}

/*
 * Wishlist page: open to signed-out visitors (their list is kept in the browser) and listed in the
 * account menu below. The pattern allows a query string for ?wish=1,2,3.
 */
osc_add_route('aisle-wishlist', '^wishlist/?(?:\?.*)?$', 'wishlist', 'wishlist.php', false, 'wishlist', 'wishlist', 'Wishlist');

/**
 * Address of the wishlist page.
 */
function aisle_wishlist_url(): string
{
    return (string) osc_route_url('aisle-wishlist');
}

if (!OC_ADMIN) {
    osc_add_filter('user_menu_filter', static function ($options) {
        $options = is_array($options) ? $options : [];
        $options[] = ['name' => __('Wishlist', 'aisle'), 'url' => aisle_wishlist_url(), 'class' => 'opt_wishlist'];

        return $options;
    });
}

/**
 * Read one API route as the visitor. Returns the body on 200, otherwise null; the first
 * failure of a request is written to the error log.
 *
 * @param array<string,mixed> $query
 *
 * @return array<mixed>|null
 */
function aisle_api(string $path, array $query = []): ?array
{
    static $logged = false;
    static $seen   = [];

    $key = $path . '?' . http_build_query($query);
    if (isset($seen[$key])) {
        return $seen[$key];
    }
    $res = getenv('AISLE_API_FAIL') === '1' ? ['status' => 503] : osc_api_get($path, $query);
    if (($res['status'] ?? 0) === 200 && is_array($res['body'] ?? null)) {
        return $seen[$key] = $res['body'];
    }
    $status = (int) ($res['status'] ?? 0);
    if ($status === 0 || $status === 429 || $status >= 500) {
        aisle_api_failed(true);
    }
    if (!$logged) {
        $logged = true;
        error_log('aisle: GET ' . $path . ' answered ' . $status);
    }

    return null;
}

/**
 * True when an API read in this request failed on the server side (no answer, 429 or 5xx), as
 * opposed to a missing record. Pages use it to show a retry line instead of an empty state.
 */
function aisle_api_failed(bool $mark = false): bool
{
    static $failed = false;
    if ($mark) {
        $failed = true;
    }

    return $failed;
}

/**
 * A quiet "could not load, try again" line, once per request, and only after an API read failed.
 */
function aisle_api_error(bool $wrap = false): string
{
    static $done = false;
    if ($done || !aisle_api_failed()) {
        return '';
    }
    $done = true;

    $line = '<p class="api-error" role="status">' . osc_esc_html(__('We could not load this just now.', 'aisle')) . ' <a href="">' . osc_esc_html(__('Try again', 'aisle')) . '</a></p>';

    return $wrap ? '<div class="wrap">' . $line . '</div>' : $line;
}

/**
 * The page facts a tracking plugin reads from the #aisle-page block. Keys set with
 * aisle_page_data_set() win over what aisle_page_data() works out itself.
 *
 * @return array<string,mixed>
 */
function &aisle_page_data_store(): array
{
    static $store = [];

    return $store;
}

/**
 * True when the request was routed to one of the theme's custom routes.
 */
function aisle_route_is(string $id): bool
{
    return Params::getParamString('page') === 'custom' && Params::getParamString('route') === $id;
}

/**
 * Set one key of the page data block: page, currency, list, items, search_term or order.
 *
 * @param mixed $value
 */
function aisle_page_data_set(string $key, $value): void
{
    $store       = &aisle_page_data_store();
    $store[$key] = $value;
}

/**
 * A variant's options as one label, "Red / M". Empty when it has none.
 *
 * @param mixed $options
 */
function aisle_option_label($options): string
{
    if (!is_array($options)) {
        return '';
    }

    return implode(' / ', array_filter(array_map('strval', array_values($options)), 'strlen'));
}

/**
 * One tracked item from an API listing. The first variant stands in unless one is given.
 *
 * @param array<string,mixed>      $listing
 * @param array<string,mixed>|null $variant
 *
 * @return array<string,mixed>
 */
function aisle_page_item(array $listing, int $index, ?array $variant = null): array
{
    $variants = $listing['product']['variants'] ?? [];
    if ($variant === null && is_array($variants) && isset($variants[0]) && is_array($variants[0])) {
        $variant = $variants[0];
    }
    $price  = is_array($listing['price'] ?? null) ? $listing['price'] : [];
    $amount = $variant['price'] ?? $price['amount'] ?? 0;
    $item   = [
        'item_id'       => (string) (int) ($listing['id'] ?? 0),
        'item_name'     => (string) ($listing['title'] ?? ''),
        'price'         => is_numeric($amount) ? round((float) $amount, 2) : 0.0,
        'currency'      => (string) ($price['currency'] ?? ''),
        'item_category' => is_array($listing['category'] ?? null) ? (string) ($listing['category']['name'] ?? '') : '',
        'index'         => $index,
    ];
    if (is_array($variant) && (int) ($variant['id'] ?? 0) > 0) {
        $item['variant_id'] = (int) $variant['id'];
        $label              = aisle_option_label($variant['options'] ?? null);
        if ($label !== '') {
            $item['item_variant'] = $label;
        }
    }

    return $item;
}

/**
 * Items of a list of API listings, numbered from $offset.
 *
 * @param array<int,mixed> $listings
 *
 * @return array<int,array<string,mixed>>
 */
function aisle_page_items(array $listings, int $offset = 0): array
{
    $out = [];
    foreach ($listings as $listing) {
        if (is_array($listing)) {
            $out[] = aisle_page_item($listing, $offset + count($out));
        }
    }

    return $out;
}

/**
 * The order just placed, for the purchase event: only for the signed-in owner, only on the
 * order page opened with ?placed=1. No names, e-mails or addresses.
 *
 * @return array<string,mixed>|null
 */
function aisle_page_order(): ?array
{
    if (!aisle_route_is('aisle-order') || Params::getParamInt('placed') !== 1 || !osc_is_web_user_logged_in()) {
        return null;
    }
    $id    = Params::getParamInt('id');
    $body  = $id > 0 ? aisle_api('account/orders/' . $id) : null;
    $order = is_array($body['data'] ?? null) ? $body['data'] : null;
    if ($order === null || ($order['kind'] ?? 'goods') !== 'goods') {
        return null;
    }
    $num   = static fn ($a): float => is_array($a) && is_numeric($a['amount'] ?? null) ? (float) $a['amount'] : 0.0;
    $items = [];
    $tax   = 0.0;
    $ship  = 0.0;
    $coupon = '';
    foreach ((array) ($order['lines'] ?? []) as $line) {
        if (!is_array($line)) {
            continue;
        }
        switch ($line['kind'] ?? '') {
            case 'item':
                $row = [
                    'item_id'   => (string) (int) ($line['listing']['id'] ?? 0),
                    'item_name' => (string) ($line['title'] ?? ''),
                    'price'     => round($num($line['unit'] ?? null), 2),
                    'quantity'  => (int) ($line['qty'] ?? 1),
                    'currency'  => (string) ($line['unit']['currency'] ?? ''),
                    'index'     => count($items),
                ];
                $label = aisle_option_label($line['options'] ?? null);
                if ($label !== '') {
                    $row['item_variant'] = $label;
                }
                $items[] = $row;
                break;
            case 'tax':
                $tax += !empty($line['included']) ? $num($line['unit'] ?? null) : $num($line['amount'] ?? null);
                break;
            case 'shipping':
                $ship += $num($line['amount'] ?? null);
                break;
            case 'discount':
                if ($coupon === '') {
                    $coupon = (string) ($line['title'] ?? '');
                }
                break;
        }
    }

    return [
        'transaction_id' => (string) ($order['number'] ?? $id),
        'value'          => round($num($order['total'] ?? null), 2),
        'tax'            => round($tax, 2),
        'shipping'       => round($ship, 2),
        'coupon'         => $coupon,
        'currency'       => (string) ($order['total']['currency'] ?? ''),
        'items'          => $items,
    ];
}

/**
 * Everything the #aisle-page block carries on this request.
 *
 * @return array<string,mixed>
 */
function aisle_page_data(): array
{
    $data = aisle_page_data_store();
    if (!isset($data['page'])) {
        if (osc_is_home_page()) {
            $data['page'] = 'home';
        } elseif (osc_is_search_page()) {
            $data['page'] = 'search';
        } elseif (osc_is_ad_page()) {
            $data['page'] = 'product';
        } elseif (aisle_route_is('aisle-checkout')) {
            $data['page'] = 'checkout';
        } elseif (aisle_route_is('aisle-order')) {
            $data['page'] = 'order';
        } elseif (osc_is_static_page()) {
            $data['page'] = 'page';
        } elseif (osc_is_user_dashboard() || aisle_route_is('aisle-wishlist') || osc_is_current_page('user', '')) {
            $data['page'] = 'account';
        } else {
            $data['page'] = 'page';
        }
    }
    if (!isset($data['currency'])) {
        $data['currency'] = (string) osc_currency();
    }
    if (!isset($data['order']) && $data['page'] === 'order') {
        $order = aisle_page_order();
        if ($order !== null) {
            $data['order'] = $order;
        }
    }
    if (!isset($data['items'])) {
        $data['items'] = [];
    }

    return $data;
}

/**
 * Items of one site menu ('main' or 'footer'), each with a label and url. Fetched once per request.
 *
 * @return array<int,array{label:string,url:string}>
 */
function aisle_menu(string $id): array
{
    static $menus = null;

    if ($menus === null) {
        $menus = [];
        $body  = aisle_api('menus');
        foreach (($body['data'] ?? []) as $menu) {
            if (is_array($menu) && isset($menu['id'], $menu['items']) && is_array($menu['items'])) {
                $menus[(string) $menu['id']] = $menu['items'];
            }
        }
    }

    return $menus[$id] ?? [];
}

/**
 * True when a menu link points at this page, its category, or a category above it.
 * Friendly URLs match by path prefix; plain ?page=search links match by their query.
 */
function aisle_menu_current(string $url): bool
{
    static $here = null;

    if ($here === null) {
        $paths = [];
        $ids   = [];
        $uri   = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $paths[] = '/' . trim($uri, '/');
        if (osc_is_search_page()) {
            $ids = array_map('intval', (array) osc_search_category_id());
        } elseif (osc_is_ad_page()) {
            $ids[] = (int) osc_item_category_id();
        }
        $seen = [];
        foreach ($ids as $id) {
            foreach (Category::getInstance()->toRootTree($id) as $cat) {
                $catId = (int) ($cat['pk_i_id'] ?? 0);
                if ($catId > 0 && !isset($seen[$catId])) {
                    $seen[$catId] = true;
                    $paths[]      = '/' . trim((string) parse_url(osc_search_url(['sCategory' => $catId]), PHP_URL_PATH), '/');
                }
            }
        }
        $here = ['paths' => $paths, 'cats' => $seen];
    }

    $parts = parse_url($url);
    if (!is_array($parts)) {
        return false;
    }
    parse_str((string) ($parts['query'] ?? ''), $query);
    if (isset($query['sCategory']) && is_numeric($query['sCategory'])) {
        return isset($here['cats'][(int) $query['sCategory']]);
    }
    $path = '/' . trim((string) ($parts['path'] ?? ''), '/');
    if ($path === '/' || $path === '/index.php' || $query !== []) {
        return false;
    }
    foreach ($here['paths'] as $current) {
        if ($current === $path || strpos($current . '/', $path . '/') === 0) {
            return true;
        }
    }

    return false;
}

/**
 * True while the customiser's preview frame is showing an unsaved draft.
 */
function aisle_is_preview(): bool
{
    return function_exists('osc_customiser_preview') && osc_customiser_preview();
}

/**
 * A theme setting, or $default while it is empty. In the customiser preview the drafted value wins.
 *
 * @param mixed $default
 *
 * @return mixed
 */
function aisle_setting(string $key, $default = '')
{
    $value = function_exists('osc_customiser_draft_value') ? osc_customiser_draft_value('aisle', $key) : null;
    if ($value === null) {
        $value = osc_get_preference($key, 'aisle');
    }

    return $value === null || $value === false || $value === '' ? $default : $value;
}

/**
 * The owner's default scheme for <html data-theme>: '' (follow the system), 'light' or 'dark'.
 */
function aisle_scheme(): string
{
    $scheme = (string) aisle_setting('scheme', 'system');

    return $scheme === 'light' || $scheme === 'dark' ? $scheme : '';
}

/**
 * The owner's brand preset for <html data-brand>; raspberry is the default and sets nothing.
 */
function aisle_brand(): string
{
    $brand = (string) aisle_setting('brand', 'raspberry');

    return in_array($brand, AISLE_BRANDS, true) && $brand !== 'raspberry' ? $brand : '';
}

/**
 * The owner's font pair for <html data-font>; the first pair is the default and sets nothing.
 */
function aisle_font(): string
{
    $font = (string) aisle_setting('font', AISLE_FONTS[0]);

    return in_array($font, AISLE_FONTS, true) && $font !== AISLE_FONTS[0] ? $font : '';
}

/**
 * The font files the chosen pair needs, for <link rel="preload">. The system pair needs none.
 *
 * @return string[]
 */
function aisle_font_files(): array
{
    switch (aisle_font()) {
        case 'fraunces-inter':
            return ['fonts/fraunces-latin.woff2', 'fonts/inter-latin.woff2'];
        case 'system':
            return [];
        default:
            return ['fonts/figtree-latin.woff2', 'fonts/bricolage-grotesque-latin.woff2'];
    }
}

/**
 * The shop logo height in pixels: one of AISLE_LOGO_HEIGHTS, 32 by default.
 */
function aisle_logo_height(): int
{
    $height = (int) aisle_setting('logo_height', 32);

    return in_array($height, AISLE_LOGO_HEIGHTS, true) ? $height : 32;
}

/**
 * URL of an image setting (a media resource id), or '' when it is not set.
 */
function aisle_image_url(string $key, string $variant = 'preview'): string
{
    $id = aisle_setting($key, '');
    if ($id === '' || !class_exists('mindstellar\\settings\\SettingsImage')) {
        return '';
    }

    return \mindstellar\settings\SettingsImage::url($id, $variant);
}

/**
 * The logo link: the owner's image (with an optional dark one), else the site name's initial and the name.
 */
function aisle_logo(): string
{
    $site = (string) osc_get_preference('pageTitle');
    $home = osc_esc_html(osc_base_url());
    $img  = aisle_image_url('logo');
    if ($img === '') {
        $initial = $site !== '' ? mb_strtoupper(mb_substr($site, 0, 1, 'UTF-8'), 'UTF-8') : 'A';

        return '<a class="logo" href="' . $home . '"><i aria-hidden="true">' . osc_esc_html($initial) . '</i>' . osc_esc_html($site) . '</a>';
    }
    $height = aisle_logo_height();
    $dark   = aisle_image_url('logo_dark');
    $html   = '<a class="logo' . ($dark !== '' ? ' has-dark' : '') . '" href="' . $home . '">'
        . '<img class="logo-light" src="' . osc_esc_html($img) . '" height="' . $height . '" alt="' . osc_esc_html($site) . '">';
    if ($dark !== '') {
        $html .= '<img class="logo-dark" src="' . osc_esc_html($dark) . '" height="' . $height . '" alt="">';
    }

    return $html . '</a>';
}

/**
 * The extra <html> attributes: the font pair, and in the customiser preview the preview class and the
 * value lists the preview script may apply.
 */
function aisle_html_attrs(): string
{
    $out = aisle_font() !== '' ? ' data-font="' . osc_esc_html(aisle_font()) . '"' : '';
    if (aisle_is_preview()) {
        $out .= ' class="is-preview"'
            . ' data-osc-allow-data-brand="' . osc_esc_html(implode(' ', AISLE_BRANDS)) . '"'
            . ' data-osc-allow-data-theme="' . osc_esc_html('system light dark') . '"'
            . ' data-osc-allow-data-font="' . osc_esc_html(implode(' ', AISLE_FONTS)) . '"';
    }

    return $out;
}

/**
 * The current item's place as "City, Region, Country", each in the visitor's language.
 */
function aisle_item_location(): string
{
    $parts = [];
    foreach ([['city', osc_item_city_id(), osc_item_city()], ['region', osc_item_region_id(), osc_item_region()], ['country', osc_item_country_code(), osc_item_country()]] as [$type, $id, $name]) {
        $label = $id !== '' && $id !== '0' && function_exists('osc_location_name') ? osc_location_name($type, $id, $name) : $name;
        if ($label !== '' && !in_array($label, $parts, true)) {
            $parts[] = $label;
        }
    }

    return implode(', ', $parts);
}

/**
 * An SVG icon from the set in common/icons.php. 20px, or 16px when $small.
 */
function aisle_icon(string $name, bool $small = false): string
{
    $name = preg_replace('/[^a-z0-9-]/', '', $name);

    return '<svg class="ico' . ($small ? ' sm' : '') . '" aria-hidden="true"><use href="#i-' . $name . '"/></svg>';
}

/**
 * A price as the API formatted it, in a tabular-number span. Empty when the listing has no price.
 *
 * @param array<string,mixed>|null $price a listing's `price` object
 */
function aisle_price(?array $price): string
{
    if ($price === null || !isset($price['formatted'])) {
        return '';
    }

    return '<span class="num">' . osc_esc_html((string) $price['formatted']) . '</span>';
}

/**
 * Format a decimal amount ("1299.00") like the API's price.formatted, for the was-price.
 */
function aisle_money(string $amount, ?string $currency): string
{
    $symbol = (string) $currency;
    foreach (osc_get_currencies() as $row) {
        if (strcasecmp((string) ($row['pk_c_code'] ?? ''), (string) $currency) === 0) {
            $symbol = (string) ($row['s_description'] ?? $symbol);
            break;
        }
    }

    return (string) osc_format_price((int) round((float) $amount * 1000000), $symbol);
}

/**
 * Print a widget zone, or nothing when it holds nothing.
 */
function aisle_widget_zone(string $location, string $class): void
{
    ob_start();
    osc_show_widgets($location);
    $html = trim((string) ob_get_clean());

    if ($html !== '') {
        echo '<div class="' . osc_esc_html($class) . '">' . $html . '</div>';
    }
}

/**
 * The widget zone the owner can place widgets into.
 */
function aisle_widget_locations(): void
{
    osc_add_theme_support('widget_locations', [
        'footer' => [
            'label'       => __('Footer', 'aisle'),
            'description' => __('Below the footer links.', 'aisle'),
        ],
        'home'   => [
            'label'       => __('Home page sections', 'aisle'),
            'description' => __('The sections of the home page, top to bottom.', 'aisle'),
        ],
    ]);
}
osc_add_hook('init', 'aisle_widget_locations');

/**
 * The owner's settings page (admin only). The front end reads the values through aisle_setting().
 */
function aisle_register_settings(): void
{
    if (!defined('OC_ADMIN') || OC_ADMIN !== true) {
        return;
    }

    $form = (new \mindstellar\admin\ui\FormSpec('aisle'))
        ->title(__('Aisle', 'aisle'))
        ->menu('appearance')
        ->group(__('Look', 'aisle'))
        ->select(
            'scheme',
            __('Colour scheme', 'aisle'),
            [
                'system' => __('Follow the visitor\'s device', 'aisle'),
                'light'  => __('Light', 'aisle'),
                'dark'   => __('Dark', 'aisle'),
            ],
            __('Visitors can still switch it with the button in the header.', 'aisle')
        )
            ->default('system')
        ->select(
            'brand',
            __('Brand colour', 'aisle'),
            [
                'raspberry' => __('Raspberry', 'aisle'),
                'ocean'     => __('Ocean', 'aisle'),
                'forest'    => __('Forest', 'aisle'),
                'marigold'  => __('Marigold', 'aisle'),
                'plum'      => __('Plum', 'aisle'),
            ]
        )
            ->default('raspberry')
        ->select(
            'font',
            __('Fonts', 'aisle'),
            [
                'bricolage-figtree' => __('Bricolage + Figtree (default)', 'aisle'),
                'fraunces-inter'    => __('Fraunces + Inter', 'aisle'),
                'system'            => __('System fonts', 'aisle'),
            ],
            __('The fonts are served from your site. System fonts load no font file.', 'aisle')
        )
            ->default('bricolage-figtree')
        ->image('logo', __('Logo', 'aisle'), __('Shown in the header and footer. Without it the shop name is used.', 'aisle'))
            ->attrs(array('data-surface' => 'light'))
        ->image('logo_dark', __('Logo on dark', 'aisle'), __('Optional. Used when the dark scheme is on.', 'aisle'))
            ->attrs(array('data-surface' => 'dark'))
        ->select(
            'logo_height',
            __('Logo height', 'aisle'),
            ['24' => __('Small', 'aisle'), '32' => __('Medium', 'aisle'), '40' => __('Large', 'aisle')]
        )
            ->default('32')
        ->text(
            'announcement',
            __('Announcement bar', 'aisle'),
            __('One short line above the header. Leave empty to hide the bar.', 'aisle')
        );

    $form->group(__('Catalogue', 'aisle'))
        ->textarea(
            'filter_options',
            __('Filters by option', 'aisle'),
            __('One line per option, with its values after a colon. Example: Colour: Sunflower, Cherry, Navy', 'aisle')
        );

    $form->group(__('Footer', 'aisle'))
        ->text('footer_note', __('Footer note', 'aisle'), __('A short line about the shop, under the logo.', 'aisle'))
        ->text('payment_methods', __('Ways to pay', 'aisle'), __('Shown as small tags under the footer note, separated by commas. Example: UPI, VISA, COD', 'aisle'))
        ->register();
}
osc_add_hook('init', 'aisle_register_settings');

/**
 * First-run defaults. Never overwrites a value the owner already saved.
 */
function aisle_theme_install(): void
{
    if (!osc_get_preference('scheme', 'aisle')) {
        osc_set_preference('scheme', 'system', 'aisle');
    }
    if (!osc_get_preference('brand', 'aisle')) {
        osc_set_preference('brand', 'raspberry', 'aisle');
    }
    aisle_seed_home_sections();
    osc_set_preference('version', AISLE_VERSION, 'aisle');
    osc_reset_preferences();
}

function aisle_theme_update(string $current): void
{
    if ($current === '') {
        aisle_theme_install();

        return;
    }
    aisle_seed_home_sections();
    osc_set_preference('version', AISLE_VERSION, 'aisle');
    osc_reset_preferences();
}

function aisle_check_install(): void
{
    $current = (string) osc_get_preference('version', 'aisle');
    if ($current === '' || version_compare($current, AISLE_VERSION, '<')) {
        aisle_theme_update($current);
    }
}
aisle_check_install();

/**
 * The "Full width" page template: the theme's own page.php without the narrow reading column.
 * The id is shared by our themes, so a page keeps the template when the site changes theme.
 */
function aisle_page_templates(): void
{
    if (function_exists('osc_register_page_template')) {
        osc_register_page_template('theme.full_width', array(
            'label'       => __('Full width', 'aisle'),
            'description' => __('The page uses the whole width of the content area.', 'aisle'),
            'render'      => 'page.php',
        ));
    }
}

osc_add_hook('init', 'aisle_page_templates');

/** Whether the current static page uses the Full width template. */
function aisle_is_full_width(): bool
{
    if (!osc_is_static_page()) {
        return false;
    }
    $meta = json_decode((string) osc_static_page_field('s_meta'), true);

    return is_array($meta) && ($meta['template'] ?? '') === 'theme.full_width';
}
