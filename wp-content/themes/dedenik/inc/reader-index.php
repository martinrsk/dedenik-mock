<?php
/** Compact lookup for commenter archives. WordPress remains the source of visibility. */
defined( 'ABSPATH' ) || exit;

function dedenik_reader_index_table() {
    global $wpdb;
    return $wpdb->prefix . 'dedenik_reader_comments';
}

function dedenik_reader_index_ready() {
    return 'ready' === get_option( 'dedenik_reader_index_cursor', false );
}

function dedenik_reader_profile( $comment ) {
    $key = get_option( 'dedenik_reader_profile_secret', '' );
    if ( ! is_string( $key ) || strlen( $key ) !== 64 ) { return ''; }
    $name  = mb_strtolower( $comment->comment_author, 'UTF-8' );
    $email = mb_strtolower( $comment->comment_author_email, 'UTF-8' );
    return hash_hmac( 'sha256', pack( 'N', strlen( $name ) ) . $name . pack( 'N', strlen( $email ) ) . $email, $key );
}

/** Called only by the background worker, under its per-site database lock. */
function dedenik_reader_index_install() {
    global $wpdb;
    $table = dedenik_reader_index_table();
    if ( false === $wpdb->query( "CREATE TABLE IF NOT EXISTS $table (
        comment_id bigint(20) unsigned NOT NULL,
        profile char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
        comment_date datetime NOT NULL,
        PRIMARY KEY (comment_id),
        KEY profile_date (profile, comment_date, comment_id)
    ) ENGINE=InnoDB" ) ) { return false; }

    // Re-read the authoritative row under the lock. Two simultaneous installs
    // must never replace an existing secret, even with a stale options cache.
    $key = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name='dedenik_reader_profile_secret'" );
    if ( $wpdb->last_error ) { return false; }
    if ( null === $key && ! add_option( 'dedenik_reader_profile_secret', bin2hex( random_bytes( 32 ) ), '', false ) ) { return false; }
    add_option( 'dedenik_reader_index_cursor', '0', '', false );
    return false !== get_option( 'dedenik_reader_index_cursor', false );
}

/** Run one restartable batch. Returns true on completion, false for more work, or WP_Error. */
function dedenik_reader_index_batch( $limit = 500 ) {
    global $wpdb;
    $table = dedenik_reader_index_table();
    $lock = 'dedenik_reader_' . md5( DB_NAME . '/' . $table );
    if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $lock ) ) ) { return false; }
    try {
        // Another worker may have advanced the cursor since WordPress loaded
        // its options cache. Read progress after acquiring the database lock.
        $cursor = $wpdb->get_var( "SELECT option_value FROM $wpdb->options WHERE option_name='dedenik_reader_index_cursor'" );
        if ( $wpdb->last_error ) { return new WP_Error( 'reader_cursor', 'Čtení stavu indexu selhalo.' ); }
        if ( 'ready' === $cursor ) { return true; }
        if ( null === $cursor && ! dedenik_reader_index_install() ) {
            return new WP_Error( 'reader_install', 'Instalace indexu diskutujících selhala.' );
        }
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT comment_ID, comment_author, comment_author_email FROM $wpdb->comments
             WHERE comment_ID > %d ORDER BY comment_ID LIMIT %d", (int) $cursor, min( 1000, max( 1, (int) $limit ) )
        ) );
        if ( $wpdb->last_error ) { return new WP_Error( 'reader_read', 'Čtení komentářů selhalo.' ); }
        if ( ! $rows ) {
            update_option( 'dedenik_reader_index_cursor', 'ready', false );
            return dedenik_reader_index_ready();
        }

        $cases = array(); $params = array(); $ids = array();
        foreach ( $rows as $row ) {
            $profile = dedenik_reader_profile( $row );
            if ( '' === $profile ) { return new WP_Error( 'reader_key', 'Klíč archivu není dostupný.' ); }
            $ids[] = (int) $row->comment_ID;
            $cases[] = 'WHEN %d THEN %s';
            array_push( $params, (int) $row->comment_ID, $profile );
        }
        // Ignore duplicates so a concurrent edit always wins. Selecting the live
        // IDs again prevents a concurrent deletion from resurrecting a mapping.
        $sql = "INSERT INTO $table (comment_id,profile,comment_date)
            SELECT comment_ID, CASE comment_ID " . implode( ' ', $cases ) . " END, comment_date
            FROM $wpdb->comments WHERE comment_ID IN (" . implode( ',', $ids ) . ')
            ON DUPLICATE KEY UPDATE comment_id=VALUES(comment_id)';
        if ( false === $wpdb->query( $wpdb->prepare( $sql, ...$params ) ) ) {
            return new WP_Error( 'reader_write', 'Zápis indexu diskutujících selhal.' );
        }
        update_option( 'dedenik_reader_index_cursor', (string) end( $ids ), false );
        return false;
    } finally {
        $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
    }
}

