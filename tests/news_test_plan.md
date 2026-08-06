# News Aggregator Repeatable Test Plan

Use disposable test records and a non-production database. Record pass/fail and the exact input used.

## Automated baseline

1. Run `php tests/news_unit.php`.
2. Run `find classes/News connectors/news admin/api/news api/news customer/api scripts -name '*.php' -print0 | xargs -0 -n1 php -l`.
3. Apply all three migrations twice; both runs must finish without destructive changes.

## Feeds and duplicates

1. Test a valid RSS 2.0 feed and a valid Atom feed; preview title, URL, date, author, and summary.
2. Import fixtures missing date/author; confirm nullable fields and successful import.
3. Test invalid XML, timeout, more than three redirects, a redirect to a private IP, `file://`, localhost, and RFC1918/loopback/link-local targets; confirm sanitized failures and import logs.
4. Include CDATA plus script, iframe, event-handler, and tracking-pixel markup; confirm only plain text is stored.
5. Import the same item twice by external ID, tracking-parameter URL, canonical URL, and identical content; confirm no second article and `last_seen_at` changes.
6. Import similar cross-source headlines; confirm both remain and a similarity relation is created.

## Administration

1. Call every admin endpoint signed out, as customer, and as admin; expect 401/403/success respectively.
2. Repeat each mutation with a missing/wrong CSRF token; expect 403 and no database change.
3. Save a disabled source, test it, activate it, import it, disable it, and inspect import history/errors.
4. Add a manual story; edit all review fields; approve, schedule, publish, reject, archive, feature, mark breaking, and mark duplicate.
5. Add keywords/entities and confirm associations; create classification rules and confirm suggestions on a new import.
6. Queue and process a summary; confirm `needs_review` and no automatic publication.
7. Upload allowed images and reject renamed scripts, SVG, oversized files, invalid dimensions, and traversal names.
8. Create a story cluster and newsletter draft; add published stories and render provider-neutral HTML.
9. Exercise bulk actions with valid, missing, duplicated, and manipulated article IDs; confirm history rows.

## Public pages

1. Browse all, cruise, resort, category, source, entity, date, and keyword searches with multiple pages.
2. Disable JavaScript and repeat listing, filtering, pagination, and article navigation.
3. Open published, future, missing, rejected, and archived slugs; only current published stories should render.
4. Verify escaped headline/summary/entity output, source attribution, `noopener noreferrer`, canonical/OG tags, JSON-LD, breadcrumbs, image alt text, and `/customer/sitemap-news.php`.
5. Test keyboard navigation, visible focus, 200% zoom, narrow mobile layout, and screen-reader labels/status messages.

## Personalization and privacy

1. Add/remove entity, category, and keyword preferences at all priorities.
2. Confirm explicit follows rank above freshness and recommendation reasons display.
3. Disable personalization; confirm a neutral latest-news feed. Limit focus to cruise and resort separately.
4. Save/unsave a story, add/edit a private note, and verify another user cannot access it.
5. Hide a recommendation and confirm the underlying public story remains.
6. Disable history, view a story, and confirm no view row; enable it, confirm a row; delete history.
7. Create daily/weekly/monthly/disabled digests, run the worker twice, and confirm scheduling and digest history prevent immediate repeats.
8. Use an unsubscribe token, retry it, manipulate its alert/user portion, and confirm only the valid alert is disabled.

## Security regression

Attempt SQL injection in every ID/search/text field, stored/reflected XSS in feed and editor fields, CSRF, IDOR against another user's saves/preferences, unsupported URL schemes, DNS/private-network SSRF, executable uploads, and rapid repeated import/search/save requests. Confirm prepared statements, escaping, authorization, URL guards, upload validation, and rate limits prevent impact.
