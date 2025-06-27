<?php
/**
 * Admin functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Admin Class
 * 
 * Handles admin dashboard and management
 */
class StockCartl_Admin {

    /**
     * Settings instance
     *
     * @var StockCartl_Settings
     */
    private $settings;

    /**
     * Constructor
     * 
     * @param StockCartl_Settings $settings Settings instance
     */
    public function __construct($settings) {
        $this->settings = $settings;
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Add admin scripts and styles
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Add product tab
        add_filter('woocommerce_product_data_tabs', array($this, 'add_product_tab'));
        
        // Add product tab content
        add_action('woocommerce_product_data_panels', array($this, 'add_product_tab_content'));
        
        // Save product meta
        add_action('woocommerce_process_product_meta', array($this, 'save_product_meta'));
        
        // Add AJAX handlers
        add_action('wp_ajax_stockcartl_load_waitlist', array($this, 'ajax_load_waitlist'));
        add_action('wp_ajax_stockcartl_delete_entry', array($this, 'ajax_delete_entry'));
        add_action('wp_ajax_stockcartl_export_waitlist', array($this, 'ajax_export_waitlist'));
        
        // HPOS Compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
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
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('StockCartl', 'stockcartl'),
            __('StockCartl', 'stockcartl'),
            'manage_woocommerce',
            'stockcartl',
            array($this, 'render_dashboard_page'),
            'dashicons-list-view',
            58 // After WooCommerce
        );
        
