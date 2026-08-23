<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>
<section class="email-tools" id="emailToolsApp">
    <div id="page-title-meta" data-title="Norman and Company | Email Tools" hidden></div>
    <div id="emailToolsAlert" class="product-alert" role="status" aria-live="polite" hidden></div>
    <nav class="email-tools-tabs" aria-label="Email tools">
        <button type="button" class="is-active" data-email-tab="newsletter">Monthly Newsletter</button>
        <button type="button" data-email-tab="sales">Sales Campaigns</button>
        <button type="button" data-email-tab="config">Email Configuration</button>
    </nav>

    <section data-email-panel="newsletter">
        <div class="email-tools-grid">
            <form id="emailNewsletterTemplateForm" class="email-tools-card">
                <input type="hidden" name="id" value="">
                <h2 id="emailNewsletterTemplateFormTitle">Create newsletter template</h2>
                <p class="email-tools-help">Save reusable newsletter content, then choose the template in the delivery panel.</p>
                <label>Template name<input name="template_name" maxlength="180" required placeholder="Monthly travel newsletter"></label>
                <label>Email subject<input name="subject" maxlength="255" required placeholder="This month at Norman and Company"></label>
                <label>Newsletter body</label>
                <div class="email-editor-toolbar" aria-label="Newsletter formatting">
                    <button type="button" data-editor-command="bold"><strong>B</strong></button>
                    <button type="button" data-editor-command="italic"><em>I</em></button>
                    <button type="button" data-editor-command="formatBlock" data-editor-value="h2">Heading</button>
                    <button type="button" data-editor-command="insertUnorderedList">List</button>
                    <button type="button" data-editor-link>Link</button>
                    <button type="button" data-editor-unsubscribe>Unsubscribe</button>
                </div>
                <div id="emailNewsletterEditor" class="email-html-editor" contenteditable="true" role="textbox" aria-multiline="true"><p>Hello {FirstName},</p><p>Write your newsletter here.</p></div>
                <textarea name="html_body" hidden></textarea>
                <p class="email-tools-help">Available variables: {FirstName}, {LastName}, {EmailAddress}, {UnsubscribeURL}. Use the Unsubscribe button to place the secure recipient-specific link. If omitted, an unsubscribe link is appended automatically.</p>
                <div class="email-template-form-actions">
                    <button class="btn-primary" id="emailNewsletterTemplateSaveButton" type="submit">Save Newsletter Template</button>
                    <button class="btn-secondary" id="emailNewsletterTemplateCancelButton" type="button" hidden>Cancel Edit</button>
                </div>
            </form>
            <section class="email-tools-card">
                <h2>Send monthly newsletter</h2>
                <form id="emailNewsletterSendForm" class="email-tools-send-form">
                    <label>Delivery name<input name="newsletter_name" maxlength="180" required placeholder="August 2026 Newsletter"></label>
                    <label>Newsletter template<select name="newsletter_template_id" required><option value="">Choose a template</option></select></label>
                    <button class="btn-primary" type="submit">Send Newsletter</button>
                </form>
                <p class="email-tools-help">Sent to active customer accounts. The queue releases one batch every five minutes until exhausted.</p>
                <dl class="email-tools-summary" id="emailNewsletterSummary"></dl>
            </section>
        </div>
        <section class="email-tools-card email-tools-table-card">
            <h2>Newsletter templates</h2>
            <p class="email-tools-help">Edit complete newsletter content or remove templates that have not been used in delivery history.</p>
            <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Template</th><th>Subject</th><th>Last updated</th><th>Actions</th></tr></thead><tbody id="emailNewsletterTemplateRows"></tbody></table></div>
        </section>
        <section class="email-tools-card email-tools-table-card">
            <h2>Newsletter delivery history</h2>
            <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Newsletter</th><th>Template</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Failed / skipped</th></tr></thead><tbody id="emailNewsletterRows"></tbody></table></div>
        </section>
    </section>

    <section data-email-panel="sales" hidden>
        <div class="email-tools-metrics" id="emailSalesMetrics"></div>
        <div class="email-tools-grid email-tools-grid-three">
            <form id="emailLeadImportForm" class="email-tools-card" enctype="multipart/form-data">
                <h2>Import leads</h2>
                <label>Import name<input name="import_name" maxlength="180" required placeholder="August travel agency leads"></label>
                <label>CSV file<input type="file" name="leads_csv" accept=".csv,text/csv" required></label>
                <p class="email-tools-help">Give each upload a recognizable group name. Header columns: FirstName, LastName, Company, EmailAddress. Existing addresses are updated without duplicating leads.</p>
                <button class="btn-primary" type="submit">Import CSV</button>
            </form>
            <form id="emailSignatureForm" class="email-tools-card">
                <h2>HTML signature</h2>
                <label>Signature name<input name="signature_name" maxlength="120" required></label>
                <label>HTML signature<textarea name="html_body" rows="7" required placeholder="&lt;strong&gt;Your Name&lt;/strong&gt;&lt;br&gt;Norman and Company"></textarea></label>
                <label class="email-tools-check"><input type="checkbox" name="is_default" value="1"> Default signature</label>
                <button class="btn-primary" type="submit">Save Signature</button>
            </form>
            <form id="emailTemplateForm" class="email-tools-card">
                <input type="hidden" name="id" value="">
                <h2 id="emailTemplateFormTitle">Outreach template</h2>
                <label>Template name<input name="template_name" maxlength="180" required></label>
                <label>{Subject}<input name="subject_template" maxlength="255" required></label>
                <label>{Body}<textarea name="body_template" rows="8" required></textarea></label>
                <label>HTML signature<select name="signature_id"><option value="">No signature</option></select></label>
                <p class="email-tools-help">Variables: {FirstName}, {LastName}, {Company}, {EmailAddress}.</p>
                <div class="email-template-form-actions">
                    <button class="btn-primary" id="emailTemplateSaveButton" type="submit">Save Template</button>
                    <button class="btn-secondary" id="emailTemplateCancelButton" type="button" hidden>Cancel Edit</button>
                </div>
            </form>
        </div>
        <section class="email-tools-card">
            <h2>Send campaign</h2>
            <form id="emailCampaignForm" class="email-campaign-form">
                <label>Campaign name<input name="campaign_name" maxlength="180" required></label>
                <label>Email template<select name="template_id" required><option value="">Choose a template</option></select></label>
                <label>Audience<select name="audience"><option value="all">All eligible CRM leads</option><option value="import">Selected CSV import</option><option value="filtered">Filtered CRM leads</option></select></label>
                <label>CSV import<select name="lead_import_id"><option value="">Any import / not required</option></select></label>
                <label>Lead status<select name="lead_status"><option value="">Any status</option><option>New</option><option>Contacted</option><option>Nurturing</option><option>Qualified</option><option>Disqualified</option><option>Converted</option></select></label>
                <label>Lead source<input name="lead_source" maxlength="80" placeholder="Any source"></label>
                <label>Opportunity<select name="opportunity_state"><option value="">With or without</option><option value="open">Open opportunities</option><option value="none">Without opportunities</option><option value="lost">Lost opportunities</option><option value="customer">Existing customers</option></select></label>
                <button class="btn-primary" type="submit">Send Campaign</button>
            </form>
            <p class="email-tools-help">Suppressed, unsubscribed, do-not-contact, and invalid leads are always excluded. The server sends one email every three minutes.</p>
        </section>
        <section class="email-tools-card email-tools-table-card">
            <h2>CSV imports</h2>
            <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Import</th><th>File</th><th>Leads</th><th>Invalid rows</th><th>Imported</th></tr></thead><tbody id="emailLeadImportRows"></tbody></table></div>
        </section>
        <div class="email-tools-grid">
            <section class="email-tools-card email-tools-table-card">
                <h2>Campaign statistics</h2>
                <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Campaign</th><th>CSV import</th><th>Template</th><th>Status</th><th>Queued</th><th>Sent</th><th>Failed / skipped</th></tr></thead><tbody id="emailCampaignRows"></tbody></table></div>
            </section>
            <section class="email-tools-card email-tools-table-card">
                <h2>Recent leads</h2>
                <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Lead</th><th>Company</th><th>Status</th><th>Emails sent</th><th>Last contacted</th></tr></thead><tbody id="emailLeadRows"></tbody></table></div>
            </section>
        </div>
        <section class="email-tools-card email-tools-table-card">
            <h2>Sales email templates</h2>
            <p class="email-tools-help">Edit every part of an outreach template or remove templates that have not been used by a campaign.</p>
            <div class="product-table-scroll"><table class="product-table"><thead><tr><th>Template</th><th>Subject</th><th>Body preview</th><th>Signature</th><th>Updated</th><th>Actions</th></tr></thead><tbody id="emailSalesTemplateRows"></tbody></table></div>
        </section>
    </section>

    <section data-email-panel="config" hidden>
        <div id="emailConfigAlert" class="product-alert" role="status" aria-live="polite" hidden></div>
        <form id="emailConfigForm" class="email-tools-card email-config-form">
            <div class="email-config-heading">
                <div><h2>Email Configuration</h2><p class="email-tools-help">Newsletter and sales messages use completely separate mail-server accounts.</p></div>
                <span id="emailConfigStatus" class="email-tools-status">Checking configuration…</span>
            </div>
            <fieldset class="email-config-section">
                <legend>Monthly newsletter account</legend>
                <div class="email-config-grid">
                    <label>SMTP host<input name="newsletter_smtp_host" maxlength="255" required placeholder="mail.example.com" autocomplete="off"></label>
                    <label>SMTP port<input name="newsletter_smtp_port" type="number" min="1" max="65535" required value="587"></label>
                    <label>Encryption<select name="newsletter_smtp_encryption"><option value="tls">STARTTLS</option><option value="ssl">Implicit TLS/SSL</option><option value="none">None</option></select></label>
                    <label>Authentication<select name="newsletter_smtp_auth"><option value="login">LOGIN</option><option value="plain">PLAIN</option></select></label>
                    <label>SMTP username<input name="newsletter_smtp_username" maxlength="255" required autocomplete="username"></label>
                    <label>SMTP password<input name="newsletter_smtp_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
                    <label>From email<input name="newsletter_from_email" type="email" required value="newsletters@normanandcompany.com"></label>
                    <label>From name<input name="newsletter_from_name" maxlength="180" required value="Norman and Company Newsletter"></label>
                    <label>Reply-To email<input name="newsletter_reply_to" type="email" required value="newsletters@normanandcompany.com"></label>
                </div>
                <p class="email-tools-help" id="newsletterPasswordHelp">The newsletter password is encrypted and is never returned to the browser.</p>
            </fieldset>
            <fieldset class="email-config-section">
                <legend>Sales campaign account</legend>
                <div class="email-config-grid">
                    <label>SMTP host<input name="sales_smtp_host" maxlength="255" required placeholder="mail.example.com" autocomplete="off"></label>
                    <label>SMTP port<input name="sales_smtp_port" type="number" min="1" max="65535" required value="587"></label>
                    <label>Encryption<select name="sales_smtp_encryption"><option value="tls">STARTTLS</option><option value="ssl">Implicit TLS/SSL</option><option value="none">None</option></select></label>
                    <label>Authentication<select name="sales_smtp_auth"><option value="login">LOGIN</option><option value="plain">PLAIN</option></select></label>
                    <label>SMTP username<input name="sales_smtp_username" maxlength="255" required autocomplete="username"></label>
                    <label>SMTP password<input name="sales_smtp_password" type="password" autocomplete="new-password" placeholder="Leave blank to keep current password"></label>
                    <label>From email<input name="sales_from_email" type="email" required></label>
                    <label>From name<input name="sales_from_name" maxlength="180" required value="Norman and Company"></label>
                    <label>Reply-To email<input name="sales_reply_to" type="email" required></label>
                </div>
                <p class="email-tools-help" id="salesPasswordHelp">The sales password is encrypted separately and is never returned to the browser.</p>
            </fieldset>
            <p class="email-tools-help">A protected encryption key is created outside the website automatically when permitted.</p>
            <div><button class="btn-primary" type="submit">Save Email Configuration</button></div>
        </form>
    </section>
</section>
