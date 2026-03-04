@php
    $services = $b['services'] ?? [];
    $vat = $b['vat'] ?? null;
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
    $frontendUrl = config('app.frontend_url', 'https://rjbeautylounge.com');
    $bookingUrl = $frontendUrl . '/en/booking';

    // Check if this is a multi-date booking
    $serviceDates = collect($services)->pluck('date')->filter()->unique()->values();
    $isMultiDate = $serviceDates->count() > 1;
@endphp

BOOKING CANCELLED
Booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }}

Hi {{ $b['customerName'] ?? 'there' }},

We wanted to let you know that your booking has been cancelled.

@if($isMultiDate)
Appointments on {{ $serviceDates->count() }} different dates
@else
Date: {{ $b['date'] ?? '' }}
Time: {{ $b['startTime'] ?? '' }}–{{ $b['endTime'] ?? '' }}
@endif

@if(isset($b['cancelledBy']['name']))
Cancelled by: {{ $b['cancelledBy']['name'] }}
@endif
@if($b['cancelReason'] ?? null)
Reason: {{ $b['cancelReason'] }}
@endif

CANCELLED SERVICES:
@foreach($services as $s)
- {{ $s['name'] ?? 'Service' }} @if(!empty($s['date']))({{ $s['date'] }}) @endif({{ $s['startTime'] ?? '' }}–{{ $s['endTime'] ?? '' }}, {{ $s['duration'] ?? '' }} min)
  Base: {{ $fmt($s['pricing']['basePrice'] ?? 0) }}
  VAT: @if(!empty($s['pricing']['vatEnabled'])) {{ $fmt($s['pricing']['vatAmount'] ?? 0) }} @else not applied @endif
  Line total: {{ $fmt($s['pricing']['finalPrice'] ?? ($s['price'] ?? 0)) }}
@endforeach

Subtotal: {{ $fmt($vat['finalTotalFromLines'] ?? 0) }}
Base total: {{ $fmt($vat['baseTotal'] ?? 0) }}
VAT total: {{ $fmt($vat['vatTotal'] ?? 0) }}
Total: {{ $fmt($b['totalPrice'] ?? 0) }}

---

WE WOULD LOVE TO SEE YOU AGAIN!
Book a new appointment at a time that works better for you:
{{ $bookingUrl }}

Questions? Contact us:
Email: info@rjbeautylounge.com
Phone: +971 50 903 9020

Thank you for choosing Romeo & Juliet Beauty Lounge
