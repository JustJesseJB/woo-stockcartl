=== StockCartl - Smart Waitlists for WooCommerce ===
Contributors: ambitionplugins
Tags: woocommerce, waitlist, stock, inventory, out-of-stock, deposits, preorder
Requires at least: 5.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.1.3
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Transform "Out of Stock" into revenue opportunities with intelligent waitlist management, deposit priority systems, and social proof features.

== Description ==

StockCartl is a powerful WooCommerce extension that helps you convert out-of-stock situations into revenue opportunities through intelligent waitlist management.

**Never lose a sale again when items are out of stock!**

### Key Features

* **Smart Waitlist Forms** - Replace "Out of Stock" notices with email capture forms
* **Deposit Priority System** - Allow customers to pay deposits (configurable percentage) to secure priority positions
* **Social Proof** - Show how many people are waiting for a product to build urgency
* **Email Notifications** - Automatically notify customers when products are back in stock
* **Guest-to-Customer Conversion** - Turn guest emails into customer accounts
* **Admin Dashboard** - Comprehensive waitlist management with analytics
* **CSV Export** - Export waitlist data for external processing
* **WooCommerce HPOS Compatible** - Full support for High-Performance Order Storage / Custom Order Tables
* **Variable Products Support** - Seamless waitlist functionality for out-of-stock variations
* **Debug System** - Built-in debugging tools for troubleshooting

### Pro Version (Coming Soon)

* SMS notifications
* Advanced analytics dashboard
* Multi-tier deposit systems
* AI-powered demand forecasting
* Custom compensation rules
* Cross-platform support
* Comprehensive debugging system
* Conflict detection with other plugins

== Installation ==

1. Upload the 'stockcartl' folder to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to WooCommerce > StockCartl to configure settings

== Frequently Asked Questions ==

= Does this work with variable products? =

Yes! StockCartl works seamlessly with both simple and variable products. For variable products, customers will only see the waitlist form when they select an out-of-stock variation.

= How do deposits work? =

When a customer joins a waitlist with a deposit, they pay a configurable percentage of the product price (default 25%). This creates a WooCommerce order and when the product is back in stock, customers with deposits get priority access. The deposit amount is applied to their purchase when they buy the product.

= What happens if a waitlist expires? =

If a product doesn't come back in stock during the waitlist period (configurable: 30/60/90 days), any deposits are automatically refunded through WooCommerce's refund system.

= Is this compatible with WooCommerce HPOS/Custom Order Tables? =

Yes! As of version 1.1.0, StockCartl is fully compatible with WooCommerce's High-Performance Order Storage (HPOS) feature. It works seamlessly whether you have HPOS enabled or disabled.

= How can I troubleshoot issues with the plugin? =

StockCartl includes a built-in debugging system. Administrators can enable debug logging in the plugin settings, which logs all key operations including waitlist entries, deposit payments, and email notifications. Debug logs can be viewed in the StockCartl > Debug Logs admin page.

== Screenshots ==

1. Frontend waitlist form on product page
2. Admin dashboard with waitlist overview
3. Waitlist management interface
4. Settings panel
5. Email notification template
6. Debug logs admin page

== Changelog ==

= 1.1.3 - July 3, 2025 =
* Fixed: Debug log functionality 

= 1.1.2 - July 3, 2025 =
* Added: Comprehensive debugging system for improved troubleshooting
* Enhanced: Error logging for critical operations
* Improved: Debug log viewer in admin dashboard
* Fixed: Debug log directory creation with better error handling

= 1.1.1 - June 27, 2025 =
* Fixed: Variable product waitlist functionality for out-of-stock variations
* Improved: Pre-generation of waitlist forms for better performance
* Enhanced: Form submission handling for variable products

= 1.1.0 - June 26, 2025 =
* Added: Compatibility with WooCommerce High-Performance Order Storage (HPOS/Custom Order Tables)
* Added: Declared official support for the latest WooCommerce version
* Improved: Order management now uses WooCommerce's CRUD API for better performance and compatibility

= 1.0.0 - Initial Release =
* Initial release
* Core waitlist functionality
* Deposit priority system
* Social proof features
* Admin dashboard

== Upgrade Notice ==

= 1.1.2 =
Important debugging enhancements to help diagnose and resolve issues. This update includes a comprehensive error logging system and improved debug tools for administrators.

= 1.1.1 =
Important fix for variable product waitlists. This update ensures that waitlist forms appear correctly for out-of-stock variations and improves the submission process.

= 1.1.0 =
Important update for WooCommerce compatibility. This version adds support for WooCommerce High-Performance Order Storage (HPOS/Custom Order Tables) and improves overall plugin performance.