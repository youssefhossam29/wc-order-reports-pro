<?php
/**
 * Cache Manager
 * Handles caching of report data for better performance
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Order_Reports_Cache_Manager {

    private $cache_group = 'wc_order_reports_pro';
    private $cache_prefix = 'wcorp_';

    public function get( $key ) {
        return get_transient( $this->cache_prefix . $key );
    }

    public function set( $key, $value, $expiration = 3600 ) {
        return set_transient( $this->cache_prefix . $key, $value, $expiration );
    }

    public function delete( $key ) {
        return delete_transient( $this->cache_prefix . $key );
    }

    public function clear_all() {
        global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%'
            )
        );

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_timeout_' . $this->cache_prefix ) . '%'
            )
        );

        return true;
    }

    public static function clear_cache_for_order( $order_id ) {
        $manager = new self();
        
        // Clear all report caches when an order is updated
        $manager->clear_all();
    }

    /**
     * Get cache statistics
     */
    public function get_stats() {
        global $wpdb;

        $count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like( '_transient_' . $this->cache_prefix ) . '%'
            )
        );

        return array(
            'cached_items' => intval( $count ),
            'cache_group' => $this->cache_group,
        );
    }
}
