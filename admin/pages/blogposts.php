<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<section class="product-manager blog-manager">

    <div id="page-title-meta"
        data-title="Norman and Company | Blog Editor"
        style="display: none;">
    </div>

    <div class="product-manager-header">
        <div>
            <h1>Blog Editor</h1>
        </div>

        <button type="button" class="btn-primary product-add-button" id="addBlogPostBtn">
            Add Blog Post
        </button>
    </div>

    <div class="product-toolbar" aria-label="Blog post filters">
        <div class="product-search-field">
            <label for="blogSearchInput">Search</label>
            <input
                type="search"
                id="blogSearchInput"
                placeholder="Title, excerpt, category, or slug"
                autocomplete="off"
            >
        </div>

        <div class="product-filter-field">
            <label for="blogCategoryFilter">Category</label>
            <select id="blogCategoryFilter">
                <option value="all">All Categories</option>
            </select>
        </div>

        <div class="product-filter-field">
            <label for="blogStatusFilter">Status</label>
            <select id="blogStatusFilter">
                <option value="all">All Statuses</option>
                <option value="visible">Visible</option>
                <option value="hidden">Hidden</option>
                <option value="featured">Featured</option>
            </select>
        </div>

        <button type="button" class="btn-secondary product-filter-reset" id="resetBlogFiltersBtn">
            Reset
        </button>
    </div>

    <div class="product-metrics" aria-label="Blog post summary">
        <div class="product-metric">
            <span id="blogTotalCount">0</span>
            <small>Total</small>
        </div>
        <div class="product-metric">
            <span id="blogVisibleCount">0</span>
            <small>Visible</small>
        </div>
        <div class="product-metric">
            <span id="blogFeaturedCount">0</span>
            <small>Featured</small>
        </div>
    </div>

    <div id="blogAlert" class="product-alert" role="status" aria-live="polite" hidden></div>

    <div class="product-table-panel">
        <div class="product-table-scroll">
            <table class="product-table blog-table">
                <thead>
                    <tr>
                        <th>Post</th>
                        <th>Category</th>
                        <th>Author</th>
                        <th>Published</th>
                        <th>Status</th>
                        <th>Updated</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="blogPostsTableBody">
                    <tr>
                        <td colspan="7" class="product-empty-state">Loading blog posts...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="product-pagination">
            <button type="button" id="blogPrevPageBtn" class="btn-secondary">
                Previous
            </button>

            <span id="blogPageInfo">
                Page 1
            </span>

            <button type="button" id="blogNextPageBtn" class="btn-secondary">
                Next
            </button>
        </div>
    </div>

    <dialog id="blogFormDialog" class="product-dialog blog-dialog">
        <form id="blogForm" class="product-form" enctype="multipart/form-data">
            <input type="hidden" id="blogPostId" name="id">

            <div class="product-dialog-header">
                <div>
                    <p class="section-kicker">Blog Article</p>
                    <h2 id="blogFormTitle">Add Blog Post</h2>
                </div>

                <button type="button" class="product-dialog-close" id="closeBlogDialogBtn" aria-label="Close">
                    &times;
                </button>
            </div>

            <div class="product-form-grid blog-form-grid">
                <div class="product-form-main">
                    <div class="form-group stacked">
                        <label for="blogPostTitle">Post Title</label>
                        <input
                            type="text"
                            id="blogPostTitle"
                            name="post_title"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="blogCategoryId">Category</label>
                            <select id="blogCategoryId" name="blog_category_id" required>
                                <option value="">Select category</option>
                            </select>
                        </div>

                        <div class="form-group stacked">
                            <label for="blogPublishedAt">Published At</label>
                            <input
                                type="datetime-local"
                                id="blogPublishedAt"
                                name="published_at"
                            >
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="blogPostExcerpt">Excerpt</label>
                        <textarea
                            id="blogPostExcerpt"
                            name="post_excerpt"
                            rows="4"
                        ></textarea>
                    </div>

                    <div class="form-group stacked">
                        <label for="blogPostContent">Post Content HTML</label>

                        <div class="blog-editor-shell">
                            <div class="blog-editor-toolbar" aria-label="HTML editor toolbar">
                                <button type="button" data-blog-editor-command="bold" title="Bold">
                                    <strong>B</strong>
                                </button>
                                <button type="button" data-blog-editor-command="italic" title="Italic">
                                    <em>I</em>
                                </button>
                                <button type="button" data-blog-editor-format="h2" title="Heading 2">
                                    H2
                                </button>
                                <button type="button" data-blog-editor-format="h3" title="Heading 3">
                                    H3
                                </button>
                                <button type="button" data-blog-editor-format="p" title="Paragraph">
                                    P
                                </button>
                                <button type="button" data-blog-editor-command="insertUnorderedList" title="Bulleted list">
                                    UL
                                </button>
                                <button type="button" data-blog-editor-command="insertOrderedList" title="Numbered list">
                                    OL
                                </button>
                                <button type="button" data-blog-editor-action="create-link" title="Insert link">
                                    Link
                                </button>
                                <button type="button" data-blog-editor-action="insert-image" title="Insert image">
                                    Image
                                </button>
                                <button type="button" id="blogEditorModeBtn" data-blog-editor-action="toggle-source" title="Edit HTML source">
                                    HTML
                                </button>
                            </div>

                            <div
                                id="blogHtmlEditor"
                                class="blog-html-editor"
                                contenteditable="true"
                                aria-label="Visual blog post editor"
                            ></div>

                            <textarea
                                id="blogHtmlSource"
                                class="blog-html-source"
                                rows="16"
                                spellcheck="false"
                                aria-label="Blog post HTML source"
                                hidden
                            ></textarea>

                            <textarea id="blogPostContent" name="post_content" hidden></textarea>
                        </div>
                    </div>

                    <div class="product-field-row two-column">
                        <div class="form-group stacked">
                            <label for="blogSeoSlug">SEO Slug</label>
                            <input
                                type="text"
                                id="blogSeoSlug"
                                name="seo_slug"
                                maxlength="255"
                            >
                        </div>

                        <div class="form-group stacked">
                            <label for="blogMetaTitle">Meta Title</label>
                            <input
                                type="text"
                                id="blogMetaTitle"
                                name="meta_title"
                                maxlength="255"
                            >
                        </div>
                    </div>

                    <div class="form-group stacked">
                        <label for="blogMetaDescription">Meta Description</label>
                        <textarea
                            id="blogMetaDescription"
                            name="meta_description"
                            rows="3"
                            maxlength="500"
                        ></textarea>
                    </div>
                </div>

                <aside class="product-form-side">
                    <div class="product-toggle-grid" aria-label="Blog post flags">
                        <label class="toggle-row">
                            <input type="checkbox" id="blogVisible" name="visible" value="1" checked>
                            <span>Visible</span>
                        </label>

                        <label class="toggle-row">
                            <input type="checkbox" id="blogFeatured" name="is_featured" value="1">
                            <span>Featured</span>
                        </label>
                    </div>

                    <div class="product-images-panel">
                        <div class="product-images-header">
                            <h3>Photos</h3>
                            <span id="blogImageCount">0 / 5</span>
                        </div>

                        <div id="blogImageSlots" class="product-image-slots blog-image-slots"></div>
                    </div>
                </aside>
            </div>

            <div class="product-dialog-actions">
                <button type="button" class="btn-secondary" id="cancelBlogBtn">
                    Cancel
                </button>

                <button type="submit" class="btn-primary" id="saveBlogBtn">
                    Save Post
                </button>
            </div>
        </form>
    </dialog>
</section>
