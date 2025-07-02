<?php
/**
 * Payments functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Payments Class
 * 
 * Handles deposit payments and refunds
 */
class StockCartl_Payments {

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
        
        // Hook into WooCommerce
        add_filter('woocommerce_order_status_changed', array($this, 'handle_order_status_change'), 10, 4);
        
        // Hook into product stock changes
        add_action('woocommerce_product_set_stock', array($this, 'handle_product_stock_change'));
        add_action('woocommerce_variation_set_stock', array($this, 'handle_variation_stock_change'));
        
        // Add custom order status
        add_action('init', array($this, 'register_waitlist_deposit_status'));
        add_filter('wc_order_statuses', array($this, 'add_waitlist_deposit_status'));
        
        // HPOS Compatibility
        add_action('before_woocommerce_init', array($this, 'declare_hpos_compatibility'));
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
     * Declare compatibility with HPOS (High-Performance Order Storage)
     */
    public function declare_hpos_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                plugin_basename(STOCKCARTL_PLUGIN_FILE),
                true
            );
        }
    }
    
    /**
     * Register custom order status
     */
    public function register_waitlist_deposit_status() {
        register_post_status('wc-waitlist-deposit', array(
            'label'                     => _x('Waitlist Deposit', 'Order status', 'stockcartl'),
            'public'                    => true,
            'exclude_from_search'       => false,
            'show_in_admin_all_list'    => true,
            'show_in_admin_status_list' => true,
            'label_count'               => _n_noop('Waitlist Deposit <span class="count">(%s)</span>', 'Waitlist Deposit <span class="count">(%s)</span>', 'stockcartl')
        ));
    }
    
    /**
     * Add custom order status to WooCommerce order statuses
     * 
     * @param array $order_statuses Existing order statuses
     * @return array Modified order statuses
     */
    public function add_waitlist_deposit_status($order_statuses) {
        $new_statuses = array();
        
        // Insert after processing
        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;
            
            if ($key === 'wc-processing') {
                $new_statuses['wc-waitlist-deposit'] = _x('Waitlist Deposit', 'Order status', 'stockcartl');
            }
        }
        
        return $new_statuses;
    }

    /**
     * Create a deposit order for a waitlist entry
     * 
     * @param int $entry_id The waitlist entry ID
     * @return int|false The order ID or false on failure
     */
    public function create_deposit_order($entry_id) {
        global $wpdb;
        
        $debug = $this->get_debug();
        if ($debug) {
            $debug->log_info('Creating deposit order', array(
                'entry_id' => $entry_id
            ));
        }
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            if ($debug) {
                $debug->log_error('Failed to create deposit order - entry not found', array(
                    'entry_id' => $entry_id
                ));
            }
            return false;
        }
        
        // Get product data
        $product = wc_get_product($entry->product_id);
        if (!$product) {
            if ($debug) {
                $debug->log_error('Failed to create deposit order - product not found', array(
                    'entry_id' => $entry_id,
                    'product_id' => $entry->product_id
                ));
            }
            return false;
        }
        
        // Get variation if applicable
        $variation = null;
        if ($entry->variation_id) {
            $variation = wc_get_product($entry->variation_id);
            if (!$variation) {
                if ($debug) {
                    $debug->log_error('Failed to create deposit order - variation not found', array(
                        'entry_id' => $entry_id,
                        'product_id' => $entry->product_id,
                        'variation_id' => $entry->variation_id
                    ));
                }
                return false;
            }
        }
        
        // Calculate deposit amount (default 25%)
        $deposit_percentage = (int) $this->settings->get('deposit_percentage', 25);
        $product_price = $variation ? $variation->get_price() : $product->get_price();
        $deposit_amount = round(($product_price * $deposit_percentage / 100), 2);
        
        if ($debug) {
            $debug->log_info('Calculating deposit amount', array(
                'entry_id' => $entry_id,
                'product_price' => $product_price,
                'deposit_percentage' => $deposit_percentage,
                'calculated_amount' => $deposit_amount
            ));
        }
        
        // Check minimum deposit amount
        if ($deposit_amount < 0.01) {
            $deposit_amount = 0.01; // Minimum amount
            if ($debug) {
                $debug->log_info('Deposit amount below minimum, using minimum amount', array(
                    'entry_id' => $entry_id,
                    'minimum_amount' => $deposit_amount
                ));
            }
        }
        
        try {
            // Create order using WooCommerce CRUD API
            $order = new WC_Order();
            
            // Set order properties using CRUD methods
            $order->set_status('pending');
            $order->set_customer_id($entry->user_id);
            $order->set_customer_note(sprintf(
                __('Waitlist deposit for %s. Position will be secured after payment.', 'stockcartl'),
                $variation ? $variation->get_formatted_name() : $product->get_name()
            ));
            $order->set_created_via('stockcartl');
            $order->set_billing_email($entry->email);
            
            // Add product
            $product_to_add = $variation ?: $product;
            $item = new WC_Order_Item_Product();
            $item->set_product($product_to_add);
            $item->set_quantity(1);
            $item->set_subtotal($deposit_amount);
            $item->set_total($deposit_amount);
            $item->set_name(sprintf(
                __('%s%% Waitlist Deposit: %s', 'stockcartl'),
                $deposit_percentage,
                $product_to_add->get_formatted_name()
            ));
            $order->add_item($item);
            
            // Add order meta
            $order->add_meta_data('_stockcartl_waitlist_entry_id', $entry_id);
            $order->add_meta_data('_stockcartl_deposit_percentage', $deposit_percentage);
            $order->add_meta_data('_stockcartl_is_deposit', 'yes');
            
            // Set order total
            $order->set_total($deposit_amount);
            
            // Save order
            $order->save();
            $order_id = $order->get_id();
            
            if ($debug) {
                $debug->log_info('Deposit order created successfully', array(
                    'entry_id' => $entry_id,
                    'order_id' => $order_id,
                    'deposit_amount' => $deposit_amount
                ));
            }
            
            // Update waitlist entry
            $result = $wpdb->update(
                $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST,
                array(
                    'waitlist_type' => 'deposit_pending',
                    'deposit_amount' => $deposit_amount,
                    'deposit_order_id' => $order_id
                ),
                array('id' => $entry_id)
            );
            
            if ($result === false) {
                if ($debug) {
                    $debug->log_error('Failed to update waitlist entry with order details', array(
                        'entry_id' => $entry_id,
                        'order_id' => $order_id,
                        'wpdb_error' => $wpdb->last_error
                    ));
                }
            }
            
            return $order_id;
        } catch (Exception $e) {
            if ($debug) {
                $debug->log_error('Exception occurred while creating deposit order', array(
                    'entry_id' => $entry_id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ));
            }
            
            return false;
        }
    }
    
    /**
     * Handle order status change
     * 
     * @param int $order_id The order ID
     * @param string $from_status Previous status
     * @param string $to_status New status
     * @param WC_Order $order Order object
     */
    public function handle_order_status_change($order_id, $from_status, $to_status, $order) {
        $debug = $this->get_debug();
        
        // Check if this is a waitlist deposit order
        $is_deposit = $order->get_meta('_stockcartl_is_deposit');
        
        // Only proceed if this is a deposit order
        if ($is_deposit !== 'yes') {
            return;
        }
        
        if ($debug) {
            $debug->log_info('Processing waitlist deposit order status change', array(
                'order_id' => $order_id,
                'from_status' => $from_status,
                'to_status' => $to_status
            ));
        }
        
        // Get waitlist entry ID
        $entry_id = $order->get_meta('_stockcartl_waitlist_entry_id');
        if (!$entry_id) {
            if ($debug) {
                $debug->log_error('Cannot process order status change - missing waitlist entry ID', array(
                    'order_id' => $order_id
                ));
            }
            return;
        }
        
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        // Get entry to validate it exists
        $entry = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $entry_id));
        if (!$entry) {
            if ($debug) {
                $debug->log_error('Cannot process order status change - waitlist entry not found', array(
                    'order_id' => $order_id,
                    'entry_id' => $entry_id
                ));
            }
            return;
        }
        
        // Handle completed payment
        if ($to_status === 'completed' || $to_status === 'processing') {
            if ($debug) {
                $debug->log_info('Deposit payment completed, updating waitlist entry', array(
                    'order_id' => $order_id,
                    'entry_id' => $entry_id,
                    'status' => $to_status
                ));
            }
            
            // Update waitlist entry
            $result = $wpdb->update(
                $table,
                array(
                    'waitlist_type' => 'deposit',
                    'priority_score' => 100, // High priority for deposits
                    'status' => 'active'
                ),
                array('id' => $entry_id)
            );
            
            if ($result === false) {
                if ($debug) {
                    $debug->log_error('Failed to update waitlist entry for completed payment', array(
                        'order_id' => $order_id,
                        'entry_id' => $entry_id,
                        'wpdb_error' => $wpdb->last_error
                    ));
                }
            }
            
            // Update order status to custom status
            try {
                $order->update_status('waitlist-deposit', __('Deposit paid and waitlist position secured.', 'stockcartl'));
                
                if ($debug) {
                    $debug->log_info('Updated order to waitlist-deposit status', array(
                        'order_id' => $order_id,
                        'entry_id' => $entry_id
                    ));
                }
            } catch (Exception $e) {
                if ($debug) {
                    $debug->log_error('Failed to update order status', array(
                        'order_id' => $order_id,
                        'entry_id' => $entry_id,
                        'error_message' => $e->getMessage()
                    ));
                }
            }
            
            // Send confirmation email
            $this->send_deposit_confirmation($entry_id);
            
            // Track analytics
            $this->track_deposit_paid($entry_id);
        }
        
        // Handle refunded/cancelled order
        if ($to_status === 'refunded' || $to_status === 'cancelled') {
            if ($debug) {
                $debug->log_info('Deposit order refunded or cancelled', array(
                    'order_id' => $order_id,
                    'entry_id' => $entry_id,
                    'status' => $to_status
                ));
            }
            
            // Update waitlist entry back to free
            $result = $wpdb->update(
                $table,
                array(
                    'waitlist_type' => 'free',
                    'deposit_amount' => 0,
                    'priority_score' => 0
                ),
                array('id' => $entry_id)
            );
            
            if ($result === false) {
                if ($debug) {
                    $debug->log_error('Failed to update waitlist entry for refunded/cancelled order', array(
                        'order_id' => $order_id,
                        'entry_id' => $entry_id,
                        'wpdb_error' => $wpdb->last_error
                    ));
                }
            }
            
            // Track analytics
            $this->track_deposit_refunded($entry_id);
        }
    }
    
    /**
     * Handle product stock change
     * 
     * @param WC_Product $product The product that changed
     */
    public function handle_product_stock_change($product) {
        // Only process if stock became available
        if (!$product->is_in_stock()) {
            return;
        }
        
        // Check if we should process waitlist
        $this->process_waitlist_for_product($product->get_id());
    }
    
    /**
     * Handle variation stock change
     * 
     * @param WC_Product_Variation $variation The variation that changed
     */
    public function handle_variation_stock_change($variation) {
        // Only process if stock became available
        if (!$variation->is_in_stock()) {
            return;
        }
        
        // Check if we should process waitlist
        $this->process_waitlist_for_product($variation->get_parent_id(), $variation->get_id());
    }
    
    /**
     * Process waitlist for a product that came back in stock
     * 
     * @param int $product_id The product ID
     * @param int $variation_id The variation ID (optional)
     */
    public function process_waitlist_for_product($product_id, $variation_id = null) {
        // Log the event
        $debug = $this->get_debug();
        if ($debug) {
            $debug->log_info('Processing waitlist for product', array(
                'product_id' => $product_id,
                'variation_id' => $variation_id,
            ));
        }
        
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        // Get active waitlist entries
        $core = new StockCartl_Core();
        $entries = $core->get_waitlist_entries($product_id, $variation_id);
        
        if (empty($entries)) {
            return;
        }
        
        // Send notifications
        $notifications = new StockCartl_Notifications($this->settings);
        
        foreach ($entries as $entry) {
            // Send back in stock notification
            $notifications->send_product_available_notification($entry->id);
            
            // Update entry status
            $wpdb->update(
                $table,
                array('status' => 'notified'),
                array('id' => $entry->id)
            );
            
            // Track analytics
            $this->track_notification_sent($entry->id);
        }
    }
    
    /**
     * Process expired waitlist entries
     */
    public function process_expired_entries() {
        // Log the event
        $debug = $this->get_debug();
        if ($debug) {
            $debug->log_info('Processing expired waitlist entries');
        }
        
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_WAITLIST;
        
        // Find expired entries with deposits
        $expired_entries = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE status = 'active' 
            AND waitlist_type = 'deposit' 
            AND expires_at < %s 
            AND deposit_order_id > 0",
            current_time('mysql')
        ));
        
        if (empty($expired_entries)) {
            return;
        }
        
        foreach ($expired_entries as $entry) {
            // Process refund
            $this->process_deposit_refund($entry->id);
            
            // Update entry status
            $wpdb->update(
                $table,
                array('status' => 'expired'),
                array('id' => $entry->id)
            );
            
            // Track analytics
            $this->track_entry_expired($entry->id);
        }
    }
    
    /**
     * Process deposit refund for an expired entry
     * 
     * @param int $entry_id The waitlist entry ID
     * @return bool Success or failure
     */
    public function process_deposit_refund($entry_id) {
        global $wpdb;
        
        $debug = $this->get_debug();
        if ($debug) {
            $debug->log_info('Processing deposit refund for expired entry', array(
                'entry_id' => $entry_id
            ));
        }
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            if ($debug) {
                $debug->log_error('Failed to process refund - entry not found', array(
                    'entry_id' => $entry_id
                ));
            }
            return false;
        }
        
        if ($entry->waitlist_type !== 'deposit' || !$entry->deposit_order_id) {
            if ($debug) {
                $debug->log_error('Failed to process refund - entry is not a deposit or missing order ID', array(
                    'entry_id' => $entry_id,
                    'waitlist_type' => $entry->waitlist_type,
                    'deposit_order_id' => $entry->deposit_order_id
                ));
            }
            return false;
        }
        
        // Get order using WooCommerce API
        $order = wc_get_order($entry->deposit_order_id);
        if (!$order) {
            if ($debug) {
                $debug->log_error('Failed to process refund - order not found', array(
                    'entry_id' => $entry_id,
                    'order_id' => $entry->deposit_order_id
                ));
            }
            return false;
        }
        
        // Check if already refunded
        if ($order->get_status() === 'refunded') {
            if ($debug) {
                $debug->log_info('Order already refunded, skipping', array(
                    'entry_id' => $entry_id,
                    'order_id' => $entry->deposit_order_id
                ));
            }
            return true;
        }
        
        try {
            // Create refund using WooCommerce API
            $refund_args = array(
                'amount' => $entry->deposit_amount,
                'reason' => __('Waitlist expired - automatic refund', 'stockcartl'),
                'order_id' => $order->get_id(),
                'line_items' => array()
            );
            
            if ($debug) {
                $debug->log_info('Creating refund', array(
                    'entry_id' => $entry_id,
                    'order_id' => $entry->deposit_order_id,
                    'amount' => $entry->deposit_amount
                ));
            }
            
            // Create the refund
            $refund = wc_create_refund($refund_args);
            
            if (is_wp_error($refund)) {
                $error_message = $refund->get_error_message();
                
                if ($debug) {
                    $debug->log_error('Failed to create refund - WP error', array(
                        'entry_id' => $entry_id,
                        'order_id' => $entry->deposit_order_id,
                        'error_message' => $error_message
                    ));
                }
                
                // Log error to standard error log
                error_log('StockCartl refund failed: ' . $error_message);
                return false;
            }
            
            // Add note to order
            $order->add_order_note(__('Waitlist entry expired. Deposit automatically refunded.', 'stockcartl'));
            
            if ($debug) {
                $debug->log_info('Refund created successfully', array(
                    'entry_id' => $entry_id,
                    'order_id' => $entry->deposit_order_id,
                    'refund_id' => $refund->get_id()
                ));
            }
            
            // Send notification
            $notifications = new StockCartl_Notifications($this->settings);
            $notifications->send_deposit_refunded_notification($entry_id);
            
            return true;
        } catch (Exception $e) {
            if ($debug) {
                $debug->log_error('Exception occurred while processing refund', array(
                    'entry_id' => $entry_id,
                    'order_id' => $entry->deposit_order_id,
                    'error_message' => $e->getMessage(),
                    'error_trace' => $e->getTraceAsString()
                ));
            }
            
            return false;
        }
    }
    
    /**
     * Send deposit confirmation email
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function send_deposit_confirmation($entry_id) {
        $notifications = new StockCartl_Notifications($this->settings);
        $notifications->send_deposit_confirmation($entry_id);
    }
    
    /**
     * Track deposit paid in analytics
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function track_deposit_paid($entry_id) {
        $this->track_analytics('deposit_paid', $entry_id);
    }
    
    /**
     * Track deposit refunded in analytics
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function track_deposit_refunded($entry_id) {
        $this->track_analytics('deposit_refunded', $entry_id);
    }
    
    /**
     * Track notification sent in analytics
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function track_notification_sent($entry_id) {
        $this->track_analytics('notification_sent', $entry_id);
    }
    
    /**
     * Track entry expired in analytics
     * 
     * @param int $entry_id The waitlist entry ID
     */
    private function track_entry_expired($entry_id) {
        $this->track_analytics('entry_expired', $entry_id);
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
                    'waitlist_type' => $entry->waitlist_type,
                    'deposit_amount' => $entry->deposit_amount
                )),
                'user_id' => $entry->user_id,
                'email' => $entry->email,
                'product_id' => $entry->product_id,
                'variation_id' => $entry->variation_id
            )
        );
    }
}