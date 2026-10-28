<?php
/**
 * Module Onnodige onderdelen uit. Elk onderdeel is bewust gekozen voor Elementor-sites:
 * niets hiervan wordt door Elementor Pro, Rank Math of de formulieren gebruikt.
 */

defined( 'ABSPATH' ) || exit;

// Gutenberg uit (ook voor CPT's) en de bijbehorende frontend-CSS weg.
add_filter( 'use_block_editor_for_post_type', '__return_false', 100 );
add_filter( 'use_widgets_block_editor', '__return_false' );
add_filter( 'gutenberg_use_widgets_block_editor', '__return_false' );
add_action( 'wp_enqueue_scripts', function () {
	foreach ( [ 'wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports' ] as $h ) {
		wp_dequeue_style( $h );
		wp_deregister_style( $h );
	}
}, 100 );
remove_action( 'wp_enqueue_scripts', 'wp_enqueue_global_styles' );
remove_action( 'wp_footer', 'wp_enqueue_global_styles', 1 );
remove_action( 'wp_body_open', 'wp_global_styles_render_svg_filters' );

// Reacties volledig uit.
add_action( 'init', function () {
	foreach ( get_post_types() as $pt ) {
		if ( post_type_supports( $pt, 'comments' ) ) {
			remove_post_type_support( $pt, 'comments' );
			remove_post_type_support( $pt, 'trackbacks' );
		}
	}
}, 100 );
add_filter( 'comments_open', '__return_false', 20 );
add_filter( 'pings_open', '__return_false', 20 );
add_filter( 'comments_array', '__return_empty_array', 10 );
add_action( 'admin_menu', fn() => remove_menu_page( 'edit-comments.php' ), 999 );
add_action( 'admin_init', function () {
	if ( ( $GLOBALS['pagenow'] ?? '' ) === 'edit-comments.php' ) {
		wp_safe_redirect( admin_url() );
		exit;
	}
} );

// Feeds uit (en links in de head weg).
foreach ( [ 'do_feed', 'do_feed_rdf', 'do_feed_rss', 'do_feed_rss2', 'do_feed_atom', 'do_feed_rss2_comments', 'do_feed_atom_comments' ] as $feed ) {
	add_action( $feed, function () {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}, 1 );
}
remove_action( 'wp_head', 'feed_links', 2 );
remove_action( 'wp_head', 'feed_links_extra', 3 );

// Head opschonen: generator, WLW, RSD, shortlink, REST-link, oEmbed-discovery.
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head' );
remove_action( 'template_redirect', 'wp_shortlink_header', 11 );
remove_action( 'wp_head', 'rest_output_link_wp_head' );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );
remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
remove_action( 'wp_head', 'wp_oembed_add_host_js' );

// Emoji uit.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );
add_filter( 'tiny_mce_plugins', fn( $p ) => is_array( $p ) ? array_diff( $p, [ 'wpemoji' ] ) : $p );

// jQuery Migrate en Dashicons alleen waar nodig (niet voor bezoekers).
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( ! is_admin() && isset( $scripts->registered['jquery'] ) ) {
		$scripts->registered['jquery']->deps = array_diff( $scripts->registered['jquery']->deps, [ 'jquery-migrate' ] );
	}
} );
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_user_logged_in() ) {
		wp_dequeue_style( 'dashicons' );
	}
}, 100 );

// Bestanden bewerken in wp-admin uit.
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}
