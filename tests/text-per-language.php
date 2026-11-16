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
 * The announcement bar and footer note are one value per language: a visitor gets their language,
 * then the site default, and the old single value moves to every language once.
 * Usage:  php tests/text-per-language.php
 */

$root  = dirname(__DIR__);
$fail  = [];
$prefs = [];
$cur   = 'en_US';
$hooks = [];
define('ABS_PATH', $root);
$pin = static function (string $what, $want, $got) use (&$fail): void {
    if ($want !== $got) {
        $fail[] = $what . "\n    want " . json_encode($want) . "\n    got  " . json_encode($got);
    }
};

function osc_get_preference($key, $section = 'osclass')
{
    return $GLOBALS['prefs'][$section . '/' . $key] ?? '';
}
function osc_set_preference($key, $value = '', $section = 'osclass')
{
    $GLOBALS['prefs'][$section . '/' . $key] = $value;
}
function osc_delete_preference($key, $section = 'osclass')
{
    unset($GLOBALS['prefs'][$section . '/' . $key]);
}
function osc_reset_preferences()
{
}
function osc_add_hook($name, $fn, $priority = 5)
{
    $GLOBALS['hooks'][$name][] = $fn;
}
function osc_settings_locales()
{
    return ['en_US' => 'English', 'hi_IN' => 'Hindi', 'fr_FR' => 'French'];
}
function osc_current_user_locale()
{
    return $GLOBALS['cur'];
}
function osc_language()
{
    return 'en_US';
}

require $root . '/inc/text.php';

foreach (['announcement', 'footer_note'] as $name) {
    $read = static fn (): string => aisle_text_setting($name, 'fallback');
    $p    = 'aisle/' . $name;

    $prefs = [$p . 'en_US' => 'Hello', $p . 'hi_IN' => 'Namaste'];
    $cur   = 'hi_IN';
    $pin("$name: locale B sees B text", 'Namaste', $read());
    $cur = 'fr_FR';
    $pin("$name: a locale with no value gets the default locale text", 'Hello', $read());
    $prefs = [];
    $pin("$name: nothing saved gives the fallback", 'fallback', $read());

    $prefs = [$p => 'Old line', $p . 'hi_IN' => 'Hindi line'];
    $cur   = 'hi_IN';
    aisle_migrate_text();
    $pin("$name: migration copies the old value, keeps a set one, drops the old key", ['Old line', 'Hindi line', 'Old line', null], [
        $prefs[$p . 'en_US'] ?? null, $prefs[$p . 'hi_IN'] ?? null, $prefs[$p . 'fr_FR'] ?? null, $prefs[$p] ?? null,
    ]);
}
$pin('the move is hooked on init', true, in_array('aisle_migrate_text', $hooks['init'] ?? [], true));

$src = file_get_contents($root . '/functions.php');
foreach (['announcement', 'footer_note'] as $name) {
    $pin("$name is declared translated", true, (bool) preg_match("/'$name'.*?->translate\\(\\)/s", $src));
}
$pin('the customiser no longer edits them as single values', false, str_contains(file_get_contents($root . '/sections.php'), "'announcement'"));
$pin('header reads the announcement per language', true, str_contains(file_get_contents($root . '/common/header.php'), "aisle_text_setting('announcement')"));
$pin('footer reads the note per language', true, str_contains(file_get_contents($root . '/common/footer.php'), "aisle_text_setting('footer_note'"));

if ($fail) {
    fwrite(STDERR, "FAIL\n  " . implode("\n  ", $fail) . "\n");
    exit(1);
}
echo "ok\n";
