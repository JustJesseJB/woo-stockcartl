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
        
        // Debug the settings
        add_action('wp_footer', function() {
            if (!is_product()) return;
            
            echo '<div style="border: 2px dashed orange; padding: 10px; margin: 10px 0; position: fixed; bottom: 10px; right: 10px; background: white; z-index: 9999;">
                    <h4>StockCartl Settings Debug</h4>
                    <pre>Enabled: ' . ($this->settings->get('enabled') ? 'Yes' : 'No') . '</pre>
                  </div>';
        });
        
        // Hook into WooCommerce product page
        add_action('woocommerce_single_product_summary', array($this, 'maybe_display_waitlist_form'), 35);
        
        // Add scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_stockcartl_join_waitlist', array($this, 'process_join_waitlist'));
        add_action('wp_ajax_nopriv_stockcartl_join_waitlist', array($this, 'process_join_waitlist'));
        add_action('wp_ajax_stockcartl_load_variation_form', array($this, 'ajax_load_variation_form'));
        add_action('wp_ajax_nopriv_stockcartl_load_variation_form', array($this, 'ajax_load_variation_form'));
        
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
            STOCKCARTL_VERSION . '.' . time() // Add timestamp to force refresh
        );

        // Enqueue main script
        wp_enqueue_script(
            'stockcartl-scripts',
            STOCKCARTL_PLUGIN_URL . 'assets/js/stockcartl-frontend.js',
            array('jquery'),
            STOCKCARTL_VERSION . '.' . time(), // Add timestamp to force refresh
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
        
        // Add a test to verify the nonce is available
        add_action('wp_footer', function() {
            echo '<script>
                console.log("StockCartl nonce check:", typeof stockcartl_data !== "undefined" ? "Data object exists" : "Missing data object");
                console.log("StockCartl nonce value:", typeof stockcartl_data !== "undefined" ? stockcartl_data.nonce : "N/A");
            </script>';
        });
        
        // Add basic script check
        add_action('wp_footer', function() {
            echo '<script>
                console.log("StockCartl Basic JS Check: If you see this, JavaScript is working");
                
                // Test jQuery
                if (typeof jQuery !== "undefined") {
                    console.log("jQuery is available");
                } else {
                    console.log("jQuery is NOT available");
                }
                
                // Test if variations form exists
                jQuery(document).ready(function($) {
                    if ($(".variations_form").length) {
                        console.log("Variations form found");
                    } else {
                        console.log("No variations form found on page");
                    }
                });
            </script>';
        });
    }

    /**
     * Check if we should display the waitlist form and display it if needed
     */
    public function maybe_display_waitlist_form() {
        global $product;

        // Debug output (can be removed later)
        echo '<!-- StockCartl Debug: Hook fired -->';
        echo '<div style="display:none;">
                Product ID: ' . ($product ? $product->get_id() : 'No product') . '<br>
                Type: ' . ($product ? $product->get_type() : 'Unknown') . '<br>
                In Stock: ' . ($product && $product->is_in_stock() ? 'Yes' : 'No') . '<br>
                Plugin Enabled: ' . ($this->settings->get('enabled') ? 'Yes' : 'No') . '
              </div>';

        // Check if plugin is enabled
        if (!$this->settings->get('enabled')) {
            echo '<!-- StockCartl Debug: Plugin not enabled -->';
            return;
        }

        // Handle simple products
        if ($product->is_type('simple')) {
            // Only show for out of stock products
            if ($product->is_in_stock()) {
                echo '<!-- StockCartl Debug: Simple product is in stock -->';
                return;
            }
            
            echo '<!-- StockCartl Debug: Displaying form for simple product -->';
            $this->display_waitlist_form($product->get_id());
            return;
        }

        // Handle variable products - CHANGED LOGIC HERE
        if ($product->is_type('variable')) {
            // For variable products, we always show the waitlist placeholder
            // and let JavaScript handle showing/hiding based on the selected variation
            echo '<!-- StockCartl Debug: Handling variable product -->';
            $this->display_variable_product_waitlist($product);
            return;
        }
    }

    /**
     * Display waitlist form for simple products
     * 
     * @param int $product_id The product ID
     * @param int $variation_id The variation ID (optional)
     */
    public function display_waitlist_form($product_id, $variation_id = null) {
        // Get product
        $product = wc_get_product($product_id);
        if (!$product) {
            echo '<!-- StockCartl Debug: Product not found -->';
            return;
        }

        echo '<!-- StockCartl Debug: Displaying waitlist form for product ID ' . $product_id . ' -->';
        if ($variation_id) {
            echo '<!-- StockCartl Debug: Variation ID ' . $variation_id . ' -->';
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
     * Get waitlist form HTML for a variation
     * 
     * @param int $product_id The product ID
     * @param int $variation_id The variation ID
     * @return string The form HTML
     */
    private function get_variation_form_html($product_id, $variation_id) {
        // Start output buffer
        ob_start();
        
        // Display form
        $this->display_waitlist_form($product_id, $variation_id);
        
        // Get form HTML
        return ob_get_clean();
    }

    /**
     * Display waitlist handling for variable products
     * 
     * @param WC_Product_Variable $product The variable product
     */
    public function display_variable_product_waitlist($product) {
        // Add visible placeholder with debug information
        echo '<div class="stockcartl-waitlist-form-wrapper" style="display:none;">
                <div style="border: 1px solid #e5e5e5; padding: 10px; background: #f8f8f8;">
                    <h3>StockCartl Waitlist</h3>
                    <p>Select an out-of-stock variation to see the waitlist form.</p>
                </div>
              </div>';
        
        // Pre-generate form templates for variations
        $variations = $product->get_available_variations();
        $variation_forms = array();
        
        foreach ($variations as $variation_data) {
            $variation_id = $variation_data['variation_id'];
            if (!$variation_data['is_in_stock']) {
                $variation_forms[$variation_id] = $this->get_variation_form_html($product->get_id(), $variation_id);
            }
        }
        
        // Create a nonce directly
        $nonce = wp_create_nonce('stockcartl_join_waitlist');
        
        // Add direct inline script at the end of the page
        add_action('wp_footer', function() use ($product, $nonce, $variation_forms) {
            ?>
            <script type="text/javascript">
            console.log('StockCartl: Direct inline script executed for product ID <?php echo $product->get_id(); ?>');
            
            jQuery(document).ready(function($) {
                console.log('StockCartl: jQuery ready in direct script');
                
                // Hardcode the ajax URL and nonce since stockcartl_data might not be available
                var ajaxUrl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
                var nonce = '<?php echo esc_js($nonce); ?>';
                console.log('StockCartl: Using hardcoded nonce:', nonce);
                
                // Pre-loaded variation forms
                var variationForms = <?php echo json_encode($variation_forms); ?>;
                console.log('StockCartl: Pre-loaded forms for out-of-stock variations:', Object.keys(variationForms).length);
                
                // Locate the form wrapper
                var stockcartlForm = $('.stockcartl-waitlist-form-wrapper');
                console.log('StockCartl: Form wrapper elements found:', stockcartlForm.length);
                
                // Initially hide the form
                stockcartlForm.hide();
                
                // Find the variations form
                var variationsForm = $('.variations_form');
                console.log('StockCartl: Variations form elements found:', variationsForm.length);
                
                // Listen for variation changes
                variationsForm.on('show_variation', function(event, variation) {
                    console.log('StockCartl: Variation shown event fired');
                    console.log('StockCartl: Variation data:', variation);
                    
                    if (variation && typeof variation.is_in_stock !== 'undefined') {
                        console.log('StockCartl: Variation in stock status:', variation.is_in_stock);
                        
                        if (!variation.is_in_stock) {
                            console.log('StockCartl: Showing waitlist form for out-of-stock variation');
                            
                            // Use pre-generated form if available
                            if (variationForms[variation.variation_id]) {
                                stockcartlForm.html(variationForms[variation.variation_id]).show();
                                
                                // Re-attach event handlers
                                stockcartlForm.find('form').on('submit', function(e) {
                                    e.preventDefault();
                                    
                                    var form = $(this);
                                    var messageDiv = form.find('.stockcartl-message');
                                    
                                    // Clear previous messages
                                    messageDiv.removeClass('error success').empty();
                                    
                                    // Get form data
                                    var formData = {
                                        action: 'stockcartl_join_waitlist',
                                        email: form.find('input[name="stockcartl_email"]').val(),
                                        product_id: form.find('input[name="stockcartl_product_id"]').val(),
                                        variation_id: form.find('input[name="stockcartl_variation_id"]').val(),
                                        nonce: form.find('input[name="stockcartl_nonce"]').val(),
                                        deposit: 0
                                    };
                                    
                                    // Disable form while processing
                                    form.find('button').prop('disabled', true);
                                    
                                    // Send AJAX request
                                    $.ajax({
                                        url: ajaxUrl,
                                        type: 'POST',
                                        data: formData,
                                        success: function(response) {
                                            if (response.success) {
                                                messageDiv.addClass('success').text(response.data.message);
                                                
                                                // Hide form after success
                                                setTimeout(function() {
                                                    form.slideUp();
                                                    form.closest('.stockcartl-waitlist-form').prepend(
                                                        '<div class="stockcartl-already-joined"><p>' + 
                                                        'You have been added to the waitlist!' + 
                                                        '</p></div>'
                                                    );
                                                }, 2000);
                                            } else {
                                                messageDiv.addClass('error').text(response.data.message || 'Error adding to waitlist');
                                                form.find('button').prop('disabled', false);
                                            }
                                        },
                                        error: function() {
                                            messageDiv.addClass('error').text('Error adding to waitlist');
                                            form.find('button').prop('disabled', false);
                                        }
                                    });
                                });
                                
                                // Handle deposit button if it exists
                                stockcartlForm.find('.stockcartl-deposit-button').on('click', function() {
                                    var form = $(this).closest('form');
                                    var messageDiv = form.find('.stockcartl-message');
                                    
                                    // Clear previous messages
                                    messageDiv.removeClass('error success').empty();
                                    
                                    // Get form data
                                    var formData = {
                                        action: 'stockcartl_join_waitlist',
                                        email: form.find('input[name="stockcartl_email"]').val(),
                                        product_id: form.find('input[name="stockcartl_product_id"]').val(),
                                        variation_id: form.find('input[name="stockcartl_variation_id"]').val(),
                                        nonce: form.find('input[name="stockcartl_nonce"]').val(),
                                        deposit: 1
                                    };
                                    
                                    // Disable form while processing
                                    form.find('button').prop('disabled', true);
                                    
                                    // Send AJAX request
                                    $.ajax({
                                        url: ajaxUrl,
                                        type: 'POST',
                                        data: formData,
                                        success: function(response) {
                                            if (response.success) {
                                                messageDiv.addClass('success').text(response.data.message);
                                                
                                                // Redirect if needed (for deposit checkout)
                                                if (response.data.redirect) {
                                                    window.location.href = response.data.redirect;
                                                }
                                            } else {
                                                messageDiv.addClass('error').text(response.data.message || 'Error adding to waitlist');
                                                form.find('button').prop('disabled', false);
                                            }
                                        },
                                        error: function() {
                                            messageDiv.addClass('error').text('Error adding to waitlist');
                                            form.find('button').prop('disabled', false);
                                        }
                                    });
                                });
                            } else {
                                // Fallback to simple form if pre-generated form is not available
                                stockcartlForm.html('<div style="padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">' +
                                    '<h3 style="margin-top: 0; color: #1a1a1a;">Join Waitlist</h3>' +
                                    '<form class="stockcartl-direct-form">' +
                                    '<div style="margin-bottom: 15px;">' +
                                    '<label for="stockcartl-email" style="display: block; margin-bottom: 5px; font-weight: 600;">Email address</label>' +
                                    '<input type="email" id="stockcartl-email" name="email" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' +
                                    '</div>' +
                                    '<input type="hidden" name="product_id" value="<?php echo esc_js($product->get_id()); ?>">' +
                                    '<input type="hidden" name="variation_id" value="' + variation.variation_id + '">' +
                                    '<input type="hidden" name="nonce" value="' + nonce + '">' +
                                    '<button type="submit" style="background-color: #1a1a1a; color: #ffffff; border: none; padding: 12px 20px; font-weight: 600; border-radius: 4px; cursor: pointer;">Join Waitlist</button>' +
                                    '<div class="stockcartl-message" style="margin-top: 10px;"></div>' +
                                    '</form>' +
                                    '</div>').show();
                                    
                                // Attach event handler to the simple form
                                $('.stockcartl-direct-form').on('submit', function(e) {
                                    e.preventDefault();
                                    
                                    var form = $(this);
                                    var messageDiv = form.find('.stockcartl-message');
                                    
                                    // Get form data
                                    var email = form.find('input[name="email"]').val();
                                    var productId = form.find('input[name="product_id"]').val();
                                    var variationId = form.find('input[name="variation_id"]').val();
                                    
                                    // Validate email
                                    if (!email || !email.includes('@')) {
                                        messageDiv.html('<div style="color: red;">Please enter a valid email address.</div>');
                                        return;
                                    }
                                    
                                    // Disable form while processing
                                    form.find('button').prop('disabled', true).text('Processing...');
                                    
                                    // Send AJAX request
                                    $.ajax({
                                        url: ajaxUrl,
                                        type: 'POST',
                                        data: {
                                            action: 'stockcartl_join_waitlist',
                                            email: email,
                                            product_id: productId,
                                            variation_id: variationId,
                                            nonce: nonce,
                                            deposit: 0
                                        },
                                        success: function(response) {
                                            console.log('StockCartl: Form submission response:', response);
                                            if (response.success) {
                                                messageDiv.html('<div style="color: green;">' + response.data.message + '</div>');
                                                form.find('input[type="email"]').hide();
                                                form.find('button').hide();
                                            } else {
                                                messageDiv.html('<div style="color: red;">' + (response.data ? response.data.message : 'Error adding to waitlist') + '</div>');
                                                form.find('button').prop('disabled', false).text('Join Waitlist');
                                            }
                                        },
                                        error: function(xhr, status, error) {
                                            console.error('StockCartl: Form submission error:', xhr.responseText);
                                            messageDiv.html('<div style="color: red;">Error: ' + status + '</div>');
                                            form.find('button').prop('disabled', false).text('Join Waitlist');
                                        }
                                    });
                                });
                            }
                        } else {
                            console.log('StockCartl: Hiding waitlist form for in-stock variation');
                            stockcartlForm.hide();
                        }
                    } else {
                        console.log('StockCartl: Variation object missing or invalid');
                    }
                });
                
                // Hide form when no variation is selected
                variationsForm.on('hide_variation', function() {
                    console.log('StockCartl: Hide variation event fired');
                    stockcartlForm.hide();
                });
                
                // Debug all variation events
                variationsForm.on('found_variation woocommerce_variation_has_changed check_variations update_variation_values', function(event) {
                    console.log('StockCartl: Variation event fired:', event.type);
                });
                
                // Extra debug for when page loads
                console.log('StockCartl: Initial variation state:', {
                    'variations_form_exists': $('.variations_form').length > 0,
                    'waitlist_wrapper_exists': $('.stockcartl-waitlist-form-wrapper').length > 0
                });
            });
            </script>
            <?php
        });
    }
    
    /**
     * AJAX handler to load waitlist form for a variation
     */
    public function ajax_load_variation_form() {
        // Debug the request
        error_log('StockCartl: AJAX request received for variation form. POST data: ' . print_r($_POST, true));
        
        // Check for missing parameters first
        if (!isset($_POST['product_id']) || !isset($_POST['variation_id'])) {
            wp_send_json_error(array(
                'message' => __('Missing product or variation ID.', 'stockcartl'),
                'debug' => 'Missing required parameters'
            ));
            return;
        }
        
        // Verify nonce - with better error handling
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_join_waitlist')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'stockcartl'),
                'debug' => 'Nonce verification failed',
                'nonce_received' => isset($_POST['nonce']) ? 'yes' : 'no'
            ));
            return;
        }
        
        $product_id = absint($_POST['product_id']);
        $variation_id = absint($_POST['variation_id']);
        
        // Verify product exists
        $product = wc_get_product($product_id);
        if (!$product) {
            wp_send_json_error(array(
                'message' => __('Product not found.', 'stockcartl'),
                'debug' => 'Product ID: ' . $product_id . ' not found'
            ));
            return;
        }
        
        // Verify variation exists
        $variation = wc_get_product($variation_id);
        if (!$variation || $variation->get_parent_id() != $product_id) {
            wp_send_json_error(array(
                'message' => __('Variation not found.', 'stockcartl'),
                'debug' => 'Variation ID: ' . $variation_id . ' not found or not a child of product ' . $product_id
            ));
            return;
        }
        
        // Verify variation is out of stock
        if ($variation->is_in_stock()) {
            wp_send_json_error(array(
                'message' => __('This variation is in stock.', 'stockcartl'),
                'debug' => 'Variation is in stock'
            ));
            return;
        }
        
        // Start output buffer
        ob_start();
        
        // Display form
        $this->display_waitlist_form($product_id, $variation_id);
        
        // Get form HTML
        $form_html = ob_get_clean();
        
        // Return form HTML
        wp_send_json_success(array(
            'html' => $form_html,
            'debug' => 'Form generated successfully'
        ));
    }

    /**
     * Process waitlist join form submission
     */
    public function process_join_waitlist() {
        // Debug log all POST data
        error_log('StockCartl: JOIN WAITLIST POST DATA: ' . print_r($_POST, true));

        // Verify required fields
        if (!isset($_POST['email']) || !isset($_POST['product_id'])) {
            wp_send_json_error(array('message' => __('Missing required fields.', 'stockcartl')));
            return;
        }
        
        // Verify nonce with better error handling
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'stockcartl_join_waitlist')) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'stockcartl'),
                'nonce_received' => isset($_POST['nonce']) ? 'yes: ' . $_POST['nonce'] : 'no'
            ));
            return;
        }
        
        // Sanitize inputs
        $email = sanitize_email($_POST['email']);
        $product_id = absint($_POST['product_id']);
        $variation_id = isset($_POST['variation_id']) ? absint($_POST['variation_id']) : 0;
        
        // Debug log
        error_log("StockCartl: Processing waitlist join for product $product_id, variation $variation_id, email $email");
        
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
        if ($product->is_in_stock() && !$variation_id) {
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