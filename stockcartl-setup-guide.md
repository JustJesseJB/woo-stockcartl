# StockCartl - Complete Setup Guide

This guide will walk you through the entire process of setting up the StockCartl plugin on your WordPress site with WooCommerce.

## Table of Contents

1. [Installation](#installation)
2. [Initial Configuration](#initial-configuration)
3. [Setting Up Email Templates](#setting-up-email-templates)
4. [Product Configuration](#product-configuration)
5. [Testing the Waitlist](#testing-the-waitlist)
6. [Managing Waitlists](#managing-waitlists)
7. [Troubleshooting](#troubleshooting)

## Installation

### Prerequisite Check

Before installing, make sure your site meets the following requirements:
- WordPress 5.0 or higher
- WooCommerce 4.0 or higher
- PHP 7.4 or higher

### Installation Steps

1. **Download the Plugin Files**
   - Download all the plugin files and folders we've created
   - Create a ZIP file containing the `stockcartl` folder with all its contents

2. **Install via WordPress Admin**
   - Go to your WordPress admin dashboard
   - Navigate to Plugins > Add New
   - Click the "Upload Plugin" button at the top
   - Choose the ZIP file and click "Install Now"
   - After installation completes, click "Activate Plugin"

3. **Manual Installation (Alternative)**
   - Extract the ZIP file to get the `stockcartl` folder
   - Using FTP or your hosting file manager, upload the entire `stockcartl` folder to your `/wp-content/plugins/` directory
   - Go to the Plugins page in your WordPress admin
   - Find "StockCartl" and click "Activate"

4. **Verify Installation**
   - After activation, you should see a new "StockCartl" menu item in your WordPress admin menu
   - You should also see a "StockCartl" submenu under WooCommerce

## Initial Configuration

1. **Access Settings**
   - Go to WooCommerce > StockCartl Settings

2. **General Settings**
   - **Enable StockCartl**: Make sure this is checked to activate the plugin
   - **Join Button Text**: Customize the text on the waitlist button (default: "Join Waitlist")
   - **Social Proof Text**: Customize the text that shows how many people are waiting (default: "{count} people waiting")
   - **Minimum Social Proof**: Set the minimum number of people required before showing the social proof message (default: 3)

3. **Deposit Settings**
   - **Enable Deposits**: Check this to allow customers to pay deposits for priority positions
   - **Deposit Percentage**: Set the percentage of the product price to charge as a deposit (default: 25%)
   - **Deposit Button Text**: Customize the text on the deposit button (default: "Secure Your Spot - Pay Deposit")

4. **Expiration Settings**
   - **Waitlist Expiration**: Choose how long waitlist entries remain active before expiring (default: 60 days)

5. **Email Settings**
   - **Waitlist Joined Subject**: Customize the subject line for waitlist confirmation emails
   - **Product Available Subject**: Customize the subject line for back-in-stock notification emails

6. **Save Changes**
   - Click the "Save Changes" button at the bottom of the page

## Setting Up Email Templates

The email templates are pre-configured, but you may want to customize them to match your brand:

1. **Customizing Email Templates**
   - The email templates are located in the `stockcartl/templates/emails/` directory
   - You can override these templates by copying them to your theme
   - Create a directory structure in your theme: `yourtheme/stockcartl/emails/`
   - Copy the template files you want to customize to this directory and edit them

2. **Email Template Files**
   - `waitlist-joined.php`: Sent when a customer joins the waitlist
   - `product-available.php`: Sent when a product is back in stock
   - `deposit-confirmation.php`: Sent when a deposit payment is confirmed
   - `deposit-refunded.php`: Sent when a deposit is refunded

3. **Testing Emails**
   - To test emails, join a waitlist for an out-of-stock product
   - Check your email to see if you receive the waitlist confirmation
   - To test back-in-stock notifications, change a product from out-of-stock to in-stock

## Product Configuration

1. **Individual Product Settings**
   - Edit any product in WooCommerce
   - Go to the "StockCartl" tab in the Product Data section
   - Here you can override the global settings for this specific product
   - Options include:
     - Enable/disable waitlist for this product
     - Enable/disable deposits for this product
     - Set custom deposit percentage
     - Customize social proof text

2. **Setting Up Out-of-Stock Products**
   - Edit a product and set its stock status to "Out of stock"
   - If using inventory management, you can set the stock quantity to 0
   - For variable products, you can set individual variations to be out of stock

3. **Variable Products**
   - For variable products, waitlists are managed per variation
   - Customers will only see the waitlist form when they select an out-of-stock variation
   - You can view and manage variation waitlists in the StockCartl admin page

## Testing the Waitlist

1. **Test as a Customer**
   - Open your store in an incognito/private browsing window
   - Navigate to an out-of-stock product
   - You should see the waitlist form instead of the "Out of stock" message
   - Try joining the waitlist with your email address
   - If deposits are enabled, test the deposit payment process

2. **Verify Admin Functionality**
   - Go to StockCartl > Dashboard in your admin panel
   - Check that your test waitlist entry appears in the dashboard
   - Go to StockCartl > Waitlists to view all waitlist entries
   - Try filtering and exporting the waitlist data

3. **Test Back-in-Stock Notifications**
   - Edit a product that has waitlist entries
   - Change its stock status from "Out of stock" to "In stock"
   - Save the product
   - The system should automatically send notifications to people on the waitlist
   - Check your email to verify the notification was sent

## Managing Waitlists

1. **Dashboard Overview**
   - The StockCartl Dashboard shows key metrics:
     - Total waitlist entries
     - Deposit statistics
     - Top products with waitlists
     - Recent waitlist entries

2. **Viewing Waitlists**
   - Go to StockCartl > Waitlists
   - Here you can see all waitlist entries across your store
   - Use the filters to sort by product, status, or waitlist type
   - Click "Export to CSV" to download the data for external analysis

3. **Managing Entries**
   - You can delete entries by clicking the "Delete" link
   - You can view details of each entry including:
     - Customer email
     - Product/variation
     - Waitlist type (free or deposit)
     - Position in the waitlist
     - Date added and expiration date

4. **Handling Expired Entries**
   - Entries will automatically expire after the configured period
   - Deposits will be automatically refunded when entries expire
   - You can filter to view expired entries in the Waitlists page

## Troubleshooting

### Common Issues

1. **Waitlist Form Not Showing**
   - Verify the product is actually set to "Out of stock"
   - Check that the plugin is enabled in the StockCartl settings
   - Check if the product has waitlist disabled in its individual settings

2. **Emails Not Being Sent**
   - Check your WordPress email configuration
   - Try installing an SMTP plugin to ensure reliable email delivery
   - Check the notification queue in the database

3. **Deposit Payments Not Processing**
   - Verify that your WooCommerce payment gateways are configured correctly
   - Test with a simple payment method like Cash on Delivery first
   - Check WooCommerce order logs for any errors

4. **Database Issues**
   - If you experience database errors, try deactivating and reactivating the plugin
   - This will trigger the database tables to be recreated

### Getting Support

If you encounter issues not covered in this guide:

1. Check the plugin documentation for updates
2. Contact our support team at support@stockcartl.com
3. Provide detailed information about your issue, including:
   - WordPress version
   - WooCommerce version
   - PHP version
   - Steps to reproduce the issue
   - Any error messages you're seeing

## Next Steps

After setting up StockCartl, consider these next steps:

1. **Customize Email Templates**: Make the emails match your brand identity
2. **Add StockCartl Information to Your FAQ**: Let customers know about your waitlist system
3. **Monitor Analytics**: Use the dashboard to identify high-demand products
4. **Create Marketing Campaigns**: Target customers who have joined waitlists with relevant offers

---

Thank you for using StockCartl! If you have any questions or feedback, please don't hesitate to reach out to our team.