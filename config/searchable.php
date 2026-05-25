<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Strict mode
    |--------------------------------------------------------------------------
    |
    | When true (default), unknown filter / sort fields throw an exception.
    | When false, they are silently skipped — useful in lenient API surfaces
    | where the client may send fields the model doesn't know about.
    |
    */

    'strict' => env('SEARCHABLE_STRICT', true),

];
