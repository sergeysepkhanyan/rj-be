INVENTORY ALERT
===============

{{ $totalAlerts }} product(s) need your attention

@if(count($expiredProducts) > 0)
EXPIRED PRODUCTS ({{ count($expiredProducts) }})
-------------------------------------------------
@foreach($expiredProducts as $product)
- {{ $product['name'] }}
  SKU: {{ $product['skuId'] ?? 'N/A' }}
  Expired: {{ $product['expiryDate'] }}

@endforeach
@endif

@if(count($expiringSoonProducts) > 0)
EXPIRING SOON ({{ count($expiringSoonProducts) }})
--------------------------------------------------
@foreach($expiringSoonProducts as $product)
- {{ $product['name'] }}
  SKU: {{ $product['skuId'] ?? 'N/A' }}
  Expires: {{ $product['expiryDate'] }} ({{ $product['daysUntilExpiry'] }} days remaining)

@endforeach
@endif

@if(count($lowStockProducts) > 0)
LOW STOCK ({{ count($lowStockProducts) }})
------------------------------------------
@foreach($lowStockProducts as $product)
- {{ $product['name'] }}
  SKU: {{ $product['skuId'] ?? 'N/A' }}
  Current Stock: {{ $product['currentQuantity'] }}
  Reorder Point: {{ $product['reorderPoint'] }}

@endforeach
@endif

Please review these items and take appropriate action to maintain your inventory.

This is an automated inventory alert.
Generated on {{ now()->format('d M Y, H:i') }}
