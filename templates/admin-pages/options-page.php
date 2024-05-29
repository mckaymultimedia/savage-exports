<?php
/**
 * Option's page template.
 *
 * @package Savage-Exports
 * @subpackage templates/admin-pages
 */

?>

<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<form method="POST" action="options.php">
		<?php
			settings_fields( 'savage-option-page' );
			do_settings_sections( 'savage-option-page' );
			submit_button();
		?>
	</form>
</div>
