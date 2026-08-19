<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Caribbean Destinations"
        style="display: none;">
    </div>

    <h1>Caribbean Destinations</h1>

    <!-- ========================================= -->
    <!-- DESTINATIONS DYNAMIC CONTENT CONTAINER -->
    <!-- Javascript will populate this section -->
    <!-- ========================================= -->


    <div id="destinationsContainer" class="card-grid">

        <!-- Loading message -->
            <p id="loadingMessage">Loading destinations...</p>

    </div>

</section>
