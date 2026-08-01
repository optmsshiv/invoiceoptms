<?php
// ================================================================
//  OPTMS Invoice Manager — includes/mailer.php
//  Thin PHPMailer wrapper, SMTP creds pulled from .env (same
//  loadEnv()/env() mechanism config/db.php already uses).
//
//  REQUIRES: composer require phpmailer/phpmailer
//  (run this once inside the invoiceoptms/ app root on the server)
//
//  .env vars expected:
//    SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
//    SMTP_FROM_EMAIL, SMTP_FROM_NAME, SMTP_SECURE (tls|ssl)
// ================================================================

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

// ── Low-level sender: any HTML email via the shared SMTP account ──
function sendAppEmail(string $toEmail, string $toName, string $subject, string $htmlBody, string $textBody = ''): bool {
    // Loaded here (not at file-top) so that any page which merely
    // *includes* this file — without actually sending an email — still
    // works even if `composer require phpmailer/phpmailer` hasn't been
    // run yet. Missing autoload becomes a logged, non-fatal failure.
    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!file_exists($autoload)) {
        error_log('sendAppEmail failed: vendor/autoload.php not found — run `composer require phpmailer/phpmailer` in the app root.');
        return false;
    }
    require_once $autoload;

    if (!class_exists(PHPMailer::class)) {
        error_log('sendAppEmail failed: PHPMailer class not found — check composer install.');
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER');
        $mail->Password   = env('SMTP_PASS');
        $mail->SMTPSecure = env('SMTP_SECURE', 'tls');
        $mail->Port       = (int) env('SMTP_PORT', '587');

        $mail->setFrom(env('SMTP_FROM_EMAIL'), env('SMTP_FROM_NAME', APP_NAME));
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = $textBody !== '' ? $textBody : strip_tags($htmlBody);

        $mail->send();
        return true;
    } catch (PHPMailerException $e) {
        error_log('sendAppEmail failed: ' . $mail->ErrorInfo);
        return false;
    }
}

// ── Password reset email ───────────────────────────────────────────
function sendPasswordResetEmail(string $toEmail, string $toName, string $resetLink, string $companyName, int $ttlMinutes = 60): bool {
    $safeLink = htmlspecialchars($resetLink);
    $safeName = htmlspecialchars($toName);
    $safeCo   = htmlspecialchars($companyName);

    $html = <<<HTML
<div style="font-family:'Public Sans',Arial,sans-serif;max-width:480px;margin:0 auto;padding:32px 28px;color:#111827">
  <div style="font-size:18px;font-weight:700;color:#085041;margin-bottom:18px">{$safeCo}</div>
  <p style="font-size:14px;line-height:1.6">Hi {$safeName},</p>
  <p style="font-size:14px;line-height:1.6">
    We received a request to reset your password. Click the button below to choose a new one.
    This link expires in {$ttlMinutes} minutes and can only be used once.
  </p>
  <p style="margin:26px 0">
    <a href="{$safeLink}"
       style="background:#085041;color:#E1F5EE;text-decoration:none;padding:12px 22px;
              border-radius:9px;font-size:14px;font-weight:700;display:inline-block">
      Reset Password
    </a>
  </p>
  <p style="font-size:12.5px;color:#6B7280;line-height:1.6">
    If you didn't request this, you can safely ignore this email — your password won't be changed.
  </p>
  <p style="font-size:12px;color:#9CA3AF;line-height:1.6;margin-top:22px">
    If the button doesn't work, copy and paste this link into your browser:<br>
    <span style="word-break:break-all">{$safeLink}</span>
  </p>
</div>
HTML;

    $text = "Hi {$toName},\n\n"
          . "We received a request to reset your password for {$companyName}.\n"
          . "This link expires in {$ttlMinutes} minutes and can only be used once:\n\n"
          . "{$resetLink}\n\n"
          . "If you didn't request this, you can ignore this email.\n";

    return sendAppEmail($toEmail, $toName, "Reset your {$companyName} password", $html, $text);
}
