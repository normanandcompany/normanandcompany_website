<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__, 2) . '/config/env.php';

$pdo = normanCreateDatabaseConnection('admin');

$sources = [
    [
        'name' => 'NOAA National Hurricane Center - Atlantic',
        'slug' => 'noaa-national-hurricane-center-atlantic',
        'website' => 'https://www.nhc.noaa.gov/',
        'feed' => 'https://www.nhc.noaa.gov/index-at.xml',
        'type' => 'resort',
        'category' => 'resort-advisories',
        'frequency' => 60,
        'trust' => 98,
        'priority' => 100,
        'relevance' => 80,
        'official' => 1,
        'manual_review' => 0,
        'terms' => [],
    ],
    [
        'name' => 'U.S. Department of State Travel Advisories',
        'slug' => 'us-state-department-travel-advisories',
        'website' => 'https://travel.state.gov/',
        'feed' => 'https://travel.state.gov/_res/rss/TAsTWs.xml',
        'type' => 'resort',
        'category' => 'resort-advisories',
        'frequency' => 360,
        'trust' => 98,
        'priority' => 95,
        'relevance' => 75,
        'official' => 1,
        'manual_review' => 1,
        'terms' => [
            'caribbean', 'bahamas', 'barbados', 'belize', 'bermuda',
            'cayman islands', 'cuba', 'curaçao', 'curacao', 'dominica',
            'dominican republic', 'grenada', 'guadeloupe', 'guyana',
            'haiti', 'jamaica', 'martinique', 'montserrat', 'puerto rico',
            'saint kitts', 'st. kitts', 'saint lucia', 'st. lucia',
            'saint martin', 'st. martin', 'sint maarten',
            'saint vincent', 'st. vincent', 'suriname',
            'trinidad and tobago', 'turks and caicos', 'virgin islands',
        ],
    ],
    [
        'name' => 'Hyatt Inclusive Collection Newsroom',
        'slug' => 'hyatt-inclusive-collection-newsroom',
        'website' => 'https://newsroom.hyatt.com/',
        'feed' => 'https://newsroom.hyatt.com/news-releases?category=827&pagetemplate=rss',
        'type' => 'resort',
        'category' => 'resort-industry',
        'frequency' => 360,
        'trust' => 90,
        'priority' => 85,
        'relevance' => 70,
        'official' => 1,
        'manual_review' => 1,
        'terms' => [],
    ],
    [
        'name' => 'Hilton Stories',
        'slug' => 'hilton-stories',
        'website' => 'https://stories.hilton.com/',
        'feed' => 'https://stories.hilton.com/feed',
        'type' => 'resort',
        'category' => 'resort-industry',
        'frequency' => 360,
        'trust' => 88,
        'priority' => 80,
        'relevance' => 65,
        'official' => 1,
        'manual_review' => 1,
        'terms' => [],
    ],
    [
        'name' => 'Cruise Industry News',
        'slug' => 'cruise-industry-news',
        'website' => 'https://cruiseindustrynews.com/',
        'feed' => 'https://cruiseindustrynews.com/cruise-news/feed/',
        'type' => 'cruise',
        'category' => 'cruise-industry',
        'frequency' => 180,
        'trust' => 82,
        'priority' => 90,
        'relevance' => 72,
        'official' => 0,
        'manual_review' => 1,
        'terms' => [],
    ],
    [
        'name' => 'Cruise Hive',
        'slug' => 'cruise-hive',
        'website' => 'https://www.cruisehive.com/',
        'feed' => 'https://www.cruisehive.com/feed',
        'type' => 'cruise',
        'category' => 'cruise-lines',
        'frequency' => 180,
        'trust' => 72,
        'priority' => 75,
        'relevance' => 65,
        'official' => 0,
        'manual_review' => 1,
        'terms' => [],
    ],
    [
        'name' => 'Caribbean Journal',
        'slug' => 'caribbean-journal',
        'website' => 'https://www.caribjournal.com/',
        'feed' => 'https://www.caribjournal.com/feed/',
        'type' => 'resort',
        'category' => 'destination-updates',
        'frequency' => 180,
        'trust' => 78,
        'priority' => 90,
        'relevance' => 72,
        'official' => 0,
        'manual_review' => 1,
        'terms' => [],
    ],
    [
        'name' => 'PR Newswire Travel',
        'slug' => 'pr-newswire-travel',
        'website' => 'https://www.prnewswire.com/news-releases/consumer-products-retail-latest-news/travel-list/',
        'feed' => 'https://www.prnewswire.com/rss/travel-latest-news/travel-latest-news-list.rss',
        'type' => 'resort',
        'category' => 'resort-industry',
        'frequency' => 180,
        'trust' => 72,
        'priority' => 70,
        'relevance' => 58,
        'official' => 0,
        'manual_review' => 1,
        'terms' => [
            'cruise', 'cruise line', 'cruise ship', 'resort', 'hotel',
            'all-inclusive', 'all inclusive', 'caribbean', 'tourism',
            'travel advisory',
        ],
    ],
];

