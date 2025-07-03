<?php
/**
 * Debugging functionality for StockCartl
 *
 * @package StockCartl
 * @subpackage Debugging
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Debug Class
 * 
 * Handles all debugging and logging functionality
 */
class StockCartl_Debug {

    /**
     * Log levels
     */
    const LEVEL_INFO = 'info';
    const LEVEL_WARNING = 'warning';
    const LEVEL_ERROR = 'error';
    const LEVEL_CRITICAL = 'critical';

    /**
     * Debug modes
     */
    const MODE_NONE = 0;    // No debugging
    const MODE_BASIC = 1;    // Basic debugging (logs only)
    const MODE_ADVANCED = 2; // Advanced debugging (logs + visual)

    /**
     * Instance of this class
     *
     * @var StockCartl_Debug
     */
    private static $instance = null;

    /**
     * Settings instance
     *
     * @var StockCartl_Settings
     */
    private $settings;

    /**
     * License instance
     *
     * @var StockCartl_License
     */
    private $license;

    /**
     * Debug mode
     *
     * @var int
     */
    private $debug_mode = self::MODE_NONE;

    /**
     * Log directory
     *
     * @var string
     */
    private $log_dir;

    /**
     * Current log file
     *
     * @var string
     */
    private $log_file;

    /**
     * Initialize the class and set its properties.
     */
    private function __construct() {
        // Get settings instance
        global $stockcartl_settings;
        $this->settings = $stockcartl_settings;

        // Set up log directory
        $upload_dir = wp_upload_dir();
        $this->log_dir = $upload_dir['basedir'] . '/stockcartl-logs';

        // Create log directory if it doesn't exist
        if (!file_exists($this->log_dir)) {
            $dir_created = wp_mkdir_p($this->log_dir);
            
            if (!$dir_created) {
                // Log failure with specific error
                error_log('StockCartl: Failed to create log directory: ' . $this->log_dir . '. Error: ' . error_get_last()['message']);
                
                // Show admin notice immediately and on next page load
                add_action('admin_notices', function() {
                    ?>
                    <div class="notice notice-error">
                        <p><strong>StockCartl Debug Error:</strong> <?php printf(
                            __('Failed to create log directory at %s. Please check server permissions.', 'stockcartl'),
                            '<code>' . esc_html($this->log_dir) . '</code>'
                        ); ?></p>
                    </div>
                    <?php
                });
                
                update_option('stockcartl_debug_dir_error', true);
            } else {
                // Create .htaccess file to prevent direct access
                $htaccess_file = $this->log_dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    $htaccess_content = "# Prevent direct access to files\n";
                    $htaccess_content .= "<Files \"*\">\n";
                    $htaccess_content .= "    Require all denied\n";
                    $htaccess_content .= "</Files>";
                    @file_put_contents($htaccess_file, $htaccess_content);
                }
                
                // Create index.php file to prevent directory listing
                $index_file = $this->log_dir . '/index.php';
                if (!file_exists($index_file)) {
                    @file_put_contents($index_file, "<?php\n// Silence is golden.");
                }
                
                // Make sure the directory is writable
                if (!is_writable($this->log_dir)) {
                    // Try to set permissions
                    @chmod($this->log_dir, 0755);
                    
                    if (!is_writable($this->log_dir)) {
                        add_action('admin_notices', function() {
                            ?>
                            <div class="notice notice-error">
                                <p><strong>StockCartl Debug Error:</strong> <?php printf(
                                    __('Log directory exists but is not writable: %s', 'stockcartl'),
                                    '<code>' . esc_html($this->log_dir) . '</code>'
                                ); ?></p>
                            </div>
                            <?php
                        });
                    }
                }
                
                // Clear any previous error flag
                delete_option('stockcartl_debug_dir_error');
            }
        }
        
        // Check if log directory is writable
        if (!is_writable($this->log_dir)) {
            // Show admin notice
            add_action('admin_notices', function() {
                ?>
                <div class="notice notice-error">
                    <p><?php printf(
                        __('StockCartl: Log directory is not writable. Please check permissions for %s', 'stockcartl'),
                        '<code>' . esc_html($this->log_dir) . '</code>'
                    ); ?></p>
                </div>
                <?php
            });
        }

        // Set current log file
        $this->log_file = $this->log_dir . '/stockcartl-debug.log';

        // Set debug mode from settings
        $this->debug_mode = self::MODE_NONE; // Default to disabled

