<?php
// Exercise the registered save filters with WordPress's real emoji encoder.
define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
$filters = array();
function add_action( ...$args ) {}
function add_filter( $name, $callback, $priority = 10, $accepted_args = 1 ) {
    $GLOBALS['filters'][ $name ][ $priority ][] = $callback;
}
require ABSPATH . 'wp-includes/formatting.php';
require ABSPATH . 'wp-content/themes/dedenik/inc/setup.php';
$original = 'Příliš žluťoučký kůň 🙂 😀 😂 😉 😍 🤗 🤔 😢 😮 👍 👏 ❤️ 🌷 🐶 🐱 ☕ <em>děkuji</em> &amp; &#128578;';
foreach ( array( 'pre_comment_content', 'comment_save_pre' ) as $hook ) {
    $saved = $original;
    foreach ( $filters[ $hook ][99] ?? array() as $callback ) {
        $saved = $callback( $saved );
    }
    // Simulate a legacy utf8/utf8mb3 column dropping four-byte characters.
    $stored = preg_replace( '/[\x{10000}-\x{10FFFF}]/u', '', $saved );
    if ( html_entity_decode( $stored, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) !== html_entity_decode( $original, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) {
        throw new RuntimeException( $hook . ' lost emoji or changed comment content' );
    }
    if ( wp_encode_emoji( $saved ) !== $saved ) {
        throw new RuntimeException( $hook . ' must preserve already encoded comments on edits' );
    }
}
echo "Passed new and edited comment emoji storage checks\n";
