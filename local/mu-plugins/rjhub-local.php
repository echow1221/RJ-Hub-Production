<?php
/**
 * Plugin Name: RJ Hub Local Preview Controls
 * Description: Keeps outgoing mail and indexing off in the Compose preview.
 */

if ( 'local' === wp_get_environment_type() ) {
	add_filter( 'pre_wp_mail', '__return_false' );
	add_filter( 'wp_robots', 'wp_robots_no_robots' );
	add_filter( 'robots_txt', static fn() => "User-agent: *\nDisallow: /\n" );
}
