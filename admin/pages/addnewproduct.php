<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="content-section">

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Add New Product"
        style="display: none;">
    </div>

    <!-- PAGE TITLE -->

    <h1>Add New Product</h1>

    <!-- ADD NEW PRODUCT CARD -->

    <div class="card">
        <form id="addnewproductForm">

            <!-- PRODUCT DESCRIPTION -->

            <div class="form-group">
                <label for="productDescription">
                    Product Description:
                </label>
                <input
                    type="text"
                    id="productDescription"
                    name="productDescription"
                    placeholder="Enter Product Description"
                    size="50"
                    required
                >
            </div>

            <!-- PRODUCT CATEGORY -->

            <div class="form-group">
                <label for="productCategory">
                    Product Category:
                </label>
                <select
                    id="productCategory"
                    name="productCategory"
                    required
                >
                    <option value="">
                        Select the product category
                    </option>

                    <option value="Apparel & Fashion">
                        Apparel & Fashion
                    </option>
                    <option value="Beach Accessories">
                        Beach Accessories
                    </option>
                    <option value="Cruise Essentials">
                        Cruise Essentials
                    </option>
                    <option value="Health & Wellness">
                        Health & Wellness
                    </option>
                    <option value="Kids' Collection">
                        Kids' Collection
                    </option>
                    <option value="Novelty Items">
                        Novelty Items
                    </option>
                    <option value="Stickers & Decals">
                        Stickers & Decals
                    </option>
                    <option value="Travel Accessories">
                        Travel Accessories
                    </option>
                </select>
            </div>

            <!-- PRODUCT COST -->

            <div class="form-group">
                <label for="productCost">
                    Product Cost:
                </label>
                <input
                    type="number"
                    id="productCost"
                    name="productCost"
                    placeholder="13.95"
                    step="0.01"
                >
            </div>

            <!-- PRODUCT PRICE -->

            <div class="form-group">
                <label for="productPrice">
                    Product Price:
                </label>
                <input
                    type="number"
                    id="productPrice"
                    name="productPrice"
                    placeholder="19.95"
                    step="0.01"
                >
            </div>

            <!-- PRODUCT MARGIN -->

            <div class="form-group">
                <label for="productMargin">
                    Product Margin (%):
                </label>
                <input
                    type="number"
                    id="productMargin"
                    name="productMargin"
                    placeholder="26"
                    step="0.01"
                >
            </div>

            <!-- PRODUCT IMAGE -->

            <div class="form-group">
                <label for="productImage">
                    Product Image:
                </label>

                <input 
                    type="file"
                    name="productImage"
                    accept="image/*"
                    class="btn-primary"
                    >
            </div>
                

            <!-- SUBMIT BUTTON -->

            <div class="form-actions">
                <button
                    type="submit"
                    class="btn-primary"
                >
                    Submit
                </button>

            </div>
        </form>

    </div>

</section>