<?php
/** Compare the plugin's actual widget and REST results on a populated local WordPress. */
if ( PHP_SAPI !== 'cli' || empty( $argv[1] ) ) { exit( 1 ); }
require $argv[1];
if ( 'local' !== wp_get_environment_type() ) { exit( 1 ); }
require_once WP_PLUGIN_DIR . '/category-posts/cat-posts.php';
require_once dirname( __DIR__, 2 ) . '/wp-content/themes/dedenik/inc/category-posts.php';

function widget_check( $ok, $message ) {
    if ( ! $ok ) { throw new RuntimeException( $message ); }
}

$categories = get_categories( array( 'number' => 1, 'orderby' => 'count', 'order' => 'DESC' ) );
widget_check( $categories && $categories[0]->count > 12, 'A category with at least 13 public posts is required.' );
$settings = array_merge( \categoryPosts\default_settings(), array(
    'cat' => $categories[0]->term_id, 'num' => 5, 'offset' => 3, 'template' => '%title%',
    'enable_loadmore' => true, 'loadmore_text' => 'More (%step%/%all%)',
) );
$container = array( 'before_widget' => '', 'after_widget' => '', 'before_title' => '<h2>', 'after_title' => '</h2>' );
$render = static function ( $widget, $instance ) use ( $container ) {
    $widget->_set( 2 );
    ob_start();
    $widget->widget( $container, $instance );
    return ob_get_clean();
};

$queries = array();
add_action( 'pre_get_posts', static function ( $query ) use ( &$queries, $settings ) {
    if ( (int) $query->get( 'cat' ) === (int) $settings['cat'] ) { $queries[] = $query; }
} );
$old_widget = new \categoryPosts\Widget();
$new_widget = new Dedenik_Category_Posts_Widget();
foreach ( array(
    'page with offset' => array(),
    'only posts with thumbnails' => array( 'hideNoThumb' => true ),
    'no results' => array( 'date_range' => 'between_dates', 'start_date' => '1900-01-01', 'end_date' => '1900-01-02' ),
    'without button' => array( 'enable_loadmore' => false ),
) as $case => $overrides ) {
    $instance = array_merge( $settings, $overrides );
    $queries = array();
    $original = $render( $old_widget, $instance );
    if ( 'page with offset' === $case ) {
        widget_check( count( $queries ) === 2 && $queries[1]->post_count > 10, 'Original query did not exercise the full archive.' );
        $old_rows = $queries[1]->post_count;
    }
    $queries = array();
    widget_check( $original === $render( $new_widget, $instance ), "Widget HTML differs for $case." );
    foreach ( $queries as $query ) { widget_check( ! $query->get( 'nopaging' ), "Unbounded query for $case." ); }
    if ( 'page with offset' === $case ) {
        widget_check( count( $queries ) === 2 && $queries[1]->post_count === 1 && $queries[1]->found_posts === $old_rows,
            'Count query must return one ID and the original total.' );
        echo "Count query: $old_rows full posts before, one ID after, same total.\n";
    }
}

// Supply the widget settings through an in-memory option for this request only.
add_filter( 'option_widget_category-posts', static function () use ( $settings ) {
    return array( 2 => $settings, '_multiwidget' => 1 );
}, 1 );
$server = rest_get_server();
if ( ! in_array( 'categoryPosts', $server->get_namespaces(), true ) ) {
    \categoryPosts\register_route();
}
$request = new WP_REST_Request( 'GET', '/categoryPosts/loadmore/widget-2/8/5/0' );
$request->set_url_params( array( 'id' => 'widget-2', 'start' => '8', 'number' => '5', 'context' => '0' ) );
$expected = \categoryPosts\get_next_elements( $request )->get_data();
$queries = array();
$response = $server->dispatch( $request );
widget_check( $response->get_status() === 200 && count( $expected ) === 5 && $response->get_data() === $expected,
    'Load-more response must preserve all five items and their HTML.' );
widget_check( count( $queries ) === 1 && $queries[0]->post_count === 5, 'Load more must issue only the item query.' );
widget_check( get_option( 'widget_category-posts' )[2]['enable_loadmore'], 'REST option filter remained active.' );
echo "Widget HTML, filters, offset, pagination and REST query checks passed.\n";
