# Cloudflare Analytics Dashboard Setup

This admin dashboard uses Cloudflare as the only analytics source. It does not add visitor cookies, first-party tracking, business-event tracking, server-log parsing, HostGator/Plesk integrations, or MariaDB analytics tables.

## Files

- Admin UI: `/admin/pages/home.php`
- Admin shell script include: `/admin/index.php`
- Browser code: `/admin/js/admin.js`
- Styles: `/admin/css/admin.css`
- Protected endpoint: `/admin/api/analytics/cloudflare-dashboard.php`
- Service classes: `/includes/services/CloudflareAnalytics*.php`
- Placeholder fallback config: `/config/cloudflare-analytics.example.php`

## Cloudflare API Token Permissions

Cloudflare's current GraphQL Analytics token setup documentation says to create a custom token with:

- `Account` > `Account Analytics` > `Read`
- Zone Resources scoped to the single Norman and Company zone

Cloudflare's permissions catalog also lists zone-level `Analytics Read` and `Zone Read`. Add `Zone` > `Zone` > `Read` only if your Cloudflare dashboard or validation workflow requires zone lookup access. Do not use the Global API Key or account password.

References checked July 19, 2026:

- https://developers.cloudflare.com/analytics/graphql-api/getting-started/authentication/api-token-auth/
- https://developers.cloudflare.com/fundamentals/api/reference/permissions/

## Create The Token

1. In Cloudflare, open Account API tokens.
2. Select Create Token.
3. Choose Custom token.
4. Add `Account Analytics: Read`.
5. Restrict Zone Resources to the Norman and Company zone.
6. Optionally add `Zone: Read` if needed for zone lookup/testing.
7. Set an expiration if your operational process supports token rotation.
8. Copy the token once and store it only in environment variables or the protected fallback file.

## Find The Zone ID

1. Open Cloudflare and select the Norman and Company account/site.
2. On the account/site Overview page, find the API section.
3. Copy the Zone ID.

Use the raw 32-character Zone ID. If you accidentally copy a scoped token resource value such as `com.cloudflare.api.account.zone.<zone-id>`, the application will normalize the final zone ID portion, but the raw Zone ID is still preferred.

Cloudflare reference: https://developers.cloudflare.com/fundamentals/account/find-account-and-zone-ids/

## Configuration

Preferred environment variables:

```ini
CLOUDFLARE_API_TOKEN=replace_with_cloudflare_api_token
CLOUDFLARE_ZONE_ID=replace_with_cloudflare_zone_id
```

Optional tuning:

```ini
CLOUDFLARE_ANALYTICS_MAX_DAYS=93
CLOUDFLARE_ANALYTICS_CACHE_DIR=
CLOUDFLARE_ANALYTICS_SECRETS_FILE=
NORMAN_SITE_TIMEZONE=America/Chicago
```

The application checks environment variables first. If either Cloudflare value is missing, it checks a PHP fallback secrets file.

## Local Configuration

Use `.env` for local development if that is how your local site is already configured. Do not commit `.env`.

```ini
CLOUDFLARE_API_TOKEN=replace_with_cloudflare_api_token
CLOUDFLARE_ZONE_ID=replace_with_cloudflare_zone_id
```

## HostGator Plesk Fallback File

If Plesk cannot set environment variables easily:

1. Create a `private` folder outside the public web root, for example beside `httpdocs`.
2. Copy `/config/cloudflare-analytics.example.php` to that protected folder as `cloudflare-analytics.php`.
3. Replace only the placeholder values in the protected copy.
4. Make sure the file is not reachable over HTTP.
5. If the protected file is not located at the default path, set `CLOUDFLARE_ANALYTICS_SECRETS_FILE` to its absolute filesystem path in Plesk/PHP settings.

The default fallback path is:

```text
../private/cloudflare-analytics.php
```

relative to the PHP document root.

## Endpoint Testing

Unauthenticated requests should return HTTP 401:

```bash
curl -i "https://www.normanandcompany.com/admin/api/analytics/cloudflare-dashboard.php?range=last_7_days"
```

From an authenticated admin browser session, test:

