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

    <div class="filter-container">
        <label for="categoryFilter">Category:</label>
        <select id="categoryFilter">
            <option value="all">All Products</option>
            <option value="1">Apparel & Fashion</option>
            <option value="5">Beach Accessories</option>
            <option value="8">Cruise Essentials</option>
            <option value="3">Health & Wellness</option>
            <option value="7">Kids' Collection</option>
            <option value="6">Novelty Items</option>
            <option value="2">Stickers & Decals</option>
            <option value="4">Travel Accessories</option>
        </select>
    </div>

    <div id="productContainer" class="card-grid">

        <!-- Loading Message -->
            <p id="loadingMessage">Loading products...</p>

    </div>

</section>
