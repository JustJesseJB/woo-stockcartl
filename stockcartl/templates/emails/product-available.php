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
        .product-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .product-price {
            font-size: 18px;
            font-weight: bold;
            color: #d4af37;
            margin: 10px 0;
        }
        .button {
            display: inline-block;
            background-color: #d4af37;
            color: #ffffff !important;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 4px;
            margin-top: 10px;
            font-weight: bold;
        }
        .button:hover {
            background-color: #c4a030;
        }
        .priority-notice {
            background-color: #d4af37;
            color: #ffffff;
            padding: 10px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <h1><?php echo esc_html($site_name); ?></h1>
        </div>
        
        <div class="email-body">
            <h2><?php esc_html_e('Good news! Your waitlisted item is back in stock', 'stockcartl'); ?></h2>
            
            <?php if ($waitlist_type === 'deposit') : ?>
            <div class="priority-notice">
                <?php printf(esc_html__('You\'re receiving priority access because you paid a %s deposit.', 'stockcartl'), esc_html($deposit_amount)); ?>
            </div>
            <?php endif; ?>
            
            <p><?php printf(esc_html__('%s is now back in stock and available for purchase!', 'stockcartl'), '<strong>' . esc_html($product_name) . '</strong>'); ?></p>
            
            <div style="text-align: center;">
                <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product_name); ?>" class="product-image">
            </div>
            
            <div class="product-info">
                <h3><?php echo esc_html($product_name); ?></h3>
                <div class="product-price"><?php echo wp_kses_post($product_price); ?></div>
                <p><?php esc_html_e('This item is in high demand and may sell out quickly.', 'stockcartl'); ?></p>
            </div>
            
            <p><?php esc_html_e('Don\'t miss out! Click the button below to purchase this item now.', 'stockcartl'); ?></p>
            
            <div style="text-align: center;">
                <a href="<?php echo esc_url($cart_url); ?>" class="button"><?php esc_html_e('Add to Cart', 'stockcartl'); ?></a>
                <br>
                <a href="<?php echo esc_url($product_link); ?>" style="display: inline-block; margin-top: 10px; color: #666;"><?php esc_html_e('View Product Details', 'stockcartl'); ?></a>
            </div>
            
            <?php if ($waitlist_type === 'deposit') : ?>
            <p style="margin-top: 20px;">
                <?php esc_html_e('Your deposit will be automatically applied to your purchase.', 'stockcartl'); ?>
            </p>
            <?php endif; ?>
        </div>
        
        <div class="email-footer">
            <p><?php printf(esc_html__('This email was sent from %1$s. If you no longer wish to receive these emails, please visit %2$s to manage your preferences.', 'stockcartl'), esc_html($site_name), esc_url($site_url)); ?></p>
        </div>
    </div>
</body>
</html>