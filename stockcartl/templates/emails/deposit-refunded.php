<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php echo esc_html($site_name); ?></title>
    <style type="text/css">
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #333333;
            margin: 0;
            padding: 0;
        }
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .email-header {
            text-align: center;
            padding-bottom: 20px;
            border-bottom: 1px solid #eeeeee;
        }
        .email-header img {
            max-width: 200px;
            height: auto;
        }
        .email-body {
            padding: 20px 0;
            line-height: 1.5;
        }
        .email-footer {
            padding-top: 20px;
            border-top: 1px solid #eeeeee;
            font-size: 12px;
            color: #999999;
        }
        .product-image {
            max-width: 200px;
            height: auto;
            margin-bottom: 20px;
        }
        .refund-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .refund-amount {
            font-size: 18px;
            font-weight: bold;
            color: #d4af37;
            margin: 10px 0;
        }
        .refund-notice {
            background-color: #fffbed;
            border-left: 4px solid #d4af37;
            padding: 10px 15px;
            margin-bottom: 20px;
        }
        .button {
            display: inline-block;
            background-color: #d4af37;
            color: #ffffff !important;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 20px;
            font-weight: bold;
        }
        .button:hover {
            background-color: #c4a030;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1><?php echo esc_html($site_name); ?></h1>
        </div>
        
        <div class="email-body">
            <h2><?php esc_html_e('Your Waitlist Deposit Has Been Refunded', 'stockcartl'); ?></h2>
            
            <p><?php printf(esc_html__('Your deposit for %s has been automatically refunded.', 'stockcartl'), '<strong>' . esc_html($product_name) . '</strong>'); ?></p>
            
            <div class="refund-notice">
                <p><?php esc_html_e('This refund was processed because your waitlist entry has expired. The product was not restocked within the waitlist period.', 'stockcartl'); ?></p>
            </div>
            
            <div style="text-align: center;">
                <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product_name); ?>" class="product-image">
            </div>
            
            <div class="refund-info">
                <h3><?php esc_html_e('Refund Information', 'stockcartl'); ?></h3>
                <p><?php esc_html_e('Amount Refunded:', 'stockcartl'); ?> <span class="refund-amount"><?php echo esc_html($deposit_amount); ?></span></p>
                <p><?php esc_html_e('Original Order Number:', 'stockcartl'); ?> <strong><?php echo esc_html($order_id); ?></strong></p>
                <p><?php esc_html_e('Refund Method:', 'stockcartl'); ?> <strong><?php esc_html_e('Original Payment Method', 'stockcartl'); ?></strong></p>
            </div>
            
            <p><?php esc_html_e('Your refund has been processed and should appear on your original payment method within 5-10 business days, depending on your bank or card issuer.', 'stockcartl'); ?></p>
            
            <p><?php esc_html_e('We apologize that we couldn\'t restock this item during your waitlist period. You\'re welcome to join the waitlist again if you\'re still interested in this product.', 'stockcartl'); ?></p>
            
            <div style="text-align: center;">
                <a href="<?php echo esc_url($product_link); ?>" class="button"><?php esc_html_e('View Product', 'stockcartl'); ?></a>
                <?php if ($order_url) : ?>
                <br>
                <a href="<?php echo esc_url($order_url); ?>" style="display: inline-block; margin-top: 10px; color: #666;"><?php esc_html_e('View Order Details', 'stockcartl'); ?></a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="email-footer">
            <p><?php printf(esc_html__('This email was sent from %1$s. If you no longer wish to receive these emails, please visit %2$s to manage your preferences.', 'stockcartl'), esc_html($site_name), esc_url($site_url)); ?></p>
        </div>
    </div>
</body>
</html>