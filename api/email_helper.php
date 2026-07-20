<?php
/**
 * email_helper.php — Standalone SMTP mailer (no PHPMailer/composer needed)
 * Uses PHP's native socket-based SMTP or falls back to mail()
 */

function sendEmailViaSMTP(array $smtp, string $to, string $toName, string $subject, string $htmlBody, string $cc = ''): bool {
    // Try PHPMailer if available
    foreach ([
        __DIR__ . '/../vendor/autoload.php',
        __DIR__ . '/../../vendor/autoload.php',
        dirname(__DIR__) . '/vendor/autoload.php',
    ] as $autoload) {
        if (file_exists($autoload)) {
            try {
                require_once $autoload;
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host       = $smtp['host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $smtp['user'];
                $mail->Password   = $smtp['pass'];
                $mail->SMTPSecure = ($smtp['port'] == 465) ? 'ssl' : 'tls';
                $mail->Port       = $smtp['port'];
                $mail->setFrom($smtp['from'], $smtp['name'] ?? '');
                $mail->addAddress($to, $toName);
                if ($cc) $mail->addCC($cc);
                $mail->isHTML(true);
                $mail->Subject = $subject;
                $mail->Body    = $htmlBody;
                $mail->AltBody = strip_tags($htmlBody);
                $mail->send();
                return true;
            } catch (Throwable $e) {
                error_log('PHPMailer failed: ' . $e->getMessage() . ' — falling back to socket SMTP');
            }
            break;
        }
    }

    // Fallback: native socket SMTP (no dependencies)
    return _sendViaNativeSocket($smtp, $to, $toName, $subject, $htmlBody, $cc);
}

function _sendViaNativeSocket(array $smtp, string $to, string $toName, string $subject, string $htmlBody, string $cc = ''): bool {
    $host = $smtp['host'];
    $port = (int)($smtp['port'] ?? 587);
    $user = $smtp['user'];
    $pass = $smtp['pass'];
    $from = $smtp['from'];
    $name = $smtp['name'] ?? '';

    $errno = $errstr = '';
    $tls = ($port == 465);
    $connHost = $tls ? "ssl://$host" : $host;

    $sock = @fsockopen($connHost, $port, $errno, $errstr, 15);
    if (!$sock) {
        error_log("SMTP socket connect failed: $errstr ($errno)");
        return _fallbackMailFunction($to, $subject, $htmlBody, $from, $name);
    }

    $read = function() use ($sock) {
        $out = '';
        while (!feof($sock)) {
            $line = fgets($sock, 515);
            $out .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $out;
    };
    $send = function(string $cmd) use ($sock, $read) {
        fputs($sock, $cmd . "\r\n");
        return $read();
    };

    try {
        $read(); // greeting
        $send("EHLO " . gethostname());
        if (!$tls) {
            $send("STARTTLS");
            stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
        }
        $send("AUTH LOGIN");
        $send(base64_encode($user));
        $r = $send(base64_encode($pass));
        if (strpos($r, '235') === false) throw new Exception("Auth failed: $r");

        $send("MAIL FROM:<$from>");
        $send("RCPT TO:<$to>");
        if ($cc) $send("RCPT TO:<$cc>");
        $send("DATA");

        $boundary = md5(uniqid());
        $date     = date('r');
        $toLine   = $toName ? "\"$toName\" <$to>" : $to;
        $fromLine = $name   ? "\"$name\" <$from>" : $from;
        $ccLine   = $cc ? "Cc: $cc\r\n" : '';

        $message  = "Date: $date\r\n";
        $message .= "From: $fromLine\r\n";
        $message .= "To: $toLine\r\n";
        $message .= $ccLine;
        $message .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $message .= "MIME-Version: 1.0\r\n";
        $message .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
        $message .= "\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
        $message .= strip_tags($htmlBody) . "\r\n\r\n";
        $message .= "--$boundary\r\n";
        $message .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
        $message .= $htmlBody . "\r\n\r\n";
        $message .= "--$boundary--\r\n";
        $message .= "\r\n.\r\n";

        $r = $send($message);
        $send("QUIT");
        fclose($sock);
        return strpos($r, '250') !== false || strpos($r, '2.0.0') !== false;
    } catch (Throwable $e) {
        error_log('Native SMTP error: ' . $e->getMessage());
        @fclose($sock);
        return _fallbackMailFunction($to, $subject, $htmlBody, $from, $name);
    }
}

function _fallbackMailFunction(string $to, string $subject, string $body, string $from, string $name): bool {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . ($name ? "\"$name\" <$from>" : $from) . "\r\n";
    return @mail($to, $subject, $body, $headers);
}
