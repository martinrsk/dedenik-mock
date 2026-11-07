<?php
/**
 * CLI integration for the public comment-ID archive on a fresh local WordPress.
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

    $nickname = 'A & B 50%_Žába';
    $email = 'test%_a@example.invalid';
    $same = array();
    for ( $i = 0; $i < 22; ++$i ) {
        $same[] = $add_comment( $posts[0], $nickname, $email );
    }
    $same[] = $add_comment( $posts[0], mb_strtolower( $nickname ), $email );
    $same[] = $add_comment( $posts[0], $nickname, strtoupper( $email ) );
    $seed = $same[0];
    // These lookalikes must not match the seed, even with a case/accent-insensitive DB collation.
    $add_comment( $posts[0], $nickname, 'other@example.invalid' );
    $add_comment( $posts[0], 'Other reader', $email );
    $add_comment( $posts[0], 'A & B 50XXŽába', $email );
    $add_comment( $posts[0], 'A & B 50%XŽába', $email );
    $add_comment( $posts[0], $nickname, 'testXXa@example.invalid' );
    $add_comment( $posts[0], $nickname, 'test%Xa@example.invalid' );
    $add_comment( $posts[0], 'A & B 50%_Zaba', $email );
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
    reader_check( str_contains( $link, 'disk_komentar=' . $seed ) && str_contains( $link, 'A &amp; B' ), 'Escaped author label points to its own public seed even without an index.' );
    foreach ( $hidden as $id ) {
        reader_check( ! str_contains( get_comment_author_link( $id ), 'disk_komentar=' ), 'Hidden seed does not render an archive link.' );
    }
    $public_output = wp_json_encode( array( $page_one, $page_two, $url, $link ) );
    reader_check( ! str_contains( rawurldecode( $public_output ), '@example.invalid' ), 'Raw or URL-encoded private email escaped into the public archive.' );

    wp_set_comment_status( $seed, 'hold' );
    reader_check( 404 === dedenik_discussant_comment_archive( $seed )['status'], 'A newly moderated seed must stop resolving.' );
    reader_check( 23 === dedenik_discussant_comment_archive( $same[1] )['total'], 'New moderation applies immediately to counts.' );
    echo "Reader history: $reader_checks real WordPress/MariaDB checks passed.\n";
} finally {
    foreach ( $comments as $id ) {
        wp_delete_comment( $id, true );
    }
    foreach ( $posts as $id ) {
        wp_delete_post( $id, true );
    }
}
