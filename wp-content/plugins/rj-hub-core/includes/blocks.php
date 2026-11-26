<?php

defined( 'ABSPATH' ) || exit;

function rjh_collections(): array {
	return array(
		'blog'      => array( 'Blog', 'post', 'category', 'Articles will appear here after the source archive and bylines are reviewed.' ),
		'projects'  => array( 'Projects', 'rj_project', 'rj_project_topic', 'Project summaries and source documents will appear here after editorial review.' ),
		'resources' => array( 'Resources', 'rj_resource', 'rj_resource_type', 'Reviewed readings, tools, and community resources will appear here.' ),
		'partners'  => array( 'Community partners', 'rj_partner', '', 'Current partnerships are under review.' ),
		'offerings' => array( 'Offerings', 'rj_offering', '', 'Current offerings are under review.' ),
	);
}

function rjh_register_blocks(): void {
	$base = plugin_dir_url( __DIR__ ) . 'assets/';
	wp_register_style( 'rjh-blocks', $base . 'blocks.css', array(), (string) filemtime( __DIR__ . '/../assets/blocks.css' ) );
	wp_register_script(
		'rjh-editor', $base . 'editor.js',
		array( 'wp-blocks', 'wp-block-editor', 'wp-components', 'wp-core-data', 'wp-data', 'wp-edit-post', 'wp-editor', 'wp-element', 'wp-plugins' ),
		(string) filemtime( __DIR__ . '/../assets/editor.js' ), true
	);

	$editor_data = array( 'collections' => array(), 'fields' => array(), 'intake' => rjh_intake_fields() );
	foreach ( rjh_collections() as $key => list( $label ) ) {
		$editor_data['collections'][ $key ] = $label;
	}
	foreach ( array_merge( array( 'post' ), array_keys( rjh_types() ) ) as $type ) {
		$editor_data['fields'][ $type ] = rjh_fields( $type );
	}
	wp_add_inline_script( 'rjh-editor', 'window.rjhEditor = ' . wp_json_encode( $editor_data ) . ';', 'before' );

	register_block_type(
		'rj-hub/archive',
		array(
			'api_version'     => 3,
			'attributes'      => array( 'collection' => array( 'type' => 'string', 'default' => 'blog' ) ),
			'render_callback' => 'rjh_render_archive',
			'style'           => 'rjh-blocks',
			'editor_script'   => 'rjh-editor',
		)
	);
	register_block_type(
		'rj-hub/editorial-feed',
		array(
			'api_version'     => 3,
			'attributes'      => array(
				'collection' => array( 'type' => 'string', 'default' => 'blog', 'enum' => array( 'blog', 'projects' ) ),
				'heading'    => array( 'type' => 'string', 'default' => '' ),
				'limit'      => array( 'type' => 'integer', 'default' => 3 ),
			),
			'render_callback' => 'rjh_render_editorial_feed',
			'style'           => 'rjh-blocks',
			'editor_script'   => 'rjh-editor',
		)
	);
	$intake_attributes = array();
	foreach ( rjh_intake_fields() as $key => $value ) {
		$intake_attributes[ $key ] = array( 'type' => 'string', 'default' => $value );
	}
	foreach ( array( 'map', 'intake', 'byline' ) as $block ) {
		register_block_type(
			'rj-hub/' . $block,
			array(
				'api_version'     => 3,
				'render_callback' => 'rjh_render_' . $block,
				'style'           => 'rjh-blocks',
				'editor_script'   => 'rjh-editor',
				'uses_context'    => array( 'postId' ),
				'attributes'      => 'intake' === $block ? $intake_attributes : array(),
			)
		);
	}
}

function rjh_request_text( string $name ): string {
	return isset( $_GET[ $name ] ) && is_string( $_GET[ $name ] )
		? sanitize_text_field( wp_unslash( $_GET[ $name ] ) )
		: '';
}

function rjh_search_page(): string {
	$page = get_queried_object_id();
	return is_singular() && $page ? get_permalink( $page ) : home_url( '/' );
}

function rjh_query_fields( string $url ): void {
	$query = wp_parse_url( $url, PHP_URL_QUERY );
	if ( ! $query ) {
		return;
	}
	parse_str( $query, $fields );
	foreach ( $fields as $key => $value ) {
		if ( is_string( $value ) ) {
			printf( '<input type="hidden" name="%s" value="%s">', esc_attr( $key ), esc_attr( $value ) );
		}
	}
}

function rjh_term_select( string $taxonomy, string $field, string $label, string $selected ): void {
	$terms = get_terms( array( 'taxonomy' => $taxonomy ) );
	if ( ! $terms || is_wp_error( $terms ) ) {
		return;
	}
	?>
	<label><?php echo esc_html( $label ); ?>
		<select name="<?php echo esc_attr( $field ); ?>">
			<option value=""><?php echo esc_html( 'All ' . strtolower( $label ) ); ?></option>
			<?php foreach ( $terms as $term ) : ?>
				<option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>><?php echo esc_html( $term->name ); ?></option>
			<?php endforeach; ?>
		</select>
	</label>
	<?php
}

