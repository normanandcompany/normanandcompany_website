<?php
declare(strict_types=1);
require_once __DIR__ . '/Support.php';

interface NewsSummaryProvider { public function generate(array $article): array; }

final class ExtractiveNewsSummaryProvider implements NewsSummaryProvider
{
    public function generate(array $article): array
    {
        $summary = NewsSupport::excerpt((string) ($article['source_excerpt'] ?: $article['summary']), 900);
        if ($summary === '') throw new RuntimeException('There is not enough permitted source text to create a summary.');
        return ['summary' => $summary, 'provider' => 'local-extractive', 'model' => 'sentence-excerpt-v1', 'confidence' => 55.0];
    }
}

final class NewsSummaryService
{
    public function __construct(private NewsSummaryProvider $provider) {}
    public function generate(array $article): array { return $this->provider->generate($article); }
}
