<!-- ========================================= -->
<!-- ADMIN INDEX PAGE -->
<!-- File: /index.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Norman and Company | Caribbean Travel & Cruise Resources</title>

    <meta name="description" content="Norman and Company provides Caribbean travel guides, cruise tips, travel essentials, and curated products.">
    <meta name="keywords" content="Caribbean travel, cruise travel, travel essentials, cruise packing, travel store">
    <meta name="author" content="Norman and Company">

    <link rel="canonical" href="https://www.normanandcompany.com/">
    <link rel="stylesheet" href="/admin/css/admin.css">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="icon" href="/favicon.ico">

    <meta property="og:title" content="Norman and Company">
    <meta property="og:description" content="Trusted Caribbean travel resources and cruise insights.">
    <meta property="og:type" content="website">
    <meta property="og:image" content="/images/hero-caribbean.jpg">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "TravelAgency",
      "name": "Norman and Company",
      "url": "https://www.normanandcompany.com",
      "logo": "/images/NormanAndCompanyLogo2.png"
    }
    </script>

    <script src="/admin/js/admin.js" defer></script>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo-section">
            <!--
            <img src="/images/Banner4.png" 
                 alt="Norman and Company Logo" 
                 class="logo"> -->
        </div>
    </div>
</header>

<!-- MAIN ADMIN LAYOUT -->
<div class="admin-layout">

    <!-- Admin Sidebar Navigation -->
    <aside class="admin-sidebar">
        <nav class="admin-nav">
            <ul>
                <li><a href="/admin/index.php">Dashboard</a></li>
                <li><a href="#" onclick="loadPage('calendar')">Calendar</a></li>
                <li><a href="#" onclick="loadPage('products')">Products</a></li>
                <li><a href="#" onclick="loadPage('users')">Users</a></li>
                <li><a href="#" onclick="loadPage('transactions')">Transactions</a></li>
                <li><a href="#" onclick="loadPage('travelcontent')">Travel Content</a></li>
                <li><a href="#" onclick="loadPage('blogposts')">Blog Editor</a></li>
                <li><a href="#" onclick="loadPage('apis')">Scrapers/API</a></li>
                <li><a href="#" onclick="loadPage('reports')">Reports</a></li>
                <li><a href="#" onclick="loadPage('settings')">Site Settings</a></li>
                <li><a href="/">Return to Website</a></li>
                <li><a href="/admin/api/logout.php">Logout</a></li>
            </ul>
        </nav>
    </aside>

    <main id="content">
    </main>

</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/footer.php'; ?>
