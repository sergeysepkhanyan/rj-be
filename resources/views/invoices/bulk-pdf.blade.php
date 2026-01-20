<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Orders Invoice Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10px;
            margin: 20px;
        }
        .invoice {
            page-break-after: always;
            margin-bottom: 30px;
        }
        .invoice:last-child {
            page-break-after: avoid;
        }
        .header h1 {
            margin: 0;
            font-size: 18px;
        }
        .invoice-info {
            margin-bottom: 15px;
        }
        .invoice-info table {
            width: 100%;
        }
        .invoice-info td {
            padding: 3px 0;
        }
        .invoice-info td:first-child {
            width: 100px;
            font-weight: bold;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #ddd;
            padding: 5px;
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
            margin-top: 15px;
            text-align: right;
        }
        .totals table {
            width: 250px;
            margin-left: auto;
        }
        .totals td {
            padding: 3px 8px;
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
            border-top: 2px solid #000;
        }
    </style>
</head>
<body>
    @foreach($orders as $invoice)
    <div class="invoice">
        <div class="header">
            <h1>Invoice #{{ $invoice['reference'] }}</h1>
        </div>

        <div class="invoice-info">
            <table>
                <tr>
                    <td>Customer:</td>
                    <td>{{ $invoice['customerName'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Email:</td>
                    <td>{{ $invoice['customerEmail'] ?? 'N/A' }}</td>
                </tr>
                <tr>
                    <td>Date:</td>
                    <td>{{ $invoice['order']->created_at->format('d M Y') }}</td>
                </tr>
                @if($invoice['paymentMethod'])
                <tr>
                    <td>Payment Method:</td>
                    <td>{{ $invoice['paymentMethod'] }}</td>
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
                @foreach($invoice['items'] as $item)
                <tr>
                    <td>{{ $item['name'] }}</td>
                    <td>{{ $item['quantity'] }}</td>
                    <td>{{ number_format($item['unitPrice'], 2) }} {{ $invoice['order']->currency ?? 'AED' }}</td>
                    <td>{{ number_format($item['subtotal'], 2) }} {{ $invoice['order']->currency ?? 'AED' }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <table>
                <tr>
                    <td>Subtotal:</td>
                    <td>{{ number_format($invoice['subtotal'], 2) }} {{ $invoice['order']->currency ?? 'AED' }}</td>
                </tr>
                @if($invoice['tax'] > 0)
                <tr>
                    <td>Tax:</td>
                    <td>{{ number_format($invoice['tax'], 2) }} {{ $invoice['order']->currency ?? 'AED' }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>Total:</td>
                    <td>{{ number_format($invoice['total'], 2) }} {{ $invoice['order']->currency ?? 'AED' }}</td>
                </tr>
            </table>
        </div>
    </div>
    @endforeach
</body>
</html>
