<?php

defined( 'ABSPATH' ) || exit;

function rjh_types(): array {
	return array(
		'rj_project'   => array( 'Projects', 'Project', 'project', 'dashicons-portfolio' ),
		'rj_resource'  => array( 'Resources', 'Resource', 'resource', 'dashicons-book' ),
		'rj_map_entry' => array( 'Historical Map Entries', 'Historical Map Entry', 'historical-location', 'dashicons-location-alt' ),
		'rj_partner'   => array( 'Community Partners', 'Community Partner', 'partner', 'dashicons-groups' ),
		'rj_offering'  => array( 'Offerings', 'Offering', 'offering', 'dashicons-welcome-learn-more' ),
	);
}

function rjh_fields( string $post_type ): array {
	$url_fields = array(
		'rj_source_url' => array( 'Source URL', 'url', 'Link to a source, reference, or approved external resource.' ),
		'rj_pdf_url'    => array( 'PDF URL', 'url', 'To add an approved PDF, upload it in Media and paste its File URL.' ),
	);
	$review_fields = array(
		'rj_review_status' => array( 'Review status', 'review', 'Select Verified only after the approved source or institutional owner confirms the record.' ),
		'rj_source_url'    => $url_fields['rj_source_url'],
	);

	switch ( $post_type ) {
		case 'rj_project':
		case 'rj_resource':
			return $url_fields;
		case 'rj_partner':
		case 'rj_offering':
			return $review_fields;
		case 'rj_map_entry':
			return array_merge(
				$review_fields,
				array(
					'rj_pdf_url'         => $url_fields['rj_pdf_url'],
					'rj_location'        => array( 'Place', 'text', 'Use the place name supported by the source. Label approximate locations.' ),
					'rj_event_date'      => array( 'Event date or date range', 'text', 'Use a supported date or explicitly describe uncertainty.' ),
					'rj_nation'          => array( 'Nation or community', 'text', 'Document the Nation or community as identified in the source.' ),
					'rj_latitude'        => array( 'Latitude', 'coordinate', 'Decimal degrees. California latitudes are approximately 32.5 to 42.1.' ),
					'rj_longitude'       => array( 'Longitude', 'coordinate', 'Decimal degrees west of Greenwich. Enter a negative number.' ),
					'rj_content_warning' => array( 'Content warning', 'text', 'Describe any additional traumatic material in this record or its source.' ),
				)
			);
	}
	return array();
}

function rjh_register_content_types(): void {
	foreach ( rjh_types() as $name => list( $plural, $single, $slug, $icon ) ) {
		$map_private = 'rj_map_entry' === $name;
		register_post_type(
			$name,
			array(
				'labels'              => array(
					'name'          => $plural,
					'singular_name' => $single,
					'add_new_item'  => "Add $single",
					'edit_item'     => "Edit $single",
				),
				'public'              => ! $map_private,
				'show_ui'             => true,
				'show_in_rest'        => true,
				'exclude_from_search' => $map_private,
				'has_archive'         => false,
				'rewrite'             => $map_private ? false : array( 'slug' => $slug, 'with_front' => false ),
				'menu_icon'           => $icon,
				'supports'            => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions', 'author', 'custom-fields' ),
				'map_meta_cap'        => true,
			)
		);

		foreach ( rjh_fields( $name ) as $meta_key => list( $label, $type ) ) {
			register_post_meta(
				$name,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => 'review' === $type ? 'unreviewed' : '',
					'show_in_rest'      => true,
					'revisions_enabled' => ! in_array( $meta_key, array( 'rj_review_status', 'rj_source_url', 'rj_location', 'rj_event_date', 'rj_latitude', 'rj_longitude' ), true ),
					'sanitize_callback' => static fn( $value ) => rjh_sanitize_field( $value, $type ),
					'auth_callback'     => static fn( $allowed, $key, $post_id, $user_id ) => user_can( $user_id, 'edit_post', $post_id ),
				)
			);
		}

		if ( isset( rjh_fields( $name )['rj_review_status'] ) ) {
			add_filter( 'rest_pre_insert_' . $name, 'rjh_validate_rest_publication', 10, 2 );
		}
	}

	register_taxonomy(
		'rj_project_topic', 'rj_project',
		array( 'label' => 'Project Topics', 'hierarchical' => true, 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'project-topic' ) )
	);
	register_taxonomy(
		'rj_resource_type', 'rj_resource',
		array( 'label' => 'Resource Categories', 'hierarchical' => true, 'show_in_rest' => true, 'rewrite' => array( 'slug' => 'resource-category' ) )
	);
}

