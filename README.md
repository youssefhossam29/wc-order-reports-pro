# WC Order Reports Pro (Bosta & WooCommerce)

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-indigo.svg)](https://php.net/)
[![HPOS](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com/document/high-performance-order-storage/)
[![Version](https://img.shields.io/badge/Version-2.3.0-emerald.svg)](https://github.com/youssefhossam29/wc-order-reports-pro)
[![License](https://img.shields.io/badge/License-GPLv2-orange.svg)](LICENSE)

**WC Order Reports Pro** is an enterprise-grade WooCommerce extension engineered for high-volume e-commerce stores, fulfillment centers, and Cash on Delivery (COD) operations. It bridges the gap between WooCommerce order management, shipping logistics (featuring dedicated Bosta integration), delivery agent benchmarking, and financial profit reporting.

---

## Order Lifecycle and Custom Statuses

WC Order Reports Pro is optimized for agile order management, warehouse fulfillment, and logistics pipelines. While standard WooCommerce statuses (`pending`, `processing`, `completed`, `cancelled`) are fully supported, the plugin leverages specialized status transitions to calculate turnaround speeds and logistics KPIs:

| Status Slug | Display Name | Pipeline Stage | Operational & Reporting Role |
| :--- | :--- | :--- | :--- |
| `wc-confirm_preparing` | Confirmed – Preparing | Warehouse / Prep | Marks the moment an order is confirmed by sales/CS and enters warehouse picking. Benchmark start point. |
| `wc-ready_ship_bosta` | Ready for Shipping (Bosta) | Dispatch Staging | Package packed, labeled, and placed in the Bosta pickup area. Completes the prep turnaround calculation. |
| `wc-ready_ship_agent` | Ready for Shipping (Agent) | Dispatch Staging | Package ready and assigned to an in-house private delivery representative. |
| `wc-sent_to_bosta` | Sent to Bosta (Awaiting Pickup) | Courier Handover | Electronic manifest generated and pickup request dispatched to Bosta. |
| `wc-handed_to_bosta` | Received by Bosta | In-Transit | Courier has physically picked up package. Starts the transit duration benchmark. |
| `wc-handed_to_agent` | Handed to Agent | In-Transit | Package physically handed to internal courier for doorstep delivery. |
| `wc-delivered` | Delivered to Customer | Fulfillment Complete | Package successfully received by customer. |
| `wc-cod_collected` | Cash Collected | Financial Settlement | Funds physically or digitally collected. Fuels the **COD Financials & Collection Efficiency** reports. |
| `wc-returned` | Returned | Reverse Logistics | Package returned or refused at doorstep. Calculates return losses and transit waste. |

> [!TIP]
> Custom order statuses can be managed in your store natively via filters or through popular plugins like *Custom Order Status for WooCommerce* or the official *Bosta WooCommerce* integration.

---

## Key Features

### 1. Daily Status Report (`daily_status`)
* Comprehensive view of daily store operations across all WooCommerce order statuses.
* Metrics card displaying total order volume alongside **total revenue value** formatted in store currency.
* Status breakdown table with dual financial and volumetric metrics:
  * Order Count and Count Percentage per status.
  * **Total Monetary Value (Total Amount)** and **Revenue Share (Amount %)** per status.
  * Comprehensive summary footer row with 100% reconciliation totals.
* Accurate order preparation turnaround calculation (**Confirm Preparing to Ready for Shipping**).
* Visual status breakdown powered by Chart.js.
* Full CSV and Excel export support with complete financial metrics.

### 2. Bosta Logistics Performance (`bosta`)
* Native tracking analytics tailored for **Bosta Shipping** integration.
* Success delivery rate calculation: Delivered vs. Returned orders.
* Pipeline monitoring: `sent_to_bosta` -> `handed_to_bosta` -> `in_transit` -> `delivered`.
* Real lifecycle turnaround tracking:
  * Average time from **Ready** to **Handed to Bosta courier**.
  * Average transit duration from **Handed** to **Final Customer Delivery**.

### 3. COD Financial and Collections Report (`cod`)
* Real-time accounting for Cash on Delivery operations:
  * **Collected Total:** Money successfully received from delivered orders (`wc-cod_collected`).
  * **Pending Total:** Outstanding funds in delivery pipelines.
  * **Expected Collection:** Sum of pending + collected amounts.
  * **Returned Losses:** Total value and volume of returned shipments.
  * **Collection Rate:** Realized vs. expected cash collection efficiency.
  * **Wholesale / COGS Total:** Total product acquisition cost based on historical snapshots.

### 4. Agent vs. Bosta Delivery Comparison (`agent`)
* Benchmarks internal delivery representatives against 3rd-party logistics (Bosta).
* Delivery success rate comparisons.
* Processing speed and fulfillment turnaround metrics.

### 5. Wholesale Cost and Historical Snapshots Architecture
* Adds a native wholesale / cost of goods (`_wholesale_price` / `_cost_of_goods`) meta field to both **Simple Products** and **Product Variations**.
* **Historical Accuracy (Snapshotting):** Captures line item cost of goods at the exact time an order is placed (`_wholesale_price_at_purchase`). This ensures accurate past profit reporting even if vendor product prices fluctuate over time.
* **Lazy Freeze Mechanism:** Safely captures missing snapshots when legacy orders reach qualifying statuses.

### 6. Tools Page and Wholesale CSV Importer (`wc-order-reports-pro-tools`)
* Dedicated administrative maintenance screen located at **Order Reports -> Tools** (`/wp-admin/admin.php?page=wc-order-reports-pro-tools`).
* **Bulk Wholesale Price Upload:** Upload standard CSV files to update product and variation cost of goods in bulk by `sku`:
  ```csv
  sku,cost_of_goods
  TSHIRT-BLK-S,150.00
  TSHIRT-BLK-M,160.00
  SHOES-BRN-42,420.50
  ```
* **Smart Number Sanitization:** Automatically handles localized number formats (supports both commas and dots as decimal/thousands separators).
* **Flexible Synchronization Options:**
  * **Overwrite Existing Prices:** Update product wholesale prices if different from CSV, or skip already configured products.
  * **Backfill Missing Snapshots:** Safely populates missing cost snapshots on past historical orders using the imported price without modifying existing snapshots.
  * **Force Overwrite with Date Range:** Administrative override tool to correct historical snapshots within an optional custom date window (From Date to To Date).
* **Asynchronous Batch Processing:** Uses AJAX batching with a live progress bar and statistics (Processed, Updated, Skipped) to prevent PHP execution timeouts on massive catalogs.

### 7. Performance, Caching Engine, and HPOS Compatibility
* **HPOS Ready:** Fully declared and tested with WooCommerce **High-Performance Order Storage (`custom_order_tables`)** as well as legacy WordPress post storage.
* Built-in transient caching (`WC_Order_Reports_Cache_Manager`) to ensure instant dashboard loading on stores with 100,000+ orders.
* Event-driven cache invalidation upon product catalog updates, settings modifications, or manual one-click purge.
* Asynchronous AJAX data fetching for non-blocking admin experiences.

### 8. Automated Email Digests and Delayed Order Alerts
* Automated daily summary digest dispatched via WP-Cron.
* Real-time admin alert notices when orders remain stuck in the same status beyond configured thresholds (e.g., 48 hours).

---

## Technical Requirements

* **WordPress:** 5.8 or higher
* **WooCommerce:** 5.0 or higher (Fully compatible with WooCommerce 8.0, 9.0+, and HPOS)
* **PHP:** 7.4, 8.0, 8.1, 8.2, or 8.3
* **Shipping Integrations:** Compatible with Bosta WooCommerce, WooCommerce COD, and standard shipping methods.

---

## Installation and Setup

1. **Download / Clone:**
   Clone the repository into your WordPress plugins folder:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/youssefhossam29/wc-order-reports-pro.git
   ```

2. **Activate:**
   * Go to **WP-Admin -> Plugins -> Installed Plugins**.
   * Locate **WC Order Reports Pro (Bosta & WooCommerce)** and click **Activate**.

3. **Access Dashboard:**
   * Navigate to the menu item: **Order Reports** (`/wp-admin/admin.php?page=wc-order-reports-pro`).
   * Select your desired date range (Today, Yesterday, Last 7 Days, Last 30 Days, or Custom Range).

4. **Access Tools and Import Costs:**
   * Navigate to: **Order Reports -> Tools** (`/wp-admin/admin.php?page=wc-order-reports-pro-tools`).
   * Upload your cost-of-goods CSV file to populate product wholesale prices and order snapshots.

5. **Configure Settings:**
   * Go to: **Order Reports -> Settings** (`/wp-admin/admin.php?page=wc-order-reports-pro-settings`).
   * Configure notification thresholds, daily email digest recipients, and wholesale lazy-freeze preferences.

---

## Security and Best Practices

* **Permission Checks:** All admin pages and AJAX endpoints enforce `manage_woocommerce` capability checks.
* **Nonce Verification:** Every AJAX request and export action is verified with nonces (`wcorp_nonce`, `wcorp_export_*`).
* **Input Sanitization:** All request variables and query parameters are strictly sanitized before processing.
* **Database Efficiency:** Queries leverage optimized metadata lookups and WordPress transient caching to prevent server overload.
