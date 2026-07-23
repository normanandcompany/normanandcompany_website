<?php
declare(strict_types=1);

final class ClassificationService
{
    public function __construct(private PDO $pdo) {}

    public function classify(array $item, array $source): array
    {
        $text = strtolower(($item['headline'] ?? '') . ' ' . ($item['summary'] ?? ''));
        $type = (string) ($source['default_news_type'] ?? 'cruise'); $category = $source['default_category_id'] ?? null;
        $confidence = 45.0; $matched = [];
        try { $rules = $this->pdo->query('SELECT * FROM news_classification_rules WHERE is_active = 1 ORDER BY score_adjustment DESC')->fetchAll(PDO::FETCH_ASSOC); }
        catch (PDOException) { $rules = []; }
        foreach ($rules as $rule) {
            $isMatch = $rule['match_type'] === 'regex' ? @preg_match($rule['match_value'], $text) === 1 : str_contains($text, strtolower($rule['match_value']));
            if (!$isMatch) continue;
            if ($rule['suggested_news_type']) $type = $rule['suggested_news_type'];
            if ($rule['suggested_category_id']) $category = (int) $rule['suggested_category_id'];
            $confidence = min(100, $confidence + (float) $rule['score_adjustment']); $matched[] = $rule['rule_name'];
        }
        return ['news_type' => $type, 'category_id' => $category, 'confidence' => $confidence, 'matched_rules' => $matched];
    }
}
