<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';
requireRole('admin');
?>

<section class="product-manager download-manager" id="downloadManager">
    <div id="page-title-meta" data-title="Norman and Company | Downloads Management" hidden></div>

    <div class="product-manager-header">
        <div>
            <h1>Downloads</h1>
        </div>
        <button type="button" class="btn-primary product-add-button" id="addDownloadBtn">Add Download</button>
    </div>

    <div class="product-metrics" aria-label="Download summary">
        <div class="product-metric"><span id="downloadAvailableCount">0</span><small>Available downloads</small></div>
        <div class="product-metric"><span id="downloadTotalCount">0</span><small>Total downloads</small></div>
        <div class="product-metric"><span id="downloadRecordCount">0</span><small>All records</small></div>
    </div>

    <div class="product-toolbar download-toolbar" aria-label="Download filters">
        <div class="product-search-field">
            <label for="downloadSearchInput">Search</label>
            <input type="search" id="downloadSearchInput" placeholder="Title, description, or filename" autocomplete="off">
        </div>
        <div class="product-filter-field">
            <label for="downloadVisibilityFilter">Visibility</label>
            <select id="downloadVisibilityFilter">
                <option value="all">All downloads</option>
                <option value="visible">Viewable</option>
                <option value="hidden">Hidden</option>
            </select>
        </div>
        <button type="button" class="btn-secondary product-filter-reset" id="resetDownloadFiltersBtn">Reset</button>
    </div>

    <div id="downloadAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table download-table">
                <thead><tr><th>Title</th><th>Category</th><th>Filename</th><th>Viewable</th><th>Link</th><th>Downloads</th><th>Actions</th></tr></thead>
                <tbody id="downloadsTableBody"><tr><td colspan="7" class="product-empty-state">Loading downloads...</td></tr></tbody>
            </table>
        </div>
    </div>

    <dialog id="downloadFormDialog" class="product-dialog download-dialog">
        <form id="downloadForm" class="product-form" enctype="multipart/form-data">
            <input type="hidden" id="downloadId" name="id">
            <input type="hidden" name="action" value="save">

            <div class="product-dialog-header">
                <div><p class="section-kicker">Download details</p><h2 id="downloadFormTitle">Add Download</h2></div>
                <button type="button" class="product-dialog-close" id="closeDownloadDialogBtn" aria-label="Close">&times;</button>
            </div>

            <div class="product-form-grid">
                <div class="product-form-main">
                    <div class="form-group stacked">
                        <label for="downloadTitle">Title</label>
                        <input type="text" id="downloadTitle" name="title" maxlength="180" required>
                    </div>
                    <div class="form-group stacked">
                        <label for="downloadCategory">Download category</label>
                        <select id="downloadCategory" name="download_category_id" required>
                            <option value="">Select a category</option>
                        </select>
                    </div>
                    <div class="form-group stacked">
                        <label for="downloadDescription">Description</label>
                        <textarea id="downloadDescription" name="description" rows="6" maxlength="10000" required></textarea>
                    </div>
                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="downloadFilename">Filename</label>
                            <input type="text" id="downloadFilename" name="filename" maxlength="255" placeholder="Uses uploaded filename when blank">
                        </div>
                        <div class="form-group stacked">
                            <label for="downloadFile">Upload file</label>
                            <input type="file" id="downloadFile" name="download_file" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.csv,.txt,.rtf,.zip,.jpg,.jpeg,.png">
                            <small id="downloadFileHelp">Required for a new download. Maximum 25 MB.</small>
                        </div>
                    </div>
                    <div class="form-group stacked">
                        <label for="downloadKey">Unique link key</label>
                        <input type="text" id="downloadKey" name="download_key" minlength="8" maxlength="64" pattern="[a-z0-9-]{8,64}" placeholder="Generated automatically when blank">
                        <small>The reusable link updates as you edit this key.</small>
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="download-link-preview">
                        <span>Download link</span>
                        <a id="downloadLinkPreview" href="#" target="_blank" rel="noopener">Generated after save</a>
                    </div>
                    <div class="form-group stacked">
                        <label for="downloadCount">Download count</label>
                        <input type="number" id="downloadCount" min="0" step="1" value="0" readonly aria-readonly="true">
                        <small>This counter updates automatically when customers download the file.</small>
                    </div>
                    <div class="product-toggle-grid">
                        <label class="toggle-row"><input type="checkbox" id="downloadViewable" name="viewable" value="1" checked><span>Viewable by customers</span></label>
                    </div>
                    <p class="download-file-status" id="downloadFileStatus">No file uploaded yet.</p>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelDownloadBtn">Cancel</button>
                <button type="submit" class="btn-primary" id="saveDownloadBtn">Save Download</button>
            </div>
        </form>
    </dialog>
</section>
