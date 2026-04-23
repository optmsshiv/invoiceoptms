<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

while (ob_get_level() > 0) ob_end_clean();

require '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'    => 'utf-8',
        'format'  => 'A4',
        'tempDir' => '/tmp',
    ]);
    $mpdf->WriteHTML('<h1>Test</h1>');
    $mpdf->Output('test.pdf', 'D');
} catch (\Throwable $e) {
    // Show ALL errors including fatal ones
    header('Content-Type: text/plain', true, 500);
    echo get_class($e) . ": " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo $e->getTraceAsString();
    exit;
}