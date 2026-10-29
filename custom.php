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

// Pages that themes and plugins add with osc_add_route(): the routed file inside the shop header and footer.
osc_current_web_theme_path('common/header.php');
$aisle_file = (string) __get('file');
if (preg_match('/^(?:cart\/)?[a-z0-9-]+\.php$/', $aisle_file) === 1 && is_file(AISLE_DIR . '/' . $aisle_file)) {
    require AISLE_DIR . '/' . $aisle_file;
} else {
    osc_render_file();
}
osc_current_web_theme_path('common/footer.php');
