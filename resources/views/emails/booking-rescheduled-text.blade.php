@php
    $services = $b['services'] ?? [];
    $serviceDates = collect($services)->pluck('date')->filter()->unique()->values();
    $isMultiDate = $serviceDates->count() > 1;
@endphp
BOOKING RESCHEDULED

Booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }}

Hi {{ $b['customerName'] ?? 'there' }},

Your booking has been rescheduled.

@if($b['previousDate'] || $b['previousStartTime'])
PREVIOUS SCHEDULE:
Date: {{ $b['previousDate'] ?? 'N/A' }}
Time: {{ $b['previousStartTime'] ?? '' }} - {{ $b['previousEndTime'] ?? '' }}
@endif

NEW SCHEDULE:
@if($isMultiDate)
{{ $serviceDates->count() }} appointments on different dates (see services below)
@else
Date: {{ $b['date'] ?? 'N/A' }}
Time: {{ $b['startTime'] ?? '' }} - {{ $b['endTime'] ?? '' }}
@endif

SERVICES:
@foreach($services as $s)
- {{ $s['name'] ?? 'Service' }} @if(!empty($s['date']))({{ $s['date'] }}) @endif({{ $s['startTime'] ?? '' }} - {{ $s['endTime'] ?? '' }})
@endforeach

Total: {{ number_format((float)($b['totalPrice'] ?? 0), 2) }} AED

If you need to make further changes, please contact us and mention booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }}.

Thank you!
