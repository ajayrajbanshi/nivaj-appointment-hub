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
 * phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- $nah_tables / $nah_table carry the plugin prefix; the sniff's 4-char minimum doesn't apply.
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
$nah_tables = [
	$wpdb->prefix . 'nah_bookings',
	$wpdb->prefix . 'nah_date_overrides',
	$wpdb->prefix . 'nah_availability_rules',
	$wpdb->prefix . 'nah_booking_types',
	$wpdb->prefix . 'nah_booking_fields',
	$wpdb->prefix . 'nah_booking_field_values',
];

foreach ( $nah_tables as $nah_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$nah_table}" );
}

// Remove options.
delete_option( 'nah_settings' );
delete_option( 'nah_version' );

// Clear scheduled cron events.
wp_clear_scheduled_hook( 'nah_send_reminders' );
