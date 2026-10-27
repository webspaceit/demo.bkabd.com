<?php

namespace App\Services;

use App\Core\Config;
use RuntimeException;

/** Small authenticated SMTP sender for reports with one CSV attachment. */
final class SmtpMailer
{
    public function sendWithCsvAttachment(string $recipient, string $subject, string $body, string $filename, string $csv): void
    {
        $host = trim((string) Config::get('MAIL_HOST', ''));
        $port = Config::getInt('MAIL_PORT', 465);
        $encryption = strtolower(trim((string) Config::get('MAIL_ENCRYPTION', 'ssl')));
        $username = (string) Config::get('MAIL_USERNAME', '');
        $password = (string) Config::get('MAIL_PASSWORD', '');
        $from = trim((string) Config::get('MAIL_FROM_ADDRESS', ''));
        $fromName = trim(preg_replace('/[\\r\\n]+/', ' ', (string) Config::get('MAIL_FROM_NAME', 'BKABD Reports')) ?? 'BKABD Reports');

        if ($host === '' || $username === '' || $password === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Email is not configured yet. Add the reports mailbox SMTP settings to demo’s private .env file.');
        }
        if (!preg_match('/^[a-zA-Z0-9.-]+$/', $host) || $port < 1 || $port > 65535 || !in_array($encryption, ['ssl', 'tls'], true)) {
            throw new RuntimeException('The SMTP host, port, or encryption setting is invalid.');
        }

        $ssl = [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'peer_name' => $host,
            'SNI_enabled' => true,
        ];
        $context = stream_context_create(['ssl' => $ssl]);
        $transport = $encryption === 'ssl' ? 'ssl://' : 'tcp://';
        $socket = @stream_socket_client($transport . $host . ':' . $port, $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $context);
        if (!is_resource($socket)) {
            throw new RuntimeException('Could not connect to the configured outgoing mail server.');
        }
        stream_set_timeout($socket, 25);

        try {
            $this->expect($socket, [220], 'mail server greeting');
            $this->command($socket, 'EHLO demo.bkabd.com', [250], 'mail server greeting');
            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220], 'secure connection setup');
                $crypto = @stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($crypto !== true) { throw new RuntimeException('Could not establish a secure connection to the mail server.'); }
                $this->command($socket, 'EHLO demo.bkabd.com', [250], 'secure mail server greeting');
            }

            $this->command($socket, 'AUTH LOGIN', [334], 'mailbox sign-in');
            $this->command($socket, base64_encode($username), [334], 'mailbox sign-in');
            $this->command($socket, base64_encode($password), [235], 'mailbox sign-in');
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250], 'sender verification');
            $this->command($socket, 'RCPT TO:<' . $recipient . '>', [250, 251], 'recipient verification');
            $this->command($socket, 'DATA', [354], 'message start');

            $boundary = 'bkabd_' . bin2hex(random_bytes(16));
            $safeFilename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename) ?: 'report.csv';
            $encodedName = '=?UTF-8?B?' . base64_encode($fromName) . '?=';
            $headers = [
                'Date: ' . date(DATE_RFC2822),
                'From: ' . $encodedName . ' <' . $from . '>',
                'To: <' . $recipient . '>',
                'Subject: ' . $subject,
                'MIME-Version: 1.0',
                'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            ];
            $message = implode("\r\n", $headers) . "\r\n\r\n"
                . '--' . $boundary . "\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($body), 76, "\r\n")
                . '--' . $boundary . "\r\n"
                . 'Content-Type: text/csv; charset=UTF-8; name="' . $safeFilename . "\"\r\n"
                . "Content-Transfer-Encoding: base64\r\n"
                . 'Content-Disposition: attachment; filename="' . $safeFilename . "\"\r\n\r\n"
                . chunk_split(base64_encode($csv), 76, "\r\n")
                . '--' . $boundary . "--\r\n";
            $message = preg_replace('/(?m)^\./', '..', $message) ?? $message;
            $this->write($socket, $message . ".\r\n");
            $this->expect($socket, [250], 'message delivery');
            $this->command($socket, 'QUIT', [221], 'mail server close');
        } finally {
            fclose($socket);
        }
    }

    /** @param list<int> $expected */
    private function command($socket, string $command, array $expected, string $stage): void
    {
        $this->write($socket, $command . "\r\n");
        $this->expect($socket, $expected, $stage);
    }

    private function write($socket, string $data): void
    {
        $length = strlen($data);
        $offset = 0;
        while ($offset < $length) {
            $written = @fwrite($socket, substr($data, $offset));
            if ($written === false || $written === 0) { throw new RuntimeException('The outgoing mail server closed the connection.'); }
            $offset += $written;
        }
    }

    /** @param list<int> $expected */
    private function expect($socket, array $expected, string $stage): void
    {
        $response = '';
        do {
            $line = @fgets($socket, 515);
            if ($line === false) { throw new RuntimeException('The outgoing mail server did not respond during ' . $stage . '.'); }
            $response = $line;
            $code = (int) substr($line, 0, 3);
        } while (isset($line[3]) && $line[3] === '-');

        if (!in_array($code, $expected, true)) {
            throw new RuntimeException('The outgoing mail server rejected the request during ' . $stage . ' (response ' . $code . ').');
        }
    }
}
