<!-- ========================================= -->
<!-- ADMIN CALENDAR PAGE -->
<!-- File: /admin/pages/calendar.php -->
<!-- Copyright 2026 @ Norman & Company -->
<!-- Written by: Joe Leone -->
<!-- ========================================= -->

<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/api/auth.php';

requireRole('admin');

$envPath = dirname(__DIR__, 2) . '/config/env.php';

if (is_file($envPath)) {
    require_once $envPath;
}

function normanAdminCalendarEnv(string $key): string
{
    if (function_exists('normanEnv')) {
        $value = normanEnv($key);

        return is_string($value) ? trim($value) : '';
    }

    foreach ([$_ENV[$key] ?? null, $_SERVER[$key] ?? null, getenv($key)] as $value) {
        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }
    }

    return '';
}

function normanAdminCalendarEmbedUrl(string $value): string
{
    $value = trim($value);

    if ($value === '') {
        return '';
    }

    if (preg_match('/src=["\']([^"\']+)["\']/i', $value, $matches) === 1) {
        $value = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    $parts = parse_url($value);

    if (!is_array($parts)) {
        return '';
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $host = strtolower((string) ($parts['host'] ?? ''));
    $path = (string) ($parts['path'] ?? '');

    if ($scheme !== 'https' || $host !== 'calendar.google.com') {
        return '';
    }

    if (!str_contains($path, '/calendar/') || !str_contains($path, '/embed')) {
        return '';
    }

    return $value;
}

$calendarValue = normanAdminCalendarEnv('NORMAN_ADMIN_CALENDAR_EMBED_URL');
$calendarEmbedUrl = normanAdminCalendarEmbedUrl($calendarValue);
$hasCalendarValue = $calendarValue !== '';
$isCalendarConfigured = $calendarEmbedUrl !== '';
?>

<section class="admin-calendar-page" style="display: flex; flex-direction: column; gap: 1.25rem; min-height: calc(100vh - 360px);">

    <!-- Page Title Metadata -->
    <div id="page-title-meta"
        data-title="Norman and Company | Calendar"
        style="display: none;">
    </div>

    <div class="admin-calendar-header" style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
        <h1>Calendar</h1>

        <?php if ($isCalendarConfigured): ?>
            <a class="admin-calendar-open-link" style="display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0.75rem 1rem; border-radius: 6px; background: #1687C9; color: white; font-weight: 700; text-decoration: none;" href="<?php echo htmlspecialchars($calendarEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">Open Calendar</a>
        <?php endif; ?>
    </div>

    <?php if ($isCalendarConfigured): ?>
        <div class="admin-calendar-frame-wrap" style="flex: 1 1 auto; width: 100%; height: calc(100vh - 420px); min-height: 720px; overflow: hidden; border: 1px solid rgba(22, 58, 99, 0.14); border-radius: 8px; background: white;">
            <iframe
                class="admin-calendar-frame"
                src="<?php echo htmlspecialchars($calendarEmbedUrl, ENT_QUOTES, 'UTF-8'); ?>"
                title="Norman and Company Google Calendar"
                loading="lazy"
                referrerpolicy="no-referrer-when-downgrade"
                style="display: block; width: 100%; height: 100%; min-height: 720px; border: 0;"></iframe>
        </div>
    <?php else: ?>
        <div class="admin-calendar-empty-state" role="status">
            <h2>Calendar Not Configured</h2>
            <?php if ($hasCalendarValue): ?>
                <p>The calendar value in <code>.env</code> is not a valid Google Calendar embed URL.</p>
            <?php else: ?>
                <p>Add the Google Calendar embed URL to <code>NORMAN_ADMIN_CALENDAR_EMBED_URL</code> in <code>.env</code>.</p>
            <?php endif; ?>
            <ol class="admin-calendar-setup-list">
                <li>Open Google Calendar settings for the calendar you want to show.</li>
                <li>Use the embed URL from the Integrate calendar section.</li>
                <li>Make sure the calendar is shared so admin users can view it.</li>
            </ol>
        </div>
    <?php endif; ?>
</section>
