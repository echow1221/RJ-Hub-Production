<?php

defined( 'ABSPATH' ) || exit;

function rjh_intake_fields(): array {
	return array(
		'learningTitle' => 'Learning and training',
		'learningText'  => 'A future request pathway could explain confirmed offerings, eligibility, and the staff member responsible for responding.',
		'projectTitle'  => 'Projects and collaboration',
		'projectText'   => 'A future request pathway could describe how to ask about research, campus or community collaboration, and ways to take part.',
		'otherTitle'    => 'Other questions',
		'otherText'     => 'Contact options will be posted through the Hub after ownership and contact details are confirmed.',
	);
}

function rjh_render_intake( array $attributes ): string {
	$text = array_merge( rjh_intake_fields(), $attributes );
	ob_start();
	?>
	<section class="rjh-intake" aria-label="Intake preview">
		<p class="rjh-content-warning"><strong>Requests are closed.</strong> This preview accepts no responses. Approved contact options will be listed when intake opens.</p>
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
