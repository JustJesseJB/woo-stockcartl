<?php
/**
 * Uninstall StockCartl
 *
 * @package StockCartl
 */

// If uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Access the database via SQL for cleanup
global $wpdb;

// Define table names
$waitlist_table = $wpdb->prefix . 'stockcartl_waitlist';
$settings_table = $wpdb->prefix . 'stockcartl_settings';
$analytics_table = $wpdb->prefix . 'stockcartl_analytics';
$notifications_table = $wpdb->prefix . 'stockcartl_notifications';

// Drop tables
$wpdb->query("DROP TABLE IF EXISTS $waitlist_table");
$wpdb->query("DROP TABLE IF EXISTS $settings_table");
$wpdb->query("DROP TABLE IF EXISTS $analytics_table");
$wpdb->query("DROP TABLE IF EXISTS $notifications_table");

// Remove scheduled events
wp_clear_scheduled_hook('stockcartl_process_notification_queue');

// Delete options
delete_option('stockcartl_version');
delete_option('stockcartl_db_version');
delete_option('stockcartl_settings');

// Remove product meta
$wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key LIKE '_stockcartl_%'");