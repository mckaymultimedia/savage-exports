<?php
/**
 * Contest Exports class.
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
 * Contest Exports.
 */
class Contest_Exports {

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

		// Log warning if no contest in "Contests" category.
		add_action( 'init', array( $this, 'maybe_log_contest_warning' ) );

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
		add_action( 'savage_contest_exports_cron_hook', array( $this, 'generate_contest_exports' ), 10, 6 );
	}

	/**
	 * Logs Warning message if there are no contests.
	 *
	 * @return void
	 */
	public function maybe_log_contest_warning(): void {

		$contests = wc_get_products(
			array(
				'category' => 'contests',
				'return'   => 'ids',
			)
		);

		if ( empty( $contests ) ) {
			new Log_Warning(
				esc_html__( '"No Contest Found."', 'savage-exports' ),
				esc_html__( 'Contests can be added by adding "Contests" category to ', 'savage-exports' ),
				esc_url( admin_url( 'edit.php?post_type=product' ) ),
				esc_html__( 'products.', 'savage-exports' ),
			);
		}

	}

	/**
	 * Generates contest exports.
	 *
	 * @param int          $current_page current page number.
	 * @param string       $file_name    name of file.
	 * @param int          $total_pages  total number of pages.
	 * @param string       $initial_date initial Date.
	 * @param string       $final_date   final Date.
	 * @param string|array $contest      Contest Name.
	 *
	 * @return void
	 */
	public function generate_contest_exports( int $current_page, string $file_name, int $total_pages, string $initial_date, string $final_date, $contest ): void {
		global $wpdb;

		// Path of file.
		$file_path = trailingslashit( $this->temp_dir ) . $file_name;

		// Convert $contest to array if it is a string.
		if ( is_string( $contest ) ) {
			$contest = array( $contest );
		}

		// Return once completed.
		if ( $current_page > $total_pages ) {

			// Send mail to user once the file is generated.
			$csv_author = get_option( 'contest_exports_author_mail_id_' . $file_name );
			$subject    = sprintf(
				// Translators: %s represents file name.
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
				esc_url( admin_url( 'admin.php?page=savage_contest_exports' ) ),
			);

			// Write file to S3 before sending mail.
			$s3 = savage_get_s3(); // Create S3 Client.

			// Return if credentials are not set.
			if ( false === $s3 || empty( SAVAGE_EXPORTS_BUCKET ) ) {

				// Update message if upload fails.
				$subject = sprintf(
					// Translators: %s represents file name.
					__( 'File %s Generation Failed!', 'savage-exports' ),
					$file_name
				);

				$body = sprintf(
					// Translators: %s represents file name.
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
						'key'    => 'contest-reports/' . $file_name,
					)
				);

				try {
					// Upload file to S3 bucket.
					$uploader->upload();
				} catch ( MultipartUploadException $e ) {

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

				}
			}

			// Send mail.
			wp_mail( $csv_author, $subject, $body ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.wp_mail_wp_mail.

			// Clean database.
			delete_option( 'contest_exports_author_mail_id_' . $file_name );

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
				__( 'Product ID', 'savage-exports' ),
				__( 'Order ID', 'savage-exports' ),
				__( 'Submission Date', 'savage-exports' ),
				__( 'First Name', 'savage-exports' ),
				__( 'Last Name', 'savage-exports' ),
				__( 'Email', 'savage-exports' ),
				__( 'Phone', 'savage-exports' ),
				__( 'Payment Status', 'savage-exports' ),
				__( 'Co-Writers', 'savage-exports' ),
				__( '(Second Entry) Co-Writers', 'savage-exports' ),
				__( '(Third Entry) Co-Writers', 'savage-exports' ),
				__( '(Fourth Entry) Co-Writers', 'savage-exports' ),
				__( '(Fifth Entry) Co-Writers', 'savage-exports' ),
				__( 'Song Title', 'savage-exports' ),
				__( '(First Entry) Categories', 'savage-exports' ),
				__( '(Second Entry) Song Title', 'savage-exports' ),
				__( '(Second Entry) Categories', 'savage-exports' ),
				__( '(Third Entry) Song Title', 'savage-exports' ),
				__( '(Third Entry) Categories', 'savage-exports' ),
				__( '(Fourth Entry) Song Title', 'savage-exports' ),
				__( '(Fourth Entry) Categories', 'savage-exports' ),
				__( '(Fifth Entry) Song Title', 'savage-exports' ),
				__( '(Fifth Entry) Categories', 'savage-exports' ),
			);

			if ( count( $contest ) > 1 ) {

				$headers[] = __( 'Song URL', 'savage-exports' );
				$headers[] = __( '(Second Entry) Song URL', 'savage-exports' );
				$headers[] = __( '(Third Entry) Song URL', 'savage-exports' );
				$headers[] = __( '(Fourth Entry) Song URL', 'savage-exports' );
				$headers[] = __( '(Fifth Entry) Song URL', 'savage-exports' );
				$headers[] = __( 'Song Lyrics', 'savage-exports' );
				$headers[] = __( '(Second Entry) Song Lyrics', 'savage-exports' );
				$headers[] = __( '(Third Entry) Song Lyrics', 'savage-exports' );
				$headers[] = __( '(Fourth Entry) Song Lyrics', 'savage-exports' );
				$headers[] = __( '(Fifth Entry) Song Lyrics', 'savage-exports' );

			} else {

				if ( false !== strpos( strtolower( wc_get_product( $contest[0] )->get_title() ), 'song contest' ) ) {
					$headers[] = __( 'Song URL', 'savage-exports' );
					$headers[] = __( '(Second Entry) Song URL', 'savage-exports' );
					$headers[] = __( '(Third Entry) Song URL', 'savage-exports' );
					$headers[] = __( '(Fourth Entry) Song URL', 'savage-exports' );
					$headers[] = __( '(Fifth Entry) Song URL', 'savage-exports' );
				} else {
					$headers[] = __( 'Song Lyrics', 'savage-exports' );
					$headers[] = __( '(Second Entry) Song Lyrics', 'savage-exports' );
					$headers[] = __( '(Third Entry) Song Lyrics', 'savage-exports' );
					$headers[] = __( '(Fourth Entry) Song Lyrics', 'savage-exports' );
					$headers[] = __( '(Fifth Entry) Song Lyrics', 'savage-exports' );
				}
			}

			$headers[] = __( 'Entry Count', 'savage-exports' );

			// Write headers.
			fputcsv( $file, $headers ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv

			// Close file once work is over.
			fclose( $file ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fclose
		}

		// Current offset.
		$offset = ( $current_page - 1 ) * $this->rows_written_per_page;

		// Generate placeholders.
		$contest_id_placeholders = implode( ', ', array_fill( 0, count( $contest ), '%d' ) );

		// Prepare values.
		$prepared_values = array_merge(
			array( $initial_date . ' 00:00:00', $final_date . ' 23:59:59' ),
			$contest,
			array( $this->rows_written_per_page, $offset )
		);

		// Prepare query.
		$query = $wpdb->prepare(
			"SELECT DISTINCT( order_items.order_id )
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_date_gmt BETWEEN %s AND %s
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta.meta_value IN ( $contest_id_placeholders ) LIMIT %d OFFSET %d", // phpcs:ignore -- $contest_id_placeholders contains placeholders.
			$prepared_values
		);

		// Fetch order ids.
		$orders = $wpdb->get_col( $query ); // phpcs:ignore -- Direct database call without caching detected.

		// Looping orders & inserting in CSV file.
		foreach ( $orders as $order_id ) {
			$order = wc_get_order( $order_id ); // Order.

			// Fetch products.
			$products = $order->get_items();

			// Loop through each product & write in CSV.
			if ( count( $contest ) > 1 ) {

				foreach ( $products as $product ) {

					if ( false !== strpos( strtolower( $product->get_name() ), 'song contest' ) ) {
						$product_id = $product->get_product_id();
						$this->write_row( $file_path, $order_id, $product_id, $contest );
					}

					if ( false !== strpos( strtolower( $product->get_name() ), 'lyric contest' ) ) {
						$product_id = $product->get_product_id();
						$this->write_row( $file_path, $order_id, $product_id, $contest );
					}
				}
			} elseif ( false !== strpos( strtolower( wc_get_product( $contest[0] )->get_title() ), 'song contest' ) ) {

				foreach ( $products as $product ) {

					if ( false !== strpos( strtolower( $product->get_name() ), 'song contest' ) ) {
						$product_id = $product->get_product_id();
						$this->write_row( $file_path, $order_id, $product_id, $contest );
					}
				}
			} else {

				foreach ( $products as $product ) {

					if ( false !== strpos( strtolower( $product->get_name() ), 'lyric contest' ) ) {
						$product_id = $product->get_product_id();
						$this->write_row( $file_path, $order_id, $product_id, $contest );
					}
				}
			}
		}

		// Fire next cron.
		as_enqueue_async_action(
			'savage_contest_exports_cron_hook',
			array(
				'current_page' => $current_page + 1, // Current Page Number.
				'file_name'    => $file_name,        // Name of file.
				'total_pages'  => $total_pages,      // Total Pages.
				'initial_date' => $initial_date,     // Initial Date.
				'final_date'   => $final_date,       // Final Date.
				'contest'      => $contest,          // Contest.
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
		$entry_count = 0;                         // Initialize entry count.
		$categories = $this->get_categories( $order_id );

		// Product ID.
		$row[] = '\'' . strval( $product_id );

		// Order ID.
		$row[] = '\'' . strval( $order_id );

		// Submission Date.
		$submission_date = $order->get_date_created();
		if ( $submission_date instanceof \WC_DateTime ) {
			$row[] = $submission_date->date_i18n();
		} else {
			$row[] = '';
		}

		// First Name.
		if ( ! empty( $order->get_billing_first_name() ) ) {
			$row[] = $order->get_billing_first_name();
		} elseif ( ! empty( $order->get_shipping_first_name() ) ) {
			$row[] = $order->get_shipping_first_name();
		} else {
			$row[] = '';
		}

		// Last Name.
		if ( ! empty( $order->get_billing_last_name() ) ) {
			$row[] = $order->get_billing_last_name();
		} elseif ( ! empty( $order->get_shipping_last_name() ) ) {
			$row[] = $order->get_shipping_last_name();
		} else {
			$row[] = '';
		}

		// Email.
		$billing_email = $order->get_billing_email();
		if ( ! empty( $billing_email ) ) {
			$row[] = $billing_email;
		} else {
			$row[] = '';
		}

		// Phone.
		if ( ! empty( $order->get_billing_phone() ) ) {
			$row[] = $order->get_billing_phone();
		} elseif ( ! empty( $order->get_shipping_phone() ) ) {
			$row[] = $order->get_shipping_phone();
		} else {
			$row[] = '';
		}

		// Payment Status.
		$payment_status = $order->get_status();
		if ( ! empty( $payment_status ) ) {
			$row[] = $payment_status;
		} else {
			$row[] = '';
		}

		// Co-Writers.
		$co_writers = $order->get_meta( '_billing_co-writers' );
		if ( ! empty( $co_writers ) ) {
			$row[] = html_entity_decode( $co_writers );
		} else {
			$row[] = '';
		}

		// (Second Entry) Co-Writers.
		$second_entry_co_writers = $order->get_meta( '_billing_entry_2_co-writers' );
		if ( ! empty( $second_entry_co_writers ) ) {
			$row[] = html_entity_decode( $second_entry_co_writers );
		} else {
			$row[] = '';
		}

		// (Third Entry) Co-Writers.
		$third_entry_co_writers = $order->get_meta( '_billing_entry_3_co-writers' );
		if ( ! empty( $third_entry_co_writers ) ) {
			$row[] = html_entity_decode( $third_entry_co_writers );
		} else {
			$row[] = '';
		}

		// (Fourth Entry) Co-Writers.
		$fourth_entry_co_writers = $order->get_meta( '_billing_entry_4_co-writers' );
		if ( ! empty( $fourth_entry_co_writers ) ) {
			$row[] = html_entity_decode( $fourth_entry_co_writers );
		} else {
			$row[] = '';
		}

		// (Fifth Entry) Co-Writers.
		$fifth_entry_co_writers = $order->get_meta( '_billing_entry_5_co-writers' );
		if ( ! empty( $fifth_entry_co_writers ) ) {
			$row[] = html_entity_decode( $fifth_entry_co_writers );
		} else {
			$row[] = '';
		}

		// Song Title.
		$song_title = $order->get_meta( '_billing_song_title' );
		if ( ! empty( $song_title ) ) {
			$row[] = html_entity_decode( $song_title );
			$entry_count++; // Increasing entry count in required field.
		} else {
			$row[] = '';
		}

		// (First Entry) Categories
		if ( ! empty( $categories ) ) {
			$row[] = $categories['song_entry_1'];
		} else {
			$row[] = '';
		}

		// (Second Entry) Song Title.
		$second_entry_song_title = $order->get_meta( '_billing_second_entry_song_title' );
		if ( ! empty( $second_entry_song_title ) ) {
			$row[] = html_entity_decode( $second_entry_song_title );
			$entry_count++; // Increasing entry count in required field.
		} else {
			$row[] = '';
		}

		// (Second Entry) Categories
		if ( ! empty( $categories ) ) {
			$row[] = $categories['song_entry_2'];
		} else {
			$row[] = '';
		}

		// (Third Entry) Song Title.
		$third_entry_song_title = $order->get_meta( '_billing_third_entry_song_title' );
		if ( ! empty( $third_entry_song_title ) ) {
			$row[] = html_entity_decode( $third_entry_song_title );
			$entry_count++; // Increasing entry count in required field.
		} else {
			$row[] = '';
		}

		// (Third Entry) Categories
		if ( ! empty( $categories ) ) {
			$row[] = $categories['song_entry_3'];
		} else {
			$row[] = '';
		}


		// (Fourth Entry) Song Title.
		$fourth_entry_song_title = $order->get_meta( '_billing_fourth_entry_song_title' );
		if ( ! empty( $fourth_entry_song_title ) ) {
			$row[] = html_entity_decode( $fourth_entry_song_title );
			$entry_count++; // Increasing entry count in required field.
		} else {
			$row[] = '';
		}

		// (Fourth Entry) Categories
		if ( ! empty( $categories ) ) {
			$row[] = $categories['song_entry_4'];
		} else {
			$row[] = '';
		}


		// (Fifth Entry) Song Title.
		$fifth_entry_song_title = $order->get_meta( '_billing_entry_5_song_title' );
		if ( ! empty( $fifth_entry_song_title ) ) {
			$row[] = html_entity_decode( $fifth_entry_song_title );
			$entry_count++; // Increasing entry count in required field.
		} else {
			$row[] = '';
		}

		// (Fifth Entry) Categories
		if ( ! empty( $categories ) ) {
			$row[] = $categories['song_entry_5'];
		} else {
			$row[] = '';
		}


		// Set Links/Lyrics of song as per requirement.
		if ( count( $contest ) > 1 ) {

			if ( false !== strpos( strtolower( wc_get_product( $product_id )->get_title() ), 'song contest' ) ) {
				$row = array_merge( $row, $this->get_entry_links( $order_id ) );
			} else {
				$row = array_merge( $row, array( '', '', '', '', '' ) );
			}

			if ( false !== strpos( strtolower( wc_get_product( $product_id )->get_title() ), 'lyric contest' ) ) {
				$row = array_merge( $row, $this->get_entry_lyrics( $order_id ) );
			} else {
				$row = array_merge( $row, array( '', '', '', '', '' ) );
			}
		} else {

			if ( ! empty( $product_id ) ) {

				if ( false !== strpos( strtolower( wc_get_product( $product_id )->get_title() ), 'song contest' ) ) {
					$row = array_merge( $row, $this->get_entry_links( $order_id ) );
				} else {
					$row = array_merge( $row, $this->get_entry_lyrics( $order_id ) );
				}
			} else {
				$row = array_merge( $row, array( '', '', '', '', '' ) );
			}
		}

		// Entry Count.
		$row[] = '\'' . strval( $entry_count );

		// Write row.
		fputcsv( $file, $row ); // phpcs:ignore -- WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_fputcsv.

		// Close file once work is over.
		fclose( $file ); // phpcs:ignore -- WordPress.WP.AlternativeFunctions.file_system_read_fclose
	}

	/**
	 * Fetches Lyric Contest entry lyrics.
	 *
	 * @param string $order_id order id.
	 *
	 * @retun array
	 */
	private function get_entry_lyrics( string $order_id ): array {
		$order  = wc_get_order( $order_id ); // Order.
		$lyrics = array(); // Initialize lyrics array.

		// Fetching lyrics for each entry.
		$lyrics_of_song              = $order->get_meta( '_billing_enter_your_lyrics_here' );
		$second_entry_lyrics_of_song = $order->get_meta( '_billing_entry_2_enter_your_lyrics_here' );
		$third_entry_lyrics_of_song  = $order->get_meta( '_billing_entry_3_enter_your_lyrics_here' );
		$fourth_entry_lyrics_of_song = $order->get_meta( '_billing_entry_4_enter_your_lyrics_here' );
		$fifth_entry_lyrics_of_song  = $order->get_meta( '_billing_entry_5_enter_your_lyrics_here' );

		// First entry's lyric.
		if ( ! empty( $lyrics_of_song ) ) {
			$lyrics[] = html_entity_decode( $lyrics_of_song );
		} else {
			$lyrics[] = '';
		}

		// Second entry's lyric.
		if ( ! empty( $second_entry_lyrics_of_song ) ) {
			$lyrics[] = html_entity_decode( $second_entry_lyrics_of_song );
		} else {
			$lyrics[] = '';
		}

		// Third entry's lyric.
		if ( ! empty( $third_entry_lyrics_of_song ) ) {
			$lyrics[] = html_entity_decode( $third_entry_lyrics_of_song );
		} else {
			$lyrics[] = '';
		}

		// Fourth entry's lyric.
		if ( ! empty( $fourth_entry_lyrics_of_song ) ) {
			$lyrics[] = html_entity_decode( $fourth_entry_lyrics_of_song );
		} else {
			$lyrics[] = '';
		}

		// Fifth entry's lyric.
		if ( ! empty( $fifth_entry_lyrics_of_song ) ) {
			$lyrics[] = html_entity_decode( $fifth_entry_lyrics_of_song );
		} else {
			$lyrics[] = '';
		}

		return $lyrics;
	}

	/**
	 * Fetches Song Contest entry links.
	 *
	 * @param string $order_id order id.
	 *
	 * @return array
	 */
	private function get_entry_links( string $order_id ): array {
		$order = wc_get_order( $order_id ); // Order.
		$links = array(); // Initialize links array.

		// Fetching links for each entry.
		$link_to_song              = $order->get_meta( '_billing_link_to_your_song' );
		$second_entry_link_to_song = $order->get_meta( '_billing_second_entry_link_to_your_song' );
		$third_entry_link_to_song  = $order->get_meta( '_billing_third_entry_link_to_your_song' );
		$fourth_entry_link_to_song = $order->get_meta( '_billing_fourth_entry_link_to_your_song' );
		$fifth_entry_link_to_song  = $order->get_meta( '_billing_entry_5_link_to_your_song' );

		// First entry's link.
		if ( ! empty( $link_to_song ) ) {
			$links[] = html_entity_decode( $link_to_song );
		} else {
			$links[] = '';
		}

		// Second entry's link.
		if ( ! empty( $second_entry_link_to_song ) ) {
			$links[] = html_entity_decode( $second_entry_link_to_song );
		} else {
			$links[] = '';
		}

		// Third entry's link.
		if ( ! empty( $third_entry_link_to_song ) ) {
			$links[] = html_entity_decode( $third_entry_link_to_song );
		} else {
			$links[] = '';
		}

		// Fourth entry's link.
		if ( ! empty( $fourth_entry_link_to_song ) ) {
			$links[] = html_entity_decode( $fourth_entry_link_to_song );
		} else {
			$links[] = '';
		}

		if ( ! empty( $fifth_entry_link_to_song ) ) {
			$links[] = html_entity_decode( $fifth_entry_link_to_song );
		} else {
			$links[] = '';
		}

		return $links;
	}


	//Function for Get Category
	private function get_categories( string $order_id ): array {
		$order = wc_get_order( $order_id ); // Order.
		$categories = array(); // Initialize categories array.
		$song_entry_1 = array(); // Initialize song_entry array.
		$song_entry_2 = array(); // Initialize song_entry array.
		$song_entry_3 = array(); // Initialize song_entry array.
		$song_entry_4 = array(); // Initialize song_entry array.
		$song_entry_5 = array(); // Initialize song_entry array.

		// First entry's categories.
		if ( ! empty( $order->get_meta( '_billing_song_1_-_americana' )) ) {
			$song_entry_1[] = 'Song 1 - Americana';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_blues' )) ) {
			$song_entry_1[] = 'Song 1 - Blues';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_childrens_music' )) ) {
			$song_entry_1[] = "Song 1 - Children's Music";
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_christian' )) ) {
			$song_entry_1[] = 'Song 1 - Christian';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_country' )) ) {
			$song_entry_1[] = 'Song 1 - Country';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_edm_electronic_dance_music' )) ) {
			$song_entry_1[] = ' Song 1 - EDM (Electronic Dance Music)';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_folksinger-songwriter' )) ) {
			$song_entry_1[] = 'Song 1 - Folk/Singer-Songwriter';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_hip-hoprap' )) ) {
			$song_entry_1[] = 'Song 1 - Hip-Hop/Rap';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_indie' )) ) {
			$song_entry_1[] = 'Song 1 - Indie';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_instrumental' )) ) {
			$song_entry_1[] = 'Song 1 - Instrumental';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_jazz' )) ) {
			$song_entry_1[] = 'Song 1 - Jazz';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_latin_music' )) ) {
			$song_entry_1[] = 'Song 1 - Latin Music';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_pop' )) ) {
			$song_entry_1[] = 'Song 1 - Pop';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_rock' )) ) {
			$song_entry_1[] = 'Song 1 - Rock';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_rbsoul' )) ) {
			$song_entry_1[] = 'Song 1 - R&B/Soul';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_teen_18_or_younger' )) ) {
			$song_entry_1[] = 'Song 1 - Teen';
		}
		if ( ! empty( $order->get_meta( '_billing_song_1_-_world_music' )) ) {
			$song_entry_1[] = 'Song 1 - World Music';
		}








		// Second entry's categories.
		if ( ! empty( $order->get_meta( '_billing_song_2_-_americana' )) ) {
			$song_entry_2[] = 'Song 2 - Americana';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_blues' )) ) {
			$song_entry_2[] = 'Song 2 - Blues';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_childrens_music' )) ) {
			$song_entry_2[] = "Song 2 - Children's Music";
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_christian' )) ) {
			$song_entry_2[] = 'Song 2 - Christian';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_country' )) ) {
			$song_entry_2[] = 'Song 2 - Country';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_edm_electronic_dance_music' )) ) {
			$song_entry_2[] = ' Song 2 - EDM (Electronic Dance Music)';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_folksinger-songwriter' )) ) {
			$song_entry_2[] = 'Song 2 - Folk/Singer-Songwriter';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_hip-hoprap' )) ) {
			$song_entry_2[] = 'Song 2 - Hip-Hop/Rap';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_indie_field' )) ) {
			$song_entry_2[] = 'Song 2 - Indie';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_instrumental' )) ) {
			$song_entry_2[] = 'Song 2 - Instrumental';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_jazz' )) ) {
			$song_entry_2[] = 'Song 2 - Jazz';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_latin_music' )) ) {
			$song_entry_2[] = 'Song 2 - Latin Music';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_pop' )) ) {
			$song_entry_2[] = 'Song 2 - Pop';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_pop' )) ) {
			$song_entry_2[] = 'Song 2 - Rock';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_rbsoul' )) ) {
			$song_entry_2[] = 'Song 2 - R&B/Soul';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_teen_18_or_younger' )) ) {
			$song_entry_2[] = 'Song 2 - Teen';
		}
		if ( ! empty( $order->get_meta( '_billing_song_2_-_world_music' )) ) {
			$song_entry_2[] = 'Song 2 - World Music';
		}

		// Third entry's categories.
		if ( ! empty( $order->get_meta( '_billing_song_3_-_americana' )) ) {
			$song_entry_3[] = 'Song 3 - Americana';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_blues' )) ) {
			$song_entry_3[] = 'Song 3 - Blues';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_childrens_music' )) ) {
			$song_entry_3[] = "Song 3 - Children's Music";
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_christian' )) ) {
			$song_entry_3[] = 'Song 3 - Christian';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_country' )) ) {
			$song_entry_3[] = 'Song 3 - Country';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_edm_electronic_dance_music' )) ) {
			$song_entry_3[] = ' Song 3 - EDM (Electronic Dance Music)';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_folksinger-songwriter' )) ) {
			$song_entry_3[] = 'Song 3 - Folk/Singer-Songwriter';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_hip-hoprap' )) ) {
			$song_entry_3[] = 'Song 3 - Hip-Hop/Rap';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_indie' )) ) {
			$song_entry_3[] = 'Song 3 - Indie';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_instrumental' )) ) {
			$song_entry_3[] = 'Song 3 - Instrumental';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_jazz' )) ) {
			$song_entry_3[] = 'Song 3 - Jazz';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_latin_music' )) ) {
			$song_entry_3[] = 'Song 3 - Latin Music';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_pop' )) ) {
			$song_entry_3[] = 'Song 3 - Pop';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_rock' )) ) {
			$song_entry_3[] = 'Song 3 - Rock';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_rbsoul' )) ) {
			$song_entry_3[] = 'Song 3 - R&B/Soul';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_teen_18_or_younger' )) ) {
			$song_entry_3[] = 'Song 3 - Teen';
		}
		if ( ! empty( $order->get_meta( '_billing_song_3_-_world_music' )) ) {
			$song_entry_3[] = 'Song 3 - World Music';
		}

		// Fourth entry's categories.
		if ( ! empty( $order->get_meta( '_billing_song_4_-_americana' )) ) {
			$song_entry_4[] = 'Song 4 - Americana';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_blues' )) ) {
			$song_entry_4[] = 'Song 4 - Blues';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_childrens_music' )) ) {
			$song_entry_4[] = "Song 4 - Children's Music";
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_christian' )) ) {
			$song_entry_4[] = 'Song 4 - Christian';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_country' )) ) {
			$song_entry_4[] = 'Song 4 - Country';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_edm_electronic_dance_music' )) ) {
			$song_entry_4[] = ' Song 4 - EDM (Electronic Dance Music)';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_folksinger-songwriter' )) ) {
			$song_entry_4[] = 'Song 4 - Folk/Singer-Songwriter';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_hip-hoprap' )) ) {
			$song_entry_4[] = 'Song 4 - Hip-Hop/Rap';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_indie' )) ) {
			$song_entry_4[] = 'Song 4 - Indie';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_instrumental' )) ) {
			$song_entry_4[] = 'Song 4 - Instrumental';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_jazz' )) ) {
			$song_entry_4[] = 'Song 4 - Jazz';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_latin_music' )) ) {
			$song_entry_4[] = 'Song 4 - Latin Music';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_pop' )) ) {
			$song_entry_4[] = 'Song 4 - Pop';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_rock' )) ) {
			$song_entry_4[] = 'Song 4 - Rock';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_rbsoul' )) ) {
			$song_entry_4[] = 'Song 4 - R&B/Soul';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_teen_18_or_younger' )) ) {
			$song_entry_4[] = 'Song 4 - Teen';
		}
		if ( ! empty( $order->get_meta( '_billing_song_4_-_world_music' )) ) {
			$song_entry_4[] = 'Song 4 - World Music';
		}


		// Fourth entry's categories.
		if ( ! empty( $order->get_meta( '_billing_song_5_-_americana' )) ) {
			$song_entry_5[] = 'Song 5 - Americana';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_blues' )) ) {
			$song_entry_5[] = 'Song 5 - Blues';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_childrens_music' )) ) {
			$song_entry_5[] = "Song 5 - Children's Music";
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_christian' )) ) {
			$song_entry_5[] = 'Song 5 - Christian';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_country' )) ) {
			$song_entry_5[] = 'Song 5 - Country';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_edm_electronic_dance_music' )) ) {
			$song_entry_5[] = ' Song 5 - EDM (Electronic Dance Music)';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_folksinger-songwriter' )) ) {
			$song_entry_5[] = 'Song 5 - Folk/Singer-Songwriter';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_hip-hoprap' )) ) {
			$song_entry_5[] = 'Song 5 - Hip-Hop/Rap';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_indie' )) ) {
			$song_entry_5[] = 'Song 5 - Indie';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_instrumental' )) ) {
			$song_entry_5[] = 'Song 5 - Instrumental';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_jazz' )) ) {
			$song_entry_5[] = 'Song 5 - Jazz';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_latin_music' )) ) {
			$song_entry_5[] = 'Song 5 - Latin Music';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_pop' )) ) {
			$song_entry_5[] = 'Song 5 - Pop';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_rock' )) ) {
			$song_entry_5[] = 'Song 5 - Rock';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_rbsoul' )) ) {
			$song_entry_5[] = 'Song 5 - R&B/Soul';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_teen_18_or_younger' )) ) {
			$song_entry_5[] = 'Song 5 - Teen';
		}
		if ( ! empty( $order->get_meta( '_billing_song_5_-_world_music' )) ) {
			$song_entry_5[] = 'Song 5 - World Music';
		}

		// Add all categories into categories array
		$categories['song_entry_1'] = (!empty($song_entry_1))?implode(', ', $song_entry_1):'';
		$categories['song_entry_2'] = (!empty($song_entry_2))?implode(', ', $song_entry_2):'';
		$categories['song_entry_3'] = (!empty($song_entry_3))?implode(', ', $song_entry_3):'';
		$categories['song_entry_4'] = (!empty($song_entry_4))?implode(', ', $song_entry_4):'';
		$categories['song_entry_5'] = (!empty($song_entry_5))?implode(', ', $song_entry_5):'';

		//Return Categories
		return $categories;
	}
}
