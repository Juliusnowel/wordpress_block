// 1) Register the [definitions_page] shortcode
add_shortcode( 'definitions_page', 'wd_definitions_page_shortcode' );
function wd_definitions_page_shortcode() {
    ob_start(); ?>
<style>
.wd-definitions-wrap {
  max-width: 1200px;
  margin: 0 auto;
  padding: 20px;
}

/* center input & make it block */
#wd-search-input {
  display: block;
  width: 100%;
  max-width: 600px;
  margin: 50px auto 10px;
  padding: 15px 12px;
  border: 2px solid #564AFF;
  border-radius: 4px;
  font-weight: 500;
  font-size: 16px; /* Prevent zoom on iOS */
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
  box-sizing: border-box;
}
#wd-search-input::placeholder { 
  font-size: 16px; 
  opacity: 0.7;
}
#wd-search-input:focus {
  outline: none;
  border-color: #4038D6;
  box-shadow: 0 0 0 2px rgba(86, 74, 255, 0.2);
}

/* suggestions dropdown */
#wd-suggestions {
  display: none;
  width: 100%;
  max-width: 600px;
  margin: 0 auto 10px;
  border: 1px solid #ddd;
  border-radius: 4px;
  max-height: 200px;
  overflow-y: auto;
  background: white;
  z-index: 1000;
  position: relative;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}
.wd-suggestion-item {
  padding: 12px;
  cursor: pointer;
  font-weight: 500;
  border-bottom: 1px solid #f0f0f0;
  transition: background-color 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}
.wd-suggestion-item:last-child {
  border-bottom: none;
}
.wd-suggestion-item:hover,
.wd-suggestion-item:active { 
  background: #f8f7ff; 
  color: #564AFF; 
}
.wd-suggestion-item.no-suggestions { 
  cursor: default; 
  color: #999;
  text-align: center;
}

/* nav letters centered */
.wd-definitions-nav {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  margin: 50px 0;
  gap: 8px;
}
.wd-definitions-nav .filter-link {
  color: #000;
  text-decoration: none;
  padding: 8px 5px;
  font-weight: 500;
  border-radius: 4px;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  min-width: 36px;
  text-align: center;
  font-size: 18px;
  font-weight: 600;
}
.wd-definitions-nav .filter-link:hover,
.wd-definitions-nav .filter-link:active {
  background: #f8f7ff;
  color: #564AFF;
}
.wd-definitions-nav .filter-link.selected {
  background: #564AFF;
  color: white;
}
.wd-category-nav {
  display: flex;
  justify-content: center;
  flex-wrap: wrap;
  gap: 10px;
  margin-bottom: 30px;
}

.wd-category-nav .cat-filter-link {
  display: inline-block;
  background: #f8f7ff;
  color: #564AFF;
  font-weight: 600;
  padding: 8px 16px;
  border: 1px solid #564AFF;
  border-radius: 6px;
  text-decoration: none;
  transition: all 0.2s ease;
}

.wd-category-nav .cat-filter-link:hover,
.wd-category-nav .cat-filter-link:active {
  background: #564AFF;
  color: #fff;
}

.wd-category-nav .cat-filter-link.selected {
  background: #564AFF;
  color: #fff;
}

@media (max-width: 600px) {
  .wd-category-nav .cat-filter-link {
    font-size: 14px;
    padding: 10px 14px;
  }
}


/* definitions list */
.wd-definitions-list {
  list-style: none;
  padding: 0;
  margin: 0 0 32px;
}

/* Modified content container for right-aligned read more */
.wd-definitions-list li .wd-content {
  flex: 1;
  min-width: 0;
  display: flex;
  flex-direction: column;
  position: relative;
}

.wd-definitions-list {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 20px;
  list-style: none;
  padding: 0;
  margin: 0 0 32px;
}

.wd-definitions-list li {
  display: flex;
  flex-direction: column;
  background: #fafafa;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  overflow: hidden;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
  transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.wd-definitions-list li:hover {
  transform: translateY(-3px);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.1);
}

.wd-definitions-list li .wd-thumb {
  width: 100%;
  height: 260px; /* was 180px — gives more visual space for images */
  object-fit: cover;
  border-bottom: 1px solid #ddd;
}

.wd-definitions-list li .wd-content {
  padding: 15px;
}


