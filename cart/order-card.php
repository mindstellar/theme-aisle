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

// The order card used by the orders list and the single order page. Both read orders from the API.

if (!function_exists('aisle_order_card')) {
    /**
     * What the cards need beyond the orders: each listing's variants (for Buy again) and the
     * files the signed-in buyer may download. One listings request however many orders there are.
     *
     * @param array<int,array<string,mixed>> $orders orders as the API sends them
     *
     * @return array{variants:array<int,array<string,int>>,downloads:array<int,array<int,array{name:string,url:string}>>}
     */
    function aisle_order_extras(array $orders): array
    {
        $ids = [];
        foreach ($orders as $order) {
            foreach ((array) ($order['lines'] ?? []) as $line) {
                $id = (int) ($line['listing']['id'] ?? 0);
                if (($line['kind'] ?? '') === 'item' && $id > 0) {
                    $ids[$id] = $id;
                }
            }
        }
        $out = ['variants' => [], 'downloads' => []];
        foreach (array_chunk(array_values($ids), 50) as $chunk) {
            $body = aisle_api('listings', ['ids' => implode(',', $chunk), 'fields' => 'id,product,ext', 'limit' => 50]);
            foreach ((array) ($body['data'] ?? []) as $row) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                foreach ((array) ($row['product']['variants'] ?? []) as $variant) {
                    $sku = (string) ($variant['sku'] ?? '');
                    if ($sku !== '' && (int) ($variant['id'] ?? 0) > 0) {
                        $out['variants'][$id][$sku] = (int) $variant['id'];
                    }
                }
                $files = [];
                foreach ((array) ($row['ext']['digital-goods']['downloads'] ?? []) as $file) {
                    $url = (string) ($file['url'] ?? '');
                    if ($url !== '' && preg_match('#^https?://#i', $url) === 1) {
                        $files[] = ['name' => (string) ($file['name'] ?? ''), 'url' => $url];
                    }
                }
                if ($files !== []) {
                    $out['downloads'][$id] = $files;
                }
            }
        }

        return $out;
    }

    /**
     * A date from the API, in the shop's format. $short leaves the year out.
     */
    function aisle_order_date(?string $iso, bool $short = false): string
    {
        if ($iso === null || $iso === '') {
            return '';
        }

        return (string) osc_format_date($iso, $short ? 'j M' : 'j M Y');
    }

    /**
     * One order as a card.
     *
     * @param array<string,mixed> $order  an API order
     * @param array<string,mixed> $extras from aisle_order_extras()
     * @param array<string,mixed> $opts   `link` (bool) makes the number a link to the order page
     */
    function aisle_order_card(array $order, array $extras, array $opts = []): string
    {
        $id        = (int) ($order['id'] ?? 0);
        $status    = (string) ($order['status'] ?? 'pending');
        $goods     = ($order['kind'] ?? 'goods') === 'goods';
        $ship      = is_array($order['shipping'] ?? null) ? $order['shipping'] : [];
        $total     = is_array($order['total'] ?? null) ? (string) ($order['total']['formatted'] ?? '') : '';
        $lines     = array_values(array_filter((array) ($order['lines'] ?? []), 'is_array'));
        $items     = array_values(array_filter($lines, static fn (array $l): bool => ($l['kind'] ?? '') === 'item'));
        $extra     = array_values(array_filter($lines, static fn (array $l): bool => ($l['kind'] ?? '') !== 'item' && empty($l['included'])));
        $included  = array_values(array_filter($lines, static fn (array $l): bool => ($l['kind'] ?? '') === 'tax' && !empty($l['included'])));
        $gateway   = is_array($order['gateway'] ?? null) ? (string) ($order['gateway']['id'] ?? '') : '';
        $paid      = in_array($status, ['paid', 'shipped', 'delivered'], true) && !($gateway === 'cod' && empty($order['paid_at']));
        $delivered = aisle_order_date($ship['delivered_at'] ?? null, true);

        switch ($status) {
            case 'shipped':
                $pill = ['ship', 'truck', __('On the way', 'aisle')];
                break;
            case 'delivered':
                $pill = ['done', 'check', $delivered !== '' ? sprintf(__('Delivered %s', 'aisle'), $delivered) : __('Delivered', 'aisle')];
                break;
            case 'paid':
                $pill = ['ship', 'box', __('Preparing', 'aisle')];
                break;
            case 'cancelled':
                $pill = ['off', 'x', __('Cancelled', 'aisle')];
                break;
            case 'failed':
                $pill = ['bad', 'x', __('Payment failed', 'aisle')];
                break;
            case 'refunded':
                $pill = ['off', 'return', __('Refunded', 'aisle')];
                break;
            default:
                $pill = ['wait', 'cash', $gateway === 'cod' ? __('Pay on delivery', 'aisle') : __('Awaiting payment', 'aisle')];
        }

        // The tracker: four steps, the last one reached is the current one.
        $steps = [];
        if ($goods && in_array($status, ['pending', 'paid', 'shipped', 'delivered'], true)) {
            $paidNote = aisle_order_date($order['paid_at'] ?? null, true);
            if (!$paid) {
                $paidNote = $gateway === 'cod' ? __('On delivery', 'aisle') : __('Waiting', 'aisle');
            }
            $shipNote = aisle_order_date($ship['shipped_at'] ?? null, true);
            if ($shipNote !== '' && ($ship['carrier'] ?? '') !== '') {
                $shipNote .= ' · ' . $ship['carrier'];
            }
            $steps = [
                [true, __('Ordered', 'aisle'), aisle_order_date($order['placed_at'] ?? null, true)],
                [$paid, __('Paid', 'aisle'), $paidNote],
                [in_array($status, ['shipped', 'delivered'], true), __('Shipped', 'aisle'), $shipNote],
                [$status === 'delivered', __('Delivered', 'aisle'), $delivered],
            ];
        }
        $now = -1;
        foreach ($steps as $n => $step) {
            if ($step[0]) {
                $now = $n;
            }
        }

        $again = [];
        foreach ($items as $line) {
            $listing = (int) ($line['listing']['id'] ?? 0);
            $sku     = (string) ($line['sku'] ?? '');
            $variant = (int) ($extras['variants'][$listing][$sku] ?? 0);
            if ($variant > 0) {
                $again[] = ['v' => $variant, 'q' => max(1, (int) ($line['qty'] ?? 1))];
            }
        }
        $firstListing = '';
        foreach ($items as $line) {
            if (!empty($line['listing']['url'])) {
                $firstListing = (string) $line['listing']['url'];
                break;
            }
        }
        $help = (string) osc_contact_url();

        ob_start();
        ?>
<article class="order" id="order-<?php echo $id; ?>" data-order="<?php echo $id; ?>" data-status="<?php echo osc_esc_html($status); ?>">
    <div class="order-h">
        <dl>
            <div><dt><?php _e('Order', 'aisle'); ?></dt><dd class="num"><?php
                if (!empty($opts['link'])) {
                    ?><a href="<?php echo osc_esc_html((string) osc_route_url('aisle-order', ['id' => $id])); ?>"><?php echo osc_esc_html((string) ($order['number'] ?? '#' . $id)); ?></a><?php
                } else {
                    echo osc_esc_html((string) ($order['number'] ?? '#' . $id));
                }
            ?></dd></div>
            <div><dt><?php _e('Placed', 'aisle'); ?></dt><dd><?php echo osc_esc_html(aisle_order_date($order['placed_at'] ?? null)); ?></dd></div>
            <div><dt><?php _e('Total', 'aisle'); ?></dt><dd class="num"><?php echo osc_esc_html($total); ?></dd></div>
        </dl>
        <span class="status <?php echo osc_esc_html($pill[0]); ?>" data-status-pill><?php echo aisle_icon($pill[1], true); ?><span><?php echo osc_esc_html($pill[2]); ?></span></span>
    </div>
    <div class="order-b">
        <?php if ($steps !== []) { ?>
            <ol class="track" data-track>
                <?php foreach ($steps as $n => $step) { ?>
                    <li class="<?php echo $step[0] ? 'did' : ''; ?><?php echo $n === $now ? ' now' : ''; ?>"><b><?php echo osc_esc_html($step[1]); ?></b><span><?php echo osc_esc_html((string) $step[2]); ?></span></li>
                <?php } ?>
            </ol>
        <?php } ?>

        <div class="olines">
            <?php if ($items === []) { ?>
                <div class="oline"><span class="file"><?php echo aisle_icon('cash'); ?></span><div><b><?php echo osc_esc_html((string) ($order['label'] ?? '')); ?></b></div></div>
            <?php } ?>
            <?php foreach ($items as $line) {
                $listingId = (int) ($line['listing']['id'] ?? 0);
                $files     = $paid ? ($extras['downloads'][$listingId] ?? []) : [];
                $photo     = is_array($line['listing']['photo'] ?? null) ? $line['listing']['photo'] : null;
                $thumb     = $photo !== null ? (string) ($photo['thumbnail'] ?? $photo['preview'] ?? '') : '';
                $url       = (string) ($line['listing']['url'] ?? '');
                $opt       = [];
                foreach ((array) ($line['options'] ?? []) as $value) {
                    $opt[] = (string) $value;
                }
                $note = array_filter([implode(' · ', $opt), $files !== [] ? __('Digital download', 'aisle') : sprintf(__('Qty %d', 'aisle'), (int) ($line['qty'] ?? 1))]);
                $ext  = $files !== [] ? strtoupper(substr((string) pathinfo($files[0]['name'], PATHINFO_EXTENSION), 0, 4)) : '';
                ?>
                <div class="oline">
                    <?php if ($files !== []) { ?>
                        <span class="file"><?php echo osc_esc_html($ext !== '' ? $ext : __('File', 'aisle')); ?></span>
                    <?php } elseif ($thumb !== '') { ?>
                        <img src="<?php echo osc_esc_html($thumb); ?>" alt="" width="64" height="64" loading="lazy">
                    <?php } else { ?>
                        <span class="file"><?php echo aisle_icon('box'); ?></span>
                    <?php } ?>
                    <div>
                        <?php if ($url !== '') { ?>
                            <a href="<?php echo osc_esc_html($url); ?>"><b><?php echo osc_esc_html((string) ($line['title'] ?? '')); ?></b></a>
                        <?php } else { ?>
                            <b><?php echo osc_esc_html((string) ($line['title'] ?? '')); ?></b>
                        <?php } ?>
                        <small><?php echo osc_esc_html(implode(' · ', $note)); ?></small>
                    </div>
                    <?php if ($files !== []) { ?>
                        <span class="dl">
                            <?php foreach ($files as $file) { ?>
                                <a class="btn btn-ghost sm" href="<?php echo osc_esc_html($file['url']); ?>" download><?php echo aisle_icon('download', true); ?><span><?php echo osc_esc_html(count($files) > 1 ? $file['name'] : __('Download', 'aisle')); ?></span></a>
                            <?php } ?>
                        </span>
                    <?php } else { ?>
                        <span class="num"><?php echo osc_esc_html((string) ($line['amount']['formatted'] ?? '')); ?></span>
                    <?php } ?>
                </div>
            <?php } ?>
        </div>

        <?php if ($extra !== []) { ?>
            <dl class="osum">
                <?php foreach ($extra as $line) { ?>
                    <div><dt><?php echo osc_esc_html((string) ($line['title'] ?? '')); ?></dt><dd class="num"><?php echo osc_esc_html((string) ($line['amount']['formatted'] ?? '')); ?></dd></div>
                <?php } ?>
            </dl>
        <?php } ?>
        <?php if ($included !== []) { ?>
            <p class="otax">
                <?php foreach ($included as $line) { ?>
                    <span><?php echo osc_esc_html(sprintf(__('Includes %1$s %2$s', 'aisle'), (string) ($line['title'] ?? ''), (string) ($line['unit']['formatted'] ?? ''))); ?></span>
                <?php } ?>
            </p>
        <?php } ?>

        <?php if ($goods && is_array($ship['address'] ?? null) && $status !== 'cancelled') {
            $a = $ship['address'];
            $addr = array_filter([(string) ($a['name'] ?? ''), (string) ($a['line1'] ?? ''), (string) ($a['line2'] ?? ''), trim(($a['city'] ?? '') . ' ' . ($a['postcode'] ?? '')), (string) ($a['state'] ?? '')]);
            ?>
            <p class="oaddr"><?php echo aisle_icon('pin', true); ?><span><?php echo osc_esc_html(implode(', ', $addr)); ?></span></p>
        <?php } ?>

        <?php $payInfo = trim((string) ($order['pay_instructions'] ?? ''));
        if ($payInfo !== '' && $status === 'pending') { ?>
            <div class="order-howto">
                <b><?php _e('How to pay', 'aisle'); ?></b>
                <p><?php echo osc_esc_html($payInfo); ?></p>
            </div>
        <?php } ?>

        <div class="order-a">
            <?php if (!empty($order['can_pay'])) { ?>
                <button class="btn btn-primary" type="button" data-pay-order="<?php echo $id; ?>"><span><?php _e('Pay now', 'aisle'); ?></span></button>
            <?php } ?>
            <?php if (!empty($ship['tracking_url'])) { ?>
                <a class="btn btn-primary" href="<?php echo osc_esc_html((string) $ship['tracking_url']); ?>" target="_blank" rel="noopener noreferrer"><?php _e('Track parcel', 'aisle'); ?></a>
            <?php } ?>
            <?php if (!empty($order['receipt_url'])) { ?>
                <a class="btn btn-ghost" href="<?php echo osc_esc_html((string) $order['receipt_url']); ?>"><?php _e('Invoice', 'aisle'); ?></a>
            <?php } ?>
            <?php if ($again !== [] && in_array($status, ['delivered', 'cancelled', 'refunded'], true)) { ?>
                <button class="btn btn-ghost" type="button" data-buy-again="<?php echo osc_esc_html((string) json_encode($again)); ?>"><?php _e('Buy again', 'aisle'); ?></button>
            <?php } ?>
            <?php if ($status === 'delivered' && $firstListing !== '') { ?>
                <a class="btn btn-ghost" href="<?php echo osc_esc_html($firstListing . '#reviews'); ?>"><?php _e('Write a review', 'aisle'); ?></a>
            <?php } ?>
            <?php if (!empty($order['can_cancel'])) { ?>
                <button class="btn btn-ghost" type="button" data-cancel-order="<?php echo $id; ?>" data-confirm="<?php echo osc_esc_html(__('Yes, cancel the order', 'aisle')); ?>"><span><?php _e('Cancel order', 'aisle'); ?></span></button>
            <?php } ?>
            <?php if ($help !== '') { ?>
                <a class="btn btn-ghost" href="<?php echo osc_esc_html($help); ?>"><?php echo $status === 'delivered' ? osc_esc_html(__('Return an item', 'aisle')) : osc_esc_html(__('Get help', 'aisle')); ?></a>
            <?php } ?>
        </div>
        <?php if (!empty($order['can_pay'])) { ?>
            <div class="order-pay" data-pay-intent hidden></div>
        <?php } ?>
    </div>
</article>
        <?php

        return (string) ob_get_clean();
    }

    /**
     * The words the orders script shows.
     *
     * @return array<string,string>
     */
    function aisle_orders_i18n(): array
    {
        return [
            'cancelled' => __('Cancelled', 'aisle'),
            'cancel_ok' => __('Order cancelled', 'aisle'),
            'cancel_no' => __('We could not cancel this order. It may have been paid already.', 'aisle'),
            'again_ok'  => __('Items added to your cart', 'aisle'),
            'again_no'  => __('Those items are no longer available', 'aisle'),
            'pay_no'    => __('We could not start the payment. Try again in a moment.', 'aisle'),
        ];
    }

    /**
     * The account nav on the theme's own account pages: core's list, drawn in Aisle's markup.
     *
     * @param string $current `orders` or `wishlist`, for pages core cannot recognise
     */
    function aisle_account_nav(string $current): string
    {
        $items = osc_account_nav_items();
        $here  = ['orders' => 'opt_billing_orders', 'wishlist' => 'opt_wishlist'][$current] ?? '';
        $html  = '<nav class="acct-nav" aria-label="' . osc_esc_html(__('Account', 'aisle')) . '">';
        foreach ($items as $item) {
            if (empty($item['name']) || empty($item['url'])) {
                continue;
            }
            $on    = !empty($item['current']) || ($here !== '' && ($item['class'] ?? '') === $here);
            $html .= '<a' . ($on ? ' class="on" aria-current="page"' : '') . ' href="' . osc_esc_html((string) $item['url']) . '">' . osc_esc_html((string) $item['name']) . '</a>';
        }

        return $html . '</nav>';
    }
}
