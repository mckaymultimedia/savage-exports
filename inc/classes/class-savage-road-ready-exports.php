<?php
/**
 * Road Ready Exports Class.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

use Aws\Exception\AwsException;
use Aws\Exception\MultipartUploadException;
use Aws\S3\MultipartUploader;
use Aws\S3\S3Client;

/**
 * Exports Road Ready Contest.
 */
class Savage_Road_Ready_Exports {


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
		$this->temp_dir              = trailingslashit( get_temp_dir() ) . 'roady-ready-exports';

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
        add_action( 'admin_menu', array( $this, 'savage_road_ready_page' ) );
        add_action( 'road_ready_contest_exports_cron_hook', array( $this, 'export_contest_csv_gf_woocommerce' ), 10, 6 );

	}
    function savage_road_ready_page()
    {
        add_submenu_page(
            'savage_address_export',
            __( 'Road Ready Contest Exports', 'savage-exports' ),
            __( 'Road Ready Contest Exports', 'savage-exports' ),
            'manage_options',
            'savage_road_ready_contest_exports',
            array($this, 'export_contest_csv_gf_woocommerce_page'),
        );
    }
    function export_contest_csv_gf_woocommerce_page()
    {
        if (!current_user_can('manage_options')) {
            return;
        }
       // update_option('exported_files', []);
        if (isset($_POST['export_csv'])) {
            $filename = 'road_ready_contest_entries_' . date('Y-m-d-H-i-s') . '.csv';
            $start_date = sanitize_text_field($_POST['start_date']);
            $end_date = sanitize_text_field($_POST['end_date']);
            $email = wp_get_current_user()->user_email;
            //$this->export_contest_csv_gf_woocommerce( $start_date, $end_date, $filename, $email );
            wp_schedule_single_event( time() + 60, 'road_ready_contest_exports_cron_hook', array( $start_date, $end_date, $filename, $email ) );
            // Schedule export using WP-CLI command
            ?>
            <div class="updated">
                <p>Road Ready Contest export scheduled successfully.</p>
            </div>
                <?php
        }

        if (isset($_POST['delete_s3_file']) && isset($_POST['file_name'])) {
            $file_name = sanitize_text_field($_POST['file_name']);
            $deleted = $this->delete_s3_file($file_name);
            if ($deleted) {
                // Remove from exported files list
                $exported_files = $this->get_exported_files();
                foreach ($exported_files as $key => $file) {
                    if ($file['name'] === $file_name) {
                        unset($exported_files[$key]);
                        break;
                    }
                }
                update_option('exported_files', $exported_files);
                echo '<div class="updated"><p>S3 File Deleted Successfully.</p></div>';
            } else {
                echo '<div class="error"><p>Failed to delete S3 File.</p></div>';
            }
        }

        // List exported files
        $exported_files = $this->get_exported_files();
        ?>
        <div class="wrap">
            <h1>Export Contest CSV</h1>
            <form method="post" action="">
                <label for="start_date">Start Date:</label>
                <input type="date" id="start_date" name="start_date" required>
                <label for="end_date">End Date:</label>
                <input type="date" id="end_date" name="end_date" required>
                <button type="submit" name="export_csv" class="savage-btn">Generate Export</button>
            </form>
                <div class="wrap">
                    <div id="savage-csv-files-container">
                        <?php foreach ($exported_files as $file) : ?>
                            <div class="csv-parent-container">
                                <div class="csv-file-name-container">
                                    <p class="csv-file-name"><?php echo esc_html($file['name']); ?></p>
                                </div>
                                <div class="csv-buttons-container">
                                    <a class="savage-btn" href="<?php echo esc_url($file['url']); ?>" target="_blank">Download File</a>
                                    <!-- Deletion form -->
                                    <form method="post" action="">
                                        <input type="hidden" name="file_name" value="<?php echo esc_attr($file['name']); ?>">
                                        <input type="submit" name="delete_s3_file" value="Delete" class="csv-delete-btn" style="font-size: 15px;">
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
        </div>
        <?php
    }

    function upload_to_s3($file_path, $filename) {
        $bucket_name = get_option( 'aws_bucket_name' );
        $aws_access_key = get_option( 'aws_access_key' );
        $aws_secret_key = get_option( 'aws_private_key' );
        $region = get_option( 'aws_bucket_region' );

        $s3_client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key'    => $aws_access_key,
                'secret' => $aws_secret_key,
            ],
        ]);

        $s3_bucket = $bucket_name;
        $s3_key = 'road-ready-contest/' . $filename;

        $result = $s3_client->putObject([
            'Bucket' => $s3_bucket,
            'Key' => $s3_key,
            'Body' => fopen($file_path, 'r')
        ]);

        // Generate presigned URL
        $command = $s3_client->getCommand('GetObject', [
            'Bucket' => $s3_bucket,
            'Key' => $s3_key,
        ]);

        $request = $s3_client->createPresignedRequest($command, '+1 hour');
        return (string)$request->getUri();
    }

    // Export contest entries to CSV and return the file path
    function export_contest_csv_gf_woocommerce($start_date, $end_date, $filename, $email)
    {
        global $wpdb;
        try {
            // Prepare values.
            $prepared_values = array( $start_date . ' 00:00:00', $end_date . ' 23:59:59' );

            $query = $wpdb->prepare(
                "SELECT DISTINCT( order_items.order_id )
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_date_gmt BETWEEN %s AND %s
        AND order_items.order_item_type = 'line_item'
        AND order_item_name = 'Road Ready Contest Entry'",
                $prepared_values
            );

            // Fetch order ids.
            $orders = $wpdb->get_col( $query ); // phpcs:ignore -- Direct database call without caching detected.
            // Define upload directory path
            $upload_dir = wp_upload_dir();

            // Create 'exports' subdirectory if not exists
            $export_dir = trailingslashit($upload_dir['basedir']) . 'export-contest-csv/';
            if (!file_exists($export_dir)) {
                if (!wp_mkdir_p($export_dir)) {
                    // Log directory creation failure
                    $this->export_contest_csv_gf_woocommerce_log('Failed to create directory: ' . $export_dir);
                    return false;
                }
            }

            $csv_data = array();
            foreach ($orders as $order_id) {
                $order = wc_get_order( $order_id ); // Order.
                // Get associated Gravity Form entry
                $entry_id = get_post_meta($order->get_id(), '_GF_entry_id', true);
                if ($entry_id) {
                    $entry = \GFAPI::get_entry($entry_id);
                    if ($entry) {
                        // Extracting values from the entry array
                        $artist_name = $entry['38'];
                        $order_id = $order->get_id();
                        $created_by_user_id = $entry['created_by'];
                        $entry_id = $entry['id'];
                        $date_created = $entry['date_created'];
                        $date_updated = $entry['date_updated'];
                        $source_url = $entry['source_url'];
                        $transaction_id = $entry['transaction_id'];
                        $payment_amount = $entry['payment_amount'];
                        $payment_date = $entry['payment_date'];
                        $payment_status = $entry['payment_status'];
                        $post_id = $entry['post_id'];
                        $user_agent = $entry['user_agent'];
                        $user_ip = $entry['user_ip'];
                        // Initialize arrays to store song data
                        $songs = $entry['1000'];
                        $song_titles = array();
                        $song_links = array();
                        $song_cowriters = array();
                        // Loop through songs and extract data
                        for ($i = 0; $i < count($songs); $i++) {
                            $song_titles[] = $songs[$i]['1002'];
                            $song_links[] = $songs[$i]['1001'];
                            $song_cowriters[] = $songs[$i]['1003'];
                        }
                        // Add values to CSV data array
                        $csv_row = array(
                            $artist_name,
                            $order_id,
                            implode(',', $song_titles),
                            $created_by_user_id,
                            $entry_id,
                            $date_created,
                            $date_updated,
                            $source_url,
                            $transaction_id,
                            $payment_amount,
                            $payment_date,
                            $payment_status,
                            $post_id,
                            $user_agent,
                            $user_ip,
                            // Song data
                            isset($song_titles[0]) ? $song_titles[0] : '',
                            isset($song_links[0]) ? $song_links[0] : '',
                            isset($song_cowriters[0]) ? $song_cowriters[0] : '',
                            isset($song_titles[1]) ? $song_titles[1] : '',
                            isset($song_links[1]) ? $song_links[1] : '',
                            isset($song_cowriters[1]) ? $song_cowriters[1] : '',
                            isset($song_titles[2]) ? $song_titles[2] : '',
                            isset($song_links[2]) ? $song_links[2] : '',
                            isset($song_cowriters[2]) ? $song_cowriters[2] : '',
                            isset($song_titles[3]) ? $song_titles[3] : '',
                            isset($song_links[3]) ? $song_links[3] : '',
                            isset($song_cowriters[3]) ? $song_cowriters[3] : '',
                            isset($song_titles[4]) ? $song_titles[4] : '',
                            isset($song_links[4]) ? $song_links[4] : '',
                            isset($song_cowriters[4]) ? $song_cowriters[4] : '',
                            // Woo Orders
                            '',
                            $order->get_status(),
                            $order->get_billing_first_name(),
                            $order->get_billing_last_name(),
                            $order->get_billing_email(),
                            $order->get_billing_phone(),
                        );
                        $csv_data[] = $csv_row;
                    }
                }
            }
            // Create CSV file
            $file_path = $export_dir . $filename;
            $file = fopen($file_path, 'w');
            if (!$file) {
                // Log file creation failure
                $this->export_contest_csv_gf_woocommerce_log('Failed to create CSV file: ' . $file_path);
                return false;
            }
            fputcsv($file, array(
                'Artist Name',
                'Order id',
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
            ));
            foreach ($csv_data as $csv_row) {
                fputcsv($file, $csv_row);
            }
            fclose($file);
            $preSignedURL = $this->upload_to_s3($file_path, $filename);
            $subject    = sprintf(
            // Translators: %s represents file name.
                __( 'File %s Generated Successfully!', 'savage-exports' ),
                $filename
            );

            $body = sprintf(
            // Translators: %1$s contains file-name. %2$s contains url.
                __(
                    'The file %1$s has generated successfully.

You can download it from %2$s',
                    'savage-exports'
                ),
                $filename,
                esc_url( admin_url( 'admin.php?page=savage_road_ready_contest_exports' ) ),
            );
            wp_mail( $email , $subject, $body);
            $exported_files = get_option('exported_files', array());
            $exported_files[] = array(
                'name' => $filename,
                'url' => $preSignedURL,
            );

            update_option('exported_files', $exported_files);
            // Clean temp folder.
            unlink( $file_path );
        } catch (\Exception $e) {
            error_log('Exception caught: ' . $e->getMessage());
            return false;
        }
    }


