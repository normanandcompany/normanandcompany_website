<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Administrator Dashboard"
        style="display: none;">
    </div>

 
    <h1>Dashboard</h1>

    <section class="dashboard-alerts" id="dashboardAlerts" aria-labelledby="dashboardAlertsTitle">
        <div class="dashboard-alerts-header">
            <div>
                <p class="section-kicker">Action required</p>
                <h2 id="dashboardAlertsTitle">Overdue Alerts</h2>
            </div>
            <span class="dashboard-alert-count" data-field="overdue_alert_count" data-format="integer">0</span>
        </div>
        <p class="dashboard-alerts-help">Open tasks past their due date and unresolved contact requests older than 48 hours appear here.</p>
        <div class="dashboard-alert-summary" aria-label="Overdue alert totals">
            <span><strong data-field="overdue_task_count" data-format="integer">0</strong> tasks</span>
            <span><strong data-field="overdue_contact_count" data-format="integer">0</strong> contact requests</span>
        </div>
        <div id="dashboardAlertList" class="dashboard-alert-list" role="list">
            <p class="dashboard-alert-empty">Loading alerts...</p>
        </div>
    </section>

    <div class="dashboard-tabs" role="tablist" aria-label="Dashboard sections">
        <button type="button" class="dashboard-tab active" id="dashboardTabButtonSite" data-dashboard-tab="site-info" role="tab" aria-selected="true" aria-controls="dashboardTabSiteInfo">Site Info</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonProducts" data-dashboard-tab="product-info" role="tab" aria-selected="false" aria-controls="dashboardTabProductInfo">Product Info</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonProductPages" data-dashboard-tab="product-page-info" role="tab" aria-selected="false" aria-controls="dashboardTabProductPageInfo">Product Page Info</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonCustomerReviews" data-dashboard-tab="customer-reviews" role="tab" aria-selected="false" aria-controls="dashboardTabCustomerReviews">Customer Reviews</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonContacts" data-dashboard-tab="contacts" role="tab" aria-selected="false" aria-controls="dashboardTabContacts">Contacts</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonUsers" data-dashboard-tab="users" role="tab" aria-selected="false" aria-controls="dashboardTabUsers">Users</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonFinancial" data-dashboard-tab="financial" role="tab" aria-selected="false" aria-controls="dashboardTabFinancial">Financial</button>
        <button type="button" class="dashboard-tab" id="dashboardTabButtonSales" data-dashboard-tab="sales-funnel" role="tab" aria-selected="false" aria-controls="dashboardTabSales">Sales Funnel</button>
    </div>

    <div class="dashboard-tab-panels">
    
    <!-- ========================================= -->
    <!-- SITE INFO SECTION -->
    <!-- ========================================= -->
    
    <div class="dashboard-tab-panel active" id="dashboardTabSiteInfo" data-dashboard-panel="site-info" role="tabpanel" aria-labelledby="dashboardTabButtonSite">
    <strong class="highlight-strong">Site Info</strong>
    <div class="cloudflare-analytics" id="cloudflareAnalyticsDashboard" data-endpoint="/admin/api/analytics/cloudflare-dashboard.php">
        <div class="cloudflare-analytics-header">
            <div>
                <h2>Cloudflare Website Analytics</h2>
                <p class="cloudflare-analytics-subtitle">Data source: Cloudflare GraphQL Analytics API. Displayed dates use the site timezone; Cloudflare query times are UTC.</p>
            </div>
            <form id="cloudflareAnalyticsForm" class="cloudflare-analytics-controls">
                <label for="cloudflareAnalyticsRange">Date range</label>
                <select id="cloudflareAnalyticsRange" name="range">
                    <option value="today">Today</option>
                    <option value="yesterday" selected>Yesterday</option>
                    <option value="last_7_days">Last 7 days</option>
                    <option value="last_30_days">Last 30 days</option>
                    <option value="this_month">This month</option>
                    <option value="last_month">Last month</option>
                    <option value="custom">Custom range</option>
                </select>
                <div class="cloudflare-custom-range" id="cloudflareCustomRange" hidden>
                    <label for="cloudflareStartDate">Start</label>
                    <input type="date" id="cloudflareStartDate" name="start_date">
                    <label for="cloudflareEndDate">End</label>
                    <input type="date" id="cloudflareEndDate" name="end_date">
                </div>
                <label class="cloudflare-force-refresh">
                    <input type="checkbox" id="cloudflareForceRefresh" name="force_refresh" value="1">
                    Force refresh
                </label>
                <button type="submit" class="btn-primary">Refresh</button>
            </form>
        </div>

        <div class="cloudflare-analytics-note">
            HTTP requests are not page views or people. Cloudflare visits are not exact unique visitors. One person may use multiple IP addresses, several people may share one IP address, and automated requests may be included.
        </div>

        <div id="cloudflareAnalyticsStatus" class="cloudflare-analytics-status" role="status">Loading Cloudflare analytics...</div>
        <div id="cloudflareAnalyticsWarnings" class="cloudflare-analytics-warnings" hidden></div>

        <div class="cloudflare-summary-grid" id="cloudflareSummaryGrid"></div>

        <div class="cloudflare-chart-grid">
            <div class="cloudflare-panel">
                <div class="cloudflare-panel-header">
                    <h3>Requests And Bandwidth</h3>
                    <span id="cloudflareTimeseriesMeta"></span>
                </div>
                <div class="cloudflare-chart-wrap">
                    <canvas id="cloudflareRequestsChart" height="120"></canvas>
                </div>
                <div id="cloudflareRequestsChartFallback" class="cloudflare-empty-state" hidden></div>
            </div>

            <div class="cloudflare-panel">
                <div class="cloudflare-panel-header">
                    <h3>Cached Versus Uncached</h3>
                </div>
                <div class="cloudflare-chart-wrap">
                    <canvas id="cloudflareCacheChart" height="120"></canvas>
                </div>
                <div id="cloudflareCacheChartFallback" class="cloudflare-empty-state" hidden></div>
            </div>
        </div>

        <div class="cloudflare-breakdown-grid">
            <div class="cloudflare-panel" data-breakdown-panel="countries">
                <h3>Top Countries</h3>
                <div data-breakdown-table="countries"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="status_codes">
                <h3>HTTP Status Codes</h3>
                <div data-breakdown-table="status_codes"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="hostnames">
                <h3>Hostnames</h3>
                <div data-breakdown-table="hostnames"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="paths">
                <h3>Top Paths</h3>
                <div data-breakdown-table="paths"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="browsers">
                <h3>Browsers</h3>
                <div data-breakdown-table="browsers"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="devices">
                <h3>Devices</h3>
                <div data-breakdown-table="devices"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="operating_systems">
                <h3>Operating Systems</h3>
                <div data-breakdown-table="operating_systems"></div>
            </div>
            <div class="cloudflare-panel" data-breakdown-panel="security_actions">
                <h3>Security Actions</h3>
                <div data-breakdown-table="security_actions"></div>
            </div>
        </div>
    </div>
    </div>
    
    <!-- ========================================= -->
    <!-- PRODUCT INFO SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabProductInfo" data-dashboard-panel="product-info" role="tabpanel" aria-labelledby="dashboardTabButtonProducts" hidden>
    <strong class="highlight-strong">Product Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Product Count*</h3>
            <span class="metric-value" data-field="product_count" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Top Selling Products</h3>
            <ul class="metric-list" data-product-info-list-field="top_selling_products" data-empty-message="No product sales recorded.">
                <li>No product sales recorded.</li>
            </ul>
        </div>
        <div class="card">
            <h3>Low Selling Products</h3>
            <ul class="metric-list" data-product-info-list-field="low_selling_products" data-empty-message="No products available.">
                <li>No products available.</li>
            </ul>
        </div>
        <div class="card">
            <h3>Low Inventory Products</h3>
            <ul class="metric-list" data-product-info-list-field="low_inventory_products" data-empty-message="No products available.">
                <li>No products available.</li>
            </ul>
        </div>
        <div class="card">
            <h3>Inbound Shipping</h3>
            <span class="metric-value" data-field="inbound_shipping_count" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Outbound Shipping</h3>
            <span class="metric-value" data-field="outbound_shipping_count" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Product Returns</h3>
            <span class="metric-value" data-field="product_returns_count" data-format="integer">0</span>
        </div>
    </div>
    </div>

    <!-- ========================================= -->
    <!-- PRODUCT PAGE INFO SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabProductPageInfo" data-dashboard-panel="product-page-info" role="tabpanel" aria-labelledby="dashboardTabButtonProductPages" hidden>
    <strong class="highlight-strong">Product Page Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Total Product Views</h3>
            <span class="metric-value" data-field="total_product_views">0</span>
        </div>
        <div class="card">
            <h3>Products Viewed</h3>
            <span class="metric-value" data-field="viewed_products_count">0</span>
        </div>
        <div class="card">
            <h3>Products With No Views</h3>
            <span class="metric-value" data-field="products_without_views">0</span>
        </div>
        <div class="card">
            <h3>Avg Views Per Viewed Product</h3>
            <span class="metric-value" data-field="average_views_per_viewed_product">0</span>
        </div>
        <div class="card">
            <h3>Top Product Views</h3>
            <ul class="metric-list" data-list-field="top_product_views">
                <li>No product views recorded.</li>
            </ul>
        </div>
        <div class="card">
            <h3>Recently Viewed Products</h3>
            <ul class="metric-list" data-list-field="recent_product_views">
                <li>No product views recorded.</li>
            </ul>
        </div>
    </div>
    </div>

    <!-- ========================================= -->
    <!-- CUSTOMER REVIEWS INFO SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabCustomerReviews" data-dashboard-panel="customer-reviews" role="tabpanel" aria-labelledby="dashboardTabButtonCustomerReviews" hidden>
    <strong class="highlight-strong">Customer Reviews</strong>
    <div class="card-grid dashboard-review-metrics">
        <div class="card">
            <h3>Total Product Reviews</h3>
            <span class="metric-value" data-field="total_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>Approved Reviews</h3>
            <span class="metric-value" data-field="approved_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>Pending Approval</h3>
            <span class="metric-value" data-field="pending_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>Public Reviews</h3>
            <span class="metric-value" data-field="public_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>Average Rating</h3>
            <span class="metric-value" data-field="average_product_review_rating" data-format="rating">0.0 / 5</span>
        </div>
        <div class="card">
            <h3>4-5 Star Reviews</h3>
            <span class="metric-value" data-field="positive_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>1-2 Star Reviews</h3>
            <span class="metric-value" data-field="low_rating_product_reviews">0</span>
        </div>
        <div class="card">
            <h3>Hidden Reviews</h3>
            <span class="metric-value" data-field="hidden_product_reviews">0</span>
        </div>
    </div>

    <div class="dashboard-review-grid">
        <div class="dashboard-review-panel">
            <div class="dashboard-review-panel-header">
                <h3>Products By Review Volume</h3>
                <span>Review count</span>
            </div>
            <ul class="metric-list dashboard-review-product-list" data-review-list-field="top_reviewed_products">
                <li>No product reviews recorded.</li>
            </ul>
        </div>

        <div class="dashboard-review-panel dashboard-review-table-panel">
            <div class="dashboard-review-panel-header">
                <h3>Recent Product Reviews</h3>
                <span>Pending first</span>
            </div>
            <div class="product-table-scroll">
                <table class="product-table dashboard-review-table">
                    <thead>
                        <tr>
                            <th>Review</th>
                            <th>Product</th>
                            <th>Customer</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody id="dashboardProductReviewsBody">
                        <tr>
                            <td colspan="6" class="product-empty-state">Loading product reviews...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    </div>

    <!-- ========================================= -->
    <!-- CONTACTS SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabContacts" data-dashboard-panel="contacts" role="tabpanel" aria-labelledby="dashboardTabButtonContacts" hidden>
    <strong class="highlight-strong">Contacts</strong>
    <div class="card-grid dashboard-contact-metrics">
        <div class="card">
            <h3>Total Contacts</h3>
            <span class="metric-value" data-field="total_contacts" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Contacts (Last 30 Days)</h3>
            <span class="metric-value" data-field="contacts_last_30_days" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Overdue Contact Requests</h3>
            <span class="metric-value" data-field="overdue_contact_count" data-format="integer">0</span>
        </div>
    </div>

    <div class="product-table-panel dashboard-contact-panel">
        <div class="dashboard-review-panel-header">
            <h3>Contact Form Submissions</h3>
            <span>Newest first</span>
        </div>
        <div class="product-table-scroll">
            <table class="product-table dashboard-contact-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Subject and Message</th>
                        <th>Contact Information</th>
                        <th>Status</th>
                        <th>Submitted</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="dashboardContactsBody">
                    <tr>
                        <td colspan="6" class="product-empty-state">Loading contacts...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <!-- ========================================= -->
    <!-- USER INFO SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabUsers" data-dashboard-panel="users" role="tabpanel" aria-labelledby="dashboardTabButtonUsers" hidden>
    <strong class="highlight-strong">Users</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Total User Count*</h3>
            <span class="metric-value" data-field="total_users">0</span>
        </div>
        <div class="card">
            <h3>New Users (Last 30)*</h3>
            <span class="metric-value" data-field="new_users_last_30_days">0</span>
        </div>
        <div class="card">
            <h3>Updated Users (Last 30)*</h3>
            <span class="metric-value" data-field="updated_users_last_30_days">0</span>
        </div>
        <div class="card">
            <h3>Active Users (Last 30)*</h3>
            <span class="metric-value" data-field="active_users_last_30_days">0</span>
        </div>
        <div class="card">
            <h3>Inactive Users (Last 30)*</h3>
            <span class="metric-value" data-field="inactive_users_last_30_days">0</span>
        </div>
        <div class="card">
            <h3>Removed Users (Last 30)*</h3>
            <span class="metric-value" data-field="removed_users_last_30_days">0</span>
        </div>
    </div>
    </div>


    <!-- ========================================= -->
    <!-- FINANCIAL INFO SECTION -->
    <!-- ========================================= -->

    <div class="dashboard-tab-panel" id="dashboardTabFinancial" data-dashboard-panel="financial" role="tabpanel" aria-labelledby="dashboardTabButtonFinancial" hidden>
    <strong class="highlight-strong">Financial</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Total Sales</h3>
            <span class="metric-value" data-field="total_sales" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Sales (YTD)</h3>
            <span class="metric-value" data-field="total_sales_ytd" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Sales (Last 30)</h3>
            <span class="metric-value" data-field="total_sales_last_30" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Cost</h3>
            <span class="metric-value" data-field="total_cost" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Cost (YTD)</h3>
            <span class="metric-value" data-field="total_cost_ytd" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Cost (Last 30)</h3>
            <span class="metric-value" data-field="total_cost_last_30" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Product Margin</h3>
            <span class="metric-value" data-field="product_margin" data-format="percent">0.0%</span>
        </div>
        <div class="card">
            <h3>Product Margin (YTD)</h3>
            <span class="metric-value" data-field="product_margin_ytd" data-format="percent">0.0%</span>
        </div>
        <div class="card">
            <h3>Product Margin (Last 30)</h3>
            <span class="metric-value" data-field="product_margin_last_30" data-format="percent">0.0%</span>
        </div>
        <div class="card">
            <h3>Total Shipping</h3>
            <span class="metric-value" data-field="total_shipping" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Shipping (YTD)</h3>
            <span class="metric-value" data-field="total_shipping_ytd" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Shipping (Last 30)</h3>
            <span class="metric-value" data-field="total_shipping_last_30" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Returns</h3>
            <span class="metric-value" data-field="total_returns" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Returns (YTD)</h3>
            <span class="metric-value" data-field="total_returns_ytd" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Returns (Last 30)</h3>
            <span class="metric-value" data-field="total_returns_last_30" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Sales Tax</h3>
            <span class="metric-value" data-field="total_sales_tax" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Sales Tax (YTD)</h3>
            <span class="metric-value" data-field="total_sales_tax_ytd" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Sales Tax (Last 30)</h3>
            <span class="metric-value" data-field="total_sales_tax_last_30" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Net Sales</h3>
            <span class="metric-value" data-field="net_sales" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Gross Profit</h3>
            <span class="metric-value" data-field="gross_profit" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Total Discounts</h3>
            <span class="metric-value" data-field="total_discounts" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Payment Fees</h3>
            <span class="metric-value" data-field="total_payment_fees" data-format="currency">$0.00</span>
        </div>
        <div class="card">
            <h3>Transactions</h3>
            <span class="metric-value" data-field="posted_transactions" data-format="integer">0</span>
        </div>
        <div class="card">
            <h3>Average Sale</h3>
            <span class="metric-value" data-field="average_sale" data-format="currency">$0.00</span>
        </div>
    </div>
    </div>
    </div>

    <div class="dashboard-tab-panel" id="dashboardTabSales" data-dashboard-panel="sales-funnel" role="tabpanel" aria-labelledby="dashboardTabButtonSales" hidden>
        <strong class="highlight-strong">Sales Funnel</strong>
        <div class="card-grid">
            <div class="card"><h3>New Leads</h3><span class="metric-value" data-field="crm_new_leads" data-format="integer">0</span></div>
            <div class="card"><h3>Qualified Leads</h3><span class="metric-value" data-field="crm_qualified_leads" data-format="integer">0</span></div>
            <div class="card"><h3>Open Opportunities</h3><span class="metric-value" data-field="crm_open_opportunities" data-format="integer">0</span></div>
            <div class="card"><h3>Pipeline Value</h3><span class="metric-value" data-field="crm_pipeline_value" data-format="currency">$0.00</span></div>
            <div class="card"><h3>Awaiting Payment</h3><span class="metric-value" data-field="crm_awaiting_payment" data-format="integer">0</span></div>
            <div class="card"><h3>Paid Sales Orders</h3><span class="metric-value" data-field="crm_paid_orders" data-format="integer">0</span></div>
            <div class="card"><h3>Sales Order Revenue</h3><span class="metric-value" data-field="crm_sales_order_revenue" data-format="currency">$0.00</span></div>
        </div>
    </div>

    </div>

</section>
