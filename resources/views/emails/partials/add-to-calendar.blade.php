@php
    $addToCalendarUrl = $addToCalendarUrl ?? null;
@endphp
@if(!empty($addToCalendarUrl))
<div style="margin-top:18px; padding:14px 16px; background:#e8f5e9; border-radius:10px; border:1px solid #c8e6c9;">
    <div style="font-size:14px; line-height:1.6; color:#333;">
        Add this appointment to your calendar so you don’t miss it:
        <a href="{{ $addToCalendarUrl }}" style="color:#2e7d32; font-weight:600; text-decoration:none;">📅 Add to Calendar</a>
    </div>
</div>
@endif
