<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    /*
    | Google Sign-In (ID tokens from credential / One Tap / Sign-In button)
    | OAuth 2.0 Client ID(s) from Google Cloud Console. Comma-separated if you use
    | Web + iOS/Android client IDs that each produce their own id_token audience.
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google Places API (reviews on website)
    |--------------------------------------------------------------------------
    |
    | Used to fetch real Google Reviews for display on the frontend.
    | Enable "Places API (New)" in Google Cloud Console and create an API key.
    | Find your Place ID at: https://developers.google.com/maps/documentation/places/web-service/place-id
    |
    */
    'google_places' => [
        'api_key'       => env('GOOGLE_PLACES_API_KEY'),
        'place_id'      => env('GOOGLE_PLACE_ID'),
        'cache_minutes' => (int) env('GOOGLE_PLACES_CACHE_MINUTES', 360),
    ],

];
