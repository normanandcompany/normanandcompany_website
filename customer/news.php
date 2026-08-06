<?php
declare(strict_types=1);
$projectRoot = dirname(__DIR__);
require_once $projectRoot . '/includes/news/bootstrap.php';
$pdo = null; $error = null; $result = ['articles'=>[],'total'=>0,'page'=>1,'pages'=>1]; $article = null;
try {
    $pdo = newsPdo('web'); $repository = new NewsArticleRepository($pdo);
    if (!empty($_GET['article'])) $article = $repository->bySlug(NewsSupport::slug((string)$_GET['article']));
    else { $page=max(1,(int)($_GET['page']??1)); $result=$repository->publicList(['type'=>$_GET['type']??'','category'=>$_GET['category']??'','source'=>$_GET['source']??'','entity'=>$_GET['entity']??'','q'=>$_GET['q']??'','from'=>$_GET['from']??'','to'=>$_GET['to']??''],$page,12); }
} catch (Throwable $e) { $error='News is temporarily unavailable. Please try again soon.'; error_log('Public news error: '.$e->getMessage()); }
$pageTitle=$article ? $article['headline'].' | Norman and Company' : 'Cruise & Resort News | Norman and Company';
$pageDescription=$article ? ($article['meta_description'] ?: $article['summary']) : 'The latest cruise, resort, destination, and Caribbean travel news curated by Norman and Company.';
$canonicalUrl=$article?'https://www.normanandcompany.com/customer/news.php?article='.rawurlencode($article['slug']):'https://www.normanandcompany.com/customer/news.php';
$socialImage=$article&&$article['image_usage_approved']?($article['image_local_path']?:$article['image_source_url']):'/images/hero-caribbean.jpg';
if($article&&$pdo&&isLoggedIn()&&getUserRole()==='customer'){try{$settings=$pdo->prepare('SELECT history_enabled FROM user_news_settings WHERE user_id=:user');$settings->execute([':user'=>getUserId()]);if($settings->fetchColumn()!==0)$pdo->prepare('INSERT INTO user_news_article_views(user_id,article_id,view_source,session_reference) VALUES(:user,:article,"article",:session)')->execute([':user'=>getUserId(),':article'=>$article['id'],':session'=>hash('sha256',session_id())]);}catch(Throwable $e){error_log('News view tracking failed: '.$e->getMessage());}}
$isCustomerNewsViewer = isLoggedIn() && getUserRole() === 'customer';
$isAdminNewsViewer = isLoggedIn() && getUserRole() === 'admin';

if ($isCustomerNewsViewer) {
    include __DIR__ . '/includes/header.php';
    include __DIR__ . '/includes/navbar.php';
} else {
    include $projectRoot . '/includes/header.php';
    include $projectRoot . '/includes/navbar.php';
}
?>
<main class="news-page customer-profile" id="main-content">
<?php if ($error): ?><div class="news-notice" role="alert"><?= newsEscape($error) ?></div>
<?php elseif (!empty($_GET['article']) && !$article): ?><section class="news-empty customer-profile__panel"><h1>Story not found</h1><p>This story is unavailable or has been archived.</p><a class="news-button customer-profile__add-button" href="/customer/news.php">Return to news</a></section>
<?php elseif ($article): ?>
    <article class="news-detail customer-profile__panel">
        <nav class="news-breadcrumb" aria-label="Breadcrumb"><a href="/">Home</a> / <a href="/customer/news.php">News</a> / <span><?= newsEscape($article['category_name'] ?: ucfirst($article['news_type'])) ?></span></nav>
        <header class="customer-profile__hero news-detail-hero">
            <div>
                <p class="customer-profile__eyebrow"><?= newsEscape($article['category_name'] ?: ucfirst($article['news_type']).' News') ?></p>
                <h1><?= newsEscape($article['headline']) ?></h1>
                <p>External reporting from <strong><?= newsEscape($article['source_name']) ?></strong> · <time datetime="<?= newsEscape($article['source_published_at'] ?: $article['published_at']) ?>"><?= newsEscape(date('F j, Y',strtotime($article['source_published_at'] ?: $article['published_at']))) ?></time></p>
            </div>
            <div class="customer-profile__avatar" aria-hidden="true">NEWS</div>
        </header>
        <?php if ((!empty($article['image_local_path']) || !empty($article['image_source_url'])) && $article['image_usage_approved']): ?><figure><img src="<?= newsEscape($article['image_local_path'] ?: $article['image_source_url']) ?>" alt="<?= newsEscape($article['image_alt_text'] ?: $article['headline']) ?>"><?php if($article['image_attribution']):?><figcaption><?= newsEscape($article['image_attribution']) ?></figcaption><?php endif;?></figure><?php endif;?>
        <div class="news-summary"><p><?= nl2br(newsEscape($article['summary'])) ?></p><?php if($article['editorial_summary']):?><p><?= nl2br(newsEscape($article['editorial_summary'])) ?></p><?php endif;?></div>
        <?php if($article['entities']):?><ul class="news-tags" aria-label="Related topics"><?php foreach($article['entities'] as $entity):?><li><?= newsEscape($entity['entity_name']) ?></li><?php endforeach;?></ul><?php endif;?>
        <p class="news-source-link"><a href="<?= newsEscape($article['source_url']) ?>" target="_blank" rel="noopener noreferrer nofollow">Read the complete story at <?= newsEscape($article['source_name']) ?>.</a></p>
        <div class="news-share" aria-label="Share this story"><a href="mailto:?subject=<?= rawurlencode($article['headline']) ?>&body=<?= rawurlencode('https://www.normanandcompany.com/customer/news.php?article='.$article['slug']) ?>">Share by email</a></div>
        <?php if($article['related']):?><section><h2>Related coverage</h2><ul><?php foreach($article['related'] as $related):?><li><a href="/customer/news.php?article=<?= rawurlencode($related['slug']) ?>"><?= newsEscape($related['headline']) ?></a> — <?= newsEscape($related['source_name']) ?></li><?php endforeach;?></ul></section><?php endif;?>
    </article>
    <script type="application/ld+json"><?= json_encode(['@context'=>'https://schema.org','@type'=>'NewsArticle','headline'=>$article['headline'],'datePublished'=>$article['source_published_at'] ?: $article['published_at'],'dateModified'=>$article['updated_at'],'description'=>$pageDescription,'mainEntityOfPage'=>'https://www.normanandcompany.com/customer/news.php?article='.$article['slug'],'publisher'=>['@type'=>'Organization','name'=>'Norman and Company'],'citation'=>$article['source_url']],JSON_UNESCAPED_SLASHES|JSON_HEX_TAG) ?></script>
