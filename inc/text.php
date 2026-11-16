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
const AISLE_TEXT_KEYS = ['announcement', 'footer_note'];

/**
 * A per-language text: the visitor's language, then the site default, then the old single value, then $default.
 */
function aisle_text_setting(string $name, string $default = ''): string
{
    foreach ([osc_current_user_locale(), osc_language()] as $code) {
        $value = trim((string) osc_get_preference($name . $code, 'aisle'));
        if ($value !== '') {
            return $value;
        }
    }
    $old = trim((string) osc_get_preference($name, 'aisle'));

    return $old !== '' ? $old : $default;
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

osc_add_hook('init', 'aisle_migrate_text', 1);
