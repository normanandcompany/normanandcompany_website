<!-- ========================================= -->
<!-- ADMIN INDEX PAGE -->
<!-- File: /index.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');

$adminCssVersion = (string) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/css/admin.css');
$adminJsVersion = (string) @filemtime($_SERVER['DOCUMENT_ROOT'] . '/admin/js/admin.js');

if ($adminCssVersion === '') {
    $adminCssVersion = '1';
}

if ($adminJsVersion === '') {
    $adminJsVersion = '1';
}
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
    <link rel="stylesheet" href="/admin/css/admin.css?v=<?php echo htmlspecialchars($adminCssVersion, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="icon" href="/favicon.ico?v=20260719" type="image/x-icon" sizes="any">
    <link rel="icon" href="/images/favicon-32x32.png?v=20260719" type="image/png" sizes="32x32">
    <link rel="icon" href="/images/favicon-16x16.png?v=20260719" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png?v=20260719" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest?v=20260719">

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

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1/dist/chart.umd.min.js" defer></script>
    <script src="/admin/js/admin.js?v=<?php echo htmlspecialchars($adminJsVersion, ENT_QUOTES, 'UTF-8'); ?>" defer></script>
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
                <li><a href="#" onclick="loadPage('tasks')">Tasks</a></li>
                <li><a href="#" onclick="loadPage('products')">Products</a></li>
                <li><a href="#" onclick="loadPage('users')">Users</a></li>
                <li><a href="#" onclick="loadPage('transactions')">Transactions</a></li>
                <li><a href="#" onclick="loadPage('travelcontent')">Travel Content</a></li>
                <li><a href="#" onclick="loadPage('blogposts')">Blog Editor</a></li>
                <li><a href="#" onclick="loadPage('news')">News Aggregator</a></li>
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
