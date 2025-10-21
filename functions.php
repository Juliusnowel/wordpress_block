<?php
function my_child_theme_enqueue_styles() {
  wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
  wp_enqueue_style('child-style', get_stylesheet_uri(), ['parent-style'], wp_get_theme()->get('Version'));
}
add_action('wp_enqueue_scripts', 'my_child_theme_enqueue_styles');

/* OPTIONAL: Google Fonts (ONLY if you switch theme.json to Inter) */
// function theme_fonts() {
//   wp_enqueue_style(
//     'theme-google-fonts',
//     'https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap',
//     [],
//     null
//   );
// }
// add_action('wp_enqueue_scripts', 'theme_fonts');

/* REGISTER BLOCKS (guarded) */
add_action('init', function () {
  $blocks = [
    get_stylesheet_directory() . '/blocks/vite-faq',
    get_stylesheet_directory() . '/blocks/definition-box',
    get_stylesheet_directory() . '/blocks/key-takeaways',
  ];
  foreach ($blocks as $dir) {
    if (file_exists($dir . '/block.json')) {
      register_block_type($dir);
    }
  }
});

/** Helpers */
function ai_is_front_render(): bool {
  return is_singular()
    && !is_admin()
    && !wp_doing_ajax()
    && !(function_exists('wp_is_json_request') && wp_is_json_request())
    && !is_feed()
    && !is_preview();
}

/**
 * Force exactly one H1 on singular views (all CPTs).
 * If none exists, promote the FIRST <h2> to <h1>.
 * Non-destructive: render-time only.
 */
$GLOBALS['ai_force_h1_seen'] = false;
$GLOBALS['ai_force_h1_done'] = false;

add_action('template_redirect', function () {
  if (ai_is_front_render()) {
    $GLOBALS['ai_force_h1_seen'] = false;
    $GLOBALS['ai_force_h1_done'] = false;
  }
});

add_filter('render_block', function ($content, $block) {
  if (!ai_is_front_render()) return $content;

  // If this block outputs an <h1>, mark as seen.
  if (stripos($content, '<h1') !== false) {
    $GLOBALS['ai_force_h1_seen'] = true;
    return $content;
  }

  // Post Title block may not be level 1.
  if (($block['blockName'] ?? '') === 'core/post-title') {
    $level = isset($block['attrs']['level']) ? (int) $block['attrs']['level'] : 1;
    if ($level === 1) $GLOBALS['ai_force_h1_seen'] = true;
    return $content;
  }

  // Promote first <h2> to <h1> if none seen yet.
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

add_filter('the_content', function ($content) {
  if (!ai_is_front_render()) return $content;
  if (stripos($content, '<h1') !== false) return $content;

  $content = preg_replace('~<h2\b~i', '<h1', $content, 1);
  $content = preg_replace('~</h2>~i', '</h1>', $content, 1);
  return $content;
}, 9999);

/** Debug comment in page source (remove later). */
add_action('wp_head', function () {
  if (!ai_is_front_render()) return;
  echo "\n<!-- ai_h1 debug: type=" . esc_html(get_post_type()) .
       " seen=" . (!empty($GLOBALS['ai_force_h1_seen']) ? 'yes' : 'no') .
       " promoted=" . (!empty($GLOBALS['ai_force_h1_done']) ? 'yes' : 'no') . " -->\n";
});
