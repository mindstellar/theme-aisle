<?php
/*
 * This file is part of Aisle (Mindstellar).
 * Copyright (c) 2021-2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * aisle_seed_home_sections(): the update that turns the old fixed home page into five rows at
 * the "home" widget location, copying what the old page showed: hero text, photo, buttons and promises. Run twice it adds
 * nothing, and it never writes when the location already holds a row.
 *
 * DB-backed, on a scratch database through the core's test helpers.
 * Usage:  OSC_CORE=/path/to/shopclass/ php tests/seed-home-sections.php
 */

$core = rtrim((string) (getenv('OSC_CORE') ?: dirname(__DIR__, 2) . '/osclass'), '/') . '/';
require_once $core . 'tests/lib/scratchdb.php';
require_once $core . 'tests/lib/harness.php';

$admin = scratchdb_session('aisle_seed_home');
if (!defined('OSC_CACHE_TTL')) {
    define('OSC_CACHE_TTL', 60);
}
foreach (['osc_base_url' => 'return $GLOBALS["aisle_test_base"] ?? "http://localhost/";', 'osc_plugins_path' => 'return ABS_PATH . "oc-content/plugins/";', 'osc_base_path' => 'return ABS_PATH;', 'osc_current_web_theme_url' => 'return "http://localhost/theme/" . ($args[0] ?? "");'] as $fn => $body) {
    if (!function_exists($fn)) {
        eval('function ' . $fn . '(...$args) { ' . $body . ' }');
    }
}
require_once ABS_PATH . 'oc-includes/osclass/helpers/hPlugins.php';
require_once ABS_PATH . 'oc-includes/osclass/helpers/hPreference.php';
require_once ABS_PATH . 'oc-includes/osclass/helpers/hCache.php';
if (!function_exists('__')) {
    function __(string $text, string $domain = ''): string
    {
        return $text;
    }
}
require_once dirname(__DIR__) . '/sections.php';

$table = DB_TABLE_PREFIX . 't_widget';
$rows  = static function () use ($admin, $table): array {
    $out = [];
    $res = $admin->query("SELECT s_type, s_description, s_config, i_order FROM $table WHERE s_location = 'home' ORDER BY i_order, pk_i_id");
    while ($r = $res->fetch_assoc()) {
        $r['s_config'] = json_decode($r['s_config'], true);
        $out[] = $r;
    }

    return $out;
};
$prefs = [
    'hero_title' => 'Good things, *easy to find.*', 'hero_text' => 'Fresh stock every week.',
    'promise_1_title' => 'Free delivery', 'promise_1_text' => 'On orders over 499',
    'promise_2_title' => 'Easy returns', 'promise_2_text' => 'Within 30 days',
    'promise_3_title' => 'Cash on delivery', 'promise_3_text' => 'Pay at your door',
    'promise_4_title' => 'Secure checkout', 'promise_4_text' => 'Cards and UPI',
];
foreach ($prefs as $key => $value) {
    seed_exec($admin, 'INSERT INTO ' . DB_TABLE_PREFIX . "t_preference (s_section, s_name, s_value, e_type) VALUES ('aisle', ?, ?, 'STRING')", 'ss', [$key, ' ' . $value . ' ']);
}
osc_reset_preferences();

harness_section('an install with the old fixed home');

aisle_seed_home_sections();
$home = $rows();
pin('five rows in the old order', [
    ['aisle.hero', 'Hero'], ['aisle.categories', 'Categories'], ['aisle.products', 'Deals'],
    ['aisle.promise', 'Promise'], ['aisle.products', 'New in'],
], array_map(static fn ($r) => [$r['s_type'], $r['s_description']], $home));
pin('i_order is the position', ['0', '1', '2', '3', '4'], array_column($home, 'i_order'));
pin('the hero copies what the old page showed: title, text, photo, buttons and the first three promises as perks', [
    'title' => 'Good things, *easy to find.*', 'text' => 'Fresh stock every week.', 'image' => '/theme/img/hero.jpg',
    'cta_label' => 'Shop now', 'cta_url' => '/?page=search', 'cta2_label' => '', 'cta2_url' => '',
    'tag' => 'first_deal', 'tag_listing' => 0,
    'perk_1' => 'Free delivery', 'perk_2' => 'Easy returns', 'perk_3' => 'Cash on delivery',
], $home[0]['s_config']);
pin('categories show seven', ['title' => '', 'count' => 7], $home[1]['s_config']);
pin('deals are an on-sale grid of eight', ['title' => '', 'source' => 'on_sale', 'category' => '', 'count' => 8, 'layout' => 'grid', 'link_label' => ''], $home[2]['s_config']);
pin('the promise row copies all four pairs with their icons', [
    '1_title' => 'Free delivery', '1_text' => 'On orders over 499', '1_icon' => 'truck',
    '2_title' => 'Easy returns', '2_text' => 'Within 30 days', '2_icon' => 'return',
    '3_title' => 'Cash on delivery', '3_text' => 'Pay at your door', '3_icon' => 'cash',
    '4_title' => 'Secure checkout', '4_text' => 'Cards and UPI', '4_icon' => 'shield',
], $home[3]['s_config']);
pin('new in is a newest rail of six', ['title' => '', 'source' => 'newest', 'category' => '', 'count' => 6, 'layout' => 'rail', 'link_label' => ''], $home[4]['s_config']);
check('every row is shown: no hide switch', array_filter($home, static fn ($r) => array_key_exists('_enabled', $r['s_config'])) === []);

harness_section('run again');

aisle_seed_home_sections();
pin('a second run adds nothing', $home, $rows());

harness_section('a home page the owner already built');

$admin->query("DELETE FROM $table WHERE s_location = 'home'");
seed_exec($admin, "INSERT INTO $table (s_description, s_location, e_kind, s_content, i_order, s_type, s_config) VALUES ('Mine', 'home', 'html', '', 0, 'aisle.banner', '{}')", '', []);
scratchdb_forget_cache();
aisle_seed_home_sections();
pin('one row there means nothing is written', [['aisle.banner', 'Mine']], array_map(static fn ($r) => [$r['s_type'], $r['s_description']], $rows()));

harness_section('a site under /sub/ with an item on sale');

$admin->query("DELETE FROM $table WHERE s_location = 'home'");
scratchdb_forget_cache();
$GLOBALS['aisle_test_base'] = 'http://localhost/sub/';
// Defined only now, so the runs above saw no API and no sale.
if (!defined('AISLE_CARD_FIELDS')) {
    define('AISLE_CARD_FIELDS', 'id,title');
}
eval('function aisle_api(string $path, array $query = []): ?array { return ["data" => [["id" => 1, "title" => "On sale"]]]; }');
aisle_seed_home_sections();
$hero = $rows()[0]['s_config'] ?? [];
pin('the hero buttons point at the deals and new in, as /sub/ paths', [
    'Shop the deals', '/sub/?page=search&sale=1', 'New in', '/sub/?page=search&sOrder=dt_pub_date&iOrderType=desc',
], [$hero['cta_label'] ?? null, $hero['cta_url'] ?? null, $hero['cta2_label'] ?? null, $hero['cta2_url'] ?? null]);

exit(harness_result());
