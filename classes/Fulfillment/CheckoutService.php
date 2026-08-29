<?php

declare(strict_types=1);

final class CheckoutService
{
    private const ALLOWED_COUNTRIES = ['US', 'CA'];
    private const US_STATES = ['AL','AK','AZ','AR','CA','CO','CT','DE','DC','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
    private const CA_PROVINCES = ['AB','BC','MB','NB','NL','NS','NT','NU','ON','PE','QC','SK','YT'];

    public function __construct(
        private readonly PDO $pdo,
        private readonly PrintfulClient $printful,
        private readonly PrintfulConfig $config
    ) {
    }

    public function quote(int $userId, array $rawCart, array $rawAddress): array
    {
        $address = self::validateAddress($rawAddress);
        $items = $this->validateCart($rawCart);
        $printfulItems = array_values(array_filter($items, fn(array $item): bool => $item['fulfillment_provider'] === 'printful'));

        if ($printfulItems === []) {
            return [
                'success' => true,
                'requires_printful_shipping' => false,
                'rates' => [],
                'subtotal' => $this->subtotal($items),
                'currency_code' => 'USD',
                'message' => 'This cart has no Printful items. Shipping for other vendors is not configured.'
            ];
        }

        $vendorId = (int) $printfulItems[0]['vendor_id'];
        $rateItems = array_map(static fn(array $item): array => [
            'sync_variant_id' => (int) $item['external_variant_id'],
            'quantity' => (int) $item['quantity']
        ], $printfulItems);

        $response = $this->printful->shippingRates($this->printfulRecipient($address), $rateItems);
        $rawRates = is_array($response['result'] ?? null) ? $response['result'] : [];
        $rates = [];

        foreach ($rawRates as $rate) {
            $id = trim((string) ($rate['id'] ?? ''));
            $amount = $rate['rate'] ?? null;

            if ($id === '' || !is_numeric($amount)) {
                continue;
            }

            $rates[] = [
                'id' => $id,
                'name' => (string) ($rate['name'] ?? $id),
                'rate' => number_format((float) $amount, 2, '.', ''),
                'currency' => strtoupper((string) ($rate['currency'] ?? 'USD')),
                'min_delivery_days' => isset($rate['minDeliveryDays']) ? (int) $rate['minDeliveryDays'] : null,
                'max_delivery_days' => isset($rate['maxDeliveryDays']) ? (int) $rate['maxDeliveryDays'] : null,
                'min_delivery_date' => $rate['minDeliveryDate'] ?? null,
                'max_delivery_date' => $rate['maxDeliveryDate'] ?? null
            ];
        }

        if ($rates === []) {
            throw new RuntimeException('Printful did not return a shipping method for this address and cart.');
        }

        $cartHash = self::cartHash($items);
        $addressHash = self::addressHash($address);
        $token = bin2hex(random_bytes(32));
        $stmt = $this->pdo->prepare("INSERT INTO checkout_shipping_quotes (quote_token, user_id, vendor_id, cart_hash, address_hash, country_code, currency_code, rates_json, expires_at) VALUES (:token, :user_id, :vendor_id, :cart_hash, :address_hash, :country, 'USD', :rates, DATE_ADD(NOW(), INTERVAL :ttl SECOND))");
        $stmt->bindValue(':token', $token);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':vendor_id', $vendorId, PDO::PARAM_INT);
        $stmt->bindValue(':cart_hash', $cartHash);
        $stmt->bindValue(':address_hash', $addressHash);
        $stmt->bindValue(':country', $address['country_code']);
        $stmt->bindValue(':rates', json_encode($rates, JSON_THROW_ON_ERROR));
        $stmt->bindValue(':ttl', $this->config->quoteTtlSeconds, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'success' => true,
            'requires_printful_shipping' => true,
            'quote_token' => $token,
            'expires_in' => $this->config->quoteTtlSeconds,
            'rates' => $rates,
            'subtotal' => $this->subtotal($items),
            'currency_code' => 'USD'
        ];
    }

