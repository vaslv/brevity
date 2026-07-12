<?php

return [
    /*
     * Visit sources that must never reach analytics: office IPs, uptime
     * monitoring, test rigs. Comma-separated exact IPs and/or CIDR ranges
     * (IPv4 and IPv6). A matching visit still redirects normally but records
     * no click — and therefore sends no callback.
     */
    'ignored_sources' => env('TRACKING_IGNORED_SOURCES', ''),

    /*
     * Name of a query parameter that disables tracking for a single visit
     * (test links). Empty = feature off. The parameter is also stripped from
     * forward_query so it never leaks into the target URL.
     */
    'disable_param' => env('TRACKING_DISABLE_PARAM', ''),
];