function rjh_render_archive( array $attributes ): string {
	$collection = $attributes['collection'] ?? 'blog';
	$config     = rjh_collections();
	if ( ! isset( $config[ $collection ] ) ) {
		return '';
	}
	list( $label, $post_type, $taxonomy, $empty ) = $config[ $collection ];
	$url   = rjh_search_page();
	$state = array(
		'rj-search'   => rjh_request_text( 'rj-search' ),
		'rj-category' => $taxonomy ? sanitize_title( rjh_request_text( 'rj-category' ) ) : '',
		'rj-tag'      => 'blog' === $collection ? sanitize_title( rjh_request_text( 'rj-tag' ) ) : '',
	);
	$page  = max( 1, absint( rjh_request_text( 'rj-page' ) ) );
	$args  = array( 'post_type' => $post_type, 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => 12, 'paged' => $page, 'ignore_sticky_posts' => true );
	if ( '' !== $state['rj-search'] ) {
		$args['s'] = $state['rj-search'];
	}
	if ( $state['rj-category'] ) {
		$args['tax_query'][] = array( 'taxonomy' => $taxonomy, 'field' => 'slug', 'terms' => $state['rj-category'] );
	}
	if ( $state['rj-tag'] ) {
		$args['tax_query'][] = array( 'taxonomy' => 'post_tag', 'field' => 'slug', 'terms' => $state['rj-tag'] );
	}
	if ( in_array( $collection, array( 'partners', 'offerings' ), true ) ) {
		$args['meta_query'][] = array( 'key' => 'rj_review_status', 'value' => 'verified' );
		$args['meta_query'][] = array( 'key' => 'rj_source_url', 'value' => '', 'compare' => '!=' );
	}

	$active = array_filter( $state, static fn( $value ) => '' !== $value );
	$query  = new WP_Query( $args );
	ob_start();
	?>
	<section class="rjh-index" aria-label="<?php echo esc_attr( $label ); ?>">
		<form class="rjh-filters" aria-label="<?php echo esc_attr( 'Filter ' . strtolower( $label ) ); ?>" method="get" action="<?php echo esc_url( $url ); ?>">
			<?php rjh_query_fields( $url ); ?>
			<label>Search <?php echo esc_html( strtolower( $label ) ); ?>
				<input type="search" name="rj-search" value="<?php echo esc_attr( $state['rj-search'] ); ?>">
			</label>
			<?php if ( $taxonomy ) { rjh_term_select( $taxonomy, 'rj-category', 'rj_project_topic' === $taxonomy ? 'Topics' : 'Categories', $state['rj-category'] ); } ?>
			<?php if ( 'blog' === $collection ) { rjh_term_select( 'post_tag', 'rj-tag', 'Tags', $state['rj-tag'] ); } ?>
			<button type="submit">Filter</button>
			<?php if ( $active ) : ?><a href="<?php echo esc_url( $url ); ?>">Clear filters</a><?php endif; ?>
		</form>
		<?php if ( $query->posts ) : ?>
			<?php if ( $active ) : ?><p><?php echo esc_html( sprintf( '%s %s', number_format_i18n( $query->found_posts ), 1 === (int) $query->found_posts ? 'result' : 'results' ) ); ?></p><?php endif; ?>
			<ul class="rjh-results">
				<?php foreach ( $query->posts as $item ) { rjh_render_card( $item, $collection, $taxonomy, $url, $active ); } ?>
			</ul>
			<?php rjh_pagination( $url, $active, 'rj-page', $page, (int) $query->max_num_pages, $label . ' pages' ); ?>
		<?php else : ?>
			<p class="rjh-empty"><?php echo esc_html( $active || $page > 1 ? 'No results match this view.' : $empty ); ?></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

function rjh_render_editorial_feed( array $attributes ): string {
	$collection = $attributes['collection'];
	list( , $post_type, $taxonomy ) = rjh_collections()[ $collection ];
	$posts = new WP_Query(
		array(
			'post_type'           => $post_type,
			'post_status'         => 'publish',
			'has_password'        => false,
			'ignore_sticky_posts' => true,
			'posts_per_page'      => max( 1, min( 6, $attributes['limit'] ) ),
			'no_found_rows'       => true,
		)
	);
	if ( ! $posts->posts ) {
		return '';
	}
	$page = get_page_by_path( $collection );
	$url  = $page && 'publish' === $page->post_status && '' === $page->post_password ? get_permalink( $page ) : '';
	$heading = $attributes['heading'] ?: ( 'blog' === $collection ? 'News and perspectives' : 'Featured research and projects' );
	ob_start();
	?>
	<section class="rj-editorial-feed" aria-label="<?php echo esc_attr( $heading ); ?>">
		<h2><?php echo esc_html( $heading ); ?></h2>
		<ul class="rjh-results">
			<?php foreach ( $posts->posts as $item ) { rjh_render_card( $item, $collection, $taxonomy, $url, array(), 'h3' ); } ?>
		</ul>
		<?php if ( $url ) : ?>
			<p class="rj-editorial-feed-more"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( 'blog' === $collection ? 'View all news' : 'View all projects' ); ?></a></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

function rjh_render_card( WP_Post $item, string $collection, string $taxonomy, string $url, array $active, string $heading = 'h2' ): void {
	$image = get_the_post_thumbnail( $item, 'medium_large', array( 'loading' => 'lazy', 'decoding' => 'async' ) );
	$terms = $taxonomy ? get_the_terms( $item, $taxonomy ) : false;
	?>
	<li><article class="rjh-card">
		<?php if ( $image ) : ?>
			<figure class="rjh-card-image"><?php echo $image; // Core generated image markup. ?></figure>
		<?php elseif ( in_array( $collection, array( 'blog', 'projects' ), true ) ) : ?>
			<div class="rjh-card-image rjh-card-pattern" aria-hidden="true"></div>
		<?php endif; ?>
		<div class="rjh-card-body">
			<?php if ( $terms && ! is_wp_error( $terms ) ) : ?>
				<ul class="rjh-card-terms" aria-label="Categories">
					<?php foreach ( $terms as $term ) : ?>
						<li><?php if ( $url ) : ?><a href="<?php echo esc_url( add_query_arg( array_merge( $active, array( 'rj-category' => $term->slug ) ), $url ) ); ?>"><?php echo esc_html( $term->name ); ?></a><?php else : echo esc_html( $term->name ); endif; ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<<?php echo esc_html( $heading ); ?>><a href="<?php echo esc_url( get_permalink( $item ) ); ?>"><?php echo esc_html( get_the_title( $item ) ); ?></a></<?php echo esc_html( $heading ); ?>>
			<?php if ( in_array( $collection, array( 'blog', 'projects' ), true ) ) : ?>
				<p class="rjh-entry-meta"><time datetime="<?php echo esc_attr( get_the_date( DATE_W3C, $item ) ); ?>"><?php echo esc_html( get_the_date( '', $item ) ); ?></time>
					<?php $byline = rjh_byline( $item ); ?>
					<?php if ( '' !== $byline ) : ?><span aria-hidden="true"> · </span><?php echo esc_html( $byline ); ?><?php endif; ?></p>
			<?php endif; ?>
			<?php $summary = rjh_summary( $item, 30 ); ?>
			<?php if ( $summary ) : ?><p><?php echo esc_html( $summary ); ?></p><?php endif; ?>
		</div>
	</article></li>
	<?php
}

function rjh_summary( WP_Post $item, int $words ): string {
	$summary = $item->post_excerpt ?: strip_shortcodes( $item->post_content );
	return wp_trim_words( wp_strip_all_tags( strip_tags( $summary, '<p><li><h2><h3>' ) ), $words );
}

function rjh_byline( WP_Post $item ): string {
	if ( metadata_exists( 'post', $item->ID, 'rj_source_byline' ) ) {
		$source = get_post_meta( $item->ID, 'rj_source_byline', true );
		if ( is_string( $source ) && ( '' !== $source || metadata_exists( 'post', $item->ID, '_rj_source_record_key' ) ) ) {
			return $source;
		}
	}
	return (string) get_the_author_meta( 'display_name', $item->post_author );
}

function rjh_render_byline( array $attributes, string $content, WP_Block $block ): string {
	$item = get_post( $block->context['postId'] ?? get_the_ID() );
	$byline = $item ? rjh_byline( $item ) : '';
	return '' !== $byline ? '<span class="rjh-byline">' . esc_html( $byline ) . '</span>' : '';
}

function rjh_pagination( string $url, array $active, string $parameter, int $page, int $pages, string $label ): void {
	if ( $pages <= 1 ) {
		return;
	}
	?>
	<nav class="rjh-pagination" aria-label="<?php echo esc_attr( $label ); ?>">
		<?php if ( $page > 1 ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array_merge( $active, array( $parameter => $page - 1 ) ), $url ) ); ?>" rel="prev">Previous page</a>
		<?php endif; ?>
		<span><?php echo esc_html( sprintf( 'Page %s of %s', number_format_i18n( $page ), number_format_i18n( $pages ) ) ); ?></span>
		<?php if ( $page < $pages ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array_merge( $active, array( $parameter => $page + 1 ) ), $url ) ); ?>" rel="next">Next page</a>
		<?php endif; ?>
	</nav>
	<?php
}

function rjh_reference_links( array $meta ): string {
	$links = array();
	foreach ( array( 'rj_source_url' => 'View source', 'rj_pdf_url' => 'Read the PDF' ) as $key => $title ) {
		if ( ! empty( $meta[ $key ] ) ) {
			$links[] = '<li><a href="' . esc_url( $meta[ $key ] ) . '">' . esc_html( $title ) . '</a></li>';
		}
	}
	return $links ? '<ul class="rjh-source-links">' . implode( '', $links ) . '</ul>' : '';
}

require_once __DIR__ . '/map.php';
require_once __DIR__ . '/intake.php';
