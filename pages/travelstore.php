<!-- ========================================= -->
<!-- TRAVELSTORE PAGE -->
<!-- File: /pages/travelstore.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Caribbean Travel Store"
        style="display: none;">
    </div>

    <h1>Caribbean Travel Store</h1>

    <div class="filter-container">
        <label for="categoryFilter">Category:</label>
        <select id="categoryFilter">
            <option value="all">All Products</option>
            <option value="1">Apparel & Fashion</option>
            <option value="5">Beach Accessories</option>
            <option value="9">Books</option>
            <option value="8">Cruise Essentials</option>
            <option value="3">Health & Wellness</option>
            <option value="7">Kids' Collection</option>
            <option value="6">Novelty Items</option>
            <option value="2">Stickers & Decals</option>
            <option value="4">Travel Accessories</option>
        </select>
    </div>

    <!-- PRODUCT CONTAINER -->
    <div id="ProductContainer">
        
        <!-- PRODUCT GRID VIEW -->
        <div id="productGridView" class="card-grid">

            <!-- Loading Message -->
            <p id="loadingMessage">Loading products...</p>

        </div>

        <!-- PRODUCT DETAILS VIEW --> 
        <div id="productDetailsView" class="hidden"></div>

    </div>

</section>
