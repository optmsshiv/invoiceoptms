<?php
require_once '/home1/edrppymy/public_html/invoiceoptms/vendor/autoload.php';

echo "Autoload OK<br>";

if (class_exists('\Mpdf\Mpdf')) {
    echo "mPDF class FOUND ✅";
} else {
    echo "mPDF class NOT FOUND ❌<br>";
    
    // Show what mpdf files actually exist
    $path = '/home1/edrppymy/public_html/invoiceoptms/vendor/mpdf';
    echo "Files in vendor/mpdf/:<br>";
    foreach (scandir($path) as $f) {
        echo $f . "<br>";
    }
}
?>