    public function prepareOrder(int $userId, array $rawCart, array $rawAddress, string $quoteToken, string $rateId): array
    {
        $address = self::validateAddress($rawAddress);
        $items = $this->validateCart($rawCart);
        $printfulItems = array_values(array_filter($items, fn(array $item): bool => $item['fulfillment_provider'] === 'printful'));
        $selectedRate = null;
        $quote = null;

        if ($printfulItems !== []) {
            $stmt = $this->pdo->prepare('SELECT * FROM checkout_shipping_quotes WHERE quote_token = :token AND user_id = :user_id AND expires_at > NOW() AND consumed_order_id IS NULL LIMIT 1');
            $stmt->execute([':token' => $quoteToken, ':user_id' => $userId]);
            $quote = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$quote || !hash_equals((string) $quote['cart_hash'], self::cartHash($items)) || !hash_equals((string) $quote['address_hash'], self::addressHash($address))) {
                throw new InvalidArgumentException('The shipping quote is stale. Recalculate shipping before preparing the order.');
            }

            $rates = json_decode((string) $quote['rates_json'], true, 512, JSON_THROW_ON_ERROR);
            foreach ($rates as $rate) {
                if (hash_equals((string) ($rate['id'] ?? ''), $rateId)) {
                    $selectedRate = $rate;
                    break;
                }
            }

            if (!$selectedRate) {
                throw new InvalidArgumentException('Select a valid shipping method from the current quote.');
            }
        }

        $subtotal = $this->subtotal($items);
        $shipping = $selectedRate ? number_format((float) $selectedRate['rate'], 2, '.', '') : '0.00';
        $tax = '0.00';
        $total = number_format((float) $subtotal + (float) $shipping, 2, '.', '');
        $orderNumber = 'NCO-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("INSERT INTO orders (user_id, order_number, order_status, payment_status, payment_amount, payment_currency, fulfillment_status, subtotal_amount, tax_amount, shipping_amount, total_amount, shipping_name, shipping_email, shipping_phone, shipping_address1, shipping_address2, shipping_city, shipping_state_code, shipping_country_code, shipping_postal_code, currency_code) VALUES (:user_id, :number, 'awaiting_payment', 'unpaid', :total, 'USD', 'pending', :subtotal, :tax, :shipping, :total, :name, :email, :phone, :address1, :address2, :city, :state, :country, :postal, 'USD')");
            $stmt->execute([
                ':user_id' => $userId,
                ':number' => $orderNumber,
                ':subtotal' => $subtotal,
                ':tax' => $tax,
                ':shipping' => $shipping,
                ':total' => $total,
                ':name' => $address['name'],
                ':email' => $address['email'],
                ':phone' => $address['phone'],
                ':address1' => $address['address1'],
                ':address2' => $address['address2'],
                ':city' => $address['city'],
                ':state' => $address['state_code'],
                ':country' => $address['country_code'],
                ':postal' => $address['postal_code']
            ]);
            $orderId = (int) $this->pdo->lastInsertId();

            $itemStmt = $this->pdo->prepare("INSERT INTO order_items (order_id, product_id, product_variant_id, vendor_id, product_sku, product_name, product_options, size_label, quantity, unit_price, unit_cost, line_subtotal, currency_code, fulfillment_status) VALUES (:order_id, :product_id, :variant_id, :vendor_id, :sku, :name, :options, :size, :quantity, :price, :cost, :subtotal, 'USD', 'pending')");
            $vendorIds = [];
            foreach ($items as $item) {
                $itemStmt->execute([
                    ':order_id' => $orderId,
                    ':product_id' => $item['product_id'],
                    ':variant_id' => $item['product_variant_id'],
                    ':vendor_id' => $item['vendor_id'],
                    ':sku' => $item['product_sku'],
                    ':name' => $item['product_name'],
                    ':options' => $item['size_label'] ? 'Size: ' . $item['size_label'] : null,
                    ':size' => $item['size_label'],
                    ':quantity' => $item['quantity'],
                    ':price' => $item['unit_price'],
                    ':cost' => $item['unit_cost'],
                    ':subtotal' => $item['line_subtotal']
                ]);
                $vendorIds[(int) $item['vendor_id']] = $item['fulfillment_provider'];
            }

