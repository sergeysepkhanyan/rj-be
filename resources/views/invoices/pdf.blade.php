<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $reference }}</title>
    <style>
        :root {
            --brand-primary: #4C3715;
            --brand-secondary: #EFE6D8;
            --brand-gold: #D4AF37;
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
            color: #333;
            background-color: #fff;
        }
        .container {
            padding: 40px;
        }
        .header {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 3px solid var(--brand-primary);
        }
        .header-content {
            display: table;
            width: 100%;
        }
        .logo-section {
            display: table-cell;
            width: 50%;
            vertical-align: top;
        }
        .logo-section img {
            max-height: 60px;
            max-width: 200px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: var(--brand-primary);
            margin-bottom: 5px;
        }
        .company-tagline {
            font-size: 11px;
            color: #666;
            font-style: italic;
        }
        .invoice-title-section {
            display: table-cell;
            width: 50%;
            text-align: right;
            vertical-align: top;
        }
        .invoice-title {
            font-size: 28px;
            font-weight: bold;
            color: var(--brand-primary);
            margin: 0 0 10px 0;
        }
        .invoice-ref {
            font-size: 14px;
            color: #666;
        }
        .info-section {
            display: table;
            width: 100%;
            margin-bottom: 30px;
        }
        .company-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 20px;
        }
        .customer-info {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            background-color: var(--brand-secondary);
            padding: 15px;
            border-radius: 8px;
        }
        .info-heading {
            font-size: 11px;
            text-transform: uppercase;
            color: #888;
            margin-bottom: 10px;
            letter-spacing: 1px;
        }
        .info-row {
            margin-bottom: 5px;
            font-size: 12px;
        }
        .info-label {
            color: #666;
        }
        .info-value {
            color: var(--brand-primary);
            font-weight: 500;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th {
            background-color: var(--brand-primary);
            color: #fff;
            padding: 12px 10px;
            text-align: left;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .items-table th:last-child {
            text-align: right;
        }
        .items-table td {
            border-bottom: 1px solid #eee;
            padding: 12px 10px;
            text-align: left;
        }
        .items-table td:last-child {
            text-align: right;
        }
        .items-table tr:nth-child(even) {
            background-color: #fafafa;
        }
        .totals {
            margin-top: 20px;
            margin-left: auto;
            width: 300px;
        }
        .totals table {
            width: 100%;
        }
        .totals td {
            padding: 8px 10px;
        }
        .totals td:first-child {
            text-align: left;
            color: #666;
        }
        .totals td:last-child {
            text-align: right;
            font-weight: 500;
        }
        .total-row {
            background-color: var(--brand-primary);
            color: #fff;
        }
        .total-row td {
            font-weight: bold;
            font-size: 14px;
            padding: 12px 10px;
        }
        .footer {
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            text-align: center;
            color: #888;
            font-size: 10px;
        }
        .footer-brand {
            color: var(--brand-primary);
            font-weight: bold;
            font-size: 12px;
            margin-bottom: 5px;
        }
        .thank-you {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background-color: var(--brand-secondary);
            border-radius: 8px;
        }
        .thank-you-text {
            font-size: 14px;
            color: var(--brand-primary);
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="logo-section">
                    @if(file_exists(public_path('images/logo.png')))
                    <img src="{{ public_path('images/logo.png') }}" alt="{{ config('app.company.name', 'R&J Beauty Lounge') }}">
                    @else
                    <div class="company-name">{{ config('app.company.name', 'R&J Beauty Lounge') }}</div>
                    @endif
                    <div class="company-tagline">{{ config('app.company.tagline', 'Where Beauty Meets Elegance') }}</div>
                </div>
                <div class="invoice-title-section">
                    <h1 class="invoice-title">INVOICE</h1>
                    <div class="invoice-ref">#{{ $reference }}</div>
                </div>
            </div>
        </div>

        <div class="info-section">
            <div class="company-info">
                <div class="info-heading">From</div>
                <div class="info-row">
                    <span class="info-value">{{ config('app.company.name', 'R&J Beauty Lounge') }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ config('app.company.address', 'Dubai, United Arab Emirates') }}</span>
                </div>
                @if(config('app.company.phone'))
                <div class="info-row">
                    <span class="info-label">Tel: {{ config('app.company.phone') }}</span>
                </div>
                @endif
                @if(config('app.company.email'))
                <div class="info-row">
                    <span class="info-label">{{ config('app.company.email') }}</span>
                </div>
                @endif
            </div>
            <div class="customer-info">
                <div class="info-heading">Bill To</div>
                <div class="info-row">
                    <span class="info-value">{{ $customerName ?? 'N/A' }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">{{ $customerEmail ?? 'N/A' }}</span>
                </div>
                <div class="info-row" style="margin-top: 15px;">
                    <span class="info-label">Date: </span>
                    <span class="info-value">{{ $order->created_at->format('d M Y') }}</span>
                </div>
                @if($paymentMethod)
                <div class="info-row">
                    <span class="info-label">Payment: </span>
                    <span class="info-value">{{ $paymentMethod }}</span>
                </div>
                @endif
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($items as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ number_format($item['unitPrice'], 2) }} {{ $order->currency ?? 'AED' }}</td>
                    <td>{{ number_format($item['subtotal'], 2) }} {{ $order->currency ?? 'AED' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td>{{ number_format($subtotal, 2) }} {{ $order->currency ?? 'AED' }}</td>
                </tr>
                @if($tax > 0)
                <tr>
                    <td>VAT (5%):</td>
                    <td>{{ number_format($tax, 2) }} {{ $order->currency ?? 'AED' }}</td>
                </tr>
                @endif
                @if(isset($discount) && $discount > 0)
                <tr style="color: #2D5F3F;">
                    <td>Discount{{ isset($discountLabel) && $discountLabel ? ' ('.$discountLabel.')' : '' }}:</td>
                    <td>-{{ number_format($discount, 2) }} {{ $order->currency ?? 'AED' }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Total:</td>
                    <td>{{ number_format($total, 2) }} {{ $order->currency ?? 'AED' }}</td>
                </tr>
            </table>
        </div>

        <div class="thank-you">
            <div class="thank-you-text">Thank you for your business!</div>
        </div>

        <div class="footer">
            <div class="footer-brand">{{ config('app.company.name', 'R&J Beauty Lounge') }}</div>
            @if(config('app.company.website'))
            <div>{{ config('app.company.website') }}</div>
            @endif
            <div style="margin-top: 5px;">{{ config('app.company.address', 'Dubai, United Arab Emirates') }}</div>
        </div>
    </div>
</body>
</html>
