<?php
/**
 * Availability Engine - the heart of the plugin.
 *
 * Handles slot generation, conflict detection, and availability queries.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Reading from custom plugin tables; WP core provides no equivalent API.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- Availability is computed on-demand; stale cache would cause double-bookings.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are built from $wpdb->prefix + static string literals, not user input.
 * phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table names are trusted $wpdb->prefix + static literals.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AvailabilityEngine {

	/**
	 * Get dates with availability for a given month.
	 *
	 * @param int $booking_type_id The booking type ID.
	 * @param int $year            The year (e.g. 2026).
	 * @param int $month           The month (1-12).
	 * @return array Array of date strings (Y-m-d) that have at least one open slot.
	 */
	public static function get_available_dates( int $booking_type_id, int $year, int $month ): array {
		$booking_type = BookingType::get( $booking_type_id );
		if ( ! $booking_type || ! $booking_type['is_active'] ) {
			return [];
		}

		$settings         = Settings::get_all();
		$max_advance_days = (int) $settings['max_booking_advance'];
		$min_notice_mins  = (int) $settings['min_booking_notice'];

		$site_tz  = wp_timezone();
		$now      = new \DateTime( 'now', $site_tz );
		$today    = $now->format( 'Y-m-d' );
		$max_date = ( clone $now )->modify( "+{$max_advance_days} days" )->format( 'Y-m-d' );

		$days_in_month = cal_days_in_month( CAL_GREGORIAN, $month, $year );
		$month_start   = sprintf( '%04d-%02d-01', $year, $month );
		$month_end     = sprintf( '%04d-%02d-%02d', $year, $month, $days_in_month );

		// Pre-fetch date overrides for the month.
		$overrides = BookingType::get_date_overrides( $booking_type_id, $month_start, $month_end );
		$override_map = self::build_override_map( $overrides );

		// Pre-fetch availability rules.
		$rules = BookingType::get_availability_rules( $booking_type_id );
		$rules_by_day = self::group_rules_by_day( $rules );

		// Pre-fetch existing bookings for the month.
		$bookings = self::get_bookings_for_range( $booking_type_id, $month_start, $month_end );
		$bookings_by_date = self::group_bookings_by_date( $bookings );

		$available_dates = [];

		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );

			// Skip past dates.
			if ( $date < $today ) {
				continue;
			}

			// Skip dates beyond max advance.
			if ( $date > $max_date ) {
				continue;
			}

			// Check if any slot is available (short-circuit).
			$has_slot = self::has_available_slot(
				$booking_type,
				$date,
				$rules_by_day,
				$override_map,
				$bookings_by_date[ $date ] ?? [],
				$now,
				$min_notice_mins
			);

			if ( $has_slot ) {
				$available_dates[] = $date;
			}
		}

		return $available_dates;
	}

	/**
	 * Get available time slots for a specific date.
	 *
	 * @param int    $booking_type_id The booking type ID.
	 * @param string $date            The date (Y-m-d).
	 * @return array Array of ['start' => 'HH:MM', 'end' => 'HH:MM'] arrays.
	 */
	public static function get_available_slots( int $booking_type_id, string $date ): array {
		$booking_type = BookingType::get( $booking_type_id );
		if ( ! $booking_type || ! $booking_type['is_active'] ) {
			return [];
		}

		$settings         = Settings::get_all();
		$min_notice_mins  = (int) $settings['min_booking_notice'];
		$max_advance_days = (int) $settings['max_booking_advance'];

		$site_tz = wp_timezone();
		$now     = new \DateTime( 'now', $site_tz );
		$today   = $now->format( 'Y-m-d' );

		// Validate date range.
		if ( $date < $today ) {
			return [];
		}

		$max_date = ( clone $now )->modify( "+{$max_advance_days} days" )->format( 'Y-m-d' );
		if ( $date > $max_date ) {
			return [];
		}

		// Get working windows for this date.
		$windows = self::get_working_windows( $booking_type_id, $date );
		if ( empty( $windows ) ) {
			return [];
		}

		// Generate candidate slots from working windows.
		$candidates = self::generate_candidate_slots(
			$windows,
			(int) $booking_type['duration'],
			(int) $booking_type['slot_interval'],
			(int) $booking_type['buffer_before'],
			(int) $booking_type['buffer_after']
		);

		if ( empty( $candidates ) ) {
			return [];
		}

		// Filter out slots that conflict with existing bookings.
		$candidates = self::filter_booked_slots(
			$booking_type_id,
			$date,
			$candidates,
			(int) $booking_type['buffer_before'],
			(int) $booking_type['buffer_after']
		);

		// Filter out past slots if date is today.
		if ( $date === $today ) {
			$candidates = self::filter_past_slots( $candidates, $now, $min_notice_mins );
		}

		// Check daily limit.
		$max_per_day = (int) $booking_type['max_bookings_per_day'];
		if ( $max_per_day > 0 ) {
			$current_count = self::count_bookings_for_date( $booking_type_id, $date );
			if ( $current_count >= $max_per_day ) {
				return [];
			}
		}

		return $candidates;
	}

	/**
	 * Check if a specific slot is available (authoritative check before booking).
	 */
	public static function is_slot_available( int $booking_type_id, string $date, string $start_time, string $end_time ): bool {
		$slots = self::get_available_slots( $booking_type_id, $date );

		foreach ( $slots as $slot ) {
			if ( $slot['start'] === $start_time && $slot['end'] === $end_time ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the working time windows for a specific date.
	 *
	 * Considers weekly rules and date overrides.
	 *
	 * @return array Array of ['start' => 'HH:MM:SS', 'end' => 'HH:MM:SS'] windows.
	 */
	private static function get_working_windows( int $booking_type_id, string $date ): array {
		// Check date overrides first.
		$overrides = BookingType::get_date_overrides( $booking_type_id, $date, $date );

		if ( ! empty( $overrides ) ) {
			// Check for full-day unavailable.
			foreach ( $overrides as $override ) {
				if (
					in_array( $override['override_type'], [ 'unavailable', 'holiday' ], true )
					&& null === $override['start_time']
				) {
					return []; // Entire day is blocked.
				}
			}

			// Custom hours override weekly rules entirely.
			$has_custom = false;
			$windows    = [];

			foreach ( $overrides as $override ) {
				if ( 'custom' === $override['override_type'] && $override['start_time'] && $override['end_time'] ) {
					$has_custom = true;
					$windows[]  = [
						'start' => $override['start_time'],
						'end'   => $override['end_time'],
					];
				}
			}

			if ( $has_custom ) {
				return $windows;
			}

			// Partial unavailable blocks - we'll subtract these from weekly rules below.
			$blocked_windows = [];
			foreach ( $overrides as $override ) {
				if ( in_array( $override['override_type'], [ 'unavailable', 'holiday' ], true ) && $override['start_time'] ) {
					$blocked_windows[] = [
						'start' => $override['start_time'],
						'end'   => $override['end_time'],
					];
				}
			}

			if ( ! empty( $blocked_windows ) ) {
				$day_of_week = (int) ( new \DateTime( $date ) )->format( 'w' );
				$rules       = BookingType::get_availability_rules( $booking_type_id );
				$windows     = [];

				foreach ( $rules as $rule ) {
					if ( (int) $rule['day_of_week'] === $day_of_week && $rule['is_enabled'] ) {
						$windows[] = [
							'start' => $rule['start_time'],
							'end'   => $rule['end_time'],
						];
					}
				}

				return self::subtract_windows( $windows, $blocked_windows );
			}
		}

		// No overrides - use weekly rules.
		$day_of_week = (int) ( new \DateTime( $date ) )->format( 'w' );
		$rules       = BookingType::get_availability_rules( $booking_type_id );
		$windows     = [];

		foreach ( $rules as $rule ) {
			if ( (int) $rule['day_of_week'] === $day_of_week && $rule['is_enabled'] ) {
				$windows[] = [
					'start' => $rule['start_time'],
					'end'   => $rule['end_time'],
				];
			}
		}

		return $windows;
	}

	/**
	 * Generate candidate time slots from working windows.
	 *
	 * @return array Array of ['start' => 'HH:MM', 'end' => 'HH:MM'].
	 */
	private static function generate_candidate_slots(
		array $windows,
		int $duration,
		int $slot_interval,
		int $buffer_before,
		int $buffer_after
	): array {
		$slots = [];

		foreach ( $windows as $window ) {
			$window_start = self::time_to_minutes( $window['start'] );
			$window_end   = self::time_to_minutes( $window['end'] );

			$cursor = $window_start;

			while ( $cursor + $duration <= $window_end ) {
				$slot_start = $cursor;
				$slot_end   = $cursor + $duration;

				$slots[] = [
					'start' => self::minutes_to_time( $slot_start ),
					'end'   => self::minutes_to_time( $slot_end ),
				];

				$cursor += $slot_interval;
			}
		}

		return $slots;
	}

	/**
	 * Filter out slots that conflict with existing bookings (including buffers).
	 */
	private static function filter_booked_slots(
		int $booking_type_id,
		string $date,
		array $candidates,
		int $buffer_before,
		int $buffer_after
	): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nah_bookings';

		$bookings = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT start_time, end_time FROM {$table}
				WHERE booking_type_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed')
				ORDER BY start_time ASC",
				$booking_type_id,
				$date
			),
			ARRAY_A
		);

		if ( empty( $bookings ) ) {
			return $candidates;
		}

		return array_values( array_filter( $candidates, function ( $slot ) use ( $bookings, $buffer_before, $buffer_after ) {
			$slot_start = self::time_to_minutes( $slot['start'] );
			$slot_end   = self::time_to_minutes( $slot['end'] );

			foreach ( $bookings as $booking ) {
				$booked_start = self::time_to_minutes( $booking['start_time'] ) - $buffer_before;
				$booked_end   = self::time_to_minutes( $booking['end_time'] ) + $buffer_after;

				// Check overlap: slot overlaps with booked range (including buffers).
				if ( $slot_start < $booked_end && $slot_end > $booked_start ) {
					return false;
				}
			}

			return true;
		} ) );
	}

	/**
	 * Filter out slots that are in the past (for today's date).
	 */
	private static function filter_past_slots( array $slots, \DateTime $now, int $min_notice_mins ): array {
		$cutoff_minutes = self::time_to_minutes( $now->format( 'H:i' ) ) + $min_notice_mins;

		return array_values( array_filter( $slots, function ( $slot ) use ( $cutoff_minutes ) {
			return self::time_to_minutes( $slot['start'] ) >= $cutoff_minutes;
		} ) );
	}

	/**
	 * Check if at least one slot is available on a date (short-circuit for calendar view).
	 */
	private static function has_available_slot(
		array $booking_type,
		string $date,
		array $rules_by_day,
		array $override_map,
		array $date_bookings,
		\DateTime $now,
		int $min_notice_mins
	): bool {
		$day_of_week = (int) ( new \DateTime( $date ) )->format( 'w' );

		// Check date overrides.
		if ( isset( $override_map[ $date ] ) ) {
			foreach ( $override_map[ $date ] as $override ) {
				if (
					in_array( $override['override_type'], [ 'unavailable', 'holiday' ], true )
					&& null === $override['start_time']
				) {
					return false; // Full day blocked.
				}
			}

			// Custom hours.
			$windows = [];
			$has_custom = false;
			foreach ( $override_map[ $date ] as $override ) {
				if ( 'custom' === $override['override_type'] && $override['start_time'] ) {
					$has_custom = true;
					$windows[] = [
						'start' => $override['start_time'],
						'end'   => $override['end_time'],
					];
				}
			}

			if ( ! $has_custom ) {
				// Use weekly rules, subtract any partial blocks.
				$windows = self::get_windows_from_rules( $rules_by_day, $day_of_week );

				$blocked_windows = [];
				foreach ( $override_map[ $date ] as $override ) {
					if ( in_array( $override['override_type'], [ 'unavailable', 'holiday' ], true ) && $override['start_time'] ) {
						$blocked_windows[] = [
							'start' => $override['start_time'],
							'end'   => $override['end_time'],
						];
					}
				}
				if ( ! empty( $blocked_windows ) ) {
					$windows = self::subtract_windows( $windows, $blocked_windows );
				}
			}
		} else {
			// Use weekly rules.
			$windows = self::get_windows_from_rules( $rules_by_day, $day_of_week );
		}

		if ( empty( $windows ) ) {
			return false;
		}

		// Check daily limit.
		$max_per_day = (int) $booking_type['max_bookings_per_day'];
		if ( $max_per_day > 0 && count( $date_bookings ) >= $max_per_day ) {
			return false;
		}

		// Generate candidates and check if any are available.
		$candidates = self::generate_candidate_slots(
			$windows,
			(int) $booking_type['duration'],
			(int) $booking_type['slot_interval'],
			(int) $booking_type['buffer_before'],
			(int) $booking_type['buffer_after']
		);

		if ( empty( $candidates ) ) {
			return false;
		}

		// Filter booked slots.
		if ( ! empty( $date_bookings ) ) {
			$buffer_before = (int) $booking_type['buffer_before'];
			$buffer_after  = (int) $booking_type['buffer_after'];

			$candidates = array_filter( $candidates, function ( $slot ) use ( $date_bookings, $buffer_before, $buffer_after ) {
				$slot_start = self::time_to_minutes( $slot['start'] );
				$slot_end   = self::time_to_minutes( $slot['end'] );

				foreach ( $date_bookings as $booking ) {
					$booked_start = self::time_to_minutes( $booking['start_time'] ) - $buffer_before;
					$booked_end   = self::time_to_minutes( $booking['end_time'] ) + $buffer_after;

					if ( $slot_start < $booked_end && $slot_end > $booked_start ) {
						return false;
					}
				}
				return true;
			} );
		}

		// Filter past slots if today.
		$today = $now->format( 'Y-m-d' );
		if ( $date === $today && ! empty( $candidates ) ) {
			$cutoff = self::time_to_minutes( $now->format( 'H:i' ) ) + $min_notice_mins;
			foreach ( $candidates as $slot ) {
				if ( self::time_to_minutes( $slot['start'] ) >= $cutoff ) {
					return true; // Found one.
				}
			}
			return false;
		}

		return ! empty( $candidates );
	}

	/**
	 * Count active bookings for a date.
	 */
	private static function count_bookings_for_date( int $booking_type_id, string $date ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'nah_bookings';

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE booking_type_id = %d AND booking_date = %s AND status IN ('pending', 'confirmed')",
				$booking_type_id,
				$date
			)
		);
	}

	/**
	 * Get bookings for a date range (used for batch availability checks).
	 */
	private static function get_bookings_for_range( int $booking_type_id, string $from, string $to ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'nah_bookings';

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT booking_date, start_time, end_time FROM {$table}
				WHERE booking_type_id = %d AND booking_date BETWEEN %s AND %s AND status IN ('pending', 'confirmed')
				ORDER BY booking_date ASC, start_time ASC",
				$booking_type_id,
				$from,
				$to
			),
			ARRAY_A
		) ?: [];
	}

	// ---- Helper methods ----

	/**
	 * Convert a time string (HH:MM or HH:MM:SS) to minutes since midnight.
	 */
	private static function time_to_minutes( string $time ): int {
		$parts = explode( ':', $time );
		return ( (int) $parts[0] * 60 ) + (int) $parts[1];
	}

	/**
	 * Convert minutes since midnight to HH:MM format.
	 */
	private static function minutes_to_time( int $minutes ): string {
		$minutes = max( 0, min( $minutes, 1439 ) ); // Cap at 23:59.
		$hours   = intdiv( $minutes, 60 );
		$mins    = $minutes % 60;
		return sprintf( '%02d:%02d', $hours, $mins );
	}

	/**
	 * Build a map of date => overrides for efficient lookup.
	 */
	private static function build_override_map( array $overrides ): array {
		$map = [];
		foreach ( $overrides as $override ) {
			$map[ $override['override_date'] ][] = $override;
		}
		return $map;
	}

	/**
	 * Group availability rules by day of week.
	 */
	private static function group_rules_by_day( array $rules ): array {
		$grouped = [];
		foreach ( $rules as $rule ) {
			$grouped[ (int) $rule['day_of_week'] ][] = $rule;
		}
		return $grouped;
	}

	/**
	 * Group bookings by date.
	 */
	private static function group_bookings_by_date( array $bookings ): array {
		$grouped = [];
		foreach ( $bookings as $booking ) {
			$grouped[ $booking['booking_date'] ][] = $booking;
		}
		return $grouped;
	}

	/**
	 * Get working windows from grouped rules for a specific day.
	 */
	private static function get_windows_from_rules( array $rules_by_day, int $day_of_week ): array {
		if ( ! isset( $rules_by_day[ $day_of_week ] ) ) {
			return [];
		}

		$windows = [];
		foreach ( $rules_by_day[ $day_of_week ] as $rule ) {
			if ( $rule['is_enabled'] ) {
				$windows[] = [
					'start' => $rule['start_time'],
					'end'   => $rule['end_time'],
				];
			}
		}

		return $windows;
	}

	/**
	 * Subtract blocked windows from working windows.
	 *
	 * Returns remaining available windows after removing blocked periods.
	 */
	private static function subtract_windows( array $windows, array $blocked ): array {
		$result = [];

		foreach ( $windows as $window ) {
			$w_start = self::time_to_minutes( $window['start'] );
			$w_end   = self::time_to_minutes( $window['end'] );

			$remaining = [ [ $w_start, $w_end ] ];

			foreach ( $blocked as $block ) {
				$b_start    = self::time_to_minutes( $block['start'] );
				$b_end      = self::time_to_minutes( $block['end'] );
				$new_remaining = [];

				foreach ( $remaining as [ $r_start, $r_end ] ) {
					if ( $b_start >= $r_end || $b_end <= $r_start ) {
						// No overlap.
						$new_remaining[] = [ $r_start, $r_end ];
					} else {
						// Overlap - split.
						if ( $r_start < $b_start ) {
							$new_remaining[] = [ $r_start, $b_start ];
						}
						if ( $b_end < $r_end ) {
							$new_remaining[] = [ $b_end, $r_end ];
						}
					}
				}

				$remaining = $new_remaining;
			}

			foreach ( $remaining as [ $r_start, $r_end ] ) {
				$result[] = [
					'start' => self::minutes_to_time( $r_start ),
					'end'   => self::minutes_to_time( $r_end ),
				];
			}
		}

		return $result;
	}
}
