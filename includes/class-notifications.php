<?php
/**
 * Notifications
 * Handles alerts and performance notifications
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Order_Reports_Notifications {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        add_action( 'admin_notices', array( $this, 'show_performance_alerts' ) );
        add_action( 'woocommerce_order_status_changed', array( $this, 'check_order_delays' ), 20, 4 );
    }

    /**
     * Show performance alerts in admin
     */
    public function show_performance_alerts() {
        $screen = get_current_screen();
        
        if ( ! $screen || strpos( $screen->id, 'wc-order-reports-pro' ) === false ) {
            return;
        }

        $alerts = $this->get_active_alerts();

        foreach ( $alerts as $alert ) {
            printf(
                '<div class="notice notice-%s is-dismissible"><p><strong>%s:</strong> %s</p></div>',
                esc_attr( $alert['type'] ),
                esc_html( $alert['title'] ),
                esc_html( $alert['message'] )
            );
        }
    }

    private function get_active_alerts() {
        $alerts = array();

        // Check for stuck orders
        $stuck_orders = $this->get_stuck_orders();
        if ( $stuck_orders > 0 ) {
            $alerts[] = array(
                'type' => 'warning',
                'title' => __( 'Delayed Orders', 'wc-order-reports-pro' ),
                'message' => sprintf(
                    __( '%d orders have been in the same status for more than 48 hours', 'wc-order-reports-pro' ),
                    $stuck_orders
                ),
            );
        }

        // Check success rate
        $success_rate = $this->get_recent_success_rate();
        if ( $success_rate !== null && $success_rate < 0.8 ) {
            $alerts[] = array(
                'type' => 'error',
                'title' => __( 'Low Success Rate', 'wc-order-reports-pro' ),
                'message' => sprintf(
                    __( 'Delivery success rate is only %.1f%% this week', 'wc-order-reports-pro' ),
                    $success_rate * 100
                ),
            );
        }

        // Check COD collection rate
        $cod_rate = $this->get_cod_collection_rate();
        if ( $cod_rate !== null && $cod_rate < 0.7 ) {
            $alerts[] = array(
                'type' => 'warning',
                'title' => __( 'Low COD Collection', 'wc-order-reports-pro' ),
                'message' => sprintf(
                    __( 'COD collection rate is %.1f%% - consider reviewing payment options', 'wc-order-reports-pro' ),
                    $cod_rate * 100
                ),
            );
        }

        return $alerts;
    }

    private function get_stuck_orders() {
        $args = array(
            'limit' => -1,
            'status' => array( 'ready_ship_bosta', 'sent_to_bosta', 'ready_ship_agent' ),
            'date_modified' => '<' . date( 'Y-m-d H:i:s', strtotime( '-48 hours' ) ),
            'return' => 'ids',
        );

        $orders = wc_get_orders( $args );
        return count( $orders );
    }

    private function get_recent_success_rate() {
        $last_week = date( 'Y-m-d', strtotime( '-7 days' ) );
        $today = date( 'Y-m-d' );

        $generator = new WC_Order_Reports_Generator( $this->plugin );
        $report = $generator->generate_report( 'bosta', $last_week, $today );

        return isset( $report['success_rate'] ) ? $report['success_rate'] : null;
    }

    private function get_cod_collection_rate() {
        $last_week = date( 'Y-m-d', strtotime( '-7 days' ) );
        $today = date( 'Y-m-d' );

        $generator = new WC_Order_Reports_Generator( $this->plugin );
        $report = $generator->generate_report( 'cod', $last_week, $today );

        return isset( $report['collection_rate'] ) ? $report['collection_rate'] : null;
    }

    /**
     * Check for delays when order status changes
     */
    public function check_order_delays( $order_id, $old_status, $new_status, $order ) {
        // Check if order took too long in previous status
        $last_changed = get_post_meta( $order_id, '_last_status_changed_at', true );
        
        if ( ! $last_changed ) {
            return;
        }

        $last_changed_ts = strtotime( $last_changed );
        $duration = time() - $last_changed_ts;

        // Alert thresholds (in seconds)
        $thresholds = array(
            'awaiting_confirm' => 86400, // 24 hours
            'confirm_preparing' => 172800, // 48 hours
            'ready_ship_bosta' => 86400, // 24 hours
            'sent_to_bosta' => 172800, // 48 hours
        );

        $clean_old = str_replace( 'wc-', '', $old_status );

        if ( isset( $thresholds[$clean_old] ) && $duration > $thresholds[$clean_old] ) {
            $this->send_delay_notification( $order_id, $old_status, $duration );
        }
    }

    private function send_delay_notification( $order_id, $status, $duration ) {
        $option_key = 'wcorp_delay_notified_' . $order_id . '_' . $status;
        
        // Avoid duplicate notifications
        if ( get_transient( $option_key ) ) {
            return;
        }

        $recipients = $this->get_notification_recipients();
        
        if ( empty( $recipients ) ) {
            return;
        }

        $order = wc_get_order( $order_id );
        $duration_human = WC_Order_Reports_Generator::seconds_to_human( $duration );

        $subject = sprintf(
            __( 'Order #%d Delayed - %s', 'wc-order-reports-pro' ),
            $order->get_order_number(),
            $status
        );

        $message = sprintf(
            __( 'Order #%d has been in status "%s" for %s. Please review.', 'wc-order-reports-pro' ),
            $order->get_order_number(),
            $status,
            $duration_human
        );

        foreach ( $recipients as $recipient ) {
            wp_mail( $recipient, $subject, $message );
        }

        // Set flag to avoid re-sending
        set_transient( $option_key, 1, 86400 ); // 24 hours
    }

    private function get_notification_recipients() {
        $recipients = get_option( 'wcorp_notification_recipients', array() );
        
        if ( empty( $recipients ) ) {
            $recipients = array( get_option( 'admin_email' ) );
        }

        return array_filter( array_map( 'sanitize_email', $recipients ) );
    }
}

// Note: Class is initialized from main plugin file when notifications feature is enabled
