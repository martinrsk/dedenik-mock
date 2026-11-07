<?php defined( 'ABSPATH' ) || exit;
// Editorial author identities live in post tags, not publishing user accounts.
$authors = array(
    array( 'name' => 'Dede', 'slug' => 'dagmar-ruscakova' ),
    array( 'name' => 'Matylda', 'slug' => 'matylda' ),
    array( 'name' => 'EvaB', 'slug' => 'evab' ),
    array( 'name' => 'Netopýrka', 'slug' => 'netopyrka' ),
    array( 'name' => 'Tora', 'slug' => 'tora' ),
    array( 'name' => 'Pů', 'slug' => 'pu' ),
    array( 'name' => 'Yga', 'slug' => 'yga' ),
    array( 'name' => 'Alex', 'slug' => 'alex' ),
    array( 'name' => 'MartinR', 'slug' => 'martinr' ),
    array( 'name' => 'DavidL', 'slug' => 'davidl' ),
    array( 'name' => 'Aries', 'slug' => 'aloisie-trpkosova-aries' ),
);
?>
<section class="author-section" id="autori"><h2><?php esc_html_e( 'Autoři, které můžete znát', 'dedenik' ); ?></h2><div class="authors">
<?php foreach ( $authors as $author ) :
    $term = get_term_by( 'slug', $author['slug'], 'post_tag' );
    $url = $term && ! is_wp_error( $term ) ? get_term_link( $term ) : '';
    $available = $url && ! is_wp_error( $url );
    $tag = $available ? 'a' : 'span';
?>
<<?php echo $tag; ?> class="author"<?php if ( $available ) : ?> href="<?php echo esc_url( $url ); ?>"<?php else : ?> aria-disabled="true"<?php endif; ?>><span class="author-initial" aria-hidden="true"><?php echo esc_html( wp_html_excerpt( $author['name'], 1, '' ) ); ?></span><span><?php echo esc_html( $author['name'] ); ?></span></<?php echo $tag; ?>>
<?php endforeach; ?>
<a class="author" href="https://www.dedenik.cz/#f2-tagcloud-2"><span class="author-initial" aria-hidden="true">…</span><span><?php esc_html_e( 'a další', 'dedenik' ); ?></span></a>
</div></section>
