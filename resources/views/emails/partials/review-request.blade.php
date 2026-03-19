@php
    $reviewUrl = config('mail.review_url');
@endphp
@if($reviewUrl)
<div style="margin-top:18px; padding:18px 16px; background:#f0f7ff; border-radius:10px; border:1px solid #cce0ff; text-align:center;">
    <div style="font-size:28px; line-height:1.3; letter-spacing:10px; color:#f5b400; margin:0 auto 14px; text-align:center; width:100%;" role="img" aria-label="{{ __('mail.review.five_stars_aria') }}">&#9733;&#9733;&#9733;&#9733;&#9733;</div>
    <div style="font-size:14px; line-height:1.6; color:#333; text-align:center; max-width:420px; margin:0 auto;">
        Your feedback means a lot to us — please take a moment to <a href="{{ $reviewUrl }}" style="color:#1a73e8; font-weight:600; text-decoration:none;">leave a review here</a>.
    </div>
</div>
@endif
