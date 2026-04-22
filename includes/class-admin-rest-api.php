<?php
/**
 * Admin REST API endpoints.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AdminRestApi {

	const NAMESPACE = 'nivaj-ah/v1/admin';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Check if current user has admin permissions.
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	public function register_routes(): void {
		$admin_args = [ 'permission_callback' => [ $this, 'check_permission' ] ];

		// ---- Booking Types ----

		register_rest_route( self::NAMESPACE, '/booking-types', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_booking_types' ],
				...$admin_args,
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'create_booking_type' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_booking_type' ],
				...$admin_args,
			],
			[
				'methods'  => 'PUT',
				'callback' => [ $this, 'update_booking_type' ],
				...$admin_args,
			],
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'delete_booking_type' ],
				...$admin_args,
			],
		] );

		// ---- Availability Rules ----

		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)/availability', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_availability' ],
				...$admin_args,
			],
			[
				'methods'  => 'PUT',
				'callback' => [ $this, 'update_availability' ],
				...$admin_args,
			],
		] );

		// ---- Date Overrides ----

		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)/overrides', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_overrides' ],
				...$admin_args,
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'create_override' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/overrides/(?P<id>\d+)', [
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'delete_override' ],
				...$admin_args,
			],
		] );

		// ---- Bookings ----

		register_rest_route( self::NAMESPACE, '/bookings', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_bookings' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/bookings/(?P<id>\d+)', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_booking' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/bookings/(?P<id>\d+)/status', [
			[
				'methods'  => 'PATCH',
				'callback' => [ $this, 'update_booking_status' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/bookings/export', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'export_bookings' ],
				...$admin_args,
			],
		] );

		// ---- Stats ----

		register_rest_route( self::NAMESPACE, '/stats', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_stats' ],
				...$admin_args,
			],
		] );

		// ---- Analytics ----

		register_rest_route( self::NAMESPACE, '/analytics', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_analytics' ],
				...$admin_args,
			],
		] );

		// ---- Custom Fields ----

		register_rest_route( self::NAMESPACE, '/booking-types/(?P<id>\d+)/fields', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_custom_fields' ],
				...$admin_args,
			],
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'create_custom_field' ],
				...$admin_args,
			],
		] );

		register_rest_route( self::NAMESPACE, '/fields/(?P<id>\d+)', [
			[
				'methods'  => 'PUT',
				'callback' => [ $this, 'update_custom_field' ],
				...$admin_args,
			],
			[
				'methods'  => 'DELETE',
				'callback' => [ $this, 'delete_custom_field' ],
				...$admin_args,
			],
		] );

		// ---- Webhook Test ----

		register_rest_route( self::NAMESPACE, '/webhook-test', [
			[
				'methods'  => 'POST',
				'callback' => [ $this, 'test_webhook' ],
				...$admin_args,
			],
		] );

		// ---- Settings ----

		register_rest_route( self::NAMESPACE, '/settings', [
			[
				'methods'  => 'GET',
				'callback' => [ $this, 'get_settings' ],
				...$admin_args,
			],
			[
				'methods'  => 'PUT',
				'callback' => [ $this, 'update_settings' ],
				...$admin_args,
			],
		] );
	}

	// ---- Booking Types ----

	public function get_booking_types( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( BookingType::get_all(), 200 );
	}

	public function get_booking_type( \WP_REST_Request $request ): \WP_REST_Response {
		$type = BookingType::get( (int) $request->get_param( 'id' ) );
		if ( ! $type ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		// Include availability rules.
		$type['availability_rules'] = BookingType::get_availability_rules( (int) $type['id'] );

		return new \WP_REST_Response( $type, 200 );
	}

	public function create_booking_type( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		if ( empty( $data['title'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Title is required.' ], 400 );
		}

		$id = BookingType::create( $data );

		if ( false === $id ) {
			return new \WP_REST_Response( [ 'message' => 'Failed to create booking type.' ], 500 );
		}

		$type = BookingType::get( $id );
		$type['availability_rules'] = BookingType::get_availability_rules( $id );

		return new \WP_REST_Response( $type, 201 );
	}

	public function update_booking_type( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		$existing = BookingType::get( $id );
		if ( ! $existing ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		$result = BookingType::update( $id, $data );

		if ( ! $result ) {
			return new \WP_REST_Response( [ 'message' => 'Failed to update.' ], 500 );
		}

		$type = BookingType::get( $id );
		$type['availability_rules'] = BookingType::get_availability_rules( $id );

		return new \WP_REST_Response( $type, 200 );
	}

	public function delete_booking_type( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		$existing = BookingType::get( $id );
		if ( ! $existing ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		// Check for existing bookings.
		$booking_count = BookingManager::count( [
			'booking_type_id' => $id,
			'status'          => [ BookingManager::STATUS_PENDING, BookingManager::STATUS_CONFIRMED ],
		] );

		if ( $booking_count > 0 ) {
			// Soft delete - deactivate instead.
			BookingType::update( $id, [ 'is_active' => 0 ] );
			return new \WP_REST_Response( [
				'message' => 'Booking type deactivated (has active bookings).',
				'soft_delete' => true,
			], 200 );
		}

		BookingType::delete( $id );

		return new \WP_REST_Response( [ 'message' => 'Deleted.' ], 200 );
	}

	// ---- Availability ----

	public function get_availability( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$rules = BookingType::get_availability_rules( $id );

		return new \WP_REST_Response( $rules, 200 );
	}

	public function update_availability( \WP_REST_Request $request ): \WP_REST_Response {
		$id    = (int) $request->get_param( 'id' );
		$rules = $request->get_json_params();

		if ( ! is_array( $rules ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid data.' ], 400 );
		}

		BookingType::set_availability_rules( $id, $rules );

		return new \WP_REST_Response( BookingType::get_availability_rules( $id ), 200 );
	}

	// ---- Date Overrides ----

	public function get_overrides( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$from = $request->get_param( 'from' ) ?? gmdate( 'Y-m-01' );
		$to   = $request->get_param( 'to' ) ?? gmdate( 'Y-m-t' );

		$overrides = BookingType::get_date_overrides( $id, $from, $to );

		return new \WP_REST_Response( $overrides, 200 );
	}

	public function create_override( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		if ( empty( $data['override_date'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Date is required.' ], 400 );
		}

		$override_id = BookingType::add_date_override( $id, $data );

		if ( false === $override_id ) {
			return new \WP_REST_Response( [ 'message' => 'Failed to create override.' ], 500 );
		}

		return new \WP_REST_Response( [ 'id' => $override_id ], 201 );
	}

	public function delete_override( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		BookingType::delete_date_override( $id );

		return new \WP_REST_Response( [ 'message' => 'Deleted.' ], 200 );
	}

	// ---- Bookings ----

	public function get_bookings( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = [
			'booking_type_id' => $request->get_param( 'type' ),
			'date_from'       => $request->get_param( 'from' ),
			'date_to'         => $request->get_param( 'to' ),
			'status'          => $request->get_param( 'status' ),
			'search'          => $request->get_param( 'search' ),
		];

		$filters  = array_filter( $filters );
		$per_page = min( max( (int) ( $request->get_param( 'per_page' ) ?: 20 ), 1 ), 100 );
		$page     = max( (int) ( $request->get_param( 'page' ) ?: 1 ), 1 );

		$result = BookingManager::get_all( $filters, $per_page, $page );

		return new \WP_REST_Response( $result, 200 );
	}

	public function get_booking( \WP_REST_Request $request ): \WP_REST_Response {
		$booking = BookingManager::get( (int) $request->get_param( 'id' ) );

		if ( ! $booking ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		$booking_type = BookingType::get( (int) $booking['booking_type_id'] );
		$booking['booking_type']  = $booking_type;
		$booking['custom_fields'] = CustomField::get_values( (int) $booking['id'] );

		return new \WP_REST_Response( $booking, 200 );
	}

	public function update_booking_status( \WP_REST_Request $request ): \WP_REST_Response {
		$id     = (int) $request->get_param( 'id' );
		$data   = $request->get_json_params();
		$status = $data['status'] ?? '';

		if ( ! in_array( $status, BookingManager::VALID_STATUSES, true ) ) {
			return new \WP_REST_Response( [ 'message' => 'Invalid status.' ], 400 );
		}

		$result = BookingManager::update_status( $id, $status );

		if ( ! $result ) {
			return new \WP_REST_Response( [ 'message' => 'Failed to update status.' ], 500 );
		}

		return new \WP_REST_Response( BookingManager::get( $id ), 200 );
	}

	public function export_bookings( \WP_REST_Request $request ): \WP_REST_Response {
		$filters = [
			'booking_type_id' => $request->get_param( 'type' ),
			'date_from'       => $request->get_param( 'from' ),
			'date_to'         => $request->get_param( 'to' ),
			'status'          => $request->get_param( 'status' ),
		];

		$filters  = array_filter( $filters );
		$bookings = BookingManager::get_for_export( $filters );

		$csv_rows = [];
		$csv_rows[] = [
			'ID', 'Booking Type', 'Date', 'Start Time', 'End Time', 'Status',
			'Customer Name', 'Customer Email', 'Customer Phone', 'Notes', 'Created',
		];

		foreach ( $bookings as $b ) {
			$csv_rows[] = [
				$b['id'],
				$b['booking_type_title'] ?? '',
				$b['booking_date'],
				$b['start_time'],
				$b['end_time'],
				$b['status'],
				$b['customer_name'],
				$b['customer_email'],
				$b['customer_phone'],
				$b['customer_notes'],
				$b['created_at'],
			];
		}

		return new \WP_REST_Response( [
			'rows'     => $csv_rows,
			'filename' => 'bookings-' . gmdate( 'Y-m-d' ) . '.csv',
		], 200 );
	}

	// ---- Stats ----

	public function get_stats( \WP_REST_Request $request ): \WP_REST_Response {
		$days = (int) ( $request->get_param( 'days' ) ?: 30 );
		return new \WP_REST_Response( BookingManager::get_stats( $days ), 200 );
	}

	public function get_analytics( \WP_REST_Request $request ): \WP_REST_Response {
		$days = (int) ( $request->get_param( 'days' ) ?: 30 );
		return new \WP_REST_Response( BookingManager::get_analytics( $days ), 200 );
	}

	// ---- Settings ----

	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		return new \WP_REST_Response( Settings::get_all(), 200 );
	}

	public function update_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$data = $request->get_json_params();

		Settings::update( $data );

		return new \WP_REST_Response( Settings::get_all(), 200 );
	}

	// ---- Custom Fields ----

	public function get_custom_fields( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		return new \WP_REST_Response( CustomField::get_by_type( $id ), 200 );
	}

	public function create_custom_field( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		$data['booking_type_id'] = $id;

		if ( empty( $data['label'] ) ) {
			return new \WP_REST_Response( [ 'message' => 'Label is required.' ], 400 );
		}

		$field_id = CustomField::create( $data );

		if ( false === $field_id ) {
			return new \WP_REST_Response( [ 'message' => 'Failed to create field.' ], 500 );
		}

		return new \WP_REST_Response( CustomField::get( $field_id ), 201 );
	}

	public function update_custom_field( \WP_REST_Request $request ): \WP_REST_Response {
		$id   = (int) $request->get_param( 'id' );
		$data = $request->get_json_params();

		$existing = CustomField::get( $id );
		if ( ! $existing ) {
			return new \WP_REST_Response( [ 'message' => 'Not found.' ], 404 );
		}

		CustomField::update( $id, $data );

		return new \WP_REST_Response( CustomField::get( $id ), 200 );
	}

	public function delete_custom_field( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		CustomField::delete( $id );

		return new \WP_REST_Response( [ 'message' => 'Deleted.' ], 200 );
	}

	// ---- Webhook Test ----

	public function test_webhook( \WP_REST_Request $request ): \WP_REST_Response {
		$result = Webhook::send_test();

		$status = $result['success'] ? 200 : 400;

		return new \WP_REST_Response( $result, $status );
	}
}
