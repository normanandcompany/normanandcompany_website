<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Caribbean Travel Essentials"
        style="display: none;">
    </div>

    <h1>Travel Essentials</h1>

    <ul>
        <li>Passport Holder</li>
        <li>Portable Charger</li>
        <li>Travel Backpack</li>
        <li>Waterproof Phone Case</li>
    </ul>
</section>