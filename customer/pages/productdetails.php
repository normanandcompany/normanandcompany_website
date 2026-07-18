<!-- ========================================= -->
<!-- PRODUCT DETAILS PAGE -->
<!-- File: /pages/productdetails.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Caribbean Travel Store"
        style="display: none;">
    </div>

    <h1>Caribbean Travel Store</h1>

    <div id="productContainer" class="card-grid">

        <!-- Loading Message -->
            <p id="loadingMessage">Loading product details...</p>

    </div>

</section>