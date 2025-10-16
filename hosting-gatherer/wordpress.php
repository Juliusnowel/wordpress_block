// 1) AJAX – language-aware slugs + Definition CPT
add_action('wp_ajax_cms_blog_search', 'cms_blog_search_ajax');
add_action('wp_ajax_nopriv_cms_blog_search', 'cms_blog_search_ajax');

function cms_expand_term_and_children_ids( $slugs, $tax = 'category' ){
    $ids = [];
    foreach ((array) $slugs as $slug){
        $t = get_term_by('slug', $slug, $tax);
        if ($t instanceof WP_Term){
            $ids[] = (int) $t->term_id;
            $kids = get_term_children($t->term_id, $tax);
            if (!is_wp_error($kids) && $kids){
                $ids = array_merge($ids, array_map('intval', $kids));
            }
        }
    }
    return array_values(array_unique($ids));
}


function cms_blog_search_ajax() {
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cms_blog_search_nonce')) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    $search_query   = isset($_POST['search_query']) ? sanitize_text_field(wp_unslash($_POST['search_query'])) : '';

    // include slugs (already in current language)
    $category_slugs = isset($_POST['category_slugs']) ? (array) $_POST['category_slugs'] : [];
    $category_slugs = array_map(function($s){
        $s = sanitize_text_field( wp_unslash($s) );
        return urldecode($s);
    }, $category_slugs);

    // NEW: exclude slugs (already in current language)
    $exclude_slugs = isset($_POST['exclude_slugs']) ? (array) $_POST['exclude_slugs'] : [];
    $exclude_slugs = array_map(function($s){
        $s = sanitize_text_field( wp_unslash($s) );
        return urldecode($s);
    }, $exclude_slugs);

    $default_types  = ['how-to','news','review','definition'];
    $post_type_in   = isset($_POST['post_type']) ? sanitize_title(wp_unslash($_POST['post_type'])) : '';
    $post_type      = $post_type_in ? $post_type_in : $default_types;

    $paged          = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;
    $posts_per_page = isset($_POST['posts_per_page']) ? max(1, (int) $_POST['posts_per_page']) : 9;

    $lang = '';
    if (isset($_POST['lang'])) {
        $lang = sanitize_key(wp_unslash($_POST['lang']));
    } elseif (function_exists('pll_current_language')) {
        $lang = pll_current_language('slug');
    }

    // Resolve term IDs for include & exclude
	$include_ids = cms_expand_term_and_children_ids( $category_slugs, 'category' );
	$exclude_ids = cms_expand_term_and_children_ids( $exclude_slugs,   'category' );

    foreach ($exclude_slugs as $slug) {
        $t = get_term_by('slug', $slug, 'category');
        if ($t instanceof WP_Term) $exclude_ids[] = (int) $t->term_id;
    }
    $exclude_ids = array_values(array_unique(array_filter($exclude_ids)));

    $args = [
        'post_type'           => $post_type,
        'posts_per_page'      => $posts_per_page,
        'paged'               => $paged,
        'post_status'         => 'publish',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'lang'                => $lang,
        'suppress_filters'    => false,
    ];

    // STRICT filter: must be IN include set AND NOT IN exclude set
    $tax = ['relation' => 'AND'];
    if (!empty($include_ids)) {
        $tax[] = [
            'taxonomy'         => 'category',
            'field'            => 'term_id',
            'terms'            => $include_ids,
            'include_children' => true,
            'operator'         => 'IN',
        ];
    }
    if (!empty($exclude_ids)) {
        $tax[] = [
            'taxonomy'         => 'category',
            'field'            => 'term_id',
            'terms'            => $exclude_ids,
            'operator'         => 'NOT IN',
        ];
    }
    if (count($tax) > 1) {
        $args['tax_query'] = $tax;
    }

    if ($search_query !== '') $args['s'] = $search_query;

    $q = new WP_Query($args);

    ob_start();
    if ($q->have_posts()) : ?>
        <div class="cms-blog-grid">
            <?php while ($q->have_posts()) : $q->the_post(); ?>
            <article class="cms-blog-item">
                <a class="cms-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                    <div class="cms-blog-image">
                        <?php if (has_post_thumbnail()) : ?>
                            <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium_large')); ?>"
                                 alt="<?php echo esc_attr(get_the_title()); ?>"
                                 loading="lazy"
                                 onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="placeholder-icon" style="display:none;">📄</div>
                        <?php else : ?>
                            <div class="placeholder-icon">📄</div>
                        <?php endif; ?>
                    </div>
                    <div class="cms-blog-content">
                        <h2 class="cms-blog-title"><?php the_title(); ?></h2>
                        <div class="cms-blog-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 30, '…')); ?></div>
                    </div>
                </a>
            </article>
            <?php endwhile; ?>
        </div>
        <?php if ($q->max_num_pages > 1) : ?>
            <nav class="cms-pagination" data-max-pages="<?php echo (int) $q->max_num_pages; ?>">
                <?php echo paginate_links([
                    'base'      => '#',
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $q->max_num_pages,
                    'prev_text' => esc_html__('Prev', 'twentytwentyfive'),
                    'next_text' => esc_html__('Next', 'twentytwentyfive'),
                    'type'      => 'list',
                ]); ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <div class="cms-no-posts"><?php esc_html_e('No posts found.', 'twentytwentyfive'); ?></div>
    <?php endif;
    wp_reset_postdata();

    wp_send_json_success([
        'html'         => ob_get_clean(),
        'found_posts'  => (int) $q->found_posts,
        'max_pages'    => (int) $q->max_num_pages,
        'current_page' => (int) $paged,
    ]);
}



