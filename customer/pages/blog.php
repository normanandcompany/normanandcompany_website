<!-- ========================================= -->
<!-- BLOG PAGE -->
<!-- File: /pages/blog.php -->
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
        data-title="Norman and Company | Norman's Navy Travel Blog"
        style="display: none;">
    </div>

    <h1>Norman's Navy Travel Blog</h1>

    <ul>
        <li>Passport Holder</li>
        <li>Portable Charger</li>
        <li>Travel Backpack</li>
        <li>Waterproof Phone Case</li>
    </ul>
</section>