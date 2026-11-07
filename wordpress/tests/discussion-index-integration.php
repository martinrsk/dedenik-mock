<?php
/** Real comment-index integration on the isolated local database; no Google calls. */
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
if ( wp_get_environment_type() !== 'local' || DB_NAME !== 'dedenik_local' ) { exit( "Local DB required.\n" ); }
global $wpdb;
$checks = 0; $posts = array(); $comments = array(); $calls = 0;
$saved = array();
foreach ( array( 'dedenik_comments_ready', 'dedenik_ai_enabled', 'dedenik_ai_key', 'dedenik_ai_daily_limit', 'dedenik_ai_daily_' . gmdate( 'Ymd' ), 'page_comments', 'comments_per_page' ) as $name ) { $saved[ $name ] = get_option( $name, null ); }
function discussion_check( $ok, $message ) { $GLOBALS['checks']++; if ( ! $ok ) { throw new RuntimeException( $message ); } }
try {
    discussion_check( ! is_wp_error( dedenik_comments_install() ), 'Schema and FULLTEXT created' );
    if ( in_array( '--build-archive', $argv, true ) || in_array( '--check-archive', $argv, true ) ) {
        if ( in_array( '--build-archive', $argv, true ) ) {
        $cursor = 0;
        do {
            $batch = dedenik_comments_batch( $cursor, 0, 1000 );
            discussion_check( ! is_wp_error( $batch ), 'Bulk archive batch succeeds' );
            $cursor = $batch['cursor'];
        } while ( ! $batch['done'] );
        }
        update_option( 'dedenik_comments_ready', true, false );
        $expected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->comments} c JOIN {$wpdb->posts} p ON p.ID=c.comment_post_ID WHERE " . dedenik_comments_public_sql() );
        discussion_check( (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . dedenik_comments_table() ) === $expected, 'Complete public discussion archive' );
        $real = dedenik_comments_search( 'kdy mluvila YGA o koních', array( 'kon', 'koň', 'kůň' ), dedenik_comments_intent( 'kdy mluvila YGA o koních' ) );
        echo 'Archive comments indexed: ' . $expected . '; YGA horses matches: ' . $real['total'] . "\n";
    }
    update_option( 'dedenik_comments_ready', true, false );
    $speaker = 'Diskutujici' . bin2hex( random_bytes( 6 ) );
    $marker = 'debatefixture' . bin2hex( random_bytes( 6 ) );
    foreach ( array( 'publish', 'private', 'password' ) as $status ) {
        $posts[] = wp_insert_post( array( 'post_title' => 'Discussion fixture', 'post_content' => 'Public article', 'post_status' => 'password' === $status ? 'publish' : $status, 'post_password' => 'password' === $status ? 'hidden' : '' ) );
    }
    for ( $i = 0; $i < 15; $i++ ) {
        $comments[] = wp_insert_comment( array( 'comment_post_ID' => $posts[0], 'comment_author' => $speaker, 'comment_author_email' => 'must-not-leak@example.invalid', 'comment_author_IP' => '192.0.2.88', 'comment_content' => "Koně $marker příběh $i", 'comment_date' => '2026-10-22 12:00:00', 'comment_approved' => 1 ) );
    }
    foreach ( array( '0', 'spam', 'trash' ) as $status ) {
        $comments[] = wp_insert_comment( array( 'comment_post_ID' => $posts[0], 'comment_author' => $speaker, 'comment_content' => $marker, 'comment_approved' => $status ) );
    }
    $comments[] = wp_insert_comment( array( 'comment_post_ID' => $posts[0], 'comment_author' => $speaker, 'comment_content' => 'Konec projektu a kontakt na koncert.', 'comment_date' => '2026-10-22 12:00:00', 'comment_approved' => 1 ) );
    foreach ( array( $posts[1], $posts[2] ) as $parent ) {
        $comments[] = wp_insert_comment( array( 'comment_post_ID' => $parent, 'comment_author' => $speaker, 'comment_content' => $marker, 'comment_approved' => 1 ) );
    }
    $comments[] = wp_insert_comment( array( 'comment_post_ID' => $posts[0], 'comment_author' => $speaker, 'comment_content' => $marker, 'comment_approved' => 1, 'comment_type' => 'pingback' ) );
    $intent = dedenik_comments_intent( "Kdy mluvila $speaker o koních" );
    discussion_check( $intent['participant'] === dedenik_index_key( $speaker ), 'Parse named participant without model' );
    discussion_check( dedenik_comments_search( 'koně', array( 'kon' ), $intent )['total'] === 15, 'Horse inflections do not match konec, kontakt or koncert' );
    $first = dedenik_comments_search( $marker, array( $marker ), $intent );
    $second = dedenik_comments_search( $marker, array( $marker ), $intent, 2 );
    discussion_check( $first['total'] === 15 && count( $first['ids'] ) === 12 && count( $second['ids'] ) === 3, 'Approved public comments paginate completely' );
    discussion_check( count( array_unique( array_merge( $first['ids'], $second['ids'] ) ) ) === 15, 'No duplicate comments across pages' );
    $record = dedenik_search_result( $first['ids'][0], $marker, true );
    discussion_check( $record['type'] === 'comment' && str_contains( $record['url'], '#comment-' ), 'Comment result has direct WordPress discussion link' );
    discussion_check( ! str_contains( wp_json_encode( $record ), 'must-not-leak' ) && ! str_contains( wp_json_encode( $record ), '192.0.2.88' ), 'No email or IP in result payload' );
    $columns = $wpdb->get_col( 'SHOW COLUMNS FROM ' . dedenik_comments_table() );
    discussion_check( ! in_array( 'comment_author_email', $columns ) && ! in_array( 'comment_author_IP', $columns ), 'Private metadata absent from schema' );
    update_option( 'page_comments', true ); update_option( 'comments_per_page', 2 );
    $link = dedenik_search_result( $first['ids'][0], $marker, true )['url'];
    discussion_check( str_contains( $link, 'comment-page-' ) || str_contains( $link, 'cpage=' ), 'Direct links account for comment pagination' );
    wp_set_comment_status( $comments[0], 'hold' );
    discussion_check( dedenik_search_result( -$comments[0], $marker, true ) === null, 'Cached ID cannot expose unapproved comment' );
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $intent )['total'] === 14, 'Unapproval updates comment index' );
    wp_set_comment_status( $comments[0], 'approve' );
    wp_update_comment( array( 'comment_ID' => $comments[0], 'comment_content' => 'Changed topic', 'comment_author' => 'Changed speaker' ) );
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $intent )['total'] === 14, 'Edited content and speaker synchronize' );
    wp_delete_comment( $comments[1], true );
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $intent )['total'] === 13, 'Deletion updates index' );
    $date_intent = $intent; $date_intent['date_from'] = '2099-01-01';
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $date_intent )['total'] === 0, 'Date restriction applies in SQL' );
    $wpdb->update( $wpdb->posts, array( 'post_password' => 'secret' ), array( 'ID' => $posts[0] ) ); clean_post_cache( $posts[0] );
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $intent )['total'] === 0 && dedenik_search_result( -$comments[2], $marker, true ) === null, 'Live parent visibility gates stale index and cached IDs' );
    wp_update_post( array( 'ID' => $posts[0], 'post_password' => '' ) );
    discussion_check( dedenik_comments_search( $marker, array( $marker ), $intent )['total'] === 13, 'Public parent restored' );
    $calendar_intent = dedenik_comments_intent( 'o čem jsme se bavili v říjnu 2026', array( 'date_from' => '2026-10-01', 'date_to' => '2026-10-31' ) );
    $calendar_result = dedenik_comments_search( 'o čem jsme se bavili v říjnu 2026', array( 'o čem jsme se bavili v říjnu 2026' ), $calendar_intent );
    discussion_check( $calendar_result['total'] >= 15, 'Calendar restriction must not also require month/year to appear in comment body' );
    update_option( 'dedenik_ai_enabled', true ); update_option( 'dedenik_ai_key', 'not-a-real-discussion-key' ); update_option( 'dedenik_ai_daily_limit', 50 );
    delete_option( 'dedenik_ai_daily_' . gmdate( 'Ymd' ) );
    $_SERVER['REMOTE_ADDR'] = '192.0.2.245';
    delete_transient( 'dedenik_ai_rate_' . hash_hmac( 'sha256', $_SERVER['REMOTE_ADDR'], wp_salt( 'nonce' ) ) );
    add_filter( 'pre_http_request', function ( $pre, $args, $url ) use ( &$calls, $speaker, $marker, &$comments ) {
        if ( $url !== dedenik_ai_endpoint() ) { return $pre; }
        $calls++;
        $sent = json_decode( json_decode( $args['body'], true )['contents'][0]['parts'][0]['text'], true );
        if ( isset( $sent['records'] ) ) {
            discussion_check( count( $sent['records'] ) <= 32, 'Mixed provider candidates remain bounded' );
            discussion_check( ! str_contains( wp_json_encode( $sent ), 'must-not-leak' ) && ! str_contains( wp_json_encode( $sent ), '192.0.2.88' ), 'Mixed provider payload excludes private metadata' );
            $public_comments = array_filter( $sent['records'], function ( $record ) { return ( $record['type'] ?? '' ) === 'comment'; } );
            discussion_check( count( $public_comments ) > 0, 'General topic search includes discussion candidates' );
            $out = array( 'ids' => array( -(int) $comments[15], reset( $public_comments )['id'] ) );
        } else {
        discussion_check( array_keys( $sent ) === array( 'query' ), 'Discussion lookup sends only query for planning, no comment database' );
        $out = array( 'terms' => array( $marker ), 'discussion' => str_contains( $sent['query'], $speaker ), 'participant' => str_contains( $sent['query'], $speaker ) ? $speaker : '' );
        }
        return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'candidates' => array( array( 'finishReason' => 'STOP', 'content' => array( 'parts' => array( array( 'text' => wp_json_encode( $out ) ) ) ) ) ) ) ) );
    }, PHP_INT_MAX, 3 );
    $query = "Kdy mluvila $speaker o $marker";
    $pagination = null;
    $ids = dedenik_ai_search( $query, 1, $pagination );
    discussion_check( count( $ids ) === 12 && $pagination['total'] === 13 && $pagination['mode'] === 'discussion', 'AI dispatch selects full discussion results' );
    $ids = dedenik_ai_search( $query, 2, $pagination );
    discussion_check( count( $ids ) === 1 && $calls === 1, 'Second discussion page reuses plan without new provider call' );
    $ids = dedenik_ai_search( $marker );
    discussion_check( count( $ids ) === 1 && $ids[0] < 0 && $ids[0] !== -(int) $comments[15] && $calls === 3, 'Model may return only public comment candidates, never pending ID' );
    $untrusted = dedenik_comments_intent( 'diskuse o koních', array( 'participant' => 'Invented person' ) );
    discussion_check( $untrusted['participant'] === '', 'Model cannot invent a participant not named in query' );
    echo "Discussion index: $checks real database checks passed.\n";
} finally {
    foreach ( $comments as $id ) { wp_delete_comment( $id, true ); }
    foreach ( $posts as $id ) { wp_delete_post( $id, true ); }
    foreach ( $saved as $name => $value ) { if ( null === $value ) { delete_option( $name ); } else { update_option( $name, $value, false ); } }
}
