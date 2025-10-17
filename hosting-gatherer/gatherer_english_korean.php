<?php
/**
 * Reusable CMS Articles Gatherer (EN/KR)
 * Shortcode (universal): [wmg_cms_articles posts_per_page="9" cms="auto"]
 * Back-compat: [wmg_wp_articles]
 */

/* -------------------- Helpers -------------------- */
function wmg_allowed_types(){ return ['how-to','news','review','definition']; }

function wmg_current_lang(){
  if(function_exists('pll_current_language')) return pll_current_language('slug') ?: 'en';
  $req = trim($_SERVER['REQUEST_URI'] ?? '', '/');
  return preg_match('~^ko(/|$)~',$req) ? 'ko' : 'en';
}

/** Map CMS key -> {en, ko, title_en, title_ko, class} */
function wmg_cms_catalog(){
  return [
    'wordpress'   => ['en'=>'wordpress','ko'=>'워드프레스','title_en'=>'WordPress Articles','title_ko'=>'워드프레스 글','class'=>'wmg-cms-wordpress'],
    'wix'         => ['en'=>'wix','ko'=>'윅스','title_en'=>'Wix Articles','title_ko'=>'윅스 글','class'=>'wmg-cms-wix'],
    'joomla'      => ['en'=>'joomla','ko'=>'줌라','title_en'=>'Joomla Articles','title_ko'=>'줌라 글','class'=>'wmg-cms-joomla'],
    'drupal'      => ['en'=>'drupal','ko'=>'드루팔','title_en'=>'Drupal Articles','title_ko'=>'드루팔 글','class'=>'wmg-cms-drupal'],
    'squarespace' => ['en'=>'squarespace','ko'=>'스퀘어스페이스','title_en'=>'Squarespace Articles','title_ko'=>'스퀘어스페이스 글','class'=>'wmg-cms-squarespace'],
  ];
}

/** Infer CMS key from URL slug */
function wmg_detect_cms_from_url(){
  $req = trim($_SERVER['REQUEST_URI'] ?? '', '/');
  $parts = array_values(array_filter(explode('/', $req)));
  if (!$parts) return 'wordpress';
  // drop language prefix if present
  if (in_array($parts[0], ['en','ko'], true)) array_shift($parts);
  $first = $parts[0] ?? '';
  $map = [
    'wp-articles'          => 'wordpress',
    'wordpress-articles'   => 'wordpress',
    'wix-articles'         => 'wix',
    'joomla-articles'      => 'joomla',
    'drupal-articles'      => 'drupal',
    'squarespace-articles' => 'squarespace',
  ];
  return $map[$first] ?? 'wordpress';
}

/** Resolve category slug for current language */
function wmg_lang_category_slug($cms_key, $lang){
  $cat = wmg_cms_catalog()[$cms_key] ?? null;
  if(!$cat) $cat = wmg_cms_catalog()['wordpress'];
  return ($lang==='ko') ? $cat['ko'] : $cat['en'];
}

/** UI strings per CMS + lang */
function wmg_ui_bundle($cms_key, $lang){
  $cat = wmg_cms_catalog()[$cms_key];
  $title = ($lang==='ko') ? $cat['title_ko'] : $cat['title_en'];
  $ph    = ($lang==='ko') ? "{$cat['ko']} 글 검색…" : "Search {$cat['en']} articles…";
  $labels = ($lang==='ko')
    ? ['all'=>'모든 유형','howto'=>'사용 가이드','news'=>'뉴스','review'=>'리뷰','def'=>'용어집']
    : ['all'=>'All types','howto'=>'Guides','news'=>'News','review'=>'Reviews','def'=>'Definitions'];
  return ['title'=>$title,'ph'=>$ph,'labels'=>$labels,'class'=>$cat['class']];
}

