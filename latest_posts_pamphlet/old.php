function wmg_latest_posts_slider() {
    ob_start();
    ?>

    <style>
    .pamphlet-container {
      display: flex;
      height: 600px;
      gap: 15px;
      margin-top: 40px;
      overflow: hidden;
    }

    .slot {
      flex: 0.6; /* make default smaller for better contrast */
      border-radius: 12px;
      overflow: hidden;
      position: relative;
      transition: flex 0.8s cubic-bezier(0.25, 1, 0.3, 1), box-shadow 0.5s ease;
      box-shadow: 0 4px 10px rgba(0, 0, 0, 0.08);
      cursor: pointer;
    }

    .slot.active {
      flex: 4; /* expand much wider when active */
      box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
      z-index: 3;
    }

    /* Default: slightly zoomed in for depth */
    .slot-image {
      width: 100%;
      height: 100%;
      background-size: cover;
      background-position: center;
      transform: scale(1.25); /* tighter zoom for non-active */
      transition: transform 0.8s ease;
    }

    /* Active: zoom out to show full image */
    .slot.active .slot-image {
      transform: scale(1.02);
    }

    .overlay {
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      padding: 25px;
      background: linear-gradient(to top, rgba(0, 0, 0, 0.75), transparent);
      color: white;
      opacity: 0;
      transform: translateY(40%);
      transition: all 0.4s ease;
      height: 100%;
      display: flex;
      flex-direction: column;
      justify-content: flex-end;
      text-decoration: none;
      pointer-events: none;
    }

    .slot.active .overlay {
      opacity: 1;
      transform: translateY(0);
      pointer-events: auto;
    }

    .overlay h3 {
      margin: 0 0 10px;
      font-size: 1.4rem;
      font-weight: 700;
    }

    .overlay p {
      margin: 0;
      font-size: 1rem;
      line-height: 1.6;
      color: rgba(255, 255, 255, 0.9);
    }

    @media (max-width: 768px) {
      .pamphlet-container {
        flex-direction: column;
        height: auto;
      }
      .slot {
        flex: none;
        height: 250px;
      }
      .slot-image {
        transform: scale(1);
      }
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
      const slots = document.querySelectorAll(".slot");

      slots.forEach(slot => {
        const link = slot.querySelector(".overlay");

        slot.addEventListener("click", (e) => {
          // if already active -> go to blog
          if (slot.classList.contains("active")) {
            const href = link.getAttribute("href");
            if (href) window.location.href = href;
            return;
          }

          // make it active
          slots.forEach(s => s.classList.remove("active"));
          slot.classList.add("active");

          // prevent immediate nav
          e.preventDefault();
          e.stopPropagation();
        });
      });

      // make first one active by default
      if (slots.length > 0) slots[0].classList.add("active");
    });
    </script>

    <?php
    // ---- Dynamic Post Cards ---- //
    $latest_posts = get_posts([
        'numberposts' => 5,
        'post_type'   => ['post', 'definition', 'review', 'news', 'how-to'],
        'post_status' => 'publish',
    ]);

    if ($latest_posts) :
        echo '<div class="pamphlet-container">';
        foreach ($latest_posts as $post) :
            setup_postdata($post);
            $thumb = get_the_post_thumbnail_url($post->ID, 'large') ?: 'https://via.placeholder.com/800x500';
            $excerpt = wp_trim_words(strip_tags($post->post_content), 25);
            ?>
            <div class="slot">
                <div class="slot-image" style="background-image: url('<?php echo esc_url($thumb); ?>');">
                    <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" class="overlay">
                        <h3><?php echo esc_html(get_the_title($post->ID)); ?></h3>
                        <p><?php echo esc_html($excerpt); ?></p>
                    </a>
                </div>
            </div>
            <?php
        endforeach;
        echo '</div>';
        wp_reset_postdata();
    endif;

    return ob_get_clean();
}
add_shortcode('latest_posts_pamphlet', 'wmg_latest_posts_slider');
