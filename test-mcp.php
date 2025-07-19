#!/usr/bin/env php
<?php declare(strict_types=1);

// Konfiguration
$token = $argv[1] ?? null;
$url = $argv[2] ?? 'https://example.com/missionbaymcp.json'; // Anpassen!

if (!$token) {
	echo "Usage:\n  php test-token.php <token> [url]\n";
	exit(1);
}

$ch = curl_init($url);

curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
		"Authorization: Bearer $token",
		"Accept: application/json"
	]
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
	echo "cURL error: $error\n";
	exit(1);
}

echo "HTTP $httpcode\n";
echo str_repeat("=", 20) . "\n";
echo $response . "\n";

if ($httpcode === 200) {
	echo "\n✅ Token accepted.\n";
} elseif ($httpcode === 401) {
	echo "\n❌ Unauthorized – token rejected.\n";
} else {
	echo "\n⚠️ Unexpected response.\n";
}

