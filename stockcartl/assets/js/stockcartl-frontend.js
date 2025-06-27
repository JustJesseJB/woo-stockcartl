/**
 * StockCartl Frontend JavaScript
 */
(function($) {
    'use strict';

    // Initialize when DOM is ready
    $(document).ready(function() {
        initWaitlistForms();
        initVariableProductHandling();
    });

    /**
     * Initialize all waitlist forms on the page
     */
    function initWaitlistForms() {
        $('.stockcartl-form').each(function() {
            var form = $(this);
            
            // Handle form submission
            form.on('submit', function(e) {
                e.preventDefault();
                processWaitlistForm(form, false);
            });
            
            // Handle deposit button click
            form.find('.stockcartl-deposit-button').on('click', function() {
                processWaitlistForm(form, true);
            });
        });
    }
    
    /**
     * Process waitlist form submission
     * 
     * @param {jQuery} form The form element
     * @param {boolean} withDeposit Whether to add deposit
     */
    function processWaitlistForm(form, withDeposit) {
        var messageDiv = form.find('.stockcartl-message');
        var productId = form.closest('.stockcartl-waitlist-form').data('product-id');
        var variationId = form.closest('.stockcartl-waitlist-form').data('variation-id') || 0;
        var email = form.find('input[name="stockcartl_email"]').val();
        var nonce = form.find('input[name="stockcartl_nonce"]').val();
        
        // Clear previous messages
        messageDiv.removeClass('error success').empty();
        
        // Validate email
        if (!isValidEmail(email)) {
            messageDiv.addClass('error').text(stockcartl_data.i18n.email_invalid);
            return;
        }
        
        // Disable form while processing
        form.find('button').prop('disabled', true);
        
        // Add loading indicator
        var loadingIndicator = $('<span class="stockcartl-loading"></span>');
        form.find(withDeposit ? '.stockcartl-deposit-button' : '.stockcartl-join-button').append(loadingIndicator);
        
        // Collect UTM parameters if present
        var utmSource = getUrlParameter('utm_source') || '';
        
        // Send AJAX request
        $.ajax({
            url: stockcartl_data.ajax_url,
            type: 'POST',
            data: {
                action: 'stockcartl_join_waitlist',
                email: email,
                product_id: productId,
                variation_id: variationId,
                nonce: stockcartl_data.nonce, // Use global nonce
                deposit: withDeposit ? 1 : 0,
                utm_source: utmSource
            },
            success: function(response) {
                // Remove loading indicator
                loadingIndicator.remove();
                
                if (response.success) {
                    messageDiv.addClass('success').text(response.data.message);
                    
                    // Redirect if needed (for deposit checkout)
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        // Hide form after success
                        setTimeout(function() {
                            form.slideUp();
                            form.closest('.stockcartl-waitlist-form').prepend(
                                '<div class="stockcartl-already-joined"><p>' + 
                                stockcartl_data.i18n.join_success + 
                                '</p></div>'
                            );
                        }, 2000);
                    }
                } else {
                    messageDiv.addClass('error').text(response.data.message || stockcartl_data.i18n.join_error);
                    form.find('button').prop('disabled', false);
                }
            },
            error: function() {
                // Remove loading indicator
                loadingIndicator.remove();
                messageDiv.addClass('error').text(stockcartl_data.i18n.join_error);
                form.find('button').prop('disabled', false);
            }
        });
    }
    
    /**
     * Initialize variable product handling
     */
    function initVariableProductHandling() {
        // This is initialized via wc_enqueue_js in the PHP class
        // to ensure it runs after WooCommerce's variation scripts
    }
    
    /**
     * Validate email address
     * 
     * @param {string} email The email address to validate
     * @return {boolean} Whether the email is valid
     */
    function isValidEmail(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(String(email).toLowerCase());
    }
    
    /**
     * Get URL parameter value
     * 
     * @param {string} name The parameter name
     * @return {string|null} The parameter value or null
     */
    function getUrlParameter(name) {
        name = name.replace(/[\[]/, '\\[').replace(/[\]]/, '\\]');
        var regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
        var results = regex.exec(location.search);
        return results === null ? '' : decodeURIComponent(results[1].replace(/\+/g, ' '));
    }
    
})(jQuery);