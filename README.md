# StockCartl - Smart Waitlists for WooCommerce

Transform "Out of Stock" into opportunity with intelligent waitlist management and deposit priority systems.

## 🚀 Features

### Free Version
- ✅ Smart waitlist forms for out-of-stock products
- ✅ Priority deposits (25% configurable)
- ✅ Social proof ("X people waiting")
- ✅ Email notifications when back in stock
- ✅ Guest-to-customer account conversion
- ✅ Admin dashboard for waitlist management
- ✅ CSV export functionality
- ✅ WooCommerce HPOS (Custom Order Tables) compatibility

### Pro Version (Coming Soon)
- 🔥 SMS notifications
- 🔥 Advanced analytics dashboard
- 🔥 Multi-tier deposit systems
- 🔥 AI-powered demand forecasting
- 🔥 Custom compensation rules
- 🔥 Cross-platform support

## 🛠️ Development Setup

### Requirements
- WordPress 5.0+
- WooCommerce 4.0+
- PHP 7.4+

### Installation for Development
1. Clone this repository
2. Copy `stockcartl/` folder to `/wp-content/plugins/`
3. Activate plugin in WordPress admin
4. Install WooCommerce if not already installed

### Project Structure
```
stockcartl/
├── stockcartl.php              # Main plugin file
├── uninstall.php               # Cleanup on uninstall
├── includes/                   # Core functionality
│   ├── class-core.php         # Database & activation
│   ├── class-frontend.php     # Form display & AJAX
│   ├── class-payments.php     # Deposit handling
│   ├── class-notifications.php # Email system
│   ├── class-admin.php        # Admin dashboard
│   └── class-settings.php     # Settings page
├── assets/                     # CSS/JS files
├── templates/                  # Form and email templates
└── languages/                  # Translation files
```

## 🎨 Brand Colors
- Black: `#1a1a1a`
- Gold: `#d4af37`
- White: `#ffffff`
- Muted Olive: `#6b6b47`
- Electric Blue: `#4a90e2`

## 📝 Changelog

### v1.1.0 (June 26, 2025)
- Added: Compatibility with WooCommerce High-Performance Order Storage (HPOS/Custom Order Tables)
- Added: Declared official support for the latest WooCommerce version
- Improved: Order management now uses WooCommerce's CRUD API for better performance and compatibility

### v1.0.0 (Initial Release)
- Initial release
- Core waitlist functionality
- Deposit priority system
- Social proof features

## 🤝 Contributing

This is a private development project. For issues or feature requests, please create an issue in this repository.

## 📄 License

Proprietary - All rights reserved to StockCartl