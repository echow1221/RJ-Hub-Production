<?php
/** Local file validation and media placement for reviewed source imports. */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit;
}

function rjh_import_readable_file( string $directory, string $relative ): string {
	$file = realpath( "$directory/$relative" );
	if ( ! $file || ! str_starts_with( $file, "$directory/" ) || ! is_file( $file ) || ! is_readable( $file ) ) {
		throw new InvalidArgumentException( 'Expected a readable file below the manifest folder.' );
	}
	return $file;
}

function rjh_import_media_preflight( string $directory, string $manifest, callable $read_url ): array {
	if ( '' === $manifest ) {
		return array();
	}
	$handle = fopen( rjh_import_readable_file( $directory, $manifest ), 'r' );
	$columns = array( 'source_url', 'local_file', 'role', 'source_attachment_id', 'alt_text', 'caption', 'credit', 'rights_status' );
	$result = array();
	$featured = false;
	$mimes = array( 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf' );
	try {
		if ( $columns !== fgetcsv( $handle, 0, ',', '"', '' ) ) {
			throw new InvalidArgumentException( 'Media bundle columns must match content/AUTHORIZED_MEDIA_TEMPLATE.csv.' );
		}
		while ( false !== ( $data = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( array( null ) === $data ) {
				continue;
			}
			if ( count( $data ) !== count( $columns ) ) {
				throw new InvalidArgumentException( 'Wrong number of media CSV fields.' );
			}
			$media = array_combine( $columns, array_map( 'trim', $data ) );
			$url = $read_url( $media['source_url'], 'media source_url' );
			if ( '' === $url || isset( $result[ $url ] ) || ! in_array( $media['role'], array( 'inline', 'featured', 'document' ), true ) || 'cleared' !== $media['rights_status'] ) {
				throw new InvalidArgumentException( 'Each media row needs a unique source URL, valid role, and cleared rights.' );
			}
			$path = rjh_import_readable_file( $directory, $media['local_file'] );
			$ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			$mime = mime_content_type( $path );
			if ( ! isset( $mimes[ $ext ] ) || $mime !== $mimes[ $ext ] || ( 'document' === $media['role'] ) !== ( 'application/pdf' === $mime ) ) {
				throw new InvalidArgumentException( 'A media file must match its type: JPEG, PNG, GIF, WebP, or a PDF document.' );
			}
			if ( 'featured' === $media['role'] && $featured ) {
				throw new InvalidArgumentException( 'Only one image can be featured on a record.' );
			}
			$featured = $featured || 'featured' === $media['role'];
			if ( '' !== $media['source_attachment_id'] && ( ! ctype_digit( $media['source_attachment_id'] ) || (int) $media['source_attachment_id'] < 1 ) ) {
				throw new InvalidArgumentException( 'A source attachment ID must be a positive integer.' );
			}
			$media['file'] = $path;
			$result[ $url ] = $media;
		}
	} finally {
		fclose( $handle );
	}
	return $result;
}

function rjh_import_media_source( $value, string $source_base, array $local_media ): string {
	if ( ! is_string( $value ) || '' === $value ) {
		return '';
	}
	if ( isset( $local_media[ $value ] ) ) {
		return $value;
	}
	$absolute = $source_base ? WP_Http::make_absolute_url( $value, $source_base ) : '';
	return isset( $local_media[ $absolute ] ) ? $absolute : '';
}

function rjh_import_replace_attachment_urls( array $blocks, array $local_media, string $source_base ): array {
	foreach ( $blocks as &$block ) {
		foreach ( array( 'url', 'href' ) as $attribute ) {
			$source = rjh_import_media_source( $block['attrs'][ $attribute ] ?? '', $source_base, $local_media );
			if ( $source ) {
				$block['attrs'][ $attribute ] = $local_media[ $source ]['url'];
			}
		}
		$id = $block['attrs']['id'] ?? null;
		if ( in_array( $block['blockName'], array( 'core/image', 'core/file' ), true ) && is_int( $id ) ) {
			foreach ( $local_media as $media ) {
				if ( (int) $media['source_attachment_id'] === $id ) {
					$block['attrs']['id'] = $media['id'];
					break;
				}
			}
		}
		$block['innerBlocks'] = rjh_import_replace_attachment_urls( $block['innerBlocks'], $local_media, $source_base );
		foreach ( $block['innerContent'] as &$html ) {
			if ( ! is_string( $html ) ) {
				continue;
			}
			$tags = new WP_HTML_Tag_Processor( $html );
			while ( $tags->next_tag() ) {
				foreach ( array( 'src', 'href', 'data' ) as $attribute ) {
					$source = rjh_import_media_source( $tags->get_attribute( $attribute ), $source_base, $local_media );
					if ( ! $source ) {
						continue;
					}
					$media = $local_media[ $source ];
					$tags->set_attribute( $attribute, $media['url'] );
					if ( 'IMG' === $tags->get_tag() && 'src' === $attribute ) {
						$tags->set_attribute( 'alt', sanitize_text_field( $media['alt_text'] ) );
						$tags->remove_attribute( 'srcset' );
						$tags->remove_attribute( 'sizes' );
						if ( '' !== $media['source_attachment_id'] ) {
							$tags->remove_class( 'wp-image-' . $media['source_attachment_id'] );
							$tags->add_class( 'wp-image-' . $media['id'] );
						}
					}
				}
			}
			$html = $tags->get_updated_html();
		}
		unset( $html );
	}
	unset( $block );
	return $blocks;
}

function rjh_import_media( int $id, array $data, array $items, array &$uploaded ): void {
	if ( ! $items ) {
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$local = array();
	foreach ( $items as $source => $media ) {
		$temp = wp_tempnam( basename( $media['file'] ) );
		try {
			if ( ! $temp || ! copy( $media['file'], $temp ) ) {
				throw new RuntimeException( 'Could not stage a reviewed media file.' );
			}
			$attachment = media_handle_sideload(
				array( 'name' => basename( $media['file'] ), 'tmp_name' => $temp, 'error' => UPLOAD_ERR_OK ),
				$id,
				null,
				array( 'post_excerpt' => sanitize_text_field( $media['caption'] ) )
			);
			if ( is_wp_error( $attachment ) ) {
				throw new RuntimeException( $attachment->get_error_message() );
			}
			$uploaded[] = $attachment;
		} finally {
			if ( $temp && is_file( $temp ) ) {
				unlink( $temp );
			}
		}
		update_post_meta( $attachment, '_wp_attachment_image_alt', sanitize_text_field( $media['alt_text'] ) );
		update_post_meta( $attachment, '_rj_media_credit', sanitize_text_field( $media['credit'] ) );
		update_post_meta( $attachment, '_rj_media_source_url', $source );
		$url = wp_get_attachment_url( $attachment );
		if ( ! $url || ( 'featured' === $media['role'] && ! set_post_thumbnail( $id, $attachment ) ) ) {
			throw new RuntimeException( 'Could not attach the reviewed file to its record.' );
		}
		$local[ $source ] = array_merge( $media, array( 'id' => $attachment, 'url' => $url ) );
	}
	foreach ( array( 'rj_pdf_url', 'rj_source_url' ) as $key ) {
		$source = $data['meta_input'][ $key ] ?? '';
		if ( isset( $local[ $source ] ) ) {
			update_post_meta( $id, $key, $local[ $source ]['url'] );
		}
	}
	$body = serialize_blocks( rjh_import_replace_attachment_urls( parse_blocks( $data['post_content'] ), $local, $data['meta_input']['_rj_source_url'] ) );
	$result = wp_update_post( array( 'ID' => $id, 'post_content' => wp_slash( $body ) ), true );
	if ( is_wp_error( $result ) ) {
		throw new RuntimeException( $result->get_error_message() );
	}
}