.wd-definitions-list li .wd-content a.title {
  display: block;
  font-size: 20px;
  font-weight: 600;
  margin-bottom: 8px;
  color: #564AFF;
  text-decoration: none;
  line-height: 1.3;
  -webkit-tap-highlight-color: transparent;
}
.wd-definitions-list li .wd-content a.title:hover,
.wd-definitions-list li .wd-content a.title:active {
  color: #4038D6;
}

/* Excerpt container for right-aligned read more */
.wd-definitions-list li .wd-content .wd-excerpt-container {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 8px;
}

.wd-definitions-list li .wd-content p.wd-excerpt {
  font-size: 16px;
  font-weight: 400;
  margin: 0;
  color: #333;
  line-height: 1.4;
  flex: 1;
}

.wd-definitions-list li .wd-content .wd-read-more {
  color: #564AFF;
  font-size: 16px;
  font-weight: 600;
  text-decoration: none;
  display: inline-block;
  padding: 4px 10px;
  border-radius: 4px;
  border: 1px solid #564AFF;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
  flex-shrink: 0;
  align-self: flex-start;
  margin-top: 10px;
}
.wd-definitions-list li .wd-content .wd-read-more:hover,
.wd-definitions-list li .wd-content .wd-read-more:active {
  background: #f8f7ff;
  text-decoration: none;
}

/* Loading state */
.wd-loading {
  text-align: center;
  padding: 40px;
  color: #666;
  font-style: italic;
}

/* pagination */
.wd-pagination {
  text-align: center;
  margin: 24px 0;
  display: flex;
  justify-content: center;
  align-items: center;
  flex-wrap: wrap;
  gap: 8px;
}
.wd-pagination a,
.wd-pagination span {
  display: inline-block;
  padding: 8px 12px;
  font-weight: 500;
  text-decoration: none;
  color: #564AFF;
  border: 1px solid #ddd;
  border-radius: 4px;
  min-width: 40px;
  text-align: center;
  transition: all 0.2s ease;
  -webkit-tap-highlight-color: transparent;
}
.wd-pagination a:hover,
.wd-pagination a:active {
  background: #f8f7ff;
  border-color: #564AFF;
}
.wd-pagination span.current {
  background: #564AFF;
  color: #fff;
  border-color: #564AFF;
}
.wd-pagination .prev-next {
  font-weight: 600;
  padding: 8px 16px;
}

/* Enhanced Mobile optimizations starting from 768px */
@media (max-width: 768px) {
  .wd-definitions-wrap {
    padding: 20px 16px;
  }
  
  .wd-definitions-nav {
    margin: 40px 0 30px;
    gap: 4px;
  }
  
  .wd-definitions-nav .filter-link {
    font-size: 16px;
    padding: 10px 8px;
	min-width: 24px;
  }
  
  .wd-definitions-list li {
    flex-direction: column;
    gap: 16px;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    grid-template-columns: 1fr;
  }
  
  .wd-definitions-list li .wd-thumb {
    flex: none;
    width: 100%;
    height: 200px;
    border-radius: 8px;
    align-self: center;
  }

  .wd-definitions-list {
    grid-template-columns: 1fr;
  }
  
  .wd-definitions-list li .wd-content {
    width: 100%;
  }
  
  .wd-definitions-list li .wd-content .wd-excerpt-container {
    gap: 12px;
  }
  
  .wd-definitions-list li .wd-content .wd-read-more {
    padding: 8px 18px;
    font-size: 14px;
    border-radius: 7px;
    background: #f8f7ff;
    border: 1px solid #564AFF;
  }
}

