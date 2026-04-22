<?php
/**
 * Uninstall - clean up all plugin data.
 *
 * Fired when the plugin is deleted via the WordPress admin.
 *
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery -- Dropping custom plugin tables; WP core has no API for this.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time schema change on uninstall; no cache is relevant.
 * phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange -- DROP TABLE is intentional on uninstall.
 * phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names are a whitelist of $wpdb->prefix + static literals; DROP TABLE does not accept placeholders.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Must only run from the uninstall handler.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
$nivaj_ah_tables = [
	$wpdb->prefix . 'nivaj_ah_bookings',
	$wpdb->prefix . 'nivaj_ah_date_overrides',
	$wpdb->prefix . 'nivaj_ah_availability_rules',
	$wpdb->prefix . 'nivaj_ah_booking_types',
	$wpdb->prefix . 'nivaj_ah_booking_fields',
	$wpdb->prefix . 'nivaj_ah_booking_field_values',
];

foreach ( $nivaj_ah_tables as $nivaj_ah_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$nivaj_ah_table}" );
}

// Remove options.
delete_option( 'nivaj_ah_settings' );
delete_option( 'nivaj_ah_version' );

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'nivaj_ah_send_reminders' );
