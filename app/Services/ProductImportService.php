<?php

namespace App\Services;

use App\Models\Product;
use App\Services\ProductCategoryService;
use App\Services\ProductService;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportService
{
    public function __construct(
        protected ProductService $productService,
        protected ProductCategoryService $productCategoryService
    ) {}

    public function import(UploadedFile $file, bool $dryRun = false): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getActiveSheet();
        $rows = $sheet->toArray(null, true, true, true);

        if (!$rows || count($rows) < 2) {
            return [
                'created' => 0,
                'failed' => 0,
                'errors' => [
                    ['row' => 1, 'message' => 'File is empty or missing headers.'],
                ],
            ];
        }

        $headerRow = array_shift($rows);
        $headerMap = $this->buildHeaderMap($headerRow);

        if (empty($headerMap)) {
            return [
                'created' => 0,
                'failed' => 0,
                'errors' => [
                    ['row' => 1, 'message' => 'Unable to detect headers.'],
                ],
            ];
        }

        $created = 0;
        $errors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $payload = $this->extractRow($row, $headerMap);

            $validation = $this->validateRow($payload);
            if ($validation['error']) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => $validation['error'],
                ];
                continue;
            }

            [$amount, $currency] = $this->parsePrice($payload['price']);
            $quantity = $this->parseQuantity($payload['quantity'], $payload['availability']);
            $status = $this->normalizeStatus($payload['status']);

            if ($amount === null) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'Invalid price format.',
                ];
                continue;
            }

            if (Product::query()->where('sku_id', $payload['sku'])->exists()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'message' => 'SKU/ID already exists.',
                ];
                continue;
            }

            $category = $this->productCategoryService->firstOrCreateByName($payload['category']);

            if ($dryRun) {
                $created++;
                continue;
            }

            $this->productService->createProduct([
                'name' => $payload['name'],
                'name_ar' => $payload['name'],
                'description' => null,
                'description_ar' => null,
                'sku_id' => $payload['sku'],
                'product_category_id' => $category->id,
                'max_quantity' => $quantity,
                'price' => $amount,
                'currency' => $currency,
                'status' => $status,
            ], [], []);

            $created++;
        }

        return [
            'created' => $created,
            'failed' => count($errors),
            'errors' => $errors,
        ];
    }

    protected function buildHeaderMap(array $headerRow): array
    {
        $map = [];
        foreach ($headerRow as $col => $value) {
            $key = $this->normalizeHeader($value);
            if ($key) {
                $map[$col] = $key;
            }
        }
        return $map;
    }

    protected function normalizeHeader(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $normalized = strtolower(preg_replace('/[^a-z0-9]+/', '', $value));
        return match ($normalized) {
            'name' => 'name',
            'skuid', 'sku', 'skuid' => 'sku',
            'category', 'productcategory' => 'category',
            'price' => 'price',
            'quantity', 'qty' => 'quantity',
            'availability', 'stock' => 'availability',
            'status' => 'status',
            default => null,
        };
    }

    protected function extractRow(array $row, array $headerMap): array
    {
        $data = [
            'name' => null,
            'sku' => null,
            'category' => null,
            'price' => null,
            'quantity' => null,
            'availability' => null,
            'status' => null,
        ];

        foreach ($headerMap as $col => $key) {
            $data[$key] = $row[$col] ?? null;
        }

        return array_map(function ($value) {
            return is_string($value) ? trim($value) : $value;
        }, $data);
    }

    protected function validateRow(array $payload): array
    {
        if (empty($payload['name'])) {
            return ['error' => 'Name is required.'];
        }
        if (empty($payload['sku'])) {
            return ['error' => 'SKU/ID is required.'];
        }
        if (empty($payload['category'])) {
            return ['error' => 'Category is required.'];
        }
        if (empty($payload['price'])) {
            return ['error' => 'Price is required.'];
        }
        return ['error' => null];
    }

    protected function parsePrice(mixed $value): array
    {
        if (is_numeric($value)) {
            return [(float) $value, 'AED'];
        }

        if (!is_string($value)) {
            return [null, 'AED'];
        }

        $amount = null;
        if (preg_match('/([0-9]+(?:[\\.,][0-9]+)?)/', $value, $m)) {
            $amount = (float) str_replace(',', '.', $m[1]);
        }

        $currency = 'AED';
        if (preg_match('/\\b([A-Za-z]{3})\\b/', $value, $m)) {
            $currency = strtoupper($m[1]);
        }

        return [$amount, $currency];
    }

    protected function parseQuantity(mixed $quantity, mixed $availability): int
    {
        if (is_numeric($quantity)) {
            return max(0, (int) $quantity);
        }

        $availabilityStr = strtolower((string) $availability);
        if (str_contains($availabilityStr, 'out')) {
            return 0;
        }
        if (str_contains($availabilityStr, 'on')) {
            return 1;
        }

        return 0;
    }

    protected function normalizeStatus(mixed $status): string
    {
        $value = strtolower(trim((string) $status));
        return match (true) {
            $value === 'publish' || $value === 'published' => 'publish',
            $value === 'active' => 'active',
            default => 'draft',
        };
    }
}
