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
 * aisle_safe_url(): only http(s) addresses and single-slash paths pass, and a path comes back as a full address.
 * Usage:  php tests/safe-url.php
 */

define('ABS_PATH', __DIR__);
function osc_add_hook(...$args): void
{
}
require_once dirname(__DIR__) . '/sections.php';

$cases = [
    ['https://example.com/a?b=1'      => 'https://example.com/a?b=1'],
    ['  /cart  '                     => 'http://shop.test:8090/cart'],
    ['/search?x=1'                   => 'http://shop.test:8090/search?x=1'],
    ['//evil.example'                => ''],
    ['/\\evil.example'               => ''],
    ['\\\\evil.example'              => ''],
    ['/a\\b'                         => ''],
    ["/a\tb"                         => ''],
    ["/a\x7fb"                       => ''],
    ["https://example.com/\x01"      => ''],
    ['javascript:alert(1)'           => ''],
    ['ftp://example.com'             => ''],
    ['/a"b'                          => ''],
    ['/a b'                          => ''],
    ['cart'                          => ''],
    ['/'                             => 'http://shop.test:8090/'],
];
$failed = 0;
foreach ($cases as $case) {
    foreach ($case as $in => $want) {
        $got = aisle_safe_url($in);
        if ($got !== $want) {
            $failed++;
            echo 'FAIL  ' . json_encode($in) . ' gave ' . json_encode($got) . ', wanted ' . json_encode($want) . "\n";
        }
    }
}

function osc_base_url($with_index = false)
{
    return 'http://shop.test:8090/sub/';
}
$resolved = [
    ['aisle_safe_url', '/sub/cart', 'http://shop.test:8090/sub/cart'],
    ['aisle_safe_url', '//evil.test', ''],
    ['aisle_safe_url', 'https://other.test/a', 'https://other.test/a'],
    ['aisle_safe_image', '/sub/oc-content/a.jpg?x=1', 'http://shop.test:8090/sub/oc-content/a.jpg?x=1'],
    ['aisle_safe_image', '/\\evil.test', ''],
    ['aisle_safe_image', 'http://127.0.0.1:8090/oc-content/a.jpg', 'http://127.0.0.1:8090/oc-content/a.jpg'],
    ['aisle_site_path', 'http://shop.test:8090/sub/oc-content/a.jpg', '/sub/oc-content/a.jpg'],
    ['aisle_site_path', 'http://shop.test:8090/sub/index.php?page=search', '/sub/index.php?page=search'],
    ['aisle_site_path', 'http://other.test/a.jpg', 'http://other.test/a.jpg'],
    ['aisle_site_path', '/already', '/already'],
    ['aisle_safe_image', '/a\\b', ''],
    ['aisle_site_path', 'http://shop.test:8090/sub/a b.jpg', 'http://shop.test:8090/sub/a b.jpg'],
    ['aisle_site_path', 'http://shop.test:8090/sub/a"b.jpg', 'http://shop.test:8090/sub/a"b.jpg'],
];
foreach ($resolved as [$fn, $in, $want]) {
    $got = $fn($in);
    if ($got !== $want) {
        $failed++;
        echo 'FAIL  ' . $fn . '(' . json_encode($in) . ') gave ' . json_encode($got) . ', wanted ' . json_encode($want) . "\n";
    }
}
$cases = array_merge($cases, $resolved);
echo $failed === 0 ? "PASS  " . count($cases) . " cases\n" : "$failed failed\n";
exit($failed === 0 ? 0 : 1);