/* Mobile optimizations starting from 425px */
@media (max-width: 425px) {
  .wd-definitions-wrap {
    padding: 16px 12px;
  }
  
  #wd-search-input {
    margin: 30px auto 10px;
    padding: 14px 16px;
    font-size: 16px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  }
  
  .wd-definitions-nav {
    margin: 30px 0 20px;
    gap: 0;
  }
  
  .wd-definitions-nav .filter-link {
    padding: 8px 6px;
    font-size: 14px;
    min-width: 18px;
    border-radius: 6px;
  }
  
  .wd-definitions-list li {
    gap: 16px;
    padding: 18px;
    margin: 16px 0;
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.1);
  }
  
  .wd-definitions-list li .wd-thumb {
    height: 180px;
    border-radius: 8px;
  }
  
  .wd-definitions-list li .wd-content a.title {
    font-size: 18px;
    margin-bottom: 8px;
    line-height: 1.3;
  }
  
  .wd-definitions-list li .wd-content .wd-excerpt-container {
    gap: 14px;
  }
  
  .wd-definitions-list li .wd-content p.wd-excerpt {
    font-size: 15px;
    line-height: 1.5;
  }
  
  .wd-definitions-list li .wd-content .wd-read-more {
    font-size: 14px;
    padding: 10px 20px;
    border-radius: 7px;
    background: #f8f7ff;
    border: 1px solid #564AFF;
    font-weight: 600;
  }
  
  .wd-suggestion-item {
    padding: 14px;
    font-size: 15px;
    border-radius: 4px;
  }
  
  .wd-pagination {
    gap: 6px;
    margin: 30px 0;
  }
  
  .wd-pagination a,
  .wd-pagination span {
    padding: 10px 14px;
    font-size: 14px;
    min-width: 40px;
    border-radius: 6px;
  }
  
  .wd-pagination .prev-next {
    padding: 10px 18px;
  }
}

@media (max-width: 375px) {
  .wd-definitions-wrap {
    padding: 12px 8px;
  }
  
  #wd-search-input {
    margin: 24px auto 8px;
    padding: 12px 14px;
    border-radius: 10px;
    box-shadow: 0 2px 6px rgba(0,0,0,0.12);
  }
  
  .wd-definitions-nav {
    margin: 24px 0 16px;
  }
  
  .wd-definitions-nav .filter-link {
    padding: 6px 4px;
    font-size: 13px;
    min-width: 16px;
    border-radius: 4px;
  }
  
  .wd-definitions-list li {
    gap: 14px;
    padding: 16px;
    margin: 12px 0;
    border-radius: 12px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.12);
  }
  
  .wd-definitions-list li .wd-thumb {
    height: 160px;
    border-radius: 8px;
  }
  
  .wd-definitions-list li .wd-content a.title {
    font-size: 17px;
    margin-bottom: 6px;
    line-height: 1.3;
  }
  
  .wd-definitions-list li .wd-content .wd-excerpt-container {
    gap: 12px;
  }
  
  .wd-definitions-list li .wd-content p.wd-excerpt {
    font-size: 14px;
    line-height: 1.5;
  }
  
  .wd-definitions-list li .wd-content .wd-read-more {
    font-size: 13px;
    padding: 8px 18px;
    border-radius: 7px;
    background: #f8f7ff;
    border: 1px solid #564AFF;
    font-weight: 600;
  }
  
  .wd-suggestion-item {
    padding: 12px;
    font-size: 14px;
  }
  
  .wd-pagination {
    gap: 4px;
    margin: 24px 0;
  }
  
  .wd-pagination a,
  .wd-pagination span {
    padding: 8px 12px;
    font-size: 13px;
    min-width: 36px;
    border-radius: 6px;
  }
  
  .wd-pagination .prev-next {
    padding: 8px 16px;
  }
}