        add_submenu_page(
            'stockcartl',
            __('Dashboard', 'stockcartl'),
            __('Dashboard', 'stockcartl'),
            'manage_woocommerce',
            'stockcartl',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'stockcartl',
            __('Waitlists', 'stockcartl'),
            __('Waitlists', 'stockcartl'),
            'manage_woocommerce',
            'stockcartl-waitlists',
            array($this, 'render_waitlists_page')
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     * 
     * @param string $hook The current admin page
     */
    public function enqueue_admin_scripts($hook) {
        // Only load on our admin pages
        if (strpos($hook, 'stockcartl') === false && strpos($hook, 'post.php') === false) {
            return;
        }
        
        // Enqueue styles
        wp_enqueue_style(
            'stockcartl-admin',
            STOCKCARTL_PLUGIN_URL . 'assets/css/stockcartl-admin.css',
            array(),
            STOCKCARTL_VERSION
        );
        
        // Enqueue scripts
        wp_enqueue_script(
            'stockcartl-admin',
            STOCKCARTL_PLUGIN_URL . 'assets/js/stockcartl-admin.js',
            array('jquery'),
            STOCKCARTL_VERSION,
            true
        );
        
        // Add localized data for the script
        wp_localize_script('stockcartl-admin', 'stockcartl_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stockcartl_admin'),
            'i18n' => array(
                'confirm_delete' => __('Are you sure you want to delete this waitlist entry?', 'stockcartl'),
                'entry_deleted' => __('Waitlist entry deleted.', 'stockcartl'),
                'error' => __('An error occurred. Please try again.', 'stockcartl')
            )
        ));
    }
    
    /**
     * Add product tab
     * 
     * @param array $tabs Existing tabs
     * @return array Modified tabs
     */
    public function add_product_tab($tabs) {
        $tabs['stockcartl'] = array(
            'label' => __('StockCartl', 'stockcartl'),
            'target' => 'stockcartl_product_data',
            'class' => array(),
            'priority' => 61 // After Linked Products
        );
        
        return $tabs;
    }
    
    /**
     * Add product tab content
     */
    public function add_product_tab_content() {
        global $post;
        
        // Get product
        $product = wc_get_product($post->ID);
        if (!$product) {
            return;
        }
        
        // Get waitlist entries count
        $core = new StockCartl_Core();
        $waitlist_count = $core->count_waitlist_entries($product->get_id(), null, 'active');
        
        // Get product settings
        $product_settings = array(
            'enabled' => $this->settings->get('enabled', '1', 'product', $product->get_id()),
            'deposit_enabled' => $this->settings->get('deposit_enabled', '1', 'product', $product->get_id()),
            'deposit_percentage' => $this->settings->get('deposit_percentage', '25', 'product', $product->get_id()),
            'social_proof_text' => $this->settings->get('social_proof_text', '{count} people waiting', 'product', $product->get_id())
        );
        
        ?>
        <div id="stockcartl_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <p class="form-field">
                    <label><?php esc_html_e('Waitlist Status', 'stockcartl'); ?></label>
                    <?php if ($waitlist_count > 0) : ?>
                        <span class="stockcartl-badge stockcartl-badge-active"><?php printf(_n('%s person waiting', '%s people waiting', $waitlist_count, 'stockcartl'), $waitlist_count); ?></span>
                    <?php else : ?>
                        <span class="stockcartl-badge stockcartl-badge-inactive"><?php esc_html_e('No waitlist', 'stockcartl'); ?></span>
                    <?php endif; ?>
                </p>
                
                <?php
                woocommerce_wp_checkbox(array(
                    'id' => '_stockcartl_enabled',
                    'label' => __('Enable Waitlist', 'stockcartl'),
                    'description' => __('Enable waitlist for this product.', 'stockcartl'),
                    'value' => $product_settings['enabled']
                ));
                
                woocommerce_wp_checkbox(array(
                    'id' => '_stockcartl_deposit_enabled',
                    'label' => __('Enable Deposits', 'stockcartl'),
                    'description' => __('Allow deposits for priority position.', 'stockcartl'),
                    'value' => $product_settings['deposit_enabled']
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_stockcartl_deposit_percentage',
                    'label' => __('Deposit Percentage', 'stockcartl'),
                    'description' => __('Percentage of product price to charge as deposit.', 'stockcartl'),
                    'type' => 'number',
                    'custom_attributes' => array(
                        'min' => '1',
                        'max' => '100',
                        'step' => '1'
                    ),
                    'value' => $product_settings['deposit_percentage'],
                    'desc_tip' => true
                ));
                
                woocommerce_wp_text_input(array(
                    'id' => '_stockcartl_social_proof_text',
                    'label' => __('Social Proof Text', 'stockcartl'),
                    'description' => __('Use {count} as a placeholder for the number of people on the waitlist.', 'stockcartl'),
                    'value' => $product_settings['social_proof_text'],
                    'desc_tip' => true
                ));
                ?>
            </div>
            
            <?php if ($waitlist_count > 0) : ?>
            <div class="options_group">
                <h4><?php esc_html_e('Waitlist Entries', 'stockcartl'); ?></h4>
                
                <div class="stockcartl-waitlist-entries">
                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Email', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Type', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Position', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Date Added', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Actions', 'stockcartl'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $entries = $core->get_waitlist_entries($product->get_id());
                            foreach ($entries as $entry) :
                                $type_label = $entry->waitlist_type === 'deposit' ? 
                                    __('Deposit', 'stockcartl') : 
                                    __('Free', 'stockcartl');
                                
                                $date_added = date_i18n(get_option('date_format'), strtotime($entry->created_at));
                            ?>
                            <tr>
                                <td><?php echo esc_html($entry->email); ?></td>
                                <td>
                                    <span class="stockcartl-type-badge stockcartl-type-<?php echo esc_attr($entry->waitlist_type); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($entry->position); ?></td>
                                <td><?php echo esc_html($date_added); ?></td>
                                <td>
                                    <a href="#" class="stockcartl-delete-entry" data-id="<?php echo esc_attr($entry->id); ?>">
                                        <?php esc_html_e('Delete', 'stockcartl'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <p class="stockcartl-export-link">
                        <a href="<?php echo esc_url(admin_url('admin-ajax.php?action=stockcartl_export_waitlist&product_id=' . $product->get_id() . '&nonce=' . wp_create_nonce('stockcartl_export'))); ?>" class="button">
                            <?php esc_html_e('Export to CSV', 'stockcartl'); ?>
                        </a>
                    </p>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="options_group">
                <h4><?php esc_html_e('Variable Products', 'stockcartl'); ?></h4>
                <p class="description"><?php esc_html_e('For variable products, waitlists are managed per variation. Customers will only see the waitlist form when they select an out-of-stock variation.', 'stockcartl'); ?></p>
                
                <?php if ($product->is_type('variable')) : ?>
                <p class="stockcartl-variations-note">
                    <?php esc_html_e('View and manage variation waitlists in the Waitlists admin page.', 'stockcartl'); ?>
                </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save product meta
     * 
     * @param int $product_id The product ID
     */
    public function save_product_meta($product_id) {
        // Save product-specific settings
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_SETTINGS;
        
        $fields = array(
            '_stockcartl_enabled' => 'enabled',
            '_stockcartl_deposit_enabled' => 'deposit_enabled',
            '_stockcartl_deposit_percentage' => 'deposit_percentage',
            '_stockcartl_social_proof_text' => 'social_proof_text'
        );
        
        foreach ($fields as $field_id => $setting_key) {
            if (isset($_POST[$field_id])) {
                $value = sanitize_text_field($_POST[$field_id]);
                
                // Check if setting exists
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table 
                    WHERE setting_scope = 'product' 
                    AND scope_id = %d 
                    AND setting_key = %s",
                    $product_id, $setting_key
                ));
                
                if ($exists) {
                    // Update existing setting
                    $wpdb->update(
                        $table,
                        array('setting_value' => $value),
                        array(
                            'setting_scope' => 'product',
                            'scope_id' => $product_id,
                            'setting_key' => $setting_key
                        )
                    );
                } else {
                    // Insert new setting
                    $wpdb->insert(
                        $table,
                        array(
                            'setting_scope' => 'product',
                            'scope_id' => $product_id,
                            'setting_key' => $setting_key,
                            'setting_value' => $value
                        )
                    );
                }
            }
        }
    }
    
    /**
     * Render dashboard page
     */
    public function render_dashboard_page() {
        global $wpdb;
        
        // Get total waitlist entries
        $waitlist_table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        $total_entries = $wpdb->get_var("SELECT COUNT(*) FROM $waitlist_table WHERE status = 'active'");
        
        // Get deposit stats
        $deposit_entries = $wpdb->get_var("SELECT COUNT(*) FROM $waitlist_table WHERE status = 'active' AND waitlist_type = 'deposit'");
        $deposit_total = $wpdb->get_var("SELECT SUM(deposit_amount) FROM $waitlist_table WHERE status = 'active' AND waitlist_type = 'deposit'");
        
        // Get top products
        $top_products = $wpdb->get_results("
            SELECT product_id, COUNT(*) as count 
            FROM $waitlist_table 
            WHERE status = 'active' 
            GROUP BY product_id 
            ORDER BY count DESC 
            LIMIT 5
        ");
        
        // Get recent entries
        $recent_entries = $wpdb->get_results("
            SELECT * FROM $waitlist_table 
            WHERE status = 'active' 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        
        ?>
        <div class="wrap stockcartl-dashboard">
            <h1><?php esc_html_e('StockCartl Dashboard', 'stockcartl'); ?></h1>
            
            <div class="stockcartl-dashboard-header">
                <div class="stockcartl-logo">
                    <img src="<?php echo esc_url(STOCKCARTL_PLUGIN_URL . 'assets/images/stockcartl-logo.png'); ?>" alt="StockCartl">
                </div>
                <div class="stockcartl-version">
                    <span><?php printf(__('Version %s', 'stockcartl'), STOCKCARTL_VERSION); ?></span>
                </div>
            </div>
            
            <div class="stockcartl-dashboard-cards">
                <div class="stockcartl-card">
                    <div class="stockcartl-card-header">
                        <h2><?php esc_html_e('Total Waitlist Entries', 'stockcartl'); ?></h2>
                    </div>
                    <div class="stockcartl-card-content">
                        <div class="stockcartl-stat">
                            <span class="stockcartl-stat-number"><?php echo esc_html($total_entries); ?></span>
                            <span class="stockcartl-stat-label"><?php esc_html_e('Active Entries', 'stockcartl'); ?></span>
                        </div>
                    </div>
                </div>
                
                <div class="stockcartl-card">
                    <div class="stockcartl-card-header">
                        <h2><?php esc_html_e('Deposit Stats', 'stockcartl'); ?></h2>
                    </div>
                    <div class="stockcartl-card-content">
                        <div class="stockcartl-stat">
                            <span class="stockcartl-stat-number"><?php echo esc_html($deposit_entries); ?></span>
                            <span class="stockcartl-stat-label"><?php esc_html_e('Deposit Entries', 'stockcartl'); ?></span>
                        </div>
                        <div class="stockcartl-stat">
                            <span class="stockcartl-stat-number"><?php echo wc_price($deposit_total ?: 0); ?></span>
                            <span class="stockcartl-stat-label"><?php esc_html_e('Total Deposits', 'stockcartl'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="stockcartl-dashboard-row">
                <div class="stockcartl-dashboard-col">
                    <div class="stockcartl-card">
                        <div class="stockcartl-card-header">
                            <h2><?php esc_html_e('Top Products', 'stockcartl'); ?></h2>
                        </div>
                        <div class="stockcartl-card-content">
                            <?php if (!empty($top_products)) : ?>
                                <table class="widefat stockcartl-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Product', 'stockcartl'); ?></th>
                                            <th><?php esc_html_e('Waitlist Count', 'stockcartl'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product_data) : 
                                            $product = wc_get_product($product_data->product_id);
                                            if (!$product) continue;
                                        ?>
                                        <tr>
                                            <td>
                                                <a href="<?php echo esc_url(get_edit_post_link($product_data->product_id)); ?>">
                                                    <?php echo esc_html($product->get_name()); ?>
                                                </a>
                                            </td>
                                            <td><?php echo esc_html($product_data->count); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php esc_html_e('No waitlist entries yet.', 'stockcartl'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <div class="stockcartl-dashboard-col">
                    <div class="stockcartl-card">
                        <div class="stockcartl-card-header">
                            <h2><?php esc_html_e('Recent Entries', 'stockcartl'); ?></h2>
                        </div>
                        <div class="stockcartl-card-content">
                            <?php if (!empty($recent_entries)) : ?>
                                <table class="widefat stockcartl-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Email', 'stockcartl'); ?></th>
                                            <th><?php esc_html_e('Product', 'stockcartl'); ?></th>
                                            <th><?php esc_html_e('Type', 'stockcartl'); ?></th>
                                            <th><?php esc_html_e('Date', 'stockcartl'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_entries as $entry) : 
                                            $product = wc_get_product($entry->product_id);
                                            if (!$product) continue;
                                            
                                            $type_label = $entry->waitlist_type === 'deposit' ? 
                                                __('Deposit', 'stockcartl') : 
                                                __('Free', 'stockcartl');
                                            
                                            $date_added = date_i18n(get_option('date_format'), strtotime($entry->created_at));
                                        ?>
                                        <tr>
                                            <td><?php echo esc_html($entry->email); ?></td>
                                            <td>
                                                <a href="<?php echo esc_url(get_edit_post_link($entry->product_id)); ?>">
                                                    <?php echo esc_html($product->get_name()); ?>
                                                </a>
                                            </td>
                                            <td>
                                                <span class="stockcartl-type-badge stockcartl-type-<?php echo esc_attr($entry->waitlist_type); ?>">
                                                    <?php echo esc_html($type_label); ?>
                                                </span>
                                            </td>
                                            <td><?php echo esc_html($date_added); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else : ?>
                                <p><?php esc_html_e('No waitlist entries yet.', 'stockcartl'); ?></p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
            .stockcartl-dashboard-header {
                display: flex;
                align-items: center;
                margin-bottom: 20px;
            }
            
            .stockcartl-logo {
                margin-right: 20px;
            }
            
            .stockcartl-logo img {
                max-height: 50px;
            }
            
            .stockcartl-version {
                color: #999;
                font-style: italic;
            }
            
            .stockcartl-dashboard-cards {
                display: flex;
                flex-wrap: wrap;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .stockcartl-card {
                background: #fff;
                border: 1px solid #e5e5e5;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
                margin-bottom: 20px;
                flex: 1;
                min-width: 250px;
            }
            
            .stockcartl-card-header {
                border-bottom: 1px solid #eee;
                padding: 10px 15px;
            }
            
            .stockcartl-card-header h2 {
                margin: 0;
                font-size: 14px;
                font-weight: 600;
            }
            
            .stockcartl-card-content {
                padding: 15px;
            }
            
            .stockcartl-stat {
                text-align: center;
                padding: 10px;
                display: inline-block;
                margin-right: 20px;
            }
            
            .stockcartl-stat-number {
                display: block;
                font-size: 24px;
                font-weight: 600;
                color: #d4af37; /* Gold */
                margin-bottom: 5px;
            }
            
            .stockcartl-stat-label {
                display: block;
                font-size: 14px;
                color: #666;
            }
            
            .stockcartl-dashboard-row {
                display: flex;
                flex-wrap: wrap;
                margin: 0 -10px;
            }
            
            .stockcartl-dashboard-col {
                flex: 1;
                min-width: 48%;
                padding: 0 10px;
            }
            
            .stockcartl-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .stockcartl-table th,
            .stockcartl-table td {
                padding: 8px 10px;
                text-align: left;
            }
            
            .stockcartl-type-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .stockcartl-type-deposit {
                background-color: #d4af37; /* Gold */
                color: #fff;
            }
            
            .stockcartl-type-free {
                background-color: #f0f0f0;
                color: #666;
            }
            
            @media screen and (max-width: 782px) {
                .stockcartl-dashboard-col {
                    min-width: 100%;
                }
            }
        </style>
        <?php
    }
    
    /**
     * Render waitlists page
     */
    public function render_waitlists_page() {
        global $wpdb;
        
        // Handle filters
        $product_filter = isset($_GET['product_id']) ? absint($_GET['product_id']) : 0;
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'active';
        $type_filter = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : '';
        
        // Build query
        $waitlist_table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        $query = "SELECT * FROM $waitlist_table WHERE 1=1";
        
        if ($product_filter) {
            $query .= $wpdb->prepare(" AND product_id = %d", $product_filter);
        }
        
        if ($status_filter) {
            $query .= $wpdb->prepare(" AND status = %s", $status_filter);
        }
        
        if ($type_filter) {
            $query .= $wpdb->prepare(" AND waitlist_type = %s", $type_filter);
        }
        
        $query .= " ORDER BY created_at DESC";
        
        // Pagination
        $per_page = 20;
        $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $total_items = $wpdb->get_var("SELECT COUNT(*) FROM ($query) AS t");
        $total_pages = ceil($total_items / $per_page);
        
        $query .= $wpdb->prepare(" LIMIT %d OFFSET %d", $per_page, ($current_page - 1) * $per_page);
        
        // Get entries
        $entries = $wpdb->get_results($query);
        
        // Get products for filter
        $products_query = "
            SELECT DISTINCT p.ID, p.post_title 
            FROM {$wpdb->posts} p 
            JOIN $waitlist_table w ON p.ID = w.product_id 
            WHERE p.post_type = 'product' 
            AND p.post_status = 'publish'
            ORDER BY p.post_title ASC
        ";
        $products = $wpdb->get_results($products_query);
        
        ?>
        <div class="wrap stockcartl-waitlists">
            <h1><?php esc_html_e('StockCartl Waitlists', 'stockcartl'); ?></h1>
            
            <div class="stockcartl-filters">
                <form method="get">
                    <input type="hidden" name="page" value="stockcartl-waitlists">
                    
                    <select name="product_id">
                        <option value=""><?php esc_html_e('All Products', 'stockcartl'); ?></option>
                        <?php foreach ($products as $product) : ?>
                            <option value="<?php echo esc_attr($product->ID); ?>" <?php selected($product_filter, $product->ID); ?>>
                                <?php echo esc_html($product->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'stockcartl'); ?></option>
                        <option value="active" <?php selected($status_filter, 'active'); ?>><?php esc_html_e('Active', 'stockcartl'); ?></option>
                        <option value="notified" <?php selected($status_filter, 'notified'); ?>><?php esc_html_e('Notified', 'stockcartl'); ?></option>
                        <option value="expired" <?php selected($status_filter, 'expired'); ?>><?php esc_html_e('Expired', 'stockcartl'); ?></option>
                    </select>
                    
                    <select name="type">
                        <option value=""><?php esc_html_e('All Types', 'stockcartl'); ?></option>
                        <option value="free" <?php selected($type_filter, 'free'); ?>><?php esc_html_e('Free', 'stockcartl'); ?></option>
                        <option value="deposit" <?php selected($type_filter, 'deposit'); ?>><?php esc_html_e('Deposit', 'stockcartl'); ?></option>
                    </select>
                    
                    <button type="submit" class="button"><?php esc_html_e('Filter', 'stockcartl'); ?></button>
                    
                    <?php if ($product_filter || $status_filter || $type_filter) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=stockcartl-waitlists')); ?>" class="button"><?php esc_html_e('Reset', 'stockcartl'); ?></a>
                    <?php endif; ?>
                    
                    <?php if (!empty($entries)) : ?>
                        <a href="<?php echo esc_url(add_query_arg(array('action' => 'stockcartl_export_waitlist', 'nonce' => wp_create_nonce('stockcartl_export')), admin_url('admin-ajax.php'))); ?>" class="button button-secondary stockcartl-export-button"><?php esc_html_e('Export to CSV', 'stockcartl'); ?></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="stockcartl-waitlist-table-wrap">
                <?php if (!empty($entries)) : ?>
                    <table class="widefat stockcartl-waitlist-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Email', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Product', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Variation', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Type', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Position', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Status', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Date Added', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Expires', 'stockcartl'); ?></th>
                                <th><?php esc_html_e('Actions', 'stockcartl'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($entries as $entry) : 
                                $product = wc_get_product($entry->product_id);
                                if (!$product) continue;
                                
                                $variation = null;
                                if ($entry->variation_id) {
                                    $variation = wc_get_product($entry->variation_id);
                                }
                                
                                $type_label = $entry->waitlist_type === 'deposit' ? 
                                    __('Deposit', 'stockcartl') : 
                                    __('Free', 'stockcartl');
                                
                                $status_labels = array(
                                    'active' => __('Active', 'stockcartl'),
                                    'notified' => __('Notified', 'stockcartl'),
                                    'expired' => __('Expired', 'stockcartl')
                                );
                                
                                $status_label = isset($status_labels[$entry->status]) ? 
                                    $status_labels[$entry->status] : 
                                    $entry->status;
                                
                                $date_added = date_i18n(get_option('date_format'), strtotime($entry->created_at));
                                $date_expires = $entry->expires_at ? 
                                    date_i18n(get_option('date_format'), strtotime($entry->expires_at)) : 
                                    '-';
                            ?>
                            <tr>
                                <td><?php echo esc_html($entry->email); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($entry->product_id)); ?>">
                                        <?php echo esc_html($product->get_name()); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($variation) : ?>
                                        <?php 
                                        $attributes = wc_get_formatted_variation($variation, true);
                                        echo esc_html($attributes);
                                        ?>
                                    <?php else : ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="stockcartl-type-badge stockcartl-type-<?php echo esc_attr($entry->waitlist_type); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($entry->position); ?></td>
                                <td>
                                    <span class="stockcartl-status-badge stockcartl-status-<?php echo esc_attr($entry->status); ?>">
                                        <?php echo esc_html($status_label); ?>
                                    </span>
                                </td>
                                <td><?php echo esc_html($date_added); ?></td>
                                <td><?php echo esc_html($date_expires); ?></td>
                                <td>
                                    <a href="#" class="stockcartl-delete-entry" data-id="<?php echo esc_attr($entry->id); ?>">
                                        <?php esc_html_e('Delete', 'stockcartl'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <?php if ($total_pages > 1) : ?>
                        <div class="stockcartl-pagination">
                            <?php
                            echo paginate_links(array(
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;', 'stockcartl'),
                                'next_text' => __('&raquo;', 'stockcartl'),
                                'total' => $total_pages,
                                'current' => $current_page
                            ));
                            ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else : ?>
                    <div class="stockcartl-no-entries">
                        <p><?php esc_html_e('No waitlist entries found.', 'stockcartl'); ?></p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <style>
            .stockcartl-filters {
                margin: 20px 0;
                background: #fff;
                padding: 10px 15px;
                border: 1px solid #e5e5e5;
                box-shadow: 0 1px 1px rgba(0,0,0,0.04);
            }
            
            .stockcartl-filters select {
                margin-right: 10px;
            }
            
            .stockcartl-export-button {
                float: right;
            }
            
            .stockcartl-waitlist-table-wrap {
                margin-top: 20px;
            }
            
            .stockcartl-waitlist-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .stockcartl-waitlist-table th,
            .stockcartl-waitlist-table td {
                padding: 8px 10px;
                text-align: left;
            }
            
            .stockcartl-type-badge,
            .stockcartl-status-badge {
                display: inline-block;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
            }
            
            .stockcartl-type-deposit {
                background-color: #d4af37; /* Gold */
                color: #fff;
            }
            
            .stockcartl-type-free {
                background-color: #f0f0f0;
                color: #666;
            }
            
            .stockcartl-status-active {
                background-color: #46b450;
                color: #fff;
            }
            
            .stockcartl-status-notified {
                background-color: #4a90e2; /* Electric Blue */
                color: #fff;
            }
            
            .stockcartl-status-expired {
                background-color: #dc3232;
                color: #fff;
            }
            
            .stockcartl-pagination {
                margin-top: 20px;
                text-align: center;
            }
            
            .stockcartl-no-entries {
                background: #fff;
                padding: 20px;
                text-align: center;
                border: 1px solid #e5e5e5;
            }
        </style>
        <?php
    }
    
    /**
     * AJAX handler to load waitlist
     */
    public function ajax_load_waitlist() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_admin')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'stockcartl')));
        }
        
        // Check product ID
        if (!isset($_POST['product_id'])) {
            wp_send_json_error(array('message' => __('Missing product ID.', 'stockcartl')));
        }
        
        $product_id = absint($_POST['product_id']);
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : null;
        
        // Get waitlist entries
        $core = new StockCartl_Core();
        $entries = $core->get_waitlist_entries($product_id, $variation_id);
        
        // Format entries for response
        $formatted_entries = array();
        foreach ($entries as $entry) {
            $formatted_entries[] = array(
                'id' => $entry->id,
                'email' => $entry->email,
                'type' => $entry->waitlist_type,
                'position' => $entry->position,
                'date_added' => date_i18n(get_option('date_format'), strtotime($entry->created_at))
            );
        }
        
        wp_send_json_success(array('entries' => $formatted_entries));
    }
    
    /**
     * AJAX handler to delete waitlist entry
     */
    public function ajax_delete_entry() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_admin')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'stockcartl')));
        }
        
        // Check entry ID
        if (!isset($_POST['entry_id'])) {
            wp_send_json_error(array('message' => __('Missing entry ID.', 'stockcartl')));
        }
        
        $entry_id = absint($_POST['entry_id']);
        
        // Delete entry
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        $result = $wpdb->delete($table, array('id' => $entry_id));
        
        if ($result === false) {
            wp_send_json_error(array('message' => __('Failed to delete entry.', 'stockcartl')));
        }
        
        wp_send_json_success(array('message' => __('Entry deleted successfully.', 'stockcartl')));
    }
    
    /**
     * AJAX handler to export waitlist to CSV
     */
    public function ajax_export_waitlist() {
        // Verify nonce
        if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'stockcartl_export')) {
            wp_die(__('Security check failed.', 'stockcartl'));
        }
        
        global $wpdb;
        $waitlist_table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        // Build query
        $query = "SELECT w.*, p.post_title as product_name FROM $waitlist_table w 
                 LEFT JOIN {$wpdb->posts} p ON w.product_id = p.ID WHERE 1=1";
        
        // Apply filters if provided
        if (isset($_GET['product_id']) && absint($_GET['product_id']) > 0) {
            $query .= $wpdb->prepare(" AND w.product_id = %d", absint($_GET['product_id']));
        }
        
        if (isset($_GET['status']) && !empty($_GET['status'])) {
            $query .= $wpdb->prepare(" AND w.status = %s", sanitize_text_field($_GET['status']));
        }
        
        if (isset($_GET['type']) && !empty($_GET['type'])) {
            $query .= $wpdb->prepare(" AND w.waitlist_type = %s", sanitize_text_field($_GET['type']));
        }
        
        $query .= " ORDER BY w.created_at DESC";
        
        // Get entries
        $entries = $wpdb->get_results($query);
        
        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=stockcartl-waitlist-' . date('Y-m-d') . '.csv');
        
        // Create output stream
        $output = fopen('php://output', 'w');
        
        // Add CSV headers
        fputcsv($output, array(
            __('ID', 'stockcartl'),
            __('Email', 'stockcartl'),
            __('Product', 'stockcartl'),
            __('Product ID', 'stockcartl'),
            __('Variation ID', 'stockcartl'),
            __('Type', 'stockcartl'),
            __('Deposit Amount', 'stockcartl'),
            __('Position', 'stockcartl'),
            __('Status', 'stockcartl'),
            __('Date Added', 'stockcartl'),
            __('Expiration Date', 'stockcartl')
        ));
        
        // Add data rows
        foreach ($entries as $entry) {
            fputcsv($output, array(
                $entry->id,
                $entry->email,
                $entry->product_name,
                $entry->product_id,
                $entry->variation_id ?: '',
                $entry->waitlist_type,
                $entry->deposit_amount,
                $entry->position,
                $entry->status,
                $entry->created_at,
                $entry->expires_at ?: ''
            ));
        }
        
        fclose($output);
        exit;
    }
}