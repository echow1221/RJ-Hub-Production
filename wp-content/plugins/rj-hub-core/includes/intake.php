<?php

defined( 'ABSPATH' ) || exit;

function rjh_intake_fields(): array {
	return array(
		'learningTitle' => 'Learning and training',
		'learningText'  => 'See Offerings for workshop listings and Resources for materials you can use on your own.',
		'projectTitle'  => 'Projects and collaboration',
		'projectText'   => 'Project pages describe the work, list contributors, and link to their sources.',
		'otherTitle'    => 'Other questions',
		'otherText'     => 'See Contact for the current RJ Hub website.',
	);
}

function rjh_render_intake( array $attributes ): string {
	$text = array_merge( rjh_intake_fields(), $attributes );
	ob_start();
	?>
	<section class="rjh-intake" aria-label="Intake information">
		<p class="rjh-content-warning"><strong>Online requests are unavailable.</strong> Keep personal information and case details out of site searches.</p>
		<?php foreach ( array( 'learning', 'project', 'other' ) as $topic ) : ?>
			<details>
				<summary><?php echo esc_html( $text[ $topic . 'Title' ] ); ?></summary>
				<p><?php echo esc_html( $text[ $topic . 'Text' ] ); ?></p>
			</details>
		<?php endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}
