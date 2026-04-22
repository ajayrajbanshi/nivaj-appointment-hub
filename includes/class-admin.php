<?php
/**
 * Admin - menu pages and asset enqueuing.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_menu_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Register admin menu pages.
	 */
	public function add_menu_pages(): void {
		add_menu_page(
			__( 'Nivaj Appointment Hub', 'nivaj-appointment-hub' ),
			__( 'Appointment Hub', 'nivaj-appointment-hub' ),
			'manage_options',
			'nivaj-ah-dashboard',
			[ $this, 'render_page' ],
			'dashicons-calendar-alt',
			26
		);

		add_submenu_page(
			'nivaj-ah-dashboard',
			__( 'Dashboard', 'nivaj-appointment-hub' ),
			__( 'Dashboard', 'nivaj-appointment-hub' ),
			'manage_options',
			'nivaj-ah-dashboard',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'nivaj-ah-dashboard',
			__( 'Booking Types', 'nivaj-appointment-hub' ),
			__( 'Booking Types', 'nivaj-appointment-hub' ),
			'manage_options',
			'nivaj-ah-booking-types',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'nivaj-ah-dashboard',
			__( 'Bookings', 'nivaj-appointment-hub' ),
			__( 'Bookings', 'nivaj-appointment-hub' ),
			'manage_options',
			'nivaj-ah-bookings',
			[ $this, 'render_page' ]
		);

		add_submenu_page(
			'nivaj-ah-dashboard',
			__( 'Settings', 'nivaj-appointment-hub' ),
			__( 'Settings', 'nivaj-appointment-hub' ),
			'manage_options',
			'nivaj-ah-settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Render admin page - a React mount point.
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin page render; $page is sanitized and only used for display.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'nivaj-ah-dashboard';
		echo '<div id="nivaj-ah-admin-root" data-page="' . esc_attr( $page ) . '"></div>';
	}

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_assets( string $hook ): void {
		// Only load on our pages.
		$our_pages = [
			'toplevel_page_nivaj-ah-dashboard',
			'appointment-hub_page_nivaj-ah-booking-types',
			'appointment-hub_page_nivaj-ah-bookings',
			'appointment-hub_page_nivaj-ah-settings',
		];

		if ( ! in_array( $hook, $our_pages, true ) ) {
			return;
		}

		// Enqueue WordPress Media Library for image uploads.
		wp_enqueue_media();

		$asset_file = NIVAJ_AH_PATH . 'assets/build/admin-app.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			add_action( 'admin_notices', function () {
				echo '<div class="notice notice-warning"><p>';
				echo esc_html__( 'Nivaj Appointment Hub: Admin assets not built. Please run npm install && npm run build in the plugin directory.', 'nivaj-appointment-hub' );
				echo '</p></div>';
			} );
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'nivaj-ah-admin-app',
			NIVAJ_AH_URL . 'assets/build/admin-app.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'nivaj-ah-admin-app',
			NIVAJ_AH_URL . 'assets/build/admin-app.css',
			[ 'wp-components' ],
			$asset['version']
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only; sanitized value passed to JS for routing.
		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		wp_localize_script( 'nivaj-ah-admin-app', 'nivajAhAdmin', [
			'restUrl'   => esc_url_raw( rest_url( 'nivaj-ah/v1/admin/' ) ),
			'publicUrl' => esc_url_raw( rest_url( 'nivaj-ah/v1/' ) ),
			'restNonce' => wp_create_nonce( 'wp_rest' ),
			'page'      => $current_page,
			'siteUrl'   => esc_url_raw( home_url() ),
		] );
	}
}
