<?php
/**
 * Shipping Addresses admin page template.
 *
 * @package Savage-Exports
 * @subpackage templates/admin-pages
 */

?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<div class="notice">
		<ul>
			<li>Please must wait for an <b>email</b> message before downloading this report.</li>
			<li>This report will take a long time to process.</li> 
		</ul>
	</div>


	<?php

		if ( ! get_option("savage_export_file_name") ) {
			add_option( "savage_export_file_name", '' );
			add_option( "savage_export_file_flag", 0 );
		}
		
	?>

	<input type="hidden" id="savage_export_file_name" value="<?php echo get_option("savage_export_file_name"); ?>">
	<input type="hidden" id="savage_export_file_flag" value="<?php echo get_option("savage_export_file_flag"); ?>">

	<div class="savage-row">
		<!-- <div class="savage-container">
			<button class="savage-btn" onclick="generate_address_export( this )">
				<?php //esc_html_e( 'Generate Export', 'savage-exports' ); ?>
			</button>
		</div> -->
		<?php 
			 $subscriptions_count = (new WP_Query(array(
				'post_type' => 'shop_subscription',
				'post_status' => array('wc-active', 'wc-pending-cancel'),
				'posts_per_page' => -1,
				'fields' => 'ids', // Retrieve only post IDs to reduce overhead
			)))->found_posts;
			$addressExport = substr(md5(time()), 0, 16);
			update_option("addressExportToken", $addressExport);
		?>
		<div class="savage-container">
			<input type="hidden" id="address_export" value="<?php echo $addressExport; ?>"> </input>
			<input type="hidden" id="subscriptions_count" value="<?php echo $subscriptions_count; ?>"> </input>
			<input type="hidden" id="author_email" value="<?php echo wp_get_current_user()->user_email; ?>"> </input>
			<button class="savage-btn" id="generate-export-button" onclick="generate_address_export_file( this )">
				<?php esc_html_e( 'Generate Export', 'savage-exports' ); ?>
			</button>
		</div>
	</div>

	<div id="savage-msg-box"></div>

	<div id="savage-csv-files-container"></div>

	<div class="savage-csv-files-load-more-container">
		<button id="savage-csv-files-load-more-button" onclick="fetch_csvs( this )">
			<?php echo esc_html__( 'Load More', 'savage-exports' ); ?>
		</button>
	</div>
</div>
