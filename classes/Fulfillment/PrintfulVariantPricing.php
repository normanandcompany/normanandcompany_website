<?php

declare(strict_types=1);

final class PrintfulVariantPricing
{
    public const MODE_DIFFERENCE = 'difference';
    public const MODE_RETAIL = 'retail';

    public static function calculate(float $basePrice, array $variants, string $mode): array
    {
        if (!in_array($mode, [self::MODE_DIFFERENCE, self::MODE_RETAIL], true)) {
            throw new InvalidArgumentException('Select a valid variant pricing mode.');
        }

        if ($basePrice < 0 || $variants === []) {
            throw new InvalidArgumentException('Variant pricing requires a base price and at least one mapped variant.');
        }

        $remotePrices = [];
        foreach ($variants as $variant) {
            if (!isset($variant['remote_price']) || !is_numeric($variant['remote_price'])) {
                throw new InvalidArgumentException('Printful did not provide a retail price for every mapped variant.');
            }
            $remotePrices[] = round((float) $variant['remote_price'], 2);
        }
        $baseline = min($remotePrices);

        $rows = [];
        foreach ($variants as $variant) {
            $remotePrice = round((float) $variant['remote_price'], 2);
            $adjustment = $mode === self::MODE_RETAIL
                ? $remotePrice - $basePrice
                : max(0.0, $remotePrice - $baseline);
            $proposedPrice = $basePrice + $adjustment;

            $rows[] = [
                ...$variant,
                'remote_price' => number_format($remotePrice, 2, '.', ''),
                'price_adjustment' => number_format($adjustment, 2, '.', ''),
                'proposed_price' => number_format($proposedPrice, 2, '.', '')
            ];
        }

        return [
            'mode' => $mode,
            'base_price' => number_format($basePrice, 2, '.', ''),
            'printful_baseline_price' => number_format($baseline, 2, '.', ''),
            'variants' => $rows
        ];
    }
}
