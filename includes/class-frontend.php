<?php
/**
 * Frontend - shortcode, Gutenberg block, and popup widget.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	private bool $assets_enqueued = false;

	public function __construct() {
		add_shortcode( 'nah_booking', [ $this, 'render_shortcode' ] );
		add_action( 'init', [ $this, 'register_block' ] );
		add_action( 'wp', [ $this, 'maybe_setup_popup' ] );
	}

	/**
	 * Render the [nah_booking] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 */
	public function render_shortcode( $atts ): string {
		$atts = shortcode_atts(
			[
				'type'  => '',     // Booking type slug or ID.
				'theme' => 'light', // light|dark.
			],
			$atts,
			'nah_booking'
		);

		$this->enqueue_frontend_assets();

		$data_attrs = '';
		if ( ! empty( $atts['type'] ) ) {
			$data_attrs .= ' data-booking-type="' . esc_attr( $atts['type'] ) . '"';
		}
		$data_attrs .= ' data-theme="' . esc_attr( $atts['theme'] ) . '"';

		return '<div class="nah-booking-widget"' . $data_attrs . '></div>';
	}

	/**
	 * Register the Gutenberg block.
	 */
	public function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$asset_file = NAH_PATH . 'assets/build/block-editor.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_register_script(
			'nah-block-editor',
			NAH_URL . 'assets/build/block-editor.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		register_block_type( 'nah/appointment-booking', [
			'editor_script'   => 'nah-block-editor',
			'render_callback' => [ $this, 'render_block' ],
			'attributes'      => [
				'bookingType' => [
					'type'    => 'string',
					'default' => '',
				],
				'theme'       => [
					'type'    => 'string',
					'default' => 'light',
				],
			],
		] );
	}

	/**
	 * Render the Gutenberg block on the frontend.
	 */
	public function render_block( array $attributes ): string {
		return $this->render_shortcode( [
			'type'  => $attributes['bookingType'] ?? '',
			'theme' => $attributes['theme'] ?? 'light',
		] );
	}

	/**
	 * Setup popup widget if enabled.
	 */
	public function maybe_setup_popup(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! Settings::get( 'popup_enabled' ) ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_popup_container' ] );
	}

	/**
	 * Render the popup widget container in the footer.
	 */
	public function render_popup_container(): void {
		$config = [
			'buttonText'     => Settings::get( 'popup_button_text' ),
			'buttonColor'    => Settings::get( 'popup_button_color' ),
			'buttonPosition' => Settings::get( 'popup_button_position' ),
			'bookingType'    => Settings::get( 'popup_booking_type' ),
			'theme'          => Settings::get( 'popup_theme' ),
			'fullscreen'     => (bool) Settings::get( 'popup_fullscreen' ),
		];

		echo '<div class="nah-popup-widget" data-config="' . esc_attr( wp_json_encode( $config ) ) . '"></div>';
	}

	/**
	 * Enqueue frontend booking widget assets.
	 */
	public function enqueue_frontend_assets(): void {
		if ( $this->assets_enqueued ) {
			return;
		}

		$asset_file = NAH_PATH . 'assets/build/booking-widget.asset.php';
		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		wp_enqueue_script(
			'nah-booking-widget',
			NAH_URL . 'assets/build/booking-widget.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		wp_enqueue_style(
			'nah-booking-widget',
			NAH_URL . 'assets/build/booking-widget.css',
			[],
			$asset['version']
		);

		// Build prefill data from URL parameters.
		$prefill = [];
		$prefill_keys = [
			'nah_type'  => 'type',
			'nah_date'  => 'date',
			'nah_name'  => 'name',
			'nah_email' => 'email',
			'nah_phone' => 'phone',
		];
		foreach ( $prefill_keys as $param => $key ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( isset( $_GET[ $param ] ) ) {
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$prefill[ $key ] = sanitize_text_field( wp_unslash( $_GET[ $param ] ) );
			}
		}

		wp_localize_script( 'nah-booking-widget', 'nahBooking', [
			'restUrl'      => esc_url_raw( rest_url( 'nah/v1/' ) ),
			'restNonce'    => wp_create_nonce( 'wp_rest' ),
			'siteTimezone' => wp_timezone_string(),
			'dateFormat'   => get_option( 'date_format' ),
			'timeFormat'   => get_option( 'time_format' ),
			'prefill'      => ! empty( $prefill ) ? $prefill : null,
		] );

		$this->assets_enqueued = true;
	}
}
