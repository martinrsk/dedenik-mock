<?php defined( 'ABSPATH' ) || exit; get_header(); ?>
<div class="wrap reading-layout"><div class="reading-column"><?php while ( have_posts() ) : the_post(); ?>
<?php dedenik_widget_area( 'widgets_before_post' ); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class( 'full-article' ); ?>>
<header class="article-header"><?php dedenik_primary_category(); ?><h1><?php the_title(); ?></h1><?php dedenik_metadata(); ?></header>
<?php dedenik_widget_area( 'widgets_before_post_content' ); ?>
<div class="entry-content"><?php the_content(); wp_link_pages( array( 'before' => '<nav class="page-links" aria-label="' . esc_attr__( 'Stránky článku', 'dedenik' ) . '">', 'after' => '</nav>' ) ); ?></div>
<?php dedenik_widget_area( 'widgets_after_post_content' ); ?>
<?php if ( ! post_password_required() ) : ?><footer class="article-footer"><?php the_tags( '<div class="tag-links">', ' ', '</div>' ); edit_post_link( __( 'Upravit článek', 'dedenik' ) ); ?></footer><?php endif; ?>
</article>
<?php dedenik_widget_area( 'widgets_after_post' ); ?>
<?php the_post_navigation( array( 'prev_text' => '<span>' . __( 'Předchozí článek', 'dedenik' ) . '</span> %title', 'next_text' => '<span>' . __( 'Další článek', 'dedenik' ) . '</span> %title' ) ); ?>
<?php if ( comments_open() || get_comments_number() ) { comments_template(); } ?>
<?php endwhile; ?></div><?php get_sidebar(); ?></div>
<?php get_footer(); ?>
