<?php

defined( 'ABSPATH' ) || exit;

function rjh_primary_navigation_id(): int {
	$id = (int) get_option( 'rj_hub_navigation_id', 0 );
	if ( $id && 'wp_navigation' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
		return $id;
	}
	$post = get_page_by_path( 'rj-hub-primary', OBJECT, 'wp_navigation' );
	return $post && 'publish' === $post->post_status ? $post->ID : 0;
}

function rjh_hide_private_navigation_link( string $html, array $block ): string {
	$attributes = $block['attrs'];
	$id = 'post-type' === ( $attributes['kind'] ?? '' ) ? (int) ( $attributes['id'] ?? 0 ) : 0;
	if ( ! $id ) {
		$url = wp_parse_url( $attributes['url'] ?? '' );
		$home = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $url ) || ( isset( $url['host'] ) && strtolower( $url['host'] ) !== strtolower( $home['host'] ) ) ) {
			return $html;
		}
		parse_str( $url['query'] ?? '', $query );
		$query_id = $query['page_id'] ?? $query['p'] ?? 0;
		$id = is_scalar( $query_id ) ? absint( $query_id ) : 0;
		$path = $url['path'] ?? '';
		$root = $home['path'] ?? '/';
		if ( ! $id && str_starts_with( $path, $root ) ) {
			$type = array( 'page', 'post' );
			$slug = trim( substr( $path, strlen( $root ) ), '/' );
			foreach ( rjh_types() as $post_type => list( , , $prefix ) ) {
				if ( str_starts_with( $slug, $prefix . '/' ) ) {
					$type = $post_type;
					$slug = substr( $slug, strlen( $prefix ) + 1 );
					break;
				}
			}
			$post = $slug ? get_page_by_path( $slug, OBJECT, $type ) : null;
			$id = $post->ID ?? 0;
		}
	}
	if ( ! $id ) {
		return $html;
	}
	$post = get_post( $id );
	return ! $post || 'publish' !== $post->post_status || '' !== $post->post_password ? '' : $html;
}

function rjh_reference_primary_navigation( ?string $pre_render, array $block ): ?string {
	$class = $block['attrs']['className'] ?? '';
	if ( null !== $pre_render || 'core/navigation' !== $block['blockName'] || ! in_array( 'rj-hub-primary-navigation', explode( ' ', $class ), true ) ) {
		return $pre_render;
	}
	$id = rjh_primary_navigation_id();
	if ( ! $id || ( $id === ( $block['attrs']['ref'] ?? null ) && ! isset( $block['attrs']['navigationMenuId'] ) && ! $block['innerBlocks'] ) ) {
		return $pre_render;
	}

	// Nested core Navigation blocks can cache their attributes before the
	// render_block_data hook. Render a new instance with its reference set.
	$block['attrs']['ref'] = $id;
	unset( $block['attrs']['navigationMenuId'] );
	$block['innerBlocks']  = array();
	$block['innerContent'] = array();
	$block['innerHTML']    = '';
	return render_block( $block );
}

function rjh_register_navigation_screen(): void {
	add_menu_page( 'Site Navigation', 'Site Navigation', 'rj_manage_primary_navigation', 'rjh-primary-navigation', 'rjh_navigation_screen', 'dashicons-menu-alt3' );
}

function rjh_default_navigation(): array {
	return array(
		array( 'Home', home_url( '/' ) ),
		array( 'About', home_url( '/about/' ) ),
		array( 'What Is RJ?', home_url( '/what-is-rj/' ) ),
		array( 'Projects', home_url( '/projects/' ) ),
		array( 'Maps', home_url( '/maps/' ) ),
		array( 'Resources', home_url( '/resources/' ) ),
		array( 'Offerings', home_url( '/offerings/' ) ),
		array( 'Community Partners', home_url( '/community-partners/' ) ),
		array( 'Blog', home_url( '/blog/' ) ),
		array( 'Get Involved', home_url( '/get-involved/' ) ),
	);
}

function rjh_menu_blocks( array $links ): string {
	$blocks = array();
	foreach ( $links as list( $label, $url ) ) {
		$blocks[] = array(
			'blockName'    => 'core/navigation-link',
			'attrs'        => array( 'label' => $label, 'url' => $url, 'kind' => 'custom', 'type' => 'custom' ),
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		);
	}
	return serialize_blocks( $blocks );
}

function rjh_menu_rows( string $content ): ?array {
	$links = array();
	foreach ( parse_blocks( $content ) as $block ) {
		if ( null === $block['blockName'] && '' === trim( $block['innerHTML'] ) ) {
			continue;
		}
		if ( 'core/navigation-link' !== $block['blockName'] || $block['innerBlocks'] || ! empty( $block['attrs']['opensInNewTab'] ) ) {
			return null;
		}
		$attrs   = $block['attrs'];
		$links[] = array( $attrs['label'] ?? '', $attrs['url'] ?? '' );
	}
	return $links;
}