<?php else: ?>
    <header class="customer-profile__hero news-profile-hero"><div><p class="customer-profile__eyebrow">Travel updates</p><h1>Cruise &amp; Resort News</h1><p>Curated travel reporting with concise summaries and clear links to original sources.</p></div><div class="customer-profile__avatar" aria-hidden="true">NEWS</div></header>
    <nav class="news-profile-actions" aria-label="News type"><a class="customer-profile__add-button" href="/customer/news.php?type=cruise">Cruise News</a><a class="customer-profile__add-button" href="/customer/news.php?type=resort">Resort News</a></nav>
    <form class="news-filter customer-profile__panel" method="get" action="/customer/news.php" role="search">
        <label>Search <input type="search" name="q" value="<?= newsEscape($_GET['q']??'') ?>" maxlength="100"></label>
        <label>News type <select name="type"><option value="">All news</option><option value="cruise" <?=($_GET['type']??'')==='cruise'?'selected':''?>>Cruise</option><option value="resort" <?=($_GET['type']??'')==='resort'?'selected':''?>>Resort</option></select></label>
        <label>From <input type="date" name="from" value="<?= newsEscape($_GET['from']??'') ?>"></label><label>To <input type="date" name="to" value="<?= newsEscape($_GET['to']??'') ?>"></label>
        <button type="submit" class="customer-profile__add-button">Search news</button>
    </form>
    <?php if(!$result['articles']):?><div class="news-empty" role="status"><h2>No stories found</h2><p>Try changing the search or filters.</p></div><?php else:?><section class="news-grid" aria-label="News stories"><?php foreach($result['articles'] as $article) include $projectRoot.'/includes/news/card.php';?></section><?php endif;?>
    <?php if($result['pages']>1):?><nav class="news-pagination" aria-label="News pages"><?php for($i=1;$i<=$result['pages'];$i++):$query=$_GET;$query['page']=$i;?><a href="?<?= newsEscape(http_build_query($query)) ?>" <?=$i===$result['page']?'aria-current="page"':''?>><?=$i?></a><?php endfor;?></nav><?php endif;?>
    <?php if ($isCustomerNewsViewer): ?>
        <aside class="news-cta customer-profile__panel">
            <p class="customer-profile__eyebrow">Your existing account</p>
            <h2>Personalize your news</h2>
            <p>Choose the cruise lines, resorts, destinations, categories, and keywords you want to follow using your current Norman and Company account.</p>
            <a class="customer-profile__add-button" href="/customer/?section=news">Configure news preferences</a>
        </aside>
    <?php elseif ($isAdminNewsViewer): ?>
        <aside class="news-cta customer-profile__panel">
            <p class="customer-profile__eyebrow">Administrator tools</p>
            <h2>Manage travel news</h2>
            <p>Review sources, imported stories, classifications, and publication settings from the administrator dashboard.</p>
            <a class="customer-profile__add-button" href="/admin/">Open News Aggregator</a>
        </aside>
    <?php else: ?>
        <aside class="news-cta customer-profile__panel">
            <p class="customer-profile__eyebrow">Member benefits</p>
            <h2>Make the news yours</h2>
            <p>Create a free Norman and Company account to follow cruise lines, resorts, ships, ports, and destinations.</p>
            <a class="customer-profile__add-button" href="/customerregistration.php">Create an account</a>
        </aside>
    <?php endif; ?>
<?php endif; ?>
</main>
<?php include $projectRoot . '/includes/footer.php'; ?>
