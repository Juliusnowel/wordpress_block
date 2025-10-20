// 1) Add AJAX handler for search (with Polylang support)
add_action('wp_ajax_cw_blog_search', 'cw_blog_search_ajax');
add_action('wp_ajax_nopriv_cw_blog_search', 'cw_blog_search_ajax');

function cw_blog_search_ajax() {
    // Security gate
    if ( ! isset($_POST['nonce']) || ! wp_verify_nonce($_POST['nonce'], 'cw_blog_search_nonce') ) {
        wp_send_json_error(['message' => 'Security check failed'], 403);
    }

    // Inputs
    $search_query   = isset($_POST['search_query']) ? sanitize_text_field( wp_unslash($_POST['search_query']) ) : '';
    $category_slug  = isset($_POST['category']) ? sanitize_text_field( wp_unslash($_POST['category']) ) : '';
    $paged          = isset($_POST['paged']) ? max(1, (int) $_POST['paged']) : 1;
    $posts_per_page = isset($_POST['posts_per_page']) ? max(1, (int) $_POST['posts_per_page']) : 9;

    // Language resolution
    if ( isset($_POST['lang']) ) {
        $lang = sanitize_key( wp_unslash($_POST['lang']) );
    } elseif ( function_exists('pll_current_language') ) {
        $lang = pll_current_language('slug');
    } else {
        $lang = '';
    }

    // Category resolution (always enforce)
    $translated_term_id = 0;
    $base_term = $category_slug ? get_term_by('slug', $category_slug, 'category') : null;

    if ( $base_term instanceof WP_Term ) {
        if ( function_exists('pll_get_term') && $lang ) {
            $maybe = pll_get_term( $base_term->term_id, $lang );
            $translated_term_id = $maybe ? (int) $maybe : (int) $base_term->term_id;
        } else {
            $translated_term_id = (int) $base_term->term_id;
        }
    }

    // Fallback: if still not found, try direct slug lookup in the current language
    if ( ! $translated_term_id && $category_slug ) {
        $term_fallback = get_term_by('slug', $category_slug, 'category');
        if ( $term_fallback instanceof WP_Term ) {
            $translated_term_id = (int) $term_fallback->term_id;
        }
    }


    // Build query args
    $query_args = array(
        'post_type'           => array('post', 'review', 'definition', 'how-to', 'news'),
        'posts_per_page'      => $posts_per_page,
        'paged'               => $paged,
        'post_status'         => 'publish',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'lang'                => $lang,
        'suppress_filters'    => false,
    );

    // Mandatory category scoping:
    // 1) Prefer translated term_id
    // 2) Fallback to slug
    // 3) If neither available, force empty result set
    if ( $translated_term_id ) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => array($translated_term_id),
                'include_children' => false,
            ),
        );
    } elseif ( $category_slug ) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy'         => 'category',
                'field'            => 'slug',
                'terms'            => array($category_slug),
                'include_children' => false,
            ),
        );
    } else {
        // No valid category context → return nothing
        $query_args['post__in'] = array(0);
    }

    if ( $search_query !== '' ) {
        $query_args['s'] = $search_query;
    }

    // Execute
    $query = new WP_Query($query_args);
    ob_start();

    if ( $query->have_posts() ) : ?>
        <div class="cw-blog-grid">
            <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                <article class="cw-blog-item">
                    <a class="cw-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                        <div class="cw-blog-image">
                            <?php if ( has_post_thumbnail() ) : ?>
                                <img
                                    src="<?php echo esc_url( get_the_post_thumbnail_url( get_the_ID(), 'medium_large' ) ); ?>"
                                    alt="<?php echo esc_attr( get_the_title() ); ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="placeholder-icon" style="display:none;">📄</div>
                            <?php else : ?>
                                <div class="placeholder-icon">📄</div>
                            <?php endif; ?>
                        </div>
                        <div class="cw-blog-content">
                            <h2 class="cw-blog-title"><?php the_title(); ?></h2>
                            <div class="cw-blog-excerpt"><?php echo wp_kses_post( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?></div>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>

        <?php if ( $query->max_num_pages > 1 ) : ?>
            <nav class="cw-pagination" data-max-pages="<?php echo (int) $query->max_num_pages; ?>">
                <?php
                echo paginate_links( array(
                    'base'      => '#',
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => $query->max_num_pages,
                    'prev_text' => esc_html__( 'Prev', 'twentytwentyfive' ),
                    'next_text' => esc_html__( 'Next', 'twentytwentyfive' ),
                    'type'      => 'list',
                ) );
                ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <div class="cw-no-posts">
            <?php esc_html_e('No posts found.', 'twentytwentyfive'); ?>
        </div>
    <?php endif;

    wp_reset_postdata();

    $response = array(
        'html'          => ob_get_clean(),
        'found_posts'   => (int) $query->found_posts,
        'max_pages'     => (int) $query->max_num_pages,
        'current_page'  => (int) $paged,
    );

    wp_send_json_success($response);
}

