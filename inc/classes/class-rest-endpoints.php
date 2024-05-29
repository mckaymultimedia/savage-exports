<?php
/**
 * Custom end-points file.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

/**
 * Custom end-points class.
 */
class Rest_Endpoints {

	/**
	 * Number of rows to be written to a CSV file in one go.
	 *
	 * @var int
	 */
	private int $rows_written_per_page;

	/**
	 * Initializes class.
	 */
	public function __construct() {

		$this->rows_written_per_page = 100;

		add_action( 'rest_api_init', array( $this, 'register_rest_apis' ) );
	}

	/**
	 * Registers rest routes.
	 *
	 * @return void
	 */
	public function register_rest_apis(): void {

		/**
		 * Get csv files.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/(?P<export_name>[\w]+-[\w]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_csvs' ),
				'args'                => array(
					'export_name' => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Delete generated csv file.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/delete',
			array(
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => array( $this, 'delete_csv' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Generates address export csv file.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/generate-address-exports',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_address_exports' ),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Generates financial export csv file.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/generate-financial-exports/(?P<start_date>\d+-\d+-\d+)/(?P<end_date>\d+-\d+-\d+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_financial_exports' ),
				'args'                => array(
					'start_date' => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
					'end_date'   => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

		/**
		 * Generates contest export csv file.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/generate-contest-exports/(?P<start_date>\d+-\d+-\d+)/(?P<end_date>\d+-\d+-\d+)/(?P<contest>[\w]+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_contest_exports' ),
				'args'                => array(
					'start_date' => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
					'end_date'   => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
					'contest'    => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);

        /**
         * Generates road ready contest export csv file.
         */
        register_rest_route(
            'savage-exports/v1',
            '/csv/generate-road-ready-contest-exports/(?P<start_date>\d+-\d+-\d+)/(?P<end_date>\d+-\d+-\d+)',
            array(
                'methods'             => \WP_REST_Server::READABLE,
                'callback'            => array( $this, 'generate_road_ready_contest_exports' ),
                'args'                => array(
                    'start_date' => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_string( $param );
                        },
                    ),
                    'end_date'   => array(
                        'validate_callback' => function( $param, $request, $key ) {
                            return is_string( $param );
                        },
                    )
                ),
                'permission_callback' => function () {
                    return current_user_can( 'manage_options' );
                },
            )
        );

		/**
		 * Get file's download link.
		 */
		register_rest_route(
			'savage-exports/v1',
			'/csv/download-link/(?P<file_name>\S+)',
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'generate_download_link' ),
				'args'                => array(
					'file_name' => array(
						'validate_callback' => function( $param, $request, $key ) {
							return is_string( $param );
						},
					),
				),
				'permission_callback' => function () {
					return current_user_can( 'manage_options' );
				},
			)
		);
	}

	/**
	 * Get csv files.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return array|\WP_Error array containing file paths.
	 */
	public function get_csvs( \WP_REST_Request $request ) {

		// Fetch query parameters.
		$query_params = $request->get_query_params();

		// Return error if any required parameter not found.
		if ( ! isset( $query_params['start_after'] ) || empty( $request['export_name'] ) ) {
			return new \WP_Error(
				'missing_required_param',
				__( 'Missing Required Parameters', 'savage-exports' ),
				array( 'status' => 404 )
			);
		}

		$export = $request['export_name']; // Folder containing files.

		$start_after    = $query_params['start_after'];   // Page number.
		$files_per_page = get_option( 'posts_per_page' ); // Number of files per page.

		// Creating default response array.
		$response = array(
			'success'          => false,
			'prev_key'         => '',
			'files_data'       => array(),
			'next_page_length' => 0,
		);

		// Fetching csv files.
		$files = savage_get_list_of_files_s3( $files_per_page, $export, $start_after );

		// Return if query failed.
		if ( ! empty( $files ) && 200 !== $files->get( '@metadata' )['statusCode'] ) {
			return $response;
		}

		// Return if there are no files.
		if ( empty( $files['Contents'] ) ) {
			$response['success']  = true;
			$response['prev_key'] = $start_after;

			return $response;
		}

		/**
		 * Creating file data for response.
		 *
		 * Format: array(
		 *              array( 'file_name': $file_name, 'file_path': $file_path ),
		 *              array( 'file_name': $file_name, 'file_path': $file_path ),
		 *              ...
		 *         );
		 */
		$files_data = array_map(
			function ( $file ) {
				$file_name = substr( $file['Key'], strpos( $file['Key'], '/' ) + 1 );
				return array(
					'file_name'     => $file_name,
					'file_path'     => $file['Key'],
					'download_link' => savage_download_file_s3( $file_name, $file['Key'] ),
				);
			},
			$files['Contents']
		);

		// Update response.
		$response['success']    = true;
		$response['prev_key']   = end( $files['Contents'] )['Key'];
		$response['files_data'] = $files_data;

		// Fetch next page.
		$next_files = savage_get_list_of_files_s3( $files_per_page, $export, end( $files['Contents'] )['Key'] );

		// If length of next page if 0 return.
		if ( empty( $next_files['Contents'] ) ) {
			$response['next_page_length'] = 0;
			return $response;
		}

		// Add length of next page.
		$response['next_page_length'] = count( $next_files['Contents'] );

		// Returning response.
		return $response;
	}

	/**
	 * Deletes generated csv file.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return array|\WP_Error true on success.
	 */
	public function delete_csv( \WP_REST_Request $request ) {

		// Fetch query parameters.
		$query_params = $request->get_query_params();

		// Return error if any required parameter not found.
		if ( empty( $query_params['file_path'] ) ) {
			return new \WP_Error(
				'missing_required_param',
				__( 'Missing Required Parameter', 'savage-exports' ),
				array( 'status' => 404 )
			);
		}

		$file_path = $query_params['file_path']; // File Path w.r.t. s3 bucket.

		// Delete file from s3.
		$is_deleted = savage_delete_file_s3( $file_path );

		// Creating response array.
		$response = array(
			'success' => $is_deleted,
		);

		$this->generate_logs(wp_get_current_user()->user_email, $file_path, "Delete");

		// Returning response.
		return $response;
	}

	/**
	 * Generates Address Export's CSV file.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return array true on success.
	 */
	public function generate_address_exports( \WP_REST_Request $request ): array {

		// Generate default response.
		$response = array(
			'success' => false,
			'message' => __( 'Scheduling Event Failed', 'savage-exports' ),
		);

		// Create a new cron job.
		$cron_id = as_enqueue_async_action(
			'savage_start_address_export_cron_hook',
			array(
				'date_check' => false, // Name of file.
			)
		);

		// Return if cron is not initialized.
		if ( 0 === $cron_id ) {
			return $response;
		}

		// Update Response as everything went right.
		$response['success'] = true;
		$response['message'] = __( 'Cron scheduled successfully! A mail will be send once the file is generated.', 'savage-exports' );

		// Preserve author's email address.
		update_option( 'address_exports_author_mail_id', wp_get_current_user()->user_email );

		$this->generate_logs(wp_get_current_user()->user_email, "Address export", "Create");

		// Returning response.
		return $response;

	}

	/**
	 * Generates Financial Export's CSV file.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return array|\WP_Error true on success.
	 */
	public function generate_financial_exports( \WP_REST_Request $request ) {

		// Return error if any required parameter not found.
		if ( empty( $request['start_date'] ) || empty( $request['end_date'] ) ) {
			return new \WP_Error(
				'missing_required_param',
				__( 'Missing Required Parameters', 'savage-exports' ),
				array( 'status' => 404 )
			);
		}

		// Generate default response.
		$response = array(
			'success' => false,
			'message' => __( 'Scheduling Event Failed', 'savage-exports' ),
		);

		$initial_date = $request['start_date'];  // Initial Date.
		$final_date   = $request['end_date'];    // Final Date.

		// CSV file Name.
		$file_name = $initial_date . '_' . $final_date . '.csv';

		// Total pages.
		$page_count = $this->get_financial_exports_page_counts( $initial_date, $final_date );

		// Return if page_count not found.
		if ( false === $page_count ) {
			$response['message'] = __( 'Cannot count pages', 'savage-exports' );
			return $response;
		}

		// Create a new cron job.
		$cron_id = as_enqueue_async_action(
			'savage_financial_export_cron_hook',
			array(
				'current_page' => 1,             // Current Page Number.
				'file_name'    => $file_name,    // Name of file.
				'total_pages'  => $page_count,   // Total Pages.
				'initial_date' => $initial_date, // Initial Date.
				'final_date'   => $final_date,   // Final Date.
			)
		);

		// Return if cron is not initialized.
		if ( 0 === $cron_id ) {
			return $response;
		}

		// Update Response as everything went right.
		$response['success'] = true;
		$response['message'] = __( 'Cron scheduled successfully! A mail will be send once the file is generated.', 'savage-exports' );

		// Preserve author's email address.
		update_option( 'financial_exports_author_mail_id_' . $file_name, wp_get_current_user()->user_email );

		$this->generate_logs(wp_get_current_user()->user_email, "Financial export", "Create");

		// Returning response.
		return $response;

	}

	/**
	 * Generates Contest Export's CSV file.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return \WP_Error|array true on success.
	 */
	public function generate_contest_exports( \WP_REST_Request $request ) {

		// Return error if any required parameter not found.
		if ( empty( $request['start_date'] ) || empty( $request['end_date'] ) || empty( $request['contest'] ) ) {
			return new \WP_Error(
				'missing_required_param',
				__( 'Missing Required Parameters', 'savage-exports' ),
				array( 'status' => 404 )
			);
		}

		// Generate default response.
		$response = array(
			'success' => false,
			'message' => __( 'Scheduling Event Failed', 'savage-exports' ),
		);

		$initial_date = $request['start_date']; // Initial Date.
		$final_date   = $request['end_date'];   // Final Date.
		$contest      = $request['contest'];    // Contest.

		// Generate csv file name.
		if ( 'all' === strtolower( $contest ) ) {
			// CSV file Name.
			$file_name = $contest.'_contests'. '_' . $initial_date . '_' . $final_date . '.csv';

			$contest = wc_get_products(
				array(
					'category' => 'contests',
					'return'   => 'ids',
				)
			);
		} else {
			// CSV file Name.
			$file_name = join( '_', explode( ' ', strtolower( wc_get_product( $contest )->get_title() ) ) ) . '_' . $initial_date . '_' . $final_date . '.csv';
		}

		// Total pages.
		$page_count = $this->get_contest_exports_page_counts( $initial_date, $final_date, $contest );

		// Return if page_count not found.
		if ( false === $page_count ) {
			$response['message'] = __( 'No entries in particular date range.', 'savage-exports' );
			return $response;
		}

		// Create a new cron job.
		$cron_id = as_enqueue_async_action(
			'savage_contest_exports_cron_hook',
			array(
				'current_page' => 1,             // Current Page Number.
				'file_name'    => $file_name,    // Name of file.
				'total_pages'  => $page_count,   // Total Pages.
				'initial_date' => $initial_date, // Initial Date.
				'final_date'   => $final_date,   // Final Date.
				'contest'      => $contest,      // Contest Name.
			)
		);

		// Return if cron is not initialized.
		if ( 0 === $cron_id ) {
			return $response;
		}

		// Update Response as everything went right.
		$response['success'] = true;
		$response['message'] = __( 'Cron scheduled successfully! A mail will be send once the file is generated.', 'savage-exports' );

		// Preserve author's email address.
		update_option( 'contest_exports_author_mail_id_' . $file_name, wp_get_current_user()->user_email );

		$this->generate_logs(wp_get_current_user()->user_email, "Contest export", "Create");

		// Returning response.
		return $response;

	}

    /**
     * Generates Contest Export's CSV file.
     *
     * @param \WP_REST_Request $request Options for the function.
     *
     * @return \WP_Error|array true on success.
     */
    public function generate_road_ready_contest_exports( \WP_REST_Request $request ) {

        // Return error if any required parameter not found.
        if ( empty( $request['start_date'] ) || empty( $request['end_date'] ) ) {
            return new \WP_Error(
                'missing_required_param',
                __( 'Missing Required Parameters', 'savage-exports' ),
                array( 'status' => 404 )
            );
        }

        // Generate default response.
        $response = array(
            'success' => false,
            'message' => __( 'Scheduling Event Failed', 'savage-exports' ),
        );

        $initial_date = $request['start_date']; // Initial Date.
        $final_date   = $request['end_date'];   // Final Date.
        // CSV file Name.
        $file_name = 'road_ready_contest'. '_' . $initial_date . '_' . $final_date . '.csv';

        // Total pages.
        $page_count = $this->get_road_ready_contest_exports_page_counts( $initial_date, $final_date );

        // Return if page_count not found.
        if ( false === $page_count ) {
            $response['message'] = __( 'No entries in particular date range.', 'savage-exports' );
            return $response;
        }

        // Create a new cron job.
        $cron_id = as_enqueue_async_action(
            'savage_road_ready_contest_exports_cron_hook',
            array(
                'current_page' => 1,             // Current Page Number.
                'file_name'    => $file_name,    // Name of file.
                'total_pages'  => $page_count,   // Total Pages.
                'initial_date' => $initial_date, // Initial Date.
                'final_date'   => $final_date,   // Final Date.
            )
        );

        // Return if cron is not initialized.
        if ( 0 === $cron_id ) {
            return $response;
        }

        // Update Response as everything went right.
        $response['success'] = true;
        $response['message'] = __( 'Cron scheduled successfully! A mail will be send once the file is generated.', 'savage-exports' );

        // Preserve author's email address.
        update_option( 'road_ready_contest_exports_author_mail_id_' . $file_name, wp_get_current_user()->user_email );

        $this->generate_logs(wp_get_current_user()->user_email, "Road Ready Contest export", "Create");

        // Returning response.
        return $response;

    }

    /**
     * Get total number of orders between a date range.
     *
     * @param string $initial_date Initial Date.
     * @param string $final_date   Final Date.
     *
     * @return int|bool total number of pages on success | false on failure.
     */
    private function get_road_ready_contest_exports_page_counts( string $initial_date, string $final_date ) {

        // Prepare query args.
        $query_args = array(
            'post_type'      => 'shop_order',
            'post_status'    => array( 'wc-completed' ),
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'date_query'     => array(
                array(
                    'after'     => $initial_date,
                    'before'    => $final_date,
                    'inclusive' => true,
                ),
            ),
            'meta_query'     => array(
                array(
                    'key'     => '_line_items',
                    'value'   => 'Road Ready Contest Entry', // Product name to search for
                    'compare' => 'LIKE',
                ),
            ),
        );

        // Fire query.
        $query = new \WP_Query( $query_args );

        if ( empty( $query ) ) {
            return false;
        }

        // Fetch total number of rows.
        $row_count = $query->found_posts;

        // 1026 => 10 + 1 => 11 Pages.
        return intval( $row_count / $this->rows_written_per_page ) +
            ( ( $row_count % $this->rows_written_per_page ) > 0 ? 1 : 0 );

    }

	/**
	 * Get total number of orders between a date range.
	 *
	 * @param string $initial_date Initial Date.
	 * @param string $final_date   Final Date.
	 *
	 * @return int|bool total number of pages on success | false on failure.
	 */
	private function get_financial_exports_page_counts( string $initial_date, string $final_date ) {

		// Prepare query args.
		$query_args = array(
			'post_type'      => 'shop_order',
			'post_status'    => array( 'wc-completed', 'wc-pending', 'wc-processing' ),
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'date_query'     => array(
				array(
					'after'     => $initial_date,
					'before'    => $final_date,
					'inclusive' => true,
				),
			),
		);

		// Fire query.
		$query = new \WP_Query( $query_args );

		if ( empty( $query ) ) {
			return false;
		}

		// Fetch total number of rows.
		$row_count = $query->found_posts;

		// 1026 => 10 + 1 => 11 Pages.
		return intval( $row_count / $this->rows_written_per_page ) +
				( ( $row_count % $this->rows_written_per_page ) > 0 ? 1 : 0 );

	}

	/**
	 * Get total number of orders between a date range for a particular contest.
	 *
	 * @param string       $initial_date Initial Date.
	 * @param string       $final_date   Final Date.
	 * @param string|array $contest      Contest ID.
	 *
	 * @return int|bool total number of pages on success | false on failure.
	 */
	private function get_contest_exports_page_counts( string $initial_date, string $final_date, $contest ) {
		global $wpdb;

		// Convert $contest to array if it is a string.
		if ( is_string( $contest ) ) {
			$contest = array( $contest );
		}

		// Generate placeholders.
		$contest_id_placeholders = implode( ', ', array_fill( 0, count( $contest ), '%d' ) );

		// Prepare values.
		$prepared_values = array_merge( array( $initial_date . ' 00:00:00', $final_date . ' 23:59:59' ), $contest );

		$query = $wpdb->prepare(
			"SELECT COUNT( order_items.order_id )
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_date_gmt BETWEEN %s AND %s
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta.meta_value IN ( $contest_id_placeholders )", // phpcs:ignore -- $contest_id_placeholders contains placeholders.
			$prepared_values
		);

		$row_count = $wpdb->get_var( $query ); // phpcs:ignore -- Direct database call without caching detected.

		if ( $row_count < 1 ) {
			return false;
		}

		// 1026 => 10 + 1 => 11 Pages.
		return intval( $row_count / $this->rows_written_per_page ) +
				( ( $row_count % $this->rows_written_per_page ) > 0 ? 1 : 0 );

	}

	/**
	 * Generates Financial Export's CSV file.
	 *
	 * @param \WP_REST_Request $request Options for the function.
	 *
	 * @return array|\WP_Error true on success.
	 */
	public function generate_download_link( \WP_REST_Request $request ) {

		$query_params = $request->get_query_params();

		// Return error if any required parameter not found.
		if ( empty( $request['file_name'] ) || empty( $query_params['key_name'] ) ) {
			return new \WP_Error(
				'missing_required_param',
				__( 'Missing Required Parameters', 'savage-exports' ),
				array( 'status' => 404 )
			);
		}

		// Setting params.
		$file_name = $request['file_name'];
		$key_name  = $query_params['key_name'];

		// Default response.
		$response = array(
			'success'       => false,
			'download_link' => '',
		);

		// Generate download link.
		$download_link = savage_download_file_s3( $file_name, $key_name );

		// Return if download link not generated.
		if ( empty( $download_link ) ) {
			return $response;
		}

		// Update response.
		$response['success']       = true;
		$response['download_link'] = $download_link;

		// Return response.
		return $response;
	}


	public function generate_logs($email, $type, $event){
		$upload_dir = wp_get_upload_dir()['basedir'];
		$file_path = $upload_dir . '/savage-export-logs/export_log.csv';
		$file = fopen( $file_path, 'a' );

		$row[] = $email;
		$row[] = $type;
		$row[] = $event;
		$row[] = date("j F Y h:i:s A");

		fputcsv( $file, $row );
		fclose( $file );

	}


}