$rules = [
    ['New cruise ship', 'new ship', 'cruise', 'cruise-ships', 24],
    ['Cruise itinerary', 'itinerary', 'cruise', 'itineraries', 22],
    ['Cruise port', 'cruise port', 'cruise', 'cruise-ports', 20],
    ['Resort opening', 'resort opening', 'resort', 'resort-openings', 24],
    ['Resort renovation', 'renovation', 'resort', 'resort-renovations', 22],
    ['Travel advisory', 'travel advisory', 'resort', 'resort-advisories', 20],
];

$categoryStatement = $pdo->prepare(
    'SELECT id FROM news_categories WHERE category_slug = :slug LIMIT 1'
);
$sourceStatement = $pdo->prepare(
    'INSERT INTO news_sources (
        source_name, source_slug, source_type, website_url, feed_url,
        default_news_type, default_category_id, is_active,
        requires_attribution, image_reuse_allowed, full_text_reuse_allowed,
        fetch_frequency_minutes, trust_score, priority_score,
        default_relevance_score, is_official_source, requires_manual_review
        , connector_config_json
    ) VALUES (
        :name, :slug, "rss", :website, :feed,
        :type, :category, 1,
        1, 0, 0,
        :frequency, :trust, :priority,
        :relevance, :official, :manual_review
        , :connector_config
    )
    ON DUPLICATE KEY UPDATE
        source_name = VALUES(source_name),
        website_url = VALUES(website_url),
        feed_url = VALUES(feed_url),
        default_news_type = VALUES(default_news_type),
        default_category_id = VALUES(default_category_id),
        is_active = 1,
        fetch_frequency_minutes = VALUES(fetch_frequency_minutes),
        trust_score = VALUES(trust_score),
        priority_score = VALUES(priority_score),
        default_relevance_score = VALUES(default_relevance_score),
        is_official_source = VALUES(is_official_source),
        requires_manual_review = VALUES(requires_manual_review),
        connector_config_json = VALUES(connector_config_json),
        image_reuse_allowed = 0,
        full_text_reuse_allowed = 0'
);
$ruleStatement = $pdo->prepare(
    'INSERT INTO news_classification_rules (
        rule_name, match_type, match_value, suggested_news_type,
        suggested_category_id, score_adjustment, is_active
    )
    SELECT :name, "contains", :value, :type, :category, :score, 1
    WHERE NOT EXISTS (
        SELECT 1 FROM news_classification_rules WHERE rule_name = :existing_name
    )'
);

$pdo->beginTransaction();

try {
    foreach ($sources as $source) {
        $categoryStatement->execute([':slug' => $source['category']]);
        $categoryId = (int) $categoryStatement->fetchColumn();
        if ($categoryId < 1) {
            throw new RuntimeException('Missing news category: ' . $source['category']);
        }

        $sourceStatement->execute([
            ':name' => $source['name'],
            ':slug' => $source['slug'],
            ':website' => $source['website'],
            ':feed' => $source['feed'],
            ':type' => $source['type'],
            ':category' => $categoryId,
            ':frequency' => $source['frequency'],
            ':trust' => $source['trust'],
            ':priority' => $source['priority'],
            ':relevance' => $source['relevance'],
            ':official' => $source['official'],
            ':manual_review' => $source['manual_review'],
            ':connector_config' => $source['terms'] === []
                ? null
                : json_encode(['include_terms' => $source['terms']], JSON_UNESCAPED_SLASHES),
        ]);
    }

    foreach ($rules as [$name, $value, $type, $categorySlug, $score]) {
        $categoryStatement->execute([':slug' => $categorySlug]);
        $categoryId = (int) $categoryStatement->fetchColumn();
        if ($categoryId < 1) {
            throw new RuntimeException('Missing news category: ' . $categorySlug);
        }

        $ruleStatement->execute([
            ':name' => $name,
            ':value' => $value,
            ':type' => $type,
            ':category' => $categoryId,
            ':score' => $score,
            ':existing_name' => $name,
        ]);
    }

    $timestampCorrection = $pdo->prepare(
        'UPDATE news_articles a
         JOIN news_sources s ON s.id = a.source_id
         SET a.published_at = NOW()
         WHERE s.source_slug = :slug
           AND a.status = "published"
           AND a.review_status = "auto_published"
           AND a.published_at > NOW()'
    );
    $timestampCorrection->execute([
        ':slug' => 'noaa-national-hurricane-center-atlantic',
    ]);

    $pdo->commit();
    fwrite(STDOUT, count($sources) . " news sources configured.\n");
} catch (Throwable $exception) {
    $pdo->rollBack();
    fwrite(STDERR, "News source setup failed: {$exception->getMessage()}\n");
    exit(1);
}
