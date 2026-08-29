<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Fulfillment/PrintfulConfig.php';
require_once __DIR__ . '/../classes/Fulfillment/PrintfulVariantPricing.php';
require_once __DIR__ . '/../classes/Fulfillment/CheckoutService.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) $failures[] = $message;
};

$us = CheckoutService::validateAddress(['name' => 'Test User', 'email' => 'test@example.com', 'address1' => '1 Main St', 'city' => 'Chicago', 'state_code' => 'IL', 'country_code' => 'US', 'postal_code' => '60601']);
$assert($us['country_code'] === 'US', 'US address validation failed.');

$ca = CheckoutService::validateAddress(['name' => 'Test User', 'email' => 'test@example.com', 'address1' => '1 Main St', 'city' => 'Toronto', 'state_code' => 'ON', 'country_code' => 'CA', 'postal_code' => 'M5V 3L9']);
$assert($ca['postal_code'] === 'M5V 3L9', 'Canadian postal code validation failed.');

try {
    CheckoutService::validateAddress(['name' => 'Test User', 'email' => 'test@example.com', 'address1' => '1 Main St', 'city' => 'London', 'state_code' => 'LN', 'country_code' => 'GB', 'postal_code' => 'SW1A 1AA']);
    $failures[] = 'Unsupported country was accepted.';
} catch (InvalidArgumentException) {
}

$hashA = CheckoutService::cartHash([['product_id' => 2, 'product_variant_id' => 4, 'quantity' => 1], ['product_id' => 1, 'product_variant_id' => 3, 'quantity' => 2]]);
$hashB = CheckoutService::cartHash([['product_id' => 1, 'product_variant_id' => 3, 'quantity' => 2], ['product_id' => 2, 'product_variant_id' => 4, 'quantity' => 1]]);
$assert(hash_equals($hashA, $hashB), 'Cart hash should be order-independent.');
$assert(!hash_equals($hashA, CheckoutService::cartHash([['product_id' => 1, 'product_variant_id' => 3, 'quantity' => 3]])), 'Quantity changes must invalidate cart hash.');

$differencePricing = PrintfulVariantPricing::calculate(24.99, [
    ['size_label' => 'S', 'remote_price' => '12.03'],
    ['size_label' => 'XL', 'remote_price' => '12.03'],
    ['size_label' => '2XL', 'remote_price' => '14.63']
], PrintfulVariantPricing::MODE_DIFFERENCE);
$assert($differencePricing['variants'][0]['proposed_price'] === '24.99', 'Standard variant should retain the local base price.');
$assert($differencePricing['variants'][2]['price_adjustment'] === '2.60', 'Oversized variant should preserve the Printful price difference.');
$assert($differencePricing['variants'][2]['proposed_price'] === '27.59', 'Oversized local price calculation failed.');

$retailPricing = PrintfulVariantPricing::calculate(24.99, [
    ['size_label' => 'S', 'remote_price' => '12.03']
], PrintfulVariantPricing::MODE_RETAIL);
$assert($retailPricing['variants'][0]['proposed_price'] === '12.03', 'Retail pricing mode should copy the Printful retail price.');

$pricedHash = CheckoutService::cartHash([['product_id' => 1, 'product_variant_id' => 3, 'quantity' => 1, 'unit_price' => '24.99']]);
$repricedHash = CheckoutService::cartHash([['product_id' => 1, 'product_variant_id' => 3, 'quantity' => 1, 'unit_price' => '27.59']]);
$assert(!hash_equals($pricedHash, $repricedHash), 'Price changes must invalidate a checkout quote.');

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Printful unit tests passed.\n";
