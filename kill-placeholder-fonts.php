<?php
/**
 * Plugin Name: Kill Placeholder Fonts (YourFont / Your_Font)
 * Description: Removes bogus Google Fonts and preload links that use placeholders.
 * Author: you
 * Version: 1.0
 */

/** Safety: only on front-end full renders */
function kpf_is_front_render(): bool {
  if (is_admin()) return false;
  if (function_exists('wp_is_json_request') && wp_is_json_request()) return false;
  if (wp_doing_ajax()) return false;
  if (is_feed() || is_preview()) return false;
  return true;
}

/**
 * 1) Dequeue any stylesheet handle that requests a placeholder family
 *    (e.g., id="google-fonts-css" with family=Your+Font+Name).
 */
add_action('wp_enqueue_scripts', function () {
  if (!kpf_is_front_render()) return;

  global $wp_styles;
  if (empty($wp_styles) || empty($wp_styles->registered)) return;

  foreach ($wp_styles->registered as $handle => $obj) {
    $src = isset($obj->src) ? (string) $obj->src : '';
    // match either Your+Font+Name or an obvious placeholder pattern
    if (
      (strpos($src, 'fonts.googleapis.com') !== false &&
       (stripos($src, 'Your+Font+Name') !== false || stripos($src, 'Your_Font') !== false || stripos($src, 'YourFont') !== false))
    ) {
      wp_dequeue_style($handle);
      wp_deregister_style($handle);
    }
  }
}, 9999);

/**
 * 2) Output buffer scrubber: remove any stray <link rel="preload"> or other tags
 *    that reference YourFont/Your_Font (e.g., fonts.gstatic.com/s/YourFont.woff2).
 *    Also strips any @import lines for the placeholder family that slipped into inline <style>.
 */
add_action('template_redirect', function () {
  if (!kpf_is_front_render()) return;
  ob_start(function ($html) {
    // Nuke preload tags that target the placeholder
    $html = preg_replace(
      '~<link[^>]+rel=["\']preload["\'][^>]+href=["\'][^"\']*(Your[_+]Font|YourFont|Your\+Font\+Name)[^"\']*["\'][^>]*>~i',
      '',
      $html
    );

    // Nuke any <link> to Google Fonts with the placeholder family
    $html = preg_replace(
      '~<link[^>]+href=["\'][^"\']*fonts\.googleapis\.com[^"\']*(Your[_+]Font|YourFont|Your\+Font\+Name)[^"\']*["\'][^>]*>~i',
      '',
      $html
    );

    // Remove @import lines for the placeholder (inside inline <style> blocks)
    $html = preg_replace(
      '~@import\s+url\([^)"]*fonts\.googleapis\.com[^)]*(Your[_+]Font|YourFont|Your\+Font\+Name)[^)]*\)\s*;~i',
      '',
      $html
    );

    // Optional: strip bare @font-face srcs that point to YourFont.woff2
    $html = preg_replace(
      '~@font-face\s*\{[^}]*?(src:[^;}]*?(Your[_+]Font|YourFont)[^;}]*?;)[^}]*\}~is',
      '',
      $html
    );

    return $html;
  });
}, 1);

/** (Optional) Leave a tiny debug crumb in page source so you can confirm it ran. */
add_action('wp_head', function () {
  if (!kpf_is_front_render()) return;
  echo "\n<!-- kill-placeholder-fonts active -->\n";
}, 9999);
