<?php
/**
 * Provision the initial, editable local preview content with WP-CLI.
 * Usage: wp eval-file scripts/seed.php /path/to/project [private-media]
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$project_root = realpath( $args[0] ?? '' );
$media_mode   = $args[1] ?? 'safe';
if ( ! $project_root || ! is_dir( $project_root . '/content/pages' ) || ! in_array( $media_mode, array( 'safe', 'private-media' ), true ) || count( $args ) > 2 ) {
	WP_CLI::error( 'Usage: wp eval-file /path/to/project/scripts/seed.php /path/to/project [private-media]' );
}

if ( ! post_type_exists( 'rj_resource' ) || 'rj-hub' !== get_stylesheet() ) {
	WP_CLI::error( 'Activate the RJ Hub theme and content plugin before seeding.' );
}

if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Starter content is for the local WordPress prototype.' );
}

$saved_media_mode = get_option( 'rj_hub_initial_seed_media_mode' );
if ( $saved_media_mode && $saved_media_mode !== $media_mode ) {
	WP_CLI::warning( 'This installation already has an initial media mode. Existing attachments and page edits are preserved.' );
	$media_mode = $saved_media_mode;
}

if ( 2 <= (int) get_option( 'rj_hub_editorial_seed_version' ) ) {
	WP_CLI::success( 'RJ Hub starter content is already installed. Editorial changes preserved.' );
	return;
}

$upgrading = (bool) get_option( 'rj_hub_initial_seed_complete' );
$theme_seed = get_stylesheet_directory() . '/seed';
$pages = array(
	'home'               => array( 'Home', "$theme_seed/home.html" ),
	'about'              => array( 'About the RJ Hub', "$project_root/content/pages/about.html" ),
	'what-is-rj'         => array( 'What Is Restorative Justice?', "$project_root/content/pages/what-is-rj.html" ),
	'blog'               => array( 'Blog & News', "$project_root/content/pages/blog.html" ),
	'projects'           => array( 'Research & Projects', "$project_root/content/pages/projects.html" ),
	'resources'          => array( 'Resources', "$project_root/content/pages/resources.html" ),
	'maps'               => array( 'Maps', "$project_root/content/pages/maps.html" ),
	'offerings'          => array( 'Offerings', "$project_root/content/pages/offerings.html" ),
	'community-partners' => array( 'Community Partners', "$project_root/content/pages/community-partners.html" ),
	'get-involved'       => array( 'Get Involved', "$project_root/content/pages/get-involved.html" ),
	'intake'             => array( 'Intake and Requests', "$project_root/content/pages/intake.html" ),
	'legacy'             => array( 'Legacy and History', "$project_root/content/pages/legacy.html" ),
	'contact'            => array( 'Contact', "$project_root/content/pages/contact.html" ),
	'accessibility'      => array( 'Accessibility', "$project_root/content/pages/accessibility.html" ),
	'privacy'            => array( 'Privacy', "$project_root/content/pages/privacy.html" ),
	'home-alternate'     => array( 'Homepage Preview', "$theme_seed/home-alternate.html" ),
	'style-guide'        => array( 'Brand System', "$theme_seed/style-guide.html" ),
);
$public_pages = array( 'home', 'about', 'what-is-rj', 'resources', 'accessibility', 'privacy' );
$previous_titles = array( 'page/blog' => 'Blog', 'page/projects' => 'Projects' );
$resources = array(
	'questions-for-restorative-reflection' => array(
		'Questions for Restorative Reflection',
		'Learning Guide',
		'Questions for reflecting on harm, responsibility, and repair.',
	),
	'planning-an-accessible-discussion'    => array(
		'Planning an Accessible Discussion',
		'Checklist',
		'A checklist for planning a discussion people can take part in.',
	),
	'planning-a-restorative-circle'         => array(
		'Planning a Restorative Circle',
		'Practice Guide',
		'Questions for planning a voluntary circle, making space for everyone, and following through afterward.',
	),
);

$read_seed_file = static function ( string $path ): string {
	if ( ! is_readable( $path ) ) {
		WP_CLI::error( 'Missing starter content file: ' . $path );
	}
	$body = file_get_contents( $path );
	if ( false === $body || '' === trim( $body ) ) {
		WP_CLI::error( 'Starter content file is empty: ' . $path );
	}
	return $body;
};

$page_bodies = array();
foreach ( $pages as $slug => list( $title, $path ) ) {
	$page_bodies[ $slug ] = $read_seed_file( $path );
}
$resource_bodies = array();
foreach ( array_keys( $resources ) as $slug ) {
	$resource_bodies[ $slug ] = $read_seed_file( "$project_root/content/resources/$slug.html" );
}
$navigation_body = $read_seed_file( "$theme_seed/navigation.html" );
$original_bodies = $upgrading ? json_decode( $read_seed_file( "$project_root/content/upgrade-v1.json" ), true ) : array();
if ( ! is_array( $original_bodies ) ) {
	WP_CLI::error( 'The original starter content snapshot could not be read.' );
}

if ( ! $saved_media_mode ) {
	add_option( 'rj_hub_initial_seed_media_mode', $media_mode );
}

$find_seed = static function ( string $type, string $key ): int {
	$matches = get_posts(
		array(
			'post_type'      => $type,
			'post_status'    => array_keys( get_post_stati() ),
			'meta_key'       => '_rj_hub_seed_key',
			'meta_value'     => $key,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return (int) ( $matches[0] ?? 0 );
};

$replacements = array();
$asset_ids    = array();
if ( 'private-media' === $media_mode ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';

	$manifest = json_decode( $read_seed_file( "$project_root/media/MANIFEST.json" ), true );
	$asset_slots = array(
		'home-hero'          => 'HERO',
		'home-support'       => 'SUPPORT',
		'workshop-activity'  => 'WORKSHOP',
		'archival-gathering' => 'ARCHIVAL',
		'rj-hub-mark'        => 'MARK',
	);
	foreach ( $asset_slots as $key => $token ) {
		$asset = $manifest[ $key ] ?? array();
		$source_path = realpath( "$project_root/" . ( $asset['path'] ?? '' ) );
		if ( ! $source_path || ! str_starts_with( $source_path, "$project_root/media/preview/" ) || ! is_readable( $source_path ) || empty( $asset['private_only'] ) ) {
			WP_CLI::error( 'Private preview media is missing or has incomplete review metadata: ' . $key );
		}
		$asset_id = $find_seed( 'attachment', $key );
		if ( ! $asset_id ) {
			$temp_file = wp_tempnam( basename( $source_path ) );
			if ( ! $temp_file || ! copy( $source_path, $temp_file ) ) {
				WP_CLI::error( 'Could not stage the private preview file: ' . $key );
			}
			$asset_id = media_handle_sideload(
				array( 'name' => basename( $source_path ), 'tmp_name' => $temp_file ),
				0,
				null,
				array(
					'post_title'   => ucwords( str_replace( '-', ' ', $key ) ) . ' (private review)',
					'post_excerpt' => $asset['caption'],
					'meta_input'   => array(
						'_rj_hub_seed_key'          => $key,
						'_rj_hub_media_review'      => $asset['rights_status'],
						'_wp_attachment_image_alt' => $asset['alt'],
					),
				)
			);
			if ( is_wp_error( $asset_id ) ) {
				if ( is_file( $temp_file ) ) {
					unlink( $temp_file );
				}
				WP_CLI::error( $asset_id->get_error_message() );
			}
		}
		$asset_ids[ $key ] = $asset_id;
		$replacements[ '{{' . $token . '_ID}}' ]      = (string) $asset_id;
		$replacements[ '{{' . $token . '_URL}}' ]     = esc_url( wp_make_link_relative( wp_get_attachment_url( $asset_id ) ) );
		$replacements[ '{{' . $token . '_ALT}}' ]     = esc_attr( $asset['alt'] );
		$replacements[ '{{' . $token . '_CAPTION}}' ] = esc_html( $asset['caption'] );
	}
}

$render_body = static function ( string $body, string $hero_class = '' ) use ( $media_mode, $replacements ): string {
	if ( 'private-media' === $media_mode ) {
		return str_replace( array( '<!-- rj-private-media:start -->', '<!-- rj-private-media:end -->' ), '', strtr( $body, $replacements ) );
	}
	return preg_replace_callback(
		'/<!-- rj-private-media:start -->.*?<!-- rj-private-media:end -->\s*/s',
		static function ( array $matches ) use ( $hero_class ): string {
			if ( $hero_class && str_contains( $matches[0], '"className":"' . $hero_class . '"' ) ) {
				$figure = "\n" . sprintf( '<figure class="wp-block-image %s"><img alt=""/></figure>', esc_attr( $hero_class ) ) . "\n";
				return serialize_block(
					array(
						'blockName'    => 'core/image',
						'attrs'        => array( 'className' => $hero_class, 'metadata' => array( 'name' => 'Hero photograph' ) ),
						'innerBlocks'  => array(),
						'innerHTML'    => $figure,
						'innerContent' => array( $figure ),
					)
				) . "\n";
			}
			return '';
		},
		$body
	);
};

