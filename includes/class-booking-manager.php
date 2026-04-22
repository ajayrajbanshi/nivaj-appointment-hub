<?php
/**
 * Booking Manager - CRUD with double-booking prevention.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reading and writing custom plugin tables; WP core provides no equivalent API.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Booking state must be authoritative per request; cache would cause double-bookings.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix + static literals; WHERE clauses are built via $wpdb->prepare().
 * phpcs:disable WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Dynamic IN() placeholders are generated from array_fill() and bound via spread.
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is constructed exclusively from $wpdb->prepare() calls; $table is $wpdb->prefix + static literal.
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- All hooks use the plugin "nah_" prefix; the sniff's 4-char minimum doesn't apply since "nah" is the unique plugin prefix.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingManager {

	const TABLE = 'nah_bookings';

	const STATUS_PENDING   = 'pending';
	const STATUS_CONFIRMED = 'confirmed';
	const STATUS_CANCELLED = 'cancelled';
	const STATUS_COMPLETED = 'completed';
	const STATUS_NO_SHOW   = 'no_show';

	const VALID_STATUSES = [
		self::STATUS_PENDING,
		self::STATUS_CONFIRMED,
		self::STATUS_CANCELLED,
		self::STATUS_COMPLETED,
		self::STATUS_NO_SHOW,
	];

	const ALLOWED_TRANSITIONS = [
		self::STATUS_PENDING   => [ self::STATUS_CONFIRMED, self::STATUS_CANCELLED ],
		self::STATUS_CONFIRMED => [ self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW ],
		self::STATUS_CANCELLED => [],
		self::STATUS_COMPLETED => [],
		self::STATUS_NO_SHOW   => [],
	];

	/**
	 * Create a booking with race-condition protection.
	 *
	 * @return array ['success' => bool, 'booking_id' => int|null, 'error' => string|null]
	 */
	public static function create( array $data ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		// Sanitize input.
		$data = self::sanitize( $data );

		// Validate required fields.
		$required = [ 'booking_type_id', 'booking_date', 'start_time', 'customer_name', 'customer_email' ];
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return [
					'success'    => false,
					'booking_id' => null,
					'error'      => sprintf( 'Missing required field: %s', $field ),
				];
			}
		}

		// Validate booking type exists and is active.
		$booking_type = BookingType::get( (int) $data['booking_type_id'] );
		if ( ! $booking_type || ! $booking_type['is_active'] ) {
			return [
				'success'    => false,
				'booking_id' => null,
				'error'      => 'Invalid or inactive booking type.',
			];
		}

		// Calculate end time from duration.
		$duration   = (int) $booking_type['duration'];
		$start_mins = self::time_to_minutes( $data['start_time'] );
		$end_time   = self::minutes_to_time( $start_mins + $duration );

		$data['end_time'] = $end_time;

		// Determine initial status.
		$settings        = Settings::get_all();
		$data['status']  = $settings['auto_confirm'] ? self::STATUS_CONFIRMED : self::STATUS_PENDING;

		// Use transaction with row-level locking to prevent double-booking.
		return self::create_with_lock( $data, $booking_type, $table );
	}

	/**
	 * Get a single booking by ID.
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get bookings with filters and pagination.
	 *
	 * @param array $filters Optional filters: booking_type_id, date_from, date_to, status, customer_email, search.
	 * @param int   $per_page Items per page.
	 * @param int   $page     Current page.
	 * @return array ['items' => array, 'total' => int, 'pages' => int]
	 */
	public static function get_all( array $filters = [], int $per_page = 20, int $page = 1 ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$where  = self::build_where_clause( $filters );
		$offset = ( $page - 1 ) * $per_page;

		// $where is already prepared by build_where_clause(), so we must not
		// wrap it in another prepare() call (LIKE % chars would be misinterpreted).
		// We prepare the LIMIT/OFFSET separately and concatenate.
		$limit_clause = $wpdb->prepare( 'LIMIT %d OFFSET %d', $per_page, $offset );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is pre-prepared
		$items = $wpdb->get_results(
			"SELECT b.*, bt.title as booking_type_title, bt.color as booking_type_color
			FROM {$table} b
			LEFT JOIN {$wpdb->prefix}nah_booking_types bt ON b.booking_type_id = bt.id
			{$where}
			ORDER BY b.booking_date DESC, b.start_time DESC
			{$limit_clause}",
			ARRAY_A
		) ?: [];

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is pre-prepared
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} b {$where}"
		);

		return [
			'items' => $items,
			'total' => $total,
			'pages' => (int) ceil( $total / $per_page ),
		];
	}

	/**
	 * Update booking status.
	 */
	public static function update_status( int $id, string $new_status ): bool {
		if ( ! in_array( $new_status, self::VALID_STATUSES, true ) ) {
			return false;
		}

		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		// Enforce valid status transitions.
		$booking = self::get( $id );
		if ( ! $booking ) {
			return false;
		}

		$current_status = $booking['status'];
		$allowed        = self::ALLOWED_TRANSITIONS[ $current_status ] ?? [];
		if ( ! in_array( $new_status, $allowed, true ) ) {
			return false;
		}

		$result = $wpdb->update(
			$table,
			[ 'status' => $new_status ],
			[ 'id' => $id ]
		);

		if ( false !== $result ) {
			/**
			 * Fires when a booking status changes.
			 *
			 * @param int    $id         Booking ID.
			 * @param string $new_status The new status.
			 */
			do_action( 'nah_booking_status_changed', $id, $new_status );

			if ( self::STATUS_CANCELLED === $new_status ) {
				do_action( 'nah_booking_cancelled', $id );
			}
		}

		return false !== $result;
	}

	/**
	 * Cancel a booking.
	 */
	public static function cancel( int $id, string $reason = '' ): bool {
		if ( ! empty( $reason ) ) {
			global $wpdb;
			$table = $wpdb->prefix . self::TABLE;
			$wpdb->update(
				$table,
				[ 'admin_notes' => sanitize_textarea_field( $reason ) ],
				[ 'id' => $id ]
			);
		}

		return self::update_status( $id, self::STATUS_CANCELLED );
	}

	/**
	 * Count bookings matching filters.
	 */
	public static function count( array $filters = [] ): int {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$where = self::build_where_clause( $filters );

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} b {$where}"
		);
	}

	/**
	 * Get bookings for export.
	 */
	public static function get_for_export( array $filters = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;
		$where = self::build_where_clause( $filters );

		return $wpdb->get_results(
			"SELECT b.*, bt.title as booking_type_title
			FROM {$table} b
			LEFT JOIN {$wpdb->prefix}nah_booking_types bt ON b.booking_type_id = bt.id
			{$where}
			ORDER BY b.booking_date ASC, b.start_time ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Get dashboard statistics.
	 */
	public static function get_stats( int $days = 30 ): array {
		global $wpdb;
		$table   = $wpdb->prefix . self::TABLE;
		$site_tz = wp_timezone();
		$now     = new \DateTime( 'now', $site_tz );
		$from    = ( clone $now )->modify( "-{$days} days" )->format( 'Y-m-d' );
		$today   = $now->format( 'Y-m-d' );

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE booking_date >= %s",
				$from
			)
		);

		$by_status = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count FROM {$table} WHERE booking_date >= %s GROUP BY status",
				$from
			),
			ARRAY_A
		) ?: [];

		$upcoming = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE booking_date >= %s AND status IN ('pending', 'confirmed')",
				$today
			)
		);

		$today_count = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE booking_date = %s AND status IN ('pending', 'confirmed')",
				$today
			)
		);

		$status_counts = [];
		foreach ( $by_status as $row ) {
			$status_counts[ $row['status'] ] = (int) $row['count'];
		}

		return [
			'total'         => $total,
			'upcoming'      => $upcoming,
			'today'         => $today_count,
			'by_status'     => $status_counts,
			'period_days'   => $days,
		];
	}

	/**
	 * Get analytics data for charts.
	 */
	public static function get_analytics( int $days = 30 ): array {
		global $wpdb;
		$table    = $wpdb->prefix . self::TABLE;
		$bt_table = $wpdb->prefix . 'nah_booking_types';
		$site_tz  = wp_timezone();
		$now      = new \DateTime( 'now', $site_tz );
		$from     = ( clone $now )->modify( "-{$days} days" )->format( 'Y-m-d' );

		// Daily counts.
		$daily_counts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_date as date, COUNT(*) as count
				FROM {$table}
				WHERE booking_date >= %s
				GROUP BY booking_date
				ORDER BY booking_date ASC",
				$from
			),
			ARRAY_A
		) ?: [];

		foreach ( $daily_counts as &$row ) {
			$row['count'] = (int) $row['count'];
		}

		// By booking type.
		$by_type = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT bt.title, COUNT(*) as count
				FROM {$table} b
				LEFT JOIN {$bt_table} bt ON b.booking_type_id = bt.id
				WHERE b.booking_date >= %s
				GROUP BY b.booking_type_id
				ORDER BY count DESC",
				$from
			),
			ARRAY_A
		) ?: [];

		foreach ( $by_type as &$row ) {
			$row['count'] = (int) $row['count'];
			$row['title'] = $row['title'] ?: __( 'Unknown', 'nivaj-appointment-hub' );
		}

		// By hour.
		$by_hour = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT HOUR(start_time) as hour, COUNT(*) as count
				FROM {$table}
				WHERE booking_date >= %s
				GROUP BY HOUR(start_time)
				ORDER BY hour ASC",
				$from
			),
			ARRAY_A
		) ?: [];

		foreach ( $by_hour as &$row ) {
			$row['hour']  = (int) $row['hour'];
			$row['count'] = (int) $row['count'];
		}

		// By status.
		$by_status_raw = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT status, COUNT(*) as count
				FROM {$table}
				WHERE booking_date >= %s
				GROUP BY status",
				$from
			),
			ARRAY_A
		) ?: [];

		$by_status = [];
		$total     = 0;
		foreach ( $by_status_raw as $row ) {
			$c = (int) $row['count'];
			$by_status[ $row['status'] ] = $c;
			$total += $c;
		}

		$cancelled = $by_status['cancelled'] ?? 0;
		$no_show   = $by_status['no_show'] ?? 0;

		return [
			'daily_counts'     => $daily_counts,
			'by_type'          => $by_type,
			'by_hour'          => $by_hour,
			'by_status'        => $by_status,
			'totals'           => [
				'total'             => $total,
				'cancellation_rate' => $total > 0 ? round( ( $cancelled / $total ) * 100, 1 ) : 0,
				'no_show_rate'      => $total > 0 ? round( ( $no_show / $total ) * 100, 1 ) : 0,
			],
			'period_days'      => $days,
		];
	}

	// ---- Private methods ----

	/**
	 * Create booking with transaction-based locking.
	 */
	private static function create_with_lock( array $data, array $booking_type, string $table ): array {
		global $wpdb;

		$wpdb->query( 'START TRANSACTION' );

		$buffer_before = (int) $booking_type['buffer_before'];
		$buffer_after  = (int) $booking_type['buffer_after'];

		// Calculate the full blocked range (including buffers).
		$slot_start = self::time_to_minutes( $data['start_time'] );
		$slot_end   = self::time_to_minutes( $data['end_time'] );
		$block_start = self::minutes_to_time( max( 0, $slot_start - $buffer_before ) );
		$block_end   = self::minutes_to_time( $slot_end + $buffer_after );

		// Lock and check for conflicts (including buffer times).
		$conflicts = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE booking_type_id = %d
				AND booking_date = %s
				AND status IN ('pending', 'confirmed')
				AND start_time < %s AND end_time > %s
				FOR UPDATE",
				$data['booking_type_id'],
				$data['booking_date'],
				$block_end,
				$block_start
			)
		);

		if ( $conflicts > 0 ) {
			$wpdb->query( 'ROLLBACK' );
			return [
				'success'    => false,
				'booking_id' => null,
				'error'      => 'This time slot is no longer available. Please choose another time.',
			];
		}

		// Check daily limit.
		$max_per_day = (int) $booking_type['max_bookings_per_day'];
		if ( $max_per_day > 0 ) {
			$day_count = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table}
					WHERE booking_type_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed')
					FOR UPDATE",
					$data['booking_type_id'],
					$data['booking_date']
				)
			);

			if ( $day_count >= $max_per_day ) {
				$wpdb->query( 'ROLLBACK' );
				return [
					'success'    => false,
					'booking_id' => null,
					'error'      => 'Maximum bookings for this day has been reached.',
				];
			}
		}

		// Insert the booking.
		$result = $wpdb->insert(
			$table,
			[
				'booking_type_id' => $data['booking_type_id'],
				'booking_date'    => $data['booking_date'],
				'start_time'      => $data['start_time'],
				'end_time'        => $data['end_time'],
				'status'          => $data['status'],
				'customer_name'   => $data['customer_name'],
				'customer_email'  => $data['customer_email'],
				'customer_phone'  => $data['customer_phone'] ?? '',
				'customer_notes'  => $data['customer_notes'] ?? '',
				'admin_notes'     => '',
				'timezone'        => $data['timezone'] ?? 'UTC',
			]
		);

		if ( false === $result ) {
			$wpdb->query( 'ROLLBACK' );
			return [
				'success'    => false,
				'booking_id' => null,
				'error'      => 'Failed to create booking. Please try again.',
			];
		}

		$booking_id = (int) $wpdb->insert_id;

		$wpdb->query( 'COMMIT' );

		/**
		 * Fires after a booking is created successfully.
		 *
		 * @param int $booking_id The new booking ID.
		 */
		do_action( 'nah_booking_created', $booking_id );

		return [
			'success'    => true,
			'booking_id' => $booking_id,
			'error'      => null,
		];
	}

	/**
	 * Build WHERE clause from filters.
	 */
	private static function build_where_clause( array $filters ): string {
		global $wpdb;
		$conditions = [];

		if ( ! empty( $filters['booking_type_id'] ) ) {
			$conditions[] = $wpdb->prepare( 'b.booking_type_id = %d', $filters['booking_type_id'] );
		}

		if ( ! empty( $filters['date_from'] ) ) {
			$conditions[] = $wpdb->prepare( 'b.booking_date >= %s', $filters['date_from'] );
		}

		if ( ! empty( $filters['date_to'] ) ) {
			$conditions[] = $wpdb->prepare( 'b.booking_date <= %s', $filters['date_to'] );
		}

		if ( ! empty( $filters['status'] ) ) {
			if ( is_array( $filters['status'] ) ) {
				$placeholders = implode( ',', array_fill( 0, count( $filters['status'] ), '%s' ) );
				$conditions[] = $wpdb->prepare(
					"b.status IN ({$placeholders})",
					...$filters['status']
				);
			} else {
				$conditions[] = $wpdb->prepare( 'b.status = %s', $filters['status'] );
			}
		}

		if ( ! empty( $filters['customer_email'] ) ) {
			$conditions[] = $wpdb->prepare( 'b.customer_email = %s', $filters['customer_email'] );
		}

		if ( ! empty( $filters['search'] ) ) {
			$like         = '%' . $wpdb->esc_like( $filters['search'] ) . '%';
			$conditions[] = $wpdb->prepare(
				'(b.customer_name LIKE %s OR b.customer_email LIKE %s OR b.customer_phone LIKE %s)',
				$like,
				$like,
				$like
			);
		}

		if ( empty( $conditions ) ) {
			return '';
		}

		return 'WHERE ' . implode( ' AND ', $conditions );
	}

	/**
	 * Sanitize booking data.
	 */
	private static function sanitize( array $data ): array {
		$sanitized = [];

		if ( isset( $data['booking_type_id'] ) ) {
			$sanitized['booking_type_id'] = absint( $data['booking_type_id'] );
		}

		if ( isset( $data['booking_date'] ) ) {
			$date_val = sanitize_text_field( $data['booking_date'] );
			$d = \DateTime::createFromFormat( 'Y-m-d', $date_val );
			$sanitized['booking_date'] = ( $d && $d->format( 'Y-m-d' ) === $date_val ) ? $date_val : '';
		}

		if ( isset( $data['start_time'] ) ) {
			$time_val = sanitize_text_field( $data['start_time'] );
			$sanitized['start_time'] = preg_match( '/^\d{2}:\d{2}$/', $time_val ) ? $time_val : '';
		}

		if ( isset( $data['end_time'] ) ) {
			$time_val = sanitize_text_field( $data['end_time'] );
			$sanitized['end_time'] = preg_match( '/^\d{2}:\d{2}$/', $time_val ) ? $time_val : '';
		}

		if ( isset( $data['customer_name'] ) ) {
			$sanitized['customer_name'] = sanitize_text_field( $data['customer_name'] );
		}

		if ( isset( $data['customer_email'] ) ) {
			$sanitized['customer_email'] = sanitize_email( $data['customer_email'] );
		}

		if ( isset( $data['customer_phone'] ) ) {
			$sanitized['customer_phone'] = sanitize_text_field( $data['customer_phone'] );
		}

		if ( isset( $data['customer_notes'] ) ) {
			$sanitized['customer_notes'] = sanitize_textarea_field( $data['customer_notes'] );
		}

		if ( isset( $data['timezone'] ) ) {
			$sanitized['timezone'] = sanitize_text_field( $data['timezone'] );
		}

		return $sanitized;
	}

	private static function time_to_minutes( string $time ): int {
		$parts = explode( ':', $time );
		return ( (int) $parts[0] * 60 ) + (int) $parts[1];
	}

	private static function minutes_to_time( int $minutes ): string {
		$minutes = max( 0, min( $minutes, 1439 ) ); // Cap at 23:59.
		return sprintf( '%02d:%02d', intdiv( $minutes, 60 ), $minutes % 60 );
	}
}