@media (max-width: 320px) {
  .wd-definitions-wrap {
    padding: 8px 6px;
  }
  
  #wd-search-input {
    margin: 20px auto 6px;
    padding: 10px 12px;
    font-size: 16px;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
  }
  
  .wd-definitions-nav {
    margin: 20px 0 12px;
  }
  
  .wd-definitions-nav .filter-link {
    padding: 5px 3px;
    font-size: 12px;
    min-width: 15px;
    border-radius: 4px;
  }
  
  .wd-definitions-list li {
    gap: 12px;
    padding: 14px;
    margin: 10px 0;
    border-radius: 10px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.15);
  }
  
  .wd-definitions-list li .wd-thumb {
    height: 140px;
    border-radius: 6px;
  }
  
  .wd-definitions-list li .wd-content a.title {
    font-size: 16px;
    margin-bottom: 6px;
    line-height: 1.2;
  }
  
  .wd-definitions-list li .wd-content .wd-excerpt-container {
    gap: 10px;
  }
  
	  .wd-definitions-list li .wd-content p.wd-excerpt {
		font-size: 13px;
		line-height: 1.4;
	}
  
  	.wd-definitions-list li .wd-content .wd-read-more {
		font-size: 12px;
		padding: 6px 16px;
		border-radius: 7px;
		background: #f8f7ff;
		border: 1px solid #564AFF;
		font-weight: 600;
  	}
	
  .wd-suggestion-item {
    padding: 10px;
    font-size: 13px;
  }
  
  .wd-pagination {
    gap: 3px;
    margin: 20px 0;
  }
  
  .wd-pagination a,
  .wd-pagination span {
    padding: 6px 10px;
    font-size: 12px;
    min-width: 32px;
    border-radius: 4px;
  }
  
  .wd-pagination .prev-next {
    padding: 6px 14px;
  }
}
</style>

    <div class="wd-definitions-wrap">
      <input id="wd-search-input" type="text" placeholder="Search definitions…" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
      <div id="wd-suggestions"></div>

      <div class="wd-definitions-nav">
        <a href="#" class="filter-link selected" data-filter="all">All</a>
        <a href="#" class="filter-link"           data-filter="0-9">0-9</a>
        <?php foreach ( range('A','Z') as $ltr ) : ?>
          <a href="#"
             class="filter-link"
             data-filter="<?php echo esc_attr($ltr); ?>">
            <?php echo esc_html($ltr); ?>
          </a>
        <?php endforeach; ?>
      </div>
      <div class="wd-category-nav">
        <a href="#" class="cat-filter-link selected" data-cat="all">All</a>
        <a href="#" class="cat-filter-link" data-cat="definition">Definition</a>
        <a href="#" class="cat-filter-link" data-cat="review">Reviews</a>
        <a href="#" class="cat-filter-link" data-cat="news">News</a>
        <a href="#" class="cat-filter-link" data-cat="how-to">How-tos</a>
    </div>


      <div id="wd-definitions-results"></div>
    </div>

    <script>
    jQuery(function($){
      var filterTimer, suggestTimer,
          selectedFilter = 'all',
          ajaxURL        = '<?php echo esc_url( admin_url('admin-ajax.php') ); ?>',
          currentPage    = 1,
          isLoading      = false,
          currentRequest = null;

        // Detect ?type= in the URL (e.g., ?type=review)
        var urlParams = new URLSearchParams(window.location.search);
        var defaultCategory = urlParams.get('type') || 'all';

        // Highlight the correct button on load
        $('.cat-filter-link').removeClass('selected');
        $('.cat-filter-link[data-cat="' + defaultCategory + '"]').addClass('selected');

        // Initial load based on URL parameter
        doFilter('', 'all', false, 1, defaultCategory);


      // Debounced input handling
      $('#wd-search-input').on('input', function(e){
        var val = $(this).val().trim();
        clearTimers();
        
        if ( ! val ) {
          $('#wd-suggestions').hide();
          selectedFilter = 'all';
          $('.filter-link').removeClass('selected');
          $('.filter-link[data-filter="all"]').addClass('selected');
          doFilter('', 'all', false, 1);
          return;
        }

        // Show suggestions with shorter delay
        suggestTimer = setTimeout(function(){
          if (!isLoading) {
            $.post(ajaxURL,{
              action: 'wd_suggest_definitions',
              search: val
            }, function(html){
              $('#wd-suggestions').html(html).show();
            });
          }
        }, 200);

        // Auto-filter with longer delay
        filterTimer = setTimeout(function(){
          doFilter( val, selectedFilter, false, 1 );
        }, 1000);
      });

      // Handle enter key
      $('#wd-search-input').on('keydown', function(e){
        if ( e.keyCode === 13 ) {
          e.preventDefault();
          clearTimers();
          $('#wd-suggestions').hide();
          doFilter( $(this).val(), selectedFilter, true, 1 );
        }
      });

      // Handle suggestion clicks
      $(document).on('click touchstart', '.wd-suggestion-item', function(e){
        e.preventDefault();
        if (!$(this).hasClass('no-suggestions')) {
          var url = $(this).data('url');
          if (url) {
            window.location.href = url;
          }
        }
      });

      // nav letter clicks with better mobile handling
      $('.filter-link').on('click touchstart', function(e){
        e.preventDefault();
        if (isLoading) return;
        
        $('.filter-link').removeClass('selected');
        $(this).addClass('selected');
        selectedFilter = $(this).data('filter');
        $('#wd-suggestions').hide();
        doFilter( $('#wd-search-input').val(), selectedFilter, false, 1 );
      });

      // category button clicks
        $('.cat-filter-link').on('click touchstart', function(e){
        e.preventDefault();
        if (isLoading) return;

        $('.cat-filter-link').removeClass('selected');
        $(this).addClass('selected');

        var category = $(this).data('cat');
        $('#wd-suggestions').hide();
        doFilter($('#wd-search-input').val(), selectedFilter, false, 1, category);
        });


      // pagination clicks with better mobile handling
      $(document).on('click touchstart', '.wd-pagination a', function(e){
        e.preventDefault();
        if (isLoading) return;
        
        var pg = parseInt($(this).data('page'),10);
        if (pg && pg > 0) {
          doFilter( $('#wd-search-input').val(), selectedFilter, false, pg );
        }
      });

      // Hide suggestions when clicking outside
      $(document).on('click touchstart', function(e){
        if (!$(e.target).closest('#wd-search-input, #wd-suggestions').length) {
          $('#wd-suggestions').hide();
        }
      });

      function clearTimers(){
        clearTimeout(filterTimer);
        clearTimeout(suggestTimer);
        if (currentRequest) {
          currentRequest.abort();
          currentRequest = null;
        }
      }

    function doFilter(search, filter, enter, page, category){
        if (isLoading) return;
        
        currentPage = page || 1;
        isLoading = true;
        $('#wd-definitions-results').html('<div class="wd-loading">Loading...</div>');
        
        currentRequest = $.post(ajaxURL, {
            action: 'wd_filter_definitions',
            search: search,
            filter: filter,
            category: category || 'all',
            enter: enter ? 1 : 0,
            page: currentPage
        }).done(function(html){
            $('#wd-definitions-results').html(html);
        }).fail(function(xhr){
            if (xhr.statusText !== 'abort') {
            $('#wd-definitions-results').html('<div class="wd-loading">Error loading definitions. Please try again.</div>');
            }
        }).always(function(){
            isLoading = false;
            currentRequest = null;
        });
        }

    });
    </script>
    <?php
    return ob_get_clean();
}