// Get exported files list
    function get_exported_files()
    {
        return get_option('exported_files', array());
    }

// Log messages to a file
    function export_contest_csv_gf_woocommerce_log($message)
    {
        $log_file = plugin_dir_path(__FILE__) . 'export_logs.log';
        $timestamp = date("Y-m-d H:i:s");
        $log_message = "[" . $timestamp . "] " . $message . "\n";
        file_put_contents($log_file, $log_message, FILE_APPEND);
    }

    /**
     * Delete file from S3.
     *
     * @param string $file_name Filename.
     *
     * @return bool True on success, false on failure.
     */
    private function delete_s3_file($file_name) {
        $aws_access_key = get_option('aws_access_key');
        $aws_secret_key = get_option('aws_private_key');
        $bucket_name = get_option('aws_bucket_name');
        $region = get_option('aws_bucket_region');

        $s3_client = new S3Client([
            'region' => $region,
            'version' => 'latest',
            'credentials' => [
                'key'    => $aws_access_key,
                'secret' => $aws_secret_key,
            ],
        ]);

        try {
            // Delete object with 'road-ready-contest' prefix
            $s3_client->deleteObject([
                'Bucket' => $bucket_name,
                'Key' => 'road-ready-contest/' . $file_name,
            ]);
            return true;
        } catch (AwsException $e) {
            error_log('Exception caught: ' . $e->getMessage());
            return false;
        }
    }
}