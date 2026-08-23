<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>
<section class="crm-manager" id="crmOrdersApp">
    <div id="page-title-meta" data-title="Norman and Company | Sales Orders" hidden></div>
    <div class="crm-alert product-alert" role="status" aria-live="polite" hidden></div>
    <form class="crm-toolbar" data-crm-filters="orders"><label>Search<input name="search" type="search" placeholder="Order number, customer, company, or email"></label><label>Status<select name="status" data-lookup="order_statuses"><option value="all">All statuses</option></select></label><button class="btn-secondary" type="submit">Apply</button></form>
    <div class="crm-summary" data-crm-summary></div>
    <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Order</th><th>Customer</th><th>Opportunity</th><th>Status</th><th>Total</th><th>Created</th><th>Payment</th></tr></thead><tbody data-crm-rows><tr><td colspan="7">Loading sales orders…</td></tr></tbody></table></div>
    <nav class="product-pagination"><button class="btn-secondary" type="button" data-crm-prev>Previous</button><span data-crm-page>Page 1</span><button class="btn-secondary" type="button" data-crm-next>Next</button></nav>
    <dialog class="product-dialog crm-dialog crm-detail-dialog" data-crm-dialog="order-detail"><div data-crm-detail></div></dialog>
</section>