function rjh_sanitize_field( $value, string $type ): string {
	if ( ! is_scalar( $value ) ) {
		return '';
	}
	$value = trim( (string) $value );
	if ( 'review' === $type ) {
		return 'verified' === $value ? 'verified' : 'unreviewed';
	}
	if ( 'url' === $type ) {
		return filter_var( $value, FILTER_VALIDATE_URL ) && preg_match( '#^https?://#i', $value )
			? esc_url_raw( $value, array( 'http', 'https' ) )
			: '';
	}
	if ( 'coordinate' === $type ) {
		return is_numeric( $value ) && is_finite( (float) $value ) ? $value : '';
	}
	return sanitize_text_field( $value );
}

function rjh_post_fields( string $post_type, int $post_id, array $pending = array() ): array {
	$values = array();
	foreach ( rjh_fields( $post_type ) as $key => list( , $type ) ) {
		$value = array_key_exists( $key, $pending ) ? $pending[ $key ] : get_post_meta( $post_id, $key, true );
		$values[ $key ] = rjh_sanitize_field( $value, $type );
	}
	return $values;
}

function rjh_publication_error( string $post_type, array $meta ): string {
	if ( ! isset( $meta['rj_review_status'] ) ) {
		return '';
	}
	if ( 'verified' !== $meta['rj_review_status'] || '' === $meta['rj_source_url'] ) {
		return 'Set Review status to Verified and enter a source URL before publishing.';
	}
	if ( 'rj_map_entry' !== $post_type ) {
		return '';
	}
	if ( '' === $meta['rj_location'] || '' === $meta['rj_event_date'] ) {
		return 'Enter the documented place and event date or date range before publishing.';
	}
	if ( '' === $meta['rj_latitude'] || '' === $meta['rj_longitude']
		|| $meta['rj_latitude'] < 32.45 || $meta['rj_latitude'] > 42.1
		|| $meta['rj_longitude'] < -124.65 || $meta['rj_longitude'] > -113.95 ) {
		return 'Enter source-supported decimal coordinates within California before publishing.';
	}
	return '';
}

function rjh_validate_rest_publication( $prepared, WP_REST_Request $request ) {
	$post_id   = (int) $request['id'];
	$post_type = $prepared->post_type ?? get_post_type( $post_id );
	$status    = $prepared->post_status ?? get_post_status( $post_id );
	if ( ! in_array( $status, array( 'publish', 'future' ), true ) ) {
		return $prepared;
	}

	$pending = (array) $request->get_param( 'meta' );
	$meta    = rjh_post_fields( $post_type, $post_id, $pending );
	$error   = rjh_publication_error( $post_type, $meta );
	if ( $error ) {
		return new WP_Error( 'rjh_pending_review', $error, array( 'status' => 400 ) );
	}

	// The REST controller usually writes meta after the post. Pass validated
	// submitted fields to the low-level publication guard and insert together.
	$prepared->meta_input = wp_array_slice_assoc( $meta, array_keys( $pending ) );
	return $prepared;
}

function rjh_guard_publication( array $data, array $postarr ): array {
	if ( ! in_array( $data['post_status'], array( 'publish', 'future' ), true )
		|| ! isset( rjh_fields( $data['post_type'] )['rj_review_status'] ) ) {
		return $data;
	}
	$pending = isset( $postarr['meta_input'] ) ? wp_unslash( $postarr['meta_input'] ) : array();
	$meta    = rjh_post_fields( $data['post_type'], (int) ( $postarr['ID'] ?? 0 ), $pending );
	if ( rjh_publication_error( $data['post_type'], $meta ) ) {
		$data['post_status'] = 'draft';
	}
	return $data;
}

function rjh_append_reference_links( string $content ): string {
	if ( ! is_singular( array( 'rj_project', 'rj_resource', 'rj_partner', 'rj_offering' ) )
		|| get_the_ID() !== get_queried_object_id() ) {
		return $content;
	}
	$meta  = rjh_post_fields( get_post_type(), get_the_ID() );
	$links = rjh_reference_links( $meta );
	return $links ? $content . '<aside aria-label="References" class="rjh-reference-links">' . $links . '</aside>' : $content;
}
