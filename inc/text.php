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

/** The owner-typed texts that hold one value per language, stored as the key plus the locale code. */
const AISLE_TEXT_KEYS = ['announcement', 'footer_note', 'payment_methods'];

/**
 * A per-language text: the visitor's language, then the site default, then the old single value, then $default.
 * In the customiser preview a drafted language wins over the stored one.
 */
function aisle_text_setting(string $name, string $default = ''): string
{
    $value = trim(osc_settings_text_value($name, 'aisle', osc_current_user_locale()));

    return $value !== '' ? $value : $default;
}

/**
 * Moves each old single text to every enabled language, then drops it, so nothing changes on
 * screen. A language that already has a value keeps it.
 */
function aisle_migrate_text(): void
{
    $moved = false;
    foreach (AISLE_TEXT_KEYS as $name) {
        $old = (string) osc_get_preference($name, 'aisle');
        if ($old === '') {
            continue;
        }
        foreach (array_keys(osc_settings_locales()) as $code) {
            if ((string) osc_get_preference($name . $code, 'aisle') === '') {
                osc_set_preference($name . $code, $old, 'aisle');
            }
        }
        osc_delete_preference($name, 'aisle');
        $moved = true;
    }
    if ($moved) {
        osc_reset_preferences();
    }
}

// Admin requests only: until then the front end reads the old value through aisle_text_setting().
if (defined('OC_ADMIN') && OC_ADMIN === true) {
    osc_add_hook('init', 'aisle_migrate_text', 1);
}
