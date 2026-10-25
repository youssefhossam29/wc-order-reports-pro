<?php
/**
 * Core Plugin Class
 *
 * @package WC_Order_Reports_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Plugin Class
 */
class WC_Order_Reports_Pro
{
    private static $instance = null;

    // Feature flags - Set to false to disable any feature
    private $features = array(
        'status_tracking' => true,  // Core: Track order status changes
        'daily_report'    => true,  // Report: Daily status report
        'bosta_report'    => true,  // Report: Bosta performance
        'cod_report'      => true,  // Report: COD financial report
        'agent_report'    => true,  // Report: Agent performance comparison
        'charts'          => true,  // UI: Visual charts
        'export_csv'      => true,  // Export: CSV download
        'export_excel'    => true,  // Export: Excel download
        'email_reports'   => true,  // Email: Scheduled reports
        'caching'         => true,  // Performance: Cache results
        'ajax_loading'    => true,  // UI: AJAX page loading
        'notifications'   => true,  // Alerts: Performance notifications
        'date_presets'    => true,  // UI: Quick date filters
        'order_timeline'  => true,  // Individual: Order timeline view
        'settings_page'   => true,  // Admin: Settings configuration
    );

    public static function init()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function load_dependencies()
    {
        // Core functionality
        if ($this->is_feature_enabled('status_tracking')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-status-tracker.php';
            new WC_Order_Reports_Status_Tracker(); // Initialize status tracking
        }

        // Wholesale price support
        require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-wholesale-price.php';

        // Reports
        require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-report-generator.php';

        // Features
        if ($this->is_feature_enabled('export_csv') || $this->is_feature_enabled('export_excel')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-exporter.php';
        }

        if ($this->is_feature_enabled('email_reports')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-email-reports.php';
            new WC_Order_Reports_Email_Reports($this); // Initialize email reports
        }

        if ($this->is_feature_enabled('caching')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-cache-manager.php';
        }

        if ($this->is_feature_enabled('notifications')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-notifications.php';
            new WC_Order_Reports_Notifications($this); // Initialize notifications
        }

        if ($this->is_feature_enabled('settings_page')) {
            require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-settings.php';
            new WC_Order_Reports_Settings(); // Initialize settings
        }

        // Admin UI
        require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-admin-page.php';
        require_once WC_ORDER_REPORTS_PRO_PATH . 'includes/class-tools.php';
        WC_Order_Reports_Tools::init();
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'register_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));

        // AJAX handlers
        if ($this->is_feature_enabled('ajax_loading')) {
            add_action('wp_ajax_wcorp_load_report', array($this, 'ajax_load_report'));
        }

        if ($this->is_feature_enabled('export_csv')) {
            add_action('admin_init', array($this, 'handle_csv_export'));
        }

        if ($this->is_feature_enabled('export_excel')) {
            add_action('admin_init', array($this, 'handle_excel_export'));
        }

        // AJAX handler for clearing cache
        if ($this->is_feature_enabled('caching')) {
            add_action('wp_ajax_wcorp_clear_cache', array($this, 'ajax_clear_cache'));
        }

        // Cache invalidation hooks
        if ($this->is_feature_enabled('caching')) {
            add_action('save_post_product', array($this, 'clear_cache_on_product_update'), 10, 3);
            add_action('updated_option', array($this, 'clear_cache_on_settings_update'), 10, 3);
        }
    }

    public function is_feature_enabled($feature)
    {
        return isset($this->features[$feature]) && $this->features[$feature] === true;
    }

    public function get_enabled_features()
    {
        return array_keys(array_filter($this->features));
    }

    public function register_admin_menu()
    {
        $capability = 'manage_woocommerce';

        add_menu_page(
            __('WC Order Reports Pro', 'wc-order-reports-pro'),
            __('Order Reports', 'wc-order-reports-pro'),
            $capability,
            'wc-order-reports-pro',
            array($this, 'render_admin_page'),
            'dashicons-chart-area',
            56
        );

        add_submenu_page(
            'wc-order-reports-pro',
            __('Tools', 'wc-order-reports-pro'),
            __('Tools', 'wc-order-reports-pro'),
            'manage_woocommerce',
            'wc-order-reports-pro-tools',
            array('WC_Order_Reports_Tools', 'render_page')
        );

        if ($this->is_feature_enabled('settings_page')) {
            add_submenu_page(
                'wc-order-reports-pro',
                __('Settings', 'wc-order-reports-pro'),
                __('Settings', 'wc-order-reports-pro'),
                $capability,
                'wc-order-reports-pro-settings',
                array('WC_Order_Reports_Settings', 'render_page')
            );
        }
    }

