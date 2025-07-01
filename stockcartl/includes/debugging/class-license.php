<?php
/**
 * License functionality for StockCartl
 *
 * @package StockCartl
 * @subpackage Debugging
 */

// If this file is called directly, abort.
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * StockCartl License Class
 * 
 * Handles license verification and feature availability
 */
class StockCartl_License {

    /**
     * License tiers
     */
    const TIER_FREE = 'free';
    const TIER_PREMIUM = 'premium';
    const TIER_ENTERPRISE = 'enterprise';

    /**
     * License data
     *
     * @var array
     */
    private $license_data;

    /**
     * Constructor
     */
    public function __construct() {
        $this->license_data = get_option('stockcartl_license_data', array(
            'tier' => self::TIER_FREE,
            'key' => '',
            'status' => 'inactive',
            'expiry' => '',
            'features' => array()
        ));

        // For development, enable all features
        if (defined('STOCKCARTL_DEV_MODE') && STOCKCARTL_DEV_MODE) {
            $this->license_data['tier'] = self::TIER_ENTERPRISE;
            $this->license_data['status'] = 'active';
        }
    }

    /**
     * Check if a specific feature is available
     *
     * @param string $feature_name The feature name
     * @return bool Whether the feature is available
     */
    public function has_feature($feature_name) {
        // During development, all features are available
        if (defined('STOCKCARTL_DEV_MODE') && STOCKCARTL_DEV_MODE) {
            return true;
        }
        
        // Feature mapping
        $features = array(
            // Free features
            'basic_logging' => array(self::TIER_FREE, self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            'simple_debug_toggle' => array(self::TIER_FREE, self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            'system_info' => array(self::TIER_FREE, self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            
            // Premium features
            'advanced_logging' => array(self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            'log_export' => array(self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            'log_filtering' => array(self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            'email_notifications' => array(self::TIER_PREMIUM, self::TIER_ENTERPRISE),
            
            // Enterprise features
            'conflict_detection' => array(self::TIER_ENTERPRISE),
            'unlimited_retention' => array(self::TIER_ENTERPRISE),
            'api_access' => array(self::TIER_ENTERPRISE),
            'priority_support' => array(self::TIER_ENTERPRISE)
        );
        
        $tier = $this->get_license_tier();
        
        // Check if feature is available for current tier
        return isset($features[$feature_name]) && in_array($tier, $features[$feature_name]);
    }

    /**
     * Get current license tier
     *
     * @return string The license tier
     */
    public function get_license_tier() {
        return $this->license_data['tier'];
    }

    /**
     * Get license status
     *
     * @return string The license status
     */
    public function get_license_status() {
        return $this->license_data['status'];
    }

    /**
     * Get license data
     *
     * @return array The license data
     */
    public function get_license_data() {
        return $this->license_data;
    }

    /**
     * Check if license is active
     *
     * @return bool Whether the license is active
     */
    public function is_active() {
        return $this->license_data['status'] === 'active';
    }

    /**
     * Get premium teaser HTML
     *
     * @param string $feature_name The feature name
     * @param string $feature_description The feature description
     * @return string The teaser HTML
     */
    public function get_premium_teaser($feature_name, $feature_description) {
        if ($this->has_feature($feature_name)) {
            return '';
        }
        
        $html = '<div class="stockcartl-premium-teaser">';
        $html .= '<h4>' . sprintf(__('Upgrade to access: %s', 'stockcartl'), $feature_description) . '</h4>';
        $html .= '<p>' . __('This feature is available in StockCartl Pro.', 'stockcartl') . '</p>';
        $html .= '<a href="https://stockcartl.com/pricing" class="button button-primary">' . __('Upgrade Now', 'stockcartl') . '</a>';
        $html .= '</div>';
        
        return $html;
    }
}