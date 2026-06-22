<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Endpoint
    |--------------------------------------------------------------------------
    |
    | The LekkerFlow capture endpoint that error records are POSTed to.
    |
    */

    'url' => env('LEKKERFLOW_ERROR_WEBHOOK_URL', 'https://lekkerflow.com/api/error-webhook/capture'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Token
    |--------------------------------------------------------------------------
    |
    | Sent as the `X-Webhook-Token` header. When empty, the handler becomes a
    | no-op so the channel is safe to leave in your logging stack on
    | environments that should not report (e.g. local).
    |
    */

    'token' => env('LEKKERFLOW_ERROR_WEBHOOK_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Minimum Level
    |--------------------------------------------------------------------------
    |
    | The minimum Monolog level a record must have to be shipped. One of:
    | debug, info, notice, warning, error, critical, alert, emergency.
    |
    */

    'level' => env('LEKKERFLOW_LOG_LEVEL', 'error'),

    /*
    |--------------------------------------------------------------------------
    | Environment & Release
    |--------------------------------------------------------------------------
    |
    | Reported alongside every error. `environment` defaults to the app's
    | environment (config('app.env')) when left null. `release` is an optional
    | app version / git sha used to attribute errors to a deploy.
    |
    */

    'environment' => env('LEKKERFLOW_ENVIRONMENT'),

    'release' => env('LEKKERFLOW_RELEASE'),

    /*
    |--------------------------------------------------------------------------
    | Request Timeout
    |--------------------------------------------------------------------------
    |
    | Seconds to wait for the webhook before giving up. Failures are swallowed
    | so reporting can never mask or replace the original application error.
    |
    */

    'timeout' => (int) env('LEKKERFLOW_TIMEOUT', 5),

];
