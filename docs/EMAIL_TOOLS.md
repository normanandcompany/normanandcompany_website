# Email Tools setup

Email Tools stores newsletter templates, delivery runs, leads, sales templates, signatures, campaigns, recipient queues, suppressions, and delivery history in MariaDB. Mail settings are managed from the **Email Configuration** tab in Email Tools. Newsletter and sales SMTP accounts are configured independently, and both passwords are encrypted before storage and never returned to the browser.

## Required environment values

When the Email Configuration form first saves a password, it creates a permission-restricted `email-config.key` beside the external `.env` file. As an alternative, you can explicitly set a long encryption key in `.env`. Do not add either file to Git.

```dotenv
NORMAN_SITE_URL=https://www.normanandcompany.com
NORMAN_EMAIL_TOKEN_KEY=replace-with-at-least-32-random-characters
NORMAN_EMAIL_CONFIG_KEY=use-a-different-random-value-of-at-least-32-characters
```

SMTP and sender values can then be entered on the Email Configuration tab. Environment values remain supported as a fallback for deployments that prefer server-managed configuration. Each profile uses its own variables:

```dotenv
NORMAN_NEWSLETTER_SMTP_HOST=mail.example.com
NORMAN_NEWSLETTER_SMTP_PORT=587
NORMAN_NEWSLETTER_SMTP_ENCRYPTION=tls
NORMAN_NEWSLETTER_SMTP_AUTH=login
NORMAN_NEWSLETTER_SMTP_USERNAME=newsletter-mailbox-login
NORMAN_NEWSLETTER_SMTP_PASSWORD=newsletter-mailbox-password

NORMAN_NEWSLETTER_FROM_EMAIL=newsletters@normanandcompany.com
NORMAN_NEWSLETTER_FROM_NAME="Norman and Company Newsletter"
NORMAN_NEWSLETTER_REPLY_TO=newsletters@normanandcompany.com

NORMAN_SALES_SMTP_HOST=mail.example.com
NORMAN_SALES_SMTP_PORT=587
NORMAN_SALES_SMTP_ENCRYPTION=tls
NORMAN_SALES_SMTP_AUTH=login
NORMAN_SALES_SMTP_USERNAME=sales-mailbox-login
NORMAN_SALES_SMTP_PASSWORD=sales-mailbox-password
NORMAN_SALES_FROM_EMAIL=sales-sender@normanandcompany.com
NORMAN_SALES_FROM_NAME="Norman and Company"
NORMAN_SALES_REPLY_TO=sales-sender@normanandcompany.com
```

`NORMAN_SMTP_ENCRYPTION` supports `tls` for STARTTLS (usually port 587) and `ssl` for implicit TLS (usually port 465). The SMTP account must be permitted to send as both configured From addresses.

Optional delivery tuning values:

```dotenv
NORMAN_NEWSLETTER_INTERVAL_MINUTES=5
NORMAN_NEWSLETTER_BATCH_SIZE=25
NORMAN_SALES_INTERVAL_MINUTES=3
```

## Database migration

Run both migrations once in each environment before opening Email Tools:

```sh
php database/migrations/2026_08_05_create_email_tools.php
php database/migrations/2026_08_05_refine_email_tools.php
php database/migrations/2026_08_05_split_email_server_configuration.php
```

The migration is idempotent and creates only `email_*` tables. The web database account needs `SELECT`, `INSERT`, and `UPDATE` access to `email_suppressions`, `email_newsletter_recipients`, `email_sales_recipients`, and `email_leads` for the public unsubscribe page. The admin database account needs access to all `email_*` tables.

## Queue cron

Run the queue processor once per minute. The processor enforces the five-minute newsletter batch interval and three-minute sales interval itself, and uses a MariaDB advisory lock to prevent overlapping runs.

```cron
* * * * * /usr/local/bin/php /absolute/path/to/scripts/process_email_queue.php >/dev/null 2>&1
```

Use the production server's actual PHP CLI path and website path. Keep cron errors directed to a protected server log while first configuring the service.

## Operating notes

- Newsletter content is saved as a reusable template. The **Send monthly newsletter** form explicitly selects a template and snapshots its subject/body into that delivery run.
- Clicking **Send Newsletter** snapshots all active, visible users whose joined role name is `customer`, excluding suppressed addresses.
- Clicking **Send Campaign** snapshots all active leads, excluding suppressed addresses.
- A sales campaign sends one message per interval. A newsletter sends one configurable batch per interval.
- Failed deliveries retry up to three times with a 15-minute delay and remain visible in campaign/newsletter counts.
- Unsubscribing adds the address to the global suppression table so it is excluded from both future newsletters and sales campaigns.
- Templates use separate `{Subject}` and `{Body}` fields. `{FirstName}`, `{LastName}`, `{Company}`, and `{EmailAddress}` are replaced at send time where available.