// 2) Updated shortcode function (with Polylang support)
function cw_blog_gatherer_func($atts) {
    $current_url = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    $url_parts   = explode('/', trim($current_url, '/'));

    // Detect language first
    $lang = function_exists('pll_current_language') ? pll_current_language('slug') : '';

    // Route to category by URL with language awareness
    if ($lang === 'ko') {
        if (in_array('web-hosting', $url_parts, true) || in_array('웹-호스팅', $url_parts, true)) {
            $category_slug   = '웹-호스팅';
            $category_name   = '웹 호스팅';
            $category_class  = 'cw-category-web-hosting';
        } else {
            $category_slug   = '콘텐츠-작성';
            $category_name   = '콘텐츠 작성';
            $category_class  = 'cw-category-content-writing';
        }
    } else {
        if (in_array('web-hosting', $url_parts, true)) {
            $category_slug   = 'web-hosting';
            $category_name   = 'Web Hosting';
            $category_class  = 'cw-category-web-hosting';
        } else {
            $category_slug   = 'content-writing';
            $category_name   = 'Content Writing';
            $category_class  = 'cw-category-content-writing';
        }
    }


    // Shortcode defaults
    $atts = shortcode_atts(
        array(
            'category'       => $category_slug, // slug
            'display_name'   => $category_name,
            'posts_per_page' => 9,
        ),
        $atts
    );

    // Language
    $lang = function_exists('pll_current_language') ? pll_current_language('slug') : '';

    // Category resolution (always enforce)
    $translated_term_id = 0;
    $base_term = get_term_by('slug', $atts['category'], 'category');

    if ($base_term instanceof WP_Term) {
        if (function_exists('pll_get_term') && !empty($lang)) {
            $maybe = pll_get_term($base_term->term_id, $lang);
            $translated_term_id = $maybe ? (int) $maybe : (int) $base_term->term_id;
        } else {
            $translated_term_id = (int) $base_term->term_id;
        }
    }

    // Build query
    $query_args = array(
        'post_type'           => array('post', 'review', 'definition', 'how-to', 'news'),
        'posts_per_page'      => (int) $atts['posts_per_page'],
        'paged'               => 1,
        'post_status'         => 'publish',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => true,
        'lang'                => $lang,
        'suppress_filters'    => false,
    );

    // Mandatory category scoping with fallback/guard
    if ($translated_term_id) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy'         => 'category',
                'field'            => 'term_id',
                'terms'            => array($translated_term_id),
                'include_children' => false,
            ),
        );
    } elseif (!empty($atts['category'])) {
        $query_args['tax_query'] = array(
            array(
                'taxonomy'         => 'category',
                'field'            => 'slug',
                'terms'            => array($atts['category']),
                'include_children' => false,
            ),
        );
    } else {
        $query_args['post__in'] = array(0);
    }

    $query = new WP_Query($query_args);

    ob_start();
    ?>
    <div class="cw-blog-container"
         data-category="<?php echo esc_attr($atts['category']); ?>"
         data-display-name="<?php echo esc_attr($atts['display_name']); ?>"
         data-posts-per-page="<?php echo esc_attr((int) $atts['posts_per_page']); ?>"
         data-nonce="<?php echo esc_attr( wp_create_nonce('cw_blog_search_nonce') ); ?>"
         data-ajax-url="<?php echo esc_url( admin_url('admin-ajax.php') ); ?>"
         data-lang="<?php echo esc_attr($lang); ?>">

        <div class="cw-hero-section <?php echo esc_attr($category_class); ?>">
            <div class="cw-hero-background" data-parallax></div>
            <div class="cw-hero-overlay"></div>
            <div class="cw-blog-header">
                <h1>
                    <?php esc_html_e('Articles for', 'twentytwentyfive'); ?>
                    <span class="subtitle"><?php echo esc_html($atts['display_name']); ?></span>
                </h1>
                <div class="cw-search-container">
                    <form class="cw-search-form">
                        <input
                            type="text"
                            name="cw_search"
                            class="cw-search-input"
                            placeholder="<?php echo esc_attr( sprintf( __('Search %s articles...', 'twentytwentyfive'), strtolower($atts['display_name']) ) ); ?>"
                            autocomplete="off"
                        >
                        <div class="cw-search-loading" style="display: none;">
                            <div class="cw-spinner"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="cw-results-container">
            <?php if ($query->have_posts()) : ?>
                <div class="cw-blog-grid">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <article class="cw-blog-item">
                            <a class="cw-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                                <div class="cw-blog-image">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <img
                                            src="<?php echo esc_url( get_the_post_thumbnail_url(get_the_ID(), 'medium_large') ); ?>"
                                            alt="<?php echo esc_attr( get_the_title() ); ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                        <div class="placeholder-icon" style="display: none;">📄</div>
                                    <?php else : ?>
                                        <div class="placeholder-icon">📄</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cw-blog-content">
                                    <h2 class="cw-blog-title"><?php the_title(); ?></h2>
                                    <div class="cw-blog-excerpt">
                                        <?php echo wp_kses_post( wp_trim_words( get_the_excerpt(), 30, '…' ) ); ?>
                                    </div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>

                <?php if ($query->max_num_pages > 1) : ?>
                    <nav class="cw-pagination" data-max-pages="<?php echo (int) $query->max_num_pages; ?>">
                        <?php
                        echo paginate_links( array(
                            'base'      => '#',
                            'format'    => '',
                            'current'   => 1,
                            'total'     => $query->max_num_pages,
                            'prev_text' => esc_html__( 'Prev', 'twentytwentyfive' ),
                            'next_text' => esc_html__( 'Next', 'twentytwentyfive' ),
                            'type'      => 'list',
                        ) );
                        ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="cw-no-posts">
                    <?php esc_html_e('No articles found.', 'twentytwentyfive'); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('dynamic_blog_gatherer', 'cw_blog_gatherer_func');


