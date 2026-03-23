<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Post-service follow-up email
    |--------------------------------------------------------------------------
    |
    | After all booked services have ended, wait this many minutes before sending
    | the thank-you / review email (scheduled command: bookings:send-post-service-followup).
    |
    */

    'post_service_followup_delay_minutes' => (int) env('BOOKING_POST_SERVICE_FOLLOWUP_DELAY_MINUTES', 5),

];
