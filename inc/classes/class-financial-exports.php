<?php
/**
 * Financial Address Exports class.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;

/**
 * Financial Exports.
 */
class Financial_Exports {

	/**
	 * Number of rows to be written to a CSV file in one go.
	 *
	 * @var int
	 */
	private int $rows_written_per_page;

	/**
	 * Path of temporary directory.
	 *
	 * @var string
	 */
	private string $temp_dir;

	/**
	 * Initialize class.
	 */
	public function __construct() {
		$this->rows_written_per_page = 100;
		$this->temp_dir              = trailingslashit( get_temp_dir() ) . 'financial-reports';

		// Initialize filesystem.
		$file_system = initialize_filesystem();

		// Create directory.
		$success = maybe_create_directory( $file_system, $this->temp_dir );

		// Log warning if directory is not created successfully.
		if ( ! $success ) {
			new Log_Warning(
				sprintf(
					// Translators: %s represents temporary directory's path.
					esc_html__( 'Reports cannot be created. "%s" directory creation failed.', 'savage-exports' ),
					$this->temp_dir
				)
			);

			return;
		}

		// Add cron hook.
		add_action( 'savage_financial_export_cron_hook', array( $this, 'generate_financial_exports' ), 10, 5 );
	}

	/**
	 * Generates financial exports.
	 *
	 * @param int    $current_page current page number.
	 * @param string $file_name    name of file.
	 * @param int    $total_pages  total number of pages.
	 * @param string $initial_date initial Date.
	 * @param string $final_date   final Date.
	 *
	 * @return void
	 */
	public function generate_financial_exports( int $current_page, string $file_name, int $total_pages, string $initial_date, string $final_date ): void {

		// Path of file.
		$file_path = trailingslashit( $this->temp_dir ) . $file_name;

		// Return once completed.
		if ( $current_page > $total_pages ) {

			// Send mail to user once the file is generated.
			$csv_author = get_option( 'financial_exports_author_mail_id_' . $file_name );
			$subject    = sprintf(
				// Translators: %s is file name.
				__( 'File %s Generated Successfully!', 'savage-exports' ),
				$file_name
			);

			$body = sprintf(
				// Translators: %1$s contains file-name. %2$s contains url.
				__(
					'The file %1$s has generated successfully.

You can download it from %2$s',
					'savage-exports'
				),
				$file_name,
				esc_url( admin_url( 'admin.php?page=savage_financial_exports' ) ),
			);

			// Write file to S3 before sending mail.
			$s3 = savage_get_s3(); // Create S3 Client.

			// Return if credentials are not set.
			if ( false === $s3 || empty( SAVAGE_EXPORTS_BUCKET ) ) {

				// Update message if upload fails.
				$subject = sprintf(
					// Translators: %s is file name.
					__( 'File %s Generation Failed!', 'savage-exports' ),
					$file_name
				);

				$body = sprintf(
					// Translators: %s is file name.
					__( 'File %s generation failed. Unable to get AWS connection.', 'savage-exports' ),
					$file_name
				);

			} else {

				// Create uploader.
				$uploader = new MultipartUploader(
					$s3,
					$file_path,
					array(
						'bucket' => SAVAGE_EXPORTS_BUCKET,
						'key'    => 'financial-reports/' . $file_name,
					)
				);

				try {
					// Upload file to S3 bucket.
					$uploader->upload();
				} catch ( MultipartUploadException $e ) {

					// Update message if upload fails.
					$subject = sprintf(
						// Translators: %s is file name.
						__( 'File %s Generation Failed!', 'savage-exports' ),
						$file_name
					);

					$body = sprintf(
						// Translators: %s is file name.
						__( 'File %s generation failed. Unable to upload on AWS.', 'savage-exports' ),
						$file_name
					);

				}
			}

			// Send mail.
			wp_mail( $csv_author, $subject, $body );

			// Clean database.
			delete_option( 'financial_exports_author_mail_id_' . $file_name );

			// Clean temp folder.
			unlink( $file_path );

			return;
		}

		// Create a new CSV file & insert headers.
		if ( 1 === $current_page ) {
			$file = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

			// Adds UTF-8 support for Microsoft Excel.
			fputs( $file, $bom = ( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputs

			// CSV file headers.
			$headers = array(
				__( 'Order ID', 'savage-exports' ),
				__( 'Product Name', 'savage-exports' ),
				__( 'Product ID', 'savage-exports' ),
				__( 'Product Variation ID', 'savage-exports' ),
				__( 'Product Duration', 'savage-exports' ),
				__( 'Gross Amount', 'savage-exports' ),
				__( 'Net Amount', 'savage-exports' ),
				__( 'Transaction Status', 'savage-exports' ),
				__( 'Product Gross Value', 'savage-exports' ),
				__( 'Quantity', 'savage-exports' ),
				__( 'Tax', 'savage-exports' ),
				__( 'Discount', 'savage-exports' ),
				__( 'Shipping Cost', 'savage-exports' ),
				__( 'Stripe Fee', 'savage-exports' ),
				__( 'Shipping City', 'savage-exports' ),
				__( 'Shipping State', 'savage-exports' ),
				__( 'Shipping Zip Code', 'savage-exports' ),
				__( 'Shipping Address', 'savage-exports' ),
				__( 'Billing City', 'savage-exports' ),
				__( 'Billing State', 'savage-exports' ),
				__( 'Billing Zip Code', 'savage-exports' ),
				__( 'Billing Address', 'savage-exports' ),
				__( 'Transaction Date', 'savage-exports' ),
				__( 'Order Date', 'savage-exports' ),
				__( 'UTM Source', 'savage-exports' ),
				__( 'UTM Medium', 'savage-exports' ),
				__( 'UTM Campaign', 'savage-exports' ),
				__( 'UTM Term', 'savage-exports' ),
				__( 'UTM Content', 'savage-exports' ),
			);

			// Write headers.
			fputcsv( $file, $headers );

			// Close file once work is over.
			fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}

		// Open file in append mode.
		$file = fopen( $file_path, 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Looping orders & inserting in CSV file.
		foreach (
			wc_get_orders( // Fetch orders.
				array(
					'limit'        => $this->rows_written_per_page,
					'type'         => 'shop_order',
					'date_created' => $initial_date . '...' . $final_date,
					'status'       => array( 'completed', 'pending', 'processing' ),
					'return'       => 'ids',
					'paged'        => $current_page,
				)
			) as $order_id
		) {
			$order                = wc_get_order( $order_id );
			$total_net_value      = 0;
			$total_items_discount = 0;
			$total_stripe_fees    = $order->get_meta( '_stripe_fee' );
			$shipping_items       = $order->get_items( 'shipping' );

			foreach ( $order->get_items() as $item ) {
				$total_net_value      += ( $order->get_item_subtotal( $item ) + $order->get_item_tax( $item ) );
				$total_items_discount += ( floatval( $item->get_subtotal() ) - floatval( $item->get_total() ) );
			}


			// Product variation IDs
			$v_ids = [];
			foreach ($order->get_items() as $item) {
				$product = $item->get_product();
				if ($product && $product->get_id()) {
					if($item->get_variation_id()){
						array_push($v_ids,$item->get_variation_id());
					}
				}
			}
			$variation_ids = (!empty($v_ids))?implode(', ',$v_ids):' ';
			$variation_ids = ($variation_ids == 0)?' ':$variation_ids;

			// UTM Record
			global $wpdb;
			$table_name = $wpdb->prefix . 'pys_stat_order';
			$utms = $wpdb->get_results("SELECT * FROM $table_name Where `order_id` = $order_id");
			$utm_source_id = $utms[0]->utm_source_id;
			$utm_medium_id = $utms[0]->utm_medium_id;
			$utm_campaing_id =$utms[0]->utm_campaing_id;
			$utm_term_id =$utms[0]->utm_term_id;
			$utm_content_id =$utms[0]->utm_content_id;

			// Order discount. To be distributed among all items.
			$discount_on_order = $order->get_total_discount() - $total_items_discount;

			// Looping through each item of order.
			foreach ( $order->get_items() as $item ) {
				$row   = array( '\'' . strval( $order_id ) );          // Order ID.
				$row[] = $item->get_name();                            // Product Name.
				$row[] = $item['product_id'];                          // Product ID.


				$row[] = $variation_ids;                          // Product Variation ID.

				// Get the product object
				$product = wc_get_product($item['product_id']);

				// Get the subscription duration from the product meta data
				$row[] = $product->get_meta('_subscription_period');
			

				$row[] = strval( $order->get_total() );                // Gross Amount.
				$row[] = strval( $order->get_item_subtotal( $item ) ); // Net Amount.
				$row[] = $order->get_status();                         // Transaction status.

				// Product's Gross Total i.e. ( ( Total Gross Value / Total Net Value ) * ( Product's Net Value + Product's Tax ) ).
				if ( floatval( 0 ) === floatval( $total_net_value ) ) {
					$row[] = '0.00';
				} else {
					$row[] = round( ( $order->get_total() / $total_net_value ) * ( $order->get_item_subtotal( $item ) + $order->get_item_tax( $item ) ), 2 );
				}

				$row[] = $item->get_quantity(); // Product's Quantity.

				// Tax per item.
				$row[] = $order->get_item_tax( $item );

				// Keeping default discount to 0.
				$row[] = '0.00';

				// Adding per item discount.
				if ( floatval( $item->get_subtotal() ) !== floatval( $item->get_total() ) ) {
					$row[ count( $row ) - 1 ] = round( floatval( $item->get_subtotal() ) - floatval( $item->get_total() ), 2 );
				}

				// Adding order discount.
				if ( $discount_on_order > 0 ) {
					// Per item order discount i.e ( ( Product's Net Value / Total Net Value ) * Total Order Discount ).
					if ( floatval( 0 ) === floatval( $total_net_value ) ) {
						$row[ count( $row ) - 1 ] = '0.00';
					} else {
						$row[ count( $row ) - 1 ] += round( ( $order->get_item_subtotal( $item ) / $total_net_value ) * $discount_on_order, 2 );
					}
				}

				// Add shipping charges.
				if ( ! empty( $shipping_items[ $item->get_id() + 1 ] ) ) {
					$row[] = round( floatval( $shipping_items[ $item->get_id() + 1 ]->get_total() ), 2 );
				} else {
					$row[] = '0.00';
				}

				// Add stripe fee.
				if ( ! empty( $total_stripe_fees ) ) {
					// Per item stripe fee i.e ( ( Product's Net Value / Total Net Value ) * Total Stripe Fees ).
					if ( floatval( 0 ) === floatval( $total_net_value ) ) {
						$row[] = '0.00';
					} else {
						$row[] = round( ( $order->get_item_subtotal( $item ) / $total_net_value ) * $total_stripe_fees, 2 );
					}
				} else {
					$row[] = '0.00';
				}

				// Add shipping city.
				$shipping_city = $order->get_shipping_city();
				if ( ! empty( $shipping_city ) ) {
					$row[] = $shipping_city;
				} else {
					$row[] = ' ';
				}

				// Add shipping state.
				$shipping_state = $order->get_shipping_state();
				if ( ! empty( $shipping_state ) ) {
					$row[] = $shipping_state;
				} else {
					$row[] = ' ';
				}

				// Add shipping zip code.
				$shipping_zip_code = $order->get_shipping_postcode();
				if ( ! empty( $shipping_zip_code ) ) {
					$row[] = $shipping_zip_code;
				} else {
					$row[] = ' ';
				}

				// Add shipping address.
				$shipping_address = trim( $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() );
				if ( ! empty( $shipping_address ) ) {
					$row[] = $shipping_address;
				} else {
					$row[] = ' ';
				}

				// Add billing city.
				$billing_city = $order->get_billing_city();
				if ( ! empty( $billing_city ) ) {
					$row[] = $billing_city;
				} else {
					$row[] = ' ';
				}

				// Add billing state.
				$billing_state = $order->get_billing_state();
				if ( ! empty( $billing_state ) ) {
					$row[] = $billing_state;
				} else {
					$row[] = ' ';
				}

				// Add billing zip code.
				$billing_zip_code = $order->get_billing_postcode();
				if ( ! empty( $billing_zip_code ) ) {
					$row[] = $billing_zip_code;
				} else {
					$row[] = ' ';
				}

				// Add billing address.
				$billing_address = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );
				if ( ! empty( $billing_address ) ) {
					$row[] = $billing_address;
				} else {
					$row[] = ' ';
				}

				// Transaction Date.
				$order_transaction_date = $order->get_date_paid();
				if ( $order_transaction_date instanceof \WC_DateTime ) {
					$row[] = $order_transaction_date->date_i18n();
				} else {
					$row[] = ' ';
				}

				// Order Date.
				$order_creation_date = $order->get_date_created();
				if ( $order_creation_date instanceof \WC_DateTime ) {
					$row[] = $order_creation_date->date_i18n();
				} else {
					$row[] = ' ';
				}

				// Add UTM Source.
				if ( ! empty( $utm_source_id ) ) {
					$table_utm_source = $wpdb->prefix . 'pys_stat_utm_source';
					$utm_source = $wpdb->get_results("SELECT * FROM $table_utm_source Where `id` = $utm_source_id");
					$row[] = $utm_source[0]->item_value;
				} else {
					$row[] = ' ';
				}

				// Add UTM Medium.
				if ( ! empty( $utm_medium_id ) ) {
					$table_utm_medium = $wpdb->prefix . 'pys_stat_utm_medium';
					$utm_medium = $wpdb->get_results("SELECT * FROM $table_utm_medium Where `id` = $utm_medium_id");
					$row[] = $utm_medium[0]->item_value;
				} else {
					$row[] = ' ';
				}

				// Add UTM campaign.
				if ( ! empty( $utm_campaing_id ) ) {
					$table_utm_campaing = $wpdb->prefix . 'pys_stat_utm_campaing';
					$utm_campaing = $wpdb->get_results("SELECT * FROM $table_utm_campaing Where `id` = $utm_campaing_id");
					$row[] = $utm_campaing[0]->item_value;
				} else {
					$row[] = ' ';
				}

				// Add UTM term.
				if ( ! empty( $utm_term_id ) ) {
					$table_utm_term = $wpdb->prefix . 'pys_stat_utm_term';
					$utm_term = $wpdb->get_results("SELECT * FROM $table_utm_term Where `id` = $utm_term_id");
					$row[] = $utm_term[0]->item_value;
				} else {
					$row[] = ' ';
				}

				// Add UTM content.
				if ( ! empty( $utm_content_id ) ) {
					$table_utm_content = $wpdb->prefix . 'pys_stat_utm_content';
					$utm_content = $wpdb->get_results("SELECT * FROM $table_utm_content Where `id` = $utm_content_id");
					$row[] = $utm_content[0]->item_value;
				} else {
					$row[] = ' ';
				}

				// Write row.
				fputcsv( $file, $row );

			}
		}

		// Close file once work is over.
		fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose

		// Fire next cron.
		as_enqueue_async_action(
			'savage_financial_export_cron_hook',
			array(
				'current_page' => $current_page + 1, // Current Page Number.
				'file_name'    => $file_name,        // Name of file.
				'total_pages'  => $total_pages,      // Total Pages.
				'initial_date' => $initial_date,     // Initial Date.
				'final_date'   => $final_date,       // Final Date.
			)
		);
	}
}