/* -------------------- AJAX (one endpoint) -------------------- */
add_action('wp_ajax_wmg_cms_articles', 'wmg_cms_articles_ajax');
add_action('wp_ajax_nopriv_wmg_cms_articles', 'wmg_cms_articles_ajax');

/* Back-compat endpoint name (keeps old pages working) */
add_action('wp_ajax_wmg_wp_articles', 'wmg_cms_articles_ajax');
add_action('wp_ajax_nopriv_wmg_wp_articles', 'wmg_cms_articles_ajax');

function wmg_cms_articles_ajax(){
  if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'wmg_cms_articles_nonce')) {
    wp_send_json_error(['message'=>'Security check failed'], 403);
  }

  $lang = sanitize_key($_POST['lang'] ?? wmg_current_lang());
  $cms  = sanitize_key($_POST['cms']  ?? wmg_detect_cms_from_url());
  $cat_slug = sanitize_text_field($_POST['category_slug'] ?? wmg_lang_category_slug($cms,$lang));

  $post_type_in = sanitize_title($_POST['post_type'] ?? '');
  $pt_allowed   = wmg_allowed_types();
  $post_type    = ($post_type_in && in_array($post_type_in, $pt_allowed, true)) ? $post_type_in : $pt_allowed;

  $search_query   = sanitize_text_field($_POST['search_query'] ?? '');
  $paged          = max(1, (int)($_POST['paged'] ?? 1));
  $ppp            = max(1, (int)($_POST['posts_per_page'] ?? 9));

  $args = [
    'post_type'           => $post_type,
    'post_status'         => 'publish',
    'posts_per_page'      => $ppp,
    'paged'               => $paged,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'ignore_sticky_posts' => true,
    'suppress_filters'    => false,
    'tax_query'           => [[
      'taxonomy'         => 'category',
      'field'            => 'slug',
      'terms'            => [$cat_slug],
      'include_children' => true,
      'operator'         => 'IN',
    ]],
  ];
  if ($search_query !== '') $args['s'] = $search_query;
  if (function_exists('pll_current_language')) $args['lang'] = $lang;

  $q = new WP_Query($args);

  ob_start(); ?>
  <?php if ($q->have_posts()): ?>
    <div class="wmg-grid">
      <?php while ($q->have_posts()): $q->the_post(); ?>
        <article class="wmg-card">
          <a class="wmg-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener">
            <div class="wmg-thumb">
              <?php if (has_post_thumbnail()): ?>
                <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(), 'medium_large')); ?>"
                     alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy"
                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                <div class="wmg-fallback" style="display:none;">📌</div>
              <?php else: ?>
                <div class="wmg-fallback">📌</div>
              <?php endif; ?>
            </div>
            <div class="wmg-body">
              <h2 class="wmg-title"><?php the_title(); ?></h2>
              <p class="wmg-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 28, '…')); ?></p>
            </div>
          </a>
        </article>
      <?php endwhile; ?>
    </div>
    <?php if ($q->max_num_pages > 1): ?>
      <nav class="wmg-pager" data-max-pages="<?php echo (int)$q->max_num_pages; ?>">
        <?php echo paginate_links([
          'base'=>'#','format'=>'','current'=>$paged,'total'=>$q->max_num_pages,
          'prev_text'=>__('Prev','twentytwentyfive'),'next_text'=>__('Next','twentytwentyfive'),'type'=>'list',
        ]); ?>
      </nav>
    <?php endif; ?>
  <?php else: ?>
    <div class="wmg-empty"><?php echo ($lang==='ko') ? '게시물이 없습니다.' : 'No posts found.'; ?></div>
  <?php endif; ?>
  <?php
  wp_reset_postdata();
  wp_send_json_success([
    'html'=>ob_get_clean(),
    'found_posts'=>(int)$q->found_posts,
    'max_pages'=>(int)$q->max_num_pages,
    'current_page'=>(int)$paged,
  ]);
}

