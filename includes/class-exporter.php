<?php
/**
 * Exporter
 * Handles CSV and Excel exports of reports
 */

if (!defined('ABSPATH')) {
    exit;
}

class WC_Order_Reports_Exporter
{

    private $plugin;

    public function __construct($plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * Export CSV
     */
    public function export_csv()
    {
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'daily_status';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d');
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');

        $generator = new WC_Order_Reports_Generator($this->plugin);
        $data = $generator->generate_report($report_type, $from, $to);

        $filename = 'wc-order-report-' . $report_type . '-' . $from . '-to-' . $to . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Write data based on report type
        switch ($report_type) {
            case 'daily_status':
                $this->export_daily_status_csv($output, $data);
                break;
            case 'bosta':
                $this->export_bosta_csv($output, $data);
                break;
            case 'cod':
                $this->export_cod_csv($output, $data);
                break;
            case 'agent':
                $this->export_agent_csv($output, $data);
                break;
        }

        fclose($output);
    }

    private function export_daily_status_csv($output, $data)
    {
        // Header
        fputcsv($output, array('WC Order Reports - Daily Status'));
        fputcsv($output, array('Date Range', $data['date_range']['from'] . ' to ' . $data['date_range']['to']));
        fputcsv($output, array());

        // Status counts
        fputcsv($output, array('Order Status', 'Count'));
        foreach ($data['status_counts'] as $status => $count) {
            fputcsv($output, array($status, $count));
        }

        fputcsv($output, array());
        fputcsv($output, array('Average Confirm to Ready Time', WC_Order_Reports_Generator::seconds_to_human($data['avg_confirm_to_ready_seconds'])));
        fputcsv($output, array('Samples', $data['num_samples']));
    }

    private function export_bosta_csv($output, $data)
    {
        fputcsv($output, array('WC Order Reports - Bosta Performance'));
        fputcsv($output, array());

        fputcsv($output, array('Metric', 'Value'));
        fputcsv($output, array('Total Orders', $data['total_orders']));
        fputcsv($output, array('Delivered', $data['delivered_count']));
        fputcsv($output, array('Returned', $data['returned_count']));
        fputcsv($output, array('Success Rate', $data['success_rate'] ? round($data['success_rate'] * 100, 2) . '%' : '-'));
        fputcsv($output, array('Sent to Bosta', $data['sent_to_bosta_count']));
        fputcsv($output, array('Handed to Bosta', $data['handed_to_bosta_count']));
        fputcsv($output, array('In Transit', $data['in_transit_count']));
        fputcsv($output, array('Avg Ready to Handed', WC_Order_Reports_Generator::seconds_to_human($data['avg_ready_to_handed_seconds'])));
        fputcsv($output, array('Avg Handed to Delivered', WC_Order_Reports_Generator::seconds_to_human($data['avg_handed_to_delivered_seconds'])));
    }

    private function export_cod_csv($output, $data)
    {
        fputcsv($output, array('WC Order Reports - COD Financial'));
        fputcsv($output, array());

        fputcsv($output, array('Metric', 'Value'));
        fputcsv($output, array('Total COD Orders', $data['total_orders']));
        fputcsv($output, array('Collected Total', number_format($data['collected_total'], 2)));
        fputcsv($output, array('Collected Orders', $data['collected_count']));
        fputcsv($output, array('Pending Total', number_format($data['pending_total'], 2)));
        fputcsv($output, array('Pending Orders', $data['pending_count']));
        fputcsv($output, array('Returned Total', number_format($data['returned_total'], 2)));
        fputcsv($output, array('Returned Orders', $data['returned_count']));
        fputcsv($output, array('Collection Rate', round($data['collection_rate'] * 100, 2) . '%'));
        fputcsv($output, array('Wholesale Amount', number_format(isset($data['wholesale_total']) ? $data['wholesale_total'] : 0, 2)));
    }

    private function export_agent_csv($output, $data)
    {
        fputcsv($output, array('WC Order Reports - Agent vs Bosta Comparison'));
        fputcsv($output, array());

        fputcsv($output, array('Metric', 'Agent', 'Bosta'));
        fputcsv($output, array('Total Orders', $data['agent']['total_orders'], $data['bosta']['total_orders']));
        fputcsv($output, array('Delivered', $data['agent']['delivered'], $data['bosta']['delivered']));
        fputcsv($output, array('Returned', $data['agent']['returned'], $data['bosta']['returned']));

        $agent_success = $data['agent']['success_rate'] ? round($data['agent']['success_rate'] * 100, 2) . '%' : '-';
        $bosta_success = $data['bosta']['success_rate'] ? round($data['bosta']['success_rate'] * 100, 2) . '%' : '-';
        fputcsv($output, array('Success Rate', $agent_success, $bosta_success));

        fputcsv($output, array(
            'Avg Delivery Time',
            WC_Order_Reports_Generator::seconds_to_human($data['agent']['avg_delivery_seconds']),
            WC_Order_Reports_Generator::seconds_to_human($data['bosta']['avg_delivery_seconds'])
        ));
    }

    /**
     * Export Excel (using PHP native)
     */
    public function export_excel()
    {
        $report_type = isset($_GET['report_type']) ? sanitize_text_field($_GET['report_type']) : 'daily_status';
        $from = isset($_GET['from']) ? sanitize_text_field($_GET['from']) : date('Y-m-d');
        $to = isset($_GET['to']) ? sanitize_text_field($_GET['to']) : date('Y-m-d');

        $generator = new WC_Order_Reports_Generator($this->plugin);
        $data = $generator->generate_report($report_type, $from, $to);

        // Create simple Excel XML
        $filename = 'wc-order-report-' . $report_type . '-' . $from . '-to-' . $to . '.xls';

        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
        echo '<Worksheet ss:Name="Report"><Table>' . "\n";

        switch ($report_type) {
            case 'daily_status':
                $this->export_daily_status_excel($data);
                break;
            case 'bosta':
                $this->export_bosta_excel($data);
                break;
            case 'cod':
                $this->export_cod_excel($data);
                break;
            case 'agent':
                $this->export_agent_excel($data);
                break;
        }

        echo '</Table></Worksheet></Workbook>';
    }

    private function export_daily_status_excel($data)
    {
        $this->excel_row(array('WC Order Reports - Daily Status'), true);
        $this->excel_row(array('Date Range: ' . $data['date_range']['from'] . ' to ' . $data['date_range']['to']));
        $this->excel_row(array());

        $this->excel_row(array('Order Status', 'Count'), true);
        foreach ($data['status_counts'] as $status => $count) {
            $this->excel_row(array($status, $count));
        }

        $this->excel_row(array());
        $this->excel_row(array('Average Confirm to Ready Time', WC_Order_Reports_Generator::seconds_to_human($data['avg_confirm_to_ready_seconds'])));
        $this->excel_row(array('Samples', $data['num_samples']));
    }

    private function export_bosta_excel($data)
    {
        $this->excel_row(array('WC Order Reports - Bosta Performance'), true);
        $this->excel_row(array());

        $this->excel_row(array('Metric', 'Value'), true);
        $this->excel_row(array('Total Orders', $data['total_orders']));
        $this->excel_row(array('Delivered', $data['delivered_count']));
        $this->excel_row(array('Returned', $data['returned_count']));
        $this->excel_row(array('Success Rate', $data['success_rate'] ? round($data['success_rate'] * 100, 2) . '%' : '-'));
        $this->excel_row(array('Sent to Bosta', $data['sent_to_bosta_count']));
        $this->excel_row(array('Handed to Bosta', $data['handed_to_bosta_count']));
        $this->excel_row(array('In Transit', $data['in_transit_count']));
        $this->excel_row(array('Avg Ready to Handed', WC_Order_Reports_Generator::seconds_to_human($data['avg_ready_to_handed_seconds'])));
        $this->excel_row(array('Avg Handed to Delivered', WC_Order_Reports_Generator::seconds_to_human($data['avg_handed_to_delivered_seconds'])));
    }

    private function export_cod_excel($data)
    {
        $this->excel_row(array('WC Order Reports - COD Financial'), true);
        $this->excel_row(array());

        $this->excel_row(array('Metric', 'Value'), true);
        $this->excel_row(array('Total COD Orders', $data['total_orders']));
        $this->excel_row(array('Collected Total', number_format($data['collected_total'], 2)));
        $this->excel_row(array('Collected Orders', $data['collected_count']));
        $this->excel_row(array('Pending Total', number_format($data['pending_total'], 2)));
        $this->excel_row(array('Pending Orders', $data['pending_count']));
        $this->excel_row(array('Wholesale Amount', number_format(isset($data['wholesale_total']) ? $data['wholesale_total'] : 0, 2)));
    }

    private function export_agent_excel($data)
    {
        $this->excel_row(array('WC Order Reports - Agent vs Bosta'), true);
        $this->excel_row(array());

        $this->excel_row(array('Metric', 'Agent', 'Bosta'), true);
        $this->excel_row(array('Total Orders', $data['agent']['total_orders'], $data['bosta']['total_orders']));
        $this->excel_row(array('Delivered', $data['agent']['delivered'], $data['bosta']['delivered']));
    }

    private function excel_row($cells, $is_header = false)
    {
        echo '<Row>';
        foreach ($cells as $cell) {
            $style = $is_header ? ' ss:StyleID="Header"' : '';
            echo '<Cell' . $style . '><Data ss:Type="String">' . htmlspecialchars($cell) . '</Data></Cell>';
        }
        echo '</Row>' . "\n";
    }
}
