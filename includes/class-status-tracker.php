<?php
/**
 * Status Tracker
 * Records timestamps when order statuses change
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Order_Reports_Status_Tracker {

    public function __construct() {
        add_action( 'woocommerce_order_status_changed', array( $this, 'record_status_timestamp' ), 10, 4 );
    }

    /**
     * Record timestamp when order status changes
     */
    public function record_status_timestamp( $order_id, $old_status, $new_status, $order ) {
        if ( ! $order_id ) {
            return;
        }

        $order = $order instanceof WC_Order ? $order : wc_get_order( $order_id );
        if ( ! $order ) {
            return;
        }

        $now = current_time( 'mysql' );

        // Normalize status names
        $clean_new = $this->meta_status_key( $new_status );
        $meta_key = '_status_changed_' . $clean_new . '_at';

        // Save timestamp & last status details
        $order->update_meta_data( $meta_key, $now );
        $order->update_meta_data( '_last_status_changed_at', $now );
        $order->update_meta_data( '_last_status', $new_status );

        // Store transition history (JSON array)
        $history = $order->get_meta( '_status_history', true );
        if ( ! $history ) {
            $history = array();
        } else {
            $history = is_array( $history ) ? $history : json_decode( $history, true );
        }

        $history[] = array(
            'from' => $old_status,
            'to' => $new_status,
            'timestamp' => $now,
            'user_id' => get_current_user_id(),
        );

        $order->update_meta_data( '_status_history', wp_json_encode( $history ) );
        $order->save();

        // Clear cache for this order
        $this->clear_order_cache( $order_id );
    }

    private function meta_status_key( $status ) {
        $s = str_replace( 'wc-', '', $status );
        $s = sanitize_key( $s );
        return $s;
    }

    private function clear_order_cache( $order_id ) {
        if ( class_exists( 'WC_Order_Reports_Cache_Manager' ) ) {
            WC_Order_Reports_Cache_Manager::clear_cache_for_order( $order_id );
        }
    }

    /**
     * Get status history for an order
     */
    public static function get_order_history( $order_id ) {
        $order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
        if ( ! $order ) {
            return array();
        }
        $history = $order->get_meta( '_status_history', true );
        if ( ! $history ) {
            return array();
        }
        return is_array( $history ) ? $history : json_decode( $history, true );
    }

    /**
     * Get timestamp for specific status
     */
    public static function get_status_timestamp( $order_id, $status ) {
        $order = $order_id instanceof WC_Order ? $order_id : wc_get_order( $order_id );
        if ( ! $order ) {
            return '';
        }
        $clean_status = str_replace( 'wc-', '', $status );
        $clean_status = sanitize_key( $clean_status );
        $meta_key = '_status_changed_' . $clean_status . '_at';
        return $order->get_meta( $meta_key, true );
    }

    /**
     * Calculate duration between two statuses
     */
    public static function get_duration_between_statuses( $order_id, $from_status, $to_status ) {
        $from_ts = self::get_status_timestamp( $order_id, $from_status );
        $to_ts = self::get_status_timestamp( $order_id, $to_status );

        if ( ! $from_ts || ! $to_ts ) {
            return false; // No data available - don't create fake timestamps
        }

        $from_time = strtotime( $from_ts );
        $to_time = strtotime( $to_ts );

        if ( $to_time < $from_time ) {
            return false;
        }

        return $to_time - $from_time;
    }
}

// Note: Class is initialized from main plugin file when status_tracking feature is enabled
