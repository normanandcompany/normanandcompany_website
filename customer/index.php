<!-- ========================================= -->
<!-- CUSTOMER INDEX PAGE -->
<!-- File: /index.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/customer/includes/header.php'; ?>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/customer/includes/navbar.php'; ?>

<main id="content">
</main>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>