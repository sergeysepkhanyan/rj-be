@php
    $services = $b['services'] ?? [];
    $customer = $b['customer'] ?? [];
    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;

    // Check if this is a multi-date booking
    $serviceDates = collect($services)->pluck('date')->filter()->unique()->values();
    $isMultiDate = $serviceDates->count() > 1;
@endphp

<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Rescheduled</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#FF8C00; color:#fff;">
                        @include('emails.partials.logo')
                        <div style="font-size:18px; font-weight:700; margin-top:10px;">Booking Rescheduled</div>
                        <div style="font-size:13px; opacity:0.9; margin-top:6px;">
                            Booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:16px;">
                            A booking has been rescheduled.
                        </div>

                        <div style="background:#f6f7fb; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:12px;">Customer Information</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Name:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['name'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Email:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['email'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#666; padding:4px 0;">Phone:</td>
                                    <td style="font-size:13px; color:#111; padding:4px 0; text-align:right;">{{ $customer['phone'] ?? 'N/A' }}</td>
                                </tr>
                            </table>
                        </div>

                        @if($b['previousDate'] || $b['previousStartTime'])
                        <div style="background:#FFF3CD; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:8px; color:#856404;">Previous Schedule</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @if($b['previousDate'])
                                <tr>
                                    <td style="font-size:13px; color:#856404; padding:4px 0;">Date:</td>
                                    <td style="font-size:13px; color:#856404; padding:4px 0; text-align:right; text-decoration:line-through;">{{ $b['previousDate'] }}</td>
                                </tr>
                                @endif
                                @if($b['previousStartTime'])
                                <tr>
                                    <td style="font-size:13px; color:#856404; padding:4px 0;">Time:</td>
                                    <td style="font-size:13px; color:#856404; padding:4px 0; text-align:right; text-decoration:line-through;">{{ $b['previousStartTime'] }} - {{ $b['previousEndTime'] ?? '' }}</td>
                                </tr>
                                @endif
                            </table>
                        </div>
                        @endif

                        <div style="background:#D4F4DD; border-radius:12px; padding:16px; margin-bottom:16px;">
                            <div style="font-size:14px; font-weight:700; margin-bottom:8px; color:#2D5F3F;">New Schedule</div>
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                @if($isMultiDate)
                                <tr>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0;">Dates:</td>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0; text-align:right; font-weight:700;">{{ $serviceDates->count() }} different dates</td>
                                </tr>
                                @else
                                <tr>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0;">Date:</td>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0; text-align:right; font-weight:700;">{{ $b['date'] ?? 'N/A' }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0;">Time:</td>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0; text-align:right; font-weight:700;">{{ $b['startTime'] ?? '' }} - {{ $b['endTime'] ?? '' }}</td>
                                </tr>
                                @endif
                                <tr>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0;">Duration:</td>
                                    <td style="font-size:13px; color:#2D5F3F; padding:4px 0; text-align:right;">{{ $b['duration'] ?? '' }} min</td>
                                </tr>
                            </table>
                        </div>

                        @if(count($services) > 0)
                        <div style="font-size:15px; font-weight:700; margin-bottom:10px;">Services</div>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0 8px;">
                            @foreach($services as $s)
                            <tr>
                                <td style="background:#f6f7fb; border-radius:10px; padding:12px;">
                                    <div style="font-size:14px; font-weight:600; color:#111;">{{ $s['name'] ?? 'Service' }}</div>
                                    <div style="font-size:12px; color:#666; margin-top:4px;">
                                        @if(!empty($s['date']))📅 {{ $s['date'] }} • @endif{{ $s['startTime'] ?? '' }} - {{ $s['endTime'] ?? '' }}
                                        @if(isset($s['master']) && is_array($s['master']) && !empty($s['master']['name']))
                                        - with {{ $s['master']['name'] }}
                                        @endif
                                    </div>
                                </td>
                            </tr>
                            @endforeach
                        </table>
                        @endif

                        <div style="border-top:2px solid #FF8C00; margin:16px 0; padding-top:16px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td style="font-size:16px; color:#111; font-weight:800;">Total Amount</td>
                                    <td align="right" style="font-size:20px; color:#FF8C00; font-weight:900;">
                                        {{ $fmt($b['totalPrice'] ?? 0) }} AED
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666;">
                        Please update your calendar accordingly.<br>
                        <span style="color:#999;">This is an automated notification from your booking system.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
