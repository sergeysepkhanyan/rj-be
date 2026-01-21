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

            $sheet->setCellValue('A1', 'Product Name');
            $sheet->setCellValue('B1', 'SKU/ID');
            $sheet->setCellValue('C1', 'Category');
            $sheet->setCellValue('D1', 'Price');
            $sheet->setCellValue('E1', 'Quantity');
            $sheet->setCellValue('F1', 'Availability');
            $sheet->setCellValue('G1', 'Date');
            $sheet->setCellValue('H1', 'Status');

            $row = 2;
            foreach ($products as $product) {
                $quantity = (int) ($product->max_quantity ?? 0);
                $availability = $quantity > 0 ? 'On Stock' : 'Out';
                $price = $this->formatPrice($product->price, $product->currency);
                $date = $product->created_at?->format('d, M Y');
                $categoryName = $product->productCategory?->name ?? '';

                $sheet->setCellValue('A' . $row, $product->name ?? '');
                $sheet->setCellValue('B' . $row, $product->sku_id ?? '');
                $sheet->setCellValue('C' . $row, $categoryName);
                $sheet->setCellValue('D' . $row, $price);
                $sheet->setCellValue('E' . $row, $quantity);
                $sheet->setCellValue('F' . $row, $availability);
                $sheet->setCellValue('G' . $row, $date);
                $sheet->setCellValue('H' . $row, $product->status ?? '');

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
