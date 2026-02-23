<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zoho OAuth2 Credentials
    |--------------------------------------------------------------------------
    | Register your app at: https://api-console.zoho.com/
    | After registering, generate a refresh token via Self Client or Auth Code flow.
    */
    'client_id'     => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Zoho Region Accounts URL
    |--------------------------------------------------------------------------
    | Use the URL matching your Zoho account region:
    |   - US/Global : https://accounts.zoho.com
    |   - EU        : https://accounts.zoho.eu
    |   - IN        : https://accounts.zoho.in
    |   - AU        : https://accounts.zoho.com.au
    |   - JP        : https://accounts.zoho.jp
    */
    'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.com'),

    /*
    |--------------------------------------------------------------------------
    | Zoho API Base URL
    |--------------------------------------------------------------------------
    | Use the URL matching your Zoho account region:
    |   - US/Global : https://www.zohoapis.com
    |   - EU        : https://www.zohoapis.eu
    |   - IN        : https://www.zohoapis.in
    |   - AU        : https://www.zohoapis.com.au
    |   - JP        : https://www.zohoapis.jp
    */
    'api_base_url' => env('ZOHO_API_BASE_URL', 'https://www.zohoapis.com'),

    /*
    |--------------------------------------------------------------------------
    | Zoho Books Organization ID
    |--------------------------------------------------------------------------
    | Required for all Zoho Books API calls.
    | Find it in Zoho Books → Settings → Organization Profile.
    */
    'books_organization_id' => env('ZOHO_BOOKS_ORGANIZATION_ID'),
];
