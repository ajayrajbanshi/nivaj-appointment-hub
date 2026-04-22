<?php
/**
 * Public REST API endpoints for the booking flow.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestApi {

	const NAMESPACE = 'nivaj-ah/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		// GET /booking-types - list active booking types.
		register_rest_route( self::NAMESPACE, '/booking-types', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_booking_types' ],
			'permission_callback' => '__return_true',
		] );

		// GET /booking-types/{id}/available-dates?month=YYYY-MM
		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)/available-dates', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_available_dates' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id'    => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'month' => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => function ( $value ) {
						return preg_match( '/^\d{4}-\d{2}$/', $value );
					},
				],
			],
		] );

		// GET /booking-types/{id}/slots?date=YYYY-MM-DD
		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)/slots', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_slots' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id'   => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'date' => [
					'type'              => 'string',
					'required'          => true,
					'validate_callback' => function ( $value ) {
						return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
					},
				],
			],
		] );

		// POST /bookings - create a booking.
		register_rest_route( self::NAMESPACE, '/bookings', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'create_booking' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'booking_type_id' => [
					'type'              => 'integer',
					'required'          => true,
					'sanitize_callback' => 'absint',
				],
				'date'            => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value );
					},
				],
				'start_time'      => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
					'validate_callback' => function ( $value ) {
						return (bool) preg_match( '/^\d{2}:\d{2}$/', $value );
					},
				],
				'customer_name'   => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'customer_email'  => [
					'type'              => 'string',
					'required'          => true,
					'sanitize_callback' => 'sanitize_email',
				],
				'customer_phone'  => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'customer_notes'  => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_textarea_field',
				],
				'timezone'        => [
					'type'              => 'string',
					'required'          => false,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'custom_fields'   => [
					'type'     => 'object',
					'required' => false,
					'default'  => [],
				],
			],
		] );
	}

	/**
	 * GET /booking-types
	 */
	public function get_booking_types( \WP_REST_Request $request ): \WP_REST_Response {
		$types = BookingType::get_all( true );

		$data = array_map( function ( $type ) {
			$image_id  = ! empty( $type['banner_image_id'] ) ? (int) $type['banner_image_id'] : 0;
			$image_url = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';

			return [
				'id'            => (int) $type['id'],
				'title'         => $type['title'],
				'slug'          => $type['slug'],
				'description'   => $type['description'],
				'duration'      => (int) $type['duration'],
				'color'         => $type['color'],
				'location_type' => $type['location_type'],
				'image_url'     => $image_url ?: '',
				'custom_fields' => CustomField::get_by_type( (int) $type['id'] ),
			];
		}, $types );

		return new \WP_REST_Response( $data, 200 );
	}

	/**
	 * GET /booking-types/{id}/available-dates?month=YYYY-MM
	 */
	public function get_available_dates( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = $request->get_param( 'id' );
		$month = $request->get_param( 'month' );

		$parts = explode( '-', $month );
		$year  = (int) $parts[0];
		$mon   = (int) $parts[1];

		if ( $mon < 1 || $mon > 12 ) {
			return new \WP_REST_Response( [ 'message' => __( 'Invalid month.', 'nivaj-appointment-hub' ) ], 400 );
		}

		$dates = AvailabilityEngine::get_available_dates( $id, $year, $mon );

		return new \WP_REST_Response( [
			'dates' => $dates,
			'month' => $month,
		], 200 );
	}

	/**
	 * GET /booking-types/{id}/slots?date=YYYY-MM-DD
	 */
	public function get_slots( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = $request->get_param( 'id' );
		$date = $request->get_param( 'date' );

		$slots = AvailabilityEngine::get_available_slots( $id, $date );

		return new \WP_REST_Response( [
			'slots' => $slots,
			'date'  => $date,
		], 200 );
	}

	/**
	 * POST /bookings
	 */
	public function create_booking( \WP_REST_Request $request ): \WP_REST_Response {
		// Rate limiting: max 5 booking attempts per IP per minute.
		$ip  = isset( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '0.0.0.0';
		$key = 'nivaj_ah_rate_' . wp_hash( $ip );
		$count = (int) get_transient( $key );
		if ( $count >= 5 ) {
			return new \WP_REST_Response( [
				'message' => __( 'Too many requests. Please try again later.', 'nivaj-appointment-hub' ),
			], 429 );
		}
		set_transient( $key, $count + 1, 60 );

		$booking_type_id = $request->get_param( 'booking_type_id' );
		$date            = $request->get_param( 'date' );
		$start_time      = $request->get_param( 'start_time' );

		// Validate slot is within available working hours before creating.
		$booking_type = BookingType::get( (int) $booking_type_id );
		if ( ! $booking_type || ! $booking_type['is_active'] ) {
			return new \WP_REST_Response( [
				'message' => __( 'Invalid or inactive booking type.', 'nivaj-appointment-hub' ),
			], 400 );
		}

		$duration   = (int) $booking_type['duration'];
		$parts      = explode( ':', $start_time );
		$end_mins   = min( ( (int) $parts[0] * 60 ) + (int) $parts[1] + $duration, 1439 );
		$end_time_calc = sprintf( '%02d:%02d', intdiv( $end_mins, 60 ), $end_mins % 60 );

		if ( ! AvailabilityEngine::is_slot_available( (int) $booking_type_id, $date, $start_time, $end_time_calc ) ) {
			return new \WP_REST_Response( [
				'message' => __( 'This time slot is no longer available.', 'nivaj-appointment-hub' ),
			], 409 );
		}

		// Validate custom fields.
		$custom_fields = $request->get_param( 'custom_fields' ) ?: [];
		if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
			$validation_error = CustomField::validate( (int) $booking_type_id, $custom_fields );
			if ( $validation_error ) {
				return new \WP_REST_Response( [ 'message' => $validation_error ], 400 );
			}
		}

		$result = BookingManager::create( [
			'booking_type_id' => $booking_type_id,
			'booking_date'    => $date,
			'start_time'      => $start_time,
			'customer_name'   => $request->get_param( 'customer_name' ),
			'customer_email'  => $request->get_param( 'customer_email' ),
			'customer_phone'  => $request->get_param( 'customer_phone' ) ?? '',
			'customer_notes'  => $request->get_param( 'customer_notes' ) ?? '',
			'timezone'        => $request->get_param( 'timezone' ) ?? 'UTC',
		] );

		if ( ! $result['success'] ) {
			return new \WP_REST_Response( [
				'message' => $result['error'],
			], 409 );
		}

		// Save custom field values.
		if ( is_array( $custom_fields ) && ! empty( $custom_fields ) ) {
			CustomField::save_values( $result['booking_id'], $custom_fields );
		}

		// Get the full booking for the response.
		$booking      = BookingManager::get( $result['booking_id'] );
		$booking_type = BookingType::get( (int) $booking['booking_type_id'] );

		$response_data = [
			'message'    => __( 'Booking created successfully.', 'nivaj-appointment-hub' ),
			'booking_id' => $result['booking_id'],
			'booking'    => [
				'id'            => (int) $booking['id'],
				'date'          => $booking['booking_date'],
				'start_time'    => substr( $booking['start_time'], 0, 5 ),
				'end_time'      => substr( $booking['end_time'], 0, 5 ),
				'status'        => $booking['status'],
				'booking_type'  => $booking_type ? $booking_type['title'] : '',
				'customer_name' => $booking['customer_name'],
			],
		];

		// DataLayer event for GTM.
		if ( Settings::get( 'datalayer_enabled' ) ) {
			$response_data['datalayer_event'] = [
				'event'           => 'booking_appointment_confirmation',
				'booking_id'      => (int) $booking['id'],
				'service_name'    => $booking_type ? $booking_type['title'] : '',
				'duration'        => $booking_type ? (int) $booking_type['duration'] : 0,
				'date'            => $booking['booking_date'],
				'time'            => substr( $booking['start_time'], 0, 5 ),
				'customer_name'   => $booking['customer_name'],
				'customer_email'  => $booking['customer_email'],
				'status'          => $booking['status'],
				'booking_type_id' => (int) $booking['booking_type_id'],
			];
		}

		return new \WP_REST_Response( $response_data, 201 );
	}
}
