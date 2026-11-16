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
 * The announcement bar and footer note are one value per language, saved by core's settings page
 * and read back per visitor language; the old single value moves to every language on an admin
 * request only; the customiser edits them per language and its preview shows the drafted language.
 *
 * DB-backed, on a scratch database through the core's test helpers.
 * Usage:  SHOPCLASS_CORE=/path/to/shopclass/ php tests/text-per-language.php
 */

if (($argv[1] ?? '') === '--hook') {
    // Child: which requests register the migration.
    define('ABS_PATH', __DIR__);
    define('OC_ADMIN', $argv[2] === 'admin');
    function osc_add_hook($name, $fn, $priority = 5)
    {
        $GLOBALS['hooks'][$name][] = $fn;
    }
    require dirname(__DIR__) . '/inc/text.php';
    echo in_array('aisle_migrate_text', $GLOBALS['hooks']['init'] ?? [], true) ? 'hooked' : 'none';
    exit(0);
}

$core = rtrim((string) (getenv('SHOPCLASS_CORE') ?: getenv('OSC_CORE') ?: dirname(__DIR__, 2) . '/shopclass'), '/') . '/';
require_once $core . 'tests/lib/scratchdb.php';
require_once $core . 'tests/lib/harness.php';

$admin = scratchdb_session('aisle_text_per_language');
define('OC_ADMIN', true);
if (!defined('OSC_CACHE_TTL')) {
    define('OSC_CACHE_TTL', 60);
}
foreach (['osc_base_url' => 'return "http://localhost/";', 'osc_plugins_path' => 'return ABS_PATH . "oc-content/plugins/";', 'osc_base_path' => 'return ABS_PATH;', 'osc_current_web_theme' => 'return "aisle";', 'osc_logged_admin_id' => 'return 0;', 'osc_is_admin_user_logged_in' => 'return false;'] as $fn => $body) {
    if (!function_exists($fn)) {
        eval('function ' . $fn . '(...$args) { ' . $body . ' }');
    }
}
foreach (['__' => 'return $text;', '_m' => 'return $text;'] as $fn => $body) {
    if (!function_exists($fn)) {
        eval('function ' . $fn . '($text, $domain = "") { ' . $body . ' }');
    }
}
foreach (['hPlugins', 'hPreference', 'hCache', 'hLocale', 'hSanitize', 'hTheme', 'hWidgets', 'hSettings'] as $helper) {
    if (is_file(ABS_PATH . "oc-includes/osclass/helpers/$helper.php")) {
        require_once ABS_PATH . "oc-includes/osclass/helpers/$helper.php";
    }
}