$save_seed = static function ( string $type, string $slug, string $title, string $body, string $excerpt = '', string $status = 'publish' ) use ( $find_seed, $original_bodies, $previous_titles, $render_body ): int {
	$existing = $find_seed( $type, $slug );
	if ( $existing ) {
		$key = "$type/$slug";
		$post = get_post( $existing );
		if ( ! isset( $original_bodies[ $key ] ) || 'trash' === $post->post_status ) {
			return $existing;
		}
		$original_body = $render_body( $original_bodies[ $key ] );
		if ( trim( $post->post_content ) !== trim( $original_body ) ) {
			if ( trim( $post->post_content ) !== trim( $body ) ) {
				WP_CLI::warning( "Preserved edited content for $key. Review its links and publication status." );
			}
			return $existing;
		}

		$changes = array();
		if ( $post->post_content !== $body ) {
			$changes['post_content'] = $body;
		}
		if ( 'draft' === $status && 'publish' === $post->post_status ) {
			$changes['post_status'] = 'draft';
		}
		if ( isset( $previous_titles[ $key ] ) && $previous_titles[ $key ] === $post->post_title ) {
			$changes['post_title'] = $title;
		}
		if ( $changes ) {
			$result = wp_update_post( wp_slash( array( 'ID' => $existing ) + $changes ), true );
			if ( is_wp_error( $result ) ) {
				WP_CLI::error( $result->get_error_message() );
			}
			WP_CLI::log( "Updated original starter $key." );
		}
		return $existing;
	}
	if ( get_page_by_path( $slug, OBJECT, $type ) ) {
		WP_CLI::error( 'Cannot place starter content over an existing slug: ' . $slug );
	}

	$local_editor = get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) );
	$record_id = wp_insert_post(
		wp_slash(
			array(
				'post_type'    => $type,
				'post_status'  => $status,
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $body,
				'post_excerpt' => $excerpt,
				'post_author'  => $local_editor[0] ?? 0,
				'meta_input'   => array( '_rj_hub_seed_key' => $slug ),
			)
		),
		true
	);
	if ( is_wp_error( $record_id ) ) {
		WP_CLI::error( $record_id->get_error_message() );
	}
	WP_CLI::log( 'Created ' . $type . ': ' . $slug );
	return $record_id;
};

