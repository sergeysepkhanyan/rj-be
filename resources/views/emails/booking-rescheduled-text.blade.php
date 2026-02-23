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
Date: {{ $b['date'] ?? 'N/A' }}
Time: {{ $b['startTime'] ?? '' }} - {{ $b['endTime'] ?? '' }}

SERVICES:
@foreach($b['services'] ?? [] as $s)
- {{ $s['name'] ?? 'Service' }} ({{ $s['startTime'] ?? '' }} - {{ $s['endTime'] ?? '' }})
@endforeach

Total: {{ number_format((float)($b['totalPrice'] ?? 0), 2) }} AED

If you need to make further changes, please contact us and mention booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }}.

Thank you!
