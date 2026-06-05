<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Salt
    |--------------------------------------------------------------------------
    |
    | Dedicated secret (HASHIDS_SALT), independent of APP_KEY. It must NOT fall
    | back to app.key: doing so coupled short-code stability to the encryption
    | key (rotating APP_KEY would change every id<->code mapping) and let anyone
    | who knows APP_KEY decode codes back to sequential link ids. Set
    | HASHIDS_SALT to the value the existing codes were generated with.
    |
    */
    'salt' => env('HASHIDS_SALT'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Length
    |--------------------------------------------------------------------------
    */
    'length' => 5,

    /*
    |--------------------------------------------------------------------------
    | Alphabet
    |--------------------------------------------------------------------------
    |
    | Letters only (A–Z, a–z)
    |
    */
    'alphabet' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz',
];
