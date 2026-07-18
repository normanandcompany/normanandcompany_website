<!-- ========================================= -->
<!-- BLOG DETAILS PAGE -->
<!-- File: /pages/blogdetails.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once __DIR__ . '/../api/db.php';

$blogPostId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$blogPost = null;
$errorMessage = '';

function blogDetailsEscape(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function blogDetailsPostId(array $post): ?int
{
    $postId = $post['id'] ?? $post['ID'] ?? $post['blog_post_id'] ?? null;

    return $postId !== null ? (int)$postId : null;
}

function blogDetailsFormatDate(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('F j, Y', $timestamp);
}

function blogDetailsDateTimeValue(?string $value): string
{
    if (!$value) {
        return '';
    }

    $timestamp = strtotime($value);

    if ($timestamp === false) {
        return $value;
    }

    return date('Y-m-d', $timestamp);
}

function blogDetailsImageSrc(?string $imageUrl): string
{
    $image = trim((string)$imageUrl);

    if ($image === '') {
        return '/images/blog/sample_image.png';
    }

    if (
        preg_match('/^(https?:)?\/\//i', $image)
        || str_starts_with($image, '/')
        || str_starts_with($image, 'data:')
    ) {
        return $image;
    }

    if (str_starts_with($image, 'images/')) {
        return '/' . $image;
    }

    if (str_starts_with($image, 'blog/')) {
        return '/images/' . $image;
    }

    return '/images/blog/' . $image;
}

function blogDetailsArticleContent(?string $content): string
{
    $content = trim((string)$content);

    if ($content === '') {
        return '';
    }

    if ($content !== strip_tags($content)) {
        return $content;
    }

    $lines = preg_split('/\R+/', $content);
    $paragraphs = [];

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line !== '') {
            $paragraphs[] = '<p>' . blogDetailsEscape($line) . '</p>';
        }
    }

    return implode("\n", $paragraphs);
}

if ($blogPostId) {
    try {
        $stmt = $pdo->prepare("CALL sp_get_all_blog_posts()");
        $stmt->execute();
        $blogPosts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        foreach ($blogPosts as $post) {
            if (blogDetailsPostId($post) === $blogPostId && (int)($post['visible'] ?? 1) === 1) {
                $blogPost = $post;
                break;
            }
        }

        if (!$blogPost) {
            $errorMessage = 'Blog article not found.';
        }
    } catch (Exception $e) {
        $errorMessage = 'Unable to load blog article.';
    }
} else {
    $errorMessage = 'No blog article was selected.';
}

$postTitle = $blogPost['post_title'] ?? 'Blog Article';
$postExcerpt = $blogPost['post_excerpt'] ?? '';
$postContent = $blogPost['post_content'] ?? '';
$authorName = $blogPost['author_full_name'] ?? '';
$publishedAt = $blogPost['published_at'] ?? '';
$categoryName = $blogPost['category_name'] ?? '';
$featuredImage = blogDetailsImageSrc($blogPost['featured_image_url'] ?? null);
?>

<?php include __DIR__ . '/../includes/header.php'; ?>

<?php include __DIR__ . '/../includes/navbar.php'; ?>

<main id="content">

    <section>

        <!-- Page Title Metadata -->
        <div id="page-title-meta"
            data-title="Norman and Company | <?php echo blogDetailsEscape($postTitle); ?>"
            style="display: none;">
        </div>

        <div class="blog-detail-title-row">
            <h1>Norman's Navy Travel Blog</h1>

            <a href="/"
               class="btn-primary blog-return-button"
               onclick="event.preventDefault(); if (typeof loadPage === 'function') { window.history.pushState({}, '', '/'); loadPage('blog'); } else { window.location.href = '/'; }">
                Return to Blogs
            </a>
        </div>

        <div id="blogDetailsContainer"
             class="blog-detail-container"
             data-blog-post-id="<?php echo blogDetailsEscape((string)($blogPostId ?? '')); ?>">

            <?php if ($blogPost): ?>
                <article id="blogDetailsArticle" class="current-article blog-detail-article">

                    <img id="blogDetailsImage"
                         class="blog-detail-image"
                         src="<?php echo blogDetailsEscape($featuredImage); ?>"
                         alt="<?php echo blogDetailsEscape($postTitle); ?>">

                    <div id="blogDetailsTitle" class="article-title">
                        <strong><?php echo blogDetailsEscape($postTitle); ?></strong>
                    </div>

                    <div id="blogDetailsMeta" class="article-meta">
                        <!-- Author --><span id="blogDetailsAuthor"><?php echo $authorName ? 'By ' . blogDetailsEscape($authorName) : ''; ?></span>
                        <?php if ($publishedAt): ?>
                            | <!-- Publish Date --><time id="blogDetailsDate" datetime="<?php echo blogDetailsEscape(blogDetailsDateTimeValue($publishedAt)); ?>"><?php echo blogDetailsEscape(blogDetailsFormatDate($publishedAt)); ?></time>
                        <?php endif; ?>
                        <?php if ($categoryName): ?>
                            | <!-- Category --><span id="blogDetailsCategory"><?php echo blogDetailsEscape($categoryName); ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (trim((string)$postExcerpt) !== ''): ?>
                        <div id="blogDetailsExcerpt" class="article-description">
                            <?php echo blogDetailsEscape($postExcerpt); ?>
                        </div>
                    <?php endif; ?>

                    <div id="blogDetailsContent" class="blog-detail-content">
                        <?php echo blogDetailsArticleContent($postContent); ?>
                    </div>

                </article>
            <?php else: ?>
                <div class="blog-detail-article">
                    <div class="article-title">
                        <strong><?php echo blogDetailsEscape($errorMessage); ?></strong>
                    </div>
                </div>
            <?php endif; ?>

        </div>

    </section>

</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
