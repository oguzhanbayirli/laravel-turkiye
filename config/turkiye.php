<?php

return [

    'tcmb' => [

        // Where the daily bulletins live. today.xml and YYYYMM/DDMMYYYY.xml
        // are resolved against this.
        'base_url' => env('TCMB_BASE_URL', 'https://www.tcmb.gov.tr/kurlar'),

        // Seconds. TCMB is usually quick, but it does have slow days.
        'timeout' => (int) env('TCMB_TIMEOUT', 10),
        'connect_timeout' => (int) env('TCMB_CONNECT_TIMEOUT', 5),

        // Extra attempts after a connection error. HTTP error responses are
        // not retried.
        'retries' => (int) env('TCMB_RETRIES', 1),

        // No bulletin is published on weekends and public holidays. When a
        // date has none, earlier days are tried, up to this many. Kurban
        // Bayramı combined with a weekend can run to nine days.
        'max_lookback_days' => 10,

        'cache' => [
            // null uses the application's default cache store.
            'store' => env('TCMB_CACHE_STORE'),
            'prefix' => 'turkiye.tcmb',

            // today.xml changes once a day at around 15:30 Istanbul time.
            'today_ttl' => 600,

            // A published bulletin never changes. null keeps it forever.
            'archive_ttl' => 60 * 60 * 24 * 30,
        ],
    ],

];
