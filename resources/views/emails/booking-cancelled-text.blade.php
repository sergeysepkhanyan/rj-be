@php
    $services = $b['services'] ?? [];
    $vat = $b['vat'] ?? null;
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;
@endphp

Booking cancelled ❌
Booking #{{ $b['id'] ?? '' }}

Date: {{ $b['date'] ?? '' }}
Time: {{ $b['startTime'] ?? '' }}–{{ $b['endTime'] ?? '' }}
Customer: {{ $b['customerName'] ?? '' }}

Cancelled by: @if(isset($b['cancelledBy']['name'])) {{ $b['cancelledBy']['name'] }} @else - @endif
Reason: {{ $b['cancelReason'] ?? '-' }}

Services:
@foreach($services as $s)
    - {{ $s['name'] ?? 'Service' }} ({{ $s['startTime'] ?? '' }}–{{ $s['endTime'] ?? '' }}, {{ $s['duration'] ?? '' }} min)
    Base: {{ $fmt($s['pricing']['basePrice'] ?? 0) }}
    VAT: @if(!empty($s['pricing']['vatEnabled'])) {{ $fmt($s['pricing']['vatAmount'] ?? 0) }} @else not applied @endif
    Line total: {{ $fmt($s['pricing']['finalPrice'] ?? ($s['price'] ?? 0)) }}
@endforeach

Subtotal: {{ $fmt($vat['finalTotalFromLines'] ?? 0) }}
Base total: {{ $fmt($vat['baseTotal'] ?? 0) }}
VAT total: {{ $fmt($vat['vatTotal'] ?? 0) }}
Total: {{ $fmt($b['totalPrice'] ?? 0) }}

If this was a mistake, please contact us and mention booking #{{ $b['id'] ?? '' }}.
