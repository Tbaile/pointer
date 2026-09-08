<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support Server
    |--------------------------------------------------------------------------
    |
    | Pointer never talks to a target machine directly. It opens an SSH
    | connection to the support server, which owns the trust relationship with
    | every target and brokers the second hop through its own tooling. The
    | identity configured here therefore only ever grants access to the support
    | server, never to a customer machine.
    |
    | The port and the host key policy are not configured here: they come from
    | the ssh client's own configuration for this host. A relative identity
    | file is resolved against the storage path.
    |
    */

    'support' => [

        'host' => env('POINTER_SUPPORT_HOST'),

        'user' => env('POINTER_SUPPORT_USER', 'pointer'),

        'identity_file' => env('POINTER_SUPPORT_IDENTITY_FILE', 'app/private/pointer'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Execution Limits
    |--------------------------------------------------------------------------
    |
    | Every remote command is bounded. Output is captured up to a byte cap and
    | flagged as truncated beyond it, so a runaway log read cannot exhaust
    | memory or flood a model's context window.
    |
    */

    'execution' => [

        'timeout' => (int) env('POINTER_COMMAND_TIMEOUT', 30),

        'max_output_bytes' => (int) env('POINTER_MAX_OUTPUT_BYTES', 65536),

    ],

];
