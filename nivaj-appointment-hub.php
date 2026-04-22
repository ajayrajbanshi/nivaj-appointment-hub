<?php
/**
 * Plugin Name: Nivaj Appointment Hub
 * Plugin URI: https://github.com/ajayrajbanshi
 * Description: A flexible appointment booking system for WordPress. Define services, set weekly and date-specific availability, and let visitors book time slots with email notifications and optional webhooks.
 * Version:     1.0.0
 * Author:      Ajay Rajbanshi
 * Author URI: https://www.ajayrajbanshi.com.np
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: nivaj-appointment-hub
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'NIVAJ_AH_VERSION', '1.0.0' );
define( 'NIVAJ_AH_FILE', __FILE__ );
define( 'NIVAJ_AH_PATH', plugin_dir_path( __FILE__ ) );
define( 'NIVAJ_AH_URL', plugin_dir_url( __FILE__ ) );
define( 'NIVAJ_AH_BASENAME', plugin_basename( __FILE__ ) );

require_once NIVAJ_AH_PATH . 'includes/class-loader.php';
Loader::register();

add_action( 'plugins_loaded', function () {
	Plugin::instance();
} );

register_activation_hook( __FILE__, function () {
	Activator::activate();
} );

register_deactivation_hook( __FILE__, function () {
	Activator::deactivate();
} );
