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
$failed = 0;
foreach ($checks as $what => $ok) {
    if (!$ok) {
        $failed++;
        echo 'FAIL  ' . $what . "\n";
    }
}
echo $failed === 0 ? 'PASS  ' . count($checks) . " cases\n" : "$failed failed\n";
exit($failed === 0 ? 0 : 1);
