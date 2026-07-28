<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | API & Scraper Management"
        style="display: none;">
    </div>

    <h1>API & Scraper Management</h1>

    <div class="card-grid">

        <div class="card">
            <h3>Waterproof Backpack</h3>
            <p>Perfect for Caribbean shore excursions.</p>
            <p><strong>$79.99</strong></p>
        </div>

        <div class="card">
            <h3>Cruise Packing Cubes</h3>
            <p>Stay organized while cruising.</p>
            <p><strong>$24.99</strong></p>
        </div>

    </div>
</section>