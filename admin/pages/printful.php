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
        <p>Colors remain separate Norman &amp; Company products. Map each enabled local size to exactly one Printful Sync Variant.</p>
        <form id="printfulMappingForm" class="product-form">
            <div class="product-field-row three-column">
                <div class="form-group stacked"><label for="printfulLocalProduct">Local product</label><select id="printfulLocalProduct" name="product_id" required><option value="">Select product</option></select></div>
                <div class="form-group stacked"><label for="printfulSizeType">Variant type</label><select id="printfulSizeType" name="size_type" required><option value="adult">Adult sizes</option><option value="children">Children's sizes</option><option value="none">Standard (no size)</option></select></div>
                <div class="form-group stacked"><label for="printfulSyncProduct">Printful Sync Product</label><select id="printfulSyncProduct" name="external_product_id" required><option value="">Load products first</option></select></div>
            </div>
            <button type="button" class="btn-secondary" data-printful-action="load-products">Load Printful products</button>
            <div id="printfulVariantMappings" class="printful-variant-mappings"><p>Select a product and load its Printful variants.</p></div>
            <p class="product-alert" data-type="warning">Verify that every mapped Printful variant is the same color. The local store intentionally represents each color as a separate product.</p>
            <div class="product-dialog-actions"><button type="submit" class="btn-primary">Save mapping</button></div>
        </form>
    </div>

    <div class="card printful-panel">
        <h2>Children's apparel sizes</h2>
        <form id="printfulChildSizeForm" class="product-field-row three-column"><input type="hidden" name="id"><div class="form-group stacked"><label>Name</label><input name="name" maxlength="50" required></div><div class="form-group stacked"><label>Sort order</label><input name="sort_order" type="number" min="0" value="10"></div><label class="toggle-row"><input name="is_active" type="checkbox" value="1" checked><span>Active</span></label><button class="btn-secondary" type="submit">Save size</button></form>
        <div id="printfulChildSizes"></div>
    </div>

    <div class="card printful-panel">
        <h2>Vendor fulfillments</h2>
        <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Order</th><th>Payment</th><th>Vendor</th><th>Fulfillment</th><th>External order</th><th>Last sync</th><th>Action</th></tr></thead><tbody id="printfulFulfillments"><tr><td colspan="7">Loading…</td></tr></tbody></table></div>
    </div>
</section>
