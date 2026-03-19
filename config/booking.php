<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Post-service follow-up email
    |--------------------------------------------------------------------------
    |
    | After all booked services have ended, wait this many hours before sending
    | the thank-you / review email (scheduled command: bookings:send-post-service-followup).
    |
    */

    'post_service_followup_delay_hours' => (int) env('BOOKING_POST_SERVICE_FOLLOWUP_DELAY_HOURS', 1),

];