/* -------------------- Shortcode (universal) -------------------- */
add_shortcode('wmg_cms_articles', 'wmg_cms_articles_sc');
add_shortcode('wmg_wp_articles',  'wmg_cms_articles_sc'); // back-compat

function wmg_cms_articles_sc($atts){
  $atts = shortcode_atts([
    'posts_per_page'=>9,
    'cms'=>'auto', // wordpress|wix|joomla|drupal|squarespace|auto
  ], $atts, 'wmg_cms_articles');

  $lang = wmg_current_lang();
  $cms  = ($atts['cms']==='auto') ? wmg_detect_cms_from_url() : sanitize_key($atts['cms']);
  $cms  = array_key_exists($cms, wmg_cms_catalog()) ? $cms : 'wordpress';

  $slug = wmg_lang_category_slug($cms, $lang);
  $ui   = wmg_ui_bundle($cms, $lang);
  $allow= wmg_allowed_types();

  $args = [
    'post_type'           => $allow,
    'post_status'         => 'publish',
    'posts_per_page'      => (int)$atts['posts_per_page'],
    'paged'               => 1,
    'orderby'             => 'date',
    'order'               => 'DESC',
    'ignore_sticky_posts' => true,
    'suppress_filters'    => false,
    'tax_query'           => [[
      'taxonomy'=>'category','field'=>'slug','terms'=>[$slug],'include_children'=>true,'operator'=>'IN',
    ]],
  ];
  if (function_exists('pll_current_language')) $args['lang'] = $lang;

  $q = new WP_Query($args);

  ob_start(); ?>
  <div class="wmg-wrap <?php echo esc_attr($ui['class']); ?>"
       data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
       data-ajax-action="wmg_cms_articles"
       data-nonce="<?php echo esc_attr(wp_create_nonce('wmg_cms_articles_nonce')); ?>"
       data-lang="<?php echo esc_attr($lang); ?>"
       data-cms="<?php echo esc_attr($cms); ?>"
       data-category-slug="<?php echo esc_attr($slug); ?>"
       data-posts-per-page="<?php echo (int)$atts['posts_per_page']; ?>">

    <!-- HERO: title only -->
    <div class="wmg-hero">
      <div class="wmg-hero-bg"></div>
      <div class="wmg-hero-overlay"></div>
      <div class="wmg-hero-inner">
        <h1 class="wmg-hero-title"><?php echo esc_html($ui['title']); ?></h1>

        <!-- centered under title -->
        <form class="wmg-search" onsubmit="return false;">
          <input type="text" class="wmg-input"
                placeholder="<?php echo esc_attr($ui['ph']); ?>" autocomplete="off">
          <div class="wmg-loading" style="display:none;"><div class="wmg-spinner"></div></div>
        </form>
      </div>
    </div>

    <!-- NEW: toolbar below banner, left side -->
    <div class="wmg-toolbar">
      <div class="wmg-type">
        <button class="wmg-dd-btn" onclick="wmgToggleDD()">
          <span class="wmg-dd-text"><?php echo esc_html($ui['labels']['all']); ?></span>
          <span class="wmg-dd-arrow">▼</span>
        </button>
        <div id="wmgDD" class="wmg-dd">
          <button class="wmg-dd-item selected" data-v=""><?php echo esc_html($ui['labels']['all']); ?></button>
          <!-- <button class="wmg-dd-item" data-v="definition"><?php echo esc_html($ui['labels']['def']); ?></button> -->
          <button class="wmg-dd-item" data-v="review"><?php echo esc_html($ui['labels']['review']); ?></button>
          <button class="wmg-dd-item" data-v="news"><?php echo esc_html($ui['labels']['news']); ?></button>
          <button class="wmg-dd-item" data-v="how-to"><?php echo esc_html($ui['labels']['howto']); ?></button>
        </div>
      </div>
    </div>

    <!-- RESULTS -->
    <div class="wmg-results">
      <?php if ($q->have_posts()): ?>
        <div class="wmg-grid">
          <?php while ($q->have_posts()): $q->the_post(); ?>
            <article class="wmg-card">
              <a class="wmg-link" href="<?php the_permalink(); ?>" target="_blank" rel="noopener">
                <div class="wmg-thumb">
                  <?php if (has_post_thumbnail()): ?>
                    <img src="<?php echo esc_url(get_the_post_thumbnail_url(get_the_ID(),'medium_large')); ?>"
                         alt="<?php echo esc_attr(get_the_title()); ?>" loading="lazy"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="wmg-fallback" style="display:none;">📌</div>
                  <?php else: ?>
                    <div class="wmg-fallback">📌</div>
                  <?php endif; ?>
                </div>
                <div class="wmg-body">
                  <h2 class="wmg-title"><?php the_title(); ?></h2>
                  <p class="wmg-excerpt"><?php echo wp_kses_post(wp_trim_words(get_the_excerpt(), 28, '…')); ?></p>
                </div>
              </a>
            </article>
          <?php endwhile; ?>
        </div>
        <?php if ($q->max_num_pages > 1): ?>
          <nav class="wmg-pager" data-max-pages="<?php echo (int)$q->max_num_pages; ?>">
            <?php echo paginate_links([
              'base'=>'#','format'=>'','current'=>1,'total'=>$q->max_num_pages,
              'prev_text'=>__('Prev','twentytwentyfive'),
              'next_text'=>__('Next','twentytwentyfive'),
              'type'=>'list',
            ]); ?>
          </nav>
        <?php endif; ?>
      <?php else: ?>
        <div class="wmg-empty"><?php echo ($lang==='ko') ? '게시물이 없습니다.' : 'No posts found.'; ?></div>
      <?php endif; wp_reset_postdata(); ?>
    </div>
  </div>
  <?php
  return ob_get_clean();
}

