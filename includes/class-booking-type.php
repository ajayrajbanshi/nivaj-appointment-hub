<?php
/**
 * BookingType model - CRUD for services/meeting types.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- CRUD on custom plugin tables; WP core has no equivalent.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin writes require an authoritative read on each request.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix + static literals; values are passed through $wpdb->prepare().
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where and $table are trusted: $where is a static string, $table is $wpdb->prefix + literal.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BookingType {

	const TABLE           = 'nivaj_ah_booking_types';
	const RULES_TABLE     = 'nivaj_ah_availability_rules';
	const OVERRIDES_TABLE = 'nivaj_ah_date_overrides';

	/**
	 * Get a single booking type by ID.
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
	 * Get a booking type by slug.
	 */
	public static function get_by_slug( string $slug ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s", $slug ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Get all booking types.
	 */
	public static function get_all( bool $active_only = false ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$where = $active_only ? 'WHERE is_active = 1' : '';

		return $wpdb->get_results(
			"SELECT * FROM {$table} {$where} ORDER BY sort_order ASC, id ASC",
			ARRAY_A
		) ?: [];
	}

	/**
	 * Create a new booking type.
	 *
	 * @return int|false The new ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$data = self::sanitize( $data );

		if ( empty( $data['slug'] ) ) {
			$data['slug'] = sanitize_title( $data['title'] );
		}
		$data['slug'] = self::unique_slug( $data['slug'] );

		$result = $wpdb->insert( $table, $data );

		if ( false === $result ) {
			return false;
		}

		$id = (int) $wpdb->insert_id;

		self::create_default_availability( $id );

		return $id;
	}

	/**
	 * Update a booking type.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::TABLE;

		$data = self::sanitize( $data );

		if ( isset( $data['slug'] ) ) {
			$existing = self::get_by_slug( $data['slug'] );
			if ( $existing && (int) $existing['id'] !== $id ) {
				$data['slug'] = self::unique_slug( $data['slug'], $id );
			}
		}

		$result = $wpdb->update( $table, $data, [ 'id' => $id ] );

		return false !== $result;
	}

	/**
	 * Delete a booking type and its related data.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;

		$wpdb->delete( $wpdb->prefix . self::RULES_TABLE, [ 'booking_type_id' => $id ], [ '%d' ] );
		$wpdb->delete( $wpdb->prefix . self::OVERRIDES_TABLE, [ 'booking_type_id' => $id ], [ '%d' ] );

		$result = $wpdb->delete( $wpdb->prefix . self::TABLE, [ 'id' => $id ], [ '%d' ] );

		return false !== $result;
	}

	/**
	 * Get availability rules for a booking type.
	 */
	public static function get_availability_rules( int $booking_type_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::RULES_TABLE;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_type_id = %d ORDER BY day_of_week ASC, start_time ASC",
				$booking_type_id
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Replace all availability rules for a booking type.
	 */
	public static function set_availability_rules( int $booking_type_id, array $rules ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::RULES_TABLE;

		$wpdb->query( 'START TRANSACTION' );

		$wpdb->delete( $table, [ 'booking_type_id' => $booking_type_id ], [ '%d' ] );

		foreach ( $rules as $rule ) {
			$day = absint( $rule['day_of_week'] ?? 0 );
			if ( $day > 6 ) {
				continue; // day_of_week must be 0-6 (Sunday=0).
			}

			$result = $wpdb->insert(
				$table,
				[
					'booking_type_id' => $booking_type_id,
					'day_of_week'     => $day,
					'start_time'      => sanitize_text_field( $rule['start_time'] ),
					'end_time'        => sanitize_text_field( $rule['end_time'] ),
					'is_enabled'      => isset( $rule['is_enabled'] ) ? (int) $rule['is_enabled'] : 1,
				]
			);

			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );
				return false;
			}
		}

		$wpdb->query( 'COMMIT' );
		return true;
	}

	/**
	 * Get date overrides for a booking type within a date range.
	 */
	public static function get_date_overrides( int $booking_type_id, string $from, string $to ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::OVERRIDES_TABLE;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_type_id = %d AND override_date BETWEEN %s AND %s ORDER BY override_date ASC, start_time ASC",
				$booking_type_id,
				$from,
				$to
			),
			ARRAY_A
		) ?: [];
	}

	/**
	 * Add a date override.
	 */
	public static function add_date_override( int $booking_type_id, array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::OVERRIDES_TABLE;

		$result = $wpdb->insert(
			$table,
			[
				'booking_type_id' => $booking_type_id,
				'override_date'   => sanitize_text_field( $data['override_date'] ),
				'override_type'   => sanitize_text_field( $data['override_type'] ?? 'unavailable' ),
				'start_time'      => isset( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : null,
				'end_time'        => isset( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : null,
				'label'           => sanitize_text_field( $data['label'] ?? '' ),
			]
		);

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Delete a date override.
	 */
	public static function delete_date_override( int $id ): bool {
		global $wpdb;
		$result = $wpdb->delete( $wpdb->prefix . self::OVERRIDES_TABLE, [ 'id' => $id ], [ '%d' ] );
		return false !== $result;
	}

	/**
	 * Sanitize booking type data.
	 */
	private static function sanitize( array $data ): array {
		$sanitized = [];

		$text_fields = [ 'title', 'slug', 'color', 'location_type', 'location_data' ];
		foreach ( $text_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = sanitize_text_field( $data[ $field ] );
			}
		}

		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = sanitize_textarea_field( $data['description'] );
		}

		$int_fields = [ 'duration', 'buffer_before', 'buffer_after', 'slot_interval', 'max_bookings_per_day', 'sort_order', 'banner_image_id' ];
		foreach ( $int_fields as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$sanitized[ $field ] = absint( $data[ $field ] );
			}
		}

		if ( isset( $data['is_active'] ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		if ( isset( $sanitized['slot_interval'] ) && ! in_array( $sanitized['slot_interval'], [ 15, 30, 60 ], true ) ) {
			$sanitized['slot_interval'] = 30;
		}

		$valid_locations = [ 'phone', 'in_person', 'video', 'custom' ];
		if ( isset( $sanitized['location_type'] ) && ! in_array( $sanitized['location_type'], $valid_locations, true ) ) {
			$sanitized['location_type'] = 'phone';
		}

		return $sanitized;
	}

	private static function unique_slug( string $slug, int $exclude_id = 0 ): string {
		global $wpdb;
		$table    = $wpdb->prefix . self::TABLE;
		$original = $slug;
		$counter  = 1;

		while ( true ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s AND id != %d", $slug, $exclude_id )
			);
			if ( ! $existing ) {
				break;
			}
			$slug = $original . '-' . $counter;
			$counter++;
		}

		return $slug;
	}

	private static function create_default_availability( int $booking_type_id ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::RULES_TABLE;

		for ( $day = 1; $day <= 5; $day++ ) {
			$wpdb->insert( $table, [
				'booking_type_id' => $booking_type_id,
				'day_of_week'     => $day,
				'start_time'      => '09:00:00',
				'end_time'        => '17:00:00',
				'is_enabled'      => 1,
			] );
		}
	}
}
