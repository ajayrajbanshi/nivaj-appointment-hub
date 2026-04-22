<?php
/**
 * PSR-4-like autoloader for the NivajAppointmentHub namespace.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Loader {

	/**
	 * Register the autoloader.
	 */
	public static function register(): void {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Autoload classes in the NivajAppointmentHub namespace.
	 *
	 * Maps NivajAppointmentHub\ClassName to includes/class-class-name.php.
	 */
	private static function autoload( string $class ): void {
		$prefix = 'NivajAppointmentHub\\';

		if ( strpos( $class, $prefix ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, strlen( $prefix ) );

		// Convert CamelCase to kebab-case for the filename.
		$filename = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1-$2', $relative_class ) );
		$filename = 'class-' . str_replace( '_', '-', $filename ) . '.php';

		$file = NIVAJ_AH_PATH . 'includes/' . $filename;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}
