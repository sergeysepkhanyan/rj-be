@php
    $reference = $b['reference'] ?? ('#' . ($b['id'] ?? ''));
    $name = $b['customerName'] ?? 'there';
    $reviewUrl = config('mail.review_url');
@endphp
{{ __('mail.booking_post_service.subject', ['reference' => $reference]) }}

{{ __('mail.booking_post_service.greeting', ['name' => $name]) }}

{{ __('mail.booking_post_service.lead') }}

{{ __('mail.booking_post_service.booking_ref', ['reference' => $reference]) }}

@if($reviewUrl)
{{ __('mail.review.five_stars_text') }}

Your feedback means a lot to us — please leave a review: {{ $reviewUrl }}
@endif

{{ __('mail.booking_post_service.footer_help', ['reference' => $reference]) }}
