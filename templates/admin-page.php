<?php
/**
 * Admin Dashboard Page Template
 *
 * @package WC_Order_Reports_Pro
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="wrap wcorp-admin-wrap">
    <h1 class="wcorp-main-title">
        <span class="dashicons dashicons-chart-area"></span>
        <?php esc_html_e('تقارير الطلبات المتقدمة', 'wc-order-reports-pro'); ?>
        <button class="button wcorp-print-btn" style="margin-left: 20px;" onclick="window.print(); return false;">
            <span class="dashicons dashicons-printer"></span>
            <?php esc_html_e('طباعة', 'wc-order-reports-pro'); ?>
        </button>
    </h1>

    <!-- Date Filter Section -->
    <div class="wcorp-filters-section">
        <form method="get" class="wcorp-date-filter" id="wcorpDateFilter">
            <input type="hidden" name="page" value="wc-order-reports-pro">
            
            <div class="wcorp-filter-group">
                <label><?php esc_html_e('From:', 'wc-order-reports-pro'); ?></label>
                <input type="date" name="from" id="wcorpFromDate" value="<?php echo esc_attr($current_from); ?>">
            </div>

            <div class="wcorp-filter-group">
                <label><?php esc_html_e('To:', 'wc-order-reports-pro'); ?></label>
                <input type="date" name="to" id="wcorpToDate" value="<?php echo esc_attr($current_to); ?>">
            </div>

            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-search"></span>
                <?php esc_html_e('Filter', 'wc-order-reports-pro'); ?>
            </button>

            <?php if ($this->plugin->is_feature_enabled('date_presets')) : ?>
            <div class="wcorp-date-presets">
                <?php foreach ($date_presets as $key => $preset) : ?>
                    <button type="button" class="button wcorp-preset-btn" data-from="<?php echo esc_attr($preset['from']); ?>" data-to="<?php echo esc_attr($preset['to']); ?>">
                        <?php echo esc_html($preset['label']); ?>
                    </button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </form>

        <!-- Export Buttons -->
        <div class="wcorp-export-section">
            <?php if ($this->plugin->is_feature_enabled('export_csv')) : ?>
            <a href="<?php echo esc_url($this->get_export_url('csv', $current_from, $current_to)); ?>" class="button">
                <span class="dashicons dashicons-download"></span>
                <?php esc_html_e('Export CSV', 'wc-order-reports-pro'); ?>
            </a>
            <?php endif; ?>

            <?php if ($this->plugin->is_feature_enabled('export_excel')) : ?>
            <a href="<?php echo esc_url($this->get_export_url('excel', $current_from, $current_to)); ?>" class="button">
                <span class="dashicons dashicons-media-spreadsheet"></span>
                <?php esc_html_e('Export Excel', 'wc-order-reports-pro'); ?>
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Reports Container -->
    <div class="wcorp-reports-container" id="wcorpReportsContainer">
        
        <?php if ($this->plugin->is_feature_enabled('daily_report')) : ?>
        <div class="wcorp-report-section" id="dailyStatusReport">
            <div class="wcorp-report-header">
                <h2>
                    <span class="dashicons dashicons-calendar-alt"></span>
                    <?php esc_html_e('تقرير الحالة اليومية', 'wc-order-reports-pro'); ?>
                </h2>
                <button class="button wcorp-refresh-btn" data-report="daily_status">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
            <div class="wcorp-report-content" data-report-type="daily_status">
                <div class="wcorp-loading"><?php esc_html_e('Loading...', 'wc-order-reports-pro'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($this->plugin->is_feature_enabled('bosta_report')) : ?>
        <div class="wcorp-report-section" id="bostaReport">
            <div class="wcorp-report-header">
                <h2>
                    <span class="dashicons dashicons-admin-site"></span>
                    <?php esc_html_e('تقرير أداء بوسطة', 'wc-order-reports-pro'); ?>
                </h2>
                <button class="button wcorp-refresh-btn" data-report="bosta">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
            <div class="wcorp-report-content" data-report-type="bosta">
                <div class="wcorp-loading"><?php esc_html_e('Loading...', 'wc-order-reports-pro'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($this->plugin->is_feature_enabled('cod_report')) : ?>
        <div class="wcorp-report-section" id="codReport">
            <div class="wcorp-report-header">
                <h2>
                    <span class="dashicons dashicons-money-alt"></span>
                    <?php esc_html_e('التقرير المالي COD', 'wc-order-reports-pro'); ?>
                </h2>
                <button class="button wcorp-refresh-btn" data-report="cod">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
            <div class="wcorp-report-content" data-report-type="cod">
                <div class="wcorp-loading"><?php esc_html_e('Loading...', 'wc-order-reports-pro'); ?></div>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($this->plugin->is_feature_enabled('agent_report')) : ?>
        <div class="wcorp-report-section" id="agentReport">
            <div class="wcorp-report-header">
                <h2>
                    <span class="dashicons dashicons-groups"></span>
                    <?php esc_html_e('مقارنة أداء المندوب مع بوسطة', 'wc-order-reports-pro'); ?>
                </h2>
                <button class="button wcorp-refresh-btn" data-report="agent">
                    <span class="dashicons dashicons-update"></span>
                </button>
            </div>
            <div class="wcorp-report-content" data-report-type="agent">
                <div class="wcorp-loading"><?php esc_html_e('Loading...', 'wc-order-reports-pro'); ?></div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php if ($this->plugin->is_feature_enabled('caching')) : ?>
    <div class="wcorp-cache-info">
        <button type="button" class="button" id="wcorpClearCache">
            <span class="dashicons dashicons-trash"></span>
            <?php esc_html_e('Clear Cache', 'wc-order-reports-pro'); ?>
        </button>
        <span class="wcorp-cache-note"><?php esc_html_e('Reports are cached for better performance', 'wc-order-reports-pro'); ?></span>
    </div>
    <?php endif; ?>

</div>
