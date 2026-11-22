<?php
defined( 'ABSPATH' ) || exit;
if ( post_password_required() ) { return; }
$discussion_open = comments_open();
if ( $discussion_open ) {
    wp_enqueue_script( 'dedenik-comment-emojis', get_theme_file_uri( 'assets/js/comment-emojis.js' ), array(), wp_get_theme()->get( 'Version' ), true );
}
?>
<section id="comments" class="comments-area" aria-labelledby="comments-title">
<h2 id="comments-title"><span id="comment-area"><?php printf( esc_html__( 'Diskuse (%s)', 'dedenik' ), esc_html( number_format_i18n( get_comments_number() ) ) ); ?></span></h2>
<?php if ( $discussion_open ) : ?>
    <p><a id="dedenik-add-comment" class="button" href="<?php echo esc_url( remove_query_arg( array( 'replytocom', 'unapproved', 'moderation-hash' ) ) . '#respond' ); ?>"><?php esc_html_e( 'Přidat komentář', 'dedenik' ); ?></a></p>
<?php endif; ?>
<?php if ( have_comments() ) : ?>
    <?php the_comments_pagination( array( 'prev_text' => __( '← Předchozí', 'dedenik' ), 'next_text' => __( 'Další →', 'dedenik' ) ) ); ?>
    <ol class="comment-list"><?php wp_list_comments( array( 'style' => 'ol', 'short_ping' => true, 'avatar_size' => 40 ) ); ?></ol>
    <?php the_comments_pagination( array( 'prev_text' => __( '← Předchozí', 'dedenik' ), 'next_text' => __( 'Další →', 'dedenik' ) ) ); ?>
<?php endif; ?>
<?php if ( ! $discussion_open ) : ?>
    <p class="comments-closed"><?php esc_html_e( 'Diskuse je uzavřená.', 'dedenik' ); ?></p>
<?php endif; ?>
<?php comment_form(); ?>
</section>
