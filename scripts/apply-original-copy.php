<?php
/**
 * Update the original local site's unchanged starter text via WP-CLI.
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

if ( 'local' !== wp_get_environment_type()
	|| 'http://127.0.0.1:8092/' !== home_url( '/' )
	|| ! get_option( 'rj_hub_initial_seed_complete' )
	|| get_option( 'rj_hub_editorial_seed_version' ) ) {
	WP_CLI::error( 'This update requires the original RJ Hub WordPress installation at http://127.0.0.1:8092/.' );
}

$manifest_path = $args[0] ?? '';
$mode          = $args[1] ?? '';
if ( ! is_readable( $manifest_path ) || ! in_array( $mode, array( 'check', 'apply' ), true ) ) {
	WP_CLI::error( 'Supply the wording update file and choose check or apply.' );
}
$records = json_decode( file_get_contents( $manifest_path ), true );
if ( ! is_array( $records ) || ! $records ) {
	WP_CLI::error( 'Could not read the wording update file.' );
}

if ( 'apply' === $mode ) {
	$administrators = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	wp_set_current_user( $administrators[0] ?? 0 );
	if ( ! current_user_can( 'unfiltered_html' ) ) {
		WP_CLI::error( 'A local administrator is required to preserve the existing WordPress block markup.' );
	}
}

$record_count  = 0;
$passage_count = 0;
foreach ( $records as $record ) {
	$slug  = $record['slug'];
	$posts = get_posts(
		array(
			'post_type'      => $record['type'],
			'post_status'    => array_diff( array_keys( get_post_stati() ), array( 'trash', 'auto-draft', 'inherit' ) ),
			'meta_key'       => '_rj_hub_seed_key',
			'meta_value'     => $slug,
			'posts_per_page' => 2,
		)
	);
	if ( 1 !== count( $posts ) ) {
		WP_CLI::warning( "Kept the site as-is for $slug. Could not identify a single original record." );
		continue;
	}

	$post     = $posts[0];
	$content  = $post->post_content;
	$changed  = 0;
	$skipped  = 0;
	foreach ( $record['changes'] as $change ) {
		$matches = substr_count( $content, $change['before'] );
		if ( 1 === $matches ) {
			$content = str_replace( $change['before'], $change['after'], $content );
			++$changed;
		} elseif ( $matches || ! str_contains( $content, $change['after'] ) ) {
			++$skipped;
		}
	}
	if ( $skipped ) {
		WP_CLI::warning( sprintf( '%s: kept %d edited or missing passage(s).', $slug, $skipped ) );
	}
	if ( ! $changed ) {
		continue;
	}

	if ( 'apply' === $mode ) {
		clean_post_cache( $post->ID );
		if ( $post->post_content !== get_post_field( 'post_content', $post->ID, 'raw' ) ) {
			WP_CLI::warning( "$slug changed during the update. Its wording was kept." );
			continue;
		}
		$result = wp_update_post( wp_slash( array( 'ID' => $post->ID, 'post_content' => $content ) ), true );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( "$slug: " . $result->get_error_message() );
		}
	}

	++$record_count;
	$passage_count += $changed;
	WP_CLI::log( sprintf( '%s %s (%d passage(s)).', 'apply' === $mode ? 'Updated' : 'Ready to update', $slug, $changed ) );
}

WP_CLI::success( sprintf( '%d record(s), %d passage(s) %s.', $record_count, $passage_count, 'apply' === $mode ? 'updated' : 'ready to update' ) );
