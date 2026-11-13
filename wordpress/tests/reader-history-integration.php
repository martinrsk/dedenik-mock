<?php
/**
 * CLI integration for legacy links and indexed public reader archives on local WordPress.
 * Use the Dedeník theme and an isolated DB named dedenik_reader_test_<8 hex chars>.
 * Run php wordpress/tests/reader-history-integration.php /path/to/test/wp-load.php.
 */

if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) {
    exit( 1 );
}
require $argv[1];
if ( 'local' !== wp_get_environment_type()
    || ! preg_match( '/^dedenik_reader_test_[a-f0-9]{8}$/D', DB_NAME )
    || ! preg_match( '/^127\.0\.0\.1(?::[0-9]+)?$/D', DB_HOST )
    || ! function_exists( 'dedenik_discussant_comment_archive' ) ) {
    fwrite( STDERR, "An isolated local database and the Dedeník theme are required.\n" );
    exit( 1 );
}

function reader_check( $ok, $message ) {
    if ( ! $ok ) {
        throw new RuntimeException( $message );
    }
    ++$GLOBALS['reader_checks'];
}

$reader_checks = 0;
$posts = array();
$comments = array();
$add_comment = function ( $post, $name, $email, $status = '1', $type = 'comment' ) use ( &$comments ) {
    $id = wp_insert_comment( array(
        'comment_post_ID'      => $post,
        'comment_author'       => $name,
        'comment_author_email' => $email,
        'comment_content'      => 'Fixture ' . count( $comments ),
        'comment_approved'     => $status,
        'comment_type'         => $type,
    ) );
    if ( ! $id ) {
        throw new RuntimeException( 'Unable to insert fixture comment.' );
    }
    $comments[] = $id;
    return (int) $id;
};

