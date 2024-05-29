<?php
/**
 * Contest Reports' admin page template.
 *
 * @package Savage-Exports
 * @subpackage templates/admin-pages
 */

$contests = wc_get_products(
	array(
		'category' => 'contests',
		'return'   => 'ids',
	)
);
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
	<div class="notice">
		<ul>
			<li>Please must wait for an <b>email</b> message before downloading this report.</li>
			<li>This report will take a long time to process.</li> 
		</ul>
	</div>
	<div class="savage-row">
		<div class="savage-container">
			<span class="savage-label"><?php esc_html_e( 'From', 'savage-exports' ); ?></span>

			<label for="savage-start-date">
				<input type="date" id="savage-start-date">
			</label>
		</div>

		<div class="savage-container">
			<span class="savage-label"><?php esc_html_e( 'To', 'savage-exports' ); ?></span>

			<label for="savage-end-date">
				<input type="date" id="savage-end-date">
			</label>
		</div>

		<div class="savage-container">
			<span class="savage-label"><?php esc_html_e( 'Contest', 'savage-exports' ); ?></span>

			<label for="savage-contests">
				<select id="savage-contests">
					<?php if ( ! empty( $contests ) ) { ?>
						<option value="all" > <?php esc_html_e( 'All Contests', 'savage-exports' ); ?> </option>
					<?php } ?>

					<?php 
						foreach ( $contests as $contest_id ) { 
							if(wc_get_product( $contest_id )->get_title() == "Song Contest Entry" || wc_get_product( $contest_id )->get_title() == "Lyric Contest Entry"){
					?>
						<option value="<?php echo esc_attr( $contest_id ); ?>">
							<?php echo str_replace('Entry','',esc_html( wc_get_product( $contest_id )->get_title() )); ?>
						</option>

					<?php 
						}
							} 
					?>
				</select>
			</label>
		</div>

		<div class="savage-container">
			<button class="savage-btn" onclick="generate_contest_report( this )">
				<?php esc_html_e( 'Generate Report', 'savage-exports' ); ?>
			</button>
		</div>
	</div>

	<div id="savage-msg-box"></div>

	<div id="savage-csv-files-container"></div>

	<div class="savage-csv-files-load-more-container">
		<button id="savage-csv-files-load-more-button" onclick="fetch_csvs( this )">
			<?php esc_html_e( 'Load More', 'savage-exports' ); ?>
		</button>
	</div>
</div>
