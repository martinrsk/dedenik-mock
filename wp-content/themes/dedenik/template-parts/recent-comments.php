<?php
defined( 'ABSPATH' ) || exit;
$recent_comments = dedenik_recent_article_comments();
?>
<aside class="recent-comments" aria-labelledby="recent-comments-heading">
    <h2 id="recent-comments-heading"><?php esc_html_e( 'Vaše nejnovější komentáře', 'dedenik' ); ?></h2>
    <?php if ( $recent_comments ) : ?>
        <div class="recent-comments-scroll" role="region" aria-labelledby="recent-comments-heading" tabindex="0">
            <ol>
                <?php foreach ( $recent_comments as $recent_comment ) :
                    $author = get_comment_author( $recent_comment );
                    $excerpt = wp_trim_words( dedenik_index_text( $recent_comment->comment_content ), 50, '…' );
                    $title = get_the_title( $recent_comment->comment_post_ID );
                    if ( '' === $title ) { $title = __( 'Článek bez názvu', 'dedenik' ); }
                    ?>
                    <li>
                        <strong><?php echo esc_html( $author ); ?></strong>
                        <?php if ( '' !== $excerpt ) : ?><p><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
                        <a href="<?php echo esc_url( get_comment_link( $recent_comment ) ); ?>"><?php echo esc_html( $title ); ?> <span aria-hidden="true">→</span></a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    <?php else : ?>
        <p><?php esc_html_e( 'Zatím tu nejsou žádné komentáře.', 'dedenik' ); ?></p>
    <?php endif; ?>
</aside>
