<?php
/**
 * Theme styles and pattern categories.
 *
 * @package RJ_Hub
 */

defined( 'ABSPATH' ) || exit;

add_action( 'after_setup_theme', function () {
	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/site.css' );
} );

add_action( 'wp_enqueue_scripts', function () {
	$path = 'assets/site.css';
	wp_enqueue_style( 'rj-hub-site', get_theme_file_uri( $path ), array(), (string) filemtime( get_theme_file_path( $path ) ) );
} );

add_action( 'init', function () {
	register_block_pattern_category( 'rj-hub', array( 'label' => __( 'RJ Hub', 'rj-hub' ) ) );
} );

add_filter( 'render_block_core/image', function ( $markup, $block ) {
	$classes = preg_split( '/\s+/', trim( $block['attrs']['className'] ?? '' ) );
	if ( ! in_array( 'rj-support-photo', $classes, true ) ) {
		return $markup;
	}

	$image = new WP_HTML_Tag_Processor( $markup );
	if ( $image->next_tag( 'img' ) ) {
		$image->set_attribute( 'loading', 'lazy' );
	}
	return $image->get_updated_html();
}, 10, 2 );
