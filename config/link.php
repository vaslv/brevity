<?php

return [
    // Selected code generation strategy for Link.code
    // Supported: 'hashid' (default)
    'code_strategy' => env('LINK_CODE_STRATEGY', 'hashid'),

    // Deleting a link with at least this many clicks shows an explicit
    // warning in the admin confirmation modal (LNK-09).
    'delete_confirm_threshold' => env('LINK_DELETE_CONFIRM_THRESHOLD', 15),
];
