<?php

namespace App\Services;

use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportService
{
    public function __construct(protected ProductService $productService) {}

    public function streamInventoryCsv(?array $ids = null): StreamedResponse
    {
        $products = $this->productService->getProductsForExport($ids);

        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="products_inventory.csv"',
        ];

        $callback = function () use ($products) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Product Name',
                'SKU/ID',
                'Category',
                'Price',
                'Quantity',
                'Availability',
                'Date',
                'Status',
            ]);

            foreach ($products as $product) {
                $quantity = (int) ($product->max_quantity ?? 0);
                $availability = $quantity > 0 ? 'On Stock' : 'Out';
                $price = $this->formatPrice($product->price, $product->currency);
                $date = $product->created_at?->format('d, M Y');
                $categoryName = $product->productCategory?->name ?? '';

                fputcsv($out, [
                    $product->name ?? '',
                    $product->sku_id ?? '',
                    $categoryName,
                    $price,
                    $quantity,
                    $availability,
                    $date,
                    $product->status ?? '',
                ]);
            }

            fclose($out);
        };

        return response()->streamDownload($callback, 'products_inventory.csv', $headers);
    }

    private function formatPrice($amount, ?string $currency): string
    {
        if ($amount === null) {
            return '';
        }

        $formatted = number_format((float) $amount, 2, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        $curr = $currency ?: 'AED';

        return "{$formatted} {$curr}";
    }
}
