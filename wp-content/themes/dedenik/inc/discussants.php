<?php
/** Public comment archives with legacy nickname links and links from individual comments. */
defined( 'ABSPATH' ) || exit;

function dedenik_discussant_url( $name, $page = 1 ) {
    $args = array( 'diskutujici' => rawurlencode( dedenik_index_text( $name ) ) );
    if ( $page > 1 ) { $args['disk_strana'] = absint( $page ); }
    return add_query_arg( $args, home_url( '/' ) );
}
function dedenik_discussant_comment_url( $id, $page = 1 ) {
    $args = array( 'disk_komentar' => absint( $id ) );
    if ( $page > 1 ) { $args['disk_strana'] = absint( $page ); }
    return add_query_arg( $args, home_url( '/' ) );
}
function dedenik_is_discussant() {
    return null !== get_query_var( 'disk_komentar', null ) || null !== get_query_var( 'diskutujici', null );
}
function dedenik_discussant_empty( $status = 404 ) {
    return array( 'status' => $status, 'name' => '', 'records' => array(), 'total' => 0, 'page' => 1, 'pages' => 0 );
}
function dedenik_discussant_archive( $name, $page = 1 ) {
    $empty = dedenik_discussant_empty();
    if ( ! is_string( $name ) || ! is_scalar( $page ) || mb_strlen( $name ) > 255 || ! preg_match( '/^[1-9][0-9]{0,5}$/D', (string) $page ) ) { return $empty; }
    $key = dedenik_index_key( dedenik_index_text( $name ) );
    if ( '' === $key ) { return $empty; }
    if ( ! dedenik_comments_ready() ) { $empty['status'] = 503; return $empty; }
    $found = dedenik_comments_search( '', array(), array( 'participant' => $key, 'discussion' => true ), (int) $page, 20 );
    if ( ! $found['total'] || (int) $page > $found['pages'] ) { return $empty; }
    $records = array(); $display = '';
    foreach ( $found['ids'] as $id ) {
        $record = dedenik_search_result( $id, '', true );
        // Recheck attribution too, in case a comment changed between the SQL read and rendering.
        if ( ! $record || dedenik_index_key( $record['author'] ) !== $key ) { continue; }
        $display = $display ?: $record['author'];
        $records[] = $record;
    }
    if ( ! $records ) { return $empty; }
    return array( 'status' => 200, 'name' => $display, 'records' => $records, 'total' => $found['total'], 'page' => $found['page'], 'pages' => $found['pages'] );
}
function dedenik_discussant_comment_archive( $id, $page = 1 ) {
    global $wpdb;

    $empty = dedenik_discussant_empty();
    if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id )
        || ! filter_var( $id, FILTER_VALIDATE_INT )
        || ( ! is_int( $page ) && ! is_string( $page ) ) || ! preg_match( '/^[1-9][0-9]{0,5}$/D', (string) $page ) ) {
        return $empty;
    }

    // The first equality can use the WordPress email index. Fold case but keep accents and punctuation.
    $public = dedenik_comments_public_sql();
    $from = "FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
        JOIN (
            SELECT c.comment_author, c.comment_author_email
            FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID = c.comment_post_ID
            WHERE c.comment_ID = %d AND c.comment_author <> '' AND $public
        ) seed ON c.comment_author_email = seed.comment_author_email
              AND CAST(LOWER(c.comment_author_email) AS BINARY) = CAST(LOWER(seed.comment_author_email) AS BINARY)
              AND CAST(LOWER(c.comment_author) AS BINARY) = CAST(LOWER(seed.comment_author) AS BINARY)
        WHERE $public";
    $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) $from", $id ) );
    if ( $wpdb->last_error ) { return dedenik_discussant_empty( 503 ); }
    $pages = (int) ceil( $total / 20 );
    $page = (int) $page;
    if ( $page > $pages ) { return $empty; }

    $rows = $wpdb->get_results( $wpdb->prepare(
        "SELECT seed.comment_author AS archive_name, c.comment_ID, c.comment_post_ID, c.comment_author, c.comment_content, c.comment_date,
                c.comment_date_gmt, c.comment_parent, c.comment_approved, c.comment_type
         $from ORDER BY c.comment_date DESC, c.comment_ID DESC LIMIT 20 OFFSET %d",
        $id, ( $page - 1 ) * 20
    ) );
    if ( $wpdb->last_error ) { return dedenik_discussant_empty( 503 ); }

    $records = array();
    foreach ( $rows as $row ) {
        $comment = new WP_Comment( $row );
        $record = dedenik_comment_record( $comment );
        if ( ! $record ) { continue; }
        $record['url'] = get_comment_link( $comment );
        $records[] = $record;
    }
    if ( ! $records ) { return $empty; }
    return array(
        'status' => 200, 'name' => dedenik_index_text( $rows[0]->archive_name ), 'records' => $records,
        'total' => $total, 'page' => $page, 'pages' => $pages, 'seed_id' => (int) $id,
    );
}

function dedenik_discussant_context() {
    static $context = null;
    if ( null === $context ) {
        $id = get_query_var( 'disk_komentar', null );
        $name = get_query_var( 'diskutujici', null );
        $page = get_query_var( 'disk_strana', '1' );
        if ( null !== $id && null !== $name ) {
            $context = dedenik_discussant_empty();
        } elseif ( null !== $id ) {
            $context = dedenik_discussant_comment_archive( $id, $page );
        } else {
            $context = dedenik_discussant_archive( is_string( $name ) ? wp_unslash( $name ) : $name, $page );
        }
    }
    return $context;
}
add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'diskutujici'; $vars[] = 'disk_komentar'; $vars[] = 'disk_strana'; return $vars;
} );
add_filter( 'redirect_canonical', function ( $url ) { return dedenik_is_discussant() ? false : $url; } );
add_filter( 'template_include', function ( $template ) {
    return dedenik_is_discussant() ? get_template_directory() . '/commenter-archive.php' : $template;
} );
add_filter( 'document_title_parts', function ( $parts ) {
    if ( dedenik_is_discussant() ) {
        $context = dedenik_discussant_context();
        $parts['title'] = $context['name'] ? 'Komentáře – ' . $context['name'] : 'Komentáře diskutujícího';
        unset( $parts['tagline'] );
    }
    return $parts;
} );
add_filter( 'get_comment_author_link', function ( $link, $author, $comment_id ) {
    if ( is_admin() || is_feed() ) { return $link; }
    $comment = get_comment( $comment_id );
    if ( ! dedenik_comments_public( $comment ) ) { return $link; }
    $name = dedenik_index_text( $comment->comment_author );
    if ( '' === $name ) { return $link; }
    return '<a class="commenter-archive-link" href="' . esc_url( dedenik_discussant_comment_url( $comment->comment_ID ) ) . '" aria-label="' . esc_attr( 'Zobrazit veřejné komentáře: ' . $name ) . '">' . esc_html( $name ) . '</a>';
}, 20, 3 );
add_action( 'wp_enqueue_scripts', function () {
    if ( is_singular() || dedenik_is_discussant() ) {
        wp_enqueue_style( 'dedenik-discussants', get_template_directory_uri() . '/assets/css/discussants.css', array( 'dedenik' ), wp_get_theme()->get( 'Version' ) );
    }
} );
