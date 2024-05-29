<?php
/**
 * Exports Admin Menu.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

/**
 * Exports' admin menu class.
 */
class Exports_Admin_Menu {

	/**
	 * Initializes class.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'savage_exports_page' ) );
		add_action( 'admin_init', array( $this, 'options_page_init' ) );
	}

	/**
	 * Adds admin menu pages.
	 *
	 * @return void
	 */
	public function savage_exports_page(): void {

		add_menu_page(
			__( 'Savage Exports', 'savage-exports' ),
			__( 'Savage Exports', 'savage-exports' ),
			'manage_options',
			'savage_address_export',
			'',
			'dashicons-media-spreadsheet',
			20
		);

		add_submenu_page(
			'savage_address_export',
			__( 'Shipping Addresses', 'savage-exports' ),
			__( 'Addresses Exports', 'savage-exports' ),
			'manage_options',
			'savage_address_export',
			array( $this, 'savage_address_admin_page_html' ),
		);

		add_submenu_page(
			'savage_address_export',
			__( 'Financial Reports', 'savage-exports' ),
			__( 'Financial Exports', 'savage-exports' ),
			'manage_options',
			'savage_financial_exports',
			array( $this, 'savage_financial_reports_admin_page_html' ),
		);

		add_submenu_page(
			'savage_address_export',
			__( 'Contest Reports', 'savage-exports' ),
			__( 'Contest Exports', 'savage-exports' ),
			'manage_options',
			'savage_contest_exports',
			array( $this, 'savage_contest_reports_admin_page_html' ),
		);
		add_submenu_page(
			'savage_address_export',
			__( 'Savage Settings', 'savage-exports' ),
			__( 'Settings', 'savage-exports' ),
			'manage_options',
			'savage_options_page',
			array( $this, 'savage_options_page_admin_page_html' ),
		);

		add_submenu_page(
			'savage_address_export',
			__( 'Savage Logs', 'savage-exports' ),
			__( 'Logs', 'savage-exports' ),
			'manage_options',
			'savage_logs_page',
			array( $this, 'savage_log_page_admin_page_html' ),
		);
	}

	/**
	 * `Shipping Addresses` menu page UI.
	 *
	 * @return void
	 */
	public function savage_address_admin_page_html(): void {
		savage_exports_render_template( 'admin-pages/shipping-addresses' );
	}

	/**
	 * `Financial Reports` menu page UI.
	 *
	 * @return void
	 */
	public function savage_financial_reports_admin_page_html(): void {
		savage_exports_render_template( 'admin-pages/financial-reports' );
	}

	/**
	 * `Contest Reports` menu page UI.
	 *
	 * @return void
	 */
	public function savage_contest_reports_admin_page_html(): void {
		savage_exports_render_template( 'admin-pages/contest-reports' );
	}

	/**
	 * Options Page UI.
	 *
	 * @return void
	 */
	public function savage_options_page_admin_page_html(): void {
		savage_exports_render_template( 'admin-pages/options-page' );
	}

	/**
	 * Options Page UI.
	 *
	 * @return void
	 */
	public function savage_log_page_admin_page_html(): void {
		savage_exports_render_template( 'admin-pages/export-logs' );
	}


	/**
	 * Initialize options page.
	 *
	 * @return void
	 */
	public function options_page_init(): void {
		register_setting(
			'savage-option-page',
			'aws_access_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'savage-option-page',
			'aws_private_key',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'savage-option-page',
			'aws_bucket_name',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		register_setting(
			'savage-option-page',
			'aws_bucket_region',
			array(
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			)
		);

		add_settings_section(
			'savage_options_page_aws_section',
			__( 'AWS', 'savage-exports' ),
			'',
			'savage-option-page'
		);

		add_settings_field(
			'aws_access_key_field',
			__( 'Access Key', 'savage-exports' ),
			array( $this, 'aws_access_key_markup' ),
			'savage-option-page',
			'savage_options_page_aws_section'
		);

		add_settings_field(
			'aws_private_key_field',
			__( 'Private/Secret Key', 'savage-exports' ),
			array( $this, 'aws_private_key_markup' ),
			'savage-option-page',
			'savage_options_page_aws_section'
		);

		add_settings_field(
			'aws_bucket_name_field',
			__( 'Bucket Name', 'savage-exports' ),
			array( $this, 'aws_bucket_name_markup' ),
			'savage-option-page',
			'savage_options_page_aws_section'
		);

		add_settings_field(
			'aws_bucket_region_field',
			__( 'Bucket Region', 'savage-exports' ),
			array( $this, 'aws_bucket_region_markup' ),
			'savage-option-page',
			'savage_options_page_aws_section'
		);
	}

	/**
	 * Displays UI for adding AWS S3's Access Key.
	 *
	 * @return void
	 */
	public function aws_access_key_markup(): void {
		$aws_access_key = get_option( 'aws_access_key' );
		?>
			<label>
				<input type="text"
						name="aws_access_key"
						class="regular-text"
						value="<?php echo esc_attr( $aws_access_key ); ?>"
						placeholder="<?php esc_attr_e( 'Enter S3 Access Key', 'savage-exports' ); ?>"
				/>
			</label>
		<?php
	}

	/**
	 * Displays UI for adding AWS S3's Private Key.
	 *
	 * @return void
	 */
	public function aws_private_key_markup(): void {
		$aws_private_key = get_option( 'aws_private_key' );
		?>
			<label>
				<input type="text"
						name="aws_private_key"
						class="regular-text"
						value="<?php echo esc_attr( $aws_private_key ); ?>"
						placeholder="<?php esc_attr_e( 'Enter S3 Private Key', 'savage-exports' ); ?>"
				/>
			</label>
		<?php
	}

	/**
	 * Displays UI for adding AWS's Bucket Name.
	 *
	 * @return void
	 */
	public function aws_bucket_name_markup(): void {
		$aws_bucket_name = get_option( 'aws_bucket_name' );
		?>
			<label>
				<input type="text"
						name="aws_bucket_name"
						class="regular-text"
						value="<?php echo esc_attr( $aws_bucket_name ); ?>"
						placeholder="<?php esc_attr_e( 'Enter Bucket Name', 'savage-exports' ); ?>"
				/>
			</label>
		<?php
	}

	/**
	 * Displays UI for adding AWS's Bucket Region.
	 *
	 * @return void
	 */
	public function aws_bucket_region_markup(): void {
		$aws_bucket_region = get_option( 'aws_bucket_region' );
		?>
			<label>
				<input type="text"
						name="aws_bucket_region"
						class="regular-text"
						value="<?php echo esc_attr( $aws_bucket_region ); ?>"
						placeholder="<?php esc_attr_e( 'Enter Bucket Region', 'savage-exports' ); ?>"
				/>
			</label>
		<?php
	}
}
