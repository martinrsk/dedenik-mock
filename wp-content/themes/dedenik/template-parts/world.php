<?php
defined( 'ABSPATH' ) || exit;
$items = dedenik_world_items();
$card_descriptions = array(
    'podcast' => 'S nadhledem a optimisticky o lidech, zvířatech, knihách a divadle',
    'books' => 'Knihy Dede – román, povídky, vyprávění, fejetony',
    'radio' => 'Fejetony pro český rozhlas – o životě kolem nás a s kapkou optimismu.',
);
if ( ! $items ) { return; }
$has_theatre = false;
$landing_id = absint( get_theme_mod( 'dedenik_world_landing', 0 ) );
$landing = $landing_id ? get_post( $landing_id ) : null;
?>
<section class="world-section section"><div class="wrap"><div class="section-heading"><h2><?php echo esc_html( get_theme_mod( 'dedenik_world_heading', __( 'Ze Světa Dede', 'dedenik' ) ) ); ?></h2>
<?php if ( $landing && 'publish' === $landing->post_status && 'page' === $landing->post_type && ! $landing->post_password ) : ?><a href="<?php echo esc_url( get_permalink( $landing ) ); ?>"><?php esc_html_e( 'Celý Svět Dede →', 'dedenik' ); ?></a><?php endif; ?>
</div><div class="world-grid">
<?php foreach ( $items as $item ) :
    if ( 'theatre' === $item['key'] ) { $has_theatre = true; }
?>
<article class="world-card world-card-<?php echo esc_attr( $item['key'] ); ?>">
<?php if ( 'podcast' === $item['key'] ) : ?><span class="world-podcast-new"><?php esc_html_e( 'NOVÉ', 'dedenik' ); ?></span><?php endif; ?>
<?php
$image = in_array( $item['key'], array( 'theatre', 'books' ), true ) ? '' : ( $item['image'] ? wp_get_attachment_image( $item['image'], 'medium', false, array( 'class' => 'world-image', 'alt' => 'radio' === $item['key'] ? 'Dagmar Ruščáková ve studiu Českého rozhlasu' : '', 'loading' => 'lazy' ) ) : '' );
if ( 'podcast' === $item['key'] ) { $image = ''; }
if ( ! $image && ! in_array( $item['key'], array( 'podcast', 'theatre', 'books' ), true ) && $item['page'] ) { $image = get_the_post_thumbnail( $item['page'], 'medium', array( 'class' => 'world-image', 'alt' => '', 'loading' => 'lazy' ) ); }
if ( $image ) { echo $image; }
else { $pictures = array( 'podcast' => 'podcast.png', 'theatre' => 'dedivadlo-logo.jpg', 'books' => 'book-logo.png', 'radio' => 'radio-columnist.png' ); $image_height = in_array( $item['key'], array( 'podcast', 'radio' ), true ) ? 110 : 170; ?>
<?php if ( 'podcast' === $item['key'] ) : ?><span class="world-podcast-art"><?php endif; ?>
<img class="world-image" src="<?php echo esc_url( get_theme_file_uri( 'assets/images/' . $pictures[ $item['key'] ] ) ); ?>" alt="<?php echo 'theatre' === $item['key'] ? esc_attr( 'Logo Dedivadla se čtyřmi členkami souboru' ) : ( 'radio' === $item['key'] ? esc_attr( 'Dagmar Ruščáková ve studiu Českého rozhlasu' ) : '' ); ?>" loading="lazy" width="110" height="<?php echo esc_attr( $image_height ); ?>">
<?php if ( 'podcast' === $item['key'] ) : ?>
</span>
<?php endif; ?>
<?php } ?>
<div><span class="badge"><?php echo esc_html( $item['label'] ); ?></span>
<?php if ( 'theatre' === $item['key'] ) : ?><div class="theatre-program-list" aria-label="<?php echo esc_attr( __( 'Program Dedivadla', 'dedenik' ) ); ?>"><?php foreach ( dedenik_theatre_program() as $performance ) : ?><p><strong><?php echo esc_html( $performance['date'] ); ?></strong> · <?php echo esc_html( $performance['title'] ); ?> · <?php echo esc_html( $performance['time'] ); ?> · <?php echo esc_html( $performance['location'] ); ?></p><?php endforeach; ?></div>
<?php elseif ( isset( $card_descriptions[ $item['key'] ] ) ) : ?><p class="world-description"><?php echo esc_html( $card_descriptions[ $item['key'] ] ); ?></p><?php endif; ?>
<a class="button world-button"<?php if ( 'theatre' === $item['key'] ) : ?> data-theatre-dialog<?php endif; ?> href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['button'] ); ?></a></div></article>
<?php endforeach; ?>
</div></div></section>

<?php if ( $has_theatre ) { get_template_part( 'template-parts/theatre-dialog' ); } ?>