```text
/admin/api/analytics/cloudflare-dashboard.php?range=today
/admin/api/analytics/cloudflare-dashboard.php?range=yesterday
/admin/api/analytics/cloudflare-dashboard.php?range=last_7_days
/admin/api/analytics/cloudflare-dashboard.php?range=last_30_days
/admin/api/analytics/cloudflare-dashboard.php?range=this_month
/admin/api/analytics/cloudflare-dashboard.php?range=last_month
/admin/api/analytics/cloudflare-dashboard.php?range=custom&start_date=2026-07-01&end_date=2026-07-18
/admin/api/analytics/cloudflare-dashboard.php?range=last_7_days&force_refresh=1
```

Invalid date tests should return HTTP 422:

```text
?range=custom&start_date=2026-99-01&end_date=2026-07-18
?range=custom&start_date=2026-07-18&end_date=2026-07-01
```

Non-admin authenticated users should receive HTTP 403.

## Cache Behavior

The endpoint uses protected filesystem cache only. It does not write analytics data to MariaDB.

Some Cloudflare zones restrict GraphQL Analytics requests to one day at a time. The service automatically splits multi-day dashboard ranges into daily Cloudflare requests and merges the normalized response. The admin dashboard defaults to Yesterday so the first load is fast and reliable on zones with a 1-day query-width limit.

Cache key inputs:

- Hash of the Zone ID
- Query version
- Dashboard query type
- Start date
- End date
- Grouping interval
- Site timezone

Expiration:

- Ranges ending today: 10 minutes
- Recently completed ranges: 2 hours
- Older ranges: 24 hours

Force refresh is admin-only and throttled when the cached response is less than 60 seconds old.

Default cache location order:

1. `CLOUDFLARE_ANALYTICS_CACHE_DIR`, if set and outside the web root
2. `../cache/cloudflare-analytics` relative to the document root
3. The server temp directory

To clear the cache, delete the `.json` files in the active cache directory. Do not delete unrelated application files.

## GraphQL Datasets And Metrics

The dashboard queries these Cloudflare GraphQL datasets server-side only:

- `httpRequestsAdaptiveGroups`
  - `count`
  - `sum.edgeResponseBytes`
  - `sum.visits`
  - `dimensions.datetimeHour`
  - `dimensions.cacheStatus`
  - `dimensions.clientCountryName`
  - `dimensions.edgeResponseStatus`
  - `dimensions.clientRequestHTTPHost`
  - `dimensions.clientRequestPath`
  - optional dimensions: `userAgentBrowser`, `clientDeviceType`, `userAgentOS`
- `firewallEventsAdaptive`
  - `action`
  - `datetime`
  - `source`
  - `clientCountryName`
  - `clientRequestPath`

Unavailable metrics are returned as `status: "not_available"` and shown as Not available in the dashboard. The dashboard does not fabricate zeros for unsupported metrics.

Known not-shown metrics:

- Exact unique visitors
- Exact page views
- Exact unique IPs
- Exact origin request counts
- Business conversions, revenue, registrations, logins, cart, orders, newsletter, or contact-form analytics

## Security Notes

- Browser JavaScript calls only `/admin/api/analytics/cloudflare-dashboard.php`.
- The browser never receives the API token, Zone ID, secrets path, authorization headers, raw Cloudflare query, or stack traces.
- Every analytics request requires the existing admin session role.
- The service logs only redacted technical context.
- The fallback secrets file must stay outside the public web root.
- Real secrets are ignored by Git.

## Rollback

1. Remove the Cloudflare dashboard markup from `/admin/pages/home.php`.
2. Remove the Chart.js script tag from `/admin/index.php`.
3. Remove the Cloudflare analytics JavaScript from `/admin/js/admin.js`.
4. Remove the Cloudflare analytics CSS from `/admin/css/admin.css`.
5. Delete `/admin/api/analytics/`.
6. Delete `/includes/services/CloudflareAnalytics*.php`.
7. Remove Cloudflare variables from the local/Plesk environment.
8. Delete only Cloudflare analytics cache files from the active cache directory.
