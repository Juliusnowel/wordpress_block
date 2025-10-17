// 1) Add AJAX handler for search
add_action('wp_ajax_cms_blog_search', 'cms_blog_search_ajax');
add_action('wp_ajax_nopriv_cms_blog_search', 'cms_blog_search_ajax');

function cms_blog_search_ajax() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'cms_blog_search_nonce')) {
        wp_die('Security check failed');
    }
    
    $search_query = sanitize_text_field($_POST['search_query']);
    $category = sanitize_text_field($_POST['category']);
    $subcategory = sanitize_text_field($_POST['subcategory'] ?? '');
    $post_type = sanitize_text_field($_POST['post_type'] ?? ''); //Add post type parameter
    $paged = intval($_POST['paged']);
    $posts_per_page = intval($_POST['posts_per_page']);
    
    // Build query args
    $query_args = array(
        'category_name'  => $category,
        'posts_per_page' => $posts_per_page,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
	
	if (empty($post_type)) {
        $query_args['post_type'] = array('how-to', 'news', 'review');
    } else {
        $query_args['post_type'] = $post_type;
    }
    
    // Add search if provided
    if (!empty($search_query)) {
        $query_args['s'] = $search_query;
    }
    
    $query = new WP_Query($query_args);
    
    ob_start();
    
    if ($query->have_posts()) : ?>
        <div class="cms-blog-grid">
            <?php while ($query->have_posts()) : $query->the_post(); ?>
                <article class="cms-blog-item">
                    <a class="cms-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                        <div class="cms-blog-image">
                            <?php if (has_post_thumbnail()) : ?>
                                <img 
                                    src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium_large')); ?>" 
                                    alt="<?php echo esc_attr(get_the_title()); ?>"
                                    loading="lazy"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                >
                                <div class="placeholder-icon" style="display: none;">📄</div>
                            <?php else : ?>
                                <div class="placeholder-icon">📄</div>
                            <?php endif; ?>
                        </div>
                        <div class="cms-blog-content">
                            <h2 class="cms-blog-title">
                                <?php the_title(); ?>
                            </h2>
                            <div class="cms-blog-excerpt"><?php echo wp_trim_words( get_the_excerpt(), 30, '…' ); ?></div>
                        </div>
                    </a>
                </article>
            <?php endwhile; ?>
        </div>
        
        <?php if ($query->max_num_pages > 1) : ?>
            <nav class="cms-pagination" data-max-pages="<?php echo $query->max_num_pages; ?>">
                <?php
					echo paginate_links(array(
						'base'      => '#',
						'format'    => '',
						'current'   => $paged,
						'total'     => $query->max_num_pages,
						'prev_text' => 'Prev',
						'next_text' => 'Next',
						'type'      => 'list',
					));
                ?>
            </nav>
        <?php endif; ?>
    <?php else : ?>
        <div class="cms-no-posts">
            <?php if (!empty($search_query)) : ?>
                No posts found for "<?php echo esc_html($search_query); ?>".
                <br><br>
                <a href="#" class="cms-clear-search" style="color: #564AFF; text-decoration: none;" target="_blank" rel="noopener noreferrer">← View all posts</a>
            <?php else : ?>
                No posts found.
            <?php endif; ?>
        </div>
    <?php endif;
    
    wp_reset_postdata();
    
    $response = array(
        'html' => ob_get_clean(),
        'found_posts' => $query->found_posts,
        'max_pages' => $query->max_num_pages,
        'current_page' => $paged,
        'category' => $category,
        'subcategory' => $subcategory,
        'post_type' => $post_type // NEW: Include post type in response
    );
    
    wp_send_json_success($response);
}

