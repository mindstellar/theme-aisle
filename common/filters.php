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

/*
 * The filter form. Expects $aisle_state, $aisle_items (the products on the page, for swatch photos), $aisle_fields (searchable custom fields of the category)
 * and $aisle_clear_url. It is a plain GET form, so it works without scripts; the script submits
 * it on change and moves it into the phone sheet.
 */
$aisle_action = aisle_catalogue_url($aisle_state);
$aisle_base   = aisle_catalogue_url(array_merge($aisle_state, ['pmin' => null, 'pmax' => null, 'stock' => false, 'sale' => false, 'meta' => [], 'opt' => [], 'sort' => '', 'q' => '']));
$aisle_parts  = explode('?', $aisle_base, 2);
$aisle_hidden = [];
if (isset($aisle_parts[1])) {
    parse_str($aisle_parts[1], $aisle_hidden);
}
if ($aisle_state['q'] !== '') {
    $aisle_hidden['sPattern'] = $aisle_state['q'];
}
if ($aisle_state['sort'] !== '') {
    $aisle_hidden['sOrder'] = $aisle_state['sort'] === 'new' ? 'dt_pub_date' : 'i_price';
    $aisle_hidden['iOrderType'] = $aisle_state['sort'] === 'price_asc' ? 'asc' : 'desc';
}
?>
<form class="filters-form" id="aisle-filters" method="get" action="<?php echo osc_esc_html($aisle_parts[0]); ?>" data-filters>
    <?php foreach ($aisle_hidden as $aisle_k => $aisle_v) { ?>
        <input type="hidden" name="<?php echo osc_esc_html((string) $aisle_k); ?>" value="<?php echo osc_esc_html((string) $aisle_v); ?>">
    <?php } ?>
    <div class="fgroup">
        <h3><?php _e('Price', 'aisle'); ?></h3>
        <div class="range">
            <label><?php _e('Min', 'aisle'); ?><input class="num" type="number" min="0" step="1" inputmode="numeric" name="sPriceMin" value="<?php echo $aisle_state['pmin'] !== null ? (int) $aisle_state['pmin'] : ''; ?>"></label>
            <label><?php _e('Max', 'aisle'); ?><input class="num" type="number" min="0" step="1" inputmode="numeric" name="sPriceMax" value="<?php echo $aisle_state['pmax'] !== null ? (int) $aisle_state['pmax'] : ''; ?>"></label>
        </div>
    </div>
    <div class="fgroup">
        <h3><?php _e('Availability', 'aisle'); ?></h3>
        <label class="check"><input type="checkbox" name="stock" value="1"<?php echo $aisle_state['stock'] ? ' checked' : ''; ?>><?php _e('In stock', 'aisle'); ?></label>
        <label class="check"><input type="checkbox" name="sale" value="1"<?php echo $aisle_state['sale'] ? ' checked' : ''; ?>><?php _e('On sale', 'aisle'); ?></label>
    </div>
    <?php $aisle_have = aisle_items_options($aisle_items ?? []);
    foreach (aisle_filter_options() as $aisle_oname => $aisle_ovals) {
        $aisle_ocur = $aisle_state['opt'][$aisle_oname] ?? [];
        if (!isset($aisle_have[$aisle_oname]) && $aisle_ocur === []) {
            continue;
        }
        $aisle_oimg = aisle_is_colour($aisle_oname) ? aisle_swatch_photos($aisle_items ?? [], $aisle_oname) : []; ?>
        <fieldset class="fgroup" data-option-filter="<?php echo osc_esc_html($aisle_oname); ?>">
            <legend><?php echo osc_esc_html($aisle_oname); ?></legend>
            <?php if (aisle_is_colour($aisle_oname)) { ?>
                <div class="swatches">
                    <?php foreach ($aisle_ovals as $aisle_oval) {
                        $aisle_on = in_array($aisle_oval, $aisle_ocur, true); ?>
                        <label class="sw" title="<?php echo osc_esc_html($aisle_oval); ?>">
                            <input type="checkbox" name="opt[<?php echo osc_esc_html($aisle_oname); ?>][]" value="<?php echo osc_esc_html($aisle_oval); ?>"<?php echo $aisle_on ? ' checked' : ''; ?>>
                            <?php if (($aisle_dot = aisle_colour_css($aisle_oval)) !== null) { ?>
                                <i class="dot" aria-hidden="true" style="background:<?php echo osc_esc_html($aisle_dot); ?>"></i>
                            <?php } elseif (isset($aisle_oimg[$aisle_oval])) { ?>
                                <img src="<?php echo osc_esc_html($aisle_oimg[$aisle_oval]); ?>" alt="" loading="lazy" decoding="async">
                            <?php } else { ?>
                                <span aria-hidden="true"><?php echo osc_esc_html(mb_strtoupper(mb_substr($aisle_oval, 0, 1))); ?></span>
                            <?php } ?>
                            <span class="sr"><?php echo osc_esc_html($aisle_oval); ?></span>
                        </label>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <?php foreach ($aisle_ovals as $aisle_oval) { ?>
                    <label class="check"><input type="checkbox" name="opt[<?php echo osc_esc_html($aisle_oname); ?>][]" value="<?php echo osc_esc_html($aisle_oval); ?>"<?php echo in_array($aisle_oval, $aisle_ocur, true) ? ' checked' : ''; ?>><?php echo osc_esc_html($aisle_oval); ?></label>
                <?php } ?>
            <?php } ?>
        </fieldset>
    <?php } ?>
    <?php foreach ($aisle_fields as $aisle_field) {
        $aisle_fid = (int) $aisle_field['id'];
        $aisle_cur = $aisle_state['meta'][$aisle_fid] ?? [];
        $aisle_opts = array_values(array_filter((array) ($aisle_field['options'] ?? []), 'is_string')); ?>
        <div class="fgroup">
            <h3><?php echo osc_esc_html((string) $aisle_field['name']); ?></h3>
            <?php if ($aisle_opts !== []) { ?>
                <?php foreach ($aisle_opts as $aisle_opt) { ?>
                    <label class="check"><input type="checkbox" name="meta[<?php echo $aisle_fid; ?>][]" value="<?php echo osc_esc_html($aisle_opt); ?>"<?php echo in_array($aisle_opt, $aisle_cur, true) ? ' checked' : ''; ?>><?php echo osc_esc_html($aisle_opt); ?></label>
                <?php } ?>
            <?php } elseif (($aisle_field['type'] ?? '') === 'checkbox') { ?>
                <label class="check"><input type="checkbox" name="meta[<?php echo $aisle_fid; ?>][]" value="1"<?php echo $aisle_cur === ['1'] ? ' checked' : ''; ?>><?php echo osc_esc_html((string) $aisle_field['name']); ?></label>
            <?php } else { ?>
                <input class="field" type="text" maxlength="100" name="meta[<?php echo $aisle_fid; ?>][]" value="<?php echo osc_esc_html($aisle_cur[0] ?? ''); ?>" aria-label="<?php echo osc_esc_html((string) $aisle_field['name']); ?>">
            <?php } ?>
        </div>
    <?php } ?>
    <div class="fgroup fapply" data-apply>
        <button class="btn btn-ink btn-block" type="submit"><?php _e('Apply filters', 'aisle'); ?></button>
        <?php if (aisle_filter_count($aisle_state) > 0) { ?>
            <a class="fclear" href="<?php echo osc_esc_html($aisle_clear_url); ?>"><?php _e('Clear all', 'aisle'); ?></a>
        <?php } ?>
    </div>
</form>
