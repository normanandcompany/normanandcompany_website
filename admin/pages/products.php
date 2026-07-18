<!-- ========================================= -->
<!-- PRODUCT MANAGEMENT PAGE -->
<!-- File: /admin/pages/products.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="product-manager">

    <div id="page-title-meta"
        data-title="Norman and Company | Product Management"
        style="display: none;">
    </div>

    <div class="product-manager-header">
        <div>
            <h1>Product Management</h1>
        </div>

        <button type="button" class="btn-primary product-add-button" id="addProductBtn">
            Add Product
        </button>
    </div>

    <div class="product-toolbar" aria-label="Product filters">
        <div class="product-search-field">
            <label for="productSearchInput">Search</label>
            <input
                type="search"
                id="productSearchInput"
                placeholder="Name, SKU, or description"
                autocomplete="off"
            >
        </div>

        <div class="product-filter-field">
            <label for="productCategoryFilter">Category</label>
            <select id="productCategoryFilter">
                <option value="all">All Categories</option>
            </select>
        </div>

        <button type="button" class="btn-secondary product-filter-reset" id="resetProductFiltersBtn">
            Reset
        </button>
    </div>

    <div class="product-metrics" aria-label="Product summary">
        <div class="product-metric">
            <span id="productTotalCount">0</span>
            <small>Total</small>
        </div>
        <div class="product-metric">
            <span id="productVisibleCount">0</span>
            <small>Visible</small>
        </div>
        <div class="product-metric">
            <span id="productFilteredCount">0</span>
            <small>Shown</small>
        </div>
    </div>

    <div id="productAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>SKU</th>
                        <th>Category</th>
                        <th>Cost</th>
                        <th>Price</th>
                        <th>Margin</th>
                        <th>Inventory</th>
                        <th>Status</th>
                        <th>Added</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="productsTableBody">
                    <tr>
                        <td colspan="10" class="product-empty-state">Loading products...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="product-pagination">
            <button type="button" id="productPrevPageBtn" class="btn-secondary">
                Previous
            </button>

            <span id="productPageInfo">
                Page 1
            </span>

            <button type="button" id="productNextPageBtn" class="btn-secondary">
                Next
            </button>
        </div>
    </div>

    <dialog id="productFormDialog" class="product-dialog">
        <form id="productForm" class="product-form" enctype="multipart/form-data">
            <input type="hidden" id="productId" name="id">

            <div class="product-dialog-header">
                <div>
                    <h2 id="productFormTitle">Add Product</h2>
                </div>

                <button type="button" class="product-dialog-close" id="closeProductDialogBtn" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="product-form-grid">
                <div class="product-form-main">
                    <div class="form-group stacked">
                        <label for="productName">Product Name</label>
                        <input
                            type="text"
                            id="productName"
                            name="product_name"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="form-group stacked">
                        <label for="productCategoryId">Category</label>
                        <select id="productCategoryId" name="product_category_id" required>
                            <option value="">Select category</option>
                        </select>
                    </div>

                    <div class="form-group stacked" id="productBookFormatGroup" hidden>
                        <label for="productBookFormatId">Book Format</label>
                        <select id="productBookFormatId" name="format_id" disabled>
                            <option value="">Select book format</option>
                        </select>
                    </div>

                    <div class="form-group stacked">
                        <label for="productDescription">Short Description</label>
                        <textarea
                            id="productDescription"
                            name="product_description"
                            rows="3"
                        ></textarea>
                    </div>

                    <div class="form-group stacked">
                        <label for="productLongDescription">Long Description</label>
                        <textarea
                            id="productLongDescription"
                            name="long_description"
                            rows="5"
                        ></textarea>
                    </div>

                    <div class="product-field-row">
                        <div class="form-group stacked">
                            <label for="productCost">Cost</label>
                            <input
                                type="number"
                                id="productCost"
                                name="cost"
                                min="0"
                                step="0.01"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="productPrice">Price</label>
                            <input
                                type="number"
                                id="productPrice"
                                name="price"
                                min="0"
                                step="0.01"
                                required
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="productMargin">Margin</label>
                            <input
                                type="text"
                                id="productMargin"
                                readonly
                            >
                        </div>
                    </div>

                    <div class="product-field-row">
                        <div class="form-group stacked">
                            <label for="productInventory">Inventory</label>
                            <input
                                type="number"
                                id="productInventory"
                                name="inventory_count"
                                min="0"
                                step="1"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="productSeoSlug">SEO Slug</label>
                            <input
                                type="text"
                                id="productSeoSlug"
                                name="seo_slug"
                                maxlength="255"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="productMetaTitle">Meta Title</label>
                            <input
                                type="text"
                                id="productMetaTitle"
                                name="meta_title"
                                maxlength="255"
                            >
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="productMetaDescription">Meta Description</label>
                        <textarea
                            id="productMetaDescription"
                            name="meta_description"
                            rows="3"
                            maxlength="500"
                        ></textarea>
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="product-toggle-grid" aria-label="Product flags">
                        <label class="toggle-row">
                            <input type="checkbox" id="productVisible" name="visible" value="1" checked>
                            <span>Visible</span>
                        </label>

                        <label class="toggle-row">
                            <input type="checkbox" id="productActive" name="is_active" value="1" checked>
                            <span>Active</span>
                        </label>

                        <label class="toggle-row">
                            <input type="checkbox" id="productFeatured" name="is_featured" value="1">
                            <span>Featured</span>
                        </label>

                        <label class="toggle-row">
                            <input type="checkbox" id="productApparel" name="is_apparel" value="1">
                            <span>Apparel</span>
                        </label>
                    </div>

                    <div class="product-images-panel">
                        <div class="product-images-header">
                            <h3>Images</h3>
                            <span id="productImageCount">0 / 10</span>
                        </div>

                        <div id="productImageSlots" class="product-image-slots"></div>
                    </div>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelProductBtn">
                    Cancel
                </button>

                <button type="submit" class="btn-primary" id="saveProductBtn">
                    Save Product
                </button>
            </div>
        </form>
    </dialog>
</section>
