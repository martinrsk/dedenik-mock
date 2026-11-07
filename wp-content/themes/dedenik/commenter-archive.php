<?php
defined( 'ABSPATH' ) || exit;
$context = dedenik_discussant_context();
status_header( $context['status'] );
nocache_headers();
if ( 404 === $context['status'] ) { global $wp_query; $wp_query->set_404(); }
get_header();
?>
<div class="wrap section commenter-archive">
<?php if ( 200 === $context['status'] ) : ?>
    <header class="archive-header">
        <p class="kicker">Z diskusí Dedeníku</p>
        <h1>Komentáře: <?php echo esc_html( $context['name'] ); ?></h1>
        <p>Veřejné komentáře pod touto přezdívkou, od nejnovějších.</p>
        <p class="commenter-meta">Celkem <?php echo esc_html( number_format_i18n( $context['total'] ) ); ?> · Strana <?php echo esc_html( $context['page'] . ' z ' . $context['pages'] ); ?></p>
    </header>
    <ol class="commenter-list" aria-label="Veřejné komentáře diskutujícího" start="<?php echo esc_attr( ( $context['page'] - 1 ) * 20 + 1 ); ?>">
    <?php foreach ( $context['records'] as $record ) : ?>
        <li class="commenter-item" data-comment-id="<?php echo esc_attr( $record['comment_id'] ); ?>">
            <article>
                <h2><a href="<?php echo esc_url( $record['url'] ); ?>"><?php echo esc_html( get_the_title( $record['post_id'] ) ); ?></a></h2>
                <time class="commenter-meta" datetime="<?php echo esc_attr( mysql2date( DATE_W3C, $record['date'], false ) ); ?>"><?php echo esc_html( mysql2date( 'j. n. Y H:i', $record['date'] ) ); ?></time>
                <p class="commenter-excerpt"><?php echo esc_html( wp_html_excerpt( $record['excerpt'], 400, '…' ) ); ?></p>
                <a href="<?php echo esc_url( $record['url'] ); ?>">Otevřít komentář v diskusi →</a>
            </article>
        </li>
    <?php endforeach; ?>
    </ol>
    <?php
    $pagination_url = isset( $context['seed_id'] ) ? dedenik_discussant_comment_url( $context['seed_id'], 999999999 )
        : dedenik_discussant_url( $context['name'], 999999999 );
    $base = str_replace( 'disk_strana=999999999', 'disk_strana=%#%', $pagination_url );
    $links = paginate_links( array( 'base' => $base, 'format' => '', 'current' => $context['page'], 'total' => $context['pages'], 'mid_size' => 1, 'end_size' => 1, 'prev_text' => '← Novější', 'next_text' => 'Starší →' ) );
    if ( $links ) { echo '<nav class="pagination" aria-label="Stránkování komentářů diskutujícího">' . $links . '</nav>'; }
    ?>
<?php elseif ( 503 === $context['status'] ) : ?>
    <h1>Přehled komentářů se připravuje</h1>
    <p>Výpis komentářů teď není dostupný. Zkuste to prosím později.</p>
<?php else : ?>
    <h1>Komentáře jsme nenašli</h1>
    <p>Pro tuto přezdívku nebo stránku výpisu nejsou dostupné žádné veřejné komentáře.</p>
    <p><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Zpět na Dedeník →</a></p>
<?php endif; ?>
</div>
<?php get_footer(); ?>
