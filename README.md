# WC Order Reports Pro (Bosta & WooCommerce)

[![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-indigo.svg)](https://php.net/)
[![Version](https://img.shields.io/badge/Version-2.2.0-emerald.svg)](https://github.com/youssefhossam29/wc-order-reports-pro)
[![License](https://img.shields.io/badge/License-GPLv2-orange.svg)](LICENSE)

**WC Order Reports Pro** is an enterprise-grade WooCommerce extension engineered for high-volume e-commerce stores, fulfillment centers, and Cash on Delivery (COD) operations. It bridges the gap between WooCommerce order management, shipping logistics (featuring dedicated Bosta integration), delivery agent benchmarking, and financial profit reporting.

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
* **Historical Accuracy (Snapshotting):** Captures line item cost of goods at the exact time an order is placed (`_wcorp_wholesale_snapshot`). This ensures accurate past profit reporting even if vendor product prices fluctuate over time.
* **Lazy Freeze Mechanism:** Safely captures missing snapshots when legacy orders reach qualifying statuses.

### 6. Bulk Tools and CSV Importer
* High-performance batch CSV import tool for bulk updating product wholesale prices by `sku`.
* Smart number sanitization supporting international formats (EU/US decimal points and thousands separators).
* Optional retro-active snapshot backfilling for historical orders.

### 7. Performance and Caching Engine
* Built-in transient caching (`WC_Order_Reports_Cache_Manager`) to ensure instant dashboard loading on stores with 100,000+ orders.
* Event-driven cache invalidation upon product catalog updates, settings modifications, or manual one-click purge.
* Asynchronous AJAX data fetching for non-blocking admin experiences.

### 8. Automated Email Digests and Delayed Order Alerts
* Automated daily summary digest dispatched via WP-Cron.
* Real-time admin alert notices when orders remain stuck in the same status beyond configured thresholds (e.g., 48 hours).

---

## Technical Requirements

* **WordPress:** 5.8 or higher
* **WooCommerce:** 5.0 or higher
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

4. **Configure Settings:**
   * Go to **Order Reports -> Settings** (`/wp-admin/admin.php?page=wc-order-reports-pro-settings`).
   * Configure notification thresholds, daily email digest recipients, and wholesale lazy-freeze preferences.

---

## Security and Best Practices

* **Permission Checks:** All admin pages and AJAX endpoints enforce `manage_woocommerce` capability checks.
* **Nonce Verification:** Every AJAX request and export action is verified with nonces (`wcorp_nonce`, `wcorp_export_*`).
* **Input Sanitization:** All request variables and query parameters are strictly sanitized before processing.
* **Database Efficiency:** Queries leverage optimized metadata lookups and WordPress transient caching to prevent server overload.
