<?php
/**
 * Webhook handler - fires HTTP webhooks on booking events.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Webhook {

	public function __construct() {
		add_action( 'nah_booking_created', [ $this, 'on_booking_created' ], 20 );
		add_action( 'nah_booking_cancelled', [ $this, 'on_booking_cancelled' ], 20 );
		add_action( 'nah_booking_status_changed', [ $this, 'on_status_changed' ], 20, 2 );
	}

	/**
	 * Fire webhook when a booking is created.
	 */
	public function on_booking_created( int $booking_id ): void {
		$this->fire( $booking_id, 'booking_created' );
	}

	/**
	 * Fire webhook when a booking is cancelled.
	 */
	public function on_booking_cancelled( int $booking_id ): void {
		$this->fire( $booking_id, 'booking_cancelled' );
	}

	/**
	 * Fire webhook when booking status changes.
	 */
	public function on_status_changed( int $booking_id, string $new_status ): void {
		// Avoid duplicate fire for cancellation (already handled by on_booking_cancelled).
		if ( 'cancelled' === $new_status ) {
			return;
		}
		$this->fire( $booking_id, 'booking_status_changed', [ 'new_status' => $new_status ] );
	}

	/**
	 * Build payload and send webhook.
	 */
	private function fire( int $booking_id, string $event, array $extra = [] ): void {
		$settings = Settings::get_all();

		if ( ! $settings['webhook_enabled'] || empty( $settings['webhook_url'] ) ) {
			return;
		}

		$payload = $this->build_payload( $booking_id, $event, $extra );
		if ( ! $payload ) {
			return;
		}

		$this->send( $settings['webhook_url'], $payload, $settings['webhook_secret'] );
	}

	/**
	 * Build the webhook payload.
	 */
	private function build_payload( int $booking_id, string $event, array $extra = [] ): ?array {
		$booking = BookingManager::get( $booking_id );
		if ( ! $booking ) {
			return null;
		}

		$type = BookingType::get( (int) $booking['booking_type_id'] );

		$payload = array_merge( [
			'event'           => $event,
			'booking_id'      => (int) $booking['id'],
			'service_name'    => $type ? $type['title'] : '',
			'duration'        => $type ? (int) $type['duration'] : 0,
			'date'            => $booking['booking_date'],
			'time'            => substr( $booking['start_time'], 0, 5 ),
			'customer_name'   => $booking['customer_name'],
			'customer_email'  => $booking['customer_email'],
			'status'          => $booking['status'],
			'booking_type_id' => (int) $booking['booking_type_id'],
			'timestamp'       => current_time( 'c' ),
		], $extra );

		// Include custom field values if available.
		$field_values = CustomField::get_values( $booking_id );
		if ( ! empty( $field_values ) ) {
			$payload['custom_fields'] = $field_values;
		}

		return $payload;
	}

	/**
	 * Send the webhook POST request.
	 */
	private function send( string $url, array $payload, string $secret ): void {
		$body    = wp_json_encode( $payload );
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( ! empty( $secret ) ) {
			$headers['X-NAH-Signature'] = hash_hmac( 'sha256', $body, $secret );
		}

		wp_remote_post( $url, [
			'body'     => $body,
			'headers'  => $headers,
			'timeout'  => 10,
			'blocking' => false,
		] );
	}

	/**
	 * Send a test webhook with sample data.
	 */
	public static function send_test(): array {
		$settings = Settings::get_all();

		if ( empty( $settings['webhook_url'] ) ) {
			return [ 'success' => false, 'message' => 'No webhook URL configured.' ];
		}

		$payload = [
			'event'           => 'test',
			'booking_id'      => 0,
			'service_name'    => 'Test Service',
			'duration'        => 30,
			'date'            => gmdate( 'Y-m-d' ),
			'time'            => '10:00',
			'customer_name'   => 'Test Customer',
			'customer_email'  => 'test@example.com',
			'status'          => 'confirmed',
			'booking_type_id' => 0,
			'timestamp'       => current_time( 'c' ),
		];

		$body    = wp_json_encode( $payload );
		$headers = [ 'Content-Type' => 'application/json' ];

		if ( ! empty( $settings['webhook_secret'] ) ) {
			$headers['X-NAH-Signature'] = hash_hmac( 'sha256', $body, $settings['webhook_secret'] );
		}

		$response = wp_remote_post( $settings['webhook_url'], [
			'body'     => $body,
			'headers'  => $headers,
			'timeout'  => 15,
			'blocking' => true,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'success' => false, 'message' => $response->get_error_message() ];
		}

		$code = wp_remote_retrieve_response_code( $response );
		return [
			'success' => $code >= 200 && $code < 300,
			'message' => sprintf( 'Webhook returned HTTP %d.', $code ),
		];
	}
}
