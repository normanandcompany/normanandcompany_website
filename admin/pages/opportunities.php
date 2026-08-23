<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>
<section class="crm-manager" id="crmOpportunitiesApp">
    <div id="page-title-meta" data-title="Norman and Company | Opportunities" hidden></div>
    <div class="crm-alert product-alert" role="status" aria-live="polite" hidden></div>
    <form class="crm-toolbar" data-crm-filters="opportunities"><label>Search<input name="search" type="search" placeholder="Opportunity, contact, or company"></label><label>Stage<select name="stage" data-lookup="opportunity_stages"><option value="all">All stages</option></select></label><label>State<select name="state"><option value="all">Open and closed</option><option value="open">Open</option><option value="closed">Won / Lost</option></select></label><button class="btn-secondary" type="submit">Apply</button></form>
    <div class="crm-summary" data-crm-summary></div>
    <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Opportunity</th><th>Lead / Company</th><th>Stage</th><th>Probability</th><th>Value</th><th>Close date</th><th>Products / Orders</th></tr></thead><tbody data-crm-rows><tr><td colspan="7">Loading opportunities…</td></tr></tbody></table></div>
    <nav class="product-pagination"><button class="btn-secondary" type="button" data-crm-prev>Previous</button><span data-crm-page>Page 1</span><button class="btn-secondary" type="button" data-crm-next>Next</button></nav>
    <dialog class="product-dialog crm-dialog crm-detail-dialog" data-crm-dialog="opportunity-detail"><div data-crm-detail></div></dialog>
</section>
