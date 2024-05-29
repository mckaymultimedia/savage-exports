<?php
/**
 * Autoloader file to autoload all class files.
 *
 * @since 1.0.0
 *
 * @package Savage-Exports
 * @subpackage inc/helpers
 */

spl_autoload_register( 'savage_exports_autoloader' );

/**
 * Takes class name and includes the file.
 *
 * @param string $class_name class name.
 *
 * @return void
 */
function savage_exports_autoloader( string $class_name ): void {

	// Include only classes.
	if ( strpos( $class_name, 'Includes' ) !== false ) {

		$class_name          = strstr( $class_name, 'Includes' );                             // Remove everything before 'Includes'.
		$class_name          = str_replace( 'Includes', 'inc\classes', $class_name ); // Replace 'Includes' with 'inc\classes'.
		$class_name          = strtolower( $class_name );                                            // Convert everything to lower case.
		$last_slash_position = strrpos( $class_name, '\\' );                                  // Fetch the position of last slash.

		$class_name = str_replace( '_', '-', $class_name );                           // Replace all '_' with '-'.
		$class_name = substr_replace(                                                                // Add 'class-' after last slash.
			$class_name,
			'class-',
			$last_slash_position + 1,
			0
		);

		$extension = '.php';                                                                         // File Extension.
		$full_path = SAVAGE_EXPORTS_PATH . $class_name . $extension;                                 // Full path of file.
		$full_path = str_replace( '\\', '/', $full_path );                            // Replace all '\' with '/'.

		// Include only if file exists.
		if ( file_exists( $full_path ) ) {
			require_once $full_path;
		}
	}
}
