<?php
/**
 * Plugin activation and deactivation.
 */

namespace NivajAppointmentHub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		self::create_tables();
		self::set_default_options();
		self::schedule_cron();

		// Store version for future migrations.
		update_option( 'nivaj_ah_version', NIVAJ_AH_VERSION );
	}

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'nivaj_ah_send_reminders' );
	}

	/**
	 * Create all custom database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$sql = [];

		// Booking types table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_booking_types (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(200) NOT NULL,
			slug VARCHAR(200) NOT NULL,
			description TEXT NOT NULL,
			duration INT UNSIGNED NOT NULL DEFAULT 30,
			color VARCHAR(7) NOT NULL DEFAULT '#2563eb',
			location_type VARCHAR(20) NOT NULL DEFAULT 'phone',
			location_data VARCHAR(500) NOT NULL DEFAULT '',
			buffer_before INT UNSIGNED NOT NULL DEFAULT 0,
			buffer_after INT UNSIGNED NOT NULL DEFAULT 0,
			slot_interval INT UNSIGNED NOT NULL DEFAULT 30,
			max_bookings_per_day INT UNSIGNED NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			banner_image_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			banner_title VARCHAR(200) NOT NULL DEFAULT '',
			banner_subtitle VARCHAR(500) NOT NULL DEFAULT '',
			banner_bg_color VARCHAR(7) NOT NULL DEFAULT '',
			banner_text_color VARCHAR(7) NOT NULL DEFAULT '',
			banner_layout VARCHAR(20) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_slug (slug),
			KEY idx_active_sort (is_active, sort_order)
		) {$charset_collate};";

		// Availability rules table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_availability_rules (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_type_id BIGINT UNSIGNED NOT NULL,
			day_of_week TINYINT UNSIGNED NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			is_enabled TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type_day (booking_type_id, day_of_week),
			KEY idx_type_enabled (booking_type_id, is_enabled)
		) {$charset_collate};";

		// Date overrides table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_date_overrides (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_type_id BIGINT UNSIGNED NOT NULL,
			override_date DATE NOT NULL,
			override_type VARCHAR(20) NOT NULL DEFAULT 'unavailable',
			start_time TIME DEFAULT NULL,
			end_time TIME DEFAULT NULL,
			label VARCHAR(200) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY idx_type_date_time (booking_type_id, override_date, start_time),
			KEY idx_date_range (override_date)
		) {$charset_collate};";

		// Bookings table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_bookings (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_type_id BIGINT UNSIGNED NOT NULL,
			booking_date DATE NOT NULL,
			start_time TIME NOT NULL,
			end_time TIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			customer_name VARCHAR(200) NOT NULL,
			customer_email VARCHAR(200) NOT NULL,
			customer_phone VARCHAR(50) NOT NULL DEFAULT '',
			customer_notes TEXT NOT NULL,
			admin_notes TEXT NOT NULL,
			reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
			timezone VARCHAR(50) NOT NULL DEFAULT 'UTC',
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type_date_status (booking_type_id, booking_date, status),
			KEY idx_date_status (booking_date, status),
			KEY idx_customer_email (customer_email),
			KEY idx_status (status),
			KEY idx_reminder (reminder_sent, booking_date, status)
		) {$charset_collate};";

		// Custom booking fields table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_booking_fields (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_type_id BIGINT UNSIGNED NOT NULL,
			field_type VARCHAR(20) NOT NULL DEFAULT 'text',
			label VARCHAR(200) NOT NULL,
			placeholder VARCHAR(200) NOT NULL DEFAULT '',
			required TINYINT(1) NOT NULL DEFAULT 0,
			sort_order INT NOT NULL DEFAULT 0,
			options_json TEXT,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY idx_type_sort (booking_type_id, sort_order)
		) {$charset_collate};";

		// Custom field values table.
		$sql[] = "CREATE TABLE {$wpdb->prefix}nivaj_ah_booking_field_values (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			booking_id BIGINT UNSIGNED NOT NULL,
			field_id BIGINT UNSIGNED NOT NULL,
			field_value TEXT,
			PRIMARY KEY  (id),
			KEY idx_booking (booking_id),
			KEY idx_field (field_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $sql as $query ) {
			dbDelta( $query );
		}
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options(): void {
		if ( false === get_option( 'nivaj_ah_settings' ) ) {
			add_option( 'nivaj_ah_settings', Settings::defaults() );
		}
	}

	/**
	 * Schedule cron events.
	 */
	private static function schedule_cron(): void {
		if ( ! wp_next_scheduled( 'nivaj_ah_send_reminders' ) ) {
			wp_schedule_event( time(), 'hourly', 'nivaj_ah_send_reminders' );
		}
	}
}
