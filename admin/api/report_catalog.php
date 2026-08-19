<?php

declare(strict_types=1);

function adminReportCatalog(): array
{
    return [
        'website-statistics' => [
            'title' => 'Website Statistics',
            'description' => 'A high-level view of product views, customer logins, and member news activity.',
            'procedure' => 'sp_report_website_statistics',
            'filename' => 'website-statistics.xlsx'
        ],
        'cloudflare' => [
            'title' => 'Cloudflare Analytics',
            'description' => 'Daily traffic, visits, bandwidth, cache performance, and security activity for the last 30 days.',
            'procedure' => 'sp_report_cloudflare_analytics',
            'filename' => 'cloudflare-analytics.xlsx'
        ],
        'users' => [
            'title' => 'Users',
            'description' => 'Customer and administrator accounts, contact details, roles, status, and recent login activity.',
            'procedure' => 'sp_report_users',
            'filename' => 'users.xlsx'
        ],
        'products' => [
            'title' => 'Products',
            'description' => 'Product catalog, categories, vendors, pricing, inventory, visibility, and lifetime views.',
            'procedure' => 'sp_report_products',
            'filename' => 'products.xlsx'
        ],
        'financial' => [
            'title' => 'Financial Summary',
            'description' => 'Monthly posted sales, returns, discounts, costs, fees, gross profit, and net proceeds by currency.',
            'procedure' => 'sp_report_financial_summary',
            'filename' => 'financial-summary.xlsx'
        ],
        'downloads' => [
            'title' => 'Downloads',
            'description' => 'Downloadable resources, categories, visibility, file names, and lifetime download counts.',
            'procedure' => 'sp_report_downloads',
            'filename' => 'downloads.xlsx'
        ],
        'transactions' => [
            'title' => 'Transactions',
            'description' => 'Order payment transactions with customer, provider, status, sales, cost, and fee details.',
            'procedure' => 'sp_report_transactions',
            'filename' => 'transactions.xlsx'
        ],
        'email-activity' => [
            'title' => 'Email Activity',
            'description' => 'Newsletter and sales-email delivery results, including sent, failed, and skipped activity.',
            'procedure' => 'sp_report_email_activity',
            'filename' => 'email-activity.xlsx'
        ],
        'contacts' => [
            'title' => 'Contacts',
            'description' => 'Contact-form submissions with sender details, subject, message, and submission date.',
            'procedure' => 'sp_report_contacts',
            'filename' => 'contacts.xlsx'
        ]
    ];
}
