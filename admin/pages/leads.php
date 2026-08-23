<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>
<section class="crm-manager" id="crmLeadsApp">
    <div id="page-title-meta" data-title="Norman and Company | Leads" hidden></div>
    <div class="crm-page-actions"><button class="btn-primary" type="button" data-crm-open="lead-form">Create Lead</button></div>
    <div class="crm-alert product-alert" role="status" aria-live="polite" hidden></div>
    <form class="crm-toolbar" data-crm-filters="leads">
        <label>Search<input name="search" type="search" placeholder="Name, company, email, or phone"></label>
        <label>Status<select name="status" data-lookup="lead_statuses"><option value="all">All statuses</option></select></label>
        <label>Source<select name="source" data-lookup="lead_sources"><option value="all">All sources</option></select></label>
        <label>Owner<select name="owner" data-user-options><option value="all">All owners</option></select></label>
        <label>Email<select name="eligibility"><option value="all">All</option><option value="eligible">Eligible</option><option value="suppressed">Suppressed</option></select></label>
        <button class="btn-secondary" type="submit">Apply</button>
    </form>
    <div class="crm-summary" data-crm-summary></div>
    <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Lead</th><th>Company</th><th>Status</th><th>Source</th><th>Owner / Follow-up</th><th>Email</th><th>Actions</th></tr></thead><tbody data-crm-rows><tr><td colspan="7">Loading leads…</td></tr></tbody></table></div>
    <nav class="product-pagination"><button class="btn-secondary" type="button" data-crm-prev>Previous</button><span data-crm-page>Page 1</span><button class="btn-secondary" type="button" data-crm-next>Next</button></nav>

    <dialog class="product-dialog crm-dialog" data-crm-dialog="lead-form"><form class="product-form" data-crm-form="save_lead">
        <input type="hidden" name="id"><header class="product-dialog-header"><div><p class="section-kicker">Lead record</p><h2 data-form-title>Create Lead</h2></div><button type="button" class="product-dialog-close" data-dialog-close>&times;</button></header>
        <div class="crm-form-grid">
            <label>First name<input name="first_name" maxlength="100" required></label><label>Last name<input name="last_name" maxlength="100"></label><label>Company<input name="company" maxlength="180"></label><label>Job title<input name="job_title" maxlength="140"></label>
            <label>Email<input name="email_address" type="email" required></label><label>Phone<input name="phone" maxlength="50"></label><label>Status<select name="lead_status" data-lookup="lead_statuses"></select></label><label>Source<select name="lead_source" data-lookup="lead_sources"></select></label>
            <label>Owner<select name="assigned_user_id" data-user-options><option value="">Unassigned</option></select></label><label>Priority<select name="priority" data-lookup="priorities"></select></label><label>Qualification score<input name="qualification_score" type="number" min="0" max="100"></label><label>Next follow-up<input name="next_follow_up_at" type="datetime-local"></label>
            <label class="crm-span">Notes<textarea name="notes" rows="4"></textarea></label>
        </div><footer class="crm-form-actions"><button class="btn-primary" type="submit">Save Lead</button></footer>
    </form></dialog>

    <dialog class="product-dialog crm-dialog crm-detail-dialog" data-crm-dialog="lead-detail"><div data-crm-detail></div></dialog>
</section>
