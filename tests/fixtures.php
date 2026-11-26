<?php
/**
 * Create fixtures only in the disposable RJ Hub QA stack.
 * Run via scripts/wp eval-file /workspace/project/tests/fixtures.php seed|clean.
 */

if ( ! in_array( wp_parse_url( home_url(), PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ||
	8081 !== wp_parse_url( home_url(), PHP_URL_PORT ) ) {
	WP_CLI::error( 'This test requires a disposable WordPress site on loopback port 8081.' );
}

if ( ! in_array( $args[0] ?? '', array( 'seed', 'clean' ), true ) ) {
	WP_CLI::error( 'Specify seed or clean.' );
}

$fixture_key = '_rjhub_qa_fixture';
$taxonomies  = array( 'category', 'post_tag', 'rj_resource_type' );
$terms       = array( 'rjhub-qa-keep', 'rjhub-qa-drop' );

$cleanup = static function () use ( $fixture_key, $taxonomies, $terms ): void {
	$ids = get_posts(
		array(
			'post_type'      => array( 'post', 'rj_resource', 'rj_map_entry' ),
			'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future', 'trash' ),
			'posts_per_page' => -1,
			'meta_key'       => $fixture_key,
			'meta_value'     => 'yes',
			'fields'         => 'ids',
		)
	);

	foreach ( $ids as $id ) {
		wp_delete_post( $id, true );
	}
	foreach ( $taxonomies as $taxonomy ) {
		foreach ( $terms as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term ) {
				wp_delete_term( $term->term_id, $taxonomy );
			}
		}
	}
};

$cleanup();
if ( 'clean' === $args[0] ) {
	WP_CLI::success( 'Removed QA fixtures.' );
	return;
}

$check = static function ( $result ) {
	if ( is_wp_error( $result ) ) {
		WP_CLI::error( $result->get_error_message() );
	}
	return $result;
};

$term_ids = array();
foreach ( $taxonomies as $taxonomy ) {
	foreach ( $terms as $slug ) {
		$term_ids[ $taxonomy ][ $slug ] = $check( wp_insert_term( $slug, $taxonomy, array( 'slug' => $slug ) ) )['term_id'];
	}
}

$insert = static function ( string $type, string $title, array $fields = array(), string $date = '' ) use ( $check, $fixture_key ): int {
	return $check(
		wp_insert_post(
			array(
				'post_type'    => $type,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => '<!-- wp:paragraph --><p>Disposable technical QA fixture.</p><!-- /wp:paragraph -->',
				'post_date'    => $date,
				'meta_input'   => array_merge( $fields, array( $fixture_key => 'yes' ) ),
			),
			true
		)
	);
};

for ( $index = 1; $index <= 14; ++$index ) {
	$term = 14 === $index ? 'rjhub-qa-drop' : 'rjhub-qa-keep';
	$date = sprintf( '2024-01-%02d 12:00:00', $index );
	$post = $insert( 'post', "rjhub-qa-fixture Blog $index", array( 'rj_source_byline' => 'Synthetic QA Author' ), $date );
	$check( wp_set_object_terms( $post, $term_ids['category'][ $term ], 'category' ) );
	$check( wp_set_object_terms( $post, $term_ids['post_tag']['rjhub-qa-keep'], 'post_tag' ) );

	$resource = $insert( 'rj_resource', "rjhub-qa-fixture Resource $index", array(), $date );
	$check( wp_set_object_terms( $resource, $term_ids['rj_resource_type'][ $term ], 'rj_resource_type' ) );
}

$wrong_tag = $insert( 'post', 'rjhub-qa-fixture Blog 15', array(), '2024-01-15 12:00:00' );
$check( wp_set_object_terms( $wrong_tag, $term_ids['category']['rjhub-qa-keep'], 'category' ) );
$check( wp_set_object_terms( $wrong_tag, $term_ids['post_tag']['rjhub-qa-drop'], 'post_tag' ) );

$nonmatching_post = $insert( 'post', 'Comparison post', array(), '2024-01-16 12:00:00' );
$check( wp_set_object_terms( $nonmatching_post, $term_ids['category']['rjhub-qa-keep'], 'category' ) );
$check( wp_set_object_terms( $nonmatching_post, $term_ids['post_tag']['rjhub-qa-keep'], 'post_tag' ) );

$nonmatching_resource = $insert( 'rj_resource', 'Comparison resource', array(), '2024-01-16 12:00:00' );
$check( wp_set_object_terms( $nonmatching_resource, $term_ids['rj_resource_type']['rjhub-qa-keep'], 'rj_resource_type' ) );

$map_fields = array(
	'rj_location'        => 'Synthetic location',
	'rj_event_date'      => 'Synthetic date',
	'rj_source_url'      => 'https://example.org/',
	'rj_nation'          => 'No historical community asserted in this fixture',
	'rj_content_warning' => 'Test data. No historical event described.',
	'rj_latitude'        => 37.5,
	'rj_longitude'       => -119.5,
	'rj_review_status'   => 'verified',
);
$verified   = $insert( 'rj_map_entry', 'rjhub-qa-fixture Verified map record', $map_fields );
$in_review  = $insert(
	'rj_map_entry',
	'rjhub-qa-fixture Unverified map record',
	array_merge( $map_fields, array( 'rj_review_status' => 'review' ) )
);
$unlocated  = $insert(
	'rj_map_entry',
	'rjhub-qa-fixture Incomplete map record',
	array_merge( $map_fields, array( 'rj_latitude' => '' ) )
);
if ( 'publish' !== get_post_status( $verified ) ||
	'draft' !== get_post_status( $in_review ) || 'draft' !== get_post_status( $unlocated ) ) {
	WP_CLI::error( 'The map fixtures were not created as expected.' );
}
WP_CLI::success( 'Created disposable archive and historical map fixtures.' );