function dedenik_reader_schedule_build( $delay = 10 ) {
    if ( ! dedenik_reader_index_ready() && ! wp_next_scheduled( 'dedenik_reader_build' ) ) {
        wp_schedule_single_event( time() + $delay, 'dedenik_reader_build' );
    }
}
add_action( 'init', function () { dedenik_reader_schedule_build(); } );
add_action( 'dedenik_reader_build', function () {
    $deadline = microtime( true ) + 4;
    for ( $batch = 0; $batch < 8 && microtime( true ) < $deadline; ++$batch ) {
        $result = dedenik_reader_index_batch();
        if ( true === $result ) { return; }
        if ( is_wp_error( $result ) ) {
            dedenik_reader_schedule_build( 5 * MINUTE_IN_SECONDS );
            return;
        }
    }
    dedenik_reader_schedule_build();
} );

/** Store the current DB row, including concurrent edits, or remove its mapping after deletion. */
function dedenik_reader_sync_comment( $id ) {
    global $wpdb;
    $id = (int) $id;
    $table = dedenik_reader_index_table();
    $ok = false;
    if ( false !== get_option( 'dedenik_reader_index_cursor', false ) ) {
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT comment_author,comment_author_email FROM $wpdb->comments WHERE comment_ID=%d", $id
        ) );
        if ( ! $wpdb->last_error && ! $row ) {
            $ok = false !== $wpdb->query( $wpdb->prepare(
                "DELETE FROM $table WHERE comment_id=%d AND NOT EXISTS (SELECT 1 FROM $wpdb->comments WHERE comment_ID=%d)", $id, $id
            ) );
        } elseif ( $row && '' !== ( $profile = dedenik_reader_profile( $row ) ) ) {
            // Only upsert if the row still represents the identity just hashed.
            // A later edit's hook will write the newer identity.
            $ok = false !== $wpdb->query( $wpdb->prepare(
                "INSERT INTO $table (comment_id,profile,comment_date)
                 SELECT comment_ID,%s,comment_date FROM $wpdb->comments WHERE comment_ID=%d
                 AND BINARY comment_author=UNHEX(%s) AND BINARY comment_author_email=UNHEX(%s)
                 ON DUPLICATE KEY UPDATE profile=VALUES(profile),comment_date=VALUES(comment_date)",
                $profile, $id, bin2hex( $row->comment_author ), bin2hex( $row->comment_author_email )
            ) );
        }
    }
    if ( ! $ok && ! wp_next_scheduled( 'dedenik_reader_retry_comment', array( $id ) ) ) {
        wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, 'dedenik_reader_retry_comment', array( $id ) );
    }
}
add_action( 'wp_insert_comment', 'dedenik_reader_sync_comment', 100 );
add_action( 'edit_comment', 'dedenik_reader_sync_comment', 100 );
add_action( 'deleted_comment', 'dedenik_reader_sync_comment', 100 );
add_action( 'dedenik_reader_retry_comment', 'dedenik_reader_sync_comment' );
