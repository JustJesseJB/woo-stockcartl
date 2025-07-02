<?php
/**
 * Core functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Core Class
 * 
 * Handles database creation and core plugin initialization
 */
class StockCartl_Core {

    /**
     * Frontend class instance
     *
     * @var StockCartl_Frontend
     */
    private $frontend;

    /**
     * Admin class instance
     *
     * @var StockCartl_Admin
     */
    private $admin;

    /**
     * Payments class instance
     *
     * @var StockCartl_Payments
     */
    private $payments;

    /**
     * Notifications class instance
     *
     * @var StockCartl_Notifications
     */
    private $notifications;

    /**
     * Settings class instance
     *
     * @var StockCartl_Settings
     */
    private $settings;

    /**
     * Constructor
     */
    public function __construct() {
        // Database version - increment when schema changes
        $this->db_version = '1.0';

        // Get debug instance
        $this->debug = $this->get_debug();
    }

    /**
     * Get debug instance
     *
     * @return StockCartl_Debug|null Debug instance
     */
    private function get_debug() {
        global $stockcartl_debug;
        
        // Return global instance if available
        if (isset($stockcartl_debug) && $stockcartl_debug instanceof StockCartl_Debug) {
            return $stockcartl_debug;
        }
        
        // Try function if global not available
        if (function_exists('stockcartl_debug')) {
            return stockcartl_debug();
        }
        
        return null;
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Log plugin initialization
        if ($this->debug) {
            $this->debug->log_info('StockCartl plugin initialized', array(
                'version' => STOCKCARTL_VERSION,
                'is_admin' => is_admin() ? 'yes' : 'no',
                'wp_version' => get_bloginfo('version'),
                'php_version' => phpversion()
            ));
        }

        // Check database version and upgrade if needed
        $this->check_db_version();

        // Initialize settings
        require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-settings.php';
        $this->settings = new StockCartl_Settings();
        
        // Load frontend if we're not in admin
        if (!is_admin() || wp_doing_ajax()) {
            require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-frontend.php';
            $this->frontend = new StockCartl_Frontend($this->settings);
        }

        // Load admin if we're in admin area
        if (is_admin()) {
            require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-admin.php';
            $this->admin = new StockCartl_Admin($this->settings);
        }

        // Always load payments and notifications (needed for hooks)
        require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-payments.php';
        $this->payments = new StockCartl_Payments($this->settings);

        require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-notifications.php';
        $this->notifications = new StockCartl_Notifications($this->settings);

        // Add AJAX handler
        add_action('wp_ajax_stockcartl_join_waitlist', array($this, 'ajax_join_waitlist'));
        add_action('wp_ajax_nopriv_stockcartl_join_waitlist', array($this, 'ajax_join_waitlist'));
        
        // Declare HPOS compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
        
        // Log successful initialization
        if ($this->debug) {
            $this->debug->log_info('StockCartl plugin initialization complete');
        }
    }
    
