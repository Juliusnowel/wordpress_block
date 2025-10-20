<?php
/**
 * Plugin Name: WMG Force H1
 * Description: Ensure every singular post/page (all CPTs) has exactly one H1 at render time.
 */
if ( ! defined('ABSPATH') ) exit;

$GLOBALS['wmg_h1_seen']     = false;
$GLOBALS['wmg_h1_promoted'] = false;

// Reset per request.
add_action('template_redirect', function () {
    if (is_singular() && !is_admin()) {
        $GLOBALS['wmg_h1_seen']     = false;
        $GLOBALS['wmg_h1_promoted'] = false;
    }
});

// Quick “am I loaded?” marker.
add_action('wp_head', function () {
    if (is_singular() && !is_admin()) {
        echo "\n<!-- wmg_force_h1 loaded; type=" . esc_html(get_post_type()) . " -->\n";
    }
});

// Block-level pass.
add_filter('render_block', function ($content, $block) {
    if (!is_singular() || is_admin()) return $content;

    // If this is the Post Title block, force it to render as <h1>.
    if (($block['blockName'] ?? '') === 'core/post-title') {
        $content = preg_replace('~<h[2-6]\b~i', '<h1', $content);
        $content = preg_replace('~</h[2-6]>~i', '</h1>', $content);
        $GLOBALS['wmg_h1_seen'] = true;
        return $content;
    }

    // If this block already contains an H1, mark and move on.
    if (stripos($content, '<h1') !== false) {
        $GLOBALS['wmg_h1_seen'] = true;
        return $content;
    }

    // If no H1 yet, promote the first H2 we encounter.
    if (!$GLOBALS['wmg_h1_seen'] && !$GLOBALS['wmg_h1_promoted'] && preg_match('~<h2\b~i', $content)) {
        $content = preg_replace('~<h2\b~i', '<h1', $content, 1);
        $content = preg_replace('~</h2>~i', '</h1>', $content, 1);
        $GLOBALS['wmg_h1_seen'] = true;
        $GLOBALS['wmg_h1_promoted'] = true;
        return $content;
    }

    return $content;
}, 9999, 2);

// Final safety on full HTML of the post content.
add_filter('the_content', function ($content) {
    if (!is_singular() || is_admin()) return $content;
    if (stripos($content, '<h1') !== false) return $content;

    $content = preg_replace('~<h2\b~i', '<h1', $content, 1);
    $content = preg_replace('~</h2>~i', '</h1>', $content, 1);
    return $content;
}, 9999);
