<?php
/**
 * Frontend functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Frontend Class
 * 
 * Handles frontend display and AJAX form processing
 */
class StockCartl_Frontend {

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
        
        // Hook into WooCommerce product page
        add_action('woocommerce_single_product_summary', array($this, 'maybe_display_waitlist_form'), 35);
        
        // Add scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
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
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on product pages
        if (!is_product()) {
            return;
        }

        // Enqueue main styles
        wp_enqueue_style(
            'stockcartl-styles',
            STOCKCARTL_PLUGIN_URL . 'assets/css/stockcartl-frontend.css',
            array(),
            STOCKCARTL_VERSION
        );

        // Enqueue main script
        wp_enqueue_script(
            'stockcartl-scripts',
            STOCKCARTL_PLUGIN_URL . 'assets/js/stockcartl-frontend.js',
            array('jquery'),
            STOCKCARTL_VERSION,
            true
        );

        // Add localized data for the script
        wp_localize_script('stockcartl-scripts', 'stockcartl_data', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('stockcartl_join_waitlist'),
            'i18n' => array(
                'join_success' => __('You have been added to the waitlist!', 'stockcartl'),
                'join_error' => __('There was an error adding you to the waitlist. Please try again.', 'stockcartl'),
                'email_invalid' => __('Please enter a valid email address.', 'stockcartl')
            )
        ));
    }

    /**
     * Check if we should display the waitlist form and display it if needed
     */
    public function maybe_display_waitlist_form() {
        global $product;

        // Check if plugin is enabled
        if (!$this->settings->get('enabled')) {
            return;
        }

        // Only show for out of stock products
        if ($product->is_in_stock()) {
            return;
        }

        // Handle simple products
        if ($product->is_type('simple')) {
            $this->display_waitlist_form($product->get_id());
            return;
        }

        // Handle variable products
        if ($product->is_type('variable')) {
            $this->display_variable_product_waitlist($product);
            return;
        }
    }

    /**
     * Display waitlist form for simple products
     * 
     * @param int $product_id The product ID
     */
    public function display_waitlist_form($product_id, $variation_id = null) {
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }

        // Get waitlist count
        $core = new StockCartl_Core();
        $waitlist_count = $core->count_waitlist_entries($product_id, $variation_id);
        
        // Get settings
        $button_text = $this->settings->get('button_text');
        $social_proof_text = $this->settings->get('social_proof_text');
        $min_social_proof = (int) $this->settings->get('min_social_proof');
        $deposit_enabled = $this->settings->get('deposit_enabled');
        $deposit_percentage = (int) $this->settings->get('deposit_percentage');
        $deposit_button_text = $this->settings->get('deposit_button_text');

        // Calculate deposit amount
        $deposit_amount = 0;
        if ($deposit_enabled) {
            $price = $product->get_price();
            $deposit_amount = round(($price * $deposit_percentage / 100), 2);
        }

        // Check if user is logged in
        $current_user = wp_get_current_user();
        $email = '';
        if ($current_user->exists()) {
            $email = $current_user->user_email;
        }

        // Check if user is already on waitlist
        $is_on_waitlist = false;
        if ($email) {
            global $wpdb;
            $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
            
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE product_id = %d 
                AND email = %s 
                AND status = 'active'",
                $product_id, $email
            );
            
            if ($variation_id) {
                $query = $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table 
                    WHERE product_id = %d 
                    AND variation_id = %d 
                    AND email = %s 
                    AND status = 'active'",
                    $product_id, $variation_id, $email
                );
            }
            
            $count = $wpdb->get_var($query);
            $is_on_waitlist = ($count > 0);
        }

        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = STOCKCARTL_PLUGIN_DIR . 'templates/waitlist-form.php';
        
        // Allow template override in theme
        $theme_template = locate_template('stockcartl/waitlist-form.php');
        if ($theme_template) {
            $template_path = $theme_template;
        }
        
        // Include the template with variables
        include $template_path;
        
        // Print the form
        echo ob_get_clean();
    }

    /**
     * Display waitlist handling for variable products
     * 
     * @param WC_Product_Variable $product The variable product
     */
    public function display_variable_product_waitlist($product) {
        // Add JavaScript to handle variation changes
        wc_enqueue_js("
            jQuery(document).ready(function($) {
                var stockcartlForm = $('.stockcartl-waitlist-form-wrapper');
                
                // Initially hide the form
                stockcartlForm.hide();
                
                // Listen for variation changes
                $('.variations_form').on('show_variation', function(event, variation) {
                    if (!variation.is_in_stock) {
                        // Load waitlist form via AJAX
                        $.ajax({
                            url: stockcartl_data.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'stockcartl_load_variation_form',
                                product_id: " . $product->get_id() . ",
                                variation_id: variation.variation_id,
                                nonce: stockcartl_data.nonce
                            },
                            success: function(response) {
                                if (response.success) {
                                    stockcartlForm.html(response.data.html).show();
                                }
                            }
                        });
                    } else {
                        stockcartlForm.hide();
                    }
                });
                
                // Hide form when no variation is selected
                $('.variations_form').on('hide_variation', function() {
                    stockcartlForm.hide();
                });
            });
        ");
        
        // Add placeholder for the form
        echo '<div class="stockcartl-waitlist-form-wrapper" style="display:none;"></div>';
        
        // Add AJAX handler for loading variation form
        add_action('wp_ajax_stockcartl_load_variation_form', array($this, 'ajax_load_variation_form'));
        add_action('wp_ajax_nopriv_stockcartl_load_variation_form', array($this, 'ajax_load_variation_form'));
    }
    
    /**
     * AJAX handler to load waitlist form for a variation
     */
    public function ajax_load_variation_form() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_join_waitlist')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'stockcartl')));
        }
        
        // Check product and variation IDs
        if (!isset($_POST['product_id']) || !isset($_POST['variation_id'])) {
            wp_send_json_error(array('message' => __('Missing product information.', 'stockcartl')));
        }
        
        $product_id = absint($_POST['product_id']);
        $variation_id = absint($_POST['variation_id']);
        
        // Start output buffer
        ob_start();
        
        // Display form
        $this->display_waitlist_form($product_id, $variation_id);
        
        // Get form HTML
        $form_html = ob_get_clean();
        
        // Return form HTML
        wp_send_json_success(array('html' => $form_html));
    }

    /**
     * Process waitlist join form submission
     */
    public function process_join_waitlist() {
        // Verify required fields
        if (!isset($_POST['email']) || !isset($_POST['product_id'])) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'stockcartl')));
        }
        
        // Sanitize inputs
        $email = sanitize_email($_POST['email']);
        $product_id = absint($_POST['product_id']);
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        // Validate email
        if (!is_email($email)) {
            wp_send_json_error(array('message' => __('Please enter a valid email address.', 'stockcartl')));
        }
        
        // Validate product
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array('message' => __('Invalid product.', 'stockcartl')));
        }
        
        // Check if product is actually out of stock
        if ($product->is_in_stock()) {
            wp_send_json_error(array('message' => __('This product is in stock. Please add it to your cart.', 'stockcartl')));
        }
        
        // Check if variation exists and is valid
        if ($variation_id > 0) {
            $variation = wc_get_product($variation_id);
            if (!$variation || $variation->get_parent_id() != $product_id) {
                wp_send_json_error(array('message' => __('Invalid product variation.', 'stockcartl')));
            }
            
            // Check if variation is actually out of stock
            if ($variation->is_in_stock()) {
                wp_send_json_error(array('message' => __('This variation is in stock. Please add it to your cart.', 'stockcartl')));
            }
        }
        
        // Check if user is already on waitlist
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE product_id = %d 
            AND email = %s 
            AND status = 'active'",
            $product_id, $email
        );
        
        if ($variation_id > 0) {
            $query = $wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                WHERE product_id = %d 
                AND variation_id = %d 
                AND email = %s 
                AND status = 'active'",
                $product_id, $variation_id, $email
            );
        }
        
        $count = $wpdb->get_var($query);
        if ($count > 0) {
            wp_send_json_error(array('message' => __('You are already on the waitlist for this product.', 'stockcartl')));
        }
        
        // Get user ID if email matches a registered user
        $user_id = email_exists($email) ? get_user_by('email', $email)->ID : null;
        
        // Set expiration date
        $expiration_days = (int) $this->settings->get('waitlist_expiration_days');
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expiration_days} days"));
        
        // Get waitlist type (free or deposit)
        $waitlist_type = 'free';
        $deposit_amount = 0;
        
        // Prepare waitlist entry data
        $entry_data = array(
            'user_id' => $user_id,
            'email' => $email,
            'product_id' => $product_id,
            'variation_id' => $variation_id > 0 ? $variation_id : null,
            'waitlist_type' => $waitlist_type,
            'deposit_amount' => $deposit_amount,
            'priority_score' => 0, // Will be updated for deposit waitlists
            'expires_at' => $expires_at,
            'status' => 'active'
        );
        
        // Add UTM source if available
        if (isset($_POST['utm_source']) && !empty($_POST['utm_source'])) {
            $entry_data['utm_source'] = sanitize_text_field($_POST['utm_source']);
        }
        
        // Insert waitlist entry
        $result = $wpdb->insert($table, $entry_data);
        
        if (!$result) {
            wp_send_json_error(array('message' => __('Failed to add you to the waitlist. Please try again.', 'stockcartl')));
        }
        
        // Get the entry ID
        $entry_id = $wpdb->insert_id;
        
        // Update position based on entry count
        $position = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
            WHERE product_id = %d 
            AND (variation_id IS NULL OR variation_id = %d) 
            AND status = 'active'",
            $product_id, $variation_id
        ));
        
        $wpdb->update(
            $table,
            array('position' => $position),
            array('id' => $entry_id)
        );
        
        // Track analytics (for Pro)
        $this->track_analytics('waitlist_join', $entry_id);
        
        // Send confirmation email
        $this->send_waitlist_joined_notification($entry_id);
        
        // Check if user wants to pay deposit
        if (isset($_POST['deposit']) && $_POST['deposit'] == '1') {
            // Pass to payments class to handle deposit
            require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-payments.php';
            $payments = new StockCartl_Payments($this->settings);
            $order_id = $payments->create_deposit_order($entry_id);
            
            if ($order_id) {
                // Return checkout URL
                $order = wc_get_order($order_id);
                wp_send_json_success(array(
                    'message' => __('You have been added to the waitlist!', 'stockcartl'),
                    'redirect' => $order->get_checkout_payment_url()
                ));
            }
        }
        
        // Return success
        wp_send_json_success(array(
            'message' => __('You have been added to the waitlist!', 'stockcartl'),
            'position' => $position
        ));
    }
    
    /**
     * Track analytics event
     * 
     * @param string $event_type The event type
     * @param int $entry_id The waitlist entry ID
     */
    private function track_analytics($event_type, $entry_id) {
        global $wpdb;
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return;
        }
        
        // Insert analytics event
        $wpdb->insert(
            $wpdb->prefix . STOCKCARTL_TABLE_ANALYTICS,
            array(
                'event_type' => $event_type,
                'event_data' => json_encode(array(
                    'entry_id' => $entry_id,
                    'position' => $entry->position,
                    'waitlist_type' => $entry->waitlist_type
                )),
                'user_id' => $entry->user_id,
                'email' => $entry->email,
                'product_id' => $entry->product_id,
                'variation_id' => $entry->variation_id
            )
        );
    }
    
    /**
     * Send waitlist joined notification
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function send_waitlist_joined_notification($entry_id) {
        require_once STOCKCARTL_PLUGIN_DIR . 'includes/class-notifications.php';
        $notifications = new StockCartl_Notifications($this->settings);
        $notifications->send_waitlist_joined_notification($entry_id);
    }
}