$page_ids = array();
$hero_classes = array( 'home' => 'rj-hero-photo', 'home-alternate' => 'rj-alternate-photo' );
foreach ( $pages as $slug => list( $title ) ) {
	$body = $render_body( $page_bodies[ $slug ], $hero_classes[ $slug ] ?? '' );
	if ( preg_match( '/\{\{[A-Z_]+\}\}/', $body ) ) {
		WP_CLI::error( 'An image placeholder remains unresolved in page: ' . $slug );
	}
	$status = in_array( $slug, $public_pages, true ) ? 'publish' : 'draft';
	$page_ids[ $slug ] = $save_seed( 'page', $slug, $title, $body, '', $status );
}

if ( ! get_option( 'page_on_front' ) ) {
	update_option( 'show_on_front', 'page' );
	update_option( 'page_on_front', $page_ids['home'] );
}
if ( ! get_option( 'blogdescription' ) ) {
	update_option( 'blogdescription', 'University of California, Berkeley' );
}
if ( ! get_option( 'wp_page_for_privacy_policy' ) || 'privacy-policy' === get_post_field( 'post_name', get_option( 'wp_page_for_privacy_policy' ) ) ) {
	update_option( 'wp_page_for_privacy_policy', $page_ids['privacy'] );
}

foreach ( $resources as $slug => list( $title, $category, $excerpt ) ) {
	$existing = $find_seed( 'rj_resource', $slug );
	$resource_id = $save_seed( 'rj_resource', $slug, $title, $resource_bodies[ $slug ], $excerpt );
	if ( ! $existing || ( ! $upgrading && ! has_term( '', 'rj_resource_type', $resource_id ) ) ) {
		$result = wp_set_object_terms( $resource_id, $category, 'rj_resource_type' );
		if ( is_wp_error( $result ) ) {
			WP_CLI::error( 'Could not set the resource category: ' . $result->get_error_message() );
		}
	}
}

$navigation = get_page_by_path( 'rj-hub-primary', OBJECT, 'wp_navigation' );
$navigation_id = $navigation && 'rj-hub-primary' !== get_post_meta( $navigation->ID, '_rj_hub_seed_key', true )
	? $navigation->ID
	: $save_seed( 'wp_navigation', 'rj-hub-primary', 'Primary Navigation', $navigation_body );
if ( ! get_option( 'rj_hub_navigation_id' ) ) {
	update_option( 'rj_hub_navigation_id', $navigation_id );
}

if ( isset( $asset_ids['rj-hub-mark'] ) && ! get_option( 'site_logo' ) ) {
	update_option( 'site_logo', $asset_ids['rj-hub-mark'] );
}

update_option( 'rj_hub_initial_seed_complete', 1 );
update_option( 'rj_hub_editorial_seed_version', 2 );
WP_CLI::success( sprintf( 'Prepared %d editable pages and %d resources. Unfinished starter sections are retained as drafts.', count( $page_ids ), count( $resources ) ) );
