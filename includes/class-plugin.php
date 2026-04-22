<?php
/**
 * Main plugin orchestrator.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Plugin {

	private static ?Plugin $instance = null;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init();
	}

	private function init(): void {
		// Check if database needs upgrading.
		$this->maybe_upgrade();

		// Translations are loaded automatically by WordPress since 4.6 for
		// plugins hosted on wordpress.org, so we do not call
		// load_plugin_textdomain() manually.

		// REST API (always loaded).
		new RestApi();
		new AdminRestApi();

		// Admin context.
		if ( is_admin() ) {
			new Admin();
		}

		// Frontend context.
		if ( ! is_admin() || wp_doing_ajax() ) {
			new Frontend();
		}

		// Notifications (hook-driven).
		new Notification();

		// Webhooks (hook-driven).
		new Webhook();

		// Cron for reminders.
		add_action( 'nah_send_reminders', [ Notification::class, 'process_reminders' ] );
	}

	/**
	 * Run database upgrade if version has changed.
	 */
	private function maybe_upgrade(): void {
		$installed_version = get_option( 'nah_version', '0' );

		if ( version_compare( $installed_version, NAH_VERSION, '<' ) ) {
			Activator::activate();
		}
	}

	private function __clone() {}
	public function __wakeup() {
		_doing_it_wrong( __METHOD__, esc_html__( 'Unserializing the Plugin singleton is not supported.', 'nivaj-appointment-hub' ), '1.0.0' );
	}
}
