<?php
/**
 * Report Generator
 * Generates all types of reports based on order data
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Reports_Generator
{

    private $plugin;
    private $cache_manager;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;

        if ($plugin->is_feature_enabled('caching') && class_exists('WC_Order_Reports_Cache_Manager')) {
            $this->cache_manager = new WC_Order_Reports_Cache_Manager();
        }
    }

    public function generate_report($report_type, $from, $to, $filters = array())
    {
        $cache_key = $this->get_cache_key($report_type, $from, $to, $filters);

        // Try to get from cache
        if ($this->cache_manager) {
            $cached = $this->cache_manager->get($cache_key);
            if ($cached !== false) {
                return $cached;
            }
        }

        // Generate report
        $data = array();
        list($from_dt, $to_dt) = $this->normalize_dates($from, $to);

        try {
            switch ($report_type) {
                case 'daily_status':
                    $data = $this->generate_daily_status_report($from_dt, $to_dt);
                    break;
                case 'bosta':
                    $data = $this->generate_bosta_report($from_dt, $to_dt);
                    break;
                case 'cod':
                    $data = $this->generate_cod_report($from_dt, $to_dt, $filters);
                    break;
                case 'agent':
                    $data = $this->generate_agent_report($from_dt, $to_dt);
                    break;
                default:
                    $data = array('error' => 'Invalid report type: ' . $report_type);
            }
        } catch (Exception $e) {
            error_log('WC Order Reports Pro Error: ' . $e->getMessage());
            $data = array('error' => 'Failed to generate report: ' . $e->getMessage());
        }

        // Store in cache only if no error
        if ($this->cache_manager && !isset($data['error'])) {
            $this->cache_manager->set($cache_key, $data, 3600); // 1 hour
        }

        return $data;
    }

    private function normalize_dates($from, $to)
    {
        // Validate input dates
        if (empty($from) || empty($to)) {
            $today = date('Y-m-d');
            return array($today . ' 00:00:00', $today . ' 23:59:59');
        }

        $from_date = date_create_from_format('Y-m-d', $from);
        $to_date = date_create_from_format('Y-m-d', $to);

        if (!$from_date) {
            $from_date = new DateTime();
        }
        if (!$to_date) {
            $to_date = new DateTime();
        }

        // Ensure from date is not after to date
        if ($from_date > $to_date) {
            $temp = $from_date;
            $from_date = $to_date;
            $to_date = $temp;
        }

        $from_dt = $from_date->format('Y-m-d') . ' 00:00:00';
        $to_dt = $to_date->format('Y-m-d') . ' 23:59:59';

        return array($from_dt, $to_dt);
    }

    private function get_cache_key($report_type, $from, $to, $filters = array())
    {
        $key_part = $report_type . '_' . $from . $to;

        if (!empty($filters)) {
            // Sort filters to ensure consistent keys
            ksort($filters);
            foreach ($filters as $key => &$value) {
                if (is_array($value)) {
                    sort($value);
                }
            }
            $key_part .= json_encode($filters);
        }

        return 'wcorp_' . $report_type . '_' . md5($key_part);
    }

    /**
     * Daily Status Report
     */
    public function generate_daily_status_report($from_dt, $to_dt)
    {
        $orders = $this->get_orders_in_range($from_dt, $to_dt);

        $status_counts = array();
        $durations = array();
        $total_orders = count($orders);

        if ($total_orders === 0) {
            return array(
                'status_counts' => array(),
                'avg_confirm_to_ready_seconds' => 0,
                'num_samples' => 0,
                'total_orders' => 0,
                'date_range' => array(
                    'from' => $from_dt,
                    'to' => $to_dt,
                ),
            );
        }

        foreach ($orders as $order) {
            if (!$order || !is_object($order)) {
                continue;
            }

            $status = 'wc-' . $order->get_status();

            if (!isset($status_counts[$status])) {
                $status_counts[$status] = 0;
            }
            $status_counts[$status]++;

            // Calculate confirm → ready duration using multiple methods
            $duration = $this->calculate_processing_duration($order);
            if ($duration !== false && $duration > 0) {
                $durations[] = $duration;
            }
        }

        $avg_seconds = count($durations) > 0 ? array_sum($durations) / count($durations) : 0;

        return array(
            'status_counts' => $status_counts,
            'avg_confirm_to_ready_seconds' => $avg_seconds,
            'num_samples' => count($durations),
            'total_orders' => $total_orders,
            'date_range' => array(
                'from' => $from_dt,
                'to' => $to_dt,
            ),
        );
    }

    /**
     * Calculate processing duration using real timestamp data only
     * Calculates time from confirm_preparing to ready_ship_agent OR ready_ship_bosta
     * Based on the order's shipping method
     */
    private function calculate_processing_duration($order)
    {
        $order_id = $order->get_id();

        // Only use Status Tracker with real timestamp data
        if (class_exists('WC_Order_Reports_Status_Tracker')) {
            // Check if order uses agent shipping
            $shipping_method = $this->get_order_shipping_method($order);

            if ($shipping_method === 'agent') {
                // For agent orders: confirm_preparing → ready_ship_agent
                $duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                    $order_id,
                    'confirm_preparing',
                    'ready_ship_agent'
                );

                if ($duration !== false && $duration > 0) {
                    return $duration;
                }
            } else {
                // For bosta orders: confirm_preparing → ready_ship_bosta
                $duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                    $order_id,
                    'confirm_preparing',
                    'ready_ship_bosta'
                );

                if ($duration !== false && $duration > 0) {
                    return $duration;
                }
            }
        }

        // No real timestamp data available - return false
        return false;
    }

    /**
     * Determine if order uses agent or bosta shipping
     */
    private function get_order_shipping_method($order)
    {
        // Check shipping method meta
        $shipping_method = $order->get_meta('shipping_method');

        if ($shipping_method && strpos($shipping_method, 'agent') !== false) {
            return 'agent';
        }

        // Check if order has bosta tracking number
        $bosta_tracking = $order->get_meta('bosta_tracking_number');
        if ($bosta_tracking) {
            return 'bosta';
        }

        // Check order status to determine shipping method
        $status = $order->get_status();

        if (in_array($status, array('ready_ship_agent', 'handed_to_agent'))) {
            return 'agent';
        }

        if (in_array($status, array('ready_ship_bosta', 'sent_to_bosta', 'handed_to_bosta', 'in_transit', 'out_for_delivery'))) {
            return 'bosta';
        }

        // Default to bosta if uncertain
        return 'bosta';
    }

    /**
     * Bosta Performance Report
     * Only for orders with bosta_tracking_number meta key
     */
    public function generate_bosta_report($from_dt, $to_dt)
    {
        $args = array(
            'limit' => -1,
            'date_created' => $from_dt . '...' . $to_dt,
            'meta_query' => array(
                array(
                    'key' => 'bosta_tracking_number',
                    'compare' => 'EXISTS',
                ),
            ),
            'return' => 'objects',
        );

        $orders = wc_get_orders($args);

        // Define bosta-related statuses
        $bosta_statuses = array(
            'wc-ready_ship_bosta',
            'wc-sent_to_bosta',
            'wc-handed_to_bosta',
            'wc-in_transit',
            'wc-out_for_delivery',
            'wc-delivered',
            'wc-cod_collected',
            'wc-returned',
        );

        $delivered_count = 0;
        $returned_count = 0;
        $ready_ship_bosta_count = 0;
        $sent_to_bosta_count = 0;
        $handed_to_bosta_count = 0;
        $in_transit_count = 0;

        $ready_to_handed_durations = array();
        $handed_to_delivered_durations = array();

        foreach ($orders as $order) {
            if (!$order || !is_object($order)) {
                continue;
            }

            $status = 'wc-' . $order->get_status();

            // Only include orders in bosta-related statuses
            if (!in_array($status, $bosta_statuses)) {
                continue;
            }

            // Count statuses according to requirements
            if (in_array($status, array('wc-delivered', 'wc-cod_collected'))) {
                $delivered_count++;
            } elseif ($status === 'wc-returned') {
                $returned_count++;
            } elseif ($status === 'wc-ready_ship_bosta') {
                $ready_ship_bosta_count++;
            } elseif ($status === 'wc-sent_to_bosta') {
                $sent_to_bosta_count++;
            } elseif ($status === 'wc-handed_to_bosta') {
                $handed_to_bosta_count++;
            } elseif (in_array($status, array('wc-in_transit', 'wc-out_for_delivery'))) {
                $in_transit_count++;
            }

            // Calculate ready_ship_bosta → handed_to_bosta duration (as per requirements)
            if (class_exists('WC_Order_Reports_Status_Tracker')) {
                $duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                    $order->get_id(),
                    'ready_ship_bosta',
                    'handed_to_bosta'
                );
                if ($duration !== false && $duration > 0) {
                    $ready_to_handed_durations[] = $duration;
                }

                // Calculate handed_to_bosta → delivered duration
                $delivered_duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                    $order->get_id(),
                    'handed_to_bosta',
                    'delivered'
                );
                if ($delivered_duration !== false && $delivered_duration > 0) {
                    $handed_to_delivered_durations[] = $delivered_duration;
                }
            }
        }

        $total_completed = $delivered_count + $returned_count;
        $success_rate = $total_completed > 0 ? ($delivered_count / $total_completed) : null;

        $avg_ready_to_handed = count($ready_to_handed_durations) > 0
            ? array_sum($ready_to_handed_durations) / count($ready_to_handed_durations)
            : 0;

        $avg_handed_to_delivered = count($handed_to_delivered_durations) > 0
            ? array_sum($handed_to_delivered_durations) / count($handed_to_delivered_durations)
            : 0;

        // Calculate actual total orders (only bosta-related statuses)
        $total_bosta_orders = $delivered_count + $returned_count + $ready_ship_bosta_count +
            $sent_to_bosta_count + $handed_to_bosta_count + $in_transit_count;

        return array(
            'delivered_count' => $delivered_count,
            'returned_count' => $returned_count,
            'success_rate' => $success_rate,
            'ready_ship_bosta_count' => $ready_ship_bosta_count,
            'sent_to_bosta_count' => $sent_to_bosta_count,
            'handed_to_bosta_count' => $handed_to_bosta_count,
            'in_transit_count' => $in_transit_count,
            'avg_ready_to_handed_seconds' => $avg_ready_to_handed,
            'ready_to_handed_samples' => count($ready_to_handed_durations),
            'avg_handed_to_delivered_seconds' => $avg_handed_to_delivered,
            'handed_to_delivered_samples' => count($handed_to_delivered_durations),
            'total_orders' => $total_bosta_orders,
        );
    }

    /**
     * Financial Report (formerly COD Report)
     * Calculates totals and wholesale amounts for orders in range
     * Respects status filters if provided
     */
    public function generate_cod_report($from_dt, $to_dt, $filters = array())
    {
        $args = array(
            'limit' => -1,
            'date_created' => $from_dt . '...' . $to_dt,
            'return' => 'objects',
        );

        // Apply status filter if present
        if (!empty($filters['status'])) {
            $args['status'] = $filters['status'];
        }

        $orders = wc_get_orders($args);

        $collected_total = 0.0;
        $collected_count = 0;
        $pending_total = 0.0;
        $pending_count = 0;
        $returned_total = 0.0;
        $returned_count = 0;
        $wholesale_total = 0.0;

        // Pending statuses as per requirements
        $pending_statuses = array(
            'wc-awaiting_confirm',
            'wc-confirm_preparing',
            'wc-ready_ship_agent',
            'wc-ready_ship_bosta',
            'wc-sent_to_bosta',
            'wc-handed_to_bosta',
            'wc-handed_to_agent',
            'wc-in_transit',
            'wc-out_for_delivery',
        );

        foreach ($orders as $order) {
            if (!$order || !is_object($order)) {
                continue;
            }

            $status = 'wc-' . $order->get_status();
            $total = floatval($order->get_total());

            if ($status === 'wc-cod_collected') {
                $collected_total += $total;
                $collected_count++;
            } elseif (in_array($status, $pending_statuses)) {
                $pending_total += $total;
                $pending_count++;
            } elseif ($status === 'wc-returned') {
                $returned_total += $total;
                $returned_count++;
            }

            // Calculate wholesale amount for all order items
            // User Request: Only for orders with status 'cod_collected'
            if ($status === 'wc-cod_collected') {
                $wholesale_total += $this->calculate_order_wholesale_amount($order);
            }
        }

        return array(
            'collected_total' => $collected_total,
            'collected_count' => $collected_count,
            'pending_total' => $pending_total,
            'pending_count' => $pending_count,
            'returned_total' => $returned_total,
            'returned_count' => $returned_count,
            'total_orders' => count($orders),
            'collection_rate' => count($orders) > 0 ? ($collected_count / count($orders)) : 0,
            'wholesale_total' => $wholesale_total,
        );
    }

    /**
     * Calculate total wholesale amount for an order
     * Sums up wholesale_price * quantity for all product items
     * Uses historical prices saved at purchase time with fallback to current product meta
     */
    private function calculate_order_wholesale_amount($order)
    {
        $wholesale_amount = 0.0;

        if (!$order || !is_object($order)) {
            return $wholesale_amount;
        }

        // Get all order items (products only, not fees/shipping)
        $items = $order->get_items('line_item');

        foreach ($items as $item) {
            if (!is_object($item)) {
                continue;
            }

            // Strict type check: ensure this is a product line item
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            // Prefer explicit snapshot meta when present (even if zero)
            if ($item->meta_exists('_wholesale_price_at_purchase')) {
                $wholesale_price = floatval($item->get_meta('_wholesale_price_at_purchase', true));
            } else {
                // Fallback: use lazy history method (may create snapshot in admin context)
                $wholesale_price = WC_Order_Reports_Wholesale_Price::get_item_wholesale_price_with_history($item);
            }

            // Get quantity
            $quantity = floatval($item->get_quantity());

            // Add to total
            $wholesale_amount += ($wholesale_price * $quantity);
        }

        return $wholesale_amount;
    }

    /**
     * Agent Performance Report (Comparison with Bosta)
     */
    public function generate_agent_report($from_dt, $to_dt)
    {
        // Get ALL orders in date range first
        $all_orders = $this->get_orders_in_range($from_dt, $to_dt);

        $agent_orders = array();

        // Filter orders that went through agent shipping (based on timestamps)
        foreach ($all_orders as $order) {
            if (!$order || !is_object($order)) {
                continue;
            }

            // Check if order has agent status timestamps (indicating it went through agent shipping)
            $ready_ship_agent_ts = get_post_meta($order->get_id(), '_status_changed_ready_ship_agent_at', true);
            $handed_to_agent_ts = get_post_meta($order->get_id(), '_status_changed_handed_to_agent_at', true);

            // If order has either timestamp, it's an agent order
            if ($ready_ship_agent_ts || $handed_to_agent_ts) {
                $agent_orders[] = $order;
            }
        }

        $agent_delivered = 0;
        $agent_returned = 0;
        $agent_ready_ship = 0;
        $agent_handed = 0;
        $agent_in_progress = 0;
        $agent_durations = array();

        foreach ($agent_orders as $order) {
            $status = 'wc-' . $order->get_status();

            // Count final statuses
            if (in_array($status, array('wc-delivered', 'wc-cod_collected'))) {
                $agent_delivered++;
            } elseif ($status === 'wc-returned') {
                $agent_returned++;
            } elseif ($status === 'wc-ready_ship_agent') {
                $agent_ready_ship++;
            } elseif ($status === 'wc-handed_to_agent') {
                $agent_handed++;
            } else {
                // Count other statuses as "in progress" (processing, etc.)
                $agent_in_progress++;
            }

            // Calculate delivery duration for agent orders
            // Try ready_ship_agent → delivered first, then handed_to_agent → delivered
            if (class_exists('WC_Order_Reports_Status_Tracker')) {
                // Try ready_ship_agent → delivered
                $duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                    $order->get_id(),
                    'ready_ship_agent',
                    'delivered'
                );

                // If no duration from ready_ship_agent, try handed_to_agent → delivered
                if (($duration === false || $duration <= 0)) {
                    $duration = WC_Order_Reports_Status_Tracker::get_duration_between_statuses(
                        $order->get_id(),
                        'handed_to_agent',
                        'delivered'
                    );
                }

                if ($duration !== false && $duration > 0) {
                    $agent_durations[] = $duration;
                }
            }
        }

        $agent_success_rate = ($agent_delivered + $agent_returned) > 0
            ? ($agent_delivered / ($agent_delivered + $agent_returned))
            : null;

        $agent_avg_delivery = count($agent_durations) > 0
            ? array_sum($agent_durations) / count($agent_durations)
            : 0;

        // Calculate actual total orders (all orders that went through agent)
        $total_agent_orders = $agent_delivered + $agent_returned + $agent_ready_ship + $agent_handed + $agent_in_progress;

        // Get Bosta data for comparison
        $bosta_data = $this->generate_bosta_report($from_dt, $to_dt);

        return array(
            'agent' => array(
                'total_orders' => $total_agent_orders,
                'delivered' => $agent_delivered,
                'returned' => $agent_returned,
                'ready_ship' => $agent_ready_ship,
                'handed' => $agent_handed,
                'in_progress' => $agent_in_progress,
                'success_rate' => $agent_success_rate,
                'avg_delivery_seconds' => $agent_avg_delivery,
                'delivery_samples' => count($agent_durations),
            ),
            'bosta' => array(
                'total_orders' => $bosta_data['total_orders'],
                'delivered' => $bosta_data['delivered_count'],
                'returned' => $bosta_data['returned_count'],
                'ready_ship' => $bosta_data['ready_ship_bosta_count'],
                'sent' => $bosta_data['sent_to_bosta_count'],
                'handed' => $bosta_data['handed_to_bosta_count'],
                'in_transit' => $bosta_data['in_transit_count'],
                'success_rate' => $bosta_data['success_rate'],
                'avg_delivery_seconds' => $bosta_data['avg_handed_to_delivered_seconds'],
            ),
        );
    }

    /**
     * Get orders in date range (optimized)
     */
    private function get_orders_in_range($from_dt, $to_dt, $additional_args = array())
    {
        $args = array_merge(
            array(
                'limit' => -1,
                'date_created' => $from_dt . '...' . $to_dt,
                'return' => 'objects',
            ),
            $additional_args
        );

        return wc_get_orders($args);
    }

    /**
     * Utility: Convert seconds to human readable
     */
    public static function seconds_to_human($seconds)
    {
        $s = intval(round($seconds));

        if ($s <= 0) {
            return '-';
        }

        $days = floor($s / 86400);
        $s -= $days * 86400;
        $hours = floor($s / 3600);
        $s -= $hours * 3600;
        $mins = floor($s / 60);
        $secs = $s - ($mins * 60);

        $parts = array();
        if ($days)
            $parts[] = $days . 'd';
        if ($hours)
            $parts[] = $hours . 'h';
        if ($mins)
            $parts[] = $mins . 'm';
        if ($secs && !$days)
            $parts[] = $secs . 's'; // Hide seconds if days present

        return implode(' ', $parts);
    }
}
