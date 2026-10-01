<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function mailHeader(string $s): string
{
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/** Envia e-mail pelo SMTP do .env. Retorna true quando o servidor aceita a mensagem. */
function sendMail(string $to, string $subject, string $html, string $text): bool
{
    $host = (string) getenv('MAIL_HOST');
    $port = (int) (getenv('MAIL_PORT') ?: 465);
    $enc = strtolower((string) getenv('MAIL_ENCRYPTION'));
    $user = (string) getenv('MAIL_USERNAME');
    $pass = (string) getenv('MAIL_PASSWORD');
    $from = (string) (getenv('MAIL_FROM_ADDRESS') ?: $user);
    $fromName = (string) (getenv('MAIL_FROM_NAME') ?: 'Coleta de Dados');

    if ($host === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        error_log('sendMail: configuracao ou destinatario invalido');
        return false;
    }

    $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        error_log('sendMail: conexao falhou ' . $errno . ' ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 515)) !== false) {
            $out .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $out;
    };
    $step = function (string $label, ?string $command, array $expect) use ($fp, $read): bool {
        if ($command !== null) {
            fwrite($fp, $command . "\r\n");
        }
        $reply = $read();
        if (!in_array((int) substr($reply, 0, 3), $expect, true)) {
            error_log('sendMail: falhou em ' . $label . ': ' . trim(substr($reply, 0, 200)));
            return false;
        }
        return true;
    };

    $ehlo = 'EHLO ' . (parse_url((string) getenv('APP_URL'), PHP_URL_HOST) ?: 'localhost');
    $ok = $step('banner', null, [220]) && $step('ehlo', $ehlo, [250]);
    if ($ok && $enc === 'tls') {
        $ok = $step('starttls', 'STARTTLS', [220])
            && stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)
            && $step('ehlo2', $ehlo, [250]);
    }

    $boundary = 'b' . bin2hex(random_bytes(12));
    $domain = substr((string) strrchr($from, '@'), 1);
    $headers = [
        'Date: ' . date('r'),
        'From: ' . mailHeader($fromName) . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . mailHeader($subject),
        'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . $domain . '>',
        'Auto-Submitted: auto-generated',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];
    $body = '--' . $boundary . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . '--' . $boundary . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . '--' . $boundary . "--\r\n";

    $ok = $ok
        && $step('auth', 'AUTH LOGIN', [334])
        && $step('auth-user', base64_encode($user), [334])
        && $step('auth-pass', base64_encode($pass), [235])
        && $step('mail-from', 'MAIL FROM:<' . $from . '>', [250])
        && $step('rcpt-to', 'RCPT TO:<' . $to . '>', [250, 251])
        && $step('data', 'DATA', [354])
        && $step('body', implode("\r\n", $headers) . "\r\n\r\n" . $body . '.', [250]);

    fwrite($fp, "QUIT\r\n");
    fclose($fp);
    return $ok;
}
