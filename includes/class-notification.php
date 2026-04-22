<?php
/**
 * Notification handler - emails for bookings.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Querying custom bookings table for reminders.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Reminder scans must be authoritative; cache would cause missed or duplicate reminders.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name comes from $wpdb->prefix + static literal.
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- $table is $wpdb->prefix + static literal.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notification {

	public function __construct() {
		add_action( 'nivaj_ah_booking_created', [ $this, 'send_confirmation' ] );
		add_action( 'nivaj_ah_booking_created', [ $this, 'send_admin_alert' ] );
		add_action( 'nivaj_ah_booking_cancelled', [ $this, 'send_cancellation' ] );
	}

	/**
	 * Send confirmation email to customer.
	 */
	public function send_confirmation( int $booking_id ): void {
		$data = $this->get_email_data( $booking_id );
		if ( ! $data ) {
			return;
		}

		$settings = $data['settings'];
		$subject  = $settings['confirmation_subject'];
		$body     = $this->render_email( 'booking-confirmed', $data );

		$this->send( $data['booking']['customer_email'], $subject, $body );
	}

	/**
	 * Send alert email to admin.
	 */
	public function send_admin_alert( int $booking_id ): void {
		$data = $this->get_email_data( $booking_id );
		if ( ! $data ) {
			return;
		}

		$settings = $data['settings'];
		$subject  = $settings['admin_alert_subject'];
		$body     = $this->render_email( 'booking-admin-alert', $data );

		$this->send( $settings['admin_email'], $subject, $body );
	}

	/**
	 * Send cancellation email to customer.
	 */
	public function send_cancellation( int $booking_id ): void {
		$data = $this->get_email_data( $booking_id );
		if ( ! $data ) {
			return;
		}

		$settings = $data['settings'];
		$subject  = $settings['cancellation_subject'];
		$body     = $this->render_email( 'booking-cancelled', $data );

		$this->send( $data['booking']['customer_email'], $subject, $body );
	}

	/**
	 * Process reminder emails (cron callback).
	 */
	public static function process_reminders(): void {
		global $wpdb;
		$table = $wpdb->prefix . 'nivaj_ah_bookings';

		$settings       = Settings::get_all();
		$reminder_hours = (int) $settings['reminder_hours'];

		if ( $reminder_hours <= 0 ) {
			return;
		}

		$site_tz = wp_timezone();
		$now     = new \DateTime( 'now', $site_tz );
		$cutoff  = ( clone $now )->modify( "+{$reminder_hours} hours" );

		// Find bookings that need reminders:
		// - Not yet reminded
		// - Status is confirmed
		// - Booking date/time is within the reminder window.
		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE reminder_sent = 0
				AND status = 'confirmed'
				AND CONCAT(booking_date, ' ', start_time) <= %s
				AND CONCAT(booking_date, ' ', start_time) > %s
				LIMIT 50",
				$cutoff->format( 'Y-m-d H:i:s' ),
				$now->format( 'Y-m-d H:i:s' )
			),
			ARRAY_A
		);

		if ( empty( $bookings ) ) {
			return;
		}

		$notification = new self();

		foreach ( $bookings as $booking ) {
			$data = $notification->get_email_data( (int) $booking['id'] );
			if ( ! $data ) {
				continue;
			}

			$subject = $settings['reminder_subject'];
			$body    = $notification->render_email( 'booking-reminder', $data );
			$sent    = $notification->send( $booking['customer_email'], $subject, $body );

			if ( $sent ) {
				$wpdb->update(
					$table,
					[ 'reminder_sent' => 1 ],
					[ 'id' => $booking['id'] ]
				);
			}
		}
	}

	/**
	 * Prepare email template data.
	 */
	private function get_email_data( int $booking_id ): ?array {
		$booking = BookingManager::get( $booking_id );
		if ( ! $booking ) {
			return null;
		}

		$booking_type = BookingType::get( (int) $booking['booking_type_id'] );
		$settings     = Settings::get_all();

		return [
			'booking'      => $booking,
			'booking_type' => $booking_type,
			'settings'     => $settings,
			'site_name'    => get_bloginfo( 'name' ),
			'site_url'     => home_url(),
		];
	}

	/**
	 * Render an email template.
	 */
	private function render_email( string $template, array $data ): string {
		$allowed_templates = [
			'booking-confirmed',
			'booking-admin-alert',
			'booking-cancelled',
			'booking-reminder',
		];

		if ( ! in_array( $template, $allowed_templates, true ) ) {
			return '';
		}

		// Check for theme override.
		$theme_path  = get_stylesheet_directory() . '/nivaj-appointment-hub/emails/' . $template . '.php';
		$plugin_path = NIVAJ_AH_PATH . 'templates/emails/' . $template . '.php';

		$path = file_exists( $theme_path ) ? $theme_path : $plugin_path;

		if ( ! file_exists( $path ) ) {
			return '';
		}

		// Make data available to template as individual variables.
		$booking      = $data['booking'];
		$booking_type = $data['booking_type'];
		$settings     = $data['settings'];
		$site_name    = $data['site_name'];
		$site_url     = $data['site_url'];

		ob_start();
		include $path;
		return ob_get_clean();
	}

	/**
	 * Send an HTML email.
	 */
	private function send( string $to, string $subject, string $body ): bool {
		$settings = Settings::get_all();
		$headers  = [ 'Content-Type: text/html; charset=UTF-8' ];

		if ( ! empty( $settings['from_name'] ) && ! empty( $settings['from_email'] ) ) {
			$from_name  = str_replace( [ "\r", "\n" ], '', $settings['from_name'] );
			$from_email = str_replace( [ "\r", "\n" ], '', $settings['from_email'] );
			$headers[]  = 'From: ' . $from_name . ' <' . $from_email . '>';
		}

		$sent = wp_mail( $to, $subject, $body, $headers );

		if ( ! $sent && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'Nivaj Appointment Hub: Failed to send email to %s (subject: %s)', $to, $subject ) );
		}

		return $sent;
	}
}