        // Check if we have settings
        if ( $this->settings ) {
            $debug_option = $this->settings->get( 'debug_mode', self::MODE_NONE );
            $this->debug_mode = intval( $debug_option );
        } else {
            // Settings not initialized yet – fall back to DB option
            $saved_options = get_option( 'stockcartl_settings', array() );
            $this->debug_mode = isset( $saved_options['debug_mode'] )
                ? intval( $saved_options['debug_mode'] )
                : self::MODE_NONE;
        }

        // Check for forced debug mode constant
        if ( defined( 'STOCKCARTL_DEBUG_MODE' ) ) {
            $this->debug_mode = intval( STOCKCARTL_DEBUG_MODE );
        }

        // Initialize license
        $this->init_license();

        // Add actions and filters
        $this->init_hooks();
    }

    /**
     * Initialize the license
     */
    private function init_license() {
        // Check if license class exists
        if (class_exists('StockCartl_License')) {
            $this->license = new StockCartl_License();
        } else {
            // Use a simple implementation for now
            $this->license = new stdClass();
            $this->license->has_feature = function($feature) {
                // During development, all features are available
                if (defined('STOCKCARTL_DEV_MODE') && STOCKCARTL_DEV_MODE) {
                    return true;
                }
                
                // Basic features always available
                $basic_features = ['basic_logging', 'simple_debug_toggle'];
                if (in_array($feature, $basic_features)) {
                    return true;
                }
                
                return false;
            };
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add debug info to footer if enabled
        if ($this->debug_mode >= self::MODE_ADVANCED) {
            add_action('wp_footer', array($this, 'display_debug_info'));
            add_action('admin_footer', array($this, 'display_debug_info'));
        }

        // Register cleanup event
        add_action('stockcartl_cleanup_logs', array($this, 'cleanup_logs'));
        
        // Make sure the scheduled task is registered
        if (!wp_next_scheduled('stockcartl_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'stockcartl_cleanup_logs');
        }
    }

    /**
     * Get instance of this class
     *
     * @return StockCartl_Debug
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Get the log directory path
     *
     * @return string Log directory path
     */
    public function get_log_dir() {
        return $this->log_dir;
    }

    /**
     * Get the log file path
     *
     * @return string Log file path
     */
    public function get_log_file() {
        return $this->log_file;
    }

    /**
     * Log a message to the debug log
     *
     * @param string $level The log level
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function log($level, $message, $context = array()) {
        // Always log critical errors
        if ($level == self::LEVEL_CRITICAL || $this->debug_mode > self::MODE_NONE) {
            // Format the log entry
            $time = current_time('mysql');
            $entry = "[{$time}] [{$level}] {$message}";
            
            // Add context if available
            if (!empty($context)) {
                $context_str = json_encode($context);
                $entry .= " Context: {$context_str}";
            }
            
            // Add new line
            $entry .= PHP_EOL;
            
            // Write to log file
            @file_put_contents($this->log_file, $entry, FILE_APPEND);
        }
        
        // Console logging in advanced mode
        if ($this->debug_mode >= self::MODE_ADVANCED && $level != self::LEVEL_INFO) {
            $this->console_log($level, $message, $context);
        }
    }

    /**
     * Log an info message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function log_info($message, $context = array()) {
        // Check if advanced logging is available
        if ($this->license->has_feature('advanced_logging')) {
            $this->log(self::LEVEL_INFO, $message, $context);
        } else {
            // Basic logging doesn't include info level
            if ($this->debug_mode >= self::MODE_ADVANCED) {
                $this->log(self::LEVEL_INFO, $message, $context);
            }
        }
    }

    /**
     * Log a warning message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function log_warning($message, $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Log an error message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function log_error($message, $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Log a critical message
     *
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    public function log_critical($message, $context = array()) {
        $this->log(self::LEVEL_CRITICAL, $message, $context);
        
        // Send email notification for critical errors if feature is available
        if ($this->license->has_feature('email_notifications')) {
            $this->send_error_notification($message, $context);
        }
    }

    /**
     * Send error notification email
     *
     * @param string $message The error message
     * @param array $context The error context
     */
    private function send_error_notification($message, $context) {
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Get site info
        $site_name = get_bloginfo('name');
        $site_url = get_bloginfo('url');
        
        // Format message
        $email_subject = "[{$site_name}] StockCartl Critical Error";
        
        $email_body = "A critical error occurred in StockCartl:\n\n";
        $email_body .= "Message: {$message}\n\n";
        
        if (!empty($context)) {
            $email_body .= "Context:\n";
            foreach ($context as $key => $value) {
                $email_body .= "- {$key}: " . (is_array($value) ? json_encode($value) : $value) . "\n";
            }
        }
        
        $email_body .= "\nSite: {$site_url}\n";
        $email_body .= "Time: " . current_time('mysql') . "\n";
        
        // Add system info
        $email_body .= "\nSystem Information:\n";
        $email_body .= "- WordPress: " . get_bloginfo('version') . "\n";
        
        if (function_exists('WC')) {
            $email_body .= "- WooCommerce: " . WC()->version . "\n";
        }
        
        $email_body .= "- PHP: " . phpversion() . "\n";
        
        // Send email
        wp_mail($admin_email, $email_subject, $email_body);
    }

    /**
     * Output a message to the browser console
     *
     * @param string $level The log level
     * @param string $message The message to log
     * @param array $context Additional context data
     */
    private function console_log($level, $message, $context = array()) {
        // Only log to console in advanced mode
        if ($this->debug_mode < self::MODE_ADVANCED) {
            return;
        }
        
        // Get color based on level
        $color = $this->get_level_color($level);
        
        // Format the message
        $formatted_message = "[StockCartl] [{$level}] {$message}";
        
        // Add script to output to console
        echo '<script>';
        echo 'console.log("%c' . esc_js($formatted_message) . '", "color: ' . esc_js($color) . ';");';
        
        // Output context if available
        if (!empty($context)) {
            echo 'console.log(' . json_encode($context) . ');';
        }
        
        echo '</script>';
    }

    /**
     * Get color for log level
     *
     * @param string $level The log level
     * @return string The color
     */
    private function get_level_color($level) {
        switch ($level) {
            case self::LEVEL_INFO:
                return '#3498db'; // Blue
            case self::LEVEL_WARNING:
                return '#f39c12'; // Orange
            case self::LEVEL_ERROR:
                return '#e74c3c'; // Red
            case self::LEVEL_CRITICAL:
                return '#c0392b'; // Dark Red
            default:
                return '#2c3e50'; // Dark Blue
        }
    }

    /**
     * Display debug info in footer
     */
    public function display_debug_info() {
        // Only show in advanced mode
        if ($this->debug_mode < self::MODE_ADVANCED) {
            return;
        }
        
        // Collect system info
        $info = array(
            'WordPress' => get_bloginfo('version'),
            'PHP' => phpversion(),
            'StockCartl' => STOCKCARTL_VERSION,
            'Debug Mode' => $this->debug_mode == self::MODE_ADVANCED ? 'Advanced' : 'Basic',
            'Memory Usage' => size_format(memory_get_usage()),
            'Memory Limit' => WP_MEMORY_LIMIT,
        );
        
        // Add WooCommerce info if available
        if (function_exists('WC')) {
            $info['WooCommerce'] = WC()->version;
        }
        
        // Output debug info
        echo '<div class="stockcartl-debug-info" style="position: fixed; bottom: 10px; right: 10px; z-index: 9999; background: rgba(0,0,0,0.8); color: #fff; padding: 10px; border-radius: 5px; font-family: monospace; font-size: 12px; max-width: 300px;">';
        echo '<h4 style="margin: 0 0 5px; color: #d4af37;">StockCartl Debug Info</h4>';
        echo '<ul style="margin: 0; padding: 0 0 0 15px;">';
        
        foreach ($info as $key => $value) {
            echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
        }
        
        echo '</ul>';
        echo '<p style="margin: 5px 0 0; font-size: 10px; text-align: right;"><a href="' . esc_url(admin_url('admin.php?page=stockcartl-settings&tab=debugging')) . '" style="color: #d4af37; text-decoration: none;">Manage Debug Settings</a></p>';
        echo '</div>';
    }

    /**
     * Get the log file content
     *
     * @param int $lines Number of lines to get (0 for all)
     * @return string The log content
     */
    public function get_log_content($lines = 0) {
        // Check if log file exists
        if (!file_exists($this->log_file)) {
            return '';
        }
        
        // Get file content
        $content = file_get_contents($this->log_file);
        
        // Return all content if lines is 0 or file is empty
        if ($lines <= 0 || empty($content)) {
            return $content;
        }
        
        // Get the last X lines
        $content_array = explode(PHP_EOL, $content);
        $content_array = array_filter($content_array); // Remove empty lines
        $content_array = array_slice($content_array, -$lines);
        
        return implode(PHP_EOL, $content_array);
    }

    /**
     * Clear the log file
     *
     * @return bool Success or failure
     */
    public function clear_log() {
        return @file_put_contents($this->log_file, '') !== false;
    }

    /**
     * Get all log files
     *
     * @return array Array of log files
     */
    public function get_log_files() {
        $files = glob($this->log_dir . '/*.log');
        $log_files = array();
        
        foreach ($files as $file) {
            // Skip current log file
            if ($file == $this->log_file) {
                continue;
            }
            
            $filename = basename($file);
            $filesize = size_format(filesize($file));
            $filetime = filemtime($file);
            
            $log_files[] = array(
                'path' => $file,
                'name' => $filename,
                'size' => $filesize,
                'time' => $filetime,
                'date' => date_i18n(get_option('date_format'), $filetime)
            );
        }
        
        // Sort by time (newest first)
        usort($log_files, function($a, $b) {
            return $b['time'] - $a['time'];
        });
        
        return $log_files;
    }

    /**
     * Archive current log file
     *
     * @return bool Success or failure
     */
    public function archive_log() {
        // Check if log file exists and has content
        if (!file_exists($this->log_file) || filesize($this->log_file) == 0) {
            return false;
        }
        
        // Create archive filename with date
        $date = date('Y-m-d');
        $archive_file = $this->log_dir . '/stockcartl-' . $date . '.log';
        
        // If archive already exists, append a number
        $counter = 1;
        while (file_exists($archive_file)) {
            $archive_file = $this->log_dir . '/stockcartl-' . $date . '-' . $counter . '.log';
            $counter++;
        }
        
        // Copy current log to archive
        $result = @copy($this->log_file, $archive_file);
        
        // Clear current log if copy was successful
        if ($result) {
            $this->clear_log();
        }
        
        return $result;
    }

    /**
     * Delete a log file
     *
     * @param string $file The log file path
     * @return bool Success or failure
     */
    public function delete_log_file($file) {
        // Make sure the file is in our log directory
        if (strpos($file, $this->log_dir) !== 0) {
            return false;
        }
        
        // Don't allow deleting the current log file
        if ($file == $this->log_file) {
            return false;
        }
        
        // Delete the file
        return @unlink($file);
    }

    /**
     * Cleanup old log files
     */
    public function cleanup_logs() {
        // Get retention period
        $retention_days = 7; // Default retention period
        
        // Get retention from settings if available
        if ($this->settings) {
            $retention_days = (int) $this->settings->get('log_retention_days', 7);
        }
        
        // Allow premium retention periods
        if ($this->license->has_feature('advanced_logging')) {
            $retention_days = max($retention_days, 30); // Minimum 30 days for premium
        }
        
        if ($this->license->has_feature('unlimited_retention')) {
            return; // Unlimited retention, skip cleanup
        }
        
        // Get all log files
        $log_files = $this->get_log_files();
        
        // Calculate cutoff time
        $cutoff_time = time() - ($retention_days * DAY_IN_SECONDS);
        
        // Delete old files
        foreach ($log_files as $file) {
            if ($file['time'] < $cutoff_time) {
                $this->delete_log_file($file['path']);
            }
        }
    }

    /**
     * Get system information
     *
     * @return array System information
     */
    public function get_system_info() {
        global $wpdb;
        
        // Collect system info
        $info = array(
            'wordpress' => array(
                'version' => get_bloginfo('version'),
                'site_url' => get_bloginfo('url'),
                'home_url' => get_home_url(),
                'is_multisite' => is_multisite() ? 'Yes' : 'No',
                'memory_limit' => WP_MEMORY_LIMIT,
                'debug_mode' => defined('WP_DEBUG') && WP_DEBUG ? 'Yes' : 'No',
                'locale' => get_locale()
            ),
            'server' => array(
                'php_version' => phpversion(),
                'mysql_version' => $wpdb->db_version(),
                'server_software' => $_SERVER['SERVER_SOFTWARE'],
                'post_max_size' => ini_get('post_max_size'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'max_execution_time' => ini_get('max_execution_time'),
                'max_input_vars' => ini_get('max_input_vars'),
                'server_time' => date_i18n(get_option('date_format') . ' ' . get_option('time_format'), time())
            ),
            'woocommerce' => array(
                'version' => function_exists('WC') ? WC()->version : 'Not installed',
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'N/A',
                'order_count' => function_exists('wc_get_order_types') ? $this->get_order_count() : 'N/A',
                'product_count' => function_exists('wc_get_products') ? $this->get_product_count() : 'N/A',
                'tax_display_shop' => function_exists('get_option') ? get_option('woocommerce_tax_display_shop') : 'N/A',
                'tax_display_cart' => function_exists('get_option') ? get_option('woocommerce_tax_display_cart') : 'N/A',
                'hpos_enabled' => $this->is_hpos_enabled() ? 'Yes' : 'No'
            ),
            'stockcartl' => array(
                'version' => STOCKCARTL_VERSION,
                'debug_mode' => $this->debug_mode == self::MODE_ADVANCED ? 'Advanced' : ($this->debug_mode == self::MODE_BASIC ? 'Basic' : 'Disabled'),
                'waitlist_count' => $this->get_waitlist_count(),
                'deposit_enabled' => $this->settings ? ($this->settings->get('deposit_enabled') ? 'Yes' : 'No') : 'N/A',
                'deposit_percentage' => $this->settings ? $this->settings->get('deposit_percentage') . '%' : 'N/A',
                'waitlist_expiration' => $this->settings ? $this->settings->get('waitlist_expiration_days') . ' days' : 'N/A'
            ),
            'theme' => array(
                'name' => wp_get_theme()->get('Name'),
                'version' => wp_get_theme()->get('Version'),
                'author' => wp_get_theme()->get('Author'),
                'child_theme' => is_child_theme() ? 'Yes' : 'No',
                'parent_theme' => is_child_theme() ? wp_get_theme()->parent()->get('Name') . ' ' . wp_get_theme()->parent()->get('Version') : 'N/A'
            ),
            'active_plugins' => $this->get_active_plugins()
        );
        
        return $info;
    }

    /**
     * Get order count
     *
     * @return int Order count
     */
    private function get_order_count() {
        if (!function_exists('wc_get_order_types')) {
            return 0;
        }
        
        $order_count = 0;
        $order_types = wc_get_order_types('order-count');
        
        // Use WooCommerce HPOS compatible way to count orders
        if ($this->is_hpos_enabled()) {
            $order_count = wc_get_orders(array(
                'type' => $order_types,
                'limit' => -1,
                'return' => 'ids',
            ));
            
            return count($order_count);
        } else {
            // Legacy method
            foreach ($order_types as $order_type) {
                $order_count += wp_count_posts($order_type)->publish;
            }
            
            return $order_count;
        }
    }

    /**
     * Get product count
     *
     * @return int Product count
     */
    private function get_product_count() {
        if (!function_exists('wc_get_products')) {
            return 0;
        }
        
        $products = wc_get_products(array(
            'limit' => -1,
            'return' => 'ids',
        ));
        
        return count($products);
    }

    /**
     * Check if HPOS is enabled
     *
     * @return bool Whether HPOS is enabled
     */
    private function is_hpos_enabled() {
        if (!class_exists('Automattic\WooCommerce\Utilities\OrderUtil')) {
            return false;
        }
        
        return \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    /**
     * Get waitlist count
     *
     * @return int Waitlist count
     */
    private function get_waitlist_count() {
        global $wpdb;
        
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
            return 0;
        }
        
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM $table WHERE status = 'active'");
    }

    /**
     * Get active plugins
     *
     * @return array Active plugins
     */
    private function get_active_plugins() {
        // Get active plugins
        $active_plugins = get_option('active_plugins', array());
        $plugins = array();
        
        foreach ($active_plugins as $plugin) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
            
            if (!empty($plugin_data['Name'])) {
                $plugins[] = array(
                    'name' => $plugin_data['Name'],
                    'version' => $plugin_data['Version'],
                    'author' => $plugin_data['Author']
                );
            }
        }
        
        return $plugins;
    }

    /**
     * Export system information as text
     *
     * @return string System information as text
     */
    public function export_system_info() {
        $info = $this->get_system_info();
        $text = "### StockCartl System Information ###\n\n";
        
        foreach ($info as $section => $data) {
            $text .= "### " . ucfirst($section) . " ###\n\n";
            
            if (is_array($data)) {
                foreach ($data as $key => $value) {
                    if (is_array($value)) {
                        $text .= $key . ":\n";
                        foreach ($value as $k => $v) {
                            $text .= "  - " . $k . ": " . $v . "\n";
                        }
                    } else {
                        $text .= $key . ": " . $value . "\n";
                    }
                }
            }
            
            $text .= "\n";
        }
        
        return $text;
    }
}

// Initialize the debug class
add_action('plugins_loaded', array('StockCartl_Debug', 'get_instance'), 11);

/**
 * Helper function to get debug instance
 *
 * @return StockCartl_Debug Debug instance
 */
function stockcartl_debug() {
    global $stockcartl_debug;
    
    // Return global instance if available
    if (isset($stockcartl_debug) && $stockcartl_debug instanceof StockCartl_Debug) {
        return $stockcartl_debug;
    }
    
    // Fall back to creating a new instance
    $stockcartl_debug = StockCartl_Debug::get_instance();
    return $stockcartl_debug;
}