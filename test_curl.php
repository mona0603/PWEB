<?php
$ch = curl_init("https://www.google.com");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);

if ($response) {
    echo "✅ cURL funciona correctamente.";
} else {
    echo "❌ cURL no funciona: " . curl_error($ch);
}
curl_close($ch);
