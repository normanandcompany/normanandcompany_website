# Printful Integration

## Architecture

Printful is one fulfillment provider in the existing vendor architecture. Customer purchases remain in `orders` and `order_items`; the separate CRM `sales_orders` workflow is unchanged. `transactions` remains the generic payment ledger. No Stripe code or Stripe-specific data has been added.

The intended production flow is:

```text
Browser cart -> server cart validation -> Printful shipping quote
-> server order preparation (unpaid) -> future verified payment
-> orders.payment_status = paid -> startOrderFulfillment()
-> vendor grouping -> Printful draft -> optional confirmation
-> authenticated status synchronization -> local shipments/tracking
```

All prices, variants, shipping rates, totals, payment state, and fulfillment authorization are determined server-side. Browser values never authorize fulfillment.

## Database changes

Run these migrations in order with the migration database account. They are additive and MariaDB-compatible:

1. `database/migrations/2026_08_23_create_printful_fulfillment.php`
2. `database/migrations/2026_08_29_add_variant_pricing.php`
3. `database/migrations/2026_08_29_sort_adult_apparel_sizes.php`
4. `database/migrations/2026_09_06_add_printful_shipping_variant_ids.php`

The migration:

- identifies providers generically with `vendors.fulfillment_provider` and associates the existing Printful vendor with `printful`;
- adds active/sort/timestamp metadata to `apparel_sizes` without deleting legacy rows;
- creates `childrens_apparel_sizes` (legacy `Child-*` adult-table rows remain untouched for history);
- adds `products.apparel_size_type`;
- creates `product_variants`, `vendor_product_mappings`, and `vendor_variant_mappings`;
- separates `orders.payment_status`, `orders.order_status`, and `orders.fulfillment_status` and adds generic payment/reference and immutable shipping snapshot fields;
- adds variant/vendor/size snapshots to `order_items`;
- creates expiring, single-use `checkout_shipping_quotes`;
- creates generic `vendor_fulfillments` and `vendor_fulfillment_items`;
- creates multi-package `fulfillment_shipments` and `fulfillment_shipment_items`;
- creates idempotent `fulfillment_webhook_events` and non-sensitive `fulfillment_api_health` diagnostics.

No existing records or size rows are deleted. Foreign keys, indexes, unique external references, and one-fulfillment-per-order/vendor constraints enforce retry safety.

## Printful API client

`classes/Fulfillment/PrintfulClient.php` is the only HTTP client for Printful. It uses server-side bearer authentication, an optional `X-PF-Store-Id`, timeouts, bounded transient retries, safe error categories, and secret-free health records. It supports store diagnostics, Sync Products/Variants, shipping rates, draft orders, confirmation, order lookup, and webhook configuration lookup.

The implementation uses Printful's stable v1 API because the product workflow is based on existing Sync Products and Sync Variants. Draft creation uses a deterministic external fulfillment reference. On retry, the coordinator searches Printful by that external reference before attempting creation, which covers a network failure after Printful created an order but before the local response arrived.

## Product and variant mapping

Open **Admin -> Printful Fulfillment**.

1. Load the live Printful Sync Products.
2. Choose the local Norman & Company product (each color remains a different local product).
3. Confirm the automatically selected adult or children's size catalog from Product Management.
4. Select the existing Printful Sync Product.
5. Map each offered local size to one Sync Variant.
6. Verify every selected Sync Variant represents the same color, then save.

Saving assigns the existing Printful vendor, records the generic product mapping, creates or activates only the selected local product-size variants, and records the Sync Variant mappings. It does not create products in Printful. A unique constraint prevents duplicate product/size mappings, while a database check prevents a variant from holding both adult and children's size IDs.

Non-apparel products are not required to have variants. Printful apparel checkout requires an active mapped variant. Unavailable/discontinued Sync Variants are rejected server-side.

## Adult and children's sizes

Adult products use `apparel_sizes`. New children's products use `childrens_apparel_sizes`, managed on the Printful administration page. Sizes can be active/inactive and sorted. `order_items.size_label` and `product_options` preserve the purchased size after a catalog size is deactivated.

The old `Child-Small`, `Child-Medium`, and `Child-Large` rows in `apparel_sizes` are deliberately preserved but excluded from new adult mapping controls.

## Cart and checkout

The storefront product endpoint now returns product-specific active variants. The browser stores the local `product_variant_id`, and the server verifies that it belongs to the product, is active, is mapped to the product's current vendor, and is available.

