<?php
$stylesPath = $_SERVER['DOCUMENT_ROOT'] . '/css/styles.css';
$stylesVersion = is_file($stylesPath) ? (string) filemtime($stylesPath) : '20260721';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle ?? 'Norman and Company | Caribbean Travel & Cruise Resources', ENT_QUOTES, 'UTF-8') ?></title>

    <meta name="description" content="<?= htmlspecialchars($pageDescription ?? 'Norman and Company provides Caribbean travel guides, cruise tips, travel essentials, and curated products.', ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="Caribbean travel, cruise travel, travel essentials, cruise packing, travel store">
    <meta name="author" content="Norman and Company">

    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl ?? 'https://www.normanandcompany.com/', ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/css/styles.css?v=<?= $stylesVersion ?>">
    <link rel="icon" href="/favicon.ico?v=20260719" type="image/x-icon" sizes="any">
    <link rel="icon" href="/images/favicon-32x32.png?v=20260719" type="image/png" sizes="32x32">
    <link rel="icon" href="/images/favicon-16x16.png?v=20260719" type="image/png" sizes="16x16">
    <link rel="apple-touch-icon" href="/images/apple-touch-icon.png?v=20260719" sizes="180x180">
    <link rel="manifest" href="/site.webmanifest?v=20260719">

    <meta property="og:title" content="<?= htmlspecialchars($pageTitle ?? 'Norman and Company', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($pageDescription ?? 'Trusted Caribbean travel resources and cruise insights.', ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:type" content="website">
    <meta property="og:image" content="<?= htmlspecialchars($socialImage ?? '/images/hero-caribbean.jpg', ENT_QUOTES, 'UTF-8') ?>">

    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "TravelAgency",
      "name": "Norman and Company",
      "url": "https://www.normanandcompany.com",
      "logo": "/images/Banner4.png"
    }
    </script>

    <script src="/js/main.js" defer></script>
</head>
<body>

<header>
    <div class="header-container">
        <div class="logo-section"> <!--
            <img src="/images/Banner2.png" 
                 alt="Norman and Company Logo" 
                 class="logo"> -->
        </div>
    </div>
</header>
