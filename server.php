<?php

/**
 * PHP-Radius-Server Entry Point
 * 
 * Features:
 *   - Centralized config from config.php
 *   - Auth / Acct forwarding to API via curl
 *   - CoA HTTP endpoint with API key validation
 *   - pcntl_fork() per-packet concurrency (like goroutines / tokio)
 */

require('RadiusPacketCode.php');
require('RadiusCore.php');
require('SystemCore.php');

// Load config
$config = require(__DIR__ . '/config.php');

$radius = new RadiusCore($config);

// Load dictionaries from config
foreach ($config['dictionaries'] as $dict) {
    $radius->load_dictionary($dict);
}

/**
 * Helper: POST JSON to API endpoint via curl
 */
function apiPost(string $url, array $data, int $timeout = 3): ?array
{
    if (empty($url)) return null;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        return null;
    }

    return json_decode($response, true);
}

/**
 * Access-Request handler
 * Forwards to API, returns response or reject on failure
 */
$radius->on("access-request", function ($attributes) use ($config) {
    $apiResult = apiPost($config['api']['url'], $attributes, $config['api']['timeout']);

    if ($apiResult && isset($apiResult['code'])) {
        $code = (int)$apiResult['code'];
        $replyAttrs = $apiResult['attributes'] ?? [];
        return [$code, $replyAttrs];
    }

    // API unreachable or error - reject
    return [RadiusPacketCode::ACCESS_REJECT, [
        'Reply-Message' => 'API Unreachable'
    ]];
});

/**
 * Accounting-Start handler
 */
$radius->on("accounting-start", function ($attributes) use ($config) {
    apiPost($config['api']['acct_url'], array_merge($attributes, ['type' => 'start']), $config['api']['timeout']);
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Start Received'
    ]];
});

/**
 * Accounting-Interim handler
 */
$radius->on("accounting-interim", function ($attributes) use ($config) {
    apiPost($config['api']['acct_url'], array_merge($attributes, ['type' => 'interim']), $config['api']['timeout']);
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Interim Received'
    ]];
});

/**
 * Accounting-Stop handler
 */
$radius->on("accounting-stop", function ($attributes) use ($config) {
    apiPost($config['api']['acct_url'], array_merge($attributes, ['type' => 'stop']), $config['api']['timeout']);
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message' => 'Accounting Stop Received'
    ]];
});

$radius->run();