            $fulfillmentStmt = $this->pdo->prepare("INSERT INTO vendor_fulfillments (order_id, vendor_id, external_reference, fulfillment_status, shipping_service_id, shipping_service_name, shipping_cost, currency_code) VALUES (:order_id, :vendor_id, :reference, 'pending', :service_id, :service_name, :shipping_cost, 'USD')");
            foreach ($vendorIds as $vendorId => $provider) {
                $isPrintful = $provider === 'printful';
                $fulfillmentStmt->execute([
                    ':order_id' => $orderId,
                    ':vendor_id' => $vendorId,
                    ':reference' => $orderNumber . '-V' . $vendorId,
                    ':service_id' => $isPrintful ? ($selectedRate['id'] ?? null) : null,
                    ':service_name' => $isPrintful ? ($selectedRate['name'] ?? null) : null,
                    ':shipping_cost' => $isPrintful ? $shipping : '0.00'
                ]);
            }

            if ($quote) {
                $stmt = $this->pdo->prepare('UPDATE checkout_shipping_quotes SET selected_rate_id = :id, selected_rate_name = :name, selected_rate_amount = :amount, consumed_order_id = :order_id WHERE id = :quote_id AND consumed_order_id IS NULL');
                $stmt->execute([':id' => $selectedRate['id'], ':name' => $selectedRate['name'], ':amount' => $shipping, ':order_id' => $orderId, ':quote_id' => $quote['id']]);
                if ($stmt->rowCount() !== 1) throw new RuntimeException('This shipping quote has already been used.');
            }

