<?php
defined( 'ABSPATH' ) || exit;
function dedenik_setup() {
    load_theme_textdomain( 'dedenik', get_template_directory() . '/languages' );
    add_theme_support( 'title-tag' );
    add_theme_support( 'automatic-feed-links' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'align-wide' );
    add_theme_support( 'editor-styles' );
    add_editor_style( 'assets/css/editor.css' );
    add_theme_support( 'custom-logo', array( 'height' => 100, 'width' => 350, 'flex-width' => true, 'flex-height' => true ) );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
    register_nav_menus( array(
        'frontier-menu-primary' => __( 'Hlavní navigace', 'dedenik' ),
        'frontier-menu-top' => __( 'Doplňková navigace', 'dedenik' ),
        'footer' => __( 'Patička – Rychlé odkazy', 'dedenik' ),
        'footer-community' => __( 'Patička – Komunita', 'dedenik' ),
        'footer-world' => __( 'Patička – Svět Dede', 'dedenik' ),
        'footer-legal' => __( 'Patička – Spodní odkazy', 'dedenik' ),
    ) );
    $GLOBALS['content_width'] = 820;
}
add_action( 'after_setup_theme', 'dedenik_setup' );
function dedenik_assets() {
    $version = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'dedenik', get_template_directory_uri() . '/assets/css/main.css', array(), $version );
    wp_enqueue_script( 'dedenik-navigation', get_template_directory_uri() . '/assets/js/navigation.js', array(), $version, true );
    wp_enqueue_script( 'dedenik-theatre-dialog', get_template_directory_uri() . '/assets/js/theatre-dialog.js', array(), $version, true );
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'dedenik-comments', get_template_directory_uri() . '/assets/js/comments.js', array( 'comment-reply' ), $version, true );
    }
}
add_action( 'wp_enqueue_scripts', 'dedenik_assets' );
// "Zobrazit další" renders more Category Posts items in a separate REST request.
function dedenik_sidebar_loadmore_images( $response, $handler, $request ) {
    if ( ! preg_match( '~^/categoryPosts/loadmore/widget-(\d+)/\d+/\d+/(\d+)/?$~', $request->get_route(), $matches )
        || ! (int) $matches[2] || 'post' !== get_post_type( (int) $matches[2] ) ) { return $response; }
    $sidebars = wp_get_sidebars_widgets();
    $widgets = array_merge( $sidebars['widgets_sidebar_left'] ?? array(), $sidebars['widgets_sidebar_right'] ?? array() );
    if ( in_array( 'category-posts-' . $matches[1], $widgets, true ) ) {
        if ( doing_filter( 'rest_request_before_callbacks' ) ) {
            add_filter( 'wp_get_attachment_image_attributes', 'dedenik_sidebar_thumbnail_source', 10, 3 );
        } else {
            remove_filter( 'wp_get_attachment_image_attributes', 'dedenik_sidebar_thumbnail_source' );
        }
    }
    return $response;
}
add_filter( 'rest_request_before_callbacks', 'dedenik_sidebar_loadmore_images', 10, 3 );
add_filter( 'rest_request_after_callbacks', 'dedenik_sidebar_loadmore_images', 10, 3 );
// The archive contains long legacy texts; keep card summaries close to the mockup.
function dedenik_excerpt_length( $length ) {
    return ( is_home() || is_archive() || is_search() ) ? 28 : $length;
}
add_filter( 'excerpt_length', 'dedenik_excerpt_length', 20 );
// Keep the homepage's editorial strip to one desktop row. Apply the same
// page size to every home page so native pagination never skips posts.
function dedenik_home_page_size( $query ) {
    if ( ! is_admin() && $query->is_main_query() && $query->is_home() && ! $query->is_feed() ) {
        $query->set( 'posts_per_page', 4 );
        $query->set( 'ignore_sticky_posts', true );
    }
}
add_action( 'pre_get_posts', 'dedenik_home_page_size' );
function dedenik_widgets() {
    $areas = array(
        'widgets_sidebar_left' => __( 'Původní levý sloupec', 'dedenik' ),
        'widgets_sidebar_right' => __( 'Původní pravý sloupec', 'dedenik' ),
        'widgets_body' => __( 'Doplňkový obsah nad patičkou (původně pod patičkou)', 'dedenik' ),
        'widgets_header' => __( 'Pod hlavičkou', 'dedenik' ),
        'widgets_below_menu' => __( 'Pod navigací', 'dedenik' ),
        'widgets_before_content' => __( 'Před hlavním obsahem', 'dedenik' ),
        'widgets_after_content' => __( 'Za hlavním obsahem', 'dedenik' ),
        'widgets_footer' => __( 'Patička', 'dedenik' ),
        'widgets_before_post' => __( 'Před článkem', 'dedenik' ),
        'widgets_before_post_content' => __( 'Před textem článku', 'dedenik' ),
        'widgets_after_post_content' => __( 'Za textem článku', 'dedenik' ),
        'widgets_after_post' => __( 'Za článkem', 'dedenik' ),
    );
    foreach ( $areas as $id => $name ) {
        register_sidebar( array( 'id' => $id, 'name' => $name,
            'before_widget' => '<section id="%1$s" class="widget %2$s">', 'after_widget' => '</section>',
            'before_title' => '<h2 class="widget-title">', 'after_title' => '</h2>' ) );
    }
}
add_action( 'widgets_init', 'dedenik_widgets' );
