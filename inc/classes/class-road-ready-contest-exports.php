<?php
/**
 * Road Ready Contest Exports class.
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
 * Road Ready Contest Exports.
 */
class Road_Ready_Contest_Exports {

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
		$this->temp_dir              = trailingslashit( get_temp_dir() ) . 'contest-reports';

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
		add_action( 'savage_road_ready_contest_exports_cron_hook', array( $this, 'generate_road_ready_contest_exports' ), 10, 6 );
	}

	/**
	 * Generates Road Ready contest exports.
	 *
	 * @param int          $current_page current page number.
	 * @param string       $file_name    name of file.
	 * @param int          $total_pages  total number of pages.
	 * @param string       $initial_date initial Date.
	 * @param string       $final_date   final Date.
	 *
	 * @return void
	 */
	public function generate_road_ready_contest_exports( int $current_page, string $file_name, int $total_pages, string $initial_date, string $final_date ): void {
		global $wpdb;

		// Path of file.
		$file_path = trailingslashit( $this->temp_dir ) . $file_name;

		// Return once completed.
		if ( $current_page > $total_pages ) {

			// Send mail to user once the file is generated.
			$csv_author = get_option( 'road_ready_contest_exports_author_mail_id_' . $file_name );
            $this->upload_to_s3($file_path, $file_name);
            // Update message if upload fails.
            $subject = sprintf(
                // Translators: %s represents file name.
                __( 'File %s Generation Failed!', 'savage-exports' ),
                $file_name
            );

            $body = sprintf(
                // Translators: %s represents file name.
                __( 'File %s generation failed. Unable to upload on AWS.', 'savage-exports' ),
                $file_name
            );

			// Send mail.
			wp_mail( $csv_author, $subject, $body ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail.

			// Clean database.
			delete_option( 'road_ready_contest_exports_author_mail_id_' . $file_name );

			// Clean temp folder.
			unlink( $file_path ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink.

			return;
		}

		// Create a new CSV file & insert headers.
		if ( 1 === $current_page ) {
			$file = fopen( $file_path, 'w' ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fopen

			// Adds UTF-8 support for Microsoft Excel.
			fputs( $file, $bom = ( chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) ) ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputs

			// CSV file headers.
			$headers = array(
                'Artist Name',
                'Order-id',
                '* Entry Content',
                'Created By (User Id)',
                'Entry',
                'Date',
                'Date Updated',
                'Source Url',
                'Transaction Id',
                'Payment Amount',
                'Payment Date',
                'Payment Status',
                'Post Id',
                'User Agent',
                'User IP',
                // Extracted GF - Entry Content
                'song1_title',
                'song1_link',
                'song1_cowriter',
                'song2_title',
                'song2_link',
                'song2_cowriter',
                'song3_title',
                'song3_link',
                'song3_cowriter',
                'song4_title',
                'song4_link',
                'song4_cowriter',
                'song5_title',
                'song5_link',
                'song5_cowriter',
                // Woo Orders
                'Line Item (Product ID)',
                'Status',
                'First Name',
                'Last Name',
                'Email',
                'Phone',
			);

			// Write headers.
			fputcsv( $file, $headers ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv

			// Close file once work is over.
			fclose( $file ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}

		// Current offset.
		$offset = ( $current_page - 1 ) * $this->rows_written_per_page;


		// Prepare values.
		$prepared_values = array_merge(
			array( $initial_date . ' 00:00:00', $final_date . ' 23:59:59' ),
			array( $this->rows_written_per_page, $offset )
		);

		// Fire next cron.
		as_enqueue_async_action(
			'savage_road_ready_contest_exports_cron_hook',
			array(
				'current_page' => $current_page + 1, // Current Page Number.
				'file_name'    => $file_name,        // Name of file.
				'total_pages'  => $total_pages,      // Total Pages.
				'initial_date' => $initial_date,     // Initial Date.
				'final_date'   => $final_date,       // Final Date.
			)
		);
	}

	/**
	 * Inserts row in CSV file.
	 *
	 * @param string $file_path   path of CSV file.
	 * @param string $order_id    order id.
	 * @param string $product_id  product id.
	 * @param array  $contest     array containing contest id.
	 *
	 * @return void
	 */
	private function write_row( string $file_path, string $order_id, string $product_id, array $contest ): void {
		// Open file in append mode.
		$file = fopen( $file_path, 'a' ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fopen

		$order       = wc_get_order( $order_id ); // Order.
		$row         = array();                   // Initialize CSV Row.

		// Write row.
		fputcsv( $file, $row ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.

		// Close file once work is over.
		fclose( $file ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fclose
	}

    function upload_to_s3($file_path, $filename) {
        $bucket_name = get_option( 'aws_bucket_name' );
        $aws_access_key = get_option( 'aws_access_key' );
        $aws_secret_key = get_option( 'aws_private_key' );
        $region = get_option( 'aws_bucket_region' );

        // Prepare headers for authorization
        $date = gmdate('D, d M Y H:i:s T', time());
        $signature = base64_encode(hash_hmac('sha1', "PUT\n\n\n$date\n/$bucket_name/$filename", $aws_secret_key, true));
        $authorization = "AWS $aws_access_key:$signature";
        // Prepare headers for the PUT request
        $headers = array(
            'Date: ' . $date,
            'Authorization: ' . $authorization
        );

        // Prepare cURL options
        $options = array(
            CURLOPT_URL => "https://{$bucket_name}.s3.{$region}.amazonaws.com/{$filename}",
            CURLOPT_PUT => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_INFILE => fopen($file_path, 'r'),
            CURLOPT_INFILESIZE => filesize($file_path),
        );

        // Initialize cURL
        $ch = curl_init();
        curl_setopt_array($ch, $options);

        // Execute cURL request
        $response = curl_exec($ch);
        // Check for errors
        if ($response === false) {
            $error_message = curl_error($ch);
            // Handle error here
            echo "Error: $error_message";exit;
        }
        // Close cURL handle
        curl_close($ch);
    }
}
