<?php

require('RadiusCore.php');
require('SystemCore.php');

$radius = new RadiusCore();
$radius->load_dictionary('dictionary.mikrotik');
$radius->load_dictionary('dictionary.wispr');

$radius->on("access-request", function($attrbutes) {
    return [3,[
        'Mikrotik-Rate-Limit'   => '5m/5m',
        'Session-Timeout'       =>  3600,
        'Reply-Message'         =>  'Nice Two'
    ]];
});

$radius->on("accounting-start", function($attrbutes) {

    return [5,[
        'Reply-Message'         =>  'Accounting Start Recieved'
    ]];
});

$radius->on("accounting-interim", function($attrbutes) {

    echo "Interim Update\r\n";

    return [3,[
        'Reply-Message'         =>  'Accounting Interim Recieved in Server'
    ]];
});

$radius->on("accounting-stop", function($attrbutes) {

    return [5,[
        'Reply-Message'         =>  'Accounting Stop Recieved'
    ]];
});

$radius->run();