// 2) Updated shortcode function
function cms_blog_gatherer_func($atts) {
    // Get the current URL path and extract category
    $url_parts = array_values(array_filter(explode('/', trim($_SERVER['REQUEST_URI'], '/'))));

    $cms_slug = isset($url_parts[0]) ? $url_parts[0] : '';
    $sub_category = isset($url_parts[1]) ? $url_parts[1] : '';
    
    // Determine category based on URL
    $category = '';
    $category_display = '';
    $category_class = '';
    
    $article_map = [
        'wp-articles' => [        
            'plugins'  => ['category' => 'plugins', 'display' => 'WordPress', 'class' => 'cms-wordpress-gatherer'],
            'wp-themes'   => ['category' => 'wp-themes', 'display' => 'WordPress', 'class' => 'cms-wordpress-gatherer'],
            'default'  => ['category' => 'plugins', 'display' => 'WordPress', 'class' => 'cms-wordpress-gatherer'],
        ],
        'joomla-articles' => [        
            'default'  => ['category' => 'joomla', 'display' => 'joomla', 'class' => 'cms-joomla-gatherer'],
        ],
        'drupal-articles' => [        
            'modules'  => ['category' => 'modules', 'display' => 'Drupal', 'class' => 'cms-drupal-gatherer'],
            'default'  => ['category' => 'modules', 'display' => 'Drupal', 'class' => 'cms-drupal-gatherer'],
        ],
        'wix-articles' =>[        
			'apps'  => ['category' => 'apps', 'display' => 'Wix', 'class' => 'cms-wix-gatherer'],
            'default'  => ['category' => 'apps', 'display' => 'Wix', 'class' => 'cms-wix-gatherer'],
        ],
        'squarespace-articles' => [        
            'extensions'  => ['category' => 'extensions', 'display' => 'Squarespace', 'class' => 'cms-squarespace-gatherer'],
            'ss-themes'   => ['category' => 'ss-themes', 'display' => 'Squarespace', 'class' => 'cms-squarespace-gatherer'],
            'default'  => ['category' => 'extensions', 'display' => 'Squarespace', 'class' => 'cms-squarespace-gatherer'],
        ],
    ];

	if (isset($article_map[$cms_slug])) {
		$cms_data = $article_map[$cms_slug];

		// Try the exact sub-category, otherwise fall back to 'default'.
		$details  = $cms_data[$sub_category] ?? $cms_data['default'];

		$category         = $details['category'];
		$category_display = $details['display'];
		$category_class   = $details['class'];
	} else {
		// global fallback
		$category         = 'content-writing';
		$category_display = 'Content Writing';
		$category_class   = 'cms-category-content-writing';
	}
    
    // Allow override via shortcode attributes
    $atts = shortcode_atts(array(
        'category' => $category,
        'display_name' => $category_display,
        'posts_per_page' => 9,
    ), $atts);
    
    // Initial load - get first page
    $query_args = array(
        'category_name'  => $atts['category'],
        'posts_per_page' => intval($atts['posts_per_page']),
		'post_type'      => array('how-to', 'news', 'review'),
        'paged'          => 1,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    
    $query = new WP_Query($query_args);
    
    ob_start();
    ?>
    <div class="cms-blog-container" 
         data-category="<?php echo esc_attr($atts['category']); ?>"
         data-display-name="<?php echo esc_attr($atts['display_name']); ?>"
         data-posts-per-page="<?php echo esc_attr($atts['posts_per_page']); ?>"
         data-nonce="<?php echo wp_create_nonce('cms_blog_search_nonce'); ?>"
         data-ajax-url="<?php echo admin_url('admin-ajax.php'); ?>"
         data-cms-slug="<?php echo esc_attr($cms_slug); ?>"
         data-sub-category="<?php echo esc_attr($sub_category); ?>"
	>
        
        <div class="cms-hero-section <?php echo esc_attr($category_class); ?>">
            <div class="cms-hero-background" data-parallax></div>
            <div class="cms-hero-overlay"></div>
            <div class="cms-blog-header">
			<?php if ( $atts['display_name'] === $atts['category'] ) : ?>
				<h1><?php echo esc_html( $atts['display_name'] ); ?> Articles</h1>
			<?php else : ?>
				<h1><?php echo esc_html( $atts['display_name'] ); ?> Articles for <span class="cms-subtitle"><?php echo esc_html( $atts['category'] ); ?></span> </h1>
			<?php endif; ?>
       
                <div class="cms-search-container">
                    <form class="cms-search-form">
                        <input 
                            type="text" 
                            name="cms_search" 
                            class="cms-search-input" 
                            placeholder="Search <?php echo esc_attr(strtolower($atts['display_name'])); ?> articles..." 
                            autocomplete="off"
                        >
                        <div class="cms-search-loading" style="display: none;">
                            <div class="cms-spinner"></div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
		
		<?php
		// grab your CMS mapping
		$cms_data = $article_map[ $cms_slug ] ?? [];

		// build a list of real sub-keys (everything except 'default')
		$sub_keys = array_filter(
		  array_keys( $cms_data ),
		  fn( $k ) => $k !== 'default'
		);

		// open the wrapper that contains BOTH nav + dropdown
		echo "<div class='cms-nav'>\n";

		// only render buttons if there’s more than one sub-key
		if ( count( $sub_keys ) > 1 ) {

		  // figure out which one is “default” if none in the URL
		  $default_key = $cms_data['default']['category'] 
						 ?? reset( $sub_keys );

		  echo "  <div class='cms-subcategory-nav'>\n";

		foreach ( $sub_keys as $sub_key ) {
			$details      = $cms_data[ $sub_key ];
			$is_active    = ( $sub_category === $sub_key )
						  || ( ! $sub_category && $sub_key === $default_key );
			$active_class = $is_active ? 'active' : '';

			// Build a human-friendly label from the slug
			$parts      = explode( '-', $details['category'], 2 );
			$afterDash  = isset( $parts[1] ) ? $parts[1] : $parts[0];
			// Now replace any remaining dashes with spaces and uppercase each word
			$label      = ucwords( str_replace( '-', ' ', $afterDash ) );

			printf(
				'<button 
					class="cms-subcategory-btn %s" 
					data-subcategory="%s" 
					data-category="%s" 
					data-display="%s"
				>%s</button>' . "\n",
				esc_attr( $active_class ),
				esc_attr( $sub_key ),
				esc_attr( $details['category'] ),
				esc_attr( $details['display'] ),  // e.g. "WordPress"
				esc_html( $label )                // e.g. "Wp Themes"
			);
		}
		  echo "  </div>\n";  // .cms-subcategory-nav
		}

		// **always** show the post-type dropdown
		?>
		<div>
		</div>
    <div class="dropdown-container">
        <button class="dropdown-button" onclick="toggleDropdown()">
            <span class="dropdown-text">All post types</span>
            <span class="dropdown-arrow">▼</span>
        </button>
        
        <div class="dropdown-menu" id="dropdownMenu">
            <button class="dropdown-item selected" onclick="selectItem(this, 'All post types', '')">All post types</button>
            <button class="dropdown-item" onclick="selectItem(this, 'How-tos', 'how-to')">How-tos</button>
            <button class="dropdown-item" onclick="selectItem(this, 'News', 'news')">News</button>
            <button class="dropdown-item" onclick="selectItem(this, 'Reviews', 'review')">Reviews</button>
        </div>
    </div>
		<?php

		// close the wrapper
		echo "</div>\n";  // .cms-nav
		?>	
        <div class="cms-results-container">
            <?php if ($query->have_posts()) : ?>
                <div class="cms-blog-grid">
                    <?php while ($query->have_posts()) : $query->the_post(); ?>
                        <article class="cms-blog-item">
                            <a class="cms-blog-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener noreferrer">
                                <div class="cms-blog-image">
                                    <?php if (has_post_thumbnail()) : ?>
                                        <img 
                                            src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium_large')); ?>" 
                                            alt="<?php echo esc_attr(get_the_title()); ?>"
                                            loading="lazy"
                                            onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                        >
                                        <div class="placeholder-icon" style="display: none;">📄</div>
                                    <?php else : ?>
                                        <div class="placeholder-icon">📄</div>
                                    <?php endif; ?>
                                </div>
                                <div class="cms-blog-content">
                                    <h2 class="cms-blog-title">
                                        <?php the_title(); ?>
                                    </h2>
                                    <div class="cms-blog-excerpt"><?php echo wp_trim_words( get_the_excerpt(), 30, '…' ); ?></div>
                                </div>
                            </a>
                        </article>
                    <?php endwhile; ?>
                </div>
                
                <?php if ($query->max_num_pages > 1) : ?>
                    <nav class="cms-pagination" data-max-pages="<?php echo $query->max_num_pages; ?>">
                        <?php
                        echo paginate_links(array(
                            'base'      => '#',
                            'format'    => '',
                            'current'   => 1,
                            'total'     => $query->max_num_pages,
                            'prev_text' => 'Prev',
                            'next_text' => 'Next',
                            'type'      => 'list',
                        ));
                        ?>
                    </nav>
                <?php endif; ?>
            <?php else : ?>
                <div class="cms-no-posts">
                    No articles found.
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}
add_shortcode('cms_blog_gatherer', 'cms_blog_gatherer_func');

// 3) Updated CSS (add these styles to your existing CSS)
add_action('wp_enqueue_scripts', 'cms_blog_gatherer_inline_styles');
function cms_blog_gatherer_inline_styles() {
    wp_register_style('cms-blog-gatherer', false);
    wp_enqueue_style('cms-blog-gatherer');
    
    $additional_css = "
	    .dropdown-container {
            position: relative;
            display: inline-block;
            width: 200px;
        }

        .dropdown-button {
            width: 100%;
            padding: 12px 16px;
            background: #5A4AFF;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 400;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.2s ease;
            box-shadow: none;
        }

        .dropdown-button:hover {
            background: #4A3AE8;
        }

        .dropdown-button:active {
            background: #3A2AD0;
        }

        .dropdown-arrow {
            font-size: 12px;
            transition: transform 0.3s ease;
            margin-left: 8px;
        }

        .dropdown-button.open .dropdown-arrow {
            transform: rotate(180deg);
        }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e5e7eb;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-4px);
            transition: all 0.2s ease;
            margin-top: 2px;
            overflow: hidden;
        }

        .dropdown-menu.open {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .dropdown-item {
            padding: 10px 16px;
            color: #374151;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.1s ease;
            border: none;
            background: none;
            width: 100%;
            text-align: left;
            font-weight: 400;
        }

        .dropdown-item:hover {
            background-color: #f8fafc;
        }

        .dropdown-item:not(:last-child) {
            border-bottom: 1px solid #f1f5f9;
        }

        .dropdown-item.selected {
            background-color: #5A4AFF;
            color: white;
            font-weight: 400;
        }
		@media (max-width: 1023px){
			.dropdown-button {
			   font-size: 15px;
				padding: 10px 16px;
			}
		}
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .dropdown-container {
                width: 100%;
            }
            
            .dropdown-button {
                padding: 15px 16px;
                font-size: 15px;
            }
            
            .dropdown-item {
                padding: 11px 16px;
                font-size: 15px;
            }
        }
		@media (max-width: 480px){
			.dropdown-button {
            	padding: 12px 16px;
        	}	
		}
		.cms-nav {
			width: 100%;
			max-width: 1400px;
			margin: 0 auto;
			padding: 0 2rem;
			margin-top: 4rem;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 1rem;
		}

		/* Subcategory navigation */
		.cms-subcategory-nav {
			display: inline-flex;
			background-color: #5A4AFF;
			border-radius: 8px;
			overflow: visible;
			padding: 3px;
			position: relative;
			width: auto;
			min-width: 0;
		}

		.cms-subcategory-btn {
			background-color: transparent;
			border: none;
			color: white;
			padding: 8px 16px; /* Original padding preserved */
			font-size: 14px;
			cursor: pointer;
			border-radius: 8px;
			transition: all 0.3s ease;
			white-space: nowrap;
			outline: none;
			text-decoration: none;
			display: flex;
			align-items: center;
			justify-content: center;
			box-sizing: border-box;
		}

		.cms-subcategory-btn.active {
			background-color: #2A1DB8;
			color: white;
		}

		.cms-subcategory-btn:hover,
		.cms-subcategory-btn:focus {
			background-color: rgba(42, 29, 184, 0.7);
			outline: 2px solid rgba(255, 255, 255, 0.3);
			outline-offset: 2px;
		}

		.cms-subcategory-btn:active {
			transform: translateY(1px);
		}

		/* Large screens and desktop (1024px and above) */
		@media (min-width: 1024px) {
			.cms-nav {
				flex-wrap: nowrap;
				gap: 2rem;
			}

			.cms-subcategory-nav {
				display: inline-flex;
				flex-direction: row;
				flex-wrap: nowrap;
				gap: 0;
			}

			.cms-subcategory-btn {
				padding: 8px 20px; /* Slightly more padding on desktop */
				font-size: 1rem;
			}

		}

		/* Tablet landscape and medium screens (769px to 1023px) */
		@media (min-width: 769px) and (max-width: 1023px) {
			.cms-nav {
				padding: 0 1.5rem;
				gap: 1.5rem;
			}

			.cms-subcategory-nav {
				display: inline-flex;
				flex-direction: row;
				flex-wrap: wrap;
				gap: 2px;
				max-width: 100%;
			}

			.cms-subcategory-btn {
				padding: 8px 14px; /* Original padding maintained */
				font-size: 13px;
				flex-shrink: 1;
				min-width: 80px;
			}
		}

		/* Tablet portrait and small screens (481px to 768px) */
		@media (min-width: 481px) and (max-width: 768px) {
			.cms-nav {
				   padding: 0 1rem;
			}
			.cms-subcategory-btn.active {
				background-color: #5A4AFF;
			}
			.cms-subcategory-nav {
				position: relative;
				display: inline-block;
				width: 100%;
				max-width: 320px;
				margin: 0 auto;
				padding: 0px;
			}

			/* Hide inactive buttons initially */
			.cms-subcategory-btn:not(.active) {
				display: none;
				position: absolute;
				top: 100%;
				left: 0;
				right: 0;
				background-color: #5A4AFF;
				z-index: 1000;
				border: 1px solid #e5e7eb;
				border-radius: 6px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
			}

			/* Active button as dropdown trigger */
			.cms-subcategory-btn.active {
				width: 100%;
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 12px 16px; /* Adjusted for mobile but keeping proportions */
				font-size: 16px;
				position: relative;
 				min-height: 48px;
			}

			/* Dropdown arrow */
			.cms-subcategory-btn.active::after {
				content: '▼';
				font-size: 12px;
				transition: transform 0.3s ease;
				margin-left: 8px;
			}

			/* Show dropdown on hover/focus */
			.cms-subcategory-nav:hover .cms-subcategory-btn:not(.active),
			.cms-subcategory-nav:focus-within .cms-subcategory-btn:not(.active),
			.cms-subcategory-nav.open .cms-subcategory-btn:not(.active) {
				display: block;
			}

			/* Rotate arrow when open */
			.cms-subcategory-nav:hover .cms-subcategory-btn.active::after,
			.cms-subcategory-nav:focus-within .cms-subcategory-btn.active::after,
			.cms-subcategory-nav.open .cms-subcategory-btn.active::after {
				transform: rotate(180deg);
			}

			/* Position dropdown items */
			.cms-subcategory-btn:not(.active) {
				padding: 12px 16px;
				font-size: 16px;
				width: 100%;
				text-align: left;
				min-height: 48px;
				justify-content: flex-start;
				background-color: white;
				color: #374151;
				margin-top: 2px;
				border: 1px solid #e5e7eb;
				border-radius: 6px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
				transition: all 0.2s ease;
				z-index: 1000;
			}

			.cms-subcategory-btn:not(.active):first-of-type {
				border-radius: 6px;
				transition: all 0.2s ease;
			}

			/* Stack items with proper spacing */
			.cms-subcategory-btn:not(.active):nth-child(2) { top: 100%; }
			.cms-subcategory-btn:not(.active):nth-child(3) { top: calc(100% + 49px); }
			.cms-subcategory-btn:not(.active):nth-child(4) { top: calc(100% + 98px); }
			.cms-subcategory-btn:not(.active):nth-child(5) { top: calc(100% + 147px); }
			.cms-subcategory-btn:not(.active):nth-child(6) { top: calc(100% + 196px); }
			.cms-subcategory-btn:not(.active):nth-child(7) { top: calc(100% + 245px); }
			.cms-subcategory-btn:not(.active):nth-child(8) { top: calc(100% + 294px); }
		}

		/* Mobile screens (320px to 480px) */
		@media (max-width: 480px) {
			.cms-nav {
				flex-direction: column;
				align-items: center;
				gap: 1rem;
				padding: 0 1rem;
				margin-top: 2rem;
			}

			.cms-subcategory-nav {
				position: relative;
				display: block;
				width: 100%;
				max-width: 100%;
				margin: 0 auto;
				padding: 0px;
			}
			.cms-subcategory-btn.active{
				background-color: #5A4AFF;
				padding: 0px;
				margin: 0px;
			}

			.cms-subcategory-btn:not(.active) {
				display: none;
				position: absolute;
				top: 100%;
				left: 0;
				right: 0;
				background-color: white;
				color:black;
				border-radius: 6px;
				z-index: 1000;
				border: 1px solid #e5e7eb;
				border-radius: 6px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
				margin-top: 2px;
			}

			.cms-subcategory-btn.active {
				width: 100%;
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 12px 16px; /* Maintaining original padding ratio */
				font-size: 15px;
				position: relative;
			}

			.cms-subcategory-btn.active::after {
				content: '▼';
				font-size: 12px;
				transition: transform 0.3s ease;
				margin-left: 8px;
			}

			.cms-subcategory-nav:hover .cms-subcategory-btn:not(.active),
			.cms-subcategory-nav:focus-within .cms-subcategory-btn:not(.active),
			.cms-subcategory-nav.open .cms-subcategory-btn:not(.active) {
				display: block;
			}

			.cms-subcategory-nav:hover .cms-subcategory-btn.active::after,
			.cms-subcategory-nav:focus-within .cms-subcategory-btn.active::after,
			.cms-subcategory-nav.open .cms-subcategory-btn.active::after {
				transform: rotate(180deg);
			}

			.cms-subcategory-btn:not(.active) {
				padding: 12px 16px;
				font-size: 15px;
				width: 100%;
				text-align: left;
				min-height: 48px;
				justify-content: flex-start;
			}

			/* Position items for mobile */
			.cms-subcategory-btn:not(.active):nth-child(2) { top: 100%; }
			.cms-subcategory-btn:not(.active):nth-child(3) { top: calc(100% + 49px); }
			.cms-subcategory-btn:not(.active):nth-child(4) { top: calc(100% + 98px); }
			.cms-subcategory-btn:not(.active):nth-child(5) { top: calc(100% + 147px); }
			.cms-subcategory-btn:not(.active):nth-child(6) { top: calc(100% + 196px); }
			.cms-subcategory-btn:not(.active):nth-child(7) { top: calc(100% + 245px); }
			.cms-subcategory-btn:not(.active):nth-child(8) { top: calc(100% + 294px); }
		}

		/* Extra small screens (below 320px) */
		@media (max-width: 319px) {
			.cms-nav {
				padding: 0 0.5rem;
				margin-top: 1.5rem;
			}

			.cms-subcategory-nav {
				min-width: 100%;
			}

			.cms-subcategory-btn {
				padding: 8px 12px; /* Maintaining original padding structure */
				font-size: 14px;
			}

			.cms-subcategory-btn.active {
				padding: 10px 12px;
				font-size: 14px;
			}

		}

		/* Accessibility improvements */
		.cms-subcategory-btn:focus {
			outline: 2px solid white;
			outline-offset: 2px;
		}

		/* High contrast mode support */
		@media (prefers-contrast: high) {
			.cms-subcategory-btn:hover,
			.cms-subcategory-btn:focus {
				outline: 3px solid currentColor;
			}
		}

		/* Touch-friendly hover states */
		@media (hover: hover) {
			.cms-subcategory-btn:hover {
				background-color: rgba(42, 29, 184, 0.7);
			}
		}

		/* Improved focus management */
		.cms-subcategory-btn:focus-visible{
			outline: 2px solid #ffffff;
			outline-offset: 2px;
		}

		/* Loading states */
		.cms-nav.loading {
			opacity: 0.7;
			pointer-events: none;
		}

		.cms-nav.loading::after {
			content: '';
			position: absolute;
			top: 50%;
			left: 50%;
			width: 20px;
			height: 20px;
			margin: -10px 0 0 -10px;
			border: 2px solid #5A4AFF;
			border-radius: 50%;
			border-top-color: transparent;
			animation: spin 1s linear infinite;
		}

		@keyframes spin {
			to { transform: rotate(360deg); }
		}

		/* Smooth scrolling for containers */
		@media (max-width: 768px) {
			.cms-nav {
				scroll-behavior: smooth;
				
			}
		}
		/* Print styles */
		@media print {
			.cms-nav {
				display: none;
			}
		}
    ";

    $css = "
		.cms-blog-link {
		   height: 100%;
		   display: block;       /* make the <a> cover its entire container */
		   text-decoration: none;
		   color: inherit;       /* so headlines/excerpts don't look like hyperlinks by default */
		}
		
        .cms-hero-section {
          /* Full viewport width */
          width: 100vw;
          position: relative;
          left: 50%;
          transform: translateX(-50%);
          /* Parallax container */
          height: 430px;
          overflow: hidden;
          display: flex;
          align-items: center;
          justify-content: center;
        }
        
        .cms-hero-background {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 130%;
          /* WordPress Cover Block Parallax Effect */
          background-size: auto;
          background-position: 50% 50%;
          background-repeat: repeat;
          background-attachment: fixed;
          /* Dynamic background based on category */
          background-image: var(--hero-bg-image);
          /* WordPress-style parallax transform */
          will-change: transform;
          transform: translate3d(0, 0, 0);
		  transform: translateY(-65.1398px);
        }
        
        .cms-hero-overlay {
          position: absolute;
          top: 0;
          left: 0;
          width: 100%;
          height: 100%;
          background: linear-gradient(173deg, rgb(125, 117, 244) 0%, rgb(142, 106, 196) 28%, rgb(86, 74, 255) 76%);
          opacity: 0.8;
        }
        
        .cms-blog-header {
          position: relative;
          z-index: 2;
          text-align: center;
          margin: 13rem 0 13rem 0;
          padding: 0;
          color: white;
          width: 100%;
        }
        
        .cms-blog-header h1 {
          margin: 0 0 0 0;
          font-weight: 700;
          color: white;
          text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .cms-blog-header .subtitle {
          margin: 0 0 0 0;
		  color: white;
          font-weight: 700;
          text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .cms-search-container {
          position: relative;
          width: 100%;
          margin: 0 auto;
          justify-content: center;  
          margin-top: 1rem;
        }
        
        .cms-search-input {
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
        
        .cms-search-input::placeholder {
          color: #666;
        }
        
        .cms-search-input:focus {
          background: white;
          border-color: #564AFF;
          box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
        
        .cms-search-icon {
          position: absolute;
          right: 1rem;
          top: 50%;
          transform: translateY(-50%);
          color: #999;
          font-size: 1.1rem;
        }
        
        .cms-blog-grid {
          margin-top: 1rem;
          display: grid;
          grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
          gap: 2rem;
          padding: 0 2rem;
        }
        
        .cms-blog-item {
          border-radius: 8px;
          overflow: hidden;
          transition: all 0.3s ease;
        }
        
        .cms-blog-item:hover {
          transform: translateY(-5px);
          box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .cms-blog-image {
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
        
        .cms-blog-image img {
          width: 100%;
          height: 100%;
          object-fit: cover;
          object-position: center;
          transition: transform 0.3s ease;
        }
        
        .cms-blog-item:hover .cms-blog-image img {
          transform: scale(1.05);
        }
        
        .cms-blog-image .placeholder-icon {
          display: flex;
          align-items: center;
          justify-content: center;
          width: 100%;
          height: 100%;
          font-size: 3rem;
          color: #999;
          background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }
        
        .cms-blog-content {
          padding: .95rem;
        }
        
        .cms-blog-title {
          margin: 0 0 1rem 0;
          font-size: 24px;
          font-weight: 700;
          color: #2d3748;
          text-decoration: none;
          transition: color 0.3s ease;
        }
        
		.cms-blog-title:hover {
          color: #667eea;
        }
        
        .cms-blog-excerpt {
		  font-size: 15px;
		  font-weight: 500;
          color: #666;
          margin: 0;
        }
        
        .cms-pagination {
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 0.5rem;
          margin-top: 3rem;
          padding: 2rem 0;
        }
        
        .cms-pagination .page-numbers {
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
        
        .cms-pagination .page-numbers:hover {
          border-color: #667eea;
          color: #667eea;
          transform: translateY(-1px);
        }
        
        .cms-pagination .current {
          background: #564AFF;
          color: white !important;
          border-color: #667eea;
          box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .cms-pagination .prev,
        .cms-pagination .next {
          padding: 0 1rem;
          font-weight: 600;
        }
        
        .cms-no-posts {
          text-align: center;
          padding: 4rem 2rem;
          color: #666;
          font-size: 1.1rem;
        }
        
        .cms-no-posts::before {
          content: '📝';
          display: block;
          font-size: 3rem;
          margin-bottom: 1rem;
        }
        
        /* Loading state for images */
        .cms-blog-image.loading {
          background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
          background-size: 200% 100%;
          animation: loading 1.5s infinite;
        }
        
        @keyframes loading {
          0% { background-position: 200% 0; }
          100% { background-position: -200% 0; }
        }
        
		  @media (max-width: 1024px) {
			  .cms-blog-grid {
				grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
				gap: 1.5rem;
				padding: 0 1.5rem;
			  }

			  .cms-blog-image {
				height: 220px;
			  }

			  .cms-pagination .page-numbers {
				min-width: 40px;
				height: 40px;
				font-size: 0.85rem;
			  }

			  .cms-pagination .prev,
			  .cms-pagination .next {
				min-width: 60px;
			  }	
		}
		
		 @media (max-width: 768px) {
          .cms-hero-background {
            background-attachment: scroll;
            height: 150%;
          }

		  .cms-hero-section {
			height: 430px;
		  }

		  .cms-blog-grid {
			grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
			gap: 1.5rem;
			padding: 0 1rem;
			margin-top: 2rem;
		  }

		  .cms-blog-image {
			height: 200px;
		  }

		  .cms-blog-content {
			padding: 1.25rem;
		  }

		  .cms-blog-title {
			font-size: 1.1rem;
		  }

		  .cms-blog-excerpt {
			font-size: 0.9rem;
		  }

		  .cms-search-input {
			width: 85%;
			height: 45px;
		  }

		  .cms-pagination {
			gap: 0.25rem;
			padding: 1.5rem 0;
		  }

		  .cms-pagination .page-numbers {
			min-width: 36px;
			height: 36px;
			font-size: 0.8rem;
			padding: 0 0.5rem;
		  }

		  .cms-pagination .prev,
		  .cms-pagination .next {
			min-width: 50px;
			font-size: 0.8rem;
		  }
		}

		/* Large phones */
		@media (max-width: 480px) {

		  .cms-blog-grid {
			grid-template-columns: 1fr;
			gap: 1rem;
			padding: 0 0.75rem;
		  }

		  .cms-blog-image {
			height: 180px;
		  }

		  .cms-blog-content {
			padding: 1rem;
		  }

		  .cms-blog-title {
			font-size: 1rem;
			margin-bottom: 0.75rem;
		  }

		  .cms-blog-excerpt {
			font-size: 0.85rem;
		  }

		  .cms-search-input {
			width: 90%;
			height: 42px;
			font-size: 0.9rem;
		  }

		  .cms-pagination {
			gap: 0.125rem;
			padding: 1rem 0;
		  }

		  .cms-pagination .page-numbers {
			min-width: 32px;
			height: 32px;
			font-size: 0.75rem;
			padding: 0 0.25rem;
		  }

		  .cms-pagination .prev,
		  .cms-pagination .next {
			min-width: 45px;
			font-size: 0.75rem;
		  }
		}

		/* Small phones */
		@media (max-width: 375px) {
		  .cms-blog-grid {
			padding: 0 0.5rem;
		  }

		  .cms-blog-image {
			height: 160px;
		  }

		  .cms-blog-content {
			padding: 0.75rem;
		  }

		  .cms-search-input {
			width: 95%;
			height: 40px;
		  }

		  .cms-pagination .page-numbers {
			min-width: 28px;
			height: 28px;
			font-size: 0.7rem;
		  }

		  .cms-pagination .prev,
		  .cms-pagination .next {
			min-width: 40px;
			font-size: 0.7rem;
		  }
		}

		/* Extra small screens */
		@media (max-width: 320px) {
		  .cms-blog-image {
			height: 140px;
		  }

		  .cms-blog-content {
			padding: 0.5rem;
		  }

		  .cms-search-input {
			width: 98%;
			height: 38px;
			font-size: 0.85rem;
		  }

		  .cms-pagination {
			gap: 0.1rem;
		  }

		  .cms-pagination .page-numbers {
			min-width: 26px;
			height: 26px;
			font-size: 0.65rem;
			padding: 0 0.125rem;
		  }

		  .cms-pagination .prev,
		  .cms-pagination .next {
			min-width: 35px;
			font-size: 0.65rem;
		  }
		}

		/* Container adjustments */
		.cms-results-container {
		  transition: opacity 0.3s ease;
		  width: 100%;
		  max-width: 1400px;
		  margin: 0 auto;
		}

		.cms-results-container.loading {
		  opacity: 0.6;
		  pointer-events: none;
		}

		/* Improved animation for blog items */
		.cms-blog-grid {
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

		/* Better hover effects on touch devices */
		@media (hover: hover) {
		  .cms-blog-item:hover {
			transform: translateY(-5px);
			box-shadow: 0 8px 30px rgba(0,0,0,0.12);
		  }

		  .cms-blog-item:hover .cms-blog-image img {
			transform: scale(1.05);
		  }

		  .cms-pagination .page-numbers:hover {
			background: #f7fafc;
			border-color: #667eea;
			color: #667eea;
			transform: translateY(-1px);
		  }
		}

		/* Accessibility improvements */
		.cms-pagination .page-numbers:focus {
		  outline: 2px solid #564AFF;
		  outline-offset: 2px;
		}

		.cms-search-input:focus {
		  outline: 2px solid #564AFF;
		  outline-offset: 2px;
		}

		/* Print styles */
		@media print {
		  .cms-hero-section,
		  .cms-search-container,
		  .cms-pagination {
			display: none;
		  }

		  .cms-blog-grid {
			grid-template-columns: 1fr;
			gap: 1rem;
		  }

		  .cms-blog-item {
			break-inside: avoid;
			box-shadow: none;
			border: 1px solid #ccc;
		  }
		}
		
        /* Category-specific background images */
        .cms-category-content-writing .cms-hero-background {
          background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/content-writing-header1.webp');
        }
        
        .cms-category-web-hosting .cms-hero-background {
          background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/web-hosting-header.webp');
        }
		
		.cms-wordpress-gatherer .cms-hero-background {
		  background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/wordpress-gatherer-header.png');
		}
		
		.cms-drupal-gatherer .cms-hero-background {
		  background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/drupal-gatherer-header.png');
		}
		
		.cms-joomla-gatherer .cms-hero-background {
		  background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/joomla-gatherer-header.png');
		}
		
		.cms-wix-gatherer .cms-hero-background {
		  background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/wix-gatherer-header.png');
		}
		
		.cms-squarespace-gatherer .cms-hero-background {
		  background-image: url('http://wixmediagroup.com/wp-content/uploads/2025/07/squarespace-gatherer-header.png');
		}
    ";
    
    // Combine CSS
    $existing_css = $css;
    $combined_css = $existing_css . $additional_css;

    wp_add_inline_style('cms-blog-gatherer', $combined_css);
}

// 4)JavaScript with AJAX functionality
add_action('wp_footer', 'cms_blog_gatherer_scripts');
function cms_blog_gatherer_scripts() {
    $article_defaults = [
        'wp-articles'        => 'plugins',
        'joomla-articles'    => '',
        'drupal-articles'    => 'modules',
        'wix-articles'       => 'apps',
        'squarespace-articles' => 'extensions',
    ];    
    ?>
    <script>
		document.addEventListener('DOMContentLoaded', () => {	
			
			const articleDefaults = <?php echo json_encode($article_defaults); ?>;
			const pathParts = window.location.pathname.split('/').filter(Boolean);
			if (pathParts.length === 1) {
				const cmsSlug = pathParts[0];
				const defaultSub = articleDefaults[cmsSlug];

				if (defaultSub) {
					const newUrl = `/${cmsSlug}/${defaultSub}/`;
					history.replaceState(null, '', newUrl);
				}
			}
			
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

			function debounce(func, delay) {
			  let timeoutId;

			  const debounced = function (...args) {
				clearTimeout(timeoutId);
				timeoutId = setTimeout(() => {
				  func.apply(this, args);
				}, delay);
			  };

			  debounced.cancel = () => {
				clearTimeout(timeoutId);
			  };

			  return debounced;
			}
						
			// AJAX search function include post type
			function performSearch(searchQuery = '', page = 1, newCategory = null, newSubCategory = null, postType = null) {
				
				// Update category if provided
				if (newCategory) {
					category = newCategory;
				}

				// Update subcategory if provided
				if (newSubCategory) {
					subCategory = newSubCategory;
				}

				// Update post type if provided
				if (postType) {
					currentPostType = postType;
				}

				// Show loading state
				if (searchLoading) {
					searchLoading.style.display = 'block';
				}
				resultsContainer.classList.add('loading');

				// Prepare form data - UPDATED to include post type
				const formData = new FormData();
				formData.append('action', 'cms_blog_search');
				formData.append('search_query', searchQuery);
				formData.append('category', category);
				formData.append('subcategory', subCategory);
				formData.append('post_type', currentPostType);
				formData.append('paged', page);
				formData.append('posts_per_page', postsPerPage);
				formData.append('nonce', nonce);

				// Make AJAX request
				fetch(ajaxUrl, {
					method: 'POST',
					body: formData
				})
				.then(response => response.json())
				.then(data => {
					if (data.success) {
						// Update results
						resultsContainer.innerHTML = data.data.html;
						currentPage = data.data.current_page;

						// Re-attach pagination event listeners
						attachPaginationListeners();

						// Re-attach image loading effects
						attachImageListeners();

						// Re-attach clear search listener
						attachClearSearchListener();

					} else {
						console.error('Search failed:', data);
					}
				})
				.catch(error => {
					console.error('AJAX error:', error);
				})
				.finally(() => {
					// Hide loading state
					if (searchLoading) {
						searchLoading.style.display = 'none';
					}
					resultsContainer.classList.remove('loading');
				});
			}
			
			const debouncedSearch = debounce(performSearch, 300);
			
			// Initialize the blog system
			const container = document.querySelector('.cms-blog-container');
			if (!container) return;

			const searchInput = document.querySelector('.cms-search-input');
			const searchForm = document.querySelector('.cms-search-form');
			const resultsContainer = document.querySelector('.cms-results-container');
			const searchLoading = document.querySelector('.cms-search-loading');
			const subcategoryBtns = document.querySelectorAll('.cms-subcategory-btn');
			const postTypeSelect = document.querySelectorAll('.dropdown-menu');
			const mainTitle = document.querySelector('.cms-main-title');
			const subtitle      = document.querySelector('.cms-subtitle');
// 			const selectedValue = document.querySelector('.dropdown-button').dataset.value;

			// Get data attributes
			let category = container.dataset.category;
			let displayName = container.dataset.displayName;
			const postsPerPage = container.dataset.postsPerPage;
			const nonce = container.dataset.nonce;
			const ajaxUrl = container.dataset.ajaxUrl;
			const cmsSlug = container.dataset.cmsSlug;
			let subCategory = container.dataset.subCategory;
			let currentPostType = '';

			let searchTimeout;
			let currentPage = 1;
			let currentSearch = '';

			// Update URL without page reload
			function updateUrl(newSubCategory) {
				const newUrl = `/${cmsSlug}/${newSubCategory}/`;
				history.pushState({ subcategory: newSubCategory }, '', newUrl);
			}

			//Post type selection event listener
			if (postTypeSelect) {
				document.addEventListener('dropdownChange', function(event) {
					currentPostType = event.detail.value;
					// Perform search with new post type
					performSearch(currentSearch, 1, null, null, currentPostType);
				});
			}

			// Subcategory button event listeners - UPDATED to reset post type
			subcategoryBtns.forEach(btn => {
				btn.addEventListener('click', function() {
					
					debouncedSearch.cancel();
					
					if (this.classList.contains('active')) {
						return;
					}
					// Remove active class from all buttons
					subcategoryBtns.forEach(b => b.classList.remove('active'));

					// Add active class to clicked button
					this.classList.add('active');

					// Get new category data
					const newSubCategory = this.dataset.subcategory;

					const newDisplay  = btn.dataset.display;
					const newCategory = btn.dataset.category;
					
					// update the headings
					if ( mainTitle ) mainTitle.textContent = `${newDisplay} Articles for`;
					if ( subtitle ) {
					  let text = newCategory;
					  const dashPos = newCategory.lastIndexOf('-');
					  if ( dashPos !== -1 ) {
						text = newCategory.substring(dashPos + 1);
					  }
					  subtitle.textContent = text;
					}

					// Update URL
					updateUrl(newSubCategory);

					// Update search placeholder
					if (searchInput) {
						searchInput.placeholder = `Search ${newDisplay.toLowerCase()} articles...`;
					}

					// Reset post type selector
					if (postTypeSelect) {
						postTypeSelect.value = '';
						currentPostType = '';
						document.querySelector('.dropdown-text').textContent = 'All post types';
						document.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));

					}

					// Clear search and perform new search
					if (searchInput) {
						searchInput.value = '';
					}
					currentSearch = '';

					// Perform search with new category
					debouncedSearch('', 1, newCategory, newSubCategory, '');
				});
			});

			// Search input event listener to include post type
			if (searchInput) {
				searchInput.addEventListener('input', function() {
					clearTimeout(searchTimeout);
					const searchQuery = this.value.trim();

					searchTimeout = setTimeout(() => {
						if (searchQuery.length >= 3 || searchQuery.length === 0) {
							currentSearch = searchQuery;
							performSearch(searchQuery, 1, null, null, currentPostType);
						}
					}, 800);
				});

				// Prevent form submission
				searchForm.addEventListener('submit', function(e) {
					e.preventDefault();
					const searchQuery = searchInput.value.trim();
					currentSearch = searchQuery;
					performSearch(searchQuery, 1, null, null, currentPostType);
				});
			}

			// Pagination event listeners - UPDATED to include post type
			function attachPaginationListeners() {
				const paginationLinks = document.querySelectorAll('.cms-pagination a.page-numbers');
				paginationLinks.forEach(link => {
					link.addEventListener('click', function(e) {
						e.preventDefault();

						let page = 1;
						if (this.classList.contains('prev')) {
							page = Math.max(1, currentPage - 1);
						} else if (this.classList.contains('next')) {
							const maxPages = parseInt(document.querySelector('.cms-pagination').dataset.maxPages);
							page = Math.min(maxPages, currentPage + 1);
						} else {
							page = parseInt(this.textContent) || 1;
						}

						performSearch(currentSearch, page, null, null, currentPostType);
					});
				});
			}
			
			// Image loading effects
			function attachImageListeners() {
				const blogImages = document.querySelectorAll('.cms-blog-image img');
				blogImages.forEach(img => {
					img.addEventListener('load', function() {
						this.parentElement.classList.remove('loading');
					});

					if (!img.complete) {
						img.parentElement.classList.add('loading');
					}
				});
			}

			// Clear search listener - UPDATED to include post type
			function attachClearSearchListener() {
				const clearSearchBtn = document.querySelector('.cms-clear-search');
				if (clearSearchBtn) {
					clearSearchBtn.addEventListener('click', function(e) {
						e.preventDefault();
						searchInput.value = '';
						currentSearch = '';
						performSearch('', 1, null, null, currentPostType);
					});
				}
			}

			// Initialize event listeners
			attachPaginationListeners();
			attachImageListeners();

			// Auto-load default subcategory on page load
			if (
				subcategoryBtns.length > 0 &&        // ← only if we rendered >0 buttons
				(!subCategory) &&                     // ← and no “/slug/...”
				articleDefaults[cmsSlug]              // ← and we have a default in the map
			) {
				const defaultSub = articleDefaults[cmsSlug];
				const defaultBtn = document.querySelector(
					`.cms-subcategory-btn[data-subcategory="${defaultSub}"]`
				);
				if (defaultBtn) {
					// give the DOM a moment to render
					setTimeout(() => defaultBtn.click(), 100);
				}
			}

		});
		
        function toggleDropdown() {
            const button = document.querySelector('.dropdown-button');
            const menu = document.getElementById('dropdownMenu');
            
            button.classList.toggle('open');
            menu.classList.toggle('open');
        }

        function selectItem(item, text, value) {
            // Remove selected class from all items
            document.querySelectorAll('.dropdown-item').forEach(i => i.classList.remove('selected'));
            
            // Add selected class to clicked item
            item.classList.add('selected');
            
            // Update button text
            document.querySelector('.dropdown-text').textContent = text;
            
            // Store the value (you can use this for form submission or API calls)
            document.querySelector('.dropdown-button').dataset.value = value;
            
            // Close dropdown
            document.querySelector('.dropdown-button').classList.remove('open');
            document.getElementById('dropdownMenu').classList.remove('open');
            
            // Optional: trigger a custom event with the selected value
            document.dispatchEvent(new CustomEvent('dropdownChange', { detail: { value: value, text: text } }));
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const container = document.querySelector('.dropdown-container');
            if (!container.contains(event.target)) {
                document.querySelector('.dropdown-button').classList.remove('open');
                document.getElementById('dropdownMenu').classList.remove('open');
            }
        });
    </script>
    <?php
}