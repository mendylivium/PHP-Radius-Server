<?php

require('RadiusPacketCode.php');
require('RadiusCore.php');
require('SystemCore.php');

$radius = new RadiusCore();
$radius->load_dictionary('dictionary.mikrotik');
$radius->load_dictionary('dictionary.wispr');

$radius->on("access-request", function($attributes) {
    return [RadiusPacketCode::ACCESS_REJECT, [
        'Mikrotik-Rate-Limit'   => '5m/5m',
        'Session-Timeout'       =>  3600,
        'Reply-Message'         =>  'Access Reject'
    ]];
});

$radius->on("accounting-start", function($attributes) {
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message'         =>  'Accounting Start Received'
    ]];
});

$radius->on("accounting-interim", function($attributes) {
    echo "Interim Update\r\n";

    return [RadiusPacketCode::ACCESS_REJECT, [
        'Reply-Message'         =>  'Accounting Interim Received in Server'
    ]];
});

$radius->on("accounting-stop", function($attributes) {
    return [RadiusPacketCode::ACCOUNTING_RESPONSE, [
        'Reply-Message'         =>  'Accounting Stop Received'
    ]];
});

$radius->run();