            $this->pdo->commit();
        } catch (Throwable $exception) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }

        return [
            'success' => true,
            'order_id' => $orderId,
            'order_number' => $orderNumber,
            'payment_status' => 'unpaid',
            'payment_integration' => 'not_configured',
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'total' => $total,
            'currency_code' => 'USD',
            'message' => 'Order prepared. Payment integration is not yet configured; fulfillment has not started.'
        ];
    }

    public static function validateAddress(array $input): array
    {
        $country = strtoupper(trim((string) ($input['country_code'] ?? '')));
        $state = strtoupper(trim((string) ($input['state_code'] ?? '')));
        if (!in_array($country, self::ALLOWED_COUNTRIES, true)) {
            throw new InvalidArgumentException('Shipping is currently available only to the United States and Canada.');
        }
        $validRegions = $country === 'US' ? self::US_STATES : self::CA_PROVINCES;
        if (!in_array($state, $validRegions, true)) {
            throw new InvalidArgumentException($country === 'US' ? 'Select a valid US state.' : 'Select a valid Canadian province or territory.');
        }

        $fields = [
            'name' => [220, true], 'email' => [255, true], 'phone' => [50, false],
            'address1' => [255, true], 'address2' => [255, false], 'city' => [100, true],
            'postal_code' => [25, true]
        ];
        $address = ['country_code' => $country, 'state_code' => $state];
        foreach ($fields as $field => [$length, $required]) {
            $value = trim((string) ($input[$field] ?? ''));
            if ($required && $value === '') {
                throw new InvalidArgumentException(ucwords(str_replace(['address1', '_'], ['address', ' '], $field)) . ' is required.');
            }
            if (mb_strlen($value) > $length) {
                throw new InvalidArgumentException('A shipping address field is too long.');
            }
            $address[$field] = $value === '' ? null : $value;
        }
        if (!filter_var($address['email'], FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Enter a valid shipping email address.');
        }
        if ($country === 'US' && !preg_match('/^\d{5}(?:-\d{4})?$/', (string) $address['postal_code'])) {
            throw new InvalidArgumentException('Enter a valid US ZIP code.');
        }
        if ($country === 'CA' && !preg_match('/^[A-Z]\d[A-Z][ -]?\d[A-Z]\d$/i', (string) $address['postal_code'])) {
            throw new InvalidArgumentException('Enter a valid Canadian postal code.');
        }
        return $address;
    }

    public static function cartHash(array $items): string
    {
        $normalized = array_map(static fn(array $item): array => [
            'product_id' => (int) ($item['product_id'] ?? 0),
            'product_variant_id' => (int) ($item['product_variant_id'] ?? 0),
            'quantity' => (int) ($item['quantity'] ?? 0)
        ], $items);
        usort($normalized, static fn(array $a, array $b): int => [$a['product_id'], $a['product_variant_id']] <=> [$b['product_id'], $b['product_variant_id']]);
        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }

    public static function addressHash(array $address): string
    {
        ksort($address);
        return hash('sha256', json_encode($address, JSON_THROW_ON_ERROR));
    }

    private function validateCart(array $rawCart): array
    {
        if ($rawCart === [] || count($rawCart) > 100) {
            throw new InvalidArgumentException('The cart must contain between 1 and 100 items.');
        }

        $stmt = $this->pdo->prepare("SELECT p.id AS product_id, p.product_name, p.sku AS product_sku, p.price, COALESCE(p.cost, 0) AS cost, p.inventory_count, p.is_apparel, p.vendor_id, v.fulfillment_provider, pv.id AS product_variant_id, pv.size_label_snapshot AS size_label, pv.is_active AS variant_active, COALESCE(vvm.external_variant_id, vpm.default_external_variant_id) AS external_variant_id, COALESCE(vvm.availability_status, vpm.default_availability_status) AS availability_status, CASE WHEN p.is_apparel = 1 THEN vvm.is_active ELSE CASE WHEN vpm.default_external_variant_id IS NOT NULL THEN 1 ELSE 0 END END AS mapping_active FROM products p INNER JOIN vendors v ON v.id = p.vendor_id LEFT JOIN product_variants pv ON pv.id = :variant_id AND pv.product_id = p.id LEFT JOIN vendor_product_mappings vpm ON vpm.product_id = p.id AND vpm.vendor_id = p.vendor_id AND vpm.mapping_status = 'active' LEFT JOIN vendor_variant_mappings vvm ON vvm.product_variant_id = pv.id AND vvm.vendor_product_mapping_id = vpm.id WHERE p.id = :product_id AND p.visible = 1 AND p.is_active = 1 AND v.visible = 1 AND v.is_active = 1 LIMIT 1");
        $items = [];
        foreach ($rawCart as $rawItem) {
            $productId = (int) ($rawItem['product_id'] ?? $rawItem['id'] ?? 0);
            $variantId = (int) ($rawItem['product_variant_id'] ?? $rawItem['variant_id'] ?? 0);
            $quantity = (int) ($rawItem['quantity'] ?? 0);
            if ($productId <= 0 || $quantity <= 0 || $quantity > 100) {
                throw new InvalidArgumentException('The cart contains an invalid product or quantity.');
            }
            $stmt->execute([':product_id' => $productId, ':variant_id' => $variantId ?: null]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                throw new InvalidArgumentException('A product in the cart is no longer available.');
            }
            $isApparel = (int) $row['is_apparel'] === 1;
            if ($isApparel && (!$variantId || !$row['product_variant_id'] || (int) $row['variant_active'] !== 1)) {
                throw new InvalidArgumentException('Select an available size for ' . $row['product_name'] . '.');
            }
            if ($row['fulfillment_provider'] === 'printful' && (!$row['external_variant_id'] || (int) $row['mapping_active'] !== 1 || in_array($row['availability_status'], ['discontinued', 'out_of_stock', 'temporary_out_of_stock'], true))) {
                throw new InvalidArgumentException($row['product_name'] . ' is not currently available from Printful in the selected size.');
            }
            if ($row['fulfillment_provider'] !== 'printful' && (int) $row['inventory_count'] < $quantity) {
                throw new InvalidArgumentException('The requested quantity of ' . $row['product_name'] . ' is not available.');
            }
            $price = number_format((float) $row['price'], 2, '.', '');
            $items[] = [
                ...$row,
                'product_id' => $productId,
                'product_variant_id' => $variantId ?: null,
                'quantity' => $quantity,
                'unit_price' => $price,
                'unit_cost' => number_format((float) $row['cost'], 2, '.', ''),
                'line_subtotal' => number_format((float) $price * $quantity, 2, '.', '')
            ];
        }
        return $items;
    }

    private function subtotal(array $items): string
    {
        return number_format(array_sum(array_map(static fn(array $item): float => (float) $item['line_subtotal'], $items)), 2, '.', '');
    }

    private function printfulRecipient(array $address): array
    {
        return array_filter([
            'name' => $address['name'], 'email' => $address['email'], 'phone' => $address['phone'],
            'address1' => $address['address1'], 'address2' => $address['address2'], 'city' => $address['city'],
            'state_code' => $address['state_code'], 'country_code' => $address['country_code'], 'zip' => $address['postal_code']
        ], static fn(mixed $value): bool => $value !== null && $value !== '');
    }
}
