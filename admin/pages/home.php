<!-- ========================================= -->
<!-- DASHBOARD PAGE -->
<!-- File: /admin/pages/home.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

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
    
    <!-- ========================================= -->
    <!-- SITE INFO SECTION -->
    <!-- ========================================= -->
    
    <strong class="highlight-strong">Site Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Site Views</h3>
            <span class="metric-value">5300</span>
        </div>
        <div class="card">
            <h3>Unique Visitors</h3>
            <span class="metric-value">2100</span>
        </div>
        <div class="card">
            <h3>Bounce Rate</h3>
            <span class="metric-value">21%</span>
        </div>
        <div class="card">
            <h3>Conversion Rate</h3>
            <span class="metric-value">32%</span>
        </div>
        <div class="card">
            <h3>Traffic Sources</h3>
            <ul class="metric-list">
                <li>Google</li>
                <li>Facebook</li>
            </ul>
        </div>
        <div class="card">
            <h3>Traffic Source Type</h3>
            <ul class="metric-list">
                <li>Organic - 34%</li>
                <li>Direct - 15%</li>
                <li>Referral - 32%</li>
                <li>Paid - 19%</li>
            </ul>
        </div>
        <div class="card">
            <h3>Avg Session Duration</h3>
            <span class="metric-value">29 minutes</span>
        </div>
        <div class="card">
            <h3>Top Pages By Traffic</h3>
            <ul class="metric-list">
                <li>Home</li>
                <li>About Us</li>
                <li>Products - Norman Plushie</li>
        </div>
    </div>
    
    <!-- ========================================= -->
    <!-- PRODUCT INFO SECTION -->
    <!-- ========================================= -->

    <strong class="highlight-strong">Product Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Product Count*</h3>
            <span class="metric-value" data-field="product_count">0</span>
        </div>
        <div class="card">
            <h3>Top Selling Products</h3>
            <ul class="metric-list">
                <li>Medical Kit</li>
                <li>Mini Normans</li>
            </ul>
        </div>
        <div class="card">
            <h3>Low Selling Products</h3>
            <ul class="metric-list">
                <li>Norman Coloring Book</li>
                <li>Norman Keyring</li>
            </ul>
        </div>
        <div class="card">
            <h3>Low Inventory Products</h3>
            <ul class="metric-list">
                <li>Medical Kit</li>
                <li>Norman Plushie</li>
            </ul>
        </div>
        <div class="card">
            <h3>Inbound Shipping</h3>
            <span class="metric-value">19</span>
        </div>
        <div class="card">
            <h3>Outbound Shipping</h3>
            <span class="metric-value">321</span>
        </div>
        <div class="card">
            <h3>Product Returns</h3>
            <span class="metric-value">12</span>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- PRODUCT PAGE INFO SECTION -->
    <!-- ========================================= -->

    <strong class="highlight-strong">Product Page Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Total Product Views</h3>
            <span class="metric-value">1900</span>
        </div>
        <div class="card">
            <h3>Top Product Views</h3>
            <ul class="metric-list">
                <li>Medical Kit</li>
                <li>Norman Plushie</li>
            </ul>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- CUSTOMER REVIEWS INFO SECTION -->
    <!-- ========================================= -->

    <strong class="highlight-strong">Customer Reviews Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Positive Product Reviews</h3>
            <span class="metric-value">12</span>
        </div>
        <div class="card">
            <h3>Negative Product Reviews</h3>
            <span class="metric-value">2</span>
        </div>
        <div class="card">
            <h3>Total Customer Contacts</h3>
            <span class="metric-value">53</span>
        </div>
        <div class="card">
            <h3>Travel Questions</h3>
            <span class="metric-value">15</span>
        </div>
        <div class="card">
            <h3>Product Questions</h3>
            <span class="metric-value">12</span>
        </div>
        <div class="card">
            <h3>Partnership Inquiries</h3>
            <span class="metric-value">3</span>
        </div>
        <div class="card">
            <h3>General Inquiries</h3>
            <span class="metric-value">23</span>
        </div>
        <div class="card">
            <h3>Customer Complaints</h3>
            <span class="metric-value">1</span>
        </div>
    </div>

    <!-- ========================================= -->
    <!-- USER INFO SECTION -->
    <!-- ========================================= -->

    <strong class="highlight-strong">User Info</strong>
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


    <!-- ========================================= -->
    <!-- FINANCIAL INFO SECTION -->
    <!-- ========================================= -->

    <strong class="highlight-strong">Financial Info</strong>
    <div class="card-grid">
        <div class="card">
            <h3>Total Sales</h3>
            <span class="metric-value">$15,000</span>
        </div>
        <div class="card">
            <h3>Total Sales (YTD)</h3>
            <span class="metric-value">$5,000</span>
        </div>
        <div class="card">
            <h3>Total Sales (Last 30)</h3>
            <span class="metric-value">$3,000</span>
        </div>
        <div class="card">
            <h3>Total Cost</h3>
            <span class="metric-value">$15,000</span>
        </div>
        <div class="card">
            <h3>Total Cost (YTD)</h3>
            <span class="metric-value">$10,200</span>
        </div>
        <div class="card">
            <h3>Total Cost (Last 30)</h3>
            <span class="metric-value">$3,000</span>
        </div>
        <div class="card">
            <h3>Product Margin</h3>
            <span class="metric-value">15%</span>
        </div>
        <div class="card">
            <h3>Product Margin (YTD)</h3>
            <span class="metric-value">17%</span>
        </div>
        <div class="card">
            <h3>Product Margin (Last 30)</h3>
            <span class="metric-value">16%</span>
        </div>
        <div class="card">
            <h3>Total Shipping</h3>
            <span class="metric-value">$1,000</span>
        </div>
        <div class="card">
            <h3>Total Shipping (YTD)</h3>
            <span class="metric-value">$500</span>
        </div>
        <div class="card">
            <h3>Total Shipping (Last 30)</h3>
            <span class="metric-value">$300</span>
        </div>
        <div class="card">
            <h3>Total Returns</h3>
            <span class="metric-value">$100</span>
        </div>
        <div class="card">
            <h3>Total Returns (YTD)</h3>
            <span class="metric-value">$50</span>
        </div>
        <div class="card">
            <h3>Total Returns (Last 30)</h3>
            <span class="metric-value">$30</span>
        </div>
        <div class="card">
            <h3>Total Sales Tax</h3>
            <span class="metric-value">$500</span>
        </div>
        <div class="card">
            <h3>Total Sales Tax (YTD)</h3>
            <span class="metric-value">$200</span>
        </div>
        <div class="card">
            <h3>Total Cost (Last 30)</h3>
            <span class="metric-value">$30</span>
        </div>
    </div>

</section>