/** Declare one top-level function of a theme file, so the theme's own code runs without loading the whole theme. */
function aisle_test_load_function(string $file, string $name): void
{
    $tokens = token_get_all((string) file_get_contents($file));
    foreach ($tokens as $i => $token) {
        if (!is_array($token) || $token[0] !== T_FUNCTION) {
            continue;
        }
        $j = $i + 1;
        while (is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) {
            $j++;
        }
        if (!is_array($tokens[$j]) || $tokens[$j][1] !== $name) {
            continue;
        }
        $code  = '';
        $depth = 0;
        for ($k = $i; $k < count($tokens); $k++) {
            $text  = is_array($tokens[$k]) ? $tokens[$k][1] : $tokens[$k];
            $code .= $text;
            if ($text === '{' || (is_array($tokens[$k]) && in_array($tokens[$k][0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($text === '}' && --$depth === 0) {
                eval($code);

                return;
            }
        }
    }
    throw new RuntimeException("$name not found in $file");
}

$root = dirname(__DIR__);
define('AISLE_DIR', $root);
const AISLE_BRANDS = ['raspberry', 'ocean', 'forest', 'marigold', 'plum'];
require_once $root . '/inc/text.php';
require_once $root . '/sections.php';
aisle_test_load_function($root . '/functions.php', 'aisle_register_settings');

if ((int) $admin->query('SELECT COUNT(*) FROM ' . DB_TABLE_PREFIX . "t_locale WHERE pk_c_code = 'en_US'")->fetch_row()[0] === 0) {
    seed_locale($admin);
}
seed_locale($admin, 'hi_IN', 'Hindi');
Preference::getInstance()->replace('language', 'en_US', 'osclass', 'STRING');
osc_settings_locales(true);
aisle_register_settings();

$stored = static function (string $key) use ($admin): ?string {
    $row = $admin->query('SELECT s_value FROM ' . DB_TABLE_PREFIX . "t_preference WHERE s_section = 'aisle' AND s_name = '" . $admin->real_escape_string($key) . "'")->fetch_row();

    return $row === null ? null : (string) $row[0];
};
$as = static function (string $locale, callable $read) {
    $_COOKIE['oc_userLocale'] = $locale;
    $value = $read();
    unset($_COOKIE['oc_userLocale']);

    return $value;
};

harness_section('saved through core\'s settings page');

$_POST = [
    'scheme' => 'system', 'brand' => 'raspberry', 'font' => 'bricolage-figtree', 'logo_height' => '32',
    'announcementen_US' => 'Free delivery', 'announcementhi_IN' => 'Muft delivery',
    'footer_noteen_US' => 'Made in Pune', 'footer_notehi_IN' => '',
];
$_GET = [];
Params::init();
$saved = osc_settings_save('aisle');
pin('the save is accepted', [], $saved['errors']);
pin('each language is its own preference', ['Free delivery', 'Muft delivery', 'Made in Pune'], [$stored('announcementen_US'), $stored('announcementhi_IN'), $stored('footer_noteen_US')]);
osc_reset_preferences();

pin('a Hindi visitor reads the Hindi text', 'Muft delivery', $as('hi_IN', static fn () => aisle_text_setting('announcement')));
pin('an English visitor reads the English text', 'Free delivery', $as('en_US', static fn () => aisle_text_setting('announcement')));
pin('a language left empty falls back to the site language', 'Made in Pune', $as('hi_IN', static fn () => aisle_text_setting('footer_note', 'x')));

harness_section('the old single value');

foreach (['announcement', 'footer_note'] as $name) {
    foreach (['en_US', 'hi_IN'] as $code) {
        osc_set_preference($name . $code, '', 'aisle');
    }
}
Preference::getInstance()->replace('announcement', 'Old line', 'aisle', 'STRING');
Preference::getInstance()->replace('announcementhi_IN', 'Hindi line', 'aisle', 'STRING');
osc_reset_preferences();
pin('before the move a visitor still reads it', 'Old line', $as('en_US', static fn () => aisle_text_setting('announcement')));
aisle_migrate_text();
pin('the move copies it to each empty language, keeps a set one and drops it', ['Old line', 'Hindi line', null], [$stored('announcementen_US'), $stored('announcementhi_IN'), $stored('announcement')]);
pin('...and the settings page reads it per language', ['en_US' => 'Old line', 'hi_IN' => 'Hindi line'], osc_settings_values('aisle')['announcement'] ?? null);
$run = static fn (string $where): string => (string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --hook ' . $where);
pin('the move is hooked on admin requests', 'hooked', $run('admin'));
pin('...and not on the front end', 'none', $run('front'));

harness_section('the customiser');

$rf = function_exists('osc_customiser_draft_value') ? new ReflectionFunction('osc_customiser_draft_value') : null;
if ($rf === null || $rf->getNumberOfParameters() < 3) {
    echo "skipped: this core's customiser has no translated fields\n";
} else {
    aisle_customiser_support();
    $fields = osc_customiser_spec()['settings']['fields'] ?? [];
    check('the customiser edits the announcement and footer note', in_array('announcement', $fields, true) && in_array('footer_note', $fields, true));
    $GLOBALS['osc_customiser_preview'] = ['theme' => 'aisle', 'locations' => [], 'settings' => ['announcement' => ['hi_IN' => 'Draft line']], 'base' => [], 'page_text' => [], 'updated_at' => 1];
    pin('the preview shows the drafted language', 'Draft line', $as('hi_IN', static fn () => aisle_text_setting('announcement')));
    pin('...and the stored text for a language not drafted', 'Old line', $as('en_US', static fn () => aisle_text_setting('announcement')));
    unset($GLOBALS['osc_customiser_preview']);
}

exit(harness_result());
