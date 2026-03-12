<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Contact Form Notification Email
    |--------------------------------------------------------------------------
    |
    | This email address will receive notifications when someone submits
    | the contact form on the website. If not set, falls back to MAIL_FROM_ADDRESS.
    |
    */

    'contact_notification_email' => env('CONTACT_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Email Logo (used in all mail templates)
    |--------------------------------------------------------------------------
    |
    | Full URL to the logo image shown in email headers. Defaults to public/images/rj.png.
    | Set MAIL_LOGO_URL in .env to override (e.g. for a different logo or CDN URL).
    |
    */

    'logo_url' => env('MAIL_LOGO_URL'),

    /*
    |--------------------------------------------------------------------------
    | Google Review URL (confirmation emails)
    |--------------------------------------------------------------------------
    |
    | Link shown in booking and order confirmation emails asking customers
    | to leave a review. Default is Romeo & Juliette Beauty Lounge Dubai.
    | Set MAIL_REVIEW_URL in .env to override.
    |
    */

    'review_url' => env('MAIL_REVIEW_URL', 'https://www.google.com/search?q=romeo+juliette+beauty+lounge+dubai&sca_esv=0546383d171bc4a7&rlz=1C1CHBF_enAM818AM818&sxsrf=ANbL-n6KvtoFP06rod70AvPs-XVM9EtW7Q%3A1773237469379&ei=3XSxafTvFrixwPAPpOyr4AI&biw=1366&bih=607&ved=0ahUKEwi00turgJiTAxW4GBAIHST2CiwQ4dUDCBE&uact=5&oq=romeo+juliette+beauty+lounge+dubai&gs_lp=Egxnd3Mtd2l6LXNlcnAiInJvbWVvIGp1bGlldHRlIGJlYXV0eSBsb3VuZ2UgZHViYWkyBhAAGBYYHjIGEAAYFhgeMgYQABgWGB4yBRAAGO8FMgUQABjvBTIFEAAY7wVI2E5QmhBYyExwA3gAkAEAmAHMAaABny6qAQYwLjMyLjK4AQPIAQD4AQGYAiWgAvgwwgIIEAAY7wUYsAPCAgsQABiABBiKBRiRAsICCxAuGIAEGIoFGJECwgIFEC4YgATCAgUQABiABMICCxAuGIAEGMcBGNEDwgIaEC4YgAQYigUYkQIYlwUY3AQY3gQY3wTYAQHCAgsQLhiABBjHARivAcICFBAuGIAEGJcFGNwEGN4EGOAE2AEBwgIUEC4YgAQYlwUY3AQY3gQY3wTYAQHCAgcQLhiABBgKwgIHEAAYgAQYCsICFhAuGIAEGAoYlwUY3AQY3gQY4ATYAQHCAgkQLhiABBgKGAvCAgkQLhgKGAsYgATCAgkQABiABBgKGAvCAhgQLhiABBgKGAsYlwUY3AQY3gQY4ATYAQHCAgcQLhiABBgNwgIHEAAYgAQYDcICFhAuGIAEGA0YlwUY3AQY3gQY4ATYAQHCAgYQABgeGA3CAggQABgeGA0YCsICCBAAGBYYHhgKwgILEAAYgAQYigUYhgPCAggQABiABBiiBJgDAIgGAZAGBboGBggBEAEYFJIHBjMuMjkuNaAH1rYDsgcGMC4yOS41uAfcMMIHBzItMTguMTnIB8ICgAgB&sclient=gws-wiz-serp#lrd=0x3e5f5d54eb422159:0x85f4a0cffadf39bc,3'),

    /*
    |--------------------------------------------------------------------------
    | Calendar event location (Add to Calendar .ics)
    |--------------------------------------------------------------------------
    | Optional address/location shown in the calendar event when customers
    | add their booking to Google/Apple/Outlook. Set MAIL_CALENDAR_LOCATION in .env.
    |
    */

    'calendar_location' => env('MAIL_CALENDAR_LOCATION', 'Romeo & Juliette Beauty Lounge, Dubai'),

];
