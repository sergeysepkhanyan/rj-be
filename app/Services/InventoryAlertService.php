<?php

namespace App\Services;

use App\Mail\InventoryAlertMail;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class InventoryAlertService
{
    /**
     * Check inventory and send alerts to admin if needed.
     */
    public function checkAndSendAlerts(): array
    {
        $lowStockProducts = $this->getLowStockProducts();
        $expiringSoonProducts = $this->getExpiringSoonProducts();
        $expiredProducts = $this->getExpiredProducts();

        $totalAlerts = count($lowStockProducts) + count($expiringSoonProducts) + count($expiredProducts);

        if ($totalAlerts === 0) {
            return [
                'sent' => false,
                'message' => 'No inventory alerts to send',
                'counts' => [
                    'lowStock' => 0,
                    'expiringSoon' => 0,
                    'expired' => 0,
                ],
            ];
        }

        // Get Super Admin email
        $superAdmin = User::whereHas('role', fn($q) => $q->where('name', 'Super Admin'))->first();
        if (!$superAdmin || !$superAdmin->email) {
            return [
                'sent' => false,
                'message' => 'No Super Admin email found',
                'counts' => [
                    'lowStock' => count($lowStockProducts),
                    'expiringSoon' => count($expiringSoonProducts),
                    'expired' => count($expiredProducts),
                ],
            ];
        }

        Mail::to($superAdmin->email)->queue(new InventoryAlertMail(
            $lowStockProducts,
            $expiringSoonProducts,
            $expiredProducts
        ));

        return [
            'sent' => true,
            'message' => "Inventory alert sent to {$superAdmin->email}",
            'counts' => [
                'lowStock' => count($lowStockProducts),
                'expiringSoon' => count($expiringSoonProducts),
                'expired' => count($expiredProducts),
            ],
        ];
    }

    /**
     * Get products that are low on stock.
     */
    public function getLowStockProducts(): array
    {
        return Product::where('max_quantity', '>', 0)
            ->whereColumn('max_quantity', '<=', 'reorder_point')
            ->where('status', 'active')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'skuId' => $product->sku_id,
                    'currentQuantity' => (int) $product->max_quantity,
                    'reorderPoint' => (int) $product->reorder_point,
                ];
            })
            ->all();
    }

    /**
     * Get products that are expiring soon (within 30 days).
     */
    public function getExpiringSoonProducts(int $days = 30): array
    {
        return Product::whereNotNull('expiry_date')
            ->where('expiry_date', '>', now())
            ->where('expiry_date', '<=', now()->addDays($days))
            ->where('status', 'active')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'skuId' => $product->sku_id,
                    'expiryDate' => $product->expiry_date->format('d M Y'),
                    'daysUntilExpiry' => (int) now()->diffInDays($product->expiry_date),
                ];
            })
            ->all();
    }

    /**
     * Get products that have expired.
     */
    public function getExpiredProducts(): array
    {
        return Product::whereNotNull('expiry_date')
            ->where('expiry_date', '<', now())
            ->where('status', 'active')
            ->get()
            ->map(function ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'skuId' => $product->sku_id,
                    'expiryDate' => $product->expiry_date->format('d M Y'),
                ];
            })
            ->all();
    }
}