    public function enqueue_assets($hook)
    {
        if (strpos($hook, 'wc-order-reports-pro') === false) {
            return;
        }

        // Styles - Force cache refresh with timestamp
        wp_enqueue_style(
            'wc-order-reports-pro-admin',
            WC_ORDER_REPORTS_PRO_URL . 'assets/css/admin.css',
            array(),
            WC_ORDER_REPORTS_PRO_VERSION . '-' . filemtime(WC_ORDER_REPORTS_PRO_PATH . 'assets/css/admin.css')
        );

        // Scripts - Force cache refresh with timestamp
        wp_enqueue_script(
            'wc-order-reports-pro-admin',
            WC_ORDER_REPORTS_PRO_URL . 'assets/js/admin.js',
            array('jquery'),
            WC_ORDER_REPORTS_PRO_VERSION . '-' . filemtime(WC_ORDER_REPORTS_PRO_PATH . 'assets/js/admin.js'),
            true
        );

        // Charts library
        if ($this->is_feature_enabled('charts')) {
            wp_enqueue_script(
                'chartjs',
                'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
                array(),
                '4.4.0',
                true
            );
        }

        // Localize script
        wp_localize_script('wc-order-reports-pro-admin', 'wcorpData', array(
            'ajaxUrl'        => admin_url('admin-ajax.php'),
            'nonce'          => wp_create_nonce('wcorp_nonce'),
            'features'       => $this->get_enabled_features(),
            'currency'       => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : 'EGP',
            'currencySymbol' => function_exists('get_woocommerce_currency_symbol') ? html_entity_decode(get_woocommerce_currency_symbol()) : 'EGP',
        ));
    }

    public function render_admin_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'wc-order-reports-pro'));
        }

        $admin_page = new WC_Order_Reports_Admin_Page($this);
        $admin_page->render();
    }

    public function ajax_load_report()
    {
        check_ajax_referer('wcorp_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $report_type = isset($_POST['report_type']) ? sanitize_text_field($_POST['report_type']) : '';
        $from = isset($_POST['from']) ? sanitize_text_field($_POST['from']) : date('Y-m-d');
        $to = isset($_POST['to']) ? sanitize_text_field($_POST['to']) : date('Y-m-d');

        // Capture filters
        $filters = array();
        if (isset($_POST['order_statuses']) && is_array($_POST['order_statuses'])) {
            $filters['status'] = array_map('sanitize_text_field', $_POST['order_statuses']);
        }

        $generator = new WC_Order_Reports_Generator($this);
        $data = $generator->generate_report($report_type, $from, $to, $filters);

        wp_send_json_success($data);
    }

    public function handle_csv_export()
    {
        if (!isset($_GET['wcorp_export']) || $_GET['wcorp_export'] !== 'csv') {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        // Check nonce for the specific export type
        $export_type = sanitize_text_field($_GET['wcorp_export']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'wcorp_export_' . $export_type)) {
            wp_die('Security check failed');
        }

        $exporter = new WC_Order_Reports_Exporter($this);
        $exporter->export_csv();
        exit;
    }

    public function handle_excel_export()
    {
        if (!isset($_GET['wcorp_export']) || $_GET['wcorp_export'] !== 'excel') {
            return;
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_die('Insufficient permissions');
        }

        // Check nonce for the specific export type
        $export_type = sanitize_text_field($_GET['wcorp_export']);
        if (!wp_verify_nonce($_GET['_wpnonce'], 'wcorp_export_' . $export_type)) {
            wp_die('Security check failed');
        }

        $exporter = new WC_Order_Reports_Exporter($this);
        $exporter->export_excel();
        exit;
    }

    public function ajax_clear_cache()
    {
        check_ajax_referer('wcorp_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        if (class_exists('WC_Order_Reports_Cache_Manager')) {
            $cache = new WC_Order_Reports_Cache_Manager();
            $cache->clear_all();
            wp_send_json_success(array('message' => 'Cache cleared successfully'));
        } else {
            wp_send_json_error(array('message' => 'Cache manager not available'));
        }
    }

    /**
     * Clear cache when product is updated (wholesale price might change)
     */
    public function clear_cache_on_product_update($post_id, $post, $update)
    {
        if (class_exists('WC_Order_Reports_Cache_Manager')) {
            $cache = new WC_Order_Reports_Cache_Manager();
            $cache->clear_all();
        }
    }

    /**
     * Clear cache when settings are updated
     */
    public function clear_cache_on_settings_update($option, $old_value, $new_value)
    {
        if (strpos($option, 'wcorp_') === 0 && class_exists('WC_Order_Reports_Cache_Manager')) {
            $cache = new WC_Order_Reports_Cache_Manager();
            $cache->clear_all();
        }
    }
}
