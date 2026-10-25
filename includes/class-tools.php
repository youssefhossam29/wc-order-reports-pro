<?php
/**
 * Tools Page
 * Import/Export and maintenance tools
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Reports_Tools
{

    /**
     * Initialize hooks
     */
    public static function init()
    {
        add_action('wp_ajax_wcorp_import_upload_csv', array(__CLASS__, 'ajax_upload_csv'));
        add_action('wp_ajax_wcorp_import_process_batch', array(__CLASS__, 'ajax_process_batch'));
    }

    /**
     * Render the tools page
     */
    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'wc-order-reports-pro'));
        }
        ?>
        <div class="wrap wcorp-admin-wrap">
            <h1 class="wcorp-main-title">
                <span class="dashicons dashicons-hammer"></span>
                <?php esc_html_e('Tools', 'wc-order-reports-pro'); ?>
            </h1>

            <div class="wcorp-tools-container">
                <!-- Wholesale Price Import Tool -->
                <div class="wcorp-tool-card">
                    <h2>
                        <?php esc_html_e('Import Wholesale Prices', 'wc-order-reports-pro'); ?>
                    </h2>
                    <p class="description">
                        <?php esc_html_e('Upload a CSV file to update product wholesale prices in bulk.', 'wc-order-reports-pro'); ?><br>
                        <?php esc_html_e('Required columns: ', 'wc-order-reports-pro'); ?><code>sku</code>,
                        <code>cost_of_goods</code>
                    </p>

                    <form id="wcorp-import-form" enctype="multipart/form-data">
                        <div class="wcorp-file-upload">
                            <input type="file" name="wcorp_import_file" id="wcorp_import_file" accept=".csv" required>
                        </div>

                        <div class="wcorp-import-options" style="margin-top: 15px;">
                            <label for="wcorp_overwrite_existing">
                                <input type="checkbox" name="wcorp_overwrite_existing" id="wcorp_overwrite_existing" value="1">
                                <?php esc_html_e('Overwrite existing wholesale prices (if different)', 'wc-order-reports-pro'); ?>
                            </label>
                            <p class="description" style="margin-top: 5px;">
                                <?php esc_html_e('If unchecked, products that already have a wholesale price set will be skipped.', 'wc-order-reports-pro'); ?>
                            </p>

                            <p style="margin-top: 15px;">
                                <label for="wcorp_update_old_snapshots">
                                    <input type="checkbox" name="wcorp_update_old_snapshots" id="wcorp_update_old_snapshots"
                                        value="1">
                                    <?php esc_html_e('Update old orders wholesale snapshots (backfill missing only)', 'wc-order-reports-pro'); ?>
                                </label>
                                <br>
                                <span class="description">
                                    <?php esc_html_e('If checked, this will fill in missing wholesale price snapshots on old orders using the imported price. Existing snapshots are NEVER overwritten.', 'wc-order-reports-pro'); ?>
                                </span>
                            </p>

                            <p style="margin-top: 15px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
                                <label for="wcorp_force_overwrite_snapshots">
                                    <input type="checkbox" name="wcorp_force_overwrite_snapshots" id="wcorp_force_overwrite_snapshots"
                                        value="1">
                                    <strong><?php esc_html_e('⚠️ Force overwrite existing snapshots', 'wc-order-reports-pro'); ?></strong>
                                </label>
                                <br>
                                <span class="description" style="color: #856404;">
                                    <?php esc_html_e('⚠️ تحذير: هذا الخيار سيستبدل أسعار الجملة الموجودة في الأوردرات القديمة. استخدمه فقط إذا كنت متأكداً من أن الأسعار الحالية خاطئة ويجب تصحيحها.', 'wc-order-reports-pro'); ?>
                                </span>
                            </p>

                            <div id="wcorp_snapshot_date_range" style="margin-top: 15px; padding: 15px; background: #f0f0f1; border-radius: 4px; display: none;">
                                <p style="margin: 0 0 10px 0; font-weight: 600;">
                                    <?php esc_html_e('تحديد نطاق تاريخ الأوردرات للتحديث:', 'wc-order-reports-pro'); ?>
                                </p>
                                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                                    <div>
                                        <label for="wcorp_snapshot_date_from" style="display: block; margin-bottom: 5px;">
                                            <?php esc_html_e('من تاريخ:', 'wc-order-reports-pro'); ?>
                                        </label>
                                        <input type="date" name="wcorp_snapshot_date_from" id="wcorp_snapshot_date_from" 
                                            style="padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px;">
                                    </div>
                                    <div>
                                        <label for="wcorp_snapshot_date_to" style="display: block; margin-bottom: 5px;">
                                            <?php esc_html_e('إلى تاريخ:', 'wc-order-reports-pro'); ?>
                                        </label>
                                        <input type="date" name="wcorp_snapshot_date_to" id="wcorp_snapshot_date_to" 
                                            style="padding: 6px 10px; border: 1px solid #8c8f94; border-radius: 4px;">
                                    </div>
                                    <div style="flex: 1;">
                                        <span class="description" style="display: block; margin-top: 20px;">
                                            <?php esc_html_e('اترك فارغاً لتحديث جميع الأوردرات', 'wc-order-reports-pro'); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <script>
                        jQuery(document).ready(function($) {
                            $('#wcorp_update_old_snapshots').on('change', function() {
                                if ($(this).is(':checked')) {
                                    $('#wcorp_snapshot_date_range').slideDown();
                                } else {
                                    $('#wcorp_snapshot_date_range').slideUp();
                                }
                            });
                        });
                        </script>

                        <div class="wcorp-progress-section" style="display:none; margin-top: 15px;">
                            <div class="wcorp-progress-bar-bg"
                                style="background:#f0f0f1; height:20px; border-radius:3px; overflow:hidden;">
                                <div class="wcorp-progress-bar-fill"
                                    style="width:0%; background:#2271b1; height:100%; transition: width 0.3s;"></div>
                            </div>
                            <div class="wcorp-progress-stats" style="margin-top:5px; font-size:13px; color:#646970;">
                                <span class="processed">0</span> processed |
                                <span class="updated" style="color:#00a32a">0</span> updated |
                                <span class="skipped" style="color:#d63638">0</span> skipped
                            </div>
                            <div class="wcorp-current-status" style="margin-top:5px; font-weight:500;"></div>
                        </div>

                        <div class="wcorp-tool-actions" style="margin-top: 15px;">
                            <button type="submit" class="button button-primary" id="wcorp-start-import">
                                <?php esc_html_e('Import Wholesale Prices', 'wc-order-reports-pro'); ?>
                            </button>
                            <button type="button" class="button button-secondary" id="wcorp-cancel-import"
                                style="display:none;">
                                <?php esc_html_e('Cancel', 'wc-order-reports-pro'); ?>
                            </button>
                        </div>
                    </form>

                    <div id="wcorp-import-log"
                        style="margin-top: 20px; max-height: 200px; overflow-y: auto; background: #fff; padding: 10px; border: 1px solid #ccd0d4; display:none;">
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Handle CSV upload - Step 1
     */
    public static function ajax_upload_csv()
    {
        check_ajax_referer('wcorp_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        if (empty($_FILES['file'])) {
            wp_send_json_error(array('message' => 'No file uploaded'));
        }

        $file = $_FILES['file'];

        // Validate file type
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'csv') {
            wp_send_json_error(array('message' => 'Invalid file type. Please upload a CSV file.'));
        }

        // Upload to temp directory
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/wcorp_temp';

        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }

        // Add index.php / .htaccess to protect temp dir
        if (!file_exists($temp_dir . '/index.php')) {
            file_put_contents($temp_dir . '/index.php', '<?php // Silence is golden');
        }

        $filename = 'import_' . time() . '_' . sanitize_file_name($file['name']);
        $file_path = $temp_dir . '/' . $filename;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            // Count total rows for progress bar
            $total_rows = 0;
            $handle = fopen($file_path, 'r');
            if ($handle) {
                while (($line = fgetcsv($handle)) !== false) {
                    $total_rows++;
                }
                fclose($handle);
            }
            // Subtract header row
            $total_rows = max(0, $total_rows - 1);

            wp_send_json_success(array(
                'file_path' => $file_path,
                'total_rows' => $total_rows,
                'file_url' => $upload_dir['baseurl'] . '/wcorp_temp/' . $filename // mostly for debug if needed
            ));
        } else {
            wp_send_json_error(array('message' => 'Failed to move uploaded file.'));
        }
    }

    /**
     * Process Batch - Step 2
     */
    public static function ajax_process_batch()
    {
        check_ajax_referer('wcorp_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $file_path = isset($_POST['file_path']) ? sanitize_text_field($_POST['file_path']) : '';
        // Relaxed boolean check (handles "true", "1", 1, true)
        $overwrite = isset($_POST['overwrite']) && filter_var($_POST['overwrite'], FILTER_VALIDATE_BOOLEAN);
        $update_snapshots = isset($_POST['update_snapshots']) && filter_var($_POST['update_snapshots'], FILTER_VALIDATE_BOOLEAN);
        $force_overwrite_snapshots = isset($_POST['force_overwrite_snapshots']) && filter_var($_POST['force_overwrite_snapshots'], FILTER_VALIDATE_BOOLEAN);
        
        // Get date range for snapshot updates
        $snapshot_date_from = isset($_POST['snapshot_date_from']) ? sanitize_text_field($_POST['snapshot_date_from']) : '';
        $snapshot_date_to = isset($_POST['snapshot_date_to']) ? sanitize_text_field($_POST['snapshot_date_to']) : '';

        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;

        if (!file_exists($file_path)) {
            wp_send_json_error(array('message' => 'File not found. Please upload again.'));
        }

        $handle = fopen($file_path, 'r');
        if (!$handle) {
            wp_send_json_error(array('message' => 'Could not open file.'));
        }

        // Find header row and map columns
        $header = fgetcsv($handle);
        $header = array_map('strtolower', array_map('trim', $header));

        $sku_index = array_search('sku', $header);
        $cost_index = array_search('cost_of_goods', $header);

        if ($sku_index === false || $cost_index === false) {
            fclose($handle);
            wp_send_json_error(array('message' => 'Invalid CSV format. Header row must contain "sku" and "cost_of_goods" columns.'));
        }

        // Move to current offset
        // Note: We already read header, so row count starts at 1. 
        // We want to skip $offset rows after header.
        for ($i = 0; $i < $offset; $i++) {
            if (fgetcsv($handle) === false) {
                break;
            }
        }

        $processed = 0;
        $updated = 0;
        $skipped = 0;
        $log = array();
        $done = false;

        // Debug log for first batch
        if ($offset === 0) {
            $log[] = "DEBUG: Overwrite flag: " . ($overwrite ? 'TRUE' : 'FALSE');
            $log[] = "DEBUG: Update Snapshots flag: " . ($update_snapshots ? 'TRUE' : 'FALSE');
            $log[] = "DEBUG: Force Overwrite Snapshots flag: " . ($force_overwrite_snapshots ? 'TRUE' : 'FALSE');
            if ($update_snapshots) {
                $log[] = "DEBUG: Snapshot Date From: " . ($snapshot_date_from ? $snapshot_date_from : 'ALL');
                $log[] = "DEBUG: Snapshot Date To: " . ($snapshot_date_to ? $snapshot_date_to : 'ALL');
            }
        }

        while ($processed < $limit && ($row = fgetcsv($handle)) !== false) {
            $processed++;

            $sku = isset($row[$sku_index]) ? trim($row[$sku_index]) : '';
            $raw_cost = isset($row[$cost_index]) ? trim($row[$cost_index]) : '';

            if (empty($sku)) {
                $skipped++;
                continue;
            }

            // --- ROBUST NUMBER PARSING WITH DEBUGGING ---
            $clean_cost = preg_replace('/\s+/', '', $raw_cost);
            $debug_log = "DEBUG: SKU {$sku} | Raw: '{$raw_cost}' | Clean: '{$clean_cost}'";

            // 1. Initial heuristic for mixed separators
            if (strpos($clean_cost, ',') !== false) {
                if (strpos($clean_cost, '.') === false) {
                    // Only commas: 1,160 or 1,50
                    // Assume comma is dot first (standardize to 1.160 or 1.50)
                    $clean_cost = str_replace(',', '.', $clean_cost);
                    $debug_log .= " -> Commas->Dots: '{$clean_cost}'";
                } else {
                    // Mixed
                    $last_comma = strrpos($clean_cost, ',');
                    $last_dot = strrpos($clean_cost, '.');
                    if ($last_comma > $last_dot) { // 1.160,50
                        $clean_cost = str_replace('.', '', $clean_cost);
                        $clean_cost = str_replace(',', '.', $clean_cost);
                        $debug_log .= " -> EU Mixed: '{$clean_cost}'";
                    } else { // 1,160.50
                        $clean_cost = str_replace(',', '', $clean_cost);
                        $debug_log .= " -> US Mixed: '{$clean_cost}'";
                    }
                }
            }

            // 2. Regex corrections for specific formats
            // Check for format 1.160 (EU integer - Dot Thousands)
            // MUST be 1-3 digits, followed by groups of exactly 3 digits separated by dot
            // e.g. 1.160 or 1.000.000
            if (preg_match('/^[0-9]{1,3}(\.[0-9]{3})+$/', $clean_cost)) {
                $clean_cost = str_replace('.', '', $clean_cost);
                $debug_log .= " -> Matched Dot Thousands (removed dots): '{$clean_cost}'";
            }
            // Check for format 1,160.50 (US/UK) - already handled by mixed check usually, but if mixed check failed
            elseif (preg_match('/^[0-9]{1,3}(,[0-9]{3})*\.[0-9]+$/', $clean_cost)) {
                $clean_cost = str_replace(',', '', $clean_cost);
                $debug_log .= " -> Matched US Decimal: '{$clean_cost}'";
            }
            // Check for format 1,160 (US/UK integer)
            elseif (preg_match('/^[0-9]{1,3}(,[0-9]{3})+$/', $clean_cost)) {
                $clean_cost = str_replace(',', '', $clean_cost);
                $debug_log .= " -> Matched Comma Thousands: '{$clean_cost}'";
            }

            // 3. Fallback / Final Numeric Check
            if (!is_numeric($clean_cost)) {
                // Try brute force sanitation if still not numeric
                $maybe = preg_replace('/[^0-9.,]/', '', $raw_cost);
                // Guess based on last separator
                if (strrpos($maybe, ',') !== false && (strrpos($maybe, '.') === false || strrpos($maybe, ',') > strrpos($maybe, '.'))) {
                    $maybe = str_replace('.', '', $maybe);
                    $maybe = str_replace(',', '.', $maybe);
                } else {
                    $maybe = str_replace(',', '', $maybe);
                }
                if (is_numeric($maybe)) {
                    $clean_cost = $maybe;
                    $debug_log .= " -> Fallback Sanitation Used: '{$clean_cost}'";
                } else {
                    $debug_log .= " -> FAILED parsing";
                    $log[] = $debug_log;
                    $skipped++;
                    continue;
                }
            }

            $cost = floatval($clean_cost);
            $debug_log .= " -> Final Float: {$cost}";

            // Log useful debug info for first few items or if unexpected small value for large raw input
            if ($processed <= 5 || ($cost < 10 && strlen($raw_cost) > 3)) {
                $log[] = $debug_log;
            }

            // Find product
            $product_id = wc_get_product_id_by_sku($sku);

            if ($product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $existing_price = $product->get_meta('_wholesale_price', true);

                    // Update if:
                    // 1. Overwrite is TRUE AND cost > 0
                    // 2. OR Existing price is empty AND cost > 0
                    // 3. AND (Implicitly) New cost is different (preserved from original logic logic idea but simplified here)

                    $should_update = false;
                    if ($cost > 0) {
                        if ($existing_price === '' || $existing_price === null) {
                            $should_update = true;
                        } elseif ($overwrite) {
                            // Only update if actually different to save DB writes
                            if (abs(floatval($existing_price) - $cost) > 0.001) {
                                $should_update = true;
                            }
                        }
                    }

                    if ($should_update) {
                        $product->update_meta_data('_wholesale_price', $cost);
                        $product->save();
                        $updated++;
                        $debug_log .= " -> UPDATED (Old: " . ($existing_price !== '' ? $existing_price : 'None') . ")";

                        // --- BACKFILL SNAPSHOTS IF ENABLED ---
                        if ($update_snapshots) {
                            $type = $product->is_type('variation') ? 'variation' : 'product';
                            // Call helper from includes/class-wholesale-price.php
                            // Assuming class is autoloaded or available
                            if (class_exists('WC_Order_Reports_Wholesale_Price')) {
                                $rows = WC_Order_Reports_Wholesale_Price::backfill_snapshots(
                                    $product_id, 
                                    $cost, 
                                    $type,
                                    $snapshot_date_from,
                                    $snapshot_date_to,
                                    $force_overwrite_snapshots
                                );
                                if ($rows > 0) {
                                    $debug_log .= " -> Backfilled {$rows} snapshots";
                                }
                            }
                        }

                    } else {
                        $debug_log .= " -> SKIPPED (Condition: Exists/NoOverwrite or Same Price)";

                        // Even if we skip updating product meta, we might still want to backfill snapshots 
                        // if the user requested it and we have a valid price in the CSV matching the product?
                        // USER REQUEST: "Add an option ... to backfill missing wholesale price snapshots ... using the IMPORTED price"
                        // If we didn't update the product because of overwrite rules, we can still update snapshots if allowed.
                        // Let's allow it if cost > 0. 

                        if ($cost > 0 && $update_snapshots) {
                            $type = $product->is_type('variation') ? 'variation' : 'product';
                            if (class_exists('WC_Order_Reports_Wholesale_Price')) {
                                $rows = WC_Order_Reports_Wholesale_Price::backfill_snapshots(
                                    $product_id, 
                                    $cost, 
                                    $type,
                                    $snapshot_date_from,
                                    $snapshot_date_to,
                                    $force_overwrite_snapshots
                                );
                                if ($rows > 0) {
                                    $debug_log .= " -> Backfilled {$rows} snapshots (Product skipped)";
                                }
                            }
                        }
                        $skipped++;
                    }
                } else {
                    $debug_log .= " -> Product object not found";
                    $skipped++;
                }
            } else {
                $debug_log .= " -> Product ID lookup failed";
                $skipped++;
            }

            $log[] = $debug_log;
        }

        if (feof($handle)) {
            $done = true;
            // Clear report cache if done
            if (class_exists('WC_Order_Reports_Cache_Manager')) {
                $cache = new WC_Order_Reports_Cache_Manager();
                $cache->clear_all();
            }
            // Cleanup file
            fclose($handle);
            @unlink($file_path);
        } else {
            fclose($handle);
        }

        wp_send_json_success(array(
            'processed' => $processed,
            'updated' => $updated,
            'skipped' => $skipped,
            'done' => $done,
            'new_offset' => $offset + $processed,
            'log' => $log,
            'overwrite' => $overwrite, // Pass back to maintain state
            'update_snapshots' => $update_snapshots // Pass back to maintain state
        ));
    }
}
