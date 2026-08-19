<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../classes/Email/EmailHtml.php';
require_once __DIR__ . '/../classes/Email/EmailToken.php';

$failures = [];
$sanitized = EmailHtml::sanitize('<p onclick="bad()" style="background:url(javascript:bad)">Hello <strong>traveler</strong><script>alert(1)</script></p>');
if (str_contains($sanitized, 'onclick') || str_contains($sanitized, '<script') || str_contains($sanitized, 'javascript:') || !str_contains($sanitized, '<strong>traveler</strong>')) {
    $failures[] = 'HTML sanitizer allowlist test failed.';
}
$unsubscribeLink = EmailHtml::sanitize('<p><a href="{UnsubscribeURL}">Unsubscribe</a></p>');
if (!str_contains($unsubscribeLink, 'href="%7BUnsubscribeURL%7D"')) {
    $failures[] = 'Newsletter unsubscribe placeholder was removed by the HTML sanitizer.';
}

$previousKey = getenv('NORMAN_EMAIL_TOKEN_KEY');
putenv('NORMAN_EMAIL_TOKEN_KEY=email-tools-unit-test-key-that-is-long-enough');
$token = EmailToken::make('newsletter', 42, 'customer@example.com');
$parsed = EmailToken::parse($token);
if (($parsed['type'] ?? null) !== 'newsletter' || ($parsed['recipient_id'] ?? null) !== 42) {
    $failures[] = 'Email token round-trip test failed.';
}
if (EmailToken::parse($token . 'x') !== null) {
    $failures[] = 'Tampered email token was accepted.';
}
if ($previousKey === false) {
    putenv('NORMAN_EMAIL_TOKEN_KEY');
} else {
    putenv('NORMAN_EMAIL_TOKEN_KEY=' . $previousKey);
}

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

fwrite(STDOUT, "Email Tools unit checks passed.\n");
