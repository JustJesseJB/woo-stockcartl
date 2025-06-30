<?php
/**
 * Template for the waitlist form
 *
 * Override this template by copying it to yourtheme/stockcartl/waitlist-form.php
 *
 * @package StockCartl
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Available variables:
 * 
 * $product_id - The product ID
 * $variation_id - The variation ID (if applicable)
 * $waitlist_count - Number of people on the waitlist
 * $is_on_waitlist - Whether the current user is already on the waitlist
 * $button_text - The join waitlist button text
 * $social_proof_text - The social proof text
 * $min_social_proof - Minimum number of people for social proof to show
 * $deposit_enabled - Whether deposit priority is enabled
 * $deposit_percentage - The deposit percentage
 * $deposit_amount - The calculated deposit amount
 * $deposit_button_text - The deposit button text
 * $email - The current user's email (if logged in)
 */
?>

<div class="stockcartl-waitlist-form" data-product-id="<?php echo esc_attr($product_id); ?>" <?php if ($variation_id) : ?>data-variation-id="<?php echo esc_attr($variation_id); ?>"<?php endif; ?>>
    
    <?php if ($is_on_waitlist) : ?>
        
        <div class="stockcartl-already-joined">
            <p><?php esc_html_e('You are already on the waitlist for this product.', 'stockcartl'); ?></p>
        </div>
        
    <?php else : ?>
        
        <div class="stockcartl-social-proof">
            <?php if ($waitlist_count >= $min_social_proof) : ?>
                <p><?php echo str_replace('{count}', $waitlist_count, esc_html($social_proof_text)); ?></p>
            <?php endif; ?>
        </div>
        
        <form class="stockcartl-form">
            <div class="stockcartl-email-field">
                <label for="stockcartl-email-<?php echo esc_attr($product_id); ?>"><?php esc_html_e('Email address', 'stockcartl'); ?></label>
                <input type="email" id="stockcartl-email-<?php echo esc_attr($product_id); ?>" name="stockcartl_email" value="<?php echo esc_attr($email); ?>" required placeholder="<?php esc_attr_e('Enter your email', 'stockcartl'); ?>">
            </div>
            
            <input type="hidden" name="stockcartl_product_id" value="<?php echo esc_attr($product_id); ?>">
            <?php if ($variation_id) : ?>
                <input type="hidden" name="stockcartl_variation_id" value="<?php echo esc_attr($variation_id); ?>">
            <?php endif; ?>
            <?php wp_nonce_field('stockcartl_join_waitlist', 'stockcartl_nonce'); ?>
            
            <div class="stockcartl-buttons">
                <button type="submit" class="stockcartl-join-button button alt"><?php echo esc_html($button_text); ?></button>
                
                <?php if ($deposit_enabled && $deposit_amount > 0) : ?>
                    <button type="button" class="stockcartl-deposit-button button"><?php echo esc_html($deposit_button_text); ?></button>
                    <div class="stockcartl-deposit-info">
                        <p><?php printf(esc_html__('Pay a %1$s%% deposit (%2$s) to secure priority position when this item is back in stock.', 'stockcartl'), $deposit_percentage, wc_price($deposit_amount)); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="stockcartl-message"></div>
        </form>
        
    <?php endif; ?>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('.stockcartl-form').on('submit', function(e) {
        e.preventDefault();
        
        var form = $(this);
        var messageDiv = form.find('.stockcartl-message');
        var productId = form.closest('.stockcartl-waitlist-form').data('product-id');
        var variationId = form.closest('.stockcartl-waitlist-form').data('variation-id') || 0;
        var email = form.find('input[name="stockcartl_email"]').val();
        var nonce = form.find('input[name="stockcartl_nonce"]').val();
        
        // Clear previous messages
        messageDiv.removeClass('error success').empty();
        
        // Validate email
        if (!email || !email.includes('@')) {
            messageDiv.addClass('error').text(stockcartl_data.i18n.email_invalid);
            return;
        }
        
        // Disable form while processing
        form.find('button').prop('disabled', true);
        
        // Send AJAX request
        $.ajax({
            url: stockcartl_data.ajax_url,
            type: 'POST',
            data: {
                action: 'stockcartl_join_waitlist',
                email: email,
                product_id: productId,
                variation_id: variationId,
                nonce: nonce,
                deposit: 0 // No deposit by default
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.addClass('success').text(response.data.message);
                    
                    // Redirect if needed
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
                    messageDiv.addClass('error').text(response.data.message);
                    form.find('button').prop('disabled', false);
                }
            },
            error: function() {
                messageDiv.addClass('error').text(stockcartl_data.i18n.join_error);
                form.find('button').prop('disabled', false);
            }
        });
    });
    
    // Handle deposit button click
    $('.stockcartl-deposit-button').on('click', function() {
        var form = $(this).closest('form');
        var messageDiv = form.find('.stockcartl-message');
        var productId = form.closest('.stockcartl-waitlist-form').data('product-id');
        var variationId = form.closest('.stockcartl-waitlist-form').data('variation-id') || 0;
        var email = form.find('input[name="stockcartl_email"]').val();
        var nonce = form.find('input[name="stockcartl_nonce"]').val();
        
        // Clear previous messages
        messageDiv.removeClass('error success').empty();
        
        // Validate email
        if (!email || !email.includes('@')) {
            messageDiv.addClass('error').text(stockcartl_data.i18n.email_invalid);
            return;
        }
        
        // Disable form while processing
        form.find('button').prop('disabled', true);
        
        // Send AJAX request with deposit flag
        $.ajax({
            url: stockcartl_data.ajax_url,
            type: 'POST',
            data: {
                action: 'stockcartl_join_waitlist',
                email: email,
                product_id: productId,
                variation_id: variationId,
                nonce: nonce,
                deposit: 1 // Request deposit
            },
            success: function(response) {
                if (response.success) {
                    messageDiv.addClass('success').text(response.data.message);
                    
                    // Redirect to checkout for deposit
                    if (response.data.redirect) {
                        window.location.href = response.data.redirect;
                    }
                } else {
                    messageDiv.addClass('error').text(response.data.message);
                    form.find('button').prop('disabled', false);
                }
            },
            error: function() {
                messageDiv.addClass('error').text(stockcartl_data.i18n.join_error);
                form.find('button').prop('disabled', false);
            }
        });
    });
});
</script>