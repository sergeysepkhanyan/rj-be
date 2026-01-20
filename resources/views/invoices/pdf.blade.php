<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice #{{ $reference }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 40px;
        }
        .header {
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .invoice-info {
            margin-bottom: 20px;
        }
        .invoice-info table {
            width: 100%;
        }
        .invoice-info td {
            padding: 5px 0;
        }
        .invoice-info td:first-child {
            width: 150px;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .items-table td:last-child,
        .items-table th:last-child {
            text-align: right;
        }
        .totals {
            margin-top: 20px;
            text-align: right;
        }
        .totals table {
            width: 300px;
            margin-left: auto;
        }
        .totals td {
            padding: 5px 10px;
        }
        .totals td:first-child {
            text-align: left;
            font-weight: bold;
        }
        .totals td:last-child {
            text-align: right;
        }
        .total-row {
            font-weight: bold;
            font-size: 14px;
            border-top: 2px solid #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoice #{{ $reference }}</h1>
    </div>

    <div class="invoice-info">
        <table>
            <tr>
                <td>Customer:</td>
                <td>{{ $customerName ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Email:</td>
                <td>{{ $customerEmail ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td>Date:</td>
                <td>{{ $order->created_at->format('d M Y') }}</td>
            </tr>
            @if($paymentMethod)
            <tr>
                <td>Payment Method:</td>
                <td>{{ $paymentMethod }}</td>
            </tr>
            @endif
        </table>
    </div>

    <table class="items-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Quantity</th>
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
                <td>Tax:</td>
                <td>{{ number_format($tax, 2) }} {{ $order->currency ?? 'AED' }}</td>
            </tr>
            @endif
            <tr class="total-row">
                <td>Total:</td>
                <td>{{ number_format($total, 2) }} {{ $order->currency ?? 'AED' }}</td>
            </tr>
        </table>
    </div>
</body>
</html>
