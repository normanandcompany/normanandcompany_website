<?php

declare(strict_types=1);

final class SmtpMailer
{
    private $socket = null;

    public function __construct(private array $config = [])
    {
    }

    public function send(array $message): void
    {
        $smtp = is_array($message['smtp_config'] ?? null) ? $message['smtp_config'] : $this->config;
        $host = (string) ($smtp['smtp_host'] ?? normanRequiredEnv('NORMAN_SMTP_HOST'));
        $port = (int) ($smtp['smtp_port'] ?? normanEnv('NORMAN_SMTP_PORT', '587'));
        $encryption = strtolower((string) ($smtp['smtp_encryption'] ?? normanEnv('NORMAN_SMTP_ENCRYPTION', 'tls')));
        $target = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
        $this->socket = @stream_socket_client($target, $code, $error, 20, STREAM_CLIENT_CONNECT);
        if (!is_resource($this->socket)) {
            throw new RuntimeException("SMTP connection failed ({$code}). {$error}");
        }

        stream_set_timeout($this->socket, 20);
        try {
            $this->expect([220]);
            $hostname = preg_replace('/[^a-z0-9.-]/i', '', gethostname() ?: 'localhost') ?: 'localhost';
            $this->command('EHLO ' . $hostname, [250]);
            if ($encryption === 'tls') {
                $this->command('STARTTLS', [220]);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to start encrypted SMTP connection.');
                }
                $this->command('EHLO ' . $hostname, [250]);
            }

            $username = (string) ($smtp['smtp_username'] ?? normanRequiredEnv('NORMAN_SMTP_USERNAME'));
            $password = (string) ($smtp['smtp_password'] ?? normanRequiredEnv('NORMAN_SMTP_PASSWORD'));
            $auth = strtolower((string) ($smtp['smtp_auth'] ?? normanEnv('NORMAN_SMTP_AUTH', 'login')));
            if ($auth === 'plain') {
                $this->command('AUTH PLAIN ' . base64_encode("\0{$username}\0{$password}"), [235], false);
            } else {
                $this->command('AUTH LOGIN', [334]);
                $this->command(base64_encode($username), [334], false);
                $this->command(base64_encode($password), [235], false);
            }

            $fromEmail = self::cleanEmail((string) $message['from_email']);
            $toEmail = self::cleanEmail((string) $message['to_email']);
            $this->command('MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->command('RCPT TO:<' . $toEmail . '>', [250, 251]);
            $this->command('DATA', [354]);
            $payload = $this->buildMessage($message, $fromEmail, $toEmail);
            $payload = preg_replace('/(?m)^\./', '..', $payload) ?? $payload;
            fwrite($this->socket, $payload . "\r\n.\r\n");
            $this->expect([250]);
            $this->command('QUIT', [221]);
        } finally {
            if (is_resource($this->socket)) {
                fclose($this->socket);
            }
            $this->socket = null;
        }
    }

    private function buildMessage(array $message, string $fromEmail, string $toEmail): string
    {
        $boundary = '=_Norman_' . bin2hex(random_bytes(12));
        $subject = self::encodeHeader((string) $message['subject']);
        $fromName = self::encodeHeader((string) ($message['from_name'] ?? 'Norman and Company'));
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . substr(strrchr($fromEmail, '@') ?: '@localhost', 1) . '>',
            "From: {$fromName} <{$fromEmail}>",
            'To: <' . $toEmail . '>',
            'Subject: ' . $subject,
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"'
        ];
        if (!empty($message['reply_to'])) {
            $headers[] = 'Reply-To: <' . self::cleanEmail((string) $message['reply_to']) . '>';
        }
        if (!empty($message['unsubscribe_url'])) {
            $url = preg_replace('/[\r\n]/', '', (string) $message['unsubscribe_url']);
            $headers[] = 'List-Unsubscribe: <' . $url . '>';
            $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
        }

        $text = self::normalizeLines((string) ($message['text_body'] ?? EmailHtml::textVersion((string) $message['html_body'])));
        $html = self::normalizeLines((string) $message['html_body']);
        return implode("\r\n", $headers) . "\r\n\r\n"
            . '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($text) . "\r\n--" . $boundary
            . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n"
            . quoted_printable_encode($html) . "\r\n--" . $boundary . '--';
    }

    private function command(string $command, array $expected, bool $logSafe = true): void
    {
        if (!is_resource($this->socket)) {
            throw new RuntimeException('SMTP connection is not available.');
        }
        fwrite($this->socket, $command . "\r\n");
        $this->expect($expected, $logSafe ? $command : '[credential]');
    }

    private function expect(array $expected, string $context = ''): string
    {
        $response = '';
        do {
            $line = fgets($this->socket, 4096);
            if ($line === false) {
                throw new RuntimeException('SMTP server stopped responding.');
            }
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');

        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $expected, true)) {
            $safeResponse = preg_replace('/[\r\n]+/', ' ', trim($response));
            throw new RuntimeException('SMTP rejected ' . ($context ?: 'request') . ": {$safeResponse}");
        }
        return $response;
    }

    private static function cleanEmail(string $email): string
    {
        $email = strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new InvalidArgumentException('Invalid email address.');
        }
        return $email;
    }

    private static function encodeHeader(string $value): string
    {
        $value = trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function normalizeLines(string $value): string
    {
        return preg_replace('/\r\n|\r|\n/', "\r\n", $value) ?? $value;
    }
}