// 2) Shortcode: language-aware router + parent-category fallback + Definition CPT
function cms_blog_gatherer_func($atts) {
    $lang = function_exists('pll_current_language') ? pll_current_language('slug') : '';

    $parts = array_values(array_filter(explode('/', trim($_SERVER['REQUEST_URI'], '/'))));
    $langs = function_exists('pll_languages_list') ? pll_languages_list(['fields'=>'slug']) : ['en','ko'];
    if (!empty($parts) && in_array($parts[0], $langs, true)) array_shift($parts);

    $cms_slug     = $parts[0] ?? '';
    $sub_category = $parts[1] ?? '';

    $map = [
        'wp-articles' => [
            'parent'   => ['category'=>'wordpress',   'display'=>['en'=>'WordPress','ko'=>'워드프레스'], 'class'=>'cms-wordpress-gatherer'],
            'plugins'  => ['category'=>'plugins',     'display'=>['en'=>'WordPress','ko'=>'워드프레스'], 'class'=>'cms-wordpress-gatherer'],
            'wp-themes'=> ['category'=>'wp-themes',   'display'=>['en'=>'WordPress','ko'=>'워드프레스'], 'class'=>'cms-wordpress-gatherer'],
            'default'  => ['category'=>'wordpress',   'display'=>['en'=>'WordPress','ko'=>'워드프레스'], 'class'=>'cms-wordpress-gatherer'],
        ],
        'wix-articles' => [
            'parent'   => ['category'=>'wix',         'display'=>['en'=>'Wix','ko'=>'윅스'], 'class'=>'cms-wix-gatherer'],
            'apps'     => ['category'=>'apps',        'display'=>['en'=>'Wix','ko'=>'윅스'], 'class'=>'cms-wix-gatherer'],
            'default'  => ['category'=>'wix',         'display'=>['en'=>'Wix','ko'=>'윅스'], 'class'=>'cms-wix-gatherer'],
        ],
        'drupal-articles' => [
            'parent'   => ['category'=>'drupal',      'display'=>['en'=>'Drupal','ko'=>'드루팔'], 'class'=>'cms-drupal-gatherer'],
            'modules'  => ['category'=>'modules',     'display'=>['en'=>'Drupal','ko'=>'드루팔'], 'class'=>'cms-drupal-gatherer'],
            'default'  => ['category'=>'drupal',      'display'=>['en'=>'Drupal','ko'=>'드루팔'], 'class'=>'cms-drupal-gatherer'],
        ],
        'joomla-articles' => [
            'parent'   => ['category'=>'joomla',      'display'=>['en'=>'Joomla','ko'=>'줌라'], 'class'=>'cms-joomla-gatherer'],
            'default'  => ['category'=>'joomla',      'display'=>['en'=>'Joomla','ko'=>'줌라'], 'class'=>'cms-joomla-gatherer'],
        ],
        'squarespace-articles' => [
            'parent'   => ['category'=>'squarespace', 'display'=>['en'=>'Squarespace','ko'=>'스퀘어스페이스'], 'class'=>'cms-squarespace-gatherer'],
            'extensions'=>['category'=>'extensions',  'display'=>['en'=>'Squarespace','ko'=>'스퀘어스페이스'], 'class'=>'cms-squarespace-gatherer'],
            'ss-themes'=> ['category'=>'ss-themes',   'display'=>['en'=>'Squarespace','ko'=>'스퀘어스페이스'], 'class'=>'cms-squarespace-gatherer'],
            'default'  => ['category'=>'squarespace', 'display'=>['en'=>'Squarespace','ko'=>'스퀘어스페이스'], 'class'=>'cms-squarespace-gatherer'],
        ],
    ];

    $ui = ($lang === 'ko') ? [
        'articles'=>'아티클','articles_for'=>'관련 글','search_placeholder'=>'%s 아티클 검색…',
        'all_types'=>'모든 글 유형','howtos'=>'사용 가이드','news'=>'뉴스','reviews'=>'리뷰','definitions'=>'용어집',
    ] : [
        'articles'=>'Articles','articles_for'=>'Articles for','search_placeholder'=>'Search %s articles…',
        'all_types'=>'All post types','howtos'=>'How-tos','news'=>'News','reviews'=>'Reviews','definitions'=>'Definitions',
    ];

    // Helper to map base slug -> current language slug
    $to_lang_slug = function($base) use ($lang) {
        $base = sanitize_title($base);
        $term = get_term_by('slug', $base, 'category');
        if ($term && function_exists('pll_get_term') && $lang) {
            $tr_id = pll_get_term($term->term_id, $lang);
            if ($tr_id) {
                $tr = get_term($tr_id, 'category');
                if ($tr && !is_wp_error($tr)) return $tr->slug;
            }
        }
        return $base;
    };

    // Include set = current CMS parent (+ subcats)
    // Exclude set = all other CMS parents + glossary (+ optional extra buckets you don't want)
    $cms_parents = ['wordpress','wix','drupal','joomla','squarespace'];

    if (isset($map[$cms_slug])) {
        $cms      = $map[$cms_slug];
        $active   = $cms[$sub_category] ?? $cms['default'];
        $display  = $active['display'][$lang === 'ko' ? 'ko' : 'en'];
        $hero_cls = $active['class'];

        $current_parent_base = $cms['parent']['category'];

        // include (base slugs)
        if ($sub_category) {
            $include_bases = [$active['category']];
        } else {
            $include_bases = [$current_parent_base];
            foreach ($cms as $k => $row) {
                if (in_array($k, ['parent','default'], true)) continue;
                $include_bases[] = $row['category'];
            }
            $include_bases = array_values(array_unique($include_bases));
        }

        // exclude (base slugs): all other CMS parents + glossary (and optionally uncategorized)
        $exclude_bases = array_values(array_diff($cms_parents, [$current_parent_base]));
        $exclude_bases[] = 'glossary';
        // $exclude_bases[] = 'uncategorized'; // uncomment if needed

    } else {
        $display         = ($lang === 'ko') ? '콘텐츠 작성' : 'Content Writing';
        $hero_cls        = 'cms-category-content-writing';
        $include_bases   = ['content-writing'];
        // $exclude_bases   = ['glossary']; // keep glossary out of the generic page too
    }

    // Translate base slugs -> current language slugs
    $category_slugs = array_values(array_unique(array_filter(array_map($to_lang_slug, $include_bases))));
    $exclude_slugs  = array_values(array_unique(array_filter(array_map($to_lang_slug, $exclude_bases))));

    // Resolve term IDs for initial render
    $term_ids = [];
    foreach ($category_slugs as $slug) {
        $t = get_term_by('slug', $slug, 'category');
        if ($t instanceof WP_Term) $term_ids[] = (int) $t->term_id;
    }
    $term_ids = array_values(array_unique(array_filter($term_ids)));

    $atts = shortcode_atts(['posts_per_page' => 9], $atts);

    // Initial query (strict IN + NOT IN)
    $args = [
        'post_type'           => ['how-to','news','review','definition'],
        'posts_per_page'      => (int) $atts['posts_per_page'],
        'paged'               => 1,
        'post_status'         => 'publish',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'lang'                => $lang,
        'suppress_filters'    => false,
    ];
    $tax = ['relation' => 'AND'];
	
	if (!empty($term_ids)) {
		$tax[] = [
			'taxonomy'         => 'category',
			'field'            => 'term_id',
			'terms'            => $term_ids,
			'include_children' => true,
			'operator'         => 'IN',
		];
	}

	$ex_ids = cms_expand_term_and_children_ids( $exclude_slugs, 'category' );
	if ($ex_ids) {
		$tax[] = [
			'taxonomy' => 'category',
			'field'    => 'term_id',
			'terms'    => $ex_ids,
			'operator' => 'NOT IN',
		];
	}

	if (count($tax) > 1) {
		$args['tax_query'] = $tax;
	}
    if (!empty($exclude_slugs)) {
        // translate exclude slugs to IDs
        $ex_ids = [];
        $ex_ids = array_values(array_unique(array_filter($ex_ids)));
        if ($ex_ids) {
            $tax[] = [
                'taxonomy' => 'category',
                'field'    => 'term_id',
                'terms'    => $ex_ids,
                'operator' => 'NOT IN',
            ];
        }
    }
    if (count($tax) > 1) $args['tax_query'] = $tax;

    $q = new WP_Query($args);
    $placeholder = sprintf($ui['search_placeholder'], $display);

    ob_start();     ?>
    <div class="cms-blog-container"
         data-category-slugs='<?php echo esc_attr(wp_json_encode($category_slugs)); ?>'
         data-exclude-slugs='<?php echo esc_attr(wp_json_encode($exclude_slugs)); ?>'
         data-posts-per-page="<?php echo esc_attr((int) $atts['posts_per_page']); ?>"
         data-nonce="<?php echo esc_attr(wp_create_nonce('cms_blog_search_nonce')); ?>"
         data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-lang="<?php echo esc_attr($lang); ?>">


        <div class="cms-hero-section <?php echo esc_attr($hero_cls); ?>">
            <div class="cms-hero-background" data-parallax></div>
            <div class="cms-hero-overlay"></div>
            <div class="cms-blog-header">
                <?php if ($sub_category) : ?>
                    <h1 class="cms-main-title"><?php echo esc_html($display.' '.$ui['articles_for']); ?>
                        <span class="cms-subtitle"><?php echo esc_html($sub_category); ?></span>
                    </h1>
                <?php else : ?>
                    <h1 class="cms-main-title"><?php echo esc_html($display.' '.$ui['articles']); ?></h1>
                <?php endif; ?>
                <div class="cms-search-container">
                    <form class="cms-search-form">
                        <input type="text" name="cms_search" class="cms-search-input"
                               placeholder="<?php echo esc_attr($placeholder); ?>" autocomplete="off">
                        <div class="cms-search-loading" style="display:none;"><div class="cms-spinner"></div></div>
                    </form>
                </div>
            </div>
        </div>

        <div class="cms-toolbar" style="max-width:1400px;margin:2rem auto 0;padding:0 2rem;">
            <div class="dropdown-container">
                <button class="dropdown-button" onclick="toggleDropdown()">
                    <span class="dropdown-text"><?php echo esc_html($ui['all_types']); ?></span>
                    <span class="dropdown-arrow">▼</span>
                </button>
                <div class="dropdown-menu" id="dropdownMenu">
                    <button class="dropdown-item selected" onclick="selectItem(this, '<?php echo esc_js($ui['all_types']); ?>', '')"><?php echo esc_html($ui['all_types']); ?></button>
                    <button class="dropdown-item" onclick="selectItem(this, '<?php echo esc_js($ui['howtos']); ?>', 'how-to')"><?php echo esc_html($ui['howtos']); ?></button>
                    <button class="dropdown-item" onclick="selectItem(this, '<?php echo esc_js($ui['news']); ?>', 'news')"><?php echo esc_html($ui['news']); ?></button>
                    <button class="dropdown-item" onclick="selectItem(this, '<?php echo esc_js($ui['reviews']); ?>', 'review')"><?php echo esc_html($ui['reviews']); ?></button>
                    <button class="dropdown-item" onclick="selectItem(this, '<?php echo esc_js($ui['definitions']); ?>', 'definition')"><?php echo esc_html($ui['definitions']); ?></button>
                </div>
            </div>
        </div>

        <div class="cms-results-container">
            <?php if ($q->have_posts()) : ?>
                <div class="cms-blog-grid">
                    <?php while ($q->have_posts()) : $q->the_post(); ?>
                        <article class="cms-blog-item">
                            <a class="cms-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                                <div class="cms-blog-image">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium_large')); ?>"
                                             alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy"
                                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="placeholder-icon" style="display:none;">📄</div>
                                    <?php else : ?>
                                        <div class="placeholder-icon">📄</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cms-blog-content">
                                    <h2 class="cms-blog-title"><?php the_title(); ?></h2>
                                    <div class="cms-blog-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 30, '…')); ?></div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ($q->max_num_pages > 1) : ?>
                    <nav class="cms-pagination" data-max-pages="<?php echo (int) $q->max_num_pages; ?>">
                        <?php echo paginate_links([
                            'base'      => '#',
                            'format'    => '',
                            'current'   => 1,
                            'total'     => $q->max_num_pages,
                            'prev_text' => esc_html__('Prev', 'twentytwentyfive'),
                            'next_text' => esc_html__('Next', 'twentytwentyfive'),
                            'type'      => 'list',
                        ]); ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="cms-no-posts"><?php echo ($lang==='ko') ? '게시물이 없습니다.' : 'No posts found.'; ?></div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('cms_blog_gatherer', 'cms_blog_gatherer_func');

// 3) CSS – keep dropdown only, remove .cms-nav / .cms-subcategory-* rules
add_action('wp_enqueue_scripts', 'cms_blog_gatherer_inline_styles');
function cms_blog_gatherer_inline_styles() {
    wp_register_style('cms-blog-gatherer', false);
    wp_enqueue_style('cms-blog-gatherer');

    $css = "
        /* --- dropdown keeps working --- */
        .dropdown-container{position:relative;display:inline-block;width:200px}
        .dropdown-button{width:100%;padding:12px 16px;background:#5A4AFF;color:#fff;border:0;border-radius:6px;font-size:1rem;display:flex;justify-content:space-between;align-items:center;cursor:pointer;transition:.2s}
        .dropdown-button:hover{background:#4A3AE8}
        .dropdown-arrow{font-size:12px;transition:transform .3s;margin-left:8px}
        .dropdown-button.open .dropdown-arrow{transform:rotate(180deg)}
        .dropdown-menu{position:absolute;top:100%;left:0;right:0;background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);z-index:1000;opacity:0;visibility:hidden;transform:translateY(-4px);transition:.2s;margin-top:2px;overflow:hidden}
        .dropdown-menu.open{opacity:1;visibility:visible;transform:translateY(0)}
        .dropdown-item{padding:10px 16px;color:#374151;cursor:pointer;font-size:14px;border:0;background:none;width:100%;text-align:left}
        .dropdown-item:hover{background:#f8fafc}
        .dropdown-item.selected{background:#5A4AFF;color:#fff}
        @media (max-width:768px){.dropdown-container{width:100%}.dropdown-button{padding:15px 16px;font-size:15px}.dropdown-item{padding:11px 16px;font-size:15px}}

        /* --- existing layout (unchanged) --- */
        .cms-hero-section{width:98vw;position:relative;left:50%;transform:translateX(-50%);height:430px;overflow:hidden;display:flex;align-items:center;justify-content:center}
        .cms-hero-background{position:absolute;top:0;left:0;width:100%;height:130%;background-size:auto;background-position:50% 50%;background-repeat:repeat;background-attachment:fixed;background-image:var(--hero-bg-image);will-change:transform;transform:translateY(-65.1398px)}
        .cms-hero-overlay{position:absolute;inset:0;background:linear-gradient(173deg,#7d75f4 0%,#8e6ac4 28%,#564aff 76%);opacity:.8}
        .cms-blog-header{position:relative;z-index:2;text-align:center;margin:13rem 0;color:#fff}
        .cms-search-container{position:relative;width:100%;margin-top:1rem}
        .cms-search-input{width:40%;height:45px;padding:0 .75rem;border:2px solid #564AFF;border-radius:8px;font-size:1rem;background:rgba(255,255,255,.9);color:#333;outline:none;transition:.3s}
        .cms-search-input:focus{background:#fff;border-color:#564AFF;box-shadow:0 6px 20px rgba(0,0,0,.15)}
        .cms-blog-grid{margin-top:1rem;display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:2rem;padding:0 2rem}
        .cms-blog-item{border-radius:8px;overflow:hidden;transition:.3s}
        .cms-blog-item:hover{transform:translateY(-5px);box-shadow:0 8px 30px rgba(0,0,0,.12)}
        .cms-blog-image{width:100%;height:220px;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);display:flex;align-items:center;justify-content:center;border-bottom:1px solid #f0f0f0;position:relative;overflow:hidden}
        .cms-blog-image img{width:100%;height:100%;object-fit:cover;transition:.3s}
        .cms-blog-item:hover .cms-blog-image img{transform:scale(1.05)}
        .cms-blog-content{padding:.95rem}
        .cms-blog-title{margin:0 0 1rem;font-size:24px;font-weight:700;color:#2d3748}
        .cms-blog-excerpt{font-size:15px;font-weight:500;color:#666;margin:0}
        .cms-pagination{display:flex;justify-content:center;align-items:center;gap:.5rem;margin-top:3rem;padding:2rem 0}
        .cms-pagination .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .75rem;border-radius:8px;text-decoration:none;color:#4a5568;background:#fff}
        .cms-pagination .current{background:#564AFF;color:#fff}
        .cms-results-container{transition:opacity .3s;width:100%;max-width:1400px;margin:0 auto}

        /* Category headers */
        .cms-category-content-writing .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/content-writing-header1.webp')}
        .cms-category-web-hosting .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/web-hosting-header.webp')}
        .cms-wordpress-gatherer .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/wordpress-gatherer-header.png')}
        .cms-drupal-gatherer .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/drupal-gatherer-header.png')}
        .cms-joomla-gatherer .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/joomla-gatherer-header.png')}
        .cms-wix-gatherer .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/wix-gatherer-header.png')}
        .cms-squarespace-gatherer .cms-hero-background{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/squarespace-gatherer-header.png')}
    ";
    wp_add_inline_style('cms-blog-gatherer', $css);
}

// 4) JavaScript – passes lang + all category slugs
add_action('wp_footer', 'cms_blog_gatherer_scripts');
function cms_blog_gatherer_scripts() { ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
  const container        = document.querySelector('.cms-blog-container');
  if (!container) return;

  // Parallax
  const parallaxElements = document.querySelectorAll('[data-parallax]');
  const updateParallax = () => {
    parallaxElements.forEach(el => {
      const yPos = -(el.getBoundingClientRect().top * 0.5);
      el.style.transform = `translateY(${yPos}px)`;
    });
  };
  let ticking = false;
  window.addEventListener('scroll', () => { if (!ticking){ requestAnimationFrame(()=>{updateParallax(); ticking=false;}); ticking=true; }});
  updateParallax();

  // Core refs
  const searchInput      = document.querySelector('.cms-search-input');
  const searchForm       = document.querySelector('.cms-search-form');
  const resultsContainer = document.querySelector('.cms-results-container');
  const searchLoading    = document.querySelector('.cms-search-loading');

  // Dataset
  const categorySlugs = JSON.parse(container.dataset.categorySlugs || '[]');
  const excludeSlugs  = JSON.parse(container.dataset.excludeSlugs  || '[]');
  const postsPerPage  = container.dataset.postsPerPage;
  const nonce         = container.dataset.nonce;
  const ajaxUrl       = container.dataset.ajaxUrl;
  const lang          = container.dataset.lang || '';

  let currentPage    = 1;
  let currentSearch  = '';
  let currentPostType= '';

function performSearch(searchQuery = '', page = 1, postType = null) {
  if (postType !== null) currentPostType = postType;

  if (searchLoading) searchLoading.style.display = 'block';
  resultsContainer.classList.add('loading');

  const formData = new FormData();
  formData.append('action', 'cms_blog_search');
  formData.append('search_query', searchQuery);

  categorySlugs.forEach(s => formData.append('category_slugs[]', s));
  excludeSlugs.forEach(s  => formData.append('exclude_slugs[]',  s)); // NEW

  formData.append('post_type', currentPostType);
  formData.append('paged', page);
  formData.append('posts_per_page', postsPerPage);
  formData.append('nonce', nonce);
  formData.append('lang', lang);

  fetch(ajaxUrl, { method: 'POST', body: formData })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          resultsContainer.innerHTML = data.data.html;
          currentPage = data.data.current_page;
          attachPaginationListeners();
          attachImageListeners();
          attachClearSearchListener();
        } else {
          console.error('Search failed:', data);
        }
      })
      .catch(err => console.error('AJAX error:', err))
      .finally(() => {
        if (searchLoading) searchLoading.style.display = 'none';
        resultsContainer.classList.remove('loading');
      });
  }

  // Search
  if (searchInput && searchForm) {
    let t;
    searchInput.addEventListener('input', function () {
      clearTimeout(t);
      const q = this.value.trim();
      t = setTimeout(() => {
        if (q.length >= 3 || q.length === 0) {
          currentSearch = q;
          performSearch(q, 1, currentPostType);
        }
      }, 800);
    });
    searchForm.addEventListener('submit', e => {
      e.preventDefault();
      currentSearch = searchInput.value.trim();
      performSearch(currentSearch, 1, currentPostType);
    });
  }

  // Pagination
  function attachPaginationListeners() {
    const links = document.querySelectorAll('.cms-pagination a.page-numbers');
    links.forEach(a => {
      a.addEventListener('click', e => {
        e.preventDefault();
        let page = 1;
        if (a.classList.contains('prev')) {
          page = Math.max(1, currentPage - 1);
        } else if (a.classList.contains('next')) {
          const max = parseInt(document.querySelector('.cms-pagination').dataset.maxPages);
          page = Math.min(max, currentPage + 1);
        } else {
          page = parseInt(a.textContent) || 1;
        }
        performSearch(currentSearch, page, currentPostType);
      });
    });
  }

  // Images
  function attachImageListeners() {
    document.querySelectorAll('.cms-blog-image img').forEach(img => {
      img.addEventListener('load', () => img.parentElement.classList.remove('loading'));
      if (!img.complete) img.parentElement.classList.add('loading');
    });
  }

  // Clear search
  function attachClearSearchListener() {
    const clearBtn = document.querySelector('.cms-clear-search');
    if (!clearBtn) return;
    clearBtn.addEventListener('click', e => {
      e.preventDefault();
      if (searchInput) searchInput.value = '';
      currentSearch = '';
      performSearch('', 1, currentPostType);
    });
  }

  // Post-type dropdown hook
  document.addEventListener('dropdownChange', e => {
    currentPostType = e.detail.value || '';
    performSearch(currentSearch, 1, currentPostType);
  });

  // Initial bindings
  attachPaginationListeners();
  attachImageListeners();
});

// Dropdown helpers
function toggleDropdown(){
  const btn = document.querySelector('.dropdown-button');
  const menu= document.getElementById('dropdownMenu');
  btn.classList.toggle('open'); menu.classList.toggle('open');
}
function selectItem(item,text,value){
  document.querySelectorAll('.dropdown-item').forEach(i=>i.classList.remove('selected'));
  item.classList.add('selected');
  document.querySelector('.dropdown-text').textContent=text;
  document.querySelector('.dropdown-button').dataset.value=value;
  document.querySelector('.dropdown-button').classList.remove('open');
  document.getElementById('dropdownMenu').classList.remove('open');
  document.dispatchEvent(new CustomEvent('dropdownChange',{detail:{value,text}}));
}
document.addEventListener('click',e=>{
  const c=document.querySelector('.dropdown-container');
  if(c && !c.contains(e.target)){
    const b=document.querySelector('.dropdown-button');
    const m=document.getElementById('dropdownMenu');
    if(b) b.classList.remove('open');
    if(m) m.classList.remove('open');
  }
});
</script>
<?php }

