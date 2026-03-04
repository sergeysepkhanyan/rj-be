<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportService
{
    public function __construct(protected ProductService $productService) {}

    public function streamInventoryCsv(?array $ids = null): StreamedResponse
    {
        $products = $this->productService->getProductsForExport($ids);

        $filename = 'full-inventory-' . date('Y-m-d') . '.xlsx';

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ];

        $callback = function () use ($products) {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Header row
            $headers = [
                'A1' => 'Product Name',
                'B1' => 'SKU/ID',
                'C1' => 'Category',
                'D1' => 'Supplier',
                'E1' => 'Price',
                'F1' => 'Cost Price',
                'G1' => 'Quantity',
                'H1' => 'Reorder Point',
                'I1' => 'Availability',
                'J1' => 'Unit of Sale',
                'K1' => 'Sales Channel',
                'L1' => 'Product Type',
                'M1' => 'Production Date',
                'N1' => 'Expiry Date',
                'O1' => 'Created Date',
                'P1' => 'Status',
            ];

            foreach ($headers as $cell => $value) {
                $sheet->setCellValue($cell, $value);
            }

            $row = 2;
            foreach ($products as $product) {
                $quantity = (int) ($product->max_quantity ?? 0);
                $reorderPoint = (int) ($product->reorder_point ?? 0);
                $availability = $quantity > 0 ? 'On Stock' : 'Out';
                $price = $this->formatPrice($product->price, $product->currency);
                $costPrice = $this->formatPrice($product->cost_price, $product->currency);
                $createdDate = $product->created_at?->format('d, M Y');
                $productionDate = $product->production_date?->format('d, M Y') ?? '';
                $expiryDate = $product->expiry_date?->format('d, M Y') ?? '';
                $categoryName = $product->productCategory?->name ?? '';
                $supplierName = $product->supplier?->name ?? '';

                $sheet->setCellValue('A' . $row, $product->name ?? '');
                $sheet->setCellValue('B' . $row, $product->sku_id ?? '');
                $sheet->setCellValue('C' . $row, $categoryName);
                $sheet->setCellValue('D' . $row, $supplierName);
                $sheet->setCellValue('E' . $row, $price);
                $sheet->setCellValue('F' . $row, $costPrice);
                $sheet->setCellValue('G' . $row, $quantity);
                $sheet->setCellValue('H' . $row, $reorderPoint);
                $sheet->setCellValue('I' . $row, $availability);
                $sheet->setCellValue('J' . $row, $product->unit_of_sale ?? '');
                $sheet->setCellValue('K' . $row, $product->sales_channel ?? '');
                $sheet->setCellValue('L' . $row, $product->product_type ?? '');
                $sheet->setCellValue('M' . $row, $productionDate);
                $sheet->setCellValue('N' . $row, $expiryDate);
                $sheet->setCellValue('O' . $row, $createdDate);
                $sheet->setCellValue('P' . $row, $product->status ?? '');

                $row++;
            }

            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        };

        return response()->streamDownload($callback, $filename, $headers);
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