// 3) CSS (No changes made here)
add_action('wp_enqueue_scripts', 'cw_blog_gatherer_inline_styles');
function cw_blog_gatherer_inline_styles() {
    wp_register_style('cw-blog-gatherer', false);
    wp_enqueue_style('cw-blog-gatherer');
    
    $additional_css = "
        /* Loading spinner */
        .cw-search-loading {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
        }
        
        .cw-spinner {
            width: 20px;
            height: 20px;
            border: 2px solid #f3f3f3;
            border-top: 2px solid #564AFF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Results container transitions */
        .cw-results-container {
            transition: opacity 0.3s ease;
        }
        
        .cw-results-container.loading {
            opacity: 0.6;
            pointer-events: none;
        }
        
        /* Fade-in animation for new results */
        .cw-blog-grid {
            animation: fadeIn 0.5s ease-in-out;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Search input focus state */
        .cw-search-input:focus {
            background: white;
            border-color: #564AFF;
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        /* Clear search button */
        .cw-clear-search {
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .cw-clear-search:hover {
            color: #564AFF !important;
        }
    ";

    $css = "
		.cw-blog-link {
		   height: 100%;
		   display: block;
		   text-decoration: none;
		   color: inherit;
		}
        .cw-blog-container {
        }
        
        .cw-hero-section {
          width: 98vw;
          position: relative;
          left: 50%;
          transform: translateX(-50%);
          height: 500px;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .cw-hero-background {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 130%;
          background-size: auto;
          background-position: 50% 50%;
          background-repeat: repeat;
          background-attachment: fixed;
          background-image: var(--hero-bg-image);
          will-change: transform;
          transform: translate3d(0, 0, 0);
		  transform: translateY(-65.1398px);
        }
        
        .cw-hero-overlay {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: linear-gradient(173deg, rgb(125, 117, 244) 0%, rgb(142, 106, 196) 28%, rgb(86, 74, 255) 76%);
          opacity: 0.8;
        }
        
        .cw-blog-header {
          position: relative;
          z-index: 2;
          text-align: center;
          margin: 13rem 0 13rem 0;
          padding: 0;
          color: white;
          width: 100%;
        }
        
        .cw-blog-header h1 {
          margin: 0 0 0 0;
          font-weight: 700;
          color: white;
          text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .cw-blog-header .subtitle {
		  color: white;
          font-weight: 700;
          text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .cw-search-container {
          position: relative;
          width: 100%;
          margin: 0 auto;
          justify-content: center;  
          margin-top: 1rem;
        }
        
        .cw-search-input {
          width: 40%;
          height: 45px;
          padding: 0 0.75rem 0 .75rem;
          border: 2px solid #564AFF;
          border-radius: 8px;
          font-size: 1rem;
          background: rgba(255,255,255,0.9);
          backdrop-filter: blur(10px);
          color: #333;
          outline: none;
          transition: all 0.3s ease;
        }
        
        .cw-search-input::placeholder {
          color: #666;
        }
        
        .cw-search-input:focus {
          background: white;
          border-color: #564AFF;
          box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .cw-search-icon {
          position: absolute;
          right: 1rem;
          top: 50%;
          transform: translateY(-50%);
          color: #999;
          font-size: 1.1rem;
        }
        
        .cw-blog-grid {
          margin-top: 4rem;
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 2rem;
          padding: 0 2rem;
        }
        
        .cw-blog-item {
          border-radius: 8px;
          overflow: hidden;
          transition: all 0.3s ease;
        }
        
        .cw-blog-item:hover {
          transform: translateY(-5px);
          box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .cw-blog-image {
          width: 100%;
          height: 220px;
          background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
          display: flex;
          align-items: center;
          justify-content: center;
          font-size: 3rem;
          color: #999;
          border-bottom: 1px solid #f0f0f0;
          position: relative;
          overflow: hidden;
        }
        
        .cw-blog-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          object-position: center;
          transition: transform 0.3s ease;
        }
        
        .cw-blog-item:hover .cw-blog-image img {
          transform: scale(1.05);
        }
        
        .cw-blog-image .placeholder-icon {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 100%;
          height: 100%;
          font-size: 3rem;
          color: #999;
          background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .cw-blog-content {
          padding: .95rem;
        }
        
        .cw-blog-title {
          margin: 0 0 1rem 0;
          font-size: 24px;
          font-weight: 700;
          color: #2d3748;
          text-decoration: none;
          transition: color 0.3s ease;
        }
        
        .cw-blog-title a {
        }
        
		.cw-blog-title:hover {
          color: #667eea;
        }
        
        .cw-blog-excerpt {
        font-size: 15px;
		font-weight: 500;
          color: #666;
          margin: 0;
        }
        
        .cw-pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 0.5rem;
          margin-top: 3rem;
          padding: 2rem 0;
        }
        
        .cw-pagination .page-numbers {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          min-width: 40px;
          height: 40px;
          padding: 0 0.75rem;
          border-radius: 8px;
          text-decoration: none;
          color: #4a5568;
          font-weight: 500;
          transition: all 0.3s ease;
          background: white;
          list-style:none; 
          gap: 10px;
        }
        
        .cw-pagination .page-numbers:hover {
          border-color: #667eea;
          color: #667eea;
          transform: translateY(-1px);
        }
        
        .cw-pagination .current {
          background: #564AFF;
          color: white !important;
          border-color: #667eea;
          box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .cw-pagination .prev,
        .cw-pagination .next {
          padding: 0 1rem;
          font-weight: 600;
        }
        
        .cw-no-posts {
          text-align: center;
          padding: 4rem 2rem;
          color: #666;
          font-size: 1.1rem;
        }
        
        .cw-no-posts::before {
          content: '📝';
          display: block;
          font-size: 3rem;
          margin-bottom: 1rem;
        }
        
        .cw-blog-image.loading {
          background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
          background-size: 200% 100%;
          animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
          0% { background-position: 200% 0; }
          100% { background-position: -200% 0; }
        }
        
		  @media (max-width: 1024px) {
			  .cw-blog-grid {
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 1.5rem;
				padding: 0 1.5rem;
			  }

			  .cw-blog-image {
				height: 220px;
			  }

			  .cw-pagination .page-numbers {
				min-width: 40px;
				height: 40px;
				font-size: 0.85rem;
			  }

			  .cw-pagination .prev,
			  .cw-pagination .next {
				min-width: 60px;
			  }
		}
		
		 @media (max-width: 768px) {
          .cw-hero-background {
            background-attachment: scroll;
            height: 150%;
          }
          
		  .cw-hero-section {
			height: 430px;
		  }

		  .cw-blog-grid {
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
			gap: 1.5rem;
			padding: 0 1rem;
			margin-top: 2rem;
		  }

		  .cw-blog-image {
			height: 200px;
		  }

		  .cw-blog-content {
			padding: 1.25rem;
		  }

		  .cw-blog-title {
			font-size: 1.1rem;
		  }

		  .cw-blog-excerpt {
			font-size: 0.9rem;
		  }

		  .cw-search-input {
			width: 85%;
			height: 45px;
		  }

		  .cw-pagination {
			gap: 0.25rem;
			padding: 1.5rem 0;
		  }

		  .cw-pagination .page-numbers {
			min-width: 36px;
			height: 36px;
			font-size: 0.8rem;
			padding: 0 0.5rem;
		  }

		  .cw-pagination .prev,
		  .cw-pagination .next {
			min-width: 50px;
			font-size: 0.8rem;
		  }
		}

		@media (max-width: 480px) {
		  .cw-blog-grid {
			grid-template-columns: 1fr;
			gap: 1rem;
			padding: 0 0.75rem;
		  }

		  .cw-blog-image {
			height: 180px;
		  }

		  .cw-blog-content {
			padding: 1rem;
		  }

		  .cw-blog-title {
			font-size: 1rem;
			margin-bottom: 0.75rem;
		  }

		  .cw-blog-excerpt {
			font-size: 0.85rem;
		  }

		  .cw-search-input {
			width: 90%;
			height: 42px;
			font-size: 0.9rem;
		  }

		  .cw-pagination {
			gap: 0.125rem;
			padding: 1rem 0;
		  }

		  .cw-pagination .page-numbers {
			min-width: 32px;
			height: 32px;
			font-size: 0.75rem;
			padding: 0 0.25rem;
		  }

		  .cw-pagination .prev,
		  .cw-pagination .next {
			min-width: 45px;
			font-size: 0.75rem;
		  }
		}

		@media (max-width: 375px) {
		  .cw-blog-grid {
			padding: 0 0.5rem;
		  }

		  .cw-blog-image {
			height: 160px;
		  }

		  .cw-blog-content {
			padding: 0.75rem;
		  }

		  .cw-search-input {
			width: 95%;
			height: 40px;
		  }

		  .cw-pagination .page-numbers {
			min-width: 28px;
			height: 28px;
			font-size: 0.7rem;
		  }

		  .cw-pagination .prev,
		  .cw-pagination .next {
			min-width: 40px;
			font-size: 0.7rem;
		  }
		}

		@media (max-width: 320px) {
		  .cw-blog-image {
			height: 140px;
		  }

		  .cw-blog-content {
			padding: 0.5rem;
		  }

		  .cw-search-input {
			width: 98%;
			height: 38px;
			font-size: 0.85rem;
		  }

		  .cw-pagination {
			gap: 0.1rem;
		  }

		  .cw-pagination .page-numbers {
			min-width: 26px;
			height: 26px;
			font-size: 0.65rem;
			padding: 0 0.125rem;
		  }

		  .cw-pagination .prev,
		  .cw-pagination .next {
			min-width: 35px;
			font-size: 0.65rem;
		  }
		}

		.cw-results-container {
		  transition: opacity 0.3s ease;
		  width: 100%;
		  max-width: 1400px;
		  margin: 0 auto;
		}

		.cw-results-container.loading {
		  opacity: 0.6;
		  pointer-events: none;
		}

		.cw-blog-grid {
		  animation: fadeIn 0.5s ease-in-out;
		}

		@keyframes fadeIn {
		  from { 
			opacity: 0; 
			transform: translateY(20px); 
		  }
		  to { 
			opacity: 1; 
			transform: translateY(0); 
		  }
		}

		@media (hover: hover) {
		  .cw-blog-item:hover {
			transform: translateY(-5px);
			box-shadow: 0 8px 30px rgba(0,0,0,0.12);
		  }

		  .cw-blog-item:hover .cw-blog-image img {
			transform: scale(1.05);
		  }

		  .cw-pagination .page-numbers:hover {
			background: #f7fafc;
			border-color: #667eea;
			color: #667eea;
			transform: translateY(-1px);
		  }
		}

		.cw-pagination .page-numbers:focus {
		  outline: 2px solid #564AFF;
		  outline-offset: 2px;
		}

		.cw-search-input:focus {
		  outline: 2px solid #564AFF;
		  outline-offset: 2px;
		}

		@media print {
		  .cw-hero-section,
		  .cw-search-container,
		  .cw-pagination {
			display: none;
		  }

		  .cw-blog-grid {
			grid-template-columns: 1fr;
			gap: 1rem;
		  }

		  .cw-blog-item {
			break-inside: avoid;
			box-shadow: none;
			border: 1px solid #ccc;
		  }
		}
		
        .cw-category-content-writing .cw-hero-background {
          background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/content-writing-header1.webp');
        }
        
        .cw-category-web-hosting .cw-hero-background {
          background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/web-hosting-header.webp');
        }
    ";
    
    $existing_css = $css;
    $combined_css = $existing_css . $additional_css;
    
    wp_add_inline_style('cw-blog-gatherer', $combined_css);
}


// 4) Updated JavaScript with Polylang support
add_action('wp_footer', 'cw_blog_gatherer_scripts');
function cw_blog_gatherer_scripts() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const container = document.querySelector('.cw-blog-container');
        if (!container) return;
        
        const searchInput = document.querySelector('.cw-search-input');
        const searchForm = document.querySelector('.cw-search-form');
        const resultsContainer = document.querySelector('.cw-results-container');
        const searchLoading = document.querySelector('.cw-search-loading');
        
        const category = container.dataset.category;
        const displayName = container.dataset.displayName;
        const postsPerPage = container.dataset.postsPerPage;
        const nonce = container.dataset.nonce;
        const ajaxUrl = container.dataset.ajaxUrl;
        const lang = container.dataset.lang;
        
        let searchTimeout;
        let currentPage = 1;
        let currentSearch = '';
        
        function performSearch(searchQuery = '', page = 1) {
            if (searchLoading) {
                searchLoading.style.display = 'block';
            }
            resultsContainer.classList.add('loading');
            
            const formData = new FormData();
            formData.append('action', 'cw_blog_search');
            formData.append('search_query', searchQuery);
            formData.append('category', category);
            formData.append('paged', page);
            formData.append('posts_per_page', postsPerPage);
            formData.append('nonce', nonce);
            formData.append('lang', lang);
            
            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultsContainer.innerHTML = data.data.html;
                    currentPage = data.data.current_page;
                    
                    attachPaginationListeners();
                    attachImageListeners();
                    attachClearSearchListener();
                    
                    if (page === 1) {
                        resultsContainer.scrollIntoView({ 
                            behavior: 'smooth', 
                            block: 'start' 
                        });
                    }
                } else {
                    console.error('Search failed:', data);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
            })
            .finally(() => {
                if (searchLoading) {
                    searchLoading.style.display = 'none';
                }
                resultsContainer.classList.remove('loading');
            });
        }
        
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                clearTimeout(searchTimeout);
                const searchQuery = this.value.trim();
                
                searchTimeout = setTimeout(() => {
                    if (searchQuery.length >= 3 || searchQuery.length === 0) {
                        currentSearch = searchQuery;
                        performSearch(searchQuery, 1);
                    }
                }, 800);
            });
            
            searchForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const searchQuery = searchInput.value.trim();
                currentSearch = searchQuery;
                performSearch(searchQuery, 1);
            });
        }
        
        function attachPaginationListeners() {
            const paginationLinks = document.querySelectorAll('.cw-pagination a.page-numbers');
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    let page = 1;
                    if (this.classList.contains('prev')) {
                        page = Math.max(1, currentPage - 1);
                    } else if (this.classList.contains('next')) {
                        const maxPages = parseInt(document.querySelector('.cw-pagination').dataset.maxPages);
                        page = Math.min(maxPages, currentPage + 1);
                    } else {
                        page = parseInt(this.textContent) || 1;
                    }
                    
                    performSearch(currentSearch, page);
                });
            });
        }
        
        function attachImageListeners() {
            const blogImages = document.querySelectorAll('.cw-blog-image img');
            blogImages.forEach(img => {
                img.addEventListener('load', function() {
                    this.parentElement.classList.remove('loading');
                });
                
                if (!img.complete) {
                    img.parentElement.classList.add('loading');
                }
            });
        }
        
        function attachClearSearchListener() {
            const clearSearchBtn = document.querySelector('.cw-clear-search');
            if (clearSearchBtn) {
                clearSearchBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    searchInput.value = '';
                    currentSearch = '';
                    performSearch('', 1);
                });
            }
        }
        
        attachPaginationListeners();
        attachImageListeners();
        
        const parallaxElements = document.querySelectorAll('[data-parallax]');
        function updateParallax() {
            parallaxElements.forEach(element => {
                const rect = element.getBoundingClientRect();
                const speed = 0.5;
                const yPos = -(rect.top * speed);
                element.style.transform = `translateY(${yPos}px)`;
            });
        }
        
        let ticking = false;
        function requestTick() {
            if (!ticking) {
                requestAnimationFrame(updateParallax);
                ticking = true;
            }
        }
        
        window.addEventListener('scroll', function() {
            requestTick();
            ticking = false;
        });
        
        updateParallax();
    });
    </script>
    <?php
}