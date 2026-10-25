/**
 * WC Order Reports Pro - Admin JavaScript
 * Handles AJAX loading, charts, and interactivity
 */

(function ($) {
    'use strict';

    const WCOrderReportsPro = {

        charts: {},

        init: function () {
            this.bindEvents();
            this.bindKeyboardShortcuts();
            this.addTooltips();
            this.enhanceRefreshButtons();
            this.enableAutoRefresh();
            this.addPrintSupport();
            this.initImportTool();
            this.loadReports();
        },

        bindEvents: function () {
            // Date preset buttons
            if ($('.wcorp-preset-btn').length) {
                $('.wcorp-preset-btn').on('click', this.handlePresetClick.bind(this));
            }

            // Refresh buttons
            if ($('.wcorp-refresh-btn').length) {
                $('.wcorp-refresh-btn').on('click', this.handleRefreshClick.bind(this));
            }

            // Clear cache button
            if ($('#wcorpClearCache').length) {
                $('#wcorpClearCache').on('click', this.handleClearCache.bind(this));
            }

        },

        handlePresetClick: function (e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const from = $btn.data('from');
            const to = $btn.data('to');

            $('#wcorpFromDate').val(from);
            $('#wcorpToDate').val(to);

            // Auto-submit form
            $('#wcorpDateFilter').submit();
        },

        handleRefreshClick: function (e) {
            e.preventDefault();
            const $btn = $(e.currentTarget);
            const reportType = $btn.data('report');

            $btn.addClass('rotating');
            this.loadReport(reportType, true);
        },

        handleClearCache: function (e) {
            e.preventDefault();

            if (!confirm('Are you sure you want to clear all cached reports?')) {
                return;
            }

            $.ajax({
                url: wcorpData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcorp_clear_cache',
                    nonce: wcorpData.nonce
                },
                success: function (response) {
                    if (response.success) {
                        alert('Cache cleared successfully!');
                        location.reload();
                    }
                }
            });
        },

        loadReports: function () {
            const reportTypes = ['daily_status', 'bosta', 'cod', 'agent'];

            reportTypes.forEach(type => {
                if ($(`[data-report-type="${type}"]`).length) {
                    this.loadReport(type);
                }
            });
        },

        loadReport: function (reportType, forceRefresh = false) {
            const $container = $(`[data-report-type="${reportType}"]`);

            if (!$container.length) {
                console.warn(`Report container not found for type: ${reportType}`);
                return;
            }

            const from = $('#wcorpFromDate').val();
            const to = $('#wcorpToDate').val();

            $container.html('<div class="wcorp-loading">Loading...</div>');

            $.ajax({
                url: wcorpData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcorp_load_report',
                    nonce: wcorpData.nonce,
                    report_type: reportType,
                    from: from || new Date().toISOString().split('T')[0],
                    to: to || new Date().toISOString().split('T')[0],
                    force_refresh: forceRefresh
                },
                success: (response) => {
                    if (response.success && response.data) {
                        this.renderReport(reportType, response.data, $container);
                    } else {
                        const errorMsg = response.data && response.data.error ? response.data.error : 'Failed to load report';
                        $container.html(`<div class="wcorp-error">Error: ${errorMsg}</div>`);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('AJAX Error:', status, error);
                    $container.html('<div class="wcorp-error">Network error occurred. Please try again.</div>');
                },
                complete: () => {
                    $(`.wcorp-refresh-btn[data-report="${reportType}"]`).removeClass('rotating');
                }
            });
        },

        renderReport: function (reportType, data, $container) {
            let html = '';

            switch (reportType) {
                case 'daily_status':
                    html = this.renderDailyStatusReport(data);
                    break;
                case 'bosta':
                    html = this.renderBostaReport(data);
                    break;
                case 'cod':
                    html = this.renderCODReport(data);
                    break;
                case 'agent':
                    html = this.renderAgentReport(data);
                    break;
            }

            $container.html(html);

            // Render charts if enabled
            if (wcorpData && wcorpData.features && wcorpData.features.includes('charts')) {
                this.renderCharts(reportType, data);
            }
        },

        renderDailyStatusReport: function (data) {
            let html = '';

            // Check if data exists
            if (!data || typeof data !== 'object') {
                return '<div class="wcorp-error">No data available for this report</div>';
            }

            // Metrics
            html += '<div class="wcorp-metrics-grid">';
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Total Orders</div>
                <div class="wcorp-metric-value">${data.total_orders || 0} <span style="font-size: 18px; font-weight: normal; opacity: 0.9;">(${this.formatCurrency(data.total_amount || 0)})</span></div>
                <div class="wcorp-metric-change">Total Revenue: ${this.formatCurrency(data.total_amount || 0)}</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Avg Processing Time</div>
                <div class="wcorp-metric-value">${this.secondsToHuman(data.avg_confirm_to_ready_seconds || 0)}</div>
                <div class="wcorp-metric-change">${data.num_samples || 0} samples</div>
            </div>`;
            html += '</div>';

            // Status table
            if (data.status_counts && Object.keys(data.status_counts).length > 0) {
                html += '<table class="wcorp-report-table">';
                html += '<thead><tr><th>Order Status</th><th>Count</th><th>Count %</th><th>Total Amount</th><th>Amount %</th></tr></thead>';
                html += '<tbody>';

                for (const [status, count] of Object.entries(data.status_counts)) {
                    const countPercentage = data.total_orders > 0 ? ((count / data.total_orders) * 100).toFixed(1) : '0.0';
                    const amount = (data.status_totals && data.status_totals[status]) ? data.status_totals[status] : 0;
                    const amountPercentage = data.total_amount > 0 ? ((amount / data.total_amount) * 100).toFixed(1) : '0.0';
                    const cleanStatus = status.replace('wc-', '').replace(/_/g, ' ').toUpperCase();

                    html += `<tr>
                        <td>${cleanStatus}</td>
                        <td><strong>${count}</strong></td>
                        <td>${countPercentage}%</td>
                        <td><strong>${this.formatCurrency(amount)}</strong></td>
                        <td>${amountPercentage}%</td>
                    </tr>`;
                }

                html += '</tbody>';
                html += `<tfoot>
                    <tr style="font-weight: bold; background: rgba(0, 0, 0, 0.03);">
                        <td>Total</td>
                        <td>${data.total_orders || 0}</td>
                        <td>100%</td>
                        <td>${this.formatCurrency(data.total_amount || 0)}</td>
                        <td>100%</td>
                    </tr>
                </tfoot>`;
                html += '</table>';
            } else {
                html += '<div class="wcorp-no-data">No order status data available for the selected period</div>';
            }

            // Chart placeholder
            if (wcorpData && wcorpData.features && wcorpData.features.includes('charts')) {
                html += '<div class="wcorp-chart-wrapper">';
                html += '<canvas id="dailyStatusChart" class="wcorp-chart-container"></canvas>';
                html += '</div>';
            }

            return html;
        },

        renderBostaReport: function (data) {
            let html = '';

            // Check if data exists
            if (!data || typeof data !== 'object') {
                return '<div class="wcorp-error">No data available for this report</div>';
            }

            // Metrics
            html += '<div class="wcorp-metrics-grid">';
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Total Orders</div>
                <div class="wcorp-metric-value">${data.total_orders || 0}</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Delivered</div>
                <div class="wcorp-metric-value">${data.delivered_count || 0}</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Success Rate</div>
                <div class="wcorp-metric-value">${data.success_rate ? (data.success_rate * 100).toFixed(1) + '%' : '-'}</div>
            </div>`;
            html += '</div>';

            // Details table
            html += '<table class="wcorp-report-table">';
            html += '<thead><tr><th>Metric</th><th>Value</th></tr></thead>';
            html += '<tbody>';
            html += `<tr><td>Returned Orders</td><td>${data.returned_count || 0}</td></tr>`;
            html += `<tr><td>Ready to Ship with Bosta</td><td>${data.ready_ship_bosta_count || 0}</td></tr>`;
            html += `<tr><td>Sent to Bosta (Awaiting Pickup)</td><td>${data.sent_to_bosta_count || 0}</td></tr>`;
            html += `<tr><td>Handed to Bosta</td><td>${data.handed_to_bosta_count || 0}</td></tr>`;
            html += `<tr><td>In Transit</td><td>${data.in_transit_count || 0}</td></tr>`;
            html += `<tr><td>Avg Ready → Handed Time</td><td>${this.secondsToHuman(data.avg_ready_to_handed_seconds || 0)} <small>(${data.ready_to_handed_samples || 0} samples)</small></td></tr>`;
            html += `<tr><td>Avg Handed → Delivered Time</td><td>${this.secondsToHuman(data.avg_handed_to_delivered_seconds || 0)} <small>(${data.handed_to_delivered_samples || 0} samples)</small></td></tr>`;
            html += '</tbody></table>';

            // Chart placeholder
            if (wcorpData && wcorpData.features && wcorpData.features.includes('charts')) {
                html += '<div class="wcorp-chart-wrapper">';
                html += '<canvas id="bostaChart" class="wcorp-chart-container"></canvas>';
                html += '</div>';
            }

            return html;
        },

        renderCODReport: function (data) {
            let html = '';

            // Check if data exists
            if (!data || typeof data !== 'object') {
                return '<div class="wcorp-error">No data available for this report</div>';
            }

            // Metrics
            html += '<div class="wcorp-metrics-grid">';
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Collected Amount</div>
                <div class="wcorp-metric-value">${this.formatCurrency(data.collected_total || 0)}</div>
                <div class="wcorp-metric-change">${data.collected_count || 0} orders</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Pending Amount</div>
                <div class="wcorp-metric-value">${this.formatCurrency(data.pending_total || 0)}</div>
                <div class="wcorp-metric-change">${data.pending_count || 0} orders</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Collection Rate</div>
                <div class="wcorp-metric-value">${((data.collection_rate || 0) * 100).toFixed(1)}%</div>
            </div>`;
            html += `<div class="wcorp-metric-card">
                <div class="wcorp-metric-label">Wholesale Amount</div>
                <div class="wcorp-metric-value">${this.formatCurrency(data.wholesale_total || 0)}</div>
                <div class="wcorp-metric-change">Total cost</div>
            </div>`;
            html += '</div>';

            // Details table
            html += '<table class="wcorp-report-table">';
            html += '<thead><tr><th>Metric</th><th>Value</th></tr></thead>';
            html += '<tbody>';
            html += `<tr><td>Total COD Orders</td><td>${data.total_orders || 0}</td></tr>`;
            html += `<tr><td>Collected Orders (تم التحصيل)</td><td>${data.collected_count || 0}</td></tr>`;
            html += `<tr><td>Pending Orders (لم تحصل بعد)</td><td>${data.pending_count || 0}</td></tr>`;
            html += `<tr><td>Returned Amount</td><td>${this.formatCurrency(data.returned_total || 0)} (${data.returned_count || 0} orders)</td></tr>`;
            html += `<tr><td>Expected Collection</td><td>${this.formatCurrency((data.pending_total || 0) + (data.collected_total || 0))}</td></tr>`;
            html += '</tbody></table>';

            // Chart placeholder
            if (wcorpData && wcorpData.features && wcorpData.features.includes('charts')) {
                html += '<div class="wcorp-chart-wrapper">';
                html += '<canvas id="codChart" class="wcorp-chart-container"></canvas>';
                html += '</div>';
            }

            return html;
        },

        renderAgentReport: function (data) {
            let html = '';

            // Check if data exists
            if (!data || typeof data !== 'object') {
                return '<div class="wcorp-error">No data available for this report</div>';
            }

            // Check if agent and bosta data exist
            if (!data.agent || !data.bosta) {
                return '<div class="wcorp-error">Agent or Bosta data is missing</div>';
            }

            // Comparison table
            html += '<div class="wcorp-comparison-table">';
            html += '<div class="wcorp-comparison-cell wcorp-comparison-header">Metric</div>';
            html += '<div class="wcorp-comparison-cell wcorp-comparison-header">Agent</div>';
            html += '<div class="wcorp-comparison-cell wcorp-comparison-header">Bosta</div>';

            html += '<div class="wcorp-comparison-cell">Total Orders</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.total_orders !== undefined ? data.agent.total_orders : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.total_orders !== undefined ? data.bosta.total_orders : 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Delivered</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.delivered !== undefined ? data.agent.delivered : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.delivered !== undefined ? data.bosta.delivered : 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Returned</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.returned !== undefined ? data.agent.returned : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.returned !== undefined ? data.bosta.returned : 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Ready to Ship</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.ready_ship !== undefined ? data.agent.ready_ship : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.ready_ship !== undefined ? data.bosta.ready_ship : 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Handed Over</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.handed !== undefined ? data.agent.handed : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.handed !== undefined ? data.bosta.handed : 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">In Progress / Transit</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.in_progress !== undefined ? data.agent.in_progress : 0}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.in_transit || 0}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Success Rate</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.agent.success_rate ? (data.agent.success_rate * 100).toFixed(1) + '%' : '-'}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${data.bosta.success_rate ? (data.bosta.success_rate * 100).toFixed(1) + '%' : '-'}</div></div>`;

            html += '<div class="wcorp-comparison-cell">Avg Delivery Time</div>';
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${this.secondsToHuman(data.agent.avg_delivery_seconds || 0)}</div></div>`;
            html += `<div class="wcorp-comparison-cell"><div class="wcorp-comparison-metric">${this.secondsToHuman(data.bosta.avg_delivery_seconds || 0)}</div></div>`;

            html += '</div>';

            // Chart placeholder
            if (wcorpData && wcorpData.features && wcorpData.features.includes('charts')) {
                html += '<div class="wcorp-chart-wrapper">';
                html += '<canvas id="agentChart" class="wcorp-chart-container"></canvas>';
                html += '</div>';
            }

            return html;
        },

        renderCharts: function (reportType, data) {
            setTimeout(() => {
                switch (reportType) {
                    case 'daily_status':
                        this.renderDailyStatusChart(data);
                        break;
                    case 'bosta':
                        this.renderBostaChart(data);
                        break;
                    case 'cod':
                        this.renderCODChart(data);
                        break;
                    case 'agent':
                        this.renderAgentChart(data);
                        break;
                }
            }, 100);
        },

        renderDailyStatusChart: function (data) {
            const ctx = document.getElementById('dailyStatusChart');
            if (!ctx || typeof Chart === 'undefined') return;

            const labels = Object.keys(data.status_counts);
            const values = Object.values(data.status_counts);

            if (this.charts.dailyStatus) {
                this.charts.dailyStatus.destroy();
            }

            this.charts.dailyStatus = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: values,
                        backgroundColor: [
                            '#3b82f6', '#8b5cf6', '#ec4899', '#f59e0b',
                            '#10b981', '#06b6d4', '#6366f1', '#ef4444'
                        ]
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right'
                        }
                    }
                }
            });
        },

        renderBostaChart: function (data) {
            const ctx = document.getElementById('bostaChart');
            if (!ctx || typeof Chart === 'undefined') return;

            if (this.charts.bosta) {
                this.charts.bosta.destroy();
            }

            this.charts.bosta = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Delivered', 'Returned', 'Ready Ship', 'Sent', 'Handed', 'In Transit'],
                    datasets: [{
                        label: 'Orders',
                        data: [
                            data.delivered_count,
                            data.returned_count,
                            data.ready_ship_bosta_count,
                            data.sent_to_bosta_count,
                            data.handed_to_bosta_count,
                            data.in_transit_count
                        ],
                        backgroundColor: ['#10b981', '#ef4444', '#8b5cf6', '#f59e0b', '#06b6d4', '#3b82f6']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },

        renderCODChart: function (data) {
            const ctx = document.getElementById('codChart');
            if (!ctx || typeof Chart === 'undefined') return;

            if (this.charts.cod) {
                this.charts.cod.destroy();
            }

            this.charts.cod = new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['Collected', 'Pending', 'Returned'],
                    datasets: [{
                        data: [
                            data.collected_total,
                            data.pending_total,
                            data.returned_total
                        ],
                        backgroundColor: ['#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false
                }
            });
        },

        renderAgentChart: function (data) {
            const ctx = document.getElementById('agentChart');
            if (!ctx || typeof Chart === 'undefined') return;

            if (this.charts.agent) {
                this.charts.agent.destroy();
            }

            this.charts.agent = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['Total', 'Delivered', 'Returned', 'Ready Ship', 'Handed'],
                    datasets: [
                        {
                            label: 'Agent',
                            data: [
                                data.agent.total_orders,
                                data.agent.delivered,
                                data.agent.returned,
                                data.agent.ready_ship,
                                data.agent.handed
                            ],
                            backgroundColor: '#3b82f6'
                        },
                        {
                            label: 'Bosta',
                            data: [
                                data.bosta.total_orders,
                                data.bosta.delivered,
                                data.bosta.returned,
                                data.bosta.ready_ship,
                                data.bosta.handed
                            ],
                            backgroundColor: '#8b5cf6'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true
                        }
                    }
                }
            });
        },

        // Utility functions
        secondsToHuman: function (seconds) {
            if (!seconds || seconds <= 0) return '-';

            const days = Math.floor(seconds / 86400);
            seconds -= days * 86400;
            const hours = Math.floor(seconds / 3600);
            seconds -= hours * 3600;
            const mins = Math.floor(seconds / 60);

            const parts = [];
            if (days) parts.push(days + 'd');
            if (hours) parts.push(hours + 'h');
            if (mins) parts.push(mins + 'm');

            return parts.join(' ') || '-';
        },

        formatCurrency: function (amount) {
            const currency = (window.wcorpData && window.wcorpData.currency) ? window.wcorpData.currency : 'EGP';
            try {
                return new Intl.NumberFormat('en-US', {
                    style: 'currency',
                    currency: currency,
                    maximumFractionDigits: 2
                }).format(amount);
            } catch (e) {
                const symbol = (window.wcorpData && window.wcorpData.currencySymbol) ? window.wcorpData.currencySymbol : 'EGP';
                return Number(amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' ' + symbol;
            }
        },

        // Add keyboard shortcuts
        bindKeyboardShortcuts: function () {
            $(document).on('keydown', function (e) {
                // Ctrl/Cmd + R to refresh all reports
                if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                    e.preventDefault();
                    $('.wcorp-refresh-btn').click();
                }

                // Ctrl/Cmd + E to export
                if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                    e.preventDefault();
                    $('.wcorp-export-section .button').first().click();
                }

                // Ctrl/Cmd + 1-4 to switch between reports
                if ((e.ctrlKey || e.metaKey) && e.key >= '1' && e.key <= '4') {
                    e.preventDefault();
                    const reportIndex = parseInt(e.key) - 1;
                    const reports = $('.wcorp-report-section');
                    if (reports.length > reportIndex) {
                        reports.eq(reportIndex)[0].scrollIntoView({ behavior: 'smooth' });
                    }
                }
            });
        },

        // Add tooltips for better UX
        addTooltips: function () {
            $('.wcorp-refresh-btn').attr('title', 'تحديث التقرير (Ctrl+R)');
            $('.wcorp-export-section .button').attr('title', 'تصدير التقرير (Ctrl+E)');
        },

        // Add loading animation to refresh buttons
        enhanceRefreshButtons: function () {
            $('.wcorp-refresh-btn').on('click', function () {
                const $btn = $(this);
                const $icon = $btn.find('.dashicons');

                $btn.addClass('rotating');
                $icon.addClass('wcorp-spin');

                setTimeout(function () {
                    $btn.removeClass('rotating');
                    $icon.removeClass('wcorp-spin');
                }, 2000);
            });
        },

        // Add auto-refresh functionality
        enableAutoRefresh: function () {
            // Auto refresh every 1 hour (3600000 milliseconds)
            setInterval(function () {
                if (document.visibilityState === 'visible') {
                    $('.wcorp-refresh-btn').click();
                }
            }, 3600000); // 1 hour
        },

        // Add print functionality
        addPrintSupport: function () {
            // Use event delegation to handle dynamically added buttons
            $(document).on('click', '.wcorp-print-btn', function (e) {
                e.preventDefault();
                console.log('Print button clicked'); // Debug log

                try {
                    // Try to print
                    window.print();
                } catch (error) {
                    console.error('Print failed:', error);
                    // Fallback: show alert
                    alert('لا يمكن طباعة الصفحة. يرجى استخدام Ctrl+P أو قائمة المتصفح للطباعة.');
                }
            });
        },

        // --- Import Tool Logic ---

        initImportTool: function () {
            if ($('#wcorp-import-form').length) {
                $('#wcorp-import-form').on('submit', this.handleImportSubmit.bind(this));
                $('#wcorp-cancel-import').on('click', this.handleImportCancel.bind(this));
            }
        },

        handleImportSubmit: function (e) {
            e.preventDefault();

            const fileInput = $('#wcorp_import_file')[0];
            if (fileInput.files.length === 0) {
                alert('Please select a file first.');
                return;
            }

            const file = fileInput.files[0];
            const overwrite = $('#wcorp_overwrite_existing').is(':checked'); // Get overwrite status
            const updateSnapshots = $('#wcorp_update_old_snapshots').is(':checked'); // Get update snapshots status

            const formData = new FormData();
            formData.append('action', 'wcorp_import_upload_csv');
            formData.append('nonce', wcorpData.nonce);
            formData.append('file', file);

            // UI Updates
            this.resetImportUI();
            $('#wcorp-start-import').prop('disabled', true).text('Uploading...');
            $('.wcorp-progress-section').slideDown();
            $('#wcorp-import-log').slideDown().html('<div class="log-entry">Uploading file...</div>');

            $.ajax({
                url: wcorpData.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        this.startBatchProcess(response.data.file_path, response.data.total_rows, overwrite, updateSnapshots);
                    } else {
                        this.handleImportError(response.data.message || 'Upload failed');
                    }
                },
                error: (xhr) => {
                    this.handleImportError('Network error during upload');
                }
            });
        },

        startBatchProcess: function (filePath, totalRows, overwrite, updateSnapshots) {
            // Get date range values
            const snapshotDateFrom = $('#wcorp_snapshot_date_from').val();
            const snapshotDateTo = $('#wcorp_snapshot_date_to').val();
            const forceOverwriteSnapshots = $('#wcorp_force_overwrite_snapshots').is(':checked');
            
            this.importState = {
                filePath: filePath,
                totalRows: totalRows,
                overwrite: overwrite,
                updateSnapshots: updateSnapshots,
                snapshotDateFrom: snapshotDateFrom,
                snapshotDateTo: snapshotDateTo,
                forceOverwriteSnapshots: forceOverwriteSnapshots,
                processed: 0,
                updated: 0,
                skipped: 0,
                cancelled: false
            };

            $('#wcorp-start-import').hide();
            $('#wcorp-cancel-import').show();
            $('.wcorp-current-status').text('Starting import...');

            this.processBatch(0);
        },

        processBatch: function (offset) {
            if (this.importState.cancelled) return;

            $.ajax({
                url: wcorpData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'wcorp_import_process_batch',
                    nonce: wcorpData.nonce,
                    file_path: this.importState.filePath,
                    overwrite: this.importState.overwrite,
                    update_snapshots: this.importState.updateSnapshots,
                    force_overwrite_snapshots: this.importState.forceOverwriteSnapshots,
                    snapshot_date_from: this.importState.snapshotDateFrom,
                    snapshot_date_to: this.importState.snapshotDateTo,
                    offset: offset,
                    limit: 50 // Batch size
                },
                success: (response) => {
                    if (response.success) {
                        this.updateImportProgress(response.data);

                        if (!response.data.done && !this.importState.cancelled) {
                            // Continue to next batch
                            this.processBatch(response.data.new_offset);
                        } else if (response.data.done) {
                            this.completeImport();
                        }
                    } else {
                        this.handleImportError(response.data.message || 'Batch processing failed');
                    }
                },
                error: (xhr) => {
                    this.handleImportError('Network error during processing');
                }
            });
        },

        updateImportProgress: function (data) {
            this.importState.processed += data.processed;
            this.importState.updated += data.updated;
            this.importState.skipped += data.skipped;

            // Update stats text
            $('.wcorp-progress-stats .processed').text(this.importState.processed);
            $('.wcorp-progress-stats .updated').text(this.importState.updated);
            $('.wcorp-progress-stats .skipped').text(this.importState.skipped);

            // Update progress bar
            const percent = this.importState.totalRows > 0
                ? Math.min(100, (this.importState.processed / this.importState.totalRows) * 100)
                : 100;
            $('.wcorp-progress-bar-fill').css('width', percent + '%');
            $('.wcorp-current-status').text(`Processing...${percent.toFixed(1)} % `);

            // Append logs
            if (data.log && data.log.length > 0) {
                const logHtml = data.log.map(msg => `<div class="log-entry">${msg}</div>`).join('');
                const $logInfo = $('#wcorp-import-log');
                $logInfo.append(logHtml);
                $logInfo.scrollTop($logInfo[0].scrollHeight);
            }
        },

        completeImport: function () {
            $('.wcorp-progress-bar-fill').css('width', '100%').css('background', '#00a32a');
            $('.wcorp-current-status').text('Import Completed Successfully!');
            $('#wcorp-cancel-import').hide();
            $('#wcorp-start-import').show().prop('disabled', false).text('Import Wholesale Prices');
            alert('Import completed successfully!');
        },

        handleImportError: function (msg) {
            $('.wcorp-progress-bar-fill').css('background', '#d63638');
            $('.wcorp-current-status').text('Error: ' + msg);
            $('#wcorp-import-log').append(`<div class="log-entry error" style="color:red">Error: ${msg}</div>`);
            $('#wcorp-start-import').prop('disabled', false).text('Import Wholesale Prices');
        },

        handleImportCancel: function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to stop the import?')) {
                this.importState.cancelled = true;
                $('.wcorp-current-status').text('Import Cancelled');
                $('#wcorp-cancel-import').hide();
                $('#wcorp-start-import').show().prop('disabled', false).text('Import Wholesale Prices');
            }
        },

        resetImportUI: function () {
            $('.wcorp-progress-bar-fill').css('width', '0%').css('background', '#2271b1');
            $('.wcorp-progress-stats span').text('0');
            $('#wcorp-import-log').html('');
        }
    };

    // Initialize when document ready
    $(document).ready(function () {
        // Check if wcorpData exists
        if (typeof wcorpData === 'undefined') {
            console.warn('WC Order Reports: wcorpData not loaded');
            return;
        }

        WCOrderReportsPro.init();
    });

})(jQuery);
