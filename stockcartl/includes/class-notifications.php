<?php
/**
 * Notifications functionality for StockCartl
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl Notifications Class
 * 
 * Handles all email notifications
 */
class StockCartl_Notifications {

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

        // Get debug instance
        $this->debug = $this->get_debug();
        
        // Add scheduled task for processing the notification queue
        add_action('stockcartl_process_notification_queue', array($this, 'process_notification_queue'));
        
        // Make sure the scheduled task is registered
        if (!wp_next_scheduled('stockcartl_process_notification_queue')) {
            wp_schedule_event(time(), 'hourly', 'stockcartl_process_notification_queue');
        }
        
        // Declare HPOS compatibility
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
                STOCKCARTL_PLUGIN_FILE,
                true
            );
        }
    }

    /**
     * Send waitlist joined notification
     * 
     * @param int $entry_id The waitlist entry ID
     * @return bool Success or failure
     */
    public function send_waitlist_joined_notification($entry_id) {
        global $wpdb;
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        // Get product data
        $product = wc_get_product($entry->product_id);
        if (!$product) {
            return false;
        }
        
        // Get variation if applicable
        $variation = null;
        if ($entry->variation_id) {
            $variation = wc_get_product($entry->variation_id);
            if (!$variation) {
                return false;
            }
        }
        
        // Prepare product name
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        
        // Get subject template
        $subject_template = $this->settings->get('email_waitlist_joined_subject', __('You\'ve joined the waitlist for {product_name}', 'stockcartl'));
        
        // Replace placeholders in subject
        $subject = str_replace(
            array('{product_name}', '{site_name}'),
            array($product_name, get_bloginfo('name')),
            $subject_template
        );
        
        // Generate message content
        $message = $this->get_waitlist_joined_email_content($entry, $product, $variation);
        
        // Queue notification
        return $this->queue_notification('waitlist_joined', $entry->email, $subject, $message);
    }
    
    /**
     * Send product available notification
     * 
     * @param int $entry_id The waitlist entry ID
     * @return bool Success or failure
     */
    public function send_product_available_notification($entry_id) {
        global $wpdb;
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        // Get product data
        $product = wc_get_product($entry->product_id);
        if (!$product) {
            return false;
        }
        
        // Get variation if applicable
        $variation = null;
        if ($entry->variation_id) {
            $variation = wc_get_product($entry->variation_id);
            if (!$variation) {
                return false;
            }
        }
        
        // Check if product is actually in stock
        if ($variation) {
            if (!$variation->is_in_stock()) {
                return false;
            }
        } else {
            if (!$product->is_in_stock()) {
                return false;
            }
        }
        
        // Prepare product name
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        
        // Get subject template
        $subject_template = $this->settings->get('email_product_available_subject', __('Good news! {product_name} is back in stock', 'stockcartl'));
        
        // Replace placeholders in subject
        $subject = str_replace(
            array('{product_name}', '{site_name}'),
            array($product_name, get_bloginfo('name')),
            $subject_template
        );
        
        // Generate message content
        $message = $this->get_product_available_email_content($entry, $product, $variation);
        
        // Queue notification
        return $this->queue_notification('product_available', $entry->email, $subject, $message);
    }
    
    /**
     * Send deposit confirmation
     * 
     * @param int $entry_id The waitlist entry ID
     * @return bool Success or failure
     */
    public function send_deposit_confirmation($entry_id) {
        global $wpdb;
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        // Get product data
        $product = wc_get_product($entry->product_id);
        if (!$product) {
            return false;
        }
        
        // Get variation if applicable
        $variation = null;
        if ($entry->variation_id) {
            $variation = wc_get_product($entry->variation_id);
            if (!$variation) {
                return false;
            }
        }
        
        // Prepare product name
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        
        // Get subject
        $subject = sprintf(
            __('Your priority waitlist deposit for %s has been confirmed', 'stockcartl'),
            $product_name
        );
        
        // Generate message content
        $message = $this->get_deposit_confirmation_email_content($entry, $product, $variation);
        
        // Queue notification
        return $this->queue_notification('deposit_confirmation', $entry->email, $subject, $message);
    }
    
    /**
     * Send deposit refunded notification
     * 
     * @param int $entry_id The waitlist entry ID
     * @return bool Success or failure
     */
    public function send_deposit_refunded_notification($entry_id) {
        global $wpdb;
        
        // Get entry data
        $entry = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . STOCKCARTL_TABLE_WAITLIST . " WHERE id = %d",
            $entry_id
        ));
        
        if (!$entry) {
            return false;
        }
        
        // Get product data
        $product = wc_get_product($entry->product_id);
        if (!$product) {
            return false;
        }
        
        // Get variation if applicable
        $variation = null;
        if ($entry->variation_id) {
            $variation = wc_get_product($entry->variation_id);
            if (!$variation) {
                return false;
            }
        }
        
        // Prepare product name
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        
        // Get subject
        $subject = sprintf(
            __('Your waitlist deposit for %s has been refunded', 'stockcartl'),
            $product_name
        );
        
        // Generate message content
        $message = $this->get_deposit_refunded_email_content($entry, $product, $variation);
        
        // Queue notification
        return $this->queue_notification('deposit_refunded', $entry->email, $subject, $message);
    }
    
    /**
     * Queue a notification for sending
     * 
     * @param string $type The notification type
     * @param string $recipient The recipient email
     * @param string $subject The email subject
     * @param string $message The email message
     * @param string $scheduled_at When to send (default: now)
     * @return bool Success or failure
     */
    private function queue_notification($type, $recipient, $subject, $message, $scheduled_at = null) {
        global $wpdb;
        
        // Log the attempt
        if ($this->debug) {
            $this->debug->log_info('Queueing notification', array(
                'type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'scheduled_at' => $scheduled_at ? $scheduled_at : 'immediate'
            ));
        }
        
        // Use current time if not specified
        if (!$scheduled_at) {
            $scheduled_at = current_time('mysql');
        }
        
        // Insert into queue
        $result = $wpdb->insert(
            $wpdb->prefix . STOCKCARTL_TABLE_NOTIFICATIONS,
            array(
                'notification_type' => $type,
                'recipient' => $recipient,
                'subject' => $subject,
                'message' => $message,
                'status' => 'pending',
                'scheduled_at' => $scheduled_at
            )
        );
        
        // Try sending immediately if inserted successfully
        if ($result) {
            $notification_id = $wpdb->insert_id;
            
            if ($this->debug) {
                $this->debug->log_info('Notification queued successfully', array(
                    'notification_id' => $notification_id,
                    'type' => $type
                ));
            }
            
            $this->process_notification($notification_id);
            return true;
        } else {
            if ($this->debug) {
                $this->debug->log_error('Failed to queue notification', array(
                    'error' => $wpdb->last_error,
                    'type' => $type,
                    'recipient' => $recipient
                ));
            }
            
            return false;
        }
    }
    
    /**
     * Process the notification queue
     */
    public function process_notification_queue() {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_NOTIFICATIONS;
        
        if ($this->debug) {
            $this->debug->log_info('Starting notification queue processing');
        }
        
        // Get pending notifications scheduled for now or earlier
        $notifications = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table 
            WHERE status = 'pending' 
            AND scheduled_at <= %s 
            ORDER BY scheduled_at ASC 
            LIMIT 50", // Process in batches
            current_time('mysql')
        ));
        
        $count = count($notifications);
        
        if ($this->debug) {
            $this->debug->log_info('Found notifications to process', array(
                'count' => $count
            ));
        }
        
        if (empty($notifications)) {
            return;
        }
        
        $success_count = 0;
        $fail_count = 0;
        
        foreach ($notifications as $notification) {
            $result = $this->process_notification($notification->id);
            if ($result) {
                $success_count++;
            } else {
                $fail_count++;
            }
        }
        
        if ($this->debug) {
            $this->debug->log_info('Notification queue processing completed', array(
                'total_processed' => $count,
                'successful' => $success_count,
                'failed' => $fail_count
            ));
        }
    }
    
    /**
     * Process a single notification
     * 
     * @param int $notification_id The notification ID
     * @return bool Success or failure
     */
    private function process_notification($notification_id) {
        global $wpdb;
        $table = $wpdb->prefix . STOCKCARTL_TABLE_NOTIFICATIONS;
        
        if ($this->debug) {
            $this->debug->log_info('Processing notification', array(
                'notification_id' => $notification_id
            ));
        }
        
        // Get notification data
        $notification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d",
            $notification_id
        ));
        
        if (!$notification) {
            if ($this->debug) {
                $this->debug->log_error('Notification not found', array(
                    'notification_id' => $notification_id
                ));
            }
            return false;
        }
        
        // Check if already sent
        if ($notification->status === 'sent') {
            if ($this->debug) {
                $this->debug->log_info('Notification already sent', array(
                    'notification_id' => $notification_id
                ));
            }
            return true;
        }
        
        // Update status to processing
        $wpdb->update(
            $table,
            array('status' => 'processing'),
            array('id' => $notification_id)
        );
        
        if ($this->debug) {
            $this->debug->log_info('Sending email notification', array(
                'notification_id' => $notification_id,
                'type' => $notification->notification_type,
                'recipient' => $notification->recipient
            ));
        }
        
        // Send email
        $headers = array('Content-Type: text/html; charset=UTF-8');
        $result = wp_mail($notification->recipient, $notification->subject, $notification->message, $headers);
        
        if ($result) {
            // Update as sent
            $wpdb->update(
                $table,
                array(
                    'status' => 'sent',
                    'sent_at' => current_time('mysql')
                ),
                array('id' => $notification_id)
            );
            
            if ($this->debug) {
                $this->debug->log_info('Email notification sent successfully', array(
                    'notification_id' => $notification_id,
                    'type' => $notification->notification_type,
                    'recipient' => $notification->recipient
                ));
            }
            
            return true;
        } else {
            // Increment retry count
            $retry_count = (int) $notification->retry_count + 1;
            $max_retries = 3;
            
            if ($this->debug) {
                $this->debug->log_error('Failed to send email notification', array(
                    'notification_id' => $notification_id,
                    'type' => $notification->notification_type,
                    'recipient' => $notification->recipient,
                    'retry_count' => $retry_count,
                    'max_retries' => $max_retries
                ));
            }
            
            if ($retry_count >= $max_retries) {
                // Mark as failed after max retries
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'failed',
                        'retry_count' => $retry_count,
                        'error_message' => 'Max retry attempts reached'
                    ),
                    array('id' => $notification_id)
                );
                
                if ($this->debug) {
                    $this->debug->log_error('Notification marked as failed after max retries', array(
                        'notification_id' => $notification_id,
                        'type' => $notification->notification_type,
                        'recipient' => $notification->recipient,
                        'retry_count' => $retry_count
                    ));
                }
            } else {
                // Schedule for retry
                $retry_time = date('Y-m-d H:i:s', strtotime('+1 hour')); // Retry in 1 hour
                
                $wpdb->update(
                    $table,
                    array(
                        'status' => 'pending',
                        'retry_count' => $retry_count,
                        'scheduled_at' => $retry_time
                    ),
                    array('id' => $notification_id)
                );
                
                if ($this->debug) {
                    $this->debug->log_info('Notification scheduled for retry', array(
                        'notification_id' => $notification_id,
                        'type' => $notification->notification_type,
                        'recipient' => $notification->recipient,
                        'retry_count' => $retry_count,
                        'retry_at' => $retry_time
                    ));
                }
            }
            
            return false;
        }
    }
    
    /**
     * Get waitlist joined email content
     * 
     * @param object $entry The waitlist entry
     * @param WC_Product $product The product
     * @param WC_Product_Variation $variation The variation (optional)
     * @return string The email content
     */
    private function get_waitlist_joined_email_content($entry, $product, $variation = null) {
        // Get product info
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        $product_link = get_permalink($product->get_id());
        $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'thumbnail');
        $product_image_url = $product_image ? $product_image[0] : wc_placeholder_img_src();
        
        // Prepare variables for template
        $vars = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'product_name' => $product_name,
            'product_link' => $product_link,
            'product_image_url' => $product_image_url,
            'waitlist_position' => $entry->position,
            'expiration_date' => date_i18n(get_option('date_format'), strtotime($entry->expires_at))
        );
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = STOCKCARTL_PLUGIN_DIR . 'templates/emails/waitlist-joined.php';
        
        // Allow template override in theme
        $theme_template = locate_template('stockcartl/emails/waitlist-joined.php');
        if ($theme_template) {
            $template_path = $theme_template;
        }
        
        // Extract variables to make them available in the template
        extract($vars);
        
        // Include the template
        include $template_path;
        
        // Get the content
        return ob_get_clean();
    }
    
    /**
     * Get product available email content
     * 
     * @param object $entry The waitlist entry
     * @param WC_Product $product The product
     * @param WC_Product_Variation $variation The variation (optional)
     * @return string The email content
     */
    private function get_product_available_email_content($entry, $product, $variation = null) {
        // Get product info
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        $product_link = get_permalink($product->get_id());
        $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'thumbnail');
        $product_image_url = $product_image ? $product_image[0] : wc_placeholder_img_src();
        
        // Get product price
        $product_price = $variation ? $variation->get_price_html() : $product->get_price_html();
        
        // Create add to cart URL (includes variation data if needed)
        $cart_url = $variation ? 
            add_query_arg(array('add-to-cart' => $product->get_id(), 'variation_id' => $variation->get_id()), wc_get_cart_url()) : 
            add_query_arg('add-to-cart', $product->get_id(), wc_get_cart_url());
        
        // Prepare variables for template
        $vars = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'product_name' => $product_name,
            'product_link' => $product_link,
            'product_image_url' => $product_image_url,
            'product_price' => $product_price,
            'cart_url' => $cart_url,
            'waitlist_type' => $entry->waitlist_type,
            'deposit_amount' => wc_price($entry->deposit_amount)
        );
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = STOCKCARTL_PLUGIN_DIR . 'templates/emails/product-available.php';
        
        // Allow template override in theme
        $theme_template = locate_template('stockcartl/emails/product-available.php');
        if ($theme_template) {
            $template_path = $theme_template;
        }
        
        // Extract variables to make them available in the template
        extract($vars);
        
        // Include the template
        include $template_path;
        
        // Get the content
        return ob_get_clean();
    }
    
    /**
     * Get deposit confirmation email content
     * 
     * @param object $entry The waitlist entry
     * @param WC_Product $product The product
     * @param WC_Product_Variation $variation The variation (optional)
     * @return string The email content
     */
    private function get_deposit_confirmation_email_content($entry, $product, $variation = null) {
        // Get product info
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        $product_link = get_permalink($product->get_id());
        $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'thumbnail');
        $product_image_url = $product_image ? $product_image[0] : wc_placeholder_img_src();
        
        // Get order
        $order = wc_get_order($entry->deposit_order_id);
        $order_url = $order ? $order->get_view_order_url() : '';
        
        // Prepare variables for template
        $vars = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'product_name' => $product_name,
            'product_link' => $product_link,
            'product_image_url' => $product_image_url,
            'waitlist_position' => $entry->position,
            'deposit_amount' => wc_price($entry->deposit_amount),
            'order_id' => $entry->deposit_order_id,
            'order_url' => $order_url,
            'expiration_date' => date_i18n(get_option('date_format'), strtotime($entry->expires_at))
        );
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = STOCKCARTL_PLUGIN_DIR . 'templates/emails/deposit-confirmation.php';
        
        // Allow template override in theme
        $theme_template = locate_template('stockcartl/emails/deposit-confirmation.php');
        if ($theme_template) {
            $template_path = $theme_template;
        }
        
        // Extract variables to make them available in the template
        extract($vars);
        
        // Include the template
        include $template_path;
        
        // Get the content
        return ob_get_clean();
    }
    
    /**
     * Get deposit refunded email content
     * 
     * @param object $entry The waitlist entry
     * @param WC_Product $product The product
     * @param WC_Product_Variation $variation The variation (optional)
     * @return string The email content
     */
    private function get_deposit_refunded_email_content($entry, $product, $variation = null) {
        // Get product info
        $product_name = $variation ? $variation->get_formatted_name() : $product->get_name();
        $product_link = get_permalink($product->get_id());
        $product_image = wp_get_attachment_image_src(get_post_thumbnail_id($product->get_id()), 'thumbnail');
        $product_image_url = $product_image ? $product_image[0] : wc_placeholder_img_src();
        
        // Get order
        $order = wc_get_order($entry->deposit_order_id);
        $order_url = $order ? $order->get_view_order_url() : '';
        
        // Prepare variables for template
        $vars = array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'product_name' => $product_name,
            'product_link' => $product_link,
            'product_image_url' => $product_image_url,
            'deposit_amount' => wc_price($entry->deposit_amount),
            'order_id' => $entry->deposit_order_id,
            'order_url' => $order_url
        );
        
        // Start output buffer
        ob_start();
        
        // Include template
        $template_path = STOCKCARTL_PLUGIN_DIR . 'templates/emails/deposit-refunded.php';
        
        // Allow template override in theme
        $theme_template = locate_template('stockcartl/emails/deposit-refunded.php');
        if ($theme_template) {
            $template_path = $theme_template;
        }
        
        // Extract variables to make them available in the template
        extract($vars);
        
        // Include the template
        include $template_path;
        
        // Get the content
        return ob_get_clean();
    }
}