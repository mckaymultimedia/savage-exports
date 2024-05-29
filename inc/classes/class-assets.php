<?php
/**
 * Assets class.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

/**
 * Class Assets.
 */
class Assets {

	/**
	 * Initializes class.
	 */
	public function __construct() {
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
	}

	/**
	 * Enqueues scripts & styles in admin.
	 *
	 * @return void
	 */
	public function admin_enqueue_scripts(): void {

		// Return if it is not admin or user does not have permission.
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Add styles.
		wp_enqueue_style(
			'savage-exports-admin-menu',
			trailingslashit( SAVAGE_EXPORTS_URL ) . 'assets/css/savage-exports-admin-menu.css',
			array(),
			SAVAGE_EXPORTS_VERSION,
		);

		// Add scripts.
		wp_register_script(
			'savage-exports-admin-menu',
			trailingslashit( SAVAGE_EXPORTS_URL ) . 'assets/js/savage-exports-admin-menu.js',
			array( 'wp-i18n' ),
			SAVAGE_EXPORTS_VERSION,
			true
		);

		wp_localize_script(
			'savage-exports-admin-menu',
			'settings',
			array(
				'nonce'                      => wp_create_nonce( 'wp_rest' ),
				'get_shipping_addresses_csv' => get_rest_url( null, 'savage-exports/v1/csv/shipping-addresses/' ),
				'get_financial_exports_csv'  => get_rest_url( null, 'savage-exports/v1/csv/financial-reports/' ),
				'get_contest_exports_csv'    => get_rest_url( null, 'savage-exports/v1/csv/contest-reports/' ),
				'get_road_ready_contest_exports_csv'    => get_rest_url( null, 'savage-exports/v1/csv/road-ready-contest-reports/' ),
				'delete_csv'                 => get_rest_url( null, 'savage-exports/v1/csv/delete/' ),
				'get_download_link'          => get_rest_url( null, 'savage-exports/v1/csv/download-link/' ),
				'generate_address_exports'   => get_rest_url( null, 'savage-exports/v1/csv/generate-address-exports/' ),
				'generate_financial_exports' => get_rest_url( null, 'savage-exports/v1/csv/generate-financial-exports/' ),
				'generate_contest_exports'   => get_rest_url( null, 'savage-exports/v1/csv/generate-contest-exports/' ),
				'generate_road_ready_contest_exports'   => get_rest_url( null, 'savage-exports/v1/csv/generate-road-ready-contest-exports/' ),
				'download_nonce'             => wp_create_nonce( 'csv-download-nonce' ),
				'download_link_regen_time'   => SAVAGE_S3_DOWNLOAD_LINK_EXPIRY_TIME,
				'current_page'               => sanitize_text_field( $_GET['page'] ?? '' ), // phpcs:ignore -- no nonce verification.
			)
		);

		wp_enqueue_script( 'savage-exports-admin-menu' );
	}
}