    /**
     * Declare compatibility with HPOS (High-Performance Order Storage)
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                STOCKCARTL_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * AJAX handler for joining waitlist
     */
    public function ajax_join_waitlist() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_join_waitlist')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'stockcartl')));
        }

        // Get frontend class to handle the request if not already loaded
        if (!isset($this->frontend)) {
            require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-frontend.php';
            $this->frontend = new StockCartl_Frontend($this->settings);
        }

        // Pass to frontend class
        $this->frontend->process_join_waitlist();
        exit;
    }

    /**
     * Create the database tables
     */
    public function create_tables() {
        global $wpdb;
        
        // Log database creation start
        if ($this->debug) {
            $this->debug->log_info('Creating/updating database tables', array(
                'db_version' => $this->db_version
            ));
        }
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Main waitlist table
        $table_waitlist = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        $sql_waitlist = "CREATE TABLE $table_waitlist (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) DEFAULT NULL,
            email varchar(191) NOT NULL,
            product_id bigint(20) NOT NULL,
            variation_id bigint(20) DEFAULT NULL,
            waitlist_type varchar(20) DEFAULT 'free',
            deposit_amount decimal(10,2) DEFAULT 0.00,
            deposit_order_id bigint(20) DEFAULT NULL,
            position int(11) DEFAULT 0,
            priority_score int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            expires_at datetime DEFAULT NULL,
            status varchar(20) DEFAULT 'active',
            utm_source varchar(191) DEFAULT NULL,
            conversion_id varchar(191) DEFAULT NULL,
            metadata longtext DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY product_id (product_id),
            KEY variation_id (variation_id),
            KEY email (email),
            KEY user_id (user_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Settings table
        $table_settings = $wpdb->prefix . STOCKCARTL_TABLE_SETTINGS;
        $sql_settings = "CREATE TABLE $table_settings (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            setting_scope varchar(50) NOT NULL DEFAULT 'global',
            scope_id bigint(20) DEFAULT NULL,
            setting_key varchar(191) NOT NULL,
            setting_value longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY scope_key (setting_scope,scope_id,setting_key),
            KEY setting_key (setting_key)
        ) $charset_collate;";
        
        // Analytics table (for Pro version)
        $table_analytics = $wpdb->prefix . STOCKCARTL_TABLE_ANALYTICS;
        $sql_analytics = "CREATE TABLE $table_analytics (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            event_type varchar(50) NOT NULL,
            event_data longtext DEFAULT NULL,
            user_id bigint(20) DEFAULT NULL,
            email varchar(191) DEFAULT NULL,
            product_id bigint(20) DEFAULT NULL,
            variation_id bigint(20) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY event_type (event_type),
            KEY product_id (product_id),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        // Notifications table
        $table_notifications = $wpdb->prefix . STOCKCARTL_TABLE_NOTIFICATIONS;
        $sql_notifications = "CREATE TABLE $table_notifications (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            notification_type varchar(50) NOT NULL,
            recipient varchar(191) NOT NULL,
            subject varchar(191) DEFAULT NULL,
            message longtext DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            scheduled_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            error_message text DEFAULT NULL,
            retry_count int(11) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY status (status),
            KEY scheduled_at (scheduled_at),
            KEY recipient (recipient)
        ) $charset_collate;";
        
        // Use dbDelta to create/update tables
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        try {
            dbDelta($sql_waitlist);
            dbDelta($sql_settings);
            dbDelta($sql_analytics);
            dbDelta($sql_notifications);
            
            // Insert default settings
            $this->insert_default_settings();
            
            // Store database version
            update_option('stockcartl_db_version', $this->db_version);
            
            if ($this->debug) {
                $this->debug->log_info('Database tables created/updated successfully');
            }
        } catch (Exception $e) {
            if ($this->debug) {
                $this->debug->log_error('Error creating database tables', array(
                    'error' => $e->getMessage()
                ));
            }
        }

        // Create log directory
        $upload_dir = wp_upload_dir();
        $log_dir = $upload_dir['basedir'] . '/stockcartl-logs';

        if (!file_exists($log_dir)) {
            $dir_created = wp_mkdir_p($log_dir);
            
            if ($dir_created) {
                if ($this->debug) {
                    $this->debug->log_info('Log directory created', array(
                        'path' => $log_dir
                    ));
                }
                
                // Create .htaccess file to prevent direct access
                $htaccess_file = $log_dir . '/.htaccess';
                if (!file_exists($htaccess_file)) {
                    $htaccess_content = "# Prevent direct access to files\n";
                    $htaccess_content .= "<Files \"*\">\n";
                    $htaccess_content .= "    Require all denied\n";
                    $htaccess_content .= "</Files>";
                    @file_put_contents($htaccess_file, $htaccess_content);
                }
                
                // Create index.php file to prevent directory listing
                $index_file = $log_dir . '/index.php';
                if (!file_exists($index_file)) {
                    @file_put_contents($index_file, "<?php\n// Silence is golden.");
                }
            } else if ($this->debug) {
                $this->debug->log_error('Failed to create log directory', array(
                    'path' => $log_dir,
                    'error' => error_get_last()
                ));
            }
        }
    }
    
    /**
     * Insert default settings
     */
    private function insert_default_settings() {
        global $wpdb;
        $table_settings = $wpdb->prefix . STOCKCARTL_TABLE_SETTINGS;
        
        // Only insert if settings don't exist
        $settings_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_settings");
        
        if ($settings_count == 0) {
            $default_settings = array(
                // General settings
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'enabled',
                    'setting_value' => '1'
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'button_text',
                    'setting_value' => __('Join Waitlist', 'stockcartl')
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'social_proof_text',
                    'setting_value' => __('{count} people waiting', 'stockcartl')
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'min_social_proof',
                    'setting_value' => '3'
                ),
                
                // Deposit settings
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'deposit_enabled',
                    'setting_value' => '1'
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'deposit_percentage',
                    'setting_value' => '25'
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'deposit_button_text',
                    'setting_value' => __('Secure Your Spot - Pay Deposit', 'stockcartl')
                ),
                
                // Expiration settings
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'waitlist_expiration_days',
                    'setting_value' => '60'
                ),
                
                // Email settings
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'email_waitlist_joined_subject',
                    'setting_value' => __('You\'ve joined the waitlist for {product_name}', 'stockcartl')
                ),
                array(
                    'setting_scope' => 'global',
                    'scope_id'      => null,
                    'setting_key'   => 'email_product_available_subject',
                    'setting_value' => __('Good news! {product_name} is back in stock', 'stockcartl')
                )
            );
            
            foreach ($default_settings as $setting) {
                $wpdb->insert($table_settings, $setting);
            }
        }
    }
    
    /**
     * Check database version and upgrade if needed
     */
    private function check_db_version() {
        $installed_db_version = get_option('stockcartl_db_version', '0');
        
        if ($this->debug) {
            $this->debug->log_info('Checking database version', array(
                'installed_version' => $installed_db_version,
                'current_version' => $this->db_version,
                'needs_upgrade' => version_compare($installed_db_version, $this->db_version, '<') ? 'yes' : 'no'
            ));
        }
        
        if (version_compare($installed_db_version, $this->db_version, '<')) {
            if ($this->debug) {
                $this->debug->log_info('Database upgrade required', array(
                    'from_version' => $installed_db_version,
                    'to_version' => $this->db_version
                ));
            }
            $this->create_tables();
        }
    }
    
    /**
     * Get a waitlist entry by ID
     * 
     * @param int $entry_id The entry ID
     * @return object|null The waitlist entry or null
     */
    public function get_waitlist_entry($entry_id) {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $entry_id));
    }
    
    /**
     * Get waitlist entries for a product
     * 
     * @param int $product_id The product ID
     * @param int $variation_id The variation ID (optional)
     * @param string $status The status to filter by (default: 'active')
     * @return array The waitlist entries
     */
    public function get_waitlist_entries($product_id, $variation_id = null, $status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        if ($variation_id) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table 
                WHERE product_id = %d 
                AND variation_id = %d 
                AND status = %s 
                ORDER BY priority_score DESC, position ASC",
                $product_id, $variation_id, $status
            ));
        } else {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table 
                WHERE product_id = %d 
                AND (variation_id IS NULL OR variation_id = 0) 
                AND status = %s 
                ORDER BY priority_score DESC, position ASC",
                $product_id, $status
            ));
        }
    }
    
    /**
     * Count waitlist entries for a product
     * 
     * @param int $product_id The product ID
     * @param int $variation_id The variation ID (optional)
     * @param string $status The status to filter by (default: 'active')
     * @return int The number of waitlist entries
     */
    public function count_waitlist_entries($product_id, $variation_id = null, $status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        if ($variation_id) {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE product_id = %d 
                AND variation_id = %d 
                AND status = %s",
                $product_id, $variation_id, $status
            ));
        } else {
            return $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE product_id = %d 
                AND (variation_id IS NULL OR variation_id = 0) 
                AND status = %s",
                $product_id, $status
            ));
        }
    }
}