/* -------------------- CSS -------------------- */
add_action('wp_enqueue_scripts', function(){
  wp_register_style('wmg_cms_articles_css', false); wp_enqueue_style('wmg_cms_articles_css');
  $css = "
    /* Hero */
    .wmg-hero{width:98vw;left:50%;transform:translateX(-50%);position:relative;height:360px;display:flex;align-items:center;justify-content:center;overflow:hidden}
    .wmg-hero-bg{position:absolute;inset:-20% 0 0 0;background-size:cover;background-position:center}
    .wmg-hero-overlay{position:absolute;inset:0;background:linear-gradient(173deg,#7d75f4 0%,#8e6ac4 28%,#564aff 76%);opacity:.8}
    .wmg-hero-inner{position:relative;color:#fff;text-align:center}
    .wmg-hero-title{margin:0;font-size:2rem;font-weight:800}

    /* Toolbar under hero (left) */
    .wmg-toolbar{max-width:1400px;margin:0 auto 12px;padding:0 2rem;display:flex;gap:12px;align-items:center;justify-content:flex-end;position:relative;z-index:2}
    .wmg-search{margin:0}
    .wmg-input{width:420px;max-width:100%;height:44px;border:2px solid #564AFF;border-radius:8px;padding:0 .75rem;background:rgba(255,255,255,.92)}
    .wmg-type{width:auto;position:relative}
    .wmg-dd-btn{height:44px;min-width:180px;padding:0 14px;border:0;border-radius:6px;background:#5A4AFF;color:#fff;display:flex;justify-content:space-between;align-items:center;cursor:pointer}
    .wmg-dd-arrow{font-size:12px;margin-left:8px}
    .wmg-dd{position:absolute;top:100%;left:0;right:auto;min-width:220px;background:#fff;border:1px solid #e5e7eb;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,.1);opacity:0;visibility:hidden;transform:translateY(-4px);transition:.2s;margin-top:2px;overflow:hidden;z-index:5}
    .wmg-dd.open{opacity:1;visibility:visible;transform:translateY(0)}
    .wmg-dd-item{display:block;width:100%;text-align:left;border:0;background:#fff;padding:10px 14px;font-size:14px;cursor:pointer}
    .wmg-dd-item:hover{background:#f8fafc}
    .wmg-dd-item.selected{background:#5A4AFF;color:#fff}

    /* Results spacing pushed down so dropdown has room */
    .wmg-results{max-width:1400px;margin:14px auto 0;padding:0 2rem}

    /* Cards */
    .wmg-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:2rem;margin:1.25rem 0}
    .wmg-card{border-radius:8px;overflow:hidden;transition:.3s;background:#fff}
    .wmg-card:hover{transform:translateY(-4px);box-shadow:0 8px 28px rgba(0,0,0,.12)}
    .wmg-thumb{height:210px;background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);display:flex;align-items:center;justify-content:center;border-bottom:1px solid #f0f0f0;position:relative}
    .wmg-thumb img{width:100%;height:100%;object-fit:cover}
    .wmg-fallback{font-size:28px}
    .wmg-body{padding:.95rem}
    .wmg-title{margin:0 0 .65rem;font-size:22px;font-weight:800;color:#2d3748}
    .wmg-excerpt{margin:0;color:#555}

    /* Pager */
    .wmg-pager{display:flex;justify-content:center;margin:2rem 0}
    .wmg-pager .page-numbers{display:inline-flex;align-items:center;justify-content:center;min-width:40px;height:40px;padding:0 .75rem;border-radius:8px;text-decoration:none;background:#fff;color:#4a5568}
    .wmg-pager .current{background:#564AFF;color:#fff}
    .wmg-empty{padding:1rem 0;text-align:center;color:#555}
    .wmg-spinner{width:18px;height:18px;border:3px solid #fff;border-top-color:transparent;border-radius:50%;animation:wmgspin .8s linear infinite;position:absolute;right:10px;top:50%;transform:translateY(-50%)}
    @keyframes wmgspin{to{transform:translateY(-50%) rotate(360deg)}}

    /* Responsive: stack tools on mobile */
    @media(max-width:768px){
      .wmg-toolbar{flex-direction:column;align-items:stretch;gap:10px}
      .wmg-input{width:100%}
      .wmg-type{width:100%}
      .wmg-dd{right:0;left:0}
    }

    /* Hero background per CMS */
    .wmg-cms-wordpress   .wmg-hero-bg{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/wordpress-gatherer-header.png')}
    .wmg-cms-wix         .wmg-hero-bg{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/wix-gatherer-header.png')}
    .wmg-cms-joomla      .wmg-hero-bg{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/joomla-gatherer-header.png')}
    .wmg-cms-drupal      .wmg-hero-bg{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/drupal-gatherer-header.png')}
    .wmg-cms-squarespace .wmg-hero-bg{background-image:url('http://wixmediagroup.com/wp-content/uploads/2025/07/squarespace-gatherer-header.png')}
  
    /* === Toolbar under title (centered) === */

    /* Search takes full row width but stays reasonable */
    .wmg-search{ width: 680px; max-width: 100%; }
    .wmg-input{ width: 100%; height: 44px; }

    /* Dropdown sits below the search with headroom */
    .wmg-type{
      width: 680px;                   /* align with search width */
      max-width: 100%;
      margin-top: 12px;               /* requested top margin */
      position: relative;
    }

    /* Results move down so the dropdown has room */
    .wmg-results{ margin: 24px auto 0; }

    /* Mobile: full-bleed inputs, keep vertical stack */
    @media (max-width: 768px){
      .wmg-search, .wmg-type{ width: 100%; }
      .wmg-type{ margin-top: 10px; }
    }

    /* Right-align the toolbar (dropdown only) */
    .wmg-toolbar{
      max-width:1400px;
      margin:16px auto 0;
      padding:0 2rem;
      display:flex;
      align-items:center;
      justify-content:flex-end;   /* <- send it to the right */
    }

    /* Let the dropdown size to content and hug the right edge */
    .wmg-type{
      width:auto;
      margin-left:auto;           /* safety: pushes it right in any flex context */
      position:relative;
    }

    /* Keep the grid clear below */
    .wmg-results{ margin:16px auto 0; }

    /* Mobile: full width control, left aligned */
    @media (max-width:768px){
      .wmg-toolbar{ justify-content:flex-start; }
      .wmg-type{ width:100%; }
      .wmg-dd-btn{ width:100%; }
    }

  "; wp_add_inline_style('wmg_cms_articles_css',$css);
});

/* -------------------- JS -------------------- */
add_action('wp_footer', function(){ ?>
<script>
(function(){
  const wrap = document.querySelector('.wmg-wrap'); if(!wrap) return;
  const ajaxUrl = wrap.dataset.ajaxUrl;
  const action  = wrap.dataset.ajaxAction || 'wmg_cms_articles';
  const nonce   = wrap.dataset.nonce;
  const lang    = wrap.dataset.lang || 'en';
  const cms     = wrap.dataset.cms || 'wordpress';
  const catSlug = wrap.dataset.categorySlug;
  const perPage = parseInt(wrap.dataset.postsPerPage,10) || 9;

  const results = wrap.querySelector('.wmg-results');
  const input   = wrap.querySelector('.wmg-input');
  const dd      = document.getElementById('wmgDD');

  let currentPage = 1, currentType = '', currentQ = '';

  function fetchPosts(page=1){
    const fd = new FormData();
    fd.append('action', action);
    fd.append('nonce',  nonce);
    fd.append('lang',   lang);
    fd.append('cms',    cms);
    fd.append('category_slug', catSlug);
    fd.append('paged',  page);
    fd.append('posts_per_page', perPage);
    fd.append('post_type', currentType);
    fd.append('search_query', currentQ);

    results.classList.add('loading');
    fetch(ajaxUrl,{method:'POST',body:fd})
      .then(r=>r.json())
      .then(data=>{
        if(data?.success){
          results.innerHTML = data.data.html;
          currentPage = data.data.current_page;
          bindPager();
        }
      })
      .finally(()=>results.classList.remove('loading'));
  }

  function bindPager(){
    document.querySelectorAll('.wmg-pager a.page-numbers').forEach(a=>{
      a.addEventListener('click', e=>{
        e.preventDefault();
        let page = 1;
        if(a.classList.contains('prev')) page = Math.max(1, currentPage-1);
        else if(a.classList.contains('next')){
          const max = parseInt(document.querySelector('.wmg-pager')?.dataset.maxPages||'1',10);
          page = Math.min(max, currentPage+1);
        } else page = parseInt(a.textContent,10)||1;
        fetchPosts(page);
      });
    });
  }

  // Debounced search
  if(input){
    let t;
    input.addEventListener('input', function(){
      clearTimeout(t);
      const q = this.value.trim();
      t = setTimeout(()=>{ currentQ = (q.length>=3||q.length===0) ? q : currentQ; fetchPosts(1); }, 500);
    });
  }

  // Dropdown
  window.wmgToggleDD = function(){ document.getElementById('wmgDD')?.classList.toggle('open'); };
  dd?.querySelectorAll('.wmg-dd-item').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      dd.querySelectorAll('.wmg-dd-item').forEach(b=>b.classList.remove('selected'));
      btn.classList.add('selected');
      currentType = btn.dataset.v || '';
      wrap.querySelector('.wmg-dd-text').textContent = btn.textContent;
      dd.classList.remove('open');
      fetchPosts(1);
    });
  });
  document.addEventListener('click', (e)=>{
    const typeBox = wrap.querySelector('.wmg-type');
    if(typeBox && !typeBox.contains(e.target)) dd?.classList.remove('open');
  });

  bindPager(); // SSR page 1 is already rendered
})();
</script>
<?php });
