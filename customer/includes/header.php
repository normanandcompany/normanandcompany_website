<?php
$stylesPath = $_SERVER['DOCUMENT_ROOT'] . '/css/styles.css';
$stylesVersion = is_file($stylesPath) ? (string) filemtime($stylesPath) : '20260721';
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
    <link rel="stylesheet" href="/css/styles.css?v=<?= $stylesVersion ?>">
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

    <script src="/js/main.js?v=<?= rawurlencode((string) filemtime(__DIR__ . '/../../js/main.js')) ?>" defer></script>
</head>
<body data-customer-area="true">

<header>
    <div class="header-container">
        <div class="logo-section">
            <!--
            <img src="/images/HeaderLogo.png" 
                 alt="Norman and Company Logo" 
                 class="logo">
            -->
        </div>
    </div>
</header>
