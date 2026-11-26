<?php
/**
 * Plugin Name: RJ Hub Core
 * Description: Editorial content types, archives, historical research map, and navigation for the RJ Hub.
 * Version: 1.0.0
 * Requires at least: 6.7
 * Requires PHP: 8.1
 * Text Domain: rj-hub
 */

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/includes/content-types.php';
require_once __DIR__ . '/includes/blocks.php';
require_once __DIR__ . '/includes/navigation.php';
require_once __DIR__ . '/includes/routes.php';

add_action( 'init', 'rjh_register_content_types' );
add_action( 'init', 'rjh_register_blocks' );
add_action( 'enqueue_block_editor_assets', 'rjh_enqueue_editor' );
add_action( 'admin_menu', 'rjh_register_navigation_screen' );
add_action( 'admin_post_rjh_save_navigation', 'rjh_save_navigation' );
add_filter( 'pre_render_block', 'rjh_reference_primary_navigation', 10, 2 );
add_filter( 'render_block_core/navigation-link', 'rjh_hide_private_navigation_link', 10, 2 );
add_filter( 'render_block_core/navigation-submenu', 'rjh_hide_private_navigation_link', 10, 2 );
add_action( 'template_redirect', 'rjh_redirect_legacy_path', 9 );
add_filter( 'the_content', 'rjh_append_reference_links' );
add_filter( 'wp_insert_post_data', 'rjh_guard_publication', 10, 2 );

register_activation_hook( __FILE__, 'rjh_activate' );

function rjh_activate(): void {
	rjh_register_content_types();
	flush_rewrite_rules( false );

	foreach ( array( 'editor', 'administrator' ) as $role_name ) {
		$role = get_role( $role_name );
		if ( $role ) {
			$role->add_cap( 'rj_manage_primary_navigation' );
		}
	}
}

function rjh_enqueue_editor(): void {
	wp_enqueue_script( 'rjh-editor' );
}
