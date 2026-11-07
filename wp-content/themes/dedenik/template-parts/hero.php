<?php
defined( 'ABSPATH' ) || exit;
$hero_query = new WP_Query( array( 'post_type' => 'post', 'post_status' => 'publish', 'posts_per_page' => 1, 'ignore_sticky_posts' => true, 'no_found_rows' => true ) );
if ( ! $hero_query->have_posts() ) { return; }
while ( $hero_query->have_posts() ) : $hero_query->the_post();
$image = post_password_required() ? '' : get_the_post_thumbnail_url( get_the_ID(), 'large' );
if ( ! $image ) { $image = wp_get_attachment_image_url( absint( get_theme_mod( 'dedenik_hero_image' ) ), 'large' ); }
?>
<section class="hero">
    <?php if ( $image ) : ?><img class="hero-image" src="<?php echo esc_url( $image ); ?>" alt="" fetchpriority="high"><?php endif; ?>
    <div class="wrap hero-inner"><div class="hero-copy"><span class="eyebrow"><?php esc_html_e( 'Dnes na Dedeníku', 'dedenik' ); ?></span>
        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
        <?php if ( ! post_password_required() ) : ?><div class="hero-excerpt"><?php the_excerpt(); ?></div><?php endif; ?>
        <?php dedenik_metadata(); ?>
        <a class="button button-light" href="<?php the_permalink(); ?>"><?php esc_html_e( 'Přečíst článek →', 'dedenik' ); ?></a>
    </div></div>
</section>
<?php endwhile; wp_reset_postdata(); ?>
