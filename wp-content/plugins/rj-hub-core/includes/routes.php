<?php

defined( 'ABSPATH' ) || exit;

function rjh_normalize_legacy_path( string $path ): string {
	if ( ! preg_match( '#^/(?:[A-Za-z0-9._~-]+/)*[A-Za-z0-9._~-]+/?$#D', $path )
		|| preg_match( '#(?:^|/)\.{1,2}(?:/|$)#', $path )
		|| preg_match( '#^/(?:wp-admin|wp-json|wp-content|wp-includes)(?:/|$)#i', $path )
		|| str_ends_with( strtolower( $path ), '.php' ) ) {
		return '';
	}
	return untrailingslashit( $path );
}

function rjh_redirect_legacy_path(): void {
	if ( ! is_404() || ! in_array( $_SERVER['REQUEST_METHOD'] ?? '', array( 'GET', 'HEAD' ), true ) ) {
		return;
	}
	$request_path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
	$site_path    = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
	if ( ! is_string( $request_path ) || ! is_string( $site_path ) || ! str_starts_with( $request_path, $site_path ) ) {
		return;
	}
	$path = rjh_normalize_legacy_path( '/' . substr( $request_path, strlen( $site_path ) ) );
	if ( '' === $path ) {
		return;
	}
	$matches = get_posts(
		array(
			'post_type'      => array_merge( array( 'post', 'page' ), array_keys( rjh_types() ) ),
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 2,
			'meta_key'       => '_rj_legacy_path',
			'meta_value'     => $path,
		)
	);
	if ( 1 !== count( $matches ) || ! is_post_type_viewable( $matches[0]->post_type ) ) {
		return;
	}
	$destination = get_permalink( $matches[0] );
	if ( ! $destination || untrailingslashit( (string) wp_parse_url( $destination, PHP_URL_PATH ) ) === untrailingslashit( $request_path ) ) {
		return;
	}
	wp_safe_redirect( $destination, 301, 'RJ Hub source route' );
	exit;
}
