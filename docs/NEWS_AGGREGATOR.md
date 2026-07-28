# Norman and Company Cruise and Resort News Aggregator

## Overview

The news module imports approved RSS, Atom, and mapped JSON API sources; normalizes and deduplicates stories; holds them for editorial review; and publishes original summaries with clear attribution. Phase 2 adds deterministic classification, relevance scoring, related-story data, reviewable summary jobs, image permission records, and newsletter HTML. Phase 3 adds user-controlled interests, personalized feeds, saved stories and private notes, alerts, digest history, and privacy controls.

External article bodies are never imported. Feed descriptions are reduced to plain text, and external images are hidden unless an administrator records approval.

## Directory map

- `database/migrations/news_phase_1.sql`, `news_phase_2.sql`, `news_phase_3.sql`: ordered schema and seed data.
- `classes/News/`: repositories and import, duplication, classification, queue, image, newsletter, recommendation, and security services.
- `connectors/news/`: common connector interface plus RSS/Atom and JSON implementations.
- `admin/pages/news.php`, `admin/api/news/`: authenticated editorial UI and endpoints.
- `news.php`, `api/news/search.php`, `sitemap-news.php`: public pages, search API, and sitemap.
- `customer/pages/` and `customer/api/news.php`: preference, feed, saved-news, alert, and privacy features.
- `scripts/`: CLI import, background-job, and digest workers.
- `config/news.example.php`: non-secret configuration reference.

## Database installation

Back up the database, then run these files in order with a migration-capable database account:

```sh
mysql -h DB_HOST -u MIGRATION_USER -p DATABASE_NAME < database/migrations/news_phase_1.sql
mysql -h DB_HOST -u MIGRATION_USER -p DATABASE_NAME < database/migrations/news_phase_2.sql
mysql -h DB_HOST -u MIGRATION_USER -p DATABASE_NAME < database/migrations/news_phase_3.sql
```

Phase 1 remains independently usable. Phase 2 and Phase 3 must be applied in order. The web and admin database roles need `SELECT`, `INSERT`, `UPDATE`, and `DELETE` permissions for the applicable new tables. The migrations contain no production feed URLs and activate no external sources.

## Configuration

The external `../normansecret/.env` file is authoritative and is loaded by `config/env.php`. Keep real values outside the public web directory and repository:

- `NEWS_UNSUBSCRIBE_SECRET`: at least 32 random characters; required before users can create digest alerts.
- `NEWS_MAIL_TRANSPORT`: leave unset or use `export`; set to `mail` only after HostGator email delivery is tested.
- `NEWS_FROM_EMAIL`: verified sender used by the optional PHP mail transport.
- API credentials: store each credential in an environment variable, then put only its environment-variable name in a source's `connector_config_json`.

Uploaded news images go to `images/news/` with generated names and non-executable MIME types. Ensure PHP can create/write that directory. Uploaded media begins in `pending`; publishing still requires the article's image approval checkbox and license/attribution review.

## Sources and imports

Open **Admin → News Aggregator → Sources**. Save a source disabled, select **Test**, inspect up to ten normalized items, then activate it. RSS and Atom work without credentials. A JSON source uses `source_type=api` and optional configuration shaped like:

```json
{
  "items_key": "articles",
  "bearer_token_env": "TRAVEL_API_TOKEN",
  "field_map": {
    "headline": "title",
    "source_url": "url",
    "published_at": "publishedAt"
  }
}
```

Manual import and source test requests require an administrator session, CSRF token, and rate-limit allowance. The importer uses a clear user agent, connection/read timeouts, a 5 MB response ceiling, secure XML flags, a three-redirect ceiling, and public-address validation on each redirect. One source failure does not stop the scheduled batch.

Duplicate matching checks source/external ID, normalized URL, content hash, and same-source headline similarity. Cross-source similar stories are retained and recorded in `news_article_relations` for editorial judgment.

## Review and publishing

Use **Review** to edit the public headline, slug, summaries, category, entities, keywords, metadata, dates, image permission details, and status. `published` articles need a publication timestamp and then appear at:

- `/news.php`
- `/news.php?type=cruise`
- `/news.php?type=resort`
- `/news.php?category=category-slug`
- `/news.php?source=source-slug`
- `/news.php?article=article-slug`

Rejected and archived stories never appear publicly. Every public detail page labels the source and links to the complete external story with safe link attributes. The XML sitemap is `/sitemap-news.php`.

