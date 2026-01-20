<?php

namespace App\Services;

use App\Filters\OrderFilter;
use App\Models\Booking;
use App\Models\Order;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderExportService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepository
    ) {}

    public function downloadInvoicePdf(Order $order)
    {
        $order->load([
            'user',
            'items.product.files',
            'shippingAddress',
            'billingAddress',
            'latestPayment.paymentMethod',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        $data = $this->prepareInvoiceData($order);

        $pdf = DomPdf::loadView('invoices.pdf', $data);
        
        $filename = 'invoice-' . ($order->reference ?? $order->id) . '.pdf';
        
        return $pdf->download($filename);
    }

    public function downloadInvoiceXlsx(Order $order): StreamedResponse
    {
        $order->load([
            'user',
            'items.product.files',
            'shippingAddress',
            'billingAddress',
            'latestPayment.paymentMethod',
        ]);

        if ($order->type === 'booking' && $order->orderable) {
            $order->load('orderable.services.bookable');
        }

        $data = $this->prepareInvoiceData($order);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Invoice #' . ($order->reference ?? $order->id));
        $sheet->setCellValue('A3', 'Customer:');
        $sheet->setCellValue('B3', $data['customerName'] ?? 'N/A');
        $sheet->setCellValue('A4', 'Email:');
        $sheet->setCellValue('B4', $data['customerEmail'] ?? 'N/A');
        $sheet->setCellValue('A5', 'Date:');
        $sheet->setCellValue('B5', $order->created_at->format('d M Y'));

        $row = 7;
        $sheet->setCellValue("A{$row}", 'Item');
        $sheet->setCellValue("B{$row}", 'Quantity');
        $sheet->setCellValue("C{$row}", 'Unit Price');
        $sheet->setCellValue("D{$row}", 'Total');

        $row = 8;
        foreach ($data['items'] as $item) {
            $sheet->setCellValue("A{$row}", $item['name']);
            $sheet->setCellValue("B{$row}", $item['quantity']);
            $sheet->setCellValue("C{$row}", $item['unitPrice']);
            $sheet->setCellValue("D{$row}", $item['subtotal']);
            $row++;
        }

        $row++;
        $sheet->setCellValue("C{$row}", 'Subtotal:');
        $sheet->setCellValue("D{$row}", $data['subtotal']);
        $row++;
        $sheet->setCellValue("C{$row}", 'Tax:');
        $sheet->setCellValue("D{$row}", $data['tax']);
        $row++;
        $sheet->setCellValue("C{$row}", 'Total:');
        $sheet->setCellValue("D{$row}", $data['total']);

        foreach (range('A', 'D') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'invoice-' . ($order->reference ?? $order->id) . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function exportOrdersPdf(?OrderFilter $filter = null, ?array $ids = null)
    {
        $query = Order::query();

        if ($ids && is_array($ids) && count($ids) > 0) {
            $query = $query->whereIn('id', $ids);
        } elseif ($filter) {
            $query = $filter->apply($query);
        }

        $orders = $query->with([
            'user',
            'items.product',
            'shippingAddress',
            'latestPayment.paymentMethod',
        ])->orderByDesc('created_at')->get();

        $data = [
            'orders' => $orders->map(fn($order) => $this->prepareInvoiceData($order)),
        ];

        $pdf = DomPdf::loadView('invoices.bulk-pdf', $data);
        
        $filename = 'orders-invoice-' . now()->format('Y-m-d') . '.pdf';
        
        return $pdf->download($filename);
    }

    public function exportOrdersXlsx(?OrderFilter $filter = null, ?array $ids = null): StreamedResponse
    {
        $query = Order::query();

        if ($ids && is_array($ids) && count($ids) > 0) {
            $query = $query->whereIn('id', $ids);
        } elseif ($filter) {
            $query = $filter->apply($query);
        }

        $orders = $query->with([
            'user',
            'items.product',
            'shippingAddress',
            'latestPayment.paymentMethod',
        ])->orderByDesc('created_at')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->setCellValue('A1', 'Payment ID');
        $sheet->setCellValue('B1', 'Service/Order');
        $sheet->setCellValue('C1', 'Payment Method');
        $sheet->setCellValue('D1', 'Amount');
        $sheet->setCellValue('E1', 'Date');

        $row = 2;
        foreach ($orders as $order) {
            $paymentId = $order->latestPayment?->external_id ?? $order->reference ?? "#{$order->id}";
            
            $productName = 'N/A';
            if ($order->type === 'ecommerce' && $order->items->isNotEmpty()) {
                $productName = $order->items->first()->product?->name ?? 'N/A';
            } elseif ($order->type === 'booking' && $order->orderable instanceof Booking) {
                $booking = $order->orderable;
                if ($booking->services->isNotEmpty()) {
                    $productName = $booking->services->first()->bookable?->name ?? 'N/A';
                }
            }

            $paymentMethod = 'N/A';
            if ($order->latestPayment?->paymentMethod) {
                $pm = $order->latestPayment->paymentMethod;
                $paymentMethod = ($pm->brand ? ucfirst($pm->brand) : 'Card') . ' ...' . $pm->last4;
            } elseif ($order->latestPayment?->provider === 'stripe') {
                $paymentMethod = 'Card';
            }

            $sheet->setCellValue("A{$row}", $paymentId);
            $sheet->setCellValue("B{$row}", $productName);
            $sheet->setCellValue("C{$row}", $paymentMethod);
            $sheet->setCellValue("D{$row}", $order->amount . ' ' . ($order->currency ?? 'AED'));
            $sheet->setCellValue("E{$row}", $order->created_at->format('d. M Y'));
            $row++;
        }

        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $filename = 'orders-export-' . now()->format('Y-m-d') . '.xlsx';

        $writer = new Xlsx($spreadsheet);

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function prepareInvoiceData(Order $order): array
    {
        $customerName = null;
        $customerEmail = null;
        
        if ($order->user) {
            $firstName = $order->user->name ?? '';
            $lastName = $order->user->last_name ?? '';
            $customerName = trim("{$firstName} {$lastName}");
            $customerEmail = $order->user->email;
        } else {
            $customerName = $order->meta['customer_name'] ?? null;
            $customerEmail = $order->meta['customer_email'] ?? null;
        }

        $items = [];
        $subtotal = 0;
        $tax = 0;

        if ($order->type === 'ecommerce' && $order->items->isNotEmpty()) {
            foreach ($order->items as $item) {
                $unitPrice = (float) $item->unit_price;
                $quantity = (int) $item->quantity;
                $itemSubtotal = $unitPrice * $quantity;
                
                $items[] = [
                    'name' => $item->product?->name ?? 'N/A',
                    'quantity' => $quantity,
                    'unitPrice' => $unitPrice,
                    'subtotal' => $itemSubtotal,
                ];
                
                $subtotal += $itemSubtotal;
            }
        } elseif ($order->type === 'booking' && $order->orderable instanceof Booking) {
            $booking = $order->orderable;
            if ($booking->services->isNotEmpty()) {
                foreach ($booking->services as $service) {
                    $price = (float) ($service->final_price ?? $service->price ?? 0);
                    
                    $items[] = [
                        'name' => $service->bookable?->name ?? 'N/A',
                        'quantity' => 1,
                        'unitPrice' => $price,
                        'subtotal' => $price,
                    ];
                    
                    $subtotal += $price;
                }
            }
        }

        $tax = $order->amount - $subtotal;
        $total = $order->amount;

        $paymentMethod = null;
        if ($order->latestPayment?->paymentMethod) {
            $pm = $order->latestPayment->paymentMethod;
            $paymentMethod = ($pm->brand ? ucfirst($pm->brand) : 'Card') . ' ...' . $pm->last4;
        } elseif ($order->latestPayment?->provider === 'stripe') {
            $paymentMethod = 'Card';
        }

        return [
            'order' => $order,
            'customerName' => $customerName,
            'customerEmail' => $customerEmail,
            'items' => $items,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total,
            'paymentMethod' => $paymentMethod,
            'reference' => $order->reference ?? "#{$order->id}",
        ];
    }
}
