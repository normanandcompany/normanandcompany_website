<!-- ========================================= -->
<!-- FAQ PAGE -->
<!-- File: /pages/faq.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<section>
    
    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Frequently Asked Questions"
        style="display: none;">
    </div>

    <h1>Frequently Asked Questions</h1>

    <!-- ========================================= -->
    <!-- RESORTS DYNAMIC CONTENT CONTAINER -->
    <!-- Javascript will populate this section -->
    <!-- ========================================= -->


    <div id="faqContainer" class="card-grid">

        <! -- Loading message -->
            <p id="loadingMessage">Loading FAQ...</p>

    </div>

    <!-- ========================================= -->
    <!-- PAGE-SPECIFIC SCRIPT -->
    <!-- Loads faq JSON data -->
    <!-- ========================================= -->

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadFAQ();
        });
    </script>
    
</section>
