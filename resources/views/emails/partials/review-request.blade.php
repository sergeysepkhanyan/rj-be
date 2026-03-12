@php
    $reviewUrl = config('mail.review_url');
@endphp
@if($reviewUrl)
<div style="margin-top:18px; padding:14px 16px; background:#f0f7ff; border-radius:10px; border:1px solid #cce0ff;">
    <div style="font-size:14px; line-height:1.6; color:#333;">
        Your feedback means a lot to us — please take a moment to <a href="{{ $reviewUrl }}" style="color:#1a73e8; font-weight:600; text-decoration:none;">leave a review here</a>.
    </div>
</div>
@endif
