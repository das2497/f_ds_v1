<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'printful' => [
        'api_key' => env('PRINTFUL_API_KEY'),
        'store_id' => env('PRINTFUL_STORE_ID'),
    ],

    'cj' => [
        'api_key' => env('CJ_API_KEY'),
    ],

    // Setup: create a Google Cloud service account with the Sheets API
    // enabled, download its JSON key to the credentials_path below, share
    // the target spreadsheet with that service account's email (Editor
    // access), and copy the spreadsheet's ID (the long id in its URL)
    // into spreadsheet_id. sync_token is any random string — put the same
    // value in the Sheet's Apps Script (see resources/google-apps-script/
    // sheet-sync.gs) as WEBHOOK_TOKEN.
    'google_sheets' => [
        'spreadsheet_id' => env('GOOGLE_SHEETS_SPREADSHEET_ID'),
        'credentials_path' => env('GOOGLE_SHEETS_CREDENTIALS_PATH'),
        'sync_token' => env('GOOGLE_SHEETS_SYNC_TOKEN'),
    ],

    // Setup: any random string, entered into the Chrome extension's
    // "Store settings" panel (resources/chrome-extension/aliexpress-importer).
    'aliexpress_extension' => [
        'token' => env('ALIEXPRESS_EXTENSION_TOKEN'),
    ],

    'ollama' => [
        'api_key' => env('OLLAMA_API_KEY'),
        'model' => env('OLLAMA_MODEL', 'gemma4:31b'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'ga4' => [
        'measurement_id' => env('GA4_MEASUREMENT_ID'),
        'api_secret' => env('GA4_API_SECRET'),
    ],

    'meta' => [
        'pixel_id' => env('META_PIXEL_ID'),
        'access_token' => env('META_ACCESS_TOKEN'),
    ],

    'tiktok' => [
        'pixel_code' => env('TIKTOK_PIXEL_CODE'),
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
    ],

];
