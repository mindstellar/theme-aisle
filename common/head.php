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

if (!function_exists('aisle_inline_styles')) {
    /**
     * Swaps the theme's own stylesheet links, and core's ui-common.css, for inline <style> blocks in
     * the same order, so the first paint waits on no CSS request. Other links are left alone.
     * Comments are dropped; gzip takes care of the white space.
     */
    function aisle_inline_styles(string $html): string
    {
        // A child theme's own files first, then Aisle's; url("../ points back at the folder the file came from.
        $active  = osc_current_web_theme_url('');
        $roots   = [[$active . 'css/', WebThemes::getInstance()->getCurrentThemePath() . 'css/', $active]];
        if ($active !== AISLE_URL) {
            $roots[] = [AISLE_URL . 'css/', AISLE_DIR . '/css/', AISLE_URL];
        }
        $roots[] = [osc_assets_url('osclass/'), ABS_PATH . 'oc-includes/assets/osclass/', $active];
        $pattern = '#<link href="([^"?]+\.css)(?:\?[^"]*)?" rel="stylesheet" type="text/css" />\R?#';
        $inline  = static function (string $href) use ($roots): ?string {
            foreach ($roots as [$base, $dir, $theme]) {
                if (strpos($href, $base) === 0) {
                    $name = substr($href, strlen($base));
                    if (strpos($name, '..') !== false || !is_file($dir . $name)) {
                        return null;
                    }
                    $css = str_replace('url("../', 'url("' . $theme, (string) file_get_contents($dir . $name));

                    return '<style>' . trim((string) preg_replace('#/\*.*?\*/#s', '', $css)) . '</style>' . PHP_EOL;
                }
            }

            return null;
        };
        $out = preg_replace_callback($pattern, static fn (array $m): string => $inline($m[1]) ?? $m[0], $html);

        return $out ?? $html;
    }
}

// Charset, viewport, feed link, API address and page token, then the queued styles and scripts.
ob_start();
osc_head();
echo aisle_inline_styles((string) ob_get_clean());

// Who is signed in, for the wishlist script. The id only; no cookie reads in JS.
echo '<meta name="aisle-user" content="' . ((int) osc_logged_user_id() > 0 ? (int) osc_logged_user_id() : '') . '">' . PHP_EOL;

// Title, description, canonical and social tags come from head-seo.php; until it exists, core's own.
if (file_exists(AISLE_DIR . '/common/head-seo.php')) {
    osc_current_web_theme_path('common/head-seo.php');
} else {
    echo '<title>' . osc_esc_html((string) meta_title()) . '</title>' . PHP_EOL;
    $aisle_desc = (string) meta_description();
    if ($aisle_desc !== '') {
        echo '<meta name="description" content="' . osc_esc_html($aisle_desc) . '">' . PHP_EOL;
    }
    $aisle_canonical = (string) osc_get_canonical();
    if ($aisle_canonical !== '') {
        echo '<link rel="canonical" href="' . osc_esc_html($aisle_canonical) . '">' . PHP_EOL;
    }
}
?>
<link rel="manifest" href="<?php echo osc_esc_html(osc_current_web_theme_url('manifest.webmanifest')); ?>">
<?php
$aisle_swatches = aisle_brand_swatches();
$aisle_theme_color = $aisle_swatches[aisle_brand() !== '' ? aisle_brand() : 'raspberry'] ?? '';
if ($aisle_theme_color !== '') { ?>
<meta name="theme-color" content="<?php echo osc_esc_html($aisle_theme_color); ?>">
<?php } ?>
<link rel="apple-touch-icon" href="<?php echo osc_esc_html(osc_current_web_theme_url('img/icon-192.png')); ?>">
<script type="application/json" id="aisle-page"><?php echo json_encode(aisle_page_data(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR); ?></script>
<?php foreach (aisle_font_files() as $aisle_font_file) { ?>
<link rel="preload" href="<?php echo osc_esc_html(osc_current_web_theme_url($aisle_font_file)); ?>" as="font" type="font/woff2" crossorigin>
<?php } ?>
<?php if (!aisle_is_preview()) { ?>
<script>
/* Applies the visitor's saved colour scheme before first paint. Same code as js/aisle-scheme.js. */
(function () {
  try {
    var v = localStorage.getItem('aisle-scheme');
    if (v === 'light' || v === 'dark') {
      document.documentElement.setAttribute('data-theme', v);
    } else if (v === 'system') {
      document.documentElement.removeAttribute('data-theme');
    }
  } catch (e) {}
})();
</script>
<?php } ?>
