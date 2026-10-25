<?php
/**
 * Wholesale Price Meta Fields
 * Adds wholesale price support to WooCommerce products (simple & variations)
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Reports_Wholesale_Price
{

    public function __construct()
    {
        // Simple product wholesale price field
        add_action('woocommerce_product_options_pricing', array($this, 'add_wholesale_price_field'));
        add_action('woocommerce_process_product_meta', array($this, 'save_wholesale_price_field'));

        // Variable product wholesale price field (per variation)
        add_action('woocommerce_product_after_variable_attributes', array($this, 'add_variation_wholesale_price_field'), 10, 3);
        add_action('woocommerce_save_product_variation', array($this, 'save_variation_wholesale_price_field'), 10, 2);

        // Save wholesale price at purchase time for historical accuracy
        add_action('woocommerce_checkout_create_order_line_item', array($this, 'save_wholesale_price_at_purchase'), 10, 4);
        add_action('woocommerce_new_order_item', array($this, 'save_wholesale_price_for_manual_orders'), 10, 3);

        // Admin order screen - show wholesale prices
        add_action('woocommerce_admin_order_item_headers', array($this, 'add_wholesale_column_header'));
        add_action('woocommerce_admin_order_item_values', array($this, 'add_wholesale_column_values'), 10, 3);
        add_action('woocommerce_admin_order_totals_after_total', array($this, 'add_wholesale_total_row'), 10, 1);
    }

    /**
     * Add wholesale price field to simple products
     */
    public function add_wholesale_price_field()
    {
        global $post;

        woocommerce_wp_text_input(
            array(
                'id' => '_wholesale_price',
                'label' => __('Wholesale Price', 'wc-order-reports-pro') . ' (' . get_woocommerce_currency_symbol() . ')',
                'placeholder' => '',
                'desc_tip' => true,
                'description' => __('Enter the wholesale/cost price for this product. Used in financial reports.', 'wc-order-reports-pro'),
                'type' => 'number',
                'custom_attributes' => array(
                    'step' => '0.01',
                    'min' => '0'
                )
            )
        );
    }

    /**
     * Save wholesale price field for simple products using WC_Product CRUD
     */
    public function save_wholesale_price_field($post_id)
    {
        $product = wc_get_product($post_id);

        if (!$product) {
            return;
        }

        $wholesale_price = isset($_POST['_wholesale_price']) ? sanitize_text_field($_POST['_wholesale_price']) : '';
        $new_price = '';

        // Validate and sanitize
        if ($wholesale_price !== '') {
            $new_price = floatval($wholesale_price);
            if ($new_price < 0) {
                $new_price = 0;
            }
        }

        // LOCKING: Check if we need to lock old orders to the OLD price before updating
        $lock_enabled = get_option('wcorp_wholesale_lock_old_orders_on_manual_edit', 1);

        if ($lock_enabled) {
            $old_price = floatval($product->get_meta('_wholesale_price', true));
            // Only backfill if old price was valid (> 0) and price has changed
            // Also check if new price is different (float comparison)
            if ($old_price > 0 && abs($old_price - floatval($new_price)) > 0.000001) {
                self::backfill_snapshots($post_id, $old_price, 'product');
            }
        }

        // Use direct post meta update to avoid infinite loops with $product->save()
        update_post_meta($post_id, '_wholesale_price', $new_price);

        // Clear cache after update if cache manager exists
        if (class_exists('WC_Order_Reports_Cache_Manager')) {
            $cache = new WC_Order_Reports_Cache_Manager();
            $cache->clear_all();
        }
    }

    /**
     * Add wholesale price field to product variations
     */
    public function add_variation_wholesale_price_field($loop, $variation_data, $variation)
    {
        $variation_obj = wc_get_product($variation->ID);
        $wholesale_price = $variation_obj ? $variation_obj->get_meta('_wholesale_price', true) : '';

        ?>
        <div class="form-row form-row-full">
            <label>
                <?php echo esc_html__('Wholesale Price', 'wc-order-reports-pro'); ?>
                (<?php echo esc_html(get_woocommerce_currency_symbol()); ?>)
            </label>
            <input type="number" step="0.01" min="0" name="_wholesale_price[<?php echo esc_attr($loop); ?>]"
                id="_wholesale_price_<?php echo esc_attr($loop); ?>" value="<?php echo esc_attr($wholesale_price); ?>"
                placeholder="<?php echo esc_attr__('Wholesale/cost price', 'wc-order-reports-pro'); ?>" style="width: 100%;" />
            <span class="description">
                <?php echo esc_html__('Enter the wholesale/cost price for this variation. If empty, parent product wholesale price will be used.', 'wc-order-reports-pro'); ?>
            </span>
        </div>
        <?php
    }

    /**
     * Save wholesale price field for variations using WC_Product CRUD
     */
    public function save_variation_wholesale_price_field($variation_id, $loop)
    {
        $variation = wc_get_product($variation_id);

        if (!$variation) {
            return;
        }

        $new_price = '';

        if (isset($_POST['_wholesale_price'][$loop])) {
            $wholesale_price = sanitize_text_field($_POST['_wholesale_price'][$loop]);

            // Validate and sanitize
            if ($wholesale_price !== '') {
                $new_price = floatval($wholesale_price);
                if ($new_price < 0) {
                    $new_price = 0;
                }
            }
        }

        // LOCKING: Check if we need to lock old orders to the OLD price before updating
        $lock_enabled = get_option('wcorp_wholesale_lock_old_orders_on_manual_edit', 1);

        if ($lock_enabled) {
            $old_price = floatval($variation->get_meta('_wholesale_price', true));
            // Only backfill if old price was valid (> 0) and price has changed
            // Also check if new price is different (float comparison)
            if ($old_price > 0 && abs($old_price - floatval($new_price)) > 0.000001) {
                self::backfill_snapshots($variation_id, $old_price, 'variation');
            }
        }

        // Use direct post meta update to avoid infinite loops with $variation->save()
        update_post_meta($variation_id, '_wholesale_price', $new_price);

        // Clear cache is handled by main product save usually, but variations save via ajax sometimes
        if (defined('DOING_AJAX') && DOING_AJAX && class_exists('WC_Order_Reports_Cache_Manager')) {
            $cache = new WC_Order_Reports_Cache_Manager();
            $cache->clear_all();
        }
    }

    /**
     * Efficiently backfill missing snapshots for a product/variation
     * Used for "Locking" old orders (using old price) or CSV "Update Snaphots" (using new price)
     * 
     * @param int $target_id Product ID or Variation ID
     * @param float $price The wholesale price to snapshot
     * @param string $type 'product' or 'variation'
     * @param string $date_from Optional: Start date for orders (Y-m-d format)
     * @param string $date_to Optional: End date for orders (Y-m-d format)
     * @param bool $force_overwrite Optional: Force overwrite existing snapshots (default: false)
     * @return int Number of rows inserted/updated
     */
    public static function backfill_snapshots($target_id, $price, $type = 'product', $date_from = '', $date_to = '', $force_overwrite = false)
    {
        global $wpdb;

        if ($price <= 0) {
            return 0;
        }

        // Determine lookup meta key based on type
        $lookup_key = ($type === 'variation') ? '_variation_id' : '_product_id';

        // Get allowed statuses for lazy freeze/backfill
        $allowed_statuses = get_option('wcorp_wholesale_lazy_freeze_statuses', array('processing', 'completed'));

        if (empty($allowed_statuses)) {
            return 0;
        }

        // Build query args for wc_get_orders
        $query_args = array(
            'limit' => -1,
            'status' => $allowed_statuses,
            'return' => 'ids'
        );

        // Add date filter if provided
        if (!empty($date_from) || !empty($date_to)) {
            if (!empty($date_from) && !empty($date_to)) {
                // Both dates provided
                $query_args['date_created'] = $date_from . '...' . $date_to;
            } elseif (!empty($date_from)) {
                // Only from date
                $query_args['date_created'] = '>=' . $date_from;
            } elseif (!empty($date_to)) {
                // Only to date
                $query_args['date_created'] = '<=' . $date_to;
            }
        }

        // Get all matching orders
        $order_ids = wc_get_orders($query_args);

        if (empty($order_ids)) {
            return 0;
        }

        // Now find order items that match the product/variation
        $order_item_ids = array();

        foreach ($order_ids as $order_id) {
            // Get order items for this product/variation
            $items_sql = $wpdb->prepare("
                SELECT DISTINCT oim.order_item_id
                FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
                JOIN {$wpdb->prefix}woocommerce_order_items oi ON oi.order_item_id = oim.order_item_id
                WHERE oim.meta_key = %s
                    AND CAST(oim.meta_value AS UNSIGNED) = %d
                    AND oi.order_id = %d
            ", $lookup_key, $target_id, $order_id);

            $items = $wpdb->get_col($items_sql);

            if (!empty($items)) {
                foreach ($items as $item_id) {
                    // Check if we should include this item
                    if ($force_overwrite) {
                        // Include all items
                        $order_item_ids[] = $item_id;
                    } else {
                        // Only include items without existing snapshots
                        $existing = wc_get_order_item_meta($item_id, '_wholesale_price_at_purchase', true);
                        if ($existing === '' || $existing === null) {
                            $order_item_ids[] = $item_id;
                        }
                    }
                }
            }
        }

        if (empty($order_item_ids)) {
            return 0;
        }

        $updated = 0;

        // Process in chunks for performance/stability on large datasets
        $chunks = array_chunk($order_item_ids, 500);
        foreach ($chunks as $chunk) {
            foreach ($chunk as $order_item_id) {
                wc_update_order_item_meta($order_item_id, '_wholesale_price_at_purchase', floatval($price));
                $updated++;
            }

            // Light cache flush between chunks to keep memory in check
            if (function_exists('wp_cache_flush_group')) {
                wp_cache_flush_group('posts');
            }
        }

        return $updated;
    }

    /**
     * Get wholesale price for an order item
     * Handles variations with parent fallback
     */
    public static function get_item_wholesale_price($item)
    {
        $wholesale_price = 0;

        if (!is_object($item)) {
            return $wholesale_price;
        }

        // Get product from item
        $product = $item->get_product();

        if (!$product) {
            return $wholesale_price;
        }

        // For variations, try variation wholesale price first
        if ($product->is_type('variation')) {
            $variation_wholesale = $product->get_meta('_wholesale_price', true);

            if ($variation_wholesale !== '' && $variation_wholesale !== null) {
                $wholesale_price = floatval($variation_wholesale);
            } else {
                // Fallback to parent product wholesale price
                $parent_id = $product->get_parent_id();
                if ($parent_id) {
                    $parent_product = wc_get_product($parent_id);
                    if ($parent_product) {
                        $parent_wholesale = $parent_product->get_meta('_wholesale_price', true);
                        if ($parent_wholesale !== '' && $parent_wholesale !== null) {
                            $wholesale_price = floatval($parent_wholesale);
                        }
                    }
                }
            }
        } else {
            // Simple product
            $simple_wholesale = $product->get_meta('_wholesale_price', true);
            if ($simple_wholesale !== '' && $simple_wholesale !== null) {
                $wholesale_price = floatval($simple_wholesale);
            }
        }

        return $wholesale_price;
    }

    /**
     * Check if wholesale price should be frozen based on threshold setting
     * 
     * @param float $wholesale_price Wholesale price to check
     * @return bool True if price should be frozen
     */
    private static function should_freeze_wholesale_price($wholesale_price)
    {
        $min_value = floatval(get_option('wcorp_wholesale_lazy_freeze_min_value', 0));
        return $wholesale_price > $min_value;
    }

    /**
     * Save wholesale price at purchase time (checkout orders)
     * Hook: woocommerce_checkout_create_order_line_item
     * 
     * @param WC_Order_Item_Product $item Order item object
     * @param string $cart_item_key Cart item key
     * @param array $values Cart item values
     * @param WC_Order $order Order object
     */
    public function save_wholesale_price_at_purchase($item, $cart_item_key, $values, $order)
    {
        // Only save if not already set (preserve historical data)
        if ($item->meta_exists('_wholesale_price_at_purchase')) {
            return;
        }


        // Get wholesale price from current product meta
        $wholesale_price = self::get_item_wholesale_price($item);

        // Check setting: always save or only if > threshold
        $always_save = get_option('wcorp_wholesale_snapshot_new_orders_always', 1);

        if ($always_save) {
            // Always save snapshot (even if 0)
            $item->update_meta_data('_wholesale_price_at_purchase', floatval($wholesale_price));
            $item->save();
        } elseif (self::should_freeze_wholesale_price($wholesale_price)) {
            // Only save if price > threshold
            $item->update_meta_data('_wholesale_price_at_purchase', floatval($wholesale_price));
            $item->save();
        }
    }

    /**
     * Save wholesale price for manual/API orders
     * Hook: woocommerce_new_order_item
     * 
     * @param int $item_id Order item ID
     * @param WC_Order_Item $item Order item object
     * @param int $order_id Order ID
     */
    public function save_wholesale_price_for_manual_orders($item_id, $item, $order_id)
    {
        // Only process product line items
        if (!$item instanceof WC_Order_Item_Product) {
            return;
        }

        // Only save if not already set (preserve historical data)
        $existing = $item->get_meta('_wholesale_price_at_purchase', true);
        if ($existing !== '' && $existing !== null) {
            return; // Already saved, don't overwrite
        }

        // Get wholesale price from current product meta
        $wholesale_price = self::get_item_wholesale_price($item);

        // Check setting: always save or only if > threshold
        $always_save = get_option('wcorp_wholesale_snapshot_new_orders_always', 1);

        if ($always_save) {
            // Always save snapshot (even if 0)
            $item->update_meta_data('_wholesale_price_at_purchase', floatval($wholesale_price));
            $item->save();
        } elseif (self::should_freeze_wholesale_price($wholesale_price)) {
            // Only save if price > threshold
            $item->update_meta_data('_wholesale_price_at_purchase', floatval($wholesale_price));
            $item->save();
        }
    }

    /**
     * Get wholesale price for an order item with historical accuracy
     * Prioritizes saved purchase-time price, falls back to current product meta
     * Implements lazy freeze-on-read for old orders (admin-only, with threshold and status checks)
     * 
     * @param WC_Order_Item_Product $item Order item object
     * @return float Wholesale price
     */
    public static function get_item_wholesale_price_with_history($item)
    {
        if (!is_object($item)) {
            return 0;
        }

        // Check if snapshot exists using meta_exists() for reliable detection
        // This properly distinguishes missing meta key vs existing meta with 0/empty values
        $snapshot_exists = $item->meta_exists('_wholesale_price_at_purchase');

        if ($snapshot_exists) {
            // Snapshot exists - get the value
            $historical_price = $item->get_meta('_wholesale_price_at_purchase', true);

            if (is_numeric($historical_price)) {
                // Valid numeric snapshot - return it
                return floatval($historical_price);
            }
        }

        // No snapshot exists - get current product wholesale price
        $current_wholesale_price = self::get_item_wholesale_price($item);

        // Lazy freeze logic: save snapshot for old orders (only in admin context)
        if (!$snapshot_exists && is_admin()) {
            // Check if lazy freeze is enabled
            $lazy_freeze_enabled = get_option('wcorp_wholesale_lazy_freeze_enabled', 1);

            if ($lazy_freeze_enabled) {
                // Check if price meets threshold
                if (self::should_freeze_wholesale_price($current_wholesale_price)) {
                    // Check order status filter
                    $order = $item->get_order();
                    if ($order) {
                        $order_status = $order->get_status();
                        $allowed_statuses = get_option('wcorp_wholesale_lazy_freeze_statuses', array('processing', 'completed'));

                        if (in_array($order_status, $allowed_statuses, true)) {
                            // All conditions met - freeze the snapshot
                            $item->update_meta_data('_wholesale_price_at_purchase', floatval($current_wholesale_price));
                            $item->save();
                        }
                    }
                }
            }
        }

        // Return current wholesale price (whether frozen or not)
        return $current_wholesale_price;
    }

    /**
     * Add wholesale column header in admin order items table
     * Hook: woocommerce_admin_order_item_headers
     */
    public function add_wholesale_column_header()
    {
        ?>
        <th class="item_wholesale sortable" data-sort="float"><?php esc_html_e('Wholesale', 'wc-order-reports-pro'); ?></th>
        <?php
    }

    /**
     * Add wholesale column values for each order item
     * Hook: woocommerce_admin_order_item_values
     * 
     * @param WC_Product|null $_product Product object
     * @param WC_Order_Item_Product $item Order item object
     * @param int|null $item_id Order item ID
     */
    public function add_wholesale_column_values($_product, $item, $item_id)
    {
        // Only show for product line items
        if (!$item instanceof WC_Order_Item_Product) {
            echo '<td class="item_wholesale">&nbsp;</td>';
            return;
        }

        // Get wholesale price using historical method
        $wholesale_price = self::get_item_wholesale_price_with_history($item);
        $quantity = floatval($item->get_quantity());
        $wholesale_line_total = $wholesale_price * $quantity;

        ?>
        <td class="item_wholesale" data-sort-value="<?php echo esc_attr($wholesale_line_total); ?>">
            <div class="view">
                <?php
                // Show unit price and line total
                if ($wholesale_price > 0) {
                    echo '<span class="wholesale-unit">' . wc_price($wholesale_price) . '</span>';
                    echo '<br><small class="wholesale-total">' . sprintf(
                        /* translators: %s: wholesale line total */
                        esc_html__('Total: %s', 'wc-order-reports-pro'),
                        wc_price($wholesale_line_total)
                    ) . '</small>';
                } else {
                    echo '<span class="na">&ndash;</span>';
                }
                ?>
            </div>
        </td>
        <?php
    }

    /**
     * Add wholesale total row in order totals section
     * Hook: woocommerce_admin_order_totals_after_total
     * 
     * @param int $order_id Order ID
     */
    public function add_wholesale_total_row($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        // Calculate total wholesale amount for the order
        $wholesale_total = 0.0;
        $items = $order->get_items('line_item');

        foreach ($items as $item) {
            if (!$item instanceof WC_Order_Item_Product) {
                continue;
            }

            $wholesale_price = self::get_item_wholesale_price_with_history($item);
            $quantity = floatval($item->get_quantity());
            $wholesale_total += ($wholesale_price * $quantity);
        }

        ?>
        <tr>
            <td class="label"><?php esc_html_e('Wholesale Total:', 'wc-order-reports-pro'); ?></td>
            <td width="1%"></td>
            <td class="total">
                <?php echo wc_price($wholesale_total, array('currency' => $order->get_currency())); ?>
            </td>
        </tr>
        <?php
    }
}

// Initialize
new WC_Order_Reports_Wholesale_Price();
