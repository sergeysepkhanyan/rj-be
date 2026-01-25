<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Order Confirmed</title>
</head>
<body style="font-family: Arial, sans-serif; padding: 20px; background-color: #f5f5f5;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 30px; border-radius: 8px;">
        <h1 style="color: #333;">Order Confirmed!</h1>
        <p>Thank you for your order.</p>
        <p><strong>Order #:</strong> {{ $order['reference'] ?? $order['id'] ?? 'N/A' }}</p>
        <p><strong>Amount:</strong> {{ $order['amount'] ?? '0' }} {{ $order['currency'] ?? 'AED' }}</p>
        <p><strong>Date:</strong> {{ $order['createdAt'] ?? 'N/A' }}</p>
        <hr style="margin: 20px 0; border: none; border-top: 1px solid #eee;">
        <p style="color: #666; font-size: 12px;">This is a test email template.</p>
    </div>
</body>
</html>
