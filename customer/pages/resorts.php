<!-- ========================================= -->
<!-- RESORTS PAGE -->
<!-- File: /pages/resorts.php -->
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
        data-title="Norman and Company | Caribbean Resorts"
        style="display: none;">
    </div>

    <h1>Caribbean Resorts</h1>

    <!-- ========================================= -->
    <!-- RESORTS DYNAMIC CONTENT CONTAINER -->
    <!-- Javascript will populate this section -->
    <!-- ========================================= -->


    <div id="resortsContainer" class="card-grid">

        <! -- Loading message -->
            <p id="loadingMessage">Loading resorts...</p>

    </div>

    <!-- ========================================= -->
    <!-- PAGE-SPECIFIC SCRIPT -->
    <!-- Loads resort JSON data -->
    <!-- ========================================= -->

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadResorts();
        });
    </script>
    
</section>