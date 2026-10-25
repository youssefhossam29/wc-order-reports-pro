<?php
/**
 * Settings Page
 * Configuration options for the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Reports_Settings
{

    public function __construct()
    {
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function register_settings()
    {
        register_setting('wcorp_settings', 'wcorp_email_recipients');
        register_setting('wcorp_settings', 'wcorp_email_report_time');
        register_setting('wcorp_settings', 'wcorp_notification_recipients');
        register_setting('wcorp_settings', 'wcorp_enable_daily_email');
        register_setting('wcorp_settings', 'wcorp_enable_delay_alerts');

        // Wholesale snapshot settings
        register_setting('wcorp_settings', 'wcorp_wholesale_lazy_freeze_enabled');
        register_setting('wcorp_settings', 'wcorp_wholesale_lazy_freeze_min_value');
        register_setting('wcorp_settings', 'wcorp_wholesale_lock_old_orders_on_manual_edit'); // New setting
        register_setting('wcorp_settings', 'wcorp_wholesale_snapshot_new_orders_always');
        register_setting('wcorp_settings', 'wcorp_wholesale_lazy_freeze_statuses');
    }

    public static function render_page()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        // Save settings
        if (isset($_POST['wcorp_save_settings'])) {
            check_admin_referer('wcorp_settings_save');

            update_option('wcorp_email_recipients', self::sanitize_email_list($_POST['wcorp_email_recipients'] ?? ''));
            update_option('wcorp_email_report_time', sanitize_text_field($_POST['wcorp_email_report_time'] ?? '09:00'));
            update_option('wcorp_notification_recipients', self::sanitize_email_list($_POST['wcorp_notification_recipients'] ?? ''));
            update_option('wcorp_enable_daily_email', isset($_POST['wcorp_enable_daily_email']) ? 1 : 0);
            update_option('wcorp_enable_delay_alerts', isset($_POST['wcorp_enable_delay_alerts']) ? 1 : 0);

            // Wholesale snapshot settings
            update_option('wcorp_wholesale_lazy_freeze_enabled', isset($_POST['wcorp_wholesale_lazy_freeze_enabled']) ? 1 : 0);
            update_option('wcorp_wholesale_lazy_freeze_min_value', floatval($_POST['wcorp_wholesale_lazy_freeze_min_value'] ?? 0));
            update_option('wcorp_wholesale_lock_old_orders_on_manual_edit', isset($_POST['wcorp_wholesale_lock_old_orders_on_manual_edit']) ? 1 : 0);
            update_option('wcorp_wholesale_snapshot_new_orders_always', isset($_POST['wcorp_wholesale_snapshot_new_orders_always']) ? 1 : 0);
            update_option('wcorp_wholesale_lazy_freeze_statuses', isset($_POST['wcorp_wholesale_lazy_freeze_statuses']) ? array_map('sanitize_text_field', $_POST['wcorp_wholesale_lazy_freeze_statuses']) : array('processing', 'completed'));

            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved successfully.', 'wc-order-reports-pro') . '</p></div>';
        }

        $email_recipients = get_option('wcorp_email_recipients', array(get_option('admin_email')));
        $email_time = get_option('wcorp_email_report_time', '09:00');
        $notification_recipients = get_option('wcorp_notification_recipients', array(get_option('admin_email')));
        $enable_daily_email = get_option('wcorp_enable_daily_email', 1);
        $enable_delay_alerts = get_option('wcorp_enable_delay_alerts', 1);

        // Wholesale snapshot settings
        $wholesale_lazy_freeze_enabled = get_option('wcorp_wholesale_lazy_freeze_enabled', 1);
        $wholesale_lazy_freeze_min_value = get_option('wcorp_wholesale_lazy_freeze_min_value', 0);
        $wholesale_snapshot_new_orders_always = get_option('wcorp_wholesale_snapshot_new_orders_always', 1);
        $wholesale_lazy_freeze_statuses = get_option('wcorp_wholesale_lazy_freeze_statuses', array('processing', 'completed'));
        ?>

        <div class="wrap">
            <h1><?php esc_html_e('WC Order Reports Pro - Settings', 'wc-order-reports-pro'); ?></h1>

            <form method="post" action="">
                <?php wp_nonce_field('wcorp_settings_save'); ?>

                <table class="form-table">
                    <tbody>
                        <!-- Email Reports -->
                        <tr>
                            <th colspan="2">
                                <h2><?php esc_html_e('Email Reports', 'wc-order-reports-pro'); ?></h2>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_enable_daily_email"><?php esc_html_e('Enable Daily Reports', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="wcorp_enable_daily_email" id="wcorp_enable_daily_email" value="1"
                                    <?php checked($enable_daily_email, 1); ?>>
                                <p class="description">
                                    <?php esc_html_e('Send daily summary reports via email', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_email_recipients"><?php esc_html_e('Email Recipients', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <textarea name="wcorp_email_recipients" id="wcorp_email_recipients" rows="3"
                                    class="large-text"><?php echo esc_textarea(implode("\n", $email_recipients)); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Enter one email address per line', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_email_report_time"><?php esc_html_e('Report Time', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="time" name="wcorp_email_report_time" id="wcorp_email_report_time"
                                    value="<?php echo esc_attr($email_time); ?>">
                                <p class="description">
                                    <?php esc_html_e('Daily reports will be sent at this time', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Notifications -->
                        <tr>
                            <th colspan="2">
                                <h2><?php esc_html_e('Performance Alerts', 'wc-order-reports-pro'); ?></h2>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_enable_delay_alerts"><?php esc_html_e('Enable Delay Alerts', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="wcorp_enable_delay_alerts" id="wcorp_enable_delay_alerts" value="1"
                                    <?php checked($enable_delay_alerts, 1); ?>>
                                <p class="description">
                                    <?php esc_html_e('Send alerts when orders are delayed', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_notification_recipients"><?php esc_html_e('Alert Recipients', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <textarea name="wcorp_notification_recipients" id="wcorp_notification_recipients" rows="3"
                                    class="large-text"><?php echo esc_textarea(implode("\n", $notification_recipients)); ?></textarea>
                                <p class="description">
                                    <?php esc_html_e('Enter one email address per line', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Wholesale Price Snapshots -->
                        <tr>
                            <th colspan="2">
                                <h2><?php esc_html_e('Wholesale Price Snapshots', 'wc-order-reports-pro'); ?></h2>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_wholesale_lazy_freeze_enabled"><?php esc_html_e('Enable Lazy Freeze for Old Orders', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="wcorp_wholesale_lazy_freeze_enabled"
                                    id="wcorp_wholesale_lazy_freeze_enabled" value="1" <?php checked($wholesale_lazy_freeze_enabled, 1); ?>>
                                <p class="description">
                                    <?php esc_html_e('When enabled, old orders without snapshots will save the current wholesale price when first accessed in admin (only if price > minimum value)', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_wholesale_lazy_freeze_min_value"><?php esc_html_e('Minimum Wholesale Value to Freeze', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number" step="0.01" min="0" name="wcorp_wholesale_lazy_freeze_min_value"
                                    id="wcorp_wholesale_lazy_freeze_min_value"
                                    value="<?php echo esc_attr($wholesale_lazy_freeze_min_value); ?>" class="small-text">
                                <p class="description">
                                    <?php esc_html_e('Only freeze snapshots if wholesale price is greater than this value (default: 0)', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_wholesale_lock_old_orders_on_manual_edit"><?php esc_html_e('Lock Old Orders on Manual Edit', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="wcorp_wholesale_lock_old_orders_on_manual_edit"
                                    id="wcorp_wholesale_lock_old_orders_on_manual_edit" value="1" <?php checked(get_option('wcorp_wholesale_lock_old_orders_on_manual_edit', 1), 1); ?>>
                                <p class="description">
                                    <?php esc_html_e('When manually updating a product wholesale price, lock existing orders to the old price (by creating a snapshot if missing).', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_wholesale_snapshot_new_orders_always"><?php esc_html_e('Always Store Wholesale Snapshot for New Orders', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <input type="checkbox" name="wcorp_wholesale_snapshot_new_orders_always"
                                    id="wcorp_wholesale_snapshot_new_orders_always" value="1" <?php checked($wholesale_snapshot_new_orders_always, 1); ?>>
                                <p class="description">
                                    <?php esc_html_e('When enabled, always save wholesale price for new orders (even if 0). When disabled, only save if price > minimum value', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label
                                    for="wcorp_wholesale_lazy_freeze_statuses"><?php esc_html_e('Allowed Order Statuses for Lazy Freeze', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <?php
                                $order_statuses = wc_get_order_statuses();
                                foreach ($order_statuses as $status_key => $status_label) {
                                    $status_slug = str_replace('wc-', '', $status_key);
                                    $checked = in_array($status_slug, $wholesale_lazy_freeze_statuses) ? 'checked' : '';
                                    echo '<label style="display: block; margin-bottom: 5px;">';
                                    echo '<input type="checkbox" name="wcorp_wholesale_lazy_freeze_statuses[]" value="' . esc_attr($status_slug) . '" ' . $checked . '> ';
                                    echo esc_html($status_label);
                                    echo '</label>';
                                }
                                ?>
                                <p class="description">
                                    <?php esc_html_e('Lazy freeze will only run for orders with these statuses (prevents freezing cancelled/failed orders)', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <!-- Cache -->
                        <tr>
                            <th colspan="2">
                                <h2><?php esc_html_e('Performance', 'wc-order-reports-pro'); ?></h2>
                            </th>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label><?php esc_html_e('Cache Management', 'wc-order-reports-pro'); ?></label>
                            </th>
                            <td>
                                <button type="button" class="button" id="wcorpClearCacheSettings">
                                    <?php esc_html_e('Clear All Cache', 'wc-order-reports-pro'); ?>
                                </button>
                                <p class="description">
                                    <?php esc_html_e('Reports are cached for 1 hour to improve performance', 'wc-order-reports-pro'); ?>
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <p class="submit">
                    <input type="submit" name="wcorp_save_settings" class="button button-primary"
                        value="<?php esc_attr_e('Save Settings', 'wc-order-reports-pro'); ?>">
                </p>
            </form>
        </div>

        <script>
            jQuery(document).ready(function ($) {
                $('#wcorpClearCacheSettings').on('click', function () {
                    if (confirm('<?php esc_html_e('Are you sure you want to clear all cached reports?', 'wc-order-reports-pro'); ?>')) {
                        $.post(ajaxurl, {
                            action: 'wcorp_clear_cache',
                            nonce: '<?php echo wp_create_nonce('wcorp_clear_cache'); ?>'
                        }, function (response) {
                            alert('<?php esc_html_e('Cache cleared successfully', 'wc-order-reports-pro'); ?>');
                        });
                    }
                });
            });
        </script>
        <?php
    }

    private static function sanitize_email_list($input)
    {
        $emails = explode("\n", $input);
        $sanitized = array();

        foreach ($emails as $email) {
            $email = trim($email);
            if (is_email($email)) {
                $sanitized[] = sanitize_email($email);
            }
        }

        return $sanitized;
    }
}

// Note: Class is initialized from main plugin file when settings_page feature is enabled