## Classification, summaries, related stories, and jobs

Classification begins with editable deterministic rules in `news_classification_rules`. Rules suggest type/category and confidence; administrators make the final choice. Relevance is a documented 0–100 combination of source default, source trust, official-source status, freshness, classification confidence, and promotional-language penalty.

Queueing a summary creates a MariaDB job. The built-in provider produces a conservative extractive draft from already-permitted excerpt text and marks it `needs_review`; it never auto-publishes. `NewsSummaryProvider` is the extension point for a future AI provider, and the system works normally without one.

Run jobs with:

```sh
php scripts/process_news_jobs.php 25
```

Related coverage can be represented through similarity relations or editorial story clusters. Newsletter drafts and ordered article rows render through `NewsNewsletterService`; no provider-specific email integration is assumed.

## Personalization, saved stories, and alerts

Signed-in customers have **My News**, **Saved News**, and **News Preferences** links. Explicit entity/category/keyword follows outweigh freshness and inferred views. Recommendation explanations are displayed. Users can disable personalization, disable history-based ranking, delete news-view history, hide recommendations, save stories, and keep private notes. Public news remains accessible without an account.

Digest alerts require explicit creation. The CLI worker respects frequency, avoids alerts with no content, stores article IDs in digest history, and uses signed opaque unsubscribe tokens rather than user IDs. Without `NEWS_MAIL_TRANSPORT=mail`, digest content is treated as provider-neutral/export workflow and no email is sent.

## HostGator cron examples

Find the hosting account's PHP CLI path in cPanel and substitute the absolute project path. Suggested schedules:

```cron
*/15 * * * * /usr/local/bin/php /absolute/path/to/scripts/import_news.php >> /absolute/private/logs/news-import.log 2>&1
*/10 * * * * /usr/local/bin/php /absolute/path/to/scripts/process_news_jobs.php 25 >> /absolute/private/logs/news-jobs.log 2>&1
15 7 * * * /usr/local/bin/php /absolute/path/to/scripts/send_news_digests.php >> /absolute/private/logs/news-digests.log 2>&1
```

The scripts use MariaDB named locks to prevent overlapping import/digest runs. Store logs outside the public document root, rotate weekly, retain only what operations require, and never log credentials or raw API configuration.

## Security and copyright notes

- All SQL using request data uses prepared statements; sorting and status values use allowlists.
- Admin/customer mutations require role checks and CSRF validation. High-impact repeated actions are rate limited per session.
- Feed URLs accept only HTTP(S), reject local/private/reserved addresses, disable automatic redirects, and validate each permitted redirect.
- XML external networking is disabled and arbitrary feed markup is stripped to text.
- Uploads are checked by detected MIME, size, dimensions, generated filename, and safe extensions.
- API keys belong in environment variables, never database display fields or JavaScript.
- Use owned, licensed, or explicitly permitted images. Store license notes and attribution before approval.
- Summaries must be original, factual, short, and attributed. Never paste full external articles into editorial fields.

## Troubleshooting

- **“Check that all news migrations have run”**: apply the migration files in order and grant the existing DB role access.
- **Feed host could not be resolved/private address rejected**: confirm public DNS and do not use localhost/intranet feeds.
- **Invalid XML**: view the source test result and confirm the endpoint returns RSS 2.0 or Atom rather than HTML.
- **Importer lock exit code 2**: another scheduled invocation is still running.
- **No public story**: confirm status is `published` and `published_at` is not in the future.
- **No digest**: configure `NEWS_UNSUBSCRIBE_SECRET`; configure and test a mail transport only when sending is intended.
- **Image unavailable**: verify `images/news/` permissions and image approval/attribution fields.

## Production release checklist

1. Apply migrations to a staging/local database and run the test plan.
2. Configure a long unsubscribe secret and optional provider credentials outside Git.
3. Create sources disabled; test licenses, attribution, redirect behavior, and sample items.
4. Activate selected sources and run a manual import.
5. Review/publish test stories and verify public filters, metadata, sitemap, and mobile layout.
6. Test customer preferences, saves, private notes, history deletion, and unsubscribe.
7. Add cron jobs with private log paths and confirm exit codes.
8. Deploy only the tested, approved revision to production.

## Intentional external-service limitations

No unverified feed is seeded or enabled. No AI, paid news API, stock image service, or email provider is hard-coded. JSON APIs need source-specific mappings and external credentials. The built-in mail transport is opt-in because this project had no existing email provider integration.