Authenticated customer checkout calls `customer/api/checkout.php` with CSRF protection. Guest checkout still directs customers to log in or register because the existing `orders.user_id` is mandatory. Checkout currently supports `US` and `CA` only. US states, Canadian provinces/territories, US ZIP codes, and Canadian postal codes are validated separately.

Only Printful items are sent to Printful's live shipping-rate endpoint. Product mapping stores both identifiers returned by Printful: the Sync Variant ID is used to create a fulfillment order, while the catalog Variant ID is used to request shipping rates. A quote is bound to hashes of the normalized server-validated cart and address, expires after a configured TTL, and can create only one order. Address, quantity, variant, cart, or expiration changes require a new quote. The selected rate is recovered from the server-stored response; browser-submitted amounts are ignored.

Mixed-vendor grouping is supported. Until another vendor receives its own shipping adapter, its shipping contribution is `0.00` and the checkout response says that non-Printful shipping is not configured.

Order preparation creates an `unpaid` order and immutable order-item snapshots. It never calls fulfillment and clearly reports `payment_integration = not_configured`.

## Fulfillment

The reusable entry point is:

```php
require_once __DIR__ . '/classes/Fulfillment/bootstrap.php';
$result = startOrderFulfillment($pdo, $orderId);
```

`FulfillmentCoordinator::processPaidOrder()` independently locks and verifies the order, requires `orders.payment_status = 'paid'`, requires items and vendors, creates one generic fulfillment per vendor, and dispatches only the Printful group to Printful. Calling it for an unpaid order throws before any external order is created.

Printful orders are created as drafts first. The external Printful ID is persisted before any confirmation request. With `PRINTFUL_AUTO_CONFIRM=false`, processing stops at the draft. With it enabled, the coordinator re-reads the paid state immediately before confirmation, then submits the draft. Failures remain visible as paid orders with fulfillment `error`; transient failures receive bounded retry metadata.

Other vendors remain pending for their future provider-specific adapters and are never sent to Printful.

## Webhooks and tracking

Endpoint: `https://normanandcompany.com/api/printfulWebhook.php?key=YOUR_URL_SECRET`

The stable v1 webhook payload does not provide a cryptographic signature relied upon here. The endpoint therefore requires an unguessable URL secret and treats every payload only as a notification. For order/shipment events it re-fetches the authoritative order using the authenticated Printful client before changing fulfillment or shipment state.

Raw vendor payloads are not stored. An event body hash supplies idempotency. Duplicate deliveries return success without reprocessing. Printful retries non-2xx deliveries, so processing failures are recorded and returned as errors. Multiple shipments and tracking numbers are upserted by external shipment ID. Cancellation/return/failure states are derived from the authenticated order response when Printful exposes them.

Customer order history queries shipments only through an ownership-constrained join and displays payment, fulfillment, carrier, tracking link, shipment date, and estimated delivery. It never exposes wholesale cost, raw payloads, or internal failures.

## Environment variables

Add these only to the existing external `.env` file resolved by `config/env.php`:

```dotenv
PRINTFUL_API_TOKEN=your_store_level_token
PRINTFUL_STORE_ID=
PRINTFUL_AUTO_CONFIRM=false
PRINTFUL_WEBHOOK_SECRET=a_long_random_url_secret
PRINTFUL_TIMEOUT_SECONDS=20
PRINTFUL_QUOTE_TTL_SECONDS=1800
APP_ENV=development
PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE=false
```

`PRINTFUL_API_TOKEN` is required for live API operations. `PRINTFUL_STORE_ID` is optional for a store-level token and available for account-level token support. Production should use `APP_ENV=production` and `PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE=false`. Begin production with auto-confirm disabled and enable it only after paid-order testing and operational approval.

The administration diagnostics page shows whether settings exist, auto-confirm state, last successful/failed API communication, and live connection errors. It never displays the token or webhook secret.

## Printful dashboard configuration

1. Create/verify a store-level token with store/products, orders, and webhook access.
2. Add it to the external `.env`; do not add it to Git or MariaDB.
3. Configure the single store webhook URL above using the secret from `PRINTFUL_WEBHOOK_SECRET`.
4. Enable order/shipment events such as `order_updated`, `order_failed`, `order_canceled`, `package_shipped`, and `shipment_returned` where available.
5. Use the webhook simulator, then verify the event and shipment records through the administration page.
6. Keep `PRINTFUL_AUTO_CONFIRM=false` until end-to-end paid-order testing is approved.

## Testing before Stripe

Run syntax/unit tests first:

```bash
/Applications/MAMP/bin/php/php8.4.17/bin/php tests/printful_unit.php
```

