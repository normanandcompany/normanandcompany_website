<?php /** @var array $article */ ?>
<article class="news-card customer-profile__panel">
    <?php if (!empty($article['image_local_path']) || (!empty($article['image_source_url']) && !empty($article['image_usage_approved']))): ?>
        <img src="<?= newsEscape($article['image_local_path'] ?: $article['image_source_url']) ?>" alt="<?= newsEscape($article['image_alt_text'] ?: $article['headline']) ?>" loading="lazy">
    <?php else: ?><div class="news-card-placeholder" aria-hidden="true"><?= ($article['news_type'] ?? '') === 'resort' ? 'Resort News' : 'Cruise News' ?></div><?php endif; ?>
    <div class="news-card-body">
        <div class="news-card-kicker"><?= newsEscape($article['category_name'] ?: ucfirst($article['news_type'])) ?><?php if (!empty($article['is_featured'])): ?> · Featured<?php endif; ?></div>
        <h2><a href="/news.php?article=<?= rawurlencode($article['slug']) ?>"><?= newsEscape($article['headline']) ?></a></h2>
        <p><?= newsEscape($article['summary']) ?></p>
        <div class="news-card-meta"><span><?= newsEscape($article['source_name'] ?: 'Norman and Company') ?></span><time datetime="<?= newsEscape($article['source_published_at'] ?: $article['published_at']) ?>"><?= newsEscape(date('M j, Y', strtotime($article['source_published_at'] ?: $article['published_at']))) ?></time></div>
    </div>
</article>
