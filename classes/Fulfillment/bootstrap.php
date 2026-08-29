<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/env.php';
require_once __DIR__ . '/PrintfulException.php';
require_once __DIR__ . '/PrintfulConfig.php';
require_once __DIR__ . '/PrintfulClient.php';
require_once __DIR__ . '/PrintfulVariantPricing.php';
require_once __DIR__ . '/CheckoutService.php';
require_once __DIR__ . '/FulfillmentCoordinator.php';
require_once __DIR__ . '/PrintfulWebhookService.php';

function normanPrintfulClient(PDO $pdo, bool $requireToken = true): PrintfulClient
{
    return new PrintfulClient(PrintfulConfig::fromEnvironment($requireToken), $pdo);
}

function startOrderFulfillment(PDO $pdo, int $orderId): array
{
    $config = PrintfulConfig::fromEnvironment();
    $coordinator = new FulfillmentCoordinator($pdo, new PrintfulClient($config, $pdo), $config);
    return $coordinator->processPaidOrder($orderId);
}