// 2) AJAX: suggestions only
add_action( 'wp_ajax_wd_suggest_definitions', 'wd_suggest_definitions_callback' );
add_action( 'wp_ajax_nopriv_wd_suggest_definitions', 'wd_suggest_definitions_callback' );
function wd_suggest_definitions_callback() {
    // Add small delay to prevent too many requests
    if (!defined('DOING_AJAX') || !DOING_AJAX) {
        wp_die();
    }
    
    $s = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    if ( ! $s ) {
      echo '<div class="wd-suggestion-item no-suggestions">No suggestions</div>';
      wp_die();
    }
    
    global $wpdb;
    $like = $wpdb->esc_like( $s ) . '%';
    
    // Use prepared statement for better performance
    $rows = $wpdb->get_results( $wpdb->prepare(
      "SELECT ID, post_title
         FROM {$wpdb->posts}
        WHERE post_type='definition'
          AND post_status='publish'
          AND post_title LIKE %s
        ORDER BY post_title ASC
        LIMIT 5",
      $like
    ) );
    
    if ( $rows ) {
      foreach ( $rows as $r ) {
        printf(
          '<div class="wd-suggestion-item" data-url="%s">%s</div>',
          esc_url( get_permalink( $r->ID ) ),
          esc_html( $r->post_title )
        );
      }
    } else {
      echo '<div class="wd-suggestion-item no-suggestions">No suggestions</div>';
    }
    wp_die();
}

