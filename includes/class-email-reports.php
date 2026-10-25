<?php
/**
 * Email Reports
 * Handles scheduled email reports
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WC_Order_Reports_Email_Reports {

    private $plugin;

    public function __construct( $plugin ) {
        $this->plugin = $plugin;

        // Schedule daily email if enabled
        add_action( 'wcorp_send_daily_report', array( $this, 'send_daily_report' ) );
        
        // Admin actions
        add_action( 'admin_init', array( $this, 'maybe_schedule_events' ) );
    }

    public function maybe_schedule_events() {
        if ( ! wp_next_scheduled( 'wcorp_send_daily_report' ) ) {
            $send_time = get_option( 'wcorp_email_report_time', '09:00' );
            $next_run = strtotime( 'today ' . $send_time );
            
            if ( $next_run < time() ) {
                $next_run = strtotime( 'tomorrow ' . $send_time );
            }

            wp_schedule_event( $next_run, 'daily', 'wcorp_send_daily_report' );
        }
    }

    public function send_daily_report() {
        $recipients = $this->get_email_recipients();
        
        if ( empty( $recipients ) ) {
            return;
        }

        $yesterday = date( 'Y-m-d', strtotime( '-1 day' ) );
        $generator = new WC_Order_Reports_Generator( $this->plugin );

        // Generate all reports
        $daily_report = $generator->generate_report( 'daily_status', $yesterday, $yesterday );
        $bosta_report = $generator->generate_report( 'bosta', $yesterday, $yesterday );
        $cod_report = $generator->generate_report( 'cod', $yesterday, $yesterday );

        // Build email
        $subject = sprintf( 
            __( 'Daily Order Report - %s', 'wc-order-reports-pro' ),
            date( 'F j, Y', strtotime( $yesterday ) )
        );

        $message = $this->build_email_html( $daily_report, $bosta_report, $cod_report, $yesterday );

        // Send email
        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        
        foreach ( $recipients as $recipient ) {
            wp_mail( $recipient, $subject, $message, $headers );
        }
    }

    private function build_email_html( $daily_report, $bosta_report, $cod_report, $date ) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #0073aa; color: white; padding: 20px; text-align: center; }
                .report-section { margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #0073aa; }
                .report-section h2 { margin-top: 0; color: #0073aa; }
                table { width: 100%; border-collapse: collapse; margin: 10px 0; }
                table td, table th { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
                table th { background: #0073aa; color: white; }
                .metric { font-size: 24px; font-weight: bold; color: #0073aa; }
                .footer { text-align: center; padding: 20px; color: #999; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="header">
                    <h1><?php echo esc_html( sprintf( __( 'Daily Report - %s', 'wc-order-reports-pro' ), date( 'F j, Y', strtotime( $date ) ) ) ); ?></h1>
                </div>

                <div class="report-section">
                    <h2><?php esc_html_e( 'Daily Status Summary', 'wc-order-reports-pro' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Total Orders:', 'wc-order-reports-pro' ); ?></strong> <span class="metric"><?php echo esc_html( $daily_report['total_orders'] ); ?></span></p>
                    
                    <table>
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Status', 'wc-order-reports-pro' ); ?></th>
                                <th><?php esc_html_e( 'Count', 'wc-order-reports-pro' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $daily_report['status_counts'] as $status => $count ): ?>
                            <tr>
                                <td><?php echo esc_html( $status ); ?></td>
                                <td><?php echo esc_html( $count ); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <p><strong><?php esc_html_e( 'Avg Processing Time:', 'wc-order-reports-pro' ); ?></strong> <?php echo esc_html( WC_Order_Reports_Generator::seconds_to_human( $daily_report['avg_confirm_to_ready_seconds'] ) ); ?></p>
                </div>

                <div class="report-section">
                    <h2><?php esc_html_e( 'Bosta Performance', 'wc-order-reports-pro' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Delivered:', 'wc-order-reports-pro' ); ?></strong> <span class="metric"><?php echo esc_html( $bosta_report['delivered_count'] ); ?></span></p>
                    <p><strong><?php esc_html_e( 'Success Rate:', 'wc-order-reports-pro' ); ?></strong> <?php echo $bosta_report['success_rate'] ? round( $bosta_report['success_rate'] * 100, 1 ) . '%' : '-'; ?></p>
                    <p><strong><?php esc_html_e( 'Pending Pickup:', 'wc-order-reports-pro' ); ?></strong> <?php echo esc_html( $bosta_report['sent_to_bosta_count'] ); ?></p>
                </div>

                <div class="report-section">
                    <h2><?php esc_html_e( 'COD Financial', 'wc-order-reports-pro' ); ?></h2>
                    <p><strong><?php esc_html_e( 'Collected:', 'wc-order-reports-pro' ); ?></strong> <span class="metric"><?php echo wc_price( $cod_report['collected_total'] ); ?></span></p>
                    <p><strong><?php esc_html_e( 'Pending:', 'wc-order-reports-pro' ); ?></strong> <?php echo wc_price( $cod_report['pending_total'] ); ?> (<?php echo esc_html( $cod_report['pending_count'] ); ?> orders)</p>
                    <p><strong><?php esc_html_e( 'Collection Rate:', 'wc-order-reports-pro' ); ?></strong> <?php echo round( $cod_report['collection_rate'] * 100, 1 ); ?>%</p>
                </div>

                <div class="footer">
                    <p><?php esc_html_e( 'This is an automated email from WC Order Reports Pro', 'wc-order-reports-pro' ); ?></p>
                    <p><?php echo esc_html( get_bloginfo( 'name' ) ); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    private function get_email_recipients() {
        $recipients = get_option( 'wcorp_email_recipients', array() );
        
        if ( empty( $recipients ) ) {
            $recipients = array( get_option( 'admin_email' ) );
        }

        return array_filter( array_map( 'sanitize_email', $recipients ) );
    }

    public static function unschedule_events() {
        $timestamp = wp_next_scheduled( 'wcorp_send_daily_report' );
        if ( $timestamp ) {
            wp_unschedule_event( $timestamp, 'wcorp_send_daily_report' );
        }
    }
}

// Note: Class is initialized from main plugin file when email_reports feature is enabled
