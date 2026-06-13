<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Coldest-domain window
    |--------------------------------------------------------------------------
    |
    | The "coldest" domain-selection strategy picks the domain with the fewest
    | links created within this rolling window (in days). Widen it to smooth out
    | short bursts; narrow it to react faster to recent usage.
    |
    */

    'coldest_period_days' => (int) env('DOMAIN_COLDEST_PERIOD_DAYS', 30),

];
