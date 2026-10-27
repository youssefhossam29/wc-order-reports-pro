<?php
/**
 * Plugin Name: WC Order Reports Pro (Bosta & WooCommerce)
 * Plugin URI: https://github.com/youssefhossam29/wc-order-reports-pro
 * Description: Advanced order tracking with reports, charts, exports, and performance analytics for WooCommerce & Bosta
 * Version: 2.3.0
 * Author: Youssef Hossam
 * Author URI: https://github.com/youssefhossam29
 * Text Domain: wc-order-reports-pro
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('WC_ORDER_REPORTS_PRO_VERSION', '2.3.0');
define('WC_ORDER_REPORTS_PRO_PATH', plugin_dir_path(__FILE__));
define('WC_ORDER_REPORTS_PRO_URL', plugin_dir_url(__FILE__));

// Declare compatibility with WooCommerce HPOS (High-Performance Order Storage)
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

// Initialize plugin with WooCommerce dependency guard
add_action('plugins_loaded', function () {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', function () {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><strong><?php esc_html_e('WC Order Reports Pro requires WooCommerce to be installed and active.', 'wc-order-reports-pro'); ?></strong></p>
            </div>
            <?php
        });
        return;
    }

    // Load core plugin class
    require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-wc-order-reports-pro.php';
    WC_Order_Reports_Pro::init();
});
