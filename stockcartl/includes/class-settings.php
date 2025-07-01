<?php
/**
 * Settings functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Settings Class
 * 
 * Handles plugin settings
 */
class StockCartl_Settings {

    /**
     * Settings cache
     *
     * @var array
     */
    private $settings_cache = array();

    /**
     * Constructor
     */
    public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Declare HPOS compatibility
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
        add_submenu_page(
            'woocommerce',
            __('StockCartl Settings', 'stockcartl'),
            __('StockCartl', 'stockcartl'),
            'manage_woocommerce',
            'stockcartl-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('stockcartl_settings', 'stockcartl_settings', array($this, 'validate_settings'));
        
        // General Settings Section
        add_settings_section(
            'stockcartl_general_settings',
            __('General Settings', 'stockcartl'),
            array($this, 'render_general_section'),
            'stockcartl_settings'
        );
        
        // Add fields to general section
        add_settings_field(
            'enabled',
            __('Enable StockCartl', 'stockcartl'),
            array($this, 'render_checkbox_field'),
            'stockcartl_settings',
            'stockcartl_general_settings',
            array(
                'id' => 'enabled',
                'label' => __('Enable waitlist functionality', 'stockcartl'),
                'default' => '1'
            )
        );
        
        add_settings_field(
            'button_text',
            __('Join Button Text', 'stockcartl'),
            array($this, 'render_text_field'),
            'stockcartl_settings',
            'stockcartl_general_settings',
            array(
                'id' => 'button_text',
                'default' => __('Join Waitlist', 'stockcartl')
            )
        );
        
        add_settings_field(
            'social_proof_text',
            __('Social Proof Text', 'stockcartl'),
            array($this, 'render_text_field'),
            'stockcartl_settings',
            'stockcartl_general_settings',
            array(
                'id' => 'social_proof_text',
                'default' => __('{count} people waiting', 'stockcartl'),
                'desc' => __('Use {count} as a placeholder for the number of people on the waitlist.', 'stockcartl')
            )
        );
        
        add_settings_field(
            'min_social_proof',
            __('Minimum Social Proof', 'stockcartl'),
            array($this, 'render_number_field'),
            'stockcartl_settings',
            'stockcartl_general_settings',
            array(
                'id' => 'min_social_proof',
                'default' => '3',
                'desc' => __('Minimum number of people on the waitlist before showing social proof.', 'stockcartl'),
                'min' => '1',
                'max' => '100'
            )
        );
        
        // Deposit Settings Section
        add_settings_section(
            'stockcartl_deposit_settings',
            __('Deposit Settings', 'stockcartl'),
            array($this, 'render_deposit_section'),
            'stockcartl_settings'
        );
        
        // Add fields to deposit section
        add_settings_field(
            'deposit_enabled',
            __('Enable Deposits', 'stockcartl'),
            array($this, 'render_checkbox_field'),
            'stockcartl_settings',
            'stockcartl_deposit_settings',
            array(
                'id' => 'deposit_enabled',
                'label' => __('Allow customers to pay a deposit for priority position', 'stockcartl'),
                'default' => '1'
            )
        );
        
        add_settings_field(
            'deposit_percentage',
            __('Deposit Percentage', 'stockcartl'),
            array($this, 'render_number_field'),
            'stockcartl_settings',
            'stockcartl_deposit_settings',
            array(
                'id' => 'deposit_percentage',
                'default' => '25',
                'desc' => __('Percentage of product price to charge as deposit.', 'stockcartl'),
                'min' => '1',
                'max' => '100',
                'suffix' => '%'
            )
        );
        
        add_settings_field(
            'deposit_button_text',
            __('Deposit Button Text', 'stockcartl'),
            array($this, 'render_text_field'),
            'stockcartl_settings',
            'stockcartl_deposit_settings',
            array(
                'id' => 'deposit_button_text',
                'default' => __('Secure Your Spot - Pay Deposit', 'stockcartl')
            )
        );
        
        // Expiration Settings Section
        add_settings_section(
            'stockcartl_expiration_settings',
            __('Expiration Settings', 'stockcartl'),
            array($this, 'render_expiration_section'),
            'stockcartl_settings'
        );
        
        // Add fields to expiration section
        add_settings_field(
            'waitlist_expiration_days',
            __('Waitlist Expiration', 'stockcartl'),
            array($this, 'render_select_field'),
            'stockcartl_settings',
            'stockcartl_expiration_settings',
            array(
                'id' => 'waitlist_expiration_days',
                'default' => '60',
                'options' => array(
                    '30' => __('30 days', 'stockcartl'),
                    '60' => __('60 days', 'stockcartl'),
                    '90' => __('90 days', 'stockcartl'),
                    '180' => __('180 days', 'stockcartl'),
                    '365' => __('1 year', 'stockcartl')
                ),
                'desc' => __('How long waitlist entries remain active before expiring.', 'stockcartl')
            )
        );
        
        // Email Settings Section
        add_settings_section(
            'stockcartl_email_settings',
            __('Email Settings', 'stockcartl'),
            array($this, 'render_email_section'),
            'stockcartl_settings'
        );
        
        // Add fields to email section
        add_settings_field(
            'email_waitlist_joined_subject',
            __('Waitlist Joined Subject', 'stockcartl'),
            array($this, 'render_text_field'),
            'stockcartl_settings',
            'stockcartl_email_settings',
            array(
                'id' => 'email_waitlist_joined_subject',
                'default' => __('You\'ve joined the waitlist for {product_name}', 'stockcartl'),
                'desc' => __('Use {product_name} and {site_name} as placeholders.', 'stockcartl')
            )
        );
        
        add_settings_field(
            'email_product_available_subject',
            __('Product Available Subject', 'stockcartl'),
            array($this, 'render_text_field'),
            'stockcartl_settings',
            'stockcartl_email_settings',
            array(
                'id' => 'email_product_available_subject',
                'default' => __('Good news! {product_name} is back in stock', 'stockcartl'),
                'desc' => __('Use {product_name} and {site_name} as placeholders.', 'stockcartl')
            )
        );
        // Debugging Settings Section
        add_settings_section(
            'stockcartl_debugging_settings',
            __('Debugging Settings', 'stockcartl'),
            array($this, 'render_debugging_section'),
            'stockcartl_settings'
        );

        // Add fields to debugging section
        add_settings_field(
            'debug_mode',
            __('Debug Mode', 'stockcartl'),
            array($this, 'render_select_field'),
            'stockcartl_settings',
            'stockcartl_debugging_settings',
            array(
                'id' => 'debug_mode',
                'default' => '0',
                'options' => array(
                    '0' => __('Disabled', 'stockcartl'),
                    '1' => __('Basic (Logs Only)', 'stockcartl'),
                    '2' => __('Advanced (Logs + Visual)', 'stockcartl')
                ),
                'desc' => __('Enable debugging to help troubleshoot issues.', 'stockcartl')
            )
        );

        add_settings_field(
            'log_retention_days',
            __('Log Retention', 'stockcartl'),
            array($this, 'render_select_field'),
            'stockcartl_settings',
            'stockcartl_debugging_settings',
            array(
                'id' => 'log_retention_days',
                'default' => '7',
                'options' => array(
                    '7' => __('7 days', 'stockcartl'),
                    '14' => __('14 days', 'stockcartl'),
                    '30' => __('30 days', 'stockcartl'),
                    '90' => __('90 days', 'stockcartl')
                ),
                'desc' => __('How long to keep log files before automatic cleanup.', 'stockcartl')
            )
        );
    }
    
