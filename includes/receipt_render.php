<?php
// ═══════════════════════════════════════════════════════
//  Payment-receipt image renderer (PNG, via GD + FreeType)
//  Pure function: takes plain data, returns PNG bytes — no DB access, easy to test.
//  Fonts: fonts/DejaVuSans.ttf + DejaVuSans-Bold.ttf (both contain the ₹ sign).
// ═══════════════════════════════════════════════════════

/** Indian digit grouping: 1234567.5 → 12,34,567.50 */
function rcpt_inr(float $n): string {
  $s = number_format(abs($n), 2, '.', '');
  [$int, $dec] = explode('.', $s);
  if (strlen($int) > 3) {
    $last3 = substr($int, -3); $rest = substr($int, 0, -3);
    $int = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $last3;
  }
  return ($n < 0 ? '-' : '') . $int . '.' . $dec;
}

/**
 * $d keys: company, company_line (phone · email), receipt_no, date_text, client, invoice_no, method,
 *          txn (opt), amount (float), settlement (float), balance (float), partial (bool),
 *          plan_title + plan_line (opt strings, installment plans), currency (default ₹), accent (hex, default #0F766E)
 * Returns PNG binary (800px wide) or null when GD/FreeType is unavailable.
 */
function renderReceiptPng(array $d): ?string {
  if (!function_exists('imagecreatetruecolor') || !function_exists('imagettftext')) return null;
  $reg = __DIR__ . '/../fonts/DejaVuSans.ttf';
  $bold = __DIR__ . '/../fonts/DejaVuSans-Bold.ttf';
  if (!is_readable($reg) || !is_readable($bold)) return null;

  $S = 2;                       // draw at 2x, then downsample → smooth edges
  $W = 800 * $S; $H = 2400 * $S;
  $im = imagecreatetruecolor($W, $H);
  imageantialias($im, true);
  $hex = function (string $h) use ($im) { $h = ltrim($h, '#'); return imagecolorallocate($im, hexdec(substr($h,0,2)), hexdec(substr($h,2,2)), hexdec(substr($h,4,2))); };
  $px = fn($v) => $v * $S;
  $pt = fn($v) => $v * $S * 0.75;                 // CSS px → GD points

  $accent = $hex($d['accent'] ?? '#0F766E');
  $white = $hex('#FFFFFF'); $ink = $hex('#111827'); $muted = $hex('#6B7280'); $line = $hex('#E5E7EB'); $bg = $hex('#F3F4F6');
  imagefilledrectangle($im, 0, 0, $W, $H, $bg);

  $tw = function (string $t, float $size, string $font) use ($pt) { $b = imagettfbbox($pt($size), 0, $font, $t); return $b[2] - $b[0]; };
  $text = function (string $t, float $x, float $y, float $size, $col, string $font) use ($im, $pt) { imagettftext($im, $pt($size), 0, (int)$x, (int)$y, $col, $font, $t); };
  $fit = function (string $t, float $size, string $font, float $maxW) use ($tw) {   // ellipsis if too wide
    if ($tw($t, $size, $font) <= $maxW) return $t;
    while (mb_strlen($t) > 1 && $tw($t . '…', $size, $font) > $maxW) $t = mb_substr($t, 0, -1);
    return rtrim($t) . '…';
  };
  $rrect = function (float $x1, float $y1, float $x2, float $y2, float $r, $col) use ($im) {
    $x1=(int)$x1; $y1=(int)$y1; $x2=(int)$x2; $y2=(int)$y2; $r=(int)$r;
    imagefilledrectangle($im, $x1+$r, $y1, $x2-$r, $y2, $col);
    imagefilledrectangle($im, $x1, $y1+$r, $x2, $y2-$r, $col);
    imagefilledellipse($im, $x1+$r, $y1+$r, $r*2, $r*2, $col); imagefilledellipse($im, $x2-$r, $y1+$r, $r*2, $r*2, $col);
    imagefilledellipse($im, $x1+$r, $y2-$r, $r*2, $r*2, $col); imagefilledellipse($im, $x2-$r, $y2-$r, $r*2, $r*2, $col);
  };

  $cur = $d['currency'] ?? '₹';
  $cx = $W / 2; $m = $px(56);                      // side margin of the white card
  $cardL = $m; $cardR = $W - $m; $pad = $px(44);

  // ── header band ───────────────────────────────────────────
  $hh = $px(190);
  imagefilledrectangle($im, 0, 0, $W, (int)$hh, $accent);
  $name = $fit((string)($d['company'] ?? ''), 40, $bold, $W - $px(120));
  $text($name, $cx - $tw($name, 40, $bold) / 2, $px(88), 40, $white, $bold);
  $sub = 'PAYMENT RECEIPT';
  $text($sub, $cx - $tw($sub, 17, $reg) / 2, $px(132), 17, $hex('#CCFBF1'), $reg);
  $rn = (string)($d['receipt_no'] ?? '');
  $text($rn, $cx - $tw($rn, 15, $reg) / 2, $px(162), 15, $hex('#99F6E4'), $reg);

  // ── white card ────────────────────────────────────────────
  $cardTop = $hh - $px(0);
  $y = $px(250);
  $cardStart = $y - $px(36);
  $rows = [];
  $rows[] = ['Received from', (string)($d['client'] ?? '')];
  $rows[] = ['Invoice no.',   (string)($d['invoice_no'] ?? '')];
  $rows[] = ['Date & time',   (string)($d['date_text'] ?? '')];
  $rows[] = ['Payment method',(string)($d['method'] ?? '')];
  if (!empty($d['txn']))                           $rows[] = ['Transaction ID', (string)$d['txn']];
  if (($d['settlement'] ?? 0) > 0.009)             $rows[] = ['Settlement discount', $cur . rcpt_inr((float)$d['settlement'])];

  // pre-compute card height so we can paint the card first
  $partial = !empty($d['partial']) || ($d['balance'] ?? 0) > 0.009;
  $planLine = trim((string)($d['plan_line'] ?? '')); $planTitle = trim((string)($d['plan_title'] ?? ''));
  $h = 36 + 62 + 40 + 130 + 30 + 34 + count($rows) * 62 + 24 + 130 + ($planLine !== '' ? 132 : 0) + 150;
  $rrect($cardL, $cardStart, $cardR, $cardStart + $px($h), $px(28), $white);

  // status pill
  $label = $partial ? 'PARTIAL PAYMENT' : '✓  PAID';
  $pillFill = $partial ? '#FFF3E0' : '#E8F5E9'; $pillInk = $partial ? '#E65100' : '#2E7D32';
  $pw = $tw($label, 22, $bold) + $px(64);
  $rrect($cx - $pw / 2, $y, $cx + $pw / 2, $y + $px(62), $px(31), $hex($pillFill));
  $text($label, $cx - $tw($label, 22, $bold) / 2, $y + $px(41), 22, $hex($pillInk), $bold);
  $y += $px(62 + 40);

  // amount
  $amt = $cur . rcpt_inr((float)($d['amount'] ?? 0));
  $size = 78; while ($tw($amt, $size, $bold) > ($cardR - $cardL - $pad * 2) && $size > 36) $size -= 4;
  $text($amt, $cx - $tw($amt, $size, $bold) / 2, $y + $px(78), $size, $ink, $bold);
  $cap = 'Amount received';
  $text($cap, $cx - $tw($cap, 18, $reg) / 2, $y + $px(122), 18, $muted, $reg);
  $y += $px(130 + 30);
  imagefilledrectangle($im, (int)($cardL + $pad), (int)$y, (int)($cardR - $pad), (int)$y + $S, $line);
  $y += $px(34);

  // detail rows
  $lx = $cardL + $pad; $rx = $cardR - $pad;
  foreach ($rows as [$k, $v]) {
    $text($k, $lx, $y + $px(30), 20, $muted, $reg);
    $maxW = ($rx - $lx) - $tw($k, 20, $reg) - $px(40);
    $v = $fit($v, 21, $bold, $maxW);
    $text($v, $rx - $tw($v, 21, $bold), $y + $px(30), 21, $ink, $bold);
    $y += $px(62);
  }
  $y += $px(24);

  // balance box
  $bal = (float)($d['balance'] ?? 0);
  if ($bal > 0.009) { $bf = '#FFF8E1'; $bi = '#B26A00'; $bt1 = 'Balance due'; $bt2 = $cur . rcpt_inr($bal); }
  else              { $bf = '#E8F5E9'; $bi = '#2E7D32'; $bt1 = 'Invoice status'; $bt2 = '✓ Fully paid'; }
  $rrect($lx, $y, $rx, $y + $px(110), $px(18), $hex($bf));
  $text($bt1, $lx + $px(28), $y + $px(66), 20, $muted, $reg);
  $text($bt2, $rx - $px(28) - $tw($bt2, 34, $bold), $y + $px(70), 34, $hex($bi), $bold);
  $y += $px(110 + 20);

  // plan line (installment plans)
  if ($planLine !== '') {
    $rrect($lx, $y, $rx, $y + $px(112), $px(18), $hex('#E0F2F1'));
    if ($planTitle !== '') {
      $t1 = $fit($planTitle, 19, $bold, ($rx - $lx) - $px(56));
      $text($t1, $cx - $tw($t1, 19, $bold) / 2, $y + $px(44), 19, $hex('#00695C'), $bold);
    }
    $sz = 19; while ($tw($planLine, $sz, $reg) > ($rx - $lx) - $px(56) && $sz > 13) $sz--;
    $t2 = $fit($planLine, $sz, $reg, ($rx - $lx) - $px(56));
    $text($t2, $cx - $tw($t2, $sz, $reg) / 2, $y + $px(84), $sz, $hex('#00695C'), $reg);
    $y += $px(112 + 20);
  }

  // footer
  $y += $px(10);
  $ty = 'Thank you for your payment!';
  $text($ty, $cx - $tw($ty, 22, $bold) / 2, $y + $px(40), 22, $ink, $bold);
  $cl = $fit((string)($d['company_line'] ?? ''), 16, $reg, $cardR - $cardL - $pad);
  if ($cl !== '') $text($cl, $cx - $tw($cl, 16, $reg) / 2, $y + $px(76), 16, $muted, $reg);
  $note = 'Computer-generated receipt · no signature required';
  $text($note, $cx - $tw($note, 14, $reg) / 2, $y + $px(106), 14, $hex('#9CA3AF'), $reg);
  $finalH = (int)($cardStart + $px($h) + $px(48));

  $crop = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => $W, 'height' => $finalH]);
  imagedestroy($im);
  if (!$crop) return null;
  $out = imagecreatetruecolor(800, (int)round($finalH / $S));
  imagecopyresampled($out, $crop, 0, 0, 0, 0, 800, (int)round($finalH / $S), $W, $finalH);
  imagedestroy($crop);
  ob_start(); imagepng($out, null, 6); $png = ob_get_clean();
  imagedestroy($out);
  return $png ?: null;
}
