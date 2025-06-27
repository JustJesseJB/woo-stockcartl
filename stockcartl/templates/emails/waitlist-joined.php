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
        .waitlist-info {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
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
            <h2><?php esc_html_e('You\'ve joined the waitlist!', 'stockcartl'); ?></h2>
            
            <p><?php printf(esc_html__('Thank you for joining the waitlist for %s.', 'stockcartl'), '<strong>' . esc_html($product_name) . '</strong>'); ?></p>
            
            <div style="text-align: center;">
                <img src="<?php echo esc_url($product_image_url); ?>" alt="<?php echo esc_attr($product_name); ?>" class="product-image">
            </div>
            
            <div class="waitlist-info">
                <p><?php printf(esc_html__('Your current position: %s', 'stockcartl'), '<strong>' . esc_html($waitlist_position) . '</strong>'); ?></p>
                <p><?php printf(esc_html__('Waitlist expires: %s', 'stockcartl'), '<strong>' . esc_html($expiration_date) . '</strong>'); ?></p>
            </div>
            
            <p><?php esc_html_e('We\'ll notify you as soon as this item is back in stock. Want to secure a priority position? Consider paying a deposit to move to the front of the line!', 'stockcartl'); ?></p>
            
            <div style="text-align: center;">
                <a href="<?php echo esc_url($product_link); ?>" class="button"><?php esc_html_e('View Product', 'stockcartl'); ?></a>
            </div>
        </div>
        
        <div class="email-footer">
            <p><?php printf(esc_html__('This email was sent from %1$s. If you no longer wish to receive these emails, please visit %2$s to manage your preferences.', 'stockcartl'), esc_html($site_name), esc_url($site_url)); ?></p>
        </div>
    </div>
</body>
</html>