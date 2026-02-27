@php
    $services = $b['services'] ?? [];
    $vat = $b['vat'] ?? null;

    $discountType = $b['discountType'] ?? 'none';
    $discountValue = $b['discountValue'] ?? null;
    $discountLabel = $b['discountLabel'] ?? null;
    $hasDiscount = $discountType && $discountType !== 'none' && $discountValue !== null;

    $baseTotal = $vat['baseTotal'] ?? null;
    $vatTotal = $vat['vatTotal'] ?? null;
    $linesTotal = $vat['finalTotalFromLines'] ?? null;
    $finalTotal = $b['totalPrice'] ?? null;

    $fmt = fn($n) => is_numeric($n) ? number_format((float)$n, 2, '.', '') : $n;

    $cancelledByName = null;
    if (isset($b['cancelledBy']) && is_array($b['cancelledBy'])) {
        $cancelledByName = $b['cancelledBy']['name'] ?? null;
    }

    $cancelReason = $b['cancelReason'] ?? null;
    $frontendUrl = config('app.frontend_url', 'https://rjbeautylounge.com');
    $bookingUrl = $frontendUrl . '/en/booking';
@endphp

    <!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Booking Cancelled</title>
</head>
<body style="margin:0; padding:0; background:#f6f7fb; font-family: Arial, sans-serif; color:#111;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f6f7fb; padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="640" cellpadding="0" cellspacing="0" style="background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 8px 30px rgba(0,0,0,0.06);">
                <tr>
                    <td style="padding:22px 24px; background:#4C3715; color:#fff;">
                        <div style="font-size:18px; font-weight:700;">Booking Update</div>
                        <div style="font-size:13px; opacity:0.92; margin-top:6px;">
                            Booking {{ $b['reference'] ?? ('#' . ($b['id'] ?? '')) }} • {{ $b['date'] ?? '' }} • {{ $b['startTime'] ?? '' }}–{{ $b['endTime'] ?? '' }}
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:22px 24px;">
                        <div style="font-size:16px; font-weight:700; margin-bottom:8px;">
                            Hi {{ $b['customerName'] ?? 'there' }},
                        </div>

                        <div style="font-size:14px; line-height:1.6; color:#333;">
                            Your booking has been cancelled. We understand plans change, and we're here whenever you're ready to reschedule.
                            @if($cancelledByName)
                                <br><span style="color:#555; font-size:12px;">Cancelled by: {{ $cancelledByName }}</span>
                            @endif
                        </div>

                        @if($cancelReason)
                            <div style="margin-top:12px; padding:10px 12px; background:#f6f7fb; border-radius:10px; font-size:13px; color:#333;">
                                <strong>Reason:</strong> {{ $cancelReason }}
                            </div>
                        @endif

                        <div style="height:16px;"></div>

                        <div style="font-size:15px; font-weight:700; margin-bottom:10px;">Cancelled Services</div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate; border-spacing:0 10px;">
                            @foreach($services as $s)
                                @php
                                    $p = $s['pricing'] ?? [];
                                    $name = $s['name'] ?? 'Service';
                                    $time = ($s['startTime'] ?? '') . '–' . ($s['endTime'] ?? '');
                                    $duration = $s['duration'] ?? null;

                                    $basePrice = $p['basePrice'] ?? null;
                                    $vatEnabled = (bool)($p['vatEnabled'] ?? false);
                                    $vatAmount = $p['vatAmount'] ?? 0;
                                    $finalPriceLine = $p['finalPrice'] ?? ($s['price'] ?? null);
                                @endphp
                                <tr>
                                    <td style="background:#f6f7fb; border-radius:12px; padding:14px 14px;">
                                        <div style="display:flex; justify-content:space-between; gap:12px;">
                                            <div style="flex:1;">
                                                <div style="font-size:14px; font-weight:700; color:#111;">{{ $name }}</div>
                                                <div style="font-size:12px; color:#555; margin-top:4px;">
                                                    {{ $time }}
                                                    @if($duration) • {{ $duration }} min @endif
                                                </div>

                                                <div style="font-size:12px; color:#555; margin-top:8px;">
                                                    Base: {{ $fmt($basePrice) }}
                                                    @if($vatEnabled)
                                                        • VAT: {{ $fmt($vatAmount) }}
                                                    @else
                                                        • VAT: not applied
                                                    @endif
                                                </div>
                                            </div>

                                            <div style="text-align:right; min-width:120px;">
                                                <div style="font-size:12px; color:#666;">Line total</div>
                                                <div style="font-size:16px; font-weight:800; color:#111;">{{ $fmt($finalPriceLine) }}</div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </table>

                        <div style="border-top:1px solid #eee; margin:18px 0;"></div>

                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="font-size:13px; color:#555;">Subtotal (services)</td>
                                <td align="right" style="font-size:13px; color:#111; font-weight:700;">{{ $fmt($linesTotal) }}</td>
                            </tr>

                            @if($vat)
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-top:6px;">Base total</td>
                                    <td align="right" style="font-size:13px; color:#111;">{{ $fmt($baseTotal) }}</td>
                                </tr>
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-top:6px;">VAT total</td>
                                    <td align="right" style="font-size:13px; color:#111;">{{ $fmt($vatTotal) }}</td>
                                </tr>
                            @endif

                            @if($hasDiscount)
                                <tr>
                                    <td style="font-size:13px; color:#555; padding-top:10px;">
                                        Discount
                                        @if($discountLabel) ({{ $discountLabel }}) @endif
                                    </td>
                                    <td align="right" style="font-size:13px; color:#111; font-weight:700; padding-top:10px;">
                                        applied
                                    </td>
                                </tr>
                            @endif

                            <tr>
                                <td style="font-size:14px; color:#111; font-weight:800; padding-top:14px;">Total</td>
                                <td align="right" style="font-size:18px; color:#111; font-weight:900; padding-top:14px;">
                                    {{ $fmt($finalTotal) }}
                                </td>
                            </tr>
                        </table>

                        <div style="height:24px;"></div>

                        <!-- Rebook Section - Friendly and welcoming -->
                        <div style="background:linear-gradient(135deg, #EFE6D8 0%, #F5EEE5 100%); border-radius:16px; padding:28px 24px; text-align:center; border:1px solid #D4C5B0;">
                            <div style="font-size:24px; margin-bottom:8px;">💆‍♀️</div>
                            <div style="font-size:17px; font-weight:700; color:#4C3715; margin-bottom:10px;">
                                We'd Love to See You Again!
                            </div>
                            <div style="font-size:13px; color:#666; margin-bottom:20px; line-height:1.5;">
                                Life happens, and we completely understand. When you're ready,<br>
                                we'll be here to pamper you.
                            </div>
                            <a href="{{ $bookingUrl }}" style="display:inline-block; background:#4C3715; color:#fff; text-decoration:none; padding:14px 36px; border-radius:10px; font-size:15px; font-weight:600; box-shadow:0 4px 12px rgba(76,55,21,0.2);">
                                ✨ Book New Appointment
                            </a>
                        </div>

                        <div style="height:16px;"></div>

                        <div style="font-size:12px; color:#666; line-height:1.6; text-align:center;">
                            Questions? Contact us at <a href="mailto:info@rjbeautylounge.com" style="color:#4C3715;">info@rjbeautylounge.com</a>
                            or call <a href="tel:+971509039020" style="color:#4C3715;">+971 50 903 9020</a>
                        </div>
                    </td>
                </tr>

                <tr>
                    <td style="padding:18px 24px; background:#f6f7fb; font-size:12px; color:#666; text-align:center;">
                        Thank you for choosing Romeo & Juliet Beauty Lounge<br>
                        <span style="color:#999;">This is an automated email.</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
