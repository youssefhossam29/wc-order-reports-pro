<?php
/**
 * Plugin Name: WC Order Reports Pro (Bosta & WooCommerce)
 * Plugin URI: https://github.com/youssefhossam29/wc-order-reports-pro
 * Description: Advanced order tracking with reports, charts, exports, and performance analytics for WooCommerce & Bosta
 * Version: 2.2.0
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
define('WC_ORDER_REPORTS_PRO_VERSION', '2.2.0');
define('WC_ORDER_REPORTS_PRO_PATH', plugin_dir_path(__FILE__));
define('WC_ORDER_REPORTS_PRO_URL', plugin_dir_url(__FILE__));

// Load core plugin class
require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-wc-order-reports-pro.php';

// Initialize plugin
add_action('plugins_loaded', array('WC_Order_Reports_Pro', 'init'));
