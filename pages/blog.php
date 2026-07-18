<!-- ========================================= -->
<!-- BLOG PAGE -->
<!-- File: /pages/blog.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Norman's Navy Travel Blog"
        style="display: none;">
    </div>

    <h1>Norman's Navy Travel Blog</h1>

    <div id="blogPostsContainer" class="blog-container">

        <!-- Current Article (80%) -->
        <div id="currentArticle" class="current-article">

            <a id="currentArticleLink" href="#" class="current-article-link">

                <img id="currentArticleImage" src="/images/blog/sample_image.png" alt="Current Article">

                <div id="currentArticleTitle" class="article-title">
                    <strong>Loading latest article...</strong>
                </div>

                <div id="currentArticleMeta" class="article-meta">
                    <!-- Author --><span id="currentArticleAuthor"></span> | <!-- Publish Date --><time id="currentArticleDate" datetime=""></time> | <!-- Category --><span id="currentArticleCategory"></span>
                </div>

                <div id="currentArticleDescription" class="article-description">
                    Loading article summary...
                </div>

            </a>

        </div>

        <!-- Previous Blog Articles (20%) -->
        <div id="previousBlogArticles" class="previous-blog-articles" data-page-size="4">

            <p id="previousArticlesLoadingMessage">Loading previous articles...</p>

            <div class="blog-sidebar-controls">
                <div class="blog-page-controls" aria-label="Previous blog article pages">
                    <button type="button"
                            class="blog-page-button"
                            data-blog-page-action="prev"
                            aria-label="Previous page"
                            title="Previous page">
                        &#10094;
                    </button>

                    <div class="blog-page-numbers" aria-label="Page numbers"></div>

                    <button type="button"
                            class="blog-page-button"
                            data-blog-page-action="next"
                            aria-label="Next page"
                            title="Next page">
                        &#10095;
                    </button>
                </div>

                <div class="blog-category-filter">
                    <label for="blogCategoryFilter">Category:</label>
                    <select id="blogCategoryFilter">
                        <option value="all">All Categories</option>
                    </select>
                </div>
            </div>

        </div>

    </div>

</section>
