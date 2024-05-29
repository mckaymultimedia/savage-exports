<?php
/**
 * Shipping Address Exports Class.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use \WC_Customer;
use \WC_Subscription;

/**
 * Exports Shipping addresses.
 */
class Savage_Addresses_Exports {

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
		$this->temp_dir              = trailingslashit( get_temp_dir() ) . 'shipping-addresses';

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

		// Creates and saves address file on S3.
		add_action( 'savage_address_export_cron_hook', array( $this, 'generate_address_exports' ), 10, 3 );

		// Initializes export after checking date.
		add_action( 'savage_start_address_export_cron_hook', array( $this, 'export_shipping_addresses' ) );

		// Schedule cron if not scheduled.
		if ( ! wp_next_scheduled( 'savage_start_address_export_cron_hook' ) ) {
			wp_schedule_event( time(), 'daily', 'savage_start_address_export_cron_hook' );
		}
	}

	/**
	 * Fetches user IDs list.
	 *
	 * @param integer $offset      pagination number.
	 * @param bool    $get_length  keep true if total number of users needed.
	 *
	 * @return array|integer array containing user IDs if $get_length is false else number of users.
	 */
	private function get_users_list( int $offset = 0, bool $get_length = false ) {

		if ( $get_length ) {
			return count_users()['total_users'];
		}

		// Fetch & return user IDs.
		return get_users(
			array(
				'number' => $this->rows_written_per_page,
				'offset' => $offset,
				'fields' => 'ID',
				'orderby' => 'ID',
				'order' => 'ASC',
			)
		);
	}

	/**
	 * Gets all the required data for csv row.
	 *
	 * @param integer         $user_id               customer id.
	 * @param string          $user_membership_title membership title.
	 * @param WC_Subscription $subscription          subscription object.
	 *
	 * @return array array containing csv row's data.
	 *
	 * @throws \Exception If customer cannot be read/found and $data is set.
	 */
	private function get_csv_row_data( int $user_id, string $user_membership_title, WC_Subscription $subscription ): array {

		// Initialize a new row and add User ID.
		$row_data = array( '\'' . strval( $user_id ) );

		$user = new WC_Customer( $user_id ); // Fetch user data.

        // First Name.
        if ( ! empty( $user->get_shipping_first_name() ) ) {
            $row_data[] = $user->get_shipping_first_name();
        } else if (! empty( $user->get_billing_first_name() )) {
            $row_data[] = $user->get_billing_first_name();
        } else if (! empty( $user->get_first_name() )) {
            $row_data[] = $user->get_first_name();
        }
        else{
            $row_data[] = ' ';
        }

        // Last Name.
        if ( ! empty( $user->get_shipping_last_name() ) ) {
            $row_data[] = $user->get_shipping_last_name();
        } else if (! empty( $user->get_billing_last_name() )) {
            $row_data[] = $user->get_billing_last_name();
        } else if (! empty( $user->get_last_name() )) {
            $row_data[] = $user->get_last_name();
        }
        else{
            $row_data[] = ' ';
        }

        // User Email.
        $row_data[] = (!empty( $user->get_email()))?$user->get_email():' ';

        // Subscription ID.
        $row_data[] = (!empty( $subscription->get_id()))?'\'' . strval( $subscription->get_id()):' ';

        // Subscription Name.
        $row_data[] = (!empty( $user_membership_title))?$user_membership_title:' ';

		
		try{
			// Product Variation ID
			$variation_id = '';
			if ($subscription->get_id()) {
				// Get the product ID
				$related_orders_ids_array = $subscription->get_related_orders();
				if(!empty($related_orders_ids_array)){
					foreach ( $related_orders_ids_array as $order_id ) {
						$order = wc_get_order( $order_id );
						if($order){
							$items = $order->get_items();
							foreach ($items as $item) {
								$variation_id = $item->get_variation_id();
							}
						}
						// if(!empty($order->get_items())){
						// 	foreach ($order->get_items() as $item) {
						// 		$variation_id = $item->get_variation_id();
						// 	}
						// }
					}
				}
			}  
			$row_data[] = (!empty($variation_id))?$variation_id:' '; 
		} catch (\Exception $ex) {
			$row_data[] = $ex->getMessage();
		}
        
		

		// Subscription Status.
		$subscription_status = $subscription->get_status();
        $row_data[] = ( ! empty( $subscription_status ) )?$subscription_status:' ';

		// Subscription Renewal Date.
        $row_data[] = ( $subscription->get_time( 'next_payment' ) > 0 )?date_i18n( 'Y/m/d', $subscription->get_time( 'next_payment', 'site' ) ):' ';

        // Add shipping details.
        if ( ! empty( $user->get_shipping_address() ) ) {

            $row_data[] = $user->get_shipping_address_1();
            $row_data[] = $user->get_shipping_address_2();
            $row_data[] = $user->get_shipping_city();
            $row_data[] = $user->get_shipping_state();
            $row_data[] = $user->get_shipping_postcode();
            $row_data[] = $user->get_shipping_country();

        } elseif ( ! empty( $user->get_billing_address() ) ) { // Add billing details if shipping details are not present.

            $row_data[] = $user->get_billing_address_1();
            $row_data[] = $user->get_billing_address_2();
            $row_data[] = $user->get_billing_city();
            $row_data[] = $user->get_billing_state();
            $row_data[] = $user->get_billing_postcode();
            $row_data[] = $user->get_shipping_country();

        } else {
            $row_data = array_merge( $row_data, array( ' ', ' ', ' ', ' ', ' ', ' ' ) );
        }

		return $row_data;
	}

	/**
	 * Exports Shipping addresses.
	 *
	 * @param bool $date_check check today's date before firing event.
	 *
	 * @return void
	 */
	public function export_shipping_addresses( bool $date_check = true ): void {

		if ( $date_check ) {

			// Fetch today's date.
			$date = gmdate( 'd' );

			// Return if today is not 1st.
			if ( '01' !== $date ) {
				return;
			}
		}

		// CSV file Name.
		$file_name = gmdate( 'Y-m' ) . '.csv';

		// Fetching total number of pages.
		$total_users = $this->get_users_list( 0, true );

		update_option("savage_export_file_name", $file_name);
		// Create a new cron job.

		
		if ( ! get_option("savage_export_offset") ) {
			add_option( "savage_export_offset", 0 );
		}else{
			update_option("savage_export_offset", 0);
		}

		as_enqueue_async_action(
			'savage_address_export_cron_hook',
			array(
				'file_name'   => $file_name,    // Name of file.
				'offset'      => 0,             // Current offset.
				'total_users' => $total_users,  // Total Users.
			)
		);
	}

	/**
	 * Generates shipping addresses.
	 *
	 * @param string $file_name   name of file.
	 * @param int    $offset      current offset.
	 * @param int    $total_users total number of users.
	 *
	 * @return void
	 *
	 * @throws \Exception If customer cannot be read/found and $data is set.
	 */
	public function generate_address_exports( string $file_name, int $offset, int $total_users ): void {

		if ( ! get_option("savage_export_offset") ) {
			add_option( "savage_export_offset", 0 );
		}

		$offset = get_option("savage_export_offset");


		// Path of file.
		$file_path = trailingslashit( $this->temp_dir ) . $file_name;

		// Return when done.
		if ( $offset > $total_users ) {

			// Send mail to user once the file is manually generated.
			$csv_author = get_option( 'address_exports_author_mail_id' );
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
				esc_url( admin_url( 'admin.php?page=savage_address_export' ) ),
			);

			// Write file to S3.
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

				// Send mail if `csv_author` is not empty.
				if ( ! empty( $csv_author ) ) {
					wp_mail( $csv_author, $subject, $body ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail - Warning for bulk email.
				}

				return;
			}

			// Create uploader.
			$uploader = new MultipartUploader(
				$s3,
				$file_path,
				array(
					'bucket' => SAVAGE_EXPORTS_BUCKET,
					'key'    => 'shipping-addresses/' . $file_name,
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

			// Clean temp folder.
			unlink( $file_path );

			// Send mail if `csv_author` is not empty.
			if ( ! empty( $csv_author ) ) {
				wp_mail( $csv_author, $subject, $body ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail - Warning for bulk email.
			}

			update_option("savage_export_file_flag", 1);

			return;
		}

		// Create a new file and write headers if its first page.
		if ( $offset == 0 ) {
			// Open CSV file in write mode.
			$file = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

			// Adds UTF-8 support for Microsoft Excel.
			fputs( $file, $bom = ( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputs

			// CSV file headers.
			$headers = array(
				__( 'User ID', 'savage-exports' ),
				__( 'First Name', 'savage-exports' ),
				__( 'Last Name', 'savage-exports' ),
				__( 'Email', 'savage-exports' ),
				__( 'Subscription ID', 'savage-exports' ),
				__( 'Subscription Product Name', 'savage-exports' ),
				__( 'Product Variation ID', 'savage-exports' ),
				__( 'Subscription Status', 'savage-exports' ),
				__( 'Subscription Renewal Date', 'savage-exports' ),
				__( 'Address 1', 'savage-exports' ),
				__( 'Address 2', 'savage-exports' ),
				__( 'City', 'savage-exports' ),
				__( 'State', 'savage-exports' ),
				__( 'Zip', 'savage-exports' ),
				__( 'Country', 'savage-exports' ),
			);

			// Write headers.
			fputcsv( $file, $headers ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv

			fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}

		// Open file in append mode.
		$file = fopen( $file_path, 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fopen

		// Fetching users.
		$users = $this->get_users_list( $offset );

		// Write rows.
		foreach ( $users as $user_id ) {
                // Check if user has any active or pending-cancel subscriptions.

                if (
                    !wcs_user_has_subscription($user_id, '', array('active', 'pending-cancel'))
                ){
                    continue;
                }
                // Fetch user's subscriptions.
                $subscriptions = wcs_get_users_subscriptions( $user_id );
                foreach ( $subscriptions as $subscription ) {

                    if (
                        'active' !== $subscription->get_status() &&
                        'pending-cancel' !== $subscription->get_status()
                    ) {
                        continue;
                    }
                    // Fetch memberships(Subscription Products) for getting product title.
                    $memberships           = wc_memberships()->get_user_memberships_instance()->get_user_memberships( $user_id );
                    $user_membership_title = array();

                    // Update $user_membership_title array if membership is active.
                    if ( ! empty( $memberships ) ) {
                        foreach ( $memberships as $membership ) {

                            // Get membership plan.
                            $plan = $membership->get_plan();

                            if ( $plan && wc_memberships_is_user_active_member( $user_id, $plan ) ) {

                                $user_membership_title[] = $plan->name;
                            }
                        }
                    }

                    if ( 0 === count( $user_membership_title ) ) {
                        $user_membership_title = ''; // Make $user_membership_title to null string if no membership found.
                    } else {
                        // Convert $user_membership_title to string by joining items with `and`.
                        $user_membership_title = join( ' and ', $user_membership_title );
                    }

                    $row_data = $this->get_csv_row_data( $user_id, $user_membership_title, $subscription ); // Get data.

                    // Insert row.
                    fputcsv( $file, $row_data ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv

                    break;
                }

		}

		

		fclose( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose


		
		update_option("savage_export_offset", $offset + $this->rows_written_per_page);
		sleep(3);
		
		// Configure next cron.
		as_enqueue_async_action(
			'savage_address_export_cron_hook',
			array(
				'file_name'   => $file_name,                             // Name of file.
				'offset'      => $offset + $this->rows_written_per_page, // Current offset.
				'total_users' => $total_users,                           // Total Pages.
			)
		);
	}
}