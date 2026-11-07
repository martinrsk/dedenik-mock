<?php
/** Local SQL/rendering integration for public commenter archives; no AI requests. */
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
if ( wp_get_environment_type() !== 'local' || DB_NAME !== 'dedenik_local' ) { exit( "Local DB required.\n" ); }
global $wpdb;
$expected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE " . dedenik_comments_public_sql() );
if ( ! dedenik_comments_installed() || (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . dedenik_comments_table() ) !== $expected ) { exit( "Build the local discussion index first.\n" ); }
update_option( 'dedenik_comments_ready', true, false );
$checks = 0; $comments = array(); $posts = array();
function discussant_check( $ok, $message ) { $GLOBALS['checks']++; if ( ! $ok ) { throw new RuntimeException( $message ); } }
try {
    $yga = dedenik_discussant_archive( 'YGA' );
    $next = dedenik_discussant_archive( 'yga', 2 );
    discussant_check( $yga['status'] === 200 && count( $yga['records'] ) === 20 && $yga['total'] > 20, 'Complete public YGA archive available without API' );
    discussant_check( ! array_intersect( array_column( $yga['records'], 'comment_id' ), array_column( $next['records'], 'comment_id' ) ), 'Comment pages do not overlap' );
    discussant_check( dedenik_discussant_archive( 'YGA', $yga['pages'] + 1 )['status'] === 404, 'Out-of-range page is 404, not duplicate content' );
    foreach ( array( array(), '<script></script>', "' OR 1=1 --", 'unknown-fixture-' . bin2hex( random_bytes( 6 ) ) ) as $name ) {
        discussant_check( dedenik_discussant_archive( $name )['status'] === 404, 'Malformed/unknown name cannot enumerate other people' );
    }
    discussant_check( dedenik_discussant_archive( 'YGA', array( 2 ) )['status'] === 404 && dedenik_discussant_archive( 'YGA', '2 OR 1=1' )['status'] === 404, 'Page parameter strictly validated' );
    $name = 'Archivář & "Přezdívka" ' . bin2hex( random_bytes( 6 ) );
    foreach ( array( 'publish', 'private', 'password' ) as $state ) {
        $posts[] = wp_insert_post( array( 'post_title' => 'Commenter archive test', 'post_status' => 'password' === $state ? 'publish' : $state, 'post_password' => 'password' === $state ? 'hidden' : '' ) );
    }
    for ( $i = 0; $i < 22; $i++ ) {
        $comments[] = wp_insert_comment( array( 'comment_post_ID' => $posts[0], 'comment_author' => $name, 'comment_author_url' => 'https://example.invalid/blog', 'comment_author_email' => 'hidden@example.invalid', 'comment_author_IP' => '192.0.2.42', 'comment_content' => 'Public example ' . $i, 'comment_approved' => 1 ) );
    }
    foreach ( array( array( $posts[0], 0 ), array( $posts[1], 1 ), array( $posts[2], 1 ) ) as $hidden ) {
        $comments[] = wp_insert_comment( array( 'comment_post_ID' => $hidden[0], 'comment_author' => $name, 'comment_content' => 'Hidden example', 'comment_approved' => $hidden[1] ) );
    }
    $archive = dedenik_discussant_archive( $name );
    discussant_check( $archive['total'] === 22 && count( $archive['records'] ) === 20 && count( dedenik_discussant_archive( $name, 2 )['records'] ) === 2, 'All matching public comments and only those comments paginate' );
    $link = get_comment_author_link( $comments[0] );
    discussant_check( str_contains( $link, 'diskutujici=' ) && ! str_contains( $link, 'example.invalid/blog' ) && str_contains( $link, '&amp;' ), 'Author link points to local archive with escaped public label' );
    $url = html_entity_decode( dedenik_discussant_url( $name, 2 ), ENT_QUOTES, 'UTF-8' );
    parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $params );
    discussant_check( $params['diskutujici'] === $name && (int) $params['disk_strana'] === 2, 'URL roundtrips Unicode, ampersands and quotes' );
    set_query_var( 'diskutujici', wp_slash( $name ) ); set_query_var( 'disk_strana', '1' );
    discussant_check( dedenik_discussant_context()['total'] === 22, 'WordPress slashed request variables resolve the correct nickname' );
    discussant_check( dedenik_is_discussant() && apply_filters( 'redirect_canonical', home_url( '/' ) ) === false, 'Commenter route preserves its own URL' );
    if ( in_array( '--http', $argv, true ) && wp_parse_url( home_url(), PHP_URL_HOST ) === '127.0.0.1' ) {
        $http = file_get_contents( dedenik_discussant_url( $name ), false, stream_context_create( array( 'http' => array( 'timeout' => 20 ) ) ) );
        discussant_check( is_string( $http ) && substr_count( $http, 'data-comment-id=' ) === 20, 'HTTP archive resolves quoted Unicode nickname' );
        preg_match_all( '/href="([^"]+)"/', $http, $hrefs );
        $next_url = '';
        foreach ( $hrefs[1] as $href ) {
            $href = html_entity_decode( $href, ENT_QUOTES, 'UTF-8' );
            parse_str( wp_parse_url( $href, PHP_URL_QUERY ) ?? '', $query );
            if ( (string) ( $query['disk_strana'] ?? '' ) === '2' ) {
                discussant_check( ( $query['diskutujici'] ?? '' ) === $name, 'HTTP pagination preserves entire nickname' );
                $next_url = $href; break;
            }
        }
        discussant_check( (bool) $next_url, 'HTTP next-page link exists' );
        $next_html = file_get_contents( $next_url );
        discussant_check( substr_count( $next_html, 'data-comment-id=' ) === 2, 'HTTP next page has remaining comments' );
    }
    discussant_check( ! str_contains( get_comment_author_link( $comments[22] ), 'diskutujici=' ), 'No archive links for unapproved comment' );
    discussant_check( ! str_contains( wp_json_encode( $archive ), 'hidden@example.invalid' ) && ! str_contains( wp_json_encode( $archive ), '192.0.2.42' ), 'No email, IP or account metadata returned' );
    $wpdb->update( $wpdb->comments, array( 'comment_approved' => '0' ), array( 'comment_ID' => $comments[0] ) ); clean_comment_cache( $comments[0] );
    discussant_check( dedenik_discussant_archive( $name )['total'] === 21, 'Live moderation excludes stale indexed row' );
    wp_update_post( array( 'ID' => $posts[0], 'post_password' => 'secret' ) );
    discussant_check( dedenik_discussant_archive( $name )['status'] === 404, 'Protected parent hides entire commenter archive when no public comments remain' );
    update_option( 'dedenik_comments_ready', false, false );
    discussant_check( dedenik_discussant_archive( 'YGA' )['status'] === 503, 'Unavailable index produces explicit temporary response' );
    update_option( 'dedenik_comments_ready', true, false );
    echo "Commenter archives: $checks real database checks passed. YGA: " . $yga['total'] . " public comments.\n";
} finally {
    update_option( 'dedenik_comments_ready', true, false );
    foreach ( $comments as $id ) { wp_delete_comment( $id, true ); }
    foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
}
