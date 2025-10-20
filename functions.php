<?php
function my_child_theme_enqueue_styles() {
    wp_enqueue_style('parent-style', get_parent_theme_file_uri('style.css'));
    wp_enqueue_style('child-style', get_stylesheet_uri(), ['parent-style'], wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

/* REGISTER BLOCKS */
add_action('init', function () {
    register_block_type( get_stylesheet_directory() . '/blocks/vite-faq' );
    register_block_type( get_stylesheet_directory() . '/blocks/definition-box' );
    register_block_type( get_stylesheet_directory() . '/blocks/key-takeaways' );
});

/**
 * Force exactly one H1 on singular views (all CPTs).
 * If none exists, promote the FIRST <h2> to <h1>.
 * Non-destructive: render-time only.
 */

$GLOBALS['ai_force_h1_seen'] = false; // have we seen an <h1> yet?
$GLOBALS['ai_force_h1_done'] = false; // have we already promoted an <h2>?

// Reset per front-end singular request
add_action('template_redirect', function () {
    if (is_singular() && !is_admin()) {
        $GLOBALS['ai_force_h1_seen'] = false;
        $GLOBALS['ai_force_h1_done'] = false;
    }
});

/**
 * Watch every block as it renders. If no H1 has appeared,
 * flip the first <h2> we encounter into <h1>.
 */
add_filter('render_block', function ($content, $block) {
    if (!is_singular() || is_admin()) return $content;

    // If this block actually outputs an <h1>, mark it.
    if (stripos($content, '<h1') !== false) {
        $GLOBALS['ai_force_h1_seen'] = true;
        return $content;
    }

    // Special-case: Post Title block can be level != 1.
    if (($block['blockName'] ?? '') === 'core/post-title') {
        $level = isset($block['attrs']['level']) ? (int) $block['attrs']['level'] : 1;
        if ($level === 1) {
            $GLOBALS['ai_force_h1_seen'] = true;
            return $content;
        }
        // Not an H1 → don't mark as seen; allow promotion of first H2 elsewhere.
    }

    // If we still haven't seen an H1, promote the first H2 inside THIS block.
    if (!$GLOBALS['ai_force_h1_seen'] && !$GLOBALS['ai_force_h1_done']) {
        $new = preg_replace('~<h2\b~i', '<h1', $content, 1, $opened);
        if ($opened) {
            $new = preg_replace('~</h2>~i', '</h1>', $new, 1);
            $GLOBALS['ai_force_h1_seen'] = true;
            $GLOBALS['ai_force_h1_done'] = true;
            return $new;
        }
    }

    return $content;
}, 9999, 2);

/**
 * Final safety: very late pass on the full content.
 * If we *still* have no H1, convert the first H2.
 */
add_filter('the_content', function ($content) {
    if (!is_singular() || is_admin()) return $content;
    if (stripos($content, '<h1') !== false) return $content;

    $content = preg_replace('~<h2\b~i', '<h1', $content, 1);
    $content = preg_replace('~</h2>~i', '</h1>', $content, 1);
    return $content;
}, 9999);

/** Debug comment in page source (remove later). */
add_action('wp_head', function () {
    if (!is_singular()) return;
    echo "\n<!-- ai_h1 debug: type=" . esc_html(get_post_type()) .
         " seen=" . (!empty($GLOBALS['ai_force_h1_seen']) ? 'yes' : 'no') .
         " promoted=" . (!empty($GLOBALS['ai_force_h1_done']) ? 'yes' : 'no') . " -->\n";
});