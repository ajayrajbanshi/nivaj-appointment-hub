<?php
/**
 * Plugin settings management.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_KEY = 'nah_settings';

	/**
	 * Get all settings merged with defaults.
	 */
	public static function get_all(): array {
		return wp_parse_args( get_option( self::OPTION_KEY, [] ), self::defaults() );
	}

	/**
	 * Get a single setting value.
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::get_all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Update settings (merges with existing).
	 */
	public static function update( array $settings ): bool {
		$sanitized = self::sanitize( $settings );
		return update_option( self::OPTION_KEY, wp_parse_args( $sanitized, self::get_all() ) );
	}

	/**
	 * Default settings values.
	 */
	public static function defaults(): array {
		return [
			'business_name'        => get_bloginfo( 'name' ),
			'from_email'           => get_option( 'admin_email' ),
			'from_name'            => get_bloginfo( 'name' ),
			'admin_email'          => get_option( 'admin_email' ),
			'timezone'             => wp_timezone_string(),
			'date_format'          => get_option( 'date_format' ),
			'time_format'          => get_option( 'time_format' ),
			'booking_page_id'      => 0,
			'confirmation_subject' => __( 'Your booking is confirmed', 'nivaj-appointment-hub' ),
			'reminder_subject'     => __( 'Reminder: Your appointment is tomorrow', 'nivaj-appointment-hub' ),
			'cancellation_subject' => __( 'Your booking has been cancelled', 'nivaj-appointment-hub' ),
			'admin_alert_subject'  => __( 'New booking received', 'nivaj-appointment-hub' ),
			'reminder_hours'       => 24,
			'auto_confirm'         => true,
			'min_booking_notice'   => 60,
			'max_booking_advance'  => 60,
			// Integrations.
			'webhook_enabled'      => false,
			'webhook_url'          => '',
			'webhook_secret'       => '',
			'datalayer_enabled'    => true,
			// Popup widget.
			'popup_enabled'        => false,
			'popup_button_text'    => __( 'Book Now', 'nivaj-appointment-hub' ),
			'popup_button_color'   => '#2563eb',
			'popup_button_position'=> 'bottom-right',
			'popup_booking_type'   => '',
			'popup_theme'          => 'light',
			'popup_fullscreen'     => false,
		];
	}

	/**
	 * Sanitize settings input.
	 */
	private static function sanitize( array $input ): array {
		$sanitized = [];

		$text_fields = [
			'business_name',
			'from_name',
			'confirmation_subject',
			'reminder_subject',
			'cancellation_subject',
			'admin_alert_subject',
			'date_format',
			'time_format',
			'timezone',
			'webhook_secret',
			'popup_button_text',
			'popup_button_color',
			'popup_booking_type',
			'popup_theme',
		];

		foreach ( $text_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $input[ $field ] );
			}
		}

		$email_fields = [ 'from_email', 'admin_email' ];
		foreach ( $email_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_email( $input[ $field ] );
			}
		}

		$int_fields = [
			'booking_page_id',
			'reminder_hours',
			'min_booking_notice',
			'max_booking_advance',
		];
		foreach ( $int_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = absint( $input[ $field ] );
			}
		}

		$bool_fields = [ 'auto_confirm', 'webhook_enabled', 'datalayer_enabled', 'popup_enabled', 'popup_fullscreen' ];
		foreach ( $bool_fields as $field ) {
			if ( isset( $input[ $field ] ) ) {
				$sanitized[ $field ] = (bool) $input[ $field ];
			}
		}

		// URL fields.
		if ( isset( $input['webhook_url'] ) ) {
			$sanitized['webhook_url'] = esc_url_raw( $input['webhook_url'] );
		}
		// Popup position whitelist.
		if ( isset( $input['popup_button_position'] ) ) {
			$valid_positions = [ 'bottom-right', 'bottom-left' ];
			$sanitized['popup_button_position'] = in_array( $input['popup_button_position'], $valid_positions, true )
				? $input['popup_button_position']
				: 'bottom-right';
		}

		return $sanitized;
	}
}
