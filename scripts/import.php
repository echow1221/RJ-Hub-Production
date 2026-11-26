<?php
/**
 * Validate and import an authorized, individually reviewed source manifest.
 * Usage: wp eval-file scripts/import.php /path/to/authorized.csv [apply]
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

$manifest_path = realpath( $args[0] ?? '' );
$apply         = isset( $args[1] ) && 'apply' === $args[1];
if ( ! $manifest_path || ! is_file( $manifest_path ) || ( isset( $args[1] ) && ! $apply ) || count( $args ) > 2 ) {
	WP_CLI::error( 'Usage: wp eval-file /path/to/project/scripts/import.php /path/to/authorized.csv [apply]' );
}
if ( ! post_type_exists( 'rj_resource' ) ) {
	WP_CLI::error( 'Activate the RJ Hub content plugin before importing.' );
}
if ( 'local' !== wp_get_environment_type() ) {
	WP_CLI::error( 'Use the importer on an isolated local installation.' );
}

$manifest_dir = dirname( $manifest_path );
$columns = array(
	'source_id', 'source_url', 'source_status', 'title', 'byline', 'published_at', 'modified_at',
	'wordpress_author_login', 'target_type', 'target_slug', 'body_file', 'body_format', 'excerpt',
	'review_status', 'rights_status', 'approved_for_preview', 'source_attachment_urls',
	'citation_url', 'pdf_url', 'terms', 'tags', 'location', 'latitude', 'longitude', 'event_date', 'nation', 'content_warning',
);
$types = array( 'post', 'page', 'rj_project', 'rj_resource', 'rj_map_entry', 'rj_partner', 'rj_offering' );
$source_statuses = array( 'publish', 'published', 'draft', 'pending', 'private', 'future', 'unpublished' );

$read_url = static function ( string $input, string $field ): string {
	if ( '' === $input ) {
		return '';
	}

	$sanitized = esc_url_raw( $input, array( 'http', 'https' ) );
	$parsed    = wp_parse_url( $sanitized );
	if ( ! is_array( $parsed ) || empty( $parsed['host'] ) || ! in_array( $parsed['scheme'] ?? '', array( 'http', 'https' ), true ) || isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
		throw new InvalidArgumentException( 'Expected a complete HTTP(S) URL without credentials for ' . $field . '.' );
	}
	return $sanitized;
};

$read_date = static function ( string $input, string $field ): ?DateTimeImmutable {
	if ( '' === $input ) {
		return null;
	}
	if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}(?:T[0-9]{2}:[0-9]{2}:[0-9]{2}(?:Z|[+-][0-9]{2}:[0-9]{2}))?$/D', $input ) ) {
		throw new InvalidArgumentException( "Expected YYYY-MM-DD or ISO 8601 with an offset for $field." );
	}
	$date = new DateTimeImmutable( $input, wp_timezone() );
	$errors = DateTimeImmutable::getLastErrors();
	if ( is_array( $errors ) && ( $errors['warning_count'] || $errors['error_count'] ) ) {
		throw new InvalidArgumentException( 'Invalid date in ' . $field . '.' );
	}
	return $date;
};

$find_imported = static function ( string $key ) use ( $types ): int {
	$matches = get_posts(
		array(
			'post_type'      => $types,
			'post_status'    => array_keys( get_post_stati() ),
			'meta_key'       => '_rj_source_record_key',
			'meta_value'     => $key,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		)
	);
	return (int) ( $matches[0] ?? 0 );
};

$validate_row = static function ( array $row ) use ( $manifest_dir, $types, $source_statuses, $read_url, $read_date, $find_imported ): array {
	$type = $row['target_type'];
	$slug = $row['target_slug'];
	$title = sanitize_text_field( $row['title'] );
	if ( ! in_array( $type, $types, true ) || ! post_type_exists( $type ) || ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug ) ) {
		throw new InvalidArgumentException( 'Choose a supported target_type and an explicit lowercase target_slug.' );
	}
	if ( '' === $title || ! in_array( $row['source_status'], $source_statuses, true ) ) {
		throw new InvalidArgumentException( 'A title and a recognized source_status are required.' );
	}

	$source_url = $read_url( $row['source_url'], 'source_url' );
	if ( '' === $source_url && '' === $row['source_id'] ) {
		throw new InvalidArgumentException( 'A source URL or source ID is required.' );
	}
	// Keep record identity stable when an export contains both the source ID and any URL alias.
	$key = hash( 'sha256', '' !== $row['source_id'] ? 'id:' . $row['source_id'] : 'url:' . $source_url );
	$existing_id = $find_imported( $key );
	$slug_match  = get_page_by_path( $slug, OBJECT, $type );
	if ( ! $existing_id && $slug_match ) {
		throw new InvalidArgumentException( "The target $type/$slug already exists. A reviewer must resolve the target manually." );
	}

	$body_file = realpath( "$manifest_dir/" . $row['body_file'] );
	if ( ! $body_file || ! is_file( $body_file ) || ! str_starts_with( $body_file, "$manifest_dir/" ) || ! is_readable( $body_file ) ) {
		throw new InvalidArgumentException( 'body_file must be a readable file below the manifest folder.' );
	}
	$raw_body = file_get_contents( $body_file );
	if ( false === $raw_body || '' === trim( $raw_body ) || ! in_array( $row['body_format'], array( 'classic', 'blocks' ), true ) ) {
		throw new InvalidArgumentException( 'A nonempty body file and classic or blocks body_format are required.' );
	}
	if ( 'blocks' === $row['body_format'] && ! has_blocks( $raw_body ) ) {
		throw new InvalidArgumentException( 'The body file for blocks body_format has no WordPress blocks.' );
	}

	if ( ! in_array( $row['review_status'], array( 'unreviewed', 'in_review', 'verified' ), true ) ||
		! in_array( $row['rights_status'], array( 'review_required', 'cleared' ), true ) ||
		! in_array( $row['approved_for_preview'], array( 'yes', 'no' ), true ) ) {
		throw new InvalidArgumentException( 'Set review_status, rights_status, and approved_for_preview explicitly.' );
	}
	$publish = in_array( $row['source_status'], array( 'publish', 'published' ), true ) &&
		'yes' === $row['approved_for_preview'] && 'cleared' === $row['rights_status'] && 'verified' === $row['review_status'];
	$published = $read_date( $row['published_at'], 'published_at' );
	$read_date( $row['modified_at'], 'modified_at' );
	if ( $publish && ( ! $published || $published->getTimestamp() > time() ) ) {
		throw new InvalidArgumentException( 'A public source record needs a known, non-future publication date before publishing here.' );
	}

	$author = 0;
	if ( '' !== $row['wordpress_author_login'] ) {
		$user = get_user_by( 'login', $row['wordpress_author_login'] );
		if ( ! $user ) {
			throw new InvalidArgumentException( 'wordpress_author_login does not identify an existing user.' );
		}
		$author = $user->ID;
	}

	$attachment_urls = array_filter( array_map( 'trim', explode( '|', $row['source_attachment_urls'] ) ) );
	foreach ( $attachment_urls as &$attachment_url ) {
		$attachment_url = $read_url( $attachment_url, 'source_attachment_urls' );
	}
	unset( $attachment_url );
	$valid_meta = array(
		'_rj_source_record_key'     => $key,
		'_rj_source_id'             => $row['source_id'],
		'_rj_source_url'            => $source_url,
		'_rj_source_status'         => $row['source_status'],
		'rj_source_byline'          => sanitize_text_field( $row['byline'] ),
		'rj_source_published'       => $row['published_at'],
		'rj_source_modified'        => $row['modified_at'],
		'_rj_source_attachment_urls' => array_values( $attachment_urls ),
		'_rj_source_rights_status'  => $row['rights_status'],
	);
	if ( in_array( $type, array( 'rj_project', 'rj_resource', 'rj_map_entry', 'rj_partner', 'rj_offering' ), true ) ) {
		$valid_meta['rj_source_url'] = $read_url( $row['citation_url'], 'citation_url' );
	}
	if ( in_array( $type, array( 'rj_project', 'rj_resource', 'rj_map_entry' ), true ) ) {
		$valid_meta['rj_pdf_url'] = $read_url( $row['pdf_url'], 'pdf_url' );
	}
	if ( in_array( $type, array( 'rj_map_entry', 'rj_partner', 'rj_offering' ), true ) ) {
		$valid_meta['rj_review_status'] = $publish ? 'verified' : 'unreviewed';
		if ( $publish && empty( $valid_meta['rj_source_url'] ) ) {
			throw new InvalidArgumentException( 'A verified source or approval URL is required before publishing this record type.' );
		}
	}
	if ( 'rj_map_entry' === $type ) {
		foreach ( array( 'location', 'event_date', 'nation', 'content_warning' ) as $field ) {
			$valid_meta[ 'rj_' . $field ] = sanitize_text_field( $row[ $field ] );
			if ( $publish && '' === $valid_meta[ 'rj_' . $field ] ) {
				throw new InvalidArgumentException( 'Verified map entries require ' . $field . '.' );
			}
		}
		foreach ( array( 'latitude' => 90, 'longitude' => 180 ) as $field => $limit ) {
			$coordinate = $row[ $field ];
			if ( ( '' !== $coordinate || $publish ) && ( ! is_numeric( $coordinate ) || abs( (float) $coordinate ) > $limit ) ) {
				throw new InvalidArgumentException( 'Invalid coordinate in ' . $field . '.' );
			}
			$valid_meta[ 'rj_' . $field ] = $coordinate;
		}
	}

	$safe_body = wp_kses_post( $raw_body );
	if ( trim( $safe_body ) !== trim( $raw_body ) ) {
		WP_CLI::warning( "$type/$slug: WordPress adjusted HTML during sanitization. Compare the entire article to the original after importing." );
	}
	if ( 'classic' === $row['body_format'] ) {
		$safe_body = "<!-- wp:freeform -->\n$safe_body\n<!-- /wp:freeform -->";
	}
	$data = array(
		'post_type'    => $type,
		'post_status'  => $publish ? 'publish' : 'draft',
		'post_name'    => $slug,
		'post_title'   => $title,
		'post_excerpt' => wp_kses_post( $row['excerpt'] ),
		'post_content' => $safe_body,
		'post_author'  => $author,
		'meta_input'   => $valid_meta,
	);
	if ( $published ) {
		$data['post_date']     = $published->setTimezone( wp_timezone() )->format( 'Y-m-d H:i:s' );
		$data['post_date_gmt'] = $published->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' );
	}
	$taxonomy = array( 'post' => 'category', 'rj_resource' => 'rj_resource_type', 'rj_project' => 'rj_project_topic' )[ $type ] ?? '';
	$terms = array_values( array_filter( array_map( 'trim', explode( '|', $row['terms'] ) ) ) );
	if ( $terms && ! taxonomy_exists( $taxonomy ) ) {
		throw new InvalidArgumentException( 'This type has no matching taxonomy for the supplied terms.' );
	}
	$tags = array_values( array_filter( array_map( 'trim', explode( '|', $row['tags'] ) ) ) );
	if ( $tags && 'post' !== $type ) {
		throw new InvalidArgumentException( 'Only posts support tags in this importer.' );
	}
	$taxonomies = array_filter( array( $taxonomy => $terms, 'post_tag' => $tags ) );

	return compact( 'key', 'existing_id', 'data', 'taxonomies' );
};

$file = fopen( $manifest_path, 'r' );
if ( ! $file ) {
	WP_CLI::error( 'Cannot read the source manifest.' );
}
$header = fgetcsv( $file, 0, ',', '"', '' );
if ( ! is_array( $header ) || $columns !== $header ) {
	fclose( $file );
	WP_CLI::error( 'Manifest columns must match content/AUTHORIZED_IMPORT_TEMPLATE.csv exactly.' );
}
$rows = array();
$seen_keys = array();
$seen_targets = array();
$problems = array();
$line = 1;
while ( false !== ( $values = fgetcsv( $file, 0, ',', '"', '' ) ) ) {
	++$line;
	if ( array( null ) === $values ) {
		continue;
	}
	if ( count( $values ) !== count( $columns ) ) {
		$problems[] = "Record $line has the wrong number of CSV fields.";
		continue;
	}
	$row = array_combine( $columns, array_map( 'trim', $values ) );
	try {
		$validated = $validate_row( $row );
		$key = $validated['key'];
		$target = $row['target_type'] . '/' . $row['target_slug'];
		if ( isset( $seen_keys[ $key ] ) || isset( $seen_targets[ $target ] ) ) {
			throw new InvalidArgumentException( 'Duplicate source record or target slug.' );
		}
		$seen_keys[ $key ] = true;
		$seen_targets[ $target ] = true;
		$rows[] = $validated;
	} catch ( Exception $error ) {
		$problems[] = "Record $line: " . $error->getMessage();
	}
}
fclose( $file );

if ( $problems ) {
	foreach ( $problems as $problem ) {
		WP_CLI::warning( $problem );
	}
	WP_CLI::error( 'Source manifest preflight failed. No content imported.' );
}
if ( ! $rows ) {
	WP_CLI::error( 'The manifest contains no importable records.' );
}

$counts = array( 'existing' => 0, 'publish' => 0, 'draft' => 0 );
foreach ( $rows as $row ) {
	$data = $row['data'];
	$description = $data['post_type'] . '/' . $data['post_name'];
	if ( $row['existing_id'] ) {
		++$counts['existing'];
		WP_CLI::log( 'Preserved existing source record: ' . $description );
		continue;
	}
	++$counts[ $data['post_status'] ];
	if ( ! $apply ) {
		WP_CLI::log( $data['post_status'] . ' after apply: ' . $description );
		continue;
	}

	// Import as a draft and attach terms before making an approved record visible.
	$initial = array_merge( $data, array( 'post_status' => 'draft' ) );
	$id = wp_insert_post( wp_slash( $initial ), true );
	if ( is_wp_error( $id ) ) {
		WP_CLI::error( $description . ': ' . $id->get_error_message() );
	}
	if ( $data['post_name'] !== get_post_field( 'post_name', $id ) ) {
		wp_delete_post( $id, true );
		WP_CLI::error( 'Could not preserve the requested canonical slug: ' . $description );
	}
	foreach ( $row['taxonomies'] as $taxonomy => $terms ) {
		$result = wp_set_object_terms( $id, $terms, $taxonomy );
		if ( is_wp_error( $result ) ) {
			wp_delete_post( $id, true );
			WP_CLI::error( $description . ': ' . $result->get_error_message() );
		}
	}
	if ( 'publish' === $data['post_status'] ) {
		$result = wp_update_post( array( 'ID' => $id, 'post_status' => 'publish' ), true );
		if ( is_wp_error( $result ) || 'publish' !== get_post_status( $id ) || $data['post_name'] !== get_post_field( 'post_name', $id ) ) {
			wp_delete_post( $id, true );
			WP_CLI::error( 'Could not publish with the reviewed status and canonical slug: ' . $description );
		}
	}

	WP_CLI::log( sprintf( 'Imported %s as %s with ID %d.', $description, $data['post_status'], $id ) );
}

WP_CLI::success(
	sprintf(
		'%s. Published candidates: %d. Draft candidates: %d. Existing records preserved: %d.',
		$apply ? 'Import complete' : 'Dry run finished',
		$counts['publish'],
		$counts['draft'],
		$counts['existing']
	)
);