After a real local product has been mapped to a Printful Sync Product, the guarded end-to-end test can exercise live US/Canada rates, stale quote protection, mixed-vendor grouping, the unpaid-order gate, an unconfirmed Printful draft, retry idempotency, webhook replay, and transactional multiple-package synchronization:

```bash
PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE=true \
  /Applications/MAMP/bin/php/php8.4.17/bin/php \
  tests/printful_e2e.php --product=7 --user=1
```

This test creates a local development order and one real Printful draft. It refuses to run outside development or when auto-confirm is enabled, and it never confirms manufacturing.

In an explicitly non-production environment only, set:

```dotenv
APP_ENV=development
PRINTFUL_DEV_ALLOW_PAYMENT_OVERRIDE=true
PRINTFUL_AUTO_CONFIRM=false
```

Prepare an order through authenticated checkout, then run:

```bash
/Applications/MAMP/bin/php/php8.4.17/bin/php scripts/printful_test_fulfillment.php --order=123 --mark-paid
```

The CLI refuses web access, refuses production, refuses when auto-confirm is enabled, and requires the separate payment-override switch. It records `development_override`, never a fake Stripe identifier, and calls the exact coordinator future Stripe will call. Re-run it to verify that no second Printful order appears.

Also test US/Canada quotes, invalid countries/regions/postal codes, address/quantity/variant changes, quote expiration/reuse, mixed vendors, unavailable variants, authentication failure, rate limits, timeouts, webhook replay, multiple packages, and customer ownership.

## Troubleshooting

- **Missing token:** add `PRINTFUL_API_TOKEN` to the external environment file and reload PHP/Apache.
- **401/403:** verify token scopes and store ownership; add `PRINTFUL_STORE_ID` only if the token is account-level.
- **No rates:** verify the destination, Sync Variant availability, quantity, and Printful shipping configuration.
- **Stale quote:** recalculate after any address/cart/variant change or after TTL expiration.
- **Unpaid fulfillment blocked:** expected; only verified server payment state authorizes fulfillment.
- **Paid order / fulfillment error:** use the administration record to correct the mapping/API problem, then retry. The paid order remains intact.
- **Webhook failure:** verify the exact secret-bearing URL and token access, then manually synchronize the fulfillment.

## Limitations and remaining work

- Stripe is intentionally not implemented, so customer checkout stops after preparing an unpaid order.
- Guest order preparation remains unavailable because the existing master order requires a user.
- Tax calculation is currently `0.00`; a future authoritative tax service must run before payment.
- Non-Printful vendor shipping adapters are not present and currently contribute `0.00` shipping.
- Automated cancellation/refund requests to Printful are not exposed in the UI; authoritative cancellation/return status synchronization is supported.
- Production still requires its own external environment configuration, product mappings, webhook registration, migrations, and deployment smoke test.

## Future Stripe Integration

Stripe must integrate at the server-side payment boundary, never in Printful classes and never from a success page.

After a verified Stripe webhook resolves the local order, perform one database transaction that:

1. locks the `orders` row;
2. idempotently records/updates a generic `transactions` row with `order_id`, `payment_provider = 'stripe'`, the real Stripe reference, `transaction_status = 'paid'`, verified amount/currency, and `processed_at`;
3. verifies the Stripe amount/currency equals the server-owned `orders.total_amount`/`currency_code`;
4. sets `orders.payment_status = 'paid'`, `payment_provider = 'stripe'`, the real `payment_reference`, `payment_amount`, `payment_currency`, and `paid_at`;
5. commits;
6. invokes `startOrderFulfillment($pdo, $orderId)`.

The webhook event table for Stripe should have a unique provider/event ID so replay returns the prior result. Calling fulfillment more than once is safe: local unique constraints, deterministic external references, stored external IDs, and Printful external-reference lookup prevent duplicate merchandise.

Stripe must not call `PrintfulClient`, `createDraftOrder()`, or `confirmOrder()` directly. It must not trust query parameters, JavaScript payment flags, redirect pages, client totals, or a browser-provided PaymentIntent. Failed/canceled payments must not set `paid` or call fulfillment. Refunds should update generic transaction/order payment state independently; already-shipped fulfillment remains a separate fact.

### Future Stripe test plan

Test Stripe test payment -> verified server webhook -> local paid state -> coordinator -> one Printful draft. Replay the Stripe webhook and assert there is still one local vendor fulfillment and one Printful order. Also test failed/canceled payments, duplicate and delayed webhooks, amount/currency mismatch, paid order with Printful outage, refund after fulfillment, and payment success while Printful auto-confirm is disabled.
