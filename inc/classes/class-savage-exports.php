<?php
/**
 * The core plugin class.
 *
 * @since      1.0.0
 *
 * @package    Savage-Exports
 * @subpackage inc/classes
 */

namespace Savage_Exports\Includes;

/**
 * Main plugin class.
 * It provides core functionalities.
 *
 * @since      1.0.0
 * @package    Savage-Exports
 * @subpackage inc/classes
 */
class Savage_Exports {

	/**
	 * Invokes the Plugin.
	 */
	public function __construct() {

		// Add admin menu pages for plugin.
		new Exports_Admin_Menu();

		// Register custom endpoints.
		new Rest_Endpoints();

		// Add assets.
		new Assets();

		// Initialize financial exports class.
		new Financial_Exports();

		// Initialize address exports class.
		new Savage_Addresses_Exports();

		// Initialize contest exports class.
		new Contest_Exports();

        // Initialize road ready contest exports class.
        new Savage_Road_Ready_Exports();

	}
}
