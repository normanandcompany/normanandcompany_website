<!-- ========================================= -->
<!-- CUSTOMER HOME PAGE -->
<!-- File: /pages/home.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('customer');
?>

<section>

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Customer Profile Page"
        style="display: none;">
    </div>

    <div>
        <h1>Customer Profile Page</h1>
        <p>
            Discover Norman and Company, your trusted gateway to smarter, more confident travel planning. Whether you’re setting sail through the Caribbean, booking a resort getaway, or mapping out your next great adventure, our platform brings together everything you need in one place. From curated cruise line insights to detailed destination guides, we help you make informed decisions so every journey starts with clarity and excitement.
        </p>
        <p>
            Explore a thoughtfully organized collection of travel resources designed for both seasoned travelers and first-time explorers. You’ll find in-depth cruise and resort information, practical travel tips, destination breakdowns, and expertly selected travel essentials available for purchase. Each section is built to simplify planning while enhancing the quality of your experience, ensuring you spend less time searching and more time anticipating the trip ahead.
        </p>
        <p>
            At Norman and Company, we believe travel should feel effortless, inspired, and well-prepared. Our goal is to connect you with the right tools, knowledge, and products to elevate every stage of your journey—from initial research to the moment you return home. Wherever you’re headed next, we’re here to help you travel with confidence and purpose.
        </p>
        <div class="button-group">
            <a href="#" onclick="loadPage('destinations')" class="btn">Explore Destinations</a>
            <a href="#" onclick="loadPage('cruiselines')" class="btn">Explore Cruiselines</a>
            <a href="#" onclick="loadPage('resorts')" class="btn">Explore Resorts</a>
            <a href="#" onclick="loadPage('travelstore')" class="btn">Travel Store</a>
        </div>
    </div>
</section>