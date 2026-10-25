<?php
/**
 * Admin Page
 * Main dashboard UI controller for reports
 *
 * @package WC_Order_Reports_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Order_Reports_Admin_Page {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;
    }

    public function render() {
        $date_presets = $this->get_date_presets();
        $current_from = isset( $_GET['from'] ) ? sanitize_text_field( $_GET['from'] ) : date( 'Y-m-d' );
        $current_to   = isset( $_GET['to'] ) ? sanitize_text_field( $_GET['to'] ) : date( 'Y-m-d' );

        $template = WC_ORDER_REPORTS_PRO_PATH . 'templates/admin-page.php';
        if ( file_exists( $template ) ) {
            include $template;
        }
    }

    private function get_date_presets() {
        return array(
            'today' => array(
                'label' => __( 'Today', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-d' ),
                'to'    => date( 'Y-m-d' ),
            ),
            'yesterday' => array(
                'label' => __( 'Yesterday', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-d', strtotime( '-1 day' ) ),
                'to'    => date( 'Y-m-d', strtotime( '-1 day' ) ),
            ),
            'last_7_days' => array(
                'label' => __( 'Last 7 Days', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-d', strtotime( '-7 days' ) ),
                'to'    => date( 'Y-m-d' ),
            ),
            'last_30_days' => array(
                'label' => __( 'Last 30 Days', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-d', strtotime( '-30 days' ) ),
                'to'    => date( 'Y-m-d' ),
            ),
            'this_month' => array(
                'label' => __( 'This Month', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-01' ),
                'to'    => date( 'Y-m-d' ),
            ),
            'last_month' => array(
                'label' => __( 'Last Month', 'wc-order-reports-pro' ),
                'from'  => date( 'Y-m-01', strtotime( 'first day of last month' ) ),
                'to'    => date( 'Y-m-t', strtotime( 'last day of last month' ) ),
            ),
        );
    }

    public function get_export_url( $format, $from, $to, $report_type = 'daily_status' ) {
        return wp_nonce_url(
            add_query_arg(
                array(
                    'wcorp_export' => $format,
                    'report_type'  => $report_type,
                    'from'         => $from,
                    'to'           => $to,
                ),
                admin_url( 'admin.php?page=wc-order-reports-pro' )
            ),
            'wcorp_export_' . $format
        );
    }
}
