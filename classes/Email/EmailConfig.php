<?php

declare(strict_types=1);

final class EmailConfig
{
    private ?array $record = null;

    public function __construct(private PDO $pdo)
    {
    }

    public function delivery(): array
    {
        return [
            'newsletter' => $this->profile('newsletter'),
            'sales' => $this->profile('sales')
        ];
    }

    public function publicValues(): array
    {
        $values = [];
        foreach (['newsletter', 'sales'] as $profile) {
            $delivery = $this->profile($profile, false);
            foreach ($delivery as $key => $value) {
                if ($key !== 'smtp_password') {
                    $values[$profile . '_' . $key] = $value;
                }
            }
            $values[$profile . '_password_configured'] = $this->passwordConfigured($profile);
        }
        $values['encryption_key_configured'] = $this->encryptionKey() !== null;
        $values['source'] = $this->record() ? 'database' : 'environment';
        return $values;
    }

    public function isReady(): bool
    {
        try {
            foreach ($this->delivery() as $profile) {
                if ($profile['smtp_host'] === '' || $profile['smtp_username'] === '' || $profile['smtp_password'] === ''
                    || !filter_var($profile['from_email'], FILTER_VALIDATE_EMAIL)
                    || !filter_var($profile['reply_to'], FILTER_VALIDATE_EMAIL)) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function save(array $input, int $userId): void
    {
        $newsletter = $this->validatedProfile('newsletter', $input, 'Norman and Company Newsletter');
        $sales = $this->validatedProfile('sales', $input, 'Norman and Company');
        $current = $this->record();
        $newsletterPassword = $this->passwordForSave('newsletter', (string) ($input['newsletter_smtp_password'] ?? ''), $current);
        $salesPassword = $this->passwordForSave('sales', (string) ($input['sales_smtp_password'] ?? ''), $current);

        $stmt = $this->pdo->prepare("INSERT INTO email_configuration
            (id,smtp_host,smtp_port,smtp_encryption,smtp_auth,smtp_username,smtp_password_encrypted,
             newsletter_smtp_host,newsletter_smtp_port,newsletter_smtp_encryption,newsletter_smtp_auth,newsletter_smtp_username,newsletter_smtp_password_encrypted,
             sales_smtp_host,sales_smtp_port,sales_smtp_encryption,sales_smtp_auth,sales_smtp_username,sales_smtp_password_encrypted,
             newsletter_from_email,newsletter_from_name,newsletter_reply_to,sales_from_email,sales_from_name,sales_reply_to,updated_by_user_id)
            VALUES(1,:legacy_host,:legacy_port,:legacy_encryption,:legacy_auth,:legacy_username,:legacy_password,
             :newsletter_host,:newsletter_port,:newsletter_encryption,:newsletter_auth,:newsletter_username,:newsletter_password,
             :sales_host,:sales_port,:sales_encryption,:sales_auth,:sales_username,:sales_password,
             :newsletter_email,:newsletter_name,:newsletter_reply,:sales_email,:sales_name,:sales_reply,:user)
            ON DUPLICATE KEY UPDATE
             smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_auth=VALUES(smtp_auth),smtp_username=VALUES(smtp_username),smtp_password_encrypted=VALUES(smtp_password_encrypted),
             newsletter_smtp_host=VALUES(newsletter_smtp_host),newsletter_smtp_port=VALUES(newsletter_smtp_port),newsletter_smtp_encryption=VALUES(newsletter_smtp_encryption),newsletter_smtp_auth=VALUES(newsletter_smtp_auth),newsletter_smtp_username=VALUES(newsletter_smtp_username),newsletter_smtp_password_encrypted=VALUES(newsletter_smtp_password_encrypted),
             sales_smtp_host=VALUES(sales_smtp_host),sales_smtp_port=VALUES(sales_smtp_port),sales_smtp_encryption=VALUES(sales_smtp_encryption),sales_smtp_auth=VALUES(sales_smtp_auth),sales_smtp_username=VALUES(sales_smtp_username),sales_smtp_password_encrypted=VALUES(sales_smtp_password_encrypted),
             newsletter_from_email=VALUES(newsletter_from_email),newsletter_from_name=VALUES(newsletter_from_name),newsletter_reply_to=VALUES(newsletter_reply_to),
             sales_from_email=VALUES(sales_from_email),sales_from_name=VALUES(sales_from_name),sales_reply_to=VALUES(sales_reply_to),updated_by_user_id=VALUES(updated_by_user_id)");
        $stmt->execute([
            ':legacy_host' => $newsletter['smtp_host'], ':legacy_port' => $newsletter['smtp_port'],
            ':legacy_encryption' => $newsletter['smtp_encryption'], ':legacy_auth' => $newsletter['smtp_auth'],
            ':legacy_username' => $newsletter['smtp_username'], ':legacy_password' => $newsletterPassword,
            ':newsletter_host' => $newsletter['smtp_host'], ':newsletter_port' => $newsletter['smtp_port'],
            ':newsletter_encryption' => $newsletter['smtp_encryption'], ':newsletter_auth' => $newsletter['smtp_auth'],
            ':newsletter_username' => $newsletter['smtp_username'], ':newsletter_password' => $newsletterPassword,
            ':sales_host' => $sales['smtp_host'], ':sales_port' => $sales['smtp_port'],
            ':sales_encryption' => $sales['smtp_encryption'], ':sales_auth' => $sales['smtp_auth'],
            ':sales_username' => $sales['smtp_username'], ':sales_password' => $salesPassword,
            ':newsletter_email' => $newsletter['from_email'], ':newsletter_name' => $newsletter['from_name'],
            ':newsletter_reply' => $newsletter['reply_to'], ':sales_email' => $sales['from_email'],
            ':sales_name' => $sales['from_name'], ':sales_reply' => $sales['reply_to'], ':user' => $userId
        ]);
        $this->record = null;
    }

    private function profile(string $profile, bool $includePassword = true): array
    {
        $record = $this->record();
        $upper = strtoupper($profile);
        $legacy = fn(string $field) => $record[$field] ?? null;
        $value = fn(string $field, string $env, string $default = '') =>
            $record[$profile . '_' . $field] ?? $legacy($field) ?? normanEnv("NORMAN_{$upper}_{$env}", normanEnv("NORMAN_{$env}", $default));
        $result = [
            'smtp_host' => (string) $value('smtp_host', 'SMTP_HOST'),
            'smtp_port' => (int) $value('smtp_port', 'SMTP_PORT', '587'),
            'smtp_encryption' => (string) $value('smtp_encryption', 'SMTP_ENCRYPTION', 'tls'),
            'smtp_auth' => (string) $value('smtp_auth', 'SMTP_AUTH', 'login'),
            'smtp_username' => (string) $value('smtp_username', 'SMTP_USERNAME'),
            'from_email' => (string) ($record[$profile . '_from_email'] ?? normanEnv("NORMAN_{$upper}_FROM_EMAIL", $profile === 'newsletter' ? 'newsletters@normanandcompany.com' : '')),
            'from_name' => (string) ($record[$profile . '_from_name'] ?? normanEnv("NORMAN_{$upper}_FROM_NAME", $profile === 'newsletter' ? 'Norman and Company Newsletter' : 'Norman and Company')),
            'reply_to' => (string) ($record[$profile . '_reply_to'] ?? normanEnv("NORMAN_{$upper}_REPLY_TO", $profile === 'newsletter' ? 'newsletters@normanandcompany.com' : ''))
        ];
        if ($includePassword) {
            $result['smtp_password'] = $this->configuredPassword($profile, $record);
        }
        return $result;
    }

    private function validatedProfile(string $profile, array $input, string $defaultName): array
    {
        $label = $profile === 'newsletter' ? 'Newsletter' : 'Sales';
        $host = trim((string) ($input[$profile . '_smtp_host'] ?? ''));
        $username = trim((string) ($input[$profile . '_smtp_username'] ?? ''));
        $port = (int) ($input[$profile . '_smtp_port'] ?? 587);
        if ($host === '' || $username === '' || $port < 1 || $port > 65535) {
            throw new InvalidArgumentException("Enter a valid {$label} SMTP host, port, and username.");
        }
        $encryption = (string) ($input[$profile . '_smtp_encryption'] ?? 'tls');
        $auth = (string) ($input[$profile . '_smtp_auth'] ?? 'login');
        if (!in_array($encryption, ['tls', 'ssl', 'none'], true) || !in_array($auth, ['login', 'plain'], true)) {
            throw new InvalidArgumentException("Choose valid {$label} SMTP security options.");
        }
        $fromEmail = strtolower(trim((string) ($input[$profile . '_from_email'] ?? '')));
        $replyTo = strtolower(trim((string) ($input[$profile . '_reply_to'] ?? '')));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL) || !filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Enter valid {$label} From and Reply-To addresses.");
        }
        return [
            'smtp_host' => $host, 'smtp_port' => $port, 'smtp_encryption' => $encryption,
            'smtp_auth' => $auth, 'smtp_username' => $username, 'from_email' => $fromEmail,
            'from_name' => mb_substr(trim((string) ($input[$profile . '_from_name'] ?? $defaultName)), 0, 180),
            'reply_to' => $replyTo
        ];
    }

    private function passwordForSave(string $profile, string $password, ?array $record): string
    {
        $existing = $record[$profile . '_smtp_password_encrypted'] ?? $record['smtp_password_encrypted'] ?? null;
        if ($password !== '') {
            return $this->encrypt($password);
        }
        if ($existing) {
            return $existing;
        }
        $upper = strtoupper($profile);
        if ((string) normanEnv("NORMAN_{$upper}_SMTP_PASSWORD", normanEnv('NORMAN_SMTP_PASSWORD', '')) !== '') {
            return '';
        }
        throw new InvalidArgumentException(($profile === 'newsletter' ? 'Newsletter' : 'Sales') . ' SMTP password is required the first time configuration is saved.');
    }

    private function configuredPassword(string $profile, ?array $record): string
    {
        $encrypted = $record[$profile . '_smtp_password_encrypted'] ?? $record['smtp_password_encrypted'] ?? null;
        if ($encrypted) {
            return $this->decrypt($encrypted);
        }
        $upper = strtoupper($profile);
        return (string) normanEnv("NORMAN_{$upper}_SMTP_PASSWORD", normanEnv('NORMAN_SMTP_PASSWORD', ''));
    }

    private function passwordConfigured(string $profile): bool
    {
        $record = $this->record();
        $upper = strtoupper($profile);
        return !empty($record[$profile . '_smtp_password_encrypted']) || !empty($record['smtp_password_encrypted'])
            || (string) normanEnv("NORMAN_{$upper}_SMTP_PASSWORD", normanEnv('NORMAN_SMTP_PASSWORD', '')) !== '';
    }

    private function record(): ?array
    {
        if ($this->record === null) {
            $record = $this->pdo->query('SELECT * FROM email_configuration WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            $this->record = $record ?: [];
        }
        return $this->record ?: null;
    }

    private function encrypt(string $plaintext): string
    {
        $key = $this->encryptionKey(true);
        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new RuntimeException('Unable to encrypt the SMTP password.');
        }
        return base64_encode($iv . $tag . $ciphertext);
    }

    private function decrypt(string $encoded): string
    {
        $key = $this->encryptionKey();
        $payload = base64_decode($encoded, true);
        if ($key === null || $payload === false || strlen($payload) < 29) {
            throw new RuntimeException('The stored SMTP password cannot be decrypted. Check NORMAN_EMAIL_CONFIG_KEY.');
        }
        $plaintext = openssl_decrypt(substr($payload, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($payload, 0, 12), substr($payload, 12, 16));
        if ($plaintext === false) {
            throw new RuntimeException('The stored SMTP password cannot be decrypted. Check NORMAN_EMAIL_CONFIG_KEY.');
        }
        return $plaintext;
    }

    private function encryptionKey(bool $create = false): ?string
    {
        $configured = (string) normanEnv('NORMAN_EMAIL_CONFIG_KEY', (string) normanEnv('NORMAN_EMAIL_TOKEN_KEY', ''));
        if ($configured !== '') {
            return hash('sha256', $configured, true);
        }
        $keyPath = dirname(normanEnvFilePath()) . DIRECTORY_SEPARATOR . 'email-config.key';
        if (is_file($keyPath) && is_readable($keyPath)) {
            $fileKey = trim((string) file_get_contents($keyPath));
            return $fileKey === '' ? null : hash('sha256', $fileKey, true);
        }
        if (!$create) {
            return null;
        }
        $directory = dirname($keyPath);
        if (!is_dir($directory) || !is_writable($directory)) {
            throw new InvalidArgumentException('Set NORMAN_EMAIL_CONFIG_KEY in the protected environment file before saving SMTP passwords.');
        }
        $handle = @fopen($keyPath, 'x');
        if ($handle !== false) {
            $fileKey = base64_encode(random_bytes(48));
            fwrite($handle, $fileKey . PHP_EOL);
            fclose($handle);
            @chmod($keyPath, 0600);
            return hash('sha256', $fileKey, true);
        }
        if (is_file($keyPath) && is_readable($keyPath)) {
            $fileKey = trim((string) file_get_contents($keyPath));
            return $fileKey === '' ? null : hash('sha256', $fileKey, true);
        }
        throw new InvalidArgumentException('Unable to create the protected email configuration key. Set NORMAN_EMAIL_CONFIG_KEY manually.');
    }
}
