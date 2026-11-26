<?php
/**
 * Template Name: Fotogalerie
 * Template Post Type: page
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<div class="wrap section photo-gallery">
<?php while ( have_posts() ) : the_post(); ?>
    <?php
    $page_id = get_the_ID();
    $parent_id = wp_get_post_parent_id( $page_id );
    $is_album = $parent_id && 'page-photo-gallery.php' === get_page_template_slug( $parent_id );
    ?>
    <article <?php post_class(); ?>>
        <?php if ( $is_album && 'publish' === get_post_status( $parent_id ) ) : ?>
            <p><a href="<?php echo esc_url( get_permalink( $parent_id ) ); ?>"><?php esc_html_e( '← Všechna alba', 'dedenik' ); ?></a></p>
        <?php endif; ?>
        <header class="article-header"><h1><?php the_title(); ?></h1></header>
        <div class="entry-content"><?php the_content(); wp_link_pages(); ?></div>
        <?php if ( ! $is_album && ! post_password_required() ) : ?>
            <?php
            $albums = get_posts( array(
                'post_type'      => 'page',
                'post_parent'    => $page_id,
                'post_status'    => 'publish',
                'has_password'   => false,
                'meta_key'       => '_wp_page_template',
                'meta_value'     => 'page-photo-gallery.php',
                'posts_per_page' => -1,
                'orderby'        => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
            ) );
            ?>
            <?php if ( $albums ) : ?>
                <ul class="photo-albums">
                <?php foreach ( $albums as $album ) : ?>
                    <li><a href="<?php echo esc_url( get_permalink( $album ) ); ?>">
                        <?php echo get_the_post_thumbnail( $album, 'medium_large', array( 'alt' => '' ) ); ?>
                        <span><?php echo esc_html( get_the_title( $album ) ); ?></span>
                    </a></li>
                <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p><?php esc_html_e( 'Zatím tu nejsou žádná alba.', 'dedenik' ); ?></p>
            <?php endif; ?>
        <?php endif; ?>
        <?php edit_post_link( __( 'Upravit stránku', 'dedenik' ), '<p>', '</p>' ); ?>
    </article>
<?php endwhile; ?>
<dialog id="photo-viewer" class="photo-viewer" aria-label="<?php esc_attr_e( 'Zvětšená fotografie', 'dedenik' ); ?>" aria-describedby="photo-viewer-description">
    <div class="photo-viewer-toolbar">
        <button type="button" class="photo-viewer-fullscreen" hidden data-restore-label="<?php esc_attr_e( 'Původní velikost', 'dedenik' ); ?>"><?php esc_html_e( 'Celá obrazovka', 'dedenik' ); ?></button>
        <button type="button" class="photo-viewer-close" autofocus><?php esc_html_e( 'Zavřít', 'dedenik' ); ?></button>
    </div>
    <div class="photo-viewer-frame"><img alt=""></div>
    <p id="photo-viewer-description" aria-live="polite"></p>
    <div class="photo-viewer-controls">
        <button type="button" data-step="-1"><?php esc_html_e( '← Předchozí', 'dedenik' ); ?></button>
        <button type="button" data-step="1"><?php esc_html_e( 'Další →', 'dedenik' ); ?></button>
    </div>
</dialog>
</div>
<?php get_footer(); ?>
