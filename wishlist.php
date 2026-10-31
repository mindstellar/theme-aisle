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

// Rendered by core's custom route. Signed in, the list comes from the account; otherwise from
// ?wish=1,2,3 (the script asks for it with the ids saved in the browser).
$aisle_wish_raw = Params::getParamString('wish');
$aisle_ids      = [];
if (is_string($aisle_wish_raw) && $aisle_wish_raw !== '') {
    foreach (explode(',', $aisle_wish_raw) as $aisle_part) {
        if (preg_match('/^\d{1,10}$/', $aisle_part) === 1 && (int) $aisle_part > 0) {
            $aisle_ids[(int) $aisle_part] = (int) $aisle_part;
        }
    }
    $aisle_ids = array_slice(array_values($aisle_ids), 0, 50);
} elseif (osc_is_web_user_logged_in()) {
    $aisle_body = aisle_api('account/wishlist');
    foreach ((array) ($aisle_body['data']['ids'] ?? []) as $aisle_part) {
        if (is_numeric($aisle_part) && (int) $aisle_part > 0) {
            $aisle_ids[(int) $aisle_part] = (int) $aisle_part;
        }
    }
    $aisle_ids = array_values($aisle_ids);
}

$aisle_items = [];
foreach (array_chunk($aisle_ids, 50) as $aisle_chunk) {
    $aisle_res = aisle_api('listings', ['ids' => implode(',', $aisle_chunk), 'include' => 'rating', 'limit' => 50, 'fields' => AISLE_CARD_FIELDS]);
    foreach ((array) ($aisle_res['data'] ?? []) as $aisle_row) {
        if (is_array($aisle_row) && isset($aisle_row['id'])) {
            $aisle_items[(int) $aisle_row['id']] = $aisle_row;
        }
    }
}
$aisle_shown = [];
foreach ($aisle_ids as $aisle_id) {
    if (isset($aisle_items[$aisle_id])) {
        $aisle_shown[] = $aisle_items[$aisle_id];
    }
}
$aisle_in_account = osc_is_web_user_logged_in();
if ($aisle_in_account) {
    require_once AISLE_DIR . '/cart/order-card.php';
    ?>
<link rel="stylesheet" href="<?php echo osc_esc_html(aisle_asset('css/account.css')); ?>">
<div class="wrap">
    <div class="acct">
        <?php echo aisle_account_nav('wishlist'); ?>
<?php } ?>
<div class="<?php echo $aisle_in_account ? '' : 'wrap '; ?>wishlist" data-wishlist-page data-url="<?php echo osc_esc_html(aisle_wishlist_url()); ?>" data-ids="<?php echo osc_esc_html(implode(',', array_map(static fn ($r): int => (int) $r['id'], $aisle_shown))); ?>">
    <h1><?php _e('Wishlist', 'aisle'); ?></h1>
    <div class="grid" data-wishlist-grid>
        <?php foreach ($aisle_shown as $aisle_row) {
            echo aisle_card($aisle_row);
        } ?>
    </div>
    <?php echo $aisle_shown === [] ? aisle_api_error() : ''; ?>
    <p class="wish-empty" data-wishlist-empty<?php echo $aisle_shown !== [] || aisle_api_failed() ? ' hidden' : ''; ?>><?php _e('Nothing saved yet.', 'aisle'); ?> <a href="<?php echo osc_esc_html(osc_base_url()); ?>"><?php _e('Keep browsing', 'aisle'); ?></a></p>
</div>
<?php if ($aisle_in_account) { ?>
    </div>
</div>
<?php } ?>
