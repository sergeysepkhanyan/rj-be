Order Confirmed!

Thank you for your order.

Order #: {{ $order['reference'] ?? $order['id'] ?? 'N/A' }}
Amount: {{ $order['amount'] ?? '0' }} {{ $order['currency'] ?? 'AED' }}
Date: {{ $order['createdAt'] ?? 'N/A' }}

This is a test email template.