// 3) AJAX: main filter & display with pagination & thumbnail
add_action( 'wp_ajax_wd_filter_definitions', 'wd_filter_definitions_callback' );
add_action( 'wp_ajax_nopriv_wd_filter_definitions', 'wd_filter_definitions_callback' );
function wd_filter_definitions_callback() {
    if (!defined('DOING_AJAX') || !DOING_AJAX) wp_die();

    $search   = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
    $filter   = isset($_POST['filter']) ? sanitize_text_field($_POST['filter']) : 'all';
    $category = isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'all';
    $enter    = ! empty($_POST['enter']);
    $paged    = max(1, intval($_POST['page'] ?? 1));

    $args = [
        'post_type'      => ['definition', 'reviews', 'news', 'how-tos'],
        'posts_per_page' => $enter ? 1 : 8,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'paged'          => $paged,
        'no_found_rows'  => false,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
    ];

    // Search term
    if ($search) {
        $args['s'] = $search;
    }

    // Category filter
    if ($category !== 'all') {
        $args['post_type'] = $category;
    }


    // Letter filter
    add_filter('posts_where', function($where) use ($filter) {
        global $wpdb;
        if ($filter !== 'all') {
        if ($filter === '0-9') {
            $where .= " AND {$wpdb->posts}.post_title REGEXP '^[0-9]'";
        } else {
            $ltr = esc_sql($filter);
            $where .= " AND {$wpdb->posts}.post_title LIKE '{$ltr}%'";
        }
        }
        return $where;
    }, 10, 1);

    $q = new WP_Query($args);
    $posts = $q->have_posts() ? $q->posts : [];

    function _wd_get_first_sentence( $content ) {
      if ( preg_match('/<p\b[^>]*>(.*?)<\/p>/is', $content, $m ) ) {
        $txt = wp_strip_all_tags( $m[1] );
      } else {
        $txt = wp_strip_all_tags( $content );
      }
      if ( preg_match('/^.*?[\.!?](\s|$)/', trim($txt), $smatch ) ) {
        $excerpt = $smatch[0];
      } else {
        $excerpt = trim($txt);
      }
      
      // Limit to 120 characters
      if ( strlen($excerpt) > 120 ) {
        $excerpt = substr($excerpt, 0, 120);
        $last_space = strrpos($excerpt, ' ');
        if ($last_space !== false) {
            $excerpt = substr($excerpt, 0, $last_space);
        }
    }

      
      return $excerpt;
    }

    // output list
    echo '<ul class="wd-definitions-list">';
    if ( $posts ) {
      foreach ( $posts as $p ) {
        $thumb = get_the_post_thumbnail( $p->ID, 'thumbnail large',
                   [ 'class'=>'wd-thumb', 'alt'=>esc_attr($p->post_title), 'loading'=>'lazy' ] );
        $ex    = _wd_get_first_sentence( $p->post_content );
        printf(
          '<li>%s<div class="wd-content"><a class="title" href="%s" target="_blank" rel="noopener noreferrer">%s</a><p class="wd-excerpt">%s</p><a class="wd-read-more" href="%s" target="_blank" rel="noopener noreferrer">Read more</a></div></li>',
          $thumb,
          esc_url( get_permalink( $p->ID ) ),
          esc_html( $p->post_title ),
          esc_html( $ex ),
          esc_url( get_permalink( $p->ID ) )
        );
      }
    } else {
      $msg = $enter ? 'Not found.' : 'No definitions found.';
      echo "<li style='text-align: center; padding: 20px; color: #666;'>{$msg}</li>";
    }
    echo '</ul>';

    // pagination with prev/next
    if ( ! $enter && $q->max_num_pages > 1 ) {
      echo '<div class="wd-pagination">';
      
      // Previous button
      if ( $paged > 1 ) {
        printf( '<a href="#" class="prev-next" data-page="%d">‹ Prev</a>', $paged - 1 );
      }
      
      // Page numbers with smart display
      $start = max(1, $paged - 2);
      $end = min($q->max_num_pages, $paged + 2);
      
      // Show first page if we're not starting from 1
      if ( $start > 1 ) {
        printf( '<a href="#" data-page="1">1</a>' );
        if ( $start > 2 ) {
          echo '<span style="cursor: default;">...</span>';
        }
      }
      
      // Show page numbers around current page
      for ( $i = $start; $i <= $end; $i++ ) {
        if ( $i === $paged ) {
          printf( '<span class="current">%d</span>', $i );
        } else {
          printf( '<a href="#" data-page="%d">%d</a>', $i, $i );
        }
      }
      
      // Show last page if we're not ending at the last page
      if ( $end < $q->max_num_pages ) {
        if ( $end < $q->max_num_pages - 1 ) {
          echo '<span style="cursor: default;">...</span>';
        }
        printf( '<a href="#" data-page="%d">%d</a>', $q->max_num_pages, $q->max_num_pages );
      }
      
      // Next button
      if ( $paged < $q->max_num_pages ) {
        printf( '<a href="#" class="prev-next" data-page="%d">Next ›</a>', $paged + 1 );
      }
      
      echo '</div>';
    }

    wp_reset_postdata();
    wp_die();
}

