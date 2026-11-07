<?php
/** Real SQL integration, confined to the isolated local clone. No Google requests. */
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
if ( wp_get_environment_type() !== 'local' || DB_NAME !== 'dedenik_local' ) { exit( "Local database required.\n" ); }
global $wpdb;
$checks = 0; $created = array(); $term_id = 0;
function index_check( $value, $message ) {
    $GLOBALS['checks']++;
    if ( ! $value ) { throw new RuntimeException( $message ); }
}
try {
    index_check( ! is_wp_error( dedenik_index_install() ), 'Schema creation and FULLTEXT index' );
    $cursor = 0;
    do {
        $batch = dedenik_index_batch( $cursor, 100 );
        index_check( ! is_wp_error( $batch ), 'Batch indexed without SQL errors' );
        $cursor = $batch['cursor'];
    } while ( ! $batch['done'] );
    $tables = dedenik_index_tables();
    $expected = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} p WHERE " . dedenik_index_public_sql() );
    index_check( (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tables['documents']}" ) === $expected, 'Complete public archive indexed' );
    $author = dedenik_index_author( 'Najdi vše od uživatele 814151' );
    index_check( $author && $author['total'] > 1, '814151 found as title attribution' );
    echo 'Archive indexed: ' . $expected . '; 814151: ' . $author['total'] . " articles.\n";
    $marker = 'ixfixture' . bin2hex( random_bytes( 6 ) );
    $alias = 'Author' . bin2hex( random_bytes( 6 ) );
    $term = wp_insert_term( $marker, 'post_tag' );
    $term_id = $term['term_id'];
    for ( $i = 0; $i < 25; $i++ ) {
        $created[] = wp_insert_post( array( 'post_title' => "TEST – $alias: Příběh $i", 'post_content' => 'Fulltext ' . $marker, 'post_status' => 'publish' ) );
    }
    foreach ( array( 'draft', 'private', 'password', 'future' ) as $status ) {
        $created[] = wp_insert_post( array( 'post_title' => "TEST – $alias: Hidden", 'post_content' => $marker,
            'post_status' => 'password' === $status ? 'publish' : $status, 'post_password' => 'password' === $status ? 'hidden' : '',
            'post_date' => 'future' === $status ? '2099-01-01 00:00:00' : current_time( 'mysql' ) ) );
    }
    $one = dedenik_index_author( 'vše od autora ' . $alias );
    $two = dedenik_index_author( 'vše od autora ' . $alias, 2 );
    $three = dedenik_index_author( $alias, 3 );
    index_check( $one['total'] === 25 && $one['pages'] === 3, 'Author filter includes all 25 and excludes private/draft/password/future' );
    index_check( count( array_unique( array_merge( $one['ids'], $two['ids'], $three['ids'] ) ) ) === 25, 'Pagination has full coverage without duplicates' );
    index_check( count( dedenik_index_candidates( array( $marker ), 64 ) ) === 25, 'FULLTEXT finds public body content' );
    $first = $created[0];
    wp_set_post_terms( $first, array( $term_id ), 'post_tag' );
    index_check( (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tables['terms']} WHERE post_id=%d AND taxonomy='post_tag'", $first ) ) === 1, 'Taxonomy changes synchronize indexed relations' );
    wp_update_term( $term_id, 'post_tag', array( 'name' => $marker . 'renamed' ) );
    index_check( in_array( $first, dedenik_index_candidates( array( $marker . 'renamed' ) ), true ), 'Renaming taxonomy updates searchable text' );
    update_post_meta( $first, '_dedenik_search_authors', "Další přezdívka" );
    index_check( dedenik_index_author( 'od dalsi prezdivka' )['total'] === 1, 'Editorial aliases synchronize and normalize accents' );
    delete_post_meta( $first, '_dedenik_search_authors' );
    index_check( dedenik_index_author( 'Další přezdívka' ) === null, 'Removed alias disappears' );
    // Deliberately stale index rows must not reveal an unpublished WordPress record.
    $wpdb->update( $wpdb->posts, array( 'post_status' => 'private' ), array( 'ID' => $first ) );
    clean_post_cache( $first );
    index_check( dedenik_index_author( $alias )['total'] === 24, 'Visibility is checked in live tables despite stale index row' );
    wp_update_post( array( 'ID' => $first, 'post_status' => 'private' ) );
    index_check( ! $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM {$tables['documents']} WHERE post_id=%d", $first ) ), 'Unpublishing removes indexed document' );
    wp_delete_post( $created[1], true );
    index_check( dedenik_index_author( $alias )['total'] === 23, 'Deleted article removed from author index' );
    index_check( dedenik_index_candidates( array( "\"' OR 1=1 --" ) ) === array(), 'Search tokens cannot become SQL predicates' );
    index_check( dedenik_index_author( "' OR 1=1 --" ) === null, 'Author name cannot become SQL predicates' );
    echo "Search index: $checks real database checks passed.\n";
} finally {
    foreach ( $created as $id ) { wp_delete_post( $id, true ); }
    if ( $term_id ) { wp_delete_term( $term_id, 'post_tag' ); }
}
