<?php
declare(strict_types=1);

final class RelevanceService
{
    public function score(array $item, array $source, array $classification): float
    {
        $score = (float) ($source['default_relevance_score'] ?? 50);
        $score += ((float) ($source['trust_score'] ?? 50) - 50) * .2;
        if (!empty($source['is_official_source'])) $score += 10;
        if (!empty($item['published_at']) && strtotime($item['published_at']) >= time() - 172800) $score += 10;
        $score += max(0, ((float) ($classification['confidence'] ?? 50) - 50) * .2);
        if (preg_match('/\b(sponsored|advertorial)\b/i', ($item['headline'] ?? '') . ' ' . ($item['summary'] ?? ''))) $score -= 15;
        return round(max(0, min(100, $score)), 2);
    }
}
