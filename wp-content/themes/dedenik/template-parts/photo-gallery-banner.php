<?php
defined( 'ABSPATH' ) || exit;

$gallery = get_page_by_path( 'fotogalerie' );
if ( ! $gallery || 'publish' !== $gallery->post_status || $gallery->post_password ) {
    return;
}
?>
<div class="section wrap photo-gallery-banner-row" id="fotogalerie-martina-r">
    <a class="photo-gallery-banner" href="<?php echo esc_url( get_permalink( $gallery ) ); ?>">
        <img src="<?php echo esc_url( get_theme_file_uri( 'assets/images/fotogalerie-martina-r.webp' ) ); ?>" alt="" width="1350" height="450" loading="lazy" decoding="async">
        <span><?php esc_html_e( 'Fotogalerie Martina R.', 'dedenik' ); ?></span>
    </a>
</div>
