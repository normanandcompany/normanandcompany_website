<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>
<section class="product-manager printful-manager" id="printfulAdminApp">
    <div id="page-title-meta" data-title="Norman and Company | Printful Fulfillment" hidden></div>
    <div class="product-manager-header"><div><p class="section-kicker">Vendor Fulfillment</p><h1>Printful</h1></div><button class="btn-secondary" type="button" data-printful-action="refresh">Refresh</button></div>
    <div id="printfulAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-metrics" id="printfulDiagnostics"><div class="product-metric"><span>…</span><small>Connection</small></div></div>

    <div class="card printful-panel">
        <h2>Product and variant mapping</h2>
        <p>The product's size catalog comes from Product Management. Choose the matching Printful product, then review the exact variants used for fulfillment.</p>
        <form id="printfulMappingForm" class="product-form">
            <div class="product-field-row two-column">
                <div class="form-group stacked"><label for="printfulLocalProduct">Local product</label><select id="printfulLocalProduct" name="product_id" required><option value="">Select product</option></select></div>
                <div class="form-group stacked"><label for="printfulSyncProduct">Printful Sync Product</label><select id="printfulSyncProduct" name="external_product_id" required><option value="">Load products first</option></select></div>
            </div>
            <p id="printfulProductSizingSummary" class="printful-sizing-summary">Select a local product to see its size catalog.</p>
            <button type="button" class="btn-secondary" data-printful-action="load-products">Load Printful products</button>
            <details class="printful-mapping-review">
                <summary>Fulfillment variant review <span id="printfulMappingReviewStatus"></span></summary>
                <div id="printfulVariantMappings" class="printful-variant-mappings"><p>Select a product and load its Printful variants.</p></div>
            </details>
            <p class="product-alert" data-type="warning">Verify that every mapped Printful variant is the same color. The local store intentionally represents each color as a separate product.</p>
            <div class="product-dialog-actions"><button type="submit" class="btn-primary">Save mapping</button></div>
        </form>

        <section class="printful-pricing-tools" aria-labelledby="printfulPricingTitle">
            <div>
                <h3 id="printfulPricingTitle">Variant pricing</h3>
                <p>Preview pricing for the selected local product after its mapping has been saved.</p>
            </div>
            <div class="product-field-row two-column">
                <div class="form-group stacked">
                    <label for="printfulPricingMode">Pricing rule</label>
                    <select id="printfulPricingMode">
                        <option value="difference">Base price + Printful size difference (recommended)</option>
                        <option value="retail">Copy Printful retail prices exactly</option>
                    </select>
                </div>
                <div class="printful-pricing-actions">
                    <button type="button" class="btn-secondary" data-printful-action="preview-pricing">Preview pricing</button>
                    <button type="button" class="btn-primary" data-printful-action="apply-pricing" hidden>Apply pricing</button>
                </div>
            </div>
            <div id="printfulPricingPreview"><p>Select a mapped apparel product to preview its variant prices.</p></div>
        </section>
    </div>

    <div class="card printful-panel">
        <h2>Global size catalogs</h2>
        <p>These tables control the adult and children's sizes available for product mapping. Make a size inactive instead of deleting it so historical orders remain readable.</p>
        <div class="printful-size-catalogs">
            <section>
                <h3>Adult apparel sizes</h3>
                <form class="printful-size-form" data-printful-size-form="adult">
                    <input type="hidden" name="id"><input type="hidden" name="size_type" value="adult">
                    <div class="form-group stacked"><label>Size name</label><input name="name" maxlength="50" required></div>
                    <div class="form-group stacked"><label>Sort order</label><input name="sort_order" type="number" min="0" value="10"></div>
                    <label class="toggle-row"><input name="is_active" type="checkbox" value="1" checked><span>Active</span></label>
                    <button class="btn-secondary" type="submit">Save adult size</button>
                </form>
                <div id="printfulAdultSizes" class="printful-size-list"></div>
            </section>
            <section>
                <h3>Children's apparel sizes</h3>
                <form class="printful-size-form" data-printful-size-form="children">
                    <input type="hidden" name="id"><input type="hidden" name="size_type" value="children">
                    <div class="form-group stacked"><label>Size name</label><input name="name" maxlength="50" required></div>
                    <div class="form-group stacked"><label>Sort order</label><input name="sort_order" type="number" min="0" value="10"></div>
                    <label class="toggle-row"><input name="is_active" type="checkbox" value="1" checked><span>Active</span></label>
                    <button class="btn-secondary" type="submit">Save children's size</button>
                </form>
                <div id="printfulChildSizes" class="printful-size-list"></div>
            </section>
        </div>
    </div>

    <div class="card printful-panel">
        <h2>Vendor fulfillments</h2>
        <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Order</th><th>Payment</th><th>Vendor</th><th>Fulfillment</th><th>External order</th><th>Last sync</th><th>Action</th></tr></thead><tbody id="printfulFulfillments"><tr><td colspan="7">Loading…</td></tr></tbody></table></div>
    </div>
</section>
