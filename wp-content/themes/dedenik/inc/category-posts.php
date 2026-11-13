<?php
/** Keep Category Posts' load-more count from fetching entire articles. */
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'categoryPosts\\Widget' ) ) { return; }

class Dedenik_Category_Posts_Widget extends \categoryPosts\Widget {
    private function needs_plugin_sticky_count( $instance ) {
        // Core may append sticky posts outside the filters on an uncategorized
        // home query. Category archives don't run that special case.
        return ! empty( $instance['sticky'] ) && empty( $instance['cat'] ) && get_option( 'sticky_posts' );
    }

    public function get_elements_HTML( $instance, $singular_id, $start, $number ) {
        if ( ! empty( $instance['enable_loadmore'] ) && ! $this->needs_plugin_sticky_count( $instance ) ) {
            $instance['enable_loadmore'] = false;
        }
        return parent::get_elements_HTML( $instance, $singular_id, $start, $number );
    }

    public function loadMoreHTML( $instance ) {
        // The parent calls this only when the visible page was full. It builds
        // the button with the original settings, independent of the item loop.
        if ( ! empty( $instance['enable_loadmore'] ) && ! $this->needs_plugin_sticky_count( $instance ) ) {
            $args = $this->queryArgs( $instance );
            // The original nopaging query counted the entire category, even
            // when the visible items started at an offset.
            unset( $args['offset'] );
            $args['showposts'] = 1;
            $args['fields'] = 'ids';
            $args['orderby'] = 'none';
            $args['no_found_rows'] = false;
            $GLOBALS['post_count'] = ( new WP_Query( $args ) )->found_posts;
        }
        return parent::loadMoreHTML( $instance );
    }
}

add_action( 'widgets_init', function () {
    global $wp_widget_factory;
    $class = 'categoryPosts\\Widget';
    if ( isset( $wp_widget_factory->widgets[ $class ] ) && $class === get_class( $wp_widget_factory->widgets[ $class ] ) ) {
        // Preserve the original registration key for the_widget() callers.
        $wp_widget_factory->widgets[ $class ] = new Dedenik_Category_Posts_Widget();
    }
}, 99 );

// The REST callback creates its own plugin widget and returns only item HTML.
// Its result never uses a total. Disable that query only for this callback.
add_filter( 'rest_dispatch_request', function ( $result, $request, $route, $handler ) {
    if ( null !== $result || 'categoryPosts\\get_next_elements' !== $handler['callback']
        || ! preg_match( '/^widget-(\d+)$/D', (string) $request['id'], $match ) ) { return $result; }

    $skip_count = static function ( $settings ) use ( $match ) {
        if ( isset( $settings[ $match[1] ]['enable_loadmore'] ) ) {
            $settings[ $match[1] ]['enable_loadmore'] = false;
        }
        return $settings;
    };
    add_filter( 'option_widget_category-posts', $skip_count );
    try {
        return $handler['callback']( $request );
    } finally {
        remove_filter( 'option_widget_category-posts', $skip_count );
    }
}, 100, 4 );
