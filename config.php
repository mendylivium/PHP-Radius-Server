<?php

/**
 * PHP-Radius-Server Configuration
 * 
 * All settings in one place (like config.toml in radgo/radrust/radsee).
 * Edit this file to configure the server.
 */

return [

    // Log level: 'off', 'error', 'warn', 'info', 'debug'
    'log' => 'info',

    'radius' => [
        'ip'              => '0.0.0.0',
        'secret'          => 'mysecret',
        'auth_port'       => 1812,
        'acct_port'       => 1813,
        'coa_http_port'   => 3799,
        'coa_api_key'     => 'my-coa-secret-key',
        'dictionary_dir'  => 'dictionary',
    ],

    'api' => [
        'url'       => 'https://example.com/api/auth',
        'acct_url'  => 'https://example.com/api/acct',
        'timeout'   => 3,
    ],

    // Dictionaries to load (filenames inside dictionary_dir)
    'dictionaries' => [
        'dictionary.mikrotik',
        'dictionary.wispr',
    ],

    // Worker pool sizes (like thread pool in radsee / goroutines in radgo)
    // Uses pcntl_fork() for per-packet concurrency
    'workers' => [
        'auth_workers' => 50,   // max concurrent auth request handlers
        'acct_workers' => 20,   // max concurrent acct request handlers
    ],

];
