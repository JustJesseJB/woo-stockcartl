<?php
/**
 * StockCartl - WooCommerce Waitlist Plugin
 *
 * @package           StockCartl
 * @author            StockCartl Team
 * @copyright         2025 StockCartl
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       StockCartl
 * Plugin URI:        https://stockcartl.com
 * Description:       Transform "Out of Stock" into revenue opportunities with intelligent waitlist management, deposit priority systems, and social proof features.
 * Version:           1.0.0
 * Requires at least: 5.0
 * Requires PHP:      7.4
 * Author:            Ambition Plugins
 * Author URI:        https://amplifiedplugins.com
 * Text Domain:       stockcartl
 * Domain Path:       /languages
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * WC requires at least: 4.0.0
 * WC tested up to:   8.0.0
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Define plugin constants
define('STOCKCARTL_VERSION', '1.0.0');
define('STOCKCARTL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('STOCKCARTL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('STOCKCARTL_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('STOCKCARTL_TABLE_WAITLIST', 'stockcartl_waitlist');
define('STOCKCARTL_TABLE_SETTINGS', 'stockcartl_settings');
define('STOCKCARTL_TABLE_ANALYTICS', 'stockcartl_analytics');
define('STOCKCARTL_TABLE_NOTIFICATIONS', 'stockcartl_notifications');

/**
 * Check if WooCommerce is active
 */
function stockcartl_check_woocommerce() {
    if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        add_action('admin_notices', 'stockcartl_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function stockcartl_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error is-dismissible">
        <p><?php _e('StockCartl requires WooCommerce to be installed and activated.', 'stockcartl'); ?></p>
    </div>
    <?php
}

/**
 * Class Autoloader
 * 
 * @param string $class The class name to autoload
 * @return void
 */
function stockcartl_autoloader($class) {
    // Only load classes with our prefix
    if (strpos($class, 'StockCartl_') !== 0) {
        return;
    }

    // Convert class name to filename: StockCartl_Core becomes class-core.php
    $class_name = str_replace('StockCartl_', '', $class);
    $class_name = strtolower($class_name);
    $class_file = 'class-' . str_replace('_', '-', $class_name) . '.php';
    $class_path = STOCKCARTL_PLUGIN_DIR . 'includes/' . $class_file;

    // If the file exists, require it
    if (file_exists($class_path)) {
        require_once $class_path;
    }
}
spl_autoload_register('stockcartl_autoloader');

/**
 * Activation hook - Create database tables
 */
function stockcartl_activate() {
    if (!stockcartl_check_woocommerce()) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(__('StockCartl requires WooCommerce to be installed and activated.', 'stockcartl'));
    }

    // Load Core class for database setup
    require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-core.php';
    $core = new StockCartl_Core();
    $core->create_tables();

    // Set version in options
    update_option('stockcartl_version', STOCKCARTL_VERSION);
    
    // Clear any cached data
    wp_cache_flush();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'stockcartl_activate');

/**
 * Deactivation hook
 */
function stockcartl_deactivate() {
    // Clear any cached data
    wp_cache_flush();
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'stockcartl_deactivate');

/**
 * Start the plugin
 */
function stockcartl_init() {
    // Check if WooCommerce is active
    if (!stockcartl_check_woocommerce()) {
        return;
    }

    // Load text domain for translations
    load_plugin_textdomain('stockcartl', false, dirname(plugin_basename(__FILE__)) . '/languages/');

    // Initialize core plugin class
    require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-core.php';
    $stockcartl = new StockCartl_Core();
    $stockcartl->init();
}
add_action('plugins_loaded', 'stockcartl_init');

/**
 * Add settings link on plugins page
 */
function stockcartl_add_settings_link($links) {
    $settings_link = '<a href="' . admin_url('admin.php?page=stockcartl-settings') . '">' . __('Settings', 'stockcartl') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'stockcartl_add_settings_link');