function rjh_navigation_screen(): void {
	if ( ! current_user_can( 'rj_manage_primary_navigation' ) ) {
		wp_die( 'This account cannot edit the site navigation.' );
	}
	$id      = rjh_primary_navigation_id();
	$content = $id ? (string) get_post_field( 'post_content', $id, 'raw' ) : '';
	$links   = $id ? rjh_menu_rows( $content ) : rjh_default_navigation();
	?>
	<div class="wrap">
		<h1>Site Navigation</h1>
		<?php if ( isset( $_GET['rjh_saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Site navigation saved.</p></div>
		<?php endif; ?>
		<?php if ( null === $links ) : ?>
			<p>The primary menu uses nested or advanced navigation blocks. Ask the site administrator to edit that menu in the Site Editor.</p>
		<?php else : ?>
			<p>Use this screen to change the main menu. Lower order numbers come first. Use the blank row to add a link.</p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="rjh_save_navigation">
				<input type="hidden" name="rjh_menu_version" value="<?php echo esc_attr( hash( 'sha256', $id . ':' . $content ) ); ?>">
				<?php wp_nonce_field( 'rjh_save_navigation' ); ?>
				<table class="widefat striped">
					<thead><tr><th scope="col">Order</th><th scope="col">Link title</th><th scope="col">Destination</th><th scope="col">Remove</th></tr></thead>
					<tbody>
					<?php $links[] = array( '', '' ); ?>
					<?php foreach ( $links as $index => list( $label, $url ) ) : ?>
						<?php $row = '' === $label ? 'New link' : $label; ?>
						<tr>
							<td><input type="number" min="1" max="999" name="links[<?php echo esc_attr( $index ); ?>][position]" aria-label="<?php echo esc_attr( $row . ' order' ); ?>" value="<?php echo esc_attr( ( $index + 1 ) * 10 ); ?>" class="small-text"></td>
							<td><input type="text" name="links[<?php echo esc_attr( $index ); ?>][title]" aria-label="<?php echo esc_attr( $row . ' link title' ); ?>" value="<?php echo esc_attr( $label ); ?>" maxlength="100" class="regular-text"></td>
							<td><input type="text" name="links[<?php echo esc_attr( $index ); ?>][url]" aria-label="<?php echo esc_attr( $row . ' destination' ); ?>" value="<?php echo esc_attr( $url ); ?>" class="regular-text"></td>
							<td><?php if ( '' !== $label ) : ?><input type="checkbox" name="links[<?php echo esc_attr( $index ); ?>][remove]" value="1" aria-label="<?php echo esc_attr( 'Remove ' . $row ); ?>"><?php endif; ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<?php submit_button( 'Save navigation' ); ?>
			</form>
		<?php endif; ?>
	</div>
	<?php
}

function rjh_menu_url( string $value ): string {
	$value = trim( $value );
	if ( preg_match( '#^/(?![/\\\\])#', $value ) && ! str_contains( $value, '\\' ) ) {
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}
	return rjh_sanitize_field( $value, 'url' );
}

function rjh_save_navigation(): void {
	if ( ! current_user_can( 'rj_manage_primary_navigation' ) ) {
		wp_die( 'This account cannot edit the site navigation.', '', array( 'response' => 403 ) );
	}
	check_admin_referer( 'rjh_save_navigation' );

	$id      = rjh_primary_navigation_id();
	$content = $id ? (string) get_post_field( 'post_content', $id, 'raw' ) : '';
	$version = isset( $_POST['rjh_menu_version'] ) && is_string( $_POST['rjh_menu_version'] ) ? wp_unslash( $_POST['rjh_menu_version'] ) : '';
	if ( ! hash_equals( hash( 'sha256', $id . ':' . $content ), $version ) || ( $id && null === rjh_menu_rows( $content ) ) ) {
		wp_die( 'The menu changed while you were editing. Open Site Navigation again before saving.', '', array( 'response' => 409, 'back_link' => true ) );
	}

	$submitted = isset( $_POST['links'] ) && is_array( $_POST['links'] ) ? wp_unslash( $_POST['links'] ) : array();
	if ( count( $submitted ) > 51 ) {
		wp_die( 'The main menu is limited to 50 links.', '', array( 'response' => 400, 'back_link' => true ) );
	}
	$links = array();
	foreach ( $submitted as $row ) {
		if ( ! is_array( $row ) || ! empty( $row['remove'] ) ) {
			continue;
		}
		$label = isset( $row['title'] ) && is_string( $row['title'] ) ? sanitize_text_field( $row['title'] ) : '';
		$raw   = isset( $row['url'] ) && is_string( $row['url'] ) ? trim( $row['url'] ) : '';
		$url   = rjh_menu_url( $raw );
		if ( '' === $label && '' === $raw ) {
			continue;
		}
		if ( '' === $label || '' === $url ) {
			wp_die( 'Each link needs a title and a valid web address or site path, such as /about/.', '', array( 'response' => 400, 'back_link' => true ) );
		}
		$links[] = array( $label, $url, (int) ( $row['position'] ?? 999 ) );
	}
	if ( count( $links ) > 50 ) {
		wp_die( 'The main menu is limited to 50 links.', '', array( 'response' => 400, 'back_link' => true ) );
	}
	if ( ! $links ) {
		wp_die( 'Keep at least one link in the main menu.', '', array( 'response' => 400, 'back_link' => true ) );
	}
	usort( $links, static fn( $a, $b ) => $a[2] <=> $b[2] );

	$post = array( 'post_type' => 'wp_navigation', 'post_status' => 'publish', 'post_content' => wp_slash( rjh_menu_blocks( $links ) ) );
	if ( $id ) {
		$post['ID'] = $id;
	} else {
		$post['post_title'] = 'RJ Hub Primary';
		$post['post_name']  = 'rj-hub-primary';
	}
	$saved = $id ? wp_update_post( $post, true ) : wp_insert_post( $post, true );
	if ( is_wp_error( $saved ) || ! $saved ) {
		wp_die( 'WordPress could not save the menu.', '', array( 'response' => 500, 'back_link' => true ) );
	}
	update_option( 'rj_hub_navigation_id', $saved );
	wp_safe_redirect( add_query_arg( 'rjh_saved', '1', admin_url( 'admin.php?page=rjh-primary-navigation' ) ) );
	exit;
}
