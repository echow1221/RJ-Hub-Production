<?php

defined( 'ABSPATH' ) || exit;

function rjh_map_bounds(): array {
	return array( 'west' => -124.65, 'east' => -113.95, 'south' => 32.45, 'north' => 42.1 );
}

function rjh_map_search_where( string $where, WP_Query $query ): string {
	$search = $query->get( 'rjh_map_search' );
	if ( ! is_string( $search ) || '' === $search ) {
		return $where;
	}
	global $wpdb;
	$like = '%' . $wpdb->esc_like( $search ) . '%';
	$where .= $wpdb->prepare(
		" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s OR {$wpdb->posts}.post_content LIKE %s
			OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} AS rjh_search_meta
				WHERE rjh_search_meta.post_id = {$wpdb->posts}.ID
				AND rjh_search_meta.meta_key IN ('rj_location', 'rj_event_date', 'rj_nation')
				AND rjh_search_meta.meta_value LIKE %s))",
		$like, $like, $like, $like
	);
	return $where;
}
add_filter( 'posts_where', 'rjh_map_search_where', 10, 2 );

function rjh_render_map(): string {
	$bounds = rjh_map_bounds();
	$search = rjh_request_text( 'rj-map-search' );
	$page   = max( 1, absint( rjh_request_text( 'rj-map-page' ) ) );
	$url    = rjh_search_page();
	$query  = new WP_Query(
		array(
			'post_type'      => 'rj_map_entry',
			'post_status'    => 'publish',
			'has_password'   => false,
			'posts_per_page' => 12,
			'paged'          => $page,
			'orderby'        => 'title',
			'order'          => 'ASC',
			'rjh_map_search' => $search,
			'meta_query'     => array(
				array( 'key' => 'rj_review_status', 'value' => 'verified' ),
				array( 'key' => 'rj_source_url', 'value' => '', 'compare' => '!=' ),
				array( 'key' => 'rj_location', 'value' => '', 'compare' => '!=' ),
				array( 'key' => 'rj_event_date', 'value' => '', 'compare' => '!=' ),
				array( 'key' => 'rj_latitude', 'value' => array( $bounds['south'], $bounds['north'] ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ),
				array( 'key' => 'rj_longitude', 'value' => array( $bounds['west'], $bounds['east'] ), 'compare' => 'BETWEEN', 'type' => 'DECIMAL(10,6)' ),
			),
		)
	);
	$entries = array();
	foreach ( $query->posts as $item ) {
		$meta = rjh_post_fields( 'rj_map_entry', $item->ID );
		if ( ! rjh_publication_error( 'rj_map_entry', $meta ) ) {
			$entries[] = array( $item, $meta );
		}
	}
	$list_id = wp_unique_id( 'rjh-map-locations-' );
	ob_start();
	?>
	<section class="rjh-map" aria-label="California historical locations">
		<p class="rjh-content-warning"><strong>Content notice.</strong> This material concerns historical violence against Native Americans in California. Source documents may include descriptions of violence.</p>
		<form class="rjh-filters" method="get" action="<?php echo esc_url( $url ); ?>" aria-label="Filter historical locations">
			<?php rjh_query_fields( $url ); ?>
			<label>Search historical locations
				<input type="search" name="rj-map-search" value="<?php echo esc_attr( $search ); ?>">
			</label>
			<button type="submit">Filter</button>
			<?php if ( '' !== $search ) : ?><a href="<?php echo esc_url( $url ); ?>">Clear search</a><?php endif; ?>
		</form>
		<figure class="rjh-map-chart">
			<div class="rjh-map-plot">
				<?php foreach ( $entries as $index => list( $item, $meta ) ) :
					$x = 100 * ( (float) $meta['rj_longitude'] - $bounds['west'] ) / ( $bounds['east'] - $bounds['west'] );
					$y = 100 * ( $bounds['north'] - (float) $meta['rj_latitude'] ) / ( $bounds['north'] - $bounds['south'] );
					?>
					<a class="rjh-map-marker" href="#rj-map-entry-<?php echo esc_attr( $item->ID ); ?>" style="--rjh-x:<?php echo esc_attr( number_format( $x, 3, '.', '' ) ); ?>%;--rjh-y:<?php echo esc_attr( number_format( $y, 3, '.', '' ) ); ?>%" aria-label="<?php echo esc_attr( sprintf( 'Location %d: %s', $index + 1, get_the_title( $item ) ) ); ?>"><?php echo esc_html( $index + 1 ); ?></a>
				<?php endforeach; ?>
			</div>
			<figcaption>Approximate locations on a schematic California outline. <a href="#<?php echo esc_attr( $list_id ); ?>">Read the location list</a> for context and sources.</figcaption>
		</figure>
		<h2 id="<?php echo esc_attr( $list_id ); ?>" tabindex="-1">Historical locations</h2>
		<?php if ( $entries ) : ?>
			<ol class="rjh-map-list">
				<?php foreach ( $entries as list( $item, $meta ) ) { rjh_render_map_entry( $item, $meta ); } ?>
			</ol>
			<?php rjh_pagination( $url, '' === $search ? array() : array( 'rj-map-search' => $search ), 'rj-map-page', $page, (int) $query->max_num_pages, 'Historical locations pages' ); ?>
		<?php else : ?>
			<p class="rjh-empty"><?php echo esc_html( '' !== $search || $page > 1 ? 'No verified locations match this view.' : 'No historical locations have been verified for this preview. Entries will appear when documented locations and sources are approved.' ); ?></p>
		<?php endif; ?>
	</section>
	<?php
	return ob_get_clean();
}

function rjh_render_map_entry( WP_Post $item, array $meta ): void {
	?>
	<li><article id="rj-map-entry-<?php echo esc_attr( $item->ID ); ?>" tabindex="-1">
		<h3><?php echo esc_html( get_the_title( $item ) ); ?></h3>
		<dl>
			<?php foreach ( array( 'rj_location' => 'Place', 'rj_event_date' => 'Date', 'rj_nation' => 'Nation or community' ) as $key => $label ) : ?>
				<?php if ( '' !== $meta[ $key ] ) : ?>
					<dt><?php echo esc_html( $label ); ?></dt>
					<dd><?php echo esc_html( $meta[ $key ] ); ?></dd>
				<?php endif; ?>
			<?php endforeach; ?>
		</dl>
		<?php if ( '' !== $meta['rj_content_warning'] ) : ?><p><strong>Content notice.</strong> <?php echo esc_html( $meta['rj_content_warning'] ); ?></p><?php endif; ?>
		<?php $summary = rjh_summary( $item, 120 ); ?>
		<?php if ( $summary ) : ?><p><?php echo esc_html( $summary ); ?></p><?php endif; ?>
		<?php echo rjh_reference_links( $meta ); // Already escaped. ?>
	</article></li>
	<?php
}
