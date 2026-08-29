<?php

declare(strict_types=1);

$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../admin/api/product_helpers.php';

$failures = [];
$assertSame = static function (mixed $expected, mixed $actual, string $message) use (&$failures): void {
    if ($expected !== $actual) {
        $failures[] = $message . ' Expected ' . var_export($expected, true) . ', received ' . var_export($actual, true) . '.';
    }
};

$assertSame(null, productApparelSizeType(false, 7, null), 'Non-apparel products must not use a size catalog.');
$assertSame('children', productApparelSizeType(true, 7, null), "Kid's Collection apparel should default to children's sizes.");
$assertSame('adult', productApparelSizeType(true, 2, null), 'Other apparel should default to adult sizes.');
$assertSame('adult', productApparelSizeType(true, 7, 'adult'), 'An explicit adult override should be honored.');
$assertSame('children', productApparelSizeType(true, 2, 'children'), 'An explicit children override should be honored.');

try {
    productApparelSizeType(true, 7, 'invalid');
    $failures[] = 'An invalid size catalog was accepted.';
} catch (InvalidArgumentException) {
}

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    exit(1);
}

echo "Product sizing unit tests passed.\n";
