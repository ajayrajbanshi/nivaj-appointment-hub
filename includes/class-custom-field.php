<?php
/**
 * Custom booking fields - per-booking-type custom intake fields.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- CRUD on custom plugin tables.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Field definitions and values are read on-demand with admin writes.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix + static literals.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomField {

	const FIELDS_TABLE = 'nah_booking_fields';
	const VALUES_TABLE = 'nah_booking_field_values';

	const VALID_TYPES = [ 'text', 'textarea', 'select', 'checkbox', 'phone', 'url', 'number' ];

	/**
	 * Get all active fields for a booking type.
	 */
	public static function get_by_type( int $booking_type_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . self::FIELDS_TABLE;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE booking_type_id = %d AND is_active = 1 ORDER BY sort_order ASC, id ASC",
				$booking_type_id
			),
			ARRAY_A
		) ?: [];

		return array_map( function ( $row ) {
			$row['id']              = (int) $row['id'];
			$row['booking_type_id'] = (int) $row['booking_type_id'];
			$row['required']        = (bool) $row['required'];
			$row['sort_order']      = (int) $row['sort_order'];
			$row['is_active']       = (bool) $row['is_active'];
			$row['options']         = ! empty( $row['options_json'] ) ? json_decode( $row['options_json'], true ) : [];
			unset( $row['options_json'] );
			return $row;
		}, $rows );
	}

	/**
	 * Get a single field by ID.
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$table = $wpdb->prefix . self::FIELDS_TABLE;

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Create a custom field.
	 *
	 * @return int|false The new field ID or false on failure.
	 */
	public static function create( array $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::FIELDS_TABLE;

		$sanitized = self::sanitize( $data );

		if ( empty( $sanitized['booking_type_id'] ) || empty( $sanitized['label'] ) ) {
			return false;
		}

		$result = $wpdb->insert( $table, $sanitized );

		return false === $result ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Update a custom field.
	 */
	public static function update( int $id, array $data ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::FIELDS_TABLE;

		$sanitized = self::sanitize( $data );
		unset( $sanitized['booking_type_id'] ); // Don't allow changing the parent type.

		$result = $wpdb->update( $table, $sanitized, [ 'id' => $id ] );

		return false !== $result;
	}

	/**
	 * Delete a custom field (soft delete).
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		$table = $wpdb->prefix . self::FIELDS_TABLE;

		$result = $wpdb->update( $table, [ 'is_active' => 0 ], [ 'id' => $id ] );

		return false !== $result;
	}

	/**
	 * Save field values for a booking.
	 *
	 * @param int   $booking_id  The booking ID.
	 * @param array $fields_data Array of [ field_id => value ].
	 */
	public static function save_values( int $booking_id, array $fields_data ): void {
		global $wpdb;
		$table = $wpdb->prefix . self::VALUES_TABLE;

		foreach ( $fields_data as $field_id => $value ) {
			$wpdb->insert( $table, [
				'booking_id'  => $booking_id,
				'field_id'    => absint( $field_id ),
				'field_value' => sanitize_textarea_field( $value ),
			] );
		}
	}

	/**
	 * Get field values for a booking (with labels).
	 */
	public static function get_values( int $booking_id ): array {
		global $wpdb;
		$values_table = $wpdb->prefix . self::VALUES_TABLE;
		$fields_table = $wpdb->prefix . self::FIELDS_TABLE;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.field_value, f.label, f.field_type
				FROM {$values_table} v
				JOIN {$fields_table} f ON v.field_id = f.id
				WHERE v.booking_id = %d
				ORDER BY f.sort_order ASC, f.id ASC",
				$booking_id
			),
			ARRAY_A
		) ?: [];

		return $rows;
	}

	/**
	 * Validate required fields are present.
	 *
	 * @param int   $booking_type_id The booking type ID.
	 * @param array $fields_data     Array of [ field_id => value ].
	 * @return string|null Error message or null if valid.
	 */
	public static function validate( int $booking_type_id, array $fields_data ): ?string {
		$fields = self::get_by_type( $booking_type_id );

		foreach ( $fields as $field ) {
			if ( $field['required'] ) {
				$value = $fields_data[ $field['id'] ] ?? '';
				if ( '' === trim( (string) $value ) ) {
					return sprintf(
						/* translators: %s: field label */
						__( '%s is required.', 'nivaj-appointment-hub' ),
						$field['label']
					);
				}
			}
		}

		return null;
	}

	/**
	 * Sanitize field data for create/update.
	 */
	private static function sanitize( array $data ): array {
		$sanitized = [];

		if ( isset( $data['booking_type_id'] ) ) {
			$sanitized['booking_type_id'] = absint( $data['booking_type_id'] );
		}

		if ( isset( $data['field_type'] ) ) {
			$sanitized['field_type'] = in_array( $data['field_type'], self::VALID_TYPES, true )
				? $data['field_type']
				: 'text';
		}

		if ( isset( $data['label'] ) ) {
			$sanitized['label'] = sanitize_text_field( $data['label'] );
		}

		if ( isset( $data['placeholder'] ) ) {
			$sanitized['placeholder'] = sanitize_text_field( $data['placeholder'] );
		}

		if ( isset( $data['required'] ) ) {
			$sanitized['required'] = (int) (bool) $data['required'];
		}

		if ( isset( $data['sort_order'] ) ) {
			$sanitized['sort_order'] = (int) $data['sort_order'];
		}

		if ( isset( $data['options'] ) && is_array( $data['options'] ) ) {
			$sanitized['options_json'] = wp_json_encode(
				array_map( 'sanitize_text_field', $data['options'] )
			);
		}

		if ( isset( $data['is_active'] ) ) {
			$sanitized['is_active'] = (int) (bool) $data['is_active'];
		}

		return $sanitized;
	}
}
