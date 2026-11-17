<?php
/** Temporary outage mitigation; re-enable only after the production index is verified. */
defined( 'ABSPATH' ) || exit;
function dedenik_reader_archives_paused() {
    return ! defined( 'DEDENIK_READER_ARCHIVES_PAUSED' ) || DEDENIK_READER_ARCHIVES_PAUSED;
}
function dedenik_reader_archive_request( $query ) {
    foreach ( array( 'disk_komentar', 'disk_profil', 'diskutujici', 'disk_strana' ) as $key ) {
        if ( array_key_exists( $key, $query ) ) { return true; }
    }
    return false;
}
// Executed before loading the archive implementation or running its queries.
// WordPress and plugins have already bootstrapped: this is not an Apache-level block.
if ( dedenik_reader_archives_paused() && PHP_SAPI !== 'cli' && ! is_admin()
    && dedenik_reader_archive_request( $_GET ) ) {
    status_header( 503 );
    nocache_headers();
    header( 'Retry-After: 3600' );
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo 'Historie komentářů je dočasně nedostupná. Komentáře pod články zůstávají dostupné.';
    exit;
}
