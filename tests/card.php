<?php
/*
 * This file is part of Aisle (Mindstellar).
 * Copyright (c) 2026 Navjot Tomer (Mindstellar) and contributors
 *
 * Distributed under the GNU General Public License v3.0 or later. See LICENSE.
 *
 * SPDX-License-Identifier: GPL-3.0-or-later
 */

/**
 * aisle_card(): a card with a preview and a thumbnail offers both in srcset, and its sizes ends
 * with the phone width, so a phone loads the thumbnail. A card with several variants shows Choose options.
 * Usage:  php tests/card.php
 */

define('AISLE_DIR', dirname(__DIR__));
function osc_esc_html($text): string
{
    return htmlspecialchars((string) $text, ENT_QUOTES);
}
function __(string $text, string $domain = ''): string
{
    return $text;
}
function _e(string $text, string $domain = ''): void
{
    echo $text;
}
function aisle_money(string $amount, ?string $currency): string
{
    return '€' . $amount;
}
function aisle_price(?array $price): string
{
    return '<span class="num">' . ($price['formatted'] ?? '') . '</span>';
}
const AISLE_CARD_FIELDS = 'id';
$GLOBALS['api_calls'] = [];
function aisle_api(string $path, array $query = []): ?array
{
    $GLOBALS['api_calls'][] = $query;
    $data = [];
    foreach (explode(',', (string) $query['ids']) as $id) {
        $data[] = ['id' => (int) $id, 'price' => ['amount' => '40', 'formatted' => '€' . $id]];
    }

    return ['data' => $data];
}
class View
{
    public static function getInstance(): self
    {
        return new self();
    }

    public function _get(string $k): array
    {
        return [['pk_i_id' => 7], ['pk_i_id' => 8], ['pk_i_id' => 9]];
    }
}
require_once AISLE_DIR . '/common/card.php';

$html = aisle_card([
    'title'  => 'Mug',
    'url'    => '/mug',
    'photos' => [['preview' => '/p/mug_preview.jpg', 'thumbnail' => '/p/mug_thumbnail.jpg']],
]);
preg_match('/ srcset="([^"]*)" sizes="([^"]*)"/', $html, $m);
$srcset = $m[1] ?? '';
$sizes  = array_map('trim', explode(',', $m[2] ?? ''));

$checks = [
    'srcset offers the thumbnail at 240w and the preview at 800w' => $srcset === '/p/mug_thumbnail.jpg 240w, /p/mug_preview.jpg 800w',
    'sizes ends with the phone branch, 120px with no media query' => end($sizes) === '120px',
    'the wider branches come first'                               => count($sizes) === 3 && str_starts_with($sizes[0], '(min-width: '),
];
$one = aisle_card(['title' => 'Lamp', 'url' => '/lamp', 'product' => ['variants' => [['id' => 5, 'in_stock' => true]]]]);
$many = aisle_card(['title' => 'Tee', 'url' => '/tee', 'product' => ['variants' => [['id' => 6, 'in_stock' => true], ['id' => 7, 'in_stock' => false]]]]);
$gone = aisle_card(['title' => 'Hat', 'url' => '/hat', 'product' => ['variants' => [['id' => 8, 'in_stock' => false], ['id' => 9, 'in_stock' => false]]]]);
$checks['one variant in stock quick-adds']        = str_contains($one, 'data-quick-add="5"') && !str_contains($one, 'Choose options');
$checks['several variants link to the product']   = str_contains($many, 'href="/tee"') && str_contains($many, 'Choose options') && !str_contains($many, 'data-quick-add');
$checks['all variants sold out has no button']    = !str_contains($gone, 'quick');
$mixed = aisle_card(['title' => 'Cap', 'url' => '/cap', 'product' => ['variants' => [['id' => 1, 'in_stock' => true, 'low_stock' => 3], ['id' => 2, 'in_stock' => true]]]]);
$allLow = aisle_card(['title' => 'Cup', 'url' => '/cup', 'product' => ['variants' => [['id' => 3, 'in_stock' => true, 'low_stock' => 3], ['id' => 4, 'in_stock' => true, 'low_stock' => 2]]]]);
$checks['one low variant of several shows no note'] = !str_contains($mixed, 'Only ');
$checks['all variants low shows the total']         = str_contains($allLow, 'Only 5 left');
$vp = aisle_card(['title' => 'Mat', 'url' => '/mat', 'price' => ['amount' => '30', 'formatted' => '€30', 'currency' => 'EUR'], 'product' => ['variants' => [['id' => 1, 'price' => '20', 'compare_at' => '30'], ['id' => 2, 'price' => '15']]]]);
$vs = aisle_card(['title' => 'Mat', 'url' => '/mat', 'price' => ['amount' => '30', 'formatted' => '€30', 'currency' => 'EUR'], 'product' => ['variants' => [['id' => 1, 'price' => '20', 'compare_at' => '30'], ['id' => 2, 'price' => '25']]]]);
$checks['lowest variant price shows with From']  = str_contains($vp, 'From €15') && !str_contains($vp, 'class="deal');
$checks['sale shows when the shown variant is on sale'] = str_contains($vs, 'From €20') && str_contains($vs, '33% off');
$plain = aisle_card(['title' => 'Rug', 'url' => '/rug', 'price' => ['amount' => '40', 'formatted' => '€40', 'currency' => 'EUR']]);
$checks['no variant prices shows the product price'] = str_contains($plain, '€40');
$alert = ['price' => ['amount' => '20', 'currency' => 'EUR', 'formatted' => '€20'], 'product' => ['variants' => [['id' => 1, 'price' => '15'], ['id' => 2, 'price' => '26']]]];
$checks['alert price line uses the card price code'] = aisle_listing_price_text($alert) === 'From €15';
$checks['alert price line without variant prices is the listing price'] = aisle_listing_price_text(['price' => ['amount' => '40', 'formatted' => '€40', 'currency' => 'EUR']]) === '€40';
$rows = [aisle_alert_price_text('core', ['pk_i_id' => 7]), aisle_alert_price_text('core', ['pk_i_id' => 8]), aisle_alert_price_text('core', ['pk_i_id' => 9])];
$checks['alert rows read plain text from the API']  = $rows === ['€7', '€8', '€9'];
$checks['three alert rows cost one API call']      = count($GLOBALS['api_calls']) === 1 && $GLOBALS['api_calls'][0]['ids'] === '7,8,9';
$checks['a row the API lacks keeps the core text'] = aisle_alert_price_text('core', ['pk_i_id' => 0]) === 'core';
$failed = 0;
foreach ($checks as $what => $ok) {
    if (!$ok) {
        $failed++;
        echo 'FAIL  ' . $what . "\n";
    }
}
echo $failed === 0 ? 'PASS  ' . count($checks) . " cases\n" : "$failed failed\n";
exit($failed === 0 ? 0 : 1);
