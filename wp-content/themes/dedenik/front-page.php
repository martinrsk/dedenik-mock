<?php
/** Uses the existing main query and its pagination. */
defined( 'ABSPATH' ) || exit;
if ( ! is_home() ) { get_template_part( 'page' ); return; }
get_header();
if ( ! is_paged() ) { get_template_part( 'template-parts/hero' ); }
?>
<section class="section wrap<?php if ( ! is_paged() ) { echo ' latest-with-comments'; } ?>" id="latest">
    <div class="section-heading"><h1><?php esc_html_e( 'Nejnovější články', 'dedenik' ); ?></h1><?php if ( ! is_paged() && $GLOBALS['wp_query']->max_num_pages > 1 ) : ?><a href="<?php echo esc_url( get_pagenum_link( 2 ) ); ?>"><?php esc_html_e( 'Další články →', 'dedenik' ); ?></a><?php endif; ?></div>
    <nav class="category-tabs" aria-label="<?php esc_attr_e( 'Rubriky', 'dedenik' ); ?>"><a class="active" href="<?php echo esc_url( dedenik_posts_url() ); ?>" aria-current="page"><?php esc_html_e( 'Vše', 'dedenik' ); ?></a>
    <?php foreach ( get_categories( array( 'orderby' => 'count', 'order' => 'DESC', 'number' => 8 ) ) as $category ) : ?><a href="<?php echo esc_url( get_category_link( $category ) ); ?>"><?php echo esc_html( $category->name ); ?></a><?php endforeach; ?></nav>
    <?php if ( ! is_paged() && have_posts() ) : ?>
    <div class="cards"><?php while ( have_posts() ) : the_post(); get_template_part( 'template-parts/card' ); endwhile; ?></div>
    <?php else : get_template_part( 'template-parts/listing' ); endif; ?>
    <?php if ( ! is_paged() ) { get_template_part( 'template-parts/recent-comments' ); } ?>
</section>
<?php
if ( ! is_paged() ) {
    get_template_part( 'template-parts/world' );
    get_template_part( 'template-parts/discover' );
    get_template_part( 'template-parts/photo-gallery-banner' );
    get_template_part( 'template-parts/search-archive' );
}
?>
<?php if ( is_active_sidebar( 'widgets_sidebar_left' ) || is_active_sidebar( 'widgets_sidebar_right' ) ) : ?>
<div class="wrap"><details class="home-community"><summary><?php esc_html_e( 'Další obsah komunity a informace o Dedeníku', 'dedenik' ); ?></summary><?php get_sidebar(); ?></details></div>
<?php endif; ?>
<?php get_footer(); ?>
