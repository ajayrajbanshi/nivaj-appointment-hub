<?php
/**
 * CSV Exporter for bookings.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exporter {

	/**
	 * Generate CSV content from bookings.
	 */
	public static function bookings_to_csv( array $filters = [] ): string {
		$bookings = BookingManager::get_for_export( $filters );

		$rows = [];

		$rows[] = [
			'ID',
			'Booking Type',
			'Date',
			'Start Time',
			'End Time',
			'Status',
			'Customer Name',
			'Customer Email',
			'Customer Phone',
			'Customer Notes',
			'Admin Notes',
			'Created At',
		];

		foreach ( $bookings as $booking ) {
			$rows[] = [
				$booking['id'],
				$booking['booking_type_title'] ?? '',
				$booking['booking_date'],
				$booking['start_time'],
				$booking['end_time'],
				$booking['status'],
				$booking['customer_name'],
				$booking['customer_email'],
				$booking['customer_phone'],
				$booking['customer_notes'],
				$booking['admin_notes'],
				$booking['created_at'],
			];
		}

		$lines = array_map( [ self::class, 'encode_csv_row' ], $rows );

		return implode( "\r\n", $lines ) . "\r\n";
	}

	/**
	 * Encode a single CSV row per RFC 4180.
	 */
	private static function encode_csv_row( array $row ): string {
		return implode( ',', array_map( [ self::class, 'encode_csv_field' ], $row ) );
	}

	/**
	 * Encode a single CSV field. Quotes when needed and escapes embedded quotes.
	 */
	private static function encode_csv_field( $value ): string {
		$value = (string) $value;

		if ( '' === $value ) {
			return '';
		}

		// Quote if the value contains a delimiter, quote, or line break.
		if ( preg_match( '/[",\r\n]/', $value ) ) {
			return '"' . str_replace( '"', '""', $value ) . '"';
		}

		return $value;
	}
}
