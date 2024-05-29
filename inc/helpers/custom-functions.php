<?php
/**
 * Features custom functions.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/helpers
 */

use Aws\Result;
use Aws\S3\S3Client;
use Aws\S3\MultipartUploader;
use Aws\S3\Exception\S3Exception;
use Savage_Exports\Includes\Log_Warning;

/**
 * Creates directory if it does not exist.
 *
 * @param \WP_Filesystem_VIP $wp_filesystem VIP FileSystem.
 * @param string             $dir_path      path of directory.
 *
 * @return bool true if directory created successfully or already exists else false.
 */
function maybe_create_directory( \WP_Filesystem_VIP $wp_filesystem, string $dir_path ): bool {

	$success = true;

	if ( ! $wp_filesystem->is_dir( $dir_path ) ) {
		// Recursively create directories.
		$success = wp_mkdir_p( $dir_path );
	}

	return $success;

}

/**
 * Initializes WP-FileSystem if not initialized.
 */
function initialize_filesystem(): \WP_Filesystem_VIP {

	global $wp_filesystem;

	// If not already set, initialize the WP filesystem.
	if ( ! is_a( $wp_filesystem, 'WP_Filesystem_Base' ) ) {
		$creds = request_filesystem_credentials( site_url() );
		wp_filesystem( $creds );
	}

	return $wp_filesystem;

}

/**
 * An extension to get_template_part function to allow variables to be passed to the template.
 *
 * @param string $slug      file slug like you use in get_template_part without php extension.
 * @param array  $variables pass an array of variables you want to use in array keys.
 *
 * @return void
 */
function savage_exports_get_template_part( string $slug, array $variables = array() ): void {
	// Using plugin's templates.
	$template = sprintf( '%s/templates/%s.php', SAVAGE_EXPORTS_PATH, $slug );

	// Checking if template exists in plugin.
	if ( ! file_exists( $template ) ) {
		return;
	}

	if ( ! empty( $variables ) && is_array( $variables ) ) {
		extract( $variables, EXTR_SKIP ); // phpcs:ignore -- Used as an exception as there is no better alternative.
	}

	include $template; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable
}

/**
 * Render template.
 *
 * @param string $slug template path.
 * @param array  $vars variables to be used in the template.
 * @param bool   $echo whether need to echo to return HTML markup.
 *
 * @return string Template markup.
 */
function savage_exports_render_template( string $slug, array $vars = array(), bool $echo = true ): string {

	if ( true === $echo ) {

		// Load template and output the data.
		savage_exports_get_template_part( $slug, $vars );

		return ''; // Job done, bail out.
	}

	ob_start();

	// Load template output in buffer.
	savage_exports_get_template_part( $slug, $vars );

	return ob_get_clean();

}

/**
 * Initializes Amazon S3.
 *
 * @return S3Client|false S3Client object if success | false of failure.
 */
function savage_get_s3() {

	// Connect to AWS.
	try {
		// Create S3 Client.
		$s3 = new S3Client(
			array(
				'version'     => 'latest',
				'region'      => SAVAGE_EXPORTS_AWS_REGION,
				'credentials' => array(
					'key'    => get_option( 'aws_access_key' ),
					'secret' => get_option( 'aws_private_key' ),
				),
			)
		);
	} catch ( Exception $e ) {
		return false;
	}

	return $s3;

}

/**
 * Uploads files to Amazon S3 bucket.
 *
 * @param string $file_full_path     file path.
 * @param string $file_relative_path relative path of file w.r.t. S3.
 *
 * @return bool True on success | False on failure.
 */
function savage_upload_s3( string $file_full_path, string $file_relative_path ): bool {

	// Initialize S3 Client.
	$s3 = savage_get_s3();

	// Return if S3 Client is not initialized.
	if ( false === $s3 ) {
		return false;
	}

	// File name with folder.
	$key_name = $file_relative_path;

	// Prepare the upload parameters.
	$uploader = new MultipartUploader(
		$s3,
		$file_full_path,
		array(
			'bucket' => SAVAGE_EXPORTS_BUCKET,
			'key'    => $key_name,
		)
	);

	try {

		// Upload File.
		$uploader->upload();
		return true;

	} catch ( S3Exception $e ) {
		return false;
	} catch ( Exception $e ) {
		return false;
	}
}

/**
 * Fetches files from Amazon S3.
 *
 * @param int    $files_per_page number of files per page.
 * @param string $export_name    name of folder for export.
 * @param string $start_after    needle from which fetching starts.
 *
 * @return array|Result Empty array on failure | Result object on success.
 */
function savage_get_list_of_files_s3( int $files_per_page, string $export_name, string $start_after = '' ) {

	// Create S3 Client.
	$s3 = savage_get_s3();

	// Return if S3 Client is not initialized.
	if ( false === $s3 ) {
		return array();
	}

	// Fetch files.
	$objects = $s3->listObjectsV2(
		array(
			'Bucket'     => SAVAGE_EXPORTS_BUCKET,
			'Prefix'     => $export_name,
			'MaxKeys'    => $files_per_page,
			'StartAfter' => $start_after,
		)
	);

	// Return files.
	return $objects;

}

/**
 * Deletes file from S3.
 *
 * @param string $key_name relative path w.r.t. S3.
 *
 * @return bool True on success | False on failure.
 */
function savage_delete_file_s3( string $key_name ): bool {

	// Create S3 Client.
	$s3 = savage_get_s3();

	// Return if S3 Client is not initialized.
	if ( false === $s3 ) {
		return false;
	}

	try {
		// Delete file.
		$s3->deleteObject(
			array(
				'Bucket' => SAVAGE_EXPORTS_BUCKET,
				'Key'    => $key_name,
			)
		);

		// Return true if success.
		return true;
	} catch ( S3Exception $e ) {
		// Return false on failure.
		return false;
	}

}

/**
 * Generates download link for a given s3 file.
 *
 * @param string $file     file name.
 * @param string $key_name file path w.r.t. S3.
 *
 * @return string download link.
 */
function savage_download_file_s3( string $file, string $key_name ): string {

	// Create S3 Client.
	$s3 = savage_get_s3();

	// Return if S3 Client is not initialized.
	if ( false === $s3 ) {
		return false;
	}

	// Command to create download link.
	$cmd = $s3->getCommand(
		'GetObject',
		array(
			'Bucket'                     => SAVAGE_EXPORTS_BUCKET,
			'Key'                        => $key_name,
			'ResponseContentDisposition' => 'attachment; filename="' . $file . '"',
		)
	);

	// Generate download link for 3 min interval.
	$request       = $s3->createPresignedRequest( $cmd, '+' . SAVAGE_S3_DOWNLOAD_LINK_EXPIRY_TIME . ' min' );
	$presigned_url = (string) $request->getUri();

	return $presigned_url;

}

/**
 * Creates necessary taxonomies if not available.
 *
 * @return void.
 */
function maybe_create_taxonomies(): void {

	$contest_tax = get_term_by( 'name', 'contests', 'product_cat' );

	// Return if taxonomy already created.
	if ( false !== $contest_tax ) {
		return;
	}

	// Create Contests taxonomy.
	$result = wp_insert_term(
		__( 'Contests', 'savage-exports' ),
		'product_cat',
		array(
			'slug' => 'contests',
		)
	);

	// Creation failed!
	if ( is_wp_error( $result ) ) {
		new Log_Warning(
			esc_html__( '"Contests" product category creation failed.', 'savage-exports' )
		);
	}

}