    /**
     * Render settings page
     */
    public function render_settings_page() {
        ?>
        <div class="wrap stockcartl-settings-wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="stockcartl-header">
                <div class="stockcartl-logo">
                    <img src="<?php echo esc_url(STOCKCARTL_PLUGIN_URL . 'assets/images/stockcartl-logo.png'); ?>" alt="StockCartl">
                </div>
                <div class="stockcartl-version">
                    <span><?php printf(__('Version %s', 'stockcartl'), STOCKCARTL_VERSION); ?></span>
                </div>
            </div>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('stockcartl_settings');
                do_settings_sections('stockcartl_settings');
                submit_button();
                ?>
            </form>
        </div>
        <style>
            .stockcartl-settings-wrap {
                max-width: 800px;
            }
            .stockcartl-header {
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
            .form-table th {
                width: 200px;
            }
            .stockcartl-field-desc {
                color: #666;
                font-style: italic;
                margin-top: 5px;
            }
        </style>
        <?php
    }
    
    /**
     * Render general section
     */
    public function render_general_section() {
        echo '<p>' . esc_html__('Configure general waitlist settings.', 'stockcartl') . '</p>';
    }
    
    /**
     * Render deposit section
     */
    public function render_deposit_section() {
        echo '<p>' . esc_html__('Configure deposit settings for priority waitlist.', 'stockcartl') . '</p>';
    }
    
    /**
     * Render expiration section
     */
    public function render_expiration_section() {
        echo '<p>' . esc_html__('Configure how long waitlist entries remain active.', 'stockcartl') . '</p>';
    }
    
    /**
     * Render email section
     */
    public function render_email_section() {
        echo '<p>' . esc_html__('Configure email notification settings.', 'stockcartl') . '</p>';
    }
    
    /**
     * Render debugging section
     */
    public function render_debugging_section() {
        echo '<p>' . esc_html__('Configure debugging and logging settings.', 'stockcartl') . '</p>';
        
        // Check if license class exists
        if (class_exists('StockCartl_License')) {
            $license = new StockCartl_License();
            
            // Show premium features teaser if needed
            if (!$license->has_feature('advanced_logging')) {
                echo '<div class="stockcartl-premium-notice" style="background: #f7f7f7; border-left: 4px solid #d4af37; padding: 10px 12px; margin-bottom: 10px;">';
                echo '<p><strong>' . esc_html__('Upgrade to StockCartl Pro for advanced debugging features:', 'stockcartl') . '</strong></p>';
                echo '<ul style="list-style-type: disc; margin-left: 20px;">';
                echo '<li>' . esc_html__('Advanced logging with multiple log levels', 'stockcartl') . '</li>';
                echo '<li>' . esc_html__('Extended log retention (up to 90 days)', 'stockcartl') . '</li>';
                echo '<li>' . esc_html__('Enhanced log viewer with advanced filtering', 'stockcartl') . '</li>';
                echo '<li>' . esc_html__('Log export functionality', 'stockcartl') . '</li>';
                echo '<li>' . esc_html__('Email notifications for critical errors', 'stockcartl') . '</li>';
                echo '</ul>';
                echo '<p><a href="https://stockcartl.com/pricing" class="button button-primary" target="_blank">' . esc_html__('Upgrade Now', 'stockcartl') . '</a></p>';
                echo '</div>';
            }
        }
    }

    /**
     * Render checkbox field
     * 
     * @param array $args Field arguments
     */
    public function render_checkbox_field($args) {
        $id = $args['id'];
        $label = $args['label'];
        $default = isset($args['default']) ? $args['default'] : '0';
        $desc = isset($args['desc']) ? $args['desc'] : '';
        
        $option_name = "stockcartl_settings[$id]";
        $option_value = $this->get_admin_setting($id, $default);
        
        ?>
        <label for="<?php echo esc_attr($option_name); ?>">
            <input type="checkbox" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="1" <?php checked('1', $option_value); ?>>
            <?php echo esc_html($label); ?>
        </label>
        <?php if ($desc) : ?>
            <p class="stockcartl-field-desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render text field
     * 
     * @param array $args Field arguments
     */
    public function render_text_field($args) {
        $id = $args['id'];
        $default = isset($args['default']) ? $args['default'] : '';
        $desc = isset($args['desc']) ? $args['desc'] : '';
        
        $option_name = "stockcartl_settings[$id]";
        $option_value = $this->get_admin_setting($id, $default);
        
        ?>
        <input type="text" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($option_value); ?>" class="regular-text">
        <?php if ($desc) : ?>
            <p class="stockcartl-field-desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render number field
     * 
     * @param array $args Field arguments
     */
    public function render_number_field($args) {
        $id = $args['id'];
        $default = isset($args['default']) ? $args['default'] : '0';
        $desc = isset($args['desc']) ? $args['desc'] : '';
        $min = isset($args['min']) ? $args['min'] : '';
        $max = isset($args['max']) ? $args['max'] : '';
        $step = isset($args['step']) ? $args['step'] : '1';
        $suffix = isset($args['suffix']) ? $args['suffix'] : '';
        
        $option_name = "stockcartl_settings[$id]";
        $option_value = $this->get_admin_setting($id, $default);
        
        ?>
        <input type="number" id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>" value="<?php echo esc_attr($option_value); ?>" class="small-text" 
            <?php if ($min !== '') echo 'min="' . esc_attr($min) . '"'; ?> 
            <?php if ($max !== '') echo 'max="' . esc_attr($max) . '"'; ?> 
            step="<?php echo esc_attr($step); ?>">
        <?php if ($suffix) echo ' ' . esc_html($suffix); ?>
        <?php if ($desc) : ?>
            <p class="stockcartl-field-desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Render select field
     * 
     * @param array $args Field arguments
     */
    public function render_select_field($args) {
        $id = $args['id'];
        $default = isset($args['default']) ? $args['default'] : '';
        $options = isset($args['options']) ? $args['options'] : array();
        $desc = isset($args['desc']) ? $args['desc'] : '';
        
        $option_name = "stockcartl_settings[$id]";
        $option_value = $this->get_admin_setting($id, $default);
        
        ?>
        <select id="<?php echo esc_attr($option_name); ?>" name="<?php echo esc_attr($option_name); ?>">
            <?php foreach ($options as $value => $label) : ?>
                <option value="<?php echo esc_attr($value); ?>" <?php selected($option_value, $value); ?>><?php echo esc_html($label); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($desc) : ?>
            <p class="stockcartl-field-desc"><?php echo esc_html($desc); ?></p>
        <?php endif; ?>
        <?php
    }
    
    /**
     * Validate settings
     * 
     * @param array $input The submitted settings
     * @return array Validated settings
     */
    public function validate_settings($input) {
        $output = array();
        
        // Validate general settings
        $output['enabled'] = isset($input['enabled']) ? '1' : '0';
        $output['button_text'] = sanitize_text_field($input['button_text']);
        $output['social_proof_text'] = sanitize_text_field($input['social_proof_text']);
        $output['min_social_proof'] = absint($input['min_social_proof']);
        
        // Validate deposit settings
        $output['deposit_enabled'] = isset($input['deposit_enabled']) ? '1' : '0';
        $output['deposit_percentage'] = max(1, min(100, absint($input['deposit_percentage'])));
        $output['deposit_button_text'] = sanitize_text_field($input['deposit_button_text']);
        
        // Validate expiration settings
        $output['waitlist_expiration_days'] = absint($input['waitlist_expiration_days']);
        
        // Validate email settings
        $output['email_waitlist_joined_subject'] = sanitize_text_field($input['email_waitlist_joined_subject']);
        $output['email_product_available_subject'] = sanitize_text_field($input['email_product_available_subject']);
        
        // Validate debug settings
        $output['debug_mode'] = isset($input['debug_mode']) ? absint($input['debug_mode']) : 0;
        $output['log_retention_days'] = isset($input['log_retention_days']) ? absint($input['log_retention_days']) : 7;

        // Save to database
        $this->save_settings_to_db($output);
        
        // Clear cache
        $this->settings_cache = array();
        
        return $output;
    }
    
    /**
     * Save settings to database
     * 
     * @param array $settings The settings to save
     */
    private function save_settings_to_db($settings) {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_SETTINGS;
        
        foreach ($settings as $key => $value) {
            // Check if setting exists
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table 
                WHERE setting_scope = 'global' 
                AND scope_id IS NULL 
                AND setting_key = %s",
                $key
            ));
            
            if ($exists) {
                // Update existing setting
                $wpdb->update(
                    $table,
                    array('setting_value' => $value),
                    array(
                        'setting_scope' => 'global',
                        'scope_id' => null,
                        'setting_key' => $key
                    )
                );
            } else {
                // Insert new setting
                $wpdb->insert(
                    $table,
                    array(
                        'setting_scope' => 'global',
                        'scope_id' => null,
                        'setting_key' => $key,
                        'setting_value' => $value
                    )
                );
            }
        }
    }
    
    /**
     * Get setting value
     * 
     * @param string $key The setting key
     * @param mixed $default Default value if setting not found
     * @param string $scope The setting scope (default: global)
     * @param int $scope_id The scope ID (default: null)
     * @return mixed The setting value
     */
    public function get($key, $default = '', $scope = 'global', $scope_id = null) {
        global $wpdb;
        
        // Check cache first
        $cache_key = $scope . '_' . ($scope_id ? $scope_id : '0') . '_' . $key;
        if (isset($this->settings_cache[$cache_key])) {
            return $this->settings_cache[$cache_key];
        }
        
        // Query database
        $table = $wpdb->prefix . STOCKCARTL_TABLE_SETTINGS;
        
        $query = $wpdb->prepare(
            "SELECT setting_value FROM $table 
            WHERE setting_scope = %s 
            AND setting_key = %s",
            $scope, $key
        );
        
        if ($scope_id === null) {
            $query .= " AND scope_id IS NULL";
        } else {
            $query .= $wpdb->prepare(" AND scope_id = %d", $scope_id);
        }
        
        $value = $wpdb->get_var($query);
        
        // Return default if not found
        if ($value === null) {
            return $default;
        }
        
        // Cache result
        $this->settings_cache[$cache_key] = $value;
        
        return $value;
    }
    
    /**
     * Get admin setting value (from wp_options)
     * 
     * @param string $key The setting key
     * @param mixed $default Default value if setting not found
     * @return mixed The setting value
     */
    private function get_admin_setting($key, $default = '') {
        $options = get_option('stockcartl_settings', array());
        
        if (isset($options[$key])) {
            return $options[$key];
        }
        
        // Try to get from database as fallback
        return $this->get($key, $default);
    }
}