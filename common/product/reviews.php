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

// Needs $aisle_listing, $aisle_reviews (the first comments page body, or null) from item.php.
global $aisle_listing, $aisle_reviews;

require_once AISLE_DIR . '/common/stars.php';

$aisle_rating = is_array($aisle_listing['rating'] ?? null) ? $aisle_listing['rating'] : [];
$aisle_rcount = (int) ($aisle_rating['count'] ?? 0);
$aisle_ravg   = isset($aisle_rating['average']) && is_numeric($aisle_rating['average']) ? (float) $aisle_rating['average'] : null;
$aisle_dist   = is_array($aisle_rating['distribution'] ?? null) ? $aisle_rating['distribution'] : [];
$aisle_rlist  = is_array($aisle_reviews['data'] ?? null) ? array_values(array_filter($aisle_reviews['data'], 'is_array')) : [];
$aisle_rnext  = is_string($aisle_reviews['links']['next'] ?? null) ? $aisle_reviews['links']['next'] : '';
$aisle_rid    = (int) ($aisle_listing['id'] ?? 0);
$aisle_rin    = osc_is_web_user_logged_in();
$aisle_stars  = aisle_ratings_on();
$aisle_ri18n  = [
    'more'     => __('Show more', 'aisle'),
    'failed'   => __('Could not send your review, try again', 'aisle'),
    'pending'  => __('Thanks, it shows once it is approved', 'aisle'),
    'posted'   => __('Thanks for your review', 'aisle'),
    'already'  => __('You already reviewed this', 'aisle'),
    'pick'     => $aisle_stars ? __('Choose a star rating or write a review', 'aisle') : __('Write your review first', 'aisle'),
    'anon'     => __('A customer', 'aisle'),
    'rated'    => __('Rated %s out of 5', 'aisle'),
];

if (!function_exists('aisle_review_item')) {
    /**
     * One review card: stars, title, text, "Name · date".
     *
     * @param array<string,mixed> $c a comment from the API
     */
    function aisle_review_item(array $c): string
    {
        $rating = isset($c['rating']) && is_numeric($c['rating']) && aisle_ratings_on() ? (int) $c['rating'] : 0;
        $name   = trim((string) ($c['author']['name'] ?? ''));
        $when   = strtotime((string) ($c['published_at'] ?? ''));
        $title  = trim((string) ($c['title'] ?? ''));
        $body   = trim((string) ($c['body'] ?? ''));

        if ($rating < 1 && $title === '' && $body === '') {
            return '';
        }
        $head = '';
        if ($rating >= 1) {
            $head .= aisle_stars_row($rating, (string) $rating);
        }
        if ($title !== '') {
            $head .= '<b>' . osc_esc_html($title) . '</b>';
        }

        $by = osc_esc_html($name !== '' ? $name : __('A customer', 'aisle'));
        if ($when !== false) {
            $by .= ' · <time datetime="' . osc_esc_html(gmdate('c', $when)) . '">' . osc_esc_html(gmdate('j M Y', $when)) . '</time>';
        }

        return '<article class="rvitem">'
            . ($head !== '' ? '<header>' . $head . '</header>' : '')
            . ($body !== '' ? '<p>' . nl2br(osc_esc_html($body), false) . '</p>' : '')
            . '<footer>' . $by . '</footer></article>';
    }
}
?>
<section class="sec" id="reviews" data-reviews data-stars="<?php echo $aisle_stars ? '1' : '0'; ?>" data-listing="<?php echo $aisle_rid; ?>" data-i18n="<?php echo osc_esc_html((string) json_encode($aisle_ri18n, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE)); ?>">
    <div class="sec-head">
        <h2><?php _e('Reviews', 'aisle'); ?></h2>
        <a href="#write-review"><?php $aisle_stars ? _e('Rate this product', 'aisle') : _e('Write a review', 'aisle'); ?></a>
    </div>
    <div class="rv<?php echo $aisle_stars ? '' : ' rv--plain'; ?>">
        <?php if ($aisle_stars) { ?>
        <div class="rv-sum">
            <?php if ($aisle_rcount > 0 && $aisle_ravg !== null) { ?>
                <div class="rv-score">
                    <span class="score num"><?php echo osc_esc_html(number_format($aisle_ravg, 1, '.', '')); ?></span>
                    <div>
                        <?php echo aisle_stars_row((int) round($aisle_ravg), number_format($aisle_ravg, 1, '.', '')); ?>
                        <p class="num"><?php echo osc_esc_html(sprintf(_n('%d review', '%d reviews', $aisle_rcount, 'aisle'), $aisle_rcount)); ?></p>
                    </div>
                </div>
                <div class="bars num">
                    <?php for ($aisle_n = 5; $aisle_n >= 1; $aisle_n--) { ?>
                        <?php
                        $aisle_c = (int) ($aisle_dist[(string) $aisle_n] ?? 0);
                        $aisle_w = (int) round($aisle_c / $aisle_rcount * 100);
                        ?>
                        <div><span><?php echo $aisle_n; ?> ★</span><i><b style="width:<?php echo $aisle_w; ?>%"></b></i><span><?php echo $aisle_c; ?></span></div>
                    <?php } ?>
                </div>
            <?php } else { ?>
                <p class="rv-none"><?php _e('No reviews yet', 'aisle'); ?></p>
            <?php } ?>
        </div>
        <?php } ?>
        <div>
            <?php if (!$aisle_stars && $aisle_rlist === []) { ?>
                <p class="rv-none"><?php _e('No reviews yet', 'aisle'); ?></p>
            <?php } ?>
            <div class="rvlist" data-review-list>
                <?php foreach ($aisle_rlist as $aisle_c) { ?>
                    <?php echo aisle_review_item($aisle_c); ?>
                <?php } ?>
            </div>
            <?php if ($aisle_rnext !== '') { ?>
                <button class="btn btn-ghost rv-more" type="button" data-review-more data-next="<?php echo osc_esc_html($aisle_rnext); ?>"><?php _e('Show more', 'aisle'); ?></button>
            <?php } ?>
            <div class="rvform" id="write-review">
                <?php if ($aisle_rin) { ?>
                    <form data-review-form novalidate>
                        <h3><?php _e('Write a review', 'aisle'); ?></h3>
                        <?php if ($aisle_stars) { ?>
                        <fieldset class="rate">
                            <legend><?php _e('Your rating', 'aisle'); ?></legend>
                            <?php for ($aisle_n = 5; $aisle_n >= 1; $aisle_n--) { ?>
                                <input type="radio" name="rating" id="rate-<?php echo $aisle_n; ?>" value="<?php echo $aisle_n; ?>">
                                <label for="rate-<?php echo $aisle_n; ?>" title="<?php echo osc_esc_html(sprintf(_n('%d star', '%d stars', $aisle_n, 'aisle'), $aisle_n)); ?>"><svg class="ico" aria-hidden="true"><use href="#i-star"/></svg><span class="sr"><?php echo osc_esc_html(sprintf(_n('%d star', '%d stars', $aisle_n, 'aisle'), $aisle_n)); ?></span></label>
                            <?php } ?>
                        </fieldset>
                        <?php } ?>
                        <label class="fld"><span><?php _e('Title', 'aisle'); ?></span><input type="text" name="title" maxlength="100" autocomplete="off"></label>
                        <label class="fld"><span><?php _e('Your review', 'aisle'); ?></span><textarea name="body" rows="4" maxlength="2000"></textarea></label>
                        <div class="rvform-foot">
                            <button class="btn btn-primary" type="submit"><?php _e('Send review', 'aisle'); ?></button>
                            <p class="rv-msg" data-review-msg role="status" aria-live="polite"></p>
                        </div>
                    </form>
                <?php } else { ?>
                    <p><a href="<?php echo osc_esc_html(osc_user_login_url()); ?>"><?php _e('Sign in to write a review', 'aisle'); ?></a></p>
                <?php } ?>
            </div>
        </div>
    </div>
</section>
