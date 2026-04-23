<?php
while (ob_get_level() > 0) ob_end_clean();

require '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';

try {
    $mpdf = new \Mpdf\Mpdf([
        'mode'    => 'utf-8',
        'format'  => 'A4',
        'tempDir' => '/tmp',
    ]);
    $mpdf->WriteHTML('<h1>mPDF Test</h1><p>Working!</p>');
    $mpdf->Output('test.pdf', 'D');
} catch (Exception $e) {
    header('Content-Type: text/plain', true, 500);
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine();
}