try {
    foreach ( array( 'publish', 'private', 'password' ) as $visibility ) {
        $id = wp_insert_post( array(
            'post_status'   => 'password' === $visibility ? 'publish' : $visibility,
            'post_password' => 'password' === $visibility ? 'fixture' : '',
            'post_title'    => 'Reader archive fixture',
        ), true );
        reader_check( ! is_wp_error( $id ) && $id, 'Unable to create fixture post.' );
        $posts[] = $id;
    }

    $suffix = bin2hex( random_bytes( 4 ) );
    $nickname = 'A & B 50%_Žába ' . $suffix;
    $email = 'test%_a@example.invalid';
    $same = array();
    for ( $i = 0; $i < 22; ++$i ) {
        $same[] = $add_comment( $posts[0], $nickname, $email );
    }
    $same[] = $add_comment( $posts[0], mb_strtolower( $nickname ), $email );
    $same[] = $add_comment( $posts[0], $nickname, strtoupper( $email ) );
    $seed = $same[0];
    // These lookalikes must not match the seed, even with a case/accent-insensitive DB collation.
    $other_email = $add_comment( $posts[0], $nickname, 'other@example.invalid' );
    $other_name = $add_comment( $posts[0], 'Other reader ' . $suffix, $email );
    $add_comment( $posts[0], 'A & B 50XXŽába ' . $suffix, $email );
    $add_comment( $posts[0], 'A & B 50%XŽába ' . $suffix, $email );
    $add_comment( $posts[0], $nickname, 'testXXa@example.invalid' );
    $add_comment( $posts[0], $nickname, 'test%Xa@example.invalid' );
    $add_comment( $posts[0], 'A & B 50%_Zaba ' . $suffix, $email );
    $hidden = array(
        $add_comment( $posts[0], $nickname, $email, '0' ),
        $add_comment( $posts[0], $nickname, $email, 'spam' ),
        $add_comment( $posts[0], $nickname, $email, '1', 'pingback' ),
        $add_comment( $posts[1], $nickname, $email ),
        $add_comment( $posts[2], $nickname, $email ),
    );
    $blank = array(
        $add_comment( $posts[0], $nickname, '' ),
        $add_comment( $posts[0], $nickname, '' ),
    );
    $add_comment( $posts[0], 'Unrelated blank email', '' );

    $page_one = dedenik_discussant_comment_archive( $seed );
    $page_two = dedenik_discussant_comment_archive( (string) $seed, 2 );
    reader_check( 200 === $page_one['status'] && 200 === $page_two['status'], 'Archive works without a discussion index.' );
    reader_check( 24 === $page_one['total'] && 2 === $page_one['pages'], 'Total must include only public comments with the same nickname and email, ignoring only case.' );
    $expected = array_reverse( $same );
    reader_check( array_slice( $expected, 0, 20 ) === array_column( $page_one['records'], 'comment_id' ), 'First page returns the newest 20 matching IDs.' );
    reader_check( array_slice( $expected, 20 ) === array_column( $page_two['records'], 'comment_id' ), 'Second page contains only the four remaining IDs.' );

    $blank_page = dedenik_discussant_comment_archive( $blank[0] );
    reader_check( array_reverse( $blank ) === array_column( $blank_page['records'], 'comment_id' ), 'Blank email matches only blank email under the exact same nickname.' );
    foreach ( $hidden as $id ) {
        reader_check( 404 === dedenik_discussant_comment_archive( $id )['status'], 'Hidden and non-comment seeds must be rejected.' );
    }
    foreach ( array( 0, -1, array( $seed ), $seed . ' OR 1=1', '1e1' ) as $invalid ) {
        reader_check( 404 === dedenik_discussant_comment_archive( $invalid )['status'], 'A seed must be a positive decimal ID.' );
    }
    foreach ( array( 0, -2, '2 OR 1=1', array( '2' ), 3 ) as $invalid ) {
        reader_check( 404 === dedenik_discussant_comment_archive( $seed, $invalid )['status'], 'Invalid or out-of-range pages are 404.' );
    }

    $url = dedenik_discussant_comment_url( $seed, 2 );
    parse_str( wp_parse_url( $url, PHP_URL_QUERY ), $params );
    reader_check( array( 'disk_komentar' => (string) $seed, 'disk_strana' => '2' ) == $params, 'Pagination URL carries only the public ID and page.' );
    $link = get_comment_author_link( $seed );
    $target = dedenik_reader_index_ready() ? dedenik_discussant_profile_url( dedenik_reader_profile( get_comment( $seed ) ) ) : dedenik_discussant_comment_url( $seed );
    reader_check( str_contains( $link, 'A &amp; B' ) && str_contains( $link, esc_url( $target ) ), 'Escaped author label has a working archive target while the index becomes ready.' );
    foreach ( $hidden as $id ) {
        reader_check( ! preg_match( '/(?:disk_profil|disk_komentar)=/', get_comment_author_link( $id ) ), 'Hidden seed does not render an archive link.' );
    }
    $public_output = wp_json_encode( array( $page_one, $page_two, $url, $link ) );
    reader_check( ! str_contains( rawurldecode( $public_output ), '@example.invalid' ), 'Raw or URL-encoded private email escaped into the public archive.' );

    // Use small batches to exercise continuation over multiple identities and pages.
    $batches = 0;
    do {
        $finished = dedenik_reader_index_batch( 7 );
        reader_check( ! is_wp_error( $finished ), is_wp_error( $finished ) ? $finished->get_error_message() : '' );
        reader_check( ++$batches < 1000, 'Index population must finish on this isolated fixture.' );
    } while ( true !== $finished );

    $profile = dedenik_reader_profile( get_comment( $seed ) );
    reader_check( 1 === preg_match( '/^[a-f0-9]{64}$/D', $profile ), 'Canonical URL uses a public opaque identifier.' );
    $canonical = dedenik_discussant_profile_url( $profile );
    $second_url = dedenik_discussant_profile_url( $profile, 2 );
    parse_str( wp_parse_url( $second_url, PHP_URL_QUERY ), $params );
    reader_check( array( 'disk_profil' => $profile, 'disk_strana' => '2' ) == $params, 'Canonical pagination preserves only the profile and page.' );
    foreach ( array( $seed, $same[10], end( $same ) ) as $id ) {
        reader_check( $profile === dedenik_reader_profile( get_comment( $id ) ) && str_contains( get_comment_author_link( $id ), esc_url( $canonical ) ), 'Different comment IDs and case variants link to the identical canonical profile.' );
    }
    foreach ( array( $other_email, $other_name, $blank[0] ) as $id ) {
        reader_check( $profile !== dedenik_reader_profile( get_comment( $id ) ), 'Different identity must get a different public URL.' );
    }
    $indexed_first = dedenik_discussant_profile_archive( $profile, 1 );
    $indexed_second = dedenik_discussant_profile_archive( $profile, 2 );
    reader_check( $indexed_first['total'] === 24 && $indexed_first['pages'] === 2 && count( $indexed_first['records'] ) === 20,
        'Canonical archive counts only public comments and preserves page size.' );
    reader_check( array_merge( array_column( $indexed_first['records'], 'comment_id' ), array_column( $indexed_second['records'], 'comment_id' ) ) === $expected,
        'Canonical pages preserve every legacy match and its order.' );
    $blank_profile = dedenik_reader_profile( get_comment( $blank[0] ) );
    reader_check( array_reverse( $blank ) === array_column( dedenik_discussant_profile_archive( $blank_profile )['records'], 'comment_id' ), 'Blank email does not mix different readers.' );
    foreach ( array( array( $profile ), '', strtoupper( $profile ), $profile . 'x', "1 OR 1=1", str_repeat( '0', 64 ) ) as $invalid ) {
        reader_check( 404 === dedenik_discussant_profile_archive( $invalid )['status'], 'Malformed and unknown profiles are rejected.' );
    }
    foreach ( array( -1, '2 OR 1=1', array( 2 ), 3 ) as $invalid ) {
        reader_check( 404 === dedenik_discussant_profile_archive( $profile, $invalid )['status'], 'Malformed and out-of-range profile pages are rejected.' );
    }
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/?' . wp_parse_url( $url, PHP_URL_QUERY );
    set_query_var( 'disk_komentar', (string) $seed );
    set_query_var( 'disk_strana', '2' );
    reader_check( $second_url === dedenik_discussant_redirect_url(), 'The public legacy link redirects to the same profile page.' );
    foreach ( array( $hidden[0], $hidden[3], '1 OR 1=1' ) as $id ) {
        set_query_var( 'disk_komentar', (string) $id );
        reader_check( ! dedenik_discussant_redirect_url(), 'Invalid and private seeds cannot reveal a canonical profile through redirects.' );
    }
    set_query_var( 'disk_komentar', null );
    set_query_var( 'disk_strana', null );
    $public_output = rawurldecode( wp_json_encode( array( $indexed_first, $indexed_second, $canonical, $second_url, get_comment_author_link( $seed ) ) ) );
    reader_check( ! str_contains( strtolower( $public_output ), '@example.invalid' ), 'Indexed responses and links never expose the private email.' );

    // Observe a new identity so a warm object cache cannot hide the database path.
    $new_id = $add_comment( $posts[0], 'Future ' . $suffix, $email );
    $new_profile = dedenik_reader_profile( get_comment( $new_id ) );
    $sql = array();
    $observe = static function ( $query ) use ( &$sql ) { $sql[] = $query; return $query; };
    add_filter( 'query', $observe );
    try { $new_page = dedenik_discussant_profile_archive( $new_profile ); }
    finally { remove_filter( 'query', $observe ); }
    reader_check( $new_page['total'] === 1 && (int) $new_page['records'][0]['comment_id'] === $new_id, 'New profile works immediately after a new comment.' );
    $lookup = array_filter( $sql, static function ( $query ) use ( $new_profile ) { return str_starts_with( ltrim( $query ), 'SELECT' ) && str_contains( $query, $new_profile ); } );
    reader_check( (bool) $lookup && ! preg_match( '/LOWER\(\s*(?:\w+\.)?comment_author/i', implode( "\n", $sql ) ), 'Canonical reads look up the exact profile without case-folding the comment table.' );
    $lookup_table = dedenik_reader_index_table();
    $indexed_columns = $wpdb->get_results( "SHOW INDEX FROM $lookup_table", ARRAY_A );
    $profile_keys = array_column( array_filter( $indexed_columns, static function ( $column ) {
        return 'profile' === $column['Column_name'] && 1 === (int) $column['Seq_in_index'];
    } ), 'Key_name' );
    foreach ( $lookup as $statement ) {
        $plan = $wpdb->get_results( 'EXPLAIN ' . $statement, ARRAY_A );
        reader_check( ! $wpdb->last_error && (bool) array_filter( $plan, static function ( $row ) use ( $profile_keys ) {
            return (bool) array_intersect( $profile_keys, explode( ',', $row['possible_keys'] ?? '' ) );
        } ), 'EXPLAIN exposes an index starting with the reader profile.' );
    }
    $edited_profile = dedenik_reader_profile( get_comment( $other_name ) );
    wp_update_comment( array( 'comment_ID' => $new_id, 'comment_author' => get_comment( $other_name )->comment_author, 'comment_author_email' => $email ) );
    reader_check( 404 === dedenik_discussant_profile_archive( $new_profile )['status'] && 2 === dedenik_discussant_profile_archive( $edited_profile )['total'], 'Editing a reader identity moves the comment between profiles.' );
    wp_delete_comment( $new_id, true );
    reader_check( 1 === dedenik_discussant_profile_archive( $edited_profile )['total'], 'Deleting a comment updates the archive immediately.' );
    wp_set_comment_status( $seed, 'hold' );
    reader_check( 404 === dedenik_discussant_comment_archive( $seed )['status'], 'A newly moderated seed must stop resolving.' );
    reader_check( 23 === dedenik_discussant_comment_archive( $same[1] )['total'], 'New moderation applies immediately to counts.' );
    reader_check( 23 === dedenik_discussant_profile_archive( $profile )['total'], 'Live moderation updates the stable archive.' );
    wp_delete_comment( $seed, true );
    reader_check( 23 === dedenik_discussant_profile_archive( $profile )['total'] && str_contains( get_comment_author_link( $same[1] ), esc_url( $canonical ) ), 'Deleting the original seed preserves the stable URL and remaining history.' );
    wp_update_post( array( 'ID' => $posts[0], 'post_password' => 'fixture' ) );
    reader_check( 404 === dedenik_discussant_profile_archive( $profile )['status'], 'Protecting the parent post immediately hides the profile.' );
    echo "Reader history: $reader_checks real WordPress/MariaDB checks passed.\n";
} finally {
    foreach ( $comments as $id ) {
        wp_delete_comment( $id, true );
    }
    foreach ( $posts as $id ) {
        wp_delete_post( $